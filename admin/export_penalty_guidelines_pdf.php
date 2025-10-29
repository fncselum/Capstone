<?php
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

// Get filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build SQL query with filters
$sql = "SELECT pg.*, au.username as created_by_name 
        FROM penalty_guidelines pg
        LEFT JOIN admin_users au ON pg.created_by = au.id
        WHERE 1=1";

if ($filter_type !== 'all') {
    $sql .= " AND pg.penalty_type = '" . $conn->real_escape_string($filter_type) . "'";
}

if ($filter_status !== 'all') {
    $sql .= " AND pg.status = '" . $conn->real_escape_string($filter_status) . "'";
}

if (!empty($search_query)) {
    $search_escaped = $conn->real_escape_string($search_query);
    $sql .= " AND (pg.title LIKE '%$search_escaped%' OR pg.penalty_description LIKE '%$search_escaped%')";
}

$sql .= " ORDER BY pg.created_at DESC";

$guidelines = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penalty Guidelines Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 30px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #1e5631;
        }
        
        .header h1 {
            color: #1e5631;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .filters-info {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 8px;
            border-left: 4px solid #1e5631;
        }
        
        .filters-info strong {
            color: #1e5631;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        thead {
            background: #1e5631;
            color: white;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        
        tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        tbody tr:hover {
            background: #e8f5e9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-draft {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-archived {
            background: #f5f5f5;
            color: #757575;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #999;
            font-size: 0.9rem;
        }
        
        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #1e5631;
        }
        
        .summary-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #1e5631;
            margin-bottom: 5px;
        }
        
        .summary-card .label {
            color: #666;
            font-size: 0.9rem;
        }
        
        @media print {
            body {
                padding: 20px;
            }
            
            .no-print {
                display: none;
            }
            
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #1e5631;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-btn:hover {
            background: #163f24;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">
        🖨️ Print / Save as PDF
    </button>
    
    <div class="header">
        <h1>Penalty Guidelines Report</h1>
        <p class="subtitle">De La Salle Andres Soriano Memorial College (ASMC) - Equipment Management System</p>
    </div>
    
    <?php if ($filter_type !== 'all' || $filter_status !== 'all' || !empty($search_query)): ?>
    <div class="filters-info">
        <strong>Applied Filters:</strong>
        <?php if ($filter_type !== 'all'): ?>
            Type: <?= htmlspecialchars($filter_type) ?> |
        <?php endif; ?>
        <?php if ($filter_status !== 'all'): ?>
            Status: <?= htmlspecialchars($filter_status) ?> |
        <?php endif; ?>
        <?php if (!empty($search_query)): ?>
            Search: "<?= htmlspecialchars($search_query) ?>"
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php
    // Calculate summary
    $total_count = 0;
    $total_amount = 0;
    $active_count = 0;
    
    if ($guidelines) {
        $guidelines->data_seek(0);
        while ($g = $guidelines->fetch_assoc()) {
            $total_count++;
            $total_amount += $g['penalty_amount'];
            if ($g['status'] === 'active') {
                $active_count++;
            }
        }
        $guidelines->data_seek(0);
    }
    ?>
    
    <div class="summary">
        <div class="summary-card">
            <div class="number"><?= $total_count ?></div>
            <div class="label">Total Guidelines</div>
        </div>
        <div class="summary-card">
            <div class="number"><?= $active_count ?></div>
            <div class="label">Active Guidelines</div>
        </div>
        <div class="summary-card">
            <div class="number">₱<?= number_format($total_amount, 2) ?></div>
            <div class="label">Total Penalty Amount</div>
        </div>
    </div>
    
    <?php if ($guidelines && $guidelines->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Points</th>
                <th>Status</th>
                <th>Created By</th>
            </tr>
        </thead>
        <tbody>
            <?php while($guideline = $guidelines->fetch_assoc()): ?>
            <tr>
                <td><strong><?= htmlspecialchars($guideline['title']) ?></strong></td>
                <td><?= htmlspecialchars($guideline['penalty_type']) ?></td>
                <td>₱<?= number_format($guideline['penalty_amount'], 2) ?></td>
                <td><?= $guideline['penalty_points'] ?> pts</td>
                <td>
                    <span class="status-badge status-<?= $guideline['status'] ?>">
                        <?= ucfirst($guideline['status']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($guideline['created_by_name'] ?? 'Unknown') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="text-align: center; padding: 40px; color: #999;">No guidelines found matching the criteria.</p>
    <?php endif; ?>
    
    <div class="footer">
        <p><strong>Generated on:</strong> <?= date('F d, Y h:i A') ?></p>
        <p><strong>Generated by:</strong> <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></p>
        <p style="margin-top: 10px; font-size: 0.8rem;">This is a system-generated report from the Equipment Management System</p>
    </div>
</body>
</html>
