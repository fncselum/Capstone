<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Only returned items with flagged/damage verification and without an active (non-cancelled) penalty
$sql = "
    SELECT 
        t.id,
        t.user_id,
        u.student_id,
        u.rfid_tag AS user_rfid,
        t.equipment_id,
        COALESCE(e.name, inv.equipment_name) AS equipment_name,
        t.return_verification_status,
        t.actual_return_date
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN equipment e ON (t.equipment_id = e.rfid_tag OR t.equipment_id = e.id)
    LEFT JOIN inventory inv ON inv.equipment_id = t.equipment_id
    WHERE 
        t.return_verification_status IN ('Damage','Flagged')
        AND t.actual_return_date IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM penalties p 
            WHERE p.transaction_id = t.id AND p.status <> 'Cancelled'
        )
    ORDER BY t.actual_return_date DESC, t.id DESC
";

$res = $conn->query($sql);
if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Query failed']);
    exit;
}

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'student_id' => $row['student_id'] ?? '',
        'rfid' => $row['user_rfid'] ?? '',
        'equipment_id' => $row['equipment_id'] ?? '',
        'equipment_name' => $row['equipment_name'] ?? '',
        'status' => $row['return_verification_status'] ?? '',
        'returned_at' => $row['actual_return_date'] ?? ''
    ];
}

$conn->close();

echo json_encode(['success' => true, 'items' => $data]);
