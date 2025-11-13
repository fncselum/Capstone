<?php
session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
$db_online = !($conn->connect_error);

$data = [
    'db_online' => $db_online,
    'system_health' => [
        'database_status' => $db_online ? 'Online' : 'Offline',
        'db_class' => $db_online ? 'online' : 'offline',
        'kiosk_status' => 'Unavailable',
        'kiosk_class' => 'offline',
        'response_time' => 'Unavailable',
        'last_transaction' => null
    ],
    'equipment' => [
        'total_equipment' => 0,
        'available' => 0,
        'out_of_stock' => 0,
        'maintenance' => 0
    ],
    'stats' => [
        'today_transactions' => 0,
        'active_borrows' => 0,
        'overdue_items' => 0,
        'active_users' => 0
    ]
];

if ($db_online) {
    $conn->select_db($dbname);

    // Response time
    $start = microtime(true);
    $ping = $conn->query("SELECT 1");
    $elapsed = ($ping ? microtime(true) - $start : 1.0);
    if ($elapsed < 0.2) $data['system_health']['response_time'] = 'Good';
    elseif ($elapsed < 0.6) $data['system_health']['response_time'] = 'Fair';
    else $data['system_health']['response_time'] = 'Slow';

    // Last transaction
    $res = $conn->query("SELECT MAX(transaction_date) as last_txn FROM transactions");
    if ($res && ($row = $res->fetch_assoc())) {
        $data['system_health']['last_transaction'] = $row['last_txn'];
    }

    // Maintenance mode
    $maintenance_mode = '0';
    $table_check = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        if ($stmt) {
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r && ($srow = $r->fetch_assoc())) {
                $maintenance_mode = $srow['setting_value'];
            }
            $stmt->close();
        }
    }

    if ($maintenance_mode === '1') {
        $data['system_health']['kiosk_status'] = 'Maintenance';
        $data['system_health']['kiosk_class'] = 'warning';
    } else {
        $data['system_health']['kiosk_status'] = 'Operational';
        $data['system_health']['kiosk_class'] = 'online';
    }

    // Equipment availability (real quantities)
    $q = "SELECT 
            COUNT(*) AS total_items,
            SUM(GREATEST(COALESCE(i.quantity, e.quantity, 0)
                       - COALESCE(i.borrowed_quantity, 0)
                       - COALESCE(i.damaged_quantity, 0)
                       - COALESCE(i.maintenance_quantity, 0), 0)) AS total_available_units,
            SUM(CASE WHEN GREATEST(COALESCE(i.quantity, e.quantity, 0)
                       - COALESCE(i.borrowed_quantity, 0)
                       - COALESCE(i.damaged_quantity, 0)
                       - COALESCE(i.maintenance_quantity, 0), 0) = 0 THEN 1 ELSE 0 END) AS out_of_stock_items,
            SUM(COALESCE(i.maintenance_quantity, 0)) AS maintenance_units
          FROM equipment e
          LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id";
    $resEq = $conn->query($q);
    if ($resEq && ($eq = $resEq->fetch_assoc())) {
        $data['equipment'] = [
            'total_equipment' => (int)($eq['total_items'] ?? 0),
            'available' => (int)($eq['total_available_units'] ?? 0),
            'out_of_stock' => (int)($eq['out_of_stock_items'] ?? 0),
            'maintenance' => (int)($eq['maintenance_units'] ?? 0)
        ];
    }

    // Quick stats
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    $resT = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_date BETWEEN '$today_start' AND '$today_end'");
    if ($resT && ($rowT = $resT->fetch_assoc())) {
        $data['stats']['today_transactions'] = (int)$rowT['count'];
    }

    $resB = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'Active'");
    if ($resB && ($rowB = $resB->fetch_assoc())) {
        $data['stats']['active_borrows'] = (int)$rowB['count'];
    }

    $resO = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'Active' AND expected_return_date < NOW()");
    if ($resO && ($rowO = $resO->fetch_assoc())) {
        $data['stats']['overdue_items'] = (int)$rowO['count'];
    }

    $resU = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Active'");
    if ($resU && ($rowU = $resU->fetch_assoc())) {
        $data['stats']['active_users'] = (int)$rowU['count'];
    }
}

echo json_encode($data);
