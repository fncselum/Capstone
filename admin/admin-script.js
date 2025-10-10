// Admin Dashboard JavaScript

// Navigation functionality
document.addEventListener('DOMContentLoaded', function() {
    // Check authentication first
    checkAuthentication();
    
    // Initialize navigation
    initializeNavigation();
    
    // Initialize sidebar toggle for mobile
    initializeSidebarToggle();
    
    // Initialize search functionality
    initializeSearch();
    
    // Initialize filters
    initializeFilters();
    
    // Load dashboard data
    loadDashboardData();
    
    // Update admin info with logged in user
    updateAdminInfo();
});

// Explicit toggle function for sidebar (used by onclick in HTML)
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
}

function checkAuthentication() {
    const isLoggedIn = sessionStorage.getItem('adminLoggedIn') || localStorage.getItem('adminLoggedIn');
    if (isLoggedIn !== 'true') {
        // Not logged in, redirect to login page
        window.location.href = 'login.html';
        return;
    }
}

function updateAdminInfo() {
    const adminName = sessionStorage.getItem('adminUsername') || localStorage.getItem('adminUsername') || 'Admin User';
    const adminNameElement = document.querySelector('.admin-name');
    if (adminNameElement) {
        adminNameElement.textContent = adminName;
    }
}

function initializeNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    const contentSections = document.querySelectorAll('.content-section');
    const pageTitle = document.querySelector('.page-title');
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all nav items
            navItems.forEach(nav => nav.classList.remove('active'));
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // Hide all content sections
            contentSections.forEach(section => section.classList.remove('active'));
            
            // Show corresponding section
            const sectionId = this.getAttribute('data-section');
            const targetSection = document.getElementById(sectionId);
            if (targetSection) {
                targetSection.classList.add('active');
                
                // Update page title
                const navText = this.querySelector('span').textContent;
                pageTitle.textContent = navText;
                
                // Load section-specific data
                loadSectionData(sectionId);
            }
        });
    });
}

function initializeSidebarToggle() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }
}

function initializeSearch() {
    const searchInput = document.querySelector('.search-bar input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            performSearch(searchTerm);
        });
    }
}

function initializeFilters() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filterType = this.textContent.toLowerCase();
            filterTransactions(filterType);
        });
    });
}

function performSearch(searchTerm) {
    // Implement search functionality based on current section
    const activeSection = document.querySelector('.content-section.active');
    if (activeSection) {
        const sectionId = activeSection.id;
        
        switch(sectionId) {
            case 'inventory':
                searchEquipment(searchTerm);
                break;
            case 'transactions':
                searchTransactions(searchTerm);
                break;
            case 'borrowings':
                searchBorrowings(searchTerm);
                break;
            case 'returns':
                searchReturns(searchTerm);
                break;
        }
    }
}

function searchEquipment(term) {
    // Implement equipment search
    console.log('Searching equipment for:', term);
}

function searchTransactions(term) {
    // Implement transaction search
    console.log('Searching transactions for:', term);
}

function searchBorrowings(term) {
    // Implement borrowing search
    console.log('Searching borrowings for:', term);
}

function searchReturns(term) {
    // Implement return search
    console.log('Searching returns for:', term);
}

function filterTransactions(filterType) {
    // Implement transaction filtering
    console.log('Filtering transactions by:', filterType);
}

function loadSectionData(sectionId) {
    switch(sectionId) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'inventory':
            loadInventoryData();
            break;
        case 'categories':
            loadCategoriesData();
            break;
        case 'transactions':
            loadTransactionsData();
            break;
        case 'borrowings':
            loadBorrowingsData();
            break;
        case 'returns':
            loadReturnsData();
            break;
        case 'reports':
            loadReportsData();
            break;
    }
}

function loadDashboardData() {
    // Load dashboard statistics and recent activity
    console.log('Loading dashboard data...');
    updateDashboardStats();
    updateRecentActivity();
}

function loadInventoryData() {
    // Load equipment inventory
    console.log('Loading inventory data...');
    populateEquipmentGrid();
}

function loadCategoriesData() {
    // Load categories data
    console.log('Loading categories data...');
}

function loadTransactionsData() {
    // Load all transactions
    console.log('Loading transactions data...');
}

