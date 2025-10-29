<?php
session_start();
date_default_timezone_set('Asia/Manila');

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

// Fetch kiosk activity statistics
$stats = [];

// Total transactions today
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_date BETWEEN '$today_start' AND '$today_end'");
$stats['today_transactions'] = $result ? $result->fetch_assoc()['count'] : 0;

// Active borrows
$result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'Active'");
$stats['active_borrows'] = $result ? $result->fetch_assoc()['count'] : 0;

// Pending returns (overdue)
$result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'Active' AND expected_return_date < NOW()");
$stats['overdue_items'] = $result ? $result->fetch_assoc()['count'] : 0;

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Active'");
$stats['active_users'] = $result ? $result->fetch_assoc()['count'] : 0;

// Recent kiosk activity (last 10 transactions)
$recent_activity = [];
$query = "SELECT t.id, t.transaction_type, t.transaction_date, t.status,
                 u.student_id, u.rfid_tag as user_rfid,
                 e.name as equipment_name, e.rfid_tag as equipment_rfid,
                 c.name as category_name
          FROM transactions t
          LEFT JOIN users u ON t.user_id = u.id
          LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
          LEFT JOIN categories c ON e.category_id = c.id
          ORDER BY t.transaction_date DESC
          LIMIT 10";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
}

// Hourly transaction distribution (last 24 hours)
$hourly_data = [];
for ($i = 23; $i >= 0; $i--) {
    $hour_start = date('Y-m-d H:00:00', strtotime("-$i hours"));
    $hour_end = date('Y-m-d H:59:59', strtotime("-$i hours"));
    $result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_date BETWEEN '$hour_start' AND '$hour_end'");
    $count = $result ? $result->fetch_assoc()['count'] : 0;
    $hourly_data[] = [
        'hour' => date('H:00', strtotime("-$i hours")),
        'count' => $count
    ];
}

