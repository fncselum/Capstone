<?php
// Migration: add importance_level to inventory
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$mysqli = @new mysqli($host, $user, $password, $dbname);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error);
}

$result = $mysqli->query("SHOW COLUMNS FROM inventory LIKE 'importance_level'");
if ($result && $result->num_rows === 0) {
    $sql = "ALTER TABLE inventory ADD COLUMN importance_level VARCHAR(32) NULL AFTER borrow_period_days";
    if ($mysqli->query($sql)) {
        echo "Added inventory.importance_level\n";
    } else {
        echo "Failed to add column: " . $mysqli->error . "\n";
    }
} else {
    echo "Column importance_level already exists.\n";
}

$mysqli->close();
