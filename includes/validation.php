<?php
/**
 * Input Validation Helper Functions
 * Centralized validation utilities for the Equipment Kiosk system
 */

// Prevent direct access
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Validate equipment data
 */
function validateEquipmentData($data) {
    $errors = [];
    
    // Required fields
    $required_fields = ['name', 'rfid_tag', 'category_id', 'quantity', 'size_category'];
    $missing_fields = validateRequiredFields($data, $required_fields);
    $errors = array_merge($errors, $missing_fields);
    
    // Validate name
    if (isset($data['name']) && !empty($data['name'])) {
        if (strlen($data['name']) < 2 || strlen($data['name']) > 100) {
            $errors[] = 'Equipment name must be between 2 and 100 characters';
        }
    }
    
    // Validate RFID tag
    if (isset($data['rfid_tag']) && !empty($data['rfid_tag'])) {
        if (strlen($data['rfid_tag']) < 3 || strlen($data['rfid_tag']) > 50) {
            $errors[] = 'RFID tag must be between 3 and 50 characters';
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['rfid_tag'])) {
            $errors[] = 'RFID tag can only contain letters, numbers, hyphens, and underscores';
        }
    }
    
    // Validate category ID
    if (isset($data['category_id']) && !empty($data['category_id'])) {
        if (!is_numeric($data['category_id']) || $data['category_id'] < 1) {
            $errors[] = 'Please select a valid category';
        }
    }
    
    // Validate quantity
    if (isset($data['quantity']) && !empty($data['quantity'])) {
        if (!is_numeric($data['quantity']) || $data['quantity'] < 0) {
            $errors[] = 'Quantity must be a non-negative number';
        }
        if ($data['quantity'] > 9999) {
            $errors[] = 'Quantity cannot exceed 9999';
        }
    }
    
    // Validate size category
    if (isset($data['size_category']) && !empty($data['size_category'])) {
        $allowed_sizes = ['Small', 'Medium', 'Large'];
        if (!in_array($data['size_category'], $allowed_sizes)) {
            $errors[] = 'Size category must be Small, Medium, or Large';
        }
    }
    
    // Validate description (optional)
    if (isset($data['description']) && !empty($data['description'])) {
        if (strlen($data['description']) > 500) {
            $errors[] = 'Description cannot exceed 500 characters';
        }
    }
    
    return $errors;
}

/**
 * Validate transaction data
 */
function validateTransactionData($data) {
    $errors = [];
    
    // Required fields
    $required_fields = ['equipment_id', 'due_date'];
    $missing_fields = validateRequiredFields($data, $required_fields);
    $errors = array_merge($errors, $missing_fields);
    
    // Validate equipment ID
    if (isset($data['equipment_id']) && !empty($data['equipment_id'])) {
        if (!is_numeric($data['equipment_id']) || $data['equipment_id'] < 1) {
            $errors[] = 'Invalid equipment selection';
        }
    }
    
    // Validate due date
    if (isset($data['due_date']) && !empty($data['due_date'])) {
        $due_date = DateTime::createFromFormat('Y-m-d\TH:i', $data['due_date']);
        if (!$due_date) {
            $errors[] = 'Invalid due date format';
        } else {
            $now = new DateTime();
            if ($due_date <= $now) {
                $errors[] = 'Due date must be in the future';
            }
            // Check if due date is not more than 30 days in the future
            $max_date = (new DateTime())->add(new DateInterval('P30D'));
            if ($due_date > $max_date) {
                $errors[] = 'Due date cannot be more than 30 days in the future';
            }
        }
    }
    
    // Validate quantity
    if (isset($data['quantity'])) {
        if (!is_numeric($data['quantity']) || $data['quantity'] < 1) {
            $errors[] = 'Quantity must be at least 1';
        }
        if ($data['quantity'] > 10) {
            $errors[] = 'Quantity cannot exceed 10 items per transaction';
        }
    }
    
    return $errors;
}

/**
 * Validate user data
 */
function validateUserData($data) {
    $errors = [];
    
    // Required fields
    $required_fields = ['student_id', 'rfid_tag'];
    $missing_fields = validateRequiredFields($data, $required_fields);
    $errors = array_merge($errors, $missing_fields);
    
    // Validate student ID
    if (isset($data['student_id']) && !empty($data['student_id'])) {
        if (strlen($data['student_id']) < 3 || strlen($data['student_id']) > 20) {
            $errors[] = 'Student ID must be between 3 and 20 characters';
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['student_id'])) {
            $errors[] = 'Student ID can only contain letters, numbers, hyphens, and underscores';
        }
    }
    
    // Validate RFID tag
    if (isset($data['rfid_tag']) && !empty($data['rfid_tag'])) {
        if (strlen($data['rfid_tag']) < 3 || strlen($data['rfid_tag']) > 50) {
            $errors[] = 'RFID tag must be between 3 and 50 characters';
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['rfid_tag'])) {
            $errors[] = 'RFID tag can only contain letters, numbers, hyphens, and underscores';
        }
    }
    
    return $errors;
}

