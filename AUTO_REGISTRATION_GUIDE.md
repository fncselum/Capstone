# 🎯 Auto RFID Registration System

## ✅ **Complete! Automatic User Registration**

### **🎯 What Was Implemented:**

The system now **automatically registers new RFID tags** when scanned for the first time. No manual registration needed!

---

## 🔄 **How It Works**

### **Scan Flow:**

```
1. User scans RFID or enters manually
   ↓
2. System checks database
   ├─ RFID exists? → Login user
   └─ RFID new? → Auto-register + Login
   ↓
3. Redirect to borrow-return page
```

### **Auto-Registration Process:**

```sql
-- When new RFID is scanned:
INSERT INTO users (
    rfid_tag,
    student_id,
    status,
    penalty_points,
    registered_at
) VALUES (
    'SCANNED_RFID',
    'SCANNED_RFID',  -- Uses RFID as initial student ID
    'Active',
    0,
    NOW()
);
```

---

## 📊 **Database Structure**

### **Required Columns:**
```sql
users table:
├── id (INT, PRIMARY KEY, AUTO_INCREMENT)
├── rfid_tag (VARCHAR) - Stores RFID card number
├── student_id (VARCHAR) - Student ID (initially same as RFID)
├── status (VARCHAR/ENUM) - Active/Inactive/Suspended
├── penalty_points (INT) - Default: 0
├── registered_at (DATETIME) - Auto-set on registration
└── updated_at (DATETIME) - Optional
```

### **Optional Columns (Auto-detected):**
```sql
├── is_admin (TINYINT) - 0 or 1
└── admin_level (VARCHAR/ENUM) - user/admin/super_admin
```

---

## ✨ **Features**

### **1. Smart Column Detection** ✅
- Automatically detects which columns exist
- Works with or without admin columns
- No errors if columns are missing

### **2. Auto-Registration** ✅
- New RFID → Instantly registered
- Status set to "Active"
- Penalty points set to 0
- Uses RFID as initial student ID

### **3. Existing User Login** ✅
- Checks RFID exists in database
- Validates account status
- Loads user information
- Redirects appropriately

### **4. Status Validation** ✅
- **Active** → Allowed to proceed
- **Inactive** → Blocked with message
- **Suspended** → Blocked with message

---

## 🎨 **User Experience**

### **First-Time User:**
```
1. Scan RFID: "ABC123"
2. System: "Welcome! RFID registered successfully."
3. → Redirects to borrow-return page
4. Database: New record created automatically
```

### **Returning User:**
```
1. Scan RFID: "ABC123"
2. System: "RFID verified successfully"
3. → Redirects to borrow-return page
4. Database: Loads existing user data
```

### **Admin User (if admin columns exist):**
```
1. Scan RFID: "ADMIN001"
2. System: "RFID verified successfully"
3. → Redirects to admin dashboard
4. Database: Loads admin privileges
```

---

## 🔒 **Security Features**

### **1. SQL Injection Prevention** ✅
```php
// Uses prepared statements
$stmt = $conn->prepare("INSERT INTO users (...) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("sssi", $rfid, $student_id, $status, $penalty_points);
```

### **2. Input Validation** ✅
```php
// Trims and validates input
$rfid = trim($_POST['rfid'] ?? '');
if (empty($rfid)) {
    // Error handling
}
```

### **3. Status Checking** ✅
```php
// Blocks suspended/inactive users
if ($user['status'] === 'Suspended') {
    // Deny access
}
```

---

## 📝 **Session Variables Stored**

### **After Successful Scan:**
```php
$_SESSION['user_id']         // Database ID
$_SESSION['rfid_tag']        // RFID card number
$_SESSION['student_id']      // Student ID
$_SESSION['is_admin']        // Boolean (if column exists)
$_SESSION['admin_level']     // String (if column exists)
$_SESSION['penalty_points']  // Integer
```

---

## 🧪 **Testing Guide**

### **Test 1: New RFID Registration**
```
1. Go to: localhost/Capstone/user/index.php
2. Click "Manual Entry"
3. Enter: "TEST001"
4. Click Submit
5. ✓ Should see: "Welcome! RFID registered successfully."
6. ✓ Check database: New record with rfid_tag = "TEST001"
```

### **Test 2: Existing RFID Login**
```
1. Scan the same RFID again: "TEST001"
2. ✓ Should see: "RFID verified successfully"
3. ✓ Should redirect to borrow-return page
4. ✓ User info should display correctly
```

### **Test 3: Suspended Account**
```
1. In database: UPDATE users SET status = 'Suspended' WHERE rfid_tag = 'TEST001'
2. Try to scan: "TEST001"
3. ✓ Should see: "Your account is suspended..."
4. ✓ Should NOT redirect
```

