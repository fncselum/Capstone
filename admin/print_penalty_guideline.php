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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $sql = "SELECT pg.*, au.username as created_by_name 
            FROM penalty_guidelines pg
            LEFT JOIN admin_users au ON pg.created_by = au.id
            WHERE pg.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $guideline = $result->fetch_assoc();
    
    if (!$guideline) {
        die("Guideline not found");
    }
} else {
    die("Invalid ID");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print - <?= htmlspecialchars($guideline['title']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
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
        }
        
        .header .subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .info-section {
            margin-bottom: 30px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #1e5631;
        }
        
        .info-item .label {
            font-weight: bold;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .info-item .value {
            font-size: 1.1rem;
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.9rem;
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
        
        .description-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .description-section h3 {
            margin-bottom: 15px;
            color: #1e5631;
        }
        
        .description-section p {
            line-height: 1.8;
            white-space: pre-wrap;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #999;
            font-size: 0.9rem;
        }
        
        @media print {
            body {
                padding: 20px;
            }
            
            .no-print {
                display: none;
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
        }
        
        .print-btn:hover {
            background: #163f24;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">
        🖨️ Print
    </button>
    
    <div class="header">
        <h1>Penalty Guideline</h1>
        <p class="subtitle">De La Salle Andres Soriano Memorial College (ASMC) - Equipment Management System</p>
    </div>
    
    <div class="content">
        <h2 style="margin-bottom: 20px; color: #1e5631;"><?= htmlspecialchars($guideline['title']) ?></h2>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Penalty Type</div>
                <div class="value"><?= htmlspecialchars($guideline['penalty_type']) ?></div>
            </div>
            
            <div class="info-item">
                <div class="label">Status</div>
                <div class="value">
                    <span class="status-badge status-<?= $guideline['status'] ?>">
                        <?= ucfirst($guideline['status']) ?>
                    </span>
                </div>
            </div>
            
            <div class="info-item">
                <div class="label">Penalty Amount</div>
                <div class="value">₱<?= number_format($guideline['penalty_amount'], 2) ?></div>
            </div>
            
            <div class="info-item">
                <div class="label">Penalty Points</div>
                <div class="value"><?= $guideline['penalty_points'] ?> points</div>
            </div>
        </div>
        
        <div class="description-section">
            <h3>Description</h3>
            <p><?= nl2br(htmlspecialchars($guideline['penalty_description'])) ?></p>
        </div>
        
        <div class="footer">
            <p>Created by: <?= htmlspecialchars($guideline['created_by_name'] ?? 'Unknown') ?></p>
            <p>Created: <?= date('F d, Y', strtotime($guideline['created_at'])) ?></p>
            <p>Last Updated: <?= date('F d, Y', strtotime($guideline['updated_at'])) ?></p>
            <p style="margin-top: 20px;">Printed on: <?= date('F d, Y h:i A') ?></p>
        </div>
    </div>
</body>
</html>
