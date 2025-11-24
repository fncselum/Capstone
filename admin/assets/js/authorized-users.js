/**
 * Authorized Users Management JavaScript
 * Handles CRUD operations for authorized users
 */

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    initializeEventListeners();
});

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', debounce(function() {
        loadUsers();
    }, 300));
    
    // Status filter
    document.getElementById('statusFilter').addEventListener('change', function() {
        loadUsers();
    });
    
    // Add user form submission
    document.getElementById('addUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitAddUser();
    });
    
    // Edit user form submission
    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitEditUser();
    });

    // Photo preview: Add modal
    const addPhotoInput = document.getElementById('photo');
    if (addPhotoInput) {
        addPhotoInput.addEventListener('change', function() {
            const file = this.files && this.files[0];
            const wrapper = document.getElementById('addPhotoPreviewWrapper');
            const img = document.getElementById('addPhotoPreview');
            if (file && img && wrapper) {
                const url = URL.createObjectURL(file);
                img.src = url;
                wrapper.style.display = 'block';
            } else if (wrapper) {
                wrapper.style.display = 'none';
            }
        });
    }

    // Photo preview: Edit modal (new upload)
    const editPhotoInput = document.getElementById('editPhoto');
    if (editPhotoInput) {
        editPhotoInput.addEventListener('change', function() {
            const file = this.files && this.files[0];
            const fetchedWrapper = document.getElementById('editPhotoFetchedWrapper');
            const fetchedImg = document.getElementById('editPhotoFetched');
            if (file && fetchedWrapper && fetchedImg) {
                const url = URL.createObjectURL(file);
                fetchedImg.src = url;
                fetchedImg.style.display = 'block';
                fetchedWrapper.style.display = 'flex';
            }
        });
    }
}

/**
 * Load all users with filters
 */
async function loadUsers() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    
    const params = new URLSearchParams({
        action: 'get_all',
        search: search,
        status: status
    });
    
    try {
        const response = await fetch(`api/authorized_users_handler.php?${params}`);
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            showAlert('Server error. Check console for details.', 'error');
            return;
        }
        
        if (data.success) {
            displayUsers(data.data);
        } else {
            showAlert(data.message || 'Failed to load users', 'error');
        }
    } catch (error) {
        console.error('Error loading users:', error);
        showAlert('Error loading users', 'error');
    }
}

/**
 * Display users in table
 */
function displayUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    
    if (users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="empty-cell">
                    <i class="fas fa-users"></i><br>
                    No users found
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>${escapeHtml(user.id)}</td>
            <td><code>${escapeHtml(user.student_id)}</code></td>
            <td>${user.email ? escapeHtml(user.email) : '<span style="color:#9ca3af">—</span>'}</td>
            <td><span class="status-badge ${user.status.toLowerCase()}">${escapeHtml(user.status)}</span></td>
            <td>${escapeHtml(user.user_type || 'Student')}</td>
            <td>
                <span class="penalty-badge ${user.penalty_points > 0 ? 'has-penalty' : ''}">
                    ${user.penalty_points}
                </span>
            </td>
            <td>${formatDate(user.registered_at)}</td>
            <td class="actions-cell">
                <button class="btn-icon btn-edit" onclick="openEditModal(${user.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon btn-toggle" onclick="toggleUserStatus(${user.id})" title="Toggle Status">
                    <i class="fas fa-${user.status === 'Active' ? 'toggle-on' : 'toggle-off'}"></i>
                </button>
                <button class="btn-icon btn-delete" onclick="deleteUser(${user.id})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// ==================== ADD USER ====================

/**
 * Open add user modal
 */
function openAddModal() {
    document.getElementById('addUserForm').reset();
    const wrapper = document.getElementById('addPhotoPreviewWrapper');
    if (wrapper) wrapper.style.display = 'none';
    document.getElementById('addModal').classList.add('active');
}

/**
 * Close add user modal
 */
function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

/**
 * Submit add user form
 */
async function submitAddUser() {
    const form = document.getElementById('addUserForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'create');
    
    try {
        const response = await fetch('api/authorized_users_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response');
        }
        
        if (data.success) {
            showAlert('User added successfully', 'success');
            closeAddModal();
            loadUsers();
            // Reload page to update statistics
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showAlert(data.message || 'Failed to add user', 'error');
        }
    } catch (error) {
        console.error('Error adding user:', error);
        showAlert('Error: ' + error.message, 'error');
    }
}

// ==================== EDIT USER ====================

/**
 * Open edit user modal
 */
async function openEditModal(userId) {
    try {
        const response = await fetch(`api/authorized_users_handler.php?action=get_all`);
        const data = await response.json();
        
        if (data.success) {
            const user = data.data.find(u => u.id == userId);
            if (user) {
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editStudentId').value = user.student_id; // Same as rfid_tag
                document.getElementById('editStatus').value = user.status;
                const editRoleEl = document.getElementById('editUserType');
                if (editRoleEl) editRoleEl.value = (user.user_type === 'Teacher' ? 'Teacher' : 'Student');
                const editEmailEl = document.getElementById('editEmail');
                if (editEmailEl) editEmailEl.value = user.email || '';
                document.getElementById('editPenaltyPoints').value = user.penalty_points;
                // Clear new upload input
                const editPhotoInput = document.getElementById('editPhoto');
                if (editPhotoInput) editPhotoInput.value = '';
                // Fetch stored photo (BLOB or path) and show centered below uploader
                try {
                    const resp = await fetch(`api/get_user_photo.php?id=${encodeURIComponent(user.id)}`);
                    const pdata = await resp.json();
                    const fetchedWrapper = document.getElementById('editPhotoFetchedWrapper');
                    const fetchedImg = document.getElementById('editPhotoFetched');
                    if (pdata && pdata.success && pdata.dataUrl && fetchedWrapper && fetchedImg) {
                        fetchedImg.src = pdata.dataUrl;
                        fetchedImg.style.display = 'block';
                        fetchedWrapper.style.display = 'flex';
                    } else if (fetchedWrapper && fetchedImg) {
                        fetchedImg.removeAttribute('src');
                        fetchedImg.style.display = 'none';
                        fetchedWrapper.style.display = 'flex';
                    }
                } catch (e) {
                    // ignore fetch errors to avoid blocking modal
                }
                
                document.getElementById('editModal').classList.add('active');
            }
        }
    } catch (error) {
        console.error('Error loading user:', error);
        showAlert('Error loading user details', 'error');
    }
}

/**
 * Close edit user modal
 */
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

/**
 * Submit edit user form
 */
async function submitEditUser() {
    const form = document.getElementById('editUserForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    formData.append('action', 'update');
    
    try {
        const response = await fetch('api/authorized_users_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response');
        }
        
        if (data.success) {
            showAlert('User updated successfully', 'success');
            closeEditModal();
            loadUsers();
            // Reload page to update statistics
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showAlert(data.message || 'Failed to update user', 'error');
        }
    } catch (error) {
        console.error('Error updating user:', error);
        showAlert('Error: ' + error.message, 'error');
    }
}

// ==================== DELETE USER ====================

/**
 * Delete user
 */
async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', userId);
    
    try {
        const response = await fetch('api/authorized_users_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response');
        }
        
        if (data.success) {
            showAlert('User deleted successfully', 'success');
            loadUsers();
            // Reload page to update statistics
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showAlert(data.message || 'Failed to delete user', 'error');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showAlert('Error: ' + error.message, 'error');
    }
}

// ==================== TOGGLE STATUS ====================

/**
 * Toggle user status
 */
async function toggleUserStatus(userId) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('id', userId);
    
    try {
        const response = await fetch('api/authorized_users_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response');
        }
        
        if (data.success) {
            showAlert(`Status changed to ${data.new_status}`, 'success');
            loadUsers();
            // Reload page to update statistics
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showAlert(data.message || 'Failed to toggle status', 'error');
        }
    } catch (error) {
        console.error('Error toggling status:', error);
        showAlert('Error: ' + error.message, 'error');
    }
}

// ==================== UTILITY FUNCTIONS ====================

/**
 * Format date to readable string
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric'
    };
    
    return date.toLocaleDateString('en-US', options);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Show alert message
 */
function showAlert(message, type = 'success') {
    // Ensure toast container exists
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = `
            position: fixed; top: 16px; right: 16px; z-index: 99999;
            display: flex; flex-direction: column; gap: 10px; align-items: flex-end;`;
        document.body.appendChild(container);
    }

    const bg = type === 'success' ? '#ecfdf5' : '#fef2f2';
    const border = type === 'success' ? '#10b981' : '#ef4444';
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    const color = type === 'success' ? '#065f46' : '#7f1d1d';

    const toast = document.createElement('div');
    toast.setAttribute('role', 'status');
    toast.style.cssText = `
        min-width: 280px; max-width: 420px; display: flex; align-items: center; gap: 10px;
        background: ${bg}; border: 1px solid ${border}; color: ${color};
        border-radius: 10px; padding: 12px 14px; box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        transform: translateY(-12px); opacity: 0; transition: all .25s ease;`;

    toast.innerHTML = `
        <i class="fas fa-${icon}" style="min-width:18px;"></i>
        <div style="flex:1; font-weight:600;">${message}</div>
        <button aria-label="Close" style="background:transparent; border:none; color:${color}; font-size:16px; cursor:pointer;">&times;</button>
    `;

    const closeBtn = toast.querySelector('button');
    closeBtn.addEventListener('click', () => dismiss());

    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });

    let timeout = setTimeout(() => dismiss(), 4000);

    function dismiss() {
        clearTimeout(timeout);
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-8px)';
        setTimeout(() => toast.remove(), 200);
    }
}

/**
 * Debounce function for search
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