// Equipment availability
$equipment_stats = [];
$query = "SELECT 
            COUNT(*) as total_equipment,
            SUM(CASE WHEN i.availability_status = 'Available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN i.availability_status = 'Out of Stock' THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN i.availability_status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance
          FROM equipment e
          LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id";
$result = $conn->query($query);
if ($result) {
    $equipment_stats = $result->fetch_assoc();
}

// System health indicators
$system_health = [
    'database_status' => 'Online',
    'last_transaction' => null,
    'response_time' => 'Good'
];

$result = $conn->query("SELECT MAX(transaction_date) as last_txn FROM transactions");
if ($result) {
    $row = $result->fetch_assoc();
    $system_health['last_transaction'] = $row['last_txn'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Status - Equipment System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .admin-container.sidebar-hidden .main-content {
            margin-left: 0;
        }

        .top-header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8rem;
            color: #333;
            margin: 0;
        }

        .content-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .section-header {
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 1.3rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        /* Health Grid */
        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .health-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 10px;
        }

        .health-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #666;
        }

        .health-icon.online {
            background: #e8f5e9;
            color: #4caf50;
        }

        .health-info {
            flex: 1;
        }

        .health-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }

        .health-value {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
        }

        .health-value.online {
            color: #4caf50;
        }

        .refresh-btn {
            padding: 10px 20px;
            background: #9c27b0;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .refresh-btn:hover {
            background: #7b1fa2;
        }

        /* Equipment Overview */
        .equipment-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }

        .equipment-stat {
            text-align: center;
            padding: 25px;
            background: #f9f9f9;
            border-radius: 10px;
            border-left: 4px solid #9c27b0;
        }

        .equipment-stat.available {
            border-left-color: #4caf50;
        }

        .equipment-stat.out-of-stock {
            border-left-color: #f44336;
        }

        .equipment-stat.maintenance {
            border-left-color: #ff9800;
        }

        .equipment-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .equipment-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }

        /* Chart Container */
        .chart-container {
            height: 300px;
            padding: 20px;
        }

        /* Activity Table */
        .activity-table-container {
            overflow-x: auto;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table thead {
            background: #f5f5f5;
        }

        .activity-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            border-bottom: 2px solid #e0e0e0;
        }

        .activity-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }

        .activity-table tbody tr:hover {
            background: #f9f9f9;
        }

        .activity-time {
            color: #666;
            font-size: 0.85rem;
        }

        .activity-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .activity-type.borrow {
            background: #e3f2fd;
            color: #2196f3;
        }

        .activity-type.return {
            background: #e8f5e9;
            color: #4caf50;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #333;
        }

        .student-info i {
            color: #9c27b0;
        }

        .equipment-info {
            color: #333;
            font-weight: 500;
        }

        .category-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #f5f5f5;
            border-radius: 4px;
            font-size: 0.8rem;
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: #fff3e0;
            color: #ff9800;
        }

        .status-badge.returned {
            background: #e8f5e9;
            color: #4caf50;
        }

        .status-badge.overdue {
            background: #ffebee;
            color: #f44336;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Kiosk Status</h1>
            </header>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e3f2fd;">
                        <i class="fas fa-exchange-alt" style="color: #2196f3;"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['today_transactions']) ?></div>
                        <div class="stat-label">Today's Transactions</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff3e0;">
                        <i class="fas fa-clock" style="color: #ff9800;"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['active_borrows']) ?></div>
                        <div class="stat-label">Active Borrows</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #ffebee;">
                        <i class="fas fa-exclamation-triangle" style="color: #f44336;"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['overdue_items']) ?></div>
                        <div class="stat-label">Overdue Items</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #e8f5e9;">
                        <i class="fas fa-users" style="color: #4caf50;"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['active_users']) ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                </div>
            </div>

            <!-- System Health -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-heartbeat"></i> System Health</h2>
                    <button class="refresh-btn" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>

                <div class="health-grid">
                    <div class="health-item">
                        <div class="health-icon online">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="health-info">
                            <div class="health-label">Database Status</div>
                            <div class="health-value online"><?= $system_health['database_status'] ?></div>
                        </div>
                    </div>

                    <div class="health-item">
                        <div class="health-icon online">
                            <i class="fas fa-desktop"></i>
                        </div>
                        <div class="health-info">
                            <div class="health-label">Kiosk System</div>
                            <div class="health-value online">Operational</div>
                        </div>
                    </div>

                    <div class="health-item">
                        <div class="health-icon online">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div class="health-info">
                            <div class="health-label">Response Time</div>
                            <div class="health-value"><?= $system_health['response_time'] ?></div>
                        </div>
                    </div>

                    <div class="health-item">
                        <div class="health-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="health-info">
                            <div class="health-label">Last Transaction</div>
                            <div class="health-value">
                                <?= $system_health['last_transaction'] ? date('M d, Y H:i', strtotime($system_health['last_transaction'])) : 'No transactions yet' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Equipment Availability -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-boxes"></i> Equipment Availability</h2>
                </div>

                <div class="equipment-overview">
                    <div class="equipment-stat">
                        <div class="equipment-number"><?= $equipment_stats['total_equipment'] ?? 0 ?></div>
                        <div class="equipment-label">Total Equipment</div>
                    </div>
                    <div class="equipment-stat available">
                        <div class="equipment-number"><?= $equipment_stats['available'] ?? 0 ?></div>
                        <div class="equipment-label">Available</div>
                    </div>
                    <div class="equipment-stat out-of-stock">
                        <div class="equipment-number"><?= $equipment_stats['out_of_stock'] ?? 0 ?></div>
                        <div class="equipment-label">Out of Stock</div>
                    </div>
                    <div class="equipment-stat maintenance">
                        <div class="equipment-number"><?= $equipment_stats['maintenance'] ?? 0 ?></div>
                        <div class="equipment-label">Maintenance</div>
                    </div>
                </div>
            </section>

            <!-- Activity Chart -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-line"></i> Transaction Activity (Last 24 Hours)</h2>
                </div>

                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </section>

            <!-- Recent Activity -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Recent Kiosk Activity</h2>
                </div>

                <div class="activity-table-container">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Student ID</th>
                                <th>Equipment</th>
                                <th>Category</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_activity)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                                        No recent activity
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                    <tr>
                                        <td>
                                            <div class="activity-time">
                                                <?= date('M d, H:i', strtotime($activity['transaction_date'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="activity-type <?= strtolower($activity['transaction_type']) ?>">
                                                <i class="fas fa-<?= $activity['transaction_type'] === 'Borrow' ? 'arrow-right' : 'arrow-left' ?>"></i>
                                                <?= htmlspecialchars($activity['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="student-info">
                                                <i class="fas fa-id-card"></i>
                                                <?= htmlspecialchars($activity['student_id'] ?? 'N/A') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="equipment-info">
                                                <?= htmlspecialchars($activity['equipment_name']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="category-badge">
                                                <?= htmlspecialchars($activity['category_name']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= strtolower($activity['status']) ?>">
                                                <?= htmlspecialchars($activity['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Activity Chart
        const ctx = document.getElementById('activityChart').getContext('2d');
        const hourlyData = <?= json_encode($hourly_data) ?>;
        
        const activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: hourlyData.map(d => d.hour),
                datasets: [{
                    label: 'Transactions',
                    data: hourlyData.map(d => d.count),
                    borderColor: '#9c27b0',
                    backgroundColor: 'rgba(156, 39, 176, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
