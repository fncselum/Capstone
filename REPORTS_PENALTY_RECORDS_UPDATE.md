# Reports - Penalty Records Update

## Overview
Updated the monthly reports system to align with client's policy by replacing "Penalty Points" with "Penalty Records Count". This change reflects the actual number of penalty records issued for each equipment rather than accumulated point values.

---

## Changes Made

### **1. Database Query Updates**

#### **Added Penalty Records Query:**
```php
// Count penalty records for each equipment
$penaltyStmt = $conn->prepare("SELECT e.rfid_tag, COUNT(p.id) as penalty_count
                                 FROM penalties p
                                 INNER JOIN transactions t ON p.transaction_id = t.id
                                 INNER JOIN equipment e ON t.equipment_id = e.id
                                 WHERE MONTH(p.created_at) = ? AND YEAR(p.created_at) = ?
                                 GROUP BY e.rfid_tag");
```

**Purpose:**
- Counts actual penalty records from `penalties` table
- Groups by equipment RFID tag
- Filters by selected month and year
- Uses INNER JOINs to link penalties → transactions → equipment

#### **Removed Old Logic:**
```php
// OLD - Removed
$penalty = (float)($r['penalty_applied'] ?? 0);
if ($penalty > 0) {
    $totals['penalty_total'] += $penalty;
    $equipmentSummary[$rfid]['penalty_total'] += $penalty;
}
```

---

### **2. Data Structure Changes**

#### **Totals Array:**
```php
// BEFORE
$totals = [
    'penalty_total' => 0,  // Sum of penalty points
];

// AFTER
$totals = [
    'penalty_records' => 0,  // Count of penalty records
];
```

#### **Equipment Summary Array:**
```php
// BEFORE
$equipmentSummary[$rfid] = [
    'penalty_total' => 0,
];

// AFTER
$equipmentSummary[$rfid] = [
    'penalty_records' => 0,
];
```

---

### **3. UI Changes**

#### **Summary Card:**
```html
<!-- BEFORE -->
<div class="summary-card">
    <h3><i class="fas fa-coins"></i> Penalty Points</h3>
    <strong><?= number_format($totals['penalty_total']) ?></strong>
    <div>Recorded this period</div>
</div>

<!-- AFTER -->
<div class="summary-card">
    <h3><i class="fas fa-file-invoice"></i> Penalty Records</h3>
    <strong><?= number_format($totals['penalty_records']) ?></strong>
    <div class="subtitle">Issued this period</div>
</div>
```

**Changes:**
- Icon: `fa-coins` → `fa-file-invoice`
- Label: "Penalty Points" → "Penalty Records"
- Subtitle: "Recorded" → "Issued"

#### **Equipment Summary Table:**
```html
<!-- BEFORE -->
<th>Penalty Points</th>
<td><?= number_format($item['penalty_total']) ?></td>

<!-- AFTER -->
<th>Penalty Records</th>
<td><?= number_format($item['penalty_records']) ?></td>
```

#### **Detailed Transactions Table:**
```html
<!-- BEFORE -->
<th>Penalty Points</th>
<td><?= $penaltyAmount > 0 ? number_format($penaltyAmount) : '—' ?></td>

<!-- AFTER -->
<!-- Column removed entirely -->
```

**Removed:**
- "Penalty Points" column from detailed transactions table
- Associated PHP logic for calculating penalty amounts per transaction

---

### **4. Design Enhancements**

#### **Modern Card Design:**
```css
.summary-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fdf9 100%);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border: 2px solid #e8f3ee;
    position: relative;
    overflow: hidden;
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, #006633 0%, #00994d 100%);
}
```

**Features:**
- Gradient background
- Left border accent (green)
- Hover lift effect
- Enhanced shadows

#### **Enhanced Table Styling:**
```css
th {
    background: linear-gradient(135deg, #f3fbf6 0%, #e8f5ee 100%);
    color: #006633;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #006633;
}

tbody tr:nth-child(even) {
    background: #fafcfb;
}
```

**Features:**
- Gradient header background
- Uppercase headers with letter spacing
- Alternating row colors
- Smooth hover transitions

#### **Improved Badges:**
```css
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid;
}

.badge.borrow { 
    background: #e3f2fd; 
    color: #0d47a1;
    border-color: #90caf9;
}
```

**Features:**
- Pill-shaped design
- Border accents
- Icon support
- Better contrast

#### **Panel Headers:**
```css
.panel h3 {
    margin-bottom: 20px;
    font-size: 1.2rem;
    color: #006633;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 3px solid #e8f3ee;
}
```

**Features:**
- Icons in headers
- Bottom border accent
- Larger, bolder text
- Green color theme

#### **Empty States:**
```css
.empty-state {
    padding: 40px 24px;
    text-align: center;
    color: #556b66;
    font-size: 1rem;
}

.empty-state i {
    font-size: 3rem;
    color: #c5d9ce;
    margin-bottom: 16px;
    display: block;
}
```

**Features:**
- Large icon
- Centered text
- Generous padding
- Muted colors

---

## Client Policy Alignment

### **From Client Document:**

