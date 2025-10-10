<?php
// Database connection
$host = "localhost";
$user = "root";       
$password = "";   // no password for XAMPP
$dbname = "capstone";

// Create connection
$db_connected = true;
$db_error = null;
$conn = @new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    $db_connected = false;
    $db_error = $conn->connect_error;
}

// Get categories for filter
$categories = [];
if ($db_connected) {
    if ($result = $conn->query("SELECT id, name FROM categories ORDER BY name")) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        $result->free();
    }
}

// Get equipment data
$equipment = [];
if ($db_connected) {
    $query = "SELECT e.*, c.name as category_name 
              FROM equipment e 
              LEFT JOIN categories c ON e.category_id = c.id 
              ORDER BY e.name";
    if ($result = $conn->query($query)) {
        while ($row = $result->fetch_assoc()) {
            $equipment[] = $row;
        }
        $result->free();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Inventory</title>
    <link rel="stylesheet" href="admin-styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="inventory-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="View and filter available equipment in the inventory">
</head>
<body>
	<div class="admin-container">
		<!-- Sidebar -->
		<nav class="sidebar">
			<div class="sidebar-header">
				<div class="logo">
					<img src="../uploads/De lasalle ASMC.png" alt="De La Salle ASMC Logo" class="main-logo" style="height:30px; width:auto;">
					<span>Admin Panel</span>
				</div>
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
					<a href="admin-inventory.php"><i class="fas fa-cog"></i><span>Manage Equipment</span></a>
				</li>
				<li class="nav-item active">
					<a href="inventory.php"><i class="fas fa-eye"></i><span>View Inventory</span></a>
				</li>
				<li class="nav-item">
					<a href="student-activity.php"><i class="fas fa-users"></i><span>User Activity</span></a>
				</li>
			</ul>
			<div class="sidebar-footer">
					<i class="fas fa-sign-out-alt"></i> Logout
				</button>
			</div>
		</nav>

        <!-- Main -->
        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Equipment Inventory</h1>
            </header>
            <section class="content-section active">
                <p class="page-subtitle">Browse, search, and filter all equipment</p>

                <div class="inventory-toolbar">
                    <div class="search-wrapper" style="width: 500px; min-width: 400px; max-width: 600px; display: flex; align-items: center;">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" type="text" placeholder="Search by name, ID, condition..." style="width: 100%; height: 38px; border: none; outline: none; font-size: 1rem;">
                    </div>
                    <div class="filters">
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
                        <select id="conditionFilter">
                            <option value="all">All Conditions</option>
                            <option value="Excellent">Excellent</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="Out of Service">Out of Service</option>
                        </select>
                    </div>
                </div>

                <div class="equipment-grid" id="equipmentGrid">
                    <?php if (empty($equipment)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📦</div>
                            <h3>No equipment found</h3>
                            <p>No equipment has been added to the inventory yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($equipment as $item): ?>
                            <div class="equipment-card" data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>"
                                 data-category="<?= htmlspecialchars(strtolower($item['category_name'] ?? '')) ?>"
                                 data-condition="<?= htmlspecialchars(strtolower($item['item_condition'] ?? '')) ?>">
                                <?php if (!empty($item['image_path'])): ?>
                                    <?php
                                        $image_src = $item['image_path'];
                                        if (strpos($image_src, 'uploads/') === 0) {
                                            $image_src = '../' . $image_src;
                                        }
                                    ?>
                                    <div class="equipment-thumb">
                                        <img src="<?= htmlspecialchars($image_src) ?>"
                                             alt="<?= htmlspecialchars($item['name']) ?>"
                                             onerror="this.style.display='none'">
                                    </div>
                                <?php else: ?>
                                    <div class="equipment-thumb placeholder"></div>
                                <?php endif; ?>

                                <div class="equipment-info">
                                    <h3 class="equipment-name"><?= htmlspecialchars($item['name']) ?></h3>
                                    <div class="equipment-qty">Quantity: <?= htmlspecialchars($item['quantity'] ?? 0) ?></div>
                                    <div class="equipment-meta-line">
                                        <span class="badge category"><?= htmlspecialchars($item['category_name'] ?? '—') ?></span>
                                        <span class="status-badge <?= htmlspecialchars(strtolower(str_replace(' ', '-', $item['item_condition'] ?? ''))) ?>">
                                            <?= htmlspecialchars($item['item_condition'] ?? 'Unknown') ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <button class="btn btn-secondary" onclick="viewItem(<?= $item['id'] ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if (($item['quantity'] ?? 0) > 0): ?>
                                        <button class="btn btn-primary" onclick="borrowItem(<?= $item['id'] ?>)">
                                            <i class="fas fa-arrow-up"></i> Borrow
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-disabled" disabled>
                                            <i class="fas fa-times"></i> Out of Stock
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="emptyState" class="empty-state" style="display: none;">
                    <div class="empty-icon">📦</div>
                    <h3>No equipment found</h3>
                    <p>Try adjusting your filters or search query.</p>
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

    <script src="inventory-script.js"></script>
    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }

        // View item details
        function viewItem(id) {
            viewEquipmentDetails(id);
        }
        
        // View equipment details (same as admin-equipment-inventory.php)
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
                                    ${equipment.available_quantity !== null ? `
                                        <div class="detail-row">
                                            <span class="detail-label">Available:</span>
                                            <span class="detail-value">${equipment.available_quantity}</span>
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

        // Borrow item
        function borrowItem(id) {
            window.location.href = `borrow.php?id=${id}`;
        }

        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const categoryPills = document.getElementById('categoryPills');
            const conditionFilter = document.getElementById('conditionFilter');
            const equipmentGrid = document.getElementById('equipmentGrid');
            const emptyState = document.getElementById('emptyState');

            function filterEquipment() {
                const searchTerm = searchInput.value.toLowerCase();
                const activePill = categoryPills.querySelector('.category-pill.active');
                const selectedCategory = (activePill ? activePill.dataset.category : 'all').toLowerCase();
                const selectedCondition = conditionFilter.value.toLowerCase();
                
                const cards = equipmentGrid.querySelectorAll('.equipment-card');
                let visibleCount = 0;

                cards.forEach(card => {
                    const name = card.dataset.name || '';
                    const category = card.dataset.category || '';
                    const condition = card.dataset.condition || '';
                    
                    const matchesSearch = name.includes(searchTerm);
                    const matchesCategory = selectedCategory === 'all' || category === selectedCategory;
                    const matchesCondition = selectedCondition === 'all' || condition === selectedCondition;
                    
                    if (matchesSearch && matchesCategory && matchesCondition) {
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
            conditionFilter.addEventListener('change', filterEquipment);

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
        });
    </script>
</body>
</html>
