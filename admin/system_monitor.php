<?php
/**
 * System Monitor for Equipment Management System
 * Provides real-time monitoring of system health, performance, and security
 */

require_once '../includes/error_handler.php';
require_once '../includes/db_manager.php';
require_once '../includes/security_manager.php';

// Check admin authentication
SecurityManager::requireAuth('admin');

$db = DatabaseManager::getInstance();
$errors = SystemErrorHandler::getRecentErrors(20);
$securityEvents = self::getRecentSecurityEvents(20);
$systemStats = self::getSystemStats();

function getRecentSecurityEvents($limit = 20) {
    $logFile = '../logs/security.log';
    if (!file_exists($logFile)) {
        return [];
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    $events = [];
    
    for ($i = count($lines) - 1; $i >= 0 && count($events) < $limit; $i--) {
        $event = json_decode($lines[$i], true);
        if ($event) {
            $events[] = $event;
        }
    }
    
    return $events;
}

function getSystemStats() {
    $db = DatabaseManager::getInstance();
    
    $stats = [
        'database' => $db->getStats(),
        'disk_usage' => self::getDiskUsage(),
        'memory_usage' => self::getMemoryUsage(),
        'uptime' => self::getSystemUptime(),
        'error_count' => self::getErrorCount(),
        'security_events' => self::getSecurityEventCount()
    ];
    
    return $stats;
}

function getDiskUsage() {
    $total = disk_total_space('/');
    $free = disk_free_space('/');
    $used = $total - $free;
    
    return [
        'total' => $total,
        'used' => $used,
        'free' => $free,
        'percentage' => round(($used / $total) * 100, 2)
    ];
}

function getMemoryUsage() {
    $memUsage = memory_get_usage(true);
    $memPeak = memory_get_peak_usage(true);
    
    return [
        'current' => $memUsage,
        'peak' => $memPeak,
        'limit' => ini_get('memory_limit')
    ];
}

function getSystemUptime() {
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        return [
            'load_1min' => $load[0],
            'load_5min' => $load[1],
            'load_15min' => $load[2]
        ];
    }
    return null;
}

function getErrorCount() {
    $logFile = '../logs/system_errors.log';
    if (!file_exists($logFile)) {
        return 0;
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    return count($lines);
}

function getSecurityEventCount() {
    $logFile = '../logs/security.log';
    if (!file_exists($logFile)) {
        return 0;
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    return count($lines);
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function getStatusColor($percentage) {
    if ($percentage < 70) return 'success';
    if ($percentage < 90) return 'warning';
    return 'danger';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitor - Equipment Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f7fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        
        .stat-title {
            font-size: 14px;
            color: #666;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .stat-subtitle {
            font-size: 12px;
            color: #999;
            margin: 5px 0 0 0;
        }
        
        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .log-entry {
            padding: 10px;
            border-left: 3px solid #ddd;
            margin-bottom: 10px;
            background: #f9f9f9;
            border-radius: 0 4px 4px 0;
        }
        
        .log-entry.error { border-left-color: #dc3545; }
        .log-entry.warning { border-left-color: #ffc107; }
        .log-entry.info { border-left-color: #17a2b8; }
        
        .log-time {
            font-size: 12px;
            color: #666;
            font-weight: bold;
        }
        
        .log-message {
            margin: 5px 0;
            color: #333;
        }
        
        .log-details {
            font-size: 12px;
            color: #666;
            font-family: monospace;
        }
        
        .refresh-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .refresh-btn:hover {
            background: #0056b3;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-indicator.success { background: #28a745; }
        .status-indicator.warning { background: #ffc107; }
        .status-indicator.danger { background: #dc3545; }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .table th,
        .table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-heartbeat"></i> System Monitor</h1>
            <p>Real-time monitoring of system health, performance, and security</p>
            <button class="refresh-btn" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
        
        <!-- System Overview -->
        <div class="stats-grid">
            <div class="stat-card <?= getStatusColor($systemStats['disk_usage']['percentage']) ?>">
                <div class="stat-title">Disk Usage</div>
                <div class="stat-value"><?= $systemStats['disk_usage']['percentage'] ?>%</div>
                <div class="stat-subtitle">
                    <?= formatBytes($systemStats['disk_usage']['used']) ?> / 
                    <?= formatBytes($systemStats['disk_usage']['total']) ?>
                </div>
            </div>
            
            <div class="stat-card <?= getStatusColor(($systemStats['memory_usage']['current'] / (1024*1024*1024)) * 100) ?>">
                <div class="stat-title">Memory Usage</div>
                <div class="stat-value"><?= formatBytes($systemStats['memory_usage']['current']) ?></div>
                <div class="stat-subtitle">
                    Peak: <?= formatBytes($systemStats['memory_usage']['peak']) ?>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-title">Database Status</div>
                <div class="stat-value">
                    <span class="status-indicator success"></span>
                    <?= $systemStats['database']['connection']['pdo_connected'] ? 'Connected' : 'Disconnected' ?>
                </div>
                <div class="stat-subtitle">
                    Tables: <?= count($systemStats['database']['tables']) ?>
                </div>
            </div>
            
            <div class="stat-card <?= $systemStats['error_count'] > 10 ? 'warning' : 'success' ?>">
                <div class="stat-title">System Errors</div>
                <div class="stat-value"><?= $systemStats['error_count'] ?></div>
                <div class="stat-subtitle">Total logged errors</div>
            </div>
        </div>
        
        <!-- Recent Errors -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-exclamation-triangle"></i> Recent System Errors
            </div>
            <?php if (empty($errors)): ?>
                <p>No recent errors found. System is running smoothly!</p>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                    <div class="log-entry error">
                        <div class="log-time"><?= $error['timestamp'] ?></div>
                        <div class="log-message">
                            <strong><?= htmlspecialchars($error['type']) ?>:</strong>
                            <?= htmlspecialchars($error['message']) ?>
                        </div>
                        <div class="log-details">
                            File: <?= htmlspecialchars($error['file'] ?? 'Unknown') ?>
                            Line: <?= $error['line'] ?? 'Unknown' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Security Events -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-shield-alt"></i> Recent Security Events
            </div>
            <?php if (empty($securityEvents)): ?>
                <p>No recent security events. System is secure!</p>
            <?php else: ?>
                <?php foreach ($securityEvents as $event): ?>
                    <div class="log-entry info">
                        <div class="log-time"><?= $event['timestamp'] ?></div>
                        <div class="log-message">
                            <strong><?= htmlspecialchars($event['event']) ?>:</strong>
                            <?= htmlspecialchars(json_encode($event['data'])) ?>
                        </div>
                        <div class="log-details">
                            IP: <?= htmlspecialchars($event['ip_address']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Database Tables -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-database"></i> Database Tables
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Rows</th>
                        <th>Size (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($systemStats['database']['tables'] as $table): ?>
                        <tr>
                            <td><?= htmlspecialchars($table['table_name']) ?></td>
                            <td><?= number_format($table['table_rows']) ?></td>
                            <td><?= $table['size_mb'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- System Load -->
        <?php if ($systemStats['uptime']): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-tachometer-alt"></i> System Load
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">1 Minute Load</div>
                    <div class="stat-value"><?= $systemStats['uptime']['load_1min'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">5 Minute Load</div>
                    <div class="stat-value"><?= $systemStats['uptime']['load_5min'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">15 Minute Load</div>
                    <div class="stat-value"><?= $systemStats['uptime']['load_15min'] ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>