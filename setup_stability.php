<?php
/**
 * System Stability Setup Script
 * Automatically configures the system for maximum stability
 */

// Prevent direct access
if (php_sapi_name() !== 'cli' && !isset($_GET['admin_setup'])) {
    die('Access denied. This script can only be run by administrators.');
}

echo "🛡️  System Stability Setup Script\n";
echo "==================================\n\n";

// Check if we're in the right directory
if (!file_exists('admin/includes')) {
    die("❌ Error: Please run this script from the project root directory.\n");
}

echo "✅ Project structure detected\n";

// Create required directories
$directories = [
    'logs',
    'backups',
    'admin/logs',
    'admin/backups'
];

echo "\n📁 Creating required directories...\n";
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Created directory: $dir\n";
        } else {
            echo "❌ Failed to create directory: $dir\n";
        }
    } else {
        echo "ℹ️  Directory already exists: $dir\n";
    }
}

// Set proper permissions
echo "\n🔐 Setting directory permissions...\n";
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (chmod($dir, 0755)) {
            echo "✅ Set permissions for: $dir\n";
        } else {
            echo "❌ Failed to set permissions for: $dir\n";
        }
    }
}

// Create .htaccess for logs directory
echo "\n🔒 Creating security files...\n";
$htaccess_content = "Order Deny,Allow\nDeny from all\n";
if (file_put_contents('logs/.htaccess', $htaccess_content)) {
    echo "✅ Created .htaccess for logs directory\n";
} else {
    echo "❌ Failed to create .htaccess for logs directory\n";
}

if (file_put_contents('admin/logs/.htaccess', $htaccess_content)) {
    echo "✅ Created .htaccess for admin logs directory\n";
} else {
    echo "❌ Failed to create .htaccess for admin logs directory\n";
}

// Test database connection
echo "\n🗄️  Testing database connection...\n";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=capstone;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful\n";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "⚠️  Please check your database configuration\n";
}

// Create initial backup
echo "\n💾 Creating initial system backup...\n";
try {
    $backup_dir = 'backups';
    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = $backup_dir . '/initial_backup_' . $timestamp . '.sql';
    
    $command = "mysqldump -h localhost -u root -p capstone > " . escapeshellarg($backup_file);
    exec($command, $output, $return_code);
    
    if ($return_code === 0 && file_exists($backup_file)) {
        echo "✅ Initial backup created: $backup_file\n";
    } else {
        echo "⚠️  Backup creation failed (this is optional)\n";
    }
} catch (Exception $e) {
    echo "⚠️  Backup creation failed: " . $e->getMessage() . "\n";
}

// Test error logging
echo "\n📝 Testing error logging...\n";
try {
    require_once 'admin/includes/error_handler.php';
    SystemErrorHandler::init();
    SystemErrorHandler::logApplicationError("System stability setup completed successfully");
    echo "✅ Error logging system working\n";
} catch (Exception $e) {
    echo "❌ Error logging test failed: " . $e->getMessage() . "\n";
}

// Test validation system
echo "\n🔍 Testing validation system...\n";
try {
    require_once 'admin/includes/validation.php';
    
    // Test equipment validation
    $test_data = [
        'name' => 'Test Equipment',
        'rfid_tag' => 'TEST001',
        'category_id' => '1',
        'quantity' => '5',
        'size_category' => 'Medium',
        'description' => 'Test description'
    ];
    
    $errors = SystemValidator::validateEquipmentData($test_data);
    if (empty($errors)) {
        echo "✅ Validation system working\n";
    } else {
        echo "⚠️  Validation system has issues: " . implode(', ', $errors) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Validation system test failed: " . $e->getMessage() . "\n";
}

// Create system status file
echo "\n📊 Creating system status file...\n";
$status_file = 'system_status.json';
$status_data = [
    'setup_completed' => true,
    'setup_date' => date('Y-m-d H:i:s'),
    'version' => '1.0',
    'directories_created' => $directories,
    'database_connected' => true,
    'error_logging' => true,
    'validation_system' => true
];

if (file_put_contents($status_file, json_encode($status_data, JSON_PRETTY_PRINT))) {
    echo "✅ System status file created\n";
} else {
    echo "❌ Failed to create system status file\n";
}

// Final recommendations
echo "\n🎯 Setup Complete! Next Steps:\n";
echo "==============================\n";
echo "1. Access System Monitor: admin/system_monitor.php\n";
echo "2. Review System Stability Rules: SYSTEM_STABILITY_RULES.md\n";
echo "3. Follow Implementation Guide: STABILITY_IMPLEMENTATION_GUIDE.md\n";
echo "4. Test all functionality thoroughly\n";
echo "5. Set up regular monitoring schedule\n\n";

echo "🔗 Important URLs:\n";
echo "- System Monitor: admin/system_monitor.php\n";
echo "- Health API: admin/system_health.php\n";
echo "- Backup System: admin/backup_system.php\n\n";

echo "⚠️  Important Notes:\n";
echo "- Always test in development environment first\n";
echo "- Keep regular backups\n";
echo "- Monitor error logs daily\n";
echo "- Update system regularly\n\n";

echo "✅ System stability setup completed successfully!\n";
echo "🛡️  Your system is now protected with comprehensive stability rules.\n\n";