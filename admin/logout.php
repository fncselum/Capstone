<?php
/**
 * Admin Logout Script
 * Destroys admin session and redirects to login page
 */

// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Clear any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

// Redirect to login page
header('Location: login.php');
exit;
?>
