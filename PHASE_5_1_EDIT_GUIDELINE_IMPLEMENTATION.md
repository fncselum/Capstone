# Phase 5.1: Edit Penalty Guideline Implementation

## Overview
Implemented full edit functionality for penalty guidelines with file upload handling, allowing admins to update existing guidelines and replace supporting documents.

---

## Features Implemented

### 1. **Edit Modal with Pre-filled Data**
- Click "Edit" button on any guideline card
- Modal opens with all current data loaded
- Shows current document (if exists) with link to view
- All fields are editable

### 2. **File Upload & Replacement**
- Upload new document (PDF, DOCX, JPG, PNG)
- Max file size: 5MB
- Unique filename generation: `penalty_[timestamp]_[uniqueid].[ext]`
- Automatic deletion of old document when new one uploaded
- Optional: Keep existing document if no new file uploaded

### 3. **Database Update**
- Updates all fields: title, type, description, amount, points, status
- Updates `document_path` if new file uploaded
- Automatically sets `updated_at` to NOW()
- Preserves `created_by` and `created_at` fields

### 4. **User Experience**
- Success/error messages via session
- Page reload after successful update
- Modal closes automatically
- Current document displayed in edit form

---

## Database Schema

```sql
penalty_guidelines
├─ id INT PRIMARY KEY AUTO_INCREMENT
├─ title VARCHAR(255)
├─ penalty_type VARCHAR(255)
├─ penalty_description TEXT
├─ penalty_amount DECIMAL(10,2)
├─ penalty_points INT
├─ document_path VARCHAR(255)
├─ status ENUM('active','inactive','draft','archived')
├─ created_by INT
├─ created_at DATETIME
└─ updated_at DATETIME
```

---

## Files Modified/Created

### 1. **save_penalty_guideline.php** (Created)
**Location:** `admin/save_penalty_guideline.php`

**Functionality:**
- Handles both INSERT (new) and UPDATE (edit) operations
- File upload processing with validation
- Unique filename generation
- Old file deletion on replacement
- Database update with prepared statements
- Session-based success/error messages

**Key Features:**
```php
// File Upload
- Directory: uploads/penalty_documents/
- Filename: penalty_[timestamp]_[uniqueid].[ext]
- Max size: 5MB
- Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG

// Update Logic
if ($id) {
    // UPDATE existing guideline
    if ($document_path) {
        // Update with new document
        // Delete old document
    } else {
        // Update without changing document
    }
} else {
    // INSERT new guideline
}
```

**Security:**
- Session authentication check
- File type validation
- File size validation
- SQL injection prevention (prepared statements)
- Path traversal prevention

---

### 2. **admin-penalty-guideline.php** (Modified)

**Changes:**

#### A. Modal Form - Current Document Display
```html
<div id="currentDocumentInfo" style="display: none;">
    <small>
        <i class="fas fa-file"></i> <strong>Current:</strong> 
        <a id="currentDocumentLink" href="#" target="_blank">View Document</a>
    </small>
    <br>
    <small>Upload a new file to replace the current document</small>
</div>
```

#### B. JavaScript - editGuideline() Function
```javascript
function editGuideline(id) {
    fetch(`get_penalty_guideline.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const g = data.guideline;
                
                // Populate form fields
                document.getElementById('guidelineId').value = g.id;
                document.getElementById('title').value = g.title;
                document.getElementById('penalty_type').value = g.penalty_type;
                // ... other fields
                
                // Show current document if exists
                const currentDocInfo = document.getElementById('currentDocumentInfo');
                const currentDocLink = document.getElementById('currentDocumentLink');
                if (g.document_path) {
                    currentDocLink.href = g.document_path;
                    currentDocLink.textContent = g.document_path.split('/').pop();
                    currentDocInfo.style.display = 'block';
                } else {
                    currentDocInfo.style.display = 'none';
                }
                
                document.getElementById('guidelineModal').style.display = 'block';
            }
        });
}
```

#### C. JavaScript - openAddModal() Function
```javascript
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Penalty Guideline';
    document.getElementById('guidelineForm').reset();
    document.getElementById('guidelineId').value = '';
    // Hide current document info for new guideline
    document.getElementById('currentDocumentInfo').style.display = 'none';
    document.getElementById('guidelineModal').style.display = 'block';
    applyPolicyTemplate();
}
```

---

## Data Flow

### Edit Workflow

```
1. Admin clicks "Edit" button
   ↓
