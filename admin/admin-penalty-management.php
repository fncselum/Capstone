<?php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Simple authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Include penalty system
require_once 'penalty-system.php';

// Database connection
$host = "localhost";
$user = "root";       
$password = "";   // no password for XAMPP
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize penalty system
$penaltySystem = new PenaltySystem($conn);

// Get active guidelines for dropdown
$activeGuidelines = $penaltySystem->getActiveGuidelines();

// Handle success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Handle penalty operations
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_penalty') {
        $rfid_id = trim($_POST['rfid_id'] ?? '');
        $transaction_id = (int)($_POST['transaction_id'] ?? 0);
        $equipment_id = (int)($_POST['equipment_id'] ?? 0);
        $equipment_name = trim($_POST['equipment_name'] ?? '');
        $penalty_type = $_POST['penalty_type'] ?? '';
        $penalty_amount = (float)($_POST['penalty_amount'] ?? 0);
        $penalty_points = (int)($_POST['penalty_points'] ?? 0);
        $violation_date = $_POST['violation_date'] ?? date('Y-m-d');
        $days_overdue = (int)($_POST['days_overdue'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $notes = $_POST['notes'] ?? '';
        $guideline_id = !empty($_POST['guideline_id']) ? (int)$_POST['guideline_id'] : null;

        if ($rfid_id && $transaction_id && $equipment_id && $equipment_name && in_array($penalty_type, ['Late Return','Overdue','Damage','Damaged','Loss','Lost','Misuse','Other'])) {
            $penalty_data = [
                'penalty_type' => $penalty_type,
                'penalty_amount' => $penalty_amount,
                'penalty_points' => $penalty_points,
                'days_overdue' => $days_overdue,
                'violation_date' => $violation_date,
                'description' => $description
            ];
            if ($penaltySystem->createPenalty($transaction_id, $rfid_id, $equipment_id, $equipment_name, $penalty_data, $notes, $guideline_id)) {
                $success_message = "Penalty created and set to Pending immediately.";
            } else {
                $error_message = "Failed to create penalty: " . $conn->error;
            }
        } else {
            $error_message = "Please fill out all required fields correctly.";
        }
    }
    if ($_POST['action'] === 'update_status' && isset($_POST['penalty_id'], $_POST['status'])) {
        $penalty_id = (int)$_POST['penalty_id'];
        $status = $conn->real_escape_string($_POST['status']);
        $payment_method = isset($_POST['payment_method']) ? $conn->real_escape_string($_POST['payment_method']) : null;
        $notes = isset($_POST['notes']) ? $conn->real_escape_string($_POST['notes']) : '';
        
        if ($penaltySystem->updatePenaltyStatus($penalty_id, $status, $payment_method, $notes)) {
            $success_message = "Penalty status updated successfully!";
        } else {
            $error_message = "Failed to update penalty status: " . $conn->error;
        }
    }
    
    if ($_POST['action'] === 'auto_calculate') {
        $penalties_created = $penaltySystem->autoCalculateOverduePenalties();
        $success_message = "Auto-calculation completed! Created $penalties_created new penalties.";
    }
}

