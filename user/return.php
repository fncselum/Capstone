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
				// Get transaction and inventory details with lock
				$stmt = $conn->prepare("SELECT t.*, e.name as equipment_name, e.quantity as current_qty,
					i.available_quantity, i.minimum_stock_level
					FROM transactions t 
					JOIN equipment e ON t.equipment_id = e.id 
					LEFT JOIN inventory i ON e.id = i.equipment_id
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
					$current_available = (int)($transaction['available_quantity'] ?? 0);
					$min_stock = (int)($transaction['minimum_stock_level'] ?? 1);
					
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
					
					// Calculate new available quantity and determine status
					$new_available = $current_available + $quantity_returned;
					
					// Handle damaged equipment - adjust available quantity
					if ($condition_after === 'Damaged') {
						$new_available = $new_available - 1; // Don't add damaged item to available
					}
					
					// Determine new availability status
					$new_status = 'Available';
					if ($new_available == 0) {
						$new_status = 'Out of Stock';
					} elseif ($new_available <= $min_stock) {
						$new_status = 'Low Stock';
					}
					
					// Update inventory table
					if ($condition_after === 'Damaged') {
						// Return as damaged: decrease borrowed, increase damaged, update status
						$inv_stmt = $conn->prepare("UPDATE inventory 
							SET borrowed_quantity = borrowed_quantity - ?, 
								damaged_quantity = damaged_quantity + 1,
								availability_status = ?,
								last_updated = NOW() 
							WHERE equipment_id = ?");
						$inv_stmt->bind_param("isi", $quantity_returned, $new_status, $equipment_id);
					} else {
						// Normal return: increase available, decrease borrowed, update status
						$inv_stmt = $conn->prepare("UPDATE inventory 
							SET available_quantity = available_quantity + ?, 
								borrowed_quantity = borrowed_quantity - ?,
								availability_status = ?,
								last_updated = NOW() 
							WHERE equipment_id = ?");
						$inv_stmt->bind_param("iisi", $quantity_returned, $quantity_returned, $new_status, $equipment_id);
					}
					
					if (!$inv_stmt->execute()) {
						throw new Exception("Failed to update inventory: " . $inv_stmt->error);
					}
					$inv_stmt->close();
					
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
	<link rel="stylesheet" href="return.css?v=<?= time() ?>">
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
</body>
</html>