2. JavaScript calls editGuideline(id)
   ↓
3. Fetch guideline data from get_penalty_guideline.php
   ↓
4. Populate modal form with current data
   ↓
5. Show current document (if exists)
   ↓
6. Admin modifies fields
   ↓
7. Admin optionally uploads new document
   ↓
8. Form submits to save_penalty_guideline.php
   ↓
9. PHP validates input and file
   ↓
10. If new file uploaded:
    - Generate unique filename
    - Move file to uploads/penalty_documents/
    - Delete old file
    - Update document_path in database
    ↓
11. Update database record
    - Set updated_at = NOW()
    ↓
12. Redirect with success message
    ↓
13. Page reloads, modal closes
    ↓
14. Updated guideline displayed in grid
```

---

## File Upload Details

### Directory Structure
```
Capstone/
├─ admin/
│  ├─ admin-penalty-guideline.php
│  ├─ save_penalty_guideline.php
│  └─ get_penalty_guideline.php
└─ uploads/
   └─ penalty_documents/
      ├─ penalty_1730217600_abc123.pdf
      ├─ penalty_1730218000_def456.docx
      └─ penalty_1730218400_ghi789.jpg
```

### Filename Format
```
penalty_[timestamp]_[uniqueid].[extension]

Examples:
- penalty_1730217600_65f8a3b2c1d4e.pdf
- penalty_1730218000_65f8a4c3d2e5f.docx
- penalty_1730218400_65f8a5d4e3f6g.jpg
```

### Upload Process
```php
// 1. Validate file
if ($file_size > 5 * 1024 * 1024) {
    // Error: File too large
}

// 2. Check extension
$allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
if (!in_array($file_ext, $allowed)) {
    // Error: Invalid file type
}

// 3. Generate unique filename
$unique_filename = 'penalty_' . time() . '_' . uniqid() . '.' . $file_ext;

// 4. Move file
move_uploaded_file($file_tmp, $upload_dir . $unique_filename);

// 5. Delete old file
if ($old_document_path && file_exists('../' . $old_document_path)) {
    unlink('../' . $old_document_path);
}

// 6. Update database
$document_path = 'uploads/penalty_documents/' . $unique_filename;
```

---

## SQL Queries

### Update with New Document
```sql
UPDATE penalty_guidelines 
SET title = ?, 
    penalty_type = ?, 
    penalty_description = ?, 
    penalty_amount = ?, 
    penalty_points = ?, 
    document_path = ?, 
    status = ?, 
    updated_at = NOW() 
WHERE id = ?
```

### Update without Changing Document
```sql
UPDATE penalty_guidelines 
SET title = ?, 
    penalty_type = ?, 
    penalty_description = ?, 
    penalty_amount = ?, 
    penalty_points = ?, 
    status = ?, 
    updated_at = NOW() 
WHERE id = ?
```

### Insert New Guideline
```sql
INSERT INTO penalty_guidelines 
(title, penalty_type, penalty_description, penalty_amount, penalty_points, 
 document_path, status, created_by, created_at, updated_at) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
