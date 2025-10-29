<?php
// Database connection configuration
$host = 'localhost';
$dbname = 'capstone';
$username = 'root';
$password = '';

// Check if this is an API call (JSON response expected)
$is_api_call = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    if ($is_api_call) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    } else {
        die("Connection failed (PDO): " . $e->getMessage());
    }
}

$conn = @new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    if ($is_api_call) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    } else {
        die("Connection failed (MySQLi): " . $conn->connect_error);
    }
}
$conn->set_charset('utf8mb4');
?>
