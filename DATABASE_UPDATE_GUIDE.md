# 🗄️ Database Update Guide - Users Table

## ✅ **Update Your Users Table for RFID System**

### **📊 Current Table Structure:**
```
id | rfid_tag | student_id | status | penalty_points | registered_at | updated_at
```

### **🎯 Required Table Structure:**
```
id | rfid_tag | student_id | status | is_admin | admin_level | penalty_points | registered_at | updated_at
```

---

## 🚀 **Quick Setup (3 Steps)**

### **Step 1: Open phpMyAdmin**
1. Go to: `localhost/phpmyadmin`
2. Click on database: `capstone`
3. Click on table: `users`
4. Click on "SQL" tab

### **Step 2: Run SQL Update**

**Copy and paste this SQL:**

```sql
USE `capstone`;

-- Add admin columns
ALTER TABLE `users` 
ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
ADD COLUMN `admin_level` ENUM('user', 'admin', 'super_admin') NOT NULL DEFAULT 'user' AFTER `is_admin`;

-- Add indexes
ALTER TABLE `users` 
ADD INDEX `idx_is_admin` (`is_admin`),
ADD INDEX `idx_admin_level` (`admin_level`);
```

Click **"Go"** button.

### **Step 3: Add Sample Users**

**Copy and paste this SQL:**

```sql
-- Insert admin user
INSERT INTO `users` (`rfid_tag`, `student_id`, `status`, `is_admin`, `admin_level`, `penalty_points`) 
VALUES ('ADMIN001', 'ADMIN001', 'Active', 1, 'super_admin', 0);

-- Insert regular users
INSERT INTO `users` (`rfid_tag`, `student_id`, `status`, `is_admin`, `admin_level`, `penalty_points`) 
VALUES 
    ('RFID001', '2021-00001', 'Active', 0, 'user', 0),
    ('RFID002', '2021-00002', 'Active', 0, 'user', 0);
```

Click **"Go"** button.

---

## 📋 **Column Descriptions**

| Column | Type | Description | Example |
|--------|------|-------------|---------|
| **id** | INT | Primary key | 1, 2, 3... |
| **rfid_tag** | VARCHAR | RFID card number | RFID001, ADMIN001 |
| **student_id** | VARCHAR | Student ID number | 2021-00001 |
| **status** | ENUM | Account status | Active, Inactive, Suspended |
| **is_admin** | TINYINT | Is user admin? | 0 (No), 1 (Yes) |
| **admin_level** | ENUM | Admin access level | user, admin, super_admin |
| **penalty_points** | INT | Penalty points | 0, 5, 10... |
| **registered_at** | TIMESTAMP | Registration date | 2025-01-13 10:30:00 |
| **updated_at** | TIMESTAMP | Last update | 2025-01-13 10:30:00 |

---

## 👥 **User Types**

### **Regular User:**
```sql
is_admin = 0
admin_level = 'user'
```
- Can borrow equipment
- Can return equipment
- Cannot access admin dashboard

### **Admin:**
```sql
is_admin = 1
admin_level = 'admin'
```
- Can access admin dashboard
- Can manage equipment
- Can view reports

### **Super Admin:**
```sql
is_admin = 1
admin_level = 'super_admin'
```
- Full system access
- Can manage users
- Can manage admins

---

## 🧪 **Testing Your Setup**

### **Test 1: View All Users**
```sql
SELECT * FROM `users`;
```

### **Test 2: View Admin Users Only**
```sql
SELECT * FROM `users` WHERE is_admin = 1;
```

### **Test 3: View Regular Users Only**
```sql
SELECT * FROM `users` WHERE is_admin = 0;
```

### **Test 4: Count Users by Type**
```sql
SELECT 
    CASE WHEN is_admin = 1 THEN 'Admin' ELSE 'User' END AS type,
    COUNT(*) as count
FROM `users`
GROUP BY is_admin;
```

---

## 🔧 **Common Operations**

### **Make a User an Admin:**
```sql
UPDATE `users` 
SET `is_admin` = 1, `admin_level` = 'admin' 
WHERE `rfid_tag` = 'RFID001';
```

### **Remove Admin Privileges:**
```sql
UPDATE `users` 
SET `is_admin` = 0, `admin_level` = 'user' 
WHERE `rfid_tag` = 'ADMIN001';
```

