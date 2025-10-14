# 📦 Borrow Equipment System - Complete Guide

## ✅ **Complete! Modern Borrow System with Database Integration**

---

## 🎯 **What Was Implemented**

### **1. Modern Borrow Page (borrow.php)** ✅
- Clean, card-based equipment display
- Real-time search and category filtering
- Responsive grid layout
- Modern modal for borrow confirmation
- Auto-logout after 5 minutes inactivity

### **2. Database Integration** ✅
- Automatic equipment quantity updates
- Transaction recording in `transactions` table
- User tracking with session management
- Proper SQL injection prevention

### **3. User Experience** ✅
- Visual equipment cards with images
- Category filtering
- Search functionality
- Confirmation modal before borrowing
- Success/error messages
- Auto-redirect after success

---

## 📊 **Database Structure**

### **Tables Used:**

#### **1. equipment**
```sql
- id (INT, PRIMARY KEY)
- name (VARCHAR)
- category_id (INT)
- quantity (INT) - Decremented on borrow
- image_path (VARCHAR)
- created_at (DATETIME)
```

#### **2. transactions**
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- user_id (INT) - From session
- equipment_id (INT) - Equipment borrowed
- transaction_type (VARCHAR) - 'Borrow'
- status (VARCHAR) - 'Active'
- borrow_date (DATETIME) - Current timestamp
- due_date (DATETIME) - User selected return date
- created_at (DATETIME)
```

#### **3. categories**
```sql
- id (INT, PRIMARY KEY)
- name (VARCHAR)
```

#### **4. users**
```sql
- id (INT, PRIMARY KEY)
- rfid_tag (VARCHAR)
- student_id (VARCHAR)
- status (VARCHAR)
- penalty_points (INT)
```

---

## 🔄 **Borrow Flow**

```
1. User scans RFID → Validates → Redirects to borrow-return.php
   ↓
2. User clicks "Borrow Equipment"
   ↓
3. borrow.php loads with equipment grid
   ↓
4. User searches/filters equipment
   ↓
5. User clicks "Borrow" on equipment card
   ↓
6. Modal opens with:
   - Equipment preview
   - Student ID (readonly)
   - Current time (readonly)
   - Due date selector (required)
   ↓
7. User selects return date → Clicks "Confirm Borrow"
   ↓
8. PHP processes:
   - Validates equipment availability
   - Decrements equipment quantity
   - Inserts transaction record
   - Commits database transaction
   ↓
9. Success message displayed
   ↓
10. Auto-redirect to borrow-return.php after 3 seconds
```

---

## 🎨 **Design Features**

### **Page Layout:**
```
┌─────────────────────────────────────────┐
│  🏫 Logo  Borrow Equipment    [Back]   │
│           Student ID: XXX               │
├─────────────────────────────────────────┤
│  🔍 Search...                           │
│  [All] [Category 1] [Category 2]        │
├─────────────────────────────────────────┤
│  ┌─────┐  ┌─────┐  ┌─────┐            │
│  │ Img │  │ Img │  │ Img │            │
│  │ #1  │  │ #2  │  │ #3  │            │
│  │Name │  │Name │  │Name │            │
│  │Cat  │  │Cat  │  │Cat  │            │
│  │Qty  │  │Qty  │  │Qty  │            │
│  │[Borrow]│[Borrow]│[Borrow]          │
│  └─────┘  └─────┘  └─────┘            │
└─────────────────────────────────────────┘
```

### **Equipment Card:**
- **Image** - 200px height, placeholder icon if no image
- **ID Badge** - Green background with equipment ID
- **Name** - Bold, 1.2rem font
- **Category** - Icon + category name
- **Quantity** - Available count
- **Borrow Button** - Full width, green background

### **Borrow Modal:**
```
┌────────────────────────────────┐
│ 🤝 Confirm Borrow         [×] │
├────────────────────────────────┤
│  ┌────┐                        │
│  │Img │  Equipment Name        │
│  │    │  📦 5 available        │
│  └────┘                        │
│                                │
│  👤 Student ID: XXX            │
│  🕐 Borrow Time: Now           │
│  📅 Return By: [Select Date]   │
│                                │
│  [Cancel]  [Confirm Borrow]    │
└────────────────────────────────┘
```

---

## 💾 **Database Operations**

### **When User Borrows:**

```php
// 1. Start transaction
$conn->begin_transaction();

