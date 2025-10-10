-- =====================================================
-- Enhanced Penalties Table Migration
-- Adds guideline integration and tracking fields
-- =====================================================

-- Add new columns to penalties table
ALTER TABLE `penalties` 
ADD COLUMN `guideline_id` INT DEFAULT NULL AFTER `id`,
ADD COLUMN `resolved_by` INT DEFAULT NULL AFTER `date_resolved`,
ADD COLUMN `last_modified_by` INT DEFAULT NULL AFTER `resolved_by`,
ADD COLUMN `last_modified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `last_modified_by`,
ADD COLUMN `equipment_name` VARCHAR(255) DEFAULT NULL AFTER `description`,
ADD COLUMN `violation_date` DATE DEFAULT NULL AFTER `date_imposed`,
ADD COLUMN `days_overdue` INT DEFAULT 0 AFTER `penalty_points`,
ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `description`;

-- Add foreign key for guideline_id
ALTER TABLE `penalties`
ADD CONSTRAINT `fk_penalties_guideline` 
FOREIGN KEY (`guideline_id`) REFERENCES `penalty_guidelines`(`id`) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Add indexes for better performance
ALTER TABLE `penalties`
ADD INDEX `idx_guideline_id` (`guideline_id`),
ADD INDEX `idx_resolved_by` (`resolved_by`),
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_penalty_type` (`penalty_type`),
ADD INDEX `idx_violation_date` (`violation_date`);

-- Update existing records to have proper timestamps
UPDATE `penalties` 
SET `last_modified_at` = `updated_at` 
WHERE `last_modified_at` IS NULL;

-- Sample comment
-- Note: Make sure penalty_guidelines table exists before running this migration
