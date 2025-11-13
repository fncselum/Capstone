// ===== Equipment Kiosk - Enhanced RFID Scanner Script =====

// Scanner state references
let scannerIndicator = null;
let scannerStatusLabel = null;
let scannerInstruction = null;
let scannerResetTimer = null;
let audioContext = null;

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
    .then(data => {
        if (data.success) {
            showStatus('✓ RFID Verified!', 'success');
            
            // Check if user is admin
            if (data.is_admin) {
                // Redirect to admin dashboard
                setTimeout(() => {
                    window.location.href = '../admin/dashboard.php';
                }, 1000);
            } else {
                // Redirect to borrow-return selection page
                setTimeout(() => {
                    window.location.href = 'borrow-return.php';
                }, 1000);
            }
        } else {
            showStatus('✗ ' + (data.message || 'Invalid RFID. Please try again.'), 'error');
            rfidInput.value = '';
            rfidInput.focus();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showStatus('✗ Connection error. Please try again.', 'error');
        rfidInput.value = '';
        rfidInput.focus();
    });
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