// 2. Get equipment (with row lock)
SELECT * FROM equipment WHERE id = ? FOR UPDATE

// 3. Check quantity > 0
if ($current_qty > 0) {
    
    // 4. Update equipment quantity
    UPDATE equipment SET quantity = quantity - 1 WHERE id = ?
    
    // 5. Insert transaction record
    INSERT INTO transactions (
        user_id, 
        equipment_id, 
        transaction_type, 
        status, 
        borrow_date, 
        due_date, 
        created_at
    ) VALUES (?, ?, 'Borrow', 'Active', NOW(), ?, NOW())
    
    // 6. Commit transaction
    $conn->commit();
}
```

### **Transaction Record Example:**
```
user_id: 5
equipment_id: 12
transaction_type: 'Borrow'
status: 'Active'
borrow_date: '2025-10-13 20:40:00'
due_date: '2025-10-14 20:40:00'
created_at: '2025-10-13 20:40:00'
```

---

## 🔒 **Security Features**

### **1. SQL Injection Prevention** ✅
```php
// Uses prepared statements
$stmt = $conn->prepare("SELECT * FROM equipment WHERE id = ?");
$stmt->bind_param("i", $equipment_id);
```

### **2. Session Validation** ✅
```php
// Checks user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
```

### **3. Transaction Safety** ✅
```php
// Row locking prevents race conditions
SELECT * FROM equipment WHERE id = ? FOR UPDATE
```

### **4. Input Validation** ✅
```php
// Validates required fields
if ($equipment_id > 0 && !empty($due_date)) {
    // Process borrow
}
```

### **5. XSS Protection** ✅
```php
// Escapes output
<?= htmlspecialchars($item['name']) ?>
```

---

## ✨ **Features**

### **Search & Filter:**
- ✅ Real-time search by equipment name
- ✅ Category filtering with active state
- ✅ Instant results (no page reload)

### **Equipment Display:**
- ✅ Grid layout (auto-adjusts columns)
- ✅ Equipment images or placeholder icons
- ✅ ID badges for easy identification
- ✅ Category tags
- ✅ Quantity display
- ✅ Hover effects

### **Borrow Modal:**
- ✅ Equipment preview with image
- ✅ Student ID (auto-filled, readonly)
- ✅ Current borrow time (auto-filled)
- ✅ Due date picker (required)
- ✅ Minimum date validation (can't select past)
- ✅ Default: 1 day from now

### **User Feedback:**
- ✅ Success message with due date
- ✅ Error messages for failures
- ✅ Loading states
- ✅ Auto-redirect after success

### **Auto-Logout:**
- ✅ 5 minutes of inactivity
- ✅ Tracks mouse, keyboard, touch
- ✅ Redirects to logout.php

---

## 🧪 **Testing Guide**

### **Test 1: Browse Equipment**
```
1. Login with RFID
2. Click "Borrow Equipment"
3. ✓ See equipment grid
4. ✓ See search box and filters
5. ✓ All available equipment displayed
```

### **Test 2: Search Equipment**
```
1. Type in search box
2. ✓ Results filter in real-time
3. ✓ No page reload
4. Clear search
5. ✓ All equipment returns
```

### **Test 3: Filter by Category**
```
1. Click category button
2. ✓ Button becomes active (green)
3. ✓ Only that category shows
4. Click "All"
5. ✓ All equipment returns
```

### **Test 4: Borrow Equipment**
```
1. Click "Borrow" on equipment card
2. ✓ Modal opens
3. ✓ Equipment details shown
4. ✓ Student ID pre-filled
5. ✓ Current time shown
6. Select due date (tomorrow)
7. Click "Confirm Borrow"
8. ✓ Success message appears
9. ✓ Check database:
   - equipment.quantity decreased by 1
   - New record in transactions table
