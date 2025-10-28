<?php
/**
 * Security Helper Functions
 * Centralized security utilities for the Equipment Kiosk system
 */

// Prevent direct access
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data, $type = 'string') {
    switch ($type) {
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL);
        case 'url':
            return filter_var($data, FILTER_VALIDATE_URL);
        case 'string':
        default:
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate required fields
 */
function validateRequiredFields($data, $required_fields) {
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    return $errors;
}

/**
 * Rate limiting
 */
function checkRateLimit($identifier, $max_attempts = 100, $time_window = 60) {
    $cache_file = sys_get_temp_dir() . '/rate_limit_' . md5($identifier) . '.json';
    
    $now = time();
    $attempts = [];
    
    if (file_exists($cache_file)) {
        $attempts = json_decode(file_get_contents($cache_file), true) ?: [];
    }
    
    // Remove old attempts outside time window
    $attempts = array_filter($attempts, function($timestamp) use ($now, $time_window) {
        return ($now - $timestamp) < $time_window;
    });
    
    if (count($attempts) >= $max_attempts) {
        return false;
    }
    
    // Add current attempt
    $attempts[] = $now;
    file_put_contents($cache_file, json_encode($attempts));
    
    return true;
}

/**
 * Secure session start
 */
function secureSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    secureSessionStart();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if admin is authenticated
 */
function isAdminAuthenticated() {
    secureSessionStart();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
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
 * Validate file upload
 */
function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'], $max_size = 5242880) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error: ' . $file['error'];
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds ' . ($max_size / 1024 / 1024) . 'MB limit';
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowed_types);
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png'];
    
    if (!in_array($extension, $allowed_extensions)) {
        $errors[] = 'Invalid file extension. Allowed: ' . implode(', ', $allowed_extensions);
    }
    
    return $errors;
}

/**
 * Generate secure filename
 */
function generateSecureFilename($original_name, $prefix = '') {
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix . basename($original_name, '.' . $extension));
    return $safe_name . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
}

/**
 * Send JSON response
 */
function sendJSONResponse($data, $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send error response
 */
function sendErrorResponse($message, $http_code = 400) {
    sendJSONResponse(['success' => false, 'message' => $message], $http_code);
}

/**
 * Send success response
 */
function sendSuccessResponse($data = [], $message = 'Success') {
    sendJSONResponse(['success' => true, 'message' => $message, 'data' => $data]);
}