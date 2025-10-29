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

date_default_timezone_set('Asia/Manila');

// Filters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_period = isset($_GET['period']) ? $_GET['period'] : 'all';
$selected_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Pagination
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Build WHERE clause for period filter
$period_where = '';
if ($filter_period === 'today') {
    $period_where = "AND DATE(t.transaction_date) = CURDATE()";
} elseif ($filter_period === 'week') {
    $period_where = "AND t.transaction_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter_period === 'month') {
    $period_where = "AND t.transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// Fetch user statistics
$user_stats = [];
$stats_query = "SELECT 
                    u.id,
                    u.student_id,
                    u.rfid_tag,
                    u.status,
                    u.penalty_points,
                    COUNT(DISTINCT t.id) as total_transactions,
                    SUM(CASE WHEN t.transaction_type = 'Borrow' THEN 1 ELSE 0 END) as total_borrows,
                    SUM(CASE WHEN t.transaction_type = 'Return' THEN 1 ELSE 0 END) as total_returns,
                    SUM(CASE WHEN t.status = 'Active' THEN 1 ELSE 0 END) as active_borrows,
                    MAX(t.transaction_date) as last_activity,
                    COUNT(DISTINCT p.id) as penalty_count
                FROM users u
                LEFT JOIN transactions t ON u.id = t.user_id $period_where
                LEFT JOIN penalties p ON u.id = p.user_id
                WHERE 1=1";

if (!empty($search_query)) {
    $search_escaped = $conn->real_escape_string($search_query);
    $stats_query .= " AND (u.student_id LIKE '%$search_escaped%' OR u.rfid_tag LIKE '%$search_escaped%')";
}

if ($filter_status !== 'all') {
    $status_escaped = $conn->real_escape_string($filter_status);
    $stats_query .= " AND u.status = '$status_escaped'";
}

$stats_query .= " GROUP BY u.id
                  ORDER BY total_transactions DESC, last_activity DESC
                  LIMIT $offset, $records_per_page";

$result = $conn->query($stats_query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $user_stats[] = $row;
    }
}

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT u.id) as total FROM users u WHERE 1=1";
if (!empty($search_query)) {
    $count_query .= " AND (u.student_id LIKE '%$search_escaped%' OR u.rfid_tag LIKE '%$search_escaped%')";
}
if ($filter_status !== 'all') {
    $count_query .= " AND u.status = '$status_escaped'";
}
$count_result = $conn->query($count_query);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $records_per_page);

// Fetch detailed user activity if user selected
$user_details = null;
$user_transactions = [];
if ($selected_user > 0) {
    // Get user details
    $user_query = "SELECT u.*, 
                          COUNT(DISTINCT t.id) as total_transactions,
                          SUM(CASE WHEN t.transaction_type = 'Borrow' THEN 1 ELSE 0 END) as total_borrows,
                          SUM(CASE WHEN t.transaction_type = 'Return' THEN 1 ELSE 0 END) as total_returns,
                          SUM(CASE WHEN t.status = 'Active' THEN 1 ELSE 0 END) as active_borrows,
                          COUNT(DISTINCT p.id) as penalty_count
                   FROM users u
                   LEFT JOIN transactions t ON u.id = t.user_id
                   LEFT JOIN penalties p ON u.id = p.user_id
                   WHERE u.id = $selected_user
                   GROUP BY u.id";
    $user_result = $conn->query($user_query);
    if ($user_result) {
        $user_details = $user_result->fetch_assoc();
    }
    
    // Get user transactions
    $trans_query = "SELECT t.*, 
                           e.name as equipment_name,
                           e.rfid_tag as equipment_rfid,
                           c.name as category_name
                    FROM transactions t
                    LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
                    LEFT JOIN categories c ON e.category_id = c.id
                    WHERE t.user_id = $selected_user
                    ORDER BY t.transaction_date DESC
                    LIMIT 50";
    $trans_result = $conn->query($trans_query);
    if ($trans_result) {
        while ($row = $trans_result->fetch_assoc()) {
            $user_transactions[] = $row;
        }
    }
}

