# Security & Maintenance Alerts Setup Guide

## Overview
The system now includes automated email notifications for security events and system status changes:
- **Unauthorized Access Alerts** - When unknown RFID cards are scanned
- **Maintenance Mode Alerts** - When maintenance mode is enabled/disabled

## Features

### 🔒 Unauthorized Access Alerts
Automatically notifies admin when someone tries to use the kiosk with an unregistered RFID card.

**Email Includes:**
- RFID tag that was scanned
- Timestamp of attempt
- IP address (if available)
- Security recommendations
- Action items for admin

**Logs:**
- All attempts saved to `unauthorized_access_logs` table
- Viewable in Admin Panel → Security Logs

### 🔧 Maintenance Mode Alerts
Notifies admin when maintenance mode status changes.

**Email Includes:**
- Status (ENABLED/DISABLED)
- Changed by (admin username)
- Timestamp
- Impact on system
- What users will see

**Triggers:**
- When admin toggles maintenance mode in System Settings
- Sent immediately upon change

## Setup Instructions

### Step 1: Create Database Tables

Run these SQL files in phpMyAdmin:

**1. Unauthorized Access Logs Table:**
```sql
CREATE TABLE IF NOT EXISTS `unauthorized_access_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rfid_tag` VARCHAR(100) NOT NULL,
  `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `notified` TINYINT(1) DEFAULT 1,
  INDEX `idx_rfid_tag` (`rfid_tag`),
  INDEX `idx_attempt_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Or import: `database/unauthorized_access_logs_table.sql`

**2. Email Logs Table (Optional):**
```sql
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `email_type` ENUM('overdue', 'borrow', 'return', 'low_stock') NOT NULL,
  `equipment_name` VARCHAR(255),
  `transaction_id` INT,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('sent', 'failed') DEFAULT 'sent',
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_email_type` (`email_type`),
  INDEX `idx_sent_at` (`sent_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Or import: `database/email_logs_table.sql`

### Step 2: Verify Email Configuration

1. Email alerts must be enabled in System Settings
2. Admin email must be set in System Settings → General tab
3. Gmail credentials configured in `admin/includes/email_config.php`

### Step 3: Test the Features

**Test Unauthorized Access Alert:**
1. Go to user kiosk: `http://localhost/Capstone/user/`
2. Scan an RFID card that doesn't exist in database (or type random number)
3. System should deny access
4. Check admin email inbox for security alert
5. Check Admin Panel → Security Logs to see the attempt logged

**Test Maintenance Mode Alert:**
1. Go to System Settings → System tab
2. Toggle "Maintenance Mode" ON
3. Save settings
4. Check admin email for maintenance mode enabled notification
5. Toggle OFF and save
6. Check email for maintenance mode disabled notification

## Security Logs Dashboard

### Access
Navigate to: **Admin Panel → Security Logs**
Or visit: `admin/security_logs.php`

### Features

**Unauthorized Access Tab:**
- Total unauthorized attempts
- Attempts today
- Unique RFID tags attempted
- Detailed log table with:
  - RFID tag
  - Attempt time
  - IP address
  - Notification status

**Email Logs Tab:**
- Total emails sent
- Breakdown by type (borrow, return, overdue, low stock)
- Detailed log table with:
  - Email type
  - Recipient
  - Equipment name
  - Sent timestamp
  - Status

## Integration Details

### Files Modified

**1. `admin/includes/email_config.php`**
- Added `sendUnauthorizedAccessAlert()` function
- Added `sendMaintenanceModeAlert()` function

**2. `user/validate_rfid.php`**
- Added email config include
- Sends alert when RFID not found
- Logs attempt to database

**3. `admin/save_settings.php`**
- Added email config include
- Detects maintenance mode changes
- Sends notification on status change

### New Files Created

**1. `admin/security_logs.php`**
- Dashboard for viewing security events
- Email logs viewer
- Statistics and analytics

**2. `database/unauthorized_access_logs_table.sql`**
- Table structure for logging unauthorized access

**3. `SECURITY_ALERTS_SETUP.md`**
- This documentation file

## Email Templates

### Unauthorized Access Alert

**Subject:** Security Alert - Unauthorized Kiosk Access Attempt

**Content:**
- Red security header with warning icon
- RFID tag attempted
- Attempt timestamp
- Location (Equipment Kiosk)
- Status (Access Denied)
- Recommended actions:
  - Verify if RFID should be registered
  - Check if user needs to be added
  - Monitor for repeated attempts
  - Review security footage

### Maintenance Mode Alert

**Subject:** Maintenance Mode ENABLED/DISABLED - Equipment Kiosk System

**Content (Enabled):**
- Orange maintenance header
- Status: ENABLED
- Changed by admin
- Change timestamp
- Impact:
  - Users cannot access kiosk
  - Maintenance message displayed
  - Borrowing/returning disabled
  - Admin panel accessible
