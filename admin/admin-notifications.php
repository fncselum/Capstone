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

if ($table_exists) {
    // Get statistics
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
        FROM notifications";
    $stats_result = $conn->query($stats_query);
    if ($stats_result) {
        $stats = $stats_result->fetch_assoc();
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
                                <div class="notification-item <?= $notif['status'] === 'unread' ? 'unread' : '' ?>">
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

    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }

        function markAsRead(id) {
            if (confirm('Mark this notification as read?')) {
                fetch('update_notification.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `id=${id}&action=read`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        function archiveNotification(id) {
            if (confirm('Archive this notification?')) {
                fetch('update_notification.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `id=${id}&action=archive`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        function deleteNotification(id, title) {
            if (confirm(`Are you sure you want to delete "${title}"?\n\nThis action cannot be undone.`)) {
                fetch('delete_notification.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }
    </script>
</body>
</html>
