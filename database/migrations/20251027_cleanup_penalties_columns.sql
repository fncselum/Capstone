-- =====================================================
-- Cleanup Migration: Remove Unused Penalty Columns
-- Date: 2025-10-27
-- Purpose: Remove financial and duplicate columns from penalties table
-- =====================================================

-- These columns are NOT needed for manual damage penalty workflow:
-- 1. penalty_amount, penalty_points - No financial processing
-- 2. admin_notes - Replaced by damage_notes
-- 3. damage_description - Replaced by damage_notes
-- 4. detected_issues, similarity_score - Moved to penalty_damage_assessments table
-- 5. payment_method, payment_reference - No payment tracking
-- 6. guideline_id - Not using penalty guidelines for damage
-- 7. violation_date, days_overdue - Only relevant for overdue penalties

-- === Drop Unused Columns ===
ALTER TABLE `penalties`
DROP COLUMN IF EXISTS `penalty_amount`,
DROP COLUMN IF EXISTS `penalty_points`,
DROP COLUMN IF EXISTS `admin_notes`,
DROP COLUMN IF EXISTS `damage_description`,
DROP COLUMN IF EXISTS `detected_issues`,
DROP COLUMN IF EXISTS `similarity_score`,
DROP COLUMN IF EXISTS `payment_method`,
DROP COLUMN IF EXISTS `payment_reference`,
DROP COLUMN IF EXISTS `guideline_id`,
DROP COLUMN IF EXISTS `violation_date`,
DROP COLUMN IF EXISTS `days_overdue`;

-- === Success Message ===
SELECT 'Penalties table cleaned up - removed unused columns for manual workflow.' AS Status;
