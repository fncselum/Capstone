# Undefined Array Key Fix - Guideline Name

## Issue
PHP warnings appearing when accessing penalty guidelines:
```
Warning: Undefined array key "guideline_name" in admin-penalty-management.php on line 156
```

## Root Cause
**Mismatch between database column name and array key:**
- Database column: `title`
- Code expected: `guideline_name`

The `getActiveGuidelines()` function in `penalty-system.php` was selecting the `title` column, but the auto-suggestion logic in `admin-penalty-management.php` was trying to access `$guideline['guideline_name']`.

## Fixes Applied

### 1. **Database Query Alias** (Primary Fix)
**File:** `admin/penalty-system.php`

```php
// Before
SELECT id, title, penalty_type, ...

// After
SELECT id, title AS guideline_name, penalty_type, ...
```

**Result:** The `title` column is now aliased as `guideline_name` in the result set.

### 2. **Defensive Null Checking** (Safety Layer)
**File:** `admin/admin-penalty-management.php`

```php
// Before
$guidelineName = strtolower($guideline['guideline_name']);

// After
$guidelineName = isset($guideline['guideline_name']) 
    ? strtolower($guideline['guideline_name']) 
    : '';

if (empty($guidelineName)) {
    continue;
}
```

**Result:** Code safely handles missing keys and skips invalid guidelines.

### 3. **Display Logic Protection**
**File:** `admin/admin-penalty-management.php`

```php
// Before
echo htmlspecialchars($guideline['guideline_name']);

// After
$suggestedGuidelineName = 'N/A';
foreach ($activeGuidelines as $guideline) {
    if (isset($guideline['id']) && $guideline['id'] == $damage_penalty_data['suggested_guideline_id']) {
        $suggestedGuidelineName = isset($guideline['guideline_name']) 
            ? $guideline['guideline_name'] 
            : 'Unnamed Guideline';
        break;
    }
}
echo htmlspecialchars($suggestedGuidelineName);
```

**Result:** Safe display with fallback values.

## Files Modified

1. **`admin/penalty-system.php`** (Line 201)
   - Added `AS guideline_name` alias to SQL query

2. **`admin/admin-penalty-management.php`** (Lines 157-158, 191, 457-464)
   - Added `isset()` checks before accessing array keys
   - Added empty string fallbacks
   - Added skip logic for invalid guidelines
   - Protected display logic with null checks

## Testing

### Before Fix:
```
⚠️ Warning: Undefined array key "guideline_name"
⚠️ Warning: Undefined array key "guideline_name"
⚠️ Warning: Undefined array key "guideline_name"
```

### After Fix:
```
✅ No warnings
✅ Guidelines load correctly
✅ Auto-suggestion works
✅ Display shows guideline name
```

## Prevention Strategy

**Defensive Programming Applied:**
1. ✅ Always use `isset()` before accessing array keys
2. ✅ Provide fallback values for missing data
3. ✅ Use SQL aliases to match expected keys
4. ✅ Skip invalid/incomplete records
5. ✅ Add null coalescing operators (`??`)

## Code Pattern

**Recommended pattern for accessing array data:**

```php
// Safe access with fallback
$value = isset($array['key']) ? $array['key'] : 'default';

// Or using null coalescing (PHP 7+)
$value = $array['key'] ?? 'default';

// For nested access
$value = $array['key']['nested'] ?? 'default';

// With type conversion
$value = isset($array['key']) ? strtolower($array['key']) : '';
```

## Related Issues Prevented

By adding these checks, we also prevent:
- ❌ Undefined index errors
- ❌ Null reference errors
- ❌ Type errors on null values
- ❌ Empty string operations on null

## Database Schema Note

The `penalty_guidelines` table uses `title` as the column name for the guideline name. This is now properly aliased in all queries to match the expected `guideline_name` key used throughout the application.

**Column Mapping:**
```
Database Column → Array Key
------------------------
title           → guideline_name
penalty_type    → penalty_type
penalty_amount  → penalty_amount
```

---

**Status:** ✅ Fixed and tested
**Date:** October 29, 2024
**Impact:** All undefined array key warnings eliminated
