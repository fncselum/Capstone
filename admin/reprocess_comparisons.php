<?php
/**
 * Reprocess image comparisons for existing return transactions
 * Run this once to update all past transactions with the new hybrid comparison logic
 */

// Simple security: require a confirmation parameter
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo '<!DOCTYPE html><html><head><title>Reprocess Comparisons</title></head><body style="font-family: Arial, sans-serif; padding: 40px; max-width: 600px; margin: 0 auto;">';
    echo '<h2>⚠️ Reprocess Image Comparisons</h2>';
    echo '<p>This will reprocess all existing return transactions with the new hybrid comparison logic.</p>';
    echo '<p><strong>This may take several minutes depending on the number of transactions.</strong></p>';
    echo '<p><a href="?confirm=yes" style="display: inline-block; padding: 12px 24px; background: #4caf50; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">Start Reprocessing</a></p>';
    echo '<p><a href="admin-all-transaction.php" style="color: #666;">Cancel and go back</a></p>';
    echo '</body></html>';
    exit;
}

require_once __DIR__ . '/../config/database.php';

set_time_limit(300); // 5 minutes max
ini_set('memory_limit', '512M');

$rootPath = realpath(__DIR__ . '/..');
$processed = 0;
$skipped = 0;
$errors = 0;
$log = [];

// Fetch all returned transactions
$query = "SELECT t.*, e.name as equipment_name, e.image_path, i.item_size
          FROM transactions t
          LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
          LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id
          WHERE t.status = 'Returned' OR t.transaction_type = 'Return'
          ORDER BY t.id DESC";

$result = $conn->query($query);

if (!$result) {
    die('Query failed: ' . $conn->error);
}

echo '<pre style="font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 8px;">';
echo "=== Image Comparison Reprocessing ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

