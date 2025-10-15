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

// Fetch equipment with category names
$equipment_list = [];
$query = "SELECT e.*, c.name as category_name 
          FROM equipment e 
          LEFT JOIN categories c ON e.category_id = c.id 
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
