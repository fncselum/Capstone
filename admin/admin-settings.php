<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// Handle success/error messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Check if system_settings table exists
$table_check = $conn->query("SHOW TABLES LIKE 'system_settings'");
$table_exists = $table_check && $table_check->num_rows > 0;

// Get current settings
$settings = [];
if ($table_exists) {
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}

// Default settings if not in database
$default_settings = [
    'system_name' => 'Equipment Management System',
    'institution_name' => 'De La Salle ASMC',
    'contact_email' => 'admin@dlsasmc.edu.ph',
    'overdue_penalty_rate' => '10.00',
    'enable_notifications' => '1',
    'enable_email_alerts' => '0',
    'maintenance_mode' => '0',
    'session_timeout' => '30',
    'items_per_page' => '20'
];

// Merge with defaults
foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Get admin users count
$admin_count = 0;
$admin_check = $conn->query("SHOW TABLES LIKE 'admin_users'");
if ($admin_check && $admin_check->num_rows > 0) {
    $count_result = $conn->query("SELECT COUNT(*) as total FROM admin_users");
    if ($count_result) {
        $admin_count = $count_result->fetch_assoc()['total'];
    }
}

// Get database info
$db_size = 0;
$table_count = 0;
$size_result = $conn->query("SELECT 
    SUM(data_length + index_length) / 1024 / 1024 AS size_mb,
    COUNT(*) as tables
    FROM information_schema.TABLES 
    WHERE table_schema = '$dbname'");
if ($size_result) {
    $db_info = $size_result->fetch_assoc();
    $db_size = round($db_info['size_mb'], 2);
    $table_count = $db_info['tables'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Equipment System</title>
    <link rel="stylesheet" href="assets/css/admin-base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Tabs */
        .settings-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn:hover {
            color: #006633;
            background: #f5f9f7;
        }
        
        .tab-btn.active {
            color: #006633;
            border-bottom-color: #006633;
        }
        
        /* Settings Form */
        .settings-form {
            max-width: 800px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #006633;
        }
        
        .form-group small {
            display: block;
            margin-top: 6px;
            color: #666;
            font-size: 0.85rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #006633;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        /* Info Cards */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fdf9 100%);
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #006633;
        }
        
        .info-card h4 {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #006633;
        }
        
        /* Buttons */
        .btn-save {
            padding: 12px 24px;
            background: #006633;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-save:hover {
            background: #004d26;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #f44336;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
        }
        
        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #1b5e20;
            border-left: 4px solid #4caf50;
        }
        
        .alert-error {
            background: #ffebee;
            color: #b71c1c;
            border-left: 4px solid #f44336;
        }
        
        /* Setup Notice */
        .setup-notice {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .setup-icon {
            font-size: 4rem;
            color: #006633;
            margin-bottom: 20px;
        }
        
        .setup-notice h2 {
            color: #006633;
            margin-bottom: 15px;
        }
        
        .setup-sql textarea {
            width: 100%;
            height: 200px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            resize: vertical;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 9999;
        }

        .toast {
            min-width: 280px;
            max-width: 360px;
            background: white;
            border-radius: 12px;
            border-left: 4px solid #006633;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            padding: 16px 18px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideIn 0.4s ease, fadeOut 0.4s ease forwards;
            animation-delay: 0s, 4.6s;
            position: relative;
            overflow: hidden;
        }

        .toast::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            width: 100%;
            background: rgba(0,102,51,0.15);
            animation: shrink 5s linear forwards;
        }

        .toast.success { border-left-color: #2e7d32; }
        .toast.success .toast-icon { background: rgba(46,125,50,0.15); color: #2e7d32; }

        .toast.error { border-left-color: #c62828; }
        .toast.error .toast-icon { background: rgba(198,40,40,0.15); color: #c62828; }

        .toast.info { border-left-color: #1565c0; }
        .toast.info .toast-icon { background: rgba(21,101,192,0.15); color: #1565c0; }

        .toast-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 700;
            font-size: 1rem;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .toast-message {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            color: #888;
            cursor: pointer;
            font-size: 1rem;
            padding: 4px;
            transition: color 0.2s ease;
        }

        .toast-close:hover {
            color: #222;
        }

        @keyframes slideIn {
            from {
                transform: translateX(120%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(40%);
            }
        }

        @keyframes shrink {
            from { width: 100%; }
            to { width: 0; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">System Settings</h1>
            </header>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!$table_exists): ?>
                <div class="setup-notice">
                    <div class="setup-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h2>Database Setup Required</h2>
                    <p>The system_settings table needs to be created in your database.</p>
                    
                    <div style="margin: 30px 0;">
                        <a href="setup_settings_table.php" class="btn-save" style="text-decoration: none; display: inline-flex;">
                            <i class="fas fa-magic"></i> Auto Setup (Recommended)
                        </a>
                    </div>
                    
                    <div class="setup-sql" style="margin-top: 30px; text-align: left;">
                        <h3>Or Manual Setup SQL:</h3>
                        <textarea readonly onclick="this.select()">CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</textarea>
                        <p style="margin-top: 10px; color: #666;"><small>Click the text area above to select all, then copy (Ctrl+C) and run in phpMyAdmin</small></p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Tabs -->
                <div class="settings-tabs">
                    <button class="tab-btn <?= $active_tab === 'general' ? 'active' : '' ?>" onclick="switchTab('general')">
                        <i class="fas fa-cog"></i> General
                    </button>
                    <button class="tab-btn <?= $active_tab === 'system' ? 'active' : '' ?>" onclick="switchTab('system')">
                        <i class="fas fa-server"></i> System
                    </button>
                    <button class="tab-btn <?= $active_tab === 'database' ? 'active' : '' ?>" onclick="switchTab('database')">
                        <i class="fas fa-database"></i> Database
                    </button>
                </div>

                <!-- General Settings Tab -->
                <div id="general-tab" class="tab-content" style="display: <?= $active_tab === 'general' ? 'block' : 'none' ?>;">
                    <div style="background: white; padding: 28px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                        <h3 style="margin-bottom: 20px; color: #006633; font-weight: 700;">
                            <i class="fas fa-sliders-h"></i> General Settings
                        </h3>
                        
                        <form id="generalForm" class="settings-form">
                            <div class="form-group">
                                <label for="system_name">System Name</label>
                                <input type="text" id="system_name" name="system_name" value="<?= htmlspecialchars($settings['system_name']) ?>" required>
                                <small>The name displayed throughout the system</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="institution_name">Institution Name</label>
                                <input type="text" id="institution_name" name="institution_name" value="<?= htmlspecialchars($settings['institution_name']) ?>" required>
                                <small>Your institution or organization name</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_email">Contact Email</label>
                                <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email']) ?>" required>
                                <small>Primary contact email for the system</small>
                            </div>
                            
                            
                            
                            <div class="form-group">
                                <label for="overdue_penalty_rate">Overdue Penalty Rate (₱/day)</label>
                                <input type="number" id="overdue_penalty_rate" name="overdue_penalty_rate" value="<?= htmlspecialchars($settings['overdue_penalty_rate']) ?>" step="0.01" min="0" required>
                                <small>Daily penalty rate for overdue items</small>
                            </div>
                            
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save General Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- System Settings Tab -->
                <div id="system-tab" class="tab-content" style="display: <?= $active_tab === 'system' ? 'active' : 'none' ?>;">
                    <div style="background: white; padding: 28px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                        <h3 style="margin-bottom: 20px; color: #006633; font-weight: 700;">
                            <i class="fas fa-server"></i> System Configuration
                        </h3>
                        
                        <form id="systemForm" class="settings-form">
                            <div class="form-group">
                                <label>Enable Notifications</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_notifications" <?= $settings['enable_notifications'] == '1' ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <small>Show system notifications to admins</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Enable Email Alerts</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_email_alerts" <?= $settings['enable_email_alerts'] == '1' ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <small>Send email alerts for critical events (overdue, borrow, return notifications)</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Maintenance Mode</label>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="maintenance_mode" <?= $settings['maintenance_mode'] == '1' ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <small>Enable maintenance mode (users cannot access system)</small>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="session_timeout">Session Timeout (minutes)</label>
                                    <input type="number" id="session_timeout" name="session_timeout" value="<?= htmlspecialchars($settings['session_timeout']) ?>" min="5" required>
                                    <small>Auto logout after inactivity</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="items_per_page">Items Per Page</label>
                                    <input type="number" id="items_per_page" name="items_per_page" value="<?= htmlspecialchars($settings['items_per_page']) ?>" min="10" required>
                                    <small>Default pagination size</small>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i> Save System Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Database Tab -->
                <div id="database-tab" class="tab-content" style="display: <?= $active_tab === 'database' ? 'active' : 'none' ?>;">
                    <div class="info-cards">
                        <div class="info-card">
                            <h4>Database Size</h4>
                            <div class="value"><?= $db_size ?> MB</div>
                        </div>
                        <div class="info-card">
                            <h4>Total Tables</h4>
                            <div class="value"><?= $table_count ?></div>
                        </div>
                        <div class="info-card">
                            <h4>Admin Users</h4>
                            <div class="value"><?= $admin_count ?></div>
                        </div>
                    </div>
                    
                    <div style="background: white; padding: 28px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                        <h3 style="margin-bottom: 20px; color: #006633; font-weight: 700;">
                            <i class="fas fa-database"></i> Database Management
                        </h3>
                        
                        <p style="margin-bottom: 20px; color: #666;">
                            Database backup and maintenance operations. Use with caution.
                        </p>
                        
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <button class="btn-save" onclick="backupDatabase(this)">
                                <i class="fas fa-download"></i> Backup Database
                            </button>
                            <button class="btn-save btn-danger" onclick="clearCache(this)">
                                <i class="fas fa-trash"></i> Clear Cache
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        function logout() {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }

        function switchTab(tab) {
            window.location.href = '?tab=' + tab;
        }

        function showToast(type = 'info', title = 'Notification', message = '') {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const icons = {
                success: 'fa-circle-check',
                error: 'fa-triangle-exclamation',
                info: 'fa-circle-info'
            };

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon"><i class="fas ${icons[type] || icons.info}"></i></div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message.replace(/\n/g, '<br>')}</div>
                </div>
                <button class="toast-close" aria-label="Close"><i class="fas fa-times"></i></button>
            `;

            const removeToast = () => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            };

            toast.querySelector('.toast-close').addEventListener('click', () => {
                toast.classList.add('closing');
                setTimeout(removeToast, 200);
            });

            container.appendChild(toast);
            setTimeout(removeToast, 5000);
        }

        // General Settings Form
        document.getElementById('generalForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const settings = {};
            for (let [key, value] of formData.entries()) {
                settings[key] = value;
            }
            
            try {
                const response = await fetch('save_settings.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'settings=' + encodeURIComponent(JSON.stringify(settings))
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast('success', 'Settings Saved', 'General settings updated successfully.');
                } else {
                    showToast('error', 'Save Failed', data.message || 'Unable to save general settings.');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('error', 'Save Failed', 'An unexpected error occurred while saving general settings.');
            }
        });

        // System Settings Form
        document.getElementById('systemForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const settings = {};
            
            // Handle checkboxes
            settings['enable_notifications'] = formData.get('enable_notifications') ? '1' : '0';
            settings['enable_email_alerts'] = formData.get('enable_email_alerts') ? '1' : '0';
            settings['maintenance_mode'] = formData.get('maintenance_mode') ? '1' : '0';
            settings['session_timeout'] = formData.get('session_timeout');
            settings['items_per_page'] = formData.get('items_per_page');
            
            try {
                const response = await fetch('save_settings.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'settings=' + encodeURIComponent(JSON.stringify(settings))
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast('success', 'Settings Saved', 'System configuration updated successfully.');
                } else {
                    showToast('error', 'Save Failed', data.message || 'Unable to save system settings.');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('error', 'Save Failed', 'An unexpected error occurred while saving system settings.');
            }
        });

        async function backupDatabase(button) {
            if (!confirm('Create a database backup? This may take a few moments.')) {
                return;
            }
            
            try {
                // Show loading state
                const btn = button;
                const originalHTML = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating backup...';
                
                // Trigger download
                window.location.href = 'backup_database.php';
                
                // Reset button after delay
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                    showToast('success', 'Backup Started', 'Database backup download should begin shortly.');
                }, 2000);
                
            } catch (error) {
                console.error('Backup error:', error);
                showToast('error', 'Backup Failed', 'Unable to create database backup. Please try again.');
            }
        }

        async function clearCache(button) {
            if (!confirm('Clear system cache? This will remove temporary files and cached data.')) {
                return;
            }
            
            try {
                // Show loading state
                const btn = button;
                const originalHTML = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing cache...';
                
                const response = await fetch('clear_cache.php', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                
                if (data.success) {
                    let message = 'Cache cleared successfully!';
                    if (Array.isArray(data.cleared) && data.cleared.length > 0) {
                        message += '\n\n' + data.cleared.map(item => '• ' + item).join('\n');
                    }
                    showToast('success', 'Cache Cleared', message);
                } else {
                    showToast('error', 'Cache Clear Failed', data.message || 'Unable to clear cache.');
                }
                
            } catch (error) {
                console.error('Cache clear error:', error);
                showToast('error', 'Cache Clear Failed', 'An unexpected error occurred while clearing cache.');
            }
        }
    </script>
</body>
</html>
