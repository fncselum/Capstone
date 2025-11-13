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

// Establish DB connection but do not terminate on failure; allow health to show Offline
$conn = @new mysqli($host, $user, $password, $dbname);
$db_online = !($conn->connect_error);
if ($db_online) {
    $conn->select_db($dbname);
}

// Fetch kiosk activity statistics
$stats = [];

// Total transactions today
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$stats['today_transactions'] = 0;
if ($db_online) {
    $result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_date BETWEEN '$today_start' AND '$today_end'");
    $stats['today_transactions'] = $result ? (int)$result->fetch_assoc()['count'] : 0;
}

// Active borrows
$stats['active_borrows'] = 0;
if ($db_online) {
    $result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'Active'");
    $stats['active_borrows'] = $result ? (int)$result->fetch_assoc()['count'] : 0;
}

// Pending returns (overdue)
$stats['overdue_items'] = 0;
if ($db_online) {
    $result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'Active' AND expected_return_date < NOW()");
    $stats['overdue_items'] = $result ? (int)$result->fetch_assoc()['count'] : 0;
}

// Total users
$stats['active_users'] = 0;
if ($db_online) {
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Active'");
    $stats['active_users'] = $result ? (int)$result->fetch_assoc()['count'] : 0;
}

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
if ($db_online) {
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_activity[] = $row;
        }
    }
}

// Hourly transaction distribution (last 24 hours)
$hourly_data = [];
for ($i = 23; $i >= 0; $i--) {
    $hour_start = date('Y-m-d H:00:00', strtotime("-$i hours"));
    $hour_end = date('Y-m-d H:59:59', strtotime("-$i hours"));
    $count = 0;
    if ($db_online) {
        $result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_date BETWEEN '$hour_start' AND '$hour_end'");
        $count = $result ? (int)$result->fetch_assoc()['count'] : 0;
    }
    $hourly_data[] = [
        'hour' => date('H:00', strtotime("-$i hours")),
        'count' => $count
    ];
}

// Equipment availability (real quantities)
$equipment_stats = [];
$query = "SELECT 
            COUNT(*) AS total_items,
            SUM(GREATEST(COALESCE(i.quantity, e.quantity, 0)
                       - COALESCE(i.borrowed_quantity, 0)
                       - COALESCE(i.damaged_quantity, 0)
                       - COALESCE(i.maintenance_quantity, 0), 0)) AS total_available_units,
            SUM(CASE WHEN GREATEST(COALESCE(i.quantity, e.quantity, 0)
                       - COALESCE(i.borrowed_quantity, 0)
                       - COALESCE(i.damaged_quantity, 0)
                       - COALESCE(i.maintenance_quantity, 0), 0) = 0 THEN 1 ELSE 0 END) AS out_of_stock_items,
            SUM(COALESCE(i.maintenance_quantity, 0)) AS maintenance_units
          FROM equipment e
          LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id";
if ($db_online) {
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $equipment_stats = [
            'total_equipment' => (int)($row['total_items'] ?? 0),
            'available' => (int)($row['total_available_units'] ?? 0),
            'out_of_stock' => (int)($row['out_of_stock_items'] ?? 0),
            'maintenance' => 0
        ];

        // Override maintenance using maintenance_logs (Pending + In Progress)
        $qMaint = "SELECT SUM(maintenance_quantity) AS total_maintenance
                   FROM maintenance_logs
                   WHERE status IN ('Pending','In Progress')";
        $rMaint = $conn->query($qMaint);
        if ($rMaint && ($m = $rMaint->fetch_assoc())) {
            $equipment_stats['maintenance'] = (int)($m['total_maintenance'] ?? 0);
        }
    }
}

// System health indicators
$system_health = [
    'database_status' => $db_online ? 'Online' : 'Offline',
    'last_transaction' => null,
    'response_time' => 'Unknown',
    'kiosk_status' => 'Operational',
    'db_class' => $db_online ? 'online' : 'offline',
    'kiosk_class' => 'online'
];

