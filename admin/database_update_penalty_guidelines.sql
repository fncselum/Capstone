-- =====================================================
-- PENALTY GUIDELINES DATABASE SETUP
-- Run this SQL in phpMyAdmin to update your database
-- =====================================================

-- Check if table exists and create if not
CREATE TABLE IF NOT EXISTS `penalty_guidelines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `penalty_type` VARCHAR(100) NOT NULL,
  `penalty_description` TEXT NOT NULL,
  `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `penalty_points` INT NOT NULL DEFAULT 0,
  `document_path` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('draft', 'active', 'archived') DEFAULT 'draft',
  `created_by` INT DEFAULT NULL,
  `updated_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_penalty_type` (`penalty_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`),
  INDEX `idx_updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign keys if admin_users table exists
-- Uncomment these lines if you have admin_users table
-- ALTER TABLE `penalty_guidelines` 
-- ADD CONSTRAINT `fk_penalty_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL,
-- ADD CONSTRAINT `fk_penalty_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL;

-- Update existing penalties table to link with guidelines (if penalties table exists)
-- This creates a relationship between penalties and guidelines
ALTER TABLE `penalties` 
ADD COLUMN IF NOT EXISTS `guideline_id` INT NULL AFTER `penalty_points`,
ADD INDEX IF NOT EXISTS `idx_guideline_id` (`guideline_id`);

-- Add foreign key for penalties table (uncomment if needed)
-- ALTER TABLE `penalties` 
-- ADD CONSTRAINT `fk_penalty_guideline` FOREIGN KEY (`guideline_id`) REFERENCES `penalty_guidelines`(`id`) ON DELETE SET NULL;

-- Insert sample penalty guidelines (if table is empty)
INSERT INTO `penalty_guidelines` (`title`, `penalty_type`, `penalty_description`, `penalty_amount`, `penalty_points`, `status`, `created_by`)
SELECT * FROM (
  SELECT 'Late Return - 1 Day' as title, 'Late Return' as penalty_type, 
         'Equipment returned 1 day after the due date. First offense penalty applies.' as penalty_description,
         50.00 as penalty_amount, 1 as penalty_points, 'active' as status, 1 as created_by
  UNION ALL
  SELECT 'Late Return - 2-3 Days', 'Late Return', 
         'Equipment returned 2-3 days after the due date. Moderate penalty for extended delay.',
         100.00, 2, 'active', 1
  UNION ALL
  SELECT 'Late Return - Over 1 Week', 'Late Return', 
         'Equipment returned more than 1 week after the due date. Severe penalty for excessive delay.',
         250.00, 5, 'active', 1
  UNION ALL
  SELECT 'Minor Damage', 'Damage', 
         'Equipment has minor scratches, dents, or cosmetic damage that does not affect functionality.',
         150.00, 2, 'active', 1
  UNION ALL
  SELECT 'Major Damage', 'Damage', 
         'Equipment has significant damage affecting functionality. Requires repair or replacement.',
         500.00, 5, 'active', 1
  UNION ALL
  SELECT 'Complete Loss', 'Loss', 
         'Equipment is completely lost or cannot be recovered. Full replacement cost will be charged.',
         1000.00, 10, 'active', 1
  UNION ALL
  SELECT 'Equipment Misuse', 'Misuse', 
         'Equipment used for purposes other than intended or in violation of usage guidelines.',
         200.00, 3, 'active', 1
) AS sample_data
WHERE NOT EXISTS (SELECT 1 FROM `penalty_guidelines` LIMIT 1);

-- Create uploads directory structure (Note: This must be done manually via file system)
-- Create folder: /uploads/penalty_documents/
-- Set permissions: 755 or 777 depending on server configuration
