# Cron Job Setup Guide - Overdue Equipment Checker

## Overview
The system includes an automated script that checks for overdue equipment and sends email reminders to users. This guide explains how to set it up to run automatically every day.

## Files Included

1. **`admin/cron_check_overdue.php`** - Main cron job script
2. **`database/email_logs_table.sql`** - Email tracking table (optional)
3. **This guide** - Setup instructions

## What the Cron Job Does

- ✅ Checks for overdue equipment daily
- ✅ Sends email reminders to users with overdue items
- ✅ Logs all activities to `admin/logs/overdue_check.log`
- ✅ Tracks emails sent in database (optional)
- ✅ Respects "Enable Email Alerts" setting

## Setup Instructions

### Step 1: Create Email Logs Table (Optional)

Run this SQL in phpMyAdmin to track email history:

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

### Step 2: Test the Script Manually

Before scheduling, test if it works:

1. Open Command Prompt (CMD)
2. Navigate to PHP directory:
   ```cmd
   cd C:\xampp\php
   ```
3. Run the script:
   ```cmd
   php.exe "C:\xampp\htdocs\Capstone\admin\cron_check_overdue.php"
   ```
4. Check output for any errors
5. Verify log file created: `admin/logs/overdue_check.log`

### Step 3: Schedule with Windows Task Scheduler

#### Option A: Using GUI

1. **Open Task Scheduler**
   - Press `Win + R`
   - Type `taskschd.msc`
   - Press Enter

2. **Create New Task**
   - Click "Create Basic Task" in right panel
   - Name: `Equipment Overdue Checker`
   - Description: `Daily check for overdue equipment and send email reminders`
   - Click Next

3. **Set Trigger**
   - Select "Daily"
   - Click Next
   - Set Start date and time (e.g., 9:00 AM)
   - Recur every: 1 days
   - Click Next

4. **Set Action**
   - Select "Start a program"
   - Click Next
   - Program/script: `C:\xampp\php\php.exe`
   - Add arguments: `"C:\xampp\htdocs\Capstone\admin\cron_check_overdue.php"`
   - Click Next

5. **Finish**
   - Review settings
   - Check "Open Properties dialog" if you want to configure more
   - Click Finish

#### Option B: Using Command Line

Run this in Command Prompt (as Administrator):

```cmd
schtasks /create /tn "Equipment Overdue Checker" /tr "C:\xampp\php\php.exe \"C:\xampp\htdocs\Capstone\admin\cron_check_overdue.php\"" /sc daily /st 09:00
```

**Parameters:**
- `/tn` - Task name
- `/tr` - Task to run (PHP executable + script path)
- `/sc` - Schedule type (daily)
- `/st` - Start time (09:00 = 9:00 AM)

### Step 4: Verify Task is Running

1. Open Task Scheduler
2. Find "Equipment Overdue Checker" in task list
3. Right-click → Run
4. Check `admin/logs/overdue_check.log` for output
5. Verify emails were sent (check inbox)

## Configuration Options

### Change Schedule Time

To run at different time (e.g., 8:00 PM):

```cmd
schtasks /change /tn "Equipment Overdue Checker" /st 20:00
```

### Run Multiple Times Per Day

To run every 12 hours:

```cmd
schtasks /create /tn "Equipment Overdue Checker Morning" /tr "C:\xampp\php\php.exe \"C:\xampp\htdocs\Capstone\admin\cron_check_overdue.php\"" /sc daily /st 09:00

schtasks /create /tn "Equipment Overdue Checker Evening" /tr "C:\xampp\php\php.exe \"C:\xampp\htdocs\Capstone\admin\cron_check_overdue.php\"" /sc daily /st 21:00
```

### Disable Task Temporarily

```cmd
schtasks /change /tn "Equipment Overdue Checker" /disable
```

### Enable Task Again

```cmd
schtasks /change /tn "Equipment Overdue Checker" /enable
```

### Delete Task

```cmd
schtasks /delete /tn "Equipment Overdue Checker" /f
```

