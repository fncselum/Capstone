<?php
session_start();
header('Content-Type: application/json');

// Only allow signed-in kiosk users
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$targetId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_SESSION['user_id'] ?? 0);
if ($targetId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'capstone';

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $dbRes = $conn->query('SELECT DATABASE() db');
    $db = $dbRes && ($row = $dbRes->fetch_assoc()) ? $conn->real_escape_string($row['db']) : '';
    $sql = "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$table' AND COLUMN_NAME='$column'";
    $res = $conn->query($sql);
    if ($res && ($row = $res->fetch_assoc())) { return ((int)$row['cnt']) > 0; }
    return false;
}

try {
    $hasPath = columnExists($conn, 'users', 'photo_path');

    if (!$hasPath) {
        echo json_encode(['success' => false, 'message' => 'Photo storage not available']);
        exit;
    }

    $sql = "SELECT photo_path FROM users WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    $dataUrl = null;
    if ($row && !empty($row['photo_path'])) {
        // photo_path is a longblob containing binary image data
        $binaryData = $row['photo_path'];
        
        // Detect MIME type from binary data
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binaryData);
        
        // Fallback to JPEG if detection fails
        if (!in_array($mime, ['image/jpeg', 'image/png'])) {
            $mime = 'image/jpeg';
        }
        
        $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($binaryData);
    }

    echo json_encode(['success' => true, 'dataUrl' => $dataUrl]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
