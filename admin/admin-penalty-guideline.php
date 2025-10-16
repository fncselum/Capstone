<?php
// Enable error reporting for debugging
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

// Check if penalty_guidelines table exists
$table_check = $conn->query("SHOW TABLES LIKE 'penalty_guidelines'");
$table_exists = $table_check && $table_check->num_rows > 0;

// Handle success/error messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

$guidelines = null;

if ($table_exists) {
    // Check if admin_users table exists
    $admin_users_check = $conn->query("SHOW TABLES LIKE 'admin_users'");
    $has_admin_users = $admin_users_check && $admin_users_check->num_rows > 0;
    
    // Build SQL query with filters
    if ($has_admin_users) {
        $sql = "SELECT pg.*, au.username as created_by_name 
                FROM penalty_guidelines pg
                LEFT JOIN admin_users au ON pg.created_by = au.id
                WHERE 1=1";
    } else {
        $sql = "SELECT pg.*, 'Admin' as created_by_name 
                FROM penalty_guidelines pg
                WHERE 1=1";
    }

    if ($filter_type !== 'all') {
        $sql .= " AND pg.penalty_type = '" . $conn->real_escape_string($filter_type) . "'";
    }

    if ($filter_status !== 'all') {
        $sql .= " AND pg.status = '" . $conn->real_escape_string($filter_status) . "'";
    }

    if (!empty($search_query)) {
        $search_escaped = $conn->real_escape_string($search_query);
        $sql .= " AND (pg.title LIKE '%$search_escaped%' OR pg.penalty_description LIKE '%$search_escaped%')";
    }

    $sql .= " ORDER BY pg.created_at DESC";

    $guidelines = $conn->query($sql);
    
    // Debug: Check if query failed
    if (!$guidelines) {
        $error_message = "Query Error: " . $conn->error;
    }
}

