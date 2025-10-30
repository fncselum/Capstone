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
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

if ($action === 'read') {
    $sql = "UPDATE notifications SET status = 'read', updated_at = NOW() WHERE id = ?";
    $message = 'Notification marked as read';
} elseif ($action === 'archive') {
    $sql = "UPDATE notifications SET status = 'archived', updated_at = NOW() WHERE id = ?";
    $message = 'Notification archived';
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
}

$stmt->close();
$conn->close();
?>
