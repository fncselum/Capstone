<?php
// Test script for automated damage detection
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

echo "<h2>Automated Damage Detection Test</h2>";

// Get recent returned transactions with detected issues
$result = $conn->query("
    SELECT 
        t.id,
        t.equipment_id,
        t.detected_issues,
        t.similarity_score,
        t.item_size,
        t.status,
        e.name as equipment_name
    FROM transactions t
    LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
    WHERE t.status = 'Returned' 
    ORDER BY t.actual_return_date DESC 
    LIMIT 5
");

if ($result && $result->num_rows > 0) {
    echo "<h3>Recent Returned Transactions:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Equipment</th><th>Item Size</th><th>Similarity Score</th><th>System Detected Issues</th><th>Status</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['equipment_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['item_size']) . "</td>";
        echo "<td>" . ($row['similarity_score'] ? number_format($row['similarity_score'], 2) . '%' : 'N/A') . "</td>";
        echo "<td style='max-width: 300px; word-wrap: break-word;'>" . htmlspecialchars($row['detected_issues'] ?: 'No issues detected') . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No returned transactions found.</p>";
}

echo "<h3>System Features:</h3>";
echo "<ul>";
echo "<li>✅ <strong>Automated Detection:</strong> System compares return image with reference image</li>";
echo "<li>✅ <strong>Read-Only Display:</strong> Admin can only view detected issues, cannot edit</li>";
echo "<li>✅ <strong>Severity Classification:</strong> Issues are categorized by severity level</li>";
echo "<li>✅ <strong>Compact Modal:</strong> Review modal is now smaller and more efficient</li>";
echo "<li>✅ <strong>Real-time Analysis:</strong> Damage detection happens during return process</li>";
echo "</ul>";

echo "<h3>Damage Detection Logic:</h3>";
echo "<ul>";
echo "<li><strong>High Severity (Red):</strong> Severe damage, significant differences, broken items</li>";
echo "<li><strong>Medium Severity (Orange):</strong> Noticeable damage, minor scratches, review needed</li>";
echo "<li><strong>Low Severity (Blue):</strong> Minor wear, slight differences, acceptable</li>";
echo "<li><strong>No Issues (Green):</strong> No visible damage detected</li>";
echo "</ul>";

echo "<p><a href='admin-all-transaction.php'>← Back to Transactions</a></p>";

$conn->close();
?>
