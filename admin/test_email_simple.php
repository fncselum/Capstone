<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Unauthorized");
}

require_once 'includes/email_config.php';

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Email Configuration Test</h1>";
echo "<hr>";

// Test 1: Check if email alerts are enabled
echo "<h2>Test 1: Email Alerts Status</h2>";
$enabled = isEmailAlertsEnabled($conn);
echo "Email Alerts Enabled: " . ($enabled ? "<span style='color: green;'>✓ YES</span>" : "<span style='color: red;'>✗ NO</span>") . "<br>";
echo "<small>Go to System Settings → System tab to enable</small><br><br>";

// Test 2: Check email configuration
echo "<h2>Test 2: Email Configuration</h2>";
echo "SMTP Host: " . SMTP_HOST . "<br>";
echo "SMTP Port: " . SMTP_PORT . "<br>";
echo "SMTP Username: " . SMTP_USERNAME . "<br>";
echo "SMTP Password: " . (SMTP_PASSWORD ? str_repeat('*', 16) : "<span style='color: red;'>NOT SET</span>") . "<br>";
echo "From Email: " . SYSTEM_EMAIL_FROM . "<br><br>";

// Test 3: Check PHPMailer
echo "<h2>Test 3: PHPMailer Library</h2>";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<span style='color: green;'>✓ PHPMailer loaded successfully</span><br><br>";
} else {
    echo "<span style='color: red;'>✗ PHPMailer NOT found</span><br><br>";
}

// Test 4: Get admin email from settings
echo "<h2>Test 4: Admin Email</h2>";
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'contact_email'");
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    echo "Admin Email: " . htmlspecialchars($row['setting_value']) . "<br><br>";
} else {
    echo "<span style='color: red;'>Admin email not set in system settings</span><br><br>";
}
$stmt->close();

// Test 5: Send test low stock alert
if (isset($_GET['send_test']) && $_GET['send_test'] == '1') {
    echo "<h2>Test 5: Sending Test Low Stock Alert</h2>";
    $result = sendLowStockAlert($conn, 'Test Equipment', 3, 5);
    if ($result) {
        echo "<span style='color: green;'>✓ Test email sent successfully!</span><br>";
        echo "Check your inbox at: " . SYSTEM_EMAIL_FROM . "<br><br>";
    } else {
        echo "<span style='color: red;'>✗ Failed to send test email</span><br>";
        echo "Check PHP error logs for details<br><br>";
    }
}

$conn->close();
?>

<hr>
<p><a href="?send_test=1" style="background: #006633; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Send Test Low Stock Alert</a></p>
<p><a href="test_email.php">Go to Full Test Page</a> | <a href="admin-settings.php?tab=system">System Settings</a></p>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 50px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h1 { color: #006633; }
    h2 { color: #333; margin-top: 20px; }
</style>
