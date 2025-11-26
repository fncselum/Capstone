<?php
/**
 * Enhanced Session Handler for Face Recognition Security
 * Manages RFID + Face verification state with bypass prevention
 */

session_start();

class SecuritySessionHandler {
    
    private static $required_session_keys = ['user_id', 'rfid_tag', 'student_id'];
    private static $security_timeout = 1800; // 30 minutes
    
    /**
     * Initialize a new RFID session
     */
    public static function initRFIDSession($user_data) {
        // Clear any existing face verification
        self::clearFaceVerification();
        
        $_SESSION['user_id'] = $user_data['id'];
        $_SESSION['rfid_tag'] = $user_data['rfid_tag'];
        $_SESSION['student_id'] = $user_data['student_id'];
        $_SESSION['rfid_verified'] = true;
        $_SESSION['rfid_timestamp'] = time();
        $_SESSION['face_verified'] = false;
        $_SESSION['session_token'] = self::generateSecureToken();
        
        // Store user info for face verification
        $_SESSION['user_penalty_points'] = $user_data['penalty_points'] ?? 0;
        $_SESSION['is_admin'] = $user_data['is_admin'] ?? false;
        $_SESSION['admin_level'] = $user_data['admin_level'] ?? 'user';
        
        return $_SESSION['session_token'];
    }
    
    /**
     * Verify face recognition and set session flag
     */
    public static function setFaceVerified() {
        if (!self::isRFIDVerified()) {
            throw new Exception('RFID verification required before face verification');
        }
        
        $_SESSION['face_verified'] = true;
        $_SESSION['face_timestamp'] = time();
        $_SESSION['full_auth_timestamp'] = time();
        
        return true;
    }
    
    /**
     * Check if RFID is verified and not expired
     */
    public static function isRFIDVerified() {
        if (!isset($_SESSION['rfid_verified']) || !$_SESSION['rfid_verified']) {
            return false;
        }
        
        if (!isset($_SESSION['rfid_timestamp'])) {
            return false;
        }
        
        // Check if RFID session has expired
        if (time() - $_SESSION['rfid_timestamp'] > self::$security_timeout) {
            self::clearSession();
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if face is verified and not expired
     */
    public static function isFaceVerified() {
        if (!self::isRFIDVerified()) {
            return false;
        }
        
        if (!isset($_SESSION['face_verified']) || !$_SESSION['face_verified']) {
            return false;
        }
        
        if (!isset($_SESSION['face_timestamp'])) {
            return false;
        }
        
        // Check if face session has expired (shorter timeout for security)
        if (time() - $_SESSION['face_timestamp'] > (self::$security_timeout / 2)) {
            self::clearFaceVerification();
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if user has full authentication (RFID + Face)
     */
    public static function isFullyAuthenticated() {
        return self::isRFIDVerified() && self::isFaceVerified();
    }
    
    /**
     * Clear face verification only (keep RFID session)
     */
    public static function clearFaceVerification() {
        $_SESSION['face_verified'] = false;
        unset($_SESSION['face_timestamp']);
        unset($_SESSION['full_auth_timestamp']);
    }
    
    /**
     * Clear entire session
     */
    public static function clearSession() {
        $keys_to_clear = [
            'user_id', 'rfid_tag', 'student_id', 'rfid_verified', 'rfid_timestamp',
            'face_verified', 'face_timestamp', 'full_auth_timestamp', 'session_token',
            'user_penalty_points', 'is_admin', 'admin_level'
        ];
        
        foreach ($keys_to_clear as $key) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Validate session integrity
     */
    public static function validateSession() {
        // Check required session keys
        foreach (self::$required_session_keys as $key) {
            if (!isset($_SESSION[$key])) {
                return false;
            }
        }
        
        // Check session token
        if (!isset($_SESSION['session_token']) || empty($_SESSION['session_token'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get user info from session
     */
    public static function getUserInfo() {
        if (!self::validateSession()) {
            return null;
        }
        
        return [
            'user_id' => $_SESSION['user_id'],
            'rfid_tag' => $_SESSION['rfid_tag'],
            'student_id' => $_SESSION['student_id'],
            'penalty_points' => $_SESSION['user_penalty_points'] ?? 0,
            'is_admin' => $_SESSION['is_admin'] ?? false,
            'admin_level' => $_SESSION['admin_level'] ?? 'user',
            'rfid_verified' => self::isRFIDVerified(),
            'face_verified' => self::isFaceVerified(),
            'fully_authenticated' => self::isFullyAuthenticated()
        ];
    }
    
    /**
     * Require full authentication or redirect
     */
    public static function requireFullAuth($redirect_url = 'index.php?face=required') {
        if (!self::isFullyAuthenticated()) {
            header("Location: $redirect_url");
            exit;
        }
    }
    
    /**
     * Require RFID authentication or redirect
     */
    public static function requireRFIDAuth($redirect_url = 'index.php') {
        if (!self::isRFIDVerified()) {
            header("Location: $redirect_url");
            exit;
        }
    }
    
    /**
     * Generate secure session token
     */
    private static function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $details = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'user_id' => $_SESSION['user_id'] ?? 'unknown',
            'student_id' => $_SESSION['student_id'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        // Log to file (you can also log to database)
        $log_file = __DIR__ . '/../logs/security.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        
        @file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Check for suspicious activity
     */
    public static function checkSuspiciousActivity() {
        // Check for rapid authentication attempts
        if (isset($_SESSION['auth_attempts'])) {
            if ($_SESSION['auth_attempts'] > 5) {
                self::logSecurityEvent('suspicious_activity', ['reason' => 'too_many_attempts']);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Increment authentication attempts
     */
    public static function incrementAuthAttempts() {
        if (!isset($_SESSION['auth_attempts'])) {
            $_SESSION['auth_attempts'] = 0;
        }
        $_SESSION['auth_attempts']++;
    }
    
    /**
     * Reset authentication attempts on successful auth
     */
    public static function resetAuthAttempts() {
        unset($_SESSION['auth_attempts']);
    }
}

// Auto-cleanup expired sessions
if (isset($_SESSION['rfid_timestamp']) && 
    time() - $_SESSION['rfid_timestamp'] > SecuritySessionHandler::$security_timeout) {
    SecuritySessionHandler::clearSession();
}
?>
