/**
 * Maintenance Tracker JavaScript
 * Handles all interactive functionality for maintenance tracking
 */

let equipmentList = [];
let currentViewLogId = null;

// Load data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadEquipmentList();
    loadMaintenanceLogs();
    loadStatistics();
});

// ==================== EQUIPMENT MANAGEMENT ====================

/**
 * Load equipment list for dropdown
 */
async function loadEquipmentList() {
    try {
        const response = await fetch('api/maintenance_handler.php?action=get_equipment_list');
        const data = await response.json();
        
        if (data.success) {
            equipmentList = data.data;
            const select = document.getElementById('equipmentId');
            select.innerHTML = '<option value="">Select Equipment</option>';
            
            data.data.forEach(eq => {
                select.innerHTML += `<option value="${eq.id}" data-name="${eq.name}" data-image="${eq.image_path || ''}">${eq.name} (${eq.id})</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading equipment:', error);
        showAlert('Failed to load equipment list', 'error');
    }
}

/**
 * Update equipment preview when selection changes
 */
function updateEquipmentPreview() {
    const select = document.getElementById('equipmentId');
    const option = select.options[select.selectedIndex];
    const preview = document.getElementById('equipmentPreview');
    
    if (select.value) {
        const imagePath = option.dataset.image || '../uploads/placeholder.png';
        const name = option.dataset.name;
        preview.innerHTML = `
            <div class="equipment-preview">
                <img src="${imagePath}" alt="${name}" onerror="this.src='../uploads/placeholder.png'">
                <div>
                    <strong>${name}</strong><br>
                    <small>ID: ${select.value}</small>
                </div>
            </div>
        `;
    } else {
        preview.innerHTML = '';
    }
}

// ==================== MAINTENANCE LOGS ====================

/**
 * Load maintenance logs with filters
 */
async function loadMaintenanceLogs() {
    const status = document.getElementById('statusFilter').value;
    const type = document.getElementById('typeFilter').value;
    const search = document.getElementById('searchBox').value;
    
    try {
        const params = new URLSearchParams({
            action: 'get_all',
            status: status,
            type: type,
            search: search
        });
        
        const response = await fetch(`api/maintenance_handler.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            displayMaintenanceLogs(data.data);
        } else {
            showAlert('Failed to load maintenance logs', 'error');
        }
    } catch (error) {
        console.error('Error loading logs:', error);
        showAlert('Error loading maintenance logs', 'error');
    }
}

/**
 * Display maintenance logs in table
 */
function displayMaintenanceLogs(logs) {
    const tbody = document.getElementById('maintenanceTableBody');
    
    if (logs.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No maintenance logs found</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = logs.map(log => `
        <tr>
            <td>
                <strong>${escapeHtml(log.equipment_name)}</strong><br>
                <small style="color: #666;">ID: ${escapeHtml(log.equipment_id)}</small>
            </td>
            <td>
                <span class="type-badge">${escapeHtml(log.maintenance_type)}</span>
            </td>
            <td>
                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    ${escapeHtml(log.issue_description)}
                </div>
            </td>
            <td>
                <span class="severity-badge ${log.severity.toLowerCase()}">${escapeHtml(log.severity)}</span>
            </td>
            <td>
                <span class="status-badge ${log.status.toLowerCase().replace(' ', '-')}">${escapeHtml(log.status)}</span>
            </td>
            <td>${formatDate(log.reported_date)}</td>
            <td>${log.assigned_to ? escapeHtml(log.assigned_to) : '<em style="color: #999;">Unassigned</em>'}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon btn-view" onclick="viewMaintenanceLog(${log.id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon btn-edit" onclick="quickUpdateStatus(${log.id})" title="Update Status">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon btn-delete" onclick="deleteMaintenanceLog(${log.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// ==================== STATISTICS ====================

/**
 * Load and display statistics
 */
async function loadStatistics() {
    try {
        const response = await fetch('api/maintenance_handler.php?action=get_statistics');
        const data = await response.json();
        
        if (data.success) {
            const stats = data.data;
            document.getElementById('statPending').textContent = stats.by_status?.Pending || 0;
            document.getElementById('statInProgress').textContent = stats.by_status?.['In Progress'] || 0;
            document.getElementById('statCompleted').textContent = stats.by_status?.Completed || 0;
            document.getElementById('statTotal').textContent = stats.total || 0;
        }
    } catch (error) {
        console.error('Error loading statistics:', error);
    }
}

// ==================== MODAL MANAGEMENT ====================

/**
 * Open create modal
 */
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Add Maintenance Log';
    document.getElementById('maintenanceForm').reset();
    document.getElementById('logId').value = '';
    document.getElementById('equipmentPreview').innerHTML = '';
    document.getElementById('maintenanceModal').classList.add('active');
}

/**
 * Close create/edit modal
 */
function closeModal() {
    document.getElementById('maintenanceModal').classList.remove('active');
}

/**
 * Submit maintenance log (create)
 */
async function submitMaintenanceLog() {
    const form = document.getElementById('maintenanceForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('equipment_id', document.getElementById('equipmentId').value);
    formData.append('equipment_name', document.getElementById('equipmentId').selectedOptions[0].dataset.name);
    formData.append('maintenance_type', document.getElementById('maintenanceType').value);
    formData.append('issue_description', document.getElementById('issueDescription').value);
    formData.append('severity', document.getElementById('severity').value);
    formData.append('assigned_to', document.getElementById('assignedTo').value);
    formData.append('before_condition', document.getElementById('beforeCondition').value);
    
    try {
        const response = await fetch('api/maintenance_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Maintenance log created successfully', 'success');
            closeModal();
            loadMaintenanceLogs();
            loadStatistics();
        } else {
            showAlert(data.message || 'Failed to create maintenance log', 'error');
        }
    } catch (error) {
        console.error('Error creating log:', error);
        showAlert('Error creating maintenance log', 'error');
    }
}

// ==================== VIEW DETAILS ====================

/**
 * View maintenance log details
 */
async function viewMaintenanceLog(id) {
    currentViewLogId = id;
    
    try {
        const response = await fetch(`api/maintenance_handler.php?action=get_by_id&id=${id}`);
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to debug
        const text = await response.text();
        
        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response. Check console for details.');
        }
        
        if (data.success) {
            displayMaintenanceDetails(data.data);
            document.getElementById('viewModal').classList.add('active');
        } else {
            showAlert(data.message || 'Failed to load maintenance details', 'error');
        }
    } catch (error) {
        console.error('Error loading details:', error);
        showAlert('Error: ' + error.message, 'error');
    }
}

/**
 * Display maintenance details in view modal
 */
function displayMaintenanceDetails(log) {
    const modalBody = document.getElementById('viewModalBody');
    
    modalBody.innerHTML = `
        <div class="detail-row">
            <span class="detail-label">Equipment:</span>
            <span class="detail-value"><strong>${escapeHtml(log.equipment_name)}</strong> (${escapeHtml(log.equipment_id)})</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Maintenance Type:</span>
            <span class="detail-value"><span class="type-badge">${escapeHtml(log.maintenance_type)}</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Severity:</span>
            <span class="detail-value"><span class="severity-badge ${log.severity.toLowerCase()}">${escapeHtml(log.severity)}</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status:</span>
            <span class="detail-value"><span class="status-badge ${log.status.toLowerCase().replace(' ', '-')}">${escapeHtml(log.status)}</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Issue Description:</span>
            <span class="detail-value">${escapeHtml(log.issue_description)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Reported By:</span>
            <span class="detail-value">${escapeHtml(log.reported_by)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Reported Date:</span>
            <span class="detail-value">${formatDate(log.reported_date)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Assigned To:</span>
            <span class="detail-value">${log.assigned_to ? escapeHtml(log.assigned_to) : '<em style="color: #999;">Unassigned</em>'}</span>
        </div>
        ${log.started_date ? `
        <div class="detail-row">
            <span class="detail-label">Started Date:</span>
            <span class="detail-value">${formatDate(log.started_date)}</span>
        </div>
        ` : ''}
        ${log.completed_date ? `
        <div class="detail-row">
            <span class="detail-label">Completed Date:</span>
            <span class="detail-value">${formatDate(log.completed_date)}</span>
        </div>
        ` : ''}
        ${log.before_condition ? `
        <div class="detail-row">
            <span class="detail-label">Before Condition:</span>
            <span class="detail-value">${escapeHtml(log.before_condition)}</span>
        </div>
        ` : ''}
        ${log.after_condition ? `
        <div class="detail-row">
            <span class="detail-label">After Condition:</span>
            <span class="detail-value">${escapeHtml(log.after_condition)}</span>
        </div>
        ` : ''}
        ${log.resolution_notes ? `
        <div class="detail-row">
            <span class="detail-label">Resolution Notes:</span>
            <span class="detail-value">${escapeHtml(log.resolution_notes)}</span>
        </div>
        ` : ''}
        ${log.cost ? `
        <div class="detail-row">
            <span class="detail-label">Cost:</span>
            <span class="detail-value">₱${parseFloat(log.cost).toFixed(2)}</span>
        </div>
        ` : ''}
        ${log.parts_replaced ? `
        <div class="detail-row">
            <span class="detail-label">Parts Replaced:</span>
            <span class="detail-value">${escapeHtml(log.parts_replaced)}</span>
        </div>
        ` : ''}
        ${log.downtime_hours ? `
        <div class="detail-row">
            <span class="detail-label">Downtime:</span>
            <span class="detail-value">${parseFloat(log.downtime_hours).toFixed(1)} hours</span>
        </div>
        ` : ''}
        ${log.next_maintenance_date ? `
        <div class="detail-row">
            <span class="detail-label">Next Maintenance:</span>
            <span class="detail-value">${formatDate(log.next_maintenance_date)}</span>
        </div>
        ` : ''}
    `;
}

/**
 * Close view modal
 */
function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    currentViewLogId = null;
}

// ==================== UPDATE STATUS ====================

/**
 * Quick update status (opens update modal)
 */
function quickUpdateStatus(id) {
    currentViewLogId = id;
    openUpdateModal();
}

/**
 * Open update modal
 */
function openUpdateModal() {
    if (!currentViewLogId) {
        showAlert('No maintenance log selected', 'error');
        return;
    }
    
    document.getElementById('updateLogId').value = currentViewLogId;
    document.getElementById('updateForm').reset();
    document.getElementById('updateModal').classList.add('active');
    
    // Close view modal if open
    closeViewModal();
}

/**
 * Close update modal
 */
function closeUpdateModal() {
    document.getElementById('updateModal').classList.remove('active');
}

/**
 * Submit update
 */
async function submitUpdate() {
    const form = document.getElementById('updateForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('id', document.getElementById('updateLogId').value);
    formData.append('status', document.getElementById('updateStatus').value);
    formData.append('after_condition', document.getElementById('afterCondition').value);
    formData.append('resolution_notes', document.getElementById('resolutionNotes').value);
    formData.append('cost', document.getElementById('cost').value);
    formData.append('downtime_hours', document.getElementById('downtimeHours').value);
    formData.append('parts_replaced', document.getElementById('partsReplaced').value);
    formData.append('next_maintenance_date', document.getElementById('nextMaintenanceDate').value);
    
    try {
        const response = await fetch('api/maintenance_handler.php', {
            method: 'POST',
            body: formData
        });
        
        // Get response text first to debug
        const text = await response.text();
        
        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response. Check console for details.');
        }
        
        if (data.success) {
            showAlert('Maintenance log updated successfully', 'success');
            closeUpdateModal();
            // Reload page after short delay to show success message
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert(data.message || 'Failed to update maintenance log', 'error');
        }
    } catch (error) {
        console.error('Error updating log:', error);
        showAlert('Error: ' + error.message, 'error');
    }
}

// ==================== DELETE ====================

/**
 * Delete maintenance log
 */
async function deleteMaintenanceLog(id) {
    if (!confirm('Are you sure you want to delete this maintenance log? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    try {
        const response = await fetch('api/maintenance_handler.php', {
            method: 'POST',
            body: formData
        });
        
        // Get response text first to debug
        const text = await response.text();
        
        // Try to parse as JSON
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response. Check console for details.');
        }
        
        if (data.success) {
            showAlert('Maintenance log deleted successfully', 'success');
            // Reload page after short delay to show success message
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert(data.message || 'Failed to delete maintenance log', 'error');
        }
    } catch (error) {
        console.error('Error deleting log:', error);
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
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
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
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Insert at top of main content
    const mainContent = document.querySelector('.main-content');
    mainContent.insertBefore(alertDiv, mainContent.firstChild);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        alertDiv.style.transition = 'opacity 0.3s';
        setTimeout(() => alertDiv.remove(), 300);
    }, 5000);
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
