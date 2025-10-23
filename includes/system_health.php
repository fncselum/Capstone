<?php
/**
 * System Health Monitoring and Validation
 * Ensures system stability and prevents breakage
 */

class SystemHealth {
    private $errors = [];
    private $warnings = [];
    private $db_connection = null;
    
    public function __construct($db_connection = null) {
        $this->db_connection = $db_connection;
    }
    
    /**
     * Perform comprehensive system health check
     */
    public function performHealthCheck() {
        $this->errors = [];
        $this->warnings = [];
        
        // Check database connectivity
        $this->checkDatabaseHealth();
        
        // Check file system permissions
        $this->checkFileSystemHealth();
        
        // Check memory usage
        $this->checkMemoryHealth();
        
        // Check disk space
        $this->checkDiskSpaceHealth();
        
        // Check session security
        $this->checkSessionHealth();
        
        // Check error log size
        $this->checkErrorLogHealth();
        
        return [
            'status' => empty($this->errors) ? 'healthy' : 'unhealthy',
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Check database connectivity and performance
     */
    private function checkDatabaseHealth() {
        if (!$this->db_connection) {
            $this->errors[] = "Database connection not available";
            return;
        }
        
        try {
            // Test basic connectivity
            $result = $this->db_connection->query("SELECT 1");
            if (!$result) {
                $this->errors[] = "Database query test failed";
                return;
            }
            
            // Check for long-running queries
            $processlist = $this->db_connection->query("SHOW PROCESSLIST");
            if ($processlist) {
                $long_queries = 0;
                while ($row = $processlist->fetch_assoc()) {
                    if ($row['Time'] > 30) { // Queries running longer than 30 seconds
                        $long_queries++;
                    }
                }
                if ($long_queries > 0) {
                    $this->warnings[] = "Found {$long_queries} long-running database queries";
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Database health check failed: " . $e->getMessage();
        }
    }
    
    /**
     * Check file system permissions and accessibility
     */
    private function checkFileSystemHealth() {
        $critical_dirs = [
            'uploads/',
            'logs/',
            'admin/',
            'config/'
        ];
        
        foreach ($critical_dirs as $dir) {
            if (!is_dir($dir)) {
                $this->errors[] = "Critical directory missing: {$dir}";
                continue;
            }
            
            if (!is_readable($dir)) {
                $this->errors[] = "Directory not readable: {$dir}";
            }
            
            if (!is_writable($dir)) {
                $this->warnings[] = "Directory not writable: {$dir}";
            }
        }
        
        // Check uploads directory specifically
        if (is_dir('uploads/')) {
            $files = glob('uploads/*');
            if (count($files) > 1000) {
                $this->warnings[] = "Uploads directory has many files (" . count($files) . "). Consider cleanup.";
            }
        }
    }
    
    /**
     * Check memory usage
     */
    private function checkMemoryHealth() {
        $memory_usage = memory_get_usage(true);
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit !== '-1') {
            $memory_limit_bytes = $this->convertToBytes($memory_limit);
            $usage_percentage = ($memory_usage / $memory_limit_bytes) * 100;
            
            if ($usage_percentage > 80) {
                $this->errors[] = "Memory usage critical: " . round($usage_percentage, 2) . "%";
            } elseif ($usage_percentage > 60) {
                $this->warnings[] = "Memory usage high: " . round($usage_percentage, 2) . "%";
            }
        }
    }
    
    /**
     * Check available disk space
     */
    private function checkDiskSpaceHealth() {
        $free_bytes = disk_free_space('.');
        $total_bytes = disk_total_space('.');
        
        if ($free_bytes && $total_bytes) {
            $free_percentage = ($free_bytes / $total_bytes) * 100;
            
            if ($free_percentage < 10) {
                $this->errors[] = "Disk space critical: " . round($free_percentage, 2) . "% free";
            } elseif ($free_percentage < 20) {
                $this->warnings[] = "Disk space low: " . round($free_percentage, 2) . "% free";
            }
        }
    }
    
    /**
     * Check session security configuration
     */
    private function checkSessionHealth() {
        $session_config = [
            'session.cookie_httponly' => '1',
            'session.use_only_cookies' => '1',
            'session.cookie_secure' => '0', // Can be 0 for local development
            'session.use_strict_mode' => '1'
        ];
        
        foreach ($session_config as $setting => $expected) {
            $current = ini_get($setting);
            if ($current !== $expected) {
                $this->warnings[] = "Session security setting '{$setting}' is '{$current}', should be '{$expected}'";
            }
        }
    }
    
    /**
     * Check error log size and rotation
     */
    private function checkErrorLogHealth() {
        $log_file = 'logs/system_errors.log';
        
        if (file_exists($log_file)) {
            $log_size = filesize($log_file);
            $log_size_mb = $log_size / (1024 * 1024);
            
            if ($log_size_mb > 50) {
                $this->warnings[] = "Error log is large: " . round($log_size_mb, 2) . "MB. Consider rotation.";
            }
        }
    }
    
    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes($val) {
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
     * Log system health status
     */
    public function logHealthStatus($status) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $status['status'],
            'errors_count' => count($status['errors']),
            'warnings_count' => count($status['warnings']),
            'errors' => $status['errors'],
            'warnings' => $status['warnings']
        ];
        
        $this->ensureLogDirectory();
        file_put_contents('logs/health_check.log', json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        if (!is_dir('logs/')) {
            mkdir('logs/', 0755, true);
        }
    }
}

/**
 * Input Validation Helper Class
 */
class InputValidator {
    
    /**
     * Validate equipment data
     */
    public static function validateEquipmentData($data) {
        $errors = [];
        
        // Name validation
        if (empty($data['name'])) {
            $errors[] = "Equipment name is required";
        } elseif (strlen($data['name']) > 100) {
            $errors[] = "Equipment name must be less than 100 characters";
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-_\.]+$/', $data['name'])) {
            $errors[] = "Equipment name contains invalid characters";
        }
        
        // RFID tag validation
        if (empty($data['rfid_tag'])) {
            $errors[] = "RFID tag is required";
        } elseif (strlen($data['rfid_tag']) > 50) {
            $errors[] = "RFID tag must be less than 50 characters";
        } elseif (!preg_match('/^[a-zA-Z0-9\-_]+$/', $data['rfid_tag'])) {
            $errors[] = "RFID tag contains invalid characters";
        }
        
        // Quantity validation
        if (!isset($data['quantity']) || !is_numeric($data['quantity'])) {
            $errors[] = "Quantity must be a number";
        } elseif ($data['quantity'] < 0) {
            $errors[] = "Quantity must be positive";
        } elseif ($data['quantity'] > 9999) {
            $errors[] = "Quantity too large (max 9999)";
        }
        
        // Category validation
        if (!empty($data['category_id']) && !is_numeric($data['category_id'])) {
            $errors[] = "Invalid category selection";
        }
        
        // Size category validation
        if (!empty($data['size_category'])) {
            $valid_sizes = ['Small', 'Medium', 'Large'];
            if (!in_array($data['size_category'], $valid_sizes)) {
                $errors[] = "Invalid size category";
            }
        }
        
        // Description validation
        if (!empty($data['description']) && strlen($data['description']) > 500) {
            $errors[] = "Description must be less than 500 characters";
        }
        
        return $errors;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim(strip_tags($value));
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error: " . $file['error'];
            return $errors;
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = "File too large (max 5MB)";
        }
        
        // Check file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            $errors[] = "Invalid file type. Allowed: " . implode(', ', $allowed_types);
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        ];
        
        if (!in_array($mime_type, $allowed_mimes)) {
            $errors[] = "Invalid file format";
        }
        
        return $errors;
    }
}

/**
 * Error Handler Class
 */
class ErrorHandler {
    
    /**
     * Log error with context
     */
    public static function logError($message, $context = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        self::ensureLogDirectory();
        file_put_contents('logs/system_errors.log', json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Handle database errors gracefully
     */
    public static function handleDatabaseError($error, $operation = '') {
        self::logError("Database error during {$operation}", ['error' => $error]);
        
        // Return user-friendly message
        return "A database error occurred. Please try again later.";
    }
    
    /**
     * Handle validation errors
     */
    public static function handleValidationErrors($errors) {
        self::logError("Validation errors", ['errors' => $errors]);
        
        return "Please correct the following errors: " . implode(', ', $errors);
    }
    
    /**
     * Ensure log directory exists
     */
    private static function ensureLogDirectory() {
        if (!is_dir('logs/')) {
            mkdir('logs/', 0755, true);
        }
    }
}
?>