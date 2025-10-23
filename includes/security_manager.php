<?php
/**
 * Security Manager for Equipment Management System
 * Provides comprehensive security measures including session management, 
 * authentication, and protection against common vulnerabilities
 */

class SecurityManager {
    
    /**
     * Initialize secure session
     */
    public static function initSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure secure session parameters
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', 3600); // 1 hour
            
            session_start();
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * Secure login with rate limiting and validation
     */
    public static function secureLogin($username, $password, $userType = 'user') {
        // Check rate limiting
        $rateLimit = InputValidator::checkRateLimit($username . '_login', 5, 900); // 5 attempts in 15 minutes
        if (!$rateLimit['allowed']) {
            return [
                'success' => false,
                'message' => 'Too many login attempts. Please try again in ' . 
                           ceil($rateLimit['remaining_time'] / 60) . ' minutes.',
                'rate_limited' => true
            ];
        }
        
        // Validate input
        $loginData = InputValidator::validateLoginData(['username' => $username, 'password' => $password]);
        if (!$loginData['valid']) {
            return [
                'success' => false,
                'message' => 'Invalid input data',
                'errors' => $loginData['errors']
            ];
        }
        
        $validatedData = $loginData['data'];
        
        try {
            $db = DatabaseManager::getInstance();
            
            // Get user data
            $user = $db->fetchOne(
                "SELECT * FROM " . ($userType === 'admin' ? 'admin_users' : 'users') . 
                " WHERE username = ? AND status = 'Active'",
                [$validatedData['username']]
            );
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid username or password',
                    'rate_limited' => false
                ];
            }
            
            // Verify password
            if (!password_verify($validatedData['password'], $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid username or password',
                    'rate_limited' => false
                ];
            }
            
            // Set secure session
            self::initSecureSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $userType;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            if ($userType === 'admin') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
            } else {
                $_SESSION['user_logged_in'] = true;
                $_SESSION['student_id'] = $user['student_id'] ?? 'Guest';
                $_SESSION['rfid_tag'] = $user['rfid_tag'] ?? '';
            }
            
            // Log successful login
            self::logSecurityEvent('login_success', [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'user_type' => $userType,
                'ip_address' => self::getClientIP()
            ]);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => $user
            ];
            
        } catch (Exception $e) {
            self::logSecurityEvent('login_error', [
                'username' => $validatedData['username'],
                'error' => $e->getMessage(),
                'ip_address' => self::getClientIP()
            ]);
            
            return [
                'success' => false,
                'message' => 'Login failed. Please try again.',
                'rate_limited' => false
            ];
        }
    }
    
    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated($userType = 'user') {
        self::initSecureSession();
        
        if ($userType === 'admin') {
            return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
        } else {
            return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
        }
    }
    
    /**
     * Check session timeout
     */
    public static function checkSessionTimeout($timeoutMinutes = 60) {
        self::initSecureSession();
        
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }
        
        $timeout = $timeoutMinutes * 60;
        if (time() - $_SESSION['last_activity'] > $timeout) {
            self::logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Secure logout
     */
    public static function logout() {
        self::initSecureSession();
        
        // Log logout event
        if (isset($_SESSION['user_id'])) {
            self::logSecurityEvent('logout', [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'] ?? 'Unknown',
                'ip_address' => self::getClientIP()
            ]);
        }
        
        // Clear session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Require authentication for page access
     */
    public static function requireAuth($userType = 'user', $redirectTo = null) {
        if (!self::isAuthenticated($userType) || !self::checkSessionTimeout()) {
            if ($redirectTo === null) {
                $redirectTo = $userType === 'admin' ? 'admin/login.php' : 'user/index.php';
            }
            
            header("Location: $redirectTo");
            exit;
        }
    }
    
    /**
     * Validate CSRF token for forms
     */
    public static function validateCSRF($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? '';
        }
        
        return InputValidator::validateCSRFToken($token);
    }
    
    /**
     * Generate CSRF token for forms
     */
    public static function getCSRFToken() {
        return InputValidator::generateCSRFToken();
    }
    
    /**
     * Sanitize file uploads
     */
    public static function secureFileUpload($file, $uploadDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
        // Validate file
        $validation = InputValidator::validateFileUpload($file, $allowedTypes);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }
        
        // Generate secure filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('img_', true) . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . '/' . $filename;
        
        // Ensure upload directory exists and is secure
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Add .htaccess to prevent execution
        $htaccessFile = $uploadDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n");
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath
            ];
        } else {
            return [
                'success' => false,
                'errors' => ['Failed to save uploaded file']
            ];
        }
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $data = []) {
        $logData = [
            'event' => $event,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'data' => $data
        ];
        
        $logFile = '../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    /**
     * Check for suspicious activity
     */
    public static function checkSuspiciousActivity($userId = null) {
        $logFile = '../logs/security.log';
        if (!file_exists($logFile)) {
            return false;
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $recentTime = time() - 3600; // Last hour
        $suspiciousEvents = 0;
        
        foreach (array_reverse($lines) as $line) {
            $logData = json_decode($line, true);
            if (!$logData) continue;
            
            $logTime = strtotime($logData['timestamp']);
            if ($logTime < $recentTime) break;
            
            if ($userId && isset($logData['data']['user_id']) && $logData['data']['user_id'] != $userId) {
                continue;
            }
            
            if (in_array($logData['event'], ['login_error', 'rate_limit_exceeded', 'invalid_csrf'])) {
                $suspiciousEvents++;
            }
        }
        
        return $suspiciousEvents > 10; // More than 10 suspicious events in last hour
    }
    
    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random string
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate and sanitize SQL input
     */
    public static function sanitizeSQL($input) {
        // Remove potential SQL injection patterns
        $patterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(\b(OR|AND)\s+[\'"]\s*=\s*[\'"])/i',
            '/(\b(OR|AND)\s+[\'"]\s*LIKE\s*[\'"])/i'
        ];
        
        foreach ($patterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
?>