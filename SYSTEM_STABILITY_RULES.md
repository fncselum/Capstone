# Equipment Kiosk System Stability Rules

## Overview
This document establishes critical stability rules to ensure smooth operation and prevent system breakage of the Equipment Kiosk system.

## 🚨 CRITICAL STABILITY RULES

### 1. Database Connection Management
**RULE**: All database connections MUST use consistent error handling and connection pooling.

**Current Issues Found**:
- Mixed usage of `mysqli` and `PDO` connections
- Inconsistent error handling across files
- No connection pooling or reuse

**Required Actions**:
- Standardize on PDO for all new code
- Implement connection reuse pattern
- Add proper connection timeout handling
- Use prepared statements for ALL queries

### 2. Input Validation & Sanitization
**RULE**: ALL user inputs MUST be validated and sanitized before database operations.

**Current Issues Found**:
- Direct use of `$_POST`, `$_GET`, `$_REQUEST` without validation
- Missing CSRF protection
- Inconsistent input sanitization

**Required Actions**:
- Implement CSRF tokens for all forms
- Validate all inputs against expected types and ranges
- Use `htmlspecialchars()` for output escaping
- Implement rate limiting for API endpoints

### 3. Error Handling Standards
**RULE**: All errors MUST be logged and handled gracefully without exposing system internals.

**Current Issues Found**:
- Inconsistent error handling patterns
- Some errors exposed to users
- Missing error logging in critical areas

**Required Actions**:
- Implement centralized error logging
- Use try-catch blocks for all database operations
- Return user-friendly error messages
- Log detailed errors to system logs only

### 4. Session Security
**RULE**: All session operations MUST follow security best practices.

**Current Issues Found**:
- Good session security in login.php
- Inconsistent session validation across files

**Required Actions**:
- Implement session timeout handling
- Add session regeneration on privilege escalation
- Validate session data integrity
- Implement proper logout cleanup

### 5. Transaction Management
**RULE**: All multi-table operations MUST use database transactions.

**Current Issues Found**:
- Good transaction usage in borrow.php and transaction-approval.php
- Some operations lack proper transaction handling

**Required Actions**:
- Wrap all multi-step operations in transactions
- Implement proper rollback on failures
- Add transaction timeout handling
- Log transaction failures

## 🔧 IMPLEMENTATION GUIDELINES

### Database Operations
```php
// ✅ CORRECT PATTERN
try {
    $pdo->beginTransaction();
    
    // Multiple database operations
    $stmt1 = $pdo->prepare("SELECT ...");
    $stmt1->execute([$param1]);
    
    $stmt2 = $pdo->prepare("INSERT ...");
    $stmt2->execute([$param2]);
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback();
    error_log("Database error: " . $e->getMessage());
    // Return user-friendly error
}
```

### Input Validation
```php
// ✅ CORRECT PATTERN
function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        if ($rule['required'] && empty($data[$field])) {
            $errors[] = "{$field} is required";
        }
        
        if (isset($data[$field]) && $rule['type'] === 'int') {
            if (!is_numeric($data[$field]) || $data[$field] < 0) {
                $errors[] = "{$field} must be a positive number";
            }
        }
    }
    
    return $errors;
}
```

### Error Handling
```php
// ✅ CORRECT PATTERN
try {
    // Operation that might fail
    $result = performOperation();
    
    if (!$result) {
        throw new Exception("Operation failed");
    }
    
    return ['success' => true, 'data' => $result];
    
} catch (Exception $e) {
    error_log("Operation failed: " . $e->getMessage());
    return ['success' => false, 'message' => 'Operation failed. Please try again.'];
}
```

## 🛡️ SECURITY REQUIREMENTS

### 1. Authentication & Authorization
- All admin pages MUST verify `$_SESSION['admin_logged_in']`
- All user pages MUST verify `$_SESSION['user_id']`
- Implement role-based access control
- Add session timeout (5 minutes for users, 30 minutes for admins)

### 2. Data Protection
- Sanitize ALL output with `htmlspecialchars()`
- Use prepared statements for ALL database queries
- Implement CSRF protection on all forms
- Validate file uploads (type, size, content)

### 3. API Security
- Rate limit API endpoints (max 100 requests per minute per IP)
- Validate all API inputs
- Return consistent JSON responses
- Log all API access attempts

## 📊 MONITORING & LOGGING

### Required Logs
1. **Error Logs**: All exceptions and errors
2. **Access Logs**: User logins, page access
3. **Transaction Logs**: All borrow/return operations
4. **Security Logs**: Failed login attempts, suspicious activity

### Log Format
```
[YYYY-MM-DD HH:MM:SS] [LEVEL] [USER_ID] [IP] MESSAGE
```

## 🔄 SYSTEM MAINTENANCE

### Daily Checks
- [ ] Verify database connectivity
- [ ] Check error logs for critical issues
- [ ] Monitor disk space usage
- [ ] Verify backup integrity

### Weekly Checks
- [ ] Review security logs
- [ ] Update system dependencies
- [ ] Clean old log files
- [ ] Test critical user flows

### Monthly Checks
- [ ] Full system backup
- [ ] Security audit
- [ ] Performance optimization
- [ ] Update documentation

## 🚫 FORBIDDEN PRACTICES

### NEVER DO THESE:
1. ❌ Use `mysql_*` functions (deprecated)
2. ❌ Execute raw SQL without prepared statements
3. ❌ Display raw error messages to users
4. ❌ Store passwords in plain text
5. ❌ Trust user input without validation
6. ❌ Skip transaction handling for multi-step operations
7. ❌ Use `@` error suppression operator
8. ❌ Allow unlimited file uploads
9. ❌ Skip CSRF protection on forms
10. ❌ Log sensitive data (passwords, personal info)

## 📋 CODE REVIEW CHECKLIST

Before deploying any changes, verify:

- [ ] All database queries use prepared statements
- [ ] Input validation is implemented
- [ ] Error handling is proper
- [ ] CSRF protection is in place
- [ ] Output is properly escaped
- [ ] Transactions are used where needed
- [ ] Logging is implemented
- [ ] Security headers are set
- [ ] Session handling is secure
- [ ] File uploads are validated

## 🎯 PRIORITY FIXES

### High Priority (Fix Immediately)
1. Standardize database connection handling
2. Implement CSRF protection on all forms
3. Add proper input validation to all endpoints
4. Implement centralized error logging

### Medium Priority (Fix This Week)
1. Add rate limiting to API endpoints
2. Implement proper session timeout handling
3. Add transaction handling to remaining operations
4. Improve error messages for users

### Low Priority (Fix This Month)
1. Implement comprehensive logging system
2. Add performance monitoring
3. Create automated backup system
4. Implement system health checks

---

**Remember**: These rules are not optional. Following them ensures system stability, security, and maintainability. Any code that violates these rules should be fixed immediately.