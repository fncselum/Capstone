<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Include helper function
require_once 'update_availability_status.php';
require_once 'includes/email_config.php';

// Database configuration
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

// Initialize response variables
$error_message = null;
$success_message = null;

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create PDO connection
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Validate required fields
        $name = trim($_POST['name'] ?? '');
        $rfid_tag = trim($_POST['rfid_tag'] ?? '');
        $category_id = trim($_POST['category_id'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $borrow_period_days = isset($_POST['borrow_period_days']) && $_POST['borrow_period_days'] !== '' ? (int)$_POST['borrow_period_days'] : null;
        $image_url = trim($_POST['image_path'] ?? ''); // From form field "image_path" for URL
        $size_category = trim($_POST['size_category'] ?? '');
        $importance_level = trim($_POST['importance_level'] ?? '');
        $allowed_sizes = ['Small', 'Medium', 'Large'];
        
        // Validation
        if (empty($name)) {
            throw new Exception("Equipment Name is required.");
        }
        
        if (empty($rfid_tag)) {
            throw new Exception("RFID Tag is required.");
        }
        
        if (empty($category_id) || !is_numeric($category_id)) {
            throw new Exception("Please select a valid Category.");
        }
        
        if (empty($quantity) || !is_numeric($quantity) || $quantity < 0) {
            throw new Exception("Please enter a valid Quantity (must be 0 or greater).");
        }

        if (empty($size_category) || !in_array($size_category, $allowed_sizes, true)) {
            throw new Exception("Please select a valid item size.");
        }
        
        // Check if RFID tag already exists
        $stmt = $pdo->prepare("SELECT id, name FROM equipment WHERE rfid_tag = :rfid_tag LIMIT 1");
        $stmt->execute([':rfid_tag' => $rfid_tag]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            throw new Exception("This RFID tag ($rfid_tag) is already used by '" . htmlspecialchars($existing['name']) . "' (ID " . $existing['id'] . ").");
        }
        
        // Handle image file upload
        $image_path = null;
        
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $file_type = mime_content_type($file['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG, JPEG, and PNG images are allowed.");
            }
            
            // Validate file size (max 5MB)
            $max_size = 5 * 1024 * 1024; // 5MB in bytes
            if ($file['size'] > $max_size) {
                throw new Exception("File size exceeds 5MB limit.");
            }
            
            // Create uploads directory if it doesn't exist
            $upload_dir = dirname(__DIR__) . '/uploads/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create uploads directory.");
                }
            }
            
            // Generate safe filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($name));
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
        if (empty($image_path) && !empty($image_url)) {
            // Validate URL format
            if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                throw new Exception("Invalid image URL format.");
            }
            $image_path = $image_url;
        }
        
        // Prepare SQL statement with prepared statements for security
        $sql = "INSERT INTO equipment 
                (name, rfid_tag, category_id, quantity, size_category, description, image_path, created_at, updated_at) 
                VALUES 
                (:name, :rfid_tag, :category_id, :quantity, :size_category, :description, :image_path, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        
        // Execute with bound parameters
        $result = $stmt->execute([
            ':name' => $name,
            ':rfid_tag' => $rfid_tag,
            ':category_id' => (int)$category_id,
            ':quantity' => (int)$quantity,
            ':size_category' => $size_category,
            ':description' => $description,
            ':image_path' => $image_path
        ]);
        
        if ($result) {
            $new_equipment_id = $pdo->lastInsertId();
            
            // Automatically insert into inventory table
            try {
                // Check if inventory record already exists (shouldn't happen, but just in case)
                $stmt = $pdo->prepare("SELECT id FROM inventory WHERE equipment_id = :equipment_id LIMIT 1");
                $stmt->execute([':equipment_id' => $rfid_tag]);
                
                if ($stmt->rowCount() === 0) {
                    // Determine availability status based on quantity
                    $availability_status = ((int)$quantity > 0) ? 'Available' : 'Out of Stock';
                    
                    // Get all columns in inventory table to build dynamic query
                    $columns_query = $pdo->query("SHOW COLUMNS FROM inventory");
                    $existing_columns = $columns_query->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Build SQL based on available columns
                    $fields = ['equipment_id', 'quantity', 'available_quantity'];
                    $values = [':equipment_id', ':quantity', ':available_quantity'];
                    $params = [
                        ':equipment_id' => $rfid_tag,
                        ':quantity' => (int)$quantity,
                        ':available_quantity' => (int)$quantity
                    ];
                    
                    // Add optional columns if they exist
                    if (in_array('borrowed_quantity', $existing_columns)) {
                        $fields[] = 'borrowed_quantity';
                        $values[] = ':borrowed_quantity';
                        $params[':borrowed_quantity'] = 0;
                    }
                    
                    if (in_array('damaged_quantity', $existing_columns)) {
                        $fields[] = 'damaged_quantity';
                        $values[] = ':damaged_quantity';
                        $params[':damaged_quantity'] = 0;
                    }
                    
                    if (in_array('item_condition', $existing_columns)) {
                        $fields[] = 'item_condition';
                        $values[] = ':item_condition';
                        $params[':item_condition'] = 'Good';
                    }

                    if (in_array('item_size', $existing_columns)) {
                        $fields[] = 'item_size';
                        $values[] = ':item_size';
                        $params[':item_size'] = $size_category;
                    }

                    if (in_array('borrow_period_days', $existing_columns)) {
                        // If importance is provided, auto-map days from tier; else use provided value
                        $mapped_days = null;
                        if ($importance_level !== '') {
                            $lvl = strtolower($importance_level);
                            if ($lvl === 'reserved' || $lvl === 'high-demand') { $mapped_days = 1; }
                            elseif ($lvl === 'frequently borrowed') { $mapped_days = 2; }
                            elseif ($lvl === 'standard') { $mapped_days = 3; }
                            elseif ($lvl === 'low-usage') { $mapped_days = 5; }
                        }
                        $fields[] = 'borrow_period_days';
                        $values[] = ':borrow_period_days';
                        $params[':borrow_period_days'] = ($mapped_days !== null) ? $mapped_days : $borrow_period_days;
                    }
                    
                    if (in_array('importance_level', $existing_columns)) {
                        $fields[] = 'importance_level';
                        $values[] = ':importance_level';
                        $params[':importance_level'] = ($importance_level !== '') ? $importance_level : null;
                    }

                    if (in_array('availability_status', $existing_columns)) {
                        $fields[] = 'availability_status';
                        $values[] = ':availability_status';
                        $params[':availability_status'] = $availability_status;
                    }
                    
                    if (in_array('minimum_stock_level', $existing_columns)) {
                        $fields[] = 'minimum_stock_level';
                        $values[] = ':minimum_stock_level';
                        $params[':minimum_stock_level'] = 1;
                    }
                    
                    if (in_array('location', $existing_columns)) {
                        $fields[] = 'location';
                        $values[] = ':location';
                        $params[':location'] = null;
                    }
                    
                    if (in_array('notes', $existing_columns)) {
                        $fields[] = 'notes';
                        $values[] = ':notes';
                        $params[':notes'] = null;
                    }
                    
                    if (in_array('last_updated', $existing_columns)) {
                        $fields[] = 'last_updated';
                        $values[] = 'NOW()';
                    }
                    
                    if (in_array('created_at', $existing_columns)) {
                        $fields[] = 'created_at';
                        $values[] = 'NOW()';
                    }
                    
                    // Build and execute the INSERT query
                    $inventory_sql = "INSERT INTO inventory (" . implode(', ', $fields) . ") 
                                     VALUES (" . implode(', ', $values) . ")";
                    
                    $inventory_stmt = $pdo->prepare($inventory_sql);
                    $inventory_stmt->execute($params);
                    
                    // Automatically update availability_status
                    updateAvailabilityStatus($pdo, $rfid_tag);
                    
                    error_log("Inventory record created successfully for equipment RFID: $rfid_tag");
                } else {
                    error_log("Inventory record already exists for equipment RFID: $rfid_tag");
                }
            } catch (PDOException $e) {
                // Log error but don't fail the entire operation
                error_log("Inventory insert failed: " . $e->getMessage());
                // Optionally, you could throw an exception here if inventory sync is critical
                // throw new Exception("Equipment added but failed to create inventory record: " . $e->getMessage());
            }
            
            // Check for low stock and send email alert
            $low_stock_threshold = 5;
            if ((int)$quantity <= $low_stock_threshold && (int)$quantity > 0) {
                // Convert PDO to mysqli for email function
                $mysqli = new mysqli($host, $user, $password, $dbname);
                if (!$mysqli->connect_error) {
                    sendLowStockAlert($mysqli, $name, (int)$quantity, $low_stock_threshold);
                    $mysqli->close();
                }
            }
            
            // Set success message in session
            $_SESSION['success_message'] = "Equipment added successfully!";
            
            // Redirect to equipment inventory page
            header('Location: admin-equipment-inventory.php');
            exit;
        } else {
            throw new Exception("Failed to add equipment to database.");
        }
        
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
        error_log("Database error in add_equipment.php: " . $e->getMessage());
    } catch (Exception $e) {
        $error_message = $e->getMessage();
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
