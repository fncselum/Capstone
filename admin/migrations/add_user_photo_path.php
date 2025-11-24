<?php
// Simple migration to add users.photo_path for storing face verification image path
// Usage: visit this file in the browser while logged in as admin: /admin/migrations/add_user_photo_path.php

session_start();
require_once '../../includes/db_connection.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo "Unauthorized. Please log in as admin.\n";
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
    if (columnExists($conn, 'users', 'photo_path')) {
        echo "OK: users.photo_path already exists. Nothing to do.\n";
        exit;
    }

    // Add column photo_path and optional index
    $alter = "ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) NULL AFTER penalty_points";
    if (!$conn->query($alter)) {
        throw new Exception('Failed to add column: ' . $conn->error);
    }

    // Optional index to speed up retrievals by photo presence
    @$conn->query("CREATE INDEX idx_users_photo_path ON users (photo_path)");

    echo "SUCCESS: Added users.photo_path (VARCHAR(255) NULL).\n";
    echo "If needed, created index idx_users_photo_path.\n";

    // Ensure upload directory exists
    $uploadDir = realpath(__DIR__ . '/../../..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'user_photos';
    if (!is_dir($uploadDir)) {
        if (@mkdir($uploadDir, 0775, true)) {
            echo "Created uploads/user_photos directory.\n";
        } else {
            echo "WARNING: Could not create uploads/user_photos directory. Please create it manually and make it writable.\n";
        }
    } else {
        echo "OK: uploads/user_photos directory exists.\n";
    }

} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}
