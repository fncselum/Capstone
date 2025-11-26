# Face Recognition System - SMARTBORROW Kiosk

## Overview

This document describes the comprehensive face recognition security system implemented for the SMARTBORROW kiosk. The system provides enhanced security by requiring both RFID card verification and facial recognition before granting access to equipment borrowing/returning functions.

## Features

### 🔐 **Dual Authentication**
- **RFID Verification**: Primary authentication using student/staff ID cards
- **Face Recognition**: Secondary biometric verification using stored profile photos
- **Session Management**: Secure session handling with automatic expiration

### 👤 **Facial Recognition Capabilities**
- **68-Point Landmark Detection**: Precise facial feature mapping
- **Real-time Face Tracking**: Live webcam feed with overlay visualization
- **Bounding Box Detection**: Visual feedback showing detected face area
- **Match Percentage Display**: Real-time similarity scoring
- **Configurable Threshold**: Adjustable match sensitivity (default: 85%)

### 🛡️ **Security Features**
- **Bypass Prevention**: Server-side session validation prevents URL manipulation
- **Session Timeout**: Automatic logout after inactivity
- **Security Logging**: Comprehensive audit trail of all authentication events
- **Suspicious Activity Detection**: Monitoring for unusual access patterns

## System Architecture

### Frontend Components

#### 1. **Face Recognition Modal** (`user/index.php`)
```html
<!-- Enhanced modal with professional UI -->
<div id="faceModal" class="face-modal">
    <div class="face-modal-box">
        <div class="face-video-frame">
            <video id="faceVideo" autoplay muted playsinline></video>
            <canvas id="faceCanvas"></canvas> <!-- Landmark overlay -->
        </div>
        <div class="face-status-panel">
            <!-- Reference photo preview and status messages -->
        </div>
    </div>
</div>
```

#### 2. **Face Recognition Engine** (`user/script.js`)
```javascript
// Core face recognition functionality
- Model Loading: TinyFaceDetector, FaceLandmark68Net, FaceRecognitionNet
- Real-time Detection: 68-point facial landmarks with bounding box
- Embedding Comparison: Euclidean distance calculation
- Visual Feedback: Green landmarks and match percentage display
```

### Backend Components

#### 1. **Session Handler** (`user/session-handler.php`)
```php
class SecuritySessionHandler {
    // Secure session management
    - initRFIDSession()     // Initialize RFID verification
    - setFaceVerified()     // Set face verification flag
    - requireFullAuth()     // Enforce dual authentication
    - logSecurityEvent()    // Audit trail logging
}
```

#### 2. **Photo API** (`user/api/get_user_photo.php`)
```php
// Secure photo retrieval from longblob storage
- Session validation
- Binary data to base64 conversion
- MIME type detection
- Error handling
```

#### 3. **RFID Validation** (`user/validate_rfid.php`)
```php
// Enhanced RFID processing
- Database user lookup
- Session initialization
- Photo availability check
- Security logging
```

## Database Schema

### Users Table
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rfid_tag VARCHAR(255) UNIQUE,
    student_id VARCHAR(255) UNIQUE,
    photo_path LONGBLOB,  -- Binary image data
    status ENUM('Active', 'Inactive', 'Suspended'),
    penalty_points INT DEFAULT 0,
    is_admin BOOLEAN DEFAULT FALSE,
    admin_level VARCHAR(50) DEFAULT 'user'
);
```

### Security Logs
```sql
-- Automatic logging via SecuritySessionHandler
- Authentication events
- Page access attempts
- Suspicious activity detection
- Session management events
```

## Installation & Setup

### 1. **Model Files Setup**
```bash
# Navigate to models directory
cd /xampp/htdocs/Capstone/models

# Run the model downloader (requires Node.js)
node download_models.js

