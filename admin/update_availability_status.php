<?php
/**
 * Helper function to automatically update availability_status in inventory table
 * based on available_quantity
 * 
 * This function should be called after any inventory update operation
 */

function updateAvailabilityStatus($pdo, $equipment_id) {
    try {
        // Get current available_quantity
        $stmt = $pdo->prepare("SELECT available_quantity FROM inventory WHERE equipment_id = :equipment_id LIMIT 1");
        $stmt->execute([':equipment_id' => $equipment_id]);
        $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inventory) {
            $available_qty = (int)$inventory['available_quantity'];
            
            // Determine status based on quantity
            $status = ($available_qty > 0) ? 'Available' : 'Out of Stock';
            
            // Update availability_status
            $update_stmt = $pdo->prepare("UPDATE inventory 
                                          SET availability_status = :status, 
                                              last_updated = NOW() 
                                          WHERE equipment_id = :equipment_id");
            $update_stmt->execute([
                ':status' => $status,
                ':equipment_id' => $equipment_id
            ]);
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Failed to update availability status: " . $e->getMessage());
        return false;
    }
}

/**
 * Update all inventory records' availability_status
 * Useful for batch updates or maintenance
 */
function updateAllAvailabilityStatuses($pdo) {
    try {
        $sql = "UPDATE inventory 
                SET availability_status = CASE 
                    WHEN available_quantity > 0 THEN 'Available'
                    ELSE 'Out of Stock'
                END,
                last_updated = NOW()";
        
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to update all availability statuses: " . $e->getMessage());
        return false;
    }
}
?>
