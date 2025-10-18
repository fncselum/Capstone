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
					i.available_quantity, i.minimum_stock_level,
					i.borrowed_quantity, i.damaged_quantity, i.equipment_id AS inventory_equipment_id,
					e.rfid_tag
					FROM transactions t 
					JOIN equipment e ON t.equipment_id = e.id 
					LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id
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
					$current_available = (int)($transaction['available_quantity'] ?? 0);
					$min_stock = (int)($transaction['minimum_stock_level'] ?? 1);
					$rfid_tag = $transaction['rfid_tag'];
					$inventory_equipment_id = $transaction['inventory_equipment_id'];
					
					// Calculate penalty if overdue
					$expected_return = new DateTime($transaction['expected_return_date']);
					$actual_return = new DateTime();
					$penalty = 0;
					
					if ($actual_return > $expected_return) {
						$days_overdue = $actual_return->diff($expected_return)->days;
						$penalty = $days_overdue * 10; // 10 pesos per day
					}
					
					if (empty($rfid_tag) || empty($inventory_equipment_id)) {
						throw new Exception('Inventory record missing for this equipment.');
					}

					// Calculate new available quantity and determine status
					$new_available = $current_available + $quantity_returned;
					$new_borrowed = max(0, ((int)$transaction['borrowed_quantity']) - $quantity_returned);

					// Handle damaged equipment - adjust available quantity and damaged count
					if ($condition_after === 'Damaged') {
						$new_available = max(0, $new_available - 1);
					}

					$new_status = 'Available';
					if ($new_available == 0) {
						$new_status = 'Out of Stock';
					} elseif ($new_available <= $min_stock) {
						$new_status = 'Low Stock';
					}

					// Update inventory table using RFID tag
					if ($condition_after === 'Damaged') {
						$inv_stmt = $conn->prepare("UPDATE inventory 
							SET borrowed_quantity = GREATEST(borrowed_quantity - ?, 0),
								damaged_quantity = damaged_quantity + 1,
								availability_status = ?,
								last_updated = NOW()
							WHERE equipment_id = ?");
						$inv_stmt->bind_param("iss", $quantity_returned, $new_status, $rfid_tag);
					} else {
						$inv_stmt = $conn->prepare("UPDATE inventory 
							SET available_quantity = available_quantity + ?,
								borrowed_quantity = GREATEST(borrowed_quantity - ?, 0),
								availability_status = ?,
								last_updated = NOW()
							WHERE equipment_id = ?");
						$inv_stmt->bind_param("iiss", $quantity_returned, $quantity_returned, $new_status, $rfid_tag);
					}

					if (!$inv_stmt->execute() || $inv_stmt->affected_rows !== 1) {
						throw new Exception("Failed to update inventory: " . $inv_stmt->error);
					}
					$inv_stmt->close();

					// Equipment table already reflects total quantity; inventory handles availability tracking.
					
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
	$query = "SELECT t.*, t.quantity AS borrowed_quantity,
				 e.name as equipment_name, e.image_path,
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
					<div class="equip-card return-card" onclick="openReturnModal(<?= $item['id'] ?>, '<?= addslashes(htmlspecialchars($item['equipment_name'])) ?>', '<?= htmlspecialchars($item['status_text']) ?>', <?= abs($item['days_remaining']) ?>, <?= (int)($item['borrowed_quantity'] ?? $item['quantity']) ?>)">
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
							<p class="equip-qty">
								<i class="fas fa-boxes"></i> Quantity: <?= (int)($item['borrowed_quantity'] ?? $item['quantity']) ?>
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
			<form method="POST" id="returnForm" class="return-form">
				<input type="hidden" name="action" value="return">
				<input type="hidden" name="transaction_id" id="modalTransactionId">
				<input type="hidden" name="return_photo" id="returnPhotoInput">
				
				<div class="return-modal-left">
					<div class="capture-frame" id="captureFrame">
						<video id="returnVideo" autoplay muted playsinline></video>
						<canvas id="returnCanvas"></canvas>
						<div class="capture-overlay">
							<div class="capture-countdown" id="captureCountdown">5</div>
						</div>
					</div>
					<div class="capture-status" id="captureStatus">Initializing camera…</div>
					<button type="button" class="capture-retake" id="retakePhotoBtn" disabled>
						<i class="fas fa-camera"></i> Retake Photo
					</button>
				</div>
				
				<div class="return-modal-right">
					<div class="return-info">
						<h3 id="modalEquipmentName"></h3>
						<p id="modalStatusInfo"></p>
						<p id="modalQuantityInfo" class="return-qty"></p>
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
				</div>
			</form>
		</div>
		</div>
	</div>

	<script>
	function goBack() {
		window.location.href = 'borrow-return.php';
	}

const returnForm = document.getElementById('returnForm');
const videoEl = document.getElementById('returnVideo');
const canvasEl = document.getElementById('returnCanvas');
const countdownEl = document.getElementById('captureCountdown');
const statusEl = document.getElementById('captureStatus');
const retakeBtn = document.getElementById('retakePhotoBtn');
const photoInput = document.getElementById('returnPhotoInput');
const captureOverlay = document.querySelector('#captureFrame .capture-overlay');
const confirmBtn = returnForm.querySelector('.btn-confirm');
let mediaStream = null;
let countdownTimer = null;
let countdownSeconds = 5;

function clearCountdown() {
	if (countdownTimer) {
		clearInterval(countdownTimer);
		countdownTimer = null;
	}
}

function stopCamera() {
	if (mediaStream) {
		mediaStream.getTracks().forEach(track => track.stop());
		mediaStream = null;
	}
	if (videoEl) {
		videoEl.srcObject = null;
	}
}

function resetCaptureUI() {
	clearCountdown();
	photoInput.value = '';
	countdownSeconds = 5;
	countdownEl.textContent = countdownSeconds;
	captureOverlay.style.display = 'flex';
	videoEl.style.display = 'block';
	canvasEl.style.display = 'none';
	statusEl.textContent = 'Initializing camera…';
	retakeBtn.disabled = true;
	confirmBtn.disabled = true;
}

function startCountdown() {
	clearCountdown();
	countdownSeconds = 5;
	countdownEl.textContent = countdownSeconds;
	captureOverlay.style.display = 'flex';
	retakeBtn.disabled = true;
	confirmBtn.disabled = true;
	countdownTimer = setInterval(() => {
		countdownSeconds -= 1;
		if (countdownSeconds <= 0) {
			clearCountdown();
			capturePhoto();
		} else {
			countdownEl.textContent = countdownSeconds;
		}
	}, 1000);
}

async function startCamera() {
	try {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			statusEl.textContent = 'Camera not supported on this device.';
			confirmBtn.disabled = true;
			retakeBtn.disabled = true;
			captureOverlay.style.display = 'none';
			return;
		}
		mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
		videoEl.srcObject = mediaStream;
		await videoEl.play().catch(() => {});
		statusEl.textContent = 'Camera ready. Taking photo in 5 seconds…';
		startCountdown();
	} catch (error) {
		console.error('Camera error:', error);
		statusEl.textContent = 'Unable to access camera. Please allow camera permissions.';
		confirmBtn.disabled = true;
		retakeBtn.disabled = true;
		captureOverlay.style.display = 'none';
	}
}

function capturePhoto() {
	if (!videoEl.videoWidth || !videoEl.videoHeight) {
		statusEl.textContent = 'Camera not ready yet. Retrying…';
		startCountdown();
		return;
	}
	canvasEl.width = videoEl.videoWidth;
	canvasEl.height = videoEl.videoHeight;
	const context = canvasEl.getContext('2d');
	context.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
	const dataUrl = canvasEl.toDataURL('image/jpeg', 0.9);
	photoInput.value = dataUrl;
	canvasEl.style.display = 'block';
	videoEl.style.display = 'none';
	captureOverlay.style.display = 'none';
	statusEl.textContent = 'Photo captured. You may retake or confirm the return.';
	retakeBtn.disabled = false;
	confirmBtn.disabled = false;
}

retakeBtn.addEventListener('click', () => {
	if (retakeBtn.disabled) return;
	photoInput.value = '';
	canvasEl.style.display = 'none';
	videoEl.style.display = 'block';
	statusEl.textContent = 'Retaking photo…';
	startCountdown();
});

returnForm.addEventListener('submit', (event) => {
	if (!photoInput.value) {
		event.preventDefault();
		statusEl.textContent = 'Please wait for the photo to be captured before submitting.';
		return;
	}
});

	function openReturnModal(transactionId, equipmentName, status, daysRemaining, quantity) {
		document.getElementById('modalTransactionId').value = transactionId;
		document.getElementById('modalEquipmentName').textContent = equipmentName;
		
	resetCaptureUI();
	startCamera();

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
		document.getElementById('modalQuantityInfo').innerHTML = `<i class="fas fa-boxes"></i> Borrowed quantity: <strong>${quantity}</strong>`;
		document.getElementById('returnModal').style.display = 'flex';
	}

	function closeReturnModal() {
		stopCamera();
		resetCaptureUI();
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
