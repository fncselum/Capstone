# 🔍 **Transaction Table Not Showing - Troubleshooting Guide**

## 🎯 **Quick Fix Steps**

### **Step 1: Check the Debug Info**
1. Open `admin-all-transaction.php` in your browser
2. If you see "No transactions found", click **"Show Debug Info"**
3. This will tell you:
   - If users table exists
   - The exact query being run
   - Any error messages

---

## 🔧 **Common Issues & Solutions**

### **Issue 1: Query Error - Column Doesn't Exist**

**Symptoms:**
```
Database Error: Unknown column 'transaction_type' in 'field list'
```

**Cause:** Your transactions table uses different column names

**Solution:** Check your actual column names:
```sql
DESCRIBE transactions;
```

**Common variations:**
- `type` vs `transaction_type`
- `planned_return` vs `expected_return_date`
- `rfid_id` vs `student_id`

**Fix:** Update the query in `admin-all-transaction.php` line 32-47 to match your column names.

---

### **Issue 2: No Equipment Table Join**

**Symptoms:**
```
Database Error: Unknown table 'equipment'
```

**Cause:** Equipment table doesn't exist or has different name

**Solution:** Check if table exists:
```sql
SHOW TABLES LIKE 'equipment';
```

**If table name is different (e.g., `items`, `inventory_items`):**
Update line 37 and 46:
```php
FROM transactions t
JOIN your_table_name e ON t.equipment_id = e.id
```

---

### **Issue 3: Empty Result (0 rows)**

**Symptoms:**
- No error message
- Shows "Query executed successfully but returned 0 rows"

**Possible Causes:**

#### **A. No data in transactions table**
Check:
```sql
SELECT COUNT(*) FROM transactions;
```

If 0, you need to add transactions first.

#### **B. JOIN is filtering out all rows**
The query uses `JOIN equipment`, which means if `equipment_id` doesn't match any equipment, the row won't show.

**Fix:** Change `JOIN` to `LEFT JOIN`:
```php
// Line 37 and 46
FROM transactions t
LEFT JOIN equipment e ON t.equipment_id = e.id
```

#### **C. Wrong equipment_id values**
Check if equipment_ids in transactions match equipment table:
```sql
SELECT t.id, t.equipment_id, e.id as equip_id, e.name
FROM transactions t
LEFT JOIN equipment e ON t.equipment_id = e.id;
```

If `equip_id` is NULL, the equipment_id doesn't exist in equipment table.

---

### **Issue 4: Users Table Doesn't Exist**

**Symptoms:**
```
Database Error: Table 'capstone.users' doesn't exist
```

**Solution:** The code now has a fallback! It will use `rfid_id` from transactions table instead.

**If you still get an error:**
Make sure your transactions table has either:
- `user_id` column, OR
- `rfid_id` column

---

## 📊 **Expected Table Structures**

### **transactions table:**
```sql
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    equipment_id INT,
    transaction_type VARCHAR(50),  -- 'Borrow' or 'Return'
    transaction_date DATETIME,
    expected_return_date DATETIME,
    actual_return_date DATETIME,
    status VARCHAR(50),  -- 'Active', 'Returned', etc.
    rfid_id VARCHAR(255),
    -- other columns...
);
```

### **equipment table:**
```sql
CREATE TABLE equipment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    -- other columns...
);
```

### **users table (optional):**
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50),
    name VARCHAR(255),
    -- other columns...
);
```

---

## 🔍 **Manual Testing Queries**

### **Test 1: Check if transactions exist**
```sql
SELECT * FROM transactions LIMIT 5;
```

### **Test 2: Check equipment join**
```sql
SELECT t.*, e.name as equipment_name
FROM transactions t
LEFT JOIN equipment e ON t.equipment_id = e.id
LIMIT 5;
```

### **Test 3: Check full query (with users)**
```sql
SELECT t.*, 
       e.name as equipment_name,
       u.name as user_name,
       u.student_id
FROM transactions t
LEFT JOIN equipment e ON t.equipment_id = e.id
LEFT JOIN users u ON t.user_id = u.id
ORDER BY t.transaction_date DESC
LIMIT 5;
```

### **Test 4: Check full query (without users)**
```sql
SELECT t.*, 
       e.name as equipment_name,
       t.rfid_id as student_id
FROM transactions t
LEFT JOIN equipment e ON t.equipment_id = e.id
ORDER BY t.transaction_date DESC
LIMIT 5;
```

---

## 🛠️ **Quick Fixes**

### **Fix 1: Change JOIN to LEFT JOIN**
```php
// Line 37 in admin-all-transaction.php
FROM transactions t
LEFT JOIN equipment e ON t.equipment_id = e.id  // Changed JOIN to LEFT JOIN
LEFT JOIN users u ON t.user_id = u.id
```

### **Fix 2: Use different column names**
If your table uses `type` instead of `transaction_type`:
```php
// Line 283
<td><?= htmlspecialchars($row['type']) ?></td>

// Line 258
if ($row['type'] === 'Borrow' && $row['status'] === 'Active') {
```

### **Fix 3: Remove users table dependency**
If you don't have a users table:
```php
// Replace lines 31-48 with:
$query = "SELECT t.*, 
            e.name as equipment_name,
            t.rfid_id as student_id
     FROM transactions t
     LEFT JOIN equipment e ON t.equipment_id = e.id
     ORDER BY t.transaction_date DESC";
```

---

## 📝 **Debugging Checklist**

- [ ] Check if transactions table exists
- [ ] Check if equipment table exists
- [ ] Check if there's data in transactions table
- [ ] Check column names match the query
- [ ] Check equipment_id values are valid
- [ ] Check for any SQL errors in debug info
- [ ] Try changing JOIN to LEFT JOIN
- [ ] Check browser console for JavaScript errors
- [ ] Check if page is loading CSS properly

---

## 🎯 **Most Likely Issue**

Based on your situation (data exists but table doesn't show), the most likely causes are:

1. **Column name mismatch** - Your table uses different column names
2. **JOIN filtering out rows** - equipment_id doesn't match any equipment
3. **Query error** - Check the debug info for SQL errors

---

## 💡 **How to Get Debug Info**

1. Open the page in browser
2. Look for "No transactions found" message
3. Click "Show Debug Info" dropdown
4. Copy the information shown
5. Check the query and error message

---

## 🚀 **Next Steps**

1. **Open the page** and check what message you see
2. **Click "Show Debug Info"** to see the exact error
3. **Run the test queries** in phpMyAdmin to verify data exists
4. **Compare column names** between your table and the query
5. **Apply the appropriate fix** from above

---

**Need more help?** Share the debug info output and I can provide a specific fix!