function loadBorrowingsData() {
    // Load borrowing history
    console.log('Loading borrowings data...');
}

function loadReturnsData() {
    // Load return history
    console.log('Loading returns data...');
}

function loadReportsData() {
    // Load reports data
    console.log('Loading reports data...');
}

function updateDashboardStats() {
    // Update dashboard statistics
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        // Animate number counting
        animateNumber(stat);
    });
}

function updateRecentActivity() {
    // Update recent activity feed
    console.log('Updating recent activity...');
}

function populateEquipmentGrid() {
    const equipmentGrid = document.querySelector('.equipment-grid');
    if (equipmentGrid) {
        // Get equipment from localStorage
        let equipment = JSON.parse(localStorage.getItem('equipment') || '[]');
        
        // Add sample data if no equipment exists
        if (equipment.length === 0) {
            equipment = [
                { id: 'LAP-001', name: 'Laptop', category: 'Electronics', status: 'Borrowed', user: 'John Doe', description: 'Dell Latitude laptop', location: 'Room 201', condition: 'Good' },
                { id: 'PROJ-005', name: 'Projector', category: 'Electronics', status: 'Available', user: null, description: 'Epson projector', location: 'Storage A', condition: 'Good' },
                { id: 'MIC-010', name: 'Microphone', category: 'Electronics', status: 'Maintenance', user: null, description: 'Wireless microphone', location: 'Room 101', condition: 'Fair' }
            ];
            localStorage.setItem('equipment', JSON.stringify(equipment));
        }
        
        equipmentGrid.innerHTML = '';
        equipment.forEach(item => {
            const card = createEquipmentCard(item);
            equipmentGrid.appendChild(card);
        });
    }
}

