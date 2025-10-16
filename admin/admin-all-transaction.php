<?php
session_start();

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

// Check if users table exists
$users_table_exists = false;
$check_users = $conn->query("SHOW TABLES LIKE 'users'");
if ($check_users && $check_users->num_rows > 0) {
    $users_table_exists = true;
}

// Get all transactions with user information
if ($users_table_exists) {
    // Check what columns exist in users table
    $user_columns = [];
    $cols_result = $conn->query("SHOW COLUMNS FROM users");
    if ($cols_result) {
        while ($col = $cols_result->fetch_assoc()) {
            $user_columns[] = $col['Field'];
        }
    }
    
    // Build query based on available columns
    $user_name_col = '';
    if (in_array('name', $user_columns)) {
        $user_name_col = 'u.name as user_name,';
    } elseif (in_array('full_name', $user_columns)) {
        $user_name_col = 'u.full_name as user_name,';
    } elseif (in_array('username', $user_columns)) {
        $user_name_col = 'u.username as user_name,';
    }
    
    $student_id_col = 'u.student_id';
    if (!in_array('student_id', $user_columns) && in_array('id', $user_columns)) {
        $student_id_col = 'u.id as student_id';
    }
    
    $query = "SELECT t.*, 
                e.name as equipment_name,
                $user_name_col
                $student_id_col
         FROM transactions t
         LEFT JOIN equipment e ON t.equipment_id = e.id
         LEFT JOIN users u ON t.user_id = u.id
         ORDER BY t.transaction_date DESC";
} else {
    // Fallback if users table doesn't exist - use rfid_id from transactions
    $query = "SELECT t.*, 
                e.name as equipment_name,
                t.rfid_id as student_id
         FROM transactions t
         LEFT JOIN equipment e ON t.equipment_id = e.id
         ORDER BY t.transaction_date DESC";
}

$all_transactions = $conn->query($query);

// Debug: Check for query errors
if (!$all_transactions) {
    $query_error = $conn->error;
} else {
    $query_error = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Transactions - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/all-transactions.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            min-width: 300px;
        }
        .search-box i {
            color: #7aa893;
        }
        .search-box input {
            border: none;
            outline: none;
            flex: 1;
            font-size: 14px;
        }
        .transactions-table {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f3fbf6;
            color: #006633;
            font-weight: 700;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #e0e0e0;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        td small {
            color: #666;
            font-size: 0.85em;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .badge.borrow {
            background: #e3f2fd;
            color: #1976d2;
        }
        .badge.return {
            background: #e8f5e9;
            color: #388e3c;
        }
        .badge.violation {
            background: #ffebee;
            color: #d32f2f;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
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
                <li class="nav-item">
                    <a href="reports.php"><i class="fas fa-file-alt"></i><span>Reports</span></a>
                </li>
                <li class="nav-item active">
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

            <div class="sidebar-footer">
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </button>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">All Equipment Transactions</h1>
            </header>

            <!-- Transactions Section -->
            <section class="content-section active">
                <!-- Filter and Search Bar -->
                <div class="filter-bar">
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-filter="all" onclick="filterTransactions('all')">All</button>
                        <button class="filter-btn" data-filter="borrowed" onclick="filterTransactions('borrowed')">Active</button>
                        <button class="filter-btn" data-filter="returned" onclick="filterTransactions('returned')">Returned</button>
                        <button class="filter-btn" data-filter="overdue" onclick="filterTransactions('overdue')">Overdue</button>
                    </div>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by equipment, student ID, or name..." onkeyup="searchTransactions()">
                    </div>
                </div>

                <!-- All Transactions Table -->
                <div class="transactions-table">
                    <?php if (isset($query_error) && $query_error): ?>
                        <div class="error-message" style="background:#ffebee; color:#d32f2f; padding:20px; border-radius:8px; margin:20px 0;">
                            <strong>Database Error:</strong> <?= htmlspecialchars($query_error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($all_transactions && $all_transactions->num_rows > 0): ?>
                    <table id="transactionsTable">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Student</th>
                                <th>Quantity</th>
                                <th>Transaction Date</th>
                                <th>Expected Return</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset pointer to beginning
                            $all_transactions->data_seek(0);
                            while($row = $all_transactions->fetch_assoc()): 
                                // Determine status based on transaction_type and status
                                $status = 'borrowed';
                                $statusLabel = $row['status'] ?? 'Active';
                                $badgeClass = 'borrow';
                                
                                if ($row['status'] === 'Returned') {
                                    $status = 'returned';
                                    $statusLabel = 'Returned';
                                    $badgeClass = 'return';
                                } elseif ($row['transaction_type'] === 'Borrow' && $row['status'] === 'Active') {
                                    // Check if overdue
                                    if (isset($row['expected_return_date']) && strtotime($row['expected_return_date']) < time()) {
                                        $status = 'overdue';
                                        $statusLabel = 'Overdue';
                                        $badgeClass = 'violation';
                                    } else {
                                        $statusLabel = 'Active';
                                    }
                                }
                            ?>
                            <tr data-status="<?= $status ?>">
                                <td>
                                    <strong><?= htmlspecialchars($row['equipment_name']) ?></strong>
                                </td>
                                <td>
                                    <?php if (!empty($row['student_id'])): ?>
                                        <strong><?= htmlspecialchars($row['student_id']) ?></strong>
                                        <?php if ($users_table_exists && !empty($row['user_name'])): ?>
                                            <br><small style="color:#666;"><?= htmlspecialchars($row['user_name']) ?></small>
                                        <?php endif; ?>
                                    <?php elseif (!empty($row['rfid_id'])): ?>
                                        <?= htmlspecialchars($row['rfid_id']) ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight:600; color:#006633;">
                                        <?= isset($row['quantity']) ? htmlspecialchars($row['quantity']) : '1' ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y g:i A', strtotime($row['transaction_date'])) ?></td>
                                <td>
                                    <?php if (!empty($row['expected_return_date'])): ?>
                                        <?= date('M j, Y g:i A', strtotime($row['expected_return_date'])) ?>
                                    <?php else: ?>
                                        <span style="color:#999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data" style="text-align:center; padding:40px; background:white; border-radius:12px;">
                        <i class="fas fa-inbox" style="font-size:48px; color:#ccc; margin-bottom:20px;"></i>
                        <p style="color:#999; font-size:16px; margin:10px 0;">No transactions found in the database.</p>
                        <p style="color:#666; font-size:14px;">
                            <?php if ($all_transactions): ?>
                                Query executed successfully but returned 0 rows.
                            <?php else: ?>
                                Query failed to execute.
                            <?php endif; ?>
                        </p>
                        <!-- Debug Info -->
                        <details style="margin-top:20px; text-align:left; background:#f5f5f5; padding:15px; border-radius:8px;">
                            <summary style="cursor:pointer; font-weight:bold; color:#006633;">Show Debug Info</summary>
                            <pre style="margin-top:10px; font-size:12px; overflow-x:auto;">
Users table exists: <?= $users_table_exists ? 'Yes' : 'No' ?>
<?php if ($users_table_exists && isset($user_columns)): ?>
User table columns: <?= implode(', ', $user_columns) ?>
<?php endif; ?>

Query: <?= htmlspecialchars($query) ?>

<?php if ($query_error): ?>
Error: <?= htmlspecialchars($query_error) ?>
<?php endif; ?>

Total rows in transactions: <?php 
$count_check = $conn->query("SELECT COUNT(*) as cnt FROM transactions");
echo $count_check ? $count_check->fetch_assoc()['cnt'] : 'Unable to check';
?>
                            </pre>
                        </details>
                    </div>
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
        
        // Current filter
        let currentFilter = 'all';
        
        // Filter transactions
        function filterTransactions(filter) {
            currentFilter = filter;
            const rows = document.querySelectorAll('#transactionsTable tbody tr');
            const buttons = document.querySelectorAll('.filter-btn');
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            // Update active button
            buttons.forEach(btn => {
                if (btn.dataset.filter === filter) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            // Filter rows
            rows.forEach(row => {
                const status = row.dataset.status;
                const text = row.textContent.toLowerCase();
                const matchesFilter = filter === 'all' || status === filter;
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                
                if (matchesFilter && matchesSearch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            updateCount();
        }
        
        // Search transactions
        function searchTransactions() {
            filterTransactions(currentFilter);
        }
        
        // Update visible count
        function updateCount() {
            const rows = document.querySelectorAll('#transactionsTable tbody tr');
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            const totalRows = rows.length;
            
            // You can add a count display element if needed
            console.log(`Showing ${visibleRows.length} of ${totalRows} transactions`);
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
