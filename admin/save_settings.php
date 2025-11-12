<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include email configuration
require_once 'includes/email_config.php';

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if system_settings table exists
$table_check = $conn->query("SHOW TABLES LIKE 'system_settings'");
if (!$table_check || $table_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Settings table does not exist. Please run database setup.']);
    exit;
}

// Get POST data
$settings_json = isset($_POST['settings']) ? $_POST['settings'] : '';
$settings = json_decode($settings_json, true);

if (empty($settings) || !is_array($settings)) {
    echo json_encode(['success' => false, 'message' => 'No settings provided']);
    exit;
}

// Check if maintenance mode is being changed
$old_maintenance_mode = null;
$new_maintenance_mode = null;
if (isset($settings['maintenance_mode'])) {
    $check_maintenance = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
    $check_maintenance->execute();
    $result_maintenance = $check_maintenance->get_result();
    if ($result_maintenance && $result_maintenance->num_rows > 0) {
        $row = $result_maintenance->fetch_assoc();
        $old_maintenance_mode = $row['setting_value'];
    }
    $new_maintenance_mode = $settings['maintenance_mode'];
    $check_maintenance->close();
}

$conn->begin_transaction();

try {
    foreach ($settings as $key => $value) {
        // Sanitize key and value
        $key = $conn->real_escape_string($key);
        $value = $conn->real_escape_string($value);
        
        // Check if setting exists
        $check = $conn->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
        $check->bind_param("s", $key);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing setting
            $update = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $update->bind_param("ss", $value, $key);
            $update->execute();
            $update->close();
        } else {
            // Insert new setting
            $insert = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $insert->bind_param("ss", $key, $value);
            $insert->execute();
            $insert->close();
        }
        
        $check->close();
    }
    
    $conn->commit();
    
    // Send email if maintenance mode was changed
    if ($old_maintenance_mode !== null && $new_maintenance_mode !== null && $old_maintenance_mode !== $new_maintenance_mode) {
        $admin_username = $_SESSION['admin_username'] ?? 'Administrator';
        $is_enabled = ($new_maintenance_mode == '1');
        sendMaintenanceModeAlert($conn, $is_enabled, $admin_username);
    }
    
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $e->getMessage()]);
}

$conn->close();
?>
