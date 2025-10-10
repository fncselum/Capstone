<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    $_SESSION['error_message'] = "Connection failed: " . $conn->connect_error;
    header('Location: admin-penalty-guideline.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = trim($_POST['title'] ?? '');
        $penalty_type = trim($_POST['penalty_type'] ?? '');
        $penalty_amount = floatval($_POST['penalty_amount'] ?? 0);
        $penalty_points = intval($_POST['penalty_points'] ?? 0);
        $penalty_description = trim($_POST['penalty_description'] ?? '');
        $status = trim($_POST['status'] ?? 'draft');
        $created_by = $_SESSION['admin_id'] ?? 1; // Default to 1 if not set
        $updated_by = $_SESSION['admin_id'] ?? 1;
        
        // Validation
        if (empty($title) || empty($penalty_type) || empty($penalty_description)) {
            throw new Exception("All required fields must be filled.");
        }
        
        // Handle file upload
        $document_path = null;
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['document'];
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/jpg', 'image/png'];
            $file_type = mime_content_type($file['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only PDF, DOCX, and images are allowed.");
            }
            
            $max_size = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $max_size) {
                throw new Exception("File size exceeds 5MB limit.");
            }
            
            $upload_dir = dirname(__DIR__) . '/uploads/penalty_documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'penalty_' . time() . '_' . uniqid() . '.' . $extension;
            $target_path = $upload_dir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                throw new Exception("Failed to upload document.");
            }
            
            $document_path = 'uploads/penalty_documents/' . $filename;
        }
        
        if ($id > 0) {
            // Update existing guideline
            $sql = "UPDATE penalty_guidelines SET 
                    title = ?, 
                    penalty_type = ?, 
                    penalty_amount = ?, 
                    penalty_points = ?, 
                    penalty_description = ?, 
                    status = ?,
                    updated_by = ?";
            
            $params = [$title, $penalty_type, $penalty_amount, $penalty_points, $penalty_description, $status, $updated_by];
            $types = "sssdisi";
            
            if ($document_path) {
                $sql .= ", document_path = ?";
                $params[] = $document_path;
                $types .= "s";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Penalty guideline updated successfully!";
            } else {
                throw new Exception("Failed to update guideline.");
            }
        } else {
            // Insert new guideline
            $sql = "INSERT INTO penalty_guidelines 
                    (title, penalty_type, penalty_amount, penalty_points, penalty_description, document_path, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdisssi", $title, $penalty_type, $penalty_amount, $penalty_points, $penalty_description, $document_path, $status, $created_by);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Penalty guideline created successfully!";
            } else {
                throw new Exception("Failed to create guideline.");
            }
        }
        
        header('Location: admin-penalty-guideline.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: admin-penalty-guideline.php');
        exit;
    }
} else {
    header('Location: admin-penalty-guideline.php');
    exit;
}
?>
