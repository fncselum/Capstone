<?php
session_start();

// Include stability framework
require_once 'includes/error_handler.php';
require_once 'includes/database_manager.php';
require_once 'includes/validation.php';

// Initialize error handler
SystemErrorHandler::init();

// Check if admin is logged in
$session_error = SystemValidator::validateAdminSession();
if ($session_error) {
    SystemErrorHandler::logSecurityEvent("Unauthorized access attempt to add_equipment.php", [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    header('Location: login.php');
    exit;
}

// Include helper function
require_once 'update_availability_status.php';

// Initialize response variables
$error_message = null;
$success_message = null;

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize input data
        $form_data = SystemValidator::sanitizeInput($_POST);
        
        // Validate CSRF token
        $csrf_error = SystemValidator::validateCSRFToken($form_data['csrf_token'] ?? '');
        if ($csrf_error) {
            throw new Exception("Security validation failed. Please refresh the page and try again.");
        }
        
        // Validate equipment data
        $validation_errors = SystemValidator::validateEquipmentData($form_data);
        if (!empty($validation_errors)) {
            throw new Exception(implode(' ', $validation_errors));
        }
        
        // Validate RFID uniqueness
        $rfid_error = SystemValidator::validateRfidUniqueness($form_data['rfid_tag']);
        if ($rfid_error) {
            throw new Exception($rfid_error);
        }
        
        // Validate category exists
        $category_error = SystemValidator::validateCategoryExists($form_data['category_id']);
        if ($category_error) {
            throw new Exception($category_error);
        }
        
        // Handle image file upload
        $image_path = null;
        
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            
            // Validate file upload
            $file_errors = SystemValidator::validateFileUpload($file);
            if (!empty($file_errors)) {
                throw new Exception(implode(' ', $file_errors));
            }
            
            // Create uploads directory if it doesn't exist
            $upload_dir = dirname(__DIR__) . '/uploads/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception("Failed to create uploads directory.");
                }
            }
            
            // Generate safe filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($form_data['name']));
            $filename = $safe_name . '_' . time() . '.' . $extension;
            $target_path = $upload_dir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                throw new Exception("Failed to upload image file.");
            }
            
            // Store relative path
            $image_path = 'uploads/' . $filename;
        }
        
        // If no file uploaded, use image URL if provided
        if (empty($image_path) && !empty($form_data['image_path'])) {
            $image_path = $form_data['image_path'];
        }
        
        // Prepare SQL statement with prepared statements for security
        $sql = "INSERT INTO equipment 
                (name, rfid_tag, category_id, quantity, size_category, description, image_path, created_at, updated_at) 
                VALUES 
                (:name, :rfid_tag, :category_id, :quantity, :size_category, :description, :image_path, NOW(), NOW())";
        
        // Execute with bound parameters using DatabaseManager
        $result = DatabaseManager::execute($sql, [
            ':name' => $form_data['name'],
            ':rfid_tag' => $form_data['rfid_tag'],
            ':category_id' => (int)$form_data['category_id'],
            ':quantity' => (int)$form_data['quantity'],
            ':size_category' => $form_data['size_category'],
            ':description' => $form_data['description'],
            ':image_path' => $image_path
        ]);
        
        if ($result) {
            $new_equipment_id = DatabaseManager::getConnection()->lastInsertId();
            
            // Automatically insert into inventory table
            try {
                // Check if inventory record already exists (shouldn't happen, but just in case)
                $existing_inventory = DatabaseManager::fetchOne(
                    "SELECT id FROM inventory WHERE equipment_id = :equipment_id LIMIT 1",
                    [':equipment_id' => $form_data['rfid_tag']]
                );
                
                if (!$existing_inventory) {
                    // Determine availability status based on quantity
                    $availability_status = ((int)$quantity > 0) ? 'Available' : 'Out of Stock';
                    
                    // Get all columns in inventory table to build dynamic query
                    $existing_columns = DatabaseManager::getTableColumns('inventory');
                    $column_names = array_column($existing_columns, 'column_name');
                    
                    // Build SQL based on available columns
                    $fields = ['equipment_id', 'quantity', 'available_quantity'];
                    $values = [':equipment_id', ':quantity', ':available_quantity'];
                    $params = [
                        ':equipment_id' => $rfid_tag,
                        ':quantity' => (int)$quantity,
                        ':available_quantity' => (int)$quantity
                    ];
                    
                    // Add optional columns if they exist
                    if (in_array('borrowed_quantity', $column_names)) {
                        $fields[] = 'borrowed_quantity';
                        $values[] = ':borrowed_quantity';
                        $params[':borrowed_quantity'] = 0;
                    }
                    
                    if (in_array('damaged_quantity', $column_names)) {
                        $fields[] = 'damaged_quantity';
                        $values[] = ':damaged_quantity';
                        $params[':damaged_quantity'] = 0;
                    }
                    
                    if (in_array('item_condition', $column_names)) {
                        $fields[] = 'item_condition';
                        $values[] = ':item_condition';
                        $params[':item_condition'] = 'Good';
                    }

                    if (in_array('item_size', $column_names)) {
                        $fields[] = 'item_size';
                        $values[] = ':item_size';
                        $params[':item_size'] = $form_data['size_category'];
                    }
                    
                    if (in_array('availability_status', $column_names)) {
                        $fields[] = 'availability_status';
                        $values[] = ':availability_status';
                        $params[':availability_status'] = $availability_status;
                    }
                    
                    if (in_array('minimum_stock_level', $column_names)) {
                        $fields[] = 'minimum_stock_level';
                        $values[] = ':minimum_stock_level';
                        $params[':minimum_stock_level'] = 1;
                    }
                    
                    if (in_array('location', $column_names)) {
                        $fields[] = 'location';
                        $values[] = ':location';
                        $params[':location'] = null;
                    }
                    
                    if (in_array('notes', $column_names)) {
                        $fields[] = 'notes';
                        $values[] = ':notes';
                        $params[':notes'] = null;
                    }
                    
                    if (in_array('last_updated', $column_names)) {
                        $fields[] = 'last_updated';
                        $values[] = 'NOW()';
                    }
                    
                    if (in_array('created_at', $column_names)) {
                        $fields[] = 'created_at';
                        $values[] = 'NOW()';
                    }
                    
                    // Build and execute the INSERT query
                    $inventory_sql = "INSERT INTO inventory (" . implode(', ', $fields) . ") 
                                     VALUES (" . implode(', ', $values) . ")";
                    
                    DatabaseManager::execute($inventory_sql, $params);
                    
                    // Automatically update availability_status
                    updateAvailabilityStatus(DatabaseManager::getConnection(), $form_data['rfid_tag']);
                    
                    SystemErrorHandler::logApplicationError("Inventory record created successfully for equipment RFID: " . $form_data['rfid_tag']);
                } else {
                    SystemErrorHandler::logApplicationError("Inventory record already exists for equipment RFID: " . $form_data['rfid_tag']);
                }
            } catch (Exception $e) {
                // Log error but don't fail the entire operation
                SystemErrorHandler::logApplicationError("Inventory insert failed: " . $e->getMessage());
                // Optionally, you could throw an exception here if inventory sync is critical
                // throw new Exception("Equipment added but failed to create inventory record: " . $e->getMessage());
            }
            
            // Set success message in session
            $_SESSION['success_message'] = "Equipment added successfully!";
            
            // Redirect to equipment inventory page
            header('Location: admin-equipment-inventory.php');
            exit;
        } else {
            throw new Exception("Failed to add equipment to database.");
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        SystemErrorHandler::logApplicationError("Equipment addition failed: " . $e->getMessage(), [
            'form_data' => $form_data,
            'file' => __FILE__,
            'line' => __LINE__
        ]);
    }
    
    // If we got here, there was an error - store it in session and redirect back
    if ($error_message) {
        $_SESSION['error_message'] = $error_message;
        $_SESSION['form_data'] = $_POST; // Preserve form data
        header('Location: admin-equipment-inventory.php');
        exit;
    }
}

// If accessed directly without POST, redirect to equipment inventory
header('Location: admin-equipment-inventory.php');
exit;
