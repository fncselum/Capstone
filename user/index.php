<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";

$conn = @new mysqli($host, $user, $password, $dbname);
$maintenance_mode = false;
$face_required = isset($_GET['face']) && $_GET['face'] === 'required';

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
    <style>
        .face-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            backdrop-filter: blur(8px);
        }
        
        .face-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .face-modal-box {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            border-radius: 20px;
            padding: 30px;
            max-width: 1200px;
            width: 90%;
            height: auto;
            max-height: none;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(0, 255, 65, 0.4);
            color: white;
        }
        
        .face-modal-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }
        
        .face-camera-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .face-video-frame {
            position: relative;
            background: #000;
            border-radius: 16px;
            overflow: hidden;
            border: 3px solid rgba(0, 255, 65, 0.6);
            box-shadow: 0 0 30px rgba(0, 255, 65, 0.3);
            aspect-ratio: 4/3;
            min-height: 300px;
        }
        
        .face-video-frame video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block !important;
            background: #000;
            border-radius: 13px;
            position: relative;
            z-index: 1;
        }
        
        .face-video-frame canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 2;
        }
        
        /* Ensure video is visible when loaded */
        .face-video-frame video[src] {
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .face-video-frame video:not([src]) {
            opacity: 0.5;
        }
        
        .face-user-info {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .user-profile-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(0, 255, 65, 0.6);
            margin: 0 auto 15px;
            display: block;
            box-shadow: 0 0 20px rgba(0, 255, 65, 0.3);
        }
        
        .user-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #00ff41;
            margin: 0;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .user-detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            border-left: 3px solid rgba(0, 255, 65, 0.6);
        }
        
        .user-detail-item i {
            color: #00ff41;
            width: 20px;
            text-align: center;
        }
        
        .user-detail-label {
            font-weight: 600;
            color: #94a3b8;
            min-width: 80px;
        }
        
        .user-detail-value {
            color: white;
            font-weight: 500;
        }
        
        .face-status-message {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 15px;
            color: white;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 3px solid rgba(0, 255, 65, 0.6);
        }
        
        .face-status-message.success {
            background: rgba(0, 255, 65, 0.1);
            border-left-color: #00ff41;
            color: #00ff41;
        }
        
        .face-status-message.error {
            background: rgba(255, 65, 65, 0.1);
            border-left-color: #ff4141;
            color: #ff4141;
        }
        
        .face-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .face-actions button {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .face-btn-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .face-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .face-btn-retry {
            background: linear-gradient(135deg, #00ff41, #00cc33);
            color: #000;
        }
        
        .face-btn-retry:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 65, 0.4);
        }
        
        .face-btn-retry:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        @media (max-width: 768px) {
            .face-modal-body {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .face-modal-box {
                width: 95%;
                padding: 20px;
            }
        }

        .face-modal-header h2 {
            margin: 0;
            font-size: 1.65rem;
        }
        
        .face-ref-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #f2f8f5;
            border: 1px solid #d9eee4;
        }

        .face-ref-preview img {
            width: 72px;
            height: 72px;
            border-radius: 14px;
            object-fit: cover;
            border: 2px solid #19a974;
        }

        .face-status-message {
            min-height: 48px;
            border-radius: 14px;
            padding: 16px;
            background: #f1f5f9;
            color: #0f172a;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .face-status-message.success {
            background: #dcfce7;
            color: #166534;
        }

        .face-status-message.error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .face-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 12px;
        }

        .face-actions button {
            border: none;
            border-radius: 12px;
            padding: 12px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .face-actions button[disabled] {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .face-btn-cancel {
            background: #f8fafc;
            color: #475569;
        }

        .face-btn-retry {
            background: linear-gradient(135deg, #15803d, #19a974);
            color: #ffffff;
            box-shadow: 0 6px 16px rgba(21, 128, 61, 0.25);
        }

        @media (max-width: 840px) {
            .face-modal-body {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body data-face-required="<?= $face_required ? '1' : '0' ?>">
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

                        <div class="scanner-help" id="faceReminder" style="display:none; color:#0d3b2e; font-weight:600;">
                            Face verification required – please align your face with the camera when prompted.
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($face_required): ?>
                    <div class="face-required-alert" role="alert">
                        <i class="fas fa-user-lock"></i> Face verification is required to continue. Please rescan your RFID card to restart the security check.
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

    <!-- Face Verification Modal -->
    <div id="faceModal" class="face-modal" aria-hidden="true" role="dialog">
        <div class="face-modal-box">
            <div class="face-modal-body">
                <!-- Camera Section -->
                <div class="face-camera-section">
                    <div class="face-video-frame">
                        <video id="faceVideo" autoplay muted playsinline></video>
                        <canvas id="faceCanvas"></canvas>
                    </div>
                    <div id="faceStatusMessage" class="face-status-message">
                        <i class="fas fa-spinner fa-pulse"></i> Initializing face detection…
                    </div>
                    <div class="face-actions">
                        <button type="button" id="faceCancelBtn" class="face-btn-cancel" style="display:none;">
                            <i class="fas fa-arrow-left"></i> Back to RFID
                        </button>
                        <button type="button" id="faceRetryBtn" class="face-btn-retry" disabled>
                            <i class="fas fa-sync"></i> Retry Scan
                        </button>
                    </div>
                </div>
                
                <!-- User Info Panel -->
                <div class="face-user-info">
                    <div class="user-profile-header">
                        <div class="user-name-section">
                            <h3 id="userName">Loading...</h3>
                            <div id="userStudentId" class="user-student-id">0045458186</div>
                        </div>
                    </div>
                    <div class="user-details">
                        <div class="user-detail-item">
                            <i class="fas fa-id-card"></i>
                            <span class="user-detail-label">Student ID:</span>
                            <span id="userStudentId" class="user-detail-value">Loading...</span>
                        </div>
                        <div class="user-detail-item">
                            <i class="fas fa-envelope"></i>
                            <span class="user-detail-label">Email:</span>
                            <span id="userEmail" class="user-detail-value">Loading...</span>
                        </div>
                        <div class="user-detail-item">
                            <i class="fas fa-wifi"></i>
                            <span class="user-detail-label">RFID Tag:</span>
                            <span id="userRfidTag" class="user-detail-value">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script src="script.js?v=<?= time() ?>"></script>
</body>
</html>
