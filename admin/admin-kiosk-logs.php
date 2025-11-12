<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Simple authentication check
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

$conn->select_db($dbname);

// Pagination settings
$records_per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = [];

if ($filter_type !== 'all') {
    $where_conditions[] = "t.transaction_type = '" . $conn->real_escape_string($filter_type) . "'";
}

if ($filter_status !== 'all') {
    $where_conditions[] = "t.status = '" . $conn->real_escape_string($filter_status) . "'";
}

if (!empty($filter_date)) {
    $where_conditions[] = "DATE(t.transaction_date) = '" . $conn->real_escape_string($filter_date) . "'";
}

if (!empty($search_query)) {
    $search_escaped = $conn->real_escape_string($search_query);
    $where_conditions[] = "(u.student_id LIKE '%$search_escaped%' OR e.name LIKE '%$search_escaped%' OR t.notes LIKE '%$search_escaped%')";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
                $where_clause";
$count_result = $conn->query($count_query);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $records_per_page);

// Fetch kiosk logs with all details
$logs_query = "SELECT 
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
                u.rfid_tag as user_rfid,
                u.status as user_status,
                e.name as equipment_name,
                e.rfid_tag as equipment_rfid,
                c.name as category_name,
                a.username as approved_by_name
              FROM transactions t
              LEFT JOIN users u ON t.user_id = u.id
              LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
              LEFT JOIN categories c ON e.category_id = c.id
              LEFT JOIN admin_users a ON t.approved_by = a.id
              $where_clause
              ORDER BY t.transaction_date DESC
              LIMIT $offset, $records_per_page";

