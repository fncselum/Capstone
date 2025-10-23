# System Stability Rules & Guidelines

## 🚨 CRITICAL STABILITY RULES

### 1. Database Connection Management
- **NEVER** use `die()` or `exit()` for database connection failures in production
- **ALWAYS** implement graceful error handling with user-friendly messages
- **MUST** use prepared statements for all database queries
- **REQUIRED** to close database connections properly
- **MANDATORY** to validate database connection before executing queries

### 2. Input Validation & Security
- **ALL** user inputs MUST be validated and sanitized
- **NEVER** trust user input from `$_POST`, `$_GET`, or `$_SESSION`
- **ALWAYS** use `htmlspecialchars()` for output escaping
- **REQUIRED** to validate data types and ranges
- **MUST** implement CSRF protection for forms

### 3. Error Handling Standards
- **NO** `die()` statements in production code
- **ALL** errors must be logged to a secure log file
- **USER-FRIENDLY** error messages only (no technical details)
- **GRACEFUL** degradation when services are unavailable
- **CONSISTENT** error response format across all endpoints

### 4. Session Management
- **SECURE** session configuration required
- **VALIDATE** session data before use
- **CLEAN** up session variables after use
- **TIMEOUT** sessions appropriately
- **PROTECT** against session hijacking

### 5. File Upload Security
- **VALIDATE** file types and sizes
- **SCAN** uploaded files for malware
- **STORE** files outside web root when possible
- **GENERATE** unique filenames
- **LIMIT** upload size and frequency

## 🔧 IMPLEMENTATION GUIDELINES

### Database Operations
```php
// ✅ CORRECT - Graceful error handling
try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    // ... operations
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    // Show user-friendly message
    $error_message = "System temporarily unavailable. Please try again later.";
}

// ❌ WRONG - Hard failure
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
```

### Input Validation
```php
// ✅ CORRECT - Comprehensive validation
function validateEquipmentData($data) {
    $errors = [];
    
    if (empty($data['name']) || strlen($data['name']) > 100) {
        $errors[] = "Equipment name is required and must be less than 100 characters";
    }
    
    if (!is_numeric($data['quantity']) || $data['quantity'] < 0) {
        $errors[] = "Quantity must be a positive number";
    }
    
    return $errors;
}

// ❌ WRONG - No validation
$name = $_POST['name'];
$quantity = $_POST['quantity'];
```

### Error Logging
```php
// ✅ CORRECT - Proper error logging
function logError($message, $context = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    error_log(json_encode($logEntry), 3, 'logs/system_errors.log');
}
```

## 🛡️ SECURITY REQUIREMENTS

### 1. Authentication & Authorization
- **VERIFY** user permissions for every action
- **VALIDATE** session integrity
- **IMPLEMENT** proper logout functionality
- **PROTECT** admin functions with additional verification

### 2. Data Protection
- **ENCRYPT** sensitive data at rest
- **HASH** passwords with proper salt
- **SANITIZE** all output
- **VALIDATE** file uploads thoroughly

### 3. SQL Injection Prevention
- **USE** prepared statements exclusively
- **NEVER** concatenate user input into SQL
- **VALIDATE** all parameters
- **ESCAPE** special characters properly

## 📊 MONITORING & HEALTH CHECKS

### System Health Indicators
1. **Database connectivity**
2. **File system permissions**
3. **Memory usage**
4. **Disk space**
5. **Active sessions**
6. **Error rates**

### Performance Thresholds
- **Response time**: < 2 seconds
- **Memory usage**: < 80% of available
- **Disk space**: > 20% free
- **Error rate**: < 1% of requests

## 🚀 DEPLOYMENT RULES

### Pre-deployment Checklist
- [ ] All error handling implemented
- [ ] Input validation in place
- [ ] Security measures active
- [ ] Database queries optimized
- [ ] Logging configured
- [ ] Backup procedures tested

### Post-deployment Monitoring
- [ ] Monitor error logs
- [ ] Check system performance
- [ ] Verify all functions work
- [ ] Test error scenarios
- [ ] Validate security measures

## 🔄 MAINTENANCE PROCEDURES

### Daily Tasks
- Check error logs
- Monitor system performance
- Verify backup integrity
- Review security logs

### Weekly Tasks
- Update security patches
- Clean old log files
- Optimize database
- Review user activity

### Monthly Tasks
- Full system backup
- Security audit
- Performance analysis
- Code review

## ⚠️ EMERGENCY PROCEDURES

### System Down
1. Check error logs
2. Verify database connectivity
3. Restart services if needed
4. Restore from backup if necessary
5. Notify users of status

### Security Breach
1. Immediately disable affected accounts
2. Change all passwords
3. Review access logs
4. Patch vulnerabilities
5. Notify stakeholders

## 📋 CODE REVIEW CHECKLIST

### Before Any Code Goes Live
- [ ] Input validation implemented
- [ ] Error handling in place
- [ ] Security measures active
- [ ] Database queries safe
- [ ] Logging configured
- [ ] User experience tested
- [ ] Performance acceptable
- [ ] Documentation updated

---

**Remember: Stability is not optional. Every line of code should contribute to system reliability and user trust.**