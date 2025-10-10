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
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

require_once 'penalty-system.php';
$penaltySystem = new PenaltySystem($conn);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_penalty':
        $penalty_id = intval($_POST['penalty_id'] ?? 0);
        $penalty = $penaltySystem->getPenaltyById($penalty_id);
        
        if ($penalty) {
            // Get guideline info if exists
            if ($penalty['guideline_id']) {
                $guideline = $penaltySystem->getGuidelineById($penalty['guideline_id']);
                $penalty['guideline_info'] = $guideline;
            }
            echo json_encode(['success' => true, 'penalty' => $penalty]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Penalty not found']);
        }
        break;
        
    case 'update_penalty':
        $penalty_id = intval($_POST['penalty_id'] ?? 0);
        $guideline_id = !empty($_POST['guideline_id']) ? intval($_POST['guideline_id']) : null;
        
        $penalty_data = [
            'penalty_type' => $_POST['penalty_type'] ?? '',
            'penalty_amount' => floatval($_POST['penalty_amount'] ?? 0),
            'penalty_points' => intval($_POST['penalty_points'] ?? 0),
            'days_overdue' => intval($_POST['days_overdue'] ?? 0),
            'description' => $_POST['description'] ?? '',
            'notes' => $_POST['notes'] ?? ''
        ];
        
        if ($penaltySystem->updatePenalty($penalty_id, $penalty_data, $guideline_id)) {
            echo json_encode(['success' => true, 'message' => 'Penalty updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update penalty']);
        }
        break;
        
    case 'resolve_penalty':
        $penalty_id = intval($_POST['penalty_id'] ?? 0);
        $notes = $_POST['notes'] ?? 'Resolved by admin';
        
        if ($penaltySystem->updatePenaltyStatus($penalty_id, 'Resolved', null, $notes)) {
            echo json_encode(['success' => true, 'message' => 'Penalty marked as resolved']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to resolve penalty']);
        }
        break;
        
    case 'delete_penalty':
        $penalty_id = intval($_POST['penalty_id'] ?? 0);
        
        if ($penaltySystem->deletePenalty($penalty_id)) {
            echo json_encode(['success' => true, 'message' => 'Penalty deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete penalty']);
        }
        break;
        
    case 'get_guideline':
        $guideline_id = intval($_POST['guideline_id'] ?? 0);
        $guideline = $penaltySystem->getGuidelineById($guideline_id);
        
        if ($guideline) {
            echo json_encode(['success' => true, 'guideline' => $guideline]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Guideline not found']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
?>
