-- Migration: Update availability_status from "Out of Stock" to "Not Available"
-- Date: 2025-10-28
-- Description: Changes the availability_status terminology in inventory table

-- Step 1: Modify the ENUM column to replace 'Out of Stock' with 'Not Available'
ALTER TABLE inventory 
MODIFY COLUMN availability_status ENUM('Available', 'Not Available', 'Partially Available', 'Low Stock') 
DEFAULT 'Available';

-- Step 2: Update any existing "Out of Stock" records to "Not Available"
-- (This step may not be needed if the ENUM change handles it automatically)
UPDATE inventory 
SET availability_status = 'Not Available' 
WHERE availability_status = 'Out of Stock';

-- Step 3: Verify the update
SELECT 
    availability_status,
    COUNT(*) as count
FROM inventory
GROUP BY availability_status;
