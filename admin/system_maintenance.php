<?php
/**
 * System Maintenance
 * Automated maintenance tasks for the Equipment Kiosk system
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

$maintenance_results = [];
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    try {
        switch ($action) {
            case 'clean_logs':
                $maintenance_results[] = cleanLogFiles();
                break;
            case 'optimize_database':
                $maintenance_results[] = optimizeDatabase();
                break;
            case 'clean_temp_files':
                $maintenance_results[] = cleanTempFiles();
                break;
            case 'backup_database':
                $maintenance_results[] = backupDatabase();
                break;
            case 'run_all':
                $maintenance_results[] = cleanLogFiles();
                $maintenance_results[] = optimizeDatabase();
                $maintenance_results[] = cleanTempFiles();
                $maintenance_results[] = backupDatabase();
                break;
        }
    } catch (Exception $e) {
        $maintenance_results[] = ['action' => $action, 'status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Clean old log files
 */
function cleanLogFiles() {
    $log_dir = __DIR__ . '/../logs';
    $max_age_days = 30;
    $max_size_mb = 10;
    $cleaned_files = 0;
    $freed_space = 0;
    
    if (!is_dir($log_dir)) {
        return ['action' => 'clean_logs', 'status' => 'warning', 'message' => 'Log directory does not exist'];
    }
    
    $log_files = glob($log_dir . '/*.log');
    $cutoff_time = time() - ($max_age_days * 24 * 60 * 60);
    
    foreach ($log_files as $log_file) {
        $file_age = filemtime($log_file);
        $file_size = filesize($log_file);
        
        // Delete old files or files that are too large
        if ($file_age < $cutoff_time || $file_size > ($max_size_mb * 1024 * 1024)) {
            $freed_space += $file_size;
            if (unlink($log_file)) {
                $cleaned_files++;
            }
        }
    }
    
    return [
        'action' => 'clean_logs',
        'status' => 'success',
        'message' => "Cleaned $cleaned_files log files, freed " . round($freed_space / 1024 / 1024, 2) . " MB"
    ];
}

/**
 * Optimize database tables
 */
function optimizeDatabase() {
    try {
        $pdo = getSafeDatabaseConnection();
        $tables = ['equipment', 'inventory', 'transactions', 'users', 'categories', 'admin_users'];
        $optimized_tables = 0;
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("OPTIMIZE TABLE $table");
            if ($stmt) {
                $optimized_tables++;
            }
        }
        
        return [
            'action' => 'optimize_database',
            'status' => 'success',
            'message' => "Optimized $optimized_tables database tables"
        ];
    } catch (Exception $e) {
        return [
            'action' => 'optimize_database',
            'status' => 'error',
            'message' => 'Database optimization failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Clean temporary files
 */
function cleanTempFiles() {
    $temp_dirs = [
        sys_get_temp_dir(),
        __DIR__ . '/../uploads/temp',
        __DIR__ . '/../cache'
    ];
    
    $cleaned_files = 0;
    $freed_space = 0;
    $max_age_hours = 24;
    $cutoff_time = time() - ($max_age_hours * 60 * 60);
    
    foreach ($temp_dirs as $temp_dir) {
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoff_time) {
                    $file_size = filesize($file);
                    if (unlink($file)) {
                        $cleaned_files++;
                        $freed_space += $file_size;
                    }
                }
            }
        }
    }
    
    return [
        'action' => 'clean_temp_files',
        'status' => 'success',
        'message' => "Cleaned $cleaned_files temporary files, freed " . round($freed_space / 1024 / 1024, 2) . " MB"
    ];
}

/**
 * Backup database
 */
function backupDatabase() {
    try {
        $backup_dir = __DIR__ . '/../backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Get database credentials
        $host = 'localhost';
        $dbname = 'capstone';
        $username = 'root';
        $password = '';
        
        // Create mysqldump command
        $command = "mysqldump -h $host -u $username" . (empty($password) ? '' : " -p$password") . " $dbname > $backup_file";
        
        // Execute backup
        $output = [];
        $return_code = 0;
        exec($command, $output, $return_code);
        
        if ($return_code === 0 && file_exists($backup_file)) {
            $file_size = filesize($backup_file);
            return [
                'action' => 'backup_database',
                'status' => 'success',
                'message' => "Database backup created: " . basename($backup_file) . " (" . round($file_size / 1024 / 1024, 2) . " MB)"
            ];
        } else {
            return [
                'action' => 'backup_database',
                'status' => 'error',
                'message' => 'Database backup failed: ' . implode(' ', $output)
            ];
        }
    } catch (Exception $e) {
        return [
            'action' => 'backup_database',
            'status' => 'error',
            'message' => 'Database backup failed: ' . $e->getMessage()
        ];
    }
}

