// ===== Equipment Kiosk - Enhanced RFID Scanner Script =====

// Scanner state references
let scannerIndicator = null;
let scannerStatusLabel = null;
let scannerInstruction = null;
let scannerResetTimer = null;
let audioContext = null;

// Face verification state variables
let faceModelsLoaded = false;
let faceRecognitionActive = false;
let faceVerificationThreshold = 0.5; // Lower is stricter for face-api.js euclidean distance (0.6 = loose, 0.5 = medium, 0.2 = strict)
let referenceDescriptor = null;
let referencePhotoUrl = null;
let faceVideoStream = null;
let faceDetectionInterval = null;
let landmarkDrawingEnabled = true;
const faceState = {
    modal: null,
    video: null,
    canvas: null,
    statusMessage: null,
    retryBtn: null,
    cancelBtn: null,
    faceReminder: null,
    referencePreview: null,
    ctx: null,
    userAvatar: null,
    userName: null,
    userStudentId: null,
    userEmail: null,
    userRfidTag: null
};

const scannerStates = {
    ready: {
        title: 'Ready to Scan',
        instruction: 'Place your RFID card near the scanner'
    },
    scanning: {
        title: 'Scanning card…',
        instruction: 'Hold your RFID card steady until you hear the beep.'
    },
    success: {
        title: 'RFID Verified!',
        instruction: 'Access granted. Redirecting now…'
    },
    error: {
        title: 'Card Not Recognized',
        instruction: 'Please try again or contact the administrator.'
    }
};

// Auto-focus on RFID input
document.addEventListener('DOMContentLoaded', function() {
    const rfidInput = document.getElementById('rfidInput');
    const statusMessage = document.getElementById('statusMessage');
    scannerIndicator = document.getElementById('scannerIndicator');
    scannerStatusLabel = document.getElementById('scannerStatusLabel');
    scannerInstruction = document.getElementById('scannerInstruction');
    faceState.modal = document.getElementById('faceModal');
    faceState.video = document.getElementById('faceVideo');
    faceState.canvas = document.getElementById('faceCanvas');
    faceState.ctx = faceState.canvas ? faceState.canvas.getContext('2d') : null;
    faceState.statusMessage = document.getElementById('faceStatusMessage');
    faceState.retryBtn = document.getElementById('faceRetryBtn');
    faceState.cancelBtn = document.getElementById('faceCancelBtn');
    faceState.faceReminder = document.getElementById('faceReminder');
    faceState.referencePreview = document.getElementById('referencePhotoPreview');
    faceState.userAvatar = document.getElementById('userAvatar');
    faceState.userName = document.getElementById('userName');
    faceState.userStudentId = document.getElementById('userStudentId');
    faceState.userEmail = document.getElementById('userEmail');
    faceState.userRfidTag = document.getElementById('userRfidTag');

    if (faceState.retryBtn) {
        faceState.retryBtn.addEventListener('click', () => {
            if (faceRecognitionActive) return;
            startFaceVerificationFlow(referencePhotoUrl);
        });
    }

    if (faceState.cancelBtn) {
        faceState.cancelBtn.addEventListener('click', () => {
            stopFaceVerification();
            showStatus('Face identity mismatch. Access denied.', 'error');
            const rfidInputEl = document.getElementById('rfidInput');
            if (rfidInputEl) {
                rfidInputEl.value = '';
                rfidInputEl.focus();
            }
        });
    }

    setScannerState('ready');
    
    // Keep focus on RFID input
    if (rfidInput) {
        rfidInput.focus();
        
        // Refocus if user clicks elsewhere (but not on buttons or inputs)
        document.addEventListener('click', function(e) {
            const isButton = e.target.closest('button');
            const isInput = e.target.tagName === 'INPUT';
            
            if (!isButton && !isInput && document.activeElement !== rfidInput) {
                rfidInput.focus();
            }
        });
        
        // Handle RFID scan
        rfidInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const rfidValue = rfidInput.value.trim();
                
                if (rfidValue) {
                    processRFID(rfidValue);
                }
            }
        });
        
        // Show scanning status when typing
        rfidInput.addEventListener('input', function() {
            if (rfidInput.value.length > 0) {
                showStatus('Scanning card…', 'scanning');
            } else {
                setScannerState('ready');
                clearScannerStatus();
            }
        });
    }
});