while ($row = $result->fetch_assoc()) {
    $txnId = $row['id'];
    $equipmentName = $row['equipment_name'] ?? 'Unknown';
    $sizeCategory = strtolower($row['item_size'] ?? 'medium');
    
    echo "Processing Transaction #{$txnId} ({$equipmentName})... ";
    
    // Get return photo
    $photoQuery = $conn->prepare("SELECT file_path FROM transaction_photos WHERE transaction_id = ? AND photo_type = 'return' ORDER BY created_at DESC LIMIT 1");
    $photoQuery->bind_param('i', $txnId);
    $photoQuery->execute();
    $photoResult = $photoQuery->get_result();
    $returnPhotoRow = $photoResult->fetch_assoc();
    $photoQuery->close();
    
    if (!$returnPhotoRow) {
        echo "SKIPPED (no return photo)\n";
        $skipped++;
        continue;
    }
    
    $returnPhotoRel = $returnPhotoRow['file_path'];
    $returnPhotoAbs = $rootPath . DIRECTORY_SEPARATOR . ltrim($returnPhotoRel, '/\\');
    
    if (!file_exists($returnPhotoAbs)) {
        echo "SKIPPED (return photo not found)\n";
        $skipped++;
        continue;
    }
    
    // Get reference photo
    $referenceBase = null;
    if ($sizeCategory === 'large') {
        // For large items, use borrow photo
        $borrowQuery = $conn->prepare("SELECT file_path FROM transaction_photos WHERE transaction_id = ? AND photo_type = 'borrow' ORDER BY created_at ASC LIMIT 1");
        $borrowQuery->bind_param('i', $txnId);
        $borrowQuery->execute();
        $borrowResult = $borrowQuery->get_result();
        $borrowRow = $borrowResult->fetch_assoc();
        $borrowQuery->close();
        if ($borrowRow) {
            $referenceBase = $borrowRow['file_path'];
        }
    } else {
        // Use equipment image
        $referenceBase = $row['image_path'] ?? null;
    }
    
    if (!$referenceBase) {
        echo "SKIPPED (no reference photo)\n";
        $skipped++;
        continue;
    }
    
    // Resolve reference path
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
    
    // Skip large items (manual review only)
    if ($sizeCategory === 'large') {
        $update = $conn->prepare("UPDATE transactions SET 
            return_verification_status = 'Pending',
            return_review_status = 'Manual Review Required',
            detected_issues = 'Manual review required (large item).',
            severity_level = 'medium',
            similarity_score = NULL
        WHERE id = ?");
        $update->bind_param('i', $txnId);
        $update->execute();
        $update->close();
        echo "UPDATED (large item - manual review)\n";
        $processed++;
        continue;
    }
    
    // Run comparison
    require_once __DIR__ . '/../includes/image_comparison.php';
    
    $comparisonResults = compareReturnToReference($referenceAbs, $returnPhotoAbs, [
        'item_size' => $sizeCategory,
    ]);
    
    if (empty($comparisonResults['success'])) {
        echo "ERROR (comparison failed)\n";
        $errors++;
        
        $update = $conn->prepare("UPDATE transactions SET 
            return_verification_status = 'Pending',
            return_review_status = 'Review Required',
            detected_issues = 'Comparison failed - manual review required.',
            severity_level = 'high',
            similarity_score = NULL
        WHERE id = ?");
        $update->bind_param('i', $txnId);
        $update->execute();
        $update->close();
        continue;
    }
    
    // Extract results
    $similarity = $comparisonResults['similarity'] ?? 0;
    $ssim = $comparisonResults['ssim_score'] ?? null;
    $phash = $comparisonResults['phash_score'] ?? null;
    $pixel = $comparisonResults['pixel_difference_score'] ?? null;
    $confidence = $comparisonResults['confidence_band'] ?? 'low';
    $detectedIssuesList = $comparisonResults['detected_issues_list'] ?? [];
    if (!empty($detectedIssuesList)) {
        $detectedIssues = implode("\n", array_map(static function ($message) {
            $trimmed = trim((string)$message);
            if ($trimmed === '') {
                return $trimmed;
            }
            $normalized = ucfirst($trimmed);
            if (substr($normalized, -1) !== '.') {
                $normalized .= '.';
            }
            return $normalized;
        }, $detectedIssuesList));
    } else {
        $detectedIssues = $comparisonResults['detected_issues_text'] ?? 'Differences detected';
    }
    $severity = $comparisonResults['severity_level'] ?? 'medium';
    
    // Determine verification status
    $verificationStatus = 'Pending';
    $reviewStatus = 'Pending Review';
    
    if ($similarity >= 70.0) {
        $verificationStatus = 'Verified';
        $reviewStatus = ($sizeCategory === 'small') ? 'Verified' : 'Pending Review';
        $severity = 'none';
        $detectedIssues = "Item returned successfully – no damages detected.";
        $detectedIssuesList[0] = 'Item returned successfully – no damages detected.';
    } elseif ($similarity >= 50.0) {
        $verificationStatus = 'Flagged';
        $reviewStatus = 'Flagged';
        $severity = 'medium';
        $detectedIssues = "Minor visual difference detected – verify manually.";
        $detectedIssuesList[0] = 'Minor visual difference detected – verify manually.';
    } else {
        $verificationStatus = 'Damage';
        $reviewStatus = 'Damage';
        $severity = 'high';
        $detectedIssues = "Item mismatch detected – please check return.";
        $detectedIssuesList[0] = 'Item mismatch detected – please check return.';
    }

    if (!empty($detectedIssuesList)) {
        $detectedIssues = implode("\n", array_map(static function ($message) {
            $trimmed = trim((string)$message);
            if ($trimmed === '') {
                return $trimmed;
            }
            $normalized = ucfirst($trimmed);
            if (substr($normalized, -1) !== '.') {
                $normalized .= '.';
            }
            return $normalized;
        }, $detectedIssuesList));
    }
    
    // Update transaction
    $update = $conn->prepare("UPDATE transactions SET 
        return_verification_status = ?,
        return_review_status = ?,
        similarity_score = ?,
        detected_issues = ?,
        severity_level = ?
    WHERE id = ?");
    $update->bind_param('ssdssi', $verificationStatus, $reviewStatus, $similarity, $detectedIssues, $severity, $txnId);
    $update->execute();
    $update->close();
    
    // Store metadata (if transaction_meta table exists)
    $metaPayload = [
        'version' => '2.1-reprocess',
        'reprocessed_at' => date('Y-m-d H:i:s'),
        'results' => $comparisonResults,
    ];
    $metaStmt = $conn->prepare("INSERT INTO transaction_meta (transaction_id, meta_key, meta_value, created_at)
        VALUES (?, 'image_comparison', ?, NOW())
        ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = NOW()");
    if ($metaStmt) {
        $metaJson = json_encode($metaPayload);
        $metaStmt->bind_param('is', $txnId, $metaJson);
        $metaStmt->execute();
        $metaStmt->close();
    }
    
    // Generate comparison preview
    $comparisonDir = $rootPath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'transaction_photos' . DIRECTORY_SEPARATOR;
    if (!is_dir($comparisonDir)) {
        @mkdir($comparisonDir, 0755, true);
    }
    
    $comparisonFile = $comparisonDir . 'comparison_' . $txnId . '_' . time() . '.jpg';
    if (generateComparisonPreview($referenceAbs, $returnPhotoAbs, $comparisonFile)) {
        $comparisonPath = 'uploads/transaction_photos/' . basename($comparisonFile);
        $photoStmt = $conn->prepare("INSERT INTO transaction_photos (transaction_id, photo_type, file_path, created_at)
            VALUES (?, 'comparison', ?, NOW())
            ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), updated_at = NOW()");
        if ($photoStmt) {
            $photoStmt->bind_param('is', $txnId, $comparisonPath);
            $photoStmt->execute();
            $photoStmt->close();
        }
    }
    
    echo "SUCCESS (similarity: " . round($similarity, 2) . "%, confidence: {$confidence})\n";
    $processed++;
}

echo "\n=== Summary ===\n";
echo "Processed: {$processed}\n";
echo "Skipped: {$skipped}\n";
echo "Errors: {$errors}\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
echo '</pre>';

echo '<p style="margin-top: 20px;"><a href="admin-all-transaction.php" style="padding: 10px 20px; background: #4caf50; color: white; text-decoration: none; border-radius: 4px;">View Transactions</a></p>';

$conn->close();
