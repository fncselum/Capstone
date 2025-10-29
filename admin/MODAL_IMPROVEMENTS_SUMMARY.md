# Modal Improvements - View & Edit Document Handling

## Summary
Enhanced the **View Modal** and **Edit Modal** to properly display, download, and update documents with original filenames. The modals now provide a professional, user-friendly interface for document management.

---

## Changes Made

### 1. Edit Modal Enhancements

#### Before:
```
Current: View Document
Upload a new file to replace the current document
```

#### After:
```
┌─────────────────────────────────────────────────────────────┐
│ 📄 Current Document:                                         │
│    Penalty_Policy_Document.pdf                               │
│                                                               │
│                                    [👁️ View] [⬇️ Download]  │
│                                                               │
│ ℹ️ Upload a new file below to replace the current document  │
└─────────────────────────────────────────────────────────────┘
```

**Features:**
- ✅ **Prominent filename display** - Shows actual document name
- ✅ **Dual action buttons** - Separate View and Download buttons
- ✅ **Visual hierarchy** - Clear layout with icons and colors
- ✅ **File selection feedback** - Shows new filename when selected
- ✅ **Download with original name** - Preserves filename on download

#### File Selection Feedback:
When admin selects a new file:
```
🔼 New file selected: Updated_Policy.pdf (1.23 MB) - Will replace current document on save
```

---

### 2. View Modal Enhancements

#### Before:
```
Document:
[View Document] [Download Document]
```

#### After:
```
┌─────────────────────────────────────────────────────────────┐
│ 📄 Supporting Document                                       │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 📎 Penalty_Policy_Document.pdf    [👁️ View] [⬇️ Download]│ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                               │
│ [Document Preview Area]                                       │
└─────────────────────────────────────────────────────────────┘
```

**Features:**
- ✅ **Styled document section** - Green-themed box with border
- ✅ **Filename prominently displayed** - Easy to identify document
- ✅ **Action buttons side-by-side** - View and Download clearly visible
- ✅ **Preview area** - Shows PDF/image preview when viewing
- ✅ **Professional styling** - Consistent with admin theme

---

## Technical Implementation

### Edit Modal JavaScript

```javascript
// Populate document info in edit modal
if (g.document_file) {
    const filename = g.document_file.split('/').pop();
    currentDocName.textContent = filename;
    currentDocViewLink.href = g.document_file;
    currentDocDownloadLink.href = g.document_file;
    currentDocDownloadLink.setAttribute('download', filename);
    currentDocInfo.style.display = 'block';
}
```

### File Input Change Handler

```javascript
documentInput.addEventListener('change', function(e) {
    if (e.target.files.length > 0) {
        const newFileName = e.target.files[0].name;
        const fileSize = (e.target.files[0].size / 1024 / 1024).toFixed(2);
        
        // Update info text to show new file
        infoText.innerHTML = `<i class="fas fa-upload"></i> <strong>New file selected:</strong> 
                              ${newFileName} (${fileSize} MB) - Will replace current document on save`;
        infoText.style.color = '#2e7d32';
    }
});
```

### View Modal HTML Structure

```javascript
// Enhanced document display
`<div class="detail-document" style="...">
    <div style="margin-bottom: 12px;">
        <strong style="color: #1e5631;">
            <i class="fas fa-file-alt"></i> Supporting Document
        </strong>
    </div>
    <div style="display: flex; align-items: center; justify-content: space-between; ...">
        <div style="flex: 1;">
            <i class="fas fa-paperclip"></i>
            <span>${g.document_file.split('/').pop()}</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="openDocument('...')">
                <i class="fas fa-eye"></i> View
            </button>
            <a href="..." download="...">
                <i class="fas fa-download"></i> Download
            </a>
        </div>
    </div>
    <div id="documentPreview"></div>
</div>`
```

---

## User Experience Flow

### Viewing a Guideline:
1. Admin clicks **View** button on guideline card
2. Modal opens showing all details
3. Document section displays:
   - **Filename**: `Penalty_Policy_Document.pdf`
   - **View button**: Opens preview in modal
   - **Download button**: Downloads with original name

### Editing a Guideline:
1. Admin clicks **Edit** button on guideline card
2. Modal opens with all fields populated
3. Current document section shows:
   - **Filename**: `Penalty_Policy_Document.pdf`
   - **View button**: Opens in new tab
   - **Download button**: Downloads file
4. Admin can select new file:
   - File input shows selected filename
   - Info updates: "New file selected: Updated_Policy.pdf (1.23 MB)"
5. On save:
   - New file replaces old file
   - Old file is deleted from server
   - New filename is stored in database

---

