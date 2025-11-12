-- Unauthorized Access Logs Table
-- Tracks unauthorized RFID access attempts at the kiosk

CREATE TABLE IF NOT EXISTS `unauthorized_access_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rfid_tag` VARCHAR(100) NOT NULL,
  `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `notified` TINYINT(1) DEFAULT 1 COMMENT 'Whether admin was notified via email',
  INDEX `idx_rfid_tag` (`rfid_tag`),
  INDEX `idx_attempt_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    