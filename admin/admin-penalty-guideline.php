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

// Get penalty types for filter (based on client policy)
$default_penalty_types = ['Late Return', 'Damage', 'Loss'];
$penalty_types = $default_penalty_types;

if ($table_exists) {
    $type_result = $conn->query("SELECT DISTINCT penalty_type FROM penalty_guidelines ORDER BY penalty_type ASC");
    if ($type_result) {
        $custom_types = [];
        while ($row = $type_result->fetch_assoc()) {
            $type = trim($row['penalty_type']);
            if ($type !== '' && !in_array($type, $default_penalty_types, true) && !in_array($type, $custom_types, true)) {
                $custom_types[] = $type;
            }
        }
        sort($custom_types, SORT_NATURAL | SORT_FLAG_CASE);
        $penalty_types = array_merge($default_penalty_types, $custom_types);
    }
}
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
        <?php include 'includes/sidebar.php'; ?>

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
  `document_file` VARCHAR(255) DEFAULT NULL,
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
                                        <span class="value amount-highlight">₱<?= number_format($guideline['penalty_amount'], 2) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Points:</span>
                                        <span class="value"><?= $guideline['penalty_points'] ?> pts</span>
                                    </div>
                                    <div class="description">
                                        <?= nl2br(htmlspecialchars(substr($guideline['penalty_description'], 0, 150))) ?>
                                        <?= strlen($guideline['penalty_description']) > 150 ? '...' : '' ?>
                                    </div>
                                    <?php if (!empty($guideline['document_file'])): ?>
                                        <?php $documentUrl = '../' . ltrim($guideline['document_file'], '/\\'); ?>
                                        <div class="document-link">
                                            <i class="fas fa-paperclip"></i>
                                            <a href="<?= htmlspecialchars($documentUrl) ?>" download="<?= htmlspecialchars(basename($guideline['document_file'])) ?>" target="_blank">
                                                <?= htmlspecialchars(basename($guideline['document_file'])) ?>
                                            </a>
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
            <form id="guidelineForm" method="POST" action="save_penalty_guideline.php?ajax=1" enctype="multipart/form-data">
                <input type="hidden" name="id" id="guidelineId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="penalty_type">Penalty Type *</label>
                        <input list="penaltyTypeOptions" id="penalty_type" name="penalty_type" required placeholder="Type or select penalty type">
                        <datalist id="penaltyTypeOptions">
                            <?php foreach($penalty_types as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="penalty_amount">Penalty Amount (₱) *</label>
                        <input type="number" id="penalty_amount" name="penalty_amount" step="0.01" min="0" required>
                        <small class="form-hint">Amount to be paid by student (for tracking only - no payment processing)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="penalty_points">Penalty Points *</label>
                        <input type="number" id="penalty_points" name="penalty_points" min="0" required>
                        <small class="form-hint">Accumulated points for tracking violations</small>
                    </div>
                </div>

                <div id="policyGuidance" class="policy-guidance"></div>
                
                <div class="form-group">
                    <label for="penalty_description">Description *</label>
                    <textarea id="penalty_description" name="penalty_description" rows="5" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="document">Supporting Document (PDF, DOCX, Images)</label>
                    <div id="currentDocumentInfo" style="display: none; margin-bottom: 10px; padding: 12px; background: #f8faf9; border-radius: 6px; border-left: 3px solid #1e5631;">
                        <div style="margin-bottom: 6px; color: #333;">
                            <i class="fas fa-file-alt" style="color: #1e5631;"></i>
                            <strong>Current Document:</strong>
                        </div>
                        <div style="margin-left: 20px; display: inline-flex; align-items: center; gap: 6px; color: #1e5631; font-weight: 500;">
                            <i class="fas fa-paperclip"></i>
                            <span id="currentDocumentName"></span>
                        </div>
                        <small style="color: #999; font-style: italic; display: block; margin-top: 4px;">
                            <i class="fas fa-info-circle"></i> Upload a new file below to replace the current document
                        </small>
                    </div>
                    <input type="file" id="document" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <small style="color: #666;">Max file size: 5MB. Allowed: PDF, DOC, DOCX, JPG, PNG</small>
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

    <!-- Printable Guideline Container -->
    <div id="printContainer" class="print-container" aria-hidden="true">
        <div class="print-content">
            <header class="print-header">
                <h1>Instructional Media Center</h1>
                <h2>Penalty Guideline</h2>
            </header>
            <section class="print-body" id="printBody"></section>
            <footer class="print-footer">
                <p class="print-note">This document is generated for reference only. Payments are handled separately by the IMC office.</p>
                <p class="print-timestamp">Generated on <span id="printTimestamp"></span></p>
            </footer>
        </div>
    </div>

    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }

        // Sidebar toggle functionality handled by sidebar component
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    applyFilters();
                }, 500);
            });

            // File input change handler - show selected filename
            const documentInput = document.getElementById('document');
            if (documentInput) {
                documentInput.addEventListener('change', function(e) {
                    const currentDocInfo = document.getElementById('currentDocumentInfo');
                    if (e.target.files.length > 0) {
                        const newFileName = e.target.files[0].name;
                        const fileSize = (e.target.files[0].size / 1024 / 1024).toFixed(2);
                        
                        // Show info about the new file being uploaded
                        if (currentDocInfo && currentDocInfo.style.display !== 'none') {
                            const infoText = currentDocInfo.querySelector('small[style*="font-style: italic"]');
                            if (infoText) {
                                infoText.innerHTML = `<i class="fas fa-upload"></i> <strong>New file selected:</strong> ${newFileName} (${fileSize} MB) - Will replace current document on save`;
                                infoText.style.color = '#2e7d32';
                            }
                        }
                    }
                });
            }
        });

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Penalty Guideline';
            document.getElementById('guidelineForm').reset();
            document.getElementById('guidelineId').value = '';
            // Hide current document info for new guideline
            document.getElementById('currentDocumentInfo').style.display = 'none';
            document.getElementById('guidelineModal').style.display = 'block';
            applyPolicyTemplate();
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
                        const docPath = g.document_file ? String(g.document_file).trim() : '';
                        const docUrl = docPath ? ('../' + docPath.replace(/^[/\\]+/, '')) : '';
                        const hasDoc = !!docUrl;
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
                                ${hasDoc ? `
                                    <div class="detail-document" style="margin-top: 20px; padding: 15px; background: #f8faf9; border-radius: 8px; border-left: 4px solid #1e5631;">
                                        <div style="margin-bottom: 12px;">
                                            <strong style="color: #1e5631; font-size: 1.05em;">
                                                <i class="fas fa-file-alt"></i> Supporting Document
                                            </strong>
                                        </div>
                                        <div style="padding: 10px;">
                                            <a href="${docUrl}" download="${docPath.split('/').pop()}" style="display: inline-flex; align-items: center; color: #1e5631; text-decoration: none; font-weight: 500; font-size: 1em; transition: all 0.2s;">
                                                <i class="fas fa-paperclip" style="margin-right: 8px; color: #1e5631;"></i>
                                                <span style="border-bottom: 1px solid transparent; transition: border-color 0.2s;" onmouseover="this.style.borderColor='#1e5631'" onmouseout="this.style.borderColor='transparent'">${docPath.split('/').pop()}</span>
                                            </a>
                                        </div>
                                    </div>
                                ` : `
                                    <div class="detail-document">
                                        <strong>Document:</strong>
                                        <p style="margin:8px 0 0;color:#999;">No document uploaded.</p>
                                    </div>
                                `}
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

        function openDocument(path) {
            if (!path) return;
            const lower = path.toLowerCase();
            const isPdf = lower.endsWith('.pdf');
            const isImage = lower.endsWith('.jpg') || lower.endsWith('.jpeg') || lower.endsWith('.png') || lower.endsWith('.gif') || lower.endsWith('.webp');
            const preview = document.getElementById('documentPreview');
            if (!preview) {
                // Fallback: download if preview container not available
                const a = document.createElement('a');
                a.href = path;
                a.download = '';
                document.body.appendChild(a);
                a.click();
                a.remove();
                return;
            }
            if (isPdf) {
                preview.innerHTML = `<iframe src="${path}#view=FitH" style="width:100%;height:70vh;border:1px solid #e0e0e0;border-radius:8px;"></iframe>`;
            } else if (isImage) {
                preview.innerHTML = `<img src="${path}" alt="Document" style="max-width:100%;max-height:70vh;border:1px solid #e0e0e0;border-radius:8px;display:block;" />`;
            } else {
                // Not previewable: trigger download (no new tab)
                preview.innerHTML = '';
                const a = document.createElement('a');
                a.href = path;
                a.download = '';
                document.body.appendChild(a);
                a.click();
                a.remove();
            }
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
                        
                        // Show current document if exists
                        const currentDocInfo = document.getElementById('currentDocumentInfo');
                        const currentDocName = document.getElementById('currentDocumentName');
                        const docPathEdit = g.document_file ? String(g.document_file).trim() : '';
                        const docUrlEdit = docPathEdit ? ('../' + docPathEdit.replace(/^[/\\]+/, '')) : '';
                        
                        if (docUrlEdit) {
                            const filename = docPathEdit.split('/').pop();
                            currentDocName.textContent = filename;
                            currentDocInfo.style.display = 'block';
                        } else {
                            currentDocInfo.style.display = 'none';
                        }
                        
                        document.getElementById('guidelineModal').style.display = 'block';
                        applyPolicyTemplate(false);
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

        async function printGuideline(id) {
            try {
                const response = await fetch(`get_penalty_guideline.php?id=${id}`);
                const data = await response.json();

                if (!data.success) {
                    alert('Unable to load guideline for printing.');
                    return;
                }

                const g = data.guideline;
                const printBody = document.getElementById('printBody');
                const timestamp = document.getElementById('printTimestamp');

                const detailsHtml = `
                    <div class="print-section">
                        <h3>${g.title}</h3>
                        <dl>
                            <div><dt>Penalty Type</dt><dd>${g.penalty_type}</dd></div>
                            <div><dt>Penalty Amount</dt><dd>₱${parseFloat(g.penalty_amount).toFixed(2)}</dd></div>
                            <div><dt>Penalty Points</dt><dd>${g.penalty_points} pts</dd></div>
                            <div><dt>Status</dt><dd>${g.status}</dd></div>
                            <div><dt>Created</dt><dd>${g.created_at}</dd></div>
                            <div><dt>Updated</dt><dd>${g.updated_at}</dd></div>
                        </dl>
                        <h4>Description</h4>
                        <p>${g.penalty_description.replace(/\n/g, '<br>')}</p>
                    </div>
                `;

                printBody.innerHTML = detailsHtml;
                timestamp.textContent = new Date().toLocaleString();

                document.body.classList.add('printing');
                window.print();
            } catch (error) {
                console.error('Print error:', error);
                alert('An error occurred while preparing the print view.');
            } finally {
                document.body.classList.remove('printing');
            }
        }

        function exportToPDF() {
            const query = new URLSearchParams({
                type: document.getElementById('typeFilter').value,
                status: document.getElementById('statusFilter').value,
                search: document.getElementById('searchInput').value
            });
            window.location.href = 'export_penalty_guidelines_pdf.php?' + query.toString();
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

        // Intercept guideline card document links to avoid opening new tab
        document.addEventListener('click', function(e) {
            const anchor = e.target.closest('.document-link a');
            if (anchor && anchor.href) {
                e.preventDefault();
                // If view modal is open, preview inside it; else open a lightweight preview modal
                if (document.getElementById('viewModal').style.display === 'block') {
                    openDocument(anchor.getAttribute('href'));
                } else {
                    // Create a simple temporary preview container within the guideline modal if present
                    const temp = document.createElement('div');
                    temp.id = 'documentPreview';
                    document.body.appendChild(temp);
                    openDocument(anchor.getAttribute('href'));
                }
            }
        });

        const penaltyPolicyTemplates = {
            'Late Return': {
                title: 'Overdue Equipment Daily Fee',
                amount: 10.00,
                amountDisabled: true,
                points: 0,
                description: 'Applies when borrowed equipment is returned beyond the expected return date. Students are charged ₱10.00 for each day late. Inclusion of Saturdays and Sundays should be reviewed by the IMC in-charge.',
                guidance: `
                    <h4>Late Return Policy</h4>
                    <ul>
                        <li>Fixed charge of ₱10.00 per day overdue.</li>
                        <li>System tracks amount owed; payment is collected manually at the IMC office.</li>
                        <li>Confirm with the in-charge if weekends are included before issuing the penalty.</li>
                    </ul>
                `
            },
            'Damage': {
                title: 'Damaged Equipment - Borrower Repair Requirement',
                amount: 0.00,
                amountDisabled: true,
                points: 0,
                description: 'When equipment is returned with damage, the borrower is responsible for repairing it. Record the required repair actions and estimated cost in the notes when issuing the penalty.',
                guidance: `
                    <h4>Damaged Item Policy</h4>
                    <ul>
                        <li>Borrower must shoulder repair cost and coordinate repair.</li>
                        <li>Use penalty notes to document damage details and repair instructions.</li>
                        <li>No fixed amount—set actual expense during penalty assessment.</li>
                    </ul>
                `
            },
            'Loss': {
                title: 'Lost Equipment Replacement',
                amount: 0.00,
                amountDisabled: true,
                points: 0,
                description: 'If an item is lost, the borrower must replace it with the same unit. Use notes to specify the required model and any deadlines for replacement.',
                guidance: `
                    <h4>Lost Item Policy</h4>
                    <ul>
                        <li>Borrower must purchase and provide the exact same unit.</li>
                        <li>Record item model and replacement deadline in penalty notes.</li>
                        <li>Set replacement value during penalty issuance for tracking.</li>
                    </ul>
                `
            }
        };

        function applyPolicyTemplate(resetTitle = true) {
            const typeSelect = document.getElementById('penalty_type');
            const selectedType = typeSelect.value;
            const template = penaltyPolicyTemplates[selectedType];
            const amountInput = document.getElementById('penalty_amount');
            const pointsInput = document.getElementById('penalty_points');
            const descriptionInput = document.getElementById('penalty_description');
            const guidanceBox = document.getElementById('policyGuidance');

            if (!template) {
                amountInput.removeAttribute('readonly');
                amountInput.classList.remove('input-readonly');
                guidanceBox.innerHTML = '';
                return;
            }

            if (resetTitle) {
                document.getElementById('title').value = template.title;
            }

            amountInput.value = template.amount.toFixed(2);
            pointsInput.value = template.points;
            descriptionInput.value = template.description;
            guidanceBox.innerHTML = template.guidance;

            if (template.amountDisabled) {
                amountInput.setAttribute('readonly', 'readonly');
                amountInput.classList.add('input-readonly');
            } else {
                amountInput.removeAttribute('readonly');
                amountInput.classList.remove('input-readonly');
            }
        }

        document.getElementById('penalty_type').addEventListener('change', () => applyPolicyTemplate());

        // AJAX submit for add/edit form
        (function() {
            const form = document.getElementById('guidelineForm');
            if (!form) return;
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = form.querySelector('.btn-save');
                if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'; }
                try {
                    const formData = new FormData(form);
                    const resp = await fetch(form.action, { method: 'POST', body: formData, credentials: 'same-origin', redirect: 'follow' });
                    const ct = resp.headers.get('content-type') || '';
                    const raw = await resp.text();
                    let data = null;
                    if (ct.includes('application/json')) {
                        try { data = JSON.parse(raw); } catch(parseErr) { data = null; }
                    }
                    if (!resp.ok || !ct.includes('application/json')) {
                        console.error('Save failed. HTTP', resp.status, raw);
                        if (resp.redirected) {
                            alert('Session expired. Please log in again.');
                            window.location.href = resp.url;
                            return;
                        }
                        alert('Error: ' + (data && data.message ? data.message : (raw || ('HTTP ' + resp.status))));
                        return;
                    }
                    if (data && data.success) {
                        alert(data.message || 'Saved successfully');
                        closeModal();
                        window.location.reload();
                    } else {
                        console.error('Save error body:', raw);
                        alert('Error: ' + (data && data.message ? data.message : 'Unknown error'));
                    }
                } catch (err) {
                    console.error(err);
                    alert('An error occurred while saving.');
                } finally {
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Guideline'; }
                }
            });
        })();
    </script>
</body>
</html>
