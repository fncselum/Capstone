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
if (!$conn->connect_error) {
    $sql = "SELECT t.*, e.name AS equipment_name FROM transactions t\n"
         . "JOIN equipment e ON t.equipment_id = e.id\n"
         . "WHERE MONTH(t.transaction_date) = $month AND YEAR(t.transaction_date) = $year\n"
         . "ORDER BY t.transaction_date ASC";
    if ($rs = $conn->query($sql)) {
        while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
        $rs->free();
    }
}
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
        }
        .filters { display:flex; align-items:center; gap:10px; }
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

                <div class="panel">
                    <?php if(empty($rows)): ?>
                        <p>No transactions for this period.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>RFID</th>
                                    <th>Equipment</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($rows as $r): ?>
                                    <tr>
                                        <td><?= date('M j, Y g:i A', strtotime($r['transaction_date'])) ?></td>
                                        <td><?= htmlspecialchars($r['rfid_id']) ?></td>
                                        <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                                        <td><span class="badge <?= strtolower($r['type'])==='borrow' ? 'borrow':'return' ?>"><?= htmlspecialchars($r['type']) ?></span></td>
                                        <td><?= htmlspecialchars($r['status'] ?? '') ?></td>
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


