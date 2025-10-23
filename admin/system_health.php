<?php
/**
 * System Health Check
 * Provides comprehensive system monitoring and health status
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

// Set JSON response header
header('Content-Type: application/json');

try {
    $health_status = [
        'timestamp' => date('Y-m-d H:i:s'),
        'overall_status' => 'healthy',
        'checks' => []
    ];
    
    // Database Health Check
    $db_health = DatabaseManager::checkHealth();
    $health_status['checks']['database'] = $db_health;
    
    if ($db_health['status'] !== 'healthy') {
        $health_status['overall_status'] = 'unhealthy';
    }
    
    // File System Health Check
    $uploads_dir = dirname(__DIR__) . '/uploads';
    $logs_dir = dirname(__DIR__) . '/logs';
    $backups_dir = dirname(__DIR__) . '/backups';
    
    $file_system_health = [
        'status' => 'healthy',
        'message' => 'File system is accessible',
        'details' => []
    ];
    
    // Check uploads directory
    if (!is_dir($uploads_dir)) {
        if (!mkdir($uploads_dir, 0755, true)) {
            $file_system_health['status'] = 'unhealthy';
            $file_system_health['message'] = 'Cannot create uploads directory';
        }
    } else {
        $file_system_health['details']['uploads_writable'] = is_writable($uploads_dir);
    }
    
    // Check logs directory
    if (!is_dir($logs_dir)) {
        if (!mkdir($logs_dir, 0755, true)) {
            $file_system_health['status'] = 'unhealthy';
            $file_system_health['message'] = 'Cannot create logs directory';
        }
    } else {
        $file_system_health['details']['logs_writable'] = is_writable($logs_dir);
    }
    
    // Check backups directory
    if (!is_dir($backups_dir)) {
        if (!mkdir($backups_dir, 0755, true)) {
            $file_system_health['status'] = 'unhealthy';
            $file_system_health['message'] = 'Cannot create backups directory';
        }
    } else {
        $file_system_health['details']['backups_writable'] = is_writable($backups_dir);
    }
    
    $health_status['checks']['file_system'] = $file_system_health;
    
    if ($file_system_health['status'] !== 'healthy') {
        $health_status['overall_status'] = 'unhealthy';
    }
    
    // Session Health Check
    $session_health = [
        'status' => 'healthy',
        'message' => 'Session is working properly',
        'details' => [
            'session_id' => session_id(),
            'session_name' => session_name(),
            'session_lifetime' => ini_get('session.gc_maxlifetime'),
            'last_activity' => $_SESSION['last_activity'] ?? 'unknown'
        ]
    ];
    
    $health_status['checks']['session'] = $session_health;
    
    // Memory Usage Check
    $memory_usage = memory_get_usage(true);
    $memory_limit = ini_get('memory_limit');
    $memory_limit_bytes = self::convertToBytes($memory_limit);
    $memory_percentage = ($memory_usage / $memory_limit_bytes) * 100;
    
    $memory_health = [
        'status' => $memory_percentage > 80 ? 'warning' : 'healthy',
        'message' => $memory_percentage > 80 ? 'High memory usage detected' : 'Memory usage is normal',
        'details' => [
            'current_usage' => self::formatBytes($memory_usage),
            'memory_limit' => $memory_limit,
            'usage_percentage' => round($memory_percentage, 2)
        ]
    ];
    
    $health_status['checks']['memory'] = $memory_health;
    
    if ($memory_percentage > 90) {
        $health_status['overall_status'] = 'unhealthy';
    } elseif ($memory_percentage > 80) {
        $health_status['overall_status'] = 'warning';
    }
    
    // Database Statistics
    if ($db_health['status'] === 'healthy') {
        try {
            $db_stats = DatabaseManager::getStats();
            $health_status['checks']['database_stats'] = $db_stats;
        } catch (Exception $e) {
            $health_status['checks']['database_stats'] = [
                'status' => 'error',
                'message' => 'Failed to collect database statistics',
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Recent Errors Check
    $recent_errors = SystemErrorHandler::getRecentErrors(10);
    $error_count = count($recent_errors);
    
    $errors_health = [
        'status' => $error_count > 50 ? 'warning' : 'healthy',
        'message' => $error_count > 50 ? 'High number of recent errors' : 'Error count is normal',
        'details' => [
            'recent_errors_count' => $error_count,
            'recent_errors' => array_slice($recent_errors, 0, 5) // Show only first 5
        ]
    ];
    
    $health_status['checks']['errors'] = $errors_health;
    
    if ($error_count > 100) {
        $health_status['overall_status'] = 'unhealthy';
    } elseif ($error_count > 50) {
        $health_status['overall_status'] = 'warning';
    }
    
    // System Information
    $health_status['system_info'] = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'operating_system' => PHP_OS,
        'max_execution_time' => ini_get('max_execution_time'),
        'max_input_vars' => ini_get('max_input_vars'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ];
    
    // Set HTTP status code based on overall health
    if ($health_status['overall_status'] === 'unhealthy') {
        http_response_code(500);
    } elseif ($health_status['overall_status'] === 'warning') {
        http_response_code(200); // Still OK, but with warnings
    }
    
    echo json_encode($health_status, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    SystemErrorHandler::logApplicationError("Health check failed: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'overall_status' => 'unhealthy',
        'error' => 'Health check failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

/**
 * Convert memory limit string to bytes
 */
function convertToBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}