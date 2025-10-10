-- =====================================================
-- UPDATE PENALTIES TABLE SCHEMA
-- Run this SQL in phpMyAdmin to enhance the penalties table
-- =====================================================

-- Add new columns to penalties table
ALTER TABLE `penalties` 
ADD COLUMN IF NOT EXISTS `guideline_id` INT NULL AFTER `penalty_points`,
ADD COLUMN IF NOT EXISTS `resolved_by` INT NULL AFTER `date_resolved`,
ADD COLUMN IF NOT EXISTS `last_modified_by` INT NULL AFTER `resolved_by`,
ADD COLUMN IF NOT EXISTS `last_modified_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `last_modified_by`;

-- Add indexes for better performance
ALTER TABLE `penalties`
ADD INDEX IF NOT EXISTS `idx_guideline_id` (`guideline_id`),
ADD INDEX IF NOT EXISTS `idx_status` (`status`),
ADD INDEX IF NOT EXISTS `idx_penalty_type` (`penalty_type`),
ADD INDEX IF NOT EXISTS `idx_resolved_by` (`resolved_by`),
ADD INDEX IF NOT EXISTS `idx_date_imposed` (`date_imposed`);

-- Add foreign key to link with penalty_guidelines table
ALTER TABLE `penalties` 
ADD CONSTRAINT `fk_penalty_guideline` 
FOREIGN KEY (`guideline_id`) 
REFERENCES `penalty_guidelines`(`id`) 
ON DELETE SET NULL;

-- Update created_at and updated_at if they don't exist or aren't set properly
ALTER TABLE `penalties`
MODIFY COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
MODIFY COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add comment to table
ALTER TABLE `penalties` COMMENT = 'Stores penalty records with links to guidelines';
