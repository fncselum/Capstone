<?php
session_start();

// Simulate admin login for testing
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Update Test</h2>";

// Get a guideline to test with
$result = $conn->query("SELECT * FROM penalty_guidelines LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    echo "<h3>Testing with Guideline ID: " . $row['id'] . "</h3>";
    echo "<p><strong>Current Title:</strong> " . htmlspecialchars($row['title']) . "</p>";
    echo "<p><strong>Current Description:</strong> " . htmlspecialchars(substr($row['penalty_description'], 0, 100)) . "...</p>";
    echo "<p><strong>Current Amount:</strong> ₱" . number_format($row['penalty_amount'], 2) . "</p>";
    echo "<p><strong>Last Updated:</strong> " . $row['updated_at'] . "</p>";
    
    // Test update
    $test_title = "TEST UPDATE - " . date('H:i:s');
    $test_amount = 99.99;
    
    $stmt = $conn->prepare(
        "UPDATE penalty_guidelines 
         SET title = ?, 
             penalty_amount = ?, 
             updated_at = NOW() 
         WHERE id = ?"
    );
    $stmt->bind_param('sdi', $test_title, $test_amount, $row['id']);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'><strong>✅ UPDATE SUCCESSFUL!</strong></p>";
        
        // Verify the update
        $verify = $conn->query("SELECT * FROM penalty_guidelines WHERE id = " . $row['id']);
        if ($verify && $updated = $verify->fetch_assoc()) {
            echo "<h3>After Update:</h3>";
            echo "<p><strong>New Title:</strong> " . htmlspecialchars($updated['title']) . "</p>";
            echo "<p><strong>New Amount:</strong> ₱" . number_format($updated['penalty_amount'], 2) . "</p>";
            echo "<p><strong>New Updated Time:</strong> " . $updated['updated_at'] . "</p>";
        }
        
        // Restore original values
        $restore = $conn->prepare(
            "UPDATE penalty_guidelines 
             SET title = ?, 
                 penalty_amount = ?, 
                 updated_at = NOW() 
             WHERE id = ?"
        );
        $restore->bind_param('sdi', $row['title'], $row['penalty_amount'], $row['id']);
        $restore->execute();
        echo "<p style='color: blue;'>✅ Original values restored</p>";
        
    } else {
        echo "<p style='color: red;'><strong>❌ UPDATE FAILED:</strong> " . $stmt->error . "</p>";
    }
    
    $stmt->close();
} else {
    echo "<p style='color: red;'>No guidelines found in database. Please create one first.</p>";
}

$conn->close();

echo "<br><br><a href='admin-penalty-guideline.php'>← Back to Penalty Guidelines</a>";
?>
