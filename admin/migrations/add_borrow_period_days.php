<?php
// Migration: add borrow_period_days to inventory
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$mysqli = @new mysqli($host, $user, $password, $dbname);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error);
}

$result = $mysqli->query("SHOW COLUMNS FROM inventory LIKE 'borrow_period_days'");
if ($result && $result->num_rows === 0) {
    $sql = "ALTER TABLE inventory ADD COLUMN borrow_period_days INT NULL AFTER item_size";
    if ($mysqli->query($sql)) {
        echo "Added inventory.borrow_period_days\n";
    } else {
        echo "Failed to add column: " . $mysqli->error . "\n";
    }
} else {
    echo "Column borrow_period_days already exists.\n";
}

$mysqli->close();
