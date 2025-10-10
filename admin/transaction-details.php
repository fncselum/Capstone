<?php
session_start();

// Simple authentication check
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

// Get monthly transactions
$monthly_transactions = $conn->query(
    "SELECT t.*, e.name as equipment_name, e.description as equipment_description
     FROM transactions t
     JOIN equipment e ON t.equipment_id = e.id
     WHERE MONTH(t.transaction_date) = MONTH(CURDATE())
     ORDER BY t.transaction_date DESC"
);

// Get transaction count for current month
$transaction_count = $conn->query(
    "SELECT COUNT(*) as total FROM transactions WHERE MONTH(transaction_date) = MONTH(CURDATE())"
);
$count = $transaction_count ? $transaction_count->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Transactions - Admin Dashboard</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="transaction-details-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="../uploads/De lasalle ASMC.png" alt="Logo" class="main-logo">
                <h2>Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="admin-dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="admin-inventory.php" class="nav-item">
                    <i class="fas fa-boxes"></i>
                    <span>Equipment Inventory</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>
                <a href="admin-dashboard.php#transactions" class="nav-item">
                    <i class="fas fa-exchange-alt"></i>
                    <span>All Transactions</span>
                </a>
                <a href="student-activity.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>User Activity</span>
                </a>
                <a href="logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>Monthly Transactions</h1>
                <p>All transactions for the current month</p>
            </div>

            <div class="content-body">
                <div class="transaction-details">
                    <div class="page-header">
                        <h2>Monthly Transactions (<?= $count ?> transactions)</h2>
                        <a href="admin-dashboard.php" class="back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                    
                    <?php if ($monthly_transactions && $monthly_transactions->num_rows > 0): ?>
                        <div class="transaction-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Equipment</th>
                                        <th>Type</th>
                                        <th>RFID</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $monthly_transactions->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($row['id']) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['equipment_name']) ?></strong>
                                                <?php if($row['equipment_description']): ?>
                                                    <br><small><?= htmlspecialchars($row['equipment_description']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="type-badge <?= strtolower($row['type']) ?>">
                                                    <?= htmlspecialchars($row['type']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($row['rfid_id']) ?></td>
                                            <td>
                                                <?= date('M j, Y g:i A', strtotime($row['transaction_date'])) ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= strtolower($row['status']) ?>">
                                                    <?= htmlspecialchars($row['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($row['notes']): ?>
                                                    <span class="notes-text"><?= htmlspecialchars($row['notes']) ?></span>
                                                <?php else: ?>
                                                    <span class="no-notes">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="transaction-summary">
                            <div class="summary-card">
                                <div class="summary-icon">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="summary-content">
                                    <h4>Total Transactions</h4>
                                    <p class="summary-number"><?= $count ?></p>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="summary-content">
                                    <h4>Current Month</h4>
                                    <p class="summary-text"><?= date('F Y') ?></p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <h3>No Transactions This Month</h3>
                            <p>No transactions have been recorded for <?= date('F Y') ?> yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
