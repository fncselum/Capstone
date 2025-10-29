<?php
/**
 * Authorized Users API Handler
 * Handles CRUD operations for authorized users (RFID/Student ID management)
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
        case 'get_all':
            getAllUsers($conn);
            break;
            
        case 'create':
            createUser($conn);
            break;
            
        case 'update':
            updateUser($conn);
            break;
            
        case 'delete':
            deleteUser($conn, $_POST['id'] ?? 0);
            break;
            
        case 'toggle_status':
            toggleStatus($conn, $_POST['id'] ?? 0);
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
 * Get all users with optional filtering
 */
function getAllUsers($conn) {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'all';
    
    $sql = "SELECT * FROM users WHERE 1=1";
    
    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $sql .= " AND (rfid_tag LIKE '%$search%' OR student_id LIKE '%$search%')";
    }
    
    if ($status !== 'all') {
        $status = $conn->real_escape_string($status);
        $sql .= " AND status = '$status'";
    }
    
    $sql .= " ORDER BY registered_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $users]);
}

/**
 * Create new authorized user
 * Note: Student ID cards have embedded RFID, so rfid_tag = student_id
 */
function createUser($conn) {
    $student_id = $conn->real_escape_string($_POST['student_id'] ?? '');
    $status = $conn->real_escape_string($_POST['status'] ?? 'Active');
    
    if (empty($student_id)) {
        throw new Exception('Student ID is required');
    }
    
    // Check for duplicates (both fields use same value)
    $check = $conn->query("SELECT id FROM users WHERE rfid_tag = '$student_id' OR student_id = '$student_id'");
    if ($check && $check->num_rows > 0) {
        throw new Exception('Student ID already exists');
    }
    
    // Store same value in both rfid_tag and student_id (embedded RFID in ID card)
    $sql = "INSERT INTO users (rfid_tag, student_id, status, penalty_points) 
            VALUES ('$student_id', '$student_id', '$status', 0)";
    
    if (!$conn->query($sql)) {
        throw new Exception('Failed to create user: ' . $conn->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'User added successfully',
        'user_id' => $conn->insert_id
    ]);
}

/**
 * Update existing user
 * Note: Student ID cards have embedded RFID, so rfid_tag = student_id
 */
function updateUser($conn) {
    $id = (int)($_POST['id'] ?? 0);
    $student_id = $conn->real_escape_string($_POST['student_id'] ?? '');
    $status = $conn->real_escape_string($_POST['status'] ?? 'Active');
    $penalty_points = (int)($_POST['penalty_points'] ?? 0);
    
    if ($id <= 0) {
        throw new Exception('Invalid user ID');
    }
    
    if (empty($student_id)) {
        throw new Exception('Student ID is required');
    }
    
    // Check for duplicates (excluding current user, both fields use same value)
    $check = $conn->query("SELECT id FROM users WHERE (rfid_tag = '$student_id' OR student_id = '$student_id') AND id != $id");
    if ($check && $check->num_rows > 0) {
        throw new Exception('Student ID already exists');
    }
    
    // Update both rfid_tag and student_id with same value (embedded RFID in ID card)
    $sql = "UPDATE users SET 
            rfid_tag = '$student_id',
            student_id = '$student_id',
            status = '$status',
            penalty_points = $penalty_points,
            updated_at = NOW()
            WHERE id = $id";
    
    if (!$conn->query($sql)) {
        throw new Exception('Failed to update user: ' . $conn->error);
    }
    
    if ($conn->affected_rows === 0) {
        throw new Exception('User not found or no changes made');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
}

/**
 * Delete user
 */
function deleteUser($conn, $id) {
    $id = (int)$id;
    
    if ($id <= 0) {
        throw new Exception('Invalid user ID');
    }
    
    // Check if user has active transactions
    $check = $conn->query("SELECT id FROM transactions WHERE user_id = $id AND status IN ('Pending', 'Borrowed') LIMIT 1");
    if ($check && $check->num_rows > 0) {
        throw new Exception('Cannot delete user with active transactions. Please complete or cancel their transactions first.');
    }
    
    $sql = "DELETE FROM users WHERE id = $id";
    
    if (!$conn->query($sql)) {
        throw new Exception('Failed to delete user: ' . $conn->error);
    }
    
    if ($conn->affected_rows === 0) {
        throw new Exception('User not found');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);
}

/**
 * Toggle user status (Active/Inactive)
 */
function toggleStatus($conn, $id) {
    $id = (int)$id;
    
    if ($id <= 0) {
        throw new Exception('Invalid user ID');
    }
    
    // Get current status
    $result = $conn->query("SELECT status FROM users WHERE id = $id");
    if (!$result || $result->num_rows === 0) {
        throw new Exception('User not found');
    }
    
    $row = $result->fetch_assoc();
    $current_status = $row['status'];
    $new_status = ($current_status === 'Active') ? 'Inactive' : 'Active';
    
    $sql = "UPDATE users SET status = '$new_status', updated_at = NOW() WHERE id = $id";
    
    if (!$conn->query($sql)) {
        throw new Exception('Failed to update status: ' . $conn->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'new_status' => $new_status
    ]);
}
?>
