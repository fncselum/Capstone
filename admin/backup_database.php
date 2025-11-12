<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    // Start building SQL dump
    $sqlDump = "-- Database Backup\n";
    $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Database: $dbname\n\n";
    $sqlDump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sqlDump .= "SET time_zone = \"+00:00\";\n\n";
    
    // Loop through tables
    foreach ($tables as $table) {
        // Get CREATE TABLE statement
        $createTable = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $createTable->fetch_array();
        
        $sqlDump .= "\n\n-- --------------------------------------------------------\n";
        $sqlDump .= "-- Table structure for table `$table`\n";
        $sqlDump .= "-- --------------------------------------------------------\n\n";
        $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sqlDump .= $row[1] . ";\n\n";
        
        // Get table data
        $dataResult = $conn->query("SELECT * FROM `$table`");
        
        if ($dataResult && $dataResult->num_rows > 0) {
            $sqlDump .= "-- Dumping data for table `$table`\n\n";
            
            while ($dataRow = $dataResult->fetch_assoc()) {
                $sqlDump .= "INSERT INTO `$table` VALUES (";
                
                $values = [];
                foreach ($dataRow as $value) {
                    if ($value === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                
                $sqlDump .= implode(", ", $values);
                $sqlDump .= ");\n";
            }
            
            $sqlDump .= "\n";
        }
    }
    
    $conn->close();
    
    // Create filename with timestamp
    $filename = "backup_" . $dbname . "_" . date('Y-m-d_H-i-s') . ".sql";
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sqlDump));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Output SQL dump
    echo $sqlDump;
    exit;
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Backup failed: ' . $e->getMessage();
    header('Location: admin-settings.php?tab=database');
    exit;
}
?>
