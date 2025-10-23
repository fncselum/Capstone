<?php
/**
 * Database Manager
 * Provides secure database operations with proper error handling
 */

class DatabaseManager {
    
    private static $pdo = null;
    private static $host = "localhost";
    private static $user = "root";
    private static $password = "";
    private static $dbname = "capstone";
    
    /**
     * Get database connection
     */
    public static function getConnection() {
        if (self::$pdo === null) {
            try {
                $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8mb4";
                self::$pdo = new PDO($dsn, self::$user, self::$password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
            } catch (PDOException $e) {
                SystemErrorHandler::logDatabaseError("Connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please try again later.");
            }
        }
        
        return self::$pdo;
    }
    
    /**
     * Execute a prepared statement safely
     */
    public static function execute($sql, $params = []) {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if (!$result) {
                throw new PDOException("Query execution failed");
            }
            
            return $stmt;
        } catch (PDOException $e) {
            SystemErrorHandler::logDatabaseError($e->getMessage(), $sql, $params);
            throw new Exception("Database operation failed. Please try again later.");
        }
    }
    
    /**
     * Execute a query and return all results
     */
    public static function fetchAll($sql, $params = []) {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Execute a query and return single result
     */
    public static function fetchOne($sql, $params = []) {
        $stmt = self::execute($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Execute a query and return single value
     */
    public static function fetchValue($sql, $params = []) {
        $stmt = self::execute($sql, $params);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    }
    
    /**
     * Execute a query and return row count
     */
    public static function rowCount($sql, $params = []) {
        $stmt = self::execute($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Execute a query and return last insert ID
     */
    public static function lastInsertId($sql, $params = []) {
        $stmt = self::execute($sql, $params);
        return self::getConnection()->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction() {
        try {
            $pdo = self::getConnection();
            return $pdo->beginTransaction();
        } catch (PDOException $e) {
            SystemErrorHandler::logDatabaseError("Transaction begin failed: " . $e->getMessage());
            throw new Exception("Failed to start transaction.");
        }
    }
    
    /**
     * Commit transaction
     */
    public static function commit() {
        try {
            $pdo = self::getConnection();
            return $pdo->commit();
        } catch (PDOException $e) {
            SystemErrorHandler::logDatabaseError("Transaction commit failed: " . $e->getMessage());
            throw new Exception("Failed to commit transaction.");
        }
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback() {
        try {
            $pdo = self::getConnection();
            return $pdo->rollBack();
        } catch (PDOException $e) {
            SystemErrorHandler::logDatabaseError("Transaction rollback failed: " . $e->getMessage());
            throw new Exception("Failed to rollback transaction.");
        }
    }
    
    /**
     * Execute multiple queries in a transaction
     */
    public static function executeTransaction($queries) {
        self::beginTransaction();
        
        try {
            $results = [];
            foreach ($queries as $query) {
                $sql = $query['sql'];
                $params = $query['params'] ?? [];
                $results[] = self::execute($sql, $params);
            }
            
            self::commit();
            return $results;
        } catch (Exception $e) {
            self::rollback();
            throw $e;
        }
    }
    
    /**
     * Check if table exists
     */
    public static function tableExists($table_name) {
        $sql = "SELECT COUNT(*) FROM information_schema.tables 
                WHERE table_schema = :dbname AND table_name = :table_name";
        
        $count = self::fetchValue($sql, [
            ':dbname' => self::$dbname,
            ':table_name' => $table_name
        ]);
        
        return $count > 0;
    }
    
    /**
     * Check if column exists in table
     */
    public static function columnExists($table_name, $column_name) {
        $sql = "SELECT COUNT(*) FROM information_schema.columns 
                WHERE table_schema = :dbname 
                AND table_name = :table_name 
                AND column_name = :column_name";
        
        $count = self::fetchValue($sql, [
            ':dbname' => self::$dbname,
            ':table_name' => $table_name,
            ':column_name' => $column_name
        ]);
        
        return $count > 0;
    }
    
    /**
     * Get table columns
     */
    public static function getTableColumns($table_name) {
        $sql = "SELECT column_name, data_type, is_nullable, column_default 
                FROM information_schema.columns 
                WHERE table_schema = :dbname AND table_name = :table_name 
                ORDER BY ordinal_position";
        
        return self::fetchAll($sql, [
            ':dbname' => self::$dbname,
            ':table_name' => $table_name
        ]);
    }
    
    /**
     * Check database connection health
     */
    public static function checkHealth() {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query("SELECT 1");
            $result = $stmt->fetch();
            
            return [
                'status' => 'healthy',
                'message' => 'Database connection is working',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Get database statistics
     */
    public static function getStats() {
        try {
            $stats = [];
            
            // Get table sizes
            $sql = "SELECT 
                        table_name,
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = :dbname 
                    ORDER BY (data_length + index_length) DESC";
            
            $stats['table_sizes'] = self::fetchAll($sql, [':dbname' => self::$dbname]);
            
            // Get connection count
            $sql = "SHOW STATUS LIKE 'Threads_connected'";
            $result = self::fetchOne($sql);
            $stats['connections'] = $result['Value'] ?? 0;
            
            // Get uptime
            $sql = "SHOW STATUS LIKE 'Uptime'";
            $result = self::fetchOne($sql);
            $stats['uptime_seconds'] = $result['Value'] ?? 0;
            
            return $stats;
        } catch (Exception $e) {
            SystemErrorHandler::logDatabaseError("Stats collection failed: " . $e->getMessage());
            return ['error' => 'Failed to collect database statistics'];
        }
    }
    
    /**
     * Backup database (basic implementation)
     */
    public static function createBackup($backup_file = null) {
        if ($backup_file === null) {
            $backup_file = dirname(__DIR__) . '/backups/backup_' . date('Y-m-d_H-i-s') . '.sql';
        }
        
        // Create backup directory if it doesn't exist
        $backup_dir = dirname($backup_file);
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            escapeshellarg(self::$host),
            escapeshellarg(self::$user),
            escapeshellarg(self::$password),
            escapeshellarg(self::$dbname),
            escapeshellarg($backup_file)
        );
        
        $output = [];
        $return_code = 0;
        exec($command, $output, $return_code);
        
        if ($return_code !== 0) {
            throw new Exception("Database backup failed with return code: " . $return_code);
        }
        
        return $backup_file;
    }
}