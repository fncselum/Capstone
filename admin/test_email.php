<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
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

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $testEmail = $_POST['test_email'] ?? '';
    
    if (!empty($testEmail) && filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $subject = "Test Email - Equipment Kiosk System";
        
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #006633; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                .success-box { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 20px 0; }
                .footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 0.9em; color: #666; border-radius: 0 0 8px 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Equipment Kiosk System</h1>
                </div>
                <div class='content'>
                    <h2>Email Configuration Test</h2>
                    
                    <div class='success-box'>
                        <strong>✓ Success!</strong><br>
                        Your email configuration is working correctly.
                    </div>
                    
                    <p>This is a test email sent from the Equipment Kiosk System.</p>
                    
                    <p><strong>Test Details:</strong></p>
                    <ul>
                        <li>Sent at: " . date('Y-m-d H:i:s') . "</li>
                        <li>SMTP Host: " . SMTP_HOST . "</li>
                        <li>SMTP Port: " . SMTP_PORT . "</li>
                    </ul>
                    
                    <p>If you received this email, your email alerts are configured correctly!</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " De La Salle ASMC - Equipment Kiosk System</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $result = sendEmail($testEmail, $subject, $body);
        
        if ($result) {
            $message = "Test email sent successfully to {$testEmail}! Please check your inbox.";
            $messageType = 'success';
        } else {
            $message = "Failed to send test email. Please check your email configuration in includes/email_config.php";
            $messageType = 'error';
        }
    } else {
        $message = "Please enter a valid email address.";
        $messageType = 'error';
    }
}

$emailAlertsEnabled = isEmailAlertsEnabled($conn);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email Configuration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #006633;
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1rem;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin: 20px 0;
        }

        .status-badge.enabled {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.disabled {
            background: #ffebee;
            color: #c62828;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #006633;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #006633;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn:hover {
            background: #004d26;
        }

        .btn-back {
            background: #666;
            margin-top: 15px;
        }

        .btn-back:hover {
            background: #444;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .message.success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }

        .message.error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        .config-info {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }

        .config-info h3 {
            color: #006633;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .config-info p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .config-info code {
            background: #e0e0e0;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-envelope"></i> Test Email Configuration</h1>
            <p>Send a test email to verify your email settings</p>
            
            <?php if ($emailAlertsEnabled): ?>
                <span class="status-badge enabled">
                    <i class="fas fa-check-circle"></i> Email Alerts Enabled
                </span>
            <?php else: ?>
                <span class="status-badge disabled">
                    <i class="fas fa-times-circle"></i> Email Alerts Disabled
                </span>
            <?php endif; ?>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?= $messageType ?>">
                <i class="fas fa-<?= $messageType == 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="test_email">
                    <i class="fas fa-envelope"></i> Test Email Address
                </label>
                <input 
                    type="email" 
                    id="test_email" 
                    name="test_email" 
                    placeholder="Enter email address to test"
                    required
                >
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
        </form>

        <a href="admin-settings.php?tab=system" style="text-decoration: none;">
            <button class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Settings
            </button>
        </a>

        <div class="config-info">
            <h3><i class="fas fa-info-circle"></i> Configuration Instructions</h3>
            <p><strong>1. Update Email Credentials:</strong></p>
            <p>Edit <code>admin/includes/email_config.php</code> and update:</p>
            <ul style="margin-left: 20px; color: #666;">
                <li>SMTP_USERNAME - Your Gmail address</li>
                <li>SMTP_PASSWORD - Your Gmail App Password</li>
                <li>SYSTEM_EMAIL_FROM - Your Gmail address</li>
            </ul>
            
            <p style="margin-top: 15px;"><strong>2. Enable Email Alerts:</strong></p>
            <p>Go to System Settings → System tab and enable "Email Alerts"</p>
            
            <p style="margin-top: 15px;"><strong>3. Gmail App Password:</strong></p>
            <p>Generate an App Password from your Google Account settings (Security → 2-Step Verification → App passwords)</p>
        </div>
    </div>
</body>
</html>
