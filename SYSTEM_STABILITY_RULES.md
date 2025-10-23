# System Stability Rules & Guidelines

## 🚨 CRITICAL STABILITY RULES

### 1. Database Connection Management
- **Rule**: Always use PDO with prepared statements
- **Rule**: Implement connection pooling and retry logic
- **Rule**: Never use `die()` or `exit()` in production - use proper error handling
- **Rule**: Always close database connections explicitly

### 2. Input Validation & Sanitization
- **Rule**: Validate ALL user inputs before processing
- **Rule**: Use whitelist validation for file uploads
- **Rule**: Sanitize output with `htmlspecialchars()` for XSS prevention
- **Rule**: Validate file types using `mime_content_type()`, not just extension

### 3. Error Handling Standards
- **Rule**: Use try-catch blocks for all database operations
- **Rule**: Log errors to file, never display sensitive information to users
- **Rule**: Return JSON responses for AJAX calls
- **Rule**: Implement graceful degradation for non-critical failures

### 4. File Upload Security
- **Rule**: Limit file size (max 5MB)
- **Rule**: Validate MIME types, not just extensions
- **Rule**: Generate unique filenames to prevent conflicts
- **Rule**: Store files outside web root when possible

### 5. Session Management
- **Rule**: Always start sessions with security settings
- **Rule**: Validate session data before use
- **Rule**: Implement session timeout
- **Rule**: Regenerate session ID on login

## 🔧 IMPLEMENTATION GUIDELINES

### Database Operations
```php
// ✅ GOOD: Use prepared statements
$stmt = $pdo->prepare("SELECT * FROM equipment WHERE id = :id");
$stmt->execute([':id' => $id]);

// ❌ BAD: Direct string concatenation
$sql = "SELECT * FROM equipment WHERE id = " . $id;
```

### Error Handling
```php
// ✅ GOOD: Proper error handling
try {
    $result = $pdo->query($sql);
    return $result->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    return ['error' => 'Database operation failed'];
}

// ❌ BAD: Using die()
if (!$result) {
    die("Query failed: " . $pdo->error);
}
```

### Input Validation
```php
// ✅ GOOD: Comprehensive validation
function validateEquipmentData($data) {
    $errors = [];
    
    if (empty($data['name'])) {
        $errors[] = "Name is required";
    }
    
    if (!is_numeric($data['quantity']) || $data['quantity'] < 0) {
        $errors[] = "Quantity must be a positive number";
    }
    
    return $errors;
}
```

## 🛡️ SECURITY RULES

### 1. Authentication & Authorization
- Always verify admin login status
- Implement role-based access control
- Use secure session management
- Implement CSRF protection

### 2. Data Protection
- Encrypt sensitive data
- Use HTTPS in production
- Implement input validation
- Sanitize all outputs

### 3. File Security
- Validate file uploads
- Scan for malware
- Implement file type restrictions
- Use secure file naming

## 📊 MONITORING & LOGGING

### 1. Error Logging
- Log all database errors
- Log authentication failures
- Log file upload attempts
- Monitor system performance

### 2. Health Checks
- Database connectivity
- File system permissions
- Memory usage
- Disk space

## 🔄 BACKUP & RECOVERY

### 1. Database Backups
- Daily automated backups
- Test restore procedures
- Store backups securely
- Document recovery process

### 2. File Backups
- Regular file system backups
- Version control for code
- Document configuration changes

## ⚡ PERFORMANCE RULES

### 1. Database Optimization
- Use indexes on frequently queried columns
- Implement query caching
- Optimize complex queries
- Monitor slow queries

### 2. File Management
- Compress images
- Implement CDN for static files
- Clean up temporary files
- Monitor disk usage

## 🚀 DEPLOYMENT RULES

### 1. Pre-deployment Checks
- Test all functionality
- Verify database migrations
- Check file permissions
- Validate configuration

### 2. Post-deployment Monitoring
- Monitor error logs
- Check system performance
- Verify user functionality
- Test critical workflows

## 📋 MAINTENANCE SCHEDULE

### Daily
- Check error logs
- Monitor disk space
- Verify database connectivity
- Test critical functions

### Weekly
- Review security logs
- Update dependencies
- Clean temporary files
- Backup verification

### Monthly
- Security audit
- Performance review
- Database optimization
- Documentation update

## 🚨 EMERGENCY PROCEDURES

### 1. System Down
- Check error logs
- Verify database status
- Restart services if needed
- Notify users of issues

### 2. Data Corruption
- Stop all write operations
- Restore from backup
- Verify data integrity
- Resume operations

### 3. Security Breach
- Isolate affected systems
- Change all passwords
- Review access logs
- Implement additional security

## 📞 ESCALATION PROCEDURES

### Level 1: Minor Issues
- Log the issue
- Attempt basic fixes
- Document resolution

### Level 2: Major Issues
- Notify system administrator
- Implement workarounds
- Escalate to development team

### Level 3: Critical Issues
- Immediate system shutdown if needed
- Notify all stakeholders
- Implement emergency procedures
- Post-incident review

---

## 🎯 QUICK REFERENCE CHECKLIST

### Before Any Code Change:
- [ ] Backup current system
- [ ] Test in development environment
- [ ] Validate all inputs
- [ ] Check error handling
- [ ] Verify security measures

### After Any Code Change:
- [ ] Test all functionality
- [ ] Monitor error logs
- [ ] Check performance
- [ ] Verify user experience
- [ ] Update documentation

### Daily System Check:
- [ ] Error log review
- [ ] Database connectivity
- [ ] File system status
- [ ] User activity monitoring
- [ ] Performance metrics

---

*This document should be reviewed and updated regularly to ensure system stability and security.*