- Reminder to disable when done

**Content (Disabled):**
- Green success header
- Status: DISABLED
- Changed by admin
- Change timestamp
- Impact:
  - Kiosk accessible to users
  - All features operational
  - Normal operations restored

## Use Cases

### Scenario 1: New Student Tries to Use Kiosk

**What Happens:**
1. Student scans their new RFID card
2. System checks database - user not found
3. Access denied message shown to student
4. Email sent to admin immediately
5. Attempt logged in database
6. Admin receives email with RFID tag
7. Admin can register the student

**Admin Action:**
1. Check email for RFID tag
2. Go to User Management
3. Add new user with that RFID tag
4. Student can now use kiosk

### Scenario 2: Maintenance Work Required

**What Happens:**
1. Admin needs to update equipment
2. Goes to System Settings
3. Enables Maintenance Mode
4. Email sent to admin confirming change
5. Kiosk shows maintenance message
6. Users cannot borrow/return
7. Admin completes work
8. Disables Maintenance Mode
9. Email sent confirming restoration
10. Kiosk operational again

### Scenario 3: Security Breach Attempt

**What Happens:**
1. Someone tries multiple unknown RFID cards
2. Each attempt triggers email alert
3. All attempts logged with timestamps
4. Admin receives multiple emails
5. Admin checks Security Logs
6. Sees pattern of attempts
7. Can review security footage
8. Take appropriate action

## Monitoring & Analytics

### View Unauthorized Access Attempts

**SQL Query:**
```sql
SELECT 
    rfid_tag,
    COUNT(*) as attempt_count,
    MIN(attempt_time) as first_attempt,
    MAX(attempt_time) as last_attempt
FROM unauthorized_access_logs
GROUP BY rfid_tag
ORDER BY attempt_count DESC;
```

### View Recent Security Events

**SQL Query:**
```sql
SELECT *
FROM unauthorized_access_logs
WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY attempt_time DESC;
```

### View Email Statistics

**SQL Query:**
```sql
SELECT 
    email_type,
    COUNT(*) as count,
    DATE(sent_at) as date
FROM email_logs
WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY email_type, DATE(sent_at)
ORDER BY date DESC, email_type;
```

## Troubleshooting

### No Email Received for Unauthorized Access

**Check:**
1. Email Alerts enabled in System Settings
2. Admin email set in General settings
3. Gmail credentials correct
4. Check spam folder
5. Run `admin/test_email_simple.php`

### Unauthorized Access Not Logged

**Check:**
1. `unauthorized_access_logs` table exists
2. Database connection working
3. Check PHP error logs
4. Verify `validate_rfid.php` has email config included

### Maintenance Mode Email Not Sent

**Check:**
1. Email Alerts enabled
2. Admin email set
3. Maintenance mode actually changed (not same value)
4. Check `save_settings.php` for errors

## Security Best Practices

1. **Review Logs Regularly**
   - Check Security Logs daily
   - Look for patterns
   - Investigate repeated attempts

2. **Respond to Alerts**
   - Act on unauthorized access emails
   - Register legitimate users promptly
   - Investigate suspicious activity

3. **Monitor Email Logs**
   - Ensure emails are being sent
   - Check delivery rates
   - Verify recipients receiving emails

4. **Database Maintenance**
   - Archive old logs periodically
   - Keep recent 90 days active
   - Backup logs before deletion

5. **Test Regularly**
   - Test unauthorized access detection
   - Verify emails are sent
   - Check log recording

## Advanced Configuration

### Customize Alert Threshold

To reduce email noise, you can modify the code to only send alerts for repeated attempts:

```php
// In validate_rfid.php
// Check if this RFID has been attempted recently
$check_recent = $conn->prepare("SELECT COUNT(*) as count FROM unauthorized_access_logs WHERE rfid_tag = ? AND attempt_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$check_recent->bind_param("s", $rfid);
$check_recent->execute();
$result_recent = $check_recent->get_result();
$row = $result_recent->fetch_assoc();

// Only send email if first attempt in the hour
if ($row['count'] == 0) {
    sendUnauthorizedAccessAlert($conn, $rfid, $attemptTime);
}
```

### Add SMS Alerts

For critical security events, consider adding SMS notifications:

```php
// After email alert
if ($critical_security_event) {
    sendSMSAlert($admin_phone, "Security Alert: Unauthorized access at kiosk");
}
```

## Support

For issues or questions:
1. Check Security Logs dashboard
2. Review email logs
3. Test with `test_email_simple.php`
4. Check PHP error logs
5. Verify database tables exist

---

**Last Updated:** November 8, 2025  
**Version:** 1.0  
**Status:** Fully Operational