// Overall statistics
$overall_stats = [];
$overall_query = "SELECT 
                    COUNT(DISTINCT u.id) as total_users,
                    COUNT(DISTINCT CASE WHEN u.status = 'Active' THEN u.id END) as active_users,
                    COUNT(DISTINCT t.id) as total_transactions,
                    SUM(CASE WHEN t.transaction_type = 'Borrow' THEN 1 ELSE 0 END) as total_borrows,
                    SUM(CASE WHEN t.transaction_type = 'Return' THEN 1 ELSE 0 END) as total_returns,
                    SUM(CASE WHEN t.status = 'Active' THEN 1 ELSE 0 END) as currently_borrowed
                  FROM users u
                  LEFT JOIN transactions t ON u.id = t.user_id";
$overall_result = $conn->query($overall_query);
if ($overall_result) {
    $overall_stats = $overall_result->fetch_assoc();
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
        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fdf9 100%);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 2px solid #e8f3ee;
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #006633 0%, #00994d 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,102,51,0.15);
        }
        
        .stat-card h3 {
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
        
        .stat-card h3 i {
            font-size: 1.2rem;
            color: #006633;
        }
        
        .stat-card strong {
            font-size: 2.2rem;
            color: #006633;
            display: block;
            font-weight: 700;
        }
        
        /* Filters */
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        
        .filter-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #006633;
        }
        
        .filter-btn {
            padding: 10px 20px;
            background: #006633;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            background: #004d26;
            transform: translateY(-2px);
        }
        
        .clear-btn {
            padding: 10px 20px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .clear-btn:hover {
            background: #d32f2f;
        }
        
        /* User Table */
        .panel {
            background: #fff;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .user-table thead {
            background: linear-gradient(135deg, #f3fbf6 0%, #e8f5ee 100%);
        }
        
        .user-table th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 700;
            color: #006633;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #006633;
        }
        
        .user-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f5f3;
            font-size: 0.9rem;
        }
        
        .user-table tbody tr {
            transition: background-color 0.2s ease;
        }
        
        .user-table tbody tr:hover {
            background: #f9fcfb;
        }
        
        .user-table tbody tr:nth-child(even) {
            background: #fafcfb;
        }
        
        .user-table tbody tr:nth-child(even):hover {
            background: #f5f9f7;
        }
        
        /* Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .status-badge.active {
            background: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #81c784;
        }
        
        .status-badge.inactive {
            background: #f5f5f5;
            color: #666;
            border: 1px solid #ddd;
        }
        
        .status-badge.suspended {
            background: #ffebee;
            color: #b71c1c;
            border: 1px solid #ef9a9a;
        }
        
        .count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .count-badge.borrow {
            background: #e3f2fd;
            color: #0d47a1;
            border: 1px solid #90caf9;
        }
        
        .count-badge.return {
            background: #e8f5e9;
            color: #1b5e20;
            border: 1px solid #81c784;
        }
        
        .count-badge.penalty {
            background: #ffebee;
            color: #b71c1c;
            border: 1px solid #ef9a9a;
        }
        
        .view-btn {
            padding: 8px 16px;
            background: #9c27b0;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .view-btn:hover {
            background: #7b1fa2;
            transform: scale(1.05);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-btn {
            padding: 10px 16px;
            background: white;
            color: #006633;
            text-decoration: none;
            border: 1px solid #006633;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .page-btn:hover {
            background: #006633;
            color: white;
        }
        
        .page-number {
            padding: 10px 14px;
            background: white;
            color: #333;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .page-number:hover {
            background: #f5f5f5;
            border-color: #006633;
        }
        
        .page-number.active {
            background: #006633;
            color: white;
            border-color: #006633;
        }
        
        /* User Details Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            overflow-y: auto;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 2px solid #e8f3ee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            color: #006633;
            font-weight: 700;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #999;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            padding: 16px;
            background: #f9fcfb;
            border-radius: 10px;
            border-left: 4px solid #006633;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 1.1rem;
            color: #333;
            font-weight: 600;
        }
        
        .empty {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty i {
            font-size: 3rem;
            color: #c5d9ce;
            margin-bottom: 16px;
            display: block;
        }
        
        /* Modal scrolling */
        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-body {
            max-height: calc(90vh - 100px);
            overflow-y: auto;
        }
        
        /* Scrollbar styling */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: #006633;
            border-radius: 4px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #004d26;
        }
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
            <!-- Overall Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-users"></i> Total Users</h3>
                    <strong><?= number_format($overall_stats['total_users'] ?? 0) ?></strong>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-user-check"></i> Active Users</h3>
                    <strong><?= number_format($overall_stats['active_users'] ?? 0) ?></strong>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-exchange-alt"></i> Total Transactions</h3>
                    <strong><?= number_format($overall_stats['total_transactions'] ?? 0) ?></strong>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-arrow-right"></i> Total Borrows</h3>
                    <strong><?= number_format($overall_stats['total_borrows'] ?? 0) ?></strong>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-arrow-left"></i> Total Returns</h3>
                    <strong><?= number_format($overall_stats['total_returns'] ?? 0) ?></strong>
                </div>
                <div class="stat-card">
                    <h3><i class="fas fa-hand-holding"></i> Currently Borrowed</h3>
                    <strong><?= number_format($overall_stats['currently_borrowed'] ?? 0) ?></strong>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" name="search" id="search" placeholder="Student ID or RFID..." value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select name="status" id="status">
                                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="Active" <?= $filter_status === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $filter_status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="Suspended" <?= $filter_status === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="period">Period</label>
                            <select name="period" id="period">
                                <option value="all" <?= $filter_period === 'all' ? 'selected' : '' ?>>All Time</option>
                                <option value="today" <?= $filter_period === 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="week" <?= $filter_period === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                                <option value="month" <?= $filter_period === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="filter-btn">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                        <?php if (!empty($search_query) || $filter_status !== 'all' || $filter_period !== 'all'): ?>
                            <div class="filter-group">
                                <label>&nbsp;</label>
                                <a href="admin-user-activity.php" class="clear-btn">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- User Activity Table -->
            <div class="panel">
                <h3 style="margin-bottom: 20px; color: #006633; font-weight: 700;">
                    <i class="fas fa-history"></i> User Activity
                </h3>
                
                <?php if (empty($user_stats)): ?>
                    <div class="empty">
                        <i class="fas fa-inbox"></i>
                        No users found
                    </div>
                <?php else: ?>
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>RFID Tag</th>
                                <th>Status</th>
                                <th>Transactions</th>
                                <th>Borrows</th>
                                <th>Returns</th>
                                <th>Active</th>
                                <th>Penalties</th>
                                <th>Last Activity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_stats as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['student_id']) ?></td>
                                    <td><?= htmlspecialchars($user['rfid_tag']) ?></td>
                                    <td>
                                        <span class="status-badge <?= strtolower($user['status']) ?>">
                                            <?= htmlspecialchars($user['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($user['total_transactions']) ?></td>
                                    <td>
                                        <span class="count-badge borrow">
                                            <i class="fas fa-arrow-right"></i> <?= number_format($user['total_borrows']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="count-badge return">
                                            <i class="fas fa-arrow-left"></i> <?= number_format($user['total_returns']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($user['active_borrows']) ?></td>
                                    <td>
                                        <?php if ($user['penalty_count'] > 0): ?>
                                            <span class="count-badge penalty">
                                                <i class="fas fa-exclamation-triangle"></i> <?= number_format($user['penalty_count']) ?>
                                            </span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $user['last_activity'] ? date('M j, Y H:i', strtotime($user['last_activity'])) : 'No activity' ?>
                                    </td>
                                    <td>
                                        <a href="?user_id=<?= $user['id'] ?>" class="view-btn">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_query) ?>&status=<?= $filter_status ?>&period=<?= $filter_period ?>" class="page-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&status=<?= $filter_status ?>&period=<?= $filter_period ?>" 
                                   class="page-number <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_query) ?>&status=<?= $filter_status ?>&period=<?= $filter_period ?>" class="page-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- User Details Modal -->
            <?php if ($user_details): ?>
            <div class="modal show" id="userModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-user-circle"></i> User Details - <?= htmlspecialchars($user_details['student_id']) ?></h2>
                        <a href="admin-user-activity.php" class="modal-close">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <div class="modal-body">
                        <!-- User Information Grid -->
                        <div class="user-info-grid">
                            <div class="info-item">
                                <div class="info-label">Student ID</div>
                                <div class="info-value"><?= htmlspecialchars($user_details['student_id']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">RFID Tag</div>
                                <div class="info-value"><?= htmlspecialchars($user_details['rfid_tag']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status</div>
                                <div class="info-value">
                                    <span class="status-badge <?= strtolower($user_details['status']) ?>">
                                        <?= htmlspecialchars($user_details['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Total Transactions</div>
                                <div class="info-value"><?= number_format($user_details['total_transactions']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Total Borrows</div>
                                <div class="info-value">
                                    <span class="count-badge borrow">
                                        <i class="fas fa-arrow-right"></i> <?= number_format($user_details['total_borrows']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Total Returns</div>
                                <div class="info-value">
                                    <span class="count-badge return">
                                        <i class="fas fa-arrow-left"></i> <?= number_format($user_details['total_returns']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Active Borrows</div>
                                <div class="info-value"><?= number_format($user_details['active_borrows']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Penalty Count</div>
                                <div class="info-value">
                                    <?php if ($user_details['penalty_count'] > 0): ?>
                                        <span class="count-badge penalty">
                                            <i class="fas fa-exclamation-triangle"></i> <?= number_format($user_details['penalty_count']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #4caf50;">No Penalties</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Penalty Points</div>
                                <div class="info-value"><?= number_format($user_details['penalty_points']) ?></div>
                            </div>
                        </div>

                        <!-- Transaction History -->
                        <h3 style="margin: 30px 0 20px; color: #006633; font-weight: 700;">
                            <i class="fas fa-history"></i> Recent Transaction History
                        </h3>

                        <?php if (empty($user_transactions)): ?>
                            <div class="empty">
                                <i class="fas fa-inbox"></i>
                                No transactions found
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="user-table">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Type</th>
                                            <th>Equipment</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_transactions as $trans): ?>
                                            <tr>
                                                <td><?= date('M j, Y H:i', strtotime($trans['transaction_date'])) ?></td>
                                                <td>
                                                    <?php if ($trans['transaction_type'] === 'Borrow'): ?>
                                                        <span class="count-badge borrow">
                                                            <i class="fas fa-arrow-right"></i> Borrow
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="count-badge return">
                                                            <i class="fas fa-arrow-left"></i> Return
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($trans['equipment_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($trans['category_name'] ?? 'N/A') ?></td>
                                                <td><?= number_format($trans['quantity']) ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = strtolower($trans['status']);
                                                    if ($status_class === 'active') {
                                                        echo '<span class="status-badge active">Active</span>';
                                                    } elseif ($status_class === 'returned') {
                                                        echo '<span class="status-badge inactive">Returned</span>';
                                                    } else {
                                                        echo '<span class="status-badge">' . htmlspecialchars($trans['status']) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }
        
        // Close modal when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('userModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        window.location.href = 'admin-user-activity.php';
                    }
                });
            }
        });
        
        // Sidebar toggle functionality handled by sidebar component
    </script>
</body>
</html>
