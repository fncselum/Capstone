<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed']);
    exit;
}

$guideline_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($guideline_id > 0) {
    $sql = "SELECT id, title, penalty_type, penalty_amount, penalty_points, penalty_description 
            FROM penalty_guidelines 
            WHERE id = ? AND status = 'active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guideline_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'guideline' => $row
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Guideline not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}
?>
