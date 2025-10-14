<?php
session_start();
header('Content-Type: application/json');

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
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

if ($has_admin_fields) {
    $select_fields[] = 'is_admin';
    $select_fields[] = 'admin_level';
}

$select_sql = "SELECT " . implode(', ', $select_fields) . " FROM users WHERE rfid_tag = ? OR student_id = ?";

// Check if user exists
$stmt = $conn->prepare($select_sql);
$stmt->bind_param("ss", $rfid, $rfid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Check if user is suspended
    if ($user['status'] === 'Suspended') {
        echo json_encode([
            'success' => false,
            'message' => 'Your account is suspended. Please contact the administrator.'
        ]);
        exit;
    }
    
    // Check if user is inactive
    if ($user['status'] === 'Inactive') {
        echo json_encode([
            'success' => false,
            'message' => 'Your account is inactive. Please contact the administrator.'
        ]);
        exit;
    }
    
    // Store user info in session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['rfid_tag'] = $user['rfid_tag'];
    $_SESSION['student_id'] = $user['student_id'];
    $_SESSION['is_admin'] = isset($user['is_admin']) ? (bool)$user['is_admin'] : false;
    $_SESSION['admin_level'] = $user['admin_level'] ?? 'user';
    $_SESSION['penalty_points'] = $user['penalty_points'] ?? 0;
    
    // Check if user is admin
    $is_admin = isset($user['is_admin']) ? (bool)$user['is_admin'] : false;
    
    if ($is_admin) {
        // Set admin session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['student_id'];
        $_SESSION['admin_level'] = $user['admin_level'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'RFID verified successfully',
        'is_admin' => $is_admin,
        'admin_level' => $user['admin_level'] ?? 'user',
        'user_id' => $user['id'],
        'student_id' => $user['student_id'],
        'penalty_points' => $user['penalty_points'] ?? 0
    ]);
} else {
    // User not found - Auto-register new RFID
    $new_student_id = $rfid; // Use RFID as student ID initially
    $status = 'Active';
    $penalty_points = 0;
    
    // Insert new user
    $insert_stmt = $conn->prepare("INSERT INTO users (rfid_tag, student_id, status, penalty_points, registered_at) 
                                   VALUES (?, ?, ?, ?, NOW())");
    $insert_stmt->bind_param("sssi", $rfid, $new_student_id, $status, $penalty_points);
    
    if ($insert_stmt->execute()) {
        $new_user_id = $insert_stmt->insert_id;
        
        // Store new user info in session
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['rfid_tag'] = $rfid;
        $_SESSION['student_id'] = $new_student_id;
        $_SESSION['is_admin'] = false;
        $_SESSION['admin_level'] = 'user';
        $_SESSION['penalty_points'] = 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Welcome! RFID registered successfully.',
            'is_admin' => false,
            'admin_level' => 'user',
            'user_id' => $new_user_id,
            'student_id' => $new_student_id,
            'penalty_points' => 0,
            'new_user' => true
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error registering RFID. Please try again.'
        ]);
    }
    
    $insert_stmt->close();
}

$stmt->close();
$conn->close();
?>
