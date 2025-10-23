<?php
/**
 * System Monitor Dashboard
 * Provides real-time system health monitoring and management
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Include required files
require_once 'includes/error_handler.php';
require_once 'includes/database_manager.php';
require_once 'includes/validation.php';

// Initialize error handler
SystemErrorHandler::init();

// Get system health data
$health_data = null;
$recent_errors = SystemErrorHandler::getRecentErrors(20);
$db_stats = null;

try {
    // Get health data via AJAX call to system_health.php
    $health_response = file_get_contents('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/system_health.php');
    $health_data = json_decode($health_response, true);
    
    // Get database statistics
    $db_stats = DatabaseManager::getStats();
} catch (Exception $e) {
    SystemErrorHandler::logApplicationError("System monitor data fetch failed: " . $e->getMessage());
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .status-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        
        .status-healthy { color: #27ae60; }
        .status-warning { color: #f39c12; }
        .status-unhealthy { color: #e74c3c; }
        
        .status-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }
        
        .status-message {
            color: #666;
            margin: 5px 0 0 0;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .metric-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }
        
        .metric-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .metric-value {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .error-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .error-item {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .error-time {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .error-message {
            font-family: monospace;
            font-size: 13px;
            color: #e53e3e;
        }
        
        .refresh-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .refresh-btn:hover {
            background: #2980b9;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
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
                    <a href="system_monitor.php"><i class="fas fa-heartbeat"></i><span>System Monitor</span></a>
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
                <button class="refresh-btn" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </header>

            <div class="monitor-container">
                <!-- Overall System Status -->
                <div class="status-card">
                    <div class="status-header">
                        <i class="fas fa-heartbeat status-icon status-<?= $health_data['overall_status'] ?? 'unknown' ?>"></i>
                        <div>
                            <h2 class="status-title">System Status</h2>
                            <p class="status-message">
                                <?= ucfirst($health_data['overall_status'] ?? 'Unknown') ?> - 
                                Last checked: <?= $health_data['timestamp'] ?? 'Never' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Health Checks -->
                <?php if ($health_data && isset($health_data['checks'])): ?>
                    <?php foreach ($health_data['checks'] as $check_name => $check_data): ?>
                        <div class="status-card">
                            <div class="status-header">
                                <i class="fas fa-<?= $check_name === 'database' ? 'database' : ($check_name === 'file_system' ? 'folder' : ($check_name === 'memory' ? 'memory' : 'check-circle')) ?> status-icon status-<?= $check_data['status'] ?? 'unknown' ?>"></i>
                                <div>
                                    <h3 class="status-title"><?= ucwords(str_replace('_', ' ', $check_name)) ?></h3>
                                    <p class="status-message"><?= $check_data['message'] ?? 'No message' ?></p>
                                </div>
                            </div>
                            
                            <?php if (isset($check_data['details']) && is_array($check_data['details'])): ?>
                                <div class="metrics-grid">
                                    <?php foreach ($check_data['details'] as $detail_key => $detail_value): ?>
                                        <div class="metric-item">
                                            <div class="metric-label"><?= ucwords(str_replace('_', ' ', $detail_key)) ?></div>
                                            <div class="metric-value"><?= is_bool($detail_value) ? ($detail_value ? 'Yes' : 'No') : htmlspecialchars($detail_value) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- System Information -->
                <?php if ($health_data && isset($health_data['system_info'])): ?>
                    <div class="status-card">
                        <h3 class="status-title">System Information</h3>
                        <div class="system-info">
                            <?php foreach ($health_data['system_info'] as $info_key => $info_value): ?>
                                <div class="info-item">
                                    <div class="info-label"><?= ucwords(str_replace('_', ' ', $info_key)) ?></div>
                                    <div class="info-value"><?= htmlspecialchars($info_value) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Recent Errors -->
                <div class="status-card">
                    <h3 class="status-title">Recent Errors (<?= count($recent_errors) ?>)</h3>
                    <div class="error-list">
                        <?php if (empty($recent_errors)): ?>
                            <div class="loading">No recent errors found. System is running smoothly!</div>
                        <?php else: ?>
                            <?php foreach ($recent_errors as $error): ?>
                                <div class="error-item">
                                    <div class="error-time"><?= $error['line'] ?? 'Unknown time' ?></div>
                                    <div class="error-message"><?= htmlspecialchars($error['details'][0] ?? 'No details') ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Database Statistics -->
                <?php if ($db_stats && !isset($db_stats['error'])): ?>
                    <div class="status-card">
                        <h3 class="status-title">Database Statistics</h3>
                        <div class="metrics-grid">
                            <div class="metric-item">
                                <div class="metric-label">Active Connections</div>
                                <div class="metric-value"><?= $db_stats['connections'] ?? 'N/A' ?></div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-label">Uptime (seconds)</div>
                                <div class="metric-value"><?= number_format($db_stats['uptime_seconds'] ?? 0) ?></div>
                            </div>
                        </div>
                        
                        <?php if (isset($db_stats['table_sizes']) && !empty($db_stats['table_sizes'])): ?>
                            <h4>Table Sizes</h4>
                            <div class="metrics-grid">
                                <?php foreach ($db_stats['table_sizes'] as $table): ?>
                                    <div class="metric-item">
                                        <div class="metric-label"><?= htmlspecialchars($table['table_name']) ?></div>
                                        <div class="metric-value"><?= $table['size_mb'] ?> MB</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function refreshData() {
            location.reload();
        }
        
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }
        
        // Auto-refresh every 30 seconds
        setInterval(refreshData, 30000);
        
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const adminContainer = document.querySelector('.admin-container');
        
        if (sidebarToggle && sidebar && adminContainer) {
            sidebarToggle.addEventListener('click', function() {
                const isHidden = sidebar.classList.toggle('hidden');
                adminContainer.classList.toggle('sidebar-hidden', isHidden);
            });
        }
    </script>
</body>
</html>