<?php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

    // Simple authentication check (you can enhance this later)
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // If not logged in, redirect to login
        header('Location: login.php');
        exit;
    }

// Regenerate session ID for security
if (!isset($_SESSION['admin_initialized'])) {
    session_regenerate_id(true);
    $_SESSION['admin_initialized'] = true;
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Database connection
$host = "localhost";
$user = "root";       
$password = "";   // no password for XAMPP
$dbname = "capstone";

// Create connection
$db_connected = true;
$db_error = null;
$conn = @new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    $db_connected = false;
    $db_error = $conn->connect_error;
}

// Fetch data from DB
if ($db_connected) {
    // Database connection is established
    // Individual section files will handle their own data queries
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Equipment Kiosk - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=<?= time() ?>">
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
                <li class="nav-item active">
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

        <!-- Main -->
        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Dashboard</h1>
            </header>

            <!-- Dashboard Section - ONLY place where statistics appear -->
            <section id="dashboard" class="content-section active">
                <!-- Statistics Section -->
                <div class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-number">
                                    <?php 
                                    $equipment_count = 0;
                                    $equipment_query = $conn->query("SELECT COUNT(*) as count FROM equipment");
                                    if ($equipment_query) {
                                        $equipment_count = $equipment_query->fetch_assoc()['count'];
                                    }
                                    echo $equipment_count;
                                    ?>
                                </h3>
                                <p class="stat-label">Total Equipment Items</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-hand-holding"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-number">
                                    <?php 
                                    $borrowed_count = 0;
                                    $check_transactions = $conn->query("SHOW TABLES LIKE 'transactions'");
                                    if ($check_transactions && $check_transactions->num_rows > 0) {
                                        $borrowed_query = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE type = 'Borrow'");
                                        if ($borrowed_query) {
                                            $borrowed_count = $borrowed_query->fetch_assoc()['count'];
                                        }
                                    }
                                    echo $borrowed_count;
                                    ?>
                                </h3>
                                <p class="stat-label">Currently Borrowed</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-undo"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-number">
                                    <?php 
                                    $returned_count = 0;
                                    if ($check_transactions && $check_transactions->num_rows > 0) {
                                        $returned_query = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE type = 'Return'");
                                        if ($returned_query) {
                                            $returned_count = $returned_query->fetch_assoc()['count'];
                                        }
                                    }
                                    echo $returned_count;
                                    ?>
                                </h3>
                                <p class="stat-label">Total Returns</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <h3 class="stat-number">
                                    <?php 
                                    $violations_count = 0;
                                    if ($check_transactions && $check_transactions->num_rows > 0) {
                                        // Check if transactions table exists and has the required columns
                                        $check_columns = $conn->query("SHOW COLUMNS FROM transactions LIKE 'expected_return_date'");
                                        if ($check_columns && $check_columns->num_rows > 0) {
                                            $violations_query = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE type = 'Borrow' AND (expected_return_date < CURDATE() OR return_condition = 'Damaged')");
                                            if ($violations_query) {
                                                $violations_count = $violations_query->fetch_assoc()['count'];
                                            }
                                        } else {
                                            // Fallback: just count borrowed items if columns don't exist
                                            $violations_query = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE type = 'Borrow'");
                                            if ($violations_query) {
                                                $violations_count = $violations_query->fetch_assoc()['count'];
                                            }
                                        }
                                    }
                                    echo $violations_count;
                                    ?>
                                </h3>
                                <p class="stat-label">Active Violations</p>
                            </div>
                        </div>

                        
                    </div>
                </div>
                
                <div id="dashboard-content">
                    <?php
                    // Build detailed panel for most borrowed item (image, latest RFID, monthly usage chart)
                    $top_item = null;
                    $latest_rfid_for_top = null;
                    $daily_usage_labels = [];
                    $daily_usage_counts = [];

                    if ($db_connected && $check_transactions && $check_transactions->num_rows > 0) {
                        // Find the most borrowed equipment with its id, name, image, and total count
                        $top_item_sql =
                            "SELECT e.id AS equipment_id, e.name AS equipment_name, e.image_path, COUNT(*) AS borrow_count\n" .
                            "FROM transactions t\n" .
                            "JOIN equipment e ON t.equipment_id = e.id\n" .
                            "WHERE t.type = 'Borrow'\n" .
                            "GROUP BY t.equipment_id\n" .
                            "ORDER BY borrow_count DESC\n" .
                            "LIMIT 1";
                        $top_item_rs = $conn->query($top_item_sql);
                        if ($top_item_rs && $top_item_rs->num_rows > 0) {
                            $top_item = $top_item_rs->fetch_assoc();

                            // Get latest RFID that borrowed this equipment (represents user RFID)
                            $latest_rfid_rs = $conn->query(
                                "SELECT rfid_id FROM transactions\n" .
                                "WHERE type = 'Borrow' AND equipment_id = " . (int)$top_item['equipment_id'] . "\n" .
                                "ORDER BY transaction_date DESC\n" .
                                "LIMIT 1"
                            );
                            if ($latest_rfid_rs && $latest_rfid_rs->num_rows > 0) {
                                $latest_rfid_for_top = $latest_rfid_rs->fetch_assoc()['rfid_id'];
                            }

                            // Prepare current month per-day usage for this equipment
                            $usage_rs = $conn->query(
                                "SELECT DATE(transaction_date) AS d, COUNT(*) AS c\n" .
                                "FROM transactions\n" .
                                "WHERE type = 'Borrow'\n" .
                                "  AND equipment_id = " . (int)$top_item['equipment_id'] . "\n" .
                                "  AND MONTH(transaction_date) = MONTH(CURDATE())\n" .
                                "  AND YEAR(transaction_date) = YEAR(CURDATE())\n" .
                                "GROUP BY d\n" .
                                "ORDER BY d"
                            );

                            // Build arrays for the entire month
                            $days_in_month = (int)date('t');
                            $usage_map = [];
                            if ($usage_rs) {
                                while ($row = $usage_rs->fetch_assoc()) {
                                    $day_num = (int)date('j', strtotime($row['d']));
                                    $usage_map[$day_num] = (int)$row['c'];
                                }
                            }
                            for ($d = 1; $d <= $days_in_month; $d++) {
                                $daily_usage_labels[] = (string)$d;
                                $daily_usage_counts[] = isset($usage_map[$d]) ? (int)$usage_map[$d] : 0;
                            }
                        }
                    }
                    ?>

                    <div class="most-borrowed-panel" style="margin-top:20px; background:#ffffff; border:1px solid #edf2ef; border-radius:14px; box-shadow:0 8px 24px rgba(0,0,0,0.06); padding:22px;">
                        <?php if ($top_item): ?>
                            <div style="display:flex; gap:28px; align-items:flex-start; flex-wrap:wrap;">
                                <div style="width:360px; max-width:100%;">
                                    <div style="position:relative; width:100%; height:260px; background:#ffffff; border:1px solid #e7efe9; border-radius:16px; display:flex; align-items:center; justify-content:center; overflow:hidden; box-shadow: 0 10px 22px rgba(0,0,0,0.06);">
                                        <?php if (!empty($top_item['image_path'])): ?>
                                            <?php
                                                $top_item_src = $top_item['image_path'];
                                                if (strpos($top_item_src, 'uploads/') === 0) {
                                                    $top_item_src = '../' . $top_item_src;
                                                }
                                            ?>
                                            <img src="<?= htmlspecialchars($top_item_src) ?>" alt="<?= htmlspecialchars($top_item['equipment_name']) ?>" style="width:100%; height:100%; object-fit:cover;" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <span style="font-size:72px; color:#c7d8cf;">📦</span>
                                        <?php endif; ?>
                                        <div style="position:absolute; top:12px; left:12px; background:rgba(31,185,120,0.95); color:#fff; padding:8px 12px; border-radius:999px; font-size:12px; letter-spacing:0.3px; box-shadow:0 4px 10px rgba(31,185,120,0.35);">
                                            ⭐ <?= (int)$top_item['borrow_count'] ?> borrows
                                        </div>
                                    </div>
                                </div>
                                <div style="flex:1; min-width:300px;">
                                    <h2 style="margin:0 0 8px 0; font-size:26px; display:flex; align-items:center; gap:8px;">
                                        🌟 Top Borrowed This Month
                                    </h2>
                                    <div style="font-size:26px; font-weight:800; margin:0 0 10px 0; color:#1f2d2a; letter-spacing:0.2px;">
                                        <?= htmlspecialchars($top_item['equipment_name']) ?>
                                    </div>
                                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;">
                                        <span style="background:#eef7f2; color:#2f7d56; border:1px solid #cfe8da; padding:8px 12px; border-radius:999px; font-size:13px;">
                                            🆔 RFID: <strong style="margin-left:4px; font-weight:700;"><?= htmlspecialchars($latest_rfid_for_top ?? 'N/A') ?></strong>
                                        </span>
                                        <span style="background:#f7f9ff; color:#2859a4; border:1px solid #d7e3ff; padding:8px 12px; border-radius:999px; font-size:13px;">
                                            📈 Total Borrows: <strong style="margin-left:4px; font-weight:700;"><?= (int)$top_item['borrow_count'] ?></strong>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top:20px;">
                                <h3 style="margin:0 0 10px 0; display:flex; align-items:center; gap:8px;">
                                    <span>📅 Daily Usage (This Month)</span>
                                </h3>
                                <div style="background:#ffffff; border:1px solid #edf2ef; border-radius:14px; padding:14px; box-shadow:0 6px 18px rgba(0,0,0,0.05);">
                                    <canvas id="usageChart" style="height:300px; max-height:300px;"></canvas>
                                </div>
                            </div>
                            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                            <script>
                                (function(){
                                    const ctx = document.getElementById('usageChart').getContext('2d');
                                    const labels = <?= json_encode($daily_usage_labels) ?>;
                                    const data = <?= json_encode($daily_usage_counts) ?>;
                                    const yMax = Math.max(1, ...data); // exact cap at data max (>=1)
                                    new Chart(ctx, {
                                        type: 'line',
                                        data: {
                                            labels: labels,
                                            datasets: [{
                                                label: 'Borrows per day',
                                                data: data,
                                                borderColor: '#1fb978',
                                                borderWidth: 3,
                                                backgroundColor: 'rgba(31, 185, 120, 0.18)',
                                                pointRadius: 0,
                                                pointHoverRadius: 4,
                                                tension: 0.35,
                                                fill: true
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: true,
                                            aspectRatio: 2,
                                            scales: {
                                                x: {
                                                    title: { display: true, text: 'Day of Month' },
                                                    grid: { display: false }
                                                },
                                                y: {
                                                    beginAtZero: true,
                                                    max: yMax,
                                                    min: 0,
                                                    title: { display: true, text: 'Borrows' },
                                                    ticks: { stepSize: 1 },
                                                    grid: { color: 'rgba(0,0,0,0.06)' }
                                                }
                                            },
                                            plugins: {
                                                legend: { display: false },
                                                tooltip: {
                                                    backgroundColor: '#1f2d2a',
                                                    titleColor: '#fff',
                                                    bodyColor: '#d6f5e7',
                                                    cornerRadius: 10
                                                }
                                            }
                                        }
                                    });
                                })();
                            </script>
                        <?php else: ?>
                            <div class="welcome-message">
                                <h2>No borrow data yet</h2>
                                <p>Start recording transactions to see the most borrowed item and its usage.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Logout function
        function logout() {
            // Clear any local storage or session data if needed
            localStorage.clear();
            sessionStorage.clear();
            
            // Redirect to logout.php which will handle server-side logout
            window.location.href = 'logout.php';
        }
        
        // Simple navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const navItems = document.querySelectorAll('.nav-item');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const adminContainer = document.querySelector('.admin-container');

            navItems.forEach(nav => nav.classList.remove('active'));
            const dashboardNav = document.querySelector('.nav-item:first-child');
            if (dashboardNav) {
                dashboardNav.classList.add('active');
            }

            if (sidebarToggle && sidebar && adminContainer) {
                sidebarToggle.addEventListener('click', function() {
                    const isHidden = sidebar.classList.toggle('hidden');
                    adminContainer.classList.toggle('sidebar-hidden', isHidden);

                    const icon = sidebarToggle.querySelector('i');
                    if (icon) {
                        icon.className = isHidden ? 'fas fa-bars' : 'fas fa-bars';
                    }
                });
            }
        });
    </script>
</body>
</html>
