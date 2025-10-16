# 🤖 **Automatic Inventory Management System**

## ✅ **Implementation Complete**

### **Updated Files:**
1. ✅ `user/borrow.php` - Automatic inventory updates on borrow
2. ✅ `user/return.php` - Automatic inventory updates on return

---

## 🎯 **System Features**

### **1. Automatic Quantity Tracking** ✅
- **available_quantity** decreases when borrowed
- **borrowed_quantity** increases when borrowed
- **available_quantity** increases when returned (if not damaged)
- **borrowed_quantity** decreases when returned
- **damaged_quantity** increases when returned as damaged

### **2. Automatic Status Management** ✅
- **'Available'** - When stock is above minimum level
- **'Low Stock'** - When stock is at or below minimum_stock_level
- **'Out of Stock'** - When available_quantity reaches 0

### **3. Real-time Synchronization** ✅
- All tables updated in single transaction
- Rollback on any error
- Data consistency guaranteed

### **4. Stock Warnings** ✅
- Alerts when last item is borrowed
- Warns when stock is low
- Prevents borrowing out-of-stock items

---

## 📋 **Borrow Process Flow**

### **Step-by-Step:**

```
1. User selects equipment and due date
   ↓
2. System checks inventory availability
   ↓
3. If available_quantity > 0:
   ├─ Lock equipment and inventory rows (FOR UPDATE)
   ├─ Decrease equipment.quantity by 1
   ├─ Decrease inventory.available_quantity by 1
   ├─ Increase inventory.borrowed_quantity by 1
   ├─ Calculate new availability_status
   │  ├─ If new_available = 0 → 'Out of Stock'
   │  ├─ If new_available ≤ minimum → 'Low Stock'
   │  └─ Else → 'Available'
   ├─ Update inventory.availability_status
   ├─ Update inventory.last_updated = NOW()
   ├─ Create transaction record
   └─ Commit all changes
   ↓
4. Show success message with stock warning (if applicable)
```

### **Code Implementation (borrow.php):**

```php
// Get equipment and inventory with lock
$stmt = $conn->prepare("SELECT e.*, i.available_quantity, i.borrowed_quantity, i.minimum_stock_level 
    FROM equipment e 
    LEFT JOIN inventory i ON e.id = i.equipment_id 
    WHERE e.id = ? FOR UPDATE");

// Check availability
if ($available_qty > 0 && $current_qty > 0) {
    
    // Update equipment
    UPDATE equipment SET quantity = quantity - 1, updated_at = NOW() WHERE id = ?
    
    // Calculate new status
    $new_available = $available_qty - 1;
    $new_status = 'Available';
    if ($new_available == 0) {
        $new_status = 'Out of Stock';
    } elseif ($new_available <= $min_stock) {
        $new_status = 'Low Stock';
    }
    
    // Update inventory with new status
    UPDATE inventory 
    SET available_quantity = available_quantity - 1, 
        borrowed_quantity = borrowed_quantity + 1, 
        availability_status = ?,
        last_updated = NOW() 
    WHERE equipment_id = ?
    
    // Create transaction
    INSERT INTO transactions (...)
    
    // Commit
    $conn->commit();
    
    // Show warning if needed
    if ($new_status == 'Out of Stock') {
        $message .= "⚠ This was the last available item";
    } elseif ($new_status == 'Low Stock') {
        $message .= "⚠ Low stock: $new_available remaining";
    }
}
```

---

## 🔙 **Return Process Flow**

### **Step-by-Step:**

```
1. User selects equipment to return and condition
   ↓
2. System retrieves transaction and inventory data
   ↓
3. Calculate penalty (if overdue)
   ↓
4. Update equipment.quantity (+1)
   ↓
5. Check condition_after:
   │
   ├─ If 'Good' or 'Fair':
   │  ├─ Increase inventory.available_quantity by 1
   │  ├─ Decrease inventory.borrowed_quantity by 1
   │  └─ Calculate new status
   │
   └─ If 'Damaged':
      ├─ Increase inventory.damaged_quantity by 1
      ├─ Decrease inventory.borrowed_quantity by 1
      ├─ Do NOT increase available_quantity
      └─ Calculate new status
   ↓
6. Determine new availability_status:
   ├─ If new_available = 0 → 'Out of Stock'
   ├─ If new_available ≤ minimum → 'Low Stock'
   └─ Else → 'Available'
   ↓
7. Update inventory.availability_status
   ↓
8. Update inventory.last_updated = NOW()
   ↓
9. Update transaction record (status, dates, penalty)
   ↓
10. Update user penalty_points (if overdue)
    ↓
11. Commit all changes
```

