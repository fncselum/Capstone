<?php
/**
 * Centralized Database Manager for Equipment Management System
 * Provides secure, consistent database operations with proper error handling
 */

require_once 'error_handler.php';

class DatabaseManager {
    private static $instance = null;
    private $pdo = null;
    private $mysqli = null;
    private $connectionType = 'pdo'; // 'pdo' or 'mysqli'
    
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     */
    private function connect() {
        $host = 'localhost';
        $dbname = 'capstone';
        $username = 'root';
        $password = '';
        
        try {
            // PDO Connection (preferred)
            $this->pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4", 
                $username, 
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            
            // MySQLi Connection (for compatibility)
            $this->mysqli = new mysqli($host, $username, $password, $dbname);
            if ($this->mysqli->connect_error) {
                throw new Exception("MySQLi connection failed: " . $this->mysqli->connect_error);
            }
            $this->mysqli->set_charset("utf8mb4");
            
        } catch (PDOException $e) {
            SystemErrorHandler::handleDatabaseError($e->getMessage());
        } catch (Exception $e) {
            SystemErrorHandler::handleDatabaseError($e->getMessage());
        }
    }
    
    /**
     * Get PDO connection
     */
    public function getPDO() {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }
    
    /**
     * Get MySQLi connection
     */
    public function getMySQLi() {
        if ($this->mysqli === null) {
            $this->connect();
        }
        return $this->mysqli;
    }
    
    /**
     * Execute prepared statement with PDO
     */
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->getPDO()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            SystemErrorHandler::handleDatabaseError($e->getMessage(), $sql);
        }
    }
    
    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert data and return last insert ID
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = ':' . implode(', :', $columns);
        $sql = "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES ($placeholders)";
        
        $this->executeQuery($sql, $data);
        return $this->getPDO()->lastInsertId();
    }
    
    /**
     * Update data
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "`$column` = :$column";
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE $where";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete data
     */
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM `$table` WHERE $where";
        $stmt = $this->executeQuery($sql, $whereParams);
        return $stmt->rowCount();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->getPDO()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->getPDO()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->getPDO()->rollback();
    }
    
    /**
     * Check if transaction is active
     */
    public function inTransaction() {
        return $this->getPDO()->inTransaction();
    }
    
    /**
     * Execute transaction with automatic rollback on error
     */
    public function transaction($callback) {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Validate and sanitize input
     */
    public function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT);
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate required fields
     */
    public function validateRequired($data, $requiredFields) {
        $errors = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[] = SystemErrorHandler::handleValidationError($field, "Field '$field' is required");
            }
        }
        return $errors;
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $result = $this->fetchOne("SELECT 1 as test");
            return $result['test'] === 1;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get database statistics
     */
    public function getStats() {
        try {
            $stats = [];
            
            // Get table sizes
            $tables = $this->fetchAll("
                SELECT 
                    table_name,
                    table_rows,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = 'capstone'
                ORDER BY (data_length + index_length) DESC
            ");
            
            $stats['tables'] = $tables;
            
            // Get connection info
            $stats['connection'] = [
                'pdo_connected' => $this->pdo !== null,
                'mysqli_connected' => $this->mysqli !== null,
                'charset' => $this->mysqli ? $this->mysqli->character_set_name() : 'unknown'
            ];
            
            return $stats;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Close connections
     */
    public function close() {
        $this->pdo = null;
        if ($this->mysqli) {
            $this->mysqli->close();
            $this->mysqli = null;
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}

// Global function for backward compatibility
function getDB() {
    return DatabaseManager::getInstance();
}

// Initialize database manager
$db = DatabaseManager::getInstance();
?>