# Email Alerts Setup Guide

## Overview
The Equipment Kiosk System now supports automated email notifications for critical events such as overdue equipment, borrow confirmations, and return receipts.

## Features

### Automated Email Notifications
- ✅ **Overdue Equipment Reminders** - Sent to users with overdue items
- ✅ **Borrow Confirmations** - Sent when equipment is borrowed
- ✅ **Return Receipts** - Sent when equipment is returned
- ✅ **Low Stock Alerts** - Sent to admin when equipment quantity is low

### Email Templates
All emails include:
- Professional HTML formatting
- School branding (De La Salle ASMC)
- Clear action items
- Timestamp and details
- Responsive design

## Setup Instructions

### Step 1: Configure Email Credentials

1. Open `admin/includes/email_config.php`
2. Update the following constants with your Gmail credentials:

```php
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SYSTEM_EMAIL_FROM', 'your-email@gmail.com');
```

### Step 2: Generate Gmail App Password

**Important:** You cannot use your regular Gmail password. You must create an App Password.

1. Go to your Google Account: https://myaccount.google.com/
2. Navigate to **Security** → **2-Step Verification**
3. Enable 2-Step Verification if not already enabled
4. Scroll down to **App passwords**
5. Click **Select app** → Choose "Mail"
6. Click **Select device** → Choose "Other (Custom name)"
7. Enter "Equipment Kiosk System"
8. Click **Generate**
9. Copy the 16-character password (format: `xxxx xxxx xxxx xxxx`)
10. Paste it into `SMTP_PASSWORD` in `email_config.php`

### Step 3: Enable Email Alerts

1. Login to Admin Panel
2. Go to **System Settings** → **System** tab
3. Toggle **"Enable Email Alerts"** to ON
4. Click **"Save System Settings"**

### Step 4: Test Email Configuration

1. In System Settings → System tab, click **"Test Email Configuration"**
2. Enter your email address
3. Click **"Send Test Email"**
4. Check your inbox for the test email
5. If successful, email alerts are configured correctly!

## Email Functions

### Available Functions

#### 1. `sendOverdueNotification($conn, $userId, $equipmentName, $daysOverdue)`
Sends overdue reminder to user.

**Usage:**
```php
require_once 'includes/email_config.php';
sendOverdueNotification($conn, 123, 'Arduino Kit', 3);
```

#### 2. `sendBorrowNotification($conn, $userId, $equipmentName, $expectedReturnDate)`
Sends borrow confirmation to user.

**Usage:**
```php
sendBorrowNotification($conn, 123, 'Raspberry Pi', '2025-11-15');
```

#### 3. `sendReturnNotification($conn, $userId, $equipmentName, $condition)`
Sends return receipt to user.

**Usage:**
```php
sendReturnNotification($conn, 123, 'Arduino Kit', 'Good');
```

#### 4. `sendLowStockAlert($conn, $equipmentName, $currentQuantity, $threshold)`
Sends low stock alert to admin.

**Usage:**
```php
sendLowStockAlert($conn, 'Arduino Kit', 2, 5);
```

#### 5. `isEmailAlertsEnabled($conn)`
Checks if email alerts are enabled in system settings.

**Usage:**
```php
if (isEmailAlertsEnabled($conn)) {
    // Send email
}
```

## Integration Examples

### Example 1: Send Email on Borrow Transaction

Add to your borrow transaction handler:

```php
require_once 'includes/email_config.php';

// After successful borrow transaction
if ($transaction_success) {
    sendBorrowNotification(
        $conn, 
        $user_id, 
        $equipment_name, 
        $expected_return_date
    );
}
```

### Example 2: Send Email on Return Transaction

Add to your return transaction handler:

```php
require_once 'includes/email_config.php';

// After successful return transaction
if ($return_success) {
    sendReturnNotification(
        $conn, 
        $user_id, 
        $equipment_name, 
        $return_condition
    );
}
```

