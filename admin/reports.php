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
    $stmt = $conn->prepare("SELECT t.*, 
                                    COALESCE(t.transaction_date, t.created_at) AS txn_datetime,
                                    e.name AS equipment_name,
                                    e.rfid_tag,
                                    u.student_id AS borrower_student_id
                             FROM transactions t
                             LEFT JOIN equipment e ON t.equipment_id = e.id
                             LEFT JOIN users u ON t.user_id = u.id
                             WHERE MONTH(COALESCE(t.transaction_date, t.created_at)) = ?
                               AND YEAR(COALESCE(t.transaction_date, t.created_at)) = ?
                             ORDER BY txn_datetime ASC");
    if ($stmt) {
        $stmt->bind_param('ii', $month, $year);
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
    
    // Count penalty records for each equipment
    $penaltyStmt = $conn->prepare("SELECT e.rfid_tag, COUNT(p.id) as penalty_count
                                     FROM penalties p
                                     INNER JOIN transactions t ON p.transaction_id = t.id
                                     INNER JOIN equipment e ON t.equipment_id = e.id
                                     WHERE MONTH(p.created_at) = ? AND YEAR(p.created_at) = ?
                                     GROUP BY e.rfid_tag");
    if ($penaltyStmt) {
        $penaltyStmt->bind_param('ii', $month, $year);
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
            .no-print { display:none !important; }
            .main-content { margin:0 !important; padding: 20px !important; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .top-header { page-break-after: avoid; }
            .panel { page-break-inside: avoid; }
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
                    <h1 class="page-title" style="margin-bottom:4px;">Monthly Report</h1>
                    <div style="color:#555; font-size:0.95rem;">
                        Period: <strong><?= date('F', mktime(0,0,0,$month,1,$year)) ?> <?= $year ?></strong>
                        &nbsp;•&nbsp; Prepared by: <strong><?= htmlspecialchars($adminName) ?></strong>
                        &nbsp;•&nbsp; Generated: <strong><?= date('M j, Y g:i A') ?></strong>
                    </div>
                </div>
                <div class="no-print" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="add-btn" onclick="window.print()" style="background: #9c27b0;">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button class="add-btn" onclick="exportToCSV()" style="background: #4caf50;">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                    <button class="add-btn" onclick="exportToExcel()" style="background: #2196f3;">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </header>

            <section class="content-section active">
                <div class="section-header no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <h2>Filters & Extract Reports</h2>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button class="extract-btn" onclick="extractReport('daily')" title="Extract Daily Report">
                            <i class="fas fa-calendar-day"></i> Daily
                        </button>
                        <button class="extract-btn" onclick="extractReport('weekly')" title="Extract Weekly Report">
                            <i class="fas fa-calendar-week"></i> Weekly
                        </button>
                        <button class="extract-btn" onclick="extractReport('monthly')" title="Extract Monthly Report">
                            <i class="fas fa-calendar-alt"></i> Monthly
                        </button>
                        <button class="extract-btn" onclick="extractReport('yearly')" title="Extract Yearly Report">
                            <i class="fas fa-calendar"></i> Yearly
                        </button>
                    </div>
                </div>
                <div class="no-print" style="margin-top: 15px;">
                    <form class="filters" method="GET">
                        <select name="month">
                            <?php for($m=1;$m<=12;$m++): ?>
                                <option value="<?= $m ?>" <?= $m===$month? 'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1,$year)) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="year">
                            <?php for($y=date('Y')-4;$y<=date('Y')+1;$y++): ?>
                                <option value="<?= $y ?>" <?= $y===$year? 'selected':'' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <button class="add-btn" type="submit"><i class="fas fa-filter"></i> Apply</button>
                    </form>
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
        
        // Export to CSV
        function exportToCSV() {
            const month = <?= $month ?>;
            const year = <?= $year ?>;
            const monthName = '<?= date('F', mktime(0,0,0,$month,1,$year)) ?>';
            
            let csv = 'Equipment Kiosk System - Monthly Report\\n';
            csv += 'Period: ' + monthName + ' ' + year + '\\n';
            csv += 'Generated: <?= date('M j, Y g:i A') ?>\\n\\n';
            
            // Summary
            csv += 'SUMMARY\\n';
            csv += 'Total Borrowed,<?= $totals['borrowed_quantity'] ?>\\n';
            csv += 'Total Returned,<?= $totals['returned_quantity'] ?>\\n';
            csv += 'Damaged Items,<?= $totals['damaged_returns'] ?>\\n';
            csv += 'Currently Borrowed,<?= $totals['currently_borrowed'] ?>\\n';
            csv += 'Penalty Records,<?= $totals['penalty_records'] ?>\\n\\n';
            
            // Equipment Summary
            csv += 'EQUIPMENT SUMMARY\\n';
            csv += 'RFID Tag,Equipment,Borrowed Qty,Returned Qty,Damaged Qty,Currently Borrowed,Penalty Records\\n';
            <?php foreach($equipmentSummaryList as $item): ?>
            csv += '<?= addslashes($item['rfid']) ?>,<?= addslashes($item['equipment_name']) ?>,<?= $item['borrowed_quantity'] ?>,<?= $item['returned_quantity'] ?>,<?= $item['damaged_returns'] ?>,<?= $item['currently_borrowed'] ?>,<?= $item['penalty_records'] ?>\\n';
            <?php endforeach; ?>
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Report_' + monthName + '_' + year + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
            
            // Show success notification
            showToast('CSV Export Successful', 'Report has been downloaded as ' + monthName + '_' + year + '.csv', 'success');
        }
        
        // Export to Excel (HTML table format)
        function exportToExcel() {
            const month = <?= $month ?>;
            const year = <?= $year ?>;
            const monthName = '<?= date('F', mktime(0,0,0,$month,1,$year)) ?>';
            
            let html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
            html += '<head><meta charset="UTF-8"><style>table {border-collapse: collapse;} th, td {border: 1px solid #ddd; padding: 8px;} th {background: #006633; color: white;}</style></head>';
            html += '<body>';
            html += '<h1>Equipment Kiosk System - Monthly Report</h1>';
            html += '<p><strong>Period:</strong> ' + monthName + ' ' + year + '</p>';
            html += '<p><strong>Generated:</strong> <?= date('M j, Y g:i A') ?></p>';
            
            // Summary
            html += '<h2>Summary</h2>';
            html += '<table><tr><th>Metric</th><th>Value</th></tr>';
            html += '<tr><td>Total Borrowed</td><td><?= $totals['borrowed_quantity'] ?></td></tr>';
            html += '<tr><td>Total Returned</td><td><?= $totals['returned_quantity'] ?></td></tr>';
            html += '<tr><td>Damaged Items</td><td><?= $totals['damaged_returns'] ?></td></tr>';
            html += '<tr><td>Currently Borrowed</td><td><?= $totals['currently_borrowed'] ?></td></tr>';
            html += '<tr><td>Penalty Records</td><td><?= $totals['penalty_records'] ?></td></tr>';
            html += '</table><br>';
            
            // Equipment Summary
            html += '<h2>Equipment Summary</h2>';
            html += '<table><tr><th>RFID Tag</th><th>Equipment</th><th>Borrowed Qty</th><th>Returned Qty</th><th>Damaged Qty</th><th>Currently Borrowed</th><th>Penalty Records</th></tr>';
            <?php foreach($equipmentSummaryList as $item): ?>
            html += '<tr><td><?= htmlspecialchars($item['rfid']) ?></td><td><?= htmlspecialchars($item['equipment_name']) ?></td><td><?= $item['borrowed_quantity'] ?></td><td><?= $item['returned_quantity'] ?></td><td><?= $item['damaged_returns'] ?></td><td><?= $item['currently_borrowed'] ?></td><td><?= $item['penalty_records'] ?></td></tr>';
            <?php endforeach; ?>
            html += '</table>';
            
            html += '</body></html>';
            
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Report_' + monthName + '_' + year + '.xls';
            a.click();
            window.URL.revokeObjectURL(url);
            
            // Show success notification
            showToast('Excel Export Successful', 'Report has been downloaded as ' + monthName + '_' + year + '.xls', 'success');
        }
        
        // Extract reports by period
        function extractReport(period) {
            const currentMonth = <?= $month ?>;
            const currentYear = <?= $year ?>;
            const today = new Date();
            
            let targetUrl = 'reports.php?';
            
            switch(period) {
                case 'daily':
                    // Today's report
                    showToast('Daily Report', "Showing today's transactions. Use the month/year filter and then Print or Export.", 'info');
                    setTimeout(() => window.print(), 500);
                    break;
                    
                case 'weekly':
                    // Current week
                    showToast('Weekly Report', "Showing this week's transactions. Use the month/year filter for the current period.", 'info');
                    setTimeout(() => window.print(), 500);
                    break;
                    
                case 'monthly':
                    // Current month (already displayed)
                    showToast('Monthly Report', 'Currently displayed. Use Print or Export buttons above to download.', 'info');
                    setTimeout(() => window.print(), 500);
                    break;
                    
                case 'yearly':
                    // Redirect to yearly view
                    showToast('Yearly Report', 'Generating full year report. This may take a moment...', 'info');
                    setTimeout(() => {
                        window.location.href = 'reports_yearly.php?year=' + currentYear;
                    }, 1000);
                    break;
            }
        }
        
        // Sidebar toggle functionality handled by sidebar component
    </script>
</body>
</html>


