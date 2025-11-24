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

/**
 * Check if a column exists on a table
 */
function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $db = $conn->real_escape_string($conn->query('SELECT DATABASE() db')->fetch_assoc()['db']);
    $sql = "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$table' AND COLUMN_NAME='$column'";
    $res = $conn->query($sql);
    if ($res && ($row = $res->fetch_assoc())) { return (int)$row['cnt'] > 0; }
    return false;
}

/**
 * Process an uploaded image file. Returns relative path or null.
 */
function handlePhotoUpload($fieldName, $student_id) {
    if (!isset($_FILES[$fieldName]) || !is_uploaded_file($_FILES[$fieldName]['tmp_name'])) {
        return null;
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) { return null; }
    if ($file['size'] > 2 * 1024 * 1024) { return null; } // 2MB
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $ext = null;
    switch ($mime) {
        case 'image/jpeg': $ext = 'jpg'; break;
        case 'image/png': $ext = 'png'; break;
        default: return null;
    }
    $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$student_id);
    $baseDir = realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'user_photos';
    if (!is_dir($baseDir)) { @mkdir($baseDir, 0775, true); }
    $filename = 'user_' . $safeId . '_' . time() . '.' . $ext;
    $dest = $baseDir . DIRECTORY_SEPARATOR . $filename;
    if (!@move_uploaded_file($file['tmp_name'], $dest)) { return null; }
    // Return path relative to web root
    return 'uploads/user_photos/' . $filename;
}

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
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $user_type = $_POST['user_type'] ?? 'Student';
    $user_type = in_array($user_type, ['Student','Teacher'], true) ? $user_type : 'Student';
    
    if (empty($student_id)) {
        throw new Exception('Student ID is required');
    }
    // Require valid email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid email is required']);
        exit;
    }
    // Ensure email uniqueness if column exists
    if (columnExists($conn, 'users', 'email')) {
        $e = $conn->real_escape_string($email);
        $dup = $conn->query("SELECT id FROM users WHERE email = '$e' LIMIT 1");
        if ($dup && $dup->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email already in use']);
            exit;
        }
    }
    
    // Check for duplicates (both fields use same value)
    $check = $conn->query("SELECT id FROM users WHERE rfid_tag = '$student_id' OR student_id = '$student_id'");
    if ($check && $check->num_rows > 0) {
        throw new Exception('Student ID already exists');
    }
    
    // Store same value in both rfid_tag and student_id (embedded RFID in ID card)
    $hasEmail = columnExists($conn, 'users', 'email');
    $hasUserType = columnExists($conn, 'users', 'user_type');
    if ($hasEmail && $hasUserType) {
        $sql = "INSERT INTO users (rfid_tag, student_id, status, penalty_points, email, user_type) 
                VALUES ('$student_id', '$student_id', '$status', 0, " . (empty($email) ? "NULL" : "'$email'") . ", '$user_type')";
    } elseif ($hasEmail) {
        $sql = "INSERT INTO users (rfid_tag, student_id, status, penalty_points, email) 
                VALUES ('$student_id', '$student_id', '$status', 0, " . (empty($email) ? "NULL" : "'$email'") . ")";
    } elseif ($hasUserType) {
        $sql = "INSERT INTO users (rfid_tag, student_id, status, penalty_points, user_type) 
                VALUES ('$student_id', '$student_id', '$status', 0, '$user_type')";
    } else {
        $sql = "INSERT INTO users (rfid_tag, student_id, status, penalty_points) 
                VALUES ('$student_id', '$student_id', '$status', 0)";
    }
    if (!$conn->query($sql)) {
        throw new Exception('Failed to create user: ' . $conn->error);
    }
    $newId = $conn->insert_id;

    // Optional photo upload
    $photoPath = handlePhotoUpload('photo', $student_id);
    if ($photoPath && columnExists($conn, 'users', 'photo_path')) {
        $p = $conn->real_escape_string($photoPath);
        $conn->query("UPDATE users SET photo_path='$p', updated_at = NOW() WHERE id = $newId");
    }

    echo json_encode([
        'success' => true,
        'message' => 'User added successfully',
        'user_id' => $newId,
        'photo_path' => $photoPath,
        'email' => $email
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
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $user_type = $_POST['user_type'] ?? null;
    if ($user_type !== null && !in_array($user_type, ['Student','Teacher'], true)) { $user_type = 'Student'; }
    
    if ($id <= 0) {
        throw new Exception('Invalid user ID');
    }
    
    if (empty($student_id)) {
        throw new Exception('Student ID is required');
    }
    // Require valid email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid email is required']);
        exit;
    }
    
    // Check for duplicates (excluding current user, both fields use same value)
    $check = $conn->query("SELECT id FROM users WHERE (rfid_tag = '$student_id' OR student_id = '$student_id') AND id != $id");
    if ($check && $check->num_rows > 0) {
        throw new Exception('Student ID already exists');
    }
    // Ensure email uniqueness if column exists
    if (columnExists($conn, 'users', 'email')) {
        $e = $conn->real_escape_string($email);
        $dup = $conn->query("SELECT id FROM users WHERE email = '$e' AND id != $id LIMIT 1");
        if ($dup && $dup->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email already in use']);
            exit;
        }
    }
    
    // Update both rfid_tag and student_id with same value (embedded RFID in ID card)
    $hasEmail = columnExists($conn, 'users', 'email');
    $hasUserType = columnExists($conn, 'users', 'user_type');
    if ($hasEmail && $hasUserType) {
        $sql = "UPDATE users SET 
                rfid_tag = '$student_id',
                student_id = '$student_id',
                status = '$status',
                penalty_points = $penalty_points,
                email = " . (empty($email) ? "NULL" : "'$email'") . ",
                user_type = " . ($user_type === null ? "user_type" : "'$user_type'") . ",
                updated_at = NOW()
                WHERE id = $id";
    } elseif ($hasEmail) {
        $sql = "UPDATE users SET 
                rfid_tag = '$student_id',
                student_id = '$student_id',
                status = '$status',
                penalty_points = $penalty_points,
                email = " . (empty($email) ? "NULL" : "'$email'") . ",
                updated_at = NOW()
                WHERE id = $id";
    } elseif ($hasUserType) {
        $sql = "UPDATE users SET 
                rfid_tag = '$student_id',
                student_id = '$student_id',
                status = '$status',
                penalty_points = $penalty_points,
                user_type = " . ($user_type === null ? "user_type" : "'$user_type'") . ",
                updated_at = NOW()
                WHERE id = $id";
    } else {
        $sql = "UPDATE users SET 
                rfid_tag = '$student_id',
                student_id = '$student_id',
                status = '$status',
                penalty_points = $penalty_points,
                updated_at = NOW()
                WHERE id = $id";
    }
    
    if (!$conn->query($sql)) {
        throw new Exception('Failed to update user: ' . $conn->error);
    }
    
    if ($conn->affected_rows === 0) {
        throw new Exception('User not found or no changes made');
    }

    // Optional new photo
    $photoPath = handlePhotoUpload('photo', $student_id);
    if ($photoPath && columnExists($conn, 'users', 'photo_path')) {
        $p = $conn->real_escape_string($photoPath);
        $conn->query("UPDATE users SET photo_path='$p', updated_at = NOW() WHERE id = $id");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully',
        'photo_path' => $photoPath,
        'email' => $email
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
