<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

// Require admin session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'capstone';

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(200);
    echo json_encode(['ok' => false, 'db_online' => false, 'unread' => 0]);
    exit;
}
$conn->set_charset('utf8mb4');

$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if (!$table_check || $table_check->num_rows === 0) {
    echo json_encode(['ok' => true, 'db_online' => true, 'unread' => 0, 'last_id' => null, 'last_created_at' => null]);
    exit;
}

$unread = 0; $last_id = null; $last_created_at = null;
$res = $conn->query("SELECT 
        SUM(CASE WHEN status='unread' THEN 1 ELSE 0 END) AS unread_count,
        MAX(id) AS last_id,
        MAX(created_at) AS last_created_at
    FROM notifications");
if ($res) {
    $row = $res->fetch_assoc();
    $unread = (int)($row['unread_count'] ?? 0);
    $last_id = $row['last_id'] !== null ? (int)$row['last_id'] : null;
    $last_created_at = $row['last_created_at'] ?? null;
}

echo json_encode([
    'ok' => true,
    'db_online' => true,
    'unread' => $unread,
    'last_id' => $last_id,
    'last_created_at' => $last_created_at,
]);

$conn->close();
exit;
