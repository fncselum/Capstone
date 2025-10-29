-- Migration: Add maintenance_quantity tracking to inventory
-- Date: 2025-10-28
-- Description: Adds maintenance_quantity column and recalculates inventory availability to reserve units under maintenance

ALTER TABLE inventory
ADD COLUMN maintenance_quantity INT NOT NULL DEFAULT 0 AFTER borrowed_quantity;

-- Recalculate availability to incorporate maintenance reservations
UPDATE inventory
SET 
    available_quantity = GREATEST(quantity - borrowed_quantity - COALESCE(damaged_quantity, 0) - COALESCE(maintenance_quantity, 0), 0),
    availability_status = CASE
        WHEN GREATEST(quantity - borrowed_quantity - COALESCE(damaged_quantity, 0) - COALESCE(maintenance_quantity, 0), 0) <= 0 THEN 'Not Available'
        WHEN GREATEST(quantity - borrowed_quantity - COALESCE(damaged_quantity, 0) - COALESCE(maintenance_quantity, 0), 0) <= COALESCE(minimum_stock_level, 1) THEN 'Low Stock'
        WHEN COALESCE(maintenance_quantity, 0) > 0 THEN 'Partially Available'
        ELSE 'Available'
    END,
    last_updated = NOW();

-- Verify updates
SELECT 
    equipment_id,
    quantity,
    borrowed_quantity,
    maintenance_quantity,
    damaged_quantity,
    available_quantity,
    availability_status
FROM inventory
ORDER BY equipment_id;
