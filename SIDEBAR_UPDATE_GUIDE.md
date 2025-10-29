# Sidebar Navigation Update Guide

## Overview
The admin panel has been reorganized with a new hierarchical sidebar navigation structure.

## New Structure

### 🟩 Dashboard
- **admin-dashboard.php** - Overview of all statistics

### 📦 Equipment Management
- **admin-equipment-inventory.php** - Equipment Inventory (Add, edit, remove equipment)
- **admin-maintenance-tracker.php** - Maintenance Tracker (Track damaged/under-repair equipment)
- **admin-authorized-users.php** - Authorized Users Management (Pre-register RFID/barcode IDs)

### 🔁 Transactions
- **admin-all-transaction.php** - All Transactions (View all borrowing and return logs)
- **admin-return-verification.php** - Return Verification (Review returned items using image comparison)

### ⚖️ Penalty Management
- **admin-penalty-guideline.php** - Penalty Guidelines (Define late, lost, or damage penalty rates)
- **admin-penalty-management.php** - Penalty Records (View, approve, or clear penalties)

### 🏪 Kiosk Monitoring
- **admin-kiosk-status.php** - Kiosk Status (Monitor online/offline state)
- **admin-kiosk-logs.php** - Kiosk Logs (View recent kiosk transactions)

### 📊 Reports
- **reports.php** - Transaction Reports (Borrow/Return Summary)
- **admin-user-activity.php** - System Activity Log

### 🔔 Notifications
- **admin-notifications.php** - Manage announcements and alerts

### ⚙️ System Settings
- **admin-settings.php** - Admin accounts, database backup, configurations

## Implementation

### New Files Created
1. **admin/includes/sidebar.php** - Centralized sidebar component with hierarchical navigation
2. **admin/admin-maintenance-tracker.php** - New page for equipment maintenance tracking
3. **admin/admin-authorized-users.php** - New page for authorized user management
4. **admin/admin-return-verification.php** - New page for return verification (redirects to transactions)
5. **admin/admin-kiosk-status.php** - New page for kiosk status monitoring
6. **admin/admin-kiosk-logs.php** - New page for kiosk activity logs
7. **admin/admin-notifications.php** - New page for notifications management
8. **admin/admin-settings.php** - New page for system settings

### How to Update Existing Pages

To update any existing admin page to use the new sidebar:

1. **Remove old sidebar HTML** - Delete the existing `<nav class="sidebar">` section
2. **Include new sidebar** - Add this line where the sidebar should appear:
   ```php
   <?php include 'includes/sidebar.php'; ?>
   ```
3. **Ensure proper container structure**:
   ```html
   <div class="admin-container">
       <?php include 'includes/sidebar.php'; ?>
       <main class="main-content">
           <!-- Your page content here -->
       </main>
   </div>
   ```

### CSS Requirements

The new sidebar includes its own styles. Ensure your page has:
- `.admin-container` with `display: flex`
- `.main-content` with `margin-left: 260px` and `flex: 1`
- `.admin-container.sidebar-hidden .main-content` with `margin-left: 0`

### JavaScript Features

The sidebar includes:
- **Submenu toggle** - Click parent items to expand/collapse submenus
- **Auto-open active submenu** - Active page's submenu opens automatically
- **Mobile responsive** - Sidebar collapses on mobile devices
- **Logout function** - Integrated logout with confirmation

## Pages That Need Manual Update

The following existing pages need to be updated to use the new sidebar:

1. ✅ admin-dashboard.php
2. ✅ admin-equipment-inventory.php
3. ✅ admin-all-transaction.php
4. ✅ reports.php
5. ✅ admin-user-activity.php
6. ✅ admin-penalty-guideline.php
7. ✅ admin-penalty-management.php

## Color Coding

Each menu item has a unique color for easy identification:
- 🟩 Dashboard - Green (#4caf50)
- 📦 Equipment - Orange (#ff9800)
- 🔁 Transactions - Blue (#2196f3)
- ⚖️ Penalties - Red (#f44336)
- 🏪 Kiosk - Purple (#9c27b0)
- 📊 Reports - Cyan (#00bcd4)
- 🔔 Notifications - Deep Orange (#ff5722)
- ⚙️ Settings - Blue Grey (#607d8b)

## Testing Checklist

- [ ] All menu items are clickable
- [ ] Active page is highlighted correctly
- [ ] Submenus expand/collapse properly
- [ ] Active submenu auto-opens on page load
- [ ] Sidebar toggle works on mobile
- [ ] Logout function works with confirmation
- [ ] All links point to correct pages
- [ ] Page layout adjusts when sidebar is hidden

## Notes

- The sidebar is fixed position and scrollable
- Submenu items are indented and styled differently
- Icons use Font Awesome 6.0.0
- The sidebar includes smooth transitions and hover effects
- All placeholder pages show "Coming Soon" messages with appropriate icons
