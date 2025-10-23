<?php
/**
 * System Monitor Dashboard
 * Real-time system monitoring and health status
 */

define('SYSTEM_ACCESS', true);
require_once '../includes/error_handler.php';
require_once '../includes/security.php';
require_once '../includes/database_safe.php';

// Start secure session
secureSessionStart();

// Check if admin is authenticated
if (!isAdminAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Get health status
$health_data = null;
try {
    $health_response = file_get_contents('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/system_health_check.php');
    $health_data = json_decode($health_response, true);
} catch (Exception $e) {
    $health_data = ['overall_status' => 'error', 'message' => 'Failed to get health status'];
}

// Get system statistics
$stats = [];
try {
    $stats['total_equipment'] = getRecordCount('equipment');
    $stats['total_users'] = getRecordCount('users');
    $stats['active_transactions'] = getRecordCount('transactions', ['status' => 'Active']);
    $stats['pending_approvals'] = getRecordCount('transactions', ['approval_status' => 'Pending']);
    $stats['total_transactions'] = getRecordCount('transactions');
} catch (Exception $e) {
    $stats = ['error' => 'Failed to load statistics'];
}

// Get recent activity
$recent_activity = [];
try {
    $recent_activity = safeSelect(
        "SELECT t.*, e.name as equipment_name, u.student_id 
         FROM transactions t 
         LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag 
         LEFT JOIN users u ON t.user_id = u.id 
         ORDER BY t.created_at DESC 
         LIMIT 10"
    );
} catch (Exception $e) {
    $recent_activity = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitor - Equipment Kiosk</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .monitor-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .monitor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        
        .status-healthy {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-critical {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .monitor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .monitor-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        
        .monitor-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .check-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .check-item:last-child {
            border-bottom: none;
        }
        
        .check-name {
            font-weight: 500;
            color: #495057;
        }
        
        .check-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-time {
            font-size: 12px;
            color: #6c757d;
        }
        
        .refresh-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .refresh-btn:hover {
            background: #0056b3;
        }
        
        .refresh-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .auto-refresh input[type="checkbox"] {
            margin: 0;
        }
        
        .auto-refresh label {
            font-size: 14px;
            color: #495057;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="monitor-container">
        <div class="monitor-header">
            <div>
                <h1><i class="fas fa-heartbeat"></i> System Monitor</h1>
                <p>Real-time system health and performance monitoring</p>
            </div>
            <div class="status-indicator status-<?= $health_data['overall_status'] ?? 'error' ?>">
                <i class="fas fa-circle"></i>
                <?= ucfirst($health_data['overall_status'] ?? 'error') ?>
            </div>
        </div>

        <div class="auto-refresh">
            <input type="checkbox" id="autoRefresh" checked>
            <label for="autoRefresh">Auto-refresh every 30 seconds</label>
            <button class="refresh-btn" onclick="refreshData()">
                <i class="fas fa-sync-alt"></i> Refresh Now
            </button>
        </div>

        <div class="monitor-grid">
            <!-- System Health -->
            <div class="monitor-card">
                <h3><i class="fas fa-shield-alt"></i> System Health</h3>
                <div id="healthChecks">
                    <?php if ($health_data && isset($health_data['checks'])): ?>
                        <?php foreach ($health_data['checks'] as $check_name => $check): ?>
                            <div class="check-item">
                                <span class="check-name"><?= ucwords(str_replace('_', ' ', $check_name)) ?></span>
                                <span class="check-status status-<?= $check['status'] ?>">
                                    <?= ucfirst($check['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="check-item">
                            <span class="check-name">Health Check</span>
                            <span class="check-status status-error">Error</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Statistics -->
            <div class="monitor-card">
                <h3><i class="fas fa-chart-bar"></i> System Statistics</h3>
                <div class="stats-grid" id="systemStats">
                    <?php if (isset($stats['error'])): ?>
                        <div class="stat-item">
                            <div class="stat-number">Error</div>
                            <div class="stat-label">Failed to load</div>
                        </div>
                    <?php else: ?>
                        <div class="stat-item">
                            <div class="stat-number"><?= $stats['total_equipment'] ?? 0 ?></div>
                            <div class="stat-label">Total Equipment</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $stats['total_users'] ?? 0 ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $stats['active_transactions'] ?? 0 ?></div>
                            <div class="stat-label">Active Transactions</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= $stats['pending_approvals'] ?? 0 ?></div>
                            <div class="stat-label">Pending Approvals</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="monitor-card">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <div class="activity-list" id="recentActivity">
                    <?php if (empty($recent_activity)): ?>
                        <div class="activity-item">
                            <div class="activity-details">No recent activity</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-details">
                                    <strong><?= htmlspecialchars($activity['transaction_type']) ?></strong>
                                    <?= htmlspecialchars($activity['equipment_name'] ?? 'Unknown Equipment') ?>
                                    <br>
                                    <small>User: <?= htmlspecialchars($activity['student_id'] ?? 'Unknown') ?></small>
                                </div>
                                <div class="activity-time">
                                    <?= date('M j, H:i', strtotime($activity['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- System Summary -->
        <?php if ($health_data && isset($health_data['summary'])): ?>
        <div class="monitor-card">
            <h3><i class="fas fa-info-circle"></i> System Summary</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?= $health_data['summary']['total_checks'] ?></div>
                    <div class="stat-label">Total Checks</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color: #28a745;"><?= $health_data['summary']['healthy'] ?></div>
                    <div class="stat-label">Healthy</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color: #ffc107;"><?= $health_data['summary']['warnings'] ?></div>
                    <div class="stat-label">Warnings</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color: #dc3545;"><?= $health_data['summary']['critical'] ?></div>
                    <div class="stat-label">Critical</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        let autoRefreshInterval;
        let isRefreshing = false;

        function refreshData() {
            if (isRefreshing) return;
            
            isRefreshing = true;
            const refreshBtn = document.querySelector('.refresh-btn');
            const originalText = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            refreshBtn.disabled = true;

            // Refresh the page
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        function startAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
            
            autoRefreshInterval = setInterval(() => {
                if (!isRefreshing) {
                    refreshData();
                }
            }, 30000); // 30 seconds
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }

        // Auto-refresh toggle
        document.getElementById('autoRefresh').addEventListener('change', function() {
            if (this.checked) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });

        // Start auto-refresh on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('autoRefresh').checked) {
                startAutoRefresh();
            }
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });
    </script>
</body>
</html>