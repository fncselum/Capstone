# File Upload Update - Original Filename Preservation

## Summary
Updated the penalty guidelines file upload system to **preserve original filenames** instead of generating random names. This makes files more recognizable and easier to download with their actual names.

---

## Changes Made

### 1. File Upload Logic (`save_penalty_guideline.php`)

**Before:**
```php
// Generated random filename
$unique_filename = 'penalty_' . time() . '_' . uniqid() . '.' . $file_ext;
// Result: penalty_1761697154_690156789abcd.pdf
```

**After:**
```php
// Preserve original filename with sanitization
$original_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file_name, PATHINFO_FILENAME));
$safe_filename = $original_filename . '.' . $file_ext;

// Add timestamp prefix only if file already exists
if (file_exists($upload_path)) {
    $final_filename = time() . '_' . $safe_filename;
}
// Result: Penalty_Policy_Document.pdf
// Or if exists: 1730234567_Penalty_Policy_Document.pdf
```

### 2. Download Functionality (`admin-penalty-guideline.php`)

**Card View:**
- Changed "View Document" to "Download Document"
- Added `download` attribute with original filename

**View Modal:**
- Added both "View Document" and "Download Document" buttons
- View button: Opens preview (PDF/images)
- Download button: Forces file download with original name

---

## Benefits

### ✅ For Admins:
1. **Recognizable Files**: Can see actual filename instead of random strings
2. **Easy Downloads**: Files download with their original names
3. **Better Organization**: Easier to identify documents in file system
4. **Professional**: More user-friendly interface

### ✅ Security Features:
1. **Filename Sanitization**: Special characters replaced with underscores
2. **Duplicate Prevention**: Timestamp prefix added if file exists
3. **Extension Validation**: Only allowed file types accepted
4. **Size Limit**: 5MB maximum file size enforced

---

## Examples

### Upload Examples:

| Original Upload | Stored As | Notes |
|----------------|-----------|-------|
| `Penalty Policy 2024.pdf` | `Penalty_Policy_2024.pdf` | Spaces converted to underscores |
| `Late-Return-Fee.docx` | `Late-Return-Fee.docx` | Hyphens preserved |
| `Damage@Policy!.pdf` | `Damage_Policy_.pdf` | Special chars sanitized |
| `Policy.pdf` (duplicate) | `1730234567_Policy.pdf` | Timestamp added |

### Download Behavior:

When admin clicks "Download Document":
- File downloads with its **original name** (or sanitized version)
- Browser saves as: `Penalty_Policy_2024.pdf`
- Not as: `penalty_1761697154_690156789abcd.pdf` ❌

---

## File Structure

```
uploads/
└── penalty_documents/
    ├── Overdue_Equipment_Policy.pdf
    ├── Damaged_Equipment_Guidelines.docx
    ├── Minor_Scratches_Policy.pdf
    └── 1730234567_Policy_Document.pdf  (duplicate with timestamp)
```

---

## Technical Details

### Filename Sanitization:
```php
preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename)
```
**Allowed characters:**
- Letters: `a-z`, `A-Z`
- Numbers: `0-9`
- Special: `.` (dot), `_` (underscore), `-` (hyphen)

**Replaced with underscore:**
- Spaces, `@`, `!`, `#`, `$`, `%`, `&`, `*`, `(`, `)`, etc.

### Duplicate Handling:
```php
if (file_exists($upload_path)) {
    $final_filename = time() . '_' . $safe_filename;
}
```
Only adds timestamp if file already exists, preventing overwrites.

---

## Testing Checklist

- [x] Upload file with spaces in name → Converts to underscores
- [x] Upload file with special characters → Sanitizes properly
- [x] Upload duplicate filename → Adds timestamp prefix
- [x] Download from card view → Downloads with original name
- [x] Download from view modal → Downloads with original name
- [x] View document in modal → Shows preview correctly
- [x] Delete guideline → Removes file from server
- [x] Update guideline with new file → Replaces old file

---

## Migration Notes

### For Existing Files:
Old files with random names (e.g., `penalty_1761697154_690156789abcd.pdf`) will continue to work. New uploads will use the original filename format.

### No Database Changes Required:
The `document_file` column stores the path the same way. Only the filename format changed.

---

## User Interface Updates

### Before:
```
📎 View Document
```

### After:
**Card View:**
```
📎 Download Document
```

**View Modal:**
```
👁️ View Document    ⬇️ Download Document
```

---

## Code References

### Files Modified:
1. `save_penalty_guideline.php` - Lines 86-98 (file upload logic)
2. `admin-penalty-guideline.php` - Line 264 (card download link)
3. `admin-penalty-guideline.php` - Lines 494-500 (modal buttons)

---

**Update Date**: October 30, 2025  
**Status**: ✅ Complete  
**Tested**: Yes
