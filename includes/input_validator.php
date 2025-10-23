<?php
/**
 * Input Validation and Sanitization for Equipment Management System
 * Provides comprehensive input validation, sanitization, and CSRF protection
 */

class InputValidator {
    
    /**
     * Validate and sanitize string input
     */
    public static function validateString($input, $maxLength = 255, $required = true) {
        if ($required && (empty($input) || trim($input) === '')) {
            return [
                'valid' => false,
                'error' => 'This field is required',
                'value' => ''
            ];
        }
        
        $sanitized = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        
        if (strlen($sanitized) > $maxLength) {
            return [
                'valid' => false,
                'error' => "Maximum length is $maxLength characters",
                'value' => $sanitized
            ];
        }
        
        return [
            'valid' => true,
            'error' => null,
            'value' => $sanitized
        ];
    }
    
    /**
     * Validate and sanitize integer input
     */
    public static function validateInteger($input, $min = null, $max = null, $required = true) {
        if ($required && (empty($input) || trim($input) === '')) {
            return [
                'valid' => false,
                'error' => 'This field is required',
                'value' => 0
            ];
        }
        
        $value = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($value === false) {
            return [
                'valid' => false,
                'error' => 'Must be a valid integer',
                'value' => 0
            ];
        }
        
        if ($min !== null && $value < $min) {
            return [
                'valid' => false,
                'error' => "Value must be at least $min",
                'value' => $value
            ];
        }
        
        if ($max !== null && $value > $max) {
            return [
                'valid' => false,
                'error' => "Value must be no more than $max",
                'value' => $value
            ];
        }
        
        return [
            'valid' => true,
            'error' => null,
            'value' => $value
        ];
    }
    
    /**
     * Validate and sanitize email input
     */
    public static function validateEmail($input, $required = true) {
        if ($required && (empty($input) || trim($input) === '')) {
            return [
                'valid' => false,
                'error' => 'Email is required',
                'value' => ''
            ];
        }
        
        $email = filter_var(trim($input), FILTER_VALIDATE_EMAIL);
        
        if ($email === false) {
            return [
                'valid' => false,
                'error' => 'Invalid email format',
                'value' => htmlspecialchars($input, ENT_QUOTES, 'UTF-8')
            ];
        }
        
        return [
            'valid' => true,
            'error' => null,
            'value' => $email
        ];
    }
    
    /**
     * Validate and sanitize RFID tag
     */
    public static function validateRFID($input, $required = true) {
        if ($required && (empty($input) || trim($input) === '')) {
            return [
                'valid' => false,
                'error' => 'RFID tag is required',
                'value' => ''
            ];
        }
        
        $rfid = trim($input);
        
        // RFID should be alphanumeric and reasonable length
        if (!preg_match('/^[A-Za-z0-9]{4,20}$/', $rfid)) {
            return [
                'valid' => false,
                'error' => 'RFID tag must be 4-20 alphanumeric characters',
                'value' => htmlspecialchars($rfid, ENT_QUOTES, 'UTF-8')
            ];
        }
        
        return [
            'valid' => true,
            'error' => null,
            'value' => strtoupper($rfid)
        ];
    }
    
    /**
     * Validate and sanitize date input
     */
    public static function validateDate($input, $format = 'Y-m-d', $required = true) {
        if ($required && (empty($input) || trim($input) === '')) {
            return [
                'valid' => false,
                'error' => 'Date is required',
                'value' => ''
            ];
        }
        
        $date = trim($input);
        $d = DateTime::createFromFormat($format, $date);
        
        if (!$d || $d->format($format) !== $date) {
            return [
                'valid' => false,
                'error' => 'Invalid date format. Expected: ' . $format,
                'value' => htmlspecialchars($date, ENT_QUOTES, 'UTF-8')
            ];
        }
        
        return [
            'valid' => true,
            'error' => null,
            'value' => $date
        ];
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880) {
        $errors = [];
        
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed';
            return [
                'valid' => false,
                'errors' => $errors,
                'value' => null
            ];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size (' . ($maxSize / 1024 / 1024) . 'MB)';
        }
        
