<?php
session_start();
require_once '../includes/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Logs - Equipment System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .admin-container.sidebar-hidden .main-content {
            margin-left: 0;
        }

        .top-header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8rem;
            color: #333;
            margin: 0;
        }

        .content-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .section-header {
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 1.3rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .placeholder-message {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .placeholder-message i {
            font-size: 4rem;
            color: #9c27b0;
            margin-bottom: 20px;
        }

        .placeholder-message h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .placeholder-message p {
            font-size: 1rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title">Kiosk Logs</h1>
            </header>

            <section class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Kiosk Activity Logs</h2>
                </div>

                <div class="placeholder-message">
                    <i class="fas fa-file-alt"></i>
                    <h3>Kiosk Transaction Logs</h3>
                    <p>View recent kiosk transactions and actions.<br>
                    Monitor all activities performed at each kiosk station.<br>
                    This feature will be implemented soon.</p>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
