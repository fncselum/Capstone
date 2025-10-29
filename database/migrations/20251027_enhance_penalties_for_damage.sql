-- =====================================================
-- Phase 4 Migration: Simplified Damage Penalty Support
-- Date: 2025-10-27
-- Purpose: Keep penalties focused on manual damage judgment
-- =====================================================

-- === Penalties Table Updates ===
ALTER TABLE `penalties`
ADD COLUMN IF NOT EXISTS `equipment_id` VARCHAR(50) NULL AFTER `transaction_id`,
ADD COLUMN IF NOT EXISTS `equipment_name` VARCHAR(255) NULL AFTER `equipment_id`,
ADD COLUMN IF NOT EXISTS `damage_severity` ENUM('minor', 'moderate', 'severe', 'total_loss') NULL AFTER `penalty_type`,
ADD COLUMN IF NOT EXISTS `damage_notes` TEXT NULL AFTER `description`;

-- Simplify penalty types and statuses (no financial workflow)
ALTER TABLE `penalties`
MODIFY COLUMN `penalty_type` ENUM('Overdue', 'Damaged', 'Lost', 'Misuse', 'Other') NOT NULL DEFAULT 'Other';

ALTER TABLE `penalties`
MODIFY COLUMN `status` ENUM('Pending', 'Under Review', 'Resolved', 'Cancelled') NOT NULL DEFAULT 'Pending';

-- Description optional to let admins rely on damage_notes
ALTER TABLE `penalties`
MODIFY COLUMN `description` TEXT NULL;

-- === Damage Assessment Table ===
CREATE TABLE IF NOT EXISTS `penalty_damage_assessments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `penalty_id` INT NOT NULL,
  `detected_issues` TEXT NULL,
  `similarity_score` DECIMAL(5,2) NULL,
  `comparison_summary` VARCHAR(500) NULL,
  `admin_assessment` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`penalty_id`) REFERENCES `penalties`(`id`) ON DELETE CASCADE,
  INDEX `idx_penalty_damage_penalty_id` (`penalty_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === Backfill Equipment Info for Existing Records ===
UPDATE `penalties` p
INNER JOIN `transactions` t ON p.transaction_id = t.id
LEFT JOIN `equipment` e ON t.equipment_id = e.rfid_tag
SET 
  p.equipment_id = t.equipment_id,
  p.equipment_name = COALESCE(e.name, p.equipment_name)
WHERE p.equipment_id IS NULL;

-- === View for Pending Damage Penalties ===
CREATE OR REPLACE VIEW `v_pending_damage_penalties` AS
SELECT 
  p.id,
  p.transaction_id,
  p.user_id,
  p.equipment_id,
  p.equipment_name,
  p.damage_severity,
  p.damage_notes,
  p.status,
  p.created_at,
  da.detected_issues,
  da.similarity_score
FROM penalties p
LEFT JOIN penalty_damage_assessments da ON da.penalty_id = p.id
WHERE p.penalty_type = 'Damaged' AND p.status IN ('Pending', 'Under Review')
ORDER BY p.created_at DESC;

-- === Success Message ===
SELECT 'Penalties table ready for manual damage assessments.' AS Status;