        // Check file type
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        ];
        
        if (!isset($allowedMimeTypes[$fileExtension]) || $mimeType !== $allowedMimeTypes[$fileExtension]) {
            $errors[] = 'Invalid file type detected';
        }
        
        if (empty($errors)) {
            return [
                'valid' => true,
                'errors' => [],
                'value' => $file
            ];
        }
        
        return [
            'valid' => false,
            'errors' => $errors,
            'value' => $file
        ];
    }
    
    /**
     * Validate POST data with rules
     */
    public static function validatePostData($rules) {
        $validated = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $input = $_POST[$field] ?? '';
            $type = $rule['type'] ?? 'string';
            $required = $rule['required'] ?? true;
            
            switch ($type) {
                case 'string':
                    $result = self::validateString($input, $rule['max_length'] ?? 255, $required);
                    break;
                case 'integer':
                    $result = self::validateInteger($input, $rule['min'] ?? null, $rule['max'] ?? null, $required);
                    break;
                case 'email':
                    $result = self::validateEmail($input, $required);
                    break;
                case 'rfid':
                    $result = self::validateRFID($input, $required);
                    break;
                case 'date':
                    $result = self::validateDate($input, $rule['format'] ?? 'Y-m-d', $required);
                    break;
                default:
                    $result = self::validateString($input, 255, $required);
            }
            
            if ($result['valid']) {
                $validated[$field] = $result['value'];
            } else {
                $errors[$field] = $result['error'];
            }
        }
        
        return [
            'valid' => empty($errors),
            'data' => $validated,
            'errors' => $errors
        ];
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
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
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize output for display
     */
    public static function sanitizeOutput($input) {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate equipment data
     */
    public static function validateEquipmentData($data) {
        $rules = [
            'name' => ['type' => 'string', 'max_length' => 255, 'required' => true],
            'category_id' => ['type' => 'integer', 'min' => 1, 'required' => true],
            'quantity' => ['type' => 'integer', 'min' => 1, 'max' => 9999, 'required' => true],
            'item_condition' => ['type' => 'string', 'max_length' => 50, 'required' => true],
            'rfid_tag' => ['type' => 'rfid', 'required' => true]
        ];
        
        return self::validatePostData($rules);
    }
    
    /**
     * Validate transaction data
     */
    public static function validateTransactionData($data) {
        $rules = [
            'equipment_id' => ['type' => 'integer', 'min' => 1, 'required' => true],
            'quantity' => ['type' => 'integer', 'min' => 1, 'max' => 100, 'required' => true],
            'due_date' => ['type' => 'date', 'format' => 'Y-m-d', 'required' => true],
            'rfid_tag' => ['type' => 'rfid', 'required' => true]
        ];
        
        return self::validatePostData($rules);
    }
    
    /**
     * Validate user login data
     */
    public static function validateLoginData($data) {
        $rules = [
            'username' => ['type' => 'string', 'max_length' => 50, 'required' => true],
            'password' => ['type' => 'string', 'max_length' => 255, 'required' => true]
        ];
        
        return self::validatePostData($rules);
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => $now];
        }
        
        $rateData = $_SESSION[$key];
        
        // Reset if time window has passed
        if ($now - $rateData['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => $now];
            $rateData = $_SESSION[$key];
        }
        
        if ($rateData['attempts'] >= $maxAttempts) {
            return [
                'allowed' => false,
                'remaining_time' => $timeWindow - ($now - $rateData['first_attempt'])
            ];
        }
        
        $_SESSION[$key]['attempts']++;
        
        return [
            'allowed' => true,
            'remaining_attempts' => $maxAttempts - $_SESSION[$key]['attempts']
        ];
    }
}
?>