<?php
header('Content-Type: application/json');

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
$maintenance_mode = false;

if (!$conn->connect_error) {
    $table_check = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $maintenance_mode = ($row['setting_value'] == '1');
            }
            $stmt->close();
        }
    }
    $conn->close();
}

echo json_encode(['maintenance_mode' => $maintenance_mode]);
?>