$logs_result = $conn->query($logs_query);
$logs = [];
if ($logs_result) {
    while ($row = $logs_result->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Get summary statistics
$stats = [];

// Total logs
$result = $conn->query("SELECT COUNT(*) as count FROM transactions");
$stats['total_logs'] = $result ? $result->fetch_assoc()['count'] : 0;

// Today's logs
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE DATE(transaction_date) = '$today'");
$stats['today_logs'] = $result ? $result->fetch_assoc()['count'] : 0;

// Borrow logs
$result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_type = 'Borrow'");
$stats['borrow_logs'] = $result ? $result->fetch_assoc()['count'] : 0;

// Return logs
$result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE transaction_type = 'Return'");
$stats['return_logs'] = $result ? $result->fetch_assoc()['count'] : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Logs - Equipment System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .admin-container.sidebar-hidden .main-content {
            margin-left: 0;
        }

        .top-header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8rem;
            color: #333;
            margin: 0;
        }

        .content-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .section-header {
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 1.3rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            font-size: 1.3rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        /* Filter Form */
        .filter-form {
            margin-top: 20px;
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
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group.search-group {
            flex: 2;
            min-width: 250px;
        }

        .filter-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #333;
        }

        .filter-group select,
        .filter-group input[type="date"],
        .filter-group input[type="text"] {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #9c27b0;
        }

        .search-input-wrapper {
            display: flex;
            gap: 8px;
        }

        .search-input-wrapper input {
            flex: 1;
        }

        .search-btn {
            padding: 10px 20px;
            background: #9c27b0;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .search-btn:hover {
            background: #7b1fa2;
        }

        .clear-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            background: #f44336;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .clear-btn:hover {
            background: #d32f2f;
        }

        .export-btn {
            padding: 8px 16px;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .results-info {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }

        /* Logs Table */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .logs-table thead {
            background: #f5f5f5;
        }

        .logs-table th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 0.85rem;
            border-bottom: 2px solid #e0e0e0;
            white-space: nowrap;
        }

        .logs-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
        }

        .logs-table tbody tr:hover {
            background: #f9f9f9;
        }

        .log-id {
            font-weight: 600;
            color: #9c27b0;
        }

        .log-datetime {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .log-date {
            font-weight: 500;
            color: #333;
        }

        .log-time {
            font-size: 0.8rem;
            color: #666;
        }

        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .type-badge.borrow {
            background: #e3f2fd;
            color: #2196f3;
        }

        .type-badge.return {
            background: #e8f5e9;
            color: #4caf50;
        }

        .student-cell {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #333;
        }

        .student-cell i {
            color: #9c27b0;
        }

        .equipment-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .rfid-tag {
            font-size: 0.75rem;
            color: #999;
        }

        .category-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #f5f5f5;
            border-radius: 4px;
            font-size: 0.75rem;
            color: #666;
            white-space: nowrap;
        }

        .quantity {
            font-weight: 600;
            color: #333;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-badge.active {
            background: #fff3e0;
            color: #ff9800;
        }

        .status-badge.returned {
            background: #e8f5e9;
            color: #4caf50;
        }

        .status-badge.overdue {
            background: #ffebee;
            color: #f44336;
        }

        .approval-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .approval-badge.approved {
            background: #e8f5e9;
            color: #4caf50;
        }

        .approval-badge.pending {
            background: #fff3e0;
            color: #ff9800;
        }

        .approval-badge.rejected {
            background: #ffebee;
            color: #f44336;
        }

        .verification-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .verification-badge.verified {
            background: #e8f5e9;
            color: #4caf50;
        }

        .verification-badge.pending {
            background: #fff3e0;
            color: #ff9800;
        }

        .verification-badge.flagged {
            background: #ffebee;
            color: #f44336;
        }

        .verification-badge.na {
            background: #f5f5f5;
            color: #999;
        }

        .action-btn {
            padding: 8px 12px;
            background: #9c27b0;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
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
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .page-btn {
            padding: 10px 16px;
            background: white;
            color: #9c27b0;
            text-decoration: none;
            border: 1px solid #9c27b0;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .page-btn:hover {
            background: #9c27b0;
            color: white;
        }

        .page-numbers {
            display: flex;
            gap: 6px;
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
            border-color: #9c27b0;
        }

        .page-number.active {
            background: #9c27b0;
            color: white;
            border-color: #9c27b0;
        }

        @media print {
            .filter-form,
            .export-btn,
            .action-btn,
            .pagination {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Kiosk Logs</h1>
            </header>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e3f2fd;">
                        <i class="fas fa-list" style="color: #2196f3;"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['total_logs']) ?></div>
                        <div class="stat-label">Total Logs</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff3e0;">
                        <i class="fas fa-calendar-day" style="color: #ff9800;"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['today_logs']) ?></div>
                        <div class="stat-label">Today's Logs</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #e3f2fd;">
                        <i class="fas fa-arrow-right" style="color: #2196f3;"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['borrow_logs']) ?></div>
                        <div class="stat-label">Borrow Logs</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: #e8f5e9;">
                        <i class="fas fa-arrow-left" style="color: #4caf50;"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?= number_format($stats['return_logs']) ?></div>
                        <div class="stat-label">Return Logs</div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-filter"></i> Filters & Search</h2>
                    <button class="export-btn" onclick="window.print()">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </div>

                <form method="GET" action="" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="type">Transaction Type</label>
                            <select name="type" id="type" onchange="this.form.submit()">
                                <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
                                <option value="Borrow" <?= $filter_type === 'Borrow' ? 'selected' : '' ?>>Borrow</option>
                                <option value="Return" <?= $filter_type === 'Return' ? 'selected' : '' ?>>Return</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" onchange="this.form.submit()">
                                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="Active" <?= $filter_status === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Returned" <?= $filter_status === 'Returned' ? 'selected' : '' ?>>Returned</option>
                                <option value="Overdue" <?= $filter_status === 'Overdue' ? 'selected' : '' ?>>Overdue</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="date">Date</label>
                            <input type="date" name="date" id="date" value="<?= htmlspecialchars($filter_date) ?>" onchange="this.form.submit()">
                        </div>

                        <div class="filter-group search-group">
                            <label for="search">Search</label>
                            <div class="search-input-wrapper">
                                <input type="text" name="search" id="search" placeholder="Student ID, Equipment..." value="<?= htmlspecialchars($search_query) ?>">
                                <button type="submit" class="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <?php if ($filter_type !== 'all' || $filter_status !== 'all' || !empty($filter_date) || !empty($search_query)): ?>
                            <div class="filter-group">
                                <label>&nbsp;</label>
                                <a href="admin-kiosk-logs.php" class="clear-btn">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <!-- Logs Table -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Activity Logs</h2>
                    <div class="results-info">
                        Showing <?= number_format($offset + 1) ?> - <?= number_format(min($offset + $records_per_page, $total_records)) ?> of <?= number_format($total_records) ?> records
                    </div>
                </div>

                <div class="table-container">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Student ID</th>
                                <th>Equipment</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Approval</th>
                                <th>Verification</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="11" style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                                        No logs found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><span class="log-id">#<?= $log['id'] ?></span></td>
                                        <td>
                                            <div class="log-datetime">
                                                <div class="log-date"><?= date('M d, Y', strtotime($log['transaction_date'])) ?></div>
                                                <div class="log-time"><?= date('H:i:s', strtotime($log['transaction_date'])) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="type-badge <?= strtolower($log['transaction_type']) ?>">
                                                <i class="fas fa-<?= $log['transaction_type'] === 'Borrow' ? 'arrow-right' : 'arrow-left' ?>"></i>
                                                <?= htmlspecialchars($log['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="student-cell">
                                                <i class="fas fa-id-card"></i>
                                                <?= htmlspecialchars($log['student_id'] ?? 'N/A') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="equipment-cell">
                                                <?= htmlspecialchars($log['equipment_name']) ?>
                                                <div class="rfid-tag"><?= htmlspecialchars($log['equipment_rfid']) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="category-badge">
                                                <?= htmlspecialchars($log['category_name']) ?>
                                            </span>
                                        </td>
                                        <td><span class="quantity"><?= $log['quantity'] ?></span></td>
                                        <td>
                                            <span class="status-badge <?= strtolower($log['status']) ?>">
                                                <?= htmlspecialchars($log['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="approval-badge <?= strtolower($log['approval_status']) ?>">
                                                <?= htmlspecialchars($log['approval_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($log['return_verification_status']): ?>
                                                <span class="verification-badge <?= strtolower(str_replace(' ', '-', $log['return_verification_status'])) ?>">
                                                    <?= htmlspecialchars($log['return_verification_status']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="verification-badge na">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="action-btn view-btn" onclick="viewLogDetails(<?= $log['id'] ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&type=<?= $filter_type ?>&status=<?= $filter_status ?>&date=<?= $filter_date ?>&search=<?= urlencode($search_query) ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <div class="page-numbers">
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?page=<?= $i ?>&type=<?= $filter_type ?>&status=<?= $filter_status ?>&date=<?= $filter_date ?>&search=<?= urlencode($search_query) ?>" 
                                   class="page-number <?= $i === $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&type=<?= $filter_type ?>&status=<?= $filter_status ?>&date=<?= $filter_date ?>&search=<?= urlencode($search_query) ?>" class="page-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        function viewLogDetails(logId) {
            // Redirect to transaction details or open modal
            window.location.href = 'admin-all-transaction.php?id=' + logId;
        }
    </script>
</body>
</html>
