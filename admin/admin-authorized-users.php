<?php
session_start();
require_once '../includes/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['active'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Active'")->fetch_assoc()['count'];
$stats['inactive'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Inactive'")->fetch_assoc()['count'];
$stats['suspended'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Suspended'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorized Users - Equipment System</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/authorized-users.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Authorized Users Management</h1>
                <p class="page-subtitle">Manage student RFID/ID cards authorized to borrow equipment</p>
            </header>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stats['total'] ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                </div>
                <div class="stat-card active">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stats['active'] ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                </div>
                <div class="stat-card inactive">
                    <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stats['inactive'] ?></div>
                        <div class="stat-label">Inactive</div>
                    </div>
                </div>
                <div class="stat-card suspended">
                    <div class="stat-icon"><i class="fas fa-user-lock"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?= $stats['suspended'] ?></div>
                        <div class="stat-label">Suspended</div>
                    </div>
                </div>
            </div>

            <!-- Main Content Section -->
            <section class="content-section active">
                <div class="section-header">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by Student RFID ID...">
                    </div>
                    <div class="header-actions">
                        <select id="statusFilter" class="filter-select">
                            <option value="all">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                        <button class="btn-add" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add User
                        </button>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="table-container">
                    <table class="users-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student RFID ID</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Role</th>
                                <th>Penalty Points</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="7" class="loading-cell">
                                    <i class="fas fa-spinner fa-spin"></i> Loading users...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Authorized User</h2>
                <button class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <form id="addUserForm" class="modal-body" enctype="multipart/form-data">
                <div class="alert-info" style="background: #e3f2fd; padding: 12px; border-radius: 8px; margin-bottom: 20px; color: #1565c0; font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> Scan student ID card to auto-fill or manually enter student RFID ID
                </div>
                <div class="form-group">
                    <label for="studentId">Student RFID ID <span class="required">*</span></label>
                    <input type="text" id="studentId" name="student_id" required placeholder="Auto-fills when scanned or enter manually" autofocus>
                    <small class="form-hint">Scan the student ID card or type the RFID number manually</small>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="userType">Role</label>
                    <select id="userType" name="user_type">
                        <option value="Student">Student</option>
                        <option value="Teacher">Teacher</option>
                    </select>
                    <small class="form-hint">Teachers can borrow Reserved items.</small>
                </div>
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="student@example.com" required>
                    <small class="form-hint">Required. Must be a valid email for notifications.</small>
                </div>
                <div class="form-group">
                    <label for="photo">Student Photo (for face verification)</label>
                    <input type="file" id="photo" name="photo" accept="image/*">
                    <small class="form-hint">Accepted: JPG/PNG up to 2MB</small>
                    <div id="addPhotoPreviewWrapper" style="margin-top:8px; display:none;">
                        <img id="addPhotoPreview" src="" alt="Preview" style="max-width:120px; border-radius:8px; border:1px solid #e5e7eb;"/>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editUserForm" class="modal-body" enctype="multipart/form-data">
                <input type="hidden" id="editUserId" name="id">
                <div class="alert-info" style="background: #e3f2fd; padding: 12px; border-radius: 8px; margin-bottom: 20px; color: #1565c0; font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> Scan student ID card to auto-fill or manually enter student RFID ID
                </div>
                <div class="form-group">
                    <label for="editStudentId">Student RFID ID <span class="required">*</span></label>
                    <input type="text" id="editStudentId" name="student_id" required placeholder="Auto-fills when scanned or enter manually">
                    <small class="form-hint">Scan the student ID card or type the RFID number manually</small>
                </div>
                <div class="form-group">
                    <label for="editStatus">Status</label>
                    <select id="editStatus" name="status">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Suspended">Suspended</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editUserType">Role</label>
                    <select id="editUserType" name="user_type">
                        <option value="Student">Student</option>
                        <option value="Teacher">Teacher</option>
                    </select>
                    <small class="form-hint">Teachers can borrow Reserved items.</small>
                </div>
                <div class="form-group">
                    <label for="editEmail">Email <span class="required">*</span></label>
                    <input type="email" id="editEmail" name="email" placeholder="student@example.com" required>
                    <small class="form-hint">Required. Must be a valid email for notifications.</small>
                </div>
                <div class="form-group">
                    <label for="editPenaltyPoints">Penalty Points</label>
                    <input type="number" id="editPenaltyPoints" name="penalty_points" min="0" value="0">
                    <small class="form-hint">Accumulated penalty points</small>
                </div>
                <div class="form-group">
                    <label for="editPhoto">Upload New Photo</label>
                    <input type="file" id="editPhoto" name="photo" accept="image/*">
                    <small class="form-hint">Accepted: JPG/PNG up to 2MB</small>
                    <div id="editPhotoFetchedWrapper" style="margin-top:12px; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                        <img id="editPhotoFetched" src="" alt="Student Photo" style="max-width:160px; border-radius:10px; border:1px solid #e5e7eb; background:#ffffff; padding:6px; display:none;"/>
                        <small class="form-hint" style="margin-top:6px; text-align:center; color:#6b7280;">Tip: Use a clear, front-facing photo on a plain white background.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update User</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/authorized-users.js?v=<?= time() ?>"></script>
</body>
</html>