function createEquipmentCard(equipment) {
    const card = document.createElement('div');
    card.className = 'equipment-card';
    card.innerHTML = `
        <div class="equipment-header">
            <h3>${equipment.name}</h3>
            <span class="equipment-id">#${equipment.id}</span>
        </div>
        <div class="equipment-details">
            <p><strong>Category:</strong> ${equipment.category}</p>
            <p><strong>Status:</strong> <span class="status-badge ${equipment.status.toLowerCase().replace(' ', '-')}">${equipment.status}</span></p>
            ${equipment.user ? `<p><strong>Borrowed by:</strong> ${equipment.user}</p>` : ''}
            ${equipment.description ? `<p><strong>Description:</strong> ${equipment.description}</p>` : ''}
            ${equipment.location ? `<p><strong>Location:</strong> ${equipment.location}</p>` : ''}
            ${equipment.condition ? `<p><strong>Condition:</strong> ${equipment.condition}</p>` : ''}
        </div>
        <div class="equipment-actions">
            <button class="action-btn" onclick="viewEquipment('${equipment.id}')">
                <i class="fas fa-eye"></i>
            </button>
            <button class="action-btn" onclick="editEquipment('${equipment.id}')">
                <i class="fas fa-edit"></i>
            </button>
            <button class="action-btn" onclick="deleteEquipment('${equipment.id}')">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    return card;
}

function animateNumber(element) {
    const target = parseInt(element.textContent.replace(/,/g, ''));
    const duration = 1000;
    const step = target / (duration / 16);
    let current = 0;
    
    const timer = setInterval(() => {
        current += step;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current).toLocaleString();
    }, 16);
}

// Quick action functions
function showAddEquipment() {
    const modalContent = `
        <form id="addEquipmentForm" class="equipment-form">
            <div class="form-group">
                <label for="equipmentName">Equipment Name *</label>
                <input type="text" id="equipmentName" name="equipmentName" required>
            </div>
            
            <div class="form-group">
                <label for="equipmentCategory">Category *</label>
                <select id="equipmentCategory" name="equipmentCategory" required>
                    <option value="">Select Category</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Tools">Tools</option>
                    <option value="Sports">Sports</option>
                    <option value="Books">Books</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="equipmentId">Equipment ID *</label>
                    <input type="text" id="equipmentId" name="equipmentId" required>
                </div>
                
                <div class="form-group">
                    <label for="equipmentStatus">Status *</label>
                    <select id="equipmentStatus" name="equipmentStatus" required>
                        <option value="Available">Available</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Out of Service">Out of Service</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="equipmentDescription">Description</label>
                <textarea id="equipmentDescription" name="equipmentDescription" rows="3" placeholder="Enter equipment description..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="equipmentLocation">Location</label>
                    <input type="text" id="equipmentLocation" name="equipmentLocation" placeholder="e.g., Room 101, Storage A">
                </div>
                
                <div class="form-group">
                    <label for="equipmentCondition">Condition</label>
                    <select id="equipmentCondition" name="equipmentCondition">
                        <option value="New">New</option>
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Poor">Poor</option>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary">Add Equipment</button>
            </div>
        </form>
    `;
    
    showModal('Add New Equipment', modalContent);
    
    // Add form submission handler
    setTimeout(() => {
        const form = document.getElementById('addEquipmentForm');
        if (form) {
            form.addEventListener('submit', handleAddEquipment);
        }
    }, 100);
}

function handleAddEquipment(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const equipmentData = {
        name: formData.get('equipmentName'),
        category: formData.get('equipmentCategory'),
        id: formData.get('equipmentId'),
        status: formData.get('equipmentStatus'),
        description: formData.get('equipmentDescription'),
        location: formData.get('equipmentLocation'),
        condition: formData.get('equipmentCondition'),
        dateAdded: new Date().toISOString()
    };
    
    // Validate required fields
    if (!equipmentData.name || !equipmentData.category || !equipmentData.id || !equipmentData.status) {
        showNotification('Please fill in all required fields.', 'error');
        return;
    }
    
    // Add equipment to the system
    addEquipmentToSystem(equipmentData);
    
    // Close modal
    closeModal();
    
    // Show success message
    showNotification('Equipment added successfully!', 'success');
    
    // Refresh equipment grid if on inventory page
    const activeSection = document.querySelector('.content-section.active');
    if (activeSection && activeSection.id === 'inventory') {
        populateEquipmentGrid();
    }
}

function addEquipmentToSystem(equipmentData) {
    // In a real application, this would save to a database
    // For now, we'll store in localStorage and update the display
    
    // Get existing equipment from localStorage or initialize empty array
    let equipment = JSON.parse(localStorage.getItem('equipment') || '[]');
    
    // Add new equipment
    equipment.push(equipmentData);
    
    // Save back to localStorage
    localStorage.setItem('equipment', JSON.stringify(equipment));
    
    console.log('Equipment added:', equipmentData);
    console.log('Total equipment in system:', equipment.length);
}

function showAddCategory() {
    alert('Add Category modal will be implemented here');
    // Implement modal for adding category
}

function generateReport() {
    alert('Report generation will be implemented here');
    // Implement report generation
}

function viewOverdue() {
    alert('Overdue equipment view will be implemented here');
    // Implement overdue equipment view
}

// Equipment management functions
function viewEquipment(id) {
    alert(`Viewing equipment: ${id}`);
    // Implement equipment detail view
}

function editEquipment(id) {
    alert(`Editing equipment: ${id}`);
    // Implement equipment editing
}

function deleteEquipment(id) {
    if (confirm(`Are you sure you want to delete equipment ${id}?`)) {
        alert(`Equipment ${id} deleted`);
        // Implement equipment deletion
    }
}

// Report generation functions
function generateUsageReport() {
    alert('Generating Equipment Usage Report...');
    // Implement usage report generation
}

function generateUserReport() {
    alert('Generating User Activity Report...');
    // Implement user report generation
}

function generateOverdueReport() {
    alert('Generating Overdue Equipment Report...');
    // Implement overdue report generation
}

function generateMonthlyReport() {
    alert('Generating Monthly Summary Report...');
    // Implement monthly report generation
}

// Logout function
function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        // Clear all session and local storage data
        sessionStorage.removeItem('adminLoggedIn');
        sessionStorage.removeItem('adminUsername');
        localStorage.removeItem('adminLoggedIn');
        localStorage.removeItem('adminUsername');
        
        // Redirect to main kiosk page
        window.location.href = 'index.html';
    }
}

// Utility functions
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function showModal(title, content) {
    // Create modal
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                ${content}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add modal styles
    const style = document.createElement('style');
    style.textContent = `
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
    `;
    document.head.appendChild(style);
}

function closeModal() {
    const modal = document.querySelector('.modal');
    if (modal) {
        modal.remove();
    }
} 