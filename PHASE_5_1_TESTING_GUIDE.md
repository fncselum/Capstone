# Phase 5.1 Testing Guide

## Quick Test Steps

### Test 1: Edit Guideline (No File Change)
1. Go to `admin-penalty-guideline.php`
2. Click **Edit** button on any guideline
3. Modal opens with current data
4. Change the **Description** field
5. Click **Save Guideline**
6. ✅ Success message appears
7. ✅ Page reloads
8. ✅ Updated description visible in card

---

### Test 2: Replace Document
1. Click **Edit** on a guideline with existing document
2. Notice "Current: [filename]" displayed
3. Click **Choose File** and select new PDF
4. Click **Save Guideline**
5. ✅ New file uploaded to `uploads/penalty_documents/`
6. ✅ Old file deleted
7. ✅ New document link visible in card
8. ✅ Click document link - new file opens

---

### Test 3: Add New Document
1. Click **Edit** on guideline without document
2. No "Current document" shown
3. Upload a PDF file
4. Click **Save Guideline**
5. ✅ File uploaded successfully
6. ✅ Document link now visible in card

---

### Test 4: File Validation
1. Click **Edit** on any guideline
2. Try uploading a 10MB file
3. ✅ Error: "File size exceeds 5MB limit"
4. Try uploading a .exe file
5. ✅ Error: "Invalid file type"

---

### Test 5: Update All Fields
1. Click **Edit** on any guideline
2. Change:
   - Title
   - Penalty Type
   - Description
   - Amount
   - Points
   - Status
   - Upload new document
3. Click **Save Guideline**
4. ✅ All fields updated
5. ✅ updated_at timestamp changed
6. ✅ All changes visible in card

---

## Expected Results

### Modal Behavior
- ✅ Opens with "Edit Penalty Guideline" title
- ✅ All fields pre-filled with current data
- ✅ Current document shown (if exists)
- ✅ Can view current document by clicking link
- ✅ File input accepts new uploads

### File Upload
- ✅ Accepts: PDF, DOC, DOCX, JPG, PNG
- ✅ Rejects: EXE, ZIP, other types
- ✅ Max size: 5MB
- ✅ Unique filename generated
- ✅ Saved to: `uploads/penalty_documents/`
- ✅ Old file deleted when replaced

### Database
- ✅ All fields updated correctly
- ✅ `document_path` updated when file uploaded
- ✅ `updated_at` set to current timestamp
- ✅ `created_by` and `created_at` preserved

### User Feedback
- ✅ Success: "Penalty guideline updated successfully!"
- ✅ Error: Specific error message displayed
- ✅ Modal closes after save
- ✅ Page reloads with updated data

---

## Check These Locations

### Files to Verify
```
c:\xampp\htdocs\Capstone\admin\
├─ admin-penalty-guideline.php (modified)
├─ save_penalty_guideline.php (created)
└─ get_penalty_guideline.php (existing)

c:\xampp\htdocs\Capstone\uploads\
└─ penalty_documents\
    └─ penalty_[timestamp]_[uniqueid].[ext]
```

### Database to Check
```sql
-- Check updated_at timestamp
SELECT id, title, document_path, updated_at 
FROM penalty_guidelines 
ORDER BY updated_at DESC;

-- Verify document path
SELECT id, title, document_path 
FROM penalty_guidelines 
WHERE document_path IS NOT NULL;
```

---

## Common Issues & Solutions

### Issue: File not uploading
**Solution:** Check folder permissions
```bash
chmod 755 uploads/penalty_documents/
```

### Issue: Old file not deleted
**Solution:** Check file path in database
```php
// Path should be relative: uploads/penalty_documents/filename.pdf
// Not absolute: /var/www/html/uploads/...
```

### Issue: Modal not showing current document
**Solution:** Check JavaScript console for errors
```javascript
// Verify get_penalty_guideline.php returns document_path
console.log(data.guideline.document_path);
```

### Issue: Updated_at not changing
**Solution:** Check SQL query uses NOW()
```sql
UPDATE penalty_guidelines 
SET ..., updated_at = NOW() 
WHERE id = ?
```

---

## Browser Console Checks

### When clicking Edit:
```javascript
// Should see fetch request
fetch('get_penalty_guideline.php?id=1')

// Should log guideline data
console.log(data.guideline);
// {id: 1, title: "...", document_path: "uploads/...", ...}
```

### When saving:
```javascript
// Form should submit to save_penalty_guideline.php
<form action="save_penalty_guideline.php" method="POST">
```

---

## Success Criteria

✅ **All tests pass**  
✅ **No console errors**  
✅ **Files uploaded correctly**  
✅ **Database updated properly**  
✅ **Old files deleted**  
✅ **User feedback clear**  

---

**Phase 5.1 Ready for Testing!** 🚀
