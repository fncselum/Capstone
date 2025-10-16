<?php
session_start();

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
		
		if ($equipment_id > 0 && !empty($due_date)) {
			$conn->begin_transaction();
			try {
				// Get equipment and inventory details with lock
				$stmt = $conn->prepare("SELECT e.*, i.available_quantity, i.borrowed_quantity, i.minimum_stock_level 
					FROM equipment e 
					LEFT JOIN inventory i ON e.id = i.equipment_id 
					WHERE e.id = ? FOR UPDATE");
				$stmt->bind_param("i", $equipment_id);
				$stmt->execute();
				$result = $stmt->get_result();
				
				if ($result->num_rows === 1) {
					$equip_data = $result->fetch_assoc();
					$current_qty = (int)($equip_data['quantity'] ?? 0);
					$available_qty = (int)($equip_data['available_quantity'] ?? $current_qty);
					$equipment_name = $equip_data['name'] ?? 'Unknown';
					$condition_before = $equip_data['item_condition'] ?? 'Good';
					$min_stock = (int)($equip_data['minimum_stock_level'] ?? 1);
					
					// Check if equipment is available
					if ($available_qty > 0 && $current_qty > 0) {
						$new_qty = $current_qty - 1;
						
						// Update equipment quantity
						$update_stmt = $conn->prepare("UPDATE equipment SET quantity = ?, updated_at = NOW() WHERE id = ?");
						$update_stmt->bind_param("ii", $new_qty, $equipment_id);
						
						if (!$update_stmt->execute()) {
							throw new Exception("Failed to update equipment quantity");
						}
						
						// Update inventory table - decrease available_quantity, increase borrowed_quantity
						$new_available = $available_qty - 1;
						
						// Determine new availability status
						$new_status = 'Available';
						if ($new_available == 0) {
							$new_status = 'Out of Stock';
						} elseif ($new_available <= $min_stock) {
							$new_status = 'Low Stock';
						}
						
						$inv_stmt = $conn->prepare("UPDATE inventory 
							SET available_quantity = available_quantity - 1, 
								borrowed_quantity = borrowed_quantity + 1, 
								availability_status = ?,
								last_updated = NOW() 
							WHERE equipment_id = ?");
						$inv_stmt->bind_param("si", $new_status, $equipment_id);
						
						if (!$inv_stmt->execute()) {
							throw new Exception("Failed to update inventory: " . $inv_stmt->error);
						}
						$inv_stmt->close();
						
						// Insert transaction record with all required fields
						$transaction_type = 'Borrow';
						$quantity = 1; // Borrowing 1 item at a time
						$transaction_date = date('Y-m-d H:i:s');
						$expected_return_date = date('Y-m-d H:i:s', strtotime($due_date));
						$status = 'Active';
						$penalty_applied = 0;
						$notes = "Borrowed via kiosk by student ID: " . $student_id;
						
						$trans_stmt = $conn->prepare("INSERT INTO transactions 
							(user_id, equipment_id, transaction_type, quantity, transaction_date, 
							expected_return_date, condition_before, status, penalty_applied, notes, created_at, updated_at) 
							VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
						
						$trans_stmt->bind_param("iisiisssis", 
							$user_id, 
							$equipment_id, 
							$transaction_type, 
							$quantity,
							$transaction_date, 
							$expected_return_date, 
							$condition_before,
							$status,
							$penalty_applied,
							$notes
						);
						
						if ($trans_stmt->execute()) {
							$transaction_id = $conn->insert_id;
							$conn->commit();
							
							// Success message with details and stock status
							$return_date_formatted = date('M j, Y g:i A', strtotime($expected_return_date));
							$message = "Equipment borrowed successfully!<br><strong>$equipment_name</strong><br>Please return by: $return_date_formatted<br>Transaction ID: #$transaction_id";
							
							// Add stock warning if applicable
							if ($new_status == 'Out of Stock') {
								$message .= "<br><span style='color: #ff9800;'>⚠ This was the last available item</span>";
							} elseif ($new_status == 'Low Stock') {
								$message .= "<br><span style='color: #ff9800;'>⚠ Low stock: $new_available remaining</span>";
							}
						} else {
							throw new Exception("Failed to record transaction: " . $trans_stmt->error);
						}
						
						$trans_stmt->close();
						$update_stmt->close();
					} else {
						$conn->rollback();
						if ($available_qty == 0) {
							$error = 'Sorry, this equipment is currently out of stock. All items are borrowed or unavailable.';
						} else {
							$error = 'Sorry, this equipment is currently unavailable.';
						}
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
	$query = "SELECT e.*, c.name as category_name, 
	          i.available_quantity, i.borrowed_quantity, i.availability_status
	          FROM equipment e 
	          LEFT JOIN categories c ON e.category_id = c.id 
	          LEFT JOIN inventory i ON e.id = i.equipment_id
	          WHERE e.quantity > 0 AND (i.available_quantity > 0 OR i.available_quantity IS NULL)
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
								$qty = (int)($item['quantity'] ?? 0);
							?>
					<div class="equip-card" 
						data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>" 
						data-category="<?= htmlspecialchars($categoryKey) ?>">
						
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
							<p class="equip-category">
								<i class="fas fa-tag"></i> <?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?>
							</p>
							<div class="equip-qty">
								<i class="fas fa-boxes"></i> 
								<span><?= $qty ?> available</span>
							</div>
						</div>
						
						<button class="borrow-btn" onclick="openBorrowModal(<?= (int)$item['id'] ?>, '<?= addslashes(htmlspecialchars($item['name'])) ?>', <?= $qty ?>, '<?= addslashes(htmlspecialchars($item['image_path'] ?? '')) ?>')">
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
					</div>
				</div>
				
				<!-- Right Side: Form -->
				<div class="modal-right">
					<form method="POST" id="borrowForm" class="borrow-form">
						<input type="hidden" name="action" value="borrow">
						<input type="hidden" name="equipment_id" id="modalEquipmentId">
						
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

	function openBorrowModal(equipmentId, equipmentName, quantity, imagePath) {
		document.getElementById('modalEquipmentId').value = equipmentId;
		document.getElementById('modalEquipmentName').textContent = equipmentName;
		document.getElementById('modalEquipmentQty').textContent = quantity;
		
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
			const qty = parseInt(item.quantity) || 0;
			
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
					data-category="${categoryKey}">
					
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
						<p class="equip-category">
							<i class="fas fa-tag"></i> ${item.category_name || 'Uncategorized'}
						</p>
						<div class="equip-qty">
							<i class="fas fa-boxes"></i> 
							<span>${qty} available</span>
						</div>
					</div>
					
					<button class="borrow-btn" onclick="openBorrowModal(${item.id}, '${item.name.replace(/'/g, "\\'")}', ${qty}, '${(imageSrc || '').replace(/'/g, "\\'")}')">
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

	// Refresh every 5 seconds
	setInterval(refreshEquipmentList, 5000);

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