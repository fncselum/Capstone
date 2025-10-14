-- Add admin capability to users table
-- Run this SQL to update the users table

USE `capstone`;

-- Add is_admin column to users table
ALTER TABLE `users` 
ADD COLUMN `is_admin` TINYINT(1) DEFAULT 0 AFTER `status`,
ADD COLUMN `admin_level` ENUM('user', 'admin', 'super_admin') DEFAULT 'user' AFTER `is_admin`;

-- Add index for faster admin lookups
ALTER TABLE `users` 
ADD INDEX `idx_is_admin` (`is_admin`),
ADD INDEX `idx_admin_level` (`admin_level`);

-- Optional: Create a default admin user (update RFID and student_id as needed)
-- INSERT INTO `users` (`rfid_tag`, `student_id`, `status`, `is_admin`, `admin_level`, `penalty_points`) 
-- VALUES ('ADMIN001', 'ADMIN001', 'Active', 1, 'super_admin', 0)
-- ON DUPLICATE KEY UPDATE is_admin = 1, admin_level = 'super_admin';

-- Update existing admin users if you have specific RFID tags
-- UPDATE `users` SET `is_admin` = 1, `admin_level` = 'admin' WHERE `rfid_tag` IN ('YOUR_ADMIN_RFID_1', 'YOUR_ADMIN_RFID_2');