### Example 3: Daily Overdue Check (Cron Job)

Create a scheduled task to check for overdue items:

```php
<?php
require_once 'includes/email_config.php';

// Database connection
$conn = new mysqli("localhost", "root", "", "capstone");

// Get overdue transactions
$query = "SELECT t.*, u.id as user_id, e.name as equipment_name,
          DATEDIFF(CURDATE(), t.expected_return_date) as days_overdue
          FROM transactions t
          JOIN users u ON t.user_id = u.id
          JOIN equipment e ON t.equipment_id = e.id
          WHERE t.transaction_type = 'Borrow'
          AND t.expected_return_date < CURDATE()
          AND t.status = 'Active'";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    sendOverdueNotification(
        $conn,
        $row['user_id'],
        $row['equipment_name'],
        $row['days_overdue']
    );
}

$conn->close();
?>
```

### Example 4: Low Stock Alert

Add to equipment update handler:

```php
require_once 'includes/email_config.php';

// After updating equipment quantity
if ($new_quantity <= 5 && $new_quantity > 0) {
    sendLowStockAlert($conn, $equipment_name, $new_quantity, 5);
}
```

## Troubleshooting

### Email Not Sending

**Problem:** Test email fails to send

**Solutions:**
1. Verify Gmail App Password is correct (16 characters, no spaces)
2. Check that 2-Step Verification is enabled on your Google Account
3. Ensure SMTP settings are correct:
   - Host: `smtp.gmail.com`
   - Port: `587`
   - Encryption: `tls`
4. Check PHP error logs for detailed error messages
5. Verify PHPMailer folder exists at `Capstone/PHPMailer/`

### Email Goes to Spam

**Problem:** Emails are received but go to spam folder

**Solutions:**
1. Add sender email to contacts
2. Mark email as "Not Spam"
3. Consider using a custom domain email instead of Gmail
4. Add SPF and DKIM records (for production environments)

### Users Not Receiving Emails

**Problem:** Email alerts enabled but users don't receive emails

**Solutions:**
1. Verify user email addresses are correct in database
2. Check that `enable_email_alerts` is set to `1` in system_settings
3. Test with `test_email.php` first
4. Check if user email field is empty in database

## Security Best Practices

1. **Never commit credentials to version control**
   - Add `email_config.php` to `.gitignore`
   - Use environment variables for production

2. **Use App Passwords, not regular passwords**
   - More secure than regular Gmail password
   - Can be revoked independently

3. **Limit email sending rate**
   - Avoid sending too many emails in short time
   - Gmail has sending limits (500 emails/day for free accounts)

4. **Validate email addresses**
   - Always validate before sending
   - Handle bounced emails appropriately

## Production Recommendations

For production environments, consider:

1. **Use a dedicated email service**
   - SendGrid
   - Amazon SES
   - Mailgun
   - More reliable than Gmail for bulk sending

2. **Implement email queue**
   - Don't send emails synchronously
   - Use background jobs/cron tasks

3. **Add email logging**
   - Track sent emails
   - Monitor delivery rates
   - Debug issues

4. **Rate limiting**
   - Prevent spam
   - Respect provider limits

## File Structure

```
Capstone/
├── PHPMailer/                    # PHPMailer library
│   ├── src/
│   │   ├── PHPMailer.php
│   │   ├── SMTP.php
│   │   └── Exception.php
│   └── ...
├── admin/
│   ├── includes/
│   │   └── email_config.php      # Email configuration & functions
│   ├── test_email.php            # Email testing page
│   └── admin-settings.php        # Enable/disable email alerts
└── EMAIL_ALERTS_SETUP.md         # This documentation
```

## Support

For issues or questions:
1. Check error logs in `php_error.log`
2. Test with `test_email.php`
3. Verify all setup steps completed
4. Check Gmail account settings

---

**Last Updated:** November 8, 2025  
**Version:** 1.0
