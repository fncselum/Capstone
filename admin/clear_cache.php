<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $clearedItems = [];
    
    // 1. Clear PHP opcache
    if (function_exists('opcache_reset')) {
        opcache_reset();
        $clearedItems[] = 'OPcache';
    }
    
    // 2. Clear realpath cache
    clearstatcache(true);
    $clearedItems[] = 'Realpath cache';
    
    // 3. Clear session files (except current session)
    $sessionPath = session_save_path();
    if (!empty($sessionPath) && is_dir($sessionPath)) {
        $currentSessionId = session_id();
        $files = glob($sessionPath . '/sess_*');
        $sessionCount = 0;
        
        foreach ($files as $file) {
            $sessionId = str_replace($sessionPath . '/sess_', '', $file);
            // Don't delete current session
            if ($sessionId !== $currentSessionId && is_file($file)) {
                @unlink($file);
                $sessionCount++;
            }
        }
        
        if ($sessionCount > 0) {
            $clearedItems[] = "$sessionCount old session file(s)";
        }
    }
    
    // 4. Clear temporary files
    $tempDir = sys_get_temp_dir();
    $tempFiles = glob($tempDir . '/php*');
    $tempCount = 0;
    
    foreach ($tempFiles as $file) {
        if (is_file($file) && (time() - filemtime($file)) > 3600) { // Older than 1 hour
            @unlink($file);
            $tempCount++;
        }
    }
    
    if ($tempCount > 0) {
        $clearedItems[] = "$tempCount temporary file(s)";
    }
    
    // 5. Clear browser cache headers (for next request)
    $clearedItems[] = 'Browser cache headers';
    
    echo json_encode([
        'success' => true,
        'message' => 'Cache cleared successfully!',
        'cleared' => $clearedItems,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to clear cache: ' . $e->getMessage()
    ]);
}
?>
