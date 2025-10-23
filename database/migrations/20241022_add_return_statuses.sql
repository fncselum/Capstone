-- Add 'Not Yet Returned' to status ENUMs
ALTER TABLE transactions 
MODIFY COLUMN return_review_status ENUM('Not Yet Returned', 'Pending', 'Verified', 'Flagged', 'Rejected') DEFAULT 'Not Yet Returned',
MODIFY COLUMN return_verification_status ENUM('Not Yet Returned', 'Pending', 'Verified', 'Flagged', 'Rejected') DEFAULT 'Not Yet Returned';

-- Create transaction_meta table if it doesn't exist
CREATE TABLE IF NOT EXISTS `transaction_meta` (
  `meta_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint(20) UNSIGNED NOT NULL,
  `meta_key` varchar(255) NOT NULL,
  `meta_value` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`meta_id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
