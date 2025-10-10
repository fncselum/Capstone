<?php
// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Include database connection
require_once '../includes/db_connection.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// If already logged in, redirect to admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin-dashboard.php');
    exit;
}

$error = '';
$require_2fa = false; // Controls whether to show the RFID 2FA form

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 2: RFID verification (2FA)
    if (isset($_POST['rfid'])) {
        $rfid = trim($_POST['rfid']);
        if (!isset($_SESSION['pending_user_id'])) {
            $error = 'Session expired. Please log in again.';
        } else {
            try {
                // Update the admin user's RFID tag in the database
                $stmt = $pdo->prepare("UPDATE admin_users SET rfid_tag = ?, last_login = NOW() WHERE id = ? AND status = 'Active'");
                $stmt->execute([$rfid, $_SESSION['pending_user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    // 2FA success -> complete login
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $_SESSION['pending_username'];
                    $_SESSION['admin_id'] = $_SESSION['pending_user_id'];
                    $_SESSION['admin_rfid'] = $rfid;
                    
                    // Clean up pending session data
                    unset($_SESSION['pending_user_id']);
                    unset($_SESSION['pending_username']);
                    
                    header('Location: admin-dashboard.php');
                    exit;
                } else {
                    $error = 'Failed to verify RFID. Please try again.';
                    $require_2fa = true;
                }
            } catch (PDOException $e) {
                $error = 'Database error. Please try again.';
                $require_2fa = true;
            }
        }
    } else {
        // Step 1: Username/password
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (!empty($username) && !empty($password)) {
            try {
                // Check credentials in admin_users table
                $stmt = $pdo->prepare("SELECT id, username, password FROM admin_users WHERE username = ? AND status = 'Active'");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();
                
                if ($admin && password_verify($password, $admin['password'])) {
                    // Credentials valid -> require RFID 2FA
                    $_SESSION['pending_user_id'] = $admin['id'];
                    $_SESSION['pending_username'] = $admin['username'];
                    $require_2fa = true;
                } else {
                    $error = 'Invalid username or password. Please try again.';
                }
            } catch (PDOException $e) {
                $error = 'Database connection error. Please try again.';
            }
        } else {
            $error = 'Please enter both username and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Admin Login - Equipment Kiosk</title>
    <link rel="stylesheet" href="login-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <img src="../uploads/De lasalle ASMC.png" alt="De La Salle ASMC Logo" class="main-logo" style="height:30px; width:auto;">
                    <span>Equipment Kiosk</span>
                </div>
                <h1><?= $require_2fa ? 'Two-Factor Verification' : 'Admin Login' ?></h1>
                <p>
                    <?= $require_2fa 
                        ? 'Enter the required RFID number to complete sign-in.' 
                        : 'Enter your credentials to access the admin panel' ?>
                </p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message show">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($require_2fa): ?>
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label for="rfid">
                            <i class="fas fa-id-card"></i>
                            RFID Number (2FA)
                        </label>
                        <input type="text" id="rfid" name="rfid" required placeholder="">
                        <small>Use the required RFID to complete sign-in.</small>
                    </div>
                    <button type="submit" class="login-btn">
                        <i class="fas fa-shield-alt"></i>
                        Verify & Continue
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-user"></i>
                            Username
                        </label>
                        <input type="text" id="username" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <div class="password-input">
                            <input type="password" id="password" name="password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </button>
                </form>
            <?php endif; ?>
            
        </div>
    </div>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.querySelector('.toggle-password i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            toggleBtn.className = 'fas fa-eye';
        }
    }
    </script>
</body>
</html>
