<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create PDO connection
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Validate required fields
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $rfid_tag = trim($_POST['rfid_tag'] ?? '');
        $category_id = trim($_POST['category_id'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url = trim($_POST['image_path'] ?? '');
        $borrow_period_days = isset($_POST['borrow_period_days']) && $_POST['borrow_period_days'] !== '' ? (int)$_POST['borrow_period_days'] : null;
        $importance_level = trim($_POST['importance_level'] ?? '');
        $size_category = trim($_POST['size_category'] ?? '');
        $allowed_sizes = ['Small', 'Medium', 'Large'];
        
        // Validation
        if ($id <= 0) {
            throw new Exception("Invalid equipment ID.");
        }
        
        if (empty($name)) {
            throw new Exception("Equipment Name is required.");
        }
        
        if (empty($rfid_tag)) {
            throw new Exception("RFID Tag is required.");
        }
        
        if (!is_numeric($quantity) || $quantity < 0) {
            throw new Exception("Please enter a valid Quantity (must be 0 or greater).");
        }

        if (empty($size_category) || !in_array($size_category, $allowed_sizes, true)) {
            throw new Exception("Please select a valid item size.");
        }
        
        // Get current image path
        $stmt = $pdo->prepare("SELECT image_path FROM equipment WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $image_path = $current['image_path'] ?? '';
        
        // Check if RFID tag already exists for another equipment
        $stmt = $pdo->prepare("SELECT id, name FROM equipment WHERE rfid_tag = :rfid_tag AND id != :id LIMIT 1");
        $stmt->execute([':rfid_tag' => $rfid_tag, ':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            throw new Exception("This RFID tag ($rfid_tag) is already used by '" . htmlspecialchars($existing['name']) . "' (ID " . $existing['id'] . ").");
        }
        
        // Handle image file upload
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $file_type = mime_content_type($file['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG, JPEG, and PNG images are allowed.");
            }
            
            // Validate file size (max 5MB)
            $max_size = 5 * 1024 * 1024;
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
            
            // Delete old image if exists
            if (!empty($image_path) && file_exists(dirname(__DIR__) . '/' . $image_path)) {
                @unlink(dirname(__DIR__) . '/' . $image_path);
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
        } elseif (!empty($image_url)) {
            // Use provided URL if no file uploaded
            $image_path = $image_url;
        }
        
        // Prepare SQL statement for update
        $sql = "UPDATE equipment 
                SET name = :name, 
                    rfid_tag = :rfid_tag, 
                    category_id = :category_id, 
                    quantity = :quantity, 
                    size_category = :size_category,
                    description = :description, 
                    image_path = :image_path, 
                    updated_at = NOW() 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        
        // Execute with bound parameters
        $result = $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':rfid_tag' => $rfid_tag,
            ':category_id' => !empty($category_id) ? (int)$category_id : null,
            ':quantity' => (int)$quantity,
            ':size_category' => $size_category,
            ':description' => $description,
            ':image_path' => $image_path
        ]);
        
        if ($result) {
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
            
            // Automatically sync with inventory table
            try {
                $inventory_key = $rfid_tag;
                // Check if inventory record exists
                $check_stmt = $pdo->prepare("SELECT id, borrowed_quantity, damaged_quantity FROM inventory WHERE equipment_id = :equipment_id LIMIT 1");
                $check_stmt->execute([':equipment_id' => $inventory_key]);
                $inventory = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($inventory) {
                    // Calculate available_quantity considering borrowed and damaged items
                    $borrowed = (int)($inventory['borrowed_quantity'] ?? 0);
                    $damaged = (int)($inventory['damaged_quantity'] ?? 0);
                    $available = max(0, (int)$quantity - $borrowed - $damaged);
                    
                    // Check which columns exist in inventory table
                    $columns_check = $pdo->query("SHOW COLUMNS FROM inventory");
                    $existing_columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Build dynamic update query
                    $update_fields = [
                        'quantity = :quantity',
                        'available_quantity = :available_quantity'
                    ];
                    
                    $params = [
                        ':equipment_id' => $inventory_key,
                        ':quantity' => (int)$quantity,
                        ':available_quantity' => $available
                    ];
                    
                    // Add last_updated if column exists
                    if (in_array('last_updated', $existing_columns)) {
                        $update_fields[] = 'last_updated = NOW()';
                    }
                    
                    // Add updated_at if column exists
                    if (in_array('updated_at', $existing_columns)) {
                        $update_fields[] = 'updated_at = NOW()';
                    }
                    
                    // If importance_level exists, include it
                    if (in_array('importance_level', $existing_columns)) {
                        $update_fields[] = 'importance_level = :importance_level';
                        $params[':importance_level'] = ($importance_level !== '') ? $importance_level : null;
                    }

                    // If borrow_period_days column exists, include it (auto-map from importance when provided)
                    if (in_array('borrow_period_days', $existing_columns)) {
                        $mapped_days = null;
                        if ($importance_level !== '') {
                            $lvl = strtolower($importance_level);
                            if ($lvl === 'reserved' || $lvl === 'high-demand') { $mapped_days = 1; }
                            elseif ($lvl === 'frequently borrowed') { $mapped_days = 2; }
                            elseif ($lvl === 'standard') { $mapped_days = 3; }
                            elseif ($lvl === 'low-usage') { $mapped_days = 5; }
                        }
                        $update_fields[] = 'borrow_period_days = :borrow_period_days';
                        $params[':borrow_period_days'] = ($mapped_days !== null) ? $mapped_days : $borrow_period_days;
                    }

                    // Execute inventory update
                    $inventory_sql = "UPDATE inventory SET " . implode(', ', $update_fields) . " WHERE equipment_id = :equipment_id";
                    $inventory_stmt = $pdo->prepare($inventory_sql);
                    $inventory_stmt->execute($params);
                    
                    // Automatically update availability_status based on available_quantity
                    updateAvailabilityStatus($pdo, $inventory_key);
                    
                    error_log("Inventory synced successfully for equipment key: $inventory_key (quantity: $quantity, available: $available)");
                } else {
                    // Create inventory record if it doesn't exist
                    $create_inventory = "INSERT INTO inventory (equipment_id, quantity, available_quantity, borrowed_quantity, damaged_quantity, availability_status, last_updated, created_at) 
                                        VALUES (:equipment_id, :quantity, :available_quantity, 0, 0, :status, NOW(), NOW())";
                    $status = ((int)$quantity > 0) ? 'Available' : 'Out of Stock';
                    
                    $create_stmt = $pdo->prepare($create_inventory);
                    $create_stmt->execute([
                        ':equipment_id' => $inventory_key,
                        ':quantity' => (int)$quantity,
                        ':available_quantity' => (int)$quantity,
                        ':status' => $status
                    ]);
                    
                    updateAvailabilityStatus($pdo, $inventory_key);
                    
                    error_log("Inventory record created for equipment key: $inventory_key");
                }
            } catch (PDOException $e) {
                error_log("Inventory sync failed: " . $e->getMessage());
                // Don't fail the entire operation, but log the error
                throw new Exception("Equipment updated but inventory sync failed: " . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Equipment and inventory updated successfully!'
            ]);
        } else {
            throw new Exception("Failed to update equipment in database.");
        }
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        error_log("Database error in update_equipment_ajax.php: " . $e->getMessage());
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
