<?php
/**
 * Maintenance Tracker API Handler
 * Handles CRUD operations for maintenance logs
 */

// Prevent any HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../../includes/db_connection.php';

// Set JSON header immediately
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_all':
            getAllMaintenanceLogs($conn);
            break;
            
        case 'get_by_id':
            getMaintenanceById($conn, $_GET['id'] ?? 0);
            break;
            
        case 'create':
            createMaintenanceLog($conn);
            break;
            
        case 'update':
            updateMaintenanceLog($conn);
            break;
            
        case 'delete':
            deleteMaintenanceLog($conn, $_POST['id'] ?? 0);
            break;
            
        case 'update_status':
            updateMaintenanceStatus($conn);
            break;
            
        case 'get_equipment_list':
            getEquipmentList($conn);
            break;
            
        case 'get_statistics':
            getMaintenanceStatistics($conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getAllMaintenanceLogs($conn) {
    $status_filter = $_GET['status'] ?? 'all';
    $type_filter = $_GET['type'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT m.*, e.image_path, e.category_id 
            FROM maintenance_logs m
            LEFT JOIN equipment e ON m.equipment_id = e.rfid_tag
            WHERE 1=1";
    
    if ($status_filter !== 'all') {
        $sql .= " AND m.status = '" . $conn->real_escape_string($status_filter) . "'";
    }
    
    if ($type_filter !== 'all') {
        $sql .= " AND m.maintenance_type = '" . $conn->real_escape_string($type_filter) . "'";
    }
    
    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $sql .= " AND (m.equipment_name LIKE '%$search%' OR m.issue_description LIKE '%$search%' OR m.equipment_id LIKE '%$search%')";
    }
    
    $sql .= " ORDER BY m.reported_date DESC";
    
    $result = $conn->query($sql);
    $logs = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $logs]);
}

function getMaintenanceById($conn, $id) {
    $id = (int)$id;
    $sql = "SELECT m.*, 
                   e.image_path, 
                   e.category_id,
                   e.quantity AS equipment_quantity,
                   i.available_quantity,
                   COALESCE(i.maintenance_quantity, 0) AS total_maintenance_reserved,
                   i.quantity AS inventory_quantity,
                   i.borrowed_quantity,
                   i.damaged_quantity,
                   i.minimum_stock_level,
                   GREATEST(
                       COALESCE(i.quantity, e.quantity, 0)
                       - COALESCE(i.borrowed_quantity, 0)
                       - COALESCE(i.damaged_quantity, 0)
                       - COALESCE(i.maintenance_quantity, 0),
                       0
                   ) AS available_for_maintenance
            FROM maintenance_logs m
            LEFT JOIN equipment e ON m.equipment_id = e.rfid_tag
            LEFT JOIN inventory i ON m.equipment_id = i.equipment_id
            WHERE m.id = $id";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Maintenance log not found']);
    }
}

