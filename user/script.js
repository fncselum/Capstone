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
