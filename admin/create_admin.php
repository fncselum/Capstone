<?php
// Simple script to create/update an admin user
require_once '../includes/db_connection.php';

// Admin credentials
$username = 'admin';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        // Update existing admin user with fresh password hash
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ?, status = 'Active' WHERE username = ?");
        $stmt->execute([$hashed_password, $username]);
        
        echo "Admin user updated successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
        echo "New password hash: " . $hashed_password . "<br>";
        echo "You can now login at <a href='login.php'>login.php</a>";
    } else {
        // Insert new admin user
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, status) VALUES (?, ?, 'Active')");
        $stmt->execute([$username, $hashed_password]);
        
        echo "Admin user created successfully!<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
        echo "Password hash: " . $hashed_password . "<br>";
        echo "You can now login at <a href='login.php'>login.php</a>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
