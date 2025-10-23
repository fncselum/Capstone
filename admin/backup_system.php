<?php
/**
 * System Backup and Recovery
 * Provides automated backup and recovery functionality
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
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'create_backup':
            createBackup();
            break;
            
        case 'list_backups':
            listBackups();
            break;
            
        case 'restore_backup':
            restoreBackup();
            break;
            
        case 'delete_backup':
            deleteBackup();
            break;
            
        case 'download_backup':
            downloadBackup();
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified'
            ]);
    }
    
} catch (Exception $e) {
    SystemErrorHandler::logApplicationError("Backup operation failed: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Backup operation failed: ' . $e->getMessage()
    ]);
}

/**
 * Create a new backup
 */
function createBackup() {
    try {
        // Create backup directory if it doesn't exist
        $backup_dir = dirname(__DIR__) . '/backups';
        if (!is_dir($backup_dir)) {
            if (!mkdir($backup_dir, 0755, true)) {
                throw new Exception("Failed to create backup directory");
            }
        }
        
        // Generate backup filename
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = $backup_dir . '/backup_' . $timestamp . '.sql';
        
        // Create database backup
        $backup_path = DatabaseManager::createBackup($backup_file);
        
        // Create backup metadata
        $metadata = [
            'created_at' => date('Y-m-d H:i:s'),
            'file_size' => filesize($backup_path),
            'database' => 'capstone',
            'version' => '1.0',
            'type' => 'full'
        ];
        
        $metadata_file = $backup_path . '.meta';
        file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));
        
        // Backup uploads directory
        $uploads_backup = $backup_dir . '/uploads_' . $timestamp . '.tar.gz';
        $uploads_dir = dirname(__DIR__) . '/uploads';
        
        if (is_dir($uploads_dir)) {
            $command = "tar -czf " . escapeshellarg($uploads_backup) . " -C " . escapeshellarg(dirname($uploads_dir)) . " " . basename($uploads_dir);
            exec($command, $output, $return_code);
            
            if ($return_code !== 0) {
                SystemErrorHandler::logApplicationError("Uploads backup failed with return code: " . $return_code);
            }
        }
        
        // Clean up old backups (keep only last 10)
        cleanupOldBackups($backup_dir);
        
        SystemErrorHandler::logApplicationError("Backup created successfully: " . $backup_path);
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'backup_file' => basename($backup_path),
            'file_size' => formatBytes(filesize($backup_path)),
            'created_at' => $metadata['created_at']
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Backup creation failed: " . $e->getMessage());
    }
}

/**
 * List available backups
 */
function listBackups() {
    $backup_dir = dirname(__DIR__) . '/backups';
    $backups = [];
    
    if (is_dir($backup_dir)) {
        $files = glob($backup_dir . '/backup_*.sql');
        
        foreach ($files as $file) {
            $metadata_file = $file . '.meta';
            $metadata = [];
            
            if (file_exists($metadata_file)) {
                $metadata = json_decode(file_get_contents($metadata_file), true) ?: [];
            }
            
            $backups[] = [
                'filename' => basename($file),
                'file_path' => $file,
                'file_size' => filesize($file),
                'file_size_formatted' => formatBytes(filesize($file)),
                'created_at' => $metadata['created_at'] ?? date('Y-m-d H:i:s', filemtime($file)),
                'database' => $metadata['database'] ?? 'capstone',
                'version' => $metadata['version'] ?? '1.0',
                'type' => $metadata['type'] ?? 'full'
            ];
        }
        
        // Sort by creation date (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }
    
    echo json_encode([
        'success' => true,
        'backups' => $backups
    ]);
}

/**
 * Restore from backup
 */
function restoreBackup() {
    $backup_file = $_POST['backup_file'] ?? '';
    
    if (empty($backup_file)) {
        throw new Exception("Backup file not specified");
    }
    
    $backup_path = dirname(__DIR__) . '/backups/' . $backup_file;
    
    if (!file_exists($backup_path)) {
        throw new Exception("Backup file not found");
    }
    
    try {
        // Create a backup before restoring
        createBackup();
        
        // Restore database
        $command = sprintf(
            'mysql -h%s -u%s -p%s %s < %s',
            escapeshellarg('localhost'),
            escapeshellarg('root'),
            escapeshellarg(''),
            escapeshellarg('capstone'),
            escapeshellarg($backup_path)
        );
        
        exec($command, $output, $return_code);
        
        if ($return_code !== 0) {
            throw new Exception("Database restore failed with return code: " . $return_code);
        }
        
        SystemErrorHandler::logApplicationError("Database restored successfully from: " . $backup_file);
        
        echo json_encode([
            'success' => true,
            'message' => 'Database restored successfully from ' . $backup_file
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Restore failed: " . $e->getMessage());
    }
}

/**
 * Delete backup
 */
function deleteBackup() {
    $backup_file = $_POST['backup_file'] ?? '';
    
    if (empty($backup_file)) {
        throw new Exception("Backup file not specified");
    }
    
    $backup_path = dirname(__DIR__) . '/backups/' . $backup_file;
    $metadata_path = $backup_path . '.meta';
    
    if (!file_exists($backup_path)) {
        throw new Exception("Backup file not found");
    }
    
    // Delete backup file
    if (!unlink($backup_path)) {
        throw new Exception("Failed to delete backup file");
    }
    
    // Delete metadata file if exists
    if (file_exists($metadata_path)) {
        unlink($metadata_path);
    }
    
    SystemErrorHandler::logApplicationError("Backup deleted: " . $backup_file);
    
    echo json_encode([
        'success' => true,
        'message' => 'Backup deleted successfully'
    ]);
}

/**
 * Download backup
 */
function downloadBackup() {
    $backup_file = $_GET['backup_file'] ?? '';
    
    if (empty($backup_file)) {
        throw new Exception("Backup file not specified");
    }
    
    $backup_path = dirname(__DIR__) . '/backups/' . $backup_file;
    
    if (!file_exists($backup_path)) {
        throw new Exception("Backup file not found");
    }
    
    // Set headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backup_file . '"');
    header('Content-Length: ' . filesize($backup_path));
    
    // Output file
    readfile($backup_path);
    exit;
}

/**
 * Clean up old backups
 */
function cleanupOldBackups($backup_dir, $keep_count = 10) {
    $files = glob($backup_dir . '/backup_*.sql');
    
    if (count($files) <= $keep_count) {
        return;
    }
    
    // Sort by modification time (oldest first)
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    // Remove oldest files
    $files_to_remove = array_slice($files, 0, count($files) - $keep_count);
    
    foreach ($files_to_remove as $file) {
        unlink($file);
        
        // Also remove metadata file
        $metadata_file = $file . '.meta';
        if (file_exists($metadata_file)) {
            unlink($metadata_file);
        }
    }
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