10. ✓ Auto-redirect after 3 seconds
```

### **Test 5: Out of Stock**
```
1. Set equipment quantity to 0 in database
2. Reload page
3. ✓ Equipment not shown (WHERE quantity > 0)
```

### **Test 6: Date Validation**
```
1. Open borrow modal
2. Try to select past date
3. ✓ Cannot select (min date = now)
4. ✓ Default date = tomorrow
```

---

## 📱 **Responsive Design**

### **Desktop (>768px):**
- Grid: 3-4 columns (auto-fill, min 280px)
- Header: Horizontal layout
- Modal: Side-by-side preview

### **Mobile (<768px):**
- Grid: 1 column
- Header: Stacked layout
- Modal: Vertical preview

---

## 🎯 **Key Files**

### **Modified:**
1. ✅ `user/borrow.php` - Complete redesign
   - Modern UI
   - Database integration
   - Search & filter
   - Modal system

### **Database Tables:**
1. ✅ `equipment` - Quantity updated
2. ✅ `transactions` - Records created
3. ✅ `categories` - Used for filtering
4. ✅ `users` - Session validation

---

## 🔧 **Configuration**

### **Auto-Logout Time:**
```javascript
time = setTimeout(logout, 300000); // 5 minutes (300000ms)
```

### **Success Redirect Time:**
```javascript
setTimeout(function(){ 
    window.location.href = 'borrow-return.php'; 
}, 3000); // 3 seconds
```

### **Default Due Date:**
```javascript
// 1 day from now
const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
```

---

## 📊 **Database Queries**

### **View All Borrows:**
```sql
SELECT 
    t.*, 
    u.student_id, 
    e.name as equipment_name
FROM transactions t
JOIN users u ON t.user_id = u.id
JOIN equipment e ON t.equipment_id = e.id
WHERE t.transaction_type = 'Borrow'
AND t.status = 'Active'
ORDER BY t.borrow_date DESC;
```

### **Check User's Active Borrows:**
```sql
SELECT COUNT(*) as borrowed_count
FROM transactions
WHERE user_id = ? 
AND transaction_type = 'Borrow'
AND status = 'Active';
```

### **Equipment Availability:**
```sql
SELECT id, name, quantity
FROM equipment
WHERE quantity > 0
ORDER BY name;
```

### **Overdue Items:**
```sql
SELECT 
    t.*, 
    u.student_id, 
    e.name
FROM transactions t
JOIN users u ON t.user_id = u.id
JOIN equipment e ON t.equipment_id = e.id
WHERE t.transaction_type = 'Borrow'
AND t.status = 'Active'
AND t.due_date < NOW()
ORDER BY t.due_date ASC;
```

---

## ⚠️ **Important Notes**

### **1. Quantity Management**
- Equipment quantity decremented on borrow
- Must be incremented on return
- Check quantity > 0 before showing

### **2. Transaction Status**
- 'Active' = Currently borrowed
- Must change to 'Completed' on return
- Used to track active borrows

### **3. Due Date**
- User selects when borrowing
- Stored in transactions table
- Used for overdue tracking

### **4. Session Management**
- user_id required for all operations
- Validates on page load
- Auto-logout on inactivity

---

## 🎉 **Summary**

✅ **Modern UI** - Clean, professional design
✅ **Database Integration** - Proper transaction recording
✅ **Search & Filter** - Real-time, no reload
✅ **Responsive** - Works on all devices
✅ **Secure** - SQL injection prevention, session validation
✅ **User-Friendly** - Clear feedback, easy navigation
✅ **Auto-Logout** - Security feature
✅ **Transaction Safety** - Row locking, rollback on error

**The borrow system is production-ready!** 🚀
