# Database Cleanup Instructions - Phase 4

## Overview
The penalties table currently has many unused columns from the previous comprehensive implementation. Since the system now uses a **manual damage penalty workflow without financial processing**, these columns should be removed.

---

## Columns to Remove

### Financial Processing (Not Used)
- `penalty_amount` - No automatic fee calculation
- `penalty_points` - No point system
- `payment_method` - No payment tracking
- `payment_reference` - No payment tracking

### Duplicate/Replaced Columns
- `admin_notes` - Replaced by `damage_notes`
- `damage_description` - Replaced by `damage_notes`
- `detected_issues` - Moved to `penalty_damage_assessments` table
- `similarity_score` - Moved to `penalty_damage_assessments` table

### Not Applicable for Damage Penalties
- `guideline_id` - Not using penalty guidelines for damage
- `violation_date` - Only relevant for overdue penalties
- `days_overdue` - Only relevant for overdue penalties

---

## Columns to KEEP

### Core Penalty Fields
- `id` - Primary key
- `user_id` - Student/user who has the penalty
- `transaction_id` - Link to transaction
- `penalty_type` - Type: Overdue, Damaged, Lost, etc.
- `status` - Pending, Under Review, Resolved, Cancelled
- `description` - General description (optional)
- `notes` - Admin notes (general purpose)

### Damage-Specific Fields
- `equipment_id` - Equipment RFID tag
- `equipment_name` - Equipment name
- `damage_severity` - minor, moderate, severe, total_loss
- `damage_notes` - Admin's damage assessment

### Audit Fields
- `date_imposed` - When penalty was created
- `date_resolved` - When penalty was resolved
- `resolved_by` - Admin who resolved it
- `created_at` - Record creation timestamp
- `updated_at` - Last update timestamp

---

## How to Clean Up

### Step 1: Backup Current Data
```sql
-- Create backup of penalties table
CREATE TABLE penalties_backup_20251027 AS SELECT * FROM penalties;

-- Verify backup
SELECT COUNT(*) FROM penalties_backup_20251027;
```

### Step 2: Run Cleanup Migration
```sql
-- Run the cleanup script
SOURCE c:/xampp/htdocs/Capstone/database/migrations/20251027_cleanup_penalties_columns.sql;

-- Verify columns were removed
DESCRIBE penalties;
```

### Step 3: Verify Data Integrity
```sql
-- Check that existing penalties still have required fields
SELECT 
    id,
    user_id,
    transaction_id,
    equipment_id,
    equipment_name,
    penalty_type,
    damage_severity,
    damage_notes,
    status
FROM penalties
WHERE penalty_type = 'Damaged'
LIMIT 5;

-- Check damage assessments are linked properly
SELECT 
    p.id,
    p.equipment_name,
    p.damage_severity,
    da.detected_issues,
    da.similarity_score
FROM penalties p
LEFT JOIN penalty_damage_assessments da ON da.penalty_id = p.id
WHERE p.penalty_type = 'Damaged'
LIMIT 5;
```

---

## Expected Final Schema

After cleanup, the `penalties` table should have:

```sql
CREATE TABLE `penalties` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(50) NOT NULL,
  `transaction_id` INT NULL,
  `equipment_id` VARCHAR(50) NULL,
  `equipment_name` VARCHAR(255) NULL,
  `penalty_type` ENUM('Overdue','Damaged','Lost','Misuse','Other') NOT NULL DEFAULT 'Other',
  `damage_severity` ENUM('minor','moderate','severe','total_loss') NULL,
  `damage_notes` TEXT NULL,
  `description` TEXT NULL,
  `notes` TEXT NULL,
  `status` ENUM('Pending','Under Review','Resolved','Cancelled') NOT NULL DEFAULT 'Pending',
  `date_imposed` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `date_resolved` DATETIME NULL,
  `resolved_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Rollback Plan

If you need to restore the columns:

```sql
-- Restore from backup
DROP TABLE penalties;
CREATE TABLE penalties AS SELECT * FROM penalties_backup_20251027;

-- Or manually add columns back (not recommended)
ALTER TABLE penalties
ADD COLUMN penalty_amount DECIMAL(10,2) DEFAULT 0,
ADD COLUMN penalty_points INT DEFAULT 0,
-- ... etc
```

---

## Testing After Cleanup

1. **Create a damage penalty** from transaction review
2. **Verify it saves** with only severity and notes
3. **Check the database** to ensure no errors
4. **View penalty list** in admin panel
5. **Update penalty status** to ensure workflow still works

---

## Summary

**Before Cleanup:** 25+ columns (many unused)  
**After Cleanup:** ~16 essential columns  
**Result:** Cleaner schema, easier maintenance, matches actual workflow

Run the cleanup migration when ready! 🚀
