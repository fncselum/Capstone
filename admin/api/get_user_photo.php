<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../../includes/db_connection.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid user id']);
    exit;
}

function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $db = $conn->real_escape_string($conn->query('SELECT DATABASE() db')->fetch_assoc()['db']);
    $sql = "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$table' AND COLUMN_NAME='$column'";
    $res = $conn->query($sql);
    if ($res && ($row = $res->fetch_assoc())) { return (int)$row['cnt'] > 0; }
    return false;
}

try {
    $hasBlob = columnExists($conn, 'users', 'user_photos');
    $hasPath = columnExists($conn, 'users', 'photo_path');

    $sql = "SELECT ".($hasBlob?"user_photos":"NULL AS user_photos").", ".($hasPath?"photo_path":"NULL AS photo_path")." FROM users WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    $dataUrl = null;
    if ($row) {
        if (!empty($row['user_photos'])) {
            $blob = $row['user_photos'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($blob);
            if ($mime !== 'image/jpeg' && $mime !== 'image/png') {
                $mime = 'image/jpeg';
            }
            $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($blob);
        } elseif (!empty($row['photo_path'])) {
            $path = realpath(__DIR__ . '/../../..' . DIRECTORY_SEPARATOR . $row['photo_path']);
            if ($path && is_file($path)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
                $content = @file_get_contents($path);
                if ($content !== false) {
                    $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($content);
                }
            }
        }
    }

    echo json_encode(['success' => true, 'dataUrl' => $dataUrl]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
