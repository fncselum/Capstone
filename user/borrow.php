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
				// Get equipment details
				$stmt = $conn->prepare("SELECT * FROM equipment WHERE id = ? FOR UPDATE");
				$stmt->bind_param("i", $equipment_id);
				$stmt->execute();
				$result = $stmt->get_result();
				
				if ($result->num_rows === 1) {
					$equip_data = $result->fetch_assoc();
					$current_qty = (int)($equip_data['quantity'] ?? 0);
					
					if ($current_qty > 0) {
						$new_qty = $current_qty - 1;
						
						// Update equipment quantity
						$update_stmt = $conn->prepare("UPDATE equipment SET quantity = ? WHERE id = ?");
						$update_stmt->bind_param("ii", $new_qty, $equipment_id);
						$update_stmt->execute();
						
						// Insert transaction record
						$transaction_type = 'Borrow';
						$status = 'Active';
						$borrow_date = date('Y-m-d H:i:s');
						$due_datetime = date('Y-m-d H:i:s', strtotime($due_date));
						
						$trans_stmt = $conn->prepare("INSERT INTO transactions 
							(user_id, equipment_id, transaction_type, status, borrow_date, due_date, created_at) 
							VALUES (?, ?, ?, ?, ?, ?, NOW())");
						$trans_stmt->bind_param("iissss", $user_id, $equipment_id, $transaction_type, $status, $borrow_date, $due_datetime);
						
						if ($trans_stmt->execute()) {
							$conn->commit();
							$message = 'Equipment borrowed successfully! Please return by ' . date('M j, Y g:i A', strtotime($due_datetime));
						} else {
							throw new Exception("Failed to record transaction: " . $conn->error);
						}
					} else {
						$error = 'Sorry, this equipment is out of stock.';
					}
				} else {
					$error = 'Equipment not found.';
				}
			} catch (Exception $ex) {
				$conn->rollback();
				$error = 'Borrow failed: ' . $ex->getMessage();
			}
		} else {
			$error = 'Please provide all required information.';
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
	          ORDER BY e.name";
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
				<div class="alert alert-success">
					<i class="fas fa-check-circle"></i>
					<div>
						<strong>Success!</strong>
						<p><?= htmlspecialchars($message) ?></p>
					</div>
				</div>
				<script>
				setTimeout(function(){ window.location.href = 'borrow-return.php'; }, 3000);
				</script>
			<?php elseif ($error): ?>
				<div class="alert alert-error">
					<i class="fas fa-exclamation-circle"></i>
					<div>
						<strong>Error!</strong>
						<p><?= htmlspecialchars($error) ?></p>
					</div>
				</div>
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
				<h2><i class="fas fa-hand-holding"></i> Confirm Borrow</h2>
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
					<div class="modal-equip-info-large">
						<h3 id="modalEquipmentName"></h3>
						<p><i class="fas fa-boxes"></i> <span id="modalEquipmentQty"></span> available</p>
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
		
		document.getElementById('borrowModal').style.display = 'flex';
	}

	function closeBorrowModal() {
		document.getElementById('borrowModal').style.display = 'none';
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
		let activeCategory = 'all';

		function filterEquipment() {
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

		filterButtons.forEach(btn => {
			btn.addEventListener('click', () => {
				filterButtons.forEach(b => b.classList.remove('active'));
				btn.classList.add('active');
				activeCategory = btn.dataset.category;
				filterEquipment();
			});
		});
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

	<style>
	/* Borrow Page Styles */
	.borrow-page-content {
		padding: 2vh 3vw;
		max-width: 1400px;
		margin: 0 auto;
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
		max-width: 600px;
		max-height: 90vh;
		overflow-y: auto;
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
		padding: 25px;
		border-bottom: 1px solid #eee;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}

	.modal-header h2 {
		margin: 0;
		font-size: 1.5rem;
		color: #1e5631;
		display: flex;
		align-items: center;
		gap: 10px;
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
		background: #f0f0f0;
	}

	.modal-body {
		padding: 25px;
	}

	.modal-equipment-preview {
		display: flex;
		gap: 20px;
		margin-bottom: 25px;
		padding: 20px;
		background: #f8f9fa;
		border-radius: 12px;
	}

	.modal-equip-image {
		width: 120px;
		height: 120px;
		border-radius: 10px;
		overflow: hidden;
		background: white;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}

	.modal-equip-image img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}

	.modal-equip-image i {
		font-size: 50px;
		color: #ccc;
	}

	.modal-equip-info h3 {
		margin: 0 0 10px 0;
		font-size: 1.3rem;
		color: #333;
	}

	.modal-equip-info p {
		margin: 0;
		color: #666;
		display: flex;
		align-items: center;
		gap: 8px;
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

		.modal-equipment-preview {
			flex-direction: column;
		}

		.modal-equip-image {
			width: 100%;
		}
	}
	</style>
</body>
</html>
