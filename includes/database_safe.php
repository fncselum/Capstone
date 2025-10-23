<?php
/**
 * Safe Database Operations
 * Provides secure and consistent database operations for the Equipment Kiosk system
 */

// Prevent direct access
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Safe database connection with error handling
 */
function getSafeDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $host = 'localhost';
            $dbname = 'capstone';
            $username = 'root';
            $password = '';
            
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $pdo = new PDO($dsn, $username, $password, $options);
            
        } catch (PDOException $e) {
            logDatabaseError('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed. Please try again later.');
        }
    }
    
    return $pdo;
}

/**
 * Safe select operation
 */
function safeSelect($query, $params = [], $fetch_mode = PDO::FETCH_ASSOC) {
    try {
        $pdo = getSafeDatabaseConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll($fetch_mode);
    } catch (PDOException $e) {
        logDatabaseError('Select operation failed: ' . $e->getMessage(), $query, $params);
        throw new Exception('Database query failed. Please try again later.');
    }
}

/**
 * Safe select single row
 */
function safeSelectOne($query, $params = []) {
    try {
        $pdo = getSafeDatabaseConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDatabaseError('Select one operation failed: ' . $e->getMessage(), $query, $params);
        throw new Exception('Database query failed. Please try again later.');
    }
}

/**
 * Safe insert operation
 */
function safeInsert($query, $params = []) {
    try {
        $pdo = getSafeDatabaseConnection();
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute($params);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
    } catch (PDOException $e) {
        logDatabaseError('Insert operation failed: ' . $e->getMessage(), $query, $params);
        throw new Exception('Database insert failed. Please try again later.');
    }
}

/**
 * Safe update operation
 */
function safeUpdate($query, $params = []) {
    try {
        $pdo = getSafeDatabaseConnection();
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute($params);
        
        return $result ? $stmt->rowCount() : 0;
    } catch (PDOException $e) {
        logDatabaseError('Update operation failed: ' . $e->getMessage(), $query, $params);
        throw new Exception('Database update failed. Please try again later.');
    }
}

/**
 * Safe delete operation
 */
function safeDelete($query, $params = []) {
    try {
        $pdo = getSafeDatabaseConnection();
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute($params);
        
        return $result ? $stmt->rowCount() : 0;
    } catch (PDOException $e) {
        logDatabaseError('Delete operation failed: ' . $e->getMessage(), $query, $params);
        throw new Exception('Database delete failed. Please try again later.');
    }
}

/**
 * Safe transaction wrapper
 */
function safeTransaction($operations) {
    $pdo = getSafeDatabaseConnection();
    
    try {
        $pdo->beginTransaction();
        
        $results = [];
        foreach ($operations as $operation) {
            if (is_callable($operation)) {
                $results[] = $operation($pdo);
            } else {
                throw new Exception('Invalid operation in transaction');
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'results' => $results];
        
    } catch (Exception $e) {
        $pdo->rollback();
        logDatabaseError('Transaction failed: ' . $e->getMessage());
        throw new Exception('Transaction failed. Please try again later.');
    }
}

/**
 * Check if record exists
 */
function recordExists($table, $conditions, $params = []) {
    $where_clause = '';
    if (!empty($conditions)) {
        $where_parts = [];
        foreach ($conditions as $column => $value) {
            $where_parts[] = "$column = :$column";
            $params[":$column"] = $value;
        }
        $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
    }
    
    $query = "SELECT COUNT(*) as count FROM $table $where_clause LIMIT 1";
    $result = safeSelectOne($query, $params);
    
    return $result && $result['count'] > 0;
}

/**
 * Get record count
 */
function getRecordCount($table, $conditions = [], $params = []) {
    $where_clause = '';
    if (!empty($conditions)) {
        $where_parts = [];
        foreach ($conditions as $column => $value) {
            $where_parts[] = "$column = :$column";
            $params[":$column"] = $value;
        }
        $where_clause = 'WHERE ' . implode(' AND ', $where_parts);
    }
    
    $query = "SELECT COUNT(*) as count FROM $table $where_clause";
    $result = safeSelectOne($query, $params);
    
    return $result ? (int)$result['count'] : 0;
}

/**
 * Paginated select
 */
function safeSelectPaginated($query, $params = [], $page = 1, $limit = 20) {
    // Validate pagination parameters
    $pagination_errors = validatePaginationParams($page, $limit);
    if (!empty($pagination_errors)) {
        throw new Exception('Invalid pagination parameters: ' . implode(', ', $pagination_errors));
    }
    
    $offset = ($page - 1) * $limit;
    
    // Add LIMIT and OFFSET to query
    $paginated_query = $query . " LIMIT $limit OFFSET $offset";
    
    try {
        $data = safeSelect($paginated_query, $params);
        
        // Get total count (remove ORDER BY and LIMIT for count query)
        $count_query = preg_replace('/ORDER BY.*$/i', '', $query);
        $count_query = "SELECT COUNT(*) as total FROM ($count_query) as count_table";
        $count_result = safeSelectOne($count_query, $params);
        $total = $count_result ? (int)$count_result['total'] : 0;
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit),
                'has_next' => $page < ceil($total / $limit),
                'has_prev' => $page > 1
            ]
        ];
        
    } catch (Exception $e) {
        logDatabaseError('Paginated select failed: ' . $e->getMessage(), $query, $params);
        throw new Exception('Database query failed. Please try again later.');
    }
}

