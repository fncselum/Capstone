# 📦 **Inventory Database Integration**

## ✅ **Updated Files**

### **1. borrow.php** - Borrow Equipment Integration
### **2. return.php** - Return Equipment Integration

---

## 🗄️ **Database Structure**

### **Inventory Table Schema:**
```sql
inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT,
    quantity INT,
    available_quantity INT,
    borrowed_quantity INT,
    damaged_quantity INT,
    item_condition VARCHAR(50),
    availability_status VARCHAR(50),
    minimum_stock_level INT,
    location VARCHAR(255),
    notes TEXT,
    last_updated DATETIME,
    created_at DATETIME
)
```

### **Equipment Table Schema:**
```sql
equipment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    quantity INT,
    item_condition VARCHAR(50),
    ...
)
```

### **Transactions Table Schema:**
```sql
transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    equipment_id INT,
    transaction_type VARCHAR(50),
    quantity INT,
    condition_before VARCHAR(50),
    condition_after VARCHAR(50),
    status VARCHAR(50),
    penalty_applied INT,
    ...
)
```

---

## 🔄 **Borrow Process Flow**

### **When a student borrows equipment:**

1. **Equipment Table Update:**
   ```sql
   UPDATE equipment 
   SET quantity = quantity - 1, 
       updated_at = NOW() 
   WHERE id = ?
   ```

2. **Inventory Table Update:**
   ```sql
   UPDATE inventory 
   SET available_quantity = available_quantity - 1,
       borrowed_quantity = borrowed_quantity + 1,
       last_updated = NOW()
   WHERE equipment_id = ?
   ```

3. **Transaction Record Created:**
   ```sql
   INSERT INTO transactions 
   (user_id, equipment_id, transaction_type, quantity, 
    transaction_date, expected_return_date, condition_before, 
    status, penalty_applied, notes, created_at, updated_at)
   VALUES (?, ?, 'Borrow', 1, NOW(), ?, ?, 'Active', 0, ?, NOW(), NOW())
   ```

### **Code Implementation (borrow.php):**
```php
// Update equipment quantity
$update_stmt = $conn->prepare("UPDATE equipment SET quantity = ?, updated_at = NOW() WHERE id = ?");
$update_stmt->bind_param("ii", $new_qty, $equipment_id);

if (!$update_stmt->execute()) {
    throw new Exception("Failed to update equipment quantity");
}

// Update inventory table - decrease available_quantity, increase borrowed_quantity
$inv_stmt = $conn->prepare("UPDATE inventory SET available_quantity = available_quantity - 1, borrowed_quantity = borrowed_quantity + 1, last_updated = NOW() WHERE equipment_id = ?");
$inv_stmt->bind_param("i", $equipment_id);

if (!$inv_stmt->execute()) {
    throw new Exception("Failed to update inventory");
}
$inv_stmt->close();
```

---

## 🔙 **Return Process Flow**

### **When a student returns equipment:**

1. **Equipment Table Update:**
   ```sql
   UPDATE equipment 
   SET quantity = quantity + 1, 
       updated_at = NOW() 
   WHERE id = ?
   ```

2. **Inventory Table Update (Normal Return):**
   ```sql
   UPDATE inventory 
   SET available_quantity = available_quantity + 1,
       borrowed_quantity = borrowed_quantity - 1,
       last_updated = NOW()
   WHERE equipment_id = ?
   ```

3. **Inventory Table Update (Damaged Return):**
   ```sql
   -- First: Return the item (increase available, decrease borrowed)
   UPDATE inventory 
   SET available_quantity = available_quantity + 1,
       borrowed_quantity = borrowed_quantity - 1,
       last_updated = NOW()
   WHERE equipment_id = ?
   
   -- Then: Mark as damaged (increase damaged, decrease available)
   UPDATE inventory 
   SET damaged_quantity = damaged_quantity + 1,
       available_quantity = available_quantity - 1
   WHERE equipment_id = ?
   ```

4. **Transaction Record Updated:**
   ```sql
   UPDATE transactions 
   SET actual_return_date = ?,
       condition_after = ?,
       status = 'Returned',
       penalty_applied = ?,
       notes = ?,
       updated_at = NOW()
   WHERE id = ?
   ```

### **Code Implementation (return.php):**
```php
// Update equipment quantity
$update_equip = $conn->prepare("UPDATE equipment SET quantity = ?, updated_at = NOW() WHERE id = ?");
$update_equip->bind_param("ii", $new_qty, $equipment_id);

if (!$update_equip->execute()) {
    throw new Exception("Failed to update equipment quantity");
}

// Update inventory table - increase available_quantity, decrease borrowed_quantity
$inv_stmt = $conn->prepare("UPDATE inventory SET available_quantity = available_quantity + ?, borrowed_quantity = borrowed_quantity - ?, last_updated = NOW() WHERE equipment_id = ?");
$inv_stmt->bind_param("iii", $quantity_returned, $quantity_returned, $equipment_id);

if (!$inv_stmt->execute()) {
    throw new Exception("Failed to update inventory");
}

// Handle damaged equipment if condition is not Good
if ($condition_after === 'Damaged') {
    $dmg_stmt = $conn->prepare("UPDATE inventory SET damaged_quantity = damaged_quantity + 1, available_quantity = available_quantity - 1 WHERE equipment_id = ?");
    $dmg_stmt->bind_param("i", $equipment_id);
    $dmg_stmt->execute();
    $dmg_stmt->close();
}

$inv_stmt->close();
```

---

## 📊 **Inventory Tracking Logic**

### **Quantity Relationships:**
```
Total Quantity = Available + Borrowed + Damaged

equipment.quantity = inventory.available_quantity
```

### **Example Scenario:**

