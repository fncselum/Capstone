<?php
// Simple RFID test script to debug connection issues
session_start();
header('Content-Type: application/json');

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

echo "Testing RFID validation...\n";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error,
        'debug' => [
            'host' => $host,
            'user' => $user,
            'dbname' => $dbname
        ]
    ]);
    exit;
}

// Test if users table exists
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if (!$table_check || $table_check->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Users table does not exist',
        'debug' => 'Please check if the database is properly set up'
    ]);
    exit;
}

// Check table structure
$columns_query = "SHOW COLUMNS FROM users";
$columns_result = $conn->query($columns_query);
$existing_columns = [];
while ($col = $columns_result->fetch_assoc()) {
    $existing_columns[] = $col['Field'];
}

// Test query for all users
$test_query = "SELECT id, rfid_tag, student_id, status FROM users LIMIT 5";
$test_result = $conn->query($test_query);

$users = [];
if ($test_result) {
    while ($row = $test_result->fetch_assoc()) {
        $users[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Database connection successful',
    'debug' => [
        'columns' => $existing_columns,
        'sample_users' => $users,
        'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count']
    ]
]);

$conn->close();
?>
