<?php
session_start();
header('Content-Type: application/json');

// Include email configuration (with error handling)
$email_config_loaded = false;
if (file_exists('../admin/includes/email_config.php')) {
    try {
        require_once '../admin/includes/email_config.php';
        $email_config_loaded = true;
    } catch (Exception $e) {
        // Continue without email functionality
        error_log("Email config failed to load: " . $e->getMessage());
    }
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error,
        'debug' => [
            'host' => $host,
            'user' => $user,
            'dbname' => $dbname,
            'error' => $conn->connect_error
        ]
    ]);
    exit;
}

// Get RFID from POST
$rfid = trim($_POST['rfid'] ?? '');

if (empty($rfid)) {
    echo json_encode([
        'success' => false,
        'message' => 'RFID is required'
    ]);
    exit;
}

// Check which columns exist in the users table
$columns_query = "SHOW COLUMNS FROM users";
$columns_result = $conn->query($columns_query);
$existing_columns = [];
while ($col = $columns_result->fetch_assoc()) {
    $existing_columns[] = $col['Field'];
}

// Build SELECT query based on existing columns
$select_fields = ['id', 'rfid_tag', 'student_id', 'status', 'penalty_points'];
$has_admin_fields = in_array('is_admin', $existing_columns) && in_array('admin_level', $existing_columns);
$has_photo_path = in_array('photo_path', $existing_columns);
$has_email = in_array('email', $existing_columns);
$has_name = in_array('name', $existing_columns);

if ($has_admin_fields) {
    $select_fields[] = 'is_admin';
    $select_fields[] = 'admin_level';
}
if ($has_photo_path) {
    $select_fields[] = 'photo_path';
}
if ($has_email) {
    $select_fields[] = 'email';
}
if ($has_name) {
    $select_fields[] = 'name';
}

$select_sql = "SELECT " . implode(', ', $select_fields) . " FROM users WHERE rfid_tag = ? OR student_id = ?";

// Check if user exists
$stmt = $conn->prepare($select_sql);
$stmt->bind_param("ss", $rfid, $rfid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Only allow Active users
    if ($user['status'] !== 'Active') {
        $statusMessage = '';
        switch ($user['status']) {
            case 'Suspended':
                $statusMessage = 'Your account is suspended. Please contact the administrator.';
                break;
            case 'Inactive':
                $statusMessage = 'Your account is inactive. Please contact the administrator.';
                break;
            default:
                $statusMessage = 'Your account status does not allow access. Please contact the administrator.';
                break;
        }
        
        echo json_encode([
            'success' => false,
            'message' => $statusMessage
        ]);
        exit;
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['rfid_tag'] = $rfid;
    $_SESSION['student_id'] = $user['student_id'];
    $_SESSION['penalty_points'] = $user['penalty_points'] ?? 0;
    
    // Reset face verification status until user passes biometric check
    $_SESSION['face_verified'] = false;

    // Check if user is admin
    $is_admin = isset($user['is_admin']) ? (bool)$user['is_admin'] : false;
    
    if ($is_admin) {
        // Set admin session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['student_id'];
        $_SESSION['admin_level'] = $user['admin_level'];
    }
    
    $photoBinary = $has_photo_path;
    $photoUrl = null;
    $hasPhoto = false;
    
    if ($has_photo_path && !empty($user['photo_path'])) {
        // Check if photo_path contains a file path or binary data
        if (strpos($user['photo_path'], '/') !== false || strpos($user['photo_path'], '\\') !== false) {
            // It's a file path
            $photoUrl = $user['photo_path'];
            $hasPhoto = file_exists($_SERVER['DOCUMENT_ROOT'] . $photoUrl);
        } else {
            // It's binary data, use API endpoint
            $photoUrl = 'api/get_user_photo.php';
            $hasPhoto = true;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'RFID verified successfully',
        'is_admin' => $is_admin,
        'admin_level' => $user['admin_level'] ?? 'user',
        'user_id' => $user['id'],
        'student_id' => $user['student_id'],
        'penalty_points' => $user['penalty_points'] ?? 0,
        'reference_photo_url' => $photoUrl,
        'has_reference_photo' => $hasPhoto,
        'user_name' => $user['name'] ?? $user['student_id'],
        'user_email' => $user['email'] ?? 'Not provided',
        'rfid_tag' => $user['rfid_tag']
    ]);
} else {
    // User not found - Reject unauthorized access
    // Send email alert to admin about unauthorized access attempt (if email config loaded)
    if ($email_config_loaded && function_exists('sendUnauthorizedAccessAlert')) {
        $attemptTime = date('F j, Y g:i A');
        try {
            sendUnauthorizedAccessAlert($conn, $rfid, $attemptTime);
        } catch (Exception $e) {
            error_log("Email alert failed: " . $e->getMessage());
        }
    }
    
    // Log unauthorized access attempt
    $log_stmt = $conn->prepare("INSERT INTO unauthorized_access_logs (rfid_tag, attempt_time, ip_address) VALUES (?, NOW(), ?)");
    if ($log_stmt) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $log_stmt->bind_param("ss", $rfid, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'RFID not authorized. Please contact the administrator to register your account.'
    ]);
}

$stmt->close();
$conn->close();
?>
