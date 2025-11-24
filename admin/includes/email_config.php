<?php
/**
 * Email Configuration and Helper Functions
 * Uses PHPMailer for sending email alerts
 */

require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email configuration constants
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'fnsclr1418@gmail.com'); // Update with your Gmail
define('SMTP_PASSWORD', 'nyrh gitr ijwp ncxr'); // Update with your Gmail App Password
define('SMTP_ENCRYPTION', 'tls');
define('SYSTEM_EMAIL_FROM', 'fnsclr1418@gmail.com'); // Update with your Gmail
define('SYSTEM_EMAIL_NAME', 'Equipment Kiosk System');

/**
 * Check if email alerts are enabled in system settings
 */
function isEmailAlertsEnabled($conn) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'enable_email_alerts'");
        if (!$stmt) return false;
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            return ($row['setting_value'] == '1');
        }
        $stmt->close();
        return false;
    } catch (Exception $e) {
        error_log("Error checking email alerts status: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email using PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $recipientName Recipient name (optional)
 * @return bool Success status
 */
function sendEmail($to, $subject, $body, $recipientName = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SYSTEM_EMAIL_FROM, SYSTEM_EMAIL_NAME);
        $mail->addAddress($to, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body); // Plain text version
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send overdue equipment notification
 */
function sendOverdueNotification($conn, $userId, $equipmentName, $daysOverdue) {
    if (!isEmailAlertsEnabled($conn)) {
        return false;
    }
    
    // Get user email
    $stmt = $conn->prepare("SELECT email, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows == 0) {
        $stmt->close();
        return false;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (empty($user['email'])) {
        return false;
    }
    
    $subject = "Overdue Equipment Reminder - " . $equipmentName;
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #006633; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
            .alert-box { background: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin: 20px 0; }
            .footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 0.9em; color: #666; border-radius: 0 0 8px 8px; }
            .btn { display: inline-block; padding: 12px 24px; background: #006633; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Equipment Kiosk System</h1>
            </div>
            <div class='content'>
                <h2>Overdue Equipment Reminder</h2>
                <p>Dear {$user['full_name']},</p>
                
                <div class='alert-box'>
                    <strong>⚠️ Overdue Notice</strong><br>
                    You have equipment that is <strong>{$daysOverdue} day(s) overdue</strong>.
                </div>
                
                <p><strong>Equipment:</strong> {$equipmentName}</p>
                
                <p>Please return this equipment as soon as possible to avoid additional penalties.</p>
                
                <p>If you have already returned this equipment, please disregard this message.</p>
                
                <p>Thank you for your cooperation.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " De La Salle ASMC - Equipment Kiosk System</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body, $user['full_name']);
}

/**
 * Send equipment borrowed notification
 */
function sendBorrowNotification($conn, $userId, $equipmentName, $expectedReturnDate) {
    if (!isEmailAlertsEnabled($conn)) {
        return false;
    }
    
    // Get user email
    $stmt = $conn->prepare("SELECT email, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows == 0) {
        $stmt->close();
        return false;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (empty($user['email'])) {
        return false;
    }
    
    $subject = "Equipment Borrowed - " . $equipmentName;
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #006633; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
            .info-box { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 20px 0; }
            .footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 0.9em; color: #666; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Equipment Kiosk System</h1>
            </div>
            <div class='content'>
                <h2>Equipment Borrowed Successfully</h2>
                <p>Dear {$user['full_name']},</p>
                
                <div class='info-box'>
                    <strong>✓ Borrow Confirmed</strong><br>
                    You have successfully borrowed equipment from the kiosk.
                </div>
                
                <p><strong>Equipment:</strong> {$equipmentName}</p>
                <p><strong>Expected Return Date:</strong> {$expectedReturnDate}</p>
                
                <p>Please return the equipment on or before the expected return date to avoid penalties.</p>
                
                <p>Thank you for using the Equipment Kiosk System.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " De La Salle ASMC - Equipment Kiosk System</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body, $user['full_name']);
}

/**
 * Send equipment returned notification
 */
function sendReturnNotification($conn, $userId, $equipmentName, $condition) {
    if (!isEmailAlertsEnabled($conn)) {
        return false;
    }
    
    // Get user email
    $stmt = $conn->prepare("SELECT email, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows == 0) {
        $stmt->close();
        return false;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (empty($user['email'])) {
        return false;
    }
    
    $subject = "Equipment Returned - " . $equipmentName;
    
    $conditionColor = ($condition == 'Good') ? '#4caf50' : '#ff9800';
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #006633; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
            .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0; }
            .footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 0.9em; color: #666; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Equipment Kiosk System</h1>
            </div>
            <div class='content'>
                <h2>Equipment Returned Successfully</h2>
                <p>Dear {$user['full_name']},</p>
                
                <div class='info-box'>
                    <strong>✓ Return Confirmed</strong><br>
                    Your equipment has been successfully returned.
                </div>
                
                <p><strong>Equipment:</strong> {$equipmentName}</p>
                <p><strong>Condition:</strong> <span style='color: {$conditionColor}; font-weight: bold;'>{$condition}</span></p>
                
                <p>Thank you for returning the equipment on time and in good condition.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " De La Salle ASMC - Equipment Kiosk System</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body, $user['full_name']);
}

/**
 * Send low stock alert to admin
 */
function sendLowStockAlert($conn, $equipmentName, $currentQuantity, $threshold = 5) {
    if (!isEmailAlertsEnabled($conn)) {
        return false;
    }
    
    // Get admin email from system settings
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'contact_email'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows == 0) {
        $stmt->close();
        return false;
    }
    
    $adminEmail = $result->fetch_assoc()['setting_value'];
    $stmt->close();
    
    if (empty($adminEmail)) {
        return false;
    }
    
    $subject = "Low Stock Alert - " . $equipmentName;
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #c62828; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
            .alert-box { background: #ffebee; border-left: 4px solid #c62828; padding: 15px; margin: 20px 0; }
            .footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 0.9em; color: #666; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>⚠️ Low Stock Alert</h1>
            </div>
            <div class='content'>
                <h2>Equipment Stock Running Low</h2>
                
                <div class='alert-box'>
                    <strong>Action Required</strong><br>
                    The following equipment is running low on stock.
                </div>
                
                <p><strong>Equipment:</strong> {$equipmentName}</p>
                <p><strong>Current Quantity:</strong> {$currentQuantity}</p>
                <p><strong>Threshold:</strong> {$threshold}</p>
                
                <p>Please consider restocking this equipment to ensure availability.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " De La Salle ASMC - Equipment Kiosk System</p>
                <p>This is an automated alert from the Equipment Kiosk System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($adminEmail, $subject, $body, 'Administrator');
}

/**
 * Send unauthorized access alert to admin
 */
function sendUnauthorizedAccessAlert($conn, $rfidTag, $attemptTime = null) {
    if (!isEmailAlertsEnabled($conn)) {
        return false;
    }
    
    // Get admin email from system settings
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'contact_email'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows == 0) {
        $stmt->close();
        return false;
    }
    
    $row = $result->fetch_assoc();
    $adminEmail = $row['setting_value'];
    $stmt->close();
    
    if (empty($adminEmail)) {
        return false;
    }
    
    $attemptTime = $attemptTime ?? date('F j, Y g:i A');
    
    $subject = "Security Alert - Unauthorized Kiosk Access Attempt";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
            .alert-box { background: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .info-box { background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 10px 10px; }
            .icon { font-size: 48px; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='icon'>⚠️</div>
                <h1>Security Alert</h1>
                <p>Unauthorized Access Attempt Detected</p>
            </div>
            <div class='content'>
                <h2>Unauthorized Kiosk Access</h2>
                
                <div class='alert-box'>
                    <strong>⚠️ Security Notice:</strong> An unauthorized RFID card was scanned at the equipment kiosk.
                </div>
                
                <div class='info-box'>
                    <p><strong>RFID Tag:</strong> {$rfidTag}</p>
                    <p><strong>Attempt Time:</strong> {$attemptTime}</p>
                    <p><strong>Location:</strong> Equipment Kiosk</p>
                    <p><strong>Status:</strong> Access Denied</p>
                </div>
                
                <h3>Recommended Actions:</h3>
                <ul>
                    <li>Verify if this RFID tag should be registered</li>
                    <li>Check if user needs to be added to the system</li>
                    <li>Monitor for repeated unauthorized attempts</li>
                    <li>Review security camera footage if available</li>
                </ul>
                
                <p><strong>Note:</strong> This could be a new student/staff member who needs to be registered in the system.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " De La Salle ASMC - Equipment Kiosk System</p>
                <p>This is an automated security alert. Please review and take appropriate action.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($adminEmail, $subject, $body, 'Administrator');
}

/**
 * Send maintenance mode notification to admin
 */
function sendMaintenanceModeAlert($conn, $isEnabled, $changedBy = 'System') {
    if (!isEmailAlertsEnabled($conn)) {
        return false;
    }
    
    // Get admin email from system settings
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'contact_email'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows == 0) {
        $stmt->close();
        return false;
    }
    
    $row = $result->fetch_assoc();
    $adminEmail = $row['setting_value'];
    $stmt->close();
    
    if (empty($adminEmail)) {
        return false;
    }
    
    $status = $isEnabled ? 'ENABLED' : 'DISABLED';
    $statusColor = $isEnabled ? '#ff9800' : '#4caf50';
    $icon = $isEnabled ? '🔧' : '✅';
    $changeTime = date('F j, Y g:i A');
    
    $subject = "Maintenance Mode " . $status . " - Equipment Kiosk System";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, {$statusColor} 0%, " . ($isEnabled ? '#f57c00' : '#388e3c') . " 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
            .status-box { background: " . ($isEnabled ? '#fff3cd' : '#e8f5e9') . "; border-left: 4px solid {$statusColor}; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .info-box { background: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 10px 10px; }
            .icon { font-size: 48px; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='icon'>{$icon}</div>
                <h1>Maintenance Mode {$status}</h1>
                <p>System Status Update</p>
            </div>
            <div class='content'>
                <h2>Kiosk Status Changed</h2>
                
                <div class='status-box'>
                    <strong>" . ($isEnabled ? '🔧' : '✅') . " Maintenance Mode:</strong> {$status}
                </div>
                
                <div class='info-box'>
                    <p><strong>Changed By:</strong> {$changedBy}</p>
                    <p><strong>Change Time:</strong> {$changeTime}</p>
                    <p><strong>Current Status:</strong> " . ($isEnabled ? 'Kiosk is in maintenance mode' : 'Kiosk is operational') . "</p>
                </div>
                
                " . ($isEnabled ? "
                <h3>Maintenance Mode Active:</h3>
                <ul>
                    <li>Users cannot access the kiosk</li>
                    <li>Maintenance message displayed to users</li>
                    <li>Borrowing and returning disabled</li>
                    <li>Admin panel remains accessible</li>
                </ul>
                <p><strong>Remember:</strong> Disable maintenance mode when work is complete to restore normal operations.</p>
                " : "
                <h3>Normal Operations Restored:</h3>
                <ul>
                    <li>Kiosk is now accessible to users</li>
                    <li>Borrowing and returning enabled</li>
                    <li>All features operational</li>
                </ul>
                <p><strong>Status:</strong> System is back to normal operations.</p>
                ") . "
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " De La Salle ASMC - Equipment Kiosk System</p>
                <p>This is an automated system notification.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($adminEmail, $subject, $body, 'Administrator');
}
?>
