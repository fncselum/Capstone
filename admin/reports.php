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
    'penalty_total' => 0,
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
                    'penalty_total' => 0,
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

            $penalty = (float)($r['penalty_applied'] ?? 0);
            if ($penalty > 0) {
                $totals['penalty_total'] += $penalty;
                $equipmentSummary[$rfid]['penalty_total'] += $penalty;
            }
        }
        $stmt->close();
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
            .main-content { margin:0 !important; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .filters { display:flex; align-items:center; gap:10px; }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            border: 1px solid #e8f3ee;
        }
        .summary-card h3 {
            font-size: 0.95rem;
            color: #5f7c6e;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .summary-card strong {
            font-size: 1.6rem;
            color: #0f5132;
        }
        .panel {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            border: 1px solid #e0ece6;
        }
        .panel h3 {
            margin-bottom: 16px;
            font-size: 1.1rem;
            color: #0f5132;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #f0f5f3;
            text-align: left;
            font-size: 0.95rem;
        }
        th {
            background: #f3fbf6;
            color: #006633;
            font-weight: 600;
        }
        tr:hover { background: #f9fcfb; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge.borrow { background: #e3f2fd; color: #0d47a1; }
        .badge.return { background: #e8f5e9; color: #1b5e20; }
        .badge.damaged { background: #ffebee; color: #b71c1c; }
        .badge.neutral { background: #f0f0f0; color: #555; }
        .empty-state {
            padding: 24px;
            text-align: center;
            color: #556b66;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../uploads/De lasalle ASMC.png" alt="De La Salle ASMC Logo" class="main-logo" style="height:30px; width:auto;">
                    <span class="logo-text">Admin Panel</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="admin-dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-equipment-inventory.php"><i class="fas fa-boxes"></i><span>Equipment Inventory</span></a>
                </li>
                <li class="nav-item active">
                    <a href="reports.php"><i class="fas fa-file-alt"></i><span>Reports</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-all-transaction.php"><i class="fas fa-exchange-alt"></i><span>All Transactions</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-user-activity.php"><i class="fas fa-users"></i><span>User Activity</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-penalty-guideline.php"><i class="fas fa-exclamation-triangle"></i><span>Penalty Guidelines</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-penalty-management.php"><i class="fas fa-gavel"></i><span>Penalty Management</span></a>
                </li>
            </ul>
            <div class="sidebar-footer no-print">
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </button>
            </div>
        </nav>

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
                <div class="no-print">
                    <button class="add-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                </div>
            </header>

            <section class="content-section active">
                <div class="section-header no-print">
                    <h2>Filters</h2>
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
                        <div style="color:#6c8577; font-size:0.85rem;">Across <?= number_format($totals['borrowed_transactions']) ?> borrow transactions</div>
                    </div>
                    <div class="summary-card">
                        <h3><i class="fas fa-arrow-circle-up"></i> Total Returned</h3>
                        <strong><?= number_format($totals['returned_quantity']) ?></strong>
                        <div style="color:#6c8577; font-size:0.85rem;">Across <?= number_format($totals['returned_transactions']) ?> return transactions</div>
                    </div>
                    <div class="summary-card">
                        <h3><i class="fas fa-exclamation-triangle"></i> Damaged Items</h3>
                        <strong><?= number_format($totals['damaged_returns']) ?></strong>
                        <div style="color:#b53d3d; font-size:0.85rem;">Reported upon return</div>
                    </div>
                    <div class="summary-card">
                        <h3><i class="fas fa-hand-holding"></i> Currently Borrowed</h3>
                        <strong><?= number_format($totals['currently_borrowed']) ?></strong>
                        <div style="color:#6c8577; font-size:0.85rem;">Items not yet returned</div>
                    </div>
                    <div class="summary-card">
                        <h3><i class="fas fa-coins"></i> Penalty Points</h3>
                        <strong><?= number_format($totals['penalty_total']) ?></strong>
                        <div style="color:#6c8577; font-size:0.85rem;">Recorded this period</div>
                    </div>
                </div>

                <div class="panel">
                    <h3>Equipment Summary (RFID)</h3>
                    <?php if(empty($equipmentSummaryList)): ?>
                        <div class="empty-state">No equipment activity for this period.</div>
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
                                    <th>Penalty Points</th>
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
                                        <td><?= number_format($item['penalty_total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h3>Detailed Transactions</h3>
                    <?php if(empty($rows)): ?>
                        <div class="empty-state">No transactions for this period.</div>
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
                                    <th>Penalty Points</th>
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
                                        $penaltyAmount = (float)($r['penalty_applied'] ?? 0);
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
                                        <td><?= $penaltyAmount > 0 ? number_format($penaltyAmount) : '—' ?></td>
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
        
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const adminContainer = document.querySelector('.admin-container');
            
            if (sidebarToggle && sidebar && adminContainer) {
                sidebarToggle.addEventListener('click', function() {
                    const isHidden = sidebar.classList.toggle('hidden');
                    adminContainer.classList.toggle('sidebar-hidden', isHidden);
                });
            }
        });
    </script>
</body>
</html>