// Process RFID scan
function processRFID(rfid) {
    const statusMessage = document.getElementById('statusMessage');
    const rfidInput = document.getElementById('rfidInput');
    
    // Show processing status
    showStatus('Processing RFID…', 'scanning');
    
    // Send RFID to server for validation
    fetch('validate_rfid.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'rfid=' + encodeURIComponent(rfid)
    })
    .then(response => response.json())
    .then(async data => {
        if (data.success) {
            // Face verification required for non-admin users
            if (data.is_admin) {
                showStatus('✓ Admin RFID Verified! Redirecting…', 'success');
                setTimeout(() => { window.location.href = '../admin/dashboard.php'; }, 900);
                return;
            }

            referencePhotoUrl = data.reference_photo_url || null;
            const hasReference = !!data.has_reference_photo;

            if (!hasReference) {
                showStatus('✗ No reference photo on file. Please contact administrator.', 'error');
                resetRFIDInput();
                return;
            }

            // Populate user information
            populateUserInfo(data);

            showStatus('RFID verified. Starting face recognition…', 'success');
            if (faceState.faceReminder) {
                faceState.faceReminder.style.display = 'block';
            }
            await startFaceVerificationFlow(referencePhotoUrl);
        } else {
            showStatus('✗ ' + (data.message || 'Invalid RFID. Please try again.'), 'error');
            resetRFIDInput();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showStatus('✗ Connection error. Please try again.', 'error');
        resetRFIDInput();
    });
}

function resetRFIDInput() {
    const rfidInput = document.getElementById('rfidInput');
    if (rfidInput) {
        rfidInput.value = '';
        rfidInput.focus();
    }
}

// Show status message and update scanner state
function showStatus(message, type) {
    const statusMessage = document.getElementById('statusMessage');
    setScannerState(type);

    if (statusMessage) {
        statusMessage.textContent = message;
        statusMessage.className = 'status-message ' + type;
        statusMessage.style.display = 'block';
        statusMessage.dataset.state = type;

        if (type === 'success' || type === 'error') {
            playScannerTone(type);
        }

        // Auto-hide after delay for non-scanning messages
        if (type !== 'scanning') {
            const hideDelay = type === 'error' ? 4000 : 2500;
            clearTimeout(scannerResetTimer);
            scannerResetTimer = setTimeout(() => {
                if (statusMessage.dataset.state === type) {
                    clearScannerStatus();
                    setScannerState('ready');
                }
            }, hideDelay);
        }
    }
}

function clearScannerStatus() {
    const statusMessage = document.getElementById('statusMessage');
    if (statusMessage) {
        statusMessage.style.display = 'none';
        statusMessage.className = 'status-message';
        statusMessage.textContent = '';
        statusMessage.dataset.state = '';
    }
}

function setScannerState(state) {
    if (!scannerIndicator || !scannerStatusLabel || !scannerInstruction) {
        return;
    }

    const config = scannerStates[state] || scannerStates.ready;

    scannerStatusLabel.textContent = config.title;
    scannerInstruction.textContent = config.instruction;
    scannerIndicator.setAttribute('aria-label', config.title);

    scannerIndicator.classList.remove('ready', 'scanning', 'success', 'error');
    if (state === 'ready' || state === undefined) {
        scannerIndicator.classList.add('ready');
    } else {
        scannerIndicator.classList.add(state);
    }
}