// Get penalty types for filter
$penalty_types = ['Late Return', 'Damage', 'Loss', 'Misuse', 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penalty Guidelines - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/penalty-guideline.css?v=<?= time() ?>">
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
                <li class="nav-item active">
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
                <h1 class="page-title">Penalty Guidelines Management</h1>
                <button class="add-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Penalty Guideline
                </button>
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
                    <p>The penalty_guidelines table needs to be created in your database.</p>
                    <div class="setup-steps">
                        <h3>Setup Instructions:</h3>
                        <ol>
                            <li>Open <strong>phpMyAdmin</strong></li>
                            <li>Select your <strong>capstone</strong> database</li>
                            <li>Go to the <strong>SQL</strong> tab</li>
                            <li>Copy and paste the SQL from <code>setup_penalty_guidelines.sql</code></li>
                            <li>Click <strong>Go</strong> to execute</li>
                            <li>Refresh this page</li>
                        </ol>
                    </div>
                    <div class="setup-sql">
                        <h3>Quick Setup SQL:</h3>
                        <textarea readonly onclick="this.select()" style="width: 100%; height: 200px; padding: 12px; font-family: monospace; border: 2px solid #e0e0e0; border-radius: 8px;">CREATE TABLE IF NOT EXISTS `penalty_guidelines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `penalty_type` VARCHAR(100) NOT NULL,
  `penalty_description` TEXT NOT NULL,
  `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `penalty_points` INT NOT NULL DEFAULT 0,
  `document_path` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('draft', 'active', 'archived') DEFAULT 'draft',
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_penalty_type` (`penalty_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</textarea>
                        <p style="margin-top: 10px; color: #666;"><small>Click the text area above to select all, then copy (Ctrl+C)</small></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($table_exists): ?>
            <!-- Filters and Search -->
            <section class="content-section" style="display: block !important; visibility: visible !important;">
                <div class="filters-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search guidelines..." value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <select id="typeFilter" class="filter-select" onchange="applyFilters()">
                            <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
                            <?php foreach($penalty_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= $filter_type === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="statusFilter" class="filter-select" onchange="applyFilters()">
                            <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="archived" <?= $filter_status === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>

                        <button class="btn-export" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                </div>

                <!-- Guidelines Grid -->
                <div class="guidelines-grid" style="display: grid !important; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 24px;">
                    <?php if ($guidelines && $guidelines->num_rows > 0): ?>
                        <?php while($guideline = $guidelines->fetch_assoc()): ?>
                            <div class="guideline-card" data-id="<?= $guideline['id'] ?>">
                                <div class="card-header">
                                    <h3><?= htmlspecialchars($guideline['title']) ?></h3>
                                    <span class="status-badge status-<?= $guideline['status'] ?>">
                                        <?= ucfirst($guideline['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="card-body">
                                    <div class="info-row">
                                        <span class="label">Type:</span>
                                        <span class="value"><?= htmlspecialchars($guideline['penalty_type']) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Amount:</span>
                                        <span class="value">₱<?= number_format($guideline['penalty_amount'], 2) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Points:</span>
                                        <span class="value"><?= $guideline['penalty_points'] ?> pts</span>
                                    </div>
                                    <div class="description">
                                        <?= nl2br(htmlspecialchars(substr($guideline['penalty_description'], 0, 150))) ?>
                                        <?= strlen($guideline['penalty_description']) > 150 ? '...' : '' ?>
                                    </div>
                                    <?php if ($guideline['document_path']): ?>
                                        <div class="document-link">
                                            <i class="fas fa-paperclip"></i>
                                            <a href="<?= htmlspecialchars($guideline['document_path']) ?>" target="_blank">View Document</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer">
                                    <small>Created by: <?= htmlspecialchars($guideline['created_by_name'] ?? 'Unknown') ?></small>
                                    <div class="card-actions">
                                        <button class="btn-icon" onclick="viewGuideline(<?= $guideline['id'] ?>)" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-icon" onclick="editGuideline(<?= $guideline['id'] ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon btn-danger" onclick="deleteGuideline(<?= $guideline['id'] ?>, '<?= htmlspecialchars($guideline['title']) ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="btn-icon" onclick="printGuideline(<?= $guideline['id'] ?>)" title="Print">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h3>No Guidelines Found</h3>
                            <p>Create your first penalty guideline to get started.</p>
                            <button class="btn-primary" onclick="openAddModal()">
                                <i class="fas fa-plus"></i> Add Guideline
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add/Edit Modal -->
    <div id="guidelineModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="modalTitle">Add Penalty Guideline</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="guidelineForm" method="POST" action="save_penalty_guideline.php" enctype="multipart/form-data">
                <input type="hidden" name="id" id="guidelineId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="penalty_type">Penalty Type *</label>
                        <select id="penalty_type" name="penalty_type" required>
                            <option value="">Select Type</option>
                            <?php foreach($penalty_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="penalty_amount">Penalty Amount (₱) *</label>
                        <input type="number" id="penalty_amount" name="penalty_amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="penalty_points">Penalty Points *</label>
                        <input type="number" id="penalty_points" name="penalty_points" min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="penalty_description">Description *</label>
                    <textarea id="penalty_description" name="penalty_description" rows="5" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="document">Supporting Document (PDF, DOCX, Images)</label>
                    <input type="file" id="document" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <small>Max file size: 5MB</small>
                </div>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Save Guideline
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Guideline Details</h2>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="viewContent" class="modal-body">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }

        // Sidebar toggle
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

            // Search functionality
            const searchInput = document.getElementById('searchInput');
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    applyFilters();
                }, 500);
            });
        });

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Penalty Guideline';
            document.getElementById('guidelineForm').reset();
            document.getElementById('guidelineId').value = '';
            document.getElementById('guidelineModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('guidelineModal').style.display = 'none';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function applyFilters() {
            const type = document.getElementById('typeFilter').value;
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value;
            
            const url = new URL(window.location);
            url.searchParams.set('type', type);
            url.searchParams.set('status', status);
            url.searchParams.set('search', search);
            window.location.href = url.toString();
        }

        function viewGuideline(id) {
            fetch(`get_penalty_guideline.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const g = data.guideline;
                        document.getElementById('viewContent').innerHTML = `
                            <div class="view-details">
                                <h3>${g.title}</h3>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <strong>Type:</strong> ${g.penalty_type}
                                    </div>
                                    <div class="detail-item">
                                        <strong>Amount:</strong> ₱${parseFloat(g.penalty_amount).toFixed(2)}
                                    </div>
                                    <div class="detail-item">
                                        <strong>Points:</strong> ${g.penalty_points} pts
                                    </div>
                                    <div class="detail-item">
                                        <strong>Status:</strong> <span class="status-badge status-${g.status}">${g.status}</span>
                                    </div>
                                </div>
                                <div class="detail-description">
                                    <strong>Description:</strong>
                                    <p>${g.penalty_description.replace(/\n/g, '<br>')}</p>
                                </div>
                                ${g.document_path ? `
                                    <div class="detail-document">
                                        <strong>Document:</strong>
                                        <a href="${g.document_path}" target="_blank" class="btn-link">
                                            <i class="fas fa-paperclip"></i> View Document
                                        </a>
                                    </div>
                                ` : ''}
                                <div class="detail-meta">
                                    <small>Created: ${g.created_at}</small>
                                    <small>Updated: ${g.updated_at}</small>
                                </div>
                            </div>
                        `;
                        document.getElementById('viewModal').style.display = 'block';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function editGuideline(id) {
            fetch(`get_penalty_guideline.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const g = data.guideline;
                        document.getElementById('modalTitle').textContent = 'Edit Penalty Guideline';
                        document.getElementById('guidelineId').value = g.id;
                        document.getElementById('title').value = g.title;
                        document.getElementById('penalty_type').value = g.penalty_type;
                        document.getElementById('penalty_amount').value = g.penalty_amount;
                        document.getElementById('penalty_points').value = g.penalty_points;
                        document.getElementById('penalty_description').value = g.penalty_description;
                        document.getElementById('status').value = g.status;
                        document.getElementById('guidelineModal').style.display = 'block';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function deleteGuideline(id, title) {
            if (confirm(`Are you sure you want to delete "${title}"?\n\nThis action cannot be undone.`)) {
                fetch('delete_penalty_guideline.php', {
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

        function printGuideline(id) {
            window.open(`print_penalty_guideline.php?id=${id}`, '_blank');
        }

        function exportToPDF() {
            window.open('export_penalty_guidelines_pdf.php?' + new URLSearchParams({
                type: document.getElementById('typeFilter').value,
                status: document.getElementById('statusFilter').value,
                search: document.getElementById('searchInput').value
            }), '_blank');
        }

        // Close modals on outside click
        window.onclick = function(event) {
            const guidelineModal = document.getElementById('guidelineModal');
            const viewModal = document.getElementById('viewModal');
            if (event.target === guidelineModal) {
                closeModal();
            }
            if (event.target === viewModal) {
                closeViewModal();
            }
        }
    </script>
</body>
</html>
