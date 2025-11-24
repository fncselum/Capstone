<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$activeStatuses = ["Pending", "Under Review", "Appealed"];
$placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
$types = str_repeat('s', count($activeStatuses)) . 'i';

$sql = "SELECT 
            id,
            transaction_id,
            guideline_id,
            equipment_id,
            equipment_name,
            penalty_type,
            COALESCE(amount_owed, penalty_amount) AS amount,
            penalty_amount,
            amount_owed,
            damage_severity,
            description,
            damage_notes,
            status,
            days_overdue,
            daily_rate,
            date_imposed,
            created_at
        FROM penalties
        WHERE status IN ($placeholders) AND user_id = ?
        ORDER BY COALESCE(date_imposed, created_at) DESC, id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query preparation failed']);
    exit;
}

$params = $activeStatuses;
$params[] = $user_id;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$row['id'],
        'transaction_id' => (int)$row['transaction_id'],
        'guideline_id' => isset($row['guideline_id']) ? (int)$row['guideline_id'] : null,
        'equipment_id' => $row['equipment_id'],
        'equipment_name' => $row['equipment_name'],
        'penalty_type' => $row['penalty_type'],
        'amount' => (float)$row['amount'],
        'penalty_amount' => isset($row['penalty_amount']) ? (float)$row['penalty_amount'] : null,
        'amount_owed' => isset($row['amount_owed']) ? (float)$row['amount_owed'] : null,
        'damage_severity' => $row['damage_severity'],
        'description' => $row['description'],
        'damage_notes' => $row['damage_notes'],
        'status' => $row['status'],
        'days_overdue' => isset($row['days_overdue']) ? (int)$row['days_overdue'] : null,
        'daily_rate' => isset($row['daily_rate']) ? (float)$row['daily_rate'] : null,
        'date_imposed' => $row['date_imposed'],
        'created_at' => $row['created_at']
    ];
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'penalties' => $rows]);
