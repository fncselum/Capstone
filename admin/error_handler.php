<?php
/**
 * Centralized Error Handler
 * Provides consistent error handling across the system
 */

class SystemErrorHandler {
    private static $logFile;
    private static $initialized = false;
    
    public static function initialize() {
        if (self::$initialized) {
            return;
        }
        
        // Set up error logging
        self::$logFile = dirname(__DIR__) . '/logs/system.log';
        
        // Create logs directory if it doesn't exist
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Set error handler
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$initialized = true;
    }
    
    public static function handleError($severity, $message, $file, $line) {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = self::getErrorType($severity);
        $errorData = [
            'type' => $errorType,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => $severity
        ];
        
        self::logError($errorData);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    public static function handleException($exception) {
        $errorData = [
            'type' => 'EXCEPTION',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'timestamp' => date('Y-m-d H:i:s'),
            'trace' => $exception->getTraceAsString()
        ];
        
        self::logError($errorData);
        
        // Show user-friendly error page
        self::showErrorPage('An unexpected error occurred. Please try again later.');
    }
    
    public static function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorData = [
                'type' => 'FATAL_ERROR',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            self::logError($errorData);
            self::showErrorPage('A critical error occurred. Please contact the administrator.');
        }
    }
    
    private static function getErrorType($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'ERROR';
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'WARNING';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'NOTICE';
            case E_STRICT:
                return 'STRICT';
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'DEPRECATED';
            default:
                return 'UNKNOWN';
        }
    }
    
    private static function logError($errorData) {
        $logEntry = sprintf(
            "[%s] %s: %s in %s on line %d\n",
            $errorData['timestamp'],
            $errorData['type'],
            $errorData['message'],
            $errorData['file'],
            $errorData['line']
        );
        
        if (isset($errorData['trace'])) {
            $logEntry .= "Stack trace:\n" . $errorData['trace'] . "\n";
        }
        
        $logEntry .= str_repeat('-', 80) . "\n";
        
        error_log($logEntry, 3, self::$logFile);
    }
    
    private static function showErrorPage($message) {
        // Don't show error page if headers already sent
        if (headers_sent()) {
            return;
        }
        
        http_response_code(500);
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $message
            ]);
        } else {
            // Show HTML error page
            echo '<!DOCTYPE html>
<html>
<head>
    <title>System Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        .error-container { max-width: 600px; margin: 0 auto; text-align: center; }
        .error-icon { font-size: 48px; color: #e74c3c; margin-bottom: 20px; }
        .error-message { font-size: 18px; color: #333; margin-bottom: 30px; }
        .error-actions { margin-top: 30px; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 0 10px; }
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
        }
        exit;
    }
    
    public static function logUserAction($action, $details = '') {
        $logData = [
            'type' => 'USER_ACTION',
            'action' => $action,
            'details' => $details,
            'user_id' => $_SESSION['admin_id'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $logEntry = sprintf(
            "[%s] USER_ACTION: %s - %s (User: %s, IP: %s)\n",
            $logData['timestamp'],
            $logData['action'],
            $logData['details'],
            $logData['user_id'],
            $logData['ip_address']
        );
        
        error_log($logEntry, 3, self::$logFile);
    }
    
    public static function logSecurityEvent($event, $details = '') {
        $logData = [
            'type' => 'SECURITY_EVENT',
            'event' => $event,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $logEntry = sprintf(
            "[%s] SECURITY_EVENT: %s - %s (IP: %s)\n",
            $logData['timestamp'],
            $logData['event'],
            $logData['details'],
            $logData['ip_address']
        );
        
        error_log($logEntry, 3, self::$logFile);
    }
}

// Initialize error handler
SystemErrorHandler::initialize();
?>