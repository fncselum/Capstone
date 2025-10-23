<?php
/**
 * Secure Database Connection Handler
 * Implements system stability rules for database operations
 */

class SecureDatabaseConnection {
    private $connection = null;
    private $host;
    private $username;
    private $password;
    private $database;
    private $charset = 'utf8mb4';
    
    public function __construct($host, $username, $password, $database) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
    }
    
    /**
     * Establish database connection with error handling
     */
    public function connect() {
        try {
            // Suppress warnings for connection attempt
            $this->connection = @new mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->connection->connect_error) {
                throw new Exception("Database connection failed: " . $this->connection->connect_error);
            }
            
            // Set charset
            if (!$this->connection->set_charset($this->charset)) {
                throw new Exception("Error setting charset: " . $this->connection->error);
            }
            
            // Set SQL mode for better compatibility
            $this->connection->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            
            return true;
            
        } catch (Exception $e) {
            ErrorHandler::logError("Database connection failed", [
                'error' => $e->getMessage(),
                'host' => $this->host,
                'database' => $this->database
            ]);
            
            return false;
        }
    }
    
    /**
     * Get connection instance
     */
    public function getConnection() {
        if (!$this->connection) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * Execute prepared statement safely
     */
    public function executePreparedStatement($sql, $params = [], $types = '') {
        try {
            if (!$this->connection) {
                throw new Exception("No database connection available");
            }
            
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->connection->error);
            }
            
            if (!empty($params)) {
                if (empty($types)) {
                    $types = str_repeat('s', count($params)); // Default to string type
                }
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            return $stmt;
            
        } catch (Exception $e) {
            ErrorHandler::logError("Prepared statement execution failed", [
                'error' => $e->getMessage(),
                'sql' => $sql,
                'params' => $params
            ]);
            
            return false;
        }
    }
    
    /**
     * Execute query with error handling
     */
    public function executeQuery($sql, $params = [], $types = '') {
        try {
            if (!empty($params)) {
                $stmt = $this->executePreparedStatement($sql, $params, $types);
                if (!$stmt) {
                    return false;
                }
                return $stmt->get_result();
            } else {
                $result = $this->connection->query($sql);
                if (!$result) {
                    throw new Exception("Query failed: " . $this->connection->error);
                }
                return $result;
            }
        } catch (Exception $e) {
            ErrorHandler::logError("Query execution failed", [
                'error' => $e->getMessage(),
                'sql' => $sql
            ]);
            
            return false;
        }
    }
    
    /**
     * Get single row safely
     */
    public function getRow($sql, $params = [], $types = '') {
        $result = $this->executeQuery($sql, $params, $types);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
    
    /**
     * Get all rows safely
     */
    public function getRows($sql, $params = [], $types = '') {
        $result = $this->executeQuery($sql, $params, $types);
        if (!$result) {
            return [];
        }
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    /**
     * Insert data safely
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        $values = array_values($data);
        $types = str_repeat('s', count($values));
        
        $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES ({$placeholders})";
        
        $stmt = $this->executePreparedStatement($sql, $values, $types);
        if ($stmt) {
            return $this->connection->insert_id;
        }
        return false;
    }
    
    /**
     * Update data safely
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        $values = [];
        $types = '';
        
        foreach ($data as $column => $value) {
            $setClause[] = "{$column} = ?";
            $values[] = $value;
            $types .= 's';
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE {$where}";
        
        // Add where parameters
        $values = array_merge($values, $whereParams);
        $types .= str_repeat('s', count($whereParams));
        
        $stmt = $this->executePreparedStatement($sql, $values, $types);
        return $stmt ? $stmt->affected_rows : false;
    }
    
    /**
     * Delete data safely
     */
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $types = str_repeat('s', count($whereParams));
        
        $stmt = $this->executePreparedStatement($sql, $whereParams, $types);
        return $stmt ? $stmt->affected_rows : false;
    }
    
    /**
     * Check if connection is alive
     */
    public function isConnected() {
        if (!$this->connection) {
            return false;
        }
        
        try {
            $result = $this->connection->query("SELECT 1");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Close connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }
    
    /**
     * Get last error
     */
    public function getLastError() {
        return $this->connection ? $this->connection->error : 'No connection';
    }
    
    /**
     * Get last insert ID
     */
    public function getLastInsertId() {
        return $this->connection ? $this->connection->insert_id : 0;
    }
    
    /**
     * Get affected rows
     */
    public function getAffectedRows() {
        return $this->connection ? $this->connection->affected_rows : 0;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        if ($this->connection) {
            return $this->connection->begin_transaction();
        }
        return false;
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        if ($this->connection) {
            return $this->connection->commit();
        }
        return false;
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        if ($this->connection) {
            return $this->connection->rollback();
        }
        return false;
    }
}

// Include the error handler
require_once __DIR__ . '/system_health.php';

// Global database connection instance
$secure_db = null;

/**
 * Initialize secure database connection
 */
function initSecureDatabase() {
    global $secure_db;
    
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "capstone";
    
    $secure_db = new SecureDatabaseConnection($host, $username, $password, $database);
    
    if (!$secure_db->connect()) {
        ErrorHandler::logError("Failed to initialize secure database connection");
        return false;
    }
    
    return true;
}

/**
 * Get secure database connection
 */
function getSecureDB() {
    global $secure_db;
    
    if (!$secure_db) {
        initSecureDatabase();
    }
    
    return $secure_db;
}
?>