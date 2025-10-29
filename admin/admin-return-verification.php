<?php
session_start();
require_once '../includes/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

// Get statistics with error handling
$stats = [];

// Pending verification: return_verification_status = 'Pending' or 'Analyzing'
$result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE return_verification_status IN ('Pending', 'Analyzing')");
$stats['pending'] = $result ? $result->fetch_assoc()['count'] : 0;

// Verified today: return_verification_status = 'Verified' and verified today
$result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE return_verification_status = 'Verified' AND DATE(actual_return_date) = CURDATE()");
$stats['verified_today'] = $result ? $result->fetch_assoc()['count'] : 0;

// Damaged today: status = 'Damaged' OR return_verification_status = 'Damage' verified today
$result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE (status = 'Damaged' OR return_verification_status = 'Damage') AND DATE(actual_return_date) = CURDATE()");
$stats['damaged'] = $result ? $result->fetch_assoc()['count'] : 0;

// Good condition today: condition_after = 'Good' and verified today
$result = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE condition_after = 'Good' AND return_verification_status = 'Verified' AND DATE(actual_return_date) = CURDATE()");
$stats['good'] = $result ? $result->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Verification - Equipment System</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/return-verification.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Return Verification</h1>
                <p class="page-subtitle">Verify returned equipment and assess condition</p>
            </header>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card pending">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stats['pending'] ?></div>
                        <div class="stat-label">Pending Verification</div>
                    </div>
                </div>
                <div class="stat-card verified">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stats['verified_today'] ?></div>
                        <div class="stat-label">Verified Today</div>
                    </div>
                </div>
                <div class="stat-card good">
                    <div class="stat-icon"><i class="fas fa-thumbs-up"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stats['good'] ?></div>
                        <div class="stat-label">Good Condition</div>
                    </div>
                </div>
                <div class="stat-card damaged">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stats['damaged'] ?></div>
                        <div class="stat-label">Damaged Today</div>
                    </div>
                </div>
            </div>

            <!-- Main Content Section -->
            <section class="content-section active">
                <div class="section-header">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by Transaction ID, Equipment, User...">
                    </div>
                    <div class="header-actions">
                        <select id="statusFilter" class="filter-select">
                            <option value="Pending Return">Pending Verification</option>
                            <option value="all">All Returns</option>
                            <option value="Returned">Verified</option>
                        </select>
                    </div>
                </div>

                <!-- Returns Table -->
                <div class="table-container">
                    <table class="returns-table" id="returnsTable">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Equipment</th>
                                <th>User</th>
                                <th>Borrowed Date</th>
                                <th>Expected Return</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="returnsTableBody">
                            <tr>
                                <td colspan="7" class="loading-cell">
                                    <i class="fas fa-spinner fa-spin"></i> Loading returns...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Verification Modal -->
    <div id="verifyModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Verify Return</h2>
                <button class="modal-close" onclick="closeVerifyModal()">&times;</button>
            </div>
            <div class="modal-body" id="verifyModalBody">
                <div class="loading-cell">
                    <i class="fas fa-spinner fa-spin"></i> Loading transaction details...
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/return-verification.js?v=<?= time() ?>"></script>
</body>
</html>
