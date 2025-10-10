<?php
require_once '../includes/db_connection.php';

echo "<h1>Capstone System Status Check</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

// Check database connection
echo "<h2>Database Connection</h2>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "<span class='success'>✓ Database connection successful</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>✗ Database connection failed: " . $e->getMessage() . "</span><br>";
    exit;
}

// Check required tables
echo "<h2>Database Tables</h2>";
$required_tables = ['users', 'categories', 'equipment', 'inventory', 'transactions', 'penalties', 'admin_users'];
$existing_tables = [];

try {
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch()) {
        $existing_tables[] = $row[0];
    }
    
    echo "<table>";
    echo "<tr><th>Table</th><th>Status</th><th>Record Count</th></tr>";
    
    foreach ($required_tables as $table) {
        if (in_array($table, $existing_tables)) {
            $count_stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $count_stmt->fetch()['count'];
            echo "<tr><td>$table</td><td class='success'>✓ Exists</td><td>$count records</td></tr>";
        } else {
            echo "<tr><td>$table</td><td class='error'>✗ Missing</td><td>-</td></tr>";
        }
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<span class='error'>Error checking tables: " . $e->getMessage() . "</span><br>";
}

// Check admin user
echo "<h2>Admin User</h2>";
try {
    $stmt = $pdo->query("SELECT id, username, status, rfid_tag FROM admin_users WHERE username = 'admin'");
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<span class='success'>✓ Admin user exists</span><br>";
        echo "Username: " . $admin['username'] . "<br>";
        echo "Status: " . $admin['status'] . "<br>";
        echo "RFID Tag: " . ($admin['rfid_tag'] ? $admin['rfid_tag'] : 'Not set') . "<br>";
    } else {
        echo "<span class='warning'>⚠ Admin user not found</span><br>";
        echo "<a href='create_admin.php'>Create Admin User</a><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>Error checking admin user: " . $e->getMessage() . "</span><br>";
}

// Check categories
echo "<h2>Equipment Categories</h2>";
try {
    $stmt = $pdo->query("SELECT name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
    
    if ($categories) {
        echo "<span class='success'>✓ Categories loaded</span><br>";
        echo "Available categories: ";
        foreach ($categories as $cat) {
            echo $cat['name'] . ", ";
        }
        echo "<br>";
    } else {
        echo "<span class='warning'>⚠ No categories found</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>Error checking categories: " . $e->getMessage() . "</span><br>";
}

// System files check
echo "<h2>System Files</h2>";
$critical_files = [
    '../includes/db_connection.php',
    'login.php',
    'admin-dashboard.php',
    'admin-equipment-inventory.php',
    '../config/database.php'
];

foreach ($critical_files as $file) {
    if (file_exists($file)) {
        echo "<span class='success'>✓ $file exists</span><br>";
    } else {
        echo "<span class='error'>✗ $file missing</span><br>";
    }
}

echo "<h2>Quick Actions</h2>";
echo "<a href='create_admin.php'>Create/Update Admin User</a> | ";
echo "<a href='test_login.php'>Test Login Debug</a> | ";
echo "<a href='login.php'>Go to Login</a> | ";
echo "<a href='admin-dashboard.php'>Admin Dashboard</a>";

echo "<br><br><small>System check completed at " . date('Y-m-d H:i:s') . "</small>";
?>
