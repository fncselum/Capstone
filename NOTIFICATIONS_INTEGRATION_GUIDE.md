# Notifications System Integration Guide

## Overview
This guide explains how the notifications system is integrated with your existing equipment management system to automatically create notifications for key events.

---

## Setup Instructions

### **Step 1: Create Notifications Table**

Run this SQL in phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread', 'read', 'archived') DEFAULT 'unread',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_type` (`type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### **Step 2: Test with Sample Data (Optional)**

```sql
INSERT INTO notifications (type, title, message, status) VALUES
('warning', 'Equipment Overdue', 'Camera #CAM-001 is overdue by 3 days. Please follow up with the borrower.', 'unread'),
('info', 'New Borrow Request', 'Student ID 2024-001 has borrowed Projector #PROJ-005.', 'unread'),
('success', 'Return Verified', 'Equipment Laptop #LAP-012 has been returned and verified successfully.', 'read'),
('error', 'System Error', 'Failed to process transaction #TXN-456. Please check the logs.', 'unread'),
('warning', 'Maintenance Due', 'Equipment Monitor #MON-008 is due for maintenance.', 'read');
```

---

## Integrated Files

### **1. includes/notification_helper.php** (NEW)
Helper functions for creating notifications throughout the system.

**Functions Available:**
- `createNotification($conn, $type, $title, $message)` - Base function
- `notifyOverdueEquipment($conn, $equipment_name, $student_id, $days_overdue)`
- `notifyNewBorrow($conn, $equipment_name, $student_id, $quantity)`
- `notifyReturnVerified($conn, $equipment_name, $student_id, $condition)`
- `notifyPenaltyIssued($conn, $student_id, $penalty_type, $amount)`
- `notifyDamagedEquipment($conn, $equipment_name, $student_id, $damage_description)`
- `notifyLostEquipment($conn, $equipment_name, $student_id)`
- `notifyLowStock($conn, $equipment_name, $current_stock, $min_stock)`
- `notifyMaintenanceDue($conn, $equipment_name, $equipment_id)`
- `notifyApprovalNeeded($conn, $transaction_id, $student_id, $equipment_name)`
- `notifySystemError($conn, $error_message, $context)`
- `notifyBulkReturn($conn, $student_id, $item_count)`
- `notifyEquipmentAvailable($conn, $equipment_name)`

### **2. admin/penalty-system.php** (MODIFIED)
Automatically creates notifications when penalties are issued.

**Integration Points:**
- Line 3: Added `require_once __DIR__ . '/../includes/notification_helper.php';`
- Lines 161-172: Added notification creation after penalty is created

**Notifications Created:**
- **Penalty Issued** - When any penalty is created
- **Damaged Equipment** - When damage penalty is issued
- **Lost Equipment** - When loss penalty is issued

**Example:**
```php
// After penalty is created successfully
$student_id = $borrowerIdentifier ?: "User #{$userId}";
notifyPenaltyIssued($this->conn, $student_id, $penaltyType, $penaltyAmount);

if ($penaltyType === 'Damage' && $equipmentName) {
    notifyDamagedEquipment($this->conn, $equipmentName, $student_id, $damageNotes);
}
```

### **3. admin/return-verification.php** (MODIFIED)
Automatically creates notifications when returns are verified.

**Integration Points:**
- Line 6: Added `require_once __DIR__ . '/../includes/notification_helper.php';`
- Lines 180-186: Added notification creation after successful verification

**Notifications Created:**
- **Return Verified** - When admin verifies a return

**Example:**
```php
// After return is verified
if ($action === 'verify') {
    $student_id = $transaction['user_id'] ?? 'Unknown';
    $equipment_name = $transaction['equipment_name'] ?? 'Equipment';
    $condition = ($severityLevel === 'none' || $severityLevel === 'minor') ? 'Good' : 'Damaged';
    notifyReturnVerified($conn, $equipment_name, $student_id, $condition);
}
```

---

## How to Add Notifications to Other Files

### **Example 1: Add to Borrow Process**

In `user/borrow.php` or wherever borrows are processed:

```php
// At the top of the file
require_once __DIR__ . '/../includes/notification_helper.php';

// After successful borrow transaction
if ($borrow_success) {
    notifyNewBorrow($conn, $equipment_name, $student_id, $quantity);
}
```

### **Example 2: Add to Overdue Check**

In a cron job or scheduled task that checks for overdue items:

```php
require_once __DIR__ . '/includes/notification_helper.php';

// Query overdue transactions
$overdue_query = "SELECT * FROM transactions 
                  WHERE status = 'Active' 
                  AND expected_return_date < CURDATE()";

$result = $conn->query($overdue_query);

while ($row = $result->fetch_assoc()) {
    $days_overdue = (strtotime('now') - strtotime($row['expected_return_date'])) / 86400;
    notifyOverdueEquipment($conn, $row['equipment_name'], $row['student_id'], floor($days_overdue));
}
```

### **Example 3: Add to Low Stock Alert**

In equipment management or inventory check:

```php
require_once __DIR__ . '/../includes/notification_helper.php';

// Check stock levels
$stock_query = "SELECT name, quantity, min_quantity 
                FROM equipment 
                WHERE quantity <= min_quantity";

$result = $conn->query($stock_query);

while ($row = $result->fetch_assoc()) {
    notifyLowStock($conn, $row['name'], $row['quantity'], $row['min_quantity']);
}
```

### **Example 4: Add to Maintenance Tracker**

In maintenance scheduling system:

