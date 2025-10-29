# 🔧 Maintenance Tracker ↔️ Inventory Integration

## 📋 Overview

The Maintenance Tracker now **automatically reserves inventory** when equipment enters maintenance, preventing borrowing conflicts and ensuring accurate availability tracking.

---

## 🎯 How It Works

### **Automatic Inventory Blocking**

When you create or update a maintenance log:

1. **Status: Pending or In Progress**
   - Equipment units are **reserved** from available inventory
   - `inventory.maintenance_quantity` increases
   - `inventory.available_quantity` decreases
   - `inventory.availability_status` updates (Not Available / Partially Available / Low Stock)

2. **Status: Completed or Cancelled**
   - Equipment units are **released** back to available inventory
   - `inventory.maintenance_quantity` decreases
   - `inventory.available_quantity` increases
   - `inventory.availability_status` recalculates

### **Borrowing Side Impact**

- **User Kiosk**: Items under maintenance are **hidden** from the borrowing interface
- **Available Quantity**: Automatically excludes maintenance reservations
- **Real-time Updates**: Changes reflect immediately across all pages

---

## 🗄️ Database Changes

### **New Columns**

#### `inventory` table:
```sql
maintenance_quantity INT NOT NULL DEFAULT 0
```
- Tracks total units currently reserved for maintenance
- Updated automatically by maintenance API

#### `maintenance_logs` table:
```sql
maintenance_quantity INT NOT NULL DEFAULT 1
```
- Records how many units this specific log reserves
- Defaults to 1 unit per maintenance record

### **Availability Calculation**

**Old Formula:**
```
available = quantity - borrowed_quantity - damaged_quantity
```

**New Formula:**
```
available = quantity - borrowed_quantity - damaged_quantity - maintenance_quantity
```

### **Status Logic**

```
IF available <= 0 THEN 'Not Available'
ELSE IF available <= minimum_stock_level THEN 'Low Stock'
ELSE IF maintenance_quantity > 0 THEN 'Partially Available'
ELSE 'Available'
```

---

## 🚀 Setup Instructions

### **Step 1: Run Database Migrations**

Execute these SQL files in **phpMyAdmin** (in order):

1. **Add maintenance_quantity to inventory:**
   ```sql
   -- File: database/migrations/20251028_add_maintenance_quantity.sql
   ```

2. **Add maintenance_quantity to maintenance_logs:**
   ```sql
   -- File: database/migrations/20251028_add_maintenance_quantity_to_logs.sql
   ```

3. **Update availability status ENUM:**
   ```sql
   -- File: database/migrations/20251028_update_availability_status.sql
   ```

### **Step 2: Verify Integration**

1. Navigate to **Maintenance Tracker**
2. Create a new maintenance log for any equipment
3. Check **Equipment Inventory** page
4. Verify the equipment card shows:
   - ✅ Reduced available quantity
   - ✅ Maintenance badge with count
   - ✅ Updated availability status

---

## 📊 Equipment Inventory Display

### **Card Layout**

Equipment cards now show a **breakdown** of quantities:

```
┌─────────────────────────────┐
│  [Equipment Image]          │
├─────────────────────────────┤
│  #123                       │
│  Laptop                     │
│  📏 Medium Item             │
│                             │
│  Quantity: 10               │
│  Available: 5 ✓ Available  │
│                             │
│  🤝 3 Borrowed              │
│  🔧 2 Maintenance           │
│  ⚠️ 0 Damaged               │
└─────────────────────────────┘
```

### **Badge Colors**

- **🤝 Borrowed**: Orange (`#fff3e0`)
- **🔧 Maintenance**: Blue (`#e3f2fd`)
- **⚠️ Damaged**: Red (`#ffebee`)

---

## 🔄 Workflow Examples

### **Example 1: Single Unit Repair**

**Initial State:**
```
Equipment: Keyboard
Total Quantity: 5
Available: 5
Borrowed: 0
Maintenance: 0
Status: Available
```

**Create Maintenance Log:**
```
Type: Repair
Severity: Medium
Status: Pending
Quantity: 1 (default)
```

**Result:**
```
Total Quantity: 5
Available: 4
Borrowed: 0
Maintenance: 1 ← Reserved
Status: Partially Available
```

**Complete Maintenance:**
```
Status: Completed
After Condition: Excellent
```

**Final State:**
```
Total Quantity: 5
Available: 5 ← Released
Borrowed: 0
Maintenance: 0
Status: Available
```

### **Example 2: Multiple Units Under Maintenance**

**Initial State:**
```
Equipment: Mouse
Total Quantity: 10
Available: 7
Borrowed: 3
Maintenance: 0
```

**Create Maintenance Log:**
```
Type: Preventive
Status: In Progress
Quantity: 2
```

**Result:**
```
Total Quantity: 10
Available: 5 (10 - 3 - 2)
Borrowed: 3
Maintenance: 2 ← Reserved
Status: Partially Available
```

**User Tries to Borrow:**
- Kiosk shows: **5 available** (not 7)
- System prevents over-borrowing

---

## 🎨 Visual Indicators

### **Equipment Inventory Page**

