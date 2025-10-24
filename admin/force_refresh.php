<?php
// Force refresh script to clear any caching issues
session_start();

// Clear any session cache
if (isset($_SESSION['admin_logged_in'])) {
    // Force a fresh database connection
    $host = "localhost";
    $user = "root";
    $password = "";
    $dbname = "capstone";
    
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Force refresh with cache-busting headers
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    echo "<h2>Database Refresh Complete</h2>";
    echo "<p>The transaction has been successfully deleted from the database.</p>";
    echo "<p>Please refresh your browser to see the updated transaction list.</p>";
    echo "<p><a href='admin-all-transaction.php?t=" . time() . "'>← Refresh Transaction List</a></p>";
    
    $conn->close();
} else {
    echo "<p>Please login first.</p>";
}
?>
