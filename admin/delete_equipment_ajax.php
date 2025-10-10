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

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create PDO connection
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get equipment ID from request
        $equipment_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($equipment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid equipment ID']);
            exit;
        }
        
        // First, get the equipment details to delete associated image
        $stmt = $pdo->prepare("SELECT image_path FROM equipment WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $equipment_id]);
        $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$equipment) {
            echo json_encode(['success' => false, 'message' => 'Equipment not found']);
            exit;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Delete from inventory table first (if exists)
            $delete_inventory = $pdo->prepare("DELETE FROM inventory WHERE equipment_id = :id");
            $delete_inventory->execute([':id' => $equipment_id]);
            
            // Delete from transactions table (if exists)
            try {
                $delete_transactions = $pdo->prepare("DELETE FROM transactions WHERE equipment_id = :id");
                $delete_transactions->execute([':id' => $equipment_id]);
            } catch (PDOException $e) {
                error_log("Transactions delete failed: " . $e->getMessage());
            }
            
            // Delete the equipment record
            $delete_equipment = $pdo->prepare("DELETE FROM equipment WHERE id = :id");
            $delete_equipment->execute([':id' => $equipment_id]);
            
            // Commit transaction
            $pdo->commit();
            
            // Delete associated image file if it exists
            if (!empty($equipment['image_path']) && strpos($equipment['image_path'], 'uploads/') === 0) {
                $image_file = dirname(__DIR__) . '/' . $equipment['image_path'];
                if (file_exists($image_file)) {
                    @unlink($image_file);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Equipment deleted successfully!'
            ]);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        error_log("Database error in delete_equipment_ajax.php: " . $e->getMessage());
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
