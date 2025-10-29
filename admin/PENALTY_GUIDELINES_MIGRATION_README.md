# Penalty Guidelines - Database Column Migration

## Phase 5: Update Complete ✅

### Overview
This migration updates the `penalty_guidelines` table to rename the `document_path` column to `document_file` for better clarity and consistency with the file upload functionality.

---

## Changes Made

### 1. Database Schema Update
- **Column renamed**: `document_path` → `document_file`
- **Purpose**: Better represents that this field stores the file path for uploaded documents

### 2. Files Updated

#### Backend PHP Files:
1. ✅ **save_penalty_guideline.php**
   - Updated all references from `document_path` to `document_file`
   - File upload logic now uses `$document_file` variable
   - Database INSERT and UPDATE queries updated

2. ✅ **get_penalty_guideline.php**
   - SELECT query now explicitly fetches `document_file` column
   - Returns `document_file` in JSON response

3. ✅ **delete_penalty_guideline.php**
   - Updated to fetch `document_file` before deletion
   - File cleanup logic uses `document_file` reference

4. ✅ **print_penalty_guideline.php**
   - Display logic updated to show `document_file`
   - Basename extraction uses `document_file` field

5. ✅ **penalty-system.php**
   - `getGuidelineById()` method updated to SELECT `document_file`

#### Frontend Files:
6. ✅ **admin-penalty-guideline.php**
   - Database schema SQL updated in setup section
   - All JavaScript references updated (`g.document_file`)
   - PHP display logic updated to use `$guideline['document_file']`
   - View modal, edit modal, and print functionality all updated

---

## Migration Instructions

### Step 1: Backup Your Database
Before running any migration, **always backup your database first**:
```sql
-- In phpMyAdmin, export the 'penalty_guidelines' table
-- Or use mysqldump command
```

### Step 2: Run the Migration Script
1. Open **phpMyAdmin**
2. Select your **capstone** database
3. Go to the **SQL** tab
4. Open the file: `migrate_penalty_guidelines_column.sql`
5. Copy and paste the SQL command:
   ```sql
   ALTER TABLE `penalty_guidelines` 
   CHANGE COLUMN `document_path` `document_file` VARCHAR(255) DEFAULT NULL;
   ```
6. Click **Go** to execute

### Step 3: Verify the Migration
Run this query to confirm the column was renamed:
```sql
DESCRIBE penalty_guidelines;
```

**Expected Result**: You should see `document_file` column instead of `document_path`

### Step 4: Test the System
1. Navigate to **Admin Dashboard** → **Penalty Guidelines**
2. Try adding a new penalty guideline with a document upload
3. Verify the file uploads successfully
4. Edit an existing guideline and upload a new document
5. View a guideline to ensure the document link works
6. Delete a guideline and verify the file is removed from server

---

## Database Structure

### Updated Table Schema:
```sql
CREATE TABLE IF NOT EXISTS `penalty_guidelines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `penalty_type` VARCHAR(100) NOT NULL,
  `penalty_description` TEXT NOT NULL,
  `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `penalty_points` INT NOT NULL DEFAULT 0,
  `document_file` VARCHAR(255) DEFAULT NULL,  -- UPDATED COLUMN NAME
  `status` ENUM('draft', 'active', 'archived') DEFAULT 'draft',
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_penalty_type` (`penalty_type`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## File Upload Details

### Upload Directory:
- **Path**: `uploads/penalty_documents/`
- **Permissions**: 0755
- **Auto-created**: Yes (if doesn't exist)

### File Naming Convention:
The system preserves the **original filename** for easy identification and downloading:
```
original_filename.extension
```
Example: `Penalty_Policy_Document.pdf`

If a file with the same name already exists, a timestamp prefix is added:
```
{timestamp}_original_filename.extension
```
Example: `1730234567_Penalty_Policy_Document.pdf`

**Note**: Special characters in filenames are sanitized to underscores for security.

### Allowed File Types:
- PDF (`.pdf`)
- Word Documents (`.doc`, `.docx`)
- Images (`.jpg`, `.jpeg`, `.png`)

### File Size Limit:
- **Maximum**: 5MB per file

### File Storage:
- Files are stored in: `c:\xampp\htdocs\Capstone\uploads\penalty_documents\`
- Database stores relative path: `uploads/penalty_documents/filename.ext`
- Files are automatically deleted when guideline is deleted or replaced

---

## Sample Data

Based on your current data, here's how the records look:

| id | title | penalty_type | document_file |
|----|-------|--------------|---------------|
| 4 | Overdue Equipment Daily Fee | Late Return | uploads/penalty_documents/penalty_1761697154_69015...pdf |
| 5 | Damaged Equipment - Borrower Repair Requirement | Damage | uploads/penalty_documents/penalty_1761697192_69015...pdf |
| 9 | Minor Scratches of Items | Scratches | uploads/penalty_documents/penalty_1761752315_69023...pdf |
| 10 | dasdadasdasss | ssss | uploads/penalty_documents/penalty_1761752941_69023...pdf |

---

## Troubleshooting

### Issue: Column doesn't exist error
**Solution**: Run the migration script to rename the column

### Issue: Files not uploading
**Solution**: 
1. Check folder permissions on `uploads/penalty_documents/`
2. Ensure PHP `upload_max_filesize` is at least 5MB
3. Check PHP error logs

### Issue: Old files not displaying
**Solution**: 
1. Verify the migration script was run successfully
2. Check that existing file paths in database are correct
3. Ensure files physically exist in the upload directory

---

## Rollback Instructions

If you need to rollback this change:

```sql
-- Rename column back to document_path
ALTER TABLE `penalty_guidelines` 
CHANGE COLUMN `document_file` `document_path` VARCHAR(255) DEFAULT NULL;
```

**Note**: You would also need to revert all the PHP code changes.

---

## Support

For issues or questions:
1. Check the database structure: `DESCRIBE penalty_guidelines;`
2. Review PHP error logs in: `c:\xampp\php\logs\`
3. Check Apache error logs in: `c:\xampp\apache\logs\`

---

**Migration Date**: October 29, 2025  
**Version**: Phase 5  
**Status**: ✅ Complete