### **Suspend a User:**
```sql
UPDATE `users` 
SET `status` = 'Suspended' 
WHERE `rfid_tag` = 'RFID001';
```

### **Activate a User:**
```sql
UPDATE `users` 
SET `status` = 'Active' 
WHERE `rfid_tag` = 'RFID001';
```

### **Add Penalty Points:**
```sql
UPDATE `users` 
SET `penalty_points` = penalty_points + 5 
WHERE `rfid_tag` = 'RFID001';
```

### **Reset Penalty Points:**
```sql
UPDATE `users` 
SET `penalty_points` = 0 
WHERE `rfid_tag` = 'RFID001';
```

---

## 📝 **Sample Data**

### **Admin Users:**
| rfid_tag | student_id | status | is_admin | admin_level | penalty_points |
|----------|------------|--------|----------|-------------|----------------|
| ADMIN001 | ADMIN001 | Active | 1 | super_admin | 0 |
| ADMIN002 | ADMIN002 | Active | 1 | admin | 0 |

### **Regular Users:**
| rfid_tag | student_id | status | is_admin | admin_level | penalty_points |
|----------|------------|--------|----------|-------------|----------------|
| RFID001 | 2021-00001 | Active | 0 | user | 0 |
| RFID002 | 2021-00002 | Active | 0 | user | 0 |
| RFID003 | 2021-00003 | Active | 0 | user | 5 |
| RFID004 | 2021-00004 | Inactive | 0 | user | 0 |
| RFID005 | 2021-00005 | Suspended | 0 | user | 10 |

---

## 🎯 **How the System Uses This Data**

### **RFID Scanning Flow:**

```
1. User scans RFID → validate_rfid.php
2. System checks: SELECT * FROM users WHERE rfid_tag = 'SCANNED_VALUE'
3. System checks: is_admin column
   ├─ is_admin = 1 → Redirect to Admin Dashboard
   └─ is_admin = 0 → Redirect to Borrow-Return Page
4. System checks: status column
   ├─ Active → Allow access
   ├─ Inactive → Show error message
   └─ Suspended → Show error message
```

---

## ⚠️ **Troubleshooting**

### **Error: Column already exists**
**Solution:** Column is already added, skip that step.

### **Error: Duplicate entry**
**Solution:** User already exists, use UPDATE instead:
```sql
UPDATE `users` 
SET `is_admin` = 1, `admin_level` = 'admin' 
WHERE `rfid_tag` = 'ADMIN001';
```

### **Error: Unknown column 'is_admin'**
**Solution:** Run the ALTER TABLE command first.

### **RFID not working**
**Solution:** Check if user exists:
```sql
SELECT * FROM `users` WHERE rfid_tag = 'YOUR_RFID';
```

---

## 📁 **SQL Files Available**

### **1. update_users_table.sql**
- Complete update script
- Includes IF NOT EXISTS (MySQL 5.7+)
- Sample data included
- Verification queries

### **2. update_users_simple.sql**
- Simple version for older MySQL
- Step-by-step commands
- No IF NOT EXISTS
- Basic sample data

### **3. add_admin_to_users.sql**
- Original admin column script
- Minimal changes only

---

## ✅ **Verification Checklist**

After running the SQL, verify:

- [ ] `is_admin` column exists
- [ ] `admin_level` column exists
- [ ] At least one admin user exists
- [ ] At least one regular user exists
- [ ] Can view all users in phpMyAdmin
- [ ] Indexes are created
- [ ] No SQL errors

---

## 🚀 **Next Steps**

1. ✅ Update database (you're here!)
2. ✅ Test RFID scanning
3. ✅ Test manual entry
4. ✅ Test admin login
5. ✅ Test user login
6. ✅ Test borrow-return page

---

## 💡 **Tips**

- **Backup first:** Export your database before making changes
- **Test with sample data:** Use the provided sample users
- **Use phpMyAdmin:** Easier than command line
- **Check errors:** Read error messages carefully
- **One step at a time:** Run SQL commands one by one

---

## 📞 **Need Help?**

### **Check Current Structure:**
```sql
DESCRIBE `users`;
```

### **View All Data:**
```sql
SELECT * FROM `users`;
```

### **Delete All Test Data:**
```sql
DELETE FROM `users` WHERE rfid_tag LIKE 'RFID%' OR rfid_tag LIKE 'ADMIN%';
```

---

**Your database is ready for the RFID Kiosk System!** 🎉
