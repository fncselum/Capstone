<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Fetch equipment with category names and inventory info
$equipment_list = [];
$query = "SELECT e.*, 
                 c.name AS category_name,
                 i.quantity AS inventory_quantity,
                 i.available_quantity,
                 i.borrowed_quantity,
                 i.damaged_quantity,
                 i.availability_status,
                 i.item_size,
                 COALESCE(i.available_quantity,
                          GREATEST(e.quantity - COALESCE(i.borrowed_quantity, 0) - COALESCE(i.damaged_quantity, 0), 0)
                 ) AS computed_available
          FROM equipment e 
          LEFT JOIN categories c ON e.category_id = c.id 
          LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id
          WHERE e.quantity > 0
          ORDER BY e.id ASC";

if ($result = $conn->query($query)) {
    while ($row = $result->fetch_assoc()) {
        $equipment_list[] = $row;
    }
    $result->free();
}

// Fetch categories
$categories = [];
$cat_query = "SELECT * FROM categories ORDER BY name";
if ($cat_result = $conn->query($cat_query)) {
    while ($cat = $cat_result->fetch_assoc()) {
        $categories[] = $cat;
    }
    $cat_result->free();
}

$conn->close();

echo json_encode([
    'success' => true,
    'equipment' => $equipment_list,
    'categories' => $categories,
    'timestamp' => time()
]);
?>
