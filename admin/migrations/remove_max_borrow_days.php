<?php
// Migration: remove obsolete max_borrow_days from system_settings
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$mysqli = @new mysqli($host, $user, $password, $dbname);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error);
}

// Ensure table exists
$check = $mysqli->query("SHOW TABLES LIKE 'system_settings'");
if (!$check || $check->num_rows === 0) {
    echo "system_settings table does not exist. Nothing to remove.\n";
    exit;
}

$stmt = $mysqli->prepare("DELETE FROM system_settings WHERE setting_key = 'max_borrow_days'");
if ($stmt && $stmt->execute()) {
    echo "Removed setting max_borrow_days (if existed).\n";
} else {
    echo "Failed to remove max_borrow_days: " . $mysqli->error . "\n";
}

$mysqli->close();
