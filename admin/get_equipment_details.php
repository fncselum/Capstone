<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Database configuration
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get equipment ID from request
    $equipment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($equipment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid equipment ID']);
        exit;
    }
    
    // Fetch equipment details with category and inventory info
    $sql = "SELECT e.id, e.name, e.rfid_tag, e.category_id, e.quantity, e.description, e.image_path,
            c.name as category_name,
            i.quantity as inventory_quantity,
            i.available_quantity,
            i.item_condition
            FROM equipment e
            LEFT JOIN categories c ON e.category_id = c.id
            LEFT JOIN inventory i ON e.id = i.equipment_id
            WHERE e.id = :id
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $equipment_id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($equipment) {
        // Use equipment quantity if inventory quantity is not available
        if ($equipment['quantity'] === null && $equipment['inventory_quantity'] !== null) {
            $equipment['quantity'] = $equipment['inventory_quantity'];
        }
        
        echo json_encode([
            'success' => true,
            'equipment' => $equipment
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Equipment not found'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("Database error in get_equipment_details.php: " . $e->getMessage());
}
