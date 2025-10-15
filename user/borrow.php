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
				// Get equipment details with lock
				$stmt = $conn->prepare("SELECT * FROM equipment WHERE id = ? FOR UPDATE");
				$stmt->bind_param("i", $equipment_id);
				$stmt->execute();
				$result = $stmt->get_result();
				
				if ($result->num_rows === 1) {
					$equip_data = $result->fetch_assoc();
					$current_qty = (int)($equip_data['quantity'] ?? 0);
					$equipment_name = $equip_data['name'] ?? 'Unknown';
					$condition_before = $equip_data['item_condition'] ?? 'Good';
					
					if ($current_qty > 0) {
						$new_qty = $current_qty - 1;
						
						// Update equipment quantity
						$update_stmt = $conn->prepare("UPDATE equipment SET quantity = ?, updated_at = NOW() WHERE id = ?");
						$update_stmt->bind_param("ii", $new_qty, $equipment_id);
						
						if (!$update_stmt->execute()) {
							throw new Exception("Failed to update equipment quantity");
						}
						
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
							
							// Success message with details
							$return_date_formatted = date('M j, Y g:i A', strtotime($expected_return_date));
							$message = "Equipment borrowed successfully!<br><strong>$equipment_name</strong><br>Please return by: $return_date_formatted<br>Transaction ID: #$transaction_id";
						} else {
							throw new Exception("Failed to record transaction: " . $trans_stmt->error);
						}
						
						$trans_stmt->close();
						$update_stmt->close();
					} else {
						$conn->rollback();
						$error = 'Sorry, this equipment is currently out of stock.';
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
	// Fetch equipment with category names
	$query = "SELECT e.*, c.name as category_name 
	          FROM equipment e 
	          LEFT JOIN categories c ON e.category_id = c.id 
	          WHERE e.quantity > 0
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

	<style>
	/* Override body styles for scrolling */
	body {
		display: block !important;
		align-items: unset !important;
		overflow-y: auto !important;
		min-height: 100vh;
	}

	.container {
		min-height: 100vh;
		display: flex;
		flex-direction: column;
	}

	/* Borrow Page Styles */
	.borrow-page-content {
		padding: 2vh 3vw;
		max-width: 1400px;
		margin: 0 auto;
		flex: 1;
	}

	.borrow-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 30px;
		padding: 20px;
		background: white;
		border-radius: 15px;
		box-shadow: 0 2px 10px rgba(0,0,0,0.05);
	}

	.header-left {
		display: flex;
		align-items: center;
		gap: 20px;
	}

	.header-logo-small {
		height: 50px;
		width: auto;
	}

	.page-title {
		margin: 0;
		font-size: 1.8rem;
		color: #1e5631;
		font-weight: 700;
	}

	.page-subtitle {
		margin: 5px 0 0 0;
		color: #666;
		font-size: 0.95rem;
	}

	.back-btn {
		background: #1e5631;
		color: white;
		border: none;
		padding: 12px 24px;
		border-radius: 10px;
		cursor: pointer;
		font-size: 1rem;
		font-weight: 600;
		transition: all 0.3s ease;
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.back-btn:hover {
		background: #163f24;
		transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(30, 86, 49, 0.2);
	}

	/* Alerts */
	.alert {
		padding: 15px 20px;
		border-radius: 10px;
		margin-bottom: 20px;
		display: flex;
		align-items: center;
		gap: 15px;
		animation: slideDown 0.3s ease;
	}

	.alert i {
		font-size: 24px;
	}

	.alert-success {
		background: #d4edda;
		border: 1px solid #c3e6cb;
		color: #155724;
	}

	.alert-error {
		background: #f8d7da;
		border: 1px solid #f5c6cb;
		color: #721c24;
	}

	.alert strong {
		display: block;
		margin-bottom: 5px;
	}

	.alert p {
		margin: 0;
	}

	/* Notification Modal */
	.notification-modal {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, 0.7);
		backdrop-filter: blur(5px);
		z-index: 10000;
		justify-content: center;
		align-items: center;
		animation: fadeIn 0.3s ease;
	}

	.notification-modal-content {
		background: white;
		border-radius: 25px;
		padding: 50px 60px;
		text-align: center;
		max-width: 500px;
		box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
		animation: slideUpBounce 0.5s ease;
	}

	@keyframes fadeIn {
		from { opacity: 0; }
		to { opacity: 1; }
	}

	@keyframes slideUpBounce {
		0% { transform: translateY(100px); opacity: 0; }
		60% { transform: translateY(-10px); opacity: 1; }
		80% { transform: translateY(5px); }
		100% { transform: translateY(0); }
	}

	/* Success Modal */
	.success-icon-wrapper {
		margin: 0 auto 30px;
	}

	.success-checkmark {
		width: 100px;
		height: 100px;
		margin: 0 auto;
	}

	.check-icon {
		width: 100px;
		height: 100px;
		position: relative;
		border-radius: 50%;
		box-sizing: content-box;
		border: 4px solid #4caf50;
		background: #f0f9f4;
	}

	.icon-line {
		height: 5px;
		background-color: #4caf50;
		display: block;
		border-radius: 2px;
		position: absolute;
		z-index: 10;
	}

	.icon-line.line-tip {
		top: 46px;
		left: 14px;
		width: 25px;
		transform: rotate(45deg);
		animation: checkTip 0.75s;
	}

	.icon-line.line-long {
		top: 38px;
		right: 8px;
		width: 47px;
		transform: rotate(-45deg);
		animation: checkLong 0.75s;
	}

	.icon-circle {
		top: -4px;
		left: -4px;
		z-index: 10;
		width: 100px;
		height: 100px;
		border-radius: 50%;
		position: absolute;
		box-sizing: content-box;
		border: 4px solid rgba(76, 175, 80, 0.5);
		animation: scaleCircle 0.5s;
	}

	.icon-fix {
		top: 8px;
		width: 5px;
		left: 26px;
		z-index: 1;
		height: 85px;
		position: absolute;
		transform: rotate(-45deg);
		background-color: white;
	}

	@keyframes scaleCircle {
		0% { transform: scale(0); }
		100% { transform: scale(1); }
	}

	@keyframes checkTip {
		0% { width: 0; left: 1px; top: 19px; }
		54% { width: 0; left: 1px; top: 19px; }
		70% { width: 50px; left: -8px; top: 37px; }
		84% { width: 17px; left: 21px; top: 48px; }
		100% { width: 25px; left: 14px; top: 46px; }
	}

	@keyframes checkLong {
		0% { width: 0; right: 46px; top: 54px; }
		65% { width: 0; right: 46px; top: 54px; }
		84% { width: 55px; right: 0; top: 35px; }
		100% { width: 47px; right: 8px; top: 38px; }
	}

	.notification-title {
		font-size: 2rem;
		color: #333;
		margin-bottom: 20px;
		font-weight: 700;
	}

	.notification-message {
		font-size: 1.1rem;
		color: #666;
		margin-bottom: 30px;
		line-height: 1.8;
	}

	.notification-footer {
		margin-top: 25px;
	}

	.redirect-text {
		font-size: 1rem;
		color: #999;
		font-weight: 500;
	}

	.redirect-text span {
		color: #4caf50;
		font-weight: 700;
		font-size: 1.2rem;
	}

	/* Error Modal */
	.error-icon-wrapper {
		width: 100px;
		height: 100px;
		background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		margin: 0 auto 30px;
		box-shadow: 0 10px 30px rgba(238, 90, 111, 0.3);
		animation: scaleIn 0.5s ease;
	}

	.error-icon-wrapper i {
		font-size: 50px;
		color: white;
	}

	@keyframes scaleIn {
		0% { transform: scale(0); }
		50% { transform: scale(1.1); }
		100% { transform: scale(1); }
	}

	.notification-btn {
		background: linear-gradient(135deg, #1e5631, #2d7a45);
		color: white;
		border: none;
		padding: 15px 40px;
		border-radius: 12px;
		font-size: 1.1rem;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.3s ease;
		box-shadow: 0 4px 15px rgba(30, 86, 49, 0.3);
	}

	.notification-btn:hover {
		transform: translateY(-2px);
		box-shadow: 0 6px 20px rgba(30, 86, 49, 0.4);
	}

	/* Category Filter */
	.category-filter-bar {
		background: white;
		padding: 15px 20px;
		border-radius: 15px;
		margin-bottom: 25px;
		box-shadow: 0 2px 10px rgba(0,0,0,0.05);
		display: flex;
		gap: 10px;
		flex-wrap: wrap;
		justify-content: flex-start;
		align-items: center;
	}

	.filter-btn {
		padding: 10px 20px;
		border: 2px solid #e8f5e9;
		background: white;
		border-radius: 20px;
		cursor: pointer;
		font-size: 0.95rem;
		font-weight: 500;
		color: #1e5631;
		transition: all 0.3s ease;
		display: inline-flex;
		align-items: center;
		gap: 6px;
		white-space: nowrap;
	}

	.filter-btn:hover {
		background: #e8f5e9;
		transform: translateY(-1px);
		box-shadow: 0 2px 8px rgba(30, 86, 49, 0.15);
	}

	.filter-btn.active {
		background: #1e5631;
		color: white;
		border-color: #1e5631;
	}

	/* Equipment Grid */
	.equipment-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
		gap: 20px;
		margin-bottom: 30px;
	}

	.equip-card {
		background: white;
		border-radius: 15px;
		overflow: hidden;
		box-shadow: 0 2px 10px rgba(0,0,0,0.08);
		transition: all 0.3s ease;
		display: flex;
		flex-direction: column;
	}

	.equip-card:hover {
		transform: translateY(-5px);
		box-shadow: 0 8px 20px rgba(30, 86, 49, 0.15);
	}

	.equip-image {
		width: 100%;
		height: 200px;
		background: #f8f9fa;
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
	}

	.equip-image img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}

	.equip-image i {
		font-size: 60px;
		color: #ccc;
	}

	.equip-details {
		padding: 20px;
		flex: 1;
	}

	.equip-id {
		display: inline-block;
		background: #e8f5e9;
		color: #1e5631;
		padding: 4px 12px;
		border-radius: 12px;
		font-size: 0.85rem;
		font-weight: 600;
		margin-bottom: 10px;
	}

	.equip-name {
		font-size: 1.2rem;
		font-weight: 700;
		color: #333;
		margin: 0 0 10px 0;
		line-height: 1.3;
	}

	.equip-category {
		color: #666;
		font-size: 0.9rem;
		margin: 0 0 10px 0;
		display: flex;
		align-items: center;
		gap: 6px;
	}

	.equip-qty {
		display: flex;
		align-items: center;
		gap: 8px;
		color: #1e5631;
		font-weight: 600;
		font-size: 0.95rem;
	}

	.borrow-btn {
		width: 100%;
		padding: 14px;
		background: #1e5631;
		color: white;
		border: none;
		cursor: pointer;
		font-size: 1rem;
		font-weight: 600;
		transition: all 0.3s ease;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
	}

	.borrow-btn:hover {
		background: #163f24;
	}

	/* Modal */
	.modal-overlay {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0,0,0,0.7);
		z-index: 9999;
		align-items: center;
		justify-content: center;
	}

	.modal-box {
		background: white;
		border-radius: 20px;
		width: 90%;
		max-width: 900px;
		max-height: 90vh;
		overflow: hidden;
		animation: modalSlideIn 0.3s ease;
	}

	@keyframes modalSlideIn {
		from {
			opacity: 0;
			transform: translateY(-50px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	.modal-header {
		padding: 20px 25px;
		border-bottom: 1px solid #eee;
		display: flex;
		justify-content: space-between;
		align-items: center;
		background: #f8f9fa;
	}

	.modal-header h2 {
		margin: 0;
		font-size: 1.4rem;
		color: #1e5631;
		font-weight: 600;
	}

	.modal-close {
		background: none;
		border: none;
		font-size: 24px;
		color: #666;
		cursor: pointer;
		width: 36px;
		height: 36px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.2s;
	}

	.modal-close:hover {
		background: #e0e0e0;
	}

	/* Landscape Layout */
	.modal-body-landscape {
		display: flex;
		height: 500px;
	}

	/* Left Side - Equipment Preview */
	.modal-left {
		flex: 0 0 320px;
		background: #f8f9fa;
		padding: 30px;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 20px;
		border-right: 1px solid #e0e0e0;
	}

	.modal-equip-image-large {
		width: 200px;
		height: 200px;
		border-radius: 15px;
		overflow: hidden;
		background: white;
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 4px 12px rgba(0,0,0,0.1);
	}

	.modal-equip-image-large img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}

	.modal-equip-image-large i {
		font-size: 80px;
		color: #ccc;
	}

	.modal-equip-info-left {
		text-align: center;
	}

	.modal-equip-info-left h3 {
		margin: 0 0 10px 0;
		font-size: 1.4rem;
		color: #333;
		font-weight: 700;
	}

	.modal-qty {
		margin: 0;
		color: #1e5631;
		font-weight: 600;
		font-size: 1rem;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
	}

	/* Right Side - Form */
	.modal-right {
		flex: 1;
		padding: 30px;
		overflow-y: auto;
	}

	.form-field {
		margin-bottom: 20px;
	}

	.form-field label {
		display: block;
		margin-bottom: 8px;
		font-weight: 600;
		color: #333;
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.form-field input {
		width: 100%;
		padding: 12px 16px;
		border: 2px solid #e8f5e9;
		border-radius: 10px;
		font-size: 1rem;
		transition: all 0.3s;
		box-sizing: border-box;
	}

	.form-field input:focus {
		outline: none;
		border-color: #1e5631;
		box-shadow: 0 0 0 3px rgba(30, 86, 49, 0.1);
	}

	.form-field input[readonly] {
		background: #f8f9fa;
		color: #666;
	}

	.form-field small {
		display: block;
		margin-top: 6px;
		color: #666;
		font-size: 0.85rem;
	}

	.modal-actions {
		display: flex;
		gap: 12px;
		margin-top: 25px;
	}

	.btn-cancel, .btn-confirm {
		flex: 1;
		padding: 14px 20px;
		border: none;
		border-radius: 10px;
		font-size: 1rem;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.3s ease;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
	}

	.btn-cancel {
		background: #6c757d;
		color: white;
	}

	.btn-cancel:hover {
		background: #545b62;
	}

	.btn-confirm {
		background: #1e5631;
		color: white;
	}

	.btn-confirm:hover {
		background: #163f24;
		transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(30, 86, 49, 0.3);
	}

	.empty-state {
		text-align: center;
		padding: 60px 20px;
		color: #999;
	}

	.empty-state h3 {
		margin: 20px 0 10px 0;
		color: #666;
	}

	@media (max-width: 768px) {
		.borrow-header {
			flex-direction: column;
			gap: 15px;
		}

		.header-left {
			flex-direction: column;
			text-align: center;
		}

		.equipment-grid {
			grid-template-columns: 1fr;
		}

		.modal-body-landscape {
			flex-direction: column;
			height: auto;
			max-height: 80vh;
			overflow-y: auto;
		}

		.modal-left {
			flex: none;
			border-right: none;
			border-bottom: 1px solid #e0e0e0;
			padding: 20px;
		}

		.modal-equip-image-large {
			width: 150px;
			height: 150px;
		}

		.modal-right {
			padding: 20px;
		}
	}
	</style>
</body>
</html>
