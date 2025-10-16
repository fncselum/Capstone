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

// Lightweight AJAX endpoint to check RFID availability
if ($db_connected && isset($_GET['action']) && $_GET['action'] === 'check_rfid') {
    header('Content-Type: application/json');
    $rfid = trim($_GET['rfid_tag'] ?? '');
    $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
    // Verify column exists
    $rfid_col = $conn->query("SHOW COLUMNS FROM equipment LIKE 'rfid_tag'");
    if (!$rfid_col || $rfid_col->num_rows === 0) {
        echo json_encode(['error' => 'rfid_tag column missing']);
        exit;
    }
    if ($rfid === '') {
        echo json_encode(['exists' => false]);
        exit;
    }
    $rfid_esc = $conn->real_escape_string($rfid);
    $sql = "SELECT id, name FROM equipment WHERE rfid_tag = '$rfid_esc'" . ($exclude_id > 0 ? " AND id <> $exclude_id" : "") . " LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo json_encode(['exists' => true, 'id' => (int)$row['id'], 'name' => $row['name']]);
    } else {
        echo json_encode(['exists' => false]);
    }
    exit;
}

// Messaging
$error_message = null;
$success_message = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_connected) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Read and validate RFID tag
                $rfid_tag = trim($_POST['rfid_tag'] ?? '');
                if ($rfid_tag === '') {
                    $error_message = "RFID Tag is required.";
                    break;
                }
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
                // Ensure rfid_tag column exists
                $rfid_col = $conn->query("SHOW COLUMNS FROM equipment LIKE 'rfid_tag'");
                if (!$rfid_col || $rfid_col->num_rows === 0) {
                    $error_message = "The equipment table is missing the rfid_tag column. Please run:\n" .
                        "ALTER TABLE equipment ADD COLUMN rfid_tag VARCHAR(64) NULL;\n" .
                        "CREATE UNIQUE INDEX idx_equipment_rfid_tag ON equipment (rfid_tag);";
                    break;
                }
                // Check for duplicate RFID tag
                $rfid_esc = $conn->real_escape_string($rfid_tag);
                if ($dup = $conn->query("SELECT id, name FROM equipment WHERE rfid_tag = '$rfid_esc' LIMIT 1")) {
                    if ($dup->num_rows > 0) {
                        $row = $dup->fetch_assoc();
                        $error_message = "This RFID tag ($rfid_tag) is already used by '" . htmlspecialchars($row['name']) . "' (ID " . (int)$row['id'] . ").";
                        $dup->free();
                        break;
                    }
                    $dup->free();
                }

                $sql = "INSERT INTO equipment (rfid_tag, name, category_id, quantity, item_condition, image_path, description) VALUES ('$rfid_esc', '$name', $category_id, $quantity, '$condition', '$image_path', '$description')";
                if ($conn->query($sql)) {
                    $success_message = "Equipment saved successfully.";
                } else {
                    $error_message = "Failed to save equipment: " . $conn->error;
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                // Read and validate RFID tag
                $rfid_tag = trim($_POST['rfid_tag'] ?? '');
                if ($rfid_tag === '') {
                    $error_message = "RFID Tag is required.";
                    break;
                }
                $name = $conn->real_escape_string($_POST['name']);
                $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 'NULL';
                $quantity = (int)$_POST['quantity'];
                $condition = $conn->real_escape_string($_POST['item_condition']);
                $image_path = $conn->real_escape_string($_POST['image_path']);
                $current_image_path = $conn->real_escape_string($_POST['current_image_path'] ?? '');
                if (!empty($_FILES['image_file']['name'])) {
                    $uploadDir = __DIR__ . '/uploads/';
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
                } else if (empty($image_path) && !empty($current_image_path)) {
                    // Keep existing image if no new one provided and URL left empty
                    $image_path = $current_image_path;
                }
                $description = $conn->real_escape_string($_POST['description']);
                // Ensure rfid_tag column exists
                $rfid_col = $conn->query("SHOW COLUMNS FROM equipment LIKE 'rfid_tag'");
                if (!$rfid_col || $rfid_col->num_rows === 0) {
                    $error_message = "The equipment table is missing the rfid_tag column. Please run:\n" .
                        "ALTER TABLE equipment ADD COLUMN rfid_tag VARCHAR(64) NULL;\n" .
                        "CREATE UNIQUE INDEX idx_equipment_rfid_tag ON equipment (rfid_tag);";
                    break;
                }
                // Check for duplicate RFID tag on other records
                $rfid_esc = $conn->real_escape_string($rfid_tag);
                if ($dup = $conn->query("SELECT id, name FROM equipment WHERE rfid_tag = '$rfid_esc' AND id <> $id LIMIT 1")) {
                    if ($dup->num_rows > 0) {
                        $row = $dup->fetch_assoc();
                        $error_message = "This RFID tag ($rfid_tag) is already used by '" . htmlspecialchars($row['name']) . "' (ID " . (int)$row['id'] . ").";
                        $dup->free();
                        break;
                    }
                    $dup->free();
                }

                $sql = "UPDATE equipment SET rfid_tag='$rfid_esc', name='$name', category_id=$category_id, quantity=$quantity, item_condition='$condition', image_path='$image_path', description='$description' WHERE id=$id";
                if ($conn->query($sql)) {
                    $success_message = "Equipment updated successfully.";
                } else {
                    $error_message = "Failed to update equipment: " . $conn->error;
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                $sql = "DELETE FROM equipment WHERE id=$id";
                $conn->query($sql);
                break;
        }
        // Redirect to prevent form resubmission only if success and no errors
        if (!$error_message) {
            header('Location: admin-inventory.php');
            exit;
        }
    }
}

