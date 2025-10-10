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

$conn->select_db($dbname);

// Get all transactions
$all_transactions = $conn->query(
    "SELECT t.*, e.name as equipment_name 
     FROM transactions t
     JOIN equipment e ON t.equipment_id = e.id
     ORDER BY t.transaction_date DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Transactions - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/all-transactions.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../uploads/De lasalle ASMC.png" alt="De La Salle ASMC Logo" class="main-logo" style="height:30px; width:auto;">
                    <span class="logo-text">Admin Panel</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="admin-dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-equipment-inventory.php"><i class="fas fa-boxes"></i><span>Equipment Inventory</span></a>
                </li>
                <li class="nav-item">
                    <a href="reports.php"><i class="fas fa-file-alt"></i><span>Reports</span></a>
                </li>
                <li class="nav-item active">
                    <a href="admin-all-transaction.php"><i class="fas fa-exchange-alt"></i><span>All Transactions</span></a>
                </li>
                <li class="nav-item">
                    <a href="student-activity.php"><i class="fas fa-users"></i><span>User Activity</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-penalty-guideline.php"><i class="fas fa-exclamation-triangle"></i><span>Penalty Guidelines</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-penalty-management.php"><i class="fas fa-gavel"></i><span>Penalty Management</span></a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </button>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">All Equipment Transactions</h1>
            </header>

            <!-- Transactions Section -->
            <section class="content-section active">
                <!-- Filter Bar -->
                <div class="filter-bar">
                    <button class="filter-btn active" data-filter="all" onclick="filterTransactions('all')">All</button>
                    <button class="filter-btn" data-filter="borrowed" onclick="filterTransactions('borrowed')">Borrowed</button>
                    <button class="filter-btn" data-filter="returned" onclick="filterTransactions('returned')">Returned</button>
                    <button class="filter-btn" data-filter="overdue" onclick="filterTransactions('overdue')">Overdue</button>
                </div>

                <!-- All Transactions Table -->
                <div class="transactions-table">
                    <?php if ($all_transactions && $all_transactions->num_rows > 0): ?>
                    <table id="transactionsTable">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>RFID ID</th>
                                <th>Transaction Type</th>
                                <th>Date</th>
                                <th>Expected Return</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset pointer to beginning
                            $all_transactions->data_seek(0);
                            while($row = $all_transactions->fetch_assoc()): 
                                // Determine status
                                $status = 'borrowed';
                                $statusLabel = 'Borrowed';
                                $badgeClass = 'borrow';
                                
                                if ($row['type'] === 'Return') {
                                    $status = 'returned';
                                    $statusLabel = 'Returned';
                                    $badgeClass = 'return';
                                } elseif ($row['type'] === 'Borrow' && isset($row['planned_return']) && $row['planned_return'] < date('Y-m-d')) {
                                    $status = 'overdue';
                                    $statusLabel = 'Overdue';
                                    $badgeClass = 'violation';
                                }
                            ?>
                            <tr data-status="<?= $status ?>">
                                <td><?= htmlspecialchars($row['equipment_name']) ?></td>
                                <td><?= htmlspecialchars($row['rfid_id']) ?></td>
                                <td><?= htmlspecialchars($row['type']) ?></td>
                                <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                                <td><?= htmlspecialchars($row['planned_return'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="no-data">No transactions found.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }
        
        // Filter transactions
        function filterTransactions(filter) {
            const rows = document.querySelectorAll('#transactionsTable tbody tr');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            buttons.forEach(btn => {
                if (btn.dataset.filter === filter) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            // Filter rows
            rows.forEach(row => {
                const status = row.dataset.status;
                if (filter === 'all' || status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const adminContainer = document.querySelector('.admin-container');
            
            if (sidebarToggle && sidebar && adminContainer) {
                sidebarToggle.addEventListener('click', function() {
                    const isHidden = sidebar.classList.toggle('hidden');
                    adminContainer.classList.toggle('sidebar-hidden', isHidden);
                });
            }
        });
    </script>
</body>
</html>
