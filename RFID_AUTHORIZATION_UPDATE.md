# RFID Authorization System Update

## Overview
Updated the RFID scanner system to enforce strict authorization - only allowing automatic scanning with pre-authorized Active users from the database.

## Changes Made

### 1. **user/index.php** - Removed Manual Input
**Changes:**
- Removed "Manual Entry" button
- Removed manual input form (hidden input field and submit button)
- Now only supports automatic RFID scanning via hardware scanner

**Before:**
- Users could manually type RFID or Student ID
- Manual entry button with keyboard icon
- Hidden manual input form

**After:**
- Clean interface with only automatic scanning
- Hidden input field captures scanner data automatically
- No manual entry option

---

### 2. **user/script.js** - Simplified Script
**Changes:**
- Removed `toggleManualInput()` function
- Removed `submitManualRfid()` function
- Removed manual input event listeners
- Kept only automatic RFID scanning logic

**Removed Functions:**
```javascript
// toggleManualInput() - REMOVED
// submitManualRfid() - REMOVED
// Manual input Enter key handler - REMOVED
```

**Kept Functions:**
- Auto-focus on RFID input
- Automatic scan processing on Enter key
- Status message display
- RFID validation via fetch API

---

### 3. **user/validate_rfid.php** - Enforced Authorization
**Major Changes:**

#### A. Status Validation Enhanced
**Before:**
- Checked for Suspended and Inactive separately
- Auto-registered new RFID tags

**After:**
- Only allows users with `status = 'Active'`
- Enhanced status checking with switch statement
- Specific error messages for each status type

```php
// Only allow Active users
if ($user['status'] !== 'Active') {
    switch ($user['status']) {
        case 'Suspended':
            $statusMessage = 'Your account is suspended...';
            break;
        case 'Inactive':
            $statusMessage = 'Your account is inactive...';
            break;
        default:
            $statusMessage = 'Your account status does not allow access...';
            break;
    }
    // Reject access
}
```

#### B. Removed Auto-Registration
**Before:**
```php
// User not found - Auto-register new RFID
INSERT INTO users (rfid_tag, student_id, status, penalty_points, registered_at)
VALUES (?, ?, 'Active', 0, NOW())
```

**After:**
```php
// User not found - Reject unauthorized access
echo json_encode([
    'success' => false,
    'message' => 'RFID not authorized. Please contact the administrator...'
]);
```

---

## Authorization Flow

### Current Workflow:
1. **User scans RFID card** → Automatic capture via hidden input
2. **System validates RFID** → Checks database for matching `rfid_tag` or `student_id`
3. **Authorization check:**
   - ✅ **User found + Status = 'Active'** → Allow access
   - ❌ **User found + Status ≠ 'Active'** → Reject with specific message
   - ❌ **User not found** → Reject with "not authorized" message
4. **If authorized** → Redirect to borrow-return page (or admin dashboard if admin)

### User Status Types:
| Status | Access | Message |
|--------|--------|---------|
| `Active` | ✅ Allowed | "RFID verified successfully" |
| `Suspended` | ❌ Denied | "Your account is suspended. Please contact the administrator." |
| `Inactive` | ❌ Denied | "Your account is inactive. Please contact the administrator." |
| Not in DB | ❌ Denied | "RFID not authorized. Please contact the administrator to register your account." |

---

## Database Reference

### Users Table Structure:
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rfid_tag VARCHAR(50) UNIQUE,
    student_id VARCHAR(50) UNIQUE,
    status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    penalty_points INT DEFAULT 0,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Sample Data:
```
id | rfid_tag    | student_id  | status   | penalty_points
---|-------------|-------------|----------|---------------
1  | 0066629842  | 0066629842  | Active   | 7399410
5  | 0036690957  | 0036690957  | Active   | 0
```

---

## Security Improvements

### 1. **No Auto-Registration**
- Prevents unauthorized RFID cards from gaining automatic access
- Admin must manually add users to the database

### 2. **Strict Status Checking**
- Only `Active` status users can proceed
- Clear rejection messages for non-active users

### 3. **No Manual Input**
- Prevents manual entry of arbitrary RFID/Student IDs
- Forces use of physical RFID scanner hardware
- Reduces potential for abuse or unauthorized access attempts

---

## Admin Responsibilities

### To Authorize a New User:
1. Obtain the user's RFID tag number
2. Insert into database via admin panel or SQL:
```sql
INSERT INTO users (rfid_tag, student_id, status, penalty_points, registered_at)
VALUES ('RFID_NUMBER', 'STUDENT_ID', 'Active', 0, NOW());
```

### To Suspend/Reactivate Users:
```sql
-- Suspend user
UPDATE users SET status = 'Suspended' WHERE student_id = 'STUDENT_ID';

-- Reactivate user
UPDATE users SET status = 'Active' WHERE student_id = 'STUDENT_ID';

-- Deactivate user
UPDATE users SET status = 'Inactive' WHERE student_id = 'STUDENT_ID';
```

---

## Testing Checklist

- [ ] Scan authorized RFID (Active status) → Should allow access
- [ ] Scan suspended RFID → Should show "account is suspended" message
- [ ] Scan inactive RFID → Should show "account is inactive" message
- [ ] Scan unregistered RFID → Should show "not authorized" message
- [ ] Verify manual input option is completely removed
- [ ] Verify auto-focus works on RFID input field
- [ ] Test admin RFID → Should redirect to admin dashboard
- [ ] Test regular user RFID → Should redirect to borrow-return page

---

## Files Modified

1. **user/index.php** - Removed manual input UI elements
2. **user/script.js** - Removed manual input functions
3. **user/validate_rfid.php** - Enforced authorization and removed auto-registration

---

## Notes

- **Hardware Requirement:** System now requires a physical RFID scanner device
- **Admin Access:** Admins must pre-authorize all users in the database
- **User Experience:** Cleaner, simpler interface focused on scanning only
- **Security:** Enhanced security by preventing unauthorized access attempts

---

**Date:** October 30, 2025  
**Phase:** Phase 2 - Authorization Enhancement
