<?php
// Simple migration to add users.email for storing user email addresses
// Usage: visit this file in the browser while logged in as admin: /admin/migrations/add_user_email.php

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
    if (columnExists($conn, 'users', 'email')) {
        echo "OK: users.email already exists. Nothing to do.\n";
        exit;
    }

    // Add column email and optional index
    $alter = "ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER student_id";
    if (!$conn->query($alter)) {
        throw new Exception('Failed to add column: ' . $conn->error);
    }

    // Optional index for quick lookups by email
    @$conn->query("CREATE INDEX idx_users_email ON users (email)");

    echo "SUCCESS: Added users.email (VARCHAR(255) NULL).\n";
    echo "If needed, created index idx_users_email.\n";

} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}
