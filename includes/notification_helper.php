<?php
/**
 * Notification Helper Functions
 * Automatically create system notifications for key events
 */

/**
 * Create a notification in the database
 * 
 * @param mysqli $conn Database connection
 * @param string $type Notification type: 'info', 'warning', 'success', 'error'
 * @param string $title Notification title
 * @param string $message Notification message
 * @return bool Success status
 */
function createNotification($conn, $type, $title, $message) {
    // Validate type
    $valid_types = ['info', 'warning', 'success', 'error'];
    if (!in_array($type, $valid_types)) {
        $type = 'info';
    }
    
    // Check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if (!$table_check || $table_check->num_rows === 0) {
        return false; // Table doesn't exist yet
    }
    
    // Insert notification
    $sql = "INSERT INTO notifications (type, title, message, status, created_at) 
            VALUES (?, ?, ?, 'unread', NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("sss", $type, $title, $message);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Create notification for overdue equipment
 */
function notifyOverdueEquipment($conn, $equipment_name, $student_id, $days_overdue) {
    $title = "Equipment Overdue Alert";
    $message = "Equipment '{$equipment_name}' borrowed by Student ID {$student_id} is overdue by {$days_overdue} day(s). Please follow up.";
    $ok = createNotification($conn, 'warning', $title, $message);
    if (function_exists('isEmailEnabled') && function_exists('logEmailEvent') && isEmailEnabled($conn)) {
        // Log that an email should be sent for audit purposes
        logEmailEvent($conn, null, 'Overdue Notice', $equipment_name, null, 'queued');
    }
    return $ok;
}

/**
 * Create notification for new borrow transaction
 */
function notifyNewBorrow($conn, $equipment_name, $student_id, $quantity = 1) {
    $title = "New Borrow Transaction";
    $message = "Student ID {$student_id} has borrowed {$quantity}x {$equipment_name}.";
    return createNotification($conn, 'info', $title, $message);
}

/**
 * Create notification for return verification
 */
function notifyReturnVerified($conn, $equipment_name, $student_id, $condition = 'Good') {
    $title = "Return Verified";
    $message = "Equipment '{$equipment_name}' returned by Student ID {$student_id}. Condition: {$condition}. Return verified successfully.";
    $ok = createNotification($conn, 'success', $title, $message);
    if (function_exists('isEmailEnabled') && function_exists('logEmailEvent') && isEmailEnabled($conn)) {
        logEmailEvent($conn, null, 'Return Verified', $equipment_name, null, 'queued');
    }
    return $ok;
}

/**
 * Create notification for penalty issued
 */
function notifyPenaltyIssued($conn, $student_id, $penalty_type, $amount) {
    $title = "Penalty Issued";
    $message = "Penalty issued to Student ID {$student_id}. Type: {$penalty_type}, Amount: ₱" . number_format($amount, 2) . ".";
    $ok = createNotification($conn, 'warning', $title, $message);
    if (function_exists('isEmailEnabled') && function_exists('logEmailEvent') && isEmailEnabled($conn)) {
        logEmailEvent($conn, null, 'Penalty Issued', null, null, 'queued');
    }
    return $ok;
}

/**
 * Create notification for damaged equipment
 */
function notifyDamagedEquipment($conn, $equipment_name, $student_id, $damage_description) {
    $title = "Damaged Equipment Reported";
    $message = "Equipment '{$equipment_name}' returned by Student ID {$student_id} with damage: {$damage_description}. Repair required.";
    $ok = createNotification($conn, 'error', $title, $message);
    if (function_exists('isEmailEnabled') && function_exists('logEmailEvent') && isEmailEnabled($conn)) {
        logEmailEvent($conn, null, 'Damage Reported', $equipment_name, null, 'queued');
    }
    return $ok;
}

/**
 * Create notification for lost equipment
 */
function notifyLostEquipment($conn, $equipment_name, $student_id) {
    $title = "Lost Equipment Reported";
    $message = "Equipment '{$equipment_name}' reported lost by Student ID {$student_id}. Replacement required.";
    $ok = createNotification($conn, 'error', $title, $message);
    if (function_exists('isEmailEnabled') && function_exists('logEmailEvent') && isEmailEnabled($conn)) {
        logEmailEvent($conn, null, 'Lost Equipment', $equipment_name, null, 'queued');
    }
    return $ok;
}

/**
 * Create notification for low stock
 */
function notifyLowStock($conn, $equipment_name, $current_stock, $min_stock) {
    $title = "Low Stock Alert";
    $message = "Equipment '{$equipment_name}' stock is low. Current: {$current_stock}, Minimum: {$min_stock}. Please restock.";
    return createNotification($conn, 'warning', $title, $message);
}

/**
 * Create notification for maintenance due
 */
function notifyMaintenanceDue($conn, $equipment_name, $equipment_id) {
    $title = "Maintenance Due";
    $message = "Equipment '{$equipment_name}' (ID: {$equipment_id}) is due for maintenance. Please schedule maintenance.";
    return createNotification($conn, 'warning', $title, $message);
}

/**
 * Create notification for transaction approval needed
 */
function notifyApprovalNeeded($conn, $transaction_id, $student_id, $equipment_name) {
    $title = "Transaction Approval Required";
    $message = "Transaction #{$transaction_id} from Student ID {$student_id} for '{$equipment_name}' requires approval.";
    $ok = createNotification($conn, 'info', $title, $message);
    if (function_exists('isEmailEnabled') && function_exists('logEmailEvent') && isEmailEnabled($conn)) {
        logEmailEvent($conn, null, 'Approval Needed', $equipment_name, $transaction_id, 'queued');
    }
    return $ok;
}

/**
 * Create notification for system error
 */
function notifySystemError($conn, $error_message, $context = '') {
    $title = "System Error";
    $message = "System error occurred: {$error_message}";
    if (!empty($context)) {
        $message .= " Context: {$context}";
    }
    return createNotification($conn, 'error', $title, $message);
}

/**
 * Create notification for bulk return
 */
function notifyBulkReturn($conn, $student_id, $item_count) {
    $title = "Bulk Return Processed";
    $message = "Student ID {$student_id} has returned {$item_count} item(s) successfully.";
    $ok = createNotification($conn, 'success', $title, $message);
    if (function_exists('isEmailEnabled') && function_exists('logEmailEvent') && isEmailEnabled($conn)) {
        logEmailEvent($conn, null, 'Bulk Return', null, null, 'queued');
    }
    return $ok;
}

/**
 * Create notification for equipment availability
 */
function notifyEquipmentAvailable($conn, $equipment_name) {
    $title = "Equipment Now Available";
    $message = "Equipment '{$equipment_name}' is now available for borrowing.";
    $ok = createNotification($conn, 'info', $title, $message);
    if (function_exists('isEmailEnabled') && function_exists('logEmailEvent') && isEmailEnabled($conn)) {
        logEmailEvent($conn, null, 'Equipment Available', $equipment_name, null, 'queued');
    }
    return $ok;
}

// --- Email helpers ---------------------------------------------------------

if (!function_exists('isEmailEnabled')) {
    function isEmailEnabled(mysqli $conn): bool {
        // Check a settings table if present; otherwise default to false
        $check = $conn->query("SHOW TABLES LIKE 'system_settings'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'email_enabled' LIMIT 1");
            if ($stmt) {
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                return !empty($row) && ($row['setting_value'] === '1' || strtolower($row['setting_value']) === 'true');
            }
        }
        return false;
    }
}

