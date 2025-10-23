<?php
/**
 * Centralized Error Handler
 * Provides consistent error handling across the Equipment Kiosk system
 */

// Prevent direct access
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Custom error handler
 */
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_types = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $error_type = $error_types[$errno] ?? 'Unknown Error';
    
    $log_message = sprintf(
        "[%s] [%s] [%s:%d] %s",
        date('Y-m-d H:i:s'),
        $error_type,
        basename($errfile),
        $errline,
        $errstr
    );
    
    // Log to error log
    error_log($log_message);
    
    // Log to custom log file
    logError($log_message, $errno);
    
    // Don't execute PHP internal error handler
    return true;
}

/**
 * Custom exception handler
 */
function customExceptionHandler($exception) {
    $log_message = sprintf(
        "[%s] [Exception] [%s:%d] %s\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        basename($exception->getFile()),
        $exception->getLine(),
        $exception->getMessage(),
        $exception->getTraceAsString()
    );
    
    // Log to error log
    error_log($log_message);
    
    // Log to custom log file
    logError($log_message, E_ERROR);
    
    // Send user-friendly error response
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'An internal error occurred. Please try again later.'
        ]);
    }
}

/**
 * Log error to custom log file
 */
function logError($message, $level = E_ERROR) {
    $log_file = __DIR__ . '/../logs/errors.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Log database errors
 */
function logDatabaseError($message, $query = '', $params = []) {
    $log_message = sprintf(
        "[%s] [Database Error] %s\nQuery: %s\nParams: %s",
        date('Y-m-d H:i:s'),
        $message,
        $query,
        json_encode($params)
    );
    
    error_log($log_message);
    logError($log_message, E_ERROR);
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = '') {
    $log_file = __DIR__ . '/../logs/security.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 'anonymous';
    
    $log_entry = "[$timestamp] [$event] [User: $user_id] [IP: $ip] $details" . PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Log transaction events
 */
function logTransaction($action, $details = []) {
    $log_file = __DIR__ . '/../logs/transactions.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 'system';
    
    $log_entry = sprintf(
        "[%s] [%s] [User: %s] %s\n",
        $timestamp,
        $action,
        $user_id,
        json_encode($details)
    );
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Safe database operation wrapper
 */
function safeDatabaseOperation($operation, $error_message = 'Database operation failed') {
    try {
        return $operation();
    } catch (PDOException $e) {
        logDatabaseError($e->getMessage());
        return ['success' => false, 'message' => $error_message];
    } catch (Exception $e) {
        logError($e->getMessage());
        return ['success' => false, 'message' => $error_message];
    }
}

/**
 * Initialize error handling
 */
function initializeErrorHandling() {
    // Set custom error handler
    set_error_handler('customErrorHandler');
    
    // Set custom exception handler
    set_exception_handler('customExceptionHandler');
    
    // Set error reporting level
    error_reporting(E_ALL);
    
    // Set display errors to false in production
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Initialize error handling when this file is included
initializeErrorHandling();