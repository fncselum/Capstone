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
    return createNotification($conn, 'warning', $title, $message);
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
    return createNotification($conn, 'success', $title, $message);
}

/**
 * Create notification for penalty issued
 */
function notifyPenaltyIssued($conn, $student_id, $penalty_type, $amount) {
    $title = "Penalty Issued";
    $message = "Penalty issued to Student ID {$student_id}. Type: {$penalty_type}, Amount: ₱" . number_format($amount, 2) . ".";
    return createNotification($conn, 'warning', $title, $message);
}

/**
 * Create notification for damaged equipment
 */
function notifyDamagedEquipment($conn, $equipment_name, $student_id, $damage_description) {
    $title = "Damaged Equipment Reported";
    $message = "Equipment '{$equipment_name}' returned by Student ID {$student_id} with damage: {$damage_description}. Repair required.";
    return createNotification($conn, 'error', $title, $message);
}

/**
 * Create notification for lost equipment
 */
function notifyLostEquipment($conn, $equipment_name, $student_id) {
    $title = "Lost Equipment Reported";
    $message = "Equipment '{$equipment_name}' reported lost by Student ID {$student_id}. Replacement required.";
    return createNotification($conn, 'error', $title, $message);
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
    return createNotification($conn, 'info', $title, $message);
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
    return createNotification($conn, 'success', $title, $message);
}

/**
 * Create notification for equipment availability
 */
function notifyEquipmentAvailable($conn, $equipment_name) {
    $title = "Equipment Now Available";
    $message = "Equipment '{$equipment_name}' is now available for borrowing.";
    return createNotification($conn, 'info', $title, $message);
}
?>