## Visual Design

### Color Scheme:
- **Primary Green**: `#1e5631` (View buttons, headers)
- **Success Green**: `#2e7d32` (Download buttons, success messages)
- **Background**: `#f8faf9` (Document sections)
- **White**: `#ffffff` (Inner containers)
- **Text**: `#333333` (Primary text)
- **Muted**: `#666666`, `#999999` (Secondary text)

### Icons:
- 📄 `fa-file-alt` - Document header
- 📎 `fa-paperclip` - Filename indicator
- 👁️ `fa-eye` - View action
- ⬇️ `fa-download` - Download action
- 🔼 `fa-upload` - Upload indicator
- ℹ️ `fa-info-circle` - Information

---

## Button Styling

### View Button:
```css
background: #1e5631;
color: white;
padding: 8px 16px;
border-radius: 4px;
```

### Download Button:
```css
background: #2e7d32;
color: white;
padding: 8px 16px;
border-radius: 4px;
```

---

## File Operations

### View Document:
- **PDF files**: Opens inline preview with iframe
- **Images**: Displays image preview
- **Other files**: Triggers download

### Download Document:
- Uses `download` attribute with original filename
- Browser saves file with proper name
- Works for all file types

### Upload New Document:
- Validates file type and size
- Sanitizes filename for security
- Replaces old file on successful upload
- Deletes old file from server

---

## Benefits

### For Admins:
1. **Clear visibility** - Always see the actual document name
2. **Easy access** - View and download buttons readily available
3. **Instant feedback** - Know what file is selected before saving
4. **Professional interface** - Clean, modern design
5. **No confusion** - Clear labels and actions

### For System:
1. **Consistent UX** - Same pattern in view and edit modals
2. **Proper file handling** - Original names preserved
3. **Security maintained** - Filename sanitization in place
4. **Clean storage** - Old files deleted when replaced
5. **Reliable downloads** - Download attribute ensures proper naming

---

## Testing Checklist

### Edit Modal:
- [x] Current document displays with filename
- [x] View button opens document in new tab
- [x] Download button downloads with original name
- [x] File selection shows new filename and size
- [x] Upload replaces old document correctly
- [x] Old file is deleted from server

### View Modal:
- [x] Document section displays prominently
- [x] Filename shows correctly
- [x] View button opens preview in modal
- [x] Download button downloads with original name
- [x] PDF preview works in modal
- [x] Image preview works in modal
- [x] "No document" message shows when empty

---

## Code Files Modified

1. **admin-penalty-guideline.php**
   - Lines 352-377: Edit modal document section HTML
   - Lines 445-464: File input change handler
   - Lines 582-597: Edit modal population logic
   - Lines 525-547: View modal document display

---

## Examples

### Edit Modal - With Document:
```
┌─────────────────────────────────────────────────────────────┐
│ Supporting Document (PDF, DOCX, Images)                      │
│                                                               │
│ ┌───────────────────────────────────────────────────────┐   │
│ │ 📄 Current Document:                                   │   │
│ │    Overdue_Equipment_Policy.pdf                        │   │
│ │                                    [View] [Download]   │   │
│ │                                                         │   │
│ │ ℹ️ Upload a new file below to replace the current doc │   │
│ └───────────────────────────────────────────────────────┘   │
│                                                               │
│ [Choose File] No file chosen                                 │
│ Max file size: 5MB. Allowed: PDF, DOC, DOCX, JPG, PNG       │
└─────────────────────────────────────────────────────────────┘
```

### Edit Modal - After File Selection:
```
┌─────────────────────────────────────────────────────────────┐
│ ┌───────────────────────────────────────────────────────┐   │
│ │ 📄 Current Document:                                   │   │
│ │    Overdue_Equipment_Policy.pdf                        │   │
│ │                                    [View] [Download]   │   │
│ │                                                         │   │
│ │ 🔼 New file selected: Updated_Policy.pdf (1.23 MB)    │   │
│ │    Will replace current document on save               │   │
│ └───────────────────────────────────────────────────────┘   │
│                                                               │
│ [Choose File] Updated_Policy.pdf                             │
└─────────────────────────────────────────────────────────────┘
```

### View Modal - With Document:
```
┌─────────────────────────────────────────────────────────────┐
│ 📄 Supporting Document                                       │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 📎 Overdue_Equipment_Policy.pdf    [View] [Download]   │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                               │
│ [PDF Preview appears here when View is clicked]              │
└─────────────────────────────────────────────────────────────┘
```

---

**Update Date**: October 30, 2025  
**Status**: ✅ Complete  
**Tested**: Yes  
**User Feedback**: Improved clarity and usability