/**
 * Validate admin data
 */
function validateAdminData($data) {
    $errors = [];
    
    // Required fields
    $required_fields = ['username', 'password'];
    $missing_fields = validateRequiredFields($data, $required_fields);
    $errors = array_merge($errors, $missing_fields);
    
    // Validate username
    if (isset($data['username']) && !empty($data['username'])) {
        if (strlen($data['username']) < 3 || strlen($data['username']) > 30) {
            $errors[] = 'Username must be between 3 and 30 characters';
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['username'])) {
            $errors[] = 'Username can only contain letters, numbers, hyphens, and underscores';
        }
    }
    
    // Validate password
    if (isset($data['password']) && !empty($data['password'])) {
        if (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $data['password'])) {
            $errors[] = 'Password must contain at least one lowercase letter, one uppercase letter, and one number';
        }
    }
    
    return $errors;
}

/**
 * Validate penalty data
 */
function validatePenaltyData($data) {
    $errors = [];
    
    // Required fields
    $required_fields = ['penalty_type', 'amount', 'description'];
    $missing_fields = validateRequiredFields($data, $required_fields);
    $errors = array_merge($errors, $missing_fields);
    
    // Validate penalty type
    if (isset($data['penalty_type']) && !empty($data['penalty_type'])) {
        $allowed_types = ['Late Return', 'Damage', 'Loss', 'Other'];
        if (!in_array($data['penalty_type'], $allowed_types)) {
            $errors[] = 'Invalid penalty type';
        }
    }
    
    // Validate amount
    if (isset($data['amount']) && !empty($data['amount'])) {
        if (!is_numeric($data['amount']) || $data['amount'] < 0) {
            $errors[] = 'Penalty amount must be a non-negative number';
        }
        if ($data['amount'] > 10000) {
            $errors[] = 'Penalty amount cannot exceed 10,000';
        }
    }
    
    // Validate description
    if (isset($data['description']) && !empty($data['description'])) {
        if (strlen($data['description']) < 5 || strlen($data['description']) > 200) {
            $errors[] = 'Description must be between 5 and 200 characters';
        }
    }
    
    return $errors;
}

/**
 * Sanitize and validate search query
 */
function validateSearchQuery($query) {
    $query = trim($query);
    
    if (empty($query)) {
        return ['valid' => false, 'error' => 'Search query cannot be empty'];
    }
    
    if (strlen($query) < 2) {
        return ['valid' => false, 'error' => 'Search query must be at least 2 characters'];
    }
    
    if (strlen($query) > 100) {
        return ['valid' => false, 'error' => 'Search query cannot exceed 100 characters'];
    }
    
    // Remove potentially dangerous characters
    $query = preg_replace('/[<>"\']/', '', $query);
    
    return ['valid' => true, 'query' => $query];
}

/**
 * Validate pagination parameters
 */
function validatePaginationParams($page, $limit) {
    $errors = [];
    
    // Validate page
    if (!is_numeric($page) || $page < 1) {
        $errors[] = 'Page must be a positive number';
    }
    
    // Validate limit
    if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
        $errors[] = 'Limit must be between 1 and 100';
    }
    
    return $errors;
}

/**
 * Validate date range
 */
function validateDateRange($start_date, $end_date) {
    $errors = [];
    
    if (empty($start_date) || empty($end_date)) {
        $errors[] = 'Both start and end dates are required';
        return $errors;
    }
    
    $start = DateTime::createFromFormat('Y-m-d', $start_date);
    $end = DateTime::createFromFormat('Y-m-d', $end_date);
    
    if (!$start) {
        $errors[] = 'Invalid start date format';
    }
    
    if (!$end) {
        $errors[] = 'Invalid end date format';
    }
    
    if ($start && $end && $start > $end) {
        $errors[] = 'Start date cannot be after end date';
    }
    
    // Check if date range is not more than 1 year
    if ($start && $end) {
        $diff = $start->diff($end);
        if ($diff->days > 365) {
            $errors[] = 'Date range cannot exceed 1 year';
        }
    }
    
    return $errors;
}