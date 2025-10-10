// Equipment Kiosk JavaScript

function startKiosk() {
    window.location.href = 'rfid-scan.php';
}

// Keep the old functions for backward compatibility if needed
function handleBorrow() {
    // Check if user has RFID in session
    window.location.href = 'borrow.php';
}

function handleReturn() {
    // Check if user has RFID in session
    window.location.href = 'return.php';
}

function handleAdmin() {
    window.location.href = 'login.html';
}

// Prevent text selection on buttons for better touch experience
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.action-button, .admin-link');
    
    buttons.forEach(button => {
        button.addEventListener('selectstart', function(e) {
            e.preventDefault();
        });
    });
    
    // Add touch feedback for mobile devices
    buttons.forEach(button => {
        button.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        
        button.addEventListener('touchend', function() {
            this.style.transform = '';
        });
    });
}); 