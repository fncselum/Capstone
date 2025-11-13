<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
$maintenance_mode = false;

// Check if maintenance mode is enabled
if (!$conn->connect_error) {
    $table_check = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $maintenance_mode = ($row['setting_value'] == '1');
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Kiosk - RFID Scanner</title>
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="scanner-styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
                        <h1 class="welcome-title">Equipment Kiosk System</h1>
                        <p class="subtitle">Scan your RFID card to get started</p>
                    </div>
                </div>
            </div>
            
            <!-- RFID Scanner Section or Maintenance Message -->
            <div class="scanner-section">
                <?php if ($maintenance_mode): ?>
                    <!-- Maintenance Mode Message -->
                    <div class="scanner-card maintenance-card">
                        <div class="scanner-icon-wrapper">
                            <i class="fas fa-tools scanner-icon maintenance-icon"></i>
                        </div>
                        
                        <h2 class="scanner-title maintenance-title">System Under Maintenance</h2>
                        <p class="scanner-instruction maintenance-text">
                            We're currently performing scheduled maintenance to improve your experience.
                            The system will be back online shortly.
                        </p>
                        
                        <div class="maintenance-info">
                            <p><i class="fas fa-clock"></i> Please check back later</p>
                            <p><i class="fas fa-envelope"></i> For urgent concerns, contact the administrator</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Normal Scanner Interface -->
                    <div class="scanner-card">
                        <div class="scanner-icon-wrapper">
                            <i class="fas fa-id-card scanner-icon"></i>
                            <div class="pulse-ring"></div>
                        </div>

                        <div class="scanner-status-wrap">
                            <span class="status-indicator ready" id="scannerIndicator" aria-hidden="true"></span>
                            <div class="status-text">
                                <h2 class="scanner-title" id="scannerStatusLabel">Ready to Scan</h2>
                                <p class="scanner-instruction" id="scannerInstruction">Place your RFID card near the scanner</p>
                            </div>
                        </div>

                        <!-- Hidden RFID Input (Auto-scan only) -->
                        <input type="text" id="rfidInput" class="rfid-input" autocomplete="off" autofocus>

                        <!-- Status Message -->
                        <div id="statusMessage" class="status-message" role="status" aria-live="polite"></div>

                        <p class="scanner-help">If your card isn't recognized, please contact the administrator.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="stat-item">
                        <i class="fas fa-boxes"></i>
                        <span>Available Equipment</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-clock"></i>
                        <span>24/7 Access</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure System</span>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="instructions-section">
                <h3><i class="fas fa-info-circle"></i> How to Use</h3>
                <div class="instruction-steps">
                    <div class="step">
                        <span class="step-number">1</span>
                        <span class="step-text">Scan your RFID card</span>
                    </div>
                    <div class="step">
                        <span class="step-number">2</span>
                        <span class="step-text">Select equipment to borrow or return</span>
                    </div>
                    <div class="step">
                        <span class="step-number">3</span>
                        <span class="step-text">Confirm your transaction</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; <?= date('Y') ?> De La Salle Andres Soriano Memorial College (ASMC). All rights reserved.</p>
        </div>
    </div>

    <script src="script.js?v=<?= time() ?>"></script>
</body>
</html>
