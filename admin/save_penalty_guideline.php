<?php
session_start();
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Get form data
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$title = trim($_POST['title'] ?? '');
$penalty_type = trim($_POST['penalty_type'] ?? '');
$penalty_description = trim($_POST['penalty_description'] ?? '');
$penalty_amount = isset($_POST['penalty_amount']) ? (float)$_POST['penalty_amount'] : 0.00;
$penalty_points = isset($_POST['penalty_points']) ? (int)$_POST['penalty_points'] : 0;
$status = $_POST['status'] ?? 'draft';
$admin_id = $_SESSION['admin_id'] ?? null;

// Validate required fields
if (empty($title) || empty($penalty_type) || empty($penalty_description)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

// Handle file upload
$document_file = null;
$old_document_file = null;

// If updating, get the old document file
if ($id) {
    $stmt = $conn->prepare("SELECT document_file FROM penalty_guidelines WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $old_document_file = $row['document_file'];
    }
    $stmt->close();
}

// Process file upload if provided
if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['document'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    
    // Validate file size (5MB max)
    if ($file_size > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
        exit;
    }
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    
    if (!in_array($file_ext, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG']);
        exit;
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/penalty_documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Sanitize original filename to prevent security issues
    $original_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file_name, PATHINFO_FILENAME));
    $safe_filename = $original_filename . '.' . $file_ext;
    
    // Check if file already exists, add timestamp prefix if needed
    $final_filename = $safe_filename;
    $upload_path = $upload_dir . $final_filename;
    
    // If file exists, prepend timestamp to make it unique
    if (file_exists($upload_path)) {
        $final_filename = time() . '_' . $safe_filename;
        $upload_path = $upload_dir . $final_filename;
    }
    
    // Move uploaded file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        $document_file = 'uploads/penalty_documents/' . $final_filename;
        $document_file = str_replace('\\', '/', $document_file);
        
        // Delete old document if exists and new one uploaded successfully
        if ($old_document_file) {
            $oldRelative = ltrim($old_document_file, '/\\');
            $oldPath = dirname(__DIR__) . '/' . $oldRelative;
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload document.']);
        exit;
    }
}

// Prepare SQL query
if ($id) {
    // UPDATE existing guideline
    if ($document_file) {
        // Update with new document
        $stmt = $conn->prepare(
            "UPDATE penalty_guidelines 
             SET title = ?, 
                 penalty_type = ?, 
                 penalty_description = ?, 
                 penalty_amount = ?, 
                 penalty_points = ?, 
                 document_file = ?, 
                 status = ?, 
                 updated_at = NOW() 
             WHERE id = ?"
        );
        $stmt->bind_param(
            'sssdissi',
            $title,
            $penalty_type,
            $penalty_description,
            $penalty_amount,
            $penalty_points,
            $document_file,
            $status,
            $id
        );
    } else {
        // Update without changing document
        $stmt = $conn->prepare(
            "UPDATE penalty_guidelines 
             SET title = ?, 
                 penalty_type = ?, 
                 penalty_description = ?, 
                 penalty_amount = ?, 
                 penalty_points = ?, 
                 status = ?, 
                 updated_at = NOW() 
             WHERE id = ?"
        );
        $stmt->bind_param(
            'sssdisi',
            $title,
            $penalty_type,
            $penalty_description,
            $penalty_amount,
            $penalty_points,
            $status,
            $id
        );
    }
    
    if ($stmt->execute()) {
        $success = true;
        $message = 'Penalty guideline updated successfully!';
    } else {
        $success = false;
        $message = 'Failed to update penalty guideline: ' . $stmt->error;
    }
} else {
    // INSERT new guideline
    $stmt = $conn->prepare(
        "INSERT INTO penalty_guidelines 
         (title, penalty_type, penalty_description, penalty_amount, penalty_points, document_file, status, created_by, created_at, updated_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    $stmt->bind_param(
        'sssdissi',
        $title,
        $penalty_type,
        $penalty_description,
        $penalty_amount,
        $penalty_points,
        $document_file,
        $status,
        $admin_id
    );
    
    if ($stmt->execute()) {
        $success = true;
        $message = 'Penalty guideline created successfully!';
    } else {
        $success = false;
        $message = 'Failed to create penalty guideline: ' . $stmt->error;
    }
}

$stmt->close();
$conn->close();

// Return JSON response (header already set at top of file)
echo json_encode([
    'success' => $success,
    'message' => $message
]);
exit;
?>