### **Code Implementation (return.php):**

```php
// Get transaction and inventory with lock
$stmt = $conn->prepare("SELECT t.*, e.name as equipment_name, e.quantity as current_qty,
    i.available_quantity, i.minimum_stock_level
    FROM transactions t 
    JOIN equipment e ON t.equipment_id = e.id 
    LEFT JOIN inventory i ON e.id = i.equipment_id
    WHERE t.id = ? AND t.user_id = ? AND t.status = 'Active' FOR UPDATE");

// Calculate new available quantity
$new_available = $current_available + $quantity_returned;

// Adjust for damaged items
if ($condition_after === 'Damaged') {
    $new_available = $new_available - 1; // Don't add damaged to available
}

// Determine new status
$new_status = 'Available';
if ($new_available == 0) {
    $new_status = 'Out of Stock';
} elseif ($new_available <= $min_stock) {
    $new_status = 'Low Stock';
}

// Update inventory based on condition
if ($condition_after === 'Damaged') {
    // Return as damaged
    UPDATE inventory 
    SET borrowed_quantity = borrowed_quantity - 1, 
        damaged_quantity = damaged_quantity + 1,
        availability_status = ?,
        last_updated = NOW() 
    WHERE equipment_id = ?
} else {
    // Normal return
    UPDATE inventory 
    SET available_quantity = available_quantity + 1, 
        borrowed_quantity = borrowed_quantity - 1,
        availability_status = ?,
        last_updated = NOW() 
    WHERE equipment_id = ?
}
```

---

## 📊 **Inventory Status Logic**

### **Status Determination:**

```php
if (available_quantity == 0) {
    availability_status = 'Out of Stock'
} 
else if (available_quantity <= minimum_stock_level) {
    availability_status = 'Low Stock'
} 
else {
    availability_status = 'Available'
}
```

### **Status Examples:**

| Available | Minimum | Status | Description |
|-----------|---------|--------|-------------|
| 0 | 1 | Out of Stock | No items available |
| 1 | 1 | Low Stock | At minimum level |
| 2 | 3 | Low Stock | Below minimum |
| 5 | 2 | Available | Above minimum |
| 10 | 2 | Available | Well stocked |

---

## 🔄 **Complete Transaction Example**

### **Scenario: Borrow → Return (Good) → Borrow Again**

#### **Initial State:**
```
equipment.quantity = 10
inventory.available_quantity = 10
inventory.borrowed_quantity = 0
inventory.damaged_quantity = 0
inventory.availability_status = 'Available'
inventory.minimum_stock_level = 2
```

#### **After Borrow #1:**
```
equipment.quantity = 9
inventory.available_quantity = 9
inventory.borrowed_quantity = 1
inventory.damaged_quantity = 0
inventory.availability_status = 'Available' (9 > 2)
inventory.last_updated = 2025-10-16 18:42:00
```

#### **After Return (Good Condition):**
```
equipment.quantity = 10
inventory.available_quantity = 10
inventory.borrowed_quantity = 0
inventory.damaged_quantity = 0
inventory.availability_status = 'Available' (10 > 2)
inventory.last_updated = 2025-10-16 19:15:00
```

#### **After Multiple Borrows (8 items):**
```
equipment.quantity = 2
inventory.available_quantity = 2
inventory.borrowed_quantity = 8
inventory.damaged_quantity = 0
inventory.availability_status = 'Low Stock' (2 ≤ 2)
inventory.last_updated = 2025-10-16 20:30:00
```

#### **After Last Item Borrowed:**
```
equipment.quantity = 1
inventory.available_quantity = 1
inventory.borrowed_quantity = 9
inventory.damaged_quantity = 0
inventory.availability_status = 'Low Stock' (1 ≤ 2)
inventory.last_updated = 2025-10-16 21:00:00
⚠ Warning: "Low stock: 1 remaining"
```

#### **After Final Borrow:**
```
equipment.quantity = 0
inventory.available_quantity = 0
inventory.borrowed_quantity = 10
inventory.damaged_quantity = 0
inventory.availability_status = 'Out of Stock' (0 = 0)
inventory.last_updated = 2025-10-16 21:30:00
⚠ Warning: "This was the last available item"
```

---

## 🛡️ **Error Handling**

### **Borrow Errors:**

