<?php
session_start();

// Require admin session
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

$studentId = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$rfid = isset($_GET['rfid']) ? trim($_GET['rfid']) : '';

if ($studentId === '' && $rfid === '') {
    echo json_encode(['success' => false, 'message' => 'Missing student identifier']);
    exit;
}

$sql = "
    SELECT 
        t.id,
        t.equipment_id,
        COALESCE(e.name, inv.equipment_name) AS equipment_name,
        t.return_verification_status
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN equipment e ON (t.equipment_id = e.rfid_tag OR t.equipment_id = e.id)
    LEFT JOIN inventory inv ON inv.equipment_id = t.equipment_id
    WHERE 
        (u.student_id = ? OR u.rfid_tag = ?)
        AND t.return_verification_status IN ('Damage','Flagged')
        AND NOT EXISTS (
            SELECT 1 FROM penalties p
            WHERE p.transaction_id = t.id AND p.status <> 'Cancelled'
        )
    ORDER BY t.id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare query']);
    exit;
}

$stmt->bind_param('ss', $studentId, $rfid);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'id' => (int)$row['id'],
        'equipment_id' => $row['equipment_id'],
        'equipment_name' => $row['equipment_name'] ?: '',
        'status' => $row['return_verification_status']
    ];
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'transactions' => $items]);