function playScannerTone(type) {
    try {
        const AudioCtor = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtor) return;

        if (!audioContext) {
            audioContext = new AudioCtor();
        }

        if (audioContext.state === 'suspended') {
            audioContext.resume();
        }

        const now = audioContext.currentTime;
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        const duration = type === 'error' ? 0.4 : 0.25;

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(type === 'success' ? 880 : 330, now);

        gainNode.gain.setValueAtTime(0.0001, now);
        gainNode.gain.exponentialRampToValueAtTime(0.2, now + 0.02);
        gainNode.gain.exponentialRampToValueAtTime(0.0001, now + duration);

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        oscillator.start(now);
        oscillator.stop(now + duration);
    } catch (err) {
        console.warn('Scanner tone unavailable:', err);
    }
}

// Prevent form submission on Enter (for accessibility)
document.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        const activeElement = document.activeElement;
        if (activeElement.id === 'rfidInput') {
            e.preventDefault();
        }
    }
});

// ===== Auto-Refresh on Maintenance Mode Change =====
let currentMaintenanceState = null;

// Check maintenance mode status every 5 seconds
function checkMaintenanceStatus() {
    fetch('check_maintenance.php')
        .then(response => response.json())
        .then(data => {
            // Initialize state on first check
            if (currentMaintenanceState === null) {
                currentMaintenanceState = data.maintenance_mode;
                return;
            }
            
            // If state changed, show notification and reload
            if (currentMaintenanceState !== data.maintenance_mode) {
                console.log('Maintenance mode changed. Refreshing page...');
                
                // Show brief notification
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: rgba(0, 102, 51, 0.95);
                    color: white;
                    padding: 20px 40px;
                    border-radius: 12px;
                    font-size: 1.2rem;
                    font-weight: 600;
                    z-index: 10000;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
                    text-align: center;
                `;
                notification.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> System status updated...';
                document.body.appendChild(notification);
                
                // Reload after brief delay
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        })
        .catch(error => {
            console.error('Error checking maintenance status:', error);
        });
}

// Start checking every 5 seconds
setInterval(checkMaintenanceStatus, 5000);

// Initial check
checkMaintenanceStatus();

// ===== Face Verification Logic =====

// Helper function to resize images for better face-api.js compatibility
async function resizeImage(img, maxWidth, maxHeight) {
    return new Promise((resolve) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Calculate new dimensions maintaining aspect ratio
        let { width, height } = img;
        if (width > maxWidth || height > maxHeight) {
            const ratio = Math.min(maxWidth / width, maxHeight / height);
            width *= ratio;
            height *= ratio;
        }
        
        canvas.width = width;
        canvas.height = height;
        
        // Draw resized image
        ctx.drawImage(img, 0, 0, width, height);
        
        // Convert canvas to image
        const resizedImg = new Image();
        resizedImg.onload = () => resolve(resizedImg);
        resizedImg.src = canvas.toDataURL('image/jpeg', 0.8);
    });
}

// Debug function to test camera directly
window.testCamera = async function() {
    console.log('Testing camera directly...');
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        const video = document.getElementById('faceVideo');
        if (video) {
            video.srcObject = stream;
            video.autoplay = true;
            video.muted = true;
            video.playsInline = true;
            await video.play();
            console.log('Camera test successful - video should be visible');
        }
    } catch (error) {
        console.error('Camera test failed:', error);
    }
};

// Debug function to test camera with modal open
window.testCameraInModal = async function() {
    console.log('Testing camera in modal...');
    // Open modal first
    if (faceState.modal) {
        faceState.modal.classList.add('active');
    }
    
    try {
        await startCameraStream();
        console.log('Camera in modal test successful');
    } catch (error) {
        console.error('Camera in modal test failed:', error);
    }
};

function populateUserInfo(userData) {
    if (faceState.userName) {
        faceState.userName.textContent = userData.user_name || userData.student_id || 'Unknown User';
    }
    if (faceState.userStudentId) {
        faceState.userStudentId.textContent = userData.student_id || 'N/A';
    }
    if (faceState.userEmail) {
        faceState.userEmail.textContent = userData.user_email || 'Not provided';
    }
    if (faceState.userRfidTag) {
        faceState.userRfidTag.textContent = userData.rfid_tag || 'N/A';
    }
    
    // Set user avatar (fetch from longblob via API)
    if (faceState.userAvatar) {
        const defaultAvatar = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iNjAiIGN5PSI2MCIgcj0iNjAiIGZpbGw9IiM0Yjc2ODgiLz48Y2lyY2xlIGN4PSI2MCIgY3k9IjQ1IiByPSIyMCIgZmlsbD0iI2ZmZiIvPjxwYXRoIGQ9Im0yMCA5NWMwLTIyIDIwLTQwIDQwLTQwczQwIDE4IDQwIDQwIiBmaWxsPSIjZmZmIi8+PC9zdmc+';
        
        // Try to fetch user photo from API
        fetch('api/get_user_photo.php')
            .then(response => response.json())
            .then(result => {
                if (result.success && result.dataUrl) {
                    faceState.userAvatar.src = result.dataUrl;
                    console.log('User photo loaded successfully');
                } else {
                    console.log('No user photo found, using default avatar');
                    faceState.userAvatar.src = defaultAvatar;
                }
            })
            .catch(error => {
                console.error('Error fetching user photo:', error);
                faceState.userAvatar.src = defaultAvatar;
            });
    }
}

async function ensureFaceModelsLoaded() {
    if (faceModelsLoaded) return true;
    
    updateFaceStatus('Loading face detection models…', 'loading');
    
    try {
        // Load the three required models for face recognition with landmarks
        await faceapi.nets.tinyFaceDetector.loadFromUri('/Capstone/models');
        await faceapi.nets.faceLandmark68Net.loadFromUri('/Capstone/models');
        await faceapi.nets.faceRecognitionNet.loadFromUri('/Capstone/models');
        
        faceModelsLoaded = true;
        updateFaceStatus('Face detection models loaded successfully', 'success');
        console.log('Face-api.js models loaded: TinyFaceDetector, FaceLandmark68Net, FaceRecognitionNet');
        return true;
    } catch (error) {
        console.error('Failed to load face-api models:', error);
        updateFaceStatus('Failed to load face detection models. Please check model files.', 'error');
        return false;
    }
}

function openFaceModal() {
    if (!faceState.modal) return;
    faceState.modal.classList.add('active');
    faceState.modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    
    // Debug: Log modal and video element status
    console.log('Face modal opened');
    console.log('Video element:', faceState.video);
    console.log('Canvas element:', faceState.canvas);
    console.log('Modal visible:', faceState.modal.classList.contains('active'));
}

function closeFaceModal() {
    if (!faceState.modal) return;
    faceState.modal.classList.remove('active');
    faceState.modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function updateFaceStatus(message, type = 'info') {
    if (!faceState.statusMessage) return;
    faceState.statusMessage.textContent = message;
    faceState.statusMessage.className = 'face-status-message';
    if (type === 'success') {
        faceState.statusMessage.classList.add('success');
    } else if (type === 'error') {
        faceState.statusMessage.classList.add('error');
    }
}

async function startFaceVerificationFlow(photoUrl) {
    if (faceRecognitionActive) return;
    referenceDescriptor = null;
    updateFaceStatus('Preparing face verification…');
    faceRecognitionActive = true;
    if (faceState.retryBtn) faceState.retryBtn.disabled = true;
    if (faceState.cancelBtn) faceState.cancelBtn.style.display = 'inline-flex';

    openFaceModal();
    if (faceState.faceReminder) {
        faceState.faceReminder.style.display = 'block';
    }

    console.log('Models loading check...');
    const modelsReady = await ensureFaceModelsLoaded();
    if (!modelsReady) {
        console.log('Models not ready, aborting');
        faceRecognitionActive = false;
        return;
    }
    console.log('Models ready, proceeding...');

    console.log('Loading reference descriptor...');
    try {
        await loadReferenceDescriptor(photoUrl);
        console.log('Reference descriptor loaded successfully');
    } catch (err) {
        console.error('Failed to load reference descriptor:', err);
        updateFaceStatus('Unable to load reference photo for face verification.', 'error');
        if (faceState.retryBtn) faceState.retryBtn.disabled = false;
        faceRecognitionActive = false;
        return;
    }

    console.log('Starting camera stream...');
    try {
        await startCameraStream();
        console.log('Camera stream started successfully');
        updateFaceStatus('Camera ready. Align your face with the guide.');
        await runFaceMatchingLoop();
    } catch (err) {
        console.error('Face verification error:', err);
        updateFaceStatus(err.message || 'Face verification failed. Please try again.', 'error');
        if (faceState.retryBtn) faceState.retryBtn.disabled = false;
    } finally {
        faceRecognitionActive = false;
    }
}

async function loadReferenceDescriptor(photoUrl) {
    let imgEl = null;
    let sourceUsed = null;
    
    console.log('Loading reference photo, photoUrl:', photoUrl);
    
    async function tryFetchImage(url) {
        try {
            const img = await faceapi.fetchImage(url);
            
            // Validate image dimensions and format
            if (img.width === 0 || img.height === 0) {
                console.error('Invalid image dimensions:', img.width, 'x', img.height);
                return null;
            }
            
            // Check if image is too large (face-api.js can have issues with very large images)
            if (img.width > 2000 || img.height > 2000) {
                console.warn('Image is very large:', img.width, 'x', img.height, '- this may cause processing issues');
            }
            
            console.log('Image loaded successfully:', img.width, 'x', img.height);
            return img;
        } catch (e) {
            console.log('Failed to fetch image from:', url, e.message);
            return null;
        }
    }

    // Try direct file path first (new approach)
    if (photoUrl) {
        console.log('Trying direct file path:', photoUrl);
        imgEl = await tryFetchImage(photoUrl);
        if (imgEl) {
            sourceUsed = 'direct';
            console.log('Successfully loaded photo from file path');
        }
    }

    // Fallback: try API for backward compatibility
    if (!imgEl) {
        console.log('Trying API as fallback...');
        try {
            const resp = await fetch('api/get_user_photo.php');
            const j = await resp.json();
            console.log('API response:', j);
            if (j && j.success && j.dataUrl) {
                imgEl = await tryFetchImage(j.dataUrl);
                if (imgEl) {
                    sourceUsed = 'api';
                    console.log('Successfully loaded photo from API');
                }
            }
        } catch (e) {
            console.error('API fetch failed:', e);
        }
    }

    if (!imgEl) {
        console.error('No reference photo could be loaded');
        throw new Error('No reference photo found - please ensure your photo is uploaded in the system');
    }

    if (faceState.referencePreview) {
        faceState.referencePreview.src = sourceUsed === 'direct' ? photoUrl : (imgEl.src || '');
    }

    try {
        console.log('Processing reference image for face detection...');
        console.log('Image dimensions:', imgEl.width, 'x', imgEl.height);
        
        // Create a smaller version if image is too large
        let processImg = imgEl;
        if (imgEl.width > 1024 || imgEl.height > 1024) {
            console.log('Resizing large image for better processing...');
            processImg = await resizeImage(imgEl, 1024, 1024);
        }
        
        // Try face detection with different options
        let detection = await faceapi
            .detectSingleFace(processImg, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.3 }))
            .withFaceLandmarks()
            .withFaceDescriptor();
            
        // If first attempt fails, try with more lenient settings
        if (!detection) {
            console.log('First detection failed, trying with more lenient settings...');
            detection = await faceapi
                .detectSingleFace(processImg, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.1, inputSize: 512 }))
                .withFaceLandmarks()
                .withFaceDescriptor();
        }
            
        if (!detection) {
            throw new Error('Reference photo missing a detectable face.');
        }
        
        console.log('Reference face detection successful');
        referenceDescriptor = detection.descriptor;
    } catch (error) {
        console.error('Face detection error on reference image:', error);
        throw new Error('Failed to process reference photo: ' + error.message);
    }
}

async function startCameraStream() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('This device does not support camera access.');
    }
    if (faceVideoStream) {
        faceVideoStream.getTracks().forEach(track => track.stop());
        faceVideoStream = null;
    }
    
    try {
        faceVideoStream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'user', 
                width: { ideal: 640, min: 320 }, 
                height: { ideal: 480, min: 240 } 
            } 
        });
        
        if (faceState.video) {
            console.log('Setting up video element...');
            faceState.video.srcObject = faceVideoStream;
            faceState.video.style.transform = 'scaleX(-1)';
            faceState.video.style.transformOrigin = 'center center';
            faceState.video.autoplay = true;
            faceState.video.muted = true;
            faceState.video.playsInline = true;
            
            return new Promise((resolve, reject) => {
                faceState.video.onloadedmetadata = () => {
                    console.log('Video metadata loaded, attempting to play...');
                    faceState.video.play().then(() => {
                        console.log('Video is now playing');
                        // Set canvas dimensions after video is ready
                        setTimeout(() => {
                            if (faceState.canvas) {
                                const videoWidth = faceState.video.videoWidth || 640;
                                const videoHeight = faceState.video.videoHeight || 480;
                                faceState.canvas.width = videoWidth;
                                faceState.canvas.height = videoHeight;
                                console.log(`Camera ready: ${videoWidth}x${videoHeight}`);
                                
                                // Force video visibility
                                faceState.video.style.display = 'block';
                                faceState.video.style.visibility = 'visible';
                                faceState.video.style.opacity = '1';
                                
                                // Test canvas by drawing a green rectangle
                                const ctx = faceState.canvas.getContext('2d');
                                if (ctx) {
                                    ctx.strokeStyle = '#00ff41';
                                    ctx.lineWidth = 3;
                                    ctx.strokeRect(10, 10, 100, 50);
                                    ctx.fillStyle = '#00ff41';
                                    ctx.font = '16px Arial';
                                    ctx.fillText('Camera Active', 20, 35);
                                    console.log('Canvas test overlay drawn');
                                }
                                
                                // Log video element state for debugging
                                console.log('Video element state:', {
                                    readyState: faceState.video.readyState,
                                    videoWidth: faceState.video.videoWidth,
                                    videoHeight: faceState.video.videoHeight,
                                    paused: faceState.video.paused,
                                    currentTime: faceState.video.currentTime,
                                    srcObject: !!faceState.video.srcObject
                                });
                            }
                            resolve();
                        }, 200);
                    }).catch(error => {
                        console.error('Video play failed:', error);
                        reject(error);
                    });
                };
                
                faceState.video.onerror = (error) => {
                    console.error('Video element error:', error);
                    reject(error);
                };
                
                // Fallback timeout
                setTimeout(() => {
                    if (faceState.video.readyState === 0) {
                        console.error('Video failed to load within timeout');
                        reject(new Error('Video loading timeout'));
                    }
                }, 5000);
            });
        }
    } catch (error) {
        console.error('Camera access error:', error);
        throw new Error('Camera access denied. Please allow camera permissions and try again.');
    }
}

function stopFaceVerification() {
    if (faceVideoStream) {
        faceVideoStream.getTracks().forEach(track => track.stop());
        faceVideoStream = null;
    }
    closeFaceModal();
    faceRecognitionActive = false;
    if (faceState.faceReminder) {
        faceState.faceReminder.style.display = 'none';
    }
}

async function runFaceMatchingLoop() {
    if (!faceState.video || !referenceDescriptor) {
        throw new Error('Face verification resources unavailable.');
    }
    const options = new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.5, inputSize: 256 });
    const displaySize = { width: faceState.video.videoWidth, height: faceState.video.videoHeight };
    if (faceState.canvas) {
        faceapi.matchDimensions(faceState.canvas, displaySize);
        const ctx = faceState.canvas.getContext('2d');
        if (ctx) ctx.clearRect(0, 0, faceState.canvas.width, faceState.canvas.height);
    }

    const startTime = Date.now();
    const timeoutMs = 15000; // 15 seconds

    return new Promise((resolve, reject) => {
        const analyze = async () => {
            if (!faceRecognitionActive) return;

            const elapsed = Date.now() - startTime;
            if (elapsed > timeoutMs) {
                if (faceState.retryBtn) faceState.retryBtn.disabled = false;
                faceRecognitionActive = false;
                reject(new Error('Face not detected in time. Please retry.'));
                return;
            }

            let detection;
            try {
                detection = await faceapi.detectSingleFace(faceState.video, options).withFaceLandmarks().withFaceDescriptor();
            } catch (error) {
                console.error('Face detection error on live video:', error);
                updateFaceStatus('Face detection error. Please try again.', 'error');
                if (faceState.retryBtn) faceState.retryBtn.disabled = false;
                faceRecognitionActive = false;
                reject(error);
                return;
            }
            if (faceState.canvas) {
                const ctx = faceState.canvas.getContext('2d');
                if (ctx) ctx.clearRect(0, 0, faceState.canvas.width, faceState.canvas.height);
            }

            if (detection) {
                const resizedDetection = faceapi.resizeResults(detection, displaySize);
                if (faceState.canvas && landmarkDrawingEnabled) {
                    const ctx = faceState.canvas.getContext('2d');
                    if (ctx) {
                        // Draw face bounding box
                        faceapi.draw.drawDetections(faceState.canvas, resizedDetection);
                        
                        // Draw 68-point facial landmarks
                        faceapi.draw.drawFaceLandmarks(faceState.canvas, resizedDetection);
                        
                        // Custom styling for landmarks
                        ctx.strokeStyle = '#00ff41';
                        ctx.lineWidth = 2;
                        ctx.fillStyle = '#00ff41';
                        
                        // Draw landmark points
                        const landmarks = resizedDetection.landmarks;
                        const positions = landmarks.positions;
                        positions.forEach(point => {
                            ctx.beginPath();
                            ctx.arc(point.x, point.y, 2, 0, 2 * Math.PI);
                            ctx.fill();
                        });
                        
                        // Draw face outline
                        ctx.strokeStyle = '#00ff41';
                        ctx.lineWidth = 3;
                        ctx.strokeRect(
                            resizedDetection.detection.box.x,
                            resizedDetection.detection.box.y,
                            resizedDetection.detection.box.width,
                            resizedDetection.detection.box.height
                        );
                    }
                }
                
                // Compare with reference photo
                const distance = faceapi.euclideanDistance(referenceDescriptor, detection.descriptor);
                const matchPercentage = Math.max(0, (1 - distance) * 100).toFixed(1);
                
                if (distance <= faceVerificationThreshold) {
                    updateFaceStatus(`✓ Face verified! Match: ${matchPercentage}%`, 'success');
                    faceRecognitionActive = false;
                    if (faceState.retryBtn) faceState.retryBtn.disabled = true;
                    setTimeout(() => {
                        stopFaceVerification();
                        confirmFaceVerified();
                        resolve();
                    }, 1500);
                    return;
                } else {
                    updateFaceStatus(`Face detected. Match: ${matchPercentage}% (${faceVerificationThreshold.toFixed(2)} required)`, 'info');
                }
            } else {
                updateFaceStatus('👤 Position your face in the camera frame', 'info');
            }

            if (faceRecognitionActive) {
                requestAnimationFrame(analyze);
            }
        };

        analyze();
    });
}

function confirmFaceVerified() {
    fetch('verify_face.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ verified: true })
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            showStatus('✓ Identity verified!', 'success');
            setTimeout(() => { window.location.href = 'borrow-return.php'; }, 800);
        } else {
            showStatus('Face verification failed. Please try again.', 'error');
            resetRFIDInput();
        }
    })
    .catch(err => {
        console.error('Failed to confirm face verification:', err);
        showStatus('Server error confirming identity. Please try again.', 'error');
        resetRFIDInput();
    });
}

