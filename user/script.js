// ===== Equipment Kiosk - Enhanced RFID Scanner Script =====

// Auto-focus on RFID input
document.addEventListener('DOMContentLoaded', function() {
    const rfidInput = document.getElementById('rfidInput');
    const statusMessage = document.getElementById('statusMessage');
    
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
                showStatus('Scanning...', 'scanning');
            }
        });
    }
});

// Process RFID scan
function processRFID(rfid) {
    const statusMessage = document.getElementById('statusMessage');
    const rfidInput = document.getElementById('rfidInput');
    
    // Show processing status
    showStatus('Processing RFID...', 'scanning');
    
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

// Show status message
function showStatus(message, type) {
    const statusMessage = document.getElementById('statusMessage');
    if (statusMessage) {
        statusMessage.textContent = message;
        statusMessage.className = 'status-message ' + type;
        statusMessage.style.display = 'block';
        
        // Auto-hide after 3 seconds for non-scanning messages
        if (type !== 'scanning') {
            setTimeout(() => {
                if (statusMessage.classList.contains(type)) {
                    statusMessage.style.display = 'none';
                }
            }, 3000);
        }
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
