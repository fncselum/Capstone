<?php
session_start();

// Simple authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Include system health monitoring
require_once '../includes/system_health.php';
require_once '../includes/secure_db_connection.php';

// Initialize database connection
$db = getSecureDB();
$health_monitor = new SystemHealth($db ? $db->getConnection() : null);

// Perform health check
$health_status = $health_monitor->performHealthCheck();
$health_monitor->logHealthStatus($health_status);

// Get system statistics
$stats = [
    'total_equipment' => 0,
    'total_transactions' => 0,
    'active_users' => 0,
    'error_count' => 0
];

if ($db) {
    try {
        $stats['total_equipment'] = $db->getRow("SELECT COUNT(*) as count FROM equipment")['count'] ?? 0;
        $stats['total_transactions'] = $db->getRow("SELECT COUNT(*) as count FROM transactions")['count'] ?? 0;
        $stats['active_users'] = $db->getRow("SELECT COUNT(DISTINCT user_id) as count FROM transactions WHERE status = 'active'")['count'] ?? 0;
    } catch (Exception $e) {
        ErrorHandler::logError("Failed to get system statistics", ['error' => $e->getMessage()]);
    }
}

// Count recent errors
$error_log_file = '../logs/system_errors.log';
if (file_exists($error_log_file)) {
    $log_content = file_get_contents($error_log_file);
    $stats['error_count'] = substr_count($log_content, '"timestamp"');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitor - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .monitor-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #ddd;
        }
        
        .status-card.healthy {
            border-left-color: #4CAF50;
        }
        
        .status-card.warning {
            border-left-color: #FF9800;
        }
        
        .status-card.error {
            border-left-color: #F44336;
        }
        
        .status-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .status-icon {
            font-size: 24px;
            margin-right: 10px;
        }
        
        .status-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .status-value {
            font-size: 32px;
            font-weight: bold;
            color: #666;
            margin-bottom: 10px;
        }
        
        .status-description {
            color: #666;
            font-size: 14px;
        }
        
        .health-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .health-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        
        .error-list, .warning-list {
            margin: 10px 0;
        }
        
        .error-item, .warning-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .error-item {
            background: #ffebee;
            color: #c62828;
            border-left: 3px solid #f44336;
        }
        
        .warning-item {
            background: #fff3e0;
            color: #ef6c00;
            border-left: 3px solid #ff9800;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .refresh-btn {
            background: #2196F3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .refresh-btn:hover {
            background: #1976D2;
        }
        
        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .auto-refresh input[type="checkbox"] {
            transform: scale(1.2);
        }
    </style>
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
                <li class="nav-item">
                    <a href="admin-penalty-guideline.php"><i class="fas fa-exclamation-triangle"></i><span>Penalty Guidelines</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-penalty-management.php"><i class="fas fa-gavel"></i><span>Penalty Management</span></a>
                </li>
                <li class="nav-item active">
                    <a href="system-monitor.php"><i class="fas fa-heartbeat"></i><span>System Monitor</span></a>
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
                <h1 class="page-title">System Monitor</h1>
                <div class="auto-refresh">
                    <label>
                        <input type="checkbox" id="autoRefresh" onchange="toggleAutoRefresh()">
                        Auto Refresh (30s)
                    </label>
                    <button class="refresh-btn" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh Now
                    </button>
                </div>
            </header>

            <div class="monitor-container">
                <!-- System Status Overview -->
                <div class="status-grid">
                    <div class="status-card <?= $health_status['status'] ?>">
                        <div class="status-header">
                            <i class="fas fa-heartbeat status-icon"></i>
                            <div class="status-title">System Health</div>
                        </div>
                        <div class="status-value"><?= ucfirst($health_status['status']) ?></div>
                        <div class="status-description">
                            Last checked: <?= $health_status['timestamp'] ?>
                        </div>
                    </div>
                    
                    <div class="status-card">
                        <div class="status-header">
                            <i class="fas fa-database status-icon"></i>
                            <div class="status-title">Database</div>
                        </div>
                        <div class="status-value"><?= $db && $db->isConnected() ? 'Online' : 'Offline' ?></div>
                        <div class="status-description">
                            Connection status
                        </div>
                    </div>
                    
                    <div class="status-card">
                        <div class="status-header">
                            <i class="fas fa-exclamation-triangle status-icon"></i>
                            <div class="status-title">Errors</div>
                        </div>
                        <div class="status-value"><?= count($health_status['errors']) ?></div>
                        <div class="status-description">
                            Critical issues found
                        </div>
                    </div>
                    
                    <div class="status-card">
                        <div class="status-header">
                            <i class="fas fa-exclamation-circle status-icon"></i>
                            <div class="status-title">Warnings</div>
                        </div>
                        <div class="status-value"><?= count($health_status['warnings']) ?></div>
                        <div class="status-description">
                            Non-critical issues
                        </div>
                    </div>
                </div>

                <!-- System Statistics -->
                <div class="health-section">
                    <h2 class="health-title">System Statistics</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['total_equipment'] ?></div>
                            <div class="stat-label">Total Equipment</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['total_transactions'] ?></div>
                            <div class="stat-label">Total Transactions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['active_users'] ?></div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['error_count'] ?></div>
                            <div class="stat-label">Logged Errors</div>
                        </div>
                    </div>
                </div>

                <!-- Health Issues -->
                <?php if (!empty($health_status['errors']) || !empty($health_status['warnings'])): ?>
                <div class="health-section">
                    <h2 class="health-title">Health Issues</h2>
                    
                    <?php if (!empty($health_status['errors'])): ?>
                    <h3 style="color: #f44336; margin-bottom: 10px;">Critical Errors</h3>
                    <div class="error-list">
                        <?php foreach ($health_status['errors'] as $error): ?>
                        <div class="error-item">
                            <i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($health_status['warnings'])): ?>
                    <h3 style="color: #ff9800; margin-bottom: 10px;">Warnings</h3>
                    <div class="warning-list">
                        <?php foreach ($health_status['warnings'] as $warning): ?>
                        <div class="warning-item">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($warning) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- System Health Check Results -->
                <div class="health-section">
                    <h2 class="health-title">Detailed Health Check</h2>
                    <div id="healthDetails">
                        <p><strong>Status:</strong> <?= ucfirst($health_status['status']) ?></p>
                        <p><strong>Check Time:</strong> <?= $health_status['timestamp'] ?></p>
                        <p><strong>Errors Found:</strong> <?= count($health_status['errors']) ?></p>
                        <p><strong>Warnings Found:</strong> <?= count($health_status['warnings']) ?></p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let autoRefreshInterval = null;
        
        function refreshData() {
            location.reload();
        }
        
        function toggleAutoRefresh() {
            const checkbox = document.getElementById('autoRefresh');
            
            if (checkbox.checked) {
                autoRefreshInterval = setInterval(refreshData, 30000); // 30 seconds
            } else {
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                    autoRefreshInterval = null;
                }
            }
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
        
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }
    </script>
</body>
</html>