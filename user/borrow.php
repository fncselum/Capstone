<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$student_id = $_SESSION['student_id'] ?? 'Guest';
$rfid_tag = $_SESSION['rfid_tag'] ?? '';

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$db_connected = true;
$db_error = null;
$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
	$db_connected = false;
	$db_error = $conn->connect_error;
}

$categories = [];
$equipment_list = [];
$message = null;
$error = null;

if ($db_connected) {
	// Handle borrow action
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'borrow') {
		$equipment_id = (int)($_POST['equipment_id'] ?? 0);
		$due_date = trim($_POST['due_date'] ?? '');
		$requested_qty = (int)($_POST['quantity'] ?? 1);
		if ($requested_qty <= 0) {
			$requested_qty = 1;
		}
		
		if ($equipment_id > 0 && !empty($due_date)) {
			$conn->begin_transaction();
			try {
				// Get equipment and inventory details with lock
				$stmt = $conn->prepare("SELECT e.*, 
					i.quantity AS inventory_quantity,
					i.available_quantity,
					i.borrowed_quantity,
					i.damaged_quantity,
					i.minimum_stock_level,
					i.availability_status,
					i.equipment_id AS inventory_equipment_id,
					i.item_size AS inventory_item_size
				FROM equipment e 
				LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id 
				WHERE e.id = ? FOR UPDATE");
				$stmt->bind_param("i", $equipment_id);
				$stmt->execute();
				$result = $stmt->get_result();
				
				if ($result->num_rows === 1) {
					$equip_data = $result->fetch_assoc();
					$total_qty = (int)($equip_data['inventory_quantity'] ?? $equip_data['quantity'] ?? 0);
					$borrowed_qty = (int)($equip_data['borrowed_quantity'] ?? 0);
					$damaged_qty = (int)($equip_data['damaged_quantity'] ?? 0);
					$available_qty = $equip_data['available_quantity'] !== null
						? (int)$equip_data['available_quantity']
						: max($total_qty - $borrowed_qty - $damaged_qty, 0);
					$equipment_name = $equip_data['name'] ?? 'Unknown';
					$condition_before = $equip_data['item_condition'] ?? 'Good';
					$min_stock = (int)($equip_data['minimum_stock_level'] ?? 1);
					$rfid_code = $equip_data['rfid_tag'] ?? '';
					$inventory_equipment_id = $equip_data['inventory_equipment_id'] ?? null;
					$item_size = $equip_data['size_category'] ?? $equip_data['inventory_item_size'] ?? 'Medium';
					
					// Ensure inventory record exists and item is available
					if (empty($rfid_code) || empty($inventory_equipment_id)) {
						$conn->rollback();
						$error = 'Unable to locate inventory record for this equipment. Please contact the administrator.';
					} elseif ($available_qty > 0 && $total_qty > 0) {
						if ($requested_qty > $available_qty) {
							$conn->rollback();
							$error = 'Unable to borrow that many items. Only ' . $available_qty . ' available at the moment.';
						} else {
							// Determine flow based on size
							$transaction_type = 'Borrow';
							$quantity = $requested_qty;
							$expected_return_date = date('Y-m-d H:i:s', strtotime($due_date));
							$notes = "Borrowed via kiosk by student ID: " . $student_id;
							$penalty_applied = 0;
							$status = 'Active';
							$requiresApproval = (strtolower($item_size) === 'large');
							$borrow_status = $requiresApproval ? 'Pending Approval' : 'Active';
							$approval_status = $requiresApproval ? 'Pending' : 'Approved';
							$approved_by = $requiresApproval ? null : 1; // Set to admin ID 1 for auto-approved items
							$approved_at = $requiresApproval ? null : date('Y-m-d H:i:s');
							$rejection_reason = null;
							$return_review_status = 'Pending';
							$processed_by = $requiresApproval ? null : 1; // Set to admin ID 1 for auto-approved items
							
							$trans_stmt = $conn->prepare("INSERT INTO transactions 
								(user_id, equipment_id, transaction_type, quantity, transaction_date, 
								expected_return_date, condition_before, status, penalty_applied, notes, item_size, approval_status, approved_by, approved_at, rejection_reason, return_review_status, processed_by, created_at, updated_at) 
								VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
							
							$trans_stmt->bind_param("ississsisssissss", 
								$user_id, 
								$rfid_code, 
								$transaction_type, 
								$quantity,
								$expected_return_date, 
								$condition_before,
								$borrow_status,
								$penalty_applied,
								$notes,
								$item_size,
								$approval_status,
								$approved_by,
								$approved_at,
								$rejection_reason,
								$return_review_status,
								$processed_by
							);
							
							if ($trans_stmt->execute()) {
								$transaction_id = $conn->insert_id;
								
								if ($requiresApproval) {
									$conn->commit();
									$message = "Borrow request submitted for <strong>$equipment_name</strong>.<br>Status: <strong>Pending Admin Approval</strong><br>Transaction ID: #$transaction_id";
									$message .= "<br><span style='color:#ff9800;'>⚠ Inventory will update once the admin approves your request.</span>";
								} else {
									$inv_update = $conn->prepare("UPDATE inventory 
									SET available_quantity = available_quantity - ?,
										borrowed_quantity = borrowed_quantity + ?,
										availability_status = CASE
											WHEN (available_quantity - ?) <= 0 THEN 'Out of Stock'
											WHEN (available_quantity - ?) <= IFNULL(minimum_stock_level, 1) THEN 'Low Stock'
											ELSE 'Available'
										END,
										last_updated = NOW()
									WHERE equipment_id = ? AND available_quantity >= ?");
									$inv_update->bind_param("iiiisi", $quantity, $quantity, $quantity, $quantity, $rfid_code, $quantity);
									
									if (!$inv_update->execute() || $inv_update->affected_rows !== 1) {
										throw new Exception('Failed to update inventory. Item may be out of stock.');
									}
									$inv_update->close();

									// Fetch updated inventory status for message display
									$inv_stmt = $conn->prepare("SELECT available_quantity, borrowed_quantity, availability_status FROM inventory WHERE equipment_id = ? LIMIT 1");
									$inv_stmt->bind_param("s", $rfid_code);
									$inv_stmt->execute();
									$inv_result = $inv_stmt->get_result();
									$inv_data = $inv_result ? $inv_result->fetch_assoc() : null;
									$inv_stmt->close();
									
									if (!$inv_data) {
										throw new Exception('Inventory record missing after update.');
									}

									$new_available = (int)$inv_data['available_quantity'];
									$new_status = $inv_data['availability_status'];

									$conn->commit();
									
									$return_date_formatted = date('M j, Y g:i A', strtotime($expected_return_date));
									$quantity_phrase = $quantity . ' ' . ($quantity === 1 ? 'item' : 'items');
									$message = "Equipment borrowed successfully!<br><strong>$equipment_name</strong><br>Borrowed: $quantity_phrase<br>Please return by: $return_date_formatted<br>Transaction ID: #$transaction_id";
									
									if ($new_available <= 0) {
										$message .= "<br><span style='color: #ff9800;'>⚠ This was the last available item</span>";
									} elseif ($new_available <= $min_stock) {
										$message .= "<br><span style='color: #ff9800;'>⚠ Low stock: $new_available remaining</span>";
									} else {
										$message .= "<br><span style='color: #4caf50;'>✔ $new_available remaining in stock</span>";
									}
								}
							}
							else {
								throw new Exception("Failed to record transaction: " . $trans_stmt->error);
							}
							
							$trans_stmt->close();
						}
					} else {
						$conn->rollback();
						$error = 'Sorry, this equipment is currently out of stock. All items are borrowed or unavailable.';
					}
				} else {
					$conn->rollback();
					$error = 'Equipment not found in the system.';
				}
				
				$stmt->close();
			} catch (Exception $ex) {
				$conn->rollback();
				$error = 'Borrow failed: ' . $ex->getMessage();
			}
		} else {
			$error = 'Please provide all required information (equipment and return date).';
		}
	}

	// Fetch categories
	if ($result = $conn->query("SELECT id, name FROM categories ORDER BY name")) {
		while ($row = $result->fetch_assoc()) { $categories[] = $row; }
		$result->free();
	}
	// Fetch equipment with category names and inventory status
	$query = "SELECT e.*, 
	          c.name as category_name,
	          i.quantity AS inventory_quantity,
	          i.available_quantity,
	          i.borrowed_quantity,
	          i.damaged_quantity,
	          i.availability_status,
	          i.item_size,
	          COALESCE(i.available_quantity,
	              GREATEST(e.quantity - COALESCE(i.borrowed_quantity, 0) - COALESCE(i.damaged_quantity, 0), 0)
	          ) AS computed_available
	          FROM equipment e 
	          LEFT JOIN categories c ON e.category_id = c.id 
	          LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id
	          WHERE e.quantity > 0 AND (
	              (i.available_quantity IS NOT NULL AND i.available_quantity > 0)
	              OR (i.available_quantity IS NULL AND GREATEST(e.quantity - COALESCE(i.borrowed_quantity, 0) - COALESCE(i.damaged_quantity, 0), 0) > 0)
	          )
	          ORDER BY e.id ASC";
	if ($result = $conn->query($query)) {
		while ($row = $result->fetch_assoc()) { $equipment_list[] = $row; }
		$result->free();
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Borrow Equipment - Equipment Kiosk</title>
	<link rel="stylesheet" href="styles.css?v=<?= time() ?>">
	<link rel="stylesheet" href="scanner-styles.css?v=<?= time() ?>">
	<link rel="stylesheet" href="borrow.css?v=<?= time() ?>">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
	<div class="container">
		<!-- Background Animation -->
		<div class="background-animation">
			<div class="circle circle-1"></div>
			<div class="circle circle-2"></div>
			<div class="circle circle-3"></div>
		</div>

		<div class="borrow-page-content">
			<!-- Header -->
			<div class="borrow-header">
				<div class="header-left">
					<img src="../uploads/De lasalle ASMC.png" alt="Logo" class="header-logo-small">
					<div>
						<h1 class="page-title">Borrow Equipment</h1>
						<p class="page-subtitle">Student ID: <?= htmlspecialchars($student_id) ?></p>
					</div>
				</div>
				<button class="back-btn" onclick="goBack()">
					<i class="fas fa-arrow-left"></i> Back
				</button>
			</div>

			<?php if ($message): ?>
			<!-- Success Modal -->
			<div id="successModal" class="notification-modal">
				<div class="notification-modal-content success-modal">
					<div class="success-icon-wrapper">
						<div class="success-checkmark">
							<div class="check-icon">
								<span class="icon-line line-tip"></span>
								<span class="icon-line line-long"></span>
								<div class="icon-circle"></div>
								<div class="icon-fix"></div>
							</div>
						</div>
					</div>
					<h2 class="notification-title">Success!</h2>
					<p class="notification-message"><?= $message ?></p>
					<div class="notification-footer">
						<p class="redirect-text">Redirecting in <span id="countdown">10</span> seconds...</p>
					</div>
				</div>
			</div>
			<script>
			// Show modal with animation
			document.getElementById('successModal').style.display = 'flex';
			
			// Countdown and redirect
			let countdown = 10;
			const countdownElement = document.getElementById('countdown');
			const countdownInterval = setInterval(() => {
				countdown--;
				countdownElement.textContent = countdown;
				if (countdown <= 0) {
					clearInterval(countdownInterval);
					window.location.href = 'borrow-return.php';
				}
			}, 1000);
			</script>
		<?php elseif ($error): ?>
			<!-- Error Modal -->
			<div id="errorModal" class="notification-modal">
				<div class="notification-modal-content error-modal">
					<div class="error-icon-wrapper">
						<i class="fas fa-times-circle"></i>
					</div>
					<h2 class="notification-title">Oops!</h2>
					<p class="notification-message"><?= htmlspecialchars($error) ?></p>
					<button class="notification-btn" onclick="document.getElementById('errorModal').style.display='none'">
						<i class="fas fa-check"></i> Got it
					</button>
				</div>
			</div>
			<script>
			document.getElementById('errorModal').style.display = 'flex';
			</script>
		<?php endif; ?>

			<!-- Category Filter -->
			<div class="category-filter-bar">
				<button class="filter-btn active" data-category="all">
					<i class="fas fa-th"></i> All Equipment
				</button>
				<?php foreach($categories as $cat): ?>
					<button class="filter-btn" data-category="<?= htmlspecialchars(strtolower($cat['name'])) ?>">
						<?= htmlspecialchars($cat['name']) ?>
					</button>
				<?php endforeach; ?>
			</div>

			<!-- Equipment Grid -->
			<div class="equipment-grid" id="equipmentGrid">
				<?php if(empty($equipment_list)): ?>
					<div class="empty-state">
						<i class="fas fa-box-open" style="font-size: 80px; color: #ccc;"></i>
						<h3>No Equipment Available</h3>
						<p>There are currently no equipment items available for borrowing.</p>
					</div>
				<?php else: ?>
					<?php foreach($equipment_list as $item): 
								$condition = strtolower(str_replace(' ', '-', $item['item_condition'] ?? ''));
								$categoryKey = strtolower($item['category_name'] ?? '');
								$availableQty = (int)($item['computed_available'] ?? $item['available_quantity'] ?? 0);
								$itemSize = $item['size_category'] ?? $item['item_size'] ?? 'Medium';
								$isLarge = strtolower($itemSize) === 'large';
							?>
					<div class="equip-card" 
						data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>" 
						data-category="<?= htmlspecialchars($categoryKey) ?>"
						data-size="<?= htmlspecialchars(strtolower($itemSize)) ?>">
						
						<div class="equip-image">
							<?php if(!empty($item['image_path'])): 
								// Handle both relative and absolute paths
								$image_src = $item['image_path'];
								if (strpos($image_src, 'uploads/') === 0) {
									$image_src = '../' . $image_src;
								} elseif (strpos($image_src, '../') !== 0 && strpos($image_src, 'http') !== 0) {
									$image_src = '../uploads/' . basename($image_src);
								}
							?>
								<img src="<?= htmlspecialchars($image_src) ?>" alt="<?= htmlspecialchars($item['name']) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
								<i class="fas fa-box" style="display:none;"></i>
							<?php else: ?>
								<i class="fas fa-box"></i>
							<?php endif; ?>
						</div>
						
						<div class="equip-details">
							<span class="equip-id">#<?= $item['id'] ?></span>
							<h3 class="equip-name"><?= htmlspecialchars($item['name']) ?></h3>
							<span class="size-badge size-<?= htmlspecialchars(strtolower($itemSize)) ?>"><?= htmlspecialchars($itemSize) ?> Item</span>
							<p class="equip-category">
								<i class="fas fa-tag"></i> <?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?>
							</p>
							<div class="equip-qty">
								<i class="fas fa-boxes"></i> 
								<span><?= $availableQty ?> available</span>
							</div>
							<?php if ($isLarge): ?>
							<div class="large-item-banner">⚠ Requires admin approval</div>
							<?php endif; ?>
						</div>
						
						<button class="borrow-btn" onclick='openBorrowModal(<?= (int)$item['id'] ?>, <?= json_encode($item['name'] ?? '') ?>, <?= $availableQty ?>, <?= json_encode($item['image_path'] ?? '') ?>, <?= json_encode($itemSize) ?>)'>
							<i class="fas fa-hand-holding"></i> Borrow
						</button>
					</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Borrow Modal -->
	<div id="borrowModal" class="modal-overlay">
		<div class="modal-box">
			<div class="modal-header">
				<h2>Borrow Equipment</h2>
				<button class="modal-close" onclick="closeBorrowModal()">
					<i class="fas fa-times"></i>
				</button>
			</div>
			
			<div class="modal-body-landscape">
				<!-- Left Side: Equipment Preview -->
				<div class="modal-left">
					<div class="modal-equip-image-large">
						<img id="modalEquipmentImage" src="" alt="">
						<i id="modalEquipmentIcon" class="fas fa-box" style="display:none;"></i>
					</div>
					<div class="modal-equip-info-left">
						<h3 id="modalEquipmentName"></h3>
						<p class="modal-qty"><i class="fas fa-boxes"></i> <span id="modalEquipmentQty"></span> available</p>
						<div class="modal-size-tag" id="modalSizeTag"></div>
						<div class="large-item-banner" id="modalSizeBanner" style="display:none;">⚠ This item is large and requires admin approval before borrowing.</div>
						<div class="quantity-control">
							<label for="modalQuantityInput" class="quantity-label">Select Quantity</label>
							<div class="quantity-spinner">
								<button type="button" id="quantityMinusBtn" class="quantity-btn" aria-label="Decrease quantity">
									<i class="fas fa-minus"></i>
								</button>
								<span id="quantityDisplay" class="quantity-number">1</span>
								<button type="button" id="quantityPlusBtn" class="quantity-btn" aria-label="Increase quantity">
									<i class="fas fa-plus"></i>
								</button>
							</div>
							<small class="quantity-hint">Available: <span id="quantityMaxText">1</span></small>
						</div>
					</div>
				</div>
				
				<!-- Right Side: Form -->
				<div class="modal-right">
					<form method="POST" id="borrowForm" class="borrow-form">
						<input type="hidden" name="action" value="borrow">
						<input type="hidden" name="equipment_id" id="modalEquipmentId">
						<input type="hidden" name="quantity" id="modalQuantityInput" value="1">
						
						<div class="form-field">
							<label><i class="fas fa-user"></i> Student ID</label>
							<input type="text" value="<?= htmlspecialchars($student_id) ?>" readonly>
						</div>
						
						<div class="form-field">
							<label><i class="fas fa-clock"></i> Borrow Time</label>
							<input type="text" id="borrow_time" readonly>
							<small>Current time - will be recorded automatically</small>
						</div>
						
						<div class="form-field">
							<label><i class="fas fa-calendar-check"></i> Return By</label>
							<input type="datetime-local" id="due_date" name="due_date" required>
							<small>Select when you plan to return this equipment</small>
						</div>
						
						<div class="modal-actions">
							<button type="button" class="btn-cancel" onclick="closeBorrowModal()">
								<i class="fas fa-times"></i> Cancel
							</button>
							<button type="submit" class="btn-confirm">
								<i class="fas fa-check"></i> Confirm Borrow
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<script>
	function goBack() {
		window.location.href = 'borrow-return.php';
	}

	let currentMaxQuantity = 1;
	let currentItemSize = 'Medium';

	function updateQuantityDisplay(value) {
		const quantityDisplay = document.getElementById('quantityDisplay');
		const hiddenInput = document.getElementById('modalQuantityInput');
		const minusBtn = document.getElementById('quantityMinusBtn');
		const plusBtn = document.getElementById('quantityPlusBtn');
		const safeMax = Math.max(currentMaxQuantity, 1);
		const newValue = Math.min(Math.max(value, 1), safeMax);
		quantityDisplay.textContent = newValue;
		hiddenInput.value = newValue;
		minusBtn.disabled = newValue <= 1;
		plusBtn.disabled = newValue >= safeMax;
	}

	function applySizeUI(sizeCategory) {
		const sizeTag = document.getElementById('modalSizeTag');
		const banner = document.getElementById('modalSizeBanner');
		const confirmBtn = document.querySelector('#borrowForm .btn-confirm');
		sizeTag.textContent = `${sizeCategory} Item`;
		sizeTag.className = `modal-size-tag size-${sizeCategory.toLowerCase()}`;
		if (sizeCategory.toLowerCase() === 'large') {
			banner.style.display = 'block';
			confirmBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit for Approval';
		} else {
			banner.style.display = 'none';
			confirmBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Borrow';
		}
	}

	function openBorrowModal(equipmentId, equipmentName, quantity, imagePath, sizeCategory = 'Medium') {
		document.getElementById('modalEquipmentId').value = equipmentId;
		document.getElementById('modalEquipmentName').textContent = equipmentName;
		document.getElementById('modalEquipmentQty').textContent = quantity;
		document.getElementById('quantityMaxText').textContent = quantity;
		currentMaxQuantity = Math.max(parseInt(quantity, 10) || 1, 1);
		updateQuantityDisplay(1);
		currentItemSize = sizeCategory || 'Medium';
		applySizeUI(currentItemSize);
		
		const imageElement = document.getElementById('modalEquipmentImage');
		const iconElement = document.getElementById('modalEquipmentIcon');
		
		if (imagePath && imagePath.trim() !== '') {
			// Handle image path
			let imgSrc = imagePath;
			if (imgSrc.indexOf('uploads/') === 0) {
				imgSrc = '../' + imgSrc;
			} else if (imgSrc.indexOf('../') !== 0 && imgSrc.indexOf('http') !== 0) {
				imgSrc = '../uploads/' + imgSrc.split('/').pop();
			}
			imageElement.src = imgSrc;
			imageElement.style.display = 'block';
			iconElement.style.display = 'none';
		} else {
			imageElement.style.display = 'none';
			iconElement.style.display = 'flex';
		}
		
		// Set current borrow time with live update
		function updateBorrowTime() {
			const now = new Date();
			const options = { 
				year: 'numeric', 
				month: 'short', 
				day: 'numeric', 
				hour: '2-digit', 
				minute: '2-digit',
				second: '2-digit',
				hour12: true 
			};
			document.getElementById('borrow_time').value = now.toLocaleString('en-US', options);
		}
		updateBorrowTime();
		// Update every second
		const borrowTimeInterval = setInterval(updateBorrowTime, 1000);
		
		// Set minimum date to current date/time
		const now = new Date();
		const year = now.getFullYear();
		const month = String(now.getMonth() + 1).padStart(2, '0');
		const day = String(now.getDate()).padStart(2, '0');
		const hours = String(now.getHours()).padStart(2, '0');
		const minutes = String(now.getMinutes()).padStart(2, '0');
		const currentDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
		
		const dueDateInput = document.getElementById('due_date');
		dueDateInput.min = currentDateTime;
		
		// Set default to 1 day from now
		const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
		const tomorrowStr = `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}T${hours}:${minutes}`;
		dueDateInput.value = tomorrowStr;
		
		// Store interval ID to clear it later
		document.getElementById('borrowModal').dataset.intervalId = borrowTimeInterval;
		document.getElementById('borrowModal').style.display = 'flex';
	}

	document.getElementById('quantityMinusBtn').addEventListener('click', function() {
		const currentValue = parseInt(document.getElementById('modalQuantityInput').value, 10) || 1;
		updateQuantityDisplay(currentValue - 1);
	});

	document.getElementById('quantityPlusBtn').addEventListener('click', function() {
		const currentValue = parseInt(document.getElementById('modalQuantityInput').value, 10) || 1;
		updateQuantityDisplay(currentValue + 1);
	});

	function closeBorrowModal() {
		const modal = document.getElementById('borrowModal');
		// Clear the time update interval
		const intervalId = modal.dataset.intervalId;
		if (intervalId) {
			clearInterval(parseInt(intervalId));
		}
		modal.style.display = 'none';
	}

	// Close modal when clicking outside
	window.onclick = function(event) {
		if (event.target.id === 'borrowModal') {
			closeBorrowModal();
		}
	}

	// Category filter
	document.addEventListener('DOMContentLoaded', function() {
		const filterButtons = document.querySelectorAll('.filter-btn');

		filterButtons.forEach(btn => {
			btn.addEventListener('click', () => {
				filterButtons.forEach(b => b.classList.remove('active'));
				btn.classList.add('active');
				activeCategory = btn.dataset.category;
				filterEquipmentByCategory();
			});
		});
	});

	// Auto-refresh equipment list
	let lastUpdateTime = <?= time() ?>;
	let activeCategory = 'all';

	function refreshEquipmentList() {
		fetch('get_equipment.php')
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					// Always update to show latest data (including quantity changes)
					updateEquipmentGrid(data.equipment);
					lastUpdateTime = data.timestamp;
					// Silent update - no console log to keep it clean
				}
			})
			.catch(error => {
				// Silent error handling - continues trying
				console.error('Error fetching equipment:', error);
			});
	}

	function updateEquipmentGrid(equipmentList) {
		const grid = document.getElementById('equipmentGrid');
		
		if (equipmentList.length === 0) {
			grid.innerHTML = `
				<div class="empty-state">
					<i class="fas fa-box-open" style="font-size: 80px; color: #ccc;"></i>
					<h3>No Equipment Available</h3>
					<p>There are currently no equipment items available for borrowing.</p>
				</div>
			`;
			return;
		}

		let html = '';
		equipmentList.forEach(item => {
			const categoryKey = (item.category_name || '').toLowerCase();
			const availableQty = parseInt(item.computed_available ?? item.available_quantity ?? 0) || 0;
			const sizeCategory = item.size_category || item.item_size || 'Medium';
			const isLarge = String(sizeCategory).toLowerCase() === 'large';
			if (availableQty <= 0) {
				return;
			}
			
			// Handle image path
			let imageSrc = item.image_path || '';
			if (imageSrc) {
				if (imageSrc.indexOf('uploads/') === 0) {
					imageSrc = '../' + imageSrc;
				} else if (imageSrc.indexOf('../') !== 0 && imageSrc.indexOf('http') !== 0) {
					imageSrc = '../uploads/' + imageSrc.split('/').pop();
				}
			}

			html += `
				<div class="equip-card" 
					data-name="${(item.name || '').toLowerCase()}" 
					data-category="${categoryKey}"
					data-size="${sizeCategory.toLowerCase()}">
					
					<div class="equip-image">
						${imageSrc ? `
							<img src="${imageSrc}" alt="${item.name}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
							<i class="fas fa-box" style="display:none;"></i>
						` : `
							<i class="fas fa-box"></i>
						`}
					</div>
					
					<div class="equip-details">
						<span class="equip-id">#${item.id}</span>
						<h3 class="equip-name">${item.name}</h3>
						<span class="size-badge size-${sizeCategory.toLowerCase()}">${sizeCategory} Item</span>
						<p class="equip-category">
							<i class="fas fa-tag"></i> ${item.category_name || 'Uncategorized'}
						</p>
						<div class="equip-qty">
							<i class="fas fa-boxes"></i> 
							<span>${availableQty} available</span>
						</div>
						${isLarge ? '<div class="large-item-banner">⚠ Requires admin approval</div>' : ''}
					</div>
					
					<button class="borrow-btn" onclick='openBorrowModal(${item.id}, ${JSON.stringify(item.name || '')}, ${availableQty}, ${JSON.stringify(item.image_path || '')}, ${JSON.stringify(sizeCategory)})'>
						<i class="fas fa-hand-holding"></i> Borrow
					</button>
				</div>
			`;
		});

		grid.innerHTML = html;
		
		// Reapply category filter
		filterEquipmentByCategory();
	}

	function filterEquipmentByCategory() {
		const cards = document.querySelectorAll('.equip-card');
		let visibleCount = 0;

		cards.forEach(card => {
			const category = card.dataset.category || '';
			const matchesCategory = activeCategory === 'all' || category === activeCategory;
			
			if (matchesCategory) {
				card.style.display = 'block';
				visibleCount++;
			} else {
				card.style.display = 'none';
			}
		});
	}

	// Scroll detection for sticky header shadow effect
	window.addEventListener('scroll', function() {
		const header = document.querySelector('.borrow-header');
		const filterBar = document.querySelector('.category-filter-bar');
		
		if (window.scrollY > 10) {
			header.classList.add('scrolled');
			if (filterBar) filterBar.classList.add('scrolled');
		} else {
			header.classList.remove('scrolled');
			if (filterBar) filterBar.classList.remove('scrolled');
		}
	});

	// Auto-logout after 5 minutes
	let inactivityTime = function () {
		let time;
		window.onload = resetTimer;
		document.onmousemove = resetTimer;
		document.onkeypress = resetTimer;
		document.onclick = resetTimer;

		function logout() {
			window.location.href = 'logout.php';
		}

		function resetTimer() {
			clearTimeout(time);
			time = setTimeout(logout, 300000); // 5 minutes
		}
	};
	inactivityTime();
	</script>
</body>
</html>