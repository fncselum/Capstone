# System Stability Implementation Guide

## 🚀 Quick Start Implementation

### Step 1: Include Required Files
Add these includes to the top of your PHP files (after session_start()):

```php
<?php
session_start();

// Include stability components
require_once 'error_handler.php';
require_once 'input_validator.php';

// Initialize error handling
SystemErrorHandler::initialize();
```

### Step 2: Update Database Connections
Replace your current database connection code with this improved version:

```php
// In config/database.php or at the top of your files
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    SystemErrorHandler::logError([
        'type' => 'DATABASE_ERROR',
        'message' => 'Connection failed: ' . $e->getMessage(),
        'file' => __FILE__,
        'line' => __LINE__,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    die('Database connection failed. Please try again later.');
}
```

### Step 3: Implement Input Validation
Replace your current validation with the new validator:

```php
// Before processing form data
$validation = InputValidator::validateEquipmentData($_POST);
if (!$validation['is_valid']) {
    $_SESSION['error_message'] = implode('<br>', $validation['errors']);
    header('Location: admin-equipment-inventory.php');
    exit;
}

// Use sanitized data
$data = $validation['sanitized'];
```

### Step 4: Add Health Monitoring
Add this to your admin dashboard or create a separate monitoring page:

```php
// Check system health
$healthChecker = new SystemHealthChecker();
$healthReport = $healthChecker->runHealthCheck();

if ($healthReport['overall_status'] === 'CRITICAL') {
    // Log critical issues
    foreach ($healthReport['critical_issues'] as $issue) {
        SystemErrorHandler::logError([
            'type' => 'CRITICAL_ISSUE',
            'message' => $issue,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
```

## 📋 File-by-File Implementation

### 1. Update admin-equipment-inventory.php

Add these changes to the top of the file:

```php
<?php
session_start();

// Include stability components
require_once 'error_handler.php';
require_once 'input_validator.php';

// Initialize error handling
SystemErrorHandler::initialize();

// Validate session
if (!InputValidator::validateSession()) {
    header('Location: login.php');
    exit;
}

// Log user action
SystemErrorHandler::logUserAction('VIEW_EQUIPMENT_INVENTORY');
```

### 2. Update add_equipment.php

Replace the validation section with:

```php
// Validate input data
$validation = InputValidator::validateEquipmentData($_POST);
if (!$validation['is_valid']) {
    $_SESSION['error_message'] = implode('<br>', $validation['errors']);
    $_SESSION['form_data'] = $_POST;
    header('Location: admin-equipment-inventory.php');
    exit;
}

// Use sanitized data
$data = $validation['sanitized'];

// Validate file upload if present
if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileValidation = InputValidator::validateFileUpload($_FILES['image_file']);
    if (!$fileValidation['is_valid']) {
        $_SESSION['error_message'] = implode('<br>', $fileValidation['errors']);
        $_SESSION['form_data'] = $_POST;
        header('Location: admin-equipment-inventory.php');
        exit;
    }
}
```

### 3. Update update_equipment_ajax.php

Add error handling wrapper:

```php
<?php
session_start();
header('Content-Type: application/json');

// Include stability components
require_once 'error_handler.php';
require_once 'input_validator.php';

// Initialize error handling
SystemErrorHandler::initialize();

try {
    // Your existing code here...
    
} catch (Exception $e) {
    SystemErrorHandler::logError([
        'type' => 'AJAX_ERROR',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
```

## 🔧 Configuration Updates

### 1. Update PHP Configuration
Add these settings to your php.ini or .htaccess:

```ini
; Error handling
display_errors = Off
log_errors = On
error_log = /path/to/your/logs/php_errors.log

; Security
register_globals = Off
magic_quotes_gpc = Off
allow_url_fopen = Off
allow_url_include = Off

; Session security
session.cookie_httponly = 1
session.use_only_cookies = 1
session.cookie_secure = 1  ; Only if using HTTPS
```

### 2. Create Logs Directory
```bash
mkdir -p /path/to/your/project/logs
chmod 755 /path/to/your/project/logs
```

### 3. Set Up Cron Jobs
Add these to your crontab for automated monitoring:

```bash
# Daily health check at 2 AM
0 2 * * * /usr/bin/php /path/to/your/project/admin/system_health_check.php >> /path/to/your/project/logs/health_check.log

# Clean old log files weekly
0 3 * * 0 find /path/to/your/project/logs -name "*.log" -mtime +30 -delete
```

## 🛡️ Security Enhancements

### 1. Add CSRF Protection
Add this to forms:

```php
// Generate CSRF token
$csrfToken = InputValidator::generateCSRFToken();
```

```html
<!-- In your forms -->
<input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
```

### 2. Validate CSRF Token
Add this to form processing:

```php
if (!InputValidator::validateCSRFToken($_POST['csrf_token'])) {
    SystemErrorHandler::logSecurityEvent('CSRF_ATTACK_ATTEMPT', 'Invalid CSRF token');
    die('Invalid request');
}
```

### 3. Add Rate Limiting
Create a simple rate limiter:

```php
class RateLimiter {
    public static function checkLimit($action, $limit = 10, $window = 300) {
        $key = $action . '_' . $_SERVER['REMOTE_ADDR'];
        $file = sys_get_temp_dir() . '/rate_limit_' . md5($key);
        
        $attempts = [];
        if (file_exists($file)) {
            $attempts = json_decode(file_get_contents($file), true) ?: [];
        }
        
        // Remove old attempts
        $attempts = array_filter($attempts, function($time) use ($window) {
            return time() - $time < $window;
        });
        
        if (count($attempts) >= $limit) {
            return false;
        }
        
        $attempts[] = time();
        file_put_contents($file, json_encode($attempts));
        return true;
    }
}
```

## 📊 Monitoring Dashboard

### 1. Create Health Check Page
Create `admin/system_monitor.php`:

```php
<?php
session_start();
require_once 'error_handler.php';
require_once 'input_validator.php';

if (!InputValidator::validateSession()) {
    header('Location: login.php');
    exit;
}

$healthChecker = new SystemHealthChecker();
$report = $healthChecker->runHealthCheck();
?>

<!DOCTYPE html>
<html>
<head>
    <title>System Monitor</title>
    <style>
        .status-healthy { color: green; }
        .status-warning { color: orange; }
        .status-critical { color: red; }
        .health-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>System Health Monitor</h1>
    
    <div class="health-section">
        <h2>Overall Status: <span class="status-<?= strtolower($report['overall_status']) ?>"><?= $report['overall_status'] ?></span></h2>
        <p>Last checked: <?= $report['timestamp'] ?></p>
    </div>
    
    <?php if (!empty($report['critical_issues'])): ?>
    <div class="health-section">
        <h3>Critical Issues</h3>
        <ul>
            <?php foreach ($report['critical_issues'] as $issue): ?>
                <li><?= htmlspecialchars($issue) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($report['warnings'])): ?>
    <div class="health-section">
        <h3>Warnings</h3>
        <ul>
            <?php foreach ($report['warnings'] as $warning): ?>
                <li><?= htmlspecialchars($warning) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="health-section">
        <h3>System Information</h3>
        <ul>
            <?php foreach ($report['health_status'] as $key => $value): ?>
                <li><strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($value) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="health-section">
        <h3>Recommendations</h3>
        <ul>
            <?php foreach ($report['recommendations'] as $recommendation): ?>
                <li><?= htmlspecialchars($recommendation) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</body>
</html>
```

## 🔄 Testing Your Implementation

### 1. Test Error Handling
Try these scenarios:
- Submit invalid data
- Upload invalid files
- Access pages without proper authentication
- Check error logs

### 2. Test Health Monitoring
- Run the health check script
- Check system resources
- Verify log file creation

### 3. Test Security Features
- Try CSRF attacks
- Test rate limiting
- Verify input sanitization

## 📈 Performance Monitoring

### 1. Add Performance Tracking
```php
class PerformanceTracker {
    private static $startTime;
    
    public static function start() {
        self::$startTime = microtime(true);
    }
    
    public static function end($operation) {
        $duration = microtime(true) - self::$startTime;
        error_log("PERFORMANCE: $operation took " . round($duration, 4) . " seconds");
        return $duration;
    }
}
```

### 2. Monitor Database Queries
```php
// Before database operations
PerformanceTracker::start();

// Your database operation
$result = $pdo->query($sql);

// After database operations
$duration = PerformanceTracker::end('Database Query');
if ($duration > 1.0) {
    error_log("SLOW QUERY: Query took $duration seconds");
}
```

## 🚨 Emergency Procedures

### 1. System Down Response
1. Check error logs immediately
2. Run health check script
3. Verify database connectivity
4. Check disk space and memory
5. Restart services if needed

### 2. Data Corruption Response
1. Stop all write operations
2. Restore from latest backup
3. Verify data integrity
4. Test system functionality
5. Resume operations gradually

### 3. Security Breach Response
1. Isolate affected systems
2. Change all passwords
3. Review access logs
4. Implement additional security
5. Notify stakeholders

---

## ✅ Implementation Checklist

- [ ] Include error_handler.php in all files
- [ ] Include input_validator.php in all files
- [ ] Update database connections to use PDO
- [ ] Implement input validation for all forms
- [ ] Add CSRF protection to forms
- [ ] Set up error logging
- [ ] Create health monitoring page
- [ ] Configure PHP security settings
- [ ] Set up automated monitoring
- [ ] Test all functionality
- [ ] Document changes
- [ ] Train users on new features

---

*This implementation guide should be followed step by step to ensure system stability and security.*