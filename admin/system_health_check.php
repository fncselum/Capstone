<?php
/**
 * System Health Check Script
 * Monitors system stability and reports issues
 */

// Security: Only allow admin access
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Access denied');
}

// Set error reporting for health check
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1);

class SystemHealthChecker {
    private $pdo;
    private $healthStatus = [];
    private $criticalIssues = [];
    private $warnings = [];
    
    public function __construct() {
        $this->initializeDatabase();
    }
    
    private function initializeDatabase() {
        try {
            $host = "localhost";
            $user = "root";
            $password = "";
            $dbname = "capstone";
            
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->healthStatus['database_connection'] = 'OK';
        } catch (PDOException $e) {
            $this->criticalIssues[] = "Database connection failed: " . $e->getMessage();
            $this->healthStatus['database_connection'] = 'FAILED';
        }
    }
    
    public function runHealthCheck() {
        $this->checkDatabaseHealth();
        $this->checkFileSystemHealth();
        $this->checkSessionHealth();
        $this->checkUploadDirectoryHealth();
        $this->checkSystemResources();
        $this->checkSecuritySettings();
        
        return $this->generateReport();
    }
    
    private function checkDatabaseHealth() {
        if (!$this->pdo) {
            return;
        }
        
        try {
            // Check if required tables exist
            $requiredTables = ['equipment', 'inventory', 'categories', 'users', 'transactions'];
            $stmt = $this->pdo->query("SHOW TABLES");
            $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($requiredTables as $table) {
                if (!in_array($table, $existingTables)) {
                    $this->criticalIssues[] = "Required table '$table' is missing";
                }
            }
            
            // Check database size
            $stmt = $this->pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema='capstone'");
            $dbSize = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->healthStatus['database_size_mb'] = $dbSize['DB Size in MB'] ?? 0;
            
            // Check for recent errors in equipment table
            $stmt = $this->pdo->query("SELECT COUNT(*) as error_count FROM equipment WHERE name IS NULL OR name = ''");
            $errorCount = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($errorCount['error_count'] > 0) {
                $this->warnings[] = "Found {$errorCount['error_count']} equipment records with missing names";
            }
            
            $this->healthStatus['database_health'] = 'OK';
            
        } catch (PDOException $e) {
            $this->criticalIssues[] = "Database health check failed: " . $e->getMessage();
            $this->healthStatus['database_health'] = 'FAILED';
        }
    }
    
    private function checkFileSystemHealth() {
        // Check uploads directory
        $uploadDir = dirname(__DIR__) . '/uploads/';
        if (!is_dir($uploadDir)) {
            $this->criticalIssues[] = "Uploads directory does not exist";
        } else {
            if (!is_writable($uploadDir)) {
                $this->criticalIssues[] = "Uploads directory is not writable";
            }
            
            // Check disk space
            $freeBytes = disk_free_space($uploadDir);
            $totalBytes = disk_total_space($uploadDir);
            $freePercent = ($freeBytes / $totalBytes) * 100;
            
            $this->healthStatus['disk_free_percent'] = round($freePercent, 2);
            
            if ($freePercent < 10) {
                $this->criticalIssues[] = "Disk space critically low: " . round($freePercent, 2) . "% free";
            } elseif ($freePercent < 20) {
                $this->warnings[] = "Disk space low: " . round($freePercent, 2) . "% free";
            }
        }
        
        // Check log file
        $logFile = dirname(__DIR__) . '/logs/system.log';
        if (file_exists($logFile)) {
            $logSize = filesize($logFile);
            $this->healthStatus['log_file_size_kb'] = round($logSize / 1024, 2);
            
            if ($logSize > 10 * 1024 * 1024) { // 10MB
                $this->warnings[] = "Log file is getting large: " . round($logSize / 1024 / 1024, 2) . "MB";
            }
        }
    }
    
    private function checkSessionHealth() {
        // Check session configuration
        $sessionLifetime = ini_get('session.gc_maxlifetime');
        $this->healthStatus['session_lifetime'] = $sessionLifetime;
        
        if ($sessionLifetime < 1800) { // 30 minutes
            $this->warnings[] = "Session lifetime is very short: {$sessionLifetime} seconds";
        }
        
        // Check if session is secure
        if (ini_get('session.cookie_secure') == 0) {
            $this->warnings[] = "Session cookies are not secure (should be enabled in production)";
        }
    }
    
