<?php
/**
 * Backfill AI comparison jobs for historical return transactions
 * Run once after enabling Phase 3 AI comparison so past records receive AI analysis
 */

// Require explicit confirmation to avoid accidental execution
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo '<!DOCTYPE html><html><head><title>Backfill AI Comparisons</title></head><body style="font-family: Arial, sans-serif; padding: 40px; max-width: 640px; margin: 0 auto;">';
    echo '<h2>🤖 Backfill AI Comparison Jobs</h2>';
    echo '<p>This tool will queue AI comparison jobs for past <strong>Returned</strong> transactions that do not yet have AI analysis.</p>';
    echo '<ul>';
    echo '<li>Existing offline hybrid scores remain untouched.</li>';
    echo '<li>Large items stay on manual review.</li>';
    echo '<li>Transactions missing photos or already processed are skipped.</li>';
    echo '</ul>';
    echo '<p><strong>Note:</strong> After this runs, execute <code>php admin/ai_worker_stub.php</code> (or your real AI worker) to process the queued jobs.</p>';
    echo '<p><a href="?confirm=yes" style="display:inline-block;padding:12px 24px;background:#1976d2;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;">Queue AI Backfill Jobs</a></p>';
    echo '<p><a href="admin-all-transaction.php" style="color:#666;">Cancel and return to dashboard</a></p>';
    echo '</body></html>';
    exit;
}

require_once __DIR__ . '/../config/database.php';

set_time_limit(300);
ini_set('memory_limit', '512M');

$rootPath = realpath(__DIR__ . '/..');
$queued = 0;
$skipped = 0;
$errors = 0;

echo '<pre style="font-family:monospace;background:#f5f5f5;padding:20px;border-radius:8px;">';
echo "=== AI Comparison Backfill ===\n";
echo 'Started at: ' . date('Y-m-d H:i:s') . "\n\n";

$sql = "SELECT t.*, e.name AS equipment_name, e.image_path, i.item_size
        FROM transactions t
        LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
        LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id
        WHERE (t.status = 'Returned' OR t.transaction_type = 'Return')
        ORDER BY t.id DESC";

$result = $conn->query($sql);
if (!$result) {
    echo 'Query failed: ' . $conn->error . "\n";
    exit;
}

while ($row = $result->fetch_assoc()) {
    $txnId = (int)$row['id'];
    $equipmentName = $row['equipment_name'] ?? 'Unknown equipment';
    $sizeCategory = strtolower($row['item_size'] ?? 'medium');

    echo "Transaction #{$txnId} ({$equipmentName}) -> ";

    $currentStatus = strtolower($row['ai_analysis_status'] ?? '');
    if (in_array($currentStatus, ['pending', 'processing', 'completed'], true)) {
        echo "SKIPPED (AI status: {$row['ai_analysis_status']})\n";
        $skipped++;
        continue;
    }

    // Large items remain manual-review; just clear AI fields
    if ($sizeCategory === 'large') {
        $clearStmt = $conn->prepare("UPDATE transactions SET ai_analysis_status = NULL, ai_analysis_message = NULL, ai_similarity_score = NULL, ai_severity_level = NULL WHERE id = ?");
        if ($clearStmt) {
            $clearStmt->bind_param('i', $txnId);
            $clearStmt->execute();
            $clearStmt->close();
        }
        echo "SKIPPED (large item manual review)\n";
        $skipped++;
        continue;
    }

    // Fetch latest return photo
    $returnPhotoStmt = $conn->prepare("SELECT file_path FROM transaction_photos WHERE transaction_id = ? AND photo_type = 'return' ORDER BY created_at DESC LIMIT 1");
    $returnPhotoStmt->bind_param('i', $txnId);
    $returnPhotoStmt->execute();
    $returnPhotoResult = $returnPhotoStmt->get_result();
    $returnPhotoRow = $returnPhotoResult ? $returnPhotoResult->fetch_assoc() : null;
    $returnPhotoStmt->close();

    if (!$returnPhotoRow) {
        echo "SKIPPED (no return photo)\n";
        $skipped++;
        continue;
    }

    $returnPhotoRel = $returnPhotoRow['file_path'];
    $returnPhotoAbs = $rootPath . DIRECTORY_SEPARATOR . ltrim($returnPhotoRel, '/\\');
    if (!file_exists($returnPhotoAbs)) {
        echo "SKIPPED (return photo missing on disk)\n";
        $skipped++;
        continue;
    }

    // Determine reference photo
    $referenceBase = null;
    if (!empty($row['image_path'])) {
        $referenceBase = $row['image_path'];
    }

    if (!$referenceBase) {
        echo "SKIPPED (no reference photo)\n";
        $skipped++;
        continue;
    }

    $referenceAbs = null;
    $candidates = [
        $rootPath . DIRECTORY_SEPARATOR . ltrim($referenceBase, '/\\'),
        $rootPath . DIRECTORY_SEPARATOR . $referenceBase,
    ];
    if (strpos($referenceBase, '../') === 0) {
        $candidates[] = $rootPath . DIRECTORY_SEPARATOR . ltrim(substr($referenceBase, 3), '/\\');
    }
    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            $referenceAbs = $candidate;
            break;
        }
    }

    if (!$referenceAbs) {
        echo "SKIPPED (reference not found)\n";
        $skipped++;
        continue;
    }

    // Check if a pending/processing job already exists
    $jobCheck = $conn->prepare("SELECT job_id FROM ai_comparison_jobs WHERE transaction_id = ? AND status IN ('pending','processing') LIMIT 1");
    $jobCheck->bind_param('i', $txnId);
    $jobCheck->execute();
    $jobExists = $jobCheck->get_result()->num_rows > 0;
    $jobCheck->close();

    if ($jobExists) {
        echo "SKIPPED (job already queued)\n";
        $skipped++;
        continue;
    }

    $payload = json_encode([
        'transaction_id' => $txnId,
        'reference_path' => $referenceAbs,
        'return_path' => $returnPhotoAbs,
        'item_size' => $sizeCategory,
        'offline_similarity' => $row['similarity_score'],
        'offline_severity' => $row['severity_level'],
    ]);

    $insertJob = $conn->prepare("INSERT INTO ai_comparison_jobs (transaction_id, status, priority, payload, created_at) VALUES (?, 'pending', 3, ?, NOW())");
    if (!$insertJob) {
        echo "ERROR (job insert failed: " . $conn->error . ")\n";
        $errors++;
        continue;
    }

    $insertJob->bind_param('is', $txnId, $payload);
    if (!$insertJob->execute()) {
        echo "ERROR (" . $insertJob->error . ")\n";
        $errors++;
        $insertJob->close();
        continue;
    }
    $insertJob->close();

    $updateTxn = $conn->prepare("UPDATE transactions SET 
        ai_analysis_status = 'pending',
        ai_analysis_message = 'Analyzing Equipment for damages',
        ai_similarity_score = NULL,
        ai_severity_level = NULL,
        ai_analysis_meta = NULL
    WHERE id = ?");
    if ($updateTxn) {
        $updateTxn->bind_param('i', $txnId);
        $updateTxn->execute();
        $updateTxn->close();
    }

    echo "QUEUED\n";
    $queued++;
}

$result->free();
$conn->close();

echo "\n=== Summary ===\n";
echo 'Queued jobs: ' . $queued . "\n";
echo 'Skipped: ' . $skipped . "\n";
echo 'Errors: ' . $errors . "\n";
echo 'Completed at: ' . date('Y-m-d H:i:s') . "\n";
echo '</pre>';
