<?php
/**
 * Polling endpoint for image comparison status
 * Returns current comparison status and results for a transaction
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

if (!isset($_GET['transaction_id'])) {
    echo json_encode(['error' => 'Missing transaction_id']);
    exit;
}

$transactionId = (int)$_GET['transaction_id'];

if ($transactionId <= 0) {
    echo json_encode(['error' => 'Invalid transaction_id']);
    exit;
}

// Fetch transaction status including AI fields
$query = $conn->prepare("SELECT 
    t.id,
    t.return_verification_status,
    t.return_review_status,
    t.similarity_score,
    t.detected_issues,
    t.severity_level,
    t.status,
    t.ai_analysis_status,
    t.ai_analysis_message,
    t.ai_similarity_score,
    t.ai_severity_level,
    t.ai_analysis_meta
FROM transactions t
WHERE t.id = ?");

$query->bind_param('i', $transactionId);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Transaction not found']);
    exit;
}

$row = $result->fetch_assoc();
$query->close();

// Get comparison preview if available
$previewPath = null;
$previewQuery = $conn->prepare("SELECT file_path FROM transaction_photos 
    WHERE transaction_id = ? AND photo_type = 'comparison' 
    ORDER BY created_at DESC LIMIT 1");
$previewQuery->bind_param('i', $transactionId);
$previewQuery->execute();
$previewResult = $previewQuery->get_result();
if ($previewResult->num_rows > 0) {
    $previewRow = $previewResult->fetch_assoc();
    $previewPath = $previewRow['file_path'];
}
$previewQuery->close();

// Get detailed metrics from transaction_meta if available
$detailedMetrics = null;
$metaQuery = $conn->prepare("SELECT meta_value FROM transaction_meta 
    WHERE transaction_id = ? AND meta_key = 'image_comparison' 
    ORDER BY created_at DESC LIMIT 1");
if ($metaQuery) {
    $metaQuery->bind_param('i', $transactionId);
    $metaQuery->execute();
    $metaResult = $metaQuery->get_result();
    if ($metaResult->num_rows > 0) {
        $metaRow = $metaResult->fetch_assoc();
        $detailedMetrics = json_decode($metaRow['meta_value'], true);
    }
    $metaQuery->close();
}

$response = [
    'transaction_id' => $transactionId,
    'status' => $row['status'],
    'return_verification_status' => $row['return_verification_status'],
    'return_review_status' => $row['return_review_status'],
    'similarity_score' => $row['similarity_score'],
    'detected_issues' => $row['detected_issues'],
    'severity_level' => $row['severity_level'],
    'preview_path' => $previewPath,
    'is_analyzing' => strtolower($row['return_verification_status'] ?? '') === 'analyzing',
    'detailed_metrics' => $detailedMetrics,
    'ai_analysis_status' => $row['ai_analysis_status'],
    'ai_analysis_message' => $row['ai_analysis_message'],
    'ai_similarity_score' => $row['ai_similarity_score'],
    'ai_severity_level' => $row['ai_severity_level'],
    'ai_analysis_meta' => $row['ai_analysis_meta'],
    'is_ai_analyzing' => in_array(strtolower($row['ai_analysis_status'] ?? ''), ['pending', 'processing']),
];

echo json_encode($response);
$conn->close();
