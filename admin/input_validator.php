<?php
/**
 * Input Validation Class
 * Provides comprehensive input validation and sanitization
 */

class InputValidator {
    
    /**
     * Validate equipment data
     */
    public static function validateEquipmentData($data) {
        $errors = [];
        $sanitized = [];
        
        // Validate name
        if (empty($data['name'])) {
            $errors[] = "Equipment name is required";
        } else {
            $sanitized['name'] = self::sanitizeString($data['name'], 100);
            if (strlen($sanitized['name']) < 2) {
                $errors[] = "Equipment name must be at least 2 characters long";
            }
        }
        
        // Validate RFID tag
        if (empty($data['rfid_tag'])) {
            $errors[] = "RFID tag is required";
        } else {
            $sanitized['rfid_tag'] = self::sanitizeString($data['rfid_tag'], 50);
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $sanitized['rfid_tag'])) {
                $errors[] = "RFID tag can only contain letters, numbers, hyphens, and underscores";
            }
        }
        
        // Validate category
        if (!empty($data['category_id'])) {
            $sanitized['category_id'] = self::sanitizeInteger($data['category_id']);
            if ($sanitized['category_id'] <= 0) {
                $errors[] = "Invalid category selected";
            }
        } else {
            $sanitized['category_id'] = null;
        }
        
        // Validate quantity
        if (!isset($data['quantity']) || $data['quantity'] === '') {
            $errors[] = "Quantity is required";
        } else {
            $sanitized['quantity'] = self::sanitizeInteger($data['quantity']);
            if ($sanitized['quantity'] < 0) {
                $errors[] = "Quantity must be 0 or greater";
            }
            if ($sanitized['quantity'] > 9999) {
                $errors[] = "Quantity cannot exceed 9999";
            }
        }
        
        // Validate size category
        if (empty($data['size_category'])) {
            $errors[] = "Item size is required";
        } else {
            $allowedSizes = ['Small', 'Medium', 'Large'];
            $sanitized['size_category'] = self::sanitizeString($data['size_category'], 20);
            if (!in_array($sanitized['size_category'], $allowedSizes, true)) {
                $errors[] = "Invalid item size selected";
            }
        }
        
        // Validate description
        if (!empty($data['description'])) {
            $sanitized['description'] = self::sanitizeString($data['description'], 1000);
        } else {
            $sanitized['description'] = '';
        }
        
        // Validate image URL
        if (!empty($data['image_path'])) {
            $sanitized['image_path'] = self::sanitizeUrl($data['image_path']);
            if ($sanitized['image_path'] === false) {
                $errors[] = "Invalid image URL format";
            }
        } else {
            $sanitized['image_path'] = '';
        }
        
        return [
            'errors' => $errors,
            'sanitized' => $sanitized,
            'is_valid' => empty($errors)
        ];
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'], $maxSize = 5242880) {
        $errors = [];
        
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['errors' => [], 'is_valid' => true]; // No file uploaded is valid
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error: " . self::getUploadErrorMessage($file['error']);
            return ['errors' => $errors, 'is_valid' => false];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $errors[] = "File size exceeds " . round($maxSize / 1024 / 1024, 2) . "MB limit";
        }
        
        // Check file type
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Invalid file type. Allowed types: " . implode(', ', $allowedTypes);
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = "Invalid file extension. Allowed extensions: " . implode(', ', $allowedExtensions);
        }
        
        // Check for suspicious content
        if (self::isSuspiciousFile($file['tmp_name'])) {
            $errors[] = "File appears to contain suspicious content";
        }
        
        return [
            'errors' => $errors,
            'is_valid' => empty($errors),
            'file_type' => $fileType,
            'file_size' => $file['size']
        ];
    }
    
    /**
     * Validate search input
     */
    public static function validateSearchInput($input, $maxLength = 100) {
        $sanitized = self::sanitizeString($input, $maxLength);
        
        // Remove potentially dangerous characters
        $sanitized = preg_replace('/[<>"\']/', '', $sanitized);
        
        return $sanitized;
    }
    
    /**
     * Validate filter parameters
     */
    public static function validateFilterParams($params) {
        $sanitized = [];
        
        // Validate stock filter
        if (isset($params['stock'])) {
            $allowedStockFilters = ['all', 'available', 'out_of_stock'];
            if (in_array($params['stock'], $allowedStockFilters, true)) {
                $sanitized['stock'] = $params['stock'];
            } else {
                $sanitized['stock'] = 'all';
            }
        } else {
            $sanitized['stock'] = 'all';
        }
        
        // Validate category filter
        if (isset($params['category'])) {
            $sanitized['category'] = self::sanitizeString($params['category'], 50);
        } else {
            $sanitized['category'] = 'all';
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize string input
     */
    public static function sanitizeString($input, $maxLength = 255) {
        $sanitized = trim($input);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize integer input
     */
    public static function sanitizeInteger($input) {
        return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }
    
    /**
     * Sanitize URL input
     */
    public static function sanitizeUrl($input) {
        $url = filter_var($input, FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL);
    }
    
    /**
     * Check if file contains suspicious content
     */
    private static function isSuspiciousFile($filePath) {
        $content = file_get_contents($filePath, false, null, 0, 1024); // Read first 1KB
        
        // Check for PHP tags
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return true;
        }
        
        // Check for script tags
        if (strpos($content, '<script') !== false) {
            return true;
        }
        
        // Check for executable signatures
        $executableSignatures = [
            "\x4D\x5A", // PE executable
            "\x7F\x45\x4C\x46", // ELF executable
            "\xFE\xED\xFA", // Mach-O executable
        ];
        
        foreach ($executableSignatures as $signature) {
            if (strpos($content, $signature) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get upload error message
     */
    private static function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return "File exceeds upload_max_filesize directive";
            case UPLOAD_ERR_FORM_SIZE:
                return "File exceeds MAX_FILE_SIZE directive";
            case UPLOAD_ERR_PARTIAL:
                return "File was only partially uploaded";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Missing temporary folder";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file to disk";
            case UPLOAD_ERR_EXTENSION:
                return "File upload stopped by extension";
            default:
                return "Unknown upload error";
        }
    }
    
    /**
     * Validate session data
     */
    public static function validateSession() {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return false;
        }
        
        if (!isset($_SESSION['admin_id']) || !is_numeric($_SESSION['admin_id'])) {
            return false;
        }
        
        // Check session timeout (30 minutes)
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > 1800)) {
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
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
?>