### **Test 4: Admin Detection (if columns exist)**
```
1. In database: UPDATE users SET is_admin = 1 WHERE rfid_tag = 'ADMIN001'
2. Scan: "ADMIN001"
3. ✓ Should redirect to admin dashboard
```

---

## 📊 **Database Queries**

### **Check All Registered Users:**
```sql
SELECT * FROM users ORDER BY registered_at DESC;
```

### **Check New Registrations Today:**
```sql
SELECT * FROM users 
WHERE DATE(registered_at) = CURDATE()
ORDER BY registered_at DESC;
```

### **Find User by RFID:**
```sql
SELECT * FROM users WHERE rfid_tag = 'ABC123';
```

### **Update Student ID Later:**
```sql
UPDATE users 
SET student_id = '2024-12345', updated_at = NOW()
WHERE rfid_tag = 'ABC123';
```

### **Set User as Admin:**
```sql
UPDATE users 
SET is_admin = 1, admin_level = 'admin'
WHERE rfid_tag = 'ADMIN001';
```

---

## 🎯 **Workflow Diagram**

```
┌─────────────────────────────────────┐
│   User Scans RFID or Manual Entry   │
└──────────────┬──────────────────────┘
               ↓
┌──────────────────────────────────────┐
│   validate_rfid.php                  │
│   • Check if RFID exists             │
│   • Detect available columns         │
└──────────────┬───────────────────────┘
               ↓
        ┌──────┴──────┐
        │             │
    Exists?       New RFID?
        │             │
        ↓             ↓
┌──────────────┐  ┌──────────────────┐
│ Load User    │  │ Auto-Register    │
│ Data         │  │ • Insert record  │
│              │  │ • Set Active     │
│              │  │ • Penalty = 0    │
└──────┬───────┘  └────────┬─────────┘
       │                   │
       └─────────┬─────────┘
                 ↓
       ┌──────────────────┐
       │ Check Status     │
       │ • Active? ✓      │
       │ • Suspended? ✗   │
       │ • Inactive? ✗    │
       └─────────┬────────┘
                 ↓
       ┌──────────────────┐
       │ Check Admin      │
       │ (if columns      │
       │  exist)          │
       └─────────┬────────┘
                 ↓
        ┌────────┴────────┐
        │                 │
    Admin?            User?
        │                 │
        ↓                 ↓
┌──────────────┐  ┌──────────────────┐
│ Admin        │  │ Borrow-Return    │
│ Dashboard    │  │ Selection Page   │
└──────────────┘  └──────────────────┘
```

---

## 🔧 **Configuration**

### **Default Values for New Users:**
```php
$new_student_id = $rfid;      // Uses RFID as student ID
$status = 'Active';           // Active by default
$penalty_points = 0;          // No penalties
$is_admin = false;            // Regular user
$admin_level = 'user';        // User level
```

### **Customization Options:**

**Change default status:**
```php
$status = 'Pending';  // Require admin approval
```

**Add name field:**
```php
INSERT INTO users (rfid_tag, student_id, name, status, ...)
VALUES (?, ?, 'New User', ?, ...);
```

**Add email notification:**
```php
if ($insert_stmt->execute()) {
    // Send welcome email
    mail($email, 'Welcome', 'Your RFID has been registered');
}
```

---

## ⚠️ **Important Notes**

### **1. RFID as Initial Student ID**
- New users get RFID as student_id
- Admin can update later in dashboard
- Allows immediate system access

### **2. Status Management**
- New users: Active (can use system)
- Suspended: Blocked from system
- Inactive: Blocked from system

### **3. Admin Columns Optional**
- System works without is_admin/admin_level
- Auto-detects available columns
- No errors if columns missing

### **4. Penalty Points**
- Starts at 0 for new users
- Incremented by admin for violations
- Displayed on borrow-return page

---

## 📋 **Maintenance Tasks**

### **Daily:**
- Check new registrations
- Verify no duplicate RFIDs

### **Weekly:**
- Update student IDs for new users
- Review suspended accounts

### **Monthly:**
- Clean up inactive accounts
- Export user statistics

---

## ✅ **Summary**

✅ **Auto-registration** - New RFIDs registered instantly
✅ **No manual setup** - Users can start immediately
✅ **Smart detection** - Works with any column structure
✅ **Status validation** - Blocks suspended/inactive users
✅ **Admin support** - Detects admin users automatically
✅ **Secure** - SQL injection prevention
✅ **Session management** - Proper user tracking
✅ **Error handling** - Clear error messages

**The system is now fully automatic - just scan and go!** 🚀
