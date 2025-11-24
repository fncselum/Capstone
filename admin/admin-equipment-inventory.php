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

// Handle session messages from add_equipment.php
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
$form_data = $_SESSION['form_data'] ?? [];

// Clear session messages after displaying
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
unset($_SESSION['form_data']);

// Old inline form handling - now moved to add_equipment.php
if (false && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    // Read and validate RFID tag
    $rfid_tag = trim($_POST['rfid_tag'] ?? '');
    if ($rfid_tag === '') {
        $error_message = "RFID Tag is required.";
    } else {
        $name = $conn->real_escape_string($_POST['name']);
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 'NULL';
        $quantity = (int)$_POST['quantity'];
        $condition = $conn->real_escape_string($_POST['item_condition']);
        $image_path = $conn->real_escape_string($_POST['image_path']);
        
        // Handle file upload if provided
        if (!empty($_FILES['image_file']['name'])) {
            $uploadDir = dirname(__DIR__) . '/uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($name));
            $fileName = $safeName . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
                $image_path = 'uploads/' . $fileName;
            }
        }
        
        $description = $conn->real_escape_string($_POST['description']);
        
        // Check for duplicate RFID tag
        $rfid_esc = $conn->real_escape_string($rfid_tag);
        $dup = $conn->query("SELECT id, name FROM equipment WHERE rfid_tag = '$rfid_esc' LIMIT 1");
        if ($dup && $dup->num_rows > 0) {
            $row = $dup->fetch_assoc();
            $error_message = "This RFID tag ($rfid_tag) is already used by '" . htmlspecialchars($row['name']) . "' (ID " . (int)$row['id'] . ").";
            $dup->free();
        } else {
            $sql = "INSERT INTO equipment (rfid_tag, name, category_id, quantity, item_condition, image_path, description) VALUES ('$rfid_esc', '$name', $category_id, $quantity, '$condition', '$image_path', '$description')";
            if ($conn->query($sql)) {
                $new_id = $conn->insert_id;
                // Also insert into inventory table
                $conn->query("INSERT INTO inventory (equipment_id, quantity, available_quantity, item_condition) VALUES ($new_id, $quantity, $quantity, '$condition')");
                $success_message = "Equipment added successfully!";
                // Redirect to refresh the page
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $error_message = "Error adding equipment: " . $conn->error;
            }
        }
    }
}

// Get categories for filter
$categories = [];
$category_result = $conn->query("SELECT id, name FROM categories ORDER BY name");
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row;
    }
    $category_result->free();
}

// Get stock filter from URL parameter
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : 'all';
$allowed_filters = ['all', 'available', 'out_of_stock'];
if (!in_array($stock_filter, $allowed_filters)) {
    $stock_filter = 'all';
}

// Build SQL query with stock filter and maintenance count
$sql = "SELECT e.*, e.size_category, c.name as category_name, 
        e.quantity as quantity,
        i.available_quantity, i.borrowed_quantity, i.damaged_quantity, i.item_condition, i.availability_status, i.minimum_stock_level,
        COALESCE(i.available_quantity, GREATEST(e.quantity - COALESCE(i.borrowed_quantity, 0), 0)) AS computed_available,
        (SELECT COUNT(*) FROM maintenance_logs ml 
         WHERE ml.equipment_id = e.rfid_tag 
         AND ml.status IN ('Pending', 'In Progress')) AS maintenance_count
        FROM equipment e 
        LEFT JOIN categories c ON e.category_id = c.id 
        LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id";

// Add WHERE clause for stock filtering
if ($stock_filter === 'available') {
    $sql .= " WHERE COALESCE(i.available_quantity, GREATEST(e.quantity - COALESCE(i.borrowed_quantity, 0), 0)) > 0";
} elseif ($stock_filter === 'out_of_stock') {
    $sql .= " WHERE COALESCE(i.available_quantity, GREATEST(e.quantity - COALESCE(i.borrowed_quantity, 0), 0)) = 0";
}

$sql .= " ORDER BY e.name";

$equipment_list = $conn->query($sql);

$equipment_items = [];
if ($equipment_list) {
    while ($row = $equipment_list->fetch_assoc()) {
        $equipment_items[] = $row;
    }
    $equipment_list->free();
}

