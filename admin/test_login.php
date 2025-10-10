<?php
require_once '../includes/db_connection.php';

echo "<h2>Login Debug Test</h2>";

// Test the password hash
$test_password = 'admin123';
$stored_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

echo "<h3>Password Test:</h3>";
echo "Testing password: " . $test_password . "<br>";
echo "Stored hash: " . $stored_hash . "<br>";
echo "Password verify result: " . (password_verify($test_password, $stored_hash) ? 'TRUE' : 'FALSE') . "<br><br>";

// Test database connection and user lookup
echo "<h3>Database Test:</h3>";
try {
    $stmt = $pdo->prepare("SELECT id, username, password, status FROM admin_users WHERE username = ?");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "User found in database:<br>";
        echo "ID: " . $admin['id'] . "<br>";
        echo "Username: " . $admin['username'] . "<br>";
        echo "Password Hash: " . $admin['password'] . "<br>";
        echo "Status: " . $admin['status'] . "<br>";
        
        echo "<br>Password verification with database hash: ";
        echo (password_verify($test_password, $admin['password']) ? 'TRUE' : 'FALSE') . "<br>";
    } else {
        echo "No admin user found in database!<br>";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

// Create a fresh password hash for testing
echo "<h3>Fresh Password Hash:</h3>";
$new_hash = password_hash('admin123', PASSWORD_DEFAULT);
echo "New hash for 'admin123': " . $new_hash . "<br>";
echo "Verify new hash: " . (password_verify('admin123', $new_hash) ? 'TRUE' : 'FALSE') . "<br>";
?>
