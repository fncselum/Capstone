// Inventory Page Logic

const INVENTORY_STORAGE_KEY = 'equipment';

document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on a page that needs localStorage (legacy inventory page)
    const grid = document.getElementById('equipmentGrid');
    const searchInput = document.getElementById('searchInput');
    
    // If we have a grid but no equipment cards, we might need localStorage data
    if (grid && grid.children.length === 0) {
        ensureSeedData();
        populateCategoryFilter();
        renderEquipment();
    }
    
    wireControls();
});

function ensureSeedData() {
    const existing = localStorage.getItem(INVENTORY_STORAGE_KEY);
    if (!existing) {
        const seed = [
            { id: 'LAP-001', name: 'Laptop', category: 'Electronics', status: 'Borrowed', user: 'John Doe', location: 'Room 201', condition: 'Good', description: 'Dell Latitude 5520' },
            { id: 'PROJ-005', name: 'Projector', category: 'Electronics', status: 'Available', user: null, location: 'Storage A', condition: 'Good', description: 'Epson PowerLite 1781W' },
            { id: 'MIC-010', name: 'Microphone', category: 'Electronics', status: 'Maintenance', user: null, location: 'Room 101', condition: 'Fair', description: 'Shure SM58' },
            { id: 'TAB-003', name: 'Tablet', category: 'Electronics', status: 'Available', user: null, location: 'Library', condition: 'Good', description: 'iPad Air 4th Gen' },
            { id: 'CAM-021', name: 'Camera', category: 'Electronics', status: 'Available', user: null, location: 'Media Lab', condition: 'Good', description: 'Canon EOS Rebel T7' }
        ];
        localStorage.setItem(INVENTORY_STORAGE_KEY, JSON.stringify(seed));
    }
}

function getAllEquipment() {
    return JSON.parse(localStorage.getItem(INVENTORY_STORAGE_KEY) || '[]');
}

function wireControls() {
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const conditionFilter = document.getElementById('conditionFilter');
    
    // Add null checks to prevent errors
    if (searchInput) {
        searchInput.addEventListener('input', renderEquipment);
    }
    if (categoryFilter) {
        categoryFilter.addEventListener('change', renderEquipment);
    }
    if (conditionFilter) {
        conditionFilter.addEventListener('change', renderEquipment);
    }
    
    // Handle category pills
    const categoryPills = document.querySelectorAll('.category-pill');
    categoryPills.forEach(pill => {
        pill.addEventListener('click', function() {
            // Remove active class from all pills
            categoryPills.forEach(p => p.classList.remove('active'));
            // Add active class to clicked pill
            this.classList.add('active');
            // Update hidden select and trigger render
            if (categoryFilter) {
                categoryFilter.value = this.dataset.category;
                renderEquipment();
            }
        });
    });
}

function populateCategoryFilter() {
    const categoryFilter = document.getElementById('categoryFilter');
    if (!categoryFilter) return; // Exit if element doesn't exist
    
    const equipment = getAllEquipment();
    const categories = Array.from(new Set(equipment.map(e => e.category))).sort();
    categories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat;
        opt.textContent = cat;
        categoryFilter.appendChild(opt);
    });
}

function renderEquipment() {
    const grid = document.getElementById('equipmentGrid');
    const empty = document.getElementById('emptyState');
    
    // Add null checks for all elements
    if (!grid) return;
    
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const conditionFilter = document.getElementById('conditionFilter');
    
    const q = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const cat = categoryFilter ? categoryFilter.value : 'all';
    const condition = conditionFilter ? conditionFilter.value : 'all';

    // Get all equipment cards that are already rendered by PHP
    const allCards = grid.querySelectorAll('.equipment-card');
    let visibleCount = 0;

    allCards.forEach(card => {
        const name = card.dataset.name || '';
        const category = card.dataset.category || '';
        const cardCondition = card.dataset.condition || '';
        
        // Check if card matches filters
        const matchesQuery = !q || name.includes(q);
        const matchesCat = cat === 'all' || category === cat;
        const matchesCondition = condition === 'all' || cardCondition.toLowerCase() === condition.toLowerCase();
        
        if (matchesQuery && matchesCat && matchesCondition) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Show/hide empty state
    if (empty) {
        empty.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

function createCard(item) {
    const card = document.createElement('div');
    card.className = 'equipment-card';
    const statusClass = (item.status || '').toLowerCase().replace(/\s+/g, '-');
    card.innerHTML = `
        <div class="equipment-header">
            <h3>${escapeHtml(item.name)}</h3>
            <span class="equipment-id">#${escapeHtml(item.id)}</span>
        </div>
        <div class="equipment-meta">
            <p><strong>Category:</strong> ${escapeHtml(item.category || '—')}</p>
            <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${escapeHtml(item.status || 'Unknown')}</span></p>
            ${item.user ? `<p><strong>Borrowed by:</strong> ${escapeHtml(item.user)}</p>` : ''}
            ${item.location ? `<p><strong>Location:</strong> ${escapeHtml(item.location)}</p>` : ''}
            ${item.condition ? `<p><strong>Condition:</strong> ${escapeHtml(item.condition)}</p>` : ''}
            ${item.description ? `<p><strong>Description:</strong> ${escapeHtml(item.description)}</p>` : ''}
        </div>
        <div class="card-actions">
            <button class="btn btn-secondary" onclick="viewItem('${encodeURIComponent(item.id)}')"><i class="fas fa-eye"></i> View</button>
            <button class="btn btn-primary" onclick="borrowItem('${encodeURIComponent(item.id)}')"><i class="fas fa-arrow-up"></i> Borrow</button>
        </div>
    `;
    return card;
}

function viewItem(encodedId) {
    const id = decodeURIComponent(encodedId);
    alert(`View details for ${id}`);
}

function borrowItem(encodedId) {
    const id = decodeURIComponent(encodedId);
    // Navigate to borrow page and preselect if possible via query string
    window.location.href = `borrow.php?id=${encodeURIComponent(id)}`;
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"]+/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]));
}

function logout() {
    // Clear any stored data
    localStorage.clear();
    // Redirect to login page
    window.location.href = 'login.php';
}



