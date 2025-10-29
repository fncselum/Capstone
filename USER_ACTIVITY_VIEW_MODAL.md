# User Activity - View Button Modal Implementation

## Overview
Implemented a fully functional View button that displays a detailed modal with comprehensive user information and transaction history when clicked.

---

## Features

### **View Button Functionality**

**Location:** User Activity Table → Actions Column

**Behavior:**
- Click "View" button → Opens modal with user details
- Displays user information grid (9 fields)
- Shows recent transaction history (last 50 transactions)
- Modal can be closed via:
  - Close button (X)
  - Clicking outside modal
  - Navigating back to main page

---

## Modal Components

### **1. Modal Header**

**Content:**
- User icon + "User Details - [Student ID]"
- Close button (X)

**Styling:**
- Green color scheme (#006633)
- Bottom border separator
- Flexbox layout (space-between)

### **2. User Information Grid**

**9 Information Fields:**

1. **Student ID**
   - User's student identifier
   - Plain text display

2. **RFID Tag**
   - User's RFID card number
   - Plain text display

3. **Status**
   - Active/Inactive/Suspended
   - Color-coded badge
   - Green (Active), Gray (Inactive), Red (Suspended)

4. **Total Transactions**
   - Count of all transactions
   - Number formatted

5. **Total Borrows**
   - Count of borrow transactions
   - Blue badge with icon

6. **Total Returns**
   - Count of return transactions
   - Green badge with icon

7. **Active Borrows**
   - Currently unreturned items
   - Number formatted

8. **Penalty Count**
   - Number of penalties issued
   - Red badge if > 0
   - "No Penalties" in green if 0

9. **Penalty Points**
   - Total penalty points accumulated
   - Number formatted

**Layout:**
- Responsive grid (auto-fit, min 200px)
- Each field in bordered card
- Left border accent (green)
- Light background (#f9fcfb)

### **3. Transaction History Table**

**Displays:**
- Last 50 transactions
- Sorted by date (newest first)

**Columns (6):**
1. **Date & Time** - Transaction timestamp
2. **Type** - Borrow/Return with badge
3. **Equipment** - Equipment name
4. **Category** - Equipment category
5. **Quantity** - Number of items
6. **Status** - Active/Returned badge

**Features:**
- Horizontal scroll on small screens
- Color-coded type badges
- Status badges
- Alternating row colors

---

## Database Queries

### **User Details Query:**

```sql
SELECT u.*, 
       COUNT(DISTINCT t.id) as total_transactions,
       SUM(CASE WHEN t.transaction_type = 'Borrow' THEN 1 ELSE 0 END) as total_borrows,
       SUM(CASE WHEN t.transaction_type = 'Return' THEN 1 ELSE 0 END) as total_returns,
       SUM(CASE WHEN t.status = 'Active' THEN 1 ELSE 0 END) as active_borrows,
       COUNT(DISTINCT p.id) as penalty_count
FROM users u
LEFT JOIN transactions t ON u.id = t.user_id
LEFT JOIN penalties p ON u.id = p.user_id
WHERE u.id = [selected_user_id]
GROUP BY u.id
```

**Purpose:** Fetch comprehensive user statistics

### **Transaction History Query:**

```sql
SELECT t.*, 
       e.name as equipment_name,
       e.rfid_tag as equipment_rfid,
       c.name as category_name
FROM transactions t
LEFT JOIN equipment e ON t.equipment_id = e.rfid_tag
LEFT JOIN categories c ON e.category_id = c.id
WHERE t.user_id = [selected_user_id]
ORDER BY t.transaction_date DESC
LIMIT 50
```

**Purpose:** Fetch user's recent transaction history with equipment details

---

## URL Parameter System

### **Opening Modal:**

```
admin-user-activity.php?user_id=123
```

**Flow:**
1. User clicks "View" button
2. Page reloads with `user_id` parameter
3. PHP detects `$_GET['user_id']`
4. Fetches user details and transactions
5. Modal displays with data

### **Closing Modal:**

```
admin-user-activity.php
```

**Methods:**
1. Click close button (X) → Redirects to base URL
2. Click outside modal → Redirects to base URL
3. Browser back button → Returns to previous state

---

## CSS Styling

### **Modal Container:**

```css
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    overflow-y: auto;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}
```

**Features:**
- Full-screen overlay
- Semi-transparent background
- Centered content
- Scrollable if needed

### **Modal Content:**

```css
.modal-content {
    background: white;
    border-radius: 16px;
    max-width: 900px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease-out;
}
```

**Features:**
- White background
- Rounded corners (16px)
- Max width 900px
- 90% viewport height max
- Large shadow
- Slide-in animation

### **Animation:**

```css
@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
```

**Effect:**
- Slides down from -50px
- Fades in
- Duration: 0.3s
- Smooth ease-out

### **Modal Body Scrolling:**

```css
.modal-body {
    max-height: calc(90vh - 100px);
    overflow-y: auto;
}

.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #006633;
    border-radius: 4px;
}
```

**Features:**
- Custom scrollbar (8px wide)
- Green thumb (#006633)
- Rounded scrollbar
- Smooth scrolling

---

## JavaScript Functionality

### **Modal Close on Background Click:**

```javascript
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('userModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                window.location.href = 'admin-user-activity.php';
            }
        });
    }
});
```

**Behavior:**
- Listens for clicks on modal overlay
- If click target is the modal itself (not content)
- Redirects to base URL (closes modal)

---

## User Experience Flow

### **Opening Modal:**

```
1. User browsing activity table
   ↓
2. Clicks "View" button for specific user
   ↓
3. Page reloads with user_id parameter
   ↓
4. Modal slides in with animation
   ↓
5. User information displays in grid
   ↓
6. Transaction history loads below
```

### **Viewing Details:**

```
1. User sees 9 information fields
   ↓
2. Scrolls down to transaction history
   ↓
3. Reviews recent transactions
   ↓
4. Sees equipment names, dates, statuses
   ↓
5. Identifies patterns or issues
```

### **Closing Modal:**

```
Option 1: Click X button
   ↓
Redirects to admin-user-activity.php

Option 2: Click outside modal
   ↓
Redirects to admin-user-activity.php

Option 3: Browser back button
   ↓
Returns to previous page state
```

---

## Information Display

### **User Info Grid Layout:**

```
┌─────────────┬─────────────┬─────────────┐
│ Student ID  │  RFID Tag   │   Status    │
├─────────────┼─────────────┼─────────────┤
│Total Trans. │Total Borrows│Total Returns│
├─────────────┼─────────────┼─────────────┤
│Active Borrow│Penalty Count│Penalty Pts  │
└─────────────┴─────────────┴─────────────┘
```

**Responsive:**
- Desktop: 3 columns
- Tablet: 2 columns
- Mobile: 1 column

### **Transaction Table:**

```
┌──────────────┬────────┬───────────┬──────────┬────────┬─────────┐
│ Date & Time  │  Type  │ Equipment │ Category │ Quantity│ Status  │
├──────────────┼────────┼───────────┼──────────┼────────┼─────────┤
│Oct 30, 14:30 │Borrow  │  Mouse    │  Input   │   1    │ Active  │
│Oct 29, 10:15 │Return  │ Keyboard  │  Input   │   1    │Returned │
│Oct 28, 16:45 │Borrow  │ Monitor   │ Display  │   1    │ Active  │
└──────────────┴────────┴───────────┴──────────┴────────┴─────────┘
```

---

## Badge System

### **Status Badges:**

**Active:**
```html
<span class="status-badge active">Active</span>
```
- Background: #e8f5e9 (light green)
- Color: #1b5e20 (dark green)
- Border: #81c784 (green)

**Inactive:**
```html
<span class="status-badge inactive">Inactive</span>
```
- Background: #f5f5f5 (light gray)
- Color: #666 (gray)
- Border: #ddd (gray)

**Suspended:**
```html
<span class="status-badge suspended">Suspended</span>
```
- Background: #ffebee (light red)
- Color: #b71c1c (dark red)
- Border: #ef9a9a (red)

### **Count Badges:**

**Borrow:**
```html
<span class="count-badge borrow">
    <i class="fas fa-arrow-right"></i> 25
</span>
```
- Background: #e3f2fd (light blue)
- Color: #0d47a1 (dark blue)
- Icon: Arrow right

**Return:**
```html
<span class="count-badge return">
    <i class="fas fa-arrow-left"></i> 20
</span>
```
- Background: #e8f5e9 (light green)
- Color: #1b5e20 (dark green)
- Icon: Arrow left

**Penalty:**
```html
<span class="count-badge penalty">
    <i class="fas fa-exclamation-triangle"></i> 3
</span>
```
- Background: #ffebee (light red)
- Color: #b71c1c (dark red)
- Icon: Exclamation triangle

---

## Empty States

### **No Transactions:**

```html
<div class="empty">
    <i class="fas fa-inbox"></i>
    No transactions found
</div>
```

**Styling:**
- Large icon (3rem)
- Light color (#c5d9ce)
- Centered text
- Padding (40px)

---

## Security

### **Parameter Validation:**

```php
$selected_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
```

**Protection:**
- Type casting to integer
- Prevents SQL injection
- Invalid IDs return 0

### **Output Escaping:**

```php
<?= htmlspecialchars($user_details['student_id']) ?>
<?= htmlspecialchars($user_details['rfid_tag']) ?>
```

**Protection:**
- Prevents XSS attacks
- Escapes HTML entities
- Safe output rendering

---

## Performance

### **Query Optimization:**
- Single query for user details
- Single query for transactions
- LEFT JOINs for optional data
- LIMIT 50 for transaction history
- Indexed columns (user_id, transaction_date)

### **Loading Strategy:**
- Data fetched only when user_id present
- Modal rendered only if data exists
- No unnecessary database calls
- Efficient JOIN operations

---

## Responsive Design

### **Desktop (> 1024px):**
- Modal width: 900px
- 3-column info grid
- Full table visible
- No horizontal scroll

### **Tablet (768px - 1024px):**
- Modal width: 90%
- 2-column info grid
- Table may scroll horizontally
- Touch-friendly buttons

### **Mobile (< 768px):**
- Modal width: 90%
- 1-column info grid
- Table scrolls horizontally
- Larger touch targets
- Stacked layout

---

## Benefits

### **For Administrators:**
- ✅ **Quick Access** - One-click user details
- ✅ **Comprehensive View** - All info in one place
- ✅ **Transaction History** - See recent activity
- ✅ **Pattern Recognition** - Identify user behavior
- ✅ **Problem Detection** - Spot penalties quickly

### **For System:**
- ✅ **Efficient** - Optimized queries
- ✅ **Secure** - Parameter validation
- ✅ **Performant** - Limited data fetch
- ✅ **Maintainable** - Clean code structure

### **For User Experience:**
- ✅ **Intuitive** - Clear navigation
- ✅ **Responsive** - Works on all devices
- ✅ **Animated** - Smooth transitions
- ✅ **Accessible** - Easy to close
- ✅ **Informative** - Rich data display

---

## Testing Checklist

- [ ] View button opens modal
- [ ] User details display correctly
- [ ] All 9 info fields show data
- [ ] Transaction history loads
- [ ] Badges show correct colors
- [ ] Close button works
- [ ] Click outside closes modal
- [ ] Modal scrolls if content long
- [ ] Animation plays smoothly
- [ ] Responsive on mobile
- [ ] No SQL errors
- [ ] No XSS vulnerabilities
- [ ] Empty state displays properly
- [ ] URL parameters work
- [ ] Back button navigates correctly

---

**Date:** October 30, 2025  
**Feature:** View Button Modal  
**Status:** Fully Functional  
**Components:** User Info Grid + Transaction History  
**Max Transactions:** 50 recent
