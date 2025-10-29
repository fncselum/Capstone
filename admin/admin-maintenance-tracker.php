<?php
session_start();
require_once '../includes/db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

// Get admin username for logging
$admin_username = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Tracker - Equipment System</title>
    <link rel="stylesheet" href="assets/css/admin-base.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/maintenance-tracker.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <h1 class="page-title"><i class="fas fa-wrench"></i> Maintenance Tracker</h1>
            </header>

            <!-- Statistics Cards -->
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="statPending">0</h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon progress">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="statInProgress">0</h3>
                        <p>In Progress</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="statCompleted">0</h3>
                        <p>Completed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="statTotal">0</h3>
                        <p>Total Records</p>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="filters">
                    <select class="filter-select" id="statusFilter" onchange="loadMaintenanceLogs()">
                        <option value="all">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                    <select class="filter-select" id="typeFilter" onchange="loadMaintenanceLogs()">
                        <option value="all">All Types</option>
                        <option value="Repair">Repair</option>
                        <option value="Preventive">Preventive</option>
                        <option value="Inspection">Inspection</option>
                        <option value="Cleaning">Cleaning</option>
                        <option value="Calibration">Calibration</option>
                        <option value="Replacement">Replacement</option>
                    </select>
                    <input type="text" class="search-box" id="searchBox" placeholder="Search equipment or issue..." onkeyup="loadMaintenanceLogs()">
                </div>
                <button class="btn-add" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> Add Maintenance Log
                </button>
            </div>

            <!-- Maintenance Table -->
            <div style="overflow-x: auto;">
                <table class="maintenance-table">
                    <thead>
                        <tr>
                            <th>Equipment</th>
                            <th>Type</th>
                            <th>Issue</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th>Assigned To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="maintenanceTableBody">
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Loading maintenance logs...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal" id="maintenanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Maintenance Log</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="maintenanceForm">
                    <input type="hidden" id="logId">
                    
                    <div class="form-group">
                        <label>Equipment *</label>
                        <select id="equipmentId" required onchange="updateEquipmentPreview()">
                            <option value="">Select Equipment</option>
                        </select>
                        <div id="equipmentPreview"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Maintenance Type *</label>
                            <select id="maintenanceType" required>
                                <option value="Repair">Repair</option>
                                <option value="Preventive">Preventive</option>
                                <option value="Inspection">Inspection</option>
                                <option value="Cleaning">Cleaning</option>
                                <option value="Calibration">Calibration</option>
                                <option value="Replacement">Replacement</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Severity *</label>
                            <select id="severity" required>
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Issue Description *</label>
                        <textarea id="issueDescription" required placeholder="Describe the issue or maintenance needed..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Assigned To</label>
                            <input type="text" id="assignedTo" placeholder="Technician name">
                        </div>
                        <div class="form-group">
                            <label>Before Condition</label>
                            <select id="beforeCondition">
                                <option value="">Select condition</option>
                                <option value="Excellent">Excellent</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Out of Service">Out of Service</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button class="btn-submit" onclick="submitMaintenanceLog()">Save Log</button>
            </div>
        </div>
    </div>

    <!-- View/Update Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Maintenance Details</h2>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeViewModal()">Close</button>
                <button class="btn-submit" onclick="openUpdateModal()">Update Status</button>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal" id="updateModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Maintenance</h2>
                <button class="modal-close" onclick="closeUpdateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="updateForm">
                    <input type="hidden" id="updateLogId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Status *</label>
                            <select id="updateStatus" required>
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>After Condition</label>
                            <select id="afterCondition">
                                <option value="">Select condition</option>
                                <option value="Excellent">Excellent</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Out of Service">Out of Service</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Resolution Notes</label>
                        <textarea id="resolutionNotes" placeholder="Describe what was done..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Cost (₱)</label>
                            <input type="number" id="cost" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>Downtime (hours)</label>
                            <input type="number" id="downtimeHours" step="0.1" min="0" placeholder="0.0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Parts Replaced</label>
                        <input type="text" id="partsReplaced" placeholder="List parts replaced">
                    </div>

                    <div class="form-group">
                        <label>Next Maintenance Date</label>
                        <input type="date" id="nextMaintenanceDate">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeUpdateModal()">Cancel</button>
                <button class="btn-submit" onclick="submitUpdate()">Update</button>
            </div>
        </div>
    </div>

    <script src="assets/js/maintenance-tracker.js"></script>
</body>
</html>
