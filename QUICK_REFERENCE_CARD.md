# 🚀 System Stability Quick Reference Card

## ⚡ Daily Operations Checklist

### Morning Routine (5 minutes)
- [ ] Check system health: `admin/system_health_check.php`
- [ ] Review error logs: `logs/system.log`
- [ ] Verify database connectivity
- [ ] Check disk space (should be >20% free)

### Before Any Code Changes
- [ ] Backup current system
- [ ] Test in development environment
- [ ] Include error_handler.php and input_validator.php
- [ ] Validate all inputs
- [ ] Test error handling

### After Any Code Changes
- [ ] Test all functionality
- [ ] Check error logs
- [ ] Verify user experience
- [ ] Update documentation

## 🚨 Emergency Response

### System Down
1. **Check logs**: `tail -f logs/system.log`
2. **Run health check**: `php admin/system_health_check.php`
3. **Check database**: `mysql -u root -p capstone`
4. **Restart services**: `sudo systemctl restart apache2`
5. **Notify users**: Post maintenance notice

### Data Issues
1. **Stop writes**: Disable user access
2. **Check backups**: Verify latest backup
3. **Restore if needed**: `mysql -u root -p capstone < backup.sql`
4. **Test system**: Verify functionality
5. **Resume operations**: Enable user access

### Security Breach
1. **Isolate system**: Disable network access
2. **Change passwords**: All admin accounts
3. **Review logs**: Check for suspicious activity
4. **Update security**: Apply patches
5. **Notify stakeholders**: Report incident

## 🔧 Common Fixes

### Database Connection Issues
```php
// Check connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=capstone", "root", "");
    echo "Database connected successfully";
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
```

### File Upload Issues
```php
// Check upload directory
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if (!is_writable($uploadDir)) {
    chmod($uploadDir, 0755);
}
```

### Session Issues
```php
// Check session configuration
echo "Session lifetime: " . ini_get('session.gc_maxlifetime');
echo "Session secure: " . ini_get('session.cookie_secure');
echo "Session httponly: " . ini_get('session.cookie_httponly');
```

## 📊 Health Check Commands

### Quick System Check
```bash
# Check disk space
df -h

# Check memory usage
free -h

# Check PHP errors
tail -f /var/log/apache2/error.log

# Check system logs
tail -f logs/system.log
```

### Database Health
```sql
-- Check table sizes
SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size in MB'
FROM information_schema.tables
WHERE table_schema = 'capstone'
ORDER BY (data_length + index_length) DESC;

-- Check for errors
SELECT * FROM equipment WHERE name IS NULL OR name = '';

-- Check recent activity
SELECT COUNT(*) as recent_transactions 
FROM transactions 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY);
```

## 🛡️ Security Quick Checks

### Input Validation
```php
// Always validate inputs
$name = InputValidator::sanitizeString($_POST['name'], 100);
$quantity = InputValidator::sanitizeInteger($_POST['quantity']);

// Check for XSS
if (strpos($input, '<script') !== false) {
    // Potential XSS attack
}
```

### File Upload Security
```php
// Validate file uploads
$validation = InputValidator::validateFileUpload($_FILES['file']);
if (!$validation['is_valid']) {
    // Handle validation errors
}
```

### Session Security
```php
// Check session validity
if (!InputValidator::validateSession()) {
    header('Location: login.php');
    exit;
}
```

## 📈 Performance Monitoring

### Slow Query Detection
```php
// Track query performance
$start = microtime(true);
$result = $pdo->query($sql);
$duration = microtime(true) - $start;

if ($duration > 1.0) {
    error_log("SLOW QUERY: $sql took $duration seconds");
}
```

### Memory Usage
```php
// Check memory usage
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');

if ($memoryUsage > ($memoryLimit * 0.8)) {
    error_log("HIGH MEMORY USAGE: " . round($memoryUsage / 1024 / 1024, 2) . "MB");
}
```

## 🔄 Backup Procedures

### Database Backup
```bash
# Create backup
mysqldump -u root -p capstone > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore backup
mysql -u root -p capstone < backup_20240101_120000.sql
```

### File Backup
```bash
# Backup uploads directory
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz uploads/

# Backup entire project
tar -czf project_backup_$(date +%Y%m%d).tar.gz /path/to/project/
```

## 📞 Contact Information

### System Administrator
- **Name**: [Your Name]
- **Email**: [your.email@domain.com]
- **Phone**: [Your Phone]
- **Emergency**: [Emergency Contact]

### Hosting Provider
- **Company**: [Hosting Company]
- **Support**: [Support Email]
- **Phone**: [Support Phone]
- **Account**: [Account Number]

### Database Administrator
- **Name**: [DBA Name]
- **Email**: [dba.email@domain.com]
- **Phone**: [DBA Phone]

## 🎯 Key Files to Monitor

### Critical Files
- `logs/system.log` - System errors and events
- `logs/php_errors.log` - PHP errors
- `uploads/` - User uploaded files
- `config/database.php` - Database configuration

### Important Directories
- `admin/` - Admin panel files
- `user/` - User interface files
- `includes/` - Shared includes
- `database/` - Database files and migrations

## ⚠️ Warning Signs

### System Issues
- Error logs growing rapidly
- Database connection failures
- Slow page load times
- High memory usage
- Disk space below 20%

### Security Issues
- Unusual login attempts
- Suspicious file uploads
- Unexpected error messages
- Changes to critical files
- Unauthorized access attempts

### Data Issues
- Missing equipment records
- Inconsistent inventory counts
- Failed transactions
- Corrupted file uploads
- Database errors

## 🚀 Quick Commands

### System Status
```bash
# Check system health
php admin/system_health_check.php

# Check PHP configuration
php -i | grep -E "(memory_limit|upload_max_filesize|max_execution_time)"

# Check Apache status
sudo systemctl status apache2

# Check MySQL status
sudo systemctl status mysql
```

### Log Analysis
```bash
# Recent errors
tail -n 50 logs/system.log | grep ERROR

# Security events
grep "SECURITY_EVENT" logs/system.log

# User actions
grep "USER_ACTION" logs/system.log | tail -n 20
```

### Database Maintenance
```sql
-- Optimize tables
OPTIMIZE TABLE equipment, inventory, transactions;

-- Check table status
SHOW TABLE STATUS;

-- Repair tables if needed
REPAIR TABLE equipment, inventory, transactions;
```

---

## 📋 Emergency Contacts

| Issue Type | Contact | Phone | Email |
|------------|---------|-------|-------|
| System Down | [Admin Name] | [Phone] | [Email] |
| Database Issues | [DBA Name] | [Phone] | [Email] |
| Security Breach | [Security Team] | [Phone] | [Email] |
| Hosting Issues | [Hosting Support] | [Phone] | [Email] |

---

*Keep this reference card handy for quick troubleshooting and daily operations.*