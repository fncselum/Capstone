<?php
// Database connection configuration
$host = "localhost";
$user = "root";       
$password = "";   // no password for XAMPP
$dbname = "capstone";

// Create connection
$conn = @new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?>
