# Equipment Management System - Stability Rules

## Overview
This document establishes critical stability rules to ensure smooth operation and prevent system breakage of the Equipment Management System. These rules are based on analysis of the current codebase and industry best practices.

## 🚨 CRITICAL STABILITY RULES

### 1. Database Connection Management
**RULE**: Always use proper error handling and connection pooling
- ✅ **DO**: Use try-catch blocks for all database operations
- ✅ **DO**: Implement connection timeouts and retry logic
- ❌ **NEVER**: Use `@` operator to suppress database errors
- ❌ **NEVER**: Use `die()` or `exit()` in production code

**Current Issues Found**:
- Multiple database connection patterns (mysqli and PDO mixed)
- Error suppression with `@` operator in several files
- Inconsistent error handling across files

### 2. Input Validation & Sanitization
**RULE**: Validate and sanitize ALL user inputs before processing
- ✅ **DO**: Use `htmlspecialchars()` for output escaping
- ✅ **DO**: Use prepared statements for database queries
- ✅ **DO**: Validate data types and ranges
- ❌ **NEVER**: Use `$_POST` or `$_GET` directly without validation
- ❌ **NEVER**: Use `mysqli_real_escape_string()` as primary security measure

**Current Issues Found**:
- Direct use of `$_POST` variables without proper validation
- Inconsistent input sanitization patterns
- Missing CSRF protection

### 3. Session Security
**RULE**: Implement secure session management
- ✅ **DO**: Use `session_regenerate_id()` after login
- ✅ **DO**: Set secure session cookie parameters
- ✅ **DO**: Implement session timeout
- ❌ **NEVER**: Store sensitive data in session without encryption
- ❌ **NEVER**: Use session data without validation

**Current Issues Found**:
- Basic session handling without security enhancements
- Missing session timeout implementation
- No session hijacking protection

### 4. Error Handling & Logging
**RULE**: Implement comprehensive error handling and logging
- ✅ **DO**: Use try-catch blocks for all operations
- ✅ **DO**: Log errors to files with timestamps
- ✅ **DO**: Provide user-friendly error messages
- ❌ **NEVER**: Expose system errors to end users
- ❌ **NEVER**: Ignore database errors

**Current Issues Found**:
- Inconsistent error handling patterns
- Missing error logging system
- Some files use `die()` for error handling

### 5. File Upload Security
**RULE**: Secure file upload handling
- ✅ **DO**: Validate file types and sizes
- ✅ **DO**: Store uploads outside web root when possible
- ✅ **DO**: Use unique filenames
- ❌ **NEVER**: Trust user-provided file names
- ❌ **NEVER**: Execute uploaded files

**Current Issues Found**:
- Basic file upload without comprehensive validation
- Files stored in web-accessible directory

### 6. Transaction Integrity
**RULE**: Maintain data consistency with database transactions
- ✅ **DO**: Use database transactions for multi-step operations
- ✅ **DO**: Implement rollback on failures
- ✅ **DO**: Lock records during updates
- ❌ **NEVER**: Perform multi-table updates without transactions
- ❌ **NEVER**: Ignore transaction failures

**Current Issues Found**:
- Some transaction operations lack proper rollback handling
- Missing record locking in some update operations

## 🔧 IMPLEMENTATION PRIORITIES

### Priority 1 (Critical - Fix Immediately)
1. **Standardize Database Connections**
   - Create single database connection handler
   - Remove `@` error suppression
   - Implement proper error logging

2. **Input Validation Framework**
   - Create validation functions for all input types
   - Implement CSRF protection
   - Add rate limiting for API endpoints

3. **Session Security Enhancement**
   - Implement secure session configuration
   - Add session timeout
   - Implement session hijacking protection

### Priority 2 (High - Fix Within 1 Week)
1. **Error Handling Standardization**
   - Create centralized error handler
   - Implement error logging system
   - Replace all `die()` statements with proper error handling

2. **File Upload Security**
   - Implement comprehensive file validation
   - Add virus scanning capability
   - Secure file storage location

### Priority 3 (Medium - Fix Within 2 Weeks)
1. **Transaction Management**
   - Audit all multi-step operations
   - Implement proper transaction handling
   - Add deadlock detection

2. **Performance Optimization**
   - Implement database query optimization
   - Add caching where appropriate
   - Optimize file upload handling

## 📋 DAILY MAINTENANCE CHECKLIST

### Before Any Code Changes:
- [ ] Backup database
- [ ] Test on development environment
- [ ] Review error logs
- [ ] Check system status

### After Any Code Changes:
- [ ] Test all critical functions
- [ ] Verify database integrity
- [ ] Check error logs
- [ ] Test user workflows
- [ ] Verify security measures

### Weekly Maintenance:
- [ ] Review error logs for patterns
- [ ] Check database performance
- [ ] Verify backup integrity
- [ ] Update security measures
- [ ] Test disaster recovery procedures

## 🚫 FORBIDDEN PRACTICES

### Never Do These:
1. **Direct Database Queries**: Never use `$conn->query()` with user input
2. **Error Suppression**: Never use `@` operator for database operations
3. **Unvalidated Inputs**: Never use `$_POST` or `$_GET` without validation
4. **Hardcoded Credentials**: Never put database credentials in code
5. **Debug Code in Production**: Never leave `var_dump()` or `print_r()` in production
6. **Insecure File Operations**: Never trust user-provided file paths
7. **Missing Error Handling**: Never ignore potential errors
8. **Session Hijacking**: Never use predictable session IDs

## 🔍 MONITORING & ALERTS

### System Health Indicators:
- Database connection success rate
- Error log frequency
- Response time metrics
- File upload success rate
- Session timeout frequency

### Alert Thresholds:
- Database errors > 5 per hour
- Response time > 3 seconds
- Error rate > 1% of requests
- Failed logins > 10 per hour

## 📚 EMERGENCY PROCEDURES

### If System Goes Down:
1. Check error logs immediately
2. Verify database connectivity
3. Check file permissions
4. Review recent changes
5. Restore from backup if necessary

### If Data Corruption Detected:
1. Stop all write operations
2. Assess damage scope
3. Restore from latest clean backup
4. Replay transaction logs if available
5. Verify data integrity

## 🎯 SUCCESS METRICS

### Stability Indicators:
- 99.9% uptime
- < 1% error rate
- < 2 second average response time
- Zero data loss incidents
- < 5 minutes recovery time

---

**Last Updated**: $(date)
**Version**: 1.0
**Next Review**: $(date -d "+1 month")

Remember: These rules are not optional. They are essential for maintaining system stability and preventing data loss or security breaches.