$condition_options = ['Excellent', 'Good', 'Fair', 'Poor', 'Out of Service'];
$size_options = ['Small', 'Medium', 'Large'];
$hasEquipment = !empty($equipment_items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Inventory - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/equipment-inventory.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Equipment Inventory</h1>
            </header>

            <!-- Inventory Section -->
            <section class="content-section active">
                <?php if ($error_message): ?>
                    <div class="alert alert-error" style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="alert alert-success" style="background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <strong>Success:</strong> <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                
                <div class="section-header">
                    <div class="search-wrapper" style="flex: 1; max-width: 600px; display: flex; align-items: center;">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" type="text" placeholder="Search by name, ID, condition..." style="width: 100%; height: 38px; border: none; outline: none; font-size: 1rem;">
                    </div>
                    <select id="stockFilter" class="filter-select" onchange="filterByStock(this.value)" style="margin-right: 10px;">
                        <option value="all" <?= $stock_filter === 'all' ? 'selected' : '' ?>>All Stock Status</option>
                        <option value="available" <?= $stock_filter === 'available' ? 'selected' : '' ?>>✓ Available Only</option>
                        <option value="out_of_stock" <?= $stock_filter === 'out_of_stock' ? 'selected' : '' ?>>✗ Out of Stock Only</option>
                    </select>
                    <button class="add-btn" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Equipment</button>
                </div>
                <div class="inventory-toolbar">
                    <div class="category-pills" id="categoryPills">
                        <button class="category-pill active" data-category="all">All</button>
                        <?php foreach($categories as $category): ?>
                            <button class="category-pill" data-category="<?= htmlspecialchars(strtolower($category['name'])) ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <select id="categoryFilter" style="display:none;">
                        <option value="all">All</option>
                        <?php foreach($categories as $category): ?>
                            <option value="<?= htmlspecialchars(strtolower($category['name'])) ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="panel">
                    <div class="equipment-grid three-col" id="equipmentGrid">
                        <?php if ($hasEquipment): ?>
                            <?php foreach ($equipment_items as $row): ?>
                                <div class="equipment-card"
                                     data-name="<?= htmlspecialchars(strtolower($row['name'] ?? '')) ?>"
                                     data-category="<?= htmlspecialchars(strtolower($row['category_name'] ?? '')) ?>"
                                     data-condition="<?= htmlspecialchars(strtolower($row['item_condition'] ?? '')) ?>"
                                     data-size="<?= htmlspecialchars(strtolower($row['size_category'] ?? '')) ?>"
                                     data-equipment-card="<?= htmlspecialchars($row['name']) ?>">
                                    <?php if (!empty($row['image_path'])): ?>
                                        <?php
                                            $image_src = $row['image_path'];
                                            if (strpos($image_src, 'uploads/') === 0) {
                                                $image_src = '../' . $image_src;
                                            }
                                        ?>
                                        <div class="equipment-thumb">
                                            <img src="<?= htmlspecialchars($image_src) ?>" alt="<?= htmlspecialchars($row['name']) ?>" onerror="this.style.display='none'">
                                        </div>
                                    <?php else: ?>
                                        <div class="equipment-thumb placeholder"></div>
                                    <?php endif; ?>
                                    <div class="equipment-info">
                                        <div class="equipment-id">#<?= htmlspecialchars($row['id']) ?></div>
                                        <h3 class="equipment-name"> <?= htmlspecialchars($row['name']) ?></h3>
                                        <?php if (!empty($row['size_category'])): ?>
                                            <div class="equipment-size">
                                                <i class="fas fa-ruler-combined"></i> <?= htmlspecialchars($row['size_category']) ?> Item
                                            </div>
                                        <?php endif; ?>
                                        <?php 
                                            // Total quantity from equipment table
                                            $equipment_qty = $row['quantity'] ?? 0;
                                            $maintenance_count = $row['maintenance_count'] ?? 0;
                                            // Compute available from query
                                            $computed_available = $row['computed_available'] ?? 0;
                                            $min_stock = $row['minimum_stock_level'] ?? 1;
                                            $is_not_available = ($computed_available == 0);
                                            $is_low_stock = ($computed_available > 0 && $computed_available <= $min_stock);
                                        ?>
                                        <div class="equipment-qty-container">
                                            <div class="equipment-qty-row">
                                                Quantity: <span class="qty-value"><?= htmlspecialchars($equipment_qty) ?></span>
                                            </div>
                                            <div class="equipment-qty-row">
                                                Available: <span class="qty-value" data-available-quantity><?= htmlspecialchars($computed_available) ?></span>
                                                <span class="stock-badge <?= $is_not_available ? 'out-of-stock' : ($is_low_stock ? 'low-stock' : 'available') ?>" data-availability-status>
                                                    <?= $is_not_available ? 'Not Available' : ($is_low_stock ? 'Low Stock' : 'Available') ?>
                                                </span>
                                            </div>
                                            <?php if ($maintenance_count > 0): ?>
                                            <div class="equipment-qty-breakdown">
                                                <span class="qty-badge maintenance" title="Ongoing maintenance (Pending + In Progress)">
                                                    <i class="fas fa-wrench"></i> <?= $maintenance_count ?> Maintenance
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-actions">
                                        <button class="btn btn-secondary" onclick="viewEquipmentDetails(<?= $row['id'] ?>)"><i class="fas fa-eye"></i> View</button>
                                        <button class="btn btn-primary" onclick="manageEquipment(<?= $row['id'] ?>)"><i class="fas fa-cog"></i> Manage</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div id="emptyState" class="empty-state" style="<?= $hasEquipment ? 'display: none;' : 'display: block;' ?>">
                        <div class="empty-icon">📦</div>
                        <h3>No equipment found</h3>
                        <p>Try adjusting your filters or search query.</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Equipment Details Modal -->
    <div id="equipmentDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="detailsModalTitle">Equipment Details</h2>
                <button class="modal-close" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div id="equipmentDetailsContent" class="modal-body">
                <div class="loading">Loading...</div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal">
        <div class="modal-content delete-confirm-modal">
            <div class="delete-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2>Delete Equipment?</h2>
            <p id="deleteConfirmMessage">Are you sure you want to delete this equipment?</p>
            <p class="delete-warning">This action cannot be undone.</p>
            <div class="delete-actions">
                <button type="button" class="btn-cancel" onclick="closeDeleteConfirm()">Cancel</button>
                <button type="button" class="btn-confirm-delete" onclick="confirmDeleteAction()">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Manage Equipment Modal -->
    <div id="manageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Manage Equipment</h2>
                <button class="modal-close" onclick="closeManageModal()">&times;</button>
            </div>
            <div id="manageModalContent" class="modal-body">
                <div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>
    </div>

    <!-- Add Equipment Modal -->
    <div id="equipmentModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Add Equipment</h2>
            <form id="equipmentForm" method="POST" action="add_equipment.php" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="equipmentId">
                <input type="hidden" name="current_image_path" id="current_image_path">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Equipment Name *</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="rfid_tag">RFID Tag *</label>
                        <input type="text" id="rfid_tag" name="rfid_tag" placeholder="e.g., EQ001 or physical tag value" required>
                        <div id="rfid_tag_feedback" style="margin-top:6px; font-size:0.9rem;"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id">
                            <option value="">Select Category</option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?= $category['id'] ?>">
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" min="0" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="size_category">Item Size *</label>
                        <select id="size_category" name="size_category" required>
                            <?php foreach($size_options as $size): ?>
                                <option value="<?= $size ?>" <?= $size === 'Medium' ? 'selected' : '' ?>><?= $size ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="importance_level">Importance Level</label>
                        <select id="importance_level" name="importance_level">
                            <option value="">Select level</option>
                            <option value="Reserved">Reserved</option>
                            <option value="High-Demand">High-Demand</option>
                            <option value="Frequently Borrowed">Frequently Borrowed</option>
                            <option value="Standard">Standard</option>
                            <option value="Low-Usage">Low-Usage</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="borrow_period_days">Borrow Period (days)</label>
                        <input type="number" id="borrow_period_days" name="borrow_period_days" min="1" placeholder="e.g., 3">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="image_path">Image URL (optional)</label>
                    <input type="text" id="image_path" name="image_path" placeholder="https://example.com/image.jpg">
                </div>
                
                <div class="form-group">
                    <label for="image_file">Upload Image (optional)</label>
                    <input type="file" id="image_file" name="image_file" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Equipment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const categoryPills = document.getElementById('categoryPills');
            const equipmentGrid = document.getElementById('equipmentGrid');
            const emptyState = document.getElementById('emptyState');

            function filterEquipment() {
                const searchTerm = searchInput.value.toLowerCase();
                const activePill = categoryPills.querySelector('.category-pill.active');
                const selectedCategory = (activePill ? activePill.dataset.category : 'all').toLowerCase();
                
                const cards = equipmentGrid.querySelectorAll('.equipment-card');
                let visibleCount = 0;

                cards.forEach(card => {
                    const name = card.dataset.name || '';
                    const category = card.dataset.category || '';
                    
                    const matchesSearch = name.includes(searchTerm);
                    const matchesCategory = selectedCategory === 'all' || category === selectedCategory;
                    
                    if (matchesSearch && matchesCategory) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Show/hide empty state
                if (visibleCount === 0) {
                    emptyState.style.display = 'block';
                } else {
                    emptyState.style.display = 'none';
                }
            }

            // Add event listeners
            searchInput.addEventListener('input', filterEquipment);

            // Category pill clicks
            categoryPills.addEventListener('click', function(e) {
                const pill = e.target.closest('.category-pill');
                if (!pill) return;
                // Toggle active
                [...categoryPills.querySelectorAll('.category-pill')].forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                // Sync hidden select for any other logic
                categoryFilter.value = pill.dataset.category || 'all';
                filterEquipment();
            });

            // Initial filter
            filterEquipment();
            
            // Sidebar toggle functionality handled by sidebar component
        });
        
        // Logout function
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }
        
        // Stock filter function
        function filterByStock(value) {
            const url = new URL(window.location);
            url.searchParams.set('stock', value);
            window.location.href = url.toString();
        }
        
        // Modal functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Equipment';
            document.getElementById('formAction').value = 'add';
            document.getElementById('equipmentForm').reset();
            document.getElementById('equipmentId').value = '';
            document.getElementById('rfid_tag').value = '';
            const sizeSelect = document.getElementById('size_category');
            if (sizeSelect) {
                sizeSelect.value = 'Medium';
            }
            document.getElementById('equipmentModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('equipmentModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('equipmentModal');
            const detailsModal = document.getElementById('equipmentDetailsModal');
            if (event.target === modal) {
                closeModal();
            }
            if (event.target === detailsModal) {
                closeDetailsModal();
            }
        }
        
        // View equipment details
        function viewEquipmentDetails(id) {
            const modal = document.getElementById('equipmentDetailsModal');
            const content = document.getElementById('equipmentDetailsContent');
            
            // Show modal with loading state
            modal.style.display = 'block';
            content.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            
            // Fetch equipment details via AJAX
            fetch(`get_equipment_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const equipment = data.equipment;
                        const availableQty = (equipment.available_quantity !== undefined && equipment.available_quantity !== null)
                            ? equipment.available_quantity
                            : (equipment.computed_available !== undefined ? equipment.computed_available : null);
                        const borrowedQty = (equipment.borrowed_quantity !== undefined && equipment.borrowed_quantity !== null)
                            ? equipment.borrowed_quantity
                            : null;

                        content.innerHTML = `
                            <div class="equipment-details">
                                ${equipment.image_path ? `
                                    <div class="detail-image">
                                        <img src="${equipment.image_path.startsWith('uploads/') ? '../' + equipment.image_path : equipment.image_path}" 
                                             alt="${equipment.name}" 
                                             onerror="this.src='../uploads/placeholder.png'">
                                    </div>
                                ` : ''}
                                <div class="detail-info">
                                    <div class="detail-row">
                                        <span class="detail-label">Equipment ID:</span>
                                        <span class="detail-value">#${equipment.id}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Name:</span>
                                        <span class="detail-value">${equipment.name}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">RFID Tag:</span>
                                        <span class="detail-value">${equipment.rfid_tag || 'N/A'}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Category:</span>
                                        <span class="detail-value">${equipment.category_name || 'Uncategorized'}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Quantity:</span>
                                        <span class="detail-value">${equipment.quantity || 0}</span>
                                    </div>
                                    ${availableQty !== null ? `
                                        <div class="detail-row">
                                            <span class="detail-label">Available:</span>
                                            <span class="detail-value">${availableQty}</span>
                                        </div>
                                    ` : ''}
                                    ${borrowedQty !== null ? `
                                        <div class="detail-row">
                                            <span class="detail-label">Borrowed:</span>
                                            <span class="detail-value">${borrowedQty}</span>
                                        </div>
                                    ` : ''}
                                    ${equipment.description ? `
                                        <div class="detail-row">
                                            <span class="detail-label">Description:</span>
                                            <span class="detail-value">${equipment.description}</span>
                                        </div>
                                    ` : ''}
                                    <div class="detail-row">
                                        <span class="detail-label">Added:</span>
                                        <span class="detail-value">${equipment.created_at || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        content.innerHTML = `<div class="error-message">${data.message || 'Failed to load equipment details.'}</div>`;
                    }
                })
                .catch(error => {
                    content.innerHTML = '<div class="error-message">Error loading equipment details. Please try again.</div>';
                    console.error('Error:', error);
                });
        }
        
        function closeDetailsModal() {
            document.getElementById('equipmentDetailsModal').style.display = 'none';
        }
        
        // Manage Equipment Functions
        function manageEquipment(id) {
            const modal = document.getElementById('manageModal');
            const content = document.getElementById('manageModalContent');
            
            // Show modal with loading state
            modal.style.display = 'block';
            content.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            
            // Fetch equipment details
            fetch(`/Capstone/admin/get_equipment_details.php?id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const equipment = data.equipment;
                        content.innerHTML = `
                            <form id="manageForm" onsubmit="updateEquipment(event, ${equipment.id})">
                                ${equipment.image_path ? `
                                    <div class="manage-image-preview">
                                        <img src="${equipment.image_path.startsWith('uploads/') ? '../' + equipment.image_path : equipment.image_path}" 
                                             alt="${equipment.name}"
                                             onerror="this.parentElement.style.display='none'">
                                    </div>
                                ` : ''}
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="manage_name">Equipment Name *</label>
                                        <input type="text" id="manage_name" name="name" value="${equipment.name}" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="manage_rfid">RFID Tag *</label>
                                        <input type="text" id="manage_rfid" name="rfid_tag" value="${equipment.rfid_tag || ''}" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="manage_category">Category</label>
                                        <select id="manage_category" name="category_id">
                                            <option value="">Select Category</option>
                                            <?php foreach($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="manage_quantity">Quantity *</label>
                                        <input type="number" id="manage_quantity" name="quantity" value="${equipment.quantity || 0}" min="0" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="manage_size_category">Item Size *</label>
                                        <select id="manage_size_category" name="size_category" required>
                                            <?php foreach($size_options as $size): ?>
                                                <option value="<?= $size ?>"><?= $size ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="manage_importance_level">Importance Level</label>
                                        <select id="manage_importance_level" name="importance_level">
                                            <option value="">Select level</option>
                                            <option value="Reserved">Reserved</option>
                                            <option value="High-Demand">High-Demand</option>
                                            <option value="Frequently Borrowed">Frequently Borrowed</option>
                                            <option value="Standard">Standard</option>
                                            <option value="Low-Usage">Low-Usage</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="manage_borrow_period_days">Borrow Period (days)</label>
                                        <input type="number" id="manage_borrow_period_days" name="borrow_period_days" min="1" value="${equipment.borrow_period_days || ''}" placeholder="e.g., 3">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="manage_image">Image URL (optional)</label>
                                    <input type="text" id="manage_image" name="image_path" value="${equipment.image_path || ''}" placeholder="https://example.com/image.jpg">
                                </div>
                                
                                <div class="form-group">
                                    <label for="manage_image_file">Upload New Image (optional)</label>
                                    <input type="file" id="manage_image_file" name="image_file" accept="image/*">
                                </div>
                                
                                <div class="form-group">
                                    <label for="manage_description">Description</label>
                                    <textarea id="manage_description" name="description" rows="3">${equipment.description || ''}</textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" class="btn-cancel" onclick="closeManageModal()">Cancel</button>
                                    <button type="button" class="btn-delete" onclick="confirmDeleteEquipment(${equipment.id}, '${equipment.name.replace(/'/g, "\\'")}')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                    <button type="submit" class="btn-save">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                </div>
                            </form>
                        `;

                        // Set the selected category and size
                        const manageCategorySelect = document.getElementById('manage_category');
                        if (manageCategorySelect && equipment.category_id) {
                            manageCategorySelect.value = equipment.category_id;
                        }
                        const manageSizeSelect = document.getElementById('manage_size_category');
                        if (manageSizeSelect) {
                            manageSizeSelect.value = equipment.size_category || 'Medium';
                        }
                        const manageImportanceSelect = document.getElementById('manage_importance_level');
                        if (manageImportanceSelect) {
                            manageImportanceSelect.value = equipment.importance_level || '';
                        }
                    } else {
                        content.innerHTML = `<div class="error-message">${data.message || 'Failed to load equipment details.'}</div>`;
                    }
                })
                .catch(error => {
                    content.innerHTML = `<div class="error-message">Error loading equipment details: ${error.message}<br><br>Please check the browser console for more details.</div>`;
                    console.error('Error:', error);
                    alert('Failed to load equipment details. Error: ' + error.message);
                });
        }
        
        function closeManageModal() {
            document.getElementById('manageModal').style.display = 'none';
        }
        
        function updateEquipment(event, id) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('id', id);
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            fetch('update_equipment_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Equipment updated successfully!', 'success');
                    closeManageModal();
                    // Reload the page to refresh the equipment list
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Error: ' + (data.message || 'Failed to update equipment.'), 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                showToast('Error updating equipment. Please try again.', 'error');
                console.error('Error:', error);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        // Store delete action data
        let pendingDeleteId = null;
        
        function confirmDeleteEquipment(id, name) {
            pendingDeleteId = id;
            const modal = document.getElementById('deleteConfirmModal');
            const message = document.getElementById('deleteConfirmMessage');
            message.innerHTML = `Are you sure you want to delete <strong>"${name}"</strong>?`;
            modal.style.display = 'block';
        }
        
        function closeDeleteConfirm() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            pendingDeleteId = null;
        }
        
        function confirmDeleteAction() {
            if (pendingDeleteId) {
                deleteEquipmentAjax(pendingDeleteId);
                closeDeleteConfirm();
            }
        }
        
        function deleteEquipmentAjax(id) {
            fetch('delete_equipment_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Equipment deleted successfully!', 'success');
                    closeManageModal();
                    // Reload the page to refresh the equipment list
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Error: ' + (data.message || 'Failed to delete equipment.'), 'error');
                }
            })
            .catch(error => {
                showToast('Error deleting equipment. Please try again.', 'error');
                console.error('Error:', error);
            });
        }
        
        // Auto-fill borrow period days from importance level (Add & Manage modals)
        (function(){
            const mapDays = (level) => {
                if (!level) return '';
                const key = String(level).toLowerCase();
                if (key === 'reserved' || key === 'high-demand') return 1;
                if (key === 'frequently borrowed') return 2;
                if (key === 'standard') return 3;
                if (key === 'low-usage') return 5;
                return '';
            };

            // Add modal
            const impAdd = document.getElementById('importance_level');
            const daysAdd = document.getElementById('borrow_period_days');
            if (impAdd && daysAdd) {
                impAdd.addEventListener('change', function(){
                    const v = mapDays(this.value);
                    if (v !== '') daysAdd.value = v;
                });
            }

            // Manage modal (dynamic content) — delegate via MutationObserver
            const manageModal = document.getElementById('manageModal');
            if (manageModal) {
                const obs = new MutationObserver(() => {
                    const imp = document.getElementById('manage_importance_level');
                    const days = document.getElementById('manage_borrow_period_days');
                    if (imp && days && !imp.dataset._bound) {
                        imp.addEventListener('change', function(){
                            const v = mapDays(this.value);
                            if (v !== '') days.value = v;
                        });
                        imp.dataset._bound = '1';
                    }
                });
                obs.observe(manageModal, { childList: true, subtree: true });
            }
        })();
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>

</body>
</html>
