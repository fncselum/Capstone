# Penalty Guideline Fetch Fix

## Issue
When issuing a penalty, the system was failing with error: "Failed to create penalty. Please verify the details."

The problem was that `penalty_type` and `penalty_amount` were not being properly fetched from the `penalty_guidelines` database table.

## Root Causes

### 1. **Hardcoded Penalty Type**
The form had a hardcoded hidden input:
```html
<input type="hidden" name="penalty_type" value="Damaged">
```
This prevented the system from using the penalty type from the selected guideline.

### 2. **Penalty Amount Not Fetched**
The penalty creation logic was trying to get `penalty_amount` from POST data instead of fetching it from the guideline.

### 3. **Inconsistent Column Aliasing**
- `getActiveGuidelines()` aliased `title` as `guideline_name`
- `getGuidelineById()` used `SELECT *` without aliasing
- This caused inconsistency when accessing guideline data

## Fixes Applied

### Fix 1: Remove Hardcoded Penalty Type

**File:** `admin/admin-penalty-management.php` (Line 439)

**Before:**
```html
<input type="hidden" name="penalty_type" value="Damaged">
```

**After:**
```html
<!-- Removed - penalty_type will be fetched from guideline -->
```

---

### Fix 2: Fetch Guideline Data Before Creating Payload

**File:** `admin/admin-penalty-management.php` (Lines 209-246)

**Before:**
```php
if ($action === 'create_penalty') {
    $payload = [
        'penalty_type' => trim($_POST['penalty_type'] ?? ''),
        'penalty_amount' => isset($_POST['penalty_amount']) ? (float)$_POST['penalty_amount'] : 0,
        // ...
    ];
    
    // Guideline fetched AFTER payload creation
    if ($payload['guideline_id']) {
        $guideline = $penaltySystem->getGuidelineById($payload['guideline_id']);
        // ...
    }
}
```

**After:**
```php
if ($action === 'create_penalty') {
    // First, get guideline details if guideline_id is provided
    $guideline_id = (int)($_POST['guideline_id'] ?? 0);
    $penalty_type = '';
    $penalty_amount = 0;
    $penalty_description = '';
    
    if ($guideline_id > 0) {
        $guideline = $penaltySystem->getGuidelineById($guideline_id);
        if ($guideline) {
            $penalty_type = $guideline['penalty_type'] ?? '';
            $penalty_amount = (float)($guideline['penalty_amount'] ?? 0);
            $penalty_description = $guideline['penalty_description'] ?? '';
        }
    }
    
    $payload = [
        'penalty_type' => $penalty_type,
        'penalty_amount' => $penalty_amount,
        'description' => $penalty_description,
        'amount_owed' => $penalty_amount,
        'amount_note' => $penalty_description,
        // ...
    ];
}
```

---

### Fix 3: Update getGuidelineById Method

**File:** `admin/penalty-system.php` (Lines 162-177)

**Before:**
```php
public function getGuidelineById(int $guidelineId): ?array
{
    $query = $this->conn->prepare(
        "SELECT * FROM penalty_guidelines WHERE id = ? AND status = 'active'"
    );
    // ...
}
```

**After:**
```php
public function getGuidelineById(int $guidelineId): ?array
{
    $query = $this->conn->prepare(
        "SELECT id, title AS guideline_name, penalty_type, penalty_amount, 
                penalty_points, penalty_description, document_path, status 
         FROM penalty_guidelines 
         WHERE id = ? AND status = 'active'"
    );
    // ...
}
```

**Why:** Ensures `title` is aliased as `guideline_name` for consistency with `getActiveGuidelines()`.

---

## Data Flow

### Updated Penalty Creation Flow:

```
1. User selects penalty guideline from dropdown
   ↓
2. Form submits with guideline_id
   ↓
3. Backend fetches guideline from database:
   - penalty_type (e.g., "Damage", "Late Return", "Loss")
   - penalty_amount (e.g., 10.00, 0.00)
   - penalty_description
   ↓
4. Payload is created with fetched values:
   - penalty_type: from guideline
   - penalty_amount: from guideline
   - description: from guideline
   ↓
5. Penalty is created in database:
   - penalties table
   - penalty_damage_assessments table
   - penalty_history table
   - penalty_attachments table
```

## Database Schema Reference

### penalty_guidelines Table:
```sql
id                  INT
title               VARCHAR(255)     -- Aliased as guideline_name
penalty_type        VARCHAR(100)     -- "Damage", "Late Return", "Loss"
penalty_description TEXT
penalty_amount      DECIMAL(10,2)    -- Amount in pesos
penalty_points      INT
document_path       VARCHAR(500)
status              VARCHAR(50)      -- "active", "inactive"
created_by          INT
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

### Example Data:
| id | title | penalty_type | penalty_amount | penalty_points |
|----|-------|--------------|----------------|----------------|
| 4 | Overdue Equipment Daily Fee | Late Return | 10.00 | 0 |
| 5 | Damaged Equipment - Borrower Repair | Damage | 0.00 | 0 |
| 6 | Lost Equipment Replacement | Loss | 0.00 | 0 |

## Validation

### Required Fields Check:
```php
if ($payload['transaction_id'] <= 0 || 
    $payload['penalty_type'] === '' || 
    $payload['equipment_name'] === '') {
    $error_message = "Please complete all required fields for creating a penalty.";
}
```

Now properly validates that:
- ✅ `transaction_id` exists
- ✅ `penalty_type` is fetched from guideline
- ✅ `equipment_name` is provided

## Special Cases

### Late Return Penalties:
```php
if ($payload['penalty_type'] === 'Late Return' && $payload['days_overdue'] > 0) {
    $dailyRate = $payload['daily_rate'] ?: PenaltySystem::DEFAULT_DAILY_RATE;
    $amount = $dailyRate * $payload['days_overdue'];
    $payload['penalty_amount'] = $amount;
    $payload['amount_owed'] = $amount;
    $payload['amount_note'] = sprintf('%d day(s) × ₱%0.2f per day', 
                                       $payload['days_overdue'], $dailyRate);
}
```

For Late Return penalties, the amount is calculated based on days overdue × daily rate.

## Testing Checklist

- [x] Penalty guideline dropdown loads correctly
- [x] Guideline selection populates penalty_type
- [x] Guideline selection populates penalty_amount
- [x] Guideline selection populates penalty_description
- [x] Form validation checks for required fields
- [x] Penalty creates successfully in database
- [x] Transaction status updates after penalty creation
- [x] Penalty history record is created
- [x] Error handling for missing guideline

## Files Modified

1. **`admin/admin-penalty-management.php`**
   - Lines 209-246: Updated penalty creation logic
   - Line 439: Removed hardcoded penalty_type

2. **`admin/penalty-system.php`**
   - Lines 162-177: Updated getGuidelineById method

## Benefits

✅ **Correct Data** - Penalty type and amount fetched from database  
✅ **Consistency** - Column aliasing consistent across methods  
✅ **Flexibility** - Supports different penalty types and amounts  
✅ **Validation** - Proper error checking for required fields  
✅ **Maintainability** - Centralized guideline data management  

---

**Status:** ✅ Fixed
**Date:** October 29, 2024
**Impact:** Penalty creation now properly fetches penalty_type and penalty_amount from penalty_guidelines table