if (!function_exists('logEmailEvent')) {
    function logEmailEvent(mysqli $conn, ?int $user_id, string $email_type, ?string $equipment_name = null, ?int $transaction_id = null, string $status = 'queued'): bool {
        // Ensure table exists
        $check = $conn->query("SHOW TABLES LIKE 'email_logs'");
        if (!$check || $check->num_rows === 0) {
            return false;
        }

        $sql = "INSERT INTO email_logs (user_id, email_type, equipment_name, transaction_id, sent_at, status) VALUES (?, ?, ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return false;
        $uid = $user_id ?: null;
        $tid = $transaction_id ?: null;
        $stmt->bind_param('issis', $uid, $email_type, $equipment_name, $tid, $status);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('sendSystemEmail')) {
    function sendSystemEmail(mysqli $conn, int $user_id, string $email_type, string $subject, string $body, ?string $toAddress = null, ?string $equipment_name = null, ?int $transaction_id = null): bool {
        if (!isEmailEnabled($conn)) {
            return false;
        }
        // If no explicit address provided, try to resolve from users table
        if (!$toAddress) {
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                $toAddress = $row['email'] ?? null;
            }
        }
        // Attempt to send via PHP mail() if configured; otherwise just log as queued
        $sent = false;
        if ($toAddress) {
            // Note: mail() depends on local SMTP configuration
            $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
            $sent = @mail($toAddress, $subject, $body, $headers) === true;
        }
        logEmailEvent($conn, $user_id, $email_type, $equipment_name, $transaction_id, $sent ? 'sent' : 'queued');
        return $sent;
    }
}
?>
