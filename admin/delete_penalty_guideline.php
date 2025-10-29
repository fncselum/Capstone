<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id > 0) {
        // Get document file before deleting
        $sql = "SELECT document_file FROM penalty_guidelines WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $guideline = $result->fetch_assoc();
        
        // Delete the record
        $sql = "DELETE FROM penalty_guidelines WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Delete associated file if exists
            if ($guideline && !empty($guideline['document_file'])) {
                $relativePath = ltrim($guideline['document_file'], '/\\');
                $file_path = dirname(__DIR__) . '/' . $relativePath;
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Guideline deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete guideline']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
