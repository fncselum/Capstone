<?php
// Monthly Reports - printable
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'capstone';
$conn = @new mysqli($host, $user, $password, $dbname);

$adminName = $_SESSION['admin_username'] ?? 'Admin';

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$rows = [];
$equipmentSummary = [];
$totals = [
    'borrowed_transactions' => 0,
    'borrowed_quantity' => 0,
    'returned_transactions' => 0,
    'returned_quantity' => 0,
    'damaged_returns' => 0,
    'penalty_records' => 0,
    'currently_borrowed' => 0
];

if (!$conn->connect_error) {
    // Build WHERE clause based on period
    $whereClause = '';
    $params = [];
    $types = '';
    
    switch($period) {
        case 'daily':
            $whereClause = "DATE(COALESCE(t.transaction_date, t.created_at)) = ?";
            $params[] = $date;
            $types = 's';
            break;
        case 'weekly':
            $whereClause = "YEARWEEK(COALESCE(t.transaction_date, t.created_at), 1) = YEARWEEK(?, 1)";
            $params[] = $date;
            $types = 's';
            break;
        case 'monthly':
            $whereClause = "MONTH(COALESCE(t.transaction_date, t.created_at)) = ? AND YEAR(COALESCE(t.transaction_date, t.created_at)) = ?";
            $params[] = $month;
            $params[] = $year;
            $types = 'ii';
            break;
        case 'yearly':
            $whereClause = "YEAR(COALESCE(t.transaction_date, t.created_at)) = ?";
            $params[] = $year;
            $types = 'i';
            break;
    }
    
    $stmt = $conn->prepare("SELECT t.*, 
                                    COALESCE(t.transaction_date, t.created_at) AS txn_datetime,
                                    e.name AS equipment_name,
                                    e.rfid_tag,
                                    u.student_id AS borrower_student_id
                             FROM transactions t
                             LEFT JOIN equipment e ON t.equipment_id = e.id
                             LEFT JOIN users u ON t.user_id = u.id
                             WHERE $whereClause
                             ORDER BY txn_datetime ASC");
    if ($stmt) {
        if ($types === 's') {
            $stmt->bind_param($types, $params[0]);
        } elseif ($types === 'i') {
            $stmt->bind_param($types, $params[0]);
        } elseif ($types === 'ii') {
            $stmt->bind_param($types, $params[0], $params[1]);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($r = $result->fetch_assoc()) {
            $rows[] = $r;

            $quantity = (int)($r['quantity'] ?? 1);
            if ($quantity <= 0) { $quantity = 1; }

            $transactionType = strtolower($r['transaction_type'] ?? '');
            $status = strtolower($r['status'] ?? '');
            $conditionAfter = strtolower($r['condition_after'] ?? '');
            $rfid = $r['rfid_tag'] ?? $r['rfid_id'] ?? 'N/A';
            $equipmentName = $r['equipment_name'] ?? 'Unknown Equipment';

            if (!isset($equipmentSummary[$rfid])) {
                $equipmentSummary[$rfid] = [
                    'equipment_name' => $equipmentName,
                    'rfid' => $rfid,
                    'borrowed_transactions' => 0,
                    'borrowed_quantity' => 0,
                    'returned_transactions' => 0,
                    'returned_quantity' => 0,
                    'damaged_returns' => 0,
                    'penalty_records' => 0,
                    'currently_borrowed' => 0
                ];
            }

            if ($transactionType === 'borrow') {
                $totals['borrowed_transactions']++;
                $totals['borrowed_quantity'] += $quantity;
                if ($status === 'active') {
                    $totals['currently_borrowed'] += $quantity;
                    $equipmentSummary[$rfid]['currently_borrowed'] += $quantity;
                }
                $equipmentSummary[$rfid]['borrowed_transactions']++;
                $equipmentSummary[$rfid]['borrowed_quantity'] += $quantity;
            } elseif ($transactionType === 'return') {
                $totals['returned_transactions']++;
                $totals['returned_quantity'] += $quantity;
                $equipmentSummary[$rfid]['returned_transactions']++;
                $equipmentSummary[$rfid]['returned_quantity'] += $quantity;
            }

            if ($conditionAfter === 'damaged') {
                $totals['damaged_returns'] += $quantity;
                $equipmentSummary[$rfid]['damaged_returns'] += $quantity;
            }
        }
        $stmt->close();
    }
    
    // Count penalty records for each equipment based on period
    $penaltyWhereClause = '';
    switch($period) {
        case 'daily':
            $penaltyWhereClause = "DATE(p.created_at) = ?";
            break;
        case 'weekly':
            $penaltyWhereClause = "YEARWEEK(p.created_at, 1) = YEARWEEK(?, 1)";
            break;
        case 'monthly':
            $penaltyWhereClause = "MONTH(p.created_at) = ? AND YEAR(p.created_at) = ?";
            break;
        case 'yearly':
            $penaltyWhereClause = "YEAR(p.created_at) = ?";
            break;
    }
    
    $penaltyStmt = $conn->prepare("SELECT e.rfid_tag, COUNT(p.id) as penalty_count
                                     FROM penalties p
                                     INNER JOIN transactions t ON p.transaction_id = t.id
                                     INNER JOIN equipment e ON t.equipment_id = e.id
                                     WHERE $penaltyWhereClause
                                     GROUP BY e.rfid_tag");
    if ($penaltyStmt) {
        if ($types === 's') {
            $penaltyStmt->bind_param($types, $params[0]);
        } elseif ($types === 'i') {
            $penaltyStmt->bind_param($types, $params[0]);
        } elseif ($types === 'ii') {
            $penaltyStmt->bind_param($types, $params[0], $params[1]);
        }
        $penaltyStmt->execute();
        $penaltyResult = $penaltyStmt->get_result();
        while ($pRow = $penaltyResult->fetch_assoc()) {
            $rfid = $pRow['rfid_tag'];
            $count = (int)$pRow['penalty_count'];
            $totals['penalty_records'] += $count;
            if (isset($equipmentSummary[$rfid])) {
                $equipmentSummary[$rfid]['penalty_records'] = $count;
            }
        }
        $penaltyStmt->close();
    }
}

$equipmentSummaryList = array_values($equipmentSummary);
usort($equipmentSummaryList, function($a, $b) {
    return strcasecmp($a['equipment_name'], $b['equipment_name']);
});
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        @media print {
            /* Hide navigation and controls */
            .no-print, .admin-sidebar, .sidebar { display:none !important; }
            
            /* Reset page layout */
            body {
                margin: 0;
                padding: 0;
                background: white !important;
            }
            
            .admin-container {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .main-content { 
                margin: 0 !important; 
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            
            /* Document header styling */
            .top-header {
                background: white !important;
                border: none !important;
                border-bottom: 3px solid #006633 !important;
                padding: 30px 40px !important;
                margin: 0 0 30px 0 !important;
                page-break-after: avoid;
                text-align: center;
            }
            
            .page-title {
                color: #006633 !important;
                font-size: 2rem !important;
                font-weight: 700 !important;
                margin-bottom: 10px !important;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .top-header > div:first-child {
                text-align: center !important;
            }
            
            /* Content section */
            .content-section {
                padding: 0 40px 40px 40px !important;
            }
            
            /* Summary cards in print */
            .summary-grid { 
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 30px;
                page-break-inside: avoid;
            }
            
            .summary-card {
                box-shadow: none !important;
                border: 2px solid #e0e0e0 !important;
                border-radius: 8px !important;
                padding: 15px !important;
                background: white !important;
                page-break-inside: avoid;
            }
            
            .summary-card h3 {
                font-size: 0.9rem !important;
                color: #666 !important;
                margin-bottom: 8px !important;
            }
            
            .summary-card strong {
                font-size: 1.5rem !important;
                color: #006633 !important;
            }
            
            /* Panel styling */
            .panel { 
                page-break-inside: avoid;
                box-shadow: none !important;
                border: 2px solid #e0e0e0 !important;
                border-radius: 8px !important;
                margin-bottom: 25px !important;
                background: white !important;
            }
            
            .panel h3 {
                background: #f5f5f5 !important;
                color: #006633 !important;
                padding: 12px 15px !important;
                margin: 0 !important;
                border-bottom: 2px solid #e0e0e0 !important;
                font-size: 1.1rem !important;
            }
            
            /* Table styling */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 0.85rem !important;
                margin: 0 !important;
            }
            
            thead {
                background: #006633 !important;
                color: white !important;
            }
            
            thead th {
                padding: 10px 8px !important;
                text-align: left !important;
                font-weight: 600 !important;
                border: 1px solid #005522 !important;
            }
            
            tbody td {
                padding: 8px !important;
                border: 1px solid #ddd !important;
            }
            
            tbody tr:nth-child(even) {
                background: #f9f9f9 !important;
            }
            
            tbody tr:nth-child(odd) {
                background: white !important;
            }
            
            /* Badge styling for print */
            .badge {
                padding: 3px 8px !important;
                border-radius: 4px !important;
                font-size: 0.75rem !important;
                font-weight: 600 !important;
                display: inline-block !important;
            }
            
            .badge.borrow {
                background: #e3f2fd !important;
                color: #1976d2 !important;
                border: 1px solid #1976d2 !important;
            }
            
            .badge.return {
                background: #e8f5e9 !important;
                color: #388e3c !important;
                border: 1px solid #388e3c !important;
            }
            
            /* Page breaks */
            .section-header {
                page-break-after: avoid;
            }
            
            /* Footer for pages */
            @page {
                margin: 1.5cm;
                @bottom-right {
                    content: "Page " counter(page) " of " counter(pages);
                }
            }
        }
        
        .filters { 
            display:flex; 
            align-items:center; 
            gap:10px;
            flex-wrap: wrap;
        }
        
        .filters select {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }
        
        .filters select:focus {
            outline: none;
            border-color: #006633;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fdf9 100%);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 2px solid #e8f3ee;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #006633 0%, #00994d 100%);
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,102,51,0.15);
        }
        
        .summary-card h3 {
            font-size: 0.9rem;
            color: #5f7c6e;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-card h3 i {
            font-size: 1.2rem;
            color: #006633;
        }
        
        .summary-card strong {
            font-size: 2.2rem;
            color: #006633;
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .summary-card .subtitle {
            color: #6c8577;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .panel {
            background: #ffffff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 1px solid #e0ece6;
        }
        
        .panel h3 {
            margin-bottom: 20px;
            font-size: 1.2rem;
            color: #006633;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 3px solid #e8f3ee;
        }
        
        .panel h3 i {
            color: #006633;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f5f3;
            text-align: left;
        }
        
        th {
            background: linear-gradient(135deg, #f3fbf6 0%, #e8f5ee 100%);
            color: #006633;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #006633;
        }
        
        tbody tr {
            transition: background-color 0.2s ease;
        }
        
        tbody tr:hover { 
            background: #f9fcfb;
        }
        
        tbody tr:nth-child(even) {
            background: #fafcfb;
        }
        
        tbody tr:nth-child(even):hover {
            background: #f5f9f7;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge.borrow { 
            background: #e3f2fd; 
            color: #0d47a1;
            border: 1px solid #90caf9;
        }
        
        .badge.return { 
            background: #e8f5e9; 
            color: #1b5e20;
            border: 1px solid #81c784;
        }
        
        .badge.damaged { 
            background: #ffebee; 
            color: #b71c1c;
            border: 1px solid #ef9a9a;
        }
        
        .badge.neutral { 
            background: #f0f0f0; 
            color: #555;
            border: 1px solid #ddd;
        }
        
        .empty-state {
            padding: 40px 24px;
            text-align: center;
            color: #556b66;
            font-size: 1rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #c5d9ce;
            margin-bottom: 16px;
            display: block;
        }
        
        /* Enhanced header styling */
        .top-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8fdf9 100%);
            border: 2px solid #e8f3ee;
            border-left: 6px solid #006633;
        }
        
        .page-title {
            color: #006633;
            font-weight: 700;
        }
        
        /* Extract buttons */
        .extract-btn {
            padding: 10px 18px;
            border: 2px solid #006633;
            background: white;
            color: #006633;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .extract-btn:hover {
            background: #006633;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,102,51,0.3);
        }
        
        .extract-btn.active {
            background: #006633;
            color: white;
            font-weight: 700;
        }
        
        .extract-btn i {
            font-size: 1rem;
        }
        
        /* Enhanced add-btn for export buttons */
        .add-btn {
            transition: all 0.3s ease;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        /* Toast Notification System */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .toast {
            min-width: 300px;
            max-width: 400px;
            padding: 16px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
            border-left: 4px solid;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
        
        .toast.hiding {
            animation: slideOut 0.3s ease-in forwards;
        }
        
        .toast.success {
            border-left-color: #4caf50;
        }
        
        .toast.info {
            border-left-color: #2196f3;
        }
        
        .toast.warning {
            border-left-color: #ff9800;
        }
        
        .toast.error {
            border-left-color: #f44336;
        }
        
        .toast-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .toast.success .toast-icon {
            color: #4caf50;
        }
        
        .toast.info .toast-icon {
            color: #2196f3;
        }
        
        .toast.warning .toast-icon {
            color: #ff9800;
        }
        
        .toast.error .toast-icon {
            color: #f44336;
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #333;
            margin-bottom: 4px;
        }
        
        .toast-message {
            font-size: 0.85rem;
            color: #666;
            line-height: 1.4;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        
        .toast-close:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        /* Enhanced section header */
        .section-header h2 {
            font-size: 1.3rem;
            color: #006633;
            font-weight: 700;
        }
        
        /* Improved filter form */
        .filters select {
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        
        .filters select:hover {
            border-color: #006633;
            box-shadow: 0 2px 8px rgba(0,102,51,0.15);
        }
        
        /* Print optimizations */
        @media print {
            .summary-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .panel {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            tbody tr:nth-child(even) {
                background: #f9f9f9;
            }
        }
    </style>
</head>
<body>
    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div>
                    <?php
                        $periodLabel = ucfirst($period) . ' Report';
                        $periodDisplay = '';
                        switch($period) {
                            case 'daily':
                                $periodDisplay = date('F j, Y', strtotime($date));
                                break;
                            case 'weekly':
                                $weekStart = date('M j', strtotime($date . ' -' . date('w', strtotime($date)) . ' days'));
                                $weekEnd = date('M j, Y', strtotime($date . ' +' . (6 - date('w', strtotime($date))) . ' days'));
                                $periodDisplay = "Week of $weekStart - $weekEnd";
                                break;
                            case 'monthly':
                                $periodDisplay = date('F', mktime(0,0,0,$month,1,$year)) . ' ' . $year;
                                break;
                            case 'yearly':
                                $periodDisplay = $year;
                                break;
                        }
                    ?>
                    <h1 class="page-title" style="margin-bottom:4px;"><?= $periodLabel ?></h1>
                    <div style="color:#555; font-size:0.95rem;">
                        Period: <strong><?= $periodDisplay ?></strong>
                        &nbsp;•&nbsp; Prepared by: <strong><?= htmlspecialchars($adminName) ?></strong>
                        &nbsp;•&nbsp; Generated: <strong><?= date('M j, Y g:i A') ?></strong>
                    </div>
                </div>
                <div class="no-print" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="add-btn" onclick="window.print()" style="background: #9c27b0;">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button class="add-btn" onclick="exportToWord()" style="background: #2b579a;">
                        <i class="fas fa-file-word"></i> Export Word
                    </button>
                    <button class="add-btn" onclick="exportToExcel()" style="background: #217346;">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </header>

            <section class="content-section active">
                <div class="section-header no-print" style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; width: 100%;">
                        <form class="filters" method="GET" style="display: flex; align-items: center; gap: 10px; margin: 0; flex: 1; justify-content: flex-start;">
                            <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
                            
                            <?php if ($period === 'daily' || $period === 'weekly'): ?>
                                <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" style="padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem;">
                            <?php endif; ?>
                            
                            <?php if ($period === 'monthly'): ?>
                                <select name="month">
                                    <?php for($m=1;$m<=12;$m++): ?>
                                        <option value="<?= $m ?>" <?= $m===$month? 'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1,$year)) ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>
                            
                            <?php if ($period === 'monthly' || $period === 'yearly'): ?>
                                <select name="year">
                                    <?php for($y=date('Y')-4;$y<=date('Y')+1;$y++): ?>
                                        <option value="<?= $y ?>" <?= $y===$year? 'selected':'' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>
                            
                            <button class="add-btn" type="submit"><i class="fas fa-filter"></i> Apply</button>
                        </form>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button class="extract-btn <?= $period === 'daily' ? 'active' : '' ?>" onclick="extractReport('daily')" title="Extract Daily Report">
                                <i class="fas fa-calendar-day"></i> Daily
                            </button>
                            <button class="extract-btn <?= $period === 'weekly' ? 'active' : '' ?>" onclick="extractReport('weekly')" title="Extract Weekly Report">
                                <i class="fas fa-calendar-week"></i> Weekly
                            </button>
                            <button class="extract-btn <?= $period === 'monthly' ? 'active' : '' ?>" onclick="extractReport('monthly')" title="Extract Monthly Report">
                                <i class="fas fa-calendar-alt"></i> Monthly
                            </button>
                            <button class="extract-btn <?= $period === 'yearly' ? 'active' : '' ?>" onclick="extractReport('yearly')" title="Extract Yearly Report">
                                <i class="fas fa-calendar"></i> Yearly
                            </button>
                        </div>
                    </div>
                </div>

                <div class="summary-grid">
                    <div class="summary-card">
                        <h3><i class="fas fa-arrow-circle-down"></i> Total Borrowed</h3>
                        <strong><?= number_format($totals['borrowed_quantity']) ?></strong>
                        <div class="subtitle">Across <?= number_format($totals['borrowed_transactions']) ?> borrow transactions</div>
                    </div>
                    <div class="summary-card">
                        <h3><i class="fas fa-arrow-circle-up"></i> Total Returned</h3>
                        <strong><?= number_format($totals['returned_quantity']) ?></strong>
                        <div class="subtitle">Across <?= number_format($totals['returned_transactions']) ?> return transactions</div>
                    </div>
                    <div class="summary-card">
                        <h3><i class="fas fa-exclamation-triangle"></i> Damaged Items</h3>
                        <strong><?= number_format($totals['damaged_returns']) ?></strong>
                        <div class="subtitle" style="color:#b53d3d;">Reported upon return</div>
                    </div>
                    <div class="summary-card">
                        <h3><i class="fas fa-hand-holding"></i> Currently Borrowed</h3>
                        <strong><?= number_format($totals['currently_borrowed']) ?></strong>
                        <div class="subtitle">Items not yet returned</div>
                    </div>
                    <div class="summary-card">
                        <h3><i class="fas fa-file-invoice"></i> Penalty Records</h3>
                        <strong><?= number_format($totals['penalty_records']) ?></strong>
                        <div class="subtitle">Issued this period</div>
                    </div>
                </div>

                <div class="panel">
                    <h3><i class="fas fa-boxes"></i> Equipment Summary (RFID)</h3>
                    <?php if(empty($equipmentSummaryList)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            No equipment activity for this period.
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>RFID Tag</th>
                                    <th>Equipment</th>
                                    <th>Borrowed Qty</th>
                                    <th>Returned Qty</th>
                                    <th>Damaged Returned Qty</th>
                                    <th>Currently Borrowed Qty</th>
                                    <th>Penalty Records</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($equipmentSummaryList as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['rfid']) ?></td>
                                        <td><?= htmlspecialchars($item['equipment_name']) ?></td>
                                        <td><?= number_format($item['borrowed_quantity']) ?></td>
                                        <td><?= number_format($item['returned_quantity']) ?></td>
                                        <td><?= number_format($item['damaged_returns']) ?></td>
                                        <td><?= number_format($item['currently_borrowed']) ?></td>
                                        <td><?= number_format($item['penalty_records']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h3><i class="fas fa-list-alt"></i> Detailed Transactions</h3>
                    <?php if(empty($rows)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            No transactions for this period.
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student ID</th>
                                    <th>RFID</th>
                                    <th>Equipment</th>
                                    <th>Type</th>
                                    <th>Qty</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($rows as $r): ?>
                                    <?php
                                        $type = strtolower($r['transaction_type'] ?? '');
                                        if ($type === 'borrow') {
                                            $badgeClass = 'borrow';
                                            $badgeLabel = 'Borrow';
                                        } elseif ($type === 'return') {
                                            $badgeClass = 'return';
                                            $badgeLabel = 'Return';
                                        } else {
                                            $badgeClass = 'neutral';
                                            $badgeLabel = strtoupper($type ?: 'N/A');
                                        }
                                        $rfidDisplay = $r['rfid_tag'] ?? $r['rfid_id'] ?? 'N/A';
                                        $transactionDate = $r['txn_datetime'] ?? $r['transaction_date'] ?? $r['created_at'];
                                    ?>
                                    <tr>
                                        <td><?= $transactionDate ? date('M j, Y g:i A', strtotime($transactionDate)) : 'N/A' ?></td>
                                        <td><?= htmlspecialchars($r['borrower_student_id'] ?? $r['rfid_id'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($rfidDisplay) ?></td>
                                        <td><?= htmlspecialchars($r['equipment_name'] ?? 'Unknown') ?></td>
                                        <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                                        <td><?= number_format((int)($r['quantity'] ?? 1)) ?></td>
                                        <td><?= htmlspecialchars($r['status'] ?? 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }
        
        // Toast Notification System
        function showToast(title, message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icons = {
                success: 'fa-check-circle',
                info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle',
                error: 'fa-times-circle'
            };
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas ${icons[type]}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="closeToast(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                closeToast(toast.querySelector('.toast-close'));
            }, 5000);
        }
        
        function closeToast(button) {
            const toast = button.closest('.toast');
            toast.classList.add('hiding');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
        
        // Export to Word Document
        function exportToWord() {
            const period = '<?= $period ?>';
            const periodLabel = '<?= ucfirst($period) ?> Report';
            const periodDisplay = '<?= $periodDisplay ?>';
            const adminName = '<?= htmlspecialchars($adminName) ?>';
            
            let html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
            html += '<head><meta charset="UTF-8">';
            html += '<style>';
            html += 'body { font-family: Calibri, Arial, sans-serif; margin: 40px; line-height: 1.6; }';
            html += '.header { text-align: center; border-bottom: 4px solid #006633; padding-bottom: 20px; margin-bottom: 30px; }';
            html += '.header h1 { color: #006633; font-size: 28px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 10px 0; }';
            html += '.header h2 { color: #006633; font-size: 20px; font-weight: bold; margin: 5px 0; }';
            html += '.info-section { margin: 30px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #006633; }';
            html += '.info-row { margin: 8px 0; }';
            html += '.info-label { font-weight: bold; color: #333; display: inline-block; width: 150px; }';
            html += '.info-value { color: #666; }';
            html += '.section-title { background: #006633; color: white; padding: 12px 15px; font-size: 16px; font-weight: bold; margin: 30px 0 15px 0; text-transform: uppercase; }';
            html += 'table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }';
            html += 'th { background: #006633; color: white; padding: 12px 10px; text-align: left; font-weight: bold; border: 1px solid #005522; }';
            html += 'td { border: 1px solid #ddd; padding: 10px; }';
            html += 'tr:nth-child(even) { background: #f9f9f9; }';
            html += 'tr:nth-child(odd) { background: white; }';
            html += '.summary-value { font-weight: bold; color: #006633; font-size: 16px; }';
            html += '.footer { margin-top: 50px; padding-top: 20px; border-top: 2px solid #ddd; text-align: center; color: #999; font-size: 12px; }';
            html += '</style>';
            html += '</head><body>';
            
            // Professional Header
            html += '<div class="header">';
            html += '<h1>EQUIPMENT KIOSK SYSTEM</h1>';
            html += '<h2>' + periodLabel.toUpperCase() + '</h2>';
            html += '</div>';
            
            // Report Information
            html += '<div class="info-section">';
            html += '<div class="info-row"><span class="info-label">Period:</span><span class="info-value">' + periodDisplay + '</span></div>';
            html += '<div class="info-row"><span class="info-label">Prepared by:</span><span class="info-value">' + adminName + '</span></div>';
            html += '<div class="info-row"><span class="info-label">Generated:</span><span class="info-value"><?= date('M j, Y g:i A') ?></span></div>';
            html += '</div>';
            
            // Summary Section
            html += '<div class="section-title">Summary Statistics</div>';
            html += '<table>';
            html += '<tr><th style="width: 60%;">Metric</th><th style="width: 40%;">Value</th></tr>';
            html += '<tr><td>Total Borrowed</td><td class="summary-value"><?= $totals['borrowed_quantity'] ?></td></tr>';
            html += '<tr><td>Total Returned</td><td class="summary-value"><?= $totals['returned_quantity'] ?></td></tr>';
            html += '<tr><td>Damaged Items</td><td class="summary-value"><?= $totals['damaged_returns'] ?></td></tr>';
            html += '<tr><td>Currently Borrowed</td><td class="summary-value"><?= $totals['currently_borrowed'] ?></td></tr>';
            html += '<tr><td>Penalty Records</td><td class="summary-value"><?= $totals['penalty_records'] ?></td></tr>';
            html += '</table>';
            
            // Equipment Breakdown Section
            html += '<div class="section-title">Equipment Breakdown</div>';
            html += '<table>';
            html += '<tr><th>RFID Tag</th><th>Equipment Name</th><th>Borrowed</th><th>Returned</th><th>Damaged</th><th>Current</th><th>Penalties</th></tr>';
            <?php foreach($equipmentSummaryList as $item): ?>
            html += '<tr>';
            html += '<td><?= htmlspecialchars($item['rfid']) ?></td>';
            html += '<td><?= htmlspecialchars($item['equipment_name']) ?></td>';
            html += '<td><?= $item['borrowed_quantity'] ?></td>';
            html += '<td><?= $item['returned_quantity'] ?></td>';
            html += '<td><?= $item['damaged_returns'] ?></td>';
            html += '<td><?= $item['currently_borrowed'] ?></td>';
            html += '<td><?= $item['penalty_records'] ?></td>';
            html += '</tr>';
            <?php endforeach; ?>
            html += '</table>';
            
            // Footer
            html += '<div class="footer">';
            html += '<p>This is an official report generated by the Equipment Kiosk System.</p>';
            html += '<p>© ' + new Date().getFullYear() + ' Equipment Kiosk System. All rights reserved.</p>';
            html += '</div>';
            
            html += '</body></html>';
            
            const blob = new Blob([html], { type: 'application/msword' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const filename = 'Report_' + period + '_' + periodDisplay.replace(/[^a-zA-Z0-9]/g, '_') + '.doc';
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
            
            // Show success notification
            showToast('Word Export Successful', 'Report has been downloaded as ' + filename, 'success');
        }
        
        // Export to Excel (HTML table format)
        function exportToExcel() {
            const period = '<?= $period ?>';
            const periodLabel = '<?= ucfirst($period) ?> Report';
            const periodDisplay = '<?= $periodDisplay ?>';
            const adminName = '<?= htmlspecialchars($adminName) ?>';
            
            let html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
            html += '<head><meta charset="UTF-8">';
            html += '<style>';
            html += 'body { font-family: Arial, sans-serif; margin: 40px; }';
            html += '.header { text-align: center; border-bottom: 3px solid #006633; padding-bottom: 20px; margin-bottom: 30px; }';
            html += '.header h1 { color: #006633; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; margin: 0; }';
            html += '.header h2 { color: #006633; font-size: 18px; margin: 5px 0; }';
            html += '.info-table { margin-bottom: 30px; border: none; }';
            html += '.info-table td { padding: 5px 10px; border: none; }';
            html += '.info-label { font-weight: bold; color: #666; }';
            html += '.section-title { background: #f5f5f5; color: #006633; padding: 12px; font-size: 16px; font-weight: bold; border: 2px solid #e0e0e0; margin-top: 20px; }';
            html += 'table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }';
            html += 'th { background: #006633; color: white; padding: 12px 8px; text-align: left; font-weight: bold; border: 1px solid #005522; }';
            html += 'td { border: 1px solid #ddd; padding: 10px 8px; }';
            html += 'tr:nth-child(even) { background: #f9f9f9; }';
            html += 'tr:nth-child(odd) { background: white; }';
            html += '.summary-value { font-weight: bold; color: #006633; }';
            html += '</style>';
            html += '</head><body>';
            
            // Professional Header
            html += '<div class="header">';
            html += '<h1>EQUIPMENT KIOSK SYSTEM</h1>';
            html += '<h2>' + periodLabel.toUpperCase() + '</h2>';
            html += '</div>';
            
            // Report Information
            html += '<table class="info-table">';
            html += '<tr><td class="info-label">Period:</td><td>' + periodDisplay + '</td></tr>';
            html += '<tr><td class="info-label">Prepared by:</td><td>' + adminName + '</td></tr>';
            html += '<tr><td class="info-label">Generated:</td><td><?= date('M j, Y g:i A') ?></td></tr>';
            html += '</table>';
            
            // Summary Section
            html += '<div class="section-title">SUMMARY STATISTICS</div>';
            html += '<table>';
            html += '<tr><th>Metric</th><th>Value</th></tr>';
            html += '<tr><td>Total Borrowed</td><td class="summary-value"><?= $totals['borrowed_quantity'] ?></td></tr>';
            html += '<tr><td>Total Returned</td><td class="summary-value"><?= $totals['returned_quantity'] ?></td></tr>';
            html += '<tr><td>Damaged Items</td><td class="summary-value"><?= $totals['damaged_returns'] ?></td></tr>';
            html += '<tr><td>Currently Borrowed</td><td class="summary-value"><?= $totals['currently_borrowed'] ?></td></tr>';
            html += '<tr><td>Penalty Records</td><td class="summary-value"><?= $totals['penalty_records'] ?></td></tr>';
            html += '</table>';
            
            // Equipment Breakdown Section
            html += '<div class="section-title">EQUIPMENT BREAKDOWN</div>';
            html += '<table>';
            html += '<tr><th>RFID Tag</th><th>Equipment Name</th><th>Borrowed Qty</th><th>Returned Qty</th><th>Damaged Qty</th><th>Currently Borrowed</th><th>Penalty Records</th></tr>';
            <?php foreach($equipmentSummaryList as $item): ?>
            html += '<tr>';
            html += '<td><?= htmlspecialchars($item['rfid']) ?></td>';
            html += '<td><?= htmlspecialchars($item['equipment_name']) ?></td>';
            html += '<td><?= $item['borrowed_quantity'] ?></td>';
            html += '<td><?= $item['returned_quantity'] ?></td>';
            html += '<td><?= $item['damaged_returns'] ?></td>';
            html += '<td><?= $item['currently_borrowed'] ?></td>';
            html += '<td><?= $item['penalty_records'] ?></td>';
            html += '</tr>';
            <?php endforeach; ?>
            html += '</table>';
            
            html += '</body></html>';
            
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const filename = 'Report_' + period + '_' + periodDisplay.replace(/[^a-zA-Z0-9]/g, '_') + '.xls';
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
            
            // Show success notification
            showToast('Excel Export Successful', 'Report has been downloaded as ' + filename, 'success');
        }
        
        // Extract reports by period
        function extractReport(period) {
            const currentMonth = <?= $month ?>;
            const currentYear = <?= $year ?>;
            const today = new Date();
            const todayStr = today.toISOString().split('T')[0];
            
            let targetUrl = 'reports.php?period=' + period;
            
            switch(period) {
                case 'daily':
                    targetUrl += '&date=' + todayStr;
                    showToast('Daily Report', 'Loading daily report for today...', 'info');
                    setTimeout(() => {
                        window.location.href = targetUrl;
                    }, 500);
                    break;
                    
                case 'weekly':
                    targetUrl += '&date=' + todayStr;
                    showToast('Weekly Report', 'Loading weekly report for current week...', 'info');
                    setTimeout(() => {
                        window.location.href = targetUrl;
                    }, 500);
                    break;
                    
                case 'monthly':
                    targetUrl += '&month=' + currentMonth + '&year=' + currentYear;
                    showToast('Monthly Report', 'Loading monthly report...', 'info');
                    setTimeout(() => {
                        window.location.href = targetUrl;
                    }, 500);
                    break;
                    
                case 'yearly':
                    targetUrl += '&year=' + currentYear;
                    showToast('Yearly Report', 'Loading yearly report...', 'info');
                    setTimeout(() => {
                        window.location.href = targetUrl;
                    }, 500);
                    break;
            }
        }
        
        // Sidebar toggle functionality handled by sidebar component
    </script>
</body>
</html>


