<?php
/**
 * Cron Job: Check for Overdue Equipment and Send Email Reminders
 * 
 * This script should be run daily (e.g., via Windows Task Scheduler or cron)
 * Example: Run at 9:00 AM every day
 * 
 * Windows Task Scheduler Command:
 * C:\xampp\php\php.exe "C:\xampp\htdocs\Capstone\admin\cron_check_overdue.php"
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Include email configuration
require_once __DIR__ . '/includes/email_config.php';

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("Cron Job Failed - Database connection error: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Log start
$log_message = "\n" . str_repeat("=", 60) . "\n";
$log_message .= "Overdue Check Cron Job Started: " . date('Y-m-d H:i:s') . "\n";
$log_message .= str_repeat("=", 60) . "\n";

// Check if email alerts are enabled
if (!isEmailAlertsEnabled($conn)) {
    $log_message .= "Email alerts are disabled in system settings. Skipping email notifications.\n";
    error_log($log_message);
    $conn->close();
    exit;
}

// Get all overdue transactions
$query = "SELECT 
            t.id AS transaction_id,
            t.user_id,
            t.equipment_id,
            t.expected_return_date,
            t.transaction_date,
            t.status,
            e.name AS equipment_name,
            u.email,
            u.first_name,
            u.last_name,
            DATEDIFF(CURDATE(), DATE(t.expected_return_date)) AS days_overdue
          FROM transactions t
          JOIN users u ON t.user_id = u.id
          JOIN equipment e ON t.equipment_id = e.rfid_tag
          WHERE t.transaction_type = 'Borrow'
          AND t.status = 'Active'
          AND DATE(t.expected_return_date) < CURDATE()
          AND u.email IS NOT NULL
          AND u.email != ''
          ORDER BY days_overdue DESC";

$result = $conn->query($query);

if (!$result) {
    $log_message .= "Query Error: " . $conn->error . "\n";
    error_log($log_message);
    $conn->close();
    exit;
}

$total_overdue = $result->num_rows;
$emails_sent = 0;
$emails_failed = 0;

$log_message .= "Total overdue transactions found: $total_overdue\n\n";

if ($total_overdue > 0) {
    while ($row = $result->fetch_assoc()) {
        $transaction_id = $row['transaction_id'];
        $user_id = $row['user_id'];
        $equipment_name = $row['equipment_name'];
        $days_overdue = $row['days_overdue'];
        $user_email = $row['email'];
        $user_name = $row['first_name'] . ' ' . $row['last_name'];
        
        $log_message .= "Processing Transaction #$transaction_id:\n";
        $log_message .= "  - User: $user_name ($user_email)\n";
        $log_message .= "  - Equipment: $equipment_name\n";
        $log_message .= "  - Days Overdue: $days_overdue\n";
        
        // Send overdue notification
        $email_sent = sendOverdueNotification($conn, $user_id, $equipment_name, $days_overdue);
        
        if ($email_sent) {
            $emails_sent++;
            $log_message .= "  - Email Status: ✓ SENT\n";
            
            // Log the notification in database (optional)
            $log_stmt = $conn->prepare("INSERT INTO email_logs (user_id, email_type, equipment_name, transaction_id, sent_at) VALUES (?, 'overdue', ?, ?, NOW())");
            if ($log_stmt) {
                $log_stmt->bind_param("isi", $user_id, $equipment_name, $transaction_id);
                $log_stmt->execute();
                $log_stmt->close();
            }
        } else {
            $emails_failed++;
            $log_message .= "  - Email Status: ✗ FAILED\n";
        }
        
        $log_message .= "\n";
    }
} else {
    $log_message .= "No overdue transactions found. All equipment returned on time!\n";
}

// Summary
$log_message .= str_repeat("-", 60) . "\n";
$log_message .= "Summary:\n";
$log_message .= "  - Total Overdue: $total_overdue\n";
$log_message .= "  - Emails Sent: $emails_sent\n";
$log_message .= "  - Emails Failed: $emails_failed\n";
$log_message .= str_repeat("=", 60) . "\n";

// Log to file
$log_file = __DIR__ . '/logs/overdue_check.log';
$log_dir = dirname($log_file);

if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}

file_put_contents($log_file, $log_message, FILE_APPEND);

// Also log to PHP error log
error_log($log_message);

// Output for manual testing
echo $log_message;

$conn->close();
?>
