-- ============================================================================
-- PENALTY SYSTEM DATABASE CLEANUP
-- ============================================================================
-- This script removes unused columns and tables from the penalty system
-- Run this in phpMyAdmin SQL tab
-- ============================================================================

-- BACKUP REMINDER: Always backup your database before running this script!
-- You can export the database from phpMyAdmin before proceeding

-- ============================================================================
-- STEP 1: Keep all columns in penalties table
-- ============================================================================

-- NOTE: date_resolved and resolved_by columns are KEPT
-- These are used when admin updates penalty status to "Resolved"
-- date_resolved: When the penalty was resolved/paid
-- resolved_by: Which admin marked it as resolved

-- No columns to remove from penalties table

-- ============================================================================
-- STEP 2: Drop unused tables
-- ============================================================================

-- Drop penalty_history table (not used in createPenalty)
-- This table was intended for audit trail but is not currently implemented
DROP TABLE IF EXISTS `penalty_history`;

-- Drop penalty_attachments table (not used in createPenalty)
-- This table was intended for file attachments but is not currently implemented
DROP TABLE IF EXISTS `penalty_attachments`;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these after the cleanup to verify the changes

-- Check penalties table structure
DESCRIBE `penalties`;

-- Check if penalty_history table exists (should return error if dropped successfully)
-- DESCRIBE `penalty_history`;

-- Check if penalty_attachments table exists (should return error if dropped successfully)
-- DESCRIBE `penalty_attachments`;

-- Check penalty_damage_assessments table (should still exist)
DESCRIBE `penalty_damage_assessments`;

-- ============================================================================
-- FINAL STRUCTURE AFTER CLEANUP
-- ============================================================================

/*
penalties table (KEPT - CORE TABLE):
- id
- user_id
- transaction_id
- guideline_id
- equipment_id
- equipment_name
- penalty_type
- penalty_amount
- amount_owed
- amount_note
- days_overdue
- daily_rate
- damage_severity
- damage_notes
- description
- notes (admin additional notes)
- status
- imposed_by
- date_imposed
- date_resolved (KEPT - when penalty was resolved/paid)
- resolved_by (KEPT - admin who resolved it)
- created_at
- updated_at

penalty_damage_assessments table (KEPT - USED FOR DAMAGE TRACKING):
- id
- penalty_id
- detected_issues
- similarity_score
- comparison_summary
- admin_assessment
- created_at

penalty_guidelines table (KEPT - CORE TABLE):
- id
- title
- penalty_type
- penalty_description
- penalty_amount
- penalty_points
- document_path
- status
- created_by
- created_at
- updated_at

REMOVED:
- penalty_history table (not implemented)
- penalty_attachments table (not implemented)
*/

-- ============================================================================
-- NOTES
-- ============================================================================
/*
1. The penalty_history table was designed for audit trail but is not currently
   being used in the createPenalty() method.

2. The penalty_attachments table was designed for file uploads but is not
   currently being used in the createPenalty() method.

3. The date_resolved and resolved_by columns are KEPT in the penalties table.
   These will be used when admin updates a penalty status to "Resolved".
   - date_resolved: Timestamp when penalty was marked as resolved/paid
   - resolved_by: Admin ID who resolved the penalty

4. If you need the removed features in the future, you can recreate the tables
   using the CREATE TABLE statements below.
*/

-- ============================================================================
-- OPTIONAL: Recreate tables if needed in the future
-- ============================================================================

/*
-- Recreate penalty_history table
CREATE TABLE `penalty_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `penalty_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `old_amount` decimal(10,2) DEFAULT NULL,
  `new_amount` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `penalty_id` (`penalty_id`),
  FOREIGN KEY (`penalty_id`) REFERENCES `penalties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recreate penalty_attachments table
CREATE TABLE `penalty_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `penalty_id` int(11) NOT NULL,
  `file_type` enum('photo','document','receipt') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `penalty_id` (`penalty_id`),
  FOREIGN KEY (`penalty_id`) REFERENCES `penalties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: date_resolved and resolved_by columns are already in the table
-- No need to add them back as they were kept in the cleanup
*/
