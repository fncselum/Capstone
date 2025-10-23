<?php
/**
 * System Validation Functions
 * Provides comprehensive input validation and sanitization
 */

class SystemValidator {
    
    /**
     * Validate equipment data
     */
    public static function validateEquipmentData($data) {
        $errors = [];
        
        // Name validation
        if (empty($data['name'])) {
            $errors[] = "Equipment name is required.";
        } elseif (strlen($data['name']) > 255) {
            $errors[] = "Equipment name must be less than 255 characters.";
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-_\.]+$/', $data['name'])) {
            $errors[] = "Equipment name contains invalid characters. Only letters, numbers, spaces, hyphens, underscores, and dots are allowed.";
        }
        
        // RFID Tag validation
        if (empty($data['rfid_tag'])) {
            $errors[] = "RFID tag is required.";
        } elseif (strlen($data['rfid_tag']) > 50) {
            $errors[] = "RFID tag must be less than 50 characters.";
        } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $data['rfid_tag'])) {
            $errors[] = "RFID tag contains invalid characters. Only alphanumeric characters, hyphens, and underscores are allowed.";
        }
        
        // Category validation
        if (!empty($data['category_id']) && (!is_numeric($data['category_id']) || $data['category_id'] < 1)) {
            $errors[] = "Invalid category selected.";
        }
        
        // Quantity validation
        if (empty($data['quantity']) && $data['quantity'] !== '0') {
            $errors[] = "Quantity is required.";
        } elseif (!is_numeric($data['quantity']) || $data['quantity'] < 0 || $data['quantity'] > 999999) {
            $errors[] = "Quantity must be a number between 0 and 999,999.";
        }
        
        // Size category validation
        $allowed_sizes = ['Small', 'Medium', 'Large'];
        if (empty($data['size_category'])) {
            $errors[] = "Item size is required.";
        } elseif (!in_array($data['size_category'], $allowed_sizes, true)) {
            $errors[] = "Invalid item size selected.";
        }
        
        // Description validation
        if (!empty($data['description']) && strlen($data['description']) > 1000) {
            $errors[] = "Description must be less than 1000 characters.";
        }
        
        // Image URL validation
        if (!empty($data['image_path'])) {
            if (!filter_var($data['image_path'], FILTER_VALIDATE_URL) && !preg_match('/^uploads\/[a-zA-Z0-9_\-\.]+$/', $data['image_path'])) {
                $errors[] = "Invalid image URL format.";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = "File size exceeds maximum allowed size.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = "File upload was incomplete.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = "No file was uploaded.";
                    break;
                default:
                    $errors[] = "File upload error occurred.";
            }
            return $errors;
        }
        
        // Check file size (max 5MB)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            $errors[] = "File size exceeds 5MB limit.";
        }
        
        // Check file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Invalid file type. Only JPG, JPEG, and PNG images are allowed.";
        }
        
        // Check file extension
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed_extensions)) {
            $errors[] = "Invalid file extension. Only .jpg, .jpeg, and .png files are allowed.";
        }
        
        // Check for malicious content
        if (self::isMaliciousFile($file['tmp_name'])) {
            $errors[] = "File appears to be malicious and was rejected.";
        }
        
        return $errors;
    }
    
    /**
     * Check if file is potentially malicious
     */
    private static function isMaliciousFile($file_path) {
        // Check for executable content
        $content = file_get_contents($file_path, false, null, 0, 1024);
        
        // Check for PHP tags
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return true;
        }
        
        // Check for script tags
        if (strpos($content, '<script') !== false) {
            return true;
        }
        
        // Check for executable file signatures
        $executable_signatures = [
            "\x4D\x5A", // PE executable
            "\x7F\x45\x4C\x46", // ELF executable
            "\xFE\xED\xFA", // Mach-O executable
        ];
        
        foreach ($executable_signatures as $signature) {
            if (strpos($content, $signature) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);
            
            // Trim whitespace
            $data = trim($data);
            
            // Remove control characters except newlines and tabs
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
        }
        
        return $data;
    }
    
    /**
     * Validate RFID tag uniqueness
     */
    public static function validateRfidUniqueness($rfid_tag, $exclude_id = null) {
        global $pdo;
        
        try {
            $sql = "SELECT id, name FROM equipment WHERE rfid_tag = :rfid_tag";
            $params = [':rfid_tag' => $rfid_tag];
            
            if ($exclude_id) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = $exclude_id;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                return "RFID tag '$rfid_tag' is already used by '" . htmlspecialchars($existing['name']) . "' (ID " . $existing['id'] . ").";
            }
            
            return null;
        } catch (PDOException $e) {
            error_log("RFID uniqueness check failed: " . $e->getMessage());
            return "Database error occurred while checking RFID uniqueness.";
        }
    }
    
    /**
     * Validate category exists
     */
    public static function validateCategoryExists($category_id) {
        global $pdo;
        
        if (empty($category_id)) {
            return null; // Optional field
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $category_id]);
            
            if (!$stmt->fetch()) {
                return "Selected category does not exist.";
            }
            
            return null;
        } catch (PDOException $e) {
            error_log("Category validation failed: " . $e->getMessage());
            return "Database error occurred while validating category.";
        }
    }
    
    /**
     * Validate equipment ID exists
     */
    public static function validateEquipmentExists($equipment_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT id FROM equipment WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $equipment_id]);
            
            if (!$stmt->fetch()) {
                return "Equipment not found.";
            }
            
            return null;
        } catch (PDOException $e) {
            error_log("Equipment validation failed: " . $e->getMessage());
            return "Database error occurred while validating equipment.";
        }
    }
    
    /**
     * Validate session and authentication
     */
    public static function validateAdminSession() {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return "Session expired or unauthorized access.";
        }
        
        // Check session timeout (optional - implement if needed)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
            session_destroy();
            return "Session expired due to inactivity.";
        }
        
        $_SESSION['last_activity'] = time();
        return null;
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return "CSRF token not found in session.";
        }
        
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            return "Invalid CSRF token.";
        }
        
        return null;
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}