<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Database connection
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

$transaction_id = $_GET['id'] ?? '';

if (empty($transaction_id)) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
    exit;
}

// Get transaction details with user and equipment info
$stmt = $conn->prepare("
    SELECT 
        t.id,
        t.user_id,
        t.equipment_id,
        t.borrow_date,
        t.expected_return_date,
        t.actual_return_date,
        u.student_id,
        u.rfid_tag,
        u.name as user_name,
        e.name as equipment_name,
        e.rfid_tag as equipment_rfid
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN equipment e ON t.equipment_id = e.id
    WHERE t.id = ?
    LIMIT 1
");

$stmt->bind_param('i', $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $transaction = $result->fetch_assoc();

    // Check if a penalty already exists for this transaction
    $p = $conn->prepare("SELECT 1 FROM penalties WHERE transaction_id = ? LIMIT 1");
    if ($p) {
        $p->bind_param('i', $transaction['id']);
        $p->execute();
        $p->store_result();
        $exists = $p->num_rows > 0;
        $p->close();
        if ($exists) {
            echo json_encode([
                'success' => false,
                'already_penalized' => true,
                'message' => 'A penalty already exists for this transaction. Only one penalty can be issued per transaction.'
            ]);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'transaction' => [
            'id' => $transaction['id'],
            'user_id' => $transaction['user_id'],
            'equipment_id' => $transaction['equipment_id'],
            'student_id' => $transaction['student_id'],
            'rfid_tag' => $transaction['rfid_tag'],
            'user_name' => $transaction['user_name'],
            'equipment_name' => $transaction['equipment_name'],
            'equipment_rfid' => $transaction['equipment_rfid'],
            'borrow_date' => $transaction['borrow_date'],
            'expected_return_date' => $transaction['expected_return_date'],
            'actual_return_date' => $transaction['actual_return_date']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Transaction not found']);
}

$stmt->close();
$conn->close();
