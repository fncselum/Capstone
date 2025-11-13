<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Check if notifications table exists
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
$table_exists = $table_check && $table_check->num_rows > 0;

// Handle success/error messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$notifications = null;
$total_records = 0;
$total_pages = 1;
$stats = ['total' => 0, 'unread' => 0, 'read' => 0, 'archived' => 0];
$current_last_id = null;

if ($table_exists) {
    // Get statistics
    $stats_query = "SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) AS unread_count,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) AS read_count,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) AS archived_count
        FROM notifications";
    $stats_result = $conn->query($stats_query);
    if ($stats_result) {
        $stats_row = $stats_result->fetch_assoc();
        $stats = [
            'total' => (int)($stats_row['total'] ?? 0),
            'unread' => (int)($stats_row['unread_count'] ?? 0),
            'read' => (int)($stats_row['read_count'] ?? 0),
            'archived' => (int)($stats_row['archived_count'] ?? 0),
        ];
    }

    // Get latest notification id for change detection
    $last_res = $conn->query("SELECT MAX(id) AS last_id FROM notifications");
    if ($last_res) {
        $last_row = $last_res->fetch_assoc();
        $current_last_id = isset($last_row['last_id']) ? (int)$last_row['last_id'] : null;
    }

    // Build SQL query with filters
    $sql = "SELECT * FROM notifications WHERE 1=1";
    $count_sql = "SELECT COUNT(*) as total FROM notifications WHERE 1=1";

    if ($filter_type !== 'all') {
        $type_escaped = $conn->real_escape_string($filter_type);
        $sql .= " AND type = '$type_escaped'";
        $count_sql .= " AND type = '$type_escaped'";
    }

    if ($filter_status !== 'all') {
        $status_escaped = $conn->real_escape_string($filter_status);
        $sql .= " AND status = '$status_escaped'";
        $count_sql .= " AND status = '$status_escaped'";
    } else {
        // Default view excludes archived
        $sql .= " AND status <> 'archived'";
        $count_sql .= " AND status <> 'archived'";
    }

    if (!empty($search_query)) {
        $search_escaped = $conn->real_escape_string($search_query);
        $sql .= " AND (title LIKE '%$search_escaped%' OR message LIKE '%$search_escaped%')";
        $count_sql .= " AND (title LIKE '%$search_escaped%' OR message LIKE '%$search_escaped%')";
    }

    // Get total count for pagination
    $count_result = $conn->query($count_sql);
    if ($count_result) {
        $count_row = $count_result->fetch_assoc();
        $total_records = $count_row['total'];
        $total_pages = ceil($total_records / $records_per_page);
    }

    $sql .= " ORDER BY created_at DESC LIMIT $offset, $records_per_page";
    $notifications = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Equipment System</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Statistics Cards */
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

        .add-btn {
            padding: 10px 20px;
            background: #9c27b0;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .add-btn:hover {
            background: #7b1fa2;
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

        /* Notifications List */
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-item {
            background: white;
            border: 2px solid #e8f3ee;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }

        .notification-item.unread {
            background: #f0f9f4;
            border-color: #006633;
        }

        .notification-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .notification-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .notification-type.info {
            background: #e3f2fd;
            color: #0d47a1;
        }

        .notification-type.warning {
            background: #fff3e0;
            color: #e65100;
        }

        .notification-type.success {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .notification-type.error {
            background: #ffebee;
            color: #b71c1c;
        }

        .notification-message {
            color: #555;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .notification-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #e8f3ee;
        }

        .notification-time {
            font-size: 0.85rem;
            color: #999;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .action-btn.read {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .action-btn.read:hover {
            background: #c8e6c9;
        }

        .action-btn.archive {
            background: #f5f5f5;
            color: #666;
        }

        .action-btn.archive:hover {
            background: #e0e0e0;
        }

        .action-btn.delete {
            background: #ffebee;
            color: #b71c1c;
        }

        .action-btn.delete:hover {
            background: #ffcdd2;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 4rem;
            color: #c5d9ce;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #666;
            margin-bottom: 10px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #1b5e20;
            border-left: 4px solid #4caf50;
        }

        .alert-error {
            background: #ffebee;
            color: #b71c1c;
            border-left: 4px solid #f44336;
        }

        /* Setup Notice */
        .setup-notice {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
        }

        .setup-icon {
            font-size: 4rem;
            color: #006633;
            margin-bottom: 20px;
        }

        .setup-notice h2 {
            color: #006633;
            margin-bottom: 15px;
        }

        .setup-sql {
            margin-top: 30px;
            text-align: left;
        }

        .setup-sql textarea {
            width: 100%;
            height: 300px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            resize: vertical;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }

        .modal-header .close {
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-footer button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .modal-footer button.confirm {
            background: #006633;
            color: white;
        }

        .modal-footer button.cancel {
            background: #f5f5f5;
            color: #666;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1;
        }

        .toast {
            background: #e8f5e9;
            color: #1b5e20;
            padding: 10px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 10px;
        }

        .toast.error {
            background: #ffebee;
            color: #b71c1c;
        }
    </style>
</head>
<body>

    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">System Notifications</h1>
            </header>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!$table_exists): ?>
                <div class="setup-notice">
                    <div class="setup-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h2>Database Setup Required</h2>
                    <p>The notifications table needs to be created in your database.</p>
                    <div class="setup-sql">
                        <h3>Quick Setup SQL:</h3>
                        <textarea readonly onclick="this.select()">CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread', 'read', 'archived') DEFAULT 'unread',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_type` (`type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</textarea>
                        <p style="margin-top: 10px; color: #666;"><small>Click the text area above to select all, then copy (Ctrl+C) and run in phpMyAdmin</small></p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><i class="fas fa-bell"></i> Total</h3>
                        <strong><?= number_format($stats['total'] ?? 0) ?></strong>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fas fa-envelope"></i> Unread</h3>
                        <strong><?= number_format($stats['unread'] ?? 0) ?></strong>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fas fa-envelope-open"></i> Read</h3>
                        <strong><?= number_format($stats['read'] ?? 0) ?></strong>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fas fa-archive"></i> Archived</h3>
                        <strong><?= number_format($stats['archived'] ?? 0) ?></strong>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="search">Search</label>
                                <input type="text" name="search" id="search" placeholder="Search notifications..." value="<?= htmlspecialchars($search_query) ?>">
                            </div>
                            <div class="filter-group">
                                <label for="type">Type</label>
                                <select name="type" id="type">
                                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
                                    <option value="info" <?= $filter_type === 'info' ? 'selected' : '' ?>>Info</option>
                                    <option value="warning" <?= $filter_type === 'warning' ? 'selected' : '' ?>>Warning</option>
                                    <option value="success" <?= $filter_type === 'success' ? 'selected' : '' ?>>Success</option>
                                    <option value="error" <?= $filter_type === 'error' ? 'selected' : '' ?>>Error</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="status">Status</label>
                                <select name="status" id="status">
                                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                                    <option value="unread" <?= $filter_status === 'unread' ? 'selected' : '' ?>>Unread</option>
                                    <option value="read" <?= $filter_status === 'read' ? 'selected' : '' ?>>Read</option>
                                    <option value="archived" <?= $filter_status === 'archived' ? 'selected' : '' ?>>Archived</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="filter-btn">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                            </div>
                            <?php if (!empty($search_query) || $filter_type !== 'all' || $filter_status !== 'all'): ?>
                                <div class="filter-group">
                                    <label>&nbsp;</label>
                                    <a href="admin-notifications.php" class="clear-btn">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Notifications List -->
                <div style="background: white; padding: 28px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                    <h3 style="margin-bottom: 20px; color: #006633; font-weight: 700;">
                        <i class="fas fa-list"></i> Notifications
                    </h3>

                    <?php if ($notifications && $notifications->num_rows > 0): ?>
                        <div class="notifications-list">
                            <?php while ($notif = $notifications->fetch_assoc()): ?>
                                <div id="notif-<?= $notif['id'] ?>" class="notification-item <?= $notif['status'] === 'unread' ? 'unread' : '' ?>">
                                    <div class="notification-header">
                                        <div class="notification-title">
                                            <?php
                                            $icon = 'fa-info-circle';
                                            if ($notif['type'] === 'warning') $icon = 'fa-exclamation-triangle';
                                            elseif ($notif['type'] === 'success') $icon = 'fa-check-circle';
                                            elseif ($notif['type'] === 'error') $icon = 'fa-times-circle';
                                            ?>
                                            <i class="fas <?= $icon ?>"></i>
                                            <?= htmlspecialchars($notif['title']) ?>
                                        </div>
                                        <span class="notification-type <?= $notif['type'] ?>">
                                            <?= ucfirst($notif['type']) ?>
                                        </span>
                                    </div>
                                    <div class="notification-message">
                                        <?= nl2br(htmlspecialchars($notif['message'])) ?>
                                    </div>
                                    <div class="notification-footer">
                                        <span class="notification-time">
                                            <i class="fas fa-clock"></i>
                                            <?= date('M j, Y H:i', strtotime($notif['created_at'])) ?>
                                        </span>
                                        <div class="notification-actions">
                                            <?php if ($notif['status'] === 'unread'): ?>
                                                <button class="action-btn read" onclick="markAsRead(<?= $notif['id'] ?>)">
                                                    <i class="fas fa-check"></i> Mark as Read
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($notif['status'] !== 'archived'): ?>
                                                <button class="action-btn archive" onclick="archiveNotification(<?= $notif['id'] ?>)">
                                                    <i class="fas fa-archive"></i> Archive
                                                </button>
                                            <?php endif; ?>
                                            <button class="action-btn delete" onclick="deleteNotification(<?= $notif['id'] ?>, '<?= htmlspecialchars($notif['title']) ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_query) ?>&type=<?= $filter_type ?>&status=<?= $filter_status ?>" class="page-btn">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&type=<?= $filter_type ?>&status=<?= $filter_status ?>" 
                                       class="page-number <?= $i === $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_query) ?>&type=<?= $filter_type ?>&status=<?= $filter_status ?>" class="page-btn">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Notifications Found</h3>
                            <p>There are no notifications matching your criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <style>
        .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:1000}
        .confirm-modal{background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);width:min(480px,92vw);overflow:hidden}
        .confirm-modal .hd{padding:16px 20px;border-bottom:1px solid #f0f0f0;font-weight:800;color:#333;display:flex;align-items:center;gap:10px}
        .confirm-modal .bd{padding:18px 20px;color:#555;line-height:1.5}
        .confirm-modal .ft{display:flex;gap:10px;justify-content:flex-end;padding:14px 16px;background:#fafafa;border-top:1px solid #f0f0f0}
        .btn{padding:10px 16px;border-radius:8px;border:1px solid transparent;font-weight:700;font-size:.9rem;cursor:pointer}
        .btn.cancel{background:#fff;border-color:#ddd;color:#555}
        .btn.primary{background:#0b875b;color:#fff}
        .btn.danger{background:#e53935;color:#fff}
        .toast-wrap{position:fixed;right:20px;bottom:20px;display:flex;flex-direction:column;gap:10px;z-index:1100}
        .toast{background:#333;color:#fff;padding:12px 14px;border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.25);min-width:220px;font-size:.9rem}
        .toast.success{background:#2e7d32}
        .toast.error{background:#c62828}
    </style>
    <div id="modalBackdrop" class="modal-backdrop" aria-hidden="true">
        <div class="confirm-modal" role="dialog" aria-modal="true">
            <div class="hd" id="confirmTitle"><i class="fas fa-bell"></i><span>Confirm</span></div>
            <div class="bd" id="confirmMessage">Are you sure?</div>
            <div class="ft">
                <button type="button" class="btn cancel" id="confirmCancel">Cancel</button>
                <button type="button" class="btn primary" id="confirmOk">Confirm</button>
            </div>
        </div>
    </div>
    <div class="toast-wrap" id="toastWrap"></div>

    <script>
        const currentFilterStatus = <?= json_encode($filter_status) ?>;
        function logout(){localStorage.clear();sessionStorage.clear();window.location.href='logout.php'}

        function showToast(msg,type='success',ms=2200){
            const wrap=document.getElementById('toastWrap');
            const t=document.createElement('div');
            t.className=`toast ${type}`; t.textContent=msg; wrap.appendChild(t);
            setTimeout(()=>{t.style.opacity='0';t.style.transform='translateY(6px)';},ms-400);
            setTimeout(()=>wrap.removeChild(t),ms);
        }

        function showUndoToast(message, onUndo, ms=6000){
            const wrap=document.getElementById('toastWrap');
            const t=document.createElement('div');
            t.className='toast success';
            t.style.display='flex';
            t.style.alignItems='center';
            t.style.justifyContent='space-between';
            t.style.gap='12px';
            const span=document.createElement('span');
            span.textContent=message;
            const btn=document.createElement('button');
            btn.textContent='Undo';
            btn.className='btn cancel';
            btn.style.padding='6px 10px';
            btn.style.borderRadius='6px';
            btn.style.border='1px solid rgba(255,255,255,.6)';
            btn.style.background='transparent';
            btn.style.color='#fff';
            btn.onclick=()=>{ try{ onUndo && onUndo(); }finally{ if (t.parentNode) t.parentNode.removeChild(t); } };
            t.appendChild(span); t.appendChild(btn); wrap.appendChild(t);
            setTimeout(()=>{ if (t.parentNode) { t.style.opacity='0'; t.style.transform='translateY(6px)'; setTimeout(()=>t.parentNode && t.parentNode.removeChild(t), 400);} }, ms);
        }

        function confirmModal(opts){
            return new Promise(resolve=>{
                const bd=document.getElementById('modalBackdrop');
                const t=document.getElementById('confirmTitle').querySelector('span');
                const m=document.getElementById('confirmMessage');
                const ok=document.getElementById('confirmOk');
                const cancel=document.getElementById('confirmCancel');
                t.textContent=opts.title||'Confirm';
                m.textContent=opts.message||'Are you sure?';
                ok.textContent=opts.confirmText||'Confirm';
                ok.className=`btn ${opts.variant==='danger'?'danger':'primary'}`;
                const close=(val)=>{bd.style.display='none';ok.onclick=null;cancel.onclick=null;bd.onclick=null;resolve(val)};
                ok.onclick=()=>close(true);
                cancel.onclick=()=>close(false);
                bd.onclick=(e)=>{if(e.target===bd) close(false)};
                bd.style.display='flex';
            });
        }

        async function markAsRead(id){
            const ok=await confirmModal({title:'Mark as Read',message:'Mark this notification as read?',confirmText:'Mark as Read'});
            if(!ok) return;
            fetch('update_notification.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id=${id}&action=read`})
            .then(r=>r.json()).then(d=>{if(d.success){showToast('Marked as read','success'); setTimeout(()=>location.reload(),700);}else{showToast('Error: '+d.message,'error',3000);}})
            .catch(()=>showToast('Request failed','error',3000));
        }

        async function archiveNotification(id){
            const ok=await confirmModal({title:'Archive Notification',message:'Archive this notification?',confirmText:'Archive'});
            if(!ok) return;
            fetch('update_notification.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id=${id}&action=archive`})
            .then(r=>r.json()).then(d=>{
                if(d.success){
                    const list = document.querySelector('.notifications-list');
                    const row = document.getElementById('notif-'+id);
                    const rowHTML = row ? row.outerHTML : null;
                    // Remove from view immediately unless we are on Archived filter
                    if(currentFilterStatus !== 'archived' && row){
                        row.style.transition='opacity .2s ease'; row.style.opacity='0'; setTimeout(()=>row.remove(), 220);
                    }
                    // Show Undo to restore to 'read'
                    showUndoToast('Notification archived', async ()=>{
                        try{
                            const res = await fetch('update_notification.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}&action=read`});
                            const jr = await res.json();
                            if(jr && jr.success){
                                // Reinsert only if current view would show READ
                                if((currentFilterStatus === 'all' || currentFilterStatus === 'read') && list && rowHTML){
                                    const temp = document.createElement('div'); temp.innerHTML = rowHTML.trim();
                                    const restored = temp.firstElementChild; if(restored){
                                        restored.classList.remove('unread');
                                        const target = list.firstElementChild; if(target){ list.insertBefore(restored, target); } else { list.appendChild(restored); }
                                    }
                                }
                                showToast('Restored to Read');
                            } else {
                                showToast('Failed to restore','error',3000);
                            }
                        }catch(e){ showToast('Failed to restore','error',3000); }
                    });
                } else {
                    showToast('Error: '+d.message,'error',3000);
                }
            })
            .catch(()=>showToast('Request failed','error',3000));
        }

        async function deleteNotification(id,title){
            const ok=await confirmModal({title:'Delete Notification',message:`Delete "${title}"? This cannot be undone.`,confirmText:'Delete',variant:'danger'});
            if(!ok) return;
            fetch('delete_notification.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`id=${id}`})
            .then(r=>r.json()).then(d=>{if(d.success){showToast('Deleted','success'); setTimeout(()=>location.reload(),700);}else{showToast('Error: '+d.message,'error',3000);}})
            .catch(()=>showToast('Request failed','error',3000));
        }
    </script>

    <script>
        // Auto-refresh when new notifications arrive or unread count changes
        (function(){
            const initialUnread = <?= json_encode((int)($stats['unread'] ?? 0)) ?>;
            const initialLastId = <?= json_encode($current_last_id) ?>;
            const pollMs = 15000;
            async function checkUpdates(){
                try {
                    const res = await fetch('notifications_api.php', { cache: 'no-store' });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (!data || !data.ok) return;
                    const changed = (typeof data.unread === 'number' && data.unread !== initialUnread) ||
                                    (data.last_id !== null && data.last_id !== initialLastId);
                    if (changed) {
                        const url = new URL(window.location.href);
                        // Preserve existing filters
                        window.location.href = url.pathname + url.search;
                    }
                } catch (e) {
                    // ignore errors
                }
            }
            setInterval(checkUpdates, pollMs);
        })();
    </script>
</body>
</html>
