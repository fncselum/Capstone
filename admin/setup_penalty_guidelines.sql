-- Create penalty_guidelines table
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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_penalty_type` (`penalty_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key to penalties table (if penalties table exists)
ALTER TABLE `penalties` 
ADD COLUMN IF NOT EXISTS `guideline_id` INT NULL,
ADD FOREIGN KEY IF NOT EXISTS (`guideline_id`) REFERENCES `penalty_guidelines`(`id`) ON DELETE SET NULL;

-- Insert sample data
INSERT INTO `penalty_guidelines` (`title`, `penalty_type`, `penalty_description`, `penalty_amount`, `penalty_points`, `status`, `created_by`) VALUES
('Late Return - 1 Day', 'Late Return', 'Equipment returned 1 day after the due date. First offense penalty.', 50.00, 1, 'active', 1),
('Late Return - 2-3 Days', 'Late Return', 'Equipment returned 2-3 days after the due date. Moderate penalty for extended delay.', 100.00, 2, 'active', 1),
('Late Return - Over 1 Week', 'Late Return', 'Equipment returned more than 1 week after the due date. Severe penalty for excessive delay.', 250.00, 5, 'active', 1),
('Minor Damage', 'Damage', 'Equipment has minor scratches, dents, or cosmetic damage that does not affect functionality.', 150.00, 2, 'active', 1),
('Major Damage', 'Damage', 'Equipment has significant damage affecting functionality. Requires repair or replacement.', 500.00, 5, 'active', 1),
('Complete Loss', 'Loss', 'Equipment is completely lost or cannot be recovered. Full replacement cost will be charged.', 1000.00, 10, 'active', 1),
('Equipment Misuse', 'Misuse', 'Equipment used for purposes other than intended or in violation of usage guidelines.', 200.00, 3, 'active', 1);
