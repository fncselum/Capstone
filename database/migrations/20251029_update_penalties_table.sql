-- Migration: Update penalties table for IMC policy compliance
-- Date: 2025-10-29
-- Description: Updates penalties table to track amounts owed without payment processing

-- Step 1: Modify penalty_type to support dynamic types (remove ENUM restriction)
ALTER TABLE `penalties` 
MODIFY COLUMN `penalty_type` VARCHAR(100) NOT NULL DEFAULT 'Late Return';

-- Step 2: Update status values to match tracking workflow (no payment processing)
ALTER TABLE `penalties` 
MODIFY COLUMN `status` ENUM('Pending', 'Under Review', 'Resolved', 'Cancelled', 'Appealed') DEFAULT 'Pending';

-- Step 3: Add guideline_id to link penalties to penalty_guidelines
ALTER TABLE `penalties` 
ADD COLUMN `guideline_id` INT(11) DEFAULT NULL AFTER `transaction_id`,
ADD KEY `idx_guideline_id` (`guideline_id`);

-- Step 4: Add equipment tracking fields
ALTER TABLE `penalties` 
ADD COLUMN `equipment_id` VARCHAR(50) DEFAULT NULL AFTER `guideline_id`,
ADD COLUMN `equipment_name` VARCHAR(255) DEFAULT NULL AFTER `equipment_id`;

-- Step 5: Add damage-specific fields
ALTER TABLE `penalties` 
ADD COLUMN `damage_severity` ENUM('minor', 'moderate', 'severe', 'total_loss') DEFAULT NULL AFTER `equipment_name`,
ADD COLUMN `damage_notes` TEXT DEFAULT NULL AFTER `damage_severity`,
ADD COLUMN `detected_issues` TEXT DEFAULT NULL AFTER `damage_notes`,
ADD COLUMN `similarity_score` FLOAT DEFAULT NULL AFTER `detected_issues`;

-- Step 6: Add overdue-specific fields
ALTER TABLE `penalties` 
ADD COLUMN `days_overdue` INT(11) DEFAULT 0 AFTER `similarity_score`,
ADD COLUMN `daily_rate` DECIMAL(10,2) DEFAULT 10.00 AFTER `days_overdue`;

-- Step 7: Add amount tracking fields (no payment processing)
ALTER TABLE `penalties` 
ADD COLUMN `amount_owed` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `penalty_amount`,
ADD COLUMN `amount_note` TEXT DEFAULT NULL AFTER `amount_owed` COMMENT 'Explanation of amount calculation';

-- Step 8: Add resolution tracking
ALTER TABLE `penalties` 
ADD COLUMN `resolution_type` ENUM('Paid', 'Repaired', 'Replaced', 'Waived', 'Other') DEFAULT NULL AFTER `date_resolved`,
ADD COLUMN `resolution_notes` TEXT DEFAULT NULL AFTER `resolution_type`;

-- Step 9: Add admin tracking
ALTER TABLE `penalties` 
ADD COLUMN `imposed_by` INT(11) DEFAULT NULL AFTER `resolved_by` COMMENT 'Admin who imposed penalty',
ADD KEY `idx_imposed_by` (`imposed_by`);

-- Step 10: Add indexes for better performance
ALTER TABLE `penalties` 
ADD KEY `idx_penalty_type` (`penalty_type`),
ADD KEY `idx_status` (`status`),
ADD KEY `idx_date_imposed` (`date_imposed`);

-- Step 11: Update existing records to set default values
UPDATE `penalties` 
SET `amount_owed` = `penalty_amount` 
WHERE `amount_owed` = 0.00 AND `penalty_amount` > 0;

UPDATE `penalties` 
SET `penalty_type` = 'Late Return' 
WHERE `penalty_type` = 'Overdue';

-- Step 12: Add foreign key for guideline_id (if penalty_guidelines table exists)
-- Note: This will only work if penalty_guidelines table exists
SET @guideline_table_exists = (
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'penalty_guidelines'
);

SET @sql = IF(@guideline_table_exists > 0,
    'ALTER TABLE `penalties` ADD CONSTRAINT `fk_penalties_guideline` 
     FOREIGN KEY (`guideline_id`) REFERENCES `penalty_guidelines` (`id`) 
     ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "penalty_guidelines table does not exist, skipping foreign key" AS notice'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migration complete
-- The penalties table now supports:
-- 1. Dynamic penalty types (not limited to enum)
-- 2. Tracking amounts owed without payment processing
-- 3. Linking to penalty guidelines
-- 4. Damage assessment details
-- 5. Overdue calculation fields
-- 6. Resolution tracking (paid, repaired, replaced)
-- 7. Better admin audit trail
