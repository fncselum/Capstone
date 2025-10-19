<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$rfid = isset($_POST['rfid']) ? trim($_POST['rfid']) : '';
if ($rfid === '') {
    echo json_encode(['success' => false, 'message' => 'RFID is required']);
    exit;
}

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'capstone';

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$conn->set_charset('utf8mb4');

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT t.id AS transaction_id, t.quantity, t.expected_return_date, t.status,
        e.name AS equipment_name, e.rfid_tag,
        TIMESTAMPDIFF(DAY, t.expected_return_date, NOW()) AS days_overdue
    FROM transactions t
    JOIN equipment e ON t.equipment_id = e.id
    WHERE t.user_id = ?
        AND t.transaction_type = 'Borrow'
        AND t.status = 'Active'
        AND e.rfid_tag = ?
    LIMIT 1");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
    $conn->close();
    exit;
}

$stmt->bind_param('is', $userId, $rfid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode(['success' => false, 'message' => 'No active borrow found for that RFID']);
    $stmt->close();
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

$statusText = 'On Time';
if (!empty($row['expected_return_date'])) {
    $expected = strtotime($row['expected_return_date']);
    $now = time();
    if ($expected < $now) {
        $statusText = 'Overdue';
    } elseif (date('Y-m-d', $expected) === date('Y-m-d')) {
        $statusText = 'Due Today';
    }
}

echo json_encode([
    'success' => true,
    'transaction_id' => (int)$row['transaction_id'],
    'equipment_name' => $row['equipment_name'],
    'quantity' => (int)$row['quantity'],
    'status_text' => $statusText,
    'days_overdue' => (int)max(0, $row['days_overdue']),
]);
?>
