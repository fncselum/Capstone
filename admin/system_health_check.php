<?php
/**
 * System Health Check
 * Monitors system stability and provides health status
 */

define('SYSTEM_ACCESS', true);
require_once '../includes/error_handler.php';
require_once '../includes/security.php';
require_once '../includes/database_safe.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if admin is authenticated
if (!isAdminAuthenticated()) {
    sendErrorResponse('Unauthorized access', 401);
}

$health_status = [
    'timestamp' => date('Y-m-d H:i:s'),
    'overall_status' => 'healthy',
    'checks' => []
];

/**
 * Check database connectivity
 */
function checkDatabaseConnection() {
    try {
        $pdo = getSafeDatabaseConnection();
        $stmt = $pdo->query("SELECT 1");
        return ['status' => 'healthy', 'message' => 'Database connection successful'];
    } catch (Exception $e) {
        return ['status' => 'critical', 'message' => 'Database connection failed: ' . $e->getMessage()];
    }
}

/**
 * Check critical tables exist
 */
function checkCriticalTables() {
    try {
        $pdo = getSafeDatabaseConnection();
        $tables = ['equipment', 'inventory', 'transactions', 'users', 'categories', 'admin_users'];
        $missing_tables = [];
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                $missing_tables[] = $table;
            }
        }
        
        if (empty($missing_tables)) {
            return ['status' => 'healthy', 'message' => 'All critical tables exist'];
        } else {
            return ['status' => 'critical', 'message' => 'Missing tables: ' . implode(', ', $missing_tables)];
        }
    } catch (Exception $e) {
        return ['status' => 'critical', 'message' => 'Failed to check tables: ' . $e->getMessage()];
    }
}

/**
 * Check disk space
 */
function checkDiskSpace() {
    $free_bytes = disk_free_space(__DIR__);
    $total_bytes = disk_total_space(__DIR__);
    $free_percent = ($free_bytes / $total_bytes) * 100;
    
    if ($free_percent < 5) {
        return ['status' => 'critical', 'message' => "Disk space critically low: {$free_percent}% free"];
    } elseif ($free_percent < 15) {
        return ['status' => 'warning', 'message' => "Disk space low: {$free_percent}% free"];
    } else {
        return ['status' => 'healthy', 'message' => "Disk space OK: {$free_percent}% free"];
    }
}

/**
 * Check log file sizes
 */
function checkLogFiles() {
    $log_dir = __DIR__ . '/../logs';
    $max_log_size = 10 * 1024 * 1024; // 10MB
    $issues = [];
    
    if (!is_dir($log_dir)) {
        return ['status' => 'warning', 'message' => 'Log directory does not exist'];
    }
    
    $log_files = glob($log_dir . '/*.log');
    foreach ($log_files as $log_file) {
        $size = filesize($log_file);
        if ($size > $max_log_size) {
            $issues[] = basename($log_file) . ' (' . round($size / 1024 / 1024, 2) . 'MB)';
        }
    }
    
    if (empty($issues)) {
        return ['status' => 'healthy', 'message' => 'All log files are within size limits'];
    } else {
        return ['status' => 'warning', 'message' => 'Large log files: ' . implode(', ', $issues)];
    }
}

/**
 * Check recent errors
 */
function checkRecentErrors() {
    $log_file = __DIR__ . '/../logs/errors.log';
    
    if (!file_exists($log_file)) {
        return ['status' => 'healthy', 'message' => 'No error log file found'];
    }
    
    $recent_errors = 0;
    $critical_errors = 0;
    $one_hour_ago = time() - 3600;
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $log_time = strtotime($matches[1]);
            if ($log_time > $one_hour_ago) {
                $recent_errors++;
                if (strpos($line, '[Fatal Error]') !== false || strpos($line, '[Exception]') !== false) {
                    $critical_errors++;
                }
            }
        }
    }
    
    if ($critical_errors > 0) {
        return ['status' => 'critical', 'message' => "$critical_errors critical errors in the last hour"];
    } elseif ($recent_errors > 10) {
        return ['status' => 'warning', 'message' => "$recent_errors errors in the last hour"];
    } else {
        return ['status' => 'healthy', 'message' => "Only $recent_errors errors in the last hour"];
    }
}

/**
 * Check system performance
 */
function checkSystemPerformance() {
    $start_time = microtime(true);
    
    try {
        // Test database query performance
        $pdo = getSafeDatabaseConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM equipment");
        $stmt->fetch();
        
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
        
        if ($execution_time > 1000) {
            return ['status' => 'warning', 'message' => "Slow database response: {$execution_time}ms"];
        } else {
            return ['status' => 'healthy', 'message' => "Database response time: {$execution_time}ms"];
        }
    } catch (Exception $e) {
        return ['status' => 'critical', 'message' => 'Performance check failed: ' . $e->getMessage()];
    }
}

/**
 * Check file permissions
 */
function checkFilePermissions() {
    $critical_dirs = [
        '../uploads' => 0755,
        '../logs' => 0755,
        '../config' => 0755
    ];
    
    $issues = [];
    
    foreach ($critical_dirs as $dir => $expected_permissions) {
        $full_path = __DIR__ . '/' . $dir;
        if (is_dir($full_path)) {
            $actual_permissions = fileperms($full_path) & 0777;
            if ($actual_permissions !== $expected_permissions) {
                $issues[] = "$dir has incorrect permissions (expected: $expected_permissions, actual: $actual_permissions)";
            }
        } else {
            $issues[] = "$dir does not exist";
        }
    }
    
    if (empty($issues)) {
        return ['status' => 'healthy', 'message' => 'All critical directories have correct permissions'];
    } else {
        return ['status' => 'warning', 'message' => implode('; ', $issues)];
    }
}

// Run all health checks
$checks = [
    'database_connection' => checkDatabaseConnection(),
    'critical_tables' => checkCriticalTables(),
    'disk_space' => checkDiskSpace(),
    'log_files' => checkLogFiles(),
    'recent_errors' => checkRecentErrors(),
    'system_performance' => checkSystemPerformance(),
    'file_permissions' => checkFilePermissions()
];

$health_status['checks'] = $checks;

// Determine overall status
$critical_issues = 0;
$warning_issues = 0;

foreach ($checks as $check) {
    if ($check['status'] === 'critical') {
        $critical_issues++;
    } elseif ($check['status'] === 'warning') {
        $warning_issues++;
    }
}

if ($critical_issues > 0) {
    $health_status['overall_status'] = 'critical';
} elseif ($warning_issues > 0) {
    $health_status['overall_status'] = 'warning';
}

// Add summary
$health_status['summary'] = [
    'total_checks' => count($checks),
    'healthy' => count($checks) - $critical_issues - $warning_issues,
    'warnings' => $warning_issues,
    'critical' => $critical_issues
];

// Log health check
logTransaction('health_check', [
    'overall_status' => $health_status['overall_status'],
    'critical_issues' => $critical_issues,
    'warning_issues' => $warning_issues
]);

sendJSONResponse($health_status);