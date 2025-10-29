# Phase 4: Damage Penalty System - Testing Guide (Manual Workflow)

## Quick Start Testing

### Step 1: Database Setup
```sql
-- Run this in phpMyAdmin or MySQL command line
SOURCE c:/xampp/htdocs/Capstone/database/migrations/20251027_enhance_penalties_for_damage.sql;

-- Verify tables were created
SHOW TABLES LIKE 'penalty%';

DESCRIBE penalties;
```

### Step 2: Test the Workflow

#### A. Create a Test Return Transaction
1. Go to user interface and borrow an equipment
2. Return the equipment with a photo
3. Wait for image comparison to complete

#### B. Review Return in Admin Panel
1. Login to admin panel
2. Go to **All Transactions** page
3. Find a returned transaction with low similarity score (< 70%)
4. Click the **Review** button in the Return Verification column

#### C. Add to Penalty
1. In the Return Verification Modal, you should see:
   - Reference photo (left)
   - Return photo (right)
   - **Detected Issues** section showing comparison results
   - Three action buttons at the bottom
2. Click the purple **"Add to Penalty"** button (with gavel icon)
3. You should be redirected to Penalty Management page

#### D. Record Manual Damage Penalty
1. On Penalty Management page, you should see:
   - **Create Damage Penalty** section at the top (red gradient background)
   - Transaction details grid showing:
     - Transaction ID
     - Student ID and name
     - Equipment name
     - Similarity score (color-coded)
   - **Detected Issues** box (yellow) showing issues from image comparison
   - Penalty form with:
     - Damage Severity dropdown (manual selection)
     - Damage assessment textarea (describe observed issues)
     - Final Decision textarea (document what penalty/judgment will be applied)

2. Select a damage severity level that matches the observed condition (no automatic amount).

3. Complete both textareas with the admin’s assessment and chosen action.

4. Click **"Record Damage Penalty"** button.

5. Verify success message appears.

#### E. Verify in Database
```sql
-- Check the penalty was created
SELECT * FROM penalties 
WHERE penalty_type = 'Damaged' 
ORDER BY created_at DESC 
LIMIT 1;

-- Verify damage-specific fields (manual notes instead of financials)
SELECT 
    p.id,
    p.transaction_id,
    p.equipment_name,
    p.damage_severity,
    p.damage_notes,
    p.status,
    da.detected_issues,
    da.similarity_score,
    da.comparison_summary,
    da.admin_assessment
FROM penalties p
LEFT JOIN penalty_damage_assessments da ON da.penalty_id = p.id
WHERE p.penalty_type = 'Damaged'
ORDER BY p.created_at DESC;
```

---

## Expected Results

### ✅ Success Indicators

1. **Button Changed:**
   - Old: "Reject Return" (red)
   - New: "Add to Penalty" (purple with gavel icon)

2. **Redirect Works:**
   - URL should be: `admin-penalty-management.php?action=create_damage_penalty&transaction_id=X&...`

3. **Form Auto-Populated:**
   - Transaction ID shows correctly
   - Student information displays
   - Equipment name appears
   - Similarity score is color-coded:
     - Red if < 50%
     - Yellow if 50-69%
     - Green if ≥ 70%

4. **Detected Issues Display:**
   - Yellow box appears
   - Issues from image comparison are listed
   - Issues are formatted with line breaks

5. **Auto-Calculation Works:**
   - Changing severity updates amount and points immediately
   - Values match the severity guidelines

6. **Database Record Created:**
   - New row in `penalties` table
   - `damage_severity` field populated
   - `detected_issues` contains comparison results
   - `similarity_score` stored correctly

---

## Common Issues & Solutions

### Issue 1: "Add to Penalty" button not appearing
**Solution:** 
- Clear browser cache (Ctrl+Shift+Delete)
- Hard refresh page (Ctrl+F5)
- Check if CSS file loaded: `admin/assets/css/all-transactions.css`

### Issue 2: Redirect shows blank page
**Solution:**
- Check PHP error log: `C:\xampp\php\logs\php_error_log`
- Verify `admin-penalty-management.php` exists
- Check database connection in penalty management page

### Issue 3: Form not auto-populating
**Solution:**
- Check URL has query parameters
- Verify transaction exists in database
- Check SQL query in `admin-penalty-management.php` line 52-70

### Issue 4: Amount not auto-calculating
**Solution:**
- Open browser console (F12)
- Check for JavaScript errors
- Verify `updateDamagePenaltyAmount()` function exists
- Check element IDs match: `damage_severity`, `penalty_amount`, `penalty_points`

### Issue 5: Database error when creating penalty
**Solution:**
- Run migration SQL file first
- Check all required columns exist in `penalties` table
- Verify `damage_severity` ENUM values are correct

---

## Test Scenarios

### Scenario 1: Minor Damage (Cosmetic)
- **Setup:** Return equipment with similarity score ~70% and cosmetic scuffs.
- **Expected:** Select “Minor”, note cosmetic damage, describe action (e.g., warning issued).

