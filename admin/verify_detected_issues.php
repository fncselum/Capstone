<?php
// Simple verification script to check if detected_issues column works
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo "Please login first";
    exit;
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo "Database connection failed: " . $conn->connect_error;
    exit;
}

echo "<h2>Database Verification</h2>";

// Test 1: Check if column exists
$result = $conn->query("SHOW COLUMNS FROM transactions LIKE 'detected_issues'");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✓ detected_issues column exists</p>";
} else {
    echo "<p style='color: red;'>✗ detected_issues column not found</p>";
}

// Test 2: Try to select from the column
$result = $conn->query("SELECT id, detected_issues FROM transactions LIMIT 1");
if ($result) {
    echo "<p style='color: green;'>✓ Can query detected_issues column successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error querying detected_issues: " . $conn->error . "</p>";
}

// Test 3: Try to update the column
$testUpdate = $conn->query("UPDATE transactions SET detected_issues = 'Test update' WHERE id = 1 LIMIT 1");
if ($testUpdate) {
    echo "<p style='color: green;'>✓ Can update detected_issues column successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error updating detected_issues: " . $conn->error . "</p>";
}

$conn->close();
echo "<p><a href='admin-all-transaction.php'>← Back to Transactions</a></p>";
?>
