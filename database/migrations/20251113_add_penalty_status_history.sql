-- Migration: add penalty status history table
CREATE TABLE IF NOT EXISTS `penalty_status_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `penalty_id` INT NOT NULL,
    `old_status` VARCHAR(64) DEFAULT NULL,
    `new_status` VARCHAR(64) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_penalty_status_penalty` (`penalty_id`),
    CONSTRAINT `fk_penalty_status_penalty`
        FOREIGN KEY (`penalty_id`) REFERENCES `penalties`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
