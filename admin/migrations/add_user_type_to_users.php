<?php
// Migration: add user_type to users table (Student/Teacher)
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$mysqli = @new mysqli($host, $user, $password, $dbname);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error);
}

$check = $mysqli->query("SHOW TABLES LIKE 'users'");
if (!$check || $check->num_rows === 0) {
    echo "users table does not exist.\n";
    exit;
}

$result = $mysqli->query("SHOW COLUMNS FROM users LIKE 'user_type'");
if ($result && $result->num_rows === 0) {
    $sql = "ALTER TABLE users ADD COLUMN user_type VARCHAR(20) NOT NULL DEFAULT 'Student' AFTER student_id";
    if ($mysqli->query($sql)) {
        echo "Added users.user_type with default 'Student'\n";
    } else {
        echo "Failed to add users.user_type: " . $mysqli->error . "\n";
    }
} else {
    echo "Column user_type already exists.\n";
}

$mysqli->close();
