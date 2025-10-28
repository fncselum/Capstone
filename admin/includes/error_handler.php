<?php
/**
 * System Error Handler
 * Provides centralized error handling and logging
 */

class SystemErrorHandler {
    
    private static $log_file;
    private static $initialized = false;
    
    /**
     * Initialize error handler
     */
    public static function init($log_file = null) {
        if (self::$initialized) {
            return;
        }
        
        self::$log_file = $log_file ?: dirname(__DIR__) . '/logs/system_errors.log';
        
        // Create logs directory if it doesn't exist
        $log_dir = dirname(self::$log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // Set error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', 0); // Don't display errors to users
        ini_set('log_errors', 1);
        ini_set('error_log', self::$log_file);
        
        // Set custom error handler
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$initialized = true;
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error_type = self::getErrorType($severity);
        $error_data = [
            'type' => $error_type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => self::getClientIP()
        ];
        
        self::logError($error_data);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException($exception) {
        $error_data = [
            'type' => 'EXCEPTION',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => self::getClientIP()
        ];
        
        self::logError($error_data);
        
        // Show user-friendly error page
        self::showErrorPage('A system error occurred. Please try again later.');
    }
    
    /**
     * Handle fatal errors
     */
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $error_data = [
                'type' => 'FATAL_ERROR',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'ip_address' => self::getClientIP()
            ];
            
            self::logError($error_data);
            self::showErrorPage('A critical system error occurred. Please contact support.');
        }
    }
    
    /**
     * Log error to file
     */
    private static function logError($error_data) {
        $log_entry = sprintf(
            "[%s] %s: %s in %s on line %d\n",
            $error_data['timestamp'],
            $error_data['type'],
            $error_data['message'],
            $error_data['file'],
            $error_data['line']
        );
        
        if (isset($error_data['trace'])) {
            $log_entry .= "Stack trace:\n" . $error_data['trace'] . "\n";
        }
        
        $log_entry .= "Request URI: " . $error_data['request_uri'] . "\n";
        $log_entry .= "IP Address: " . $error_data['ip_address'] . "\n";
        $log_entry .= str_repeat('-', 80) . "\n";
        
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get error type from severity
     */
    private static function getErrorType($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'FATAL_ERROR';
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'WARNING';
            case E_PARSE:
                return 'PARSE_ERROR';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'NOTICE';
            case E_STRICT:
                return 'STRICT';
            case E_RECOVERABLE_ERROR:
                return 'RECOVERABLE_ERROR';
            default:
                return 'UNKNOWN';
        }
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Show user-friendly error page
     */
    private static function showErrorPage($message) {
        if (headers_sent()) {
            return;
        }
        
        http_response_code(500);
        
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .error-container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-icon { font-size: 48px; color: #e74c3c; text-align: center; margin-bottom: 20px; }
        .error-message { text-align: center; color: #333; font-size: 18px; margin-bottom: 20px; }
        .error-actions { text-align: center; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; margin: 0 10px; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <div class="error-message">' . htmlspecialchars($message) . '</div>
        <div class="error-actions">
            <a href="javascript:history.back()" class="btn">Go Back</a>
            <a href="admin-dashboard.php" class="btn">Dashboard</a>
        </div>
    </div>
</body>
</html>';
        
        echo $html;
        exit;
    }
    
    /**
     * Log custom application error
     */
    public static function logApplicationError($message, $context = []) {
        $error_data = [
            'type' => 'APPLICATION_ERROR',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => self::getClientIP()
        ];
        
        self::logError($error_data);
    }
    
    /**
     * Log database error
     */
    public static function logDatabaseError($message, $sql = null, $params = []) {
        $error_data = [
            'type' => 'DATABASE_ERROR',
            'message' => $message,
            'sql' => $sql,
            'params' => $params,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => self::getClientIP()
        ];
        
        self::logError($error_data);
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent($message, $context = []) {
        $error_data = [
            'type' => 'SECURITY_EVENT',
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => self::getClientIP()
        ];
        
        self::logError($error_data);
    }
    
    /**
     * Get recent errors
     */
    public static function getRecentErrors($limit = 50) {
        if (!file_exists(self::$log_file)) {
            return [];
        }
        
        $lines = file(self::$log_file, FILE_IGNORE_NEW_LINES);
        $errors = [];
        $current_error = [];
        
        foreach (array_reverse($lines) as $line) {
            if (strpos($line, '[') === 0) {
                if (!empty($current_error)) {
                    $errors[] = $current_error;
                    if (count($errors) >= $limit) {
                        break;
                    }
                }
                $current_error = ['line' => $line];
            } else {
                $current_error['details'][] = $line;
            }
        }
        
        if (!empty($current_error)) {
            $errors[] = $current_error;
        }
        
        return $errors;
    }
}