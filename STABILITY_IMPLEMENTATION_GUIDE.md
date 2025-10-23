# System Stability Implementation Guide

## 🚀 Quick Start

### 1. **Immediate Actions Required**

#### Step 1: Create Required Directories
```bash
mkdir -p /workspace/logs
mkdir -p /workspace/backups
chmod 755 /workspace/logs
chmod 755 /workspace/backups
```

#### Step 2: Update Database Configuration
Replace your current database connection in all files with the new `DatabaseManager`:

```php
// OLD WAY (Remove this)
$conn = new mysqli($host, $user, $password, $dbname);

// NEW WAY (Use this)
require_once 'includes/database_manager.php';
$pdo = DatabaseManager::getConnection();
```

#### Step 3: Add Error Handling to All Files
Add this to the top of every PHP file:

```php
require_once 'includes/error_handler.php';
SystemErrorHandler::init();
```

### 2. **Critical Files to Update**

#### High Priority (Update Immediately)
- `admin/add_equipment.php` ✅ (Already updated)
- `admin/update_equipment_ajax.php`
- `admin/delete_equipment_ajax.php`
- `admin/admin-equipment-inventory.php`

#### Medium Priority
- All transaction-related files
- User management files
- Report generation files

### 3. **New Features Available**

#### System Health Monitoring
- **URL**: `admin/system_monitor.php`
- **Features**: Real-time system health, error monitoring, database statistics
- **Auto-refresh**: Every 30 seconds

#### System Health API
- **URL**: `admin/system_health.php`
- **Returns**: JSON with system status
- **Use**: For external monitoring or API integration

#### Backup System
- **URL**: `admin/backup_system.php`
- **Actions**: Create, restore, delete, download backups
- **Auto-cleanup**: Keeps last 10 backups

## 🛡️ Security Enhancements

### 1. **Input Validation**
All user inputs are now validated using `SystemValidator`:

```php
// Validate equipment data
$errors = SystemValidator::validateEquipmentData($_POST);
if (!empty($errors)) {
    throw new Exception(implode(' ', $errors));
}

// Validate file uploads
$file_errors = SystemValidator::validateFileUpload($_FILES['image_file']);
if (!empty($file_errors)) {
    throw new Exception(implode(' ', $file_errors));
}
```

### 2. **CSRF Protection**
Add CSRF tokens to all forms:

```php
// Generate token
$csrf_token = SystemValidator::generateCSRFToken();

// In form
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

// Validate on submit
$csrf_error = SystemValidator::validateCSRFToken($_POST['csrf_token']);
if ($csrf_error) {
    throw new Exception("Security validation failed");
}
```

### 3. **Session Security**
Enhanced session validation:

```php
$session_error = SystemValidator::validateAdminSession();
if ($session_error) {
    SystemErrorHandler::logSecurityEvent("Unauthorized access attempt");
    header('Location: login.php');
    exit;
}
```

## 📊 Monitoring & Maintenance

### 1. **Daily Health Checks**
Access `admin/system_monitor.php` daily to check:
- Database connectivity
- File system permissions
- Memory usage
- Recent errors

### 2. **Weekly Maintenance**
- Review error logs in `logs/system_errors.log`
- Check backup status
- Monitor database performance
- Clean up old files

### 3. **Monthly Tasks**
- Test backup restoration
- Review security logs
- Update system documentation
- Performance optimization

## 🔧 Configuration

### 1. **Error Logging**
Configure in `admin/includes/error_handler.php`:

```php
// Log file location
$log_file = dirname(__DIR__) . '/logs/system_errors.log';

// Error reporting level
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
```

### 2. **Database Settings**
Configure in `admin/includes/database_manager.php`:

```php
private static $host = "localhost";
private static $user = "root";
private static $password = "";
private static $dbname = "capstone";
```

### 3. **Backup Settings**
Configure in `admin/backup_system.php`:

```php
// Number of backups to keep
$keep_count = 10;

// Backup directory
$backup_dir = dirname(__DIR__) . '/backups';
```

## 🚨 Emergency Procedures

### 1. **System Down**
1. Check `logs/system_errors.log`
2. Verify database connectivity
3. Check file permissions
4. Restore from latest backup if needed

### 2. **Data Corruption**
1. Stop all operations immediately
2. Restore from latest backup
3. Verify data integrity
4. Check for security breaches

### 3. **Security Breach**
1. Change all passwords
2. Review access logs
3. Update security measures
4. Notify administrators

## 📈 Performance Optimization

### 1. **Database Optimization**
- Add indexes to frequently queried columns
- Use prepared statements
- Implement connection pooling
- Regular query optimization

### 2. **File System Optimization**
- Regular cleanup of uploads
- Compress old logs
- Monitor disk space
- Optimize file permissions

### 3. **Memory Management**
- Monitor memory usage
- Optimize queries
- Implement caching
- Regular garbage collection

## 🔍 Troubleshooting

### Common Issues

#### 1. **Database Connection Failed**
```
Error: Database connection failed
Solution: Check database credentials and server status
```

#### 2. **File Upload Failed**
```
Error: Failed to upload image file
Solution: Check uploads directory permissions
```

#### 3. **Session Expired**
```
Error: Session expired or unauthorized access
Solution: User needs to log in again
```

### Debug Mode
Enable debug mode by setting:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## 📋 Checklist

### Pre-Implementation
- [ ] Backup current system
- [ ] Test in development environment
- [ ] Review all existing code
- [ ] Plan implementation schedule

### Implementation
- [ ] Create required directories
- [ ] Update database connections
- [ ] Add error handling
- [ ] Implement input validation
- [ ] Add CSRF protection
- [ ] Test all functionality

### Post-Implementation
- [ ] Monitor system health
- [ ] Review error logs
- [ ] Test backup/restore
- [ ] Train administrators
- [ ] Document procedures

## 📞 Support

### Log Files Location
- System errors: `logs/system_errors.log`
- Application logs: `logs/application.log`
- Security events: `logs/security.log`

### Monitoring URLs
- System health: `admin/system_monitor.php`
- Health API: `admin/system_health.php`
- Backup management: `admin/backup_system.php`

### Emergency Contacts
- System Administrator: [Your Contact]
- Database Administrator: [DB Contact]
- Security Officer: [Security Contact]

---

**Remember**: These stability rules are not optional - they are essential for maintaining a secure, reliable, and performant system. Implement them systematically and monitor compliance regularly.