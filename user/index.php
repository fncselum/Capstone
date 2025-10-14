<?php
session_start();
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
            
            <!-- RFID Scanner Section -->
            <div class="scanner-section">
                <div class="scanner-card">
                    <div class="scanner-icon-wrapper">
                        <i class="fas fa-id-card scanner-icon"></i>
                        <div class="pulse-ring"></div>
                    </div>
                    
                    <h2 class="scanner-title">Ready to Scan</h2>
                    <p class="scanner-instruction">Place your RFID card near the scanner</p>
                    
                    <!-- Hidden RFID Input -->
                    <input type="text" id="rfidInput" class="rfid-input" autocomplete="off" autofocus>
                    
                    <!-- Manual Input Option -->
                    <button class="manual-input-btn" onclick="toggleManualInput()">
                        <i class="fas fa-keyboard"></i> Manual Entry
                    </button>
                    
                    <!-- Manual Input Form (Hidden by default) -->
                    <div id="manualInputForm" class="manual-input-form" style="display: none;">
                        <input type="text" id="manualRfidInput" placeholder="Enter RFID or Student ID" class="manual-rfid-field">
                        <button onclick="submitManualRfid()" class="submit-manual-btn">
                            <i class="fas fa-arrow-right"></i> Submit
                        </button>
                    </div>
                    
                    <!-- Status Message -->
                    <div id="statusMessage" class="status-message"></div>
                </div>
                
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
            <p>&copy; <?= date('Y') ?> De La Salle Araneta University. All rights reserved.</p>
        </div>
    </div>

    <script src="script.js?v=<?= time() ?>"></script>
</body>
</html>