**Initial State:**
- Total: 10 items
- Available: 10
- Borrowed: 0
- Damaged: 0

**After 3 Borrows:**
- Total: 10 items
- Available: 7
- Borrowed: 3
- Damaged: 0

**After 1 Return (Good Condition):**
- Total: 10 items
- Available: 8
- Borrowed: 2
- Damaged: 0

**After 1 Return (Damaged):**
- Total: 10 items
- Available: 8
- Borrowed: 1
- Damaged: 1

---

## 🎯 **Condition Handling**

### **Equipment Conditions:**
1. **Good** - No damage, fully functional
2. **Fair** - Minor wear, still functional
3. **Damaged** - Needs repair, not available for borrowing

### **Return Condition Logic:**

```php
// In return.php modal
<select name="condition_after" id="condition_after" required>
    <option value="Good">Good - No damage</option>
    <option value="Fair">Fair - Minor wear</option>
    <option value="Damaged">Damaged - Needs repair</option>
</select>
```

**When condition_after = 'Damaged':**
- Item is returned to inventory (borrowed_quantity decreases)
- Item is marked as damaged (damaged_quantity increases)
- Item is removed from available pool (available_quantity decreases)
- Equipment cannot be borrowed until repaired

---

## 🔒 **Transaction Safety**

### **Using Database Transactions:**

Both `borrow.php` and `return.php` use MySQL transactions to ensure data integrity:

```php
$conn->begin_transaction();
try {
    // 1. Lock equipment row
    // 2. Update equipment table
    // 3. Update inventory table
    // 4. Insert/Update transaction record
    // 5. Update user penalties (if applicable)
    
    $conn->commit(); // All or nothing
} catch (Exception $ex) {
    $conn->rollback(); // Undo all changes on error
    $error = 'Operation failed: ' . $ex->getMessage();
}
```

### **Row Locking:**
```sql
SELECT * FROM equipment WHERE id = ? FOR UPDATE
```
This prevents race conditions when multiple users try to borrow the same item simultaneously.

---

## 📈 **Inventory Status Updates**

### **Automatic Status Management:**

The `availability_status` field in the inventory table should be updated based on available quantity:

```sql
-- When available_quantity = 0
UPDATE inventory SET availability_status = 'Out of Stock' WHERE available_quantity = 0

-- When available_quantity > 0 AND available_quantity <= minimum_stock_level
UPDATE inventory SET availability_status = 'Low Stock' WHERE available_quantity > 0 AND available_quantity <= minimum_stock_level

-- When available_quantity > minimum_stock_level
UPDATE inventory SET availability_status = 'Available' WHERE available_quantity > minimum_stock_level
```

**Note:** This can be implemented as a trigger or updated in the PHP code.

---

## 🔍 **Inventory Queries**

### **Check Equipment Availability:**
```sql
SELECT e.*, i.available_quantity, i.borrowed_quantity, i.damaged_quantity, i.availability_status
FROM equipment e
LEFT JOIN inventory i ON e.id = i.equipment_id
WHERE e.id = ?
```

### **Get All Available Equipment:**
```sql
SELECT e.*, i.available_quantity
FROM equipment e
LEFT JOIN inventory i ON e.id = i.equipment_id
WHERE i.available_quantity > 0 AND i.availability_status = 'Available'
ORDER BY e.name ASC
```

### **Get Borrowed Items by User:**
```sql
SELECT t.*, e.name as equipment_name, i.borrowed_quantity
FROM transactions t
JOIN equipment e ON t.equipment_id = e.id
LEFT JOIN inventory i ON e.id = i.equipment_id
WHERE t.user_id = ? AND t.status = 'Active' AND t.transaction_type = 'Borrow'
ORDER BY t.expected_return_date ASC
```

---

## ⚠️ **Important Notes**

### **1. Data Consistency:**
- Always update both `equipment.quantity` and `inventory.available_quantity` together
- Use transactions to prevent partial updates
- Validate quantities before updates (no negative values)

### **2. Damaged Equipment:**
- When equipment is returned as damaged, it's removed from available pool
- Admin must repair and manually update inventory to make it available again
- Damaged items should not appear in borrow list

### **3. Minimum Stock Levels:**
- Set `minimum_stock_level` in inventory table
- System should alert when `available_quantity <= minimum_stock_level`
- Prevents complete depletion of equipment

### **4. Synchronization:**
```php
// Always keep these in sync:
equipment.quantity = inventory.available_quantity

// Total inventory:
inventory.quantity = inventory.available_quantity + inventory.borrowed_quantity + inventory.damaged_quantity
```

---

## ✅ **Testing Checklist**

- [ ] Borrow equipment - verify both tables update
- [ ] Return equipment (Good) - verify quantities restore correctly
- [ ] Return equipment (Damaged) - verify damaged_quantity increases
- [ ] Multiple borrows - verify borrowed_quantity tracks correctly
- [ ] Check transaction rollback on error
- [ ] Verify no negative quantities possible
- [ ] Test concurrent borrows (race conditions)
- [ ] Verify inventory status updates

---

## 🎉 **Summary**

**Files Updated:**
1. ✅ `user/borrow.php` - Now updates inventory table on borrow
2. ✅ `user/return.php` - Now updates inventory table on return with damage tracking

**Database Tables Integrated:**
1. ✅ `equipment` - Main equipment data
2. ✅ `inventory` - Detailed quantity tracking
3. ✅ `transactions` - Borrow/return records

**Features Implemented:**
- ✅ Dual-table quantity updates
- ✅ Borrowed quantity tracking
- ✅ Damaged equipment handling
- ✅ Transaction safety with rollback
- ✅ Automatic timestamp updates

**Your inventory system is now fully integrated!** 🎊
