<?php
/**
 * Centralized Error Handler for Equipment Management System
 * Provides consistent error handling, logging, and user feedback
 */

class SystemErrorHandler {
    private static $logFile = '../logs/system_errors.log';
    private static $debugMode = false; // Set to true in development
    
    /**
     * Initialize error handler
     */
    public static function init() {
        // Set error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', self::$debugMode ? 1 : 0);
        ini_set('log_errors', 1);
        
        // Set custom error handler
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        // Create logs directory if it doesn't exist
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorData = [
            'type' => 'PHP Error',
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        self::logError($errorData);
        
        if (self::$debugMode) {
            echo "<div style='background:#ffebee;border:1px solid #f44336;padding:10px;margin:10px;border-radius:4px;'>";
            echo "<strong>PHP Error:</strong> {$message} in {$file} on line {$line}";
            echo "</div>";
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException($exception) {
        $errorData = [
            'type' => 'Uncaught Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        self::logError($errorData);
        
        if (self::$debugMode) {
            echo "<div style='background:#ffebee;border:1px solid #f44336;padding:10px;margin:10px;border-radius:4px;'>";
            echo "<strong>Uncaught Exception:</strong> {$exception->getMessage()}<br>";
            echo "<strong>File:</strong> {$exception->getFile()} on line {$exception->getLine()}<br>";
            echo "<strong>Trace:</strong><pre>{$exception->getTraceAsString()}</pre>";
            echo "</div>";
        } else {
            self::showUserFriendlyError();
        }
    }
    
    /**
     * Handle fatal errors
     */
    public static function handleShutdown() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorData = [
                'type' => 'Fatal Error',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ];
            
            self::logError($errorData);
            
            if (!self::$debugMode) {
                self::showUserFriendlyError();
            }
        }
    }
    
    /**
     * Log error to file
     */
    private static function logError($errorData) {
        $logEntry = json_encode($errorData) . "\n";
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Show user-friendly error message
     */
    private static function showUserFriendlyError() {
        if (headers_sent()) {
            return;
        }
        
        http_response_code(500);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>System Error - Equipment Management</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .error-container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .error-icon { font-size: 48px; color: #f44336; text-align: center; margin-bottom: 20px; }
                .error-title { color: #f44336; text-align: center; margin-bottom: 20px; }
                .error-message { color: #666; text-align: center; line-height: 1.6; }
                .error-actions { text-align: center; margin-top: 30px; }
                .btn { display: inline-block; padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; margin: 0 10px; }
                .btn:hover { background: #1976D2; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h1 class="error-title">System Temporarily Unavailable</h1>
                <p class="error-message">
                    We're experiencing technical difficulties. Our team has been notified and is working to resolve the issue.
                    Please try again in a few minutes.
                </p>
                <div class="error-actions">
                    <a href="javascript:history.back()" class="btn">Go Back</a>
                    <a href="/" class="btn">Home</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Handle database errors specifically
     */
    public static function handleDatabaseError($error, $query = null) {
        $errorData = [
            'type' => 'Database Error',
            'message' => $error,
            'query' => $query,
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        self::logError($errorData);
        
        if (self::$debugMode) {
            echo "<div style='background:#ffebee;border:1px solid #f44336;padding:10px;margin:10px;border-radius:4px;'>";
            echo "<strong>Database Error:</strong> {$error}";
            if ($query) {
                echo "<br><strong>Query:</strong> " . htmlspecialchars($query);
            }
            echo "</div>";
        }
        
        throw new Exception('Database operation failed. Please try again.');
    }
    
    /**
     * Handle validation errors
     */
    public static function handleValidationError($field, $message) {
        $errorData = [
            'type' => 'Validation Error',
            'field' => $field,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI'
        ];
        
        self::logError($errorData);
        
        return [
            'success' => false,
            'field' => $field,
            'message' => $message
        ];
    }
    
    /**
     * Get recent errors for admin review
     */
    public static function getRecentErrors($limit = 50) {
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $lines = file(self::$logFile, FILE_IGNORE_NEW_LINES);
        $errors = [];
        
        for ($i = count($lines) - 1; $i >= 0 && count($errors) < $limit; $i--) {
            $error = json_decode($lines[$i], true);
            if ($error) {
                $errors[] = $error;
            }
        }
        
        return $errors;
    }
    
    /**
     * Clear old error logs (older than 30 days)
     */
    public static function clearOldLogs() {
        if (!file_exists(self::$logFile)) {
            return;
        }
        
        $lines = file(self::$logFile, FILE_IGNORE_NEW_LINES);
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
        $newLines = [];
        
        foreach ($lines as $line) {
            $error = json_decode($line, true);
            if ($error && $error['timestamp'] > $cutoff) {
                $newLines[] = $line;
            }
        }
        
        file_put_contents(self::$logFile, implode("\n", $newLines) . "\n");
    }
}

// Initialize error handler when this file is included
SystemErrorHandler::init();
?>