// Handle filtering
$filters = [
    'status' => $_GET['status'] ?? 'all',
    'type' => $_GET['type'] ?? 'all',
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get penalties using the new method
$penalties_result = $penaltySystem->getPenalties($filters);

// Get penalty statistics
$stats = $penaltySystem->getPenaltyStatistics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="admin-styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
                <li class="nav-item">
                    <a href="admin-all-transaction.php"><i class="fas fa-exchange-alt"></i><span>All Transactions</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-user-activity.php"><i class="fas fa-users"></i><span>User Activity</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-penalty-guideline.php"><i class="fas fa-exclamation-triangle"></i><span>Penalty Guidelines</span></a>
                </li>
                <li class="nav-item active">
                    <a href="admin-penalty-management.php"><i class="fas fa-gavel"></i><span>Penalty Management</span></a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </button>
            </div>
        </nav>

        <!-- Main -->
        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Penalty Management</h1>
                <div class="header-actions">
                    <button type="button" class="btn btn-success" onclick="openCreatePenalty()">
                        <i class="fas fa-plus"></i>
                        Create Penalty
                    </button>
                    <button type="button" class="btn btn-info" onclick="openQuickPenalty()">
                        <i class="fas fa-bolt"></i>
                        Quick Penalty
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="auto_calculate">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calculator"></i>
                            Auto-Calculate Penalties
                        </button>
                    </form>
                    <a href="setup_penalties_database.php" class="btn btn-secondary">
                        <i class="fas fa-database"></i>
                        Setup Database
                    </a>
                </div>
            </header>

            <!-- Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Penalty Statistics -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-bar"></i> Penalty Statistics</h2>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?= $stats['by_status']['Pending']['count'] ?? 0 ?></h3>
                            <p class="stat-label">Pending Penalties</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number">â‚±<?= number_format($stats['total_pending'], 2) ?></h3>
                            <p class="stat-label">Total Pending Amount</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number"><?= $stats['by_status']['Paid']['count'] ?? 0 ?></h3>
                            <p class="stat-label">Paid Penalties</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-number">â‚±<?= number_format($stats['total_collected'], 2) ?></h3>
                            <p class="stat-label">Total Collected</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Penalty Filters -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-filter"></i> Filter Penalties</h2>
                </div>

                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Paid" <?= $status_filter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="Waived" <?= $status_filter === 'Waived' ? 'selected' : '' ?>>Waived</option>
                            <option value="Under Review" <?= $status_filter === 'Under Review' ? 'selected' : '' ?>>Under Review</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="type">Penalty Type:</label>
                        <select name="type" id="type">
                            <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="Overdue" <?= $type_filter === 'Overdue' ? 'selected' : '' ?>>Overdue</option>
                            <option value="Damaged" <?= $type_filter === 'Damaged' ? 'selected' : '' ?>>Damaged</option>
                            <option value="Lost" <?= $type_filter === 'Lost' ? 'selected' : '' ?>>Lost</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>

                    <a href="admin-penalty-management.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </form>
            </section>

            <!-- Penalties Table -->
            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Penalties List</h2>
                </div>

                <?php if ($penalties_result && $penalties_result->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="penalties-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Equipment</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Days Overdue</th>
                                    <th>Violation Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($penalty = $penalties_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="student-info">
                                                <strong>RFID</strong><br>
                                                <small class="rfid-id"><?= htmlspecialchars($penalty['rfid_id']) ?></small>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($penalty['equipment_name']) ?></td>
                                        <td>
                                            <span class="badge penalty-type <?= strtolower($penalty['penalty_type']) ?>">
                                                <?= htmlspecialchars($penalty['penalty_type']) ?>
                                            </span>
                                        </td>
                                        <td class="amount">â‚±<?= number_format($penalty['penalty_amount'], 2) ?></td>
                                        <td><?= $penalty['days_overdue'] ?></td>
                                        <td><?= date('M d, Y', strtotime($penalty['violation_date'])) ?></td>
                                        <td>
                                            <span class="badge status <?= strtolower(str_replace(' ', '-', $penalty['status'])) ?>">
                                                <?= htmlspecialchars($penalty['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-small btn-primary" onclick="viewPenaltyDetails(<?= $penalty['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($penalty['status'] === 'Pending'): ?>
                                                    <button class="btn btn-small btn-success" onclick="updatePenaltyStatus(<?= $penalty['id'] ?>, 'Paid')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-small btn-warning" onclick="updatePenaltyStatus(<?= $penalty['id'] ?>, 'Waived')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-gavel fa-3x"></i>
                        <h3>No Penalties Found</h3>
                        <p>No penalties match your current filter criteria.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Penalty Details Modal -->
    <div id="penaltyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Penalty Details</h2>
            <div id="penaltyDetails"></div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Update Penalty Status</h2>
            <form id="statusForm" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="penalty_id" id="status_penalty_id">
                
                <div class="form-group">
                    <label for="status_select">Status:</label>
                    <select name="status" id="status_select" required>
                        <option value="Pending">Pending</option>
                        <option value="Paid">Paid</option>
                        <option value="Waived">Waived</option>
                        <option value="Under Review">Under Review</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Payment Method:</label>
                    <select name="payment_method" id="payment_method">
                        <option value="">Select payment method</option>
                        <option value="Cash">Cash</option>
                        <option value="Check">Check</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Credit Card">Credit Card</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea name="notes" id="notes" rows="3" placeholder="Additional notes..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Penalty Modal -->
    <div id="createPenaltyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Create Penalty</h2>
            <form id="createPenaltyForm" method="POST">
                <input type="hidden" name="action" value="create_penalty">

                <div class="form-group">
                    <label for="rfid_id">Borrower RFID ID</label>
                    <input type="text" id="rfid_id" name="rfid_id" required placeholder="Enter RFID ID">
                    <button type="button" class="btn btn-small btn-secondary" onclick="searchByRFID()">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>

                <div class="form-group">
                    <label for="transaction_id">Transaction ID</label>
                    <input type="number" id="transaction_id" name="transaction_id" min="1" required placeholder="Enter Transaction ID">
                    <button type="button" class="btn btn-small btn-secondary" onclick="loadTransactionDetails()">
                        <i class="fas fa-download"></i> Load Details
                    </button>
                </div>

                <div class="form-group">
                    <label for="equipment_id">Equipment ID</label>
                    <input type="number" id="equipment_id" name="equipment_id" min="1" required placeholder="Auto-filled from transaction">
                </div>

                <div class="form-group">
                    <label for="equipment_name">Equipment Name</label>
                    <input type="text" id="equipment_name" name="equipment_name" required placeholder="Auto-filled from transaction">
                </div>

                <div class="form-group">
                    <label for="penalty_type">Penalty Type</label>
                    <select id="penalty_type" name="penalty_type" required onchange="updatePenaltyAmount()">
                        <option value="">Select penalty type</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Damaged">Damaged</option>
                        <option value="Lost">Lost</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="penalty_amount">Amount (â‚±)</label>
                    <input type="number" id="penalty_amount" name="penalty_amount" step="0.01" min="0" value="0.00" required>
                    <small class="form-help">Amount will be auto-calculated for overdue penalties</small>
                </div>

                <div class="form-group" id="days_overdue_group">
                    <label for="days_overdue">Days Overdue (for Overdue type)</label>
                    <input type="number" id="days_overdue" name="days_overdue" min="0" value="0" onchange="calculateOverduePenalty()">
                </div>

                <div class="form-group">
                    <label for="violation_date">Violation Date</label>
                    <input type="date" id="violation_date" name="violation_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Additional notes about the penalty..."></textarea>
                </div>

                <div class="penalty-preview" id="penalty_preview" style="display: none;">
                    <h4>Penalty Preview</h4>
                    <div id="preview_content"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Create & Apply Penalty
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Penalty Modal -->
    <div id="quickPenaltyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Quick Penalty Creation</h2>
            <p>Select from active borrowed transactions that may need penalties:</p>
            
            <div id="quick_penalty_list">
                <div class="loading">Loading active transactions...</div>
            </div>
        </div>
    </div>

    <style>
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .filter-form {
            display: flex;
            gap: 20px;
            align-items: end;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #333;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .penalties-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .penalties-table th,
        .penalties-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .penalties-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .penalties-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .student-info {
            line-height: 1.4;
        }
        
        .student-info small {
            color: #666;
        }
        
        .rfid-id {
            font-family: monospace;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .badge.penalty-type {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge.penalty-type.overdue {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge.penalty-type.damaged {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge.penalty-type.lost {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge.status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge.status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge.status.paid {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.status.waived {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .badge.status.under-review {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .amount {
            font-weight: 600;
            color: #dc3545;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 6px 10px;
            font-size: 0.8rem;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            position: relative;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .form-help {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
            display: block;
        }
        
        .penalty-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .penalty-preview h4 {
            margin: 0 0 10px 0;
            color: #495057;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .quick-penalty-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .quick-penalty-info {
            flex: 1;
        }
        
        .quick-penalty-info h5 {
            margin: 0 0 5px 0;
            color: #495057;
        }
        
        .quick-penalty-info small {
            color: #666;
            display: block;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
    </style>

    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }
        
        function viewPenaltyDetails(penaltyId) {
            // This would typically fetch penalty details via AJAX
            // For now, we'll show a simple message
            document.getElementById('penaltyDetails').innerHTML = 
                '<p>Penalty details for ID: ' + penaltyId + '</p>' +
                '<p>This would show detailed penalty information including student details, equipment info, violation details, etc.</p>';
            document.getElementById('penaltyModal').style.display = 'block';
        }
        
        function openCreatePenalty() {
            document.getElementById('createPenaltyModal').style.display = 'block';
        }
        
        function openQuickPenalty() {
            document.getElementById('quickPenaltyModal').style.display = 'block';
            loadQuickPenaltyList();
        }

        // Prefill Create Penalty modal from a Quick Penalty item
        function createQuickPenalty(transactionId, rfidId, daysOverdue, equipmentName, equipmentId) {
            try {
                // Fill core fields
                const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
                setVal('transaction_id', transactionId || '');
                setVal('rfid_id', rfidId || '');
                setVal('equipment_id', equipmentId || '');
                setVal('equipment_name', equipmentName || '');

                // Default to Overdue penalty, show days overdue and compute amount
                setVal('penalty_type', 'Overdue');
                const days = parseInt(daysOverdue || 0) || 0;
                setVal('days_overdue', days);
                const daysGroup = document.getElementById('days_overdue_group');
                if (daysGroup) daysGroup.style.display = 'block';
                if (typeof calculateOverduePenalty === 'function') {
                    calculateOverduePenalty();
                }

                // Close quick list and open the main create form
                const quick = document.getElementById('quickPenaltyModal');
                const create = document.getElementById('createPenaltyModal');
                if (quick) quick.style.display = 'none';
                if (create) create.style.display = 'block';

                // Update preview if available
                if (typeof updatePenaltyPreview === 'function') {
                    updatePenaltyPreview();
                }
            } catch (e) {
                console.error('Failed to create quick penalty:', e);
                alert('Unable to open Create Penalty form. Please try again.');
            }
        }
        
        function loadQuickPenaltyList() {
            const listContainer = document.getElementById('quick_penalty_list');
            listContainer.innerHTML = '<div class="loading">Loading overdue transactions...</div>';
            
            fetch('ajax_penalty_helpers.php?action=get_overdue_transactions')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        listContainer.innerHTML = `<div class="alert alert-error">${data.error}</div>`;
                        return;
                    }

                    if (!Array.isArray(data.transactions) || data.transactions.length === 0) {
                        listContainer.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-check-circle fa-3x" style="margin-bottom: 15px;"></i>
                                <h3>No Overdue Transactions</h3>
                                <p>All borrowed items are returned on time or already have penalties assigned.</p>
                            </div>
                        `;
                        return;
                    }

                    let html = '';
                    data.transactions.forEach(transaction => {
                        const dueDate = transaction.due_date || transaction.planned_return || 'Not set';
                        const daysOverdue = transaction.days_overdue || 0;
                        html += `
                            <div class="quick-penalty-item">
                                <div class="quick-penalty-info">
                                    <h5>${transaction.equipment_name}</h5>
                                    <small>RFID: ${transaction.rfid_id}</small><br>
                                    <small>Due: ${dueDate}</small><br>
                                    <small>Days overdue: ${daysOverdue}</small>
                                </div>
                                <button class="btn btn-warning" onclick="createQuickPenalty(${transaction.id}, '${transaction.rfid_id}', ${daysOverdue}, '${transaction.equipment_name}', ${transaction.equipment_id})">
                                    <i class="fas fa-exclamation-triangle"></i> Create Penalty
                                </button>
                            </div>
                        `;
                    });

                    listContainer.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading transaction details:', error);
                    alert('Error loading transaction details. Please try again.');
                });
        }
        
        function updatePenaltyAmount() {
            const penaltyType = document.getElementById('penalty_type').value;
            const daysOverdueGroup = document.getElementById('days_overdue_group');
            
            if (penaltyType === 'Overdue') {
                daysOverdueGroup.style.display = 'block';
                calculateOverduePenalty();
            } else {
                daysOverdueGroup.style.display = 'none';
                document.getElementById('penalty_amount').value = '0.00';
            }
            
            updatePenaltyPreview();
        }

        function toggleAdvanced() {
            const adv = document.getElementById('advanced_fields');
            if (!adv) return;
            adv.style.display = (adv.style.display === 'none' || adv.style.display === '') ? 'block' : 'none';
        }
        
        function calculateOverduePenalty() {
            const daysOverdue = parseInt(document.getElementById('days_overdue').value) || 0;
            
            // Get penalty settings from server
            fetch('ajax_penalty_helpers.php?action=get_penalty_settings')
                .then(response => response.json())
                .then(data => {
                    if (data.settings) {
                        const dailyRate = parseFloat(data.settings.overdue_daily_rate) || 10.00;
                        const gracePeriod = parseInt(data.settings.grace_period_days) || 0;
                        const maxPenalty = parseFloat(data.settings.max_penalty_amount) || 5000.00;
                        
                        const effectiveDays = Math.max(0, daysOverdue - gracePeriod);
                        const penaltyAmount = Math.min(effectiveDays * dailyRate, maxPenalty);
                        
                        document.getElementById('penalty_amount').value = penaltyAmount.toFixed(2);
                        updatePenaltyPreview();
                    }
                })
                .catch(error => {
                    console.error('Error getting penalty settings:', error);
                    // Fallback to default calculation
                    const dailyRate = 10.00;
                    const penaltyAmount = daysOverdue * dailyRate;
                    document.getElementById('penalty_amount').value = penaltyAmount.toFixed(2);
                    updatePenaltyPreview();
                });
        }
        
        function updatePenaltyPreview() {
            const penaltyType = document.getElementById('penalty_type').value;
            const penaltyAmount = parseFloat(document.getElementById('penalty_amount').value) || 0;
            const daysOverdue = parseInt(document.getElementById('days_overdue').value) || 0;
            const equipmentName = document.getElementById('equipment_name').value;
            const rfidId = document.getElementById('rfid_id').value;
            
            if (penaltyType && penaltyAmount > 0) {
                let previewContent = `
                    <p><strong>Penalty Type:</strong> ${penaltyType}</p>
                    <p><strong>Amount:</strong> â‚±${penaltyAmount.toFixed(2)}</p>
                    <p><strong>Equipment:</strong> ${equipmentName}</p>
                    <p><strong>RFID ID:</strong> ${rfidId}</p>
                `;
                
                if (penaltyType === 'Overdue' && daysOverdue > 0) {
                    previewContent += `<p><strong>Days Overdue:</strong> ${daysOverdue}</p>`;
                }
                
                previewContent += `<p><em>This penalty will be applied immediately upon confirmation.</em></p>`;
                
                document.getElementById('preview_content').innerHTML = previewContent;
                document.getElementById('penalty_preview').style.display = 'block';
            } else {
                document.getElementById('penalty_preview').style.display = 'none';
            }
        }

        function updatePenaltyStatus(penaltyId, status) {
            document.getElementById('status_penalty_id').value = penaltyId;
            document.getElementById('status_select').value = status;
            document.getElementById('statusModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('penaltyModal').style.display = 'none';
            document.getElementById('statusModal').style.display = 'none';
            document.getElementById('createPenaltyModal').style.display = 'none';
            document.getElementById('quickPenaltyModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const penaltyModal = document.getElementById('penaltyModal');
            const statusModal = document.getElementById('statusModal');
            const createPenaltyModal = document.getElementById('createPenaltyModal');
            const quickPenaltyModal = document.getElementById('quickPenaltyModal');
            
            if (event.target === penaltyModal) {
                penaltyModal.style.display = 'none';
            }
            if (event.target === statusModal) {
                statusModal.style.display = 'none';
            }
            if (event.target === createPenaltyModal) {
                createPenaltyModal.style.display = 'none';
            }
            if (event.target === quickPenaltyModal) {
                quickPenaltyModal.style.display = 'none';
            }
        }
        
        // Initialize form when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Hide days overdue group initially
            document.getElementById('days_overdue_group').style.display = 'none';
            
            // Sidebar toggle functionality
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
        
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }
    </script>
</body>
</html>