// Measure response time (simple ping)
if ($db_online) {
    $start = microtime(true);
    $ping = $conn->query("SELECT 1");
    $elapsed = ($ping ? microtime(true) - $start : 1.0);
    if ($elapsed < 0.2) $system_health['response_time'] = 'Good';
    elseif ($elapsed < 0.6) $system_health['response_time'] = 'Fair';
    else $system_health['response_time'] = 'Slow';

    // Last transaction
    $result = $conn->query("SELECT MAX(transaction_date) as last_txn FROM transactions");
    if ($result) {
        $row = $result->fetch_assoc();
        $system_health['last_transaction'] = $row['last_txn'];
    }

    // Maintenance mode
    $maintenance_mode = '0';
    $table_check = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $maintenance_mode = $row['setting_value'];
            }
            $stmt->close();
        }
    }

    if ($maintenance_mode === '1') {
        $system_health['kiosk_status'] = 'Maintenance';
        $system_health['kiosk_class'] = 'warning';
    } else {
        $system_health['kiosk_status'] = 'Operational';
        $system_health['kiosk_class'] = 'online';
    }
} else {
    // DB offline implies kiosk unavailable
    $system_health['response_time'] = 'Unavailable';
    $system_health['kiosk_status'] = 'Unavailable';
    $system_health['kiosk_class'] = 'offline';
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            padding: 8px 16px;
            background: #9c27b0;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .refresh-btn:hover {
            background: #7b1fa2;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(156, 39, 176, 0.3);
        }

        .refresh-btn i {
            font-size: 0.9rem;
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
                        <div id="db_icon" class="health-icon <?= htmlspecialchars($system_health['db_class']) ?>">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="health-info">
                            <div class="health-label">Database Status</div>
                            <div id="db_status_value" class="health-value <?= htmlspecialchars($system_health['db_class']) ?>"><?= htmlspecialchars($system_health['database_status']) ?></div>
                        </div>
                    </div>

                    <div class="health-item">
                        <div id="kiosk_icon" class="health-icon <?= htmlspecialchars($system_health['kiosk_class']) ?>">
                            <i class="fas fa-desktop"></i>
                        </div>
                        <div class="health-info">
                            <div class="health-label">Kiosk System</div>
                            <div id="kiosk_status_value" class="health-value <?= htmlspecialchars($system_health['kiosk_class']) ?>"><?= htmlspecialchars($system_health['kiosk_status']) ?></div>
                        </div>
                    </div>

                    <div class="health-item">
                        <div class="health-icon online">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div class="health-info">
                            <div class="health-label">Response Time</div>
                            <div id="response_time_value" class="health-value"><?= $system_health['response_time'] ?></div>
                        </div>
                    </div>

                    <div class="health-item">
                        <div class="health-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="health-info">
                            <div class="health-label">Last Transaction</div>
                            <div id="last_transaction_value" class="health-value"><?= $system_health['last_transaction'] ? date('M d, Y H:i', strtotime($system_health['last_transaction'])) : 'No transactions yet' ?></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Equipment Availability -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-boxes"></i> Equipment Availability</h2>
                </div>

                <div id="equipment-overview" class="equipment-overview">
                    <div class="equipment-stat">
                        <div id="equipment_total" class="equipment-number"><?= $equipment_stats['total_equipment'] ?? 0 ?></div>
                        <div class="equipment-label">Total Equipment</div>
                    </div>
                    <div class="equipment-stat available">
                        <div id="equipment_available" class="equipment-number"><?= $equipment_stats['available'] ?? 0 ?></div>
                        <div class="equipment-label">Available</div>
                    </div>
                    <div class="equipment-stat out-of-stock">
                        <div id="equipment_out_of_stock" class="equipment-number"><?= $equipment_stats['out_of_stock'] ?? 0 ?></div>
                        <div class="equipment-label">Out of Stock</div>
                    </div>
                    <div class="equipment-stat maintenance">
                        <div id="equipment_maintenance" class="equipment-number"><?= $equipment_stats['maintenance'] ?? 0 ?></div>
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

        // Real-time updates for health + equipment
        function setClass(el, baseSelector, newClass) {
            if (!el) return;
            el.classList.remove('online', 'offline', 'warning');
            if (newClass) el.classList.add(newClass);
        }

        async function refreshKioskHealth() {
            try {
                const resp = await fetch('kiosk_health_api.php', { cache: 'no-store' });
                if (!resp.ok) return;
                const data = await resp.json();

                // System health
                const dbIcon = document.getElementById('db_icon');
                const dbVal = document.getElementById('db_status_value');
                const kioskIcon = document.getElementById('kiosk_icon');
                const kioskVal = document.getElementById('kiosk_status_value');
                const respTime = document.getElementById('response_time_value');
                const lastTxn = document.getElementById('last_transaction_value');

                if (dbIcon) setClass(dbIcon, '.health-icon', data.system_health.db_class);
                if (dbVal) {
                    setClass(dbVal, '.health-value', data.system_health.db_class);
                    dbVal.textContent = data.system_health.database_status;
                }
                if (kioskIcon) setClass(kioskIcon, '.health-icon', data.system_health.kiosk_class);
                if (kioskVal) {
                    setClass(kioskVal, '.health-value', data.system_health.kiosk_class);
                    kioskVal.textContent = data.system_health.kiosk_status;
                }
                if (respTime) respTime.textContent = data.system_health.response_time;
                if (lastTxn) lastTxn.textContent = data.system_health.last_transaction
                    ? new Date(data.system_health.last_transaction).toLocaleString()
                    : 'No transactions yet';

                // Equipment availability
                const eqTotal = document.getElementById('equipment_total');
                const eqAvail = document.getElementById('equipment_available');
                const eqOut = document.getElementById('equipment_out_of_stock');
                const eqMaint = document.getElementById('equipment_maintenance');
                if (eqTotal) eqTotal.textContent = data.equipment.total_equipment;
                if (eqAvail) eqAvail.textContent = data.equipment.available;
                if (eqOut) eqOut.textContent = data.equipment.out_of_stock;
                if (eqMaint) eqMaint.textContent = data.equipment.maintenance;
            } catch (e) {
                // swallow errors for polling
            }
        }

        // Initial fetch and poll every 15s
        refreshKioskHealth();
        setInterval(refreshKioskHealth, 15000);
    </script>
</body>
</html>