### Scenario 2: Moderate Damage
- **Setup:** Similarity score around 55%, visible dents.
- **Expected:** Select “Moderate”, describe damage, note the penalty (e.g., partial fine or repair requirement if policy exists).

### Scenario 3: Severe Damage
- **Setup:** Similarity score < 40%, major structural issues.
- **Expected:** Select “Severe”, document assessment, specify action (e.g., escalate to admin panel for replacement charge).

### Scenario 4: Total Loss
- **Setup:** Item is unusable—add photos manually if needed.
- **Expected:** Select “Total Loss”, note that replacement is required, record admin decision.

### Scenario 5: Multiple Issues Detected
- **Setup:** Detected issues list contains multiple bullet points.
- **Expected:** All issues appear in yellow box; ensure damage_notes summarizes them and admin notes describe final action.

---

## Verification Queries

### Check Penalty Statistics
```sql
-- Count penalties by damage severity
SELECT 
    damage_severity,
    COUNT(*) as count,
    SUM(penalty_amount) as total_amount
FROM penalties 
WHERE penalty_type = 'Damaged'
GROUP BY damage_severity;
```

### View Pending Damage Penalties
```sql
-- Use the new view
SELECT * FROM v_pending_damage_penalties;
```

### Check Recent Damage Penalties
```sql
SELECT 
    p.id,
    p.transaction_id,
    p.equipment_name,
    p.damage_severity,
    p.penalty_amount,
    p.similarity_score,
    p.detected_issues,
    p.status,
    p.created_at,
    u.student_id,
    u.name as student_name
FROM penalties p
LEFT JOIN transactions t ON p.transaction_id = t.id
LEFT JOIN users u ON t.user_id = u.id
WHERE p.penalty_type = 'Damaged'
ORDER BY p.created_at DESC
LIMIT 10;
```

---

## Performance Testing

### Load Test
1. Create 10 damage penalties in succession
2. Verify page loads within 2 seconds
3. Check database performance with EXPLAIN:
```sql
EXPLAIN SELECT * FROM v_pending_damage_penalties;
```

### Concurrent Access Test
1. Open penalty management in 2 browser tabs
2. Create penalties simultaneously
3. Verify no conflicts or data corruption

---

## Browser Compatibility

Test in:
- ✅ Chrome (recommended)
- ✅ Firefox
- ✅ Edge
- ⚠️ Safari (may need testing)
- ❌ IE11 (not supported)

---

## Mobile Responsiveness

Test on:
- Desktop (1920x1080)
- Tablet (768x1024)
- Mobile (375x667)

Expected behavior:
- Form should stack vertically on mobile
- Buttons should remain accessible
- Grid should collapse to single column

---

## Security Testing

### Input Sanitization
- Try entering `' OR '1'='1` in textareas -> expected to be stored as plain text (no SQL errors).

### XSS Prevention
- Try entering `<script>alert('XSS')</script>` in damage notes -> expected to be escaped when displayed.

### Authorization Check
- Try accessing penalty management without login -> expect redirect to login page.

---

## Rollback Plan

If issues occur:
```sql
-- Rollback database changes
ALTER TABLE penalties 
DROP COLUMN equipment_id,
DROP COLUMN damage_severity,
DROP COLUMN damage_description,
DROP COLUMN detected_issues,
DROP COLUMN similarity_score,
DROP COLUMN admin_notes;

DROP TABLE IF EXISTS penalty_attachments;
DROP TABLE IF EXISTS penalty_history;
DROP VIEW IF EXISTS v_pending_damage_penalties;
```

Then restore backup files:
- `admin/admin-all-transaction.php.backup`
- `admin/admin-penalty-management.php.backup`

---

## Success Criteria

Phase 4 is successful when:
- ✅ All database migrations run without errors
- ✅ "Add to Penalty" button appears and works
- ✅ Redirect to penalty management succeeds
- ✅ Form auto-populates with transaction data
- ✅ Detected issues display correctly
- ✅ Severity selection auto-calculates amounts
- ✅ Penalty creation saves to database
- ✅ No JavaScript errors in console
- ✅ No PHP errors in logs
- ✅ UI is responsive and user-friendly

---

## Next Steps After Testing

1. **User Training:** Train admins on manual damage penalty workflow.
2. **Documentation:** Update admin manual with non-financial process.
3. **Monitoring:** Watch for any issues in production.
4. **Feedback:** Collect admin feedback for improvements.
5. **Phase 5:** Plan next enhancements (photo attachments, appeals, etc.).

---

## Support Contacts

- **Database Issues:** Check `php_error_log`
- **UI Issues:** Check browser console (F12)
- **Workflow Questions:** Refer to `PHASE_4_DAMAGE_PENALTY_SYSTEM.md`

---

**Happy Testing! 🚀**