// Get categories
$categories = [];
if ($db_connected) {
    // Ensure default categories exist
    $defaultCategories = ['Sport equipment', 'Digital Equipment', 'Room Equipment'];
    foreach ($defaultCategories as $defaultCategoryName) {
        $escapedName = $conn->real_escape_string($defaultCategoryName);
        $existsQuery = "SELECT id FROM categories WHERE name='$escapedName' LIMIT 1";
        if ($res = $conn->query($existsQuery)) {
            if ($res->num_rows === 0) {
                $conn->query("INSERT INTO categories (name) VALUES ('$escapedName')");
            }
            $res->free();
        }
    }

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
    <title>Admin - Equipment Inventory</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="admin-inventor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../uploads/De lasalle ASMC.png" alt="De La Salle ASMC Logo" class="main-logo" style="height:30px; width:auto;">
                    <span>Admin Panel</span>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="admin-dashboard.php#dashboard"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-dashboard.php#inventory"><i class="fas fa-boxes"></i><span>Equipment Inventory</span></a>
                </li>
                <li class="nav-item">
                    <a href="reports.php"><i class="fas fa-file-alt"></i><span>Reports</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-dashboard.php#transactions"><i class="fas fa-exchange-alt"></i><span>All Transactions</span></a>
                </li>
                <li class="nav-item">
                    <a href="admin-user-activity.php"><i class="fas fa-users"></i><span>User Activity</span></a>
                </li>
                <li class="nav-item active">
                    <a href="admin-inventory.php"><i class="fas fa-cog"></i><span>Manage Equipment</span></a>
                </li>
                <li class="nav-item">
                    <a href="inventory.php"><i class="fas fa-eye"></i><span>View Inventory</span></a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <button class="logout-btn" onclick="window.location.href='logout.php'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </nav>
        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Manage Equipment</h1>
            </header>
            <section class="content-section active">
        <div class="section-header">
            <h1><i class="fas fa-tools"></i> Equipment Inventory Management</h1>
            <button class="add-btn" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Equipment
            </button>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error" style="margin: 10px 0; padding: 10px; border-radius: 6px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error_message ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" style="margin: 10px 0; padding: 10px; border-radius: 6px; background:#d4edda; color:#155724; border:1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i>
                <?= $success_message ?>
            </div>
        <?php endif; ?>

        <div class="transactions-table">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Condition</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($equipment as $item): ?>
                    <tr>
                        <td><?= $item['id'] ?></td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= htmlspecialchars($item['category_name'] ?? '—') ?></td>
                        <td class="<?= ($item['quantity'] ?? 0) == 0 ? 'quantity-0' : (($item['quantity'] ?? 0) <= 2 ? 'quantity-low' : '') ?>">
                            <?= $item['quantity'] ?? 0 ?>
                        </td>
                        <td><?= htmlspecialchars($item['item_condition'] ?? '—') ?></td>
                        <td><?= htmlspecialchars(substr($item['description'] ?? '', 0, 50)) . (strlen($item['description'] ?? '') > 50 ? '...' : '') ?></td>
                        <td>
                            <div class="action-buttons">
                                <button type="button" class="action-btn edit-btn" title="Edit" data-item='<?= json_encode($item, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>'><i class="fas fa-edit"></i></button>
                                <button type="button" class="action-btn" title="Delete" onclick='deleteEquipment(<?= $item['id'] ?>, <?= json_encode($item["name"], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
            </section>
        </main>
    </div>

    <!-- Add/Edit Modal -->
    <div id="equipmentModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Add Equipment</h2>
            <form id="equipmentForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="equipmentId">
                <input type="hidden" name="current_image_path" id="current_image_path">
                
                <div class="form-group">
                    <label for="name">Equipment Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="rfid_tag">RFID Tag *</label>
                    <input type="text" id="rfid_tag" name="rfid_tag" placeholder="e.g., EQ001 or physical tag value" required>
                    <div id="rfid_tag_feedback" style="margin-top:6px; font-size:0.9rem;"></div>
                </div>
                
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
                
                <div class="form-group">
                    <label for="item_condition">Condition</label>
                    <select id="item_condition" name="item_condition">
                        <option value="">Select Condition</option>
                        <option value="Excellent">Excellent</option>
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Poor">Poor</option>
                        <option value="Out of Service">Out of Service</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label for="image_path">Image URL (optional)</label>
                    <input type="text" id="image_path" name="image_path" placeholder="https://example.com/image.jpg">
                </div>
                <div class="form-group">
                    <label for="image_file">Upload Image (optional)</label>
                    <input type="file" id="image_file" name="image_file" accept="image/*">
                </div>
                
                <div class="form-group full-width">
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
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Equipment';
            document.getElementById('formAction').value = 'add';
            document.getElementById('equipmentForm').reset();
            document.getElementById('equipmentId').value = '';
            document.getElementById('rfid_tag').value = '';
            document.getElementById('equipmentModal').style.display = 'block';
        }

        function openEditModal(item) {
            document.getElementById('modalTitle').textContent = 'Edit Equipment';
            document.getElementById('formAction').value = 'update';
            document.getElementById('equipmentId').value = item.id;
            document.getElementById('name').value = item.name;
            document.getElementById('rfid_tag').value = item.rfid_tag || '';
            document.getElementById('category_id').value = item.category_id || '';
            document.getElementById('quantity').value = item.quantity || 0;
            document.getElementById('item_condition').value = item.item_condition || '';
            document.getElementById('image_path').value = item.image_path || '';
            document.getElementById('current_image_path').value = item.image_path || '';
            document.getElementById('description').value = item.description || '';
            document.getElementById('equipmentModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('equipmentModal').style.display = 'none';
        }

        function deleteEquipment(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('equipmentModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Delegate edit button clicks to ensure reliability
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.edit-btn');
            if (btn) {
                try {
                    const data = btn.getAttribute('data-item');
                    if (data) {
                        const item = JSON.parse(data);
                        openEditModal(item);
                    }
                } catch (err) {
                    console.error('Failed to open edit modal:', err);
                }
            }
        });

        // --- RFID duplicate checking ---
        let rfidInUse = false;
        const rfidInput = document.getElementById('rfid_tag');
        const rfidFeedback = document.getElementById('rfid_tag_feedback');
        const equipmentForm = document.getElementById('equipmentForm');

        async function checkRFIDTag() {
            if (!rfidInput) return;
            const tag = rfidInput.value.trim();
            const excludeId = document.getElementById('equipmentId').value || '';
            if (tag === '') {
                rfidInUse = false;
                rfidFeedback.textContent = '';
                rfidFeedback.style.color = '';
                return;
            }
            try {
                const url = `admin-inventory.php?action=check_rfid&rfid_tag=${encodeURIComponent(tag)}&exclude_id=${encodeURIComponent(excludeId)}`;
                const res = await fetch(url, { headers: { 'Cache-Control': 'no-cache' } });
                const data = await res.json();
                if (data && data.exists) {
                    rfidInUse = true;
                    rfidFeedback.style.color = '#a94442';
                    rfidFeedback.innerHTML = `This tag is used by <strong>${data.name}</strong> (ID ${data.id}). ` +
                        `<button type="button" id="btnTryAnother" class="btn-small" style="margin-left:8px;" onclick="tryAnotherTag()">Use another tag</button>` +
                        `<button type="button" id="btnCloseModal" class="btn-small" style="margin-left:6px;" onclick="closeModal()">Close</button>`;
                } else {
                    rfidInUse = false;
                    rfidFeedback.style.color = '#155724';
                    rfidFeedback.textContent = 'Tag is available.';
                }
            } catch (err) {
                // Silent fail; do not block the admin
                rfidInUse = false;
                rfidFeedback.textContent = '';
                rfidFeedback.style.color = '';
            }
        }

        function tryAnotherTag() {
            if (!rfidInput) return;
            rfidInput.focus();
            rfidInput.select();
        }

        if (rfidInput) {
            rfidInput.addEventListener('blur', checkRFIDTag);
            rfidInput.addEventListener('input', function() {
                // Clear message while typing; re-check on blur
                rfidFeedback.textContent = '';
                rfidFeedback.style.color = '';
            });
        }

        if (equipmentForm) {
            equipmentForm.addEventListener('submit', function(e) {
                if (rfidInUse) {
                    e.preventDefault();
                    alert('This RFID tag is already used. Please enter a different tag or close the form.');
                }
            });
        }

        // If server-side flagged an RFID error, reopen modal and preserve posted values
        <?php if (!empty($error_message) && isset($_POST['action']) && in_array($_POST['action'], ['add','update'])): ?>
        (function(){
            const isUpdate = <?= json_encode($_POST['action'] === 'update') ?>;
            document.getElementById('modalTitle').textContent = isUpdate ? 'Edit Equipment' : 'Add Equipment';
            document.getElementById('formAction').value = isUpdate ? 'update' : 'add';
            document.getElementById('equipmentId').value = <?= json_encode((int)($_POST['id'] ?? 0)) ?>;
            document.getElementById('name').value = <?= json_encode($_POST['name'] ?? '') ?>;
            document.getElementById('rfid_tag').value = <?= json_encode($_POST['rfid_tag'] ?? '') ?>;
            document.getElementById('category_id').value = <?= json_encode($_POST['category_id'] ?? '') ?>;
            document.getElementById('quantity').value = <?= json_encode((int)($_POST['quantity'] ?? 0)) ?>;
            document.getElementById('item_condition').value = <?= json_encode($_POST['item_condition'] ?? '') ?>;
            document.getElementById('image_path').value = <?= json_encode($_POST['image_path'] ?? '') ?>;
            document.getElementById('current_image_path').value = <?= json_encode($_POST['current_image_path'] ?? '') ?>;
            document.getElementById('description').value = <?= json_encode($_POST['description'] ?? '') ?>;
            document.getElementById('equipmentModal').style.display = 'block';
            // Also show the duplicate message inline by triggering a check
            checkRFIDTag();
        })();
        <?php endif; ?>
    </script>
</body>
</html>
