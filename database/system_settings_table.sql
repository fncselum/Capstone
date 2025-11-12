-- System Settings Table
-- Run this SQL in phpMyAdmin to enable System Settings functionality

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('system_name', 'Equipment Management System'),
('institution_name', 'De La Salle ASMC'),
('contact_email', 'admin@dlsasmc.edu.ph'),
('max_borrow_days', '7'),
('overdue_penalty_rate', '10.00'),
('max_items_per_borrow', '3'),
('enable_notifications', '1'),
('enable_email_alerts', '0'),
('maintenance_mode', '0'),
('session_timeout', '30'),
('items_per_page', '20')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
