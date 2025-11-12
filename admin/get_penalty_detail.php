<?php
// Start session
session_start();

// Authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get penalty ID
$penalty_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($penalty_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid penalty ID']);
    exit;
}

// Fetch penalty details with all related information
$query = "SELECT 
            p.id,
            p.transaction_id,
            p.user_id,
            p.equipment_id,
            p.equipment_name,
            p.penalty_type,
            p.damage_severity,
            p.damage_notes,
            p.description,
            p.penalty_amount,
            p.amount_owed,
            p.amount_note,
            p.days_overdue,
            p.daily_rate,
            p.status,
            p.created_at,
            p.date_imposed,
            p.date_resolved,
            da.detected_issues,
            da.similarity_score,
            da.comparison_summary,
            da.admin_assessment,
            u.student_id,
            u.rfid_tag as user_rfid,
            t.equipment_id AS txn_equipment_id,
            t.transaction_date,
            t.expected_return_date as due_date,
            t.actual_return_date,
            t.transaction_type,
            t.status as txn_status,
            t.condition_after as return_condition,
            COALESCE(e.rfid_tag, p.equipment_id) as equipment_rfid,
            e.name as equipment_full_name,
            c.name as category
          FROM penalties p
          LEFT JOIN penalty_damage_assessments da ON da.penalty_id = p.id
          LEFT JOIN users u ON p.user_id = u.id
          LEFT JOIN transactions t ON p.transaction_id = t.id
          LEFT JOIN equipment e ON (
                (p.equipment_id IS NOT NULL AND p.equipment_id <> '' AND e.id = p.equipment_id)
             OR (t.equipment_id IS NOT NULL AND e.id = t.equipment_id)
             OR (p.equipment_id IS NOT NULL AND p.equipment_id <> '' AND e.rfid_tag = p.equipment_id)
          )
          LEFT JOIN categories c ON e.category_id = c.id
          WHERE p.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to prepare query']);
    exit;
}

$stmt->bind_param('i', $penalty_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Penalty not found']);
    exit;
}

$penalty = $result->fetch_assoc();

$historySql = "SELECT h.old_status, h.new_status, h.notes, h.changed_by, h.created_at, a.username AS admin_username
               FROM penalty_status_history h
               LEFT JOIN admin_users a ON a.id = h.changed_by
               WHERE h.penalty_id = ?
               ORDER BY h.created_at ASC";
$histStmt = $conn->prepare($historySql);
$penalty['status_history'] = [];
if ($histStmt) {
    $histStmt->bind_param('i', $penalty_id);
    if ($histStmt->execute()) {
        $histResult = $histStmt->get_result();
        while ($row = $histResult->fetch_assoc()) {
            $penalty['status_history'][] = $row;
        }
    }
    $histStmt->close();
}

// Format dates
if ($penalty['transaction_date']) {
    $penalty['transaction_date'] = date('M d, Y g:i A', strtotime($penalty['transaction_date']));
}
if ($penalty['due_date']) {
    $penalty['due_date'] = date('M d, Y g:i A', strtotime($penalty['due_date']));
}
if ($penalty['actual_return_date']) {
    $penalty['actual_return_date'] = date('M d, Y g:i A', strtotime($penalty['actual_return_date']));
}
if ($penalty['created_at']) {
    $penalty['planned_return'] = date('M d, Y g:i A', strtotime($penalty['created_at']));
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($penalty);

$stmt->close();
$conn->close();
?>