- **Maintenance Badge**: Blue badge with wrench icon
- **Count Display**: Shows exact number of units in maintenance
- **Availability Status**: Auto-updates based on reservations

### **Maintenance Tracker Page**

- **Equipment Dropdown**: Shows `available_for_maintenance` count
- **Validation**: Prevents reserving more units than available
- **Error Messages**: Clear feedback when stock insufficient

---

## 🔒 Business Rules

### **Reservation Rules**

1. ✅ Can reserve units when status is **Pending** or **In Progress**
2. ✅ Cannot reserve more units than currently available
3. ✅ Reservations release when status changes to **Completed** or **Cancelled**
4. ✅ Deleting a maintenance log releases its reservations

### **Validation**

- **Create**: Checks `available_quantity` before reserving
- **Update**: Validates delta changes (increase/decrease)
- **Delete**: Automatically releases reserved units
- **Concurrent Access**: Row-level locking prevents race conditions

### **Edge Cases Handled**

- ✅ Missing inventory records (auto-created from equipment table)
- ✅ Status transitions (Pending → In Progress → Completed)
- ✅ Quantity adjustments (increase/decrease maintenance units)
- ✅ Cancellations (releases reservations immediately)

---

## 🛡️ API Changes

### **Enhanced Endpoints**

#### `get_equipment_list`
**New Response Fields:**
```json
{
  "id": "EQ001",
  "name": "Laptop",
  "quantity": 10,
  "available_quantity": 5,
  "borrowed_quantity": 3,
  "damaged_quantity": 0,
  "maintenance_quantity": 2,
  "available_for_maintenance": 5,
  "availability_status": "Partially Available"
}
```

#### `create` (Maintenance Log)
**New Request Field:**
```json
{
  "maintenance_quantity": 1
}
```

**Behavior:**
- Locks inventory row
- Validates available stock
- Reserves units
- Updates availability status
- Commits transaction atomically

#### `update` (Maintenance Log)
**Status Transitions:**
- **Pending → In Progress**: Maintains reservation
- **In Progress → Completed**: Releases reservation
- **Pending → Cancelled**: Releases reservation
- **Completed → Pending**: Re-reserves units (if available)

#### `delete` (Maintenance Log)
**Behavior:**
- Releases any active reservations
- Recalculates availability
- Updates status

---

## 📝 Helper Functions

### **Backend Utilities** (`api/maintenance_handler.php`)

```php
fetchInventoryRowForUpdate($conn, $equipment_id)
// Locks inventory row, creates if missing

calculateAvailabilityData($quantity, $borrowed, $damaged, $maintenance, $minStock)
// Computes available count and status

updateInventoryMaintenance($conn, $equipment_id, $maintenanceQty, $availableQty, $status)
// Persists reservation changes

calculateMaintenanceDelta($oldStatus, $newStatus, $oldQty, $newQty)
// Determines reservation delta for status transitions

statusRequiresReservation($status)
// Returns true for 'Pending' and 'In Progress'
```

---

## 🧪 Testing Checklist

- [ ] Create maintenance log → verify inventory decreases
- [ ] Complete maintenance → verify inventory increases
- [ ] Cancel maintenance → verify inventory releases
- [ ] Delete maintenance log → verify inventory releases
- [ ] Try borrowing reserved item → verify blocked
- [ ] Check equipment cards show maintenance badges
- [ ] Verify availability status updates correctly
- [ ] Test with multiple maintenance logs on same equipment
- [ ] Validate error when trying to reserve unavailable units
- [ ] Check concurrent maintenance log creation

---

## 🐛 Troubleshooting

### **Issue: Maintenance quantity not updating**
- ✅ Run both migration files
- ✅ Clear browser cache
- ✅ Check `inventory.maintenance_quantity` column exists
- ✅ Verify `maintenance_logs.maintenance_quantity` column exists

### **Issue: Items still borrowable during maintenance**
- ✅ Check maintenance log status (must be Pending or In Progress)
- ✅ Verify `user/borrow.php` uses updated availability query
- ✅ Refresh equipment list in kiosk

### **Issue: Available quantity incorrect**
- ✅ Run recalculation query from migration
- ✅ Check for orphaned maintenance logs
- ✅ Verify all quantity columns are non-negative

### **Issue: "Only X units available" error**
- ✅ Check current `available_quantity` in inventory
- ✅ Verify no other pending maintenance logs
- ✅ Ensure borrowed + damaged + maintenance < total quantity

---

## 📚 Related Documentation

- **Maintenance Tracker Guide**: `MAINTENANCE_TRACKER_GUIDE.md`
- **Database Schema**: `database/capstone_clean_import.sql`
- **API Reference**: `admin/api/maintenance_handler.php`
- **Frontend Code**: `admin/admin-equipment-inventory.php`

---

## 🎯 Summary

✅ **Automatic inventory blocking** when equipment enters maintenance  
✅ **Real-time availability updates** across all pages  
✅ **Visual indicators** with maintenance badges  
✅ **Prevents borrowing conflicts** by reserving units  
✅ **Atomic transactions** with row-level locking  
✅ **Comprehensive validation** and error handling  
✅ **Backward compatible** with existing inventory logic  

**Version**: 1.0.0  
**Last Updated**: October 28, 2025
