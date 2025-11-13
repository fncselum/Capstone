<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'capstone';

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['ok' => false, 'db_online' => false]);
    exit;
}
$conn->set_charset('utf8mb4');

$stats = [
    'total_equipment' => 0,
    'currently_borrowed' => 0,
    'total_returns' => 0,
    'active_violations' => 0,
    'total_users' => 0,
    'pending_penalties' => 0,
    'overdue_items' => 0,
    'available_equipment' => 0,
];

// Total equipment (count rows)
$res = $conn->query("SELECT COUNT(*) AS c FROM equipment");
if ($res && ($row = $res->fetch_assoc())) $stats['total_equipment'] = (int)$row['c'];

// Currently borrowed = Active borrows
$res = $conn->query("SELECT COUNT(*) AS c FROM transactions WHERE status='Active' AND transaction_type='Borrow'");
if ($res && ($row = $res->fetch_assoc())) $stats['currently_borrowed'] = (int)$row['c'];

// Total returns
$res = $conn->query("SELECT COUNT(*) AS c FROM transactions WHERE transaction_type='Return'");
if ($res && ($row = $res->fetch_assoc())) $stats['total_returns'] = (int)$row['c'];

// Active violations (overdue or damaged after)
$res = $conn->query("SELECT COUNT(*) AS c FROM transactions WHERE transaction_type='Borrow' AND (expected_return_date < CURDATE() OR condition_after = 'Damaged')");
if ($res && ($row = $res->fetch_assoc())) $stats['active_violations'] = (int)$row['c'];

// Total users
$res = $conn->query("SELECT COUNT(*) AS c FROM users");
if ($res && ($row = $res->fetch_assoc())) $stats['total_users'] = (int)$row['c'];

// Pending penalties (Pending)
$res = $conn->query("SELECT COUNT(*) AS c FROM penalties WHERE status='Pending'");
if ($res && ($row = $res->fetch_assoc())) $stats['pending_penalties'] = (int)$row['c'];

// Overdue items: active and past expected_return_date
$res = $conn->query("SELECT COUNT(*) AS c FROM transactions WHERE status='Active' AND expected_return_date < CURDATE()");
if ($res && ($row = $res->fetch_assoc())) $stats['overdue_items'] = (int)$row['c'];

// Available equipment = sum of remaining units across items
$res = $conn->query("SELECT 
    SUM(GREATEST(COALESCE(i.quantity, e.quantity, 0)
               - COALESCE(i.borrowed_quantity, 0)
               - COALESCE(i.damaged_quantity, 0)
               - COALESCE(i.maintenance_quantity, 0), 0)) AS total_available_units
  FROM equipment e
  LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id");
if ($res && ($row = $res->fetch_assoc())) $stats['available_equipment'] = (int)($row['total_available_units'] ?? 0);


echo json_encode(['ok' => true, 'db_online' => true, 'stats' => $stats, 'ts' => date('Y-m-d H:i:s')]);
$conn->close();
exit;
