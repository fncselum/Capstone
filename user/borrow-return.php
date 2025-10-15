<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$student_id = $_SESSION['student_id'] ?? 'Guest';
$penalty_points = $_SESSION['penalty_points'] ?? 0;
$user_id = $_SESSION['user_id'];

// Database connection to check if user has borrowed items
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
$has_borrowed_items = false;
$borrowed_count = 0;

if (!$conn->connect_error) {
    // Check if user has any active borrowed items
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions 
                           WHERE user_id = ? AND transaction_type = 'Borrow' 
                           AND status = 'Active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $borrowed_count = $row['count'];
        $has_borrowed_items = $borrowed_count > 0;
    }
    
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Action - Equipment Kiosk</title>
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="scanner-styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Borrow-Return Page Specific Styles */
        .kiosk-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2vh 3vw;
            gap: 2vh;
        }
        
        .action-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3vw;
            padding: 3vh 0;
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
        }
        
        .action-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fdf9 100%);
            border-radius: 25px;
            padding: 50px 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid #e8f5e9;
            position: relative;
            overflow: hidden;
            min-height: 350px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(30, 86, 49, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .action-card:hover::before {
            left: 100%;
        }
        
        .action-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(30, 86, 49, 0.2);
            border-color: #1e5631;
        }
        
        .action-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f5f5f5;
        }
        
        .action-card.disabled:hover {
            transform: none;
            box-shadow: none;
            border-color: #e8f5e9;
        }
        
        .action-icon {
            font-size: 100px;
            margin-bottom: 25px;
            display: block;
            animation: iconFloat 3s ease-in-out infinite;
        }
        
        @keyframes iconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .action-card.borrow .action-icon {
            color: #1e5631;
        }
        
        .action-card.return .action-icon {
            color: #2563eb;
        }
        
        .action-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e5631;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        
        .action-description {
            font-size: 1.05rem;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .action-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .action-badge.success {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border: 1px solid #4caf50;
        }
        
        .action-badge.info {
            background: rgba(37, 99, 235, 0.1);
            color: #2563eb;
            border: 1px solid #2563eb;
        }
        
        .action-badge.warning {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 1px solid #ff9800;
        }
        
        .user-info-bar {
            background: #e8f5e9;
            padding: 18px 35px;
            border-radius: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            width: 100%;
            max-width: 900px;
            box-shadow: 0 2px 8px rgba(30, 86, 49, 0.1);
        }
        
        .user-detail {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-detail i {
            font-size: 1.2rem;
            color: #1e5631;
        }
        
        .user-detail span {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }
        
        .logout-btn {
            background: #1e5631;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #163f24;
            transform: translateY(-2px);
        }
        
        .header {
            width: 100%;
            max-width: 900px;
            margin-bottom: 0;
        }
        
        @media (max-width: 768px) {
            .action-selection {
                grid-template-columns: 1fr;
            }
            
            .action-card {
                min-height: 300px;
                padding: 40px 30px;
            }
            
            .action-icon {
                font-size: 80px;
            }
            
            .action-title {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Background Animation -->
        <div class="background-animation">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="circle circle-3"></div>
        </div>

        <div class="kiosk-content">
            <!-- Header with Logo and Title -->
            <div class="header">
                <div class="header-content">
                    <img src="../uploads/De lasalle ASMC.png" alt="De La Salle ASMC Logo" class="header-logo">
                    <div class="header-text">
                        <h1 class="welcome-title">Select an Action</h1>
                        <p class="subtitle">Choose what you'd like to do</p>
                    </div>
                </div>
            </div>
            
            <!-- User Info Bar -->
            <div class="user-info-bar">
                <div style="display: flex; gap: 30px;">
                    <div class="user-detail">
                        <i class="fas fa-user-circle"></i>
                        <span><strong>Student ID:</strong> <?= htmlspecialchars($student_id) ?></span>
                    </div>
                    <?php if ($has_borrowed_items): ?>
                    <div class="user-detail">
                        <i class="fas fa-box"></i>
                        <span><strong>Borrowed:</strong> <?= $borrowed_count ?> item(s)</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($penalty_points > 0): ?>
                    <div class="user-detail">
                        <i class="fas fa-exclamation-triangle" style="color: #ff9800;"></i>
                        <span style="color: #ff9800;"><strong>Penalty:</strong> <?= $penalty_points ?> points</span>
                    </div>
                    <?php endif; ?>
                </div>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
            
            <!-- Action Selection Cards -->
            <div class="action-selection">
                <!-- Borrow Card -->
                <div class="action-card borrow" onclick="handleBorrow()">
                    <i class="fas fa-box-open action-icon"></i>
                    <h2 class="action-title">Borrow Equipment</h2>
                    <p class="action-description">Select equipment to borrow from the inventory</p>
                    <span class="action-badge success">
                        <i class="fas fa-check-circle"></i> Available
                    </span>
                </div>
                
                <!-- Return Card -->
                <?php if ($has_borrowed_items): ?>
                <div class="action-card return" onclick="handleReturn()">
                    <i class="fas fa-undo-alt action-icon"></i>
                    <h2 class="action-title">Return Equipment</h2>
                    <p class="action-description">Return your borrowed equipment</p>
                    <span class="action-badge info">
                        <i class="fas fa-info-circle"></i> <?= $borrowed_count ?> item(s) to return
                    </span>
                </div>
                <?php else: ?>
                <div class="action-card return disabled">
                    <i class="fas fa-undo-alt action-icon" style="color: #999;"></i>
                    <h2 class="action-title" style="color: #999;">Return Equipment</h2>
                    <p class="action-description">Return your borrowed equipment</p>
                    <span class="action-badge warning">
                        <i class="fas fa-times-circle"></i> No borrowed items
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; <?= date('Y') ?> De La Salle Araneta University. All rights reserved.</p>
        </div>
    </div>

    <script>
        function handleBorrow() {
            window.location.href = 'borrow.php';
        }
        
        function handleReturn() {
            <?php if ($has_borrowed_items): ?>
            window.location.href = 'return.php';
            <?php endif; ?>
        }
        
        function logout() {
            // Create modern confirmation modal
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    backdrop-filter: blur(5px);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                    animation: fadeIn 0.3s ease;
                ">
                    <div style="
                        background: white;
                        border-radius: 25px;
                        padding: 50px 60px;
                        text-align: center;
                        max-width: 500px;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                        animation: slideUp 0.3s ease;
                    ">
                        <div style="
                            width: 80px;
                            height: 80px;
                            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 25px;
                            box-shadow: 0 10px 30px rgba(238, 90, 111, 0.3);
                        ">
                            <i class="fas fa-sign-out-alt" style="font-size: 40px; color: white;"></i>
                        </div>
                        <h2 style="
                            font-size: 1.8rem;
                            color: #333;
                            margin-bottom: 15px;
                            font-weight: 700;
                        ">Confirm Logout</h2>
                        <p style="
                            font-size: 1.1rem;
                            color: #666;
                            margin-bottom: 35px;
                            line-height: 1.6;
                        ">Are you sure you want to logout?<br>You will need to scan your RFID again to continue.</p>
                        <div style="display: flex; gap: 15px; justify-content: center;">
                            <button onclick="this.closest('div').parentElement.parentElement.remove()" style="
                                background: #f5f5f5;
                                color: #666;
                                border: none;
                                padding: 15px 35px;
                                border-radius: 12px;
                                font-size: 1rem;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.3s ease;
                            " onmouseover="this.style.background='#e0e0e0'" onmouseout="this.style.background='#f5f5f5'">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button onclick="confirmLogout()" style="
                                background: linear-gradient(135deg, #1e5631, #2d7a45);
                                color: white;
                                border: none;
                                padding: 15px 35px;
                                border-radius: 12px;
                                font-size: 1rem;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                box-shadow: 0 4px 15px rgba(30, 86, 49, 0.3);
                            " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(30, 86, 49, 0.4)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 4px 15px rgba(30, 86, 49, 0.3)'">
                                <i class="fas fa-check"></i> Yes, Logout
                            </button>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideUp {
                        from { transform: translateY(50px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                    @keyframes spin {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                </style>
            `;
            document.body.appendChild(modal);
        }
        
        function confirmLogout() {
            // Show modern loading overlay
            document.body.innerHTML = `
                <div style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(135deg, #1e5631, #2d7a45);
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                    animation: fadeIn 0.3s ease;
                ">
                    <div style="text-align: center;">
                        <div style="
                            width: 120px;
                            height: 120px;
                            border: 8px solid rgba(255, 255, 255, 0.2);
                            border-top-color: white;
                            border-radius: 50%;
                            margin: 0 auto 30px;
                            animation: spin 1s linear infinite;
                        "></div>
                        <h2 style="
                            font-size: 2.5rem;
                            color: white;
                            margin-bottom: 15px;
                            font-weight: 700;
                            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                        ">Logging Out...</h2>
                        <p style="
                            font-size: 1.3rem;
                            color: rgba(255, 255, 255, 0.9);
                            font-weight: 500;
                        ">Thank you for using the kiosk</p>
                        <div style="
                            margin-top: 30px;
                            display: flex;
                            gap: 8px;
                            justify-content: center;
                        ">
                            <div style="width: 12px; height: 12px; background: white; border-radius: 50%; animation: bounce 1s infinite 0s;"></div>
                            <div style="width: 12px; height: 12px; background: white; border-radius: 50%; animation: bounce 1s infinite 0.2s;"></div>
                            <div style="width: 12px; height: 12px; background: white; border-radius: 50%; animation: bounce 1s infinite 0.4s;"></div>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes spin {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes bounce {
                        0%, 100% { transform: translateY(0); opacity: 0.4; }
                        50% { transform: translateY(-15px); opacity: 1; }
                    }
                </style>
            `;
            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 1500);
        }
        
        // Auto-logout after 5 minutes of inactivity
        let inactivityTime = function () {
            let time;
            window.onload = resetTimer;
            document.onmousemove = resetTimer;
            document.onkeypress = resetTimer;
            document.onclick = resetTimer;
            document.ontouchstart = resetTimer;

            function logout() {
                window.location.href = 'logout.php';
            }

            function resetTimer() {
                clearTimeout(time);
                time = setTimeout(logout, 300000); // 5 minutes
            }
        };
        
        inactivityTime();
    </script>
</body>
</html>