# Verify model files are present:
- tiny_face_detector_model-weights_manifest.json
- tiny_face_detector_model-shard1
- face_landmark_68_model-weights_manifest.json
- face_landmark_68_model-shard1
- face_recognition_model-weights_manifest.json
- face_recognition_model-shard1
- face_recognition_model-shard2
```

### 2. **Directory Permissions**
```bash
# Ensure proper permissions for photo storage and logging
chmod 755 /xampp/htdocs/Capstone/uploads/
chmod 755 /xampp/htdocs/Capstone/logs/
```

### 3. **Database Configuration**
```php
// Update connection settings in all PHP files
$host = "localhost";
$user = "root";
$password = "";
$dbname = "capstone";
```

## Usage Flow

### 1. **RFID Scan**
```
User scans RFID card → validate_rfid.php
├── User lookup in database
├── Status verification (Active/Inactive/Suspended)
├── Photo availability check
├── Session initialization
└── Response with photo URL and verification status
```

### 2. **Face Recognition**
```
RFID Success → Face Modal Opens
├── Load face-api.js models
├── Start webcam stream
├── Load reference photo (from longblob)
├── Real-time face detection loop
│   ├── Detect 68-point landmarks
│   ├── Draw bounding box and landmarks
│   ├── Calculate face embedding
│   ├── Compare with reference embedding
│   └── Display match percentage
├── Threshold check (default: 85% match)
└── Success → verify_face.php → Redirect to borrow-return.php
```

### 3. **Protected Page Access**
```
Page Load → SecuritySessionHandler::requireFullAuth()
├── Check RFID verification status
├── Check face verification status
├── Validate session integrity
├── Check session timeout
└── Allow access OR redirect to index.php?face=required
```

## Configuration Options

### Face Recognition Threshold
```javascript
// In user/script.js
let faceVerificationThreshold = 0.15; // Lower = stricter (0.6 loose, 0.4 medium, 0.2 strict)
```

### Session Timeouts
```php
// In user/session-handler.php
private static $security_timeout = 1800; // 30 minutes for RFID
// Face timeout = RFID timeout / 2 (15 minutes)
```

### Visual Customization
```css
/* Face modal styling in user/index.php */
.face-video-frame {
    border: 3px solid rgba(0, 255, 65, 0.5);
    box-shadow: 0 0 30px rgba(0, 255, 65, 0.2);
}

/* Landmark colors in user/script.js */
ctx.strokeStyle = '#00ff41'; // Green landmarks
ctx.fillStyle = '#00ff41';   // Green dots
```

## Security Considerations

### 1. **Bypass Prevention**
- All protected pages use `SecuritySessionHandler::requireFullAuth()`
- Direct URL access blocked without proper session flags
- Session token validation prevents session hijacking

### 2. **Photo Security**
- Photos stored as binary data in database (not file system)
- Secure API endpoint with session validation
- Base64 encoding for client-side usage

### 3. **Audit Trail**
- All authentication events logged
- Security violations tracked
- Suspicious activity detection

### 4. **Session Management**
- Automatic session cleanup on timeout
- Separate timeouts for RFID and face verification
- Secure token generation

## Troubleshooting

### Common Issues

#### 1. **"No reference photo found" Error**
```
Cause: User has no photo in database or photo_path is empty
Solution: Upload photo via admin panel (admin-authorized-users.php)
```

#### 2. **Face Models Not Loading**
```
Cause: Model files missing or incorrect path
Solution: 
1. Check /Capstone/models/ directory
2. Run node download_models.js
3. Verify file permissions
```

#### 3. **Camera Access Denied**
```
Cause: Browser permissions or HTTPS requirement
Solution:
1. Allow camera access in browser
2. Use HTTPS for production
3. Check browser compatibility
```

#### 4. **Session Timeout Issues**
```
Cause: Session expiration or server-side session cleanup
Solution:
1. Check session timeout settings
2. Verify session handler inclusion
3. Check server session configuration
```

### Debug Mode
```javascript
// Enable debug logging in user/script.js
console.log('Face detection result:', detection);
console.log('Match distance:', distance);
console.log('Threshold:', faceVerificationThreshold);
```

## Performance Optimization

### 1. **Model Loading**
- Models loaded once and cached
- Lazy loading on first face verification
- Error handling for network issues

### 2. **Face Detection**
- Optimized detection options (scoreThreshold: 0.5, inputSize: 256)
- RequestAnimationFrame for smooth rendering
- Canvas clearing for performance

### 3. **Database Queries**
- Prepared statements for security
- Efficient photo retrieval
- Connection pooling considerations

## Browser Compatibility

### Supported Browsers
- ✅ Chrome 70+
- ✅ Firefox 65+
- ✅ Safari 12+
- ✅ Edge 79+

### Required Features
- WebRTC (getUserMedia)
- Canvas API
- ES6 Async/Await
- Fetch API

## Maintenance

### Regular Tasks
1. **Log Rotation**: Clean up security logs periodically
2. **Model Updates**: Check for face-api.js model updates
3. **Session Cleanup**: Monitor session storage usage
4. **Photo Management**: Optimize database photo storage

### Monitoring
- Check security logs for unusual patterns
- Monitor face recognition success rates
- Track session timeout frequencies
- Review photo upload/retrieval performance

## Support

For technical support or feature requests:
1. Check this documentation first
2. Review security logs for error details
3. Test with different browsers/devices
4. Contact system administrator

---

**Last Updated**: November 2024  
**Version**: 2.0  
**Author**: SMARTBORROW Development Team