function createMaintenanceLog($conn) {
    $equipment_id = $conn->real_escape_string($_POST['equipment_id'] ?? '');
    $equipment_name = $conn->real_escape_string($_POST['equipment_name'] ?? '');
    $maintenance_type = $conn->real_escape_string($_POST['maintenance_type'] ?? 'Repair');
    $issue_description = $conn->real_escape_string($_POST['issue_description'] ?? '');
    $severity = $conn->real_escape_string($_POST['severity'] ?? 'Medium');
    $maintenance_quantity = max(1, (int)($_POST['maintenance_quantity'] ?? 1));
    $reported_by = $_SESSION['admin_username'] ?? 'Admin';
    $assigned_to = $conn->real_escape_string($_POST['assigned_to'] ?? '');
    $before_condition = $conn->real_escape_string($_POST['before_condition'] ?? '');

    if (empty($equipment_id) || empty($equipment_name) || empty($issue_description)) {
        throw new Exception('Equipment, equipment name, and issue description are required.');
    }

    $conn->begin_transaction();

    try {
        $inventoryRow = fetchInventoryRowForUpdate($conn, $equipment_id);
        if (!$inventoryRow) {
            throw new Exception('Inventory record not found for selected equipment.');
        }

        $quantity = (int)($inventoryRow['quantity'] ?? 0);
        $borrowed = (int)($inventoryRow['borrowed_quantity'] ?? 0);
        $damaged = (int)($inventoryRow['damaged_quantity'] ?? 0);
        $currentMaintenance = (int)($inventoryRow['maintenance_quantity'] ?? 0);
        $minStock = (int)($inventoryRow['minimum_stock_level'] ?? 1);

        $availableBefore = max($quantity - $borrowed - $damaged - $currentMaintenance, 0);
        if ($availableBefore < $maintenance_quantity) {
            throw new Exception('Only ' . $availableBefore . ' units are available to reserve for maintenance.');
        }

        $newMaintenanceTotal = $currentMaintenance + $maintenance_quantity;
        [$newAvailable, $newStatus] = calculateAvailabilityData($quantity, $borrowed, $damaged, $newMaintenanceTotal, $minStock);

        updateInventoryMaintenance($conn, $equipment_id, $newMaintenanceTotal, $newAvailable, $newStatus, null, $before_condition ?: null);

        $sql = "INSERT INTO maintenance_logs (
                    equipment_id, equipment_name, maintenance_type, issue_description,
                    severity, maintenance_quantity, reported_by, assigned_to, before_condition, status
                ) VALUES (
                    '$equipment_id', '$equipment_name', '$maintenance_type', '$issue_description',
                    '$severity', $maintenance_quantity, '$reported_by', " .
                    ($assigned_to ? "'$assigned_to'" : "NULL") . ", " .
                    ($before_condition ? "'$before_condition'" : "NULL") . ", 'Pending'
                )";

        if (!$conn->query($sql)) {
            throw new Exception('Failed to create maintenance log: ' . $conn->error);
        }

        $log_id = $conn->insert_id;

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Maintenance log created successfully',
            'log_id' => $log_id
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function updateMaintenanceLog($conn) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('Invalid maintenance log ID.');
    }

    $statusParam = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : null;
    $assigned_to = isset($_POST['assigned_to']) ? $conn->real_escape_string($_POST['assigned_to']) : null;
    $resolution_notes = isset($_POST['resolution_notes']) ? $conn->real_escape_string($_POST['resolution_notes']) : null;
    $cost = $_POST['cost'] ?? null;
    $parts_replaced = isset($_POST['parts_replaced']) ? $conn->real_escape_string($_POST['parts_replaced']) : null;
    $downtime_hours = $_POST['downtime_hours'] ?? null;
    $after_condition = isset($_POST['after_condition']) ? $conn->real_escape_string($_POST['after_condition']) : null;
    $next_maintenance_date = $_POST['next_maintenance_date'] ?? null;
    $maintenanceQtyRequest = isset($_POST['maintenance_quantity']) ? max(1, (int)$_POST['maintenance_quantity']) : null;
    $before_condition = isset($_POST['before_condition']) ? $conn->real_escape_string($_POST['before_condition']) : null;

    $conn->begin_transaction();

    try {
        $log = fetchMaintenanceLogForUpdate($conn, $id);
        if (!$log) {
            throw new Exception('Maintenance log not found.');
        }

        $equipment_id = $log['equipment_id'];
        $oldStatus = $log['status'];
        $oldQty = (int)$log['maintenance_quantity'];
        $newStatus = $statusParam !== null && $statusParam !== '' ? $statusParam : $oldStatus;
        $newMaintenanceQty = $maintenanceQtyRequest !== null ? $maintenanceQtyRequest : $oldQty;

        $delta = calculateMaintenanceDelta($oldStatus, $newStatus, $oldQty, $newMaintenanceQty);

        $itemConditionUpdate = null;
        if ($after_condition && strcasecmp($newStatus, 'Completed') === 0) {
            $itemConditionUpdate = $after_condition;
        }
        if ($before_condition && strcasecmp($oldStatus, 'Pending') === 0) {
            // allow updating recorded condition prior to maintenance start
            $log['before_condition'] = $before_condition;
        }

        $inventoryAdjusted = false;
        if ($delta !== 0 || $itemConditionUpdate !== null || ($before_condition && $maintenanceQtyRequest === null && strcasecmp($oldStatus, 'Pending') === 0)) {
            $inventoryRow = fetchInventoryRowForUpdate($conn, $equipment_id);
            if (!$inventoryRow) {
                throw new Exception('Inventory record not found for selected equipment.');
            }

            $quantity = (int)($inventoryRow['quantity'] ?? 0);
            $borrowed = (int)($inventoryRow['borrowed_quantity'] ?? 0);
            $damaged = (int)($inventoryRow['damaged_quantity'] ?? 0);
            $currentMaintenanceTotal = (int)($inventoryRow['maintenance_quantity'] ?? 0);
            $minStock = (int)($inventoryRow['minimum_stock_level'] ?? 1);

            if ($delta > 0) {
                $availableBefore = max($quantity - $borrowed - $damaged - $currentMaintenanceTotal, 0);
                if ($availableBefore < $delta) {
                    throw new Exception('Only ' . $availableBefore . ' units are available to reserve for maintenance.');
                }
            }

            $newMaintenanceTotal = max(0, $currentMaintenanceTotal + $delta);
            [$newAvailable, $newAvailabilityStatus] = calculateAvailabilityData($quantity, $borrowed, $damaged, $newMaintenanceTotal, $minStock);

            updateInventoryMaintenance(
                $conn,
                $equipment_id,
                $newMaintenanceTotal,
                $newAvailable,
                $newAvailabilityStatus,
                $itemConditionUpdate,
                $before_condition
            );
            $inventoryAdjusted = true;
        }

        $updates = [];

        if ($statusParam !== null && $newStatus !== $oldStatus) {
            $updates[] = "status = '$newStatus'";
            if (strcasecmp($newStatus, 'In Progress') === 0 && !isset($_POST['started_date'])) {
                $updates[] = "started_date = NOW()";
            }
            if (strcasecmp($newStatus, 'Completed') === 0 && !isset($_POST['completed_date'])) {
                $updates[] = "completed_date = NOW()";
            }
        }

        if ($assigned_to !== null) {
            $updates[] = $assigned_to === '' ? "assigned_to = NULL" : "assigned_to = '$assigned_to'";
        }

        if ($resolution_notes !== null) {
            $updates[] = $resolution_notes === '' ? "resolution_notes = NULL" : "resolution_notes = '$resolution_notes'";
        }

        if ($cost !== null) {
            $updates[] = $cost === '' ? "cost = NULL" : "cost = " . floatval($cost);
        }

        if ($parts_replaced !== null) {
            $updates[] = $parts_replaced === '' ? "parts_replaced = NULL" : "parts_replaced = '$parts_replaced'";
        }

        if ($downtime_hours !== null) {
            $updates[] = $downtime_hours === '' ? "downtime_hours = NULL" : "downtime_hours = " . floatval($downtime_hours);
        }

        if ($after_condition !== null) {
            $updates[] = $after_condition === '' ? "after_condition = NULL" : "after_condition = '$after_condition'";
        }

        if ($before_condition !== null) {
            $updates[] = $before_condition === '' ? "before_condition = NULL" : "before_condition = '$before_condition'";
        }

        if ($next_maintenance_date !== null) {
            $updates[] = $next_maintenance_date === '' ? "next_maintenance_date = NULL" : "next_maintenance_date = '$next_maintenance_date'";
        }

        if ($maintenanceQtyRequest !== null && $newMaintenanceQty !== $oldQty) {
            $updates[] = "maintenance_quantity = $newMaintenanceQty";
        }

        if (!empty($updates)) {
            $sql = "UPDATE maintenance_logs SET " . implode(', ', $updates) . " WHERE id = $id";
            if (!$conn->query($sql)) {
                throw new Exception('Failed to update maintenance log: ' . $conn->error);
            }
        } elseif (!$inventoryAdjusted) {
            throw new Exception('No fields to update');
        }

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Maintenance log updated successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function deleteMaintenanceLog($conn, $id) {
    $id = (int)$id;
    if ($id <= 0) {
        throw new Exception('Invalid maintenance log ID.');
    }

    $conn->begin_transaction();

    try {
        $log = fetchMaintenanceLogForUpdate($conn, $id);
        if (!$log) {
            throw new Exception('Maintenance log not found.');
        }

        $equipment_id = $log['equipment_id'];
        $oldStatus = $log['status'];
        $oldQty = (int)$log['maintenance_quantity'];

        if (statusRequiresReservation($oldStatus) && $oldQty > 0) {
            $inventoryRow = fetchInventoryRowForUpdate($conn, $equipment_id);
            if ($inventoryRow) {
                $quantity = (int)($inventoryRow['quantity'] ?? 0);
                $borrowed = (int)($inventoryRow['borrowed_quantity'] ?? 0);
                $damaged = (int)($inventoryRow['damaged_quantity'] ?? 0);
                $currentMaintenanceTotal = (int)($inventoryRow['maintenance_quantity'] ?? 0);
                $minStock = (int)($inventoryRow['minimum_stock_level'] ?? 1);

                $newMaintenanceTotal = max(0, $currentMaintenanceTotal - $oldQty);
                [$newAvailable, $newAvailabilityStatus] = calculateAvailabilityData($quantity, $borrowed, $damaged, $newMaintenanceTotal, $minStock);

                updateInventoryMaintenance(
                    $conn,
                    $equipment_id,
                    $newMaintenanceTotal,
                    $newAvailable,
                    $newAvailabilityStatus
                );
            }
        }

        if (!$conn->query("DELETE FROM maintenance_logs WHERE id = $id")) {
            throw new Exception('Failed to delete maintenance log: ' . $conn->error);
        }

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Maintenance log deleted successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function updateMaintenanceStatus($conn) {
    // Reuse core update logic for consistent inventory adjustment
    updateMaintenanceLog($conn);
}

function getEquipmentList($conn) {
    $sql = "SELECT 
                e.rfid_tag AS id,
                e.name,
                e.image_path,
                e.size_category,
                COALESCE(i.quantity, e.quantity, 0) AS quantity,
                COALESCE(i.available_quantity, GREATEST(e.quantity - COALESCE(i.borrowed_quantity, 0) - COALESCE(i.damaged_quantity, 0), 0), 0) AS available_quantity,
                COALESCE(i.borrowed_quantity, 0) AS borrowed_quantity,
                COALESCE(i.damaged_quantity, 0) AS damaged_quantity,
                COALESCE(i.maintenance_quantity, 0) AS maintenance_quantity,
                COALESCE(i.availability_status, 'Available') AS availability_status,
                GREATEST(
                    COALESCE(i.quantity, e.quantity, 0)
                    - COALESCE(i.borrowed_quantity, 0)
                    - COALESCE(i.damaged_quantity, 0)
                    - COALESCE(i.maintenance_quantity, 0),
                    0
                ) AS available_for_maintenance
            FROM equipment e
            LEFT JOIN inventory i ON e.rfid_tag = i.equipment_id
            ORDER BY e.name";
    
    $result = $conn->query($sql);
    $equipment = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $equipment[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $equipment]);
}

/**
 * Helper: fetch inventory row with row-level lock (creates if missing)
 */
function fetchInventoryRowForUpdate($conn, $equipment_id) {
    $equipment_id = $conn->real_escape_string($equipment_id);
    $sql = "SELECT * FROM inventory WHERE equipment_id = '$equipment_id' FOR UPDATE";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    // Attempt to bootstrap inventory record from equipment table if missing
    $equipmentResult = $conn->query("SELECT quantity FROM equipment WHERE rfid_tag = '$equipment_id' LIMIT 1");
    if ($equipmentResult && $equipmentResult->num_rows > 0) {
        $equipmentRow = $equipmentResult->fetch_assoc();
        $quantity = (int)($equipmentRow['quantity'] ?? 0);
        $conn->query("INSERT INTO inventory (equipment_id, quantity, available_quantity, borrowed_quantity, damaged_quantity, maintenance_quantity, availability_status, last_updated, created_at) VALUES ('{$equipment_id}', {$quantity}, {$quantity}, 0, 0, 0, 'Available', NOW(), NOW())");
        $equipmentResult->free();

        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }

    return null;
}

/**
 * Helper: compute availability after maintenance reservation updates
 */
function calculateAvailabilityData($quantity, $borrowed, $damaged, $maintenance, $minStock) {
    $quantity = max(0, (int)$quantity);
    $borrowed = max(0, (int)$borrowed);
    $damaged = max(0, (int)$damaged);
    $maintenance = max(0, (int)$maintenance);
    $minStock = max(1, (int)$minStock);

    $available = max($quantity - $borrowed - $damaged - $maintenance, 0);

    if ($available <= 0) {
        $status = 'Not Available';
    } elseif ($available <= $minStock) {
        $status = 'Low Stock';
    } elseif ($maintenance > 0) {
        $status = 'Partially Available';
    } else {
        $status = 'Available';
    }

    return [$available, $status];
}

/**
 * Helper: persist maintenance reservation changes to inventory
 */
function updateInventoryMaintenance($conn, $equipment_id, $maintenanceQty, $availableQty, $status, $itemCondition = null, $beforeCondition = null) {
    $equipment_id = $conn->real_escape_string($equipment_id);
    $maintenanceQty = max(0, (int)$maintenanceQty);
    $availableQty = max(0, (int)$availableQty);
    $status = $conn->real_escape_string($status);

    $setParts = [
        "maintenance_quantity = $maintenanceQty",
        "available_quantity = $availableQty",
        "availability_status = '$status'",
        "last_updated = NOW()"
    ];

    if ($itemCondition !== null && $itemCondition !== '') {
        $setParts[] = "item_condition = '" . $conn->real_escape_string($itemCondition) . "'";
    } elseif ($beforeCondition !== null && $beforeCondition !== '') {
        $setParts[] = "item_condition = '" . $conn->real_escape_string($beforeCondition) . "'";
    }

    $sql = "UPDATE inventory SET " . implode(', ', $setParts) . " WHERE equipment_id = '$equipment_id'";

    if (!$conn->query($sql)) {
        throw new Exception('Failed to update inventory reservation: ' . $conn->error);
    }
}

/**
 * Helper: fetch maintenance log with lock
 */
function fetchMaintenanceLogForUpdate($conn, $id) {
    $id = (int)$id;
    $sql = "SELECT * FROM maintenance_logs WHERE id = $id FOR UPDATE";
    $result = $conn->query($sql);

    return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * Helper: determine maintenance reservation delta based on status transitions
 */
function calculateMaintenanceDelta($oldStatus, $newStatus, $oldQty, $newQty) {
    $oldStatus = trim((string)$oldStatus);
    $newStatus = trim((string)$newStatus);
    $oldQty = max(0, (int)$oldQty);
    $newQty = max(0, (int)$newQty);

    $oldRequires = statusRequiresReservation($oldStatus);
    $newRequires = statusRequiresReservation($newStatus);

    if ($oldRequires && $newRequires) {
        return $newQty - $oldQty;
    }

    if ($oldRequires && !$newRequires) {
        return -$oldQty;
    }

    if (!$oldRequires && $newRequires) {
        return $newQty;
    }

    return 0;
}

/**
 * Helper: statuses that reserve inventory when active
 */
function statusRequiresReservation($status) {
    $status = strtolower(trim((string)$status));
    return in_array($status, ['pending', 'in progress'], true);
}

function getMaintenanceStatistics($conn) {
    $stats = [];
    
    // Total maintenance logs
    $result = $conn->query("SELECT COUNT(*) as total FROM maintenance_logs");
    $stats['total'] = $result->fetch_assoc()['total'];
    
    // By status
    $result = $conn->query("SELECT status, COUNT(*) as count FROM maintenance_logs GROUP BY status");
    $stats['by_status'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_status'][$row['status']] = $row['count'];
    }
    
    // By type
    $result = $conn->query("SELECT maintenance_type, COUNT(*) as count FROM maintenance_logs GROUP BY maintenance_type");
    $stats['by_type'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_type'][$row['maintenance_type']] = $row['count'];
    }
    
    // Average downtime
    $result = $conn->query("SELECT AVG(downtime_hours) as avg_downtime FROM maintenance_logs WHERE downtime_hours IS NOT NULL");
    $stats['avg_downtime'] = round($result->fetch_assoc()['avg_downtime'] ?? 0, 2);
    
    // Total cost
    $result = $conn->query("SELECT SUM(cost) as total_cost FROM maintenance_logs WHERE cost IS NOT NULL");
    $stats['total_cost'] = $result->fetch_assoc()['total_cost'] ?? 0;
    
    echo json_encode(['success' => true, 'data' => $stats]);
}
?>