    private function checkUploadDirectoryHealth() {
        $uploadDir = dirname(__DIR__) . '/uploads/';
        
        if (is_dir($uploadDir)) {
            // Check for suspicious files
            $files = scandir($uploadDir);
            $suspiciousExtensions = ['.php', '.phtml', '.php3', '.php4', '.php5', '.pl', '.py', '.jsp', '.asp', '.sh', '.cgi'];
            
            foreach ($files as $file) {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array('.' . $extension, $suspiciousExtensions)) {
                    $this->warnings[] = "Suspicious file found in uploads: $file";
                }
            }
            
            // Count files
            $fileCount = count($files) - 2; // Subtract . and ..
            $this->healthStatus['upload_files_count'] = $fileCount;
        }
    }
    
    private function checkSystemResources() {
        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $this->healthStatus['memory_usage_mb'] = round($memoryUsage / 1024 / 1024, 2);
        $this->healthStatus['memory_limit'] = $memoryLimit;
        
        // Check if we're approaching memory limit
        if (is_numeric($memoryLimit)) {
            $memoryLimitBytes = $memoryLimit * 1024 * 1024;
            $usagePercent = ($memoryUsage / $memoryLimitBytes) * 100;
            
            if ($usagePercent > 80) {
                $this->warnings[] = "Memory usage is high: " . round($usagePercent, 2) . "%";
            }
        }
        
        // Check execution time
        $maxExecutionTime = ini_get('max_execution_time');
        $this->healthStatus['max_execution_time'] = $maxExecutionTime;
    }
    
    private function checkSecuritySettings() {
        // Check if error reporting is disabled in production
        $displayErrors = ini_get('display_errors');
        if ($displayErrors == 1) {
            $this->warnings[] = "Error display is enabled (should be disabled in production)";
        }
        
        // Check if register_globals is disabled
        $registerGlobals = ini_get('register_globals');
        if ($registerGlobals == 1) {
            $this->criticalIssues[] = "register_globals is enabled (security risk)";
        }
        
        // Check if magic_quotes is disabled
        $magicQuotes = ini_get('magic_quotes_gpc');
        if ($magicQuotes == 1) {
            $this->warnings[] = "magic_quotes_gpc is enabled (deprecated and potentially problematic)";
        }
    }
    
    private function generateReport() {
        $overallStatus = 'HEALTHY';
        
        if (!empty($this->criticalIssues)) {
            $overallStatus = 'CRITICAL';
        } elseif (!empty($this->warnings)) {
            $overallStatus = 'WARNING';
        }
        
        return [
            'overall_status' => $overallStatus,
            'timestamp' => date('Y-m-d H:i:s'),
            'health_status' => $this->healthStatus,
            'critical_issues' => $this->criticalIssues,
            'warnings' => $this->warnings,
            'recommendations' => $this->generateRecommendations()
        ];
    }
    
    private function generateRecommendations() {
        $recommendations = [];
        
        if (!empty($this->criticalIssues)) {
            $recommendations[] = "Address critical issues immediately";
        }
        
        if (!empty($this->warnings)) {
            $recommendations[] = "Review and address warnings when possible";
        }
        
        if ($this->healthStatus['disk_free_percent'] < 30) {
            $recommendations[] = "Consider cleaning up old files or expanding storage";
        }
        
        if ($this->healthStatus['database_size_mb'] > 100) {
            $recommendations[] = "Consider database optimization or archiving old data";
        }
        
        $recommendations[] = "Run this health check regularly (daily recommended)";
        $recommendations[] = "Set up automated monitoring and alerting";
        $recommendations[] = "Keep system documentation updated";
        
        return $recommendations;
    }
}

// Run health check
$healthChecker = new SystemHealthChecker();
$report = $healthChecker->runHealthCheck();

// Output report
header('Content-Type: application/json');
echo json_encode($report, JSON_PRETTY_PRINT);
?>