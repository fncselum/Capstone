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

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if unauthorized_access_logs table exists
$table_exists = false;
$table_check = $conn->query("SHOW TABLES LIKE 'unauthorized_access_logs'");
if ($table_check && $table_check->num_rows > 0) {
    $table_exists = true;
}

// Get unauthorized access logs
$unauthorized_logs = [];
if ($table_exists) {
    $query = "SELECT * FROM unauthorized_access_logs ORDER BY attempt_time DESC LIMIT 100";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $unauthorized_logs[] = $row;
        }
    }
}

// Get email logs if table exists
$email_logs = [];
$email_table_check = $conn->query("SHOW TABLES LIKE 'email_logs'");
if ($email_table_check && $email_table_check->num_rows > 0) {
    $query = "SELECT el.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email as user_email
              FROM email_logs el
              LEFT JOIN users u ON el.user_id = u.id
              ORDER BY el.sent_at DESC
              LIMIT 50";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $email_logs[] = $row;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security & Email Logs - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #006633 0%, #004d26 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .nav-buttons {
            margin-bottom: 20px;
        }

        .nav-buttons a {
            display: inline-block;
            padding: 10px 20px;
            background: #006633;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-right: 10px;
            transition: background 0.3s;
        }

        .nav-buttons a:hover {
            background: #004d26;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 24px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .tab.active {
            background: #006633;
            color: white;
            border-color: #006633;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card h2 {
            color: #006633;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        table th {
            background: #f5f5f5;
            color: #006633;
            font-weight: 600;
        }

        table tr:hover {
            background: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-warning {
            background: #fff3cd;
            color: #f57c00;
        }

        .badge-info {
            background: #e3f2fd;
            color: #1976d2;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #006633;
        }

        .stat-card .label {
            color: #666;
            margin-top: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        .setup-notice {
            background: #fff3cd;
            border-left: 4px solid #ff9800;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .setup-notice h3 {
            color: #f57c00;
            margin-bottom: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #006633;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #004d26;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Security & Email Logs</h1>
            <p>Monitor unauthorized access attempts and email notifications</p>
        </div>

        <div class="nav-buttons">
            <a href="admin-dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="admin-settings.php?tab=system"><i class="fas fa-cog"></i> System Settings</a>
            <a href="test_email_simple.php"><i class="fas fa-envelope"></i> Test Email</a>
        </div>

        <?php if (!$table_exists): ?>
        <div class="setup-notice">
            <h3><i class="fas fa-exclamation-triangle"></i> Database Setup Required</h3>
            <p>The unauthorized_access_logs table needs to be created to track security events.</p>
            <a href="#" class="btn" onclick="alert('Please run the SQL file: database/unauthorized_access_logs_table.sql in phpMyAdmin'); return false;">
                <i class="fas fa-database"></i> Setup Instructions
            </a>
        </div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" onclick="switchTab('unauthorized')">
                <i class="fas fa-exclamation-triangle"></i> Unauthorized Access
            </div>
            <div class="tab" onclick="switchTab('emails')">
                <i class="fas fa-envelope"></i> Email Logs
            </div>
        </div>

        <!-- Unauthorized Access Tab -->
        <div id="unauthorized-tab" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon" style="color: #d32f2f;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="value"><?= count($unauthorized_logs) ?></div>
                    <div class="label">Total Attempts</div>
                </div>
                <div class="stat-card">
                    <div class="icon" style="color: #ff9800;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="value">
                        <?php
                        $today_count = 0;
                        foreach ($unauthorized_logs as $log) {
                            if (date('Y-m-d', strtotime($log['attempt_time'])) == date('Y-m-d')) {
                                $today_count++;
                            }
                        }
                        echo $today_count;
                        ?>
                    </div>
                    <div class="label">Today</div>
                </div>
                <div class="stat-card">
                    <div class="icon" style="color: #2196f3;">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="value">
                        <?php
                        $unique_rfids = array_unique(array_column($unauthorized_logs, 'rfid_tag'));
                        echo count($unique_rfids);
                        ?>
                    </div>
                    <div class="label">Unique RFID Tags</div>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-list"></i> Recent Unauthorized Access Attempts</h2>
                
                <?php if (empty($unauthorized_logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>No Unauthorized Access Attempts</h3>
                    <p>All kiosk access attempts have been from authorized users.</p>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>RFID Tag</th>
                            <th>Attempt Time</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unauthorized_logs as $log): ?>
                        <tr>
                            <td>#<?= $log['id'] ?></td>
                            <td><strong><?= htmlspecialchars($log['rfid_tag']) ?></strong></td>
                            <td><?= date('M j, Y g:i A', strtotime($log['attempt_time'])) ?></td>
                            <td><?= htmlspecialchars($log['ip_address'] ?? 'Unknown') ?></td>
                            <td>
                                <?php if ($log['notified']): ?>
                                <span class="badge badge-success"><i class="fas fa-check"></i> Admin Notified</span>
                                <?php else: ?>
                                <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Email Logs Tab -->
        <div id="emails-tab" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon" style="color: #4caf50;">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="value"><?= count($email_logs) ?></div>
                    <div class="label">Total Emails Sent</div>
                </div>
                <div class="stat-card">
                    <div class="icon" style="color: #2196f3;">
                        <i class="fas fa-hand-holding"></i>
                    </div>
                    <div class="value">
                        <?php
                        $borrow_count = 0;
                        foreach ($email_logs as $log) {
                            if ($log['email_type'] == 'borrow') $borrow_count++;
                        }
                        echo $borrow_count;
                        ?>
                    </div>
                    <div class="label">Borrow Notifications</div>
                </div>
                <div class="stat-card">
                    <div class="icon" style="color: #ff9800;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="value">
                        <?php
                        $overdue_count = 0;
                        foreach ($email_logs as $log) {
                            if ($log['email_type'] == 'overdue') $overdue_count++;
                        }
                        echo $overdue_count;
                        ?>
                    </div>
                    <div class="label">Overdue Reminders</div>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-paper-plane"></i> Recent Email Notifications</h2>
                
                <?php if (empty($email_logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Email Logs</h3>
                    <p>Email logs will appear here once emails are sent by the system.</p>
                    <p><small>Make sure the email_logs table is created in your database.</small></p>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Equipment</th>
                            <th>Sent At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($email_logs as $log): ?>
                        <tr>
                            <td>#<?= $log['id'] ?></td>
                            <td>
                                <?php
                                $type_icons = [
                                    'borrow' => '<i class="fas fa-hand-holding"></i>',
                                    'return' => '<i class="fas fa-undo"></i>',
                                    'overdue' => '<i class="fas fa-clock"></i>',
                                    'low_stock' => '<i class="fas fa-exclamation-triangle"></i>'
                                ];
                                $type_colors = [
                                    'borrow' => 'info',
                                    'return' => 'success',
                                    'overdue' => 'warning',
                                    'low_stock' => 'danger'
                                ];
                                $icon = $type_icons[$log['email_type']] ?? '<i class="fas fa-envelope"></i>';
                                $color = $type_colors[$log['email_type']] ?? 'info';
                                ?>
                                <span class="badge badge-<?= $color ?>">
                                    <?= $icon ?> <?= ucfirst($log['email_type']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($log['user_name'] ?? 'N/A') ?></strong><br>
                                <small><?= htmlspecialchars($log['user_email'] ?? 'N/A') ?></small>
                            </td>
                            <td><?= htmlspecialchars($log['equipment_name'] ?? 'N/A') ?></td>
                            <td><?= date('M j, Y g:i A', strtotime($log['sent_at'])) ?></td>
                            <td>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> <?= ucfirst($log['status'] ?? 'sent') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.closest('.tab').classList.add('active');
        }
    </script>
</body>
</html>
