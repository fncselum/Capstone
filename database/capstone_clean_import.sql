-- Capstone Equipment Inventory System Database
-- Database: capstone
-- Clean Import Version - 2025-10-05

-- Drop existing database and recreate (CAUTION: This will delete all existing data)
DROP DATABASE IF EXISTS `capstone`;
CREATE DATABASE `capstone` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `capstone`;

-- --------------------------------------------------------

-- Table structure for table `users`
-- This table stores only RFID tags and student IDs for scanning (no personal information per client request)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfid_tag` varchar(50) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `status` enum('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
  `penalty_points` int(11) DEFAULT 0,
  `registered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfid_tag` (`rfid_tag`),
  UNIQUE KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `categories`
-- This table stores equipment categories
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories as per client requirements
INSERT INTO `categories` (`name`, `description`) VALUES
('Sport Equipment', 'Sports and recreational equipment'),
('Lab Equipment', 'Laboratory and scientific equipment'),
('Digital Equipment', 'Digital and electronic devices'),
('Room Equipment', 'Classroom and room furniture/equipment'),
('School Equipment', 'General school supplies and equipment'),
('Others', 'Miscellaneous equipment not fitting other categories');

-- --------------------------------------------------------

-- Table structure for table `equipment`
-- This table stores all equipment information
CREATE TABLE `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `rfid_tag` varchar(50) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfid_tag` (`rfid_tag`),
  KEY `fk_equipment_category` (`category_id`),
  CONSTRAINT `fk_equipment_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `inventory`
-- This table tracks equipment inventory, quantities, conditions, and availability
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `available_quantity` int(11) NOT NULL DEFAULT 0,
  `borrowed_quantity` int(11) NOT NULL DEFAULT 0,
  `damaged_quantity` int(11) NOT NULL DEFAULT 0,
  `item_condition` enum('Excellent', 'Good', 'Fair', 'Poor', 'Out of Service') DEFAULT 'Good',
  `availability_status` enum('Available', 'Out of Stock', 'Partially Available', 'Maintenance') DEFAULT 'Available',
  `minimum_stock_level` int(11) DEFAULT 1,
  `location` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_id` (`equipment_id`),
  CONSTRAINT `fk_inventory_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `transactions`
-- This table tracks borrowing and returning of equipment
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `transaction_type` enum('Borrow', 'Return', 'Maintenance', 'Damaged') NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `transaction_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expected_return_date` datetime DEFAULT NULL,
  `actual_return_date` datetime DEFAULT NULL,
  `condition_before` enum('Excellent', 'Good', 'Fair', 'Poor', 'Out of Service') DEFAULT NULL,
  `condition_after` enum('Excellent', 'Good', 'Fair', 'Poor', 'Out of Service') DEFAULT NULL,
  `status` enum('Active', 'Returned', 'Overdue', 'Lost', 'Damaged') DEFAULT 'Active',
  `penalty_applied` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `processed_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_transactions_user` (`user_id`),
  KEY `fk_transactions_equipment` (`equipment_id`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `penalties`
-- This table tracks penalties for late returns, damages, etc.
CREATE TABLE `penalties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `penalty_type` enum('Late Return', 'Damage', 'Loss', 'Other') NOT NULL,
  `penalty_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `penalty_points` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `status` enum('Pending', 'Paid', 'Waived', 'Appealed') DEFAULT 'Pending',
  `date_imposed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_resolved` datetime DEFAULT NULL,
  `resolved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_penalties_user` (`user_id`),
  KEY `fk_penalties_transaction` (`transaction_id`),
  CONSTRAINT `fk_penalties_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_penalties_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `admin_users`
-- This table stores admin user credentials
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rfid_tag` varchar(50) DEFAULT NULL,
  `status` enum('Active', 'Inactive') DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Insert default admin user (password: admin123)
INSERT INTO `admin_users` (`username`, `password`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- --------------------------------------------------------

-- Create views for easier data retrieval

-- View for equipment with inventory details
CREATE VIEW `equipment_inventory_view` AS
SELECT 
    e.id,
    e.name,
    e.rfid_tag,
    c.name as category_name,
    e.description,
    e.image_path,
    e.image_url,
    i.quantity,
    i.available_quantity,
    i.borrowed_quantity,
    i.damaged_quantity,
    i.item_condition,
    i.availability_status,
    i.location,
    e.created_at
FROM equipment e
LEFT JOIN categories c ON e.category_id = c.id
LEFT JOIN inventory i ON e.id = i.equipment_id;

-- View for active transactions
CREATE VIEW `active_transactions_view` AS
SELECT 
    t.id,
    u.rfid_tag as user_rfid,
    u.student_id,
    e.name as equipment_name,
    e.rfid_tag as equipment_rfid,
    c.name as category_name,
    t.quantity,
    t.transaction_date,
    t.expected_return_date,
    t.status,
    DATEDIFF(NOW(), t.expected_return_date) as days_overdue
FROM transactions t
JOIN users u ON t.user_id = u.id
JOIN equipment e ON t.equipment_id = e.id
JOIN categories c ON e.category_id = c.id
WHERE t.status IN ('Active', 'Overdue');

-- --------------------------------------------------------

COMMIT;
