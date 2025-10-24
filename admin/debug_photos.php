<?php
// Debug script to check photo data
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

echo "<h2>Photo Debug Information</h2>";

// Get recent returned transactions
$result = $conn->query("SELECT id, equipment_id, status, actual_return_date FROM transactions WHERE status = 'Returned' ORDER BY actual_return_date DESC LIMIT 3");
if ($result && $result->num_rows > 0) {
    echo "<h3>Recent Returned Transactions:</h3>";
    while ($row = $result->fetch_assoc()) {
        echo "<p><strong>Transaction ID:</strong> " . $row['id'] . " | <strong>Status:</strong> " . $row['status'] . " | <strong>Return Date:</strong> " . $row['actual_return_date'] . "</p>";
        
        // Get photos for this transaction
        $photoResult = $conn->query("SELECT photo_type, file_path FROM transaction_photos WHERE transaction_id = " . $row['id']);
        if ($photoResult && $photoResult->num_rows > 0) {
            echo "<ul>";
            while ($photoRow = $photoResult->fetch_assoc()) {
                echo "<li><strong>" . $photoRow['photo_type'] . ":</strong> " . $photoRow['file_path'];
                
                // Check if file exists
                $fullPath = __DIR__ . '/../' . $photoRow['file_path'];
                if (file_exists($fullPath)) {
                    echo " ✅ <em>(File exists)</em>";
                } else {
                    echo " ❌ <em>(File missing)</em>";
                }
                echo "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No photos found for this transaction.</p>";
        }
        echo "<hr>";
    }
} else {
    echo "<p>No returned transactions found.</p>";
}

$conn->close();
?>
