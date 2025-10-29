/**
 * Return Verification JavaScript
 * Handles return verification operations
 */

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadReturns();
    initializeEventListeners();
});

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', debounce(function() {
        loadReturns();
    }, 300));
    
    // Status filter
    document.getElementById('statusFilter').addEventListener('change', function() {
        loadReturns();
    });
}

/**
 * Load all returns with filters
 */
async function loadReturns() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    
    const params = new URLSearchParams({
        action: 'get_returns',
        search: search,
        status: status
    });
    
    try {
        const response = await fetch(`api/return_verification_handler.php?${params}`);
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
            displayReturns(data.data);
        } else {
            showAlert(data.message || 'Failed to load returns', 'error');
        }
    } catch (error) {
        console.error('Error loading returns:', error);
        showAlert('Error loading returns', 'error');
    }
}

/**
 * Display returns in table
 */
function displayReturns(returns) {
    const tbody = document.getElementById('returnsTableBody');
    
    if (returns.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-cell">
                    <i class="fas fa-inbox"></i><br>
                    No returns found
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = returns.map(ret => {
        const isOverdue = ret.display_status === 'Overdue';
        const isPending = ['Pending', 'Analyzing'].includes(ret.return_verification_status);
        const statusClass = isPending ? 'pending' : 'returned';
        const statusText = isPending ? 'Pending Verification' : 'Verified';
        
        return `
            <tr class="${isOverdue ? 'overdue-row' : ''}">
                <td><strong>#${escapeHtml(ret.id)}</strong></td>
                <td>
                    <div class="equipment-cell">
                        ${ret.image_path ? `<img src="../${escapeHtml(ret.image_path)}" alt="${escapeHtml(ret.equipment_name)}" class="equipment-thumb">` : ''}
                        <span>${escapeHtml(ret.equipment_name)}</span>
                    </div>
                </td>
                <td><code>${escapeHtml(ret.student_id)}</code></td>
                <td>${formatDate(ret.transaction_date)}</td>
                <td>
                    ${formatDate(ret.expected_return_date)}
                    ${isOverdue ? '<span class="overdue-badge">OVERDUE</span>' : ''}
                </td>
                <td>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                    ${ret.condition_after ? `<br><span class="condition-badge ${ret.condition_after.toLowerCase().replace(' ', '-')}">${escapeHtml(ret.condition_after)}</span>` : ''}
                </td>
                <td class="actions-cell">
                    ${isPending ? `
                        <button class="btn-icon btn-verify" onclick="openVerifyModal(${ret.id})" title="Verify Return">
                            <i class="fas fa-check-circle"></i>
                        </button>
                    ` : `
                        <button class="btn-icon btn-view" onclick="viewTransaction(${ret.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    `}
                </td>
            </tr>
        `;
    }).join('');
}

// ==================== VERIFY RETURN ====================

/**
 * Open verify modal
 */
async function openVerifyModal(transactionId) {
    const modal = document.getElementById('verifyModal');
    const modalBody = document.getElementById('verifyModalBody');
    
    modalBody.innerHTML = '<div class="loading-cell"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    modal.classList.add('active');
    
    try {
        const response = await fetch(`api/return_verification_handler.php?action=get_transaction&id=${transactionId}`);
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response');
        }
        
        if (data.success) {
            displayVerificationForm(data.data);
        } else {
            showAlert(data.message || 'Failed to load transaction', 'error');
            closeVerifyModal();
        }
    } catch (error) {
        console.error('Error loading transaction:', error);
        showAlert('Error: ' + error.message, 'error');
        closeVerifyModal();
    }
}

/**
 * Display verification form
 */
function displayVerificationForm(transaction) {
    const modalBody = document.getElementById('verifyModalBody');
    
    const transactionDate = new Date(transaction.transaction_date);
    const expectedReturn = new Date(transaction.expected_return_date);
    const now = new Date();
    const daysLate = Math.floor((now - expectedReturn) / (1000 * 60 * 60 * 24));
    const isOverdue = daysLate > 0;
    
    modalBody.innerHTML = `
        <form id="verifyForm">
            <input type="hidden" id="transactionId" value="${transaction.id}">
            
            <!-- Transaction Info -->
            <div class="info-section">
                <h3><i class="fas fa-info-circle"></i> Transaction Details</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Transaction ID:</label>
                        <span><strong>#${escapeHtml(transaction.id)}</strong></span>
                    </div>
                    <div class="info-item">
                        <label>Equipment:</label>
                        <span>${escapeHtml(transaction.equipment_name)}</span>
                    </div>
                    <div class="info-item">
                        <label>User:</label>
                        <span><code>${escapeHtml(transaction.student_id)}</code></span>
                    </div>
                    <div class="info-item">
                        <label>Borrowed:</label>
                        <span>${formatDate(transaction.transaction_date)}</span>
                    </div>
                    <div class="info-item">
                        <label>Expected Return:</label>
                        <span>${formatDate(transaction.expected_return_date)}</span>
                    </div>
                    <div class="info-item">
                        <label>Status:</label>
                        <span>
                            ${isOverdue ? `<span class="overdue-badge">OVERDUE (${daysLate} days)</span>` : '<span class="status-badge pending">On Time</span>'}
                        </span>
                    </div>
                </div>
            </div>
            
            ${isOverdue ? `
            <div class="alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Warning:</strong> This item is ${daysLate} day(s) overdue. Consider penalty if applicable.
            </div>
            ` : ''}
            
            <!-- Equipment Image -->
            ${transaction.image_path ? `
            <div class="image-section">
                <h3><i class="fas fa-image"></i> Equipment Reference Image</h3>
                <img src="../${escapeHtml(transaction.image_path)}" alt="${escapeHtml(transaction.equipment_name)}" class="equipment-image">
                <p class="image-hint">Compare the returned item with this reference image</p>
            </div>
            ` : ''}
            
            <!-- Verification Form -->
            <div class="form-section">
                <h3><i class="fas fa-clipboard-check"></i> Verification Assessment</h3>
                
                <div class="form-group">
                    <label for="returnCondition">Return Condition <span class="required">*</span></label>
                    <select id="returnCondition" name="return_condition" required onchange="handleConditionChange(this.value)">
                        <option value="">-- Select Condition --</option>
                        <option value="Good">✓ Good - No damage, fully functional</option>
                        <option value="Damaged">✗ Damaged - Requires repair or replacement</option>
                    </select>
                </div>
                
                <div id="damageAlert" class="alert-danger" style="display: none;">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Damaged Item Detected!</strong> This will:
                    <ul>
                        <li>Mark item as damaged in inventory</li>
                        <li>Reduce available quantity</li>
                        <li>May require penalty assessment</li>
                    </ul>
                </div>
                
                <div class="form-group">
                    <label for="verificationNotes">Verification Notes</label>
                    <textarea id="verificationNotes" name="notes" rows="4" placeholder="Add any observations, damage details, or comments..."></textarea>
                    <small class="form-hint">Document the condition and any issues found</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeVerifyModal()">Cancel</button>
                <button type="button" class="btn-penalty" onclick="openPenaltyConfirmation(${transaction.id})">
                    <i class="fas fa-gavel"></i> Add to Penalty
                </button>
                <button type="submit" class="btn-submit btn-verify-submit">
                    <i class="fas fa-check-circle"></i> Verify Return
                </button>
            </div>
        </form>
    `;
    
    // Add form submit handler
    document.getElementById('verifyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitVerification();
    });
}

/**
 * Handle condition change
 */
function handleConditionChange(condition) {
    const damageAlert = document.getElementById('damageAlert');
    if (condition === 'Damaged') {
        damageAlert.style.display = 'block';
    } else {
        damageAlert.style.display = 'none';
    }
}

/**
 * Submit verification
 */
async function submitVerification() {
    const form = document.getElementById('verifyForm');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'verify_return');
    formData.append('id', document.getElementById('transactionId').value);
    formData.append('return_condition', document.getElementById('returnCondition').value);
    formData.append('notes', document.getElementById('verificationNotes').value);
    
    try {
        const response = await fetch('api/return_verification_handler.php', {
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
            const condition = data.condition;
            const message = condition === 'Damaged' 
                ? 'Return verified - Item marked as damaged' 
                : 'Return verified successfully';
            
            showAlert(message, condition === 'Damaged' ? 'warning' : 'success');
            closeVerifyModal();
            
            // Reload page to update statistics
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message || 'Failed to verify return', 'error');
        }
    } catch (error) {
        console.error('Error verifying return:', error);
        showAlert('Error: ' + error.message, 'error');
    }
}

/**
 * Close verify modal
 */
function closeVerifyModal() {
    document.getElementById('verifyModal').classList.remove('active');
}

/**
 * View transaction details (for already verified returns)
 */
function viewTransaction(transactionId) {
    // Redirect to all transactions page with this transaction highlighted
    window.location.href = `admin-all-transaction.php?highlight=${transactionId}`;
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
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'}"></i>
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

// ==================== PENALTY CONFIRMATION ====================

/**
 * Open penalty confirmation modal
 */
async function openPenaltyConfirmation(transactionId) {
    try {
        const response = await fetch(`api/return_verification_handler.php?action=get_transaction&id=${transactionId}`);
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response');
        }
        
        if (data.success) {
            showPenaltyConfirmation(data.data);
        } else {
            showAlert(data.message || 'Failed to load transaction', 'error');
        }
    } catch (error) {
        console.error('Error loading transaction:', error);
        showAlert('Error: ' + error.message, 'error');
    }
}

/**
 * Show penalty confirmation modal
 */
function showPenaltyConfirmation(transaction) {
    // Get similarity score and severity from AI comparison if available
    let similarityScore = 'N/A';
    let severityLevel = 'MODERATE';
    
    // Check if there's AI comparison data
    if (transaction.ai_similarity_score !== null && transaction.ai_similarity_score !== undefined) {
        similarityScore = parseFloat(transaction.ai_similarity_score).toFixed(2) + '%';
    }
    
    if (transaction.ai_severity_level) {
        severityLevel = transaction.ai_severity_level.toUpperCase();
    }
    
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'penaltyConfirmModal';
    modal.innerHTML = `
        <div class="modal-content modal-medium">
            <div class="modal-header penalty-header">
                <h2><i class="fas fa-gavel"></i> Create Penalty Record</h2>
                <button class="modal-close" onclick="closePenaltyConfirmation()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>You are about to create a penalty record for this transaction. Please review the details below.</strong>
                </div>
                
                <div class="penalty-details">
                    <div class="detail-row">
                        <span class="detail-label">Equipment:</span>
                        <span class="detail-value">${escapeHtml(transaction.equipment_name)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Transaction ID:</span>
                        <span class="detail-value">#${escapeHtml(transaction.id)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Student ID:</span>
                        <span class="detail-value">${escapeHtml(transaction.student_id)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Similarity Score:</span>
                        <span class="detail-value">${similarityScore}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Severity Level:</span>
                        <span class="detail-value severity-${severityLevel.toLowerCase()}">${severityLevel}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closePenaltyConfirmation()">Cancel</button>
                <button type="button" class="btn-proceed" onclick="proceedToPenaltyManagement(${transaction.id})">
                    <i class="fas fa-arrow-right"></i> Proceed to Penalty Management
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

/**
 * Close penalty confirmation modal
 */
function closePenaltyConfirmation() {
    const modal = document.getElementById('penaltyConfirmModal');
    if (modal) {
        modal.remove();
    }
}

/**
 * Proceed to penalty management with transaction data
 */
function proceedToPenaltyManagement(transactionId) {
    window.location.href = `admin-penalty-management.php?action=create_from_transaction&transaction_id=${transactionId}`;
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