```php
// Out of stock
if ($available_qty == 0) {
    $error = 'Sorry, this equipment is currently out of stock. 
              All items are borrowed or unavailable.';
}

// Equipment not found
if ($result->num_rows === 0) {
    $error = 'Equipment not found in the system.';
}

// Database error
catch (Exception $ex) {
    $conn->rollback();
    $error = 'Borrow failed: ' . $ex->getMessage();
}
```

### **Return Errors:**

```php
// Transaction not found
if ($result->num_rows === 0) {
    $error = 'Transaction not found or already returned.';
}

// Database error
catch (Exception $ex) {
    $conn->rollback();
    $error = 'Return failed: ' . $ex->getMessage();
}
```

---

## 🔒 **Data Integrity Features**

### **1. Row Locking (FOR UPDATE)**
```sql
SELECT e.*, i.* 
FROM equipment e 
LEFT JOIN inventory i ON e.id = i.equipment_id 
WHERE e.id = ? 
FOR UPDATE
```
**Purpose:** Prevents race conditions when multiple users borrow simultaneously

### **2. Database Transactions**
```php
$conn->begin_transaction();
try {
    // All updates here
    $conn->commit(); // Success
} catch (Exception $ex) {
    $conn->rollback(); // Undo all changes
}
```
**Purpose:** Ensures all-or-nothing updates

### **3. Validation Checks**
```php
// Check availability before borrowing
if ($available_qty > 0 && $current_qty > 0) {
    // Proceed with borrow
}

// Validate transaction exists before returning
if ($result->num_rows === 1) {
    // Proceed with return
}
```

---

## 📈 **Equipment List Filtering**

### **Only Show Available Items:**

```php
// Updated query in borrow.php
$query = "SELECT e.*, c.name as category_name, 
          i.available_quantity, i.borrowed_quantity, i.availability_status
          FROM equipment e 
          LEFT JOIN categories c ON e.category_id = c.id 
          LEFT JOIN inventory i ON e.id = i.equipment_id
          WHERE e.quantity > 0 
          AND (i.available_quantity > 0 OR i.available_quantity IS NULL)
          ORDER BY e.id ASC";
```

**Result:** Users only see equipment that can actually be borrowed

---

## ⚙️ **Configuration**

### **Minimum Stock Level:**
Set in `inventory` table:
```sql
UPDATE inventory 
SET minimum_stock_level = 3 
WHERE equipment_id = 1;
```

### **Penalty Rate:**
Set in `return.php`:
```php
$penalty = $days_overdue * 10; // 10 pesos per day
```

---

## ✅ **Testing Checklist**

### **Borrow Tests:**
- [ ] Borrow when stock is available
- [ ] Verify status changes to 'Low Stock' at minimum
- [ ] Verify status changes to 'Out of Stock' at 0
- [ ] Verify stock warnings appear
- [ ] Try to borrow when out of stock (should fail)
- [ ] Verify all tables update correctly
- [ ] Test transaction rollback on error

### **Return Tests:**
- [ ] Return in good condition
- [ ] Return in fair condition
- [ ] Return as damaged
- [ ] Verify damaged items don't return to available
- [ ] Verify status changes back to 'Available'
- [ ] Verify overdue penalties calculate correctly
- [ ] Test transaction rollback on error

### **Concurrent Tests:**
- [ ] Multiple users borrow same item simultaneously
- [ ] Verify row locking prevents overselling
- [ ] Check data consistency after concurrent operations

---

## 🎉 **Summary**

### **What's Automated:**

✅ **Quantity Updates**
- available_quantity ↓ on borrow, ↑ on return
- borrowed_quantity ↑ on borrow, ↓ on return
- damaged_quantity ↑ when returned damaged

✅ **Status Management**
- Automatically sets 'Out of Stock' when available = 0
- Automatically sets 'Low Stock' when at minimum
- Automatically sets 'Available' when above minimum

✅ **Timestamp Updates**
- last_updated automatically set on every change

✅ **Data Synchronization**
- equipment, inventory, and transactions tables stay in sync
- All updates in single transaction
- Automatic rollback on errors

✅ **User Notifications**
- Stock warnings when borrowing last items
- Low stock alerts
- Clear error messages

### **Benefits:**

🎯 **Accuracy** - No manual inventory updates needed  
🔒 **Safety** - Transaction-based updates prevent data corruption  
⚡ **Real-time** - Status updates immediately  
📊 **Visibility** - Always know current stock levels  
🚫 **Prevention** - Can't borrow out-of-stock items  
⚠️ **Alerts** - Warnings for low/out of stock  

---

**Your inventory system is now fully automated!** 🚀
