<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create system_settings table
$sql = "CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql) === TRUE) {
    // Insert default settings
    $defaults = [
        'system_name' => 'Equipment Management System',
        'institution_name' => 'De La Salle ASMC',
        'contact_email' => 'admin@dlsasmc.edu.ph',
        'max_borrow_days' => '7',
        'overdue_penalty_rate' => '10.00',
        'max_items_per_borrow' => '3',
        'enable_notifications' => '1',
        'enable_email_alerts' => '0',
        'maintenance_mode' => '0',
        'session_timeout' => '30',
        'items_per_page' => '20'
    ];
    
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    
    foreach ($defaults as $key => $value) {
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }
    
    $stmt->close();
    
    $_SESSION['success_message'] = 'System settings table created successfully!';
} else {
    $_SESSION['error_message'] = 'Error creating table: ' . $conn->error;
}

$conn->close();

// Redirect back to settings page
header('Location: admin-settings.php');
exit;
?>