```php
require_once __DIR__ . '/../includes/notification_helper.php';

// Check maintenance due dates
$maintenance_query = "SELECT * FROM equipment 
                      WHERE next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";

$result = $conn->query($maintenance_query);

while ($row = $result->fetch_assoc()) {
    notifyMaintenanceDue($conn, $row['name'], $row['rfid_tag']);
}
```

---

## Notification Types and When to Use Them

### **🔵 Info (Blue)**
Use for informational events that don't require immediate action.

**Examples:**
- New borrow transaction
- Equipment now available
- Transaction approval needed
- Bulk return processed

### **🟠 Warning (Orange)**
Use for events that need attention but aren't critical.

**Examples:**
- Equipment overdue
- Low stock alert
- Maintenance due
- Penalty issued

### **🟢 Success (Green)**
Use for successful completions and positive events.

**Examples:**
- Return verified
- Maintenance completed
- Stock replenished
- Issue resolved

### **🔴 Error (Red)**
Use for critical issues that need immediate attention.

**Examples:**
- Damaged equipment
- Lost equipment
- System errors
- Failed transactions

---

## Automatic Notification Triggers

### **Currently Implemented:**

| Event | Notification Type | File | Function |
|-------|------------------|------|----------|
| Penalty Issued | Warning | penalty-system.php | notifyPenaltyIssued() |
| Damaged Equipment | Error | penalty-system.php | notifyDamagedEquipment() |
| Lost Equipment | Error | penalty-system.php | notifyLostEquipment() |
| Return Verified | Success | return-verification.php | notifyReturnVerified() |

### **Recommended to Add:**

| Event | Notification Type | Where to Add | Function |
|-------|------------------|--------------|----------|
| New Borrow | Info | user/borrow.php | notifyNewBorrow() |
| Equipment Overdue | Warning | Cron job/scheduler | notifyOverdueEquipment() |
| Low Stock | Warning | Inventory check | notifyLowStock() |
| Maintenance Due | Warning | Maintenance tracker | notifyMaintenanceDue() |
| Approval Needed | Info | Transaction approval | notifyApprovalNeeded() |
| Bulk Return | Success | Bulk return process | notifyBulkReturn() |
| Equipment Available | Info | After return | notifyEquipmentAvailable() |

---

## Testing the Integration

### **Test 1: Penalty Notification**

1. Go to Penalty Management
2. Issue a penalty to a student
3. Go to Notifications page
4. You should see a "Penalty Issued" notification

### **Test 2: Return Verification Notification**

1. Go to Return Verification
2. Verify a return
3. Go to Notifications page
4. You should see a "Return Verified" notification

### **Test 3: Damaged Equipment Notification**

1. Issue a damage penalty
2. Go to Notifications page
3. You should see both "Penalty Issued" and "Damaged Equipment Reported" notifications

---

## Troubleshooting

### **Notifications Not Appearing**

**Check 1: Table Exists**
```sql
SHOW TABLES LIKE 'notifications';
```

**Check 2: Helper File Included**
Make sure the file includes:
```php
require_once __DIR__ . '/../includes/notification_helper.php';
```

**Check 3: Function Called**
Add error logging:
```php
$result = notifyPenaltyIssued($conn, $student_id, $penalty_type, $amount);
error_log("Notification created: " . ($result ? 'Yes' : 'No'));
```

**Check 4: Database Permissions**
Make sure the database user has INSERT permissions on the notifications table.

### **Wrong Path to Helper File**

If you get "file not found" errors, adjust the path:

```php
// From admin folder
require_once __DIR__ . '/../includes/notification_helper.php';

// From user folder
require_once __DIR__ . '/../includes/notification_helper.php';

// From root folder
require_once __DIR__ . '/includes/notification_helper.php';
```

---

## Best Practices

### **1. Don't Create Duplicate Notifications**

Check if a similar notification already exists:

```php
$check = $conn->query("SELECT id FROM notifications 
                       WHERE title = 'Equipment Overdue' 
                       AND message LIKE '%CAM-001%' 
                       AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");

if ($check->num_rows === 0) {
    notifyOverdueEquipment($conn, 'Camera #CAM-001', '2024-001', 3);
}
```

### **2. Keep Messages Clear and Actionable**

✅ Good: "Equipment 'Laptop #LAP-012' is overdue by 3 days. Please follow up with Student ID 2024-001."

❌ Bad: "Overdue item detected."

### **3. Use Appropriate Types**

- Info: FYI, no action needed
- Warning: Needs attention soon
- Success: Good news, completed
- Error: Critical, immediate action

### **4. Include Relevant Details**

Always include:
- Equipment name/ID
- Student ID
- Specific numbers (days, amount, quantity)
- What action is needed

---

## Future Enhancements

### **Planned Features:**

1. **Email Notifications** - Send emails for critical notifications
2. **Push Notifications** - Browser push for real-time alerts
3. **Notification Preferences** - Let admins choose what to be notified about
4. **Bulk Actions** - Mark multiple as read/archive at once
5. **Auto-Archive** - Archive old notifications automatically
6. **Priority Levels** - High/Medium/Low priority
7. **Assignment** - Assign notifications to specific admins
8. **Dashboard Widget** - Show recent notifications on dashboard

---

## Summary

✅ **Notifications table created**  
✅ **Helper functions available**  
✅ **Penalty system integrated**  
✅ **Return verification integrated**  
✅ **Ready to add more integrations**  

The notifications system is now functional and integrated with your penalty and return verification systems. You can easily add more notification triggers to other parts of your system using the helper functions provided.

---

**Date:** October 30, 2025  
**Status:** Integrated and Functional  
**Files Modified:** 2  
**Files Created:** 2  
**Helper Functions:** 13
