<?php
// Migration to add unique index on users.email
// Usage: visit /admin/migrations/add_unique_index_users_email.php while logged in as admin

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

function indexExists($conn, $table, $indexName) {
    $table = $conn->real_escape_string($table);
    $indexName = $conn->real_escape_string($indexName);
    $db = $conn->real_escape_string($conn->query('SELECT DATABASE() db')->fetch_assoc()['db']);
    $sql = "SELECT COUNT(1) AS cnt FROM information_schema.statistics WHERE table_schema='$db' AND table_name='$table' AND index_name='$indexName'";
    $res = $conn->query($sql);
    if ($res && ($row = $res->fetch_assoc())) { return (int)$row['cnt'] > 0; }
    return false;
}

try {
    if (!columnExists($conn, 'users', 'email')) {
        echo "ERROR: users.email column does not exist. Please run add_user_email.php first.\n";
        exit;
    }

    if (indexExists($conn, 'users', 'uniq_users_email')) {
        echo "OK: Unique index uniq_users_email already exists. Nothing to do.\n";
        exit;
    }

    // Before making unique, check for duplicates and abort if found
    $dupCheck = $conn->query("SELECT email, COUNT(*) c FROM users WHERE email IS NOT NULL AND email <> '' GROUP BY email HAVING c > 1 LIMIT 1");
    if ($dupCheck && $dupCheck->num_rows > 0) {
        $row = $dupCheck->fetch_assoc();
        echo "ERROR: Duplicate email found (" . $row['email'] . ") . Resolve duplicates before adding a UNIQUE index.\n";
        exit;
    }

    // Add unique index
    if (!$conn->query("ALTER TABLE users ADD UNIQUE KEY uniq_users_email (email)")) {
        throw new Exception('Failed to add unique index: ' . $conn->error);
    }

    echo "SUCCESS: Added unique index uniq_users_email on users.email.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}
