<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Auth check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

// DB
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'capstone';

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo 'Database connection failed';
    exit;
}
$conn->set_charset('utf8mb4');

// Filters (optional; defaults to all)
$filter_type = $_GET['type'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$filter_date = $_GET['date'] ?? '';
$search_query = trim($_GET['search'] ?? '');

$where = [];
if ($filter_type !== 'all') {
    $where[] = "t.transaction_type = '" . $conn->real_escape_string($filter_type) . "'";
}
if ($filter_status !== 'all') {
    $where[] = "t.status = '" . $conn->real_escape_string($filter_status) . "'";
}
if ($filter_date !== '') {
    $where[] = "DATE(t.transaction_date) = '" . $conn->real_escape_string($filter_date) . "'";
}
if ($search_query !== '') {
    $q = $conn->real_escape_string($search_query);
    $where[] = "(u.student_id LIKE '%$q%' OR e.name LIKE '%$q%' OR t.notes LIKE '%$q%')";
}
$where_clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT 
            t.id,
            t.transaction_type,
            t.transaction_date,
            t.actual_return_date,
            t.expected_return_date,
            t.status,
            t.approval_status,
            t.return_verification_status,
            t.notes,
            t.quantity,
            u.student_id,
            u.rfid_tag AS user_rfid,
            u.status AS user_status,
            e.name AS equipment_name,
            e.rfid_tag AS equipment_rfid,
            c.name AS category_name,
            a.username AS approved_by_name
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
        LEFT JOIN categories c ON e.category_id = c.id
        LEFT JOIN admin_users a ON t.approved_by = a.id
        $where_clause
        ORDER BY t.transaction_date DESC";

$result = $conn->query($sql);

$filename = 'kiosk_logs_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

// Metadata rows
$generatedAt = date('Y-m-d H:i:s');
$filtersSummary = [
    'Type' => $filter_type,
    'Status' => $filter_status,
    'Date' => $filter_date ?: 'All',
    'Search' => $search_query ?: '—'
];
fputcsv($out, ['Kiosk Logs Export']);
fputcsv($out, ['Generated at', $generatedAt]);
fputcsv($out, ['Filters', 'Type=' . $filtersSummary['Type'], 'Status=' . $filtersSummary['Status'], 'Date=' . $filtersSummary['Date'], 'Search=' . $filtersSummary['Search']]);
fputcsv($out, []); // spacer

$headers = [
    'Transaction ID',
    'Type',
    'Date/Time',
    'Expected Return',
    'Actual Return',
    'Status',
    'Approval Status',
    'Return Verification',
    'Quantity',
    'Student ID',
    'User RFID',
    'User Status',
    'Equipment',
    'Equipment RFID',
    'Category',
    'Approved By',
    'Notes'
];
fputcsv($out, $headers);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Sanitize text fields to avoid CSV line breaks
        $sanitize = function($v) {
            $v = (string)$v;
            $v = str_replace(["\r\n", "\r", "\n"], ' ', $v);
            return trim($v);
        };

        $csvRow = [
            (int)$row['id'],
            $sanitize($row['transaction_type']),
            $row['transaction_date'] ? date('Y-m-d H:i:s', strtotime($row['transaction_date'])) : '',
            $row['expected_return_date'] ? date('Y-m-d H:i:s', strtotime($row['expected_return_date'])) : '',
            $row['actual_return_date'] ? date('Y-m-d H:i:s', strtotime($row['actual_return_date'])) : '',
            $sanitize($row['status']),
            $sanitize($row['approval_status']),
            $sanitize($row['return_verification_status']),
            (int)$row['quantity'],
            $sanitize($row['student_id']),
            $sanitize($row['user_rfid']),
            $sanitize($row['user_status']),
            $sanitize($row['equipment_name']),
            $sanitize($row['equipment_rfid']),
            $sanitize($row['category_name']),
            $sanitize($row['approved_by_name']),
            $sanitize($row['notes'])
        ];
        fputcsv($out, $csvRow);
    }
}

fclose($out);
$conn->close();
exit;
