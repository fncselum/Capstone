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
                $user_name_col
                $student_id_col,
                t.approved_by,
                inv.availability_status AS inventory_status,
                inv.available_quantity AS inventory_available_qty,
                inv.borrowed_quantity AS inventory_borrowed_qty
         FROM transactions t
         LEFT JOIN equipment e ON t.equipment_id = e.id
         LEFT JOIN users u ON t.user_id = u.id
         LEFT JOIN inventory inv ON e.rfid_tag = inv.equipment_id
         ORDER BY t.transaction_date DESC";
} else {
    // Fallback if users table doesn't exist - use rfid_id from transactions
    $query = "SELECT t.*, 
                COALESCE(t.transaction_date, t.created_at) AS txn_datetime,
                e.name as equipment_name,
                t.rfid_id as student_id,
                t.approved_by,
                inv.availability_status AS inventory_status,
                inv.available_quantity AS inventory_available_qty,
                inv.borrowed_quantity AS inventory_borrowed_qty
         FROM transactions t
         LEFT JOIN equipment e ON t.equipment_id = e.id
         LEFT JOIN inventory inv ON e.rfid_tag = inv.equipment_id
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
                                <th>Expected Return</th>
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
                            ?>
                            <tr id="<?= $rowId ?>" data-status="<?= $status ?>" data-item-size="<?= htmlspecialchars(strtolower($row['item_size'] ?? '')) ?>" data-approval-status="<?= htmlspecialchars($approvalStatus) ?>" data-equipment-name="<?= htmlspecialchars($row['equipment_name']) ?>">
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
                                    <?php if (!empty($row['expected_return_date'])): ?>
                                        <?= date('M j, Y g:i A', strtotime($row['expected_return_date'])) ?>
                                    <?php else: ?>
                                        <span style="color:#999;">N/A</span>
                                    <?php endif; ?>
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

        function showFeedback(message, type = 'success') {
            if (!approvalFeedback) return;
            approvalFeedback.textContent = message;
            approvalFeedback.className = `approval-feedback show ${type}`;
            setTimeout(() => {
                approvalFeedback.classList.remove('show');
            }, 4000);
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
            'Rejected': { label: 'Rejected', class: 'rejected' }
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