// Get system information
$system_info = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'disk_free_space' => round(disk_free_space(__DIR__) / 1024 / 1024 / 1024, 2) . ' GB',
    'disk_total_space' => round(disk_total_space(__DIR__) / 1024 / 1024 / 1024, 2) . ' GB',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time') . ' seconds'
];

// Get log file information
$log_info = [];
$log_dir = __DIR__ . '/../logs';
if (is_dir($log_dir)) {
    $log_files = glob($log_dir . '/*.log');
    foreach ($log_files as $log_file) {
        $log_info[] = [
            'name' => basename($log_file),
            'size' => round(filesize($log_file) / 1024 / 1024, 2) . ' MB',
            'modified' => date('Y-m-d H:i:s', filemtime($log_file))
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance - Equipment Kiosk</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .maintenance-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .maintenance-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .maintenance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .maintenance-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        
        .maintenance-card h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .maintenance-actions {
            display: grid;
            gap: 15px;
        }
        
        .maintenance-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.3s;
        }
        
        .maintenance-btn:hover {
            background: #0056b3;
        }
        
        .maintenance-btn.danger {
            background: #dc3545;
        }
        
        .maintenance-btn.danger:hover {
            background: #c82333;
        }
        
        .maintenance-btn.success {
            background: #28a745;
        }
        
        .maintenance-btn.success:hover {
            background: #218838;
        }
        
        .maintenance-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .results {
            margin-top: 20px;
        }
        
        .result-item {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid;
        }
        
        .result-success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        
        .result-warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        
        .result-error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .log-files {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .log-file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .log-file-item:last-child {
            border-bottom: none;
        }
        
        .log-file-name {
            font-weight: 500;
            color: #495057;
        }
        
        .log-file-size {
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-header">
            <h1><i class="fas fa-tools"></i> System Maintenance</h1>
            <p>Perform maintenance tasks to keep the system running smoothly</p>
        </div>

        <div class="maintenance-grid">
            <!-- Maintenance Actions -->
            <div class="maintenance-card">
                <h3><i class="fas fa-cogs"></i> Maintenance Actions</h3>
                <form method="POST" class="maintenance-actions">
                    <button type="submit" name="action" value="clean_logs" class="maintenance-btn">
                        <i class="fas fa-trash"></i> Clean Log Files
                    </button>
                    <button type="submit" name="action" value="optimize_database" class="maintenance-btn">
                        <i class="fas fa-database"></i> Optimize Database
                    </button>
                    <button type="submit" name="action" value="clean_temp_files" class="maintenance-btn">
                        <i class="fas fa-broom"></i> Clean Temp Files
                    </button>
                    <button type="submit" name="action" value="backup_database" class="maintenance-btn success">
                        <i class="fas fa-save"></i> Backup Database
                    </button>
                    <button type="submit" name="action" value="run_all" class="maintenance-btn danger">
                        <i class="fas fa-play"></i> Run All Maintenance
                    </button>
                </form>

                <?php if (!empty($maintenance_results)): ?>
                <div class="results">
                    <h4>Maintenance Results:</h4>
                    <?php foreach ($maintenance_results as $result): ?>
                        <div class="result-item result-<?= $result['status'] ?>">
                            <strong><?= ucfirst($result['action']) ?>:</strong> <?= htmlspecialchars($result['message']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- System Information -->
            <div class="maintenance-card">
                <h3><i class="fas fa-info-circle"></i> System Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">PHP Version</div>
                        <div class="info-value"><?= $system_info['php_version'] ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Server Software</div>
                        <div class="info-value"><?= htmlspecialchars($system_info['server_software']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Free Disk Space</div>
                        <div class="info-value"><?= $system_info['disk_free_space'] ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Disk Space</div>
                        <div class="info-value"><?= $system_info['disk_total_space'] ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Memory Limit</div>
                        <div class="info-value"><?= $system_info['memory_limit'] ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Max Execution Time</div>
                        <div class="info-value"><?= $system_info['max_execution_time'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Files Information -->
        <div class="maintenance-card">
            <h3><i class="fas fa-file-alt"></i> Log Files</h3>
            <div class="log-files">
                <?php if (empty($log_info)): ?>
                    <div class="log-file-item">
                        <span class="log-file-name">No log files found</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($log_info as $log): ?>
                        <div class="log-file-item">
                            <span class="log-file-name"><?= htmlspecialchars($log['name']) ?></span>
                            <span class="log-file-size"><?= $log['size'] ?> (<?= $log['modified'] ?>)</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Add confirmation for dangerous actions
        document.querySelectorAll('button[value="run_all"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to run all maintenance tasks? This may take several minutes.')) {
                    e.preventDefault();
                }
            });
        });

        document.querySelectorAll('button[value="backup_database"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Create a database backup? This may take a few minutes.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>