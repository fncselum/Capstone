<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
if ($status_filter !== 'all') {
    $where_conditions[] = "p.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($type_filter !== 'all') {
    $where_conditions[] = "p.penalty_type = '" . $conn->real_escape_string($type_filter) . "'";
}
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $where_conditions[] = "(p.user_id LIKE '%$search_escaped%' OR p.equipment_name LIKE '%$search_escaped%' OR p.transaction_id LIKE '%$search_escaped%')";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$sql = "SELECT p.*, 
               pg.title as guideline_title
        FROM penalties p
        LEFT JOIN penalty_guidelines pg ON p.guideline_id = pg.id
        $where_clause
        ORDER BY p.created_at DESC";

$result = $conn->query($sql);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=penalties_export_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add column headers
fputcsv($output, [
    'Penalty ID',
    'User/RFID',
    'Transaction ID',
    'Equipment',
    'Penalty Type',
    'Guideline',
    'Amount (₱)',
    'Points',
    'Days Overdue',
    'Status',
    'Date Imposed',
    'Date Resolved',
    'Description',
    'Notes'
]);

// Add data rows
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['user_id'],
            $row['transaction_id'],
            $row['equipment_name'] ?? 'N/A',
            $row['penalty_type'],
            $row['guideline_title'] ?? 'Manual',
            number_format($row['penalty_amount'], 2),
            $row['penalty_points'],
            $row['days_overdue'] ?? 0,
            $row['status'],
            $row['date_imposed'] ? date('Y-m-d H:i', strtotime($row['date_imposed'])) : '',
            $row['date_resolved'] ? date('Y-m-d H:i', strtotime($row['date_resolved'])) : '',
            $row['description'],
            $row['notes'] ?? ''
        ]);
    }
}

fclose($output);
$conn->close();
exit;
?>
