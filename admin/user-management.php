<?php
session_start();

// Simple authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database configuration
require_once '../config/database.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle user status updates
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $user_id = (int)$_POST['user_id'];
        $new_status = $_POST['status'];
        
        $update_sql = "UPDATE users SET status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $user_id);
        
        if ($update_stmt->execute()) {
            $success_message = "User status updated successfully";
        } else {
            $error_message = "Failed to update user status";
        }
        $update_stmt->close();
    }
}

// Get all registered users
$users_sql = "SELECT 
                id,
                rfid_tag,
                status,
                penalty_points,
                total_borrows,
                total_returns,
                first_scan_date,
                last_activity
              FROM users 
              ORDER BY last_activity DESC";

$users_result = $conn->query($users_sql);
$users = [];
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
    $users_result->free();
}

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN status = 'Suspended' THEN 1 ELSE 0 END) as suspended_users,
                SUM(total_borrows) as total_borrows,
                SUM(total_returns) as total_returns
              FROM users";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Capstone Equipment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .privacy-note {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
        }
        .user-card {
            transition: transform 0.2s;
        }
        .user-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block bg-dark sidebar">
                <div class="sidebar-sticky pt-3">
                    <h6 class="sidebar-heading text-muted px-3 mt-4 mb-1">
                        <span>Equipment System</span>
                    </h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin-dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="user-management.php">
                                <i class="fas fa-users"></i> User Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin-equipment-inventory.php">
                                <i class="fas fa-boxes"></i> Equipment
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin-all-transaction.php">
                                <i class="fas fa-exchange-alt"></i> Transactions
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ml-sm-auto px-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">User Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- Privacy Notice -->
                <div class="privacy-note">
                    <h6><i class="fas fa-shield-alt"></i> Privacy-Focused System</h6>
                    <p class="mb-0">This system only collects RFID tags from student ID cards. No personal information (names, emails, etc.) is stored to protect student privacy. Users are automatically registered when they first scan their ID card.</p>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['total_users'] ?? 0; ?></h4>
                                        <p class="mb-0">Total Registered</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-id-card fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['active_users'] ?? 0; ?></h4>
                                        <p class="mb-0">Active Users</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-user-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['suspended_users'] ?? 0; ?></h4>
                                        <p class="mb-0">Suspended</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-user-slash fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo ($stats['total_borrows'] ?? 0) - ($stats['total_returns'] ?? 0); ?></h4>
                                        <p class="mb-0">Active Borrows</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-hand-holding fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Registered RFID Tags</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>User ID</th>
                                        <th>RFID Tag</th>
                                        <th>Status</th>
                                        <th>Penalty Points</th>
                                        <th>Total Borrows</th>
                                        <th>Total Returns</th>
                                        <th>First Registered</th>
                                        <th>Last Activity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                No RFID tags registered yet. Users will be automatically registered when they first scan their ID card.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><strong>User-<?php echo $user['id']; ?></strong></td>
                                                <td><code><?php echo htmlspecialchars($user['rfid_tag']); ?></code></td>
                                                <td>
                                                    <?php
                                                    $status_class = [
                                                        'Active' => 'success',
                                                        'Inactive' => 'secondary',
                                                        'Suspended' => 'danger'
                                                    ][$user['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class; ?> status-badge">
                                                        <?php echo $user['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($user['penalty_points'] > 0): ?>
                                                        <span class="badge bg-warning"><?php echo $user['penalty_points']; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $user['total_borrows']; ?></td>
                                                <td><?php echo $user['total_returns']; ?></td>
                                                <td><?php echo date('M j, Y', strtotime($user['first_scan_date'])); ?></td>
                                                <td><?php echo date('M j, Y H:i', strtotime($user['last_activity'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="updateUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-info" onclick="viewUserActivity(<?php echo $user['id']; ?>)">
                                                            <i class="fas fa-history"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update User Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="user_id" id="modal_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label">User Status</label>
                            <select name="status" id="modal_status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Note:</strong> Suspended users cannot borrow equipment until their status is changed back to Active.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateUserStatus(userId, currentStatus) {
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('modal_status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }
        
        function viewUserActivity(userId) {
            // Redirect to user activity page
            window.location.href = `user-activity.php?user_id=${userId}`;
        }
    </script>
</body>
</html>