> **Question 1:** What penalties or sanctions do you believe should be implemented for damaged or unreturned equipment?
>
> **Answer:**
> - Overdue – ₱10.00 per day (to be reviewed if Saturday and Sunday are included)
> - Damaged Item – To be repaired by the borrower
> - Lost Item – Replace the lost item with the same unit

### **Implementation:**

✅ **Penalty Records Count** - Tracks individual penalty instances  
✅ **No Point System** - Removed accumulated points concept  
✅ **Per-Equipment Tracking** - Shows how many penalties per equipment  
✅ **Clear Reporting** - Displays actual penalty records issued  

---

## Data Flow

```
penalties table
    ↓
JOIN with transactions
    ↓
JOIN with equipment
    ↓
GROUP BY equipment RFID
    ↓
COUNT penalty records
    ↓
Display in reports
```

---

## Benefits

### **For Administrators:**
- ✅ **Accurate Tracking:** Count of actual penalty records
- ✅ **Equipment-Specific:** See which equipment has most penalties
- ✅ **Period-Based:** Filter by month/year
- ✅ **Clear Reporting:** No confusion with point systems

### **For Compliance:**
- ✅ **Policy Alignment:** Matches client's penalty structure
- ✅ **Audit Trail:** Track penalty records over time
- ✅ **Transparency:** Clear count of penalties issued

### **For System:**
- ✅ **Database-Driven:** Queries actual penalty table
- ✅ **Accurate Counts:** No manual calculations
- ✅ **Scalable:** Handles growing penalty records

---

## Report Sections

### **1. Summary Cards (5 Cards):**
- Total Borrowed
- Total Returned
- Damaged Items
- Currently Borrowed
- **Penalty Records** ← Updated

### **2. Equipment Summary Table:**
Columns:
- RFID Tag
- Equipment
- Borrowed Qty
- Returned Qty
- Damaged Returned Qty
- Currently Borrowed Qty
- **Penalty Records** ← Updated

### **3. Detailed Transactions Table:**
Columns:
- Date
- Student ID
- RFID
- Equipment
- Type
- Qty
- Status
- ~~Penalty Points~~ ← Removed

---

## Database Schema Reference

### **Penalties Table:**
```sql
CREATE TABLE `penalties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `penalty_type` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Paid','Waived') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `document_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_penalties_user` (`user_id`),
  KEY `fk_penalties_transaction` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Query Performance

### **Optimization:**
- Uses indexed columns (`created_at`, `transaction_id`)
- INNER JOINs for required relationships
- GROUP BY for aggregation
- Prepared statements for security

### **Execution:**
```
1. Filter penalties by month/year
2. Join to transactions table
3. Join to equipment table
4. Group by equipment RFID
5. Count penalty records
6. Return results
```

---

## Testing Checklist

- [ ] Penalty records count displays correctly
- [ ] Equipment summary shows accurate counts
- [ ] Detailed transactions table has no penalty column
- [ ] Summary card shows correct total
- [ ] Month/year filter works
- [ ] Print layout looks professional
- [ ] No SQL errors
- [ ] Empty states display properly
- [ ] Design is consistent
- [ ] Icons display correctly

---

## Visual Improvements

### **Color Scheme:**
- **Primary Green:** #006633 (headers, accents)
- **Light Green:** #e8f3ee (backgrounds)
- **Gradient:** #ffffff → #f8fdf9 (cards)
- **Borders:** #e8f3ee, #e0ece6

### **Typography:**
- **Headers:** Bold, uppercase, letter-spacing
- **Numbers:** Large (2.2rem), bold
- **Subtitles:** Smaller (0.85rem), muted

### **Spacing:**
- **Card Padding:** 24px
- **Panel Padding:** 28px
- **Grid Gap:** 20px
- **Table Padding:** 14px 12px

---

## Print Optimization

```css
@media print {
    .no-print { display:none !important; }
    .main-content { margin:0 !important; padding: 20px !important; }
    .summary-grid { grid-template-columns: repeat(2, 1fr); }
    .top-header { page-break-after: avoid; }
    .panel { page-break-inside: avoid; }
    .summary-card { box-shadow: none; border: 1px solid #ddd; }
}
```

**Features:**
- Hides filters and buttons
- Adjusts layout for paper
- Removes shadows
- Prevents page breaks in panels

---

## File Changes

**Modified:** `admin/reports.php`
- **Lines Changed:** ~100 lines
- **PHP Logic:** Updated queries and data structures
- **HTML:** Updated labels and removed column
- **CSS:** Enhanced styling (~250 lines)

---

## Future Enhancements

### **Potential Additions:**
1. **Penalty Type Breakdown** - Count by type (overdue, damaged, lost)
2. **Penalty Status** - Show pending vs paid
3. **Penalty Amount Summary** - Total monetary value
4. **Trend Charts** - Visualize penalty trends
5. **Export to Excel** - Download detailed reports
6. **Email Reports** - Schedule automated reports

---

**Date:** October 30, 2025  
**Update:** Penalty Points → Penalty Records Count  
**Status:** Fully Implemented  
**Client Policy:** Aligned ✅
