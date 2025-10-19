<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$adminId = $_SESSION['admin_id'] ?? null;
if (!$adminId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$action = $_POST['action'] ?? '';
$transactionId = (int)($_POST['transaction_id'] ?? 0);
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
if ($transactionId <= 0 || ($action !== 'approve' && $action !== 'reject')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
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
    $stmt = $conn->prepare("SELECT t.*, e.rfid_tag, e.name AS equipment_name, i.available_quantity, i.minimum_stock_level FROM transactions t JOIN equipment e ON t.equipment_id = e.id LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id WHERE t.id = ? FOR UPDATE");
    if (!$stmt) {
        throw new Exception('Failed to prepare transaction lookup: ' . $conn->error);
    }
    $stmt->bind_param('i', $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        throw new Exception('Transaction not found');
    }
    $transaction = $result->fetch_assoc();
    $stmt->close();
    if ($transaction['transaction_type'] !== 'Borrow') {
        throw new Exception('Only borrow transactions can be updated');
    }
    if (strtolower($transaction['item_size'] ?? '') !== 'large') {
        throw new Exception('Only large item transactions require approval');
    }
    if ($transaction['approval_status'] !== 'Pending') {
        throw new Exception('This transaction is no longer pending');
    }
    if ($action === 'approve') {
        $status = 'Active';
        $approvalStatus = 'Approved';
        $update = $conn->prepare("UPDATE transactions SET approval_status = ?, status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = NULL, updated_at = NOW() WHERE id = ?");
        if (!$update) {
            throw new Exception('Failed to prepare approval update: ' . $conn->error);
        }
        $update->bind_param('ssii', $approvalStatus, $status, $adminId, $transactionId);
        if (!$update->execute() || $update->affected_rows !== 1) {
            throw new Exception('Failed to update transaction');
        }
        $update->close();
        $quantity = (int)$transaction['quantity'];
        if ($quantity <= 0) {
            $quantity = 1;
        }
        $rfid = $transaction['rfid_tag'];
        if (!$rfid) {
            throw new Exception('Missing equipment reference');
        }
        $invUpdate = $conn->prepare("UPDATE inventory SET available_quantity = available_quantity - ?, borrowed_quantity = borrowed_quantity + ?, availability_status = CASE WHEN (available_quantity - ?) <= 0 THEN 'Out of Stock' WHEN (available_quantity - ?) <= IFNULL(minimum_stock_level, 1) THEN 'Low Stock' ELSE 'Available' END, last_updated = NOW() WHERE equipment_id = ? AND available_quantity >= ?");
        if (!$invUpdate) {
            throw new Exception('Failed to prepare inventory update: ' . $conn->error);
        }
        $invUpdate->bind_param('iiiisi', $quantity, $quantity, $quantity, $quantity, $rfid, $quantity);
        if (!$invUpdate->execute() || $invUpdate->affected_rows !== 1) {
            throw new Exception('Failed to update inventory');
        }
        $invUpdate->close();
        $invStmt = $conn->prepare('SELECT available_quantity, borrowed_quantity, availability_status FROM inventory WHERE equipment_id = ? LIMIT 1');
        if ($invStmt) {
            $invStmt->bind_param('s', $rfid);
        $invStmt->execute();
        $invResult = $invStmt->get_result();
        $invData = $invResult ? $invResult->fetch_assoc() : null;
        $invStmt->close();
        } else {
            $invData = null;
        }
        $refreshStmt = $conn->prepare('SELECT status, approval_status, approved_by, approved_at, rejection_reason FROM transactions WHERE id = ? LIMIT 1');
        if (!$refreshStmt) {
            throw new Exception('Failed to fetch updated approval details: ' . $conn->error);
        }
        $refreshStmt->bind_param('i', $transactionId);
        $refreshStmt->execute();
        $refreshResult = $refreshStmt->get_result();
        $updatedRow = $refreshResult ? $refreshResult->fetch_assoc() : null;
        $refreshStmt->close();
        if (!$updatedRow) {
            throw new Exception('Unable to load updated approval details.');
        }
        $approvedAtDisplay = !empty($updatedRow['approved_at'])
            ? date('M j, Y g:i A', strtotime($updatedRow['approved_at']))
            : null;
        $approverName = $_SESSION['admin_username'] ?? 'Admin';
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Borrow request approved',
            'transaction' => $updatedRow,
            'approved_at_display' => $approvedAtDisplay,
            'approver_username' => $approverName,
            'inventory' => $invData
        ]);
        exit;
    }
    if ($action === 'reject') {
        if ($reason === '') {
            throw new Exception('Rejection reason is required');
        }
        $status = 'Rejected';
        $approvalStatus = 'Rejected';
        $update = $conn->prepare("UPDATE transactions SET approval_status = ?, status = ?, approved_by = NULL, approved_at = NULL, rejection_reason = ?, updated_at = NOW() WHERE id = ?");
        if (!$update) {
            throw new Exception('Failed to prepare rejection update: ' . $conn->error);
        }
        $update->bind_param('sssi', $approvalStatus, $status, $reason, $transactionId);
        if (!$update->execute() || $update->affected_rows !== 1) {
            throw new Exception('Failed to update transaction');
        }
        $update->close();
        $refreshStmt = $conn->prepare('SELECT status, approval_status, approved_by, approved_at, rejection_reason FROM transactions WHERE id = ? LIMIT 1');
        if (!$refreshStmt) {
            throw new Exception('Failed to fetch updated rejection details: ' . $conn->error);
        }
        $refreshStmt->bind_param('i', $transactionId);
        $refreshStmt->execute();
        $refreshResult = $refreshStmt->get_result();
        $updatedRow = $refreshResult ? $refreshResult->fetch_assoc() : null;
        $refreshStmt->close();
        if (!$updatedRow) {
            throw new Exception('Unable to load updated rejection details.');
        }
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Borrow request rejected',
            'transaction' => $updatedRow
        ]);
        exit;
    }
    throw new Exception('Invalid action');
} catch (Exception $ex) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    exit;
}
?>
