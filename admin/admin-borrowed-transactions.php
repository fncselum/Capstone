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

// Get borrowed transactions only
$borrowed_items = $conn->query(
    "SELECT t.*, e.name as equipment_name 
     FROM transactions t
     JOIN equipment e ON t.equipment_id = e.rfid_tag
     WHERE t.transaction_type = 'Borrow' AND t.status = 'Active'
     ORDER BY t.transaction_date DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowed Items - Admin Dashboard</title>
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
                <li class="nav-item">
                    <a href="admin-all-transaction.php"><i class="fas fa-exchange-alt"></i><span>All Transactions</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-user-activity.php"><i class="fas fa-users"></i><span>User Activity</span></a>
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
                <h1 class="page-title">Borrowed Items</h1>
            </header>

            <!-- Borrowed Items Section -->
            <section class="content-section active">
                <div class="section-header">
                    <h2><i class="fas fa-hand-holding"></i> Currently Borrowed Items</h2>
                    <a href="admin-all-transaction.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to All Transactions
                    </a>
                </div>
                
                <div class="transactions-table">
                    <?php if ($borrowed_items && $borrowed_items->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>RFID ID</th>
                                <th>Borrow Date</th>
                                <th>Expected Return</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $borrowed_items->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['equipment_name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($row['rfid_id']) ?></td>
                                <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                                                                    <td>
                                        <?php 
                                        if (isset($row['planned_return']) && $row['planned_return']) {
                                            $expected_date = new DateTime($row['planned_return']);
                                            $today = new DateTime();
                                            $is_overdue = $expected_date < $today;
                                            
                                            if ($is_overdue) {
                                                echo '<span class="overdue-date">' . htmlspecialchars($row['planned_return']) . ' (OVERDUE)</span>';
                                            } else {
                                                echo htmlspecialchars($row['planned_return']);
                                            }
                                        } else {
                                            echo '<span class="no-date">Not set</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (isset($row['planned_return']) && $row['planned_return']) {
                                            $expected_date = new DateTime($row['planned_return']);
                                            $today = new DateTime();
                                            if ($expected_date < $today) {
                                                echo '<span class="badge violation">Overdue</span>';
                                            } else {
                                                echo '<span class="badge borrow">Active</span>';
                                            }
                                        } else {
                                            echo '<span class="badge borrow">Active</span>';
                                        }
                                        ?>
                                    </td>
                                <td>
                                    <a href="transaction-details.php?id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-hand-holding fa-3x"></i>
                        <h3>No Borrowed Items</h3>
                        <p>There are currently no items borrowed from the inventory.</p>
                    </div>
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
