-- Email Logs Table
-- Tracks all emails sent by the system

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `email_type` ENUM('overdue', 'borrow', 'return', 'low_stock') NOT NULL,
  `equipment_name` VARCHAR(255),
  `transaction_id` INT,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('sent', 'failed') DEFAULT 'sent',
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_email_type` (`email_type`),
  INDEX `idx_sent_at` (`sent_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
