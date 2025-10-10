<?php
session_start();
$_SESSION['admin_logged_in'] = true; // Simulate logged in admin

// Test the get_equipment_details.php endpoint
$equipment_id = isset($_GET['id']) ? $_GET['id'] : 1;

echo "<h2>Testing get_equipment_details.php with ID: $equipment_id</h2>";

// Make a request to the endpoint
$url = "http://localhost/Capstone/admin/get_equipment_details.php?id=$equipment_id";
$response = file_get_contents($url);

echo "<h3>Response:</h3>";
echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";

echo "<h3>Raw Response:</h3>";
echo "<pre>";
echo htmlspecialchars($response);
echo "</pre>";

// Also test database connection directly
echo "<h3>Direct Database Test:</h3>";
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT e.id, e.name, e.rfid_tag, e.category_id, e.quantity, e.description, e.image_path,
            c.name as category_name
            FROM equipment e
            LEFT JOIN categories c ON e.category_id = c.id
            WHERE e.id = :id
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $equipment_id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($equipment);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