## Log Files

### Location
`admin/logs/overdue_check.log`

### Sample Log Output
```
============================================================
Overdue Check Cron Job Started: 2025-11-08 09:00:01
============================================================
Total overdue transactions found: 3

Processing Transaction #145:
  - User: John Doe (john@example.com)
  - Equipment: Arduino Kit
  - Days Overdue: 2
  - Email Status: ✓ SENT

Processing Transaction #148:
  - User: Jane Smith (jane@example.com)
  - Equipment: Raspberry Pi
  - Days Overdue: 5
  - Email Status: ✓ SENT

------------------------------------------------------------
Summary:
  - Total Overdue: 3
  - Emails Sent: 3
  - Emails Failed: 0
============================================================
```

### Log Rotation

To prevent log file from growing too large, you can manually clear it:

```cmd
del "C:\xampp\htdocs\Capstone\admin\logs\overdue_check.log"
```

Or keep only recent entries (PowerShell):

```powershell
Get-Content "C:\xampp\htdocs\Capstone\admin\logs\overdue_check.log" -Tail 1000 | Set-Content "C:\xampp\htdocs\Capstone\admin\logs\overdue_check.log"
```

## Troubleshooting

### Task Doesn't Run

**Check Task Scheduler History:**
1. Open Task Scheduler
2. Click "Enable All Tasks History" in Actions panel
3. Find your task → History tab
4. Look for errors

**Common Issues:**
- PHP path incorrect → Verify: `C:\xampp\php\php.exe` exists
- Script path incorrect → Verify: `C:\xampp\htdocs\Capstone\admin\cron_check_overdue.php` exists
- Permissions → Run Task Scheduler as Administrator

### No Emails Sent

**Check:**
1. Email alerts enabled in System Settings
2. Gmail credentials correct in `email_config.php`
3. Users have email addresses in database
4. Check log file for error messages
5. Test manually: `php cron_check_overdue.php`

### Script Runs But Errors

**Check PHP Error Log:**
- Location: `C:\xampp\php\logs\php_error_log`
- Look for errors related to the script

**Check Script Log:**
- Location: `admin/logs/overdue_check.log`
- Shows detailed execution info

## Manual Testing

To test without waiting for scheduled time:

```cmd
cd C:\xampp\php
php.exe "C:\xampp\htdocs\Capstone\admin\cron_check_overdue.php"
```

Or visit in browser (for testing only):
```
http://localhost/Capstone/admin/cron_check_overdue.php
```

## Email Logs Query

View sent emails in database:

```sql
SELECT 
    el.*,
    CONCAT(u.first_name, ' ', u.last_name) AS user_name,
    u.email
FROM email_logs el
JOIN users u ON el.user_id = u.id
ORDER BY el.sent_at DESC
LIMIT 50;
```

## Production Recommendations

1. **Use a dedicated email service** (SendGrid, Amazon SES) instead of Gmail
2. **Set up proper logging** with log rotation
3. **Monitor task execution** regularly
4. **Test thoroughly** before deploying
5. **Have backup notification method** (SMS, in-app notifications)

## Advanced Configuration

### Run on Server Startup

Add `/ru SYSTEM` parameter:

```cmd
schtasks /create /tn "Equipment Overdue Checker" /tr "C:\xampp\php\php.exe \"C:\xampp\htdocs\Capstone\admin\cron_check_overdue.php\"" /sc onstart /ru SYSTEM
```

### Run with Specific User Account

```cmd
schtasks /create /tn "Equipment Overdue Checker" /tr "C:\xampp\php\php.exe \"C:\xampp\htdocs\Capstone\admin\cron_check_overdue.php\"" /sc daily /st 09:00 /ru "DOMAIN\Username" /rp "Password"
```

## Support

For issues:
1. Check log files
2. Test script manually
3. Verify email configuration
4. Check Task Scheduler history
5. Review PHP error logs

---

**Last Updated:** November 8, 2025  
**Version:** 1.0
