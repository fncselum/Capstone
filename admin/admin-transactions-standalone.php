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
                    <a href="admin-transactions-standalone.php"><i class="fas fa-exchange-alt"></i><span>All Transactions</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-user-activity.php"><i class="fas fa-users"></i><span>User Activity</span></a>
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
                <h1 class="page-title">All Transactions</h1>
            </header>

            <!-- Transactions Section -->
            <section class="content-section active">
                <div class="section-header">
                    <h2>Transaction Management</h2>
                    <div class="transaction-buttons">
                        <a href="admin-borrowed-transactions.php" class="btn btn-primary">
                            <i class="fas fa-hand-holding"></i> View Borrowed Items
                        </a>
                        <a href="admin-returned-transactions.php" class="btn btn-success">
                            <i class="fas fa-undo"></i> View Returned Items
                        </a>
                    </div>
                </div>
                
                <!-- Borrowed Items -->
                <div class="transaction-category">
                    <h3><i class="fas fa-hand-holding"></i> Borrowed Items</h3>
                    <div class="transactions-table">
                        <?php 
                        $borrowed_items = false;
                        if ($all_transactions) {
                            // Create a copy of the result set for borrowed items
                            $borrowed_items = $conn->query(
                                "SELECT t.*, e.name as equipment_name 
                                 FROM transactions t
                                 JOIN equipment e ON t.equipment_id = e.id
                                 WHERE t.type = 'Borrow'
                                 ORDER BY t.transaction_date DESC"
                            );
                        }
                        ?>
                        <?php if ($borrowed_items && $borrowed_items->num_rows > 0): ?>
                        <table>
                                                            <thead>
                                    <tr>
                                        <th>Equipment</th>
                                        <th>RFID ID</th>
                                        <th>Borrow Date</th>
                                        <th>Expected Return</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $borrowed_items->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['equipment_name']) ?></td>
                                        <td><?= htmlspecialchars($row['rfid_id']) ?></td>
                                        <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                                        <td><?= htmlspecialchars($row['planned_return'] ?? 'Not set') ?></td>
                                        <td>
                                            <span class="badge borrow">Borrowed</span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p>No borrowed items found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Returned Items -->
                <div class="transaction-category">
                    <h3><i class="fas fa-undo"></i> Returned Items</h3>
                    <div class="transactions-table">
                        <?php 
                        $returned_items = false;
                        if ($all_transactions) {
                            $returned_items = $conn->query(
                                "SELECT t.*, e.name as equipment_name 
                                 FROM transactions t
                                 JOIN equipment e ON t.equipment_id = e.id
                                 WHERE t.type = 'Return'
                                 ORDER BY t.transaction_date DESC"
                            );
                        }
                        ?>
                        <?php if ($returned_items && $returned_items->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>RFID ID</th>
                                    <th>Return Date</th>
                                    <th>Condition</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $returned_items->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['equipment_name']) ?></td>
                                    <td><?= htmlspecialchars($row['rfid_id']) ?></td>
                                    <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                                    <td>
                                        <span class="badge return"><?= htmlspecialchars($row['return_condition'] ?? 'Good') ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($row['notes'] ?? '') ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p>No returned items found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Violations (Overdue/Damaged) -->
                <div class="transaction-category">
                    <h3><i class="fas fa-exclamation-triangle"></i> Violations</h3>
                    <div class="transactions-table">
                        <?php 
                        $violations = false;
                        if ($all_transactions) {
                            // Check if transactions table has the required columns
                            $check_columns = $conn->query("SHOW COLUMNS FROM transactions LIKE 'planned_return'");
                            if ($check_columns && $check_columns->num_rows > 0) {
                                $violations = $conn->query(
                                    "SELECT t.*, e.name as equipment_name, e.rfid_tag 
                                     FROM transactions t
                                     JOIN equipment e ON t.equipment_id = e.id
                                     WHERE t.type = 'Borrow' AND (t.planned_return < CURDATE() OR t.return_condition = 'Damaged')
                                     ORDER BY t.transaction_date DESC"
                                );
                            } else {
                                // Fallback: show borrowed items if columns don't exist
                                $violations = $conn->query(
                                    "SELECT t.*, e.name as equipment_name, e.rfid_tag 
                                     FROM transactions t
                                     JOIN equipment e ON t.equipment_id = e.id
                                     WHERE t.type = 'Borrow'
                                     ORDER BY t.transaction_date DESC"
                                );
                            }
                        }
                        ?>
                        <?php if ($violations && $violations->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Equipment Tag/ID</th>
                                    <th>Equipment</th>
                                    <th>Borrower</th>
                                    <th>Borrow Date</th>
                                    <th>Expected Return</th>
                                    <th>Violation Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $violations->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['rfid_tag'] ?? $row['equipment_id']) ?></td>
                                    <td><?= htmlspecialchars($row['equipment_name']) ?></td>
                                    <td><?= htmlspecialchars($row['rfid_id']) ?></td>
                                    <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                                    <td><?= htmlspecialchars($row['planned_return'] ?? 'Not set') ?></td>
                                    <td>
                                        <?php 
                                        if (isset($row['planned_return']) && $row['planned_return'] < date('Y-m-d')) {
                                            echo '<span class="badge violation">Overdue</span>';
                                        } elseif (isset($row['return_condition']) && $row['return_condition'] === 'Damaged') {
                                            echo '<span class="badge violation">Damaged</span>';
                                        } else {
                                            echo '<span class="badge borrow">Active</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p>No violations found.</p>
                        <?php endif; ?>
                    </div>
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
