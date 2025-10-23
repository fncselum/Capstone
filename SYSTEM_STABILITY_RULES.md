# System Stability Rules & Guidelines

## 🛡️ Core Stability Principles

### 1. **Database Integrity Rules**
- **Rule 1.1**: Always use prepared statements for all database queries
- **Rule 1.2**: Implement database transactions for multi-table operations
- **Rule 1.3**: Never use direct string concatenation in SQL queries
- **Rule 1.4**: Always validate and sanitize input data before database operations
- **Rule 1.5**: Implement proper foreign key constraints and referential integrity

### 2. **Error Handling Standards**
- **Rule 2.1**: All database operations must be wrapped in try-catch blocks
- **Rule 2.2**: Never expose raw database errors to users
- **Rule 2.3**: Log all errors with sufficient context for debugging
- **Rule 2.4**: Implement graceful degradation for non-critical failures
- **Rule 2.5**: Always provide meaningful error messages to users

### 3. **Input Validation Rules**
- **Rule 3.1**: Validate all user inputs on both client and server side
- **Rule 3.2**: Implement strict data type validation
- **Rule 3.3**: Enforce length limits on all text inputs
- **Rule 3.4**: Validate file uploads (type, size, content)
- **Rule 3.5**: Sanitize all outputs to prevent XSS attacks

### 4. **Session & Authentication Security**
- **Rule 4.1**: Always check session validity before processing requests
- **Rule 4.2**: Implement proper logout functionality that clears all session data
- **Rule 4.3**: Use secure session configuration
- **Rule 4.4**: Implement CSRF protection for state-changing operations
- **Rule 4.5**: Never trust client-side data for security decisions

### 5. **File Upload Safety**
- **Rule 5.1**: Validate file types using MIME type checking
- **Rule 5.2**: Enforce file size limits (max 5MB for images)
- **Rule 5.3**: Generate unique filenames to prevent conflicts
- **Rule 5.4**: Store files outside web root when possible
- **Rule 5.5**: Clean up orphaned files when records are deleted

### 6. **Data Consistency Rules**
- **Rule 6.1**: Keep equipment and inventory tables synchronized
- **Rule 6.2**: Implement atomic operations for related data updates
- **Rule 6.3**: Use database triggers for critical data consistency
- **Rule 6.4**: Regular data integrity checks and cleanup
- **Rule 6.5**: Implement soft deletes for audit trails

### 7. **Performance & Resource Management**
- **Rule 7.1**: Implement connection pooling for database connections
- **Rule 7.2**: Use pagination for large data sets
- **Rule 7.3**: Implement caching for frequently accessed data
- **Rule 7.4**: Monitor and limit resource usage
- **Rule 7.5**: Implement query optimization and indexing

### 8. **System Monitoring & Health Checks**
- **Rule 8.1**: Implement system health monitoring
- **Rule 8.2**: Log all critical operations for audit trails
- **Rule 8.3**: Monitor database performance and connection status
- **Rule 8.4**: Implement automated backup procedures
- **Rule 8.5**: Set up alerting for critical system failures

## 🚨 Critical Failure Prevention

### Database Operations
```php
// ✅ CORRECT: Use prepared statements
$stmt = $pdo->prepare("SELECT * FROM equipment WHERE id = :id");
$stmt->execute([':id' => $id]);

// ❌ WRONG: Direct string concatenation
$sql = "SELECT * FROM equipment WHERE id = " . $id;
```

### Error Handling
```php
// ✅ CORRECT: Proper error handling
try {
    $result = $pdo->query($sql);
    if (!$result) {
        throw new Exception("Query failed");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    throw new Exception("Database operation failed");
}
```

### Input Validation
```php
// ✅ CORRECT: Comprehensive validation
function validateEquipmentData($data) {
    $errors = [];
    
    if (empty($data['name']) || strlen($data['name']) > 255) {
        $errors[] = "Name is required and must be less than 255 characters";
    }
    
    if (empty($data['rfid_tag']) || !preg_match('/^[A-Za-z0-9_-]+$/', $data['rfid_tag'])) {
        $errors[] = "RFID tag is required and must contain only alphanumeric characters";
    }
    
    if (!is_numeric($data['quantity']) || $data['quantity'] < 0) {
        $errors[] = "Quantity must be a non-negative number";
    }
    
    return $errors;
}
```

## 🔧 Implementation Checklist

### Phase 1: Critical Security & Validation
- [ ] Implement comprehensive input validation
- [ ] Add CSRF protection
- [ ] Secure file upload handling
- [ ] Improve error handling and logging

### Phase 2: Database Integrity
- [ ] Add foreign key constraints
- [ ] Implement proper transactions
- [ ] Add data consistency checks
- [ ] Create database backup procedures

### Phase 3: Performance & Monitoring
- [ ] Implement connection pooling
- [ ] Add system health monitoring
- [ ] Create performance metrics
- [ ] Set up automated alerts

### Phase 4: Maintenance & Recovery
- [ ] Create data cleanup procedures
- [ ] Implement soft delete functionality
- [ ] Add system recovery procedures
- [ ] Create maintenance documentation

## 📊 System Health Indicators

### Green (Healthy)
- All database connections successful
- No critical errors in logs
- Response times under 2 seconds
- All validations passing

### Yellow (Warning)
- Occasional connection timeouts
- Some validation failures
- Response times 2-5 seconds
- Minor errors in logs

### Red (Critical)
- Database connection failures
- Multiple validation failures
- Response times over 5 seconds
- Critical errors requiring immediate attention

## 🚀 Quick Wins for Immediate Stability

1. **Add input validation to all forms**
2. **Implement proper error logging**
3. **Add database transaction support**
4. **Create system health check endpoint**
5. **Implement proper file upload validation**

## 📝 Emergency Procedures

### If System Becomes Unstable
1. Check error logs immediately
2. Verify database connectivity
3. Check file system permissions
4. Review recent changes
5. Implement rollback if necessary

### Data Recovery
1. Restore from latest backup
2. Check data integrity
3. Verify all relationships
4. Test critical functions
5. Monitor system stability

---

**Remember**: These rules are not optional - they are essential for maintaining a stable, secure, and reliable system. Implement them systematically and monitor compliance regularly.