```

---

## Testing Checklist

### ✅ Edit Functionality
- [ ] Click Edit button opens modal
- [ ] All fields pre-filled with current data
- [ ] Current document displayed (if exists)
- [ ] Can modify all fields
- [ ] Can change status
- [ ] Modal title shows "Edit Penalty Guideline"

### ✅ File Upload
- [ ] Can upload PDF file
- [ ] Can upload DOCX file
- [ ] Can upload JPG/PNG image
- [ ] File size validation (5MB max)
- [ ] File type validation
- [ ] Unique filename generated
- [ ] Old file deleted when new uploaded
- [ ] Can update without changing document

### ✅ Database Update
- [ ] All fields updated correctly
- [ ] document_path updated when file uploaded
- [ ] updated_at timestamp updated
- [ ] created_by and created_at preserved
- [ ] Status updated correctly

### ✅ User Experience
- [ ] Success message displayed
- [ ] Error messages displayed
- [ ] Modal closes after save
- [ ] Page refreshes with updated data
- [ ] Updated guideline visible in grid

### ✅ Security
- [ ] Session authentication works
- [ ] File type validation prevents malicious files
- [ ] File size validation prevents large files
- [ ] SQL injection prevented
- [ ] Path traversal prevented

---

## Example Usage

### Scenario 1: Update Description Only
```
1. Admin clicks Edit on "Lost Equipment Replacement"
2. Modal opens with current data
3. Admin changes description to add more details
4. Admin clicks "Save Guideline"
5. Database updated, updated_at set to NOW()
6. Success message: "Penalty guideline updated successfully!"
7. Page reloads, updated description visible
```

### Scenario 2: Replace Document
```
1. Admin clicks Edit on "Overdue Equipment Daily Fee"
2. Modal shows current document: "policy_v1.pdf"
3. Admin uploads new file: "updated_policy_v2.pdf"
4. Admin clicks "Save Guideline"
5. New file saved as: "penalty_1730217600_abc123.pdf"
6. Old file "policy_v1.pdf" deleted
7. Database document_path updated
8. Success message displayed
9. New document link visible in card
```

### Scenario 3: Change Status
```
1. Admin clicks Edit on "Damaged Equipment" (status: draft)
2. Admin changes status to "active"
3. Admin clicks "Save Guideline"
4. Database updated, status = 'active'
5. Card badge changes from "Draft" to "Active"
6. Guideline now available for penalty issuance
```

---

## Error Handling

### File Upload Errors
```php
// File too large
if ($file_size > 5 * 1024 * 1024) {
    $_SESSION['error_message'] = 'File size exceeds 5MB limit.';
    header('Location: admin-penalty-guideline.php');
    exit;
}

// Invalid file type
if (!in_array($file_ext, $allowed_extensions)) {
    $_SESSION['error_message'] = 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG';
    header('Location: admin-penalty-guideline.php');
    exit;
}

// Upload failed
if (!move_uploaded_file($file_tmp, $upload_path)) {
    $_SESSION['error_message'] = 'Failed to upload document.';
    header('Location: admin-penalty-guideline.php');
    exit;
}
```

### Validation Errors
```php
// Required fields
if (empty($title) || empty($penalty_type) || empty($penalty_description)) {
    $_SESSION['error_message'] = 'Please fill in all required fields.';
    header('Location: admin-penalty-guideline.php');
    exit;
}

// Database errors
if (!$stmt->execute()) {
    $_SESSION['error_message'] = 'Failed to update penalty guideline: ' . $stmt->error;
}
```

---

## Benefits

### For Admins
✅ **Easy Updates** - Modify guidelines without recreating  
✅ **Document Management** - Replace outdated policy documents  
✅ **Version Control** - updated_at timestamp tracks changes  
✅ **Flexible Status** - Change from draft to active easily  
✅ **No Data Loss** - All fields preserved during edit  

### For System
✅ **Clean File Management** - Old files automatically deleted  
✅ **Unique Filenames** - No naming conflicts  
✅ **Secure Uploads** - File validation and size limits  
✅ **Database Integrity** - Prepared statements prevent injection  
✅ **Audit Trail** - Timestamps track when changes made  

---

## Future Enhancements (Optional)

### Version History
- Track all changes to guidelines
- Show who made changes and when
- Ability to revert to previous version

### Document Preview
- Preview PDF/images in modal before saving
- Thumbnail display in edit form
- Inline document viewer

### Bulk Operations
- Edit multiple guidelines at once
- Batch status updates
- Mass document uploads

### Advanced Validation
- Check for duplicate titles
- Validate amount ranges
- Require document for certain types

---

## Status

✅ **Phase 5.1 Complete**

**Implemented:**
- Edit modal with pre-filled data
- File upload with replacement
- Database update with timestamp
- Current document display
- Success/error messaging
- Security validation

**Files:**
- `admin/save_penalty_guideline.php` (Created)
- `admin/admin-penalty-guideline.php` (Modified)
- `PHASE_5_1_EDIT_GUIDELINE_IMPLEMENTATION.md` (Documentation)

**Ready for testing and deployment!** 🎉
