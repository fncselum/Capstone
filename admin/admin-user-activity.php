<?php
// User Activity - RFID borrow/return leaderboard
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Simple authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // If not logged in, redirect to login
    header('Location: login.php');
    exit;
}

// Regenerate session ID for security
if (!isset($_SESSION['admin_initialized'])) {
    session_regenerate_id(true);
    $_SESSION['admin_initialized'] = true;
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Database connection
$host = "localhost";
$user = "root";
$password = ""; // XAMPP default: empty password for root
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
$db_error = $conn->connect_error ? $conn->connect_error : null;

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Verify transactions table exists
$has_transactions = false;
if (!$db_error) {
    $check = $conn->query("SHOW TABLES LIKE 'transactions'");
    $has_transactions = ($check && $check->num_rows > 0);
}

// Fetch leaderboard
$rows = [];
if ($has_transactions) {
    $where = '';
    if ($q !== '') {
        $safe = $conn->real_escape_string($q);
        $where = "WHERE t.rfid_id LIKE '%$safe%'";
    }

    $sql =
        "SELECT t.rfid_id,\n" .
        "       SUM(CASE WHEN t.type='Borrow' THEN 1 ELSE 0 END) AS total_borrows,\n" .
        "       SUM(CASE WHEN t.type='Return' THEN 1 ELSE 0 END) AS total_returns,\n" .
        "       MAX(t.transaction_date) AS last_activity\n" .
        "FROM transactions t\n" .
        ($where !== '' ? $where . "\n" : '') .
        "GROUP BY t.rfid_id\n" .
        "ORDER BY total_borrows DESC, total_returns DESC, last_activity DESC\n" .
        "LIMIT 500"; // prevent unbounded output

    $rs = $conn->query($sql);
    if ($rs) {
        while ($r = $rs->fetch_assoc()) {
            $rows[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>User Activity - Equipment Kiosk Admin</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .leaderboard-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; }
        .leaderboard-title { font-size: 1.6rem; color:#006633; font-weight: 800; display:flex; align-items:center; gap:10px; }
        .search-box { display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #e0e0e0; border-radius:10px; padding:8px 12px; min-width:260px; }
        .search-box input { border:none; outline:none; font-size:14px; width: 220px; }
        .search-button { background:#006633; color:white; border:none; padding:6px 14px; border-radius:8px; cursor:pointer; font-weight:600; transition:all 0.3s ease; }
        .search-button:hover { background:#004d26; }
        .leaderboard-table { width:100%; border-collapse: collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .leaderboard-table th, .leaderboard-table td { padding:14px 16px; border-bottom:1px solid #eef2ef; text-align:left; }
        .leaderboard-table th { background:#f3fbf6; color:#006633; font-weight:700; font-size:0.95rem; }
        .rfid-pill { display:inline-flex; align-items:center; gap:8px; background:#eef7f2; color:#2f7d56; border:1px solid #cfe8da; padding:6px 10px; border-radius:999px; font-weight:700; }
        .count-pill { display:inline-flex; align-items:center; gap:6px; background:#f7f9ff; color:#2859a4; border:1px solid #d7e3ff; padding:6px 10px; border-radius:999px; font-weight:700; }
        .count-pill.return { background:#fff8f0; color:#9a5b00; border-color:#ffe3c4; }
        .last-activity { color:#555; font-size:0.9rem; }
        .empty { text-align:center; padding:40px; color:#6c757d; }
        .bar-wrap { background:#f1f5f3; border-radius:8px; height:8px; width:160px; overflow:hidden; }
        .bar-fill { background:#1fb978; height:100%; width:0; transition:width .6s ease; }
        .toolbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .panel { background:#fff; border-radius:12px; padding:20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main -->
        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">User Activity</h1>
            </header>
            <section class="content-section active">
                <div class="leaderboard-header">
                    <div class="toolbar">
                        <div class="leaderboard-title"><i class="fas fa-users"></i> Borrower of the Month</div>
                    </div>
                    <form method="GET" class="search-box" action="admin-user-activity.php">
                        <i class="fas fa-search" style="color:#7aa893;"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search RFID..." autocomplete="off">
                        <button type="submit" class="search-button">Search</button>
                    </form>
                </div>

                <div class="panel">
                <?php if ($db_error): ?>
                    <div class="empty">Database error: <?= htmlspecialchars($db_error) ?></div>
                <?php elseif (!$has_transactions): ?>
                    <div class="empty">No transaction data yet.</div>
                <?php elseif (empty($rows)): ?>
                    <div class="empty">No matching RFID records.</div>
                <?php else: ?>
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th style="width: 40%;">RFID</th>
                                <th style="width: 15%;">Borrows</th>
                                <th style="width: 15%;">Returns</th>
                                <th style="width: 20%;">Activity</th>
                                <th style="width: 10%;">Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $max_borrows = 0;
                            foreach ($rows as $r) { $max_borrows = max($max_borrows, (int)$r['total_borrows']); }
                            foreach ($rows as $r):
                                $borrows = (int)$r['total_borrows'];
                                $returns = (int)$r['total_returns'];
                                $pct = $max_borrows > 0 ? round(($borrows / $max_borrows) * 100) : 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="rfid-pill"><i class="fas fa-id-card"></i> <?= htmlspecialchars($r['rfid_id']) ?></span>
                                </td>
                                <td>
                                    <span class="count-pill"><i class="fas fa-arrow-up"></i> <?= $borrows ?></span>
                                </td>
                                <td>
                                    <span class="count-pill return"><i class="fas fa-arrow-down"></i> <?= $returns ?></span>
                                </td>
                                <td class="last-activity">
                                    <?= $r['last_activity'] ? date('M j, Y g:i A', strtotime($r['last_activity'])) : '—' ?>
                                </td>
                                <td>
                                    <div class="bar-wrap"><div class="bar-fill" style="width: <?= $pct ?>%"></div></div>
                                </td>
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
        
        // Sidebar toggle functionality handled by sidebar component
        
        // Animate bars on load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.bar-fill').forEach(function(el){
                const w = el.style.width;
                el.style.width = '0%';
                setTimeout(function(){ el.style.width = w; }, 50);
            });
        });
    </script>
</body>
</html>
