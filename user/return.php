<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
if (empty($_SESSION['face_verified'])) {
    header('Location: index.php?face=required');
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
// Email helpers
require_once __DIR__ . '/../admin/includes/email_config.php';
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
		$return_photo_data = $_POST['return_photo'] ?? '';

		if ($transaction_id > 0) {
			$conn->begin_transaction();
			try {
				// Get transaction and inventory details with lock
				$stmt = $conn->prepare("SELECT t.*, e.name as equipment_name, e.quantity as current_qty,
				i.available_quantity, i.minimum_stock_level,
				i.borrowed_quantity, i.damaged_quantity, i.equipment_id AS inventory_equipment_id,
				e.rfid_tag, e.image_path, i.item_size
				FROM transactions t 
				JOIN equipment e ON t.equipment_id = e.rfid_tag 
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
					
					$returnPhotoPath = null;
					$returnPhotoAbsolute = null;
					// Require a valid return photo for comparison
					if (!empty($return_photo_data) && strpos($return_photo_data, 'data:image') === 0) {
						$suffix = time() . '_' . $transaction_id . '.jpg';
						$relativeDir = 'uploads/transaction_photos/';
						$absoluteDir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . $relativeDir;
						if (!is_dir($absoluteDir)) {
							if (!mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
								throw new Exception('Failed to create photo directory.');
							}
						}

						$rawData = explode(',', $return_photo_data, 2);
						$decoded = base64_decode($rawData[1] ?? '', true);
						if ($decoded === false) {
							throw new Exception('Invalid return photo data.');
						}

						$returnPhotoAbsolute = $absoluteDir . $suffix;
						if (file_put_contents($returnPhotoAbsolute, $decoded) === false) {
							throw new Exception('Failed to save return photo.');
						}
						$returnPhotoPath = $relativeDir . $suffix;

						$photoStmt = $conn->prepare("INSERT INTO transaction_photos (transaction_id, photo_type, file_path, created_at) VALUES (?, 'return', ?, NOW())");
						if ($photoStmt) {
							$photoStmt->bind_param('is', $transaction_id, $returnPhotoPath);
							$photoStmt->execute();
							$photoStmt->close();
						}
					}
					else {
						throw new Exception('A clear return photo is required to proceed.');
					}

					$sizeCategory = strtolower($transaction['item_size'] ?? 'medium');
					$comparisonPhotoPath = null;
					$similarityScore = null;
					$verificationStatus = 'Pending';
					$reviewStatus = 'Pending';
					$detectedIssuesText = 'Pending comparison';
					$severityLevel = 'pending';
					$comparisonQueued = false;
					$shouldRunComparison = false;
					$referenceFullPath = null;
					$comparisonDir = null;
					$comparisonResults = [];
					$comparisonWarnings = [];
					$usedBorrowReference = false;

					$rootPath = realpath(__DIR__ . '/../');

					if (!empty($returnPhotoPath) && $returnPhotoAbsolute && file_exists($returnPhotoAbsolute)) {
						$referenceBase = null;

						// Prefer the borrow-time photo as reference; fallback to equipment image
						$borrowPhotoStmt = $conn->prepare("SELECT file_path FROM transaction_photos WHERE transaction_id = ? AND photo_type = 'borrow' ORDER BY id ASC LIMIT 1");
						if ($borrowPhotoStmt) {
							$borrowPhotoStmt->bind_param('i', $transaction_id);
							$borrowPhotoStmt->execute();
							$borrowResult = $borrowPhotoStmt->get_result();
							$borrowRow = $borrowResult ? $borrowResult->fetch_assoc() : null;
							if ($borrowRow && !empty($borrowRow['file_path'])) {
								$referenceBase = $borrowRow['file_path'];
								$usedBorrowReference = true;
							}
							$borrowPhotoStmt->close();
						}
						if (!$referenceBase && !empty($transaction['image_path'])) {
							$referenceBase = $transaction['image_path'];
						}

						if (!empty($referenceBase)) {
							$candidatePaths = [];
							$candidatePaths[] = $rootPath . DIRECTORY_SEPARATOR . ltrim($referenceBase, '/\\');
							if (strpos($referenceBase, '../') === 0) {
								$trimmed = ltrim(substr($referenceBase, 3), '/\\');
								$candidatePaths[] = $rootPath . DIRECTORY_SEPARATOR . $trimmed;
							}
							$realReference = realpath($referenceBase);
							if ($realReference !== false) {
								$candidatePaths[] = $realReference;
							}
							// Also try absolute path from database if it starts with uploads/
							if (strpos($referenceBase, 'uploads/') === 0) {
								$candidatePaths[] = $rootPath . DIRECTORY_SEPARATOR . $referenceBase;
							}
							foreach ($candidatePaths as $pathCandidate) {
								if ($pathCandidate && file_exists($pathCandidate)) {
									$referenceFullPath = $pathCandidate;
									break;
								}
							}
						}

						if (!$referenceFullPath) {
							throw new Exception('Reference image unavailable for comparison. Please contact staff.');
						}
						$comparisonDir = $rootPath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'transaction_photos' . DIRECTORY_SEPARATOR;
						if (!is_dir($comparisonDir)) {
							if (!mkdir($comparisonDir, 0755, true) && !is_dir($comparisonDir)) {
								throw new Exception('Failed to create comparison directory.');
							}
						}

						// Run comparison immediately and enforce threshold
						require_once __DIR__ . '/../includes/image_comparison.php';
						$comparisonResults = compareReturnToReference($referenceFullPath, $returnPhotoAbsolute, [ 'item_size' => $sizeCategory ]);
						if (empty($comparisonResults['success'])) {
							throw new Exception('Image comparison failed. Please retake a clearer photo.');
						}
						$similarityScore = (float)($comparisonResults['similarity'] ?? 0);
						// Dynamic threshold: stricter when we have the exact borrow-time photo, more tolerant for catalog image
						$minSimilarity = $usedBorrowReference ? 70.0 : 40.0;
						if ($similarityScore < $minSimilarity) {
							// Determine if any issues/damages detected
							$issuesList = [];
							if (!empty($comparisonResults['detected_issues_list']) && is_array($comparisonResults['detected_issues_list'])) {
								foreach ($comparisonResults['detected_issues_list'] as $msg) {
									$m = trim((string)$msg);
									if ($m !== '') { $issuesList[] = ucfirst($m) . (substr($m, -1) === '.' ? '' : '.'); }
								}
							} elseif (!empty($comparisonResults['detected_issues_text'])) {
								$t = trim((string)$comparisonResults['detected_issues_text']);
								if ($t !== '') { $issuesList[] = ucfirst($t) . (substr($t, -1) === '.' ? '' : '.'); }
							}

							$severityLevel = $comparisonResults['severity_level'] ?? 'none';
							if (empty($issuesList)) {
								$verificationStatus = 'Verified';
								$reviewStatus = 'Verified';
								$detectedIssuesText = 'None';
								$severityLevel = 'none';
							} else {
								$verificationStatus = 'Verified';
								$reviewStatus = 'Pending Review';
								$detectedIssuesText = implode("\n", $issuesList);
								if ($severityLevel === 'none') { $severityLevel = 'medium'; }
							}
						} else {
							throw new Exception('Item mismatch detected. Please retake a clearer photo or return the correct item.');
						}
					} else {
						throw new Exception('Return photo missing – please capture a clear photo to proceed.');
					}
					// At this point, comparison has verified the item. Proceed to mark return below.

					// Finalize transaction as Returned after successful comparison
					$actual_return_date = date('Y-m-d H:i:s');
					$notesBase = ($transaction['notes'] ?? '');
					$notes = $notesBase . "\n[System] Return verified at $actual_return_date (" . round((float)$similarityScore, 2) . "% similarity).";
					if (!empty($detectedIssuesText) && $detectedIssuesText !== 'None') {
						$notes .= "\n[System] Detected issues: " . str_replace(["\n", "\r"], ' | ', $detectedIssuesText);
					}
					$finalize = $conn->prepare("UPDATE transactions SET 
						transaction_type = 'Return',
						status = 'Returned',
						actual_return_date = ?,
						condition_after = ?,
						penalty_applied = ?,
						return_verification_status = ?,
						return_review_status = ?,
						similarity_score = ?,
						detected_issues = ?,
						severity_level = ?,
						notes = ?,
						updated_at = NOW()
					WHERE id = ?");
					if (!$finalize) {
						throw new Exception('Failed to finalize return.');
					}
					$finalize->bind_param('ssissdsssi', $actual_return_date, $condition_after, $penalty, $verificationStatus, $reviewStatus, $similarityScore, $detectedIssuesText, $severityLevel, $notes, $transaction_id);
					if (!$finalize->execute()) {
						throw new Exception('Failed to write return details.');
					}
					$finalize->close();

					// Transaction finalized - commit changes
					$conn->commit();
                    
                    // Success message
                    $message = "Equipment returned successfully!<br><strong>$equipment_name</strong><br>Transaction ID: #$transaction_id";
                    // Update user penalty points if overdue
                    if ($penalty > 0) {
                        $update_penalty = $conn->prepare("UPDATE users SET penalty_points = penalty_points + ? WHERE id = ?");
                        $update_penalty->bind_param("ii", $penalty, $user_id);
                        $update_penalty->execute();
                        $update_penalty->close();
                        $message .= "<br><span style='color: #ff9800;'>⚠ Overdue penalty: $penalty points</span>";
                        // Notify penalty via email (auto)
                        @sendPenaltyNotification($conn, $user_id, $equipment_name, $penalty, 'Overdue return');
                    }
                    // Send return confirmation email
                    @sendReturnNotification($conn, $user_id, $equipment_name, $condition_after);
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
	$query = "SELECT 
				 t.*, t.quantity AS borrowed_quantity,
				 e.name AS equipment_name,
				 e.image_path,
				 e.rfid_tag AS equipment_rfid,
				 c.name AS category_name,
				 i.item_size,
				 TIMESTAMPDIFF(DAY, NOW(), t.expected_return_date) AS days_remaining,
				 CASE 
				   WHEN t.expected_return_date < NOW() THEN 'Overdue'
				   WHEN DATE(t.expected_return_date) = CURDATE() THEN 'Due Today'
				   ELSE 'On Time'
				 END AS status_text
			 FROM transactions t
			 JOIN equipment e ON t.equipment_id = e.rfid_tag
			 LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id
			 LEFT JOIN categories c ON e.category_id = c.id
			 WHERE t.user_id = ?
			   AND t.status = 'Active'
			   AND t.transaction_type = 'Borrow'
			 ORDER BY t.expected_return_date ASC";

	$stmt = $conn->prepare($query);
	if ($stmt) {
		$stmt->bind_param("i", $user_id);
		$stmt->execute();
		$result = $stmt->get_result();
		while ($row = $result->fetch_assoc()) {
			$borrowed_items[] = $row;
		}
		$stmt->close();
	} else {
		$error = 'Failed to prepare items query.';
	}
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
			<div class="header-actions">
				<form id="returnScanForm" class="rfid-listener" autocomplete="off" aria-hidden="true">
					<input type="text" id="returnScanInput" name="return_rfid" inputmode="numeric" autocomplete="off" autofocus>
					<button type="submit" tabindex="-1">Scan</button>
				</form>
				<button class="back-btn" onclick="goBack()">
					<i class="fas fa-arrow-left"></i> Back
				</button>
			</div>
		</div>

		<div class="return-instructions">
			<p><strong>Scan the equipment RFID</strong> to open the return form automatically. You can also click <strong>“Return”</strong> below an item for manual processing.</p>
			<div id="scanStatusMessage" class="scan-status" role="status" aria-live="polite"></div>
		</div>

			<?php if ($message): ?>
			<!-- Success Modal -->
			<div id="successModal" class="notification-modal" data-cond="<?= htmlspecialchars($condition_after ?? '') ?>" data-qty="<?= isset($quantity_returned) ? (int)$quantity_returned : 0 ?>" data-penalty="<?= isset($penalty) ? (int)$penalty : 0 ?>">
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
			<script>
			(function(){
				const modal = document.getElementById('successModal');
				const msgEl = modal.querySelector('.notification-message');
				if (!msgEl) return;
				const html = msgEl.innerHTML;
				const txnMatch = html.match(/Transaction ID:\s*#(\d+)/i);
				const cond = modal.dataset.cond || '';
				const qty = parseInt(modal.dataset.qty || '0', 10) || 0;
				const pen = parseInt(modal.dataset.penalty || '0', 10) || 0;
				let extra = '<div class="success-summary">';
				if (cond) extra += `<div class="sum-row"><span>Condition Status</span><strong>${cond}</strong></div>`;
				if (qty) extra += `<div class=\"sum-row\"><span>Borrowed Quantity</span><strong>${qty}</strong></div>`;
				extra += `<div class=\"sum-row\"><span>Penalties Applied</span><strong>${pen > 0 ? pen + ' points' : 'None'}</strong></div>`;
				if (txnMatch) extra += `<div class=\"sum-row\"><span>Transaction ID</span><strong>#${txnMatch[1]}</strong></div>`;
				extra += '</div><p class="return-mini-note">Please keep your transaction ID for any follow-up.</p>';
				msgEl.insertAdjacentHTML('afterend', extra);
			})();
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
					<div class="equip-card return-card" data-transaction-id="<?= $item['id'] ?>" data-equipment-name="<?= htmlspecialchars($item['equipment_name']) ?>" data-equipment-rfid="<?= htmlspecialchars($item['equipment_rfid'] ?? '') ?>" data-item-size="<?= htmlspecialchars(strtolower($item['item_size'] ?? '')) ?>" onclick="openReturnModal(<?= $item['id'] ?>, '<?= addslashes(htmlspecialchars($item['equipment_name'])) ?>', '<?= htmlspecialchars($item['status_text']) ?>', <?= abs($item['days_remaining']) ?>, <?= (int)($item['borrowed_quantity'] ?? $item['quantity']) ?>)">
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
					<p class="return-steps"><span class="step">Steps:</span> Scan tag → Place item for photo → Countdown → Review image</p>
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
						<p class="return-mini-note">Ensure the photo is clear and keep your transaction ID.</p>
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
		const scanForm = document.getElementById('returnScanForm');
		const scanInput = document.getElementById('returnScanInput');
		const scanStatus = document.getElementById('scanStatusMessage');

		// Manual verification mode removed: all returns require a photo for comparison.

		function setScanStatus(message, type = 'info') {
			if (!scanStatus) return;
			scanStatus.textContent = message;
			scanStatus.dataset.state = type;
			if (type !== 'scanning') {
				setTimeout(() => {
					if (scanStatus.dataset.state === type) {
						scanStatus.textContent = '';
						scanStatus.dataset.state = '';
					}
				}, 4000);
			}
		}

		async function fetchTransactionByRFID(rfidValue) {
			const response = await fetch('fetch_return_item.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded'
				},
				body: 'rfid=' + encodeURIComponent(rfidValue)
			});
			if (!response.ok) {
				throw new Error('Network error');
			}
			const data = await response.json();
			if (!data.success) {
				throw new Error(data.message || 'Unable to find transaction');
			}
			return data;
		}

		function highlightCard(transactionId) {
			const card = document.querySelector(`[data-transaction-id="${transactionId}"]`);
			if (!card) return;
			card.classList.add('highlight');
			card.scrollIntoView({ behavior: 'smooth', block: 'center' });
			setTimeout(() => card.classList.remove('highlight'), 2000);
		}

		function ensureScanFocus() {
			if (scanInput && !document.getElementById('returnModal').classList.contains('active')) {
				scanInput.focus();
			}
		}

		if (scanForm && scanInput) {
			window.addEventListener('load', () => setTimeout(ensureScanFocus, 200));

			scanForm.addEventListener('submit', async (event) => {
				event.preventDefault();
				const rfidValue = scanInput.value.trim();
				if (!rfidValue) return;
				setScanStatus('Scanning RFID...', 'scanning');
				scanInput.value = '';
				try {
					const data = await fetchTransactionByRFID(rfidValue);
					setScanStatus(`Found ${data.equipment_name}. Preparing return form…`, 'success');
					highlightCard(data.transaction_id);
					openReturnModal(
							data.transaction_id,
							data.equipment_name,
							data.status_text,
							Math.abs(data.days_overdue || 0),
							data.quantity
						);
				} catch (err) {
					setScanStatus(err.message || 'Scan failed. Please try again.', 'error');
				} finally {
					setTimeout(ensureScanFocus, 400);
				}
			});

			document.addEventListener('click', (event) => {
				const inModal = event.target.closest('.modal-box');
				if (inModal) return;
				if (scanInput && document.activeElement !== scanInput) {
					scanInput.focus();
				}
			});
		}

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
				// Mirror preview horizontally and flip vertically to correct upside-down cameras
                videoEl.style.transform = 'scaleX(-1) scaleY(-1)';
                videoEl.style.transformOrigin = 'center center';
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
            // Apply the same transforms as the preview: horizontal mirror + vertical flip
            context.save();
            context.translate(canvasEl.width, canvasEl.height);
            context.scale(-1, -1);
            context.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
            context.restore();

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
            // Always require photo capture for returns
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
			const modal = document.getElementById('returnModal');
			modal.style.display = 'flex';
			modal.classList.add('active');

            // Photo capture is mandatory; no manual mode
		}

		function closeReturnModal() {
			stopCamera();
			resetCaptureUI();
			const modal = document.getElementById('returnModal');
			modal.style.display = 'none';
			modal.classList.remove('active');
			setTimeout(ensureScanFocus, 200);
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

		// Auto-refresh borrowed items grid so newly approved items appear without page reload
		async function refreshBorrowedItemsGrid() {
			try {
				// Do not refresh while modal is open to avoid disrupting an ongoing return
				const modalOpen = document.getElementById('returnModal')?.classList.contains('active');
				if (modalOpen) return;

				const response = await fetch(window.location.href, { cache: 'no-store', credentials: 'same-origin' });
				if (!response.ok) return;
				const html = await response.text();
				const parser = new DOMParser();
				const doc = parser.parseFromString(html, 'text/html');

				const currentSection = document.querySelector('.equipment-grid') || document.querySelector('.empty-state-container');
				const newSection = doc.querySelector('.equipment-grid') || doc.querySelector('.empty-state-container');
				if (!currentSection || !newSection) return;

				// Only replace if content has actually changed
				if (currentSection.outerHTML !== newSection.outerHTML) {
					currentSection.replaceWith(newSection);
					// After replacing, ensure scan input focus is restored
					setTimeout(ensureScanFocus, 100);
				}
			} catch (err) {
				console.warn('Return page auto-refresh failed:', err);
			}
		}

		// Refresh every 5 seconds
		setInterval(refreshBorrowedItemsGrid, 5000);
		</script>
	</body>
	</html>
