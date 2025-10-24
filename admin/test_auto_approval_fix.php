<?php
// Test script to verify auto-approval fix
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

echo "<h2>Auto-Approval Fix Verification</h2>";

// Check all transactions by item size and approval status
$result = $conn->query("
    SELECT 
        item_size,
        approval_status,
        COUNT(*) as total_count,
        SUM(CASE WHEN approved_by = 1 THEN 1 ELSE 0 END) as approved_by_1,
        SUM(CASE WHEN processed_by = 1 THEN 1 ELSE 0 END) as processed_by_1,
        SUM(CASE WHEN approved_by IS NULL THEN 1 ELSE 0 END) as approved_by_null,
        SUM(CASE WHEN processed_by IS NULL THEN 1 ELSE 0 END) as processed_by_null
    FROM transactions 
    GROUP BY item_size, approval_status 
    ORDER BY item_size, approval_status
");

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Item Size</th><th>Approval Status</th><th>Total</th><th>Approved By 1</th><th>Processed By 1</th><th>Approved By NULL</th><th>Processed By NULL</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['item_size']) . "</td>";
        echo "<td>" . htmlspecialchars($row['approval_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['total_count']) . "</td>";
        echo "<td style='color: " . ($row['approved_by_1'] > 0 ? 'green' : 'red') . ";'>" . htmlspecialchars($row['approved_by_1']) . "</td>";
        echo "<td style='color: " . ($row['processed_by_1'] > 0 ? 'green' : 'red') . ";'>" . htmlspecialchars($row['processed_by_1']) . "</td>";
        echo "<td style='color: " . ($row['approved_by_null'] > 0 ? 'red' : 'green') . ";'>" . htmlspecialchars($row['approved_by_null']) . "</td>";
        echo "<td style='color: " . ($row['processed_by_null'] > 0 ? 'red' : 'green') . ";'>" . htmlspecialchars($row['processed_by_null']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No transactions found.</p>";
}

echo "<h3>Summary</h3>";
echo "<ul>";
echo "<li>✅ <strong>Small & Medium Items:</strong> Should have approved_by = 1 and processed_by = 1 when auto-approved</li>";
echo "<li>✅ <strong>Large Items:</strong> Should have approved_by = NULL and processed_by = NULL when pending approval</li>";
echo "<li>✅ <strong>Red cells:</strong> Indicate issues that need attention</li>";
echo "<li>✅ <strong>Green cells:</strong> Indicate correct values</li>";
echo "</ul>";

echo "<p><a href='admin-all-transaction.php'>← Back to Transactions</a></p>";

$conn->close();
?>
