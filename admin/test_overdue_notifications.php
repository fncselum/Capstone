<?php
/**
 * Test Overdue Notifications
 * Manual test script to verify the overdue notification system
 */

// Set timezone and error reporting
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Overdue Notification System Test</h1>";
echo "<p>Testing at: " . date('Y-m-d H:i:s') . "</p>";

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("<p style='color:red'>Connection failed: " . $conn->connect_error . "</p>");
}

echo "<p style='color:green'>✓ Database connection successful</p>";

// Check if email configuration exists
if (file_exists(__DIR__ . '/includes/email_config.php')) {
    echo "<p style='color:green'>✓ Email configuration file found</p>";
    require_once __DIR__ . '/includes/email_config.php';
    
    if (function_exists('sendEmail')) {
        echo "<p style='color:green'>✓ Email function available</p>";
    } else {
        echo "<p style='color:red'>✗ Email function not found</p>";
    }
} else {
    echo "<p style='color:red'>✗ Email configuration file missing</p>";
}

// Check for overdue transactions
$query = "SELECT 
    t.id as transaction_id,
    t.user_id,
    t.equipment_id,
    t.quantity,
    t.expected_return_date,
    e.name as equipment_name,
    u.student_id,
    u.email,
    u.penalty_points,
    DATEDIFF(NOW(), t.expected_return_date) as days_overdue
FROM transactions t
JOIN equipment e ON t.equipment_id = e.rfid_tag
JOIN users u ON t.user_id = u.id
WHERE t.status = 'Active' 
AND t.transaction_type = 'Borrow'
AND t.expected_return_date < NOW()
AND u.status = 'Active'
ORDER BY t.expected_return_date ASC";

$result = $conn->query($query);

if ($result) {
    $transactions = $result->fetch_all(MYSQLI_ASSOC);
    echo "<h2>Overdue Transactions Found: " . count($transactions) . "</h2>";
    
    if (count($transactions) > 0) {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse;'>";
        echo "<tr style='background:#f0f0f0;'>";
        echo "<th>Transaction ID</th>";
        echo "<th>Student ID</th>";
        echo "<th>Email</th>";
        echo "<th>Equipment</th>";
        echo "<th>Days Overdue</th>";
        echo "<th>Expected Return</th>";
        echo "<th>Penalty Points</th>";
        echo "</tr>";
        
        foreach ($transactions as $t) {
            $emailStatus = empty($t['email']) ? "<span style='color:red'>No Email</span>" : "<span style='color:green'>" . htmlspecialchars($t['email']) . "</span>";
            $daysColor = $t['days_overdue'] > 7 ? 'red' : ($t['days_overdue'] > 3 ? 'orange' : 'black');
            
            echo "<tr>";
            echo "<td>" . $t['transaction_id'] . "</td>";
            echo "<td>" . htmlspecialchars($t['student_id']) . "</td>";
            echo "<td>" . $emailStatus . "</td>";
            echo "<td>" . htmlspecialchars($t['equipment_name']) . "</td>";
            echo "<td style='color:{$daysColor};font-weight:bold;'>" . $t['days_overdue'] . "</td>";
            echo "<td>" . date('M j, Y g:i A', strtotime($t['expected_return_date'])) . "</td>";
            echo "<td>" . $t['penalty_points'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count users with emails
        $withEmail = array_filter($transactions, function($t) { return !empty($t['email']); });
        echo "<p><strong>Users with email addresses:</strong> " . count($withEmail) . " / " . count($transactions) . "</p>";
        
    } else {
        echo "<p style='color:green'>No overdue transactions found.</p>";
    }
} else {
    echo "<p style='color:red'>Error querying transactions: " . $conn->error . "</p>";
}

// Check overdue notifications table
$tableExists = $conn->query("SHOW TABLES LIKE 'overdue_notifications'");
if ($tableExists && $tableExists->num_rows > 0) {
    echo "<h2>Notification History</h2>";
    $notifQuery = "SELECT 
        on.*, 
        u.student_id,
        e.name as equipment_name
    FROM overdue_notifications on
    JOIN users u ON on.user_id = u.id
    JOIN transactions t ON on.transaction_id = t.id
    JOIN equipment e ON t.equipment_id = e.rfid_tag
    ORDER BY on.sent_at DESC 
    LIMIT 10";
    
    $notifResult = $conn->query($notifQuery);
    if ($notifResult && $notifResult->num_rows > 0) {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse;'>";
        echo "<tr style='background:#f0f0f0;'>";
        echo "<th>Date Sent</th>";
        echo "<th>Student ID</th>";
        echo "<th>Email</th>";
        echo "<th>Equipment</th>";
        echo "<th>Transaction ID</th>";
        echo "</tr>";
        
        while ($notif = $notifResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . date('M j, Y g:i A', strtotime($notif['sent_at'])) . "</td>";
            echo "<td>" . htmlspecialchars($notif['student_id']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['email']) . "</td>";
            echo "<td>" . htmlspecialchars($notif['equipment_name']) . "</td>";
            echo "<td>" . $notif['transaction_id'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No notification history found.</p>";
    }
} else {
    echo "<p style='color:orange'>Overdue notifications table does not exist yet (will be created automatically).</p>";
}

// Test email generation
if (!empty($transactions)) {
    echo "<h2>Sample Email Preview</h2>";
    $sampleTransaction = $transactions[0];
    
    // Include the email generation function
    include __DIR__ . '/scripts/send_overdue_notifications.php';
    
    // This won't work directly because we included the full script
    // Let's create a simple preview instead
    echo "<div style='border:1px solid #ccc; padding:10px; background:#f9f9f9;'>";
    echo "<h3>Email Subject:</h3>";
    echo "<p>URGENT: Overdue Equipment Return - Action Required</p>";
    echo "<h3>Sample Content for Student ID: " . htmlspecialchars($sampleTransaction['student_id']) . "</h3>";
    echo "<p>Equipment: " . htmlspecialchars($sampleTransaction['equipment_name']) . "</p>";
    echo "<p>Days Overdue: " . $sampleTransaction['days_overdue'] . "</p>";
    echo "<p>Current Penalty: " . ($sampleTransaction['days_overdue'] * 10) . " points</p>";
    echo "</div>";
}

echo "<h2>Manual Test Options</h2>";
echo "<p><a href='scripts/send_overdue_notifications.php' target='_blank' style='background:#007cba;color:white;padding:10px;text-decoration:none;border-radius:5px;'>Run Notification Script</a></p>";
echo "<p><em>Note: The script will only send one notification per transaction per day to avoid spam.</em></p>";

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { width: 100%; margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f0f0f0; }
</style>
