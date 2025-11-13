<?php
session_start();
header('Content-Type: application/json');

// Include notification helper
require_once __DIR__ . '/../includes/notification_helper.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = $_SESSION['admin_id'] ?? null;
$adminName = $_SESSION['admin_username'] ?? 'Admin';
if (!$adminId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$transactionId = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
$action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
$notesInput = trim($_POST['notes'] ?? '');
$detectedIssues = trim($_POST['detected_issues'] ?? '');

if ($transactionId <= 0 || !in_array($action, ['verify', 'flag', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$requiresNotes = in_array($action, ['flag', 'reject'], true);
if ($requiresNotes && $notesInput === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide notes for this action.']);
    exit;
}

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'capstone';

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$conn->set_charset('utf8mb4');

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? FOR UPDATE");
    if (!$stmt) {
        throw new Exception('Failed to prepare transaction lookup.');
    }
    $stmt->bind_param('i', $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        throw new Exception('Transaction not found.');
    }
    $transaction = $result->fetch_assoc();
    $stmt->close();

    if ($transaction['transaction_type'] !== 'Borrow') {
        throw new Exception('Only borrow transactions can be reviewed.');
    }

    // Enhanced Image Comparison - Auto-detect issues
    $detectedIssuesText = 'No visible damage detected.';
    $similarityScore = 0;
    $severityLevel = 'none';
    $comparisonResults = null;

    // Get transaction photos for comparison
    $photosStmt = $conn->prepare("SELECT photo_path, photo_type FROM transaction_photos WHERE transaction_id = ? ORDER BY created_at ASC");
    if ($photosStmt) {
        $photosStmt->bind_param('i', $transactionId);
        $photosStmt->execute();
        $photosResult = $photosStmt->get_result();
        $photos = $photosResult->fetch_all(MYSQLI_ASSOC);
        $photosStmt->close();

        // Find reference and return photos
        $referencePhoto = null;
        $returnPhoto = null;
        
        foreach ($photos as $photo) {
            if ($photo['photo_type'] === 'borrow' && $referencePhoto === null) {
                $referencePhoto = $photo['photo_path'];
            } elseif ($photo['photo_type'] === 'return' && $returnPhoto === null) {
                $returnPhoto = $photo['photo_path'];
            }
        }

        // Perform image comparison if both photos exist
        if ($referencePhoto && $returnPhoto) {
            // Include the enhanced image comparison functions
            require_once '../includes/image_comparison.php';
            
            $comparisonResults = analyzeImageDifferences($referencePhoto, $returnPhoto, 4, $transaction['item_size'] ?? 'medium');
            
            if ($comparisonResults && isset($comparisonResults['detected_issues_text'])) {
                $detectedIssuesText = $comparisonResults['detected_issues_text'];
                $similarityScore = $comparisonResults['similarity'] ?? 0;
                $severityLevel = $comparisonResults['severity_level'] ?? 'none';
            }
        }
    }

    $currentVerification = $transaction['return_verification_status'] ?? 'Pending';
    $currentReviewStatus = $transaction['return_review_status'] ?? 'Pending';
    $currentStatus = $transaction['status'] ?? 'Active';

    $newVerification = $currentVerification;
    $newReviewStatus = $currentReviewStatus;
    $newTransactionStatus = $currentStatus;

    // Handle different verification actions
    if ($action === 'verify') {
        $newVerification = 'Verified';
        $newReviewStatus = 'Verified';
        if (in_array(strtolower($currentStatus), ['pending review', 'returned', 'active'])) {
            $newTransactionStatus = 'Returned';
        }
        $message = 'Return verified successfully.';
    } elseif ($action === 'flag') {
        $newVerification = 'Flagged';
        $newReviewStatus = 'Pending';
        $newTransactionStatus = in_array(strtolower($currentStatus), ['active', 'pending review']) ? 'Pending Review' : $currentStatus;
        $message = 'Return flagged for additional review.';
    } else { // reject
        $newVerification = 'Rejected';
        $newReviewStatus = 'Rejected';
        $newTransactionStatus = 'Rejected';
        $message = 'Return rejected.';
    }
    
    // Validate status transition
    if ($currentVerification === 'Not Yet Returned' && $newVerification !== 'Pending') {
        throw new Exception('Cannot verify/flag/reject an item that has not been returned yet.');
    }

    $existingNotes = $transaction['notes'] ?? '';
    if (!is_string($existingNotes)) {
        $existingNotes = '';
    }

    $noteFragment = '';
    if ($action === 'verify') {
        $noteFragment = "Return verified by Admin {$adminName}.";
    } elseif ($action === 'flag') {
        $noteFragment = "Return flagged by Admin {$adminName}: {$notesInput}";
    } else {
        $noteFragment = "Return rejected by Admin {$adminName}: {$notesInput}";
    }

    $newNotes = trim($existingNotes);
    if ($noteFragment !== '') {
        $newNotes = $newNotes === '' ? $noteFragment : $newNotes . ' | ' . $noteFragment;
    }

    $update = $conn->prepare("UPDATE transactions SET status = ?, return_verification_status = ?, return_review_status = ?, processed_by = ?, notes = ?, detected_issues = ?, similarity_score = ?, updated_at = NOW() WHERE id = ?");
    if (!$update) {
        throw new Exception('Failed to prepare update statement.');
    }
    $update->bind_param('sssissdi', $newTransactionStatus, $newVerification, $newReviewStatus, $adminId, $newNotes, $detectedIssuesText, $similarityScore, $transactionId);
    if (!$update->execute() || $update->affected_rows !== 1) {
        throw new Exception('Failed to update transaction.');
    }
    $update->close();

    // Update inventory upon verification similar to API handler
    if ($action === 'verify') {
        $equipmentId = $conn->real_escape_string($transaction['equipment_id']);
        // Determine condition from severity
        $isDamaged = !in_array(strtolower($severityLevel), ['none', 'minor']);
        if ($isDamaged) {
            $invSql = "UPDATE inventory SET 
                        borrowed_quantity = GREATEST(borrowed_quantity - 1, 0),
                        damaged_quantity = COALESCE(damaged_quantity, 0) + 1,
                        available_quantity = GREATEST(quantity - (borrowed_quantity - 1) - (COALESCE(damaged_quantity, 0) + 1) - COALESCE(maintenance_quantity, 0), 0),
                        availability_status = CASE
                            WHEN GREATEST(quantity - (borrowed_quantity - 1) - (COALESCE(damaged_quantity, 0) + 1) - COALESCE(maintenance_quantity, 0), 0) <= 0 THEN 'Not Available'
                            WHEN GREATEST(quantity - (borrowed_quantity - 1) - (COALESCE(damaged_quantity, 0) + 1) - COALESCE(maintenance_quantity, 0), 0) <= COALESCE(minimum_stock_level, 1) THEN 'Low Stock'
                            ELSE 'Available'
                        END,
                        last_updated = NOW()
                      WHERE equipment_id = '$equipmentId'";
        } else {
            $invSql = "UPDATE inventory SET 
                        borrowed_quantity = GREATEST(borrowed_quantity - 1, 0),
                        available_quantity = GREATEST(quantity - (borrowed_quantity - 1) - COALESCE(damaged_quantity, 0) - COALESCE(maintenance_quantity, 0), 0),
                        availability_status = CASE
                            WHEN GREATEST(quantity - (borrowed_quantity - 1) - COALESCE(damaged_quantity, 0) - COALESCE(maintenance_quantity, 0), 0) <= 0 THEN 'Not Available'
                            WHEN GREATEST(quantity - (borrowed_quantity - 1) - COALESCE(damaged_quantity, 0) - COALESCE(maintenance_quantity, 0), 0) <= COALESCE(minimum_stock_level, 1) THEN 'Low Stock'
                            ELSE 'Available'
                        END,
                        last_updated = NOW()
                      WHERE equipment_id = '$equipmentId'";
        }
        if (!$conn->query($invSql)) {
            throw new Exception('Failed to update inventory: ' . $conn->error);
        }
    }

    $conn->commit();

    // Create notification for return verification
    if ($action === 'verify') {
        $student_id = $transaction['user_id'] ?? 'Unknown';
        $equipment_name = $transaction['equipment_name'] ?? 'Equipment';
        $condition = ($severityLevel === 'none' || $severityLevel === 'minor') ? 'Good' : 'Damaged';
        notifyReturnVerified($conn, $equipment_name, $student_id, $condition);
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'transaction' => [
            'id' => $transactionId,
            'status' => $newTransactionStatus,
            'return_verification_status' => $newVerification,
            'return_review_status' => $newReviewStatus,
            'similarity_score' => $similarityScore,
            'detected_issues' => $detectedIssuesText,
            'severity_level' => $severityLevel,
            'comparison_method' => $comparisonResults['method_used'] ?? 'hybrid',
        ],
        'display' => [
            'status' => $newTransactionStatus,
            'verification_status' => $newVerification,
            'review_status' => $newReviewStatus,
            'similarity_score' => $similarityScore,
            'detected_issues' => $detectedIssuesText,
            'severity_level' => $severityLevel,
        ],
    ]);
    exit;
} catch (Exception $ex) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    exit;
}
