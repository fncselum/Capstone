<?php
/**
 * Return Verification API Handler
 * Handles return verification operations
 */

// Prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../../includes/db_connection.php';

// Set JSON header immediately
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_returns':
            getReturns($conn);
            break;
            
        case 'get_transaction':
            getTransaction($conn, $_GET['id'] ?? 0);
            break;
            
        case 'verify_return':
            verifyReturn($conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get all returns with optional filtering
 */
function getReturns($conn) {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'Pending Return';
    
    $sql = "SELECT t.*, 
                   e.name as equipment_name,
                   e.image_path,
                   u.student_id,
                   DATEDIFF(NOW(), t.transaction_date) as days_borrowed,
                   CASE 
                       WHEN t.expected_return_date < NOW() AND t.status = 'Active' THEN 'Overdue'
                       ELSE t.status
                   END as display_status
            FROM transactions t
            LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
            LEFT JOIN users u ON t.user_id = u.id
            WHERE 1=1";
    
    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $sql .= " AND (t.id LIKE '%$search%' 
                  OR e.name LIKE '%$search%' 
                  OR u.student_id LIKE '%$search%')";
    }
    
    if ($status !== 'all') {
        if ($status === 'Pending Return') {
            // Pending verification
            $sql .= " AND t.return_verification_status IN ('Pending', 'Analyzing')";
        } elseif ($status === 'Returned') {
            // Already verified
            $sql .= " AND t.return_verification_status = 'Verified'";
        }
    } else {
        // Show all returns (pending and verified)
        $sql .= " AND t.return_verification_status IN ('Pending', 'Analyzing', 'Verified', 'Damage', 'Flagged')";
    }
    
    $sql .= " ORDER BY 
              CASE 
                  WHEN t.return_verification_status IN ('Pending', 'Analyzing') THEN 1
                  WHEN t.return_verification_status = 'Verified' THEN 2
                  ELSE 3
              END,
              t.actual_return_date DESC,
              t.expected_return_date ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $returns = [];
    while ($row = $result->fetch_assoc()) {
        $returns[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $returns]);
}

/**
 * Get transaction details for verification
 */
function getTransaction($conn, $id) {
    $id = (int)$id;
    
    $sql = "SELECT t.*, 
                   e.name as equipment_name,
                   e.image_path,
                   e.rfid_tag as equipment_rfid,
                   u.student_id,
                   u.rfid_tag as user_rfid
            FROM transactions t
            LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.id = $id";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    }
}

/**
 * Verify return and update transaction
 */
function verifyReturn($conn) {
    $id = (int)($_POST['id'] ?? 0);
    $return_condition = $conn->real_escape_string($_POST['return_condition'] ?? 'Good');
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $admin_id = $_SESSION['admin_id'] ?? null;
    
    if ($id <= 0) {
        throw new Exception('Invalid transaction ID');
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Get transaction details
        $result = $conn->query("SELECT equipment_id, status, return_verification_status FROM transactions WHERE id = $id FOR UPDATE");
        if (!$result || $result->num_rows === 0) {
            throw new Exception('Transaction not found');
        }
        
        $transaction = $result->fetch_assoc();
        
        if (!in_array($transaction['return_verification_status'], ['Pending', 'Analyzing'])) {
            throw new Exception('Transaction is not pending return verification');
        }
        
        // Determine status and verification status based on condition
        $new_status = ($return_condition === 'Damaged') ? 'Damaged' : 'Returned';
        $verification_status = ($return_condition === 'Damaged') ? 'Damage' : 'Verified';
        
        // Update transaction
        $sql = "UPDATE transactions SET 
                status = '$new_status',
                condition_after = '$return_condition',
                return_verification_status = '$verification_status',
                notes = CONCAT(COALESCE(notes, ''), '\n[Verification] ', '$notes'),
                actual_return_date = NOW(),
                processed_by = " . ($admin_id ? "'Admin ID: $admin_id'" : "NULL") . "
                WHERE id = $id";
        
        if (!$conn->query($sql)) {
            throw new Exception('Failed to update transaction: ' . $conn->error);
        }
        
        // Update inventory - return the item
        $equipment_id = $conn->real_escape_string($transaction['equipment_id']);
        
        // If damaged, increment damaged_quantity
        if ($return_condition === 'Damaged') {
            $sql = "UPDATE inventory SET 
                    borrowed_quantity = GREATEST(borrowed_quantity - 1, 0),
                    damaged_quantity = damaged_quantity + 1,
                    available_quantity = GREATEST(quantity - (borrowed_quantity - 1) - (damaged_quantity + 1) - COALESCE(maintenance_quantity, 0), 0),
                    availability_status = CASE
                        WHEN GREATEST(quantity - (borrowed_quantity - 1) - (damaged_quantity + 1) - COALESCE(maintenance_quantity, 0), 0) <= 0 THEN 'Not Available'
                        WHEN GREATEST(quantity - (borrowed_quantity - 1) - (damaged_quantity + 1) - COALESCE(maintenance_quantity, 0), 0) <= COALESCE(minimum_stock_level, 1) THEN 'Low Stock'
                        ELSE 'Available'
                    END,
                    last_updated = NOW()
                    WHERE equipment_id = '$equipment_id'";
        } else {
            // Good condition - just return to available
            $sql = "UPDATE inventory SET 
                    borrowed_quantity = GREATEST(borrowed_quantity - 1, 0),
                    available_quantity = GREATEST(quantity - (borrowed_quantity - 1) - COALESCE(damaged_quantity, 0) - COALESCE(maintenance_quantity, 0), 0),
                    availability_status = CASE
                        WHEN GREATEST(quantity - (borrowed_quantity - 1) - COALESCE(damaged_quantity, 0) - COALESCE(maintenance_quantity, 0), 0) <= 0 THEN 'Not Available'
                        WHEN GREATEST(quantity - (borrowed_quantity - 1) - COALESCE(damaged_quantity, 0) - COALESCE(maintenance_quantity, 0), 0) <= COALESCE(minimum_stock_level, 1) THEN 'Low Stock'
                        ELSE 'Available'
                    END,
                    last_updated = NOW()
                    WHERE equipment_id = '$equipment_id'";
        }
        
        if (!$conn->query($sql)) {
            throw new Exception('Failed to update inventory: ' . $conn->error);
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Return verified successfully',
            'condition' => $return_condition
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}
?>