/**
 * Safe equipment operations
 */
class EquipmentOperations {
    
    public static function getEquipmentById($id) {
        $query = "SELECT e.*, c.name as category_name, i.* 
                  FROM equipment e 
                  LEFT JOIN categories c ON e.category_id = c.id 
                  LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id 
                  WHERE e.id = :id";
        return safeSelectOne($query, [':id' => $id]);
    }
    
    public static function getAvailableEquipment($category_id = null, $limit = 50) {
        $where_clause = "WHERE e.quantity > 0 AND (i.available_quantity IS NOT NULL AND i.available_quantity > 0)";
        $params = [];
        
        if ($category_id) {
            $where_clause .= " AND e.category_id = :category_id";
            $params[':category_id'] = $category_id;
        }
        
        $query = "SELECT e.*, c.name as category_name, i.available_quantity, i.availability_status
                  FROM equipment e 
                  LEFT JOIN categories c ON e.category_id = c.id 
                  LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id 
                  $where_clause
                  ORDER BY e.name ASC
                  LIMIT $limit";
        
        return safeSelect($query, $params);
    }
    
    public static function updateInventory($rfid_tag, $quantity_change, $operation = 'borrow') {
        $pdo = getSafeDatabaseConnection();
        
        try {
            $pdo->beginTransaction();
            
            // Get current inventory
            $current = safeSelectOne(
                "SELECT available_quantity, borrowed_quantity FROM inventory WHERE equipment_id = :rfid_tag",
                [':rfid_tag' => $rfid_tag]
            );
            
            if (!$current) {
                throw new Exception('Inventory record not found');
            }
            
            $new_available = $current['available_quantity'] - $quantity_change;
            $new_borrowed = $current['borrowed_quantity'] + $quantity_change;
            
            if ($new_available < 0) {
                throw new Exception('Insufficient inventory');
            }
            
            // Update inventory
            $update_query = "UPDATE inventory 
                            SET available_quantity = :available_quantity,
                                borrowed_quantity = :borrowed_quantity,
                                availability_status = CASE
                                    WHEN :available_quantity <= 0 THEN 'Out of Stock'
                                    WHEN :available_quantity <= IFNULL(minimum_stock_level, 1) THEN 'Low Stock'
                                    ELSE 'Available'
                                END,
                                last_updated = NOW()
                            WHERE equipment_id = :rfid_tag";
            
            $result = safeUpdate($update_query, [
                ':available_quantity' => $new_available,
                ':borrowed_quantity' => $new_borrowed,
                ':rfid_tag' => $rfid_tag
            ]);
            
            if ($result === 0) {
                throw new Exception('Failed to update inventory');
            }
            
            $pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
    }
}

/**
 * Safe transaction operations
 */
class TransactionOperations {
    
    public static function createTransaction($data) {
        $query = "INSERT INTO transactions 
                  (user_id, equipment_id, transaction_type, quantity, transaction_date, 
                   expected_return_date, condition_before, status, penalty_applied, notes, 
                   item_size, approval_status, approved_by, approved_at, rejection_reason, 
                   return_review_status, processed_by, created_at, updated_at) 
                  VALUES 
                  (:user_id, :equipment_id, :transaction_type, :quantity, NOW(), 
                   :expected_return_date, :condition_before, :status, :penalty_applied, :notes, 
                   :item_size, :approval_status, :approved_by, :approved_at, :rejection_reason, 
                   :return_review_status, :processed_by, NOW(), NOW())";
        
        return safeInsert($query, $data);
    }
    
    public static function getTransactionById($id) {
        $query = "SELECT t.*, e.name as equipment_name, u.student_id 
                  FROM transactions t 
                  LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag 
                  LEFT JOIN users u ON t.user_id = u.id 
                  WHERE t.id = :id";
        return safeSelectOne($query, [':id' => $id]);
    }
    
    public static function updateTransactionStatus($id, $status, $approval_status = null, $admin_id = null) {
        $query = "UPDATE transactions 
                  SET status = :status, 
                      approval_status = :approval_status,
                      approved_by = :approved_by,
                      approved_at = NOW(),
                      updated_at = NOW()
                  WHERE id = :id";
        
        $params = [
            ':status' => $status,
            ':approval_status' => $approval_status,
            ':approved_by' => $admin_id,
            ':id' => $id
        ];
        
        return safeUpdate($query, $params);
    }
}