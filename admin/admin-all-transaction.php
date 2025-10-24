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

$conn->query("UPDATE transactions SET approval_status = 'Approved' WHERE status <> 'Pending Approval' AND (approval_status = 'Pending' OR approval_status IS NULL)");


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
                COALESCE(t.transaction_date, t.created_at) AS txn_datetime,
                e.name as equipment_name,
                e.image_path as equipment_image_path,
                $user_name_col
                $student_id_col,
                t.approved_by,
                t.processed_by,
                t.detected_issues,
                inv.availability_status AS inventory_status,
                inv.available_quantity AS inventory_available_qty,
                inv.borrowed_quantity AS inventory_borrowed_qty
         FROM transactions t
         LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
         LEFT JOIN users u ON t.user_id = u.id
         LEFT JOIN inventory inv ON e.rfid_tag = inv.equipment_id
         ORDER BY t.transaction_date DESC";
} else {
    // Fallback if users table doesn't exist - use rfid_id from transactions
    $query = "SELECT t.*, 
                COALESCE(t.transaction_date, t.created_at) AS txn_datetime,
                e.name as equipment_name,
                e.image_path as equipment_image_path,
                t.rfid_id as student_id,
                t.approved_by,
                t.processed_by,
                t.detected_issues,
                inv.availability_status AS inventory_status,
                inv.available_quantity AS inventory_available_qty,
                inv.borrowed_quantity AS inventory_borrowed_qty
         FROM transactions t
         LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
         LEFT JOIN inventory inv ON e.rfid_tag = inv.equipment_id
         ORDER BY t.transaction_date DESC";
}

$all_transactions = $conn->query($query);

$transactionPhotos = [];
$photoQuery = $conn->query("SELECT transaction_id, photo_type, file_path FROM transaction_photos WHERE photo_type IN ('borrow','return','comparison','reference')");
if ($photoQuery) {
    while ($photoRow = $photoQuery->fetch_assoc()) {
        $transactionId = (int)$photoRow['transaction_id'];
        $type = $photoRow['photo_type'] ?? '';
        $path = $photoRow['file_path'] ?? '';
        if ($transactionId > 0 && $type !== '' && $path !== '') {
            if (!isset($transactionPhotos[$transactionId])) {
                $transactionPhotos[$transactionId] = [];
            }
            if (!isset($transactionPhotos[$transactionId][$type])) {
                $transactionPhotos[$transactionId][$type] = [];
            }
            $transactionPhotos[$transactionId][$type][] = $path;
        }
    }
    $photoQuery->free();
}

if (!function_exists('resolveTransactionPhotoUrl')) {
    function resolveTransactionPhotoUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (preg_match('/^https?:/i', $path)) {
            return $path;
        }
        return '../' . ltrim($path, '/');
    }
}

if (!function_exists('resolvePhotoList')) {
    function resolvePhotoList(array $paths): array
    {
        return array_values(array_filter(array_map(function ($item) {
            return resolveTransactionPhotoUrl($item);
        }, $paths ?? [])));
    }
}

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
        .badge.rejected {
            background: #ffebee;
            color: #c62828;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .approval-feedback {
            display: none;
            margin-bottom: 15px;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
        }
        .approval-feedback.show {
            display: block;
        }
        .approval-feedback.success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .approval-feedback.error {
            background: #ffebee;
            color: #c62828;
        }
        .approval-meta {
            margin-top: 6px;
            font-size: 0.85em;
            color: #555;
        }
        .approval-meta small {
            color: inherit;
        }
        .approval-meta:empty {
            display: none;
        }
        .approval-meta .danger-text {
            color: #d32f2f;
        }
        .approval-actions {
            display: flex;
            gap: 8px;
        }
        .approval-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            color: #fff;
        }
        .approve-btn {
            background: #4caf50;
        }
        .approve-btn:hover {
            background: #43a047;
        }
        .reject-btn {
            background: #f44336;
        }
        .reject-btn:hover {
            background: #e53935;
        }
        .return-verification-cell {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .return-verification-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .return-verification-badge.pending {
            background: #fff3e0;
            color: #fb8c00;
        }
        .return-verification-badge.not-returned {
            background: #f3e5f5;
            color: #6a1b9a;
        }
        .return-verification-badge.verified {
            background: #e8f5e9;
            color: #388e3c;
        }
        .return-verification-badge.flagged {
            background: #fff3e0;
            color: #fb8c00;
        }
        .return-verification-badge.rejected {
            background: #ffebee;
            color: #c62828;
        }
        .return-verification-score {
            font-size: 0.85em;
            color: #424242;
            font-weight: 600;
        }
        .view-return-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #4caf50;
            background: #ffffff;
            color: #2e7d32;
            font-size: 0.85em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }
        .detected-issues-content {
            background: #fff;
            padding: 12px 15px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            font-size: 0.95em;
            color: #2d3748;
            line-height: 1.6;
            max-height: 220px;
            overflow-y: auto;
            transition: background 0.2s ease;
        }
        .detected-issues-textarea {
            width: 100%;
            min-height: 90px;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.95em;
            resize: vertical;
            margin-bottom: 10px;
            box-sizing: border-box;
        }
        .detected-issues-textarea:focus {
            outline: none;
            border-color: #4e73df;
            box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.12);
        }
        .detected-issues-content:hover {
            background: #fdfdfe;
        }
        .view-return-btn:hover {
            background: #e8f5e9;
            color: #1b5e20;
        }
        .view-return-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .approval-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .approval-badge.pending {
            background: #fff4e5;
            color: #ef6c00;
        }
        .approval-badge.approved {
            background: #e8f5e9;
            color: #388e3c;
        }
        .approval-badge.rejected {
            background: #ffebee;
            color: #d32f2f;
        }
        .approval-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 20px;
        }
        .approval-modal.show {
            display: flex;
        }
        .approval-modal-content {
            background: #fff;
            border-radius: 12px;
            width: 100%;
            max-width: 420px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .approval-modal-content h2 {
            margin: 0 0 16px;
            font-size: 20px;
            color: #006633;
        }
        .approval-modal-content textarea {
            width: 100%;
            min-height: 100px;
            border-radius: 8px;
            border: 1px solid #d0d0d0;
            padding: 10px;
            resize: vertical;
            font-size: 14px;
        }
        .approval-modal-error {
            color: #d32f2f;
            font-size: 13px;
            margin-top: 6px;
        }
        .approval-modal-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .approval-cancel-btn {
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            background: #e0e0e0;
            color: #333;
        }
        .approval-submit-btn {
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            background: #f44336;
            color: #fff;
        }
        .approval-submit-btn:hover {
            background: #e53935;
        }
        .approve-btn:disabled,
        .reject-btn:disabled,
        .approval-submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .flag-btn {
            background: #fb8c00;
        }
        .flag-btn:hover {
            background: #f57c00;
        }
        .return-review-modal {
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .return-review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .return-review-header button {
            background: transparent;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #444;
        }
        .return-review-meta {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .return-review-equipment {
            font-weight: 600;
            color: #006633;
        }
        .return-review-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .return-review-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
        .return-review-photo {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .return-review-photo span {
            font-size: 0.8em;
            font-weight: 600;
            color: #424242;
        }
        .return-review-photo-frame {
            position: relative;
            width: 100%;
            padding-top: 60%;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
        }
        .return-review-photo-frame img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }
        .return-review-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85em;
            color: #757575;
            padding: 12px;
            text-align: center;
        }
        .return-review-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .return-review-textarea {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .return-review-textarea textarea {
            width: 100%;
            min-height: 80px;
            border-radius: 8px;
            border: 1px solid #d0d0d0;
            padding: 10px;
            resize: vertical;
            font-size: 14px;
        }
        .return-review-error {
            color: #c62828;
            font-size: 0.85em;
        }
        .return-review-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .detected-issues-section {
            margin: 15px 0;
            padding: 15px;
            background: linear-gradient(to bottom, #f8f9fa, #f1f3f5);
            border-left: 4px solid #4e73df;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            width: 100%;
            box-sizing: border-box;
            max-width: 100%;
            overflow: hidden;
        }
        @media (max-width: 768px) {
            .detected-issues-section {
                padding: 12px;
                margin: 10px 0;
            }
        }
        .detected-issues-section h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        @media (max-width: 768px) {
            .detected-issues-section h4 {
                font-size: 0.95em;
                margin-bottom: 8px;
            }
        }
        .detected-issues-section h4:before {
            content: '\f06a';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            color: #4e73df;
        }
        .detected-issues-content {
            background: #fff;
            padding: 12px 15px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            min-height: 60px;
            max-height: 40vh;
            white-space: pre-wrap;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.5;
            color: #2d3436;
            font-size: 0.95em;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            overflow-y: auto;
            box-sizing: border-box;
            width: 100%;
            resize: none;
            -ms-overflow-style: none;  /* Hide scrollbar for IE and Edge */
            scrollbar-width: none;  /* Hide scrollbar for Firefox */
        }
        .detected-issues-content::-webkit-scrollbar {
            display: none;  /* Hide scrollbar for Chrome, Safari and Opera */
        }
        @media (max-width: 768px) {
            .detected-issues-content {
                padding: 10px 12px;
                font-size: 0.9em;
                min-height: 50px;
                max-height: 30vh;
            }
        }
        /* Severity-based styling for detected issues */
        .detected-issues-section.severity-high {
            border-left-color: #e74c3c;
            background: linear-gradient(to bottom, #fef2f2, #fff5f5);
        }
        .detected-issues-section.severity-high h4 {
            color: #c0392b;
        }
        .detected-issues-section.severity-high h4:before {
            color: #e74c3c;
            content: '\f071';
        }
        
        .detected-issues-section.severity-medium {
            border-left-color: #f39c12;
            background: linear-gradient(to bottom, #fff8e6, #fffaf0);
        }
        .detected-issues-section.severity-medium h4 {
            color: #d35400;
        }
        .detected-issues-section.severity-medium h4:before {
            color: #f39c12;
            content: '\f06a';
        }
        
        .detected-issues-section.severity-low {
            border-left-color: #3498db;
        }
        
        .detected-issues-section.severity-none {
            border-left-color: #2ecc71;
            background: linear-gradient(to bottom, #f0fdf4, #f7fee7);
        }
        .detected-issues-section.severity-none h4:before {
            color: #2ecc71;
            content: '\f058';
        }

        .approval-na {
            color: #666;
        }
        .inventory-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            color: #fff;
        }
        .inventory-status-badge.available {
            background: #4caf50;
        }
        .inventory-status-badge.low-stock {
            background: #ef6c00;
        }
        .inventory-status-badge.out-of-stock {
            background: #d32f2f;
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
                    <div id="approvalFeedback" class="approval-feedback"></div>
                    <table id="transactionsTable">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Student</th>
                                <th>Quantity</th>
                                <th>Transaction Date</th>
                                <th>Return Date</th>
                                <th>Return Verification</th>
                                <th>Status</th>
                                <th>Approval</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset pointer to beginning
                            $all_transactions->data_seek(0);
                            while($row = $all_transactions->fetch_assoc()): 
                                // Determine status based on transaction_type and status
                                $status = 'borrowed';
                                $rowStatus = $row['status'] ?? 'Active';
                                $statusLabel = $rowStatus;
                                $badgeClass = 'borrow';

                                if ($rowStatus === 'Returned') {
                                    $status = 'returned';
                                    $statusLabel = 'Returned';
                                    $badgeClass = 'return';
                                } elseif ($rowStatus === 'Pending Review') {
                                    $status = 'review';
                                    $statusLabel = 'Pending Review';
                                    $badgeClass = 'pending-review';
                                } elseif ($row['transaction_type'] === 'Borrow' && $rowStatus === 'Active') {
                                    if (isset($row['expected_return_date']) && strtotime($row['expected_return_date']) < time()) {
                                        $status = 'overdue';
                                        $statusLabel = 'Overdue';
                                        $badgeClass = 'violation';
                                    } else {
                                        $statusLabel = 'Active';
                                    }
                                }
                            ?>
                            <?php
                                $isLargeItem = isset($row['item_size']) && strtolower($row['item_size']) === 'large';
                                $approvalStatus = $row['approval_status'] ?? 'Pending';
                                $approvalBadgeClass = 'pending';
                                if ($approvalStatus === 'Pending') {
                                    $approvalBadgeClass = 'pending';
                                } elseif ($approvalStatus === 'Rejected') {
                                    $approvalBadgeClass = 'rejected';
                                } elseif ($approvalStatus === 'Approved') {
                                    $approvalBadgeClass = 'approved';
                                } else {
                                    $approvalStatus = 'Pending';
                                }
                                $showApprovalActions = $isLargeItem && $approvalStatus === 'Pending';
                                $rowId = 'txn-' . $row['id'];

                                $transactionId = (int)($row['id'] ?? 0);
                                $photoSet = $transactionPhotos[$transactionId] ?? [];
                                $returnPhotos = resolvePhotoList($photoSet['return'] ?? []);
                                $damageDetections = [];
                                if (!empty($photoSet['detections'] ?? [])) {
                                    $damageDetections = $photoSet['detections'];
                                }
                                $borrowPhotos = resolvePhotoList($photoSet['borrow'] ?? []);
                                $referencePhotos = resolvePhotoList($photoSet['reference'] ?? []);
                                $equipmentImageResolved = resolveTransactionPhotoUrl($row['equipment_image_path'] ?? null);
                                if (empty($referencePhotos) && $equipmentImageResolved) {
                                    $referencePhotos[] = $equipmentImageResolved;
                                }
                                $similarityScore = isset($row['similarity_score']) ? (float)$row['similarity_score'] : null;
                                $verificationStatus = $row['return_verification_status'] ?? 'Pending';
                                $reviewStatus = $row['return_review_status'] ?? 'Pending';
                                $verificationClassMap = [
            'Not Yet Returned' => 'not-returned',
            'Pending' => 'pending',
            'Verified' => 'verified',
            'Flagged' => 'flagged',
            'Rejected' => 'rejected'
        ];
        $statusLower = strtolower($row['status'] ?? '');
        $showReturnReviewButton = ($statusLower === 'returned');
        $verificationBadgeClass = $verificationClassMap[$verificationStatus] ?? 'pending';
        $displayVerificationText = $verificationStatus ?? 'Pending';
        $verificationStatusForInfo = $verificationStatus;
        $reviewStatusForInfo = $reviewStatus;

        // Only show "Not Yet Returned" for items that are actually not returned
        if (!$showReturnReviewButton) {
            $verificationBadgeClass = 'not-returned';
            $displayVerificationText = 'Not Yet Returned';
            $verificationStatusForInfo = 'Not Yet Returned';
            $reviewStatusForInfo = 'Not Yet Returned';
        }

        $canOpenReturnReview = !empty($returnPhotos) || !empty($damageDetections) || !empty($referencePhotos) || !empty($borrowPhotos);

                                $returnInfo = [
            'transactionId' => $transactionId,
            'verificationStatus' => $verificationStatusForInfo,
            'reviewStatus' => $reviewStatusForInfo,
            'similarityScore' => $similarityScore,
            'itemSize' => $row['item_size'] ?? null,
            'equipmentName' => $row['equipment_name'] ?? null,
            'status' => $row['status'] ?? null,
            'detectedIssues' => $row['detected_issues'] ?? null,
            'borrowPhotos' => $borrowPhotos,
                                    'returnPhotos' => $returnPhotos,
                                    'detections' => $damageDetections,
            'referencePhotos' => $referencePhotos,
                                    'equipmentImage' => $equipmentImageResolved
                                ];
                                $returnInfoJson = htmlspecialchars(json_encode($returnInfo, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr id="<?= $rowId ?>" data-status="<?= $status ?>" data-item-size="<?= htmlspecialchars(strtolower($row['item_size'] ?? '')) ?>" data-approval-status="<?= htmlspecialchars($approvalStatus) ?>" data-equipment-name="<?= htmlspecialchars($row['equipment_name']) ?>" data-return-info="<?= $returnInfoJson ?>">
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
                                <td>
                                    <?php if (!empty($row['txn_datetime'])): ?>
                                        <?= date('M j, Y g:i A', strtotime($row['txn_datetime'])) ?>
                                    <?php else: ?>
                                        <span style="color:#999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['actual_return_date']) && $row['actual_return_date'] !== '0000-00-00 00:00:00'): ?>
                                        <?= date('M j, Y g:i A', strtotime($row['actual_return_date'])) ?>
                                <?php else: ?>
                                        <span style="color:#999;">N/A</span>
                                <?php endif; ?>
                                </td>
                                <td data-return-verification>
                                    <div class="return-verification-cell">
                                        <span class="return-verification-badge <?= $verificationBadgeClass ?>" data-return-verification-badge><?= htmlspecialchars($displayVerificationText) ?></span>
                                        <span class="return-verification-score" data-return-verification-score style="<?= $similarityScore === null ? 'display:none;' : '' ?>">
                                            <?= $similarityScore !== null ? 'Score: ' . number_format($similarityScore, 2) . '%' : '' ?>
                                        </span>
                                        <?php if ($showReturnReviewButton): ?>
                                        <button type="button" class="view-return-btn" data-return-review data-transaction-id="<?= $transactionId ?>" <?= $canOpenReturnReview ? '' : 'disabled' ?>>
                                            <i class="fas fa-image"></i> Review
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>" data-status-badge><?= $statusLabel ?></span>
                                </td>
                                <td>
                                    <span class="approval-badge <?= $approvalBadgeClass ?>" data-approval-badge><?= htmlspecialchars($approvalStatus) ?></span>
                                    <div class="approval-meta" data-approval-meta>
                                        <?php if (!empty($row['approved_by']) && $approvalStatus === 'Approved'): ?>
                                            <small>Admin ID: <?= htmlspecialchars($row['approved_by']) ?> <?= !empty($row['approved_at']) ? '(' . date('M j, Y g:i A', strtotime($row['approved_at'])) . ')' : '' ?></small>
                                        <?php elseif ($approvalStatus === 'Rejected' && !empty($row['rejection_reason'])): ?>
                                            <small class="danger-text">Reason: <?= htmlspecialchars($row['rejection_reason']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($showApprovalActions): ?>
                                        <div class="approval-actions" data-approval-actions>
                                            <button class="approval-btn approve-btn" data-action="approve" data-id="<?= $row['id'] ?>">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="approval-btn reject-btn" data-action="reject" data-id="<?= $row['id'] ?>" data-equipment-name="<?= htmlspecialchars($row['equipment_name']) ?>">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="approval-na" data-approval-na>—</span>
                                    <?php endif; ?>
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

    <div id="rejectionModal" class="approval-modal" role="dialog" aria-modal="true" aria-labelledby="rejectionModalTitle">
        <div class="approval-modal-content">
            <h2 id="rejectionModalTitle">Reject Borrow Request</h2>
            <p id="rejectionModalDesc" style="margin-bottom:12px; color:#444;"></p>
            <label for="rejectionReason" style="font-weight:600; display:block; margin-bottom:6px;">Reason for rejection</label>
            <textarea id="rejectionReason" placeholder="Provide a clear reason..." maxlength="500"></textarea>
            <div id="rejectionError" class="approval-modal-error" role="alert" aria-live="assertive"></div>
            <div class="approval-modal-actions">
                <button type="button" class="approval-cancel-btn" id="rejectionCancelBtn">Cancel</button>
                <button type="button" class="approval-submit-btn" id="rejectionSubmitBtn">Reject Request</button>
            </div>
        </div>
    </div>

    <div id="returnReviewModal" class="approval-modal" role="dialog" aria-modal="true" aria-labelledby="returnReviewTitle">
        <div class="approval-modal-content return-review-modal">
            <div class="return-review-header">
                <h2 id="returnReviewTitle">Return Verification</h2>
                <button type="button" id="returnReviewClose" aria-label="Close review">&times;</button>
            </div>
            <div class="return-review-meta">
                <span class="return-review-equipment" data-review-equipment></span>
                <div class="return-review-status">
                    <span class="return-verification-badge pending" data-review-status-badge>Pending</span>
                    <span class="return-verification-score" data-review-score style="display:none;"></span>
                    <span class="return-verification-score" data-review-reviewstatus style="display:none;"></span>
                </div>
            </div>
            <div class="return-review-gallery">
                <div class="return-review-photo">
                    <span>Reference</span>
                    <div class="return-review-photo-frame">
                        <img data-review-photo="reference" alt="Reference photo">
                        <div class="return-review-placeholder" data-review-placeholder="reference">No photo available</div>
                    </div>
                </div>
                <div class="return-review-photo">
                    <span>Return</span>
                    <div class="return-review-photo-frame">
                        <img data-review-photo="return" alt="Return photo">
                        <div class="return-review-placeholder" data-review-placeholder="return">No photo available</div>
                    </div>
                </div>
            </div>
            <div class="return-review-actions">
                <div class="detected-issues-section">
                    <h4>Detected Issues</h4>
                    <div id="detectedIssuesDisplay" class="detected-issues-content">No issues detected</div>
                </div>
                <div class="return-review-buttons">
                    <button type="button" class="approval-btn approve-btn" data-review-action="verify">Mark Verified</button>
                    <button type="button" class="approval-btn flag-btn" data-review-action="flag">Flag for Review</button>
                    <button type="button" class="approval-btn reject-btn" data-review-action="reject">Reject Return</button>
                </div>
                <div class="return-review-textarea" data-review-notes-container style="display:none;">
                    <span style="font-weight:600;">Additional Notes</span>
                    <textarea id="returnReviewNotes" placeholder="Add additional notes for this action" maxlength="500"></textarea>
                    <div class="return-review-error" data-review-error style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }
        
        const approvalFeedback = document.getElementById('approvalFeedback');
        const rejectionModal = document.getElementById('rejectionModal');
        const rejectionReasonInput = document.getElementById('rejectionReason');
        const rejectionError = document.getElementById('rejectionError');
        const rejectionCancelBtn = document.getElementById('rejectionCancelBtn');
        const rejectionSubmitBtn = document.getElementById('rejectionSubmitBtn');
        const rejectionDesc = document.getElementById('rejectionModalDesc');
        let rejectionTargetId = null;
        const returnReviewModal = document.getElementById('returnReviewModal');
        const returnReviewClose = document.getElementById('returnReviewClose');
        const returnReviewEquipment = document.querySelector('[data-review-equipment]');
        const returnReviewStatusBadge = document.querySelector('[data-review-status-badge]');
        const returnReviewScore = document.querySelector('[data-review-score]');
        const returnReviewStatusText = document.querySelector('[data-review-reviewstatus]');
        const returnReviewNotesContainer = document.querySelector('[data-review-notes-container]');
        const returnReviewNotes = document.getElementById('returnReviewNotes');
        const detectedIssuesDisplay = document.getElementById('detectedIssuesDisplay');
        const returnReviewError = document.querySelector('[data-review-error]');
        const returnReviewPhotos = {
            reference: document.querySelector('[data-review-photo="reference"]'),
            return: document.querySelector('[data-review-photo="return"]')
        };
        const returnReviewPlaceholders = {
            reference: document.querySelector('[data-review-placeholder="reference"]'),
            return: document.querySelector('[data-review-placeholder="return"]'),
            comparison: document.querySelector('[data-review-placeholder="comparison"]')
        };
        const returnReviewDetections = document.querySelector('[data-review-detections]');
        const returnReviewActionButtons = document.querySelectorAll('[data-review-action]');
        let activeReturnReview = null;

        function showFeedback(message, type = 'success') {
            if (!approvalFeedback) return;
            approvalFeedback.textContent = message;
            approvalFeedback.className = `approval-feedback show ${type}`;
            setTimeout(() => {
                approvalFeedback.classList.remove('show');
            }, 4000);
        }

        function applyVerificationBadgeClass(target, status) {
            if (!target) return;
            const normalized = (status || 'Pending').toLowerCase();
            target.classList.remove('pending', 'verified', 'flagged', 'rejected', 'not-returned');
            if (normalized === 'not yet returned') {
                target.classList.add('not-returned');
            } else if (normalized === 'verified') {
                target.classList.add('verified');
            } else if (normalized === 'flagged') {
                target.classList.add('flagged');
            } else if (normalized === 'rejected') {
                target.classList.add('rejected');
            } else {
                target.classList.add('pending');
            }
            target.textContent = status || 'Pending';
        }

        function setReturnReviewPhoto(type, urls) {
            const img = returnReviewPhotos[type];
            const placeholder = returnReviewPlaceholders[type];
            if (!img || !placeholder) return;
            const src = Array.isArray(urls) && urls.length > 0 ? urls[0] : null;
            if (src) {
                img.src = src;
                img.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                img.removeAttribute('src');
                img.style.display = 'none';
                placeholder.style.display = 'flex';
            }
        }

        function getActiveSimilarity() {
            if (!activeReturnReview || activeReturnReview.similarityScore === undefined || activeReturnReview.similarityScore === null) {
                return null;
            }
            const numeric = parseFloat(activeReturnReview.similarityScore);
            return Number.isFinite(numeric) ? numeric : null;
        }

        function determineDetectedIssuesText() {
            let issuesText = (activeReturnReview?.detectedIssues || '').trim();
            const similarity = getActiveSimilarity();
            const itemSize = (activeReturnReview?.itemSize || '').toLowerCase();

            if (!issuesText && similarity !== null && similarity < 70 && itemSize === 'small') {
                issuesText = 'Possible scratches or damage detected.';
            }

            return issuesText;
        }

        function applyDetectedIssuesUI(rawText) {
            const text = (rawText || '').trim();
            // System auto-detected issues - read-only display only
            if (!detectedIssuesDisplay) {
                return;
            }

            const issuesSection = detectedIssuesDisplay.closest('.detected-issues-section');
            const severityClasses = ['severity-none', 'severity-low', 'severity-medium', 'severity-high'];
            if (issuesSection) {
                issuesSection.classList.remove(...severityClasses);
            }

            const similarity = getActiveSimilarity();
            const hasText = text.length > 0;
            let severityClass = 'severity-none';

            if (hasText) {
                if (similarity !== null) {
                    if (similarity < 70) {
                        severityClass = 'severity-high';
                    } else if (similarity < 85) {
                        severityClass = 'severity-medium';
                    } else if (similarity < 95) {
                        severityClass = 'severity-low';
                    } else {
                        severityClass = 'severity-low';
                    }
                } else {
                    severityClass = 'severity-medium';
                }
            }

            if (issuesSection) {
                issuesSection.classList.add(severityClass);

                const iconMap = {
                    'severity-high': 'exclamation-triangle',
                    'severity-medium': 'exclamation-circle',
                    'severity-low': 'info-circle',
                    'severity-none': 'check-circle'
                };

                let icon = issuesSection.querySelector('.severity-icon');
                if (!icon) {
                    icon = document.createElement('i');
                    icon.className = 'fas severity-icon';
                    const heading = issuesSection.querySelector('h4');
                    if (heading) {
                        heading.prepend(icon);
                    }
                }
                if (icon) {
                    icon.className = `fas fa-${iconMap[severityClass] || 'check-circle'} severity-icon`;
                }
            }

            detectedIssuesDisplay.textContent = hasText ? text : 'No issues detected';
            detectedIssuesDisplay.style.display = 'block';
        }

        function syncDetectedIssues(text) {
            applyDetectedIssuesUI(text);
            if (activeReturnReview) {
                activeReturnReview.detectedIssues = text;
            }
        }

        function resetReturnReviewNotes() {
            if (returnReviewNotes) {
                returnReviewNotes.value = '';
            }

            const issuesText = determineDetectedIssuesText();
            syncDetectedIssues(issuesText);

            if (returnReviewError) {
                returnReviewError.textContent = '';
                returnReviewError.style.display = 'none';
            }
        }

        // System auto-detects issues - no manual input needed

        function populateReturnReview(info) {
            if (!info) {
                activeReturnReview = null;
                return;
            }
            activeReturnReview = info;
            if (returnReviewEquipment) {
                returnReviewEquipment.textContent = info.equipmentName || 'Equipment';
            }
            applyVerificationBadgeClass(returnReviewStatusBadge, info.verificationStatus);
            if (returnReviewScore) {
                if (info.similarityScore !== null && info.similarityScore !== undefined) {
                    returnReviewScore.textContent = 'Score: ' + Number(info.similarityScore).toFixed(2) + '%';
                    returnReviewScore.style.display = 'inline-block';
                } else {
                    returnReviewScore.textContent = '';
                    returnReviewScore.style.display = 'none';
                }
            }
            if (returnReviewStatusText) {
                // Remove review status display as requested
                returnReviewStatusText.textContent = '';
                returnReviewStatusText.style.display = 'none';
            }
            setReturnReviewPhoto('reference', info.referencePhotos || []);
            setReturnReviewPhoto('return', info.returnPhotos || []);
            setReturnReviewDetections(info.detections || [], info.similarityScore);
            resetReturnReviewNotes();
            if (returnReviewModal) {
                returnReviewModal.classList.add('show');
            }
        }

        function setReturnReviewDetections(detections = [], similarityScore = null) {
            if (!returnReviewDetections) return;
            const placeholder = returnReviewPlaceholders.comparison;
            const hasDetections = Array.isArray(detections) && detections.length > 0;

            returnReviewDetections.innerHTML = '';

            if (hasDetections) {
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                const list = document.createElement('ul');
                list.className = 'return-review-detections-list';

                detections.forEach((item) => {
                    const entry = document.createElement('li');
                    entry.textContent = item.label ? `${item.label}${item.confidence ? ' (' + item.confidence + '%)' : ''}` : item;
                    list.appendChild(entry);
                });

                if (similarityScore !== null && similarityScore !== undefined) {
                    const scoreItem = document.createElement('li');
                    scoreItem.textContent = `Similarity Score: ${Number(similarityScore).toFixed(2)}%`;
                    scoreItem.className = 'return-review-detections-score';
                    list.appendChild(scoreItem);
                }

                returnReviewDetections.appendChild(list);
            } else {
                if (placeholder) {
                    placeholder.style.display = 'flex';
                }
            }
        }

        function closeReturnReviewModal() {
            if (returnReviewModal) {
                returnReviewModal.classList.remove('show');
            }
            activeReturnReview = null;
        }

        function handleReturnReviewButton(action) {
            if (!action || !activeReturnReview) {
                return;
            }
            if (returnReviewNotesContainer) {
                if (action === 'flag' || action === 'reject') {
                    returnReviewNotesContainer.style.display = 'flex';
                } else {
                    returnReviewNotesContainer.style.display = 'none';
                    if (returnReviewNotes) {
                        returnReviewNotes.value = '';
                    }
                }
            }
        }

        function resetRejectionModal() {
            rejectionTargetId = null;
            if (rejectionReasonInput) rejectionReasonInput.value = '';
            if (rejectionError) rejectionError.textContent = '';
            if (rejectionDesc) rejectionDesc.textContent = '';
        }

        function closeRejectionModal() {
            if (rejectionModal) {
                rejectionModal.classList.remove('show');
                resetRejectionModal();
            }
        }

        function openRejectionModal(transactionId, equipmentName) {
            rejectionTargetId = transactionId;
            if (rejectionDesc) {
                rejectionDesc.textContent = equipmentName
                    ? `Reject borrow request for "${equipmentName}"`
                    : 'Reject this borrow request?';
            }
            if (rejectionModal) {
                rejectionModal.classList.add('show');
            }
        }

        async function sendApprovalRequest(transactionId, action, reason = '') {
            const payload = new FormData();
            payload.append('transaction_id', transactionId);
            payload.append('action', action);
            if (reason) {
                payload.append('reason', reason);
            }

            const response = await fetch('transaction-approval.php', {
                method: 'POST',
                body: payload,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => null);
                throw new Error(errorData?.message || 'Request failed');
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Unable to update transaction');
            }
            return data;
        }

        const statusMap = {
            'Active': { label: 'Active', class: 'borrow' },
            'Returned': { label: 'Returned', class: 'return' },
            'Overdue': { label: 'Overdue', class: 'violation' },
            'Rejected': { label: 'Rejected', class: 'rejected' },
            'Pending Review': { label: 'Pending Review', class: 'violation' },
            'Pending Approval': { label: 'Pending Approval', class: 'borrow' }
        };

        function updateInventoryStatusFromData(data) {
            if (!data || !data.inventory) return;
            const { availability_status } = data.inventory;
            const row = document.querySelector(`#txn-${data.transaction?.id || data.transaction_id}`);
            const equipmentCard = row ? document.querySelector(`[data-equipment-card="${row.dataset.equipmentName}"]`) : null;
            if (!equipmentCard) return;
            const statusLabel = equipmentCard.querySelector('[data-availability-status]');
            if (!statusLabel) return;
            statusLabel.textContent = availability_status || statusLabel.textContent;
            statusLabel.classList.remove('available', 'low-stock', 'out-of-stock');
            if (availability_status === 'Low Stock') {
                statusLabel.classList.add('low-stock');
            } else if (availability_status === 'Out of Stock') {
                statusLabel.classList.add('out-of-stock');
            } else {
                statusLabel.classList.add('available');
            }
        }

        function applyStatusBadge(statusBadgeEl, statusValue) {
            if (!statusBadgeEl) return;
            const info = statusMap[statusValue] || { label: statusValue || 'Active', class: 'borrow' };
            statusBadgeEl.textContent = info.label.toUpperCase();
            statusBadgeEl.classList.remove('borrow', 'return', 'violation', 'rejected');
            statusBadgeEl.classList.add(info.class);
        }

        document.addEventListener('click', (event) => {
            const reviewTrigger = event.target.closest('[data-return-review]');
            if (reviewTrigger) {
                const row = reviewTrigger.closest('tr');
                if (!row) {
                    return;
                }
                let parsed = null;
                try {
                    parsed = JSON.parse(row.dataset.returnInfo || '{}');
                } catch (err) {
                    parsed = null;
                }
                if (!parsed || !parsed.transactionId) {
                    return;
                }
                parsed.rowId = row.id;
                populateReturnReview(parsed);
                return;
            }
            if (event.target === returnReviewModal) {
                closeReturnReviewModal();
            }
        });

        if (returnReviewClose) {
            returnReviewClose.addEventListener('click', () => {
                closeReturnReviewModal();
            });
        }

        function setReturnReviewButtonsDisabled(disabled) {
            returnReviewActionButtons.forEach((btn) => {
                btn.disabled = !!disabled;
            });
        }

        async function submitReturnReview(action) {
            if (!activeReturnReview) {
                return;
            }
            if (!action) {
                return;
            }
            let notes = '';
            const requiresNotes = action === 'flag' || action === 'reject';
            if (requiresNotes && returnReviewNotesContainer) {
                returnReviewNotesContainer.style.display = 'flex';
            }
            if (requiresNotes) {
                notes = (returnReviewNotes?.value || '').trim();
                if (!notes) {
                    if (returnReviewError) {
                        returnReviewError.textContent = 'Please provide notes before flagging or rejecting this return.';
                        returnReviewError.style.display = 'block';
                    }
                    if (returnReviewNotes) {
                        returnReviewNotes.focus();
                    }
                    return;
                }
            } else {
                if (returnReviewNotesContainer) {
                    returnReviewNotesContainer.style.display = 'none';
                }
            }
            if (returnReviewError) {
                returnReviewError.textContent = '';
                returnReviewError.style.display = 'none';
            }

            const payload = new FormData();
            payload.append('transaction_id', activeReturnReview.transactionId);
            payload.append('action', action);
            
            // System auto-detects issues - no manual input needed
            
            // Add notes for flag/reject actions
            if (['flag', 'reject'].includes(action) && returnReviewNotes) {
                const notes = returnReviewNotes.value.trim();
                if (notes) {
                    payload.append('notes', notes);
                }
            }

            try {
                setReturnReviewButtonsDisabled(true);
                const response = await fetch('return-verification.php', {
                    method: 'POST',
                    body: payload,
                    credentials: 'same-origin'
                });
                if (!response.ok) {
                    throw new Error('Failed to process review');
                }
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Unable to update return verification');
                }
                const row = document.getElementById(activeReturnReview.rowId || `txn-${activeReturnReview.transactionId}`);
                if (row) {
                    updateRowReturnVerification(row, data.transaction || {}, data.display || {});
                }
                showFeedback(data.message || 'Return verification updated');
                closeReturnReviewModal();
            } catch (err) {
                if (returnReviewError) {
                    returnReviewError.textContent = err.message || 'Something went wrong.';
                    returnReviewError.style.display = 'block';
                }
            } finally {
                setReturnReviewButtonsDisabled(false);
            }
        }

        returnReviewActionButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                submitReturnReview(btn.dataset.reviewAction);
            });
        });

        function deriveRowStatusFlag(status) {
            const normalized = (status || '').toLowerCase();
            if (normalized === 'returned') {
                return 'returned';
            }
            if (normalized === 'overdue') {
                return 'overdue';
            }
            return 'borrowed';
        }

        function updateRowReturnVerification(row, transaction = {}, display = {}) {
            let verificationStatus = transaction.return_verification_status || display.verification_status || 'Pending';
            let reviewStatus = transaction.return_review_status || display.review_status || transaction.return_review_status || 'Pending';
            const finalStatus = transaction.status || display.status || 'Active';
            let similarityScore = transaction.similarity_score ?? display.similarity_score ?? null;
            const statusLower = (finalStatus || '').toLowerCase();
            const forceNotReturned = ['active','pending approval','overdue','lost','damaged'].includes(statusLower);

            if (forceNotReturned) {
                verificationStatus = 'Not Yet Returned';
                reviewStatus = 'Not Yet Returned';
                similarityScore = null;
            }

            const badge = row.querySelector('[data-return-verification-badge]');
            const scoreEl = row.querySelector('[data-return-verification-score]');
            const statusBadge = row.querySelector('[data-status-badge]');

            applyVerificationBadgeClass(badge, verificationStatus);
            if (scoreEl) {
                if (similarityScore !== null && similarityScore !== undefined) {
                    scoreEl.textContent = 'Score: ' + Number(similarityScore).toFixed(2) + '%';
                    scoreEl.style.display = 'inline-block';
                } else {
                    scoreEl.textContent = '';
                    scoreEl.style.display = 'none';
                }
            }

            applyStatusBadge(statusBadge, finalStatus);
            row.dataset.status = deriveRowStatusFlag(finalStatus);

            let existingInfo = {};
            try {
                existingInfo = JSON.parse(row.dataset.returnInfo || '{}');
            } catch (err) {
                existingInfo = {};
            }

            existingInfo.verificationStatus = verificationStatus;
            existingInfo.reviewStatus = reviewStatus;
            const detectedIssuesUpdated = transaction.detected_issues ?? display.detected_issues ?? existingInfo.detectedIssues;
            if (detectedIssuesUpdated && detectedIssuesUpdated !== '') {
                existingInfo.detectedIssues = detectedIssuesUpdated;
            } else {
                delete existingInfo.detectedIssues;
            }
            if (similarityScore !== null && similarityScore !== undefined) {
                existingInfo.similarityScore = Number(similarityScore);
            } else {
                delete existingInfo.similarityScore;
            }
            existingInfo.status = finalStatus;

            row.dataset.returnInfo = JSON.stringify(existingInfo);
        }

        function updateRowAfterApproval(row, data, action) {
            if (!row) return;
            const badge = row.querySelector('[data-approval-badge]');
            const meta = row.querySelector('[data-approval-meta]');
            const actions = row.querySelector('[data-approval-actions]');
            const naPlaceholder = row.querySelector('[data-approval-na]');
            const statusBadge = row.querySelector('[data-status-badge]');
            const transaction = data.transaction || {};

            if (badge) {
                const badgeStatus = transaction.approval_status || (action === 'approve' ? 'Approved' : 'Rejected');
                badge.textContent = badgeStatus;
                badge.classList.remove('pending', 'approved', 'rejected');
                badge.classList.add(badgeStatus === 'Approved' ? 'approved' : badgeStatus === 'Rejected' ? 'rejected' : 'pending');
            }

            if (meta) {
                meta.innerHTML = '';
                if (action === 'approve') {
                    const approvedInfo = document.createElement('small');
                    const approvedBy = transaction.approved_by ? `ID: ${transaction.approved_by}` : (data.approver_username ? data.approver_username : 'Admin');
                    const approvedAt = data.approved_at_display ? ` (${data.approved_at_display})` : '';
                    approvedInfo.textContent = `Admin ${approvedBy}${approvedAt}`;
                    meta.appendChild(approvedInfo);
                } else if (action === 'reject') {
                    const rejectionInfo = document.createElement('small');
                    rejectionInfo.className = 'danger-text';
                    const reason = transaction.rejection_reason || data.rejection_reason || 'Not specified';
                    rejectionInfo.textContent = `Reason: ${reason}`;
                    meta.appendChild(rejectionInfo);
                }
            }

            if (actions) {
                actions.remove();
            }
            if (naPlaceholder) {
                naPlaceholder.textContent = '—';
            }

            row.dataset.approvalStatus = (action === 'approve') ? 'Approved' : 'Rejected';
            if (transaction.status && statusBadge) {
                applyStatusBadge(statusBadge, transaction.status);
            }
            updateInventoryStatusFromData(data);
        }

        document.addEventListener('click', async (event) => {
            const approveBtn = event.target.closest('[data-action="approve"]');
            const rejectBtn = event.target.closest('[data-action="reject"]');

            if (approveBtn) {
                const transactionId = approveBtn.dataset.id;
                const row = approveBtn.closest('tr');
                const equipmentName = row?.dataset.equipmentName || 'this item';

                approveBtn.disabled = true;
                const rejectSibling = row?.querySelector('[data-action="reject"]');
                if (rejectSibling) rejectSibling.disabled = true;

                try {
                    const result = await sendApprovalRequest(transactionId, 'approve');
                    updateRowAfterApproval(row, result, 'approve');
                    showFeedback(`Approved borrow request for ${equipmentName}.`, 'success');
                } catch (err) {
                    console.error(err);
                    showFeedback(err.message || 'Failed to approve request.', 'error');
                    approveBtn.disabled = false;
                    if (rejectSibling) rejectSibling.disabled = false;
                }
            }

            if (rejectBtn) {
                const transactionId = rejectBtn.dataset.id;
                const row = rejectBtn.closest('tr');
                const equipmentName = row?.dataset.equipmentName || rejectBtn.dataset.equipmentName || '';
                openRejectionModal(transactionId, equipmentName);
            }
        });

        if (rejectionCancelBtn) {
            rejectionCancelBtn.addEventListener('click', closeRejectionModal);
        }

        if (rejectionModal) {
            rejectionModal.addEventListener('click', (event) => {
                if (event.target === rejectionModal) {
                    closeRejectionModal();
                }
            });
        }

        if (rejectionSubmitBtn) {
            rejectionSubmitBtn.addEventListener('click', async () => {
                if (!rejectionTargetId) {
                    showFeedback('No transaction selected.', 'error');
                    return;
                }
                const reason = rejectionReasonInput?.value.trim();
                if (!reason) {
                    if (rejectionError) {
                        rejectionError.textContent = 'Please provide a reason for rejecting this request.';
                    }
                    return;
                }
                rejectionSubmitBtn.disabled = true;
                try {
                    const result = await sendApprovalRequest(rejectionTargetId, 'reject', reason);
                    const row = document.querySelector(`#txn-${rejectionTargetId}`);
                    updateRowAfterApproval(row, result, 'reject');
                    showFeedback('Borrow request rejected.', 'success');
                    closeRejectionModal();
                } catch (err) {
                    console.error(err);
                    if (rejectionError) {
                        rejectionError.textContent = err.message || 'Failed to reject request.';
                    }
                } finally {
                    rejectionSubmitBtn.disabled = false;
                }
            });
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
