<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$student_id = $_SESSION['student_id'] ?? 'Guest';

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

$borrowed_items = [];
$message = null;
$error = null;

if ($db_connected) {
	// Handle return action
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return') {
		$transaction_id = (int)($_POST['transaction_id'] ?? 0);
		$condition_after = trim($_POST['condition_after'] ?? 'Good');
		
		if ($transaction_id > 0) {
			$conn->begin_transaction();
			try {
				// Get transaction details with lock
				$stmt = $conn->prepare("SELECT t.*, e.name as equipment_name, e.quantity as current_qty 
					FROM transactions t 
					JOIN equipment e ON t.equipment_id = e.id 
					WHERE t.id = ? AND t.user_id = ? AND t.transaction_type = 'Borrow' AND t.status = 'Active' FOR UPDATE");
				$stmt->bind_param("ii", $transaction_id, $user_id);
				$stmt->execute();
				$result = $stmt->get_result();
				
				if ($result->num_rows === 1) {
					$transaction = $result->fetch_assoc();
					$equipment_id = $transaction['equipment_id'];
					$equipment_name = $transaction['equipment_name'];
					$current_qty = (int)$transaction['current_qty'];
					$quantity_returned = (int)$transaction['quantity'];
					$new_qty = $current_qty + $quantity_returned;
					
					// Calculate penalty if overdue
					$expected_return = new DateTime($transaction['expected_return_date']);
					$actual_return = new DateTime();
					$penalty = 0;
					
					if ($actual_return > $expected_return) {
						$days_overdue = $actual_return->diff($expected_return)->days;
						$penalty = $days_overdue * 10; // 10 pesos per day
					}
					
					// Update equipment quantity
					$update_equip = $conn->prepare("UPDATE equipment SET quantity = ?, updated_at = NOW() WHERE id = ?");
					$update_equip->bind_param("ii", $new_qty, $equipment_id);
					
					if (!$update_equip->execute()) {
						throw new Exception("Failed to update equipment quantity");
					}
					
					// Update transaction record
					$actual_return_date = date('Y-m-d H:i:s');
					$status = 'Returned';
					$notes = $transaction['notes'] . " | Returned via kiosk by student ID: " . $student_id;
					
					$update_trans = $conn->prepare("UPDATE transactions 
						SET actual_return_date = ?, 
							condition_after = ?, 
							status = ?, 
							penalty_applied = ?,
							notes = ?,
							updated_at = NOW() 
						WHERE id = ?");
					
					$update_trans->bind_param("sssisi", 
						$actual_return_date,
						$condition_after,
						$status,
						$penalty,
						$notes,
						$transaction_id
					);
					
					if (!$update_trans->execute()) {
						throw new Exception("Failed to update transaction: " . $update_trans->error);
					}
					
					// Update user penalty points if overdue
					if ($penalty > 0) {
						$update_penalty = $conn->prepare("UPDATE users SET penalty_points = penalty_points + ? WHERE id = ?");
						$update_penalty->bind_param("ii", $penalty, $user_id);
						$update_penalty->execute();
						$update_penalty->close();
					}
					
					$conn->commit();
					
					// Success message
					$message = "Equipment returned successfully!<br><strong>$equipment_name</strong><br>Transaction ID: #$transaction_id";
					if ($penalty > 0) {
						$message .= "<br><span style='color: #ff9800;'>⚠ Overdue penalty: $penalty points</span>";
					}
					
					$update_trans->close();
					$update_equip->close();
				} else {
					$conn->rollback();
					$error = 'Transaction not found or already returned.';
				}
				
				$stmt->close();
			} catch (Exception $ex) {
				$conn->rollback();
				$error = 'Return failed: ' . $ex->getMessage();
			}
		} else {
			$error = 'Invalid transaction selected.';
		}
	}

	// Fetch borrowed items for the current user
	$query = "SELECT t.*, e.name as equipment_name, e.image_path,
					 c.name as category_name,
					 TIMESTAMPDIFF(DAY, NOW(), t.expected_return_date) as days_remaining,
					 CASE 
						WHEN t.expected_return_date < NOW() THEN 'Overdue'	
						WHEN DATE(t.expected_return_date) = CURDATE() THEN 'Due Today'
						ELSE 'On Time'
					 END as status_text
			  FROM transactions t
			  JOIN equipment e ON t.equipment_id = e.id
			  LEFT JOIN categories c ON e.category_id = c.id
			  WHERE t.user_id = ?
			  AND t.status = 'Active' 
			  AND t.transaction_type = 'Borrow'
			  ORDER BY t.expected_return_date ASC";
		
	$stmt = $conn->prepare($query);
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	
	while ($row = $result->fetch_assoc()) { 
		$borrowed_items[] = $row; 
	}
	
	$stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Return Equipment - Equipment Kiosk</title>
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

		<div class="return-page-content">
			<!-- Header -->
			<div class="return-header">
				<div class="header-left">
					<img src="../uploads/De lasalle ASMC.png" alt="Logo" class="header-logo-small">
					<div>
						<h1 class="page-title">Return Equipment</h1>
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
			document.getElementById('successModal').style.display = 'flex';
			
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

			<?php if (empty($borrowed_items)): ?>
				<div class="empty-state-container">
					<div class="empty-state-icon">
						<i class="fas fa-check-circle"></i>
					</div>
					<h2 class="empty-state-title">No Items to Return</h2>
					<p class="empty-state-text">You don't have any borrowed items that need to be returned.</p>
				</div>
			<?php else: ?>
				<!-- Equipment Grid -->
				<div class="equipment-grid">
					<?php foreach ($borrowed_items as $item): 
						$status_class = strtolower(str_replace(' ', '-', $item['status_text']));
						$image_src = $item['image_path'] ?? '';
						if ($image_src && strpos($image_src, 'uploads/') === 0) {
							$image_src = '../' . $image_src;
						} elseif ($image_src && strpos($image_src, '../') !== 0 && strpos($image_src, 'http') !== 0) {
							$image_src = '../uploads/' . basename($image_src);
						}
					?>
					<div class="equip-card return-card" onclick="openReturnModal(<?= $item['id'] ?>, '<?= addslashes(htmlspecialchars($item['equipment_name'])) ?>', '<?= htmlspecialchars($item['status_text']) ?>', <?= abs($item['days_remaining']) ?>)">
						<div class="equip-image">
							<?php if (!empty($image_src)): ?>
								<img src="<?= htmlspecialchars($image_src) ?>" alt="<?= htmlspecialchars($item['equipment_name']) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
								<i class="fas fa-box" style="display:none;"></i>
							<?php else: ?>
								<i class="fas fa-box"></i>
							<?php endif; ?>
						</div>
						
						<div class="equip-details">
							<span class="equip-id">#<?= $item['id'] ?></span>
							<h3 class="equip-name"><?= htmlspecialchars($item['equipment_name']) ?></h3>
							<p class="equip-category">
								<i class="fas fa-tag"></i> <?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?>
							</p>
							<p class="equip-due">
								<i class="fas fa-calendar"></i> Due: <?= date('M j, Y g:i A', strtotime($item['expected_return_date'])) ?>
							</p>
							<span class="status-badge <?= $status_class ?>">
								<?= $item['status_text'] ?>
							</span>
						</div>
						
						<button class="return-btn">
							<i class="fas fa-undo-alt"></i> Return
						</button>
					</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Return Modal -->
	<div id="returnModal" class="modal-overlay">
		<div class="modal-box">
			<div class="modal-header">
				<h2>Return Equipment</h2>
				<button class="modal-close" onclick="closeReturnModal()">
					<i class="fas fa-times"></i>
				</button>
			</div>
			
			<div class="modal-body">
				<form method="POST" id="returnForm">
					<input type="hidden" name="action" value="return">
					<input type="hidden" name="transaction_id" id="modalTransactionId">
					
					<div class="return-info">
						<h3 id="modalEquipmentName"></h3>
						<p id="modalStatusInfo"></p>
					</div>
					
					<div class="form-field">
						<label><i class="fas fa-clipboard-check"></i> Equipment Condition</label>
						<select name="condition_after" id="condition_after" required>
							<option value="Good">Good - No damage</option>
							<option value="Fair">Fair - Minor wear</option>
							<option value="Damaged">Damaged - Needs repair</option>
						</select>
						<small>Please assess the equipment condition honestly</small>
					</div>
					
					<div class="modal-actions">
						<button type="button" class="btn-cancel" onclick="closeReturnModal()">
							<i class="fas fa-times"></i> Cancel
						</button>
						<button type="submit" class="btn-confirm">
							<i class="fas fa-check"></i> Confirm Return
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<script>
	function goBack() {
		window.location.href = 'borrow-return.php';
	}

	function openReturnModal(transactionId, equipmentName, status, daysRemaining) {
		document.getElementById('modalTransactionId').value = transactionId;
		document.getElementById('modalEquipmentName').textContent = equipmentName;
		
		let statusInfo = '';
		if (status === 'Overdue') {
			const penalty = daysRemaining * 10;
			statusInfo = `<span style="color: #f44336;"><i class="fas fa-exclamation-triangle"></i> This item is ${daysRemaining} day(s) overdue. Penalty: ${penalty} points</span>`;
		} else if (status === 'Due Today') {
			statusInfo = `<span style="color: #ff9800;"><i class="fas fa-clock"></i> This item is due today</span>`;
		} else {
			statusInfo = `<span style="color: #4caf50;"><i class="fas fa-check-circle"></i> Returning on time</span>`;
		}
		
		document.getElementById('modalStatusInfo').innerHTML = statusInfo;
		document.getElementById('returnModal').style.display = 'flex';
	}

	function closeReturnModal() {
		document.getElementById('returnModal').style.display = 'none';
	}

	// Close modal when clicking outside
	window.addEventListener('click', function(event) {
		const modal = document.getElementById('returnModal');
		if (event.target === modal) {
			closeReturnModal();
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

	<style>
	/* Override body styles */
	body {
		display: block !important;
		overflow-y: auto !important;
		min-height: 100vh;
		background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
		font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
	}

	.container {
		min-height: 100vh;
		display: flex;
		flex-direction: column;
		position: relative;
	}

	/* Return Page Styles */
	.return-page-content {
		padding: 20px;
		max-width: 1400px;
		margin: 0 auto;
		width: 100%;
		flex: 1;
		position: relative;
		z-index: 1;
	}

	.return-header {
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
	}

	/* Empty State */
	.empty-state-container {
		text-align: center;
		padding: 80px 20px;
		background: white;
		border-radius: 20px;
		box-shadow: 0 2px 10px rgba(0,0,0,0.05);
	}

	.empty-state-icon {
		font-size: 80px;
		color: #4caf50;
		margin-bottom: 20px;
	}

	.empty-state-title {
		font-size: 2rem;
		color: #333;
		margin-bottom: 15px;
	}

	.empty-state-text {
		font-size: 1.1rem;
		color: #666;
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

	.equip-due {
		color: #666;
		font-size: 0.9rem;
		margin: 8px 0;
		display: flex;
		align-items: center;
		gap: 6px;
	}

	.return-card {
		cursor: pointer;
	}

	.status-badge {
		display: inline-block;
		padding: 6px 12px;
		border-radius: 15px;
		font-size: 0.85rem;
		font-weight: 600;
		margin-top: 10px;
	}

	.status-badge.on-time {
		background: rgba(76, 175, 80, 0.1);
		color: #4caf50;
		border: 1px solid #4caf50;
	}

	.status-badge.due-today {
		background: rgba(255, 152, 0, 0.1);
		color: #ff9800;
		border: 1px solid #ff9800;
	}

	.status-badge.overdue {
		background: rgba(244, 67, 54, 0.1);
		color: #f44336;
		border: 1px solid #f44336;
		animation: pulse 2s infinite;
	}

	@keyframes pulse {
		0%, 100% { opacity: 1; }
		50% { opacity: 0.7; }
	}

	.return-btn {
		width: 100%;
		padding: 14px;
		background: #2563eb;
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

	.return-btn:hover {
		background: #1d4ed8;
	}

	/* Return Modal */
	.modal-overlay {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, 0.7);
		backdrop-filter: blur(5px);
		z-index: 9999;
		align-items: center;
		justify-content: center;
		animation: fadeIn 0.3s ease;
	}

	.modal-box {
		background: white;
		border-radius: 20px;
		width: 90%;
		max-width: 600px;
		max-height: 90vh;
		overflow: hidden;
		animation: modalSlideIn 0.3s ease;
		box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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
		background: linear-gradient(135deg, #1e5631, #2d7a45);
	}

	.modal-header h2 {
		margin: 0;
		font-size: 1.5rem;
		color: white;
		font-weight: 600;
	}

	.modal-close {
		background: rgba(255, 255, 255, 0.2);
		border: none;
		font-size: 24px;
		color: white;
		cursor: pointer;
		width: 40px;
		height: 40px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.3s ease;
	}

	.modal-close:hover {
		background: rgba(255, 255, 255, 0.3);
		transform: rotate(90deg);
	}

	.modal-body {
		padding: 30px;
		overflow-y: auto;
		max-height: calc(90vh - 200px);
	}

	.return-info {
		background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
		padding: 20px;
		border-radius: 15px;
		margin-bottom: 25px;
		border-left: 4px solid #1e5631;
	}

	.return-info h3 {
		margin: 0 0 10px 0;
		color: #1e5631;
		font-size: 1.4rem;
		font-weight: 700;
	}

	.return-info p {
		margin: 0;
		font-size: 1rem;
		line-height: 1.6;
	}

	.form-field {
		margin-bottom: 25px;
	}

	.form-field label {
		display: block;
		margin-bottom: 10px;
		color: #333;
		font-weight: 600;
		font-size: 1rem;
	}

	.form-field label i {
		color: #1e5631;
		margin-right: 8px;
	}

	.form-field select {
		width: 100%;
		padding: 12px 15px;
		border: 2px solid #e0e0e0;
		border-radius: 10px;
		font-size: 1rem;
		background: white;
		cursor: pointer;
		transition: all 0.3s ease;
	}

	.form-field select:focus {
		outline: none;
		border-color: #1e5631;
		box-shadow: 0 0 0 3px rgba(30, 86, 49, 0.1);
	}

	.form-field small {
		display: block;
		margin-top: 8px;
		color: #666;
		font-size: 0.9rem;
	}

	.modal-actions {
		display: flex;
		gap: 15px;
		justify-content: flex-end;
		margin-top: 30px;
		padding-top: 20px;
		border-top: 1px solid #eee;
	}

	.btn-cancel {
		background: #6c757d;
		color: white;
		border: none;
		padding: 12px 30px;
		border-radius: 10px;
		font-size: 1rem;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.3s ease;
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.btn-cancel:hover {
		background: #5a6268;
		transform: translateY(-2px);
	}

	.btn-confirm {
		background: linear-gradient(135deg, #1e5631, #2d7a45);
		color: white;
		border: none;
		padding: 12px 30px;
		border-radius: 10px;
		font-size: 1rem;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.3s ease;
		display: flex;
		align-items: center;
		gap: 8px;
		box-shadow: 0 4px 15px rgba(30, 86, 49, 0.3);
	}

	.btn-confirm:hover {
		transform: translateY(-2px);
		box-shadow: 0 6px 20px rgba(30, 86, 49, 0.4);
	}

	/* Notification Modal Styles (reuse from borrow.php) */
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

	/* Responsive Design */
	@media (max-width: 768px) {
		.equipment-grid {
			grid-template-columns: 1fr;
		}
		
		.return-header {
			flex-direction: column;
			gap: 15px;
			text-align: center;
		}

		.header-left {
			flex-direction: column;
			text-align: center;
		}

		.modal-box {
			width: 95%;
			margin: 20px;
		}

		.modal-actions {
			flex-direction: column;
		}

		.btn-cancel,
		.btn-confirm {
			width: 100%;
			justify-content: center;
		}

		.notification-modal-content {
			padding: 40px 30px;
			max-width: 90%;
		}
	}

	@media (max-width: 480px) {
		.page-title {
			font-size: 1.4rem;
		}

		.equip-name {
			font-size: 1.1rem;
		}

		.return-header {
			padding: 15px;
		}
	}
	</style>
</body>
</html>
