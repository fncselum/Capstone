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

// Defaults
$summary = [
    'outstanding_amount' => 0.00,
    'active_penalties' => 0,
    'resolved_cases' => 0,
    'latest_penalty' => null
];

// Calculate outstanding amount and active penalties
// Active considered: Pending, Under Review, Appealed
$activeStatuses = ["Pending", "Under Review", "Appealed"];
$placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));

// Build statement types string for bind_param
$types_active = str_repeat('s', count($activeStatuses)) . 'i';

$sqlActive = "SELECT 
                COALESCE(SUM(amount_owed),0) AS total_amount,
                COUNT(*) AS cnt
              FROM penalties
              WHERE user_id = ? AND status IN ($placeholders)";

$stmt = $conn->prepare($sqlActive);
if ($stmt) {
    // Bind dynamically: user_id first is i, then statuses (s...)
    // To match types, we reorder: statuses first, then user_id -> adjust SQL accordingly
    // Rebuild SQL to bind in order: statuses..., user_id
    $sqlActive = "SELECT COALESCE(SUM(COALESCE(amount_owed, penalty_amount)),0) AS total_amount, COUNT(*) AS cnt
                  FROM penalties
                  WHERE status IN ($placeholders) AND user_id = ?";
    $stmt = $conn->prepare($sqlActive);
    if ($stmt) {
        $params = $activeStatuses;
        $params[] = $user_id;
        $types = str_repeat('s', count($activeStatuses)) . 'i';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $summary['outstanding_amount'] = (float)$row['total_amount'];
            $summary['active_penalties'] = (int)$row['cnt'];
            $summary['has_outstanding'] = $summary['outstanding_amount'] > 0;
        }
        $stmt->close();
    }
}

// Resolved cases
$sqlResolved = "SELECT COUNT(*) AS cnt FROM penalties WHERE user_id = ? AND status = 'Resolved'";
$stmt = $conn->prepare($sqlResolved);
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $summary['resolved_cases'] = (int)$row['cnt'];
    }
    $stmt->close();
}

// Latest penalty date (use date_imposed or created_at fallback)
$sqlLatest = "SELECT COALESCE(MAX(date_imposed), MAX(created_at)) AS latest FROM penalties WHERE user_id = ?";
$stmt = $conn->prepare($sqlLatest);
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $summary['latest_penalty'] = $row['latest'];
    }
    $stmt->close();
}

$conn->close();

echo json_encode(['success' => true, 'summary' => $summary]);
