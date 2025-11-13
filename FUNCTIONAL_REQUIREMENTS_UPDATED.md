# Equipment Management System - Functional Requirements (Updated)

## Document Information
- **Project:** Equipment Management System - De La Salle ASMC
- **Version:** 2.0 (Phase 2 Complete)
- **Date:** October 30, 2025
- **Status:** Updated with Phase 2 Features

---

## Table of Contents
1. [Admin Panel Functional Requirements](#admin-panel-functional-requirements)
2. [User/Kiosk Functional Requirements](#userkiosk-functional-requirements)
3. [System-Wide Functional Requirements](#system-wide-functional-requirements)

---

## Admin Panel Functional Requirements

### **Use Case No. 001 – LOG IN**
**Description:** Allow admins to log into the system

**Functional Requirements:**
- Admin can enter username and password
- System validates credentials against admin_users table
- Session is created upon successful login
- Failed login attempts are tracked
- Session timeout after 30 minutes of inactivity (configurable)
- Secure session management with httponly cookies
- Password hashing for security

**Related Files:**
- `admin/login.php`
- `admin/logout.php`

---

### **Use Case No. 002 – Manage Equipment Inventory**
**Description:** Allow admins to add, view, update, and remove equipment items in the inventory through the admin panel

**Functional Requirements:**
- **Add Equipment:**
  - Input: Name, RFID Tag, Category, Quantity, Description, Image
  - Validation: Unique RFID tag, required fields
  - Image upload support
  
- **View Equipment:**
  - Display all equipment in table format
  - Search and filter by category, name, RFID
  - Pagination (20 items per page)
  - View equipment details
  
- **Update Equipment:**
  - Edit all equipment fields
  - Update quantity
  - Change equipment status
  - Update images
  
- **Delete Equipment:**
  - Soft delete (mark as inactive)
  - Confirmation required
  - Prevent deletion if active borrows exist

**Related Files:**
- `admin/admin-equipment-inventory.php`
- `admin/add_equipment.php`

---

### **Use Case No. 003 – Manage Categories**
**Description:** Allow the admin to create, update, and remove equipment categories for better classification

**Functional Requirements:**
- Create new categories
- Edit category names and descriptions
- Delete unused categories
- Assign equipment to categories
- View equipment count per category
- Category-based filtering

**Related Files:**
- `admin/admin-equipment-inventory.php` (category management section)

---

### **Use Case No. 004 – Manage Transactions**
**Description:** Allow the admin to view and manage borrowing and returning records, including updating statuses

**Functional Requirements:**
- **View All Transactions:**
  - Display transaction history
  - Filter by type (Borrow/Return)
  - Filter by status (Active/Returned/Pending Review)
  - Filter by date range
  - Search by student ID, equipment name
  - Pagination support
  
- **Transaction Details:**
  - View complete transaction information
  - See associated user and equipment details
  - View transaction photos (borrow & return)
  - Check return verification status
  
- **Update Transaction Status:**
  - Mark as returned
  - Update verification status
  - Add admin notes
  - Process penalties if applicable

**Related Files:**
- `admin/admin-all-transaction.php`
- `admin/transaction-details.php`

---

### **Use Case No. 005 – Generate Reports**
**Description:** Allow the admin to generate printable or downloadable reports on equipment usage, status, and user transactions

**Functional Requirements:**
- **Report Types:**
  - Transaction Reports (Daily/Weekly/Monthly/Yearly)
  - Equipment Usage Reports
  - User Activity Reports
  - Penalty Reports
  
- **Export Formats:**
  - Print Report (PDF-ready)
  - Export to CSV
  - Export to Excel (.xls)
  
- **Report Filters:**
  - Date range selection
  - Equipment category
  - Transaction type
  - User status
  
- **Report Contents:**
  - Summary statistics
  - Detailed transaction data
  - Equipment details
  - User information
  - Metadata (generation date, admin name)

**Related Files:**
- `admin/reports.php`
- `admin/export_penalties_csv.php`
- `admin/export_penalty_guidelines_pdf.php`

---

### **Use Case No. 006 – View Borrowing and Returning History**
**Description:** Allow the admin to review a historical list of all borrowing and returning events, including timestamps and user IDs

**Functional Requirements:**
- View complete transaction history
- Filter by date range
- Filter by user
- Filter by equipment
- Sort by date, user, equipment
- Export history to CSV/Excel
- View transaction timeline
- See return verification status

**Related Files:**
- `admin/admin-all-transaction.php`
- `admin/admin-user-activity.php`

---

### **Use Case No. 007 – LOGOUT**
**Description:** Allow the admin to securely log out of the admin panel and end their session

**Functional Requirements:**
- Logout button in sidebar
- Confirmation dialog
- Complete session destruction
- Clear session variables
- Remove session cookie
- Clear client-side storage (localStorage, sessionStorage)
- Redirect to login page
- Prevent back-button access after logout

**Related Files:**
- `admin/logout.php`
- `admin/includes/sidebar.php`

---

### **Use Case No. 008 – View Dashboard Statistics**
**Description:** Allow admin to view comprehensive system statistics and recent activities

**Functional Requirements:**
- **Statistics Cards (8 total):**
  - Total Equipment Items
  - Currently Borrowed (Active)
  - Total Returns
  - Active Violations
  - Total Users
  - Pending Penalties
  - Overdue Items
  - Available Equipment
  
- **Recent Activities:**
  - Last 10 transactions with details
  - Recent penalties (last 5)
  - Low stock alerts (quantity ≤ 5)
  - Top 5 borrowed items this month
  
- **Visualizations:**
  - Top borrowed item panel with image
  - Daily usage chart (Chart.js)
  - Progress bars for top borrowed items
  
- **Real-time Updates:**
  - Auto-refresh capability
  - Color-coded status indicators
  - Empty state messages

**Related Files:**
- `admin/admin-dashboard.php`

---

### **Use Case No. 009 – Manage Authorized Users**
**Description:** Allow admin to manage users authorized to borrow equipment

**Functional Requirements:**
- View all registered users
- Add new users (Student ID, RFID, Name, Email)
- Edit user information
- Activate/Deactivate user accounts
- View user borrowing history
- Check user penalty status
- Search and filter users
- Export user list

**Related Files:**
- `admin/admin-authorized-users.php`

---

### **Use Case No. 010 – Return Verification**
**Description:** Allow admin to verify returned equipment and assess condition

**Functional Requirements:**
- **View Pending Returns:**
  - List of items awaiting verification
  - Filter by status (Pending/Verified/Flagged/Rejected)
  - Search by student ID or equipment
  
- **Verification Actions:**
  - Verify return (approve)
  - Flag for review (issues detected)
  - Reject return (major issues)
  
- **Image Comparison:**
  - Side-by-side comparison (borrow vs return photos)
  - AI-powered damage detection
  - Similarity score calculation
  - Detected issues highlighting
  - Severity level assessment
  
- **Admin Assessment:**
  - Add verification notes
  - Document detected issues
  - Record condition assessment
  - Trigger penalty if needed

**Related Files:**
- `admin/admin-return-verification.php`
- `admin/return-verification.php` (handler)
- `includes/image_comparison.php`

---

### **Use Case No. 011 – Penalty Management**
**Description:** Allow admin to manage penalties and penalty guidelines

**Functional Requirements:**
- **Penalty Guidelines:**
  - Create penalty guidelines
  - Edit guidelines (type, amount, points, description)
  - Upload supporting documents
  - Archive old guidelines
  - View/Print/Export guidelines
  
- **Penalty Records:**
  - View all penalties
  - Filter by status (Pending/Paid/Waived)
  - Filter by type (Late Return/Damage/Loss)
  - Search by student ID or equipment
  - Issue new penalties
  - Update penalty status
  - Record payments
  - Waive penalties (with reason)
  - Export penalty reports
  
- **Penalty Calculation:**
  - Automatic late fee calculation
  - Damage assessment based on severity
  - Loss penalty (replacement cost)
  - Penalty points tracking

**Related Files:**
- `admin/admin-penalty-guideline.php`
- `admin/admin-penalty-management.php`
- `admin/penalty-system.php`

---

### **Use Case No. 012 – Maintenance Tracker**
**Description:** Allow admin to track equipment maintenance schedules and history

**Functional Requirements:**
- View equipment maintenance status
- Schedule maintenance
- Record maintenance activities
- Track maintenance history
- Set maintenance reminders
- Mark equipment as under maintenance
- Update equipment status after maintenance
- Generate maintenance reports

**Related Files:**
- `admin/admin-maintenance-tracker.php`

---

### **Use Case No. 013 – Kiosk Monitoring**
**Description:** Allow admin to monitor kiosk status and activity logs

**Functional Requirements:**
- **Kiosk Status:**
  - View all kiosks
  - Check online/offline status
  - View last activity timestamp
  - Monitor kiosk health
  
- **Kiosk Logs:**
  - View kiosk activity logs
  - Filter by kiosk, date, action type
  - Search logs
  - Export logs
  - Track user interactions

**Related Files:**
- `admin/admin-kiosk-status.php`
- `admin/admin-kiosk-logs.php`

---

### **Use Case No. 014 – User Activity Tracking**
**Description:** Allow admin to view comprehensive user activity and statistics

**Functional Requirements:**
- **User Statistics:**
  - Total users, active users
  - Total transactions, borrows, returns
  - Currently borrowed items
  - Penalty counts
  
- **User Activity Table:**
  - Student ID, RFID Tag
  - Status (Active/Inactive/Suspended)
  - Transaction counts
  - Borrow/Return counts
  - Active borrows
  - Penalty information
  - Last activity timestamp
  
- **User Details Modal:**
  - Complete user information
  - Transaction history (last 50)
  - Penalty details
  - Borrowing statistics
  
- **Filters:**
  - Search by Student ID/RFID
  - Filter by status
  - Filter by period (All Time/Today/Last 7 Days/Last 30 Days)
  - Pagination (20 per page)

**Related Files:**
- `admin/admin-user-activity.php`

---

### **Use Case No. 015 – Notifications Management**
**Description:** Allow admin to view and manage system notifications

**Functional Requirements:**
- **View Notifications:**
  - Display all notifications
  - Filter by type (Info/Warning/Success/Error)
  - Filter by status (Unread/Read/Archived)
  - Search notifications
  - Pagination (15 per page)
  
- **Notification Actions:**
  - Mark as read
  - Archive notifications
  - Delete notifications
  
- **Notification Types:**
  - Equipment overdue alerts
  - Penalty issued notifications
  - Return verified notifications
  - Damaged equipment reports
  - Lost equipment alerts
  - Low stock warnings
  - Maintenance due reminders
  
- **Statistics:**
  - Total notifications
  - Unread count
  - Read count
  - Archived count

**Related Files:**
- `admin/admin-notifications.php`
- `admin/update_notification.php`
- `admin/delete_notification.php`
- `includes/notification_helper.php`

---

### **Use Case No. 016 – System Settings**
**Description:** Allow admin to configure system-wide settings and preferences

**Functional Requirements:**
- **General Settings:**
  - System name
  - Institution name
  - Contact email
  - Max borrow days
  - Overdue penalty rate (₱/day)
  - Max items per borrow
  
- **System Configuration:**
  - Enable/Disable notifications
  - Enable/Disable email alerts
  - Maintenance mode toggle
  - Session timeout (minutes)
  - Items per page (pagination)
  
- **Database Information:**
  - Database size (MB)
  - Total tables count
  - Admin users count
  - Backup database option
  - Clear cache option
  
- **Settings Management:**
  - Save settings via AJAX
  - Validation for all inputs
  - Default values provided
  - Settings persistence in database

**Related Files:**
- `admin/admin-settings.php`
- `admin/save_settings.php`

---

## User/Kiosk Functional Requirements

### **Use Case No. 017 – View Available Equipment**
**Description:** Allow kiosk users to browse a list of all available equipment for borrowing

**Functional Requirements:**
- Display equipment catalog
- Show availability status
- Filter by category
- Search by name or RFID
- View equipment details
- See equipment images
- Check quantity available
- View borrowing terms

**Related Files:**
- `user/borrow.php`

---

### **Use Case No. 018 – Borrow Equipment**
**Description:** Allow kiosk users to borrow equipment by scanning RFID tag and confirming their ID and selection

**Functional Requirements:**
- RFID tag scanning
- User authentication via RFID/Student ID
- Equipment selection
- Quantity selection
- Expected return date display
- Photo capture (optional)
- Confirmation screen
- Transaction receipt
- Real-time inventory update

**Related Files:**
- `user/borrow.php`

---

### **Use Case No. 019 – Return Equipment**
**Description:** Allow kiosk users to return borrowed items by scanning the equipment tag, confirming return, and optionally noting condition

**Functional Requirements:**
- RFID tag scanning
- User authentication
- View borrowed items
- Select items to return
- Photo capture (required for verification)
- Condition notes (optional)
- Return confirmation
- Transaction update
- Trigger verification workflow

**Related Files:**
- `user/return.php`
- `user/borrow-return.php`

---

### **Use Case No. 020 – View Borrowing History**
**Description:** Allow users to view their personal borrowing and return history

**Functional Requirements:**
- Display user's transaction history
- Show current borrows
- Show past returns
- Display due dates
- Show penalty information
- Filter by date range
- Search transactions

**Related Files:**
- `user/borrow-return.php`

---

## System-Wide Functional Requirements

### **Use Case No. 021 – Automatic Notifications**
**Description:** System automatically creates notifications for key events

**Functional Requirements:**
- **Automatic Triggers:**
  - Penalty issued → Warning notification
  - Return verified → Success notification
  - Equipment damaged → Error notification
  - Equipment lost → Error notification
  - Equipment overdue → Warning notification (planned)
  - Low stock → Warning notification (planned)
  - Maintenance due → Warning notification (planned)
  
- **Notification Integration:**
  - Created via helper functions
  - Stored in notifications table
  - Displayed in admin notifications page
  - Real-time updates
  - Persistent storage

**Related Files:**
- `includes/notification_helper.php`
- `admin/penalty-system.php` (integrated)
- `admin/return-verification.php` (integrated)

---

### **Use Case No. 022 – Image Comparison & Damage Detection**
**Description:** e

**Functional Requirements:**
- Side-by-side image comparison
- Similarity score calculation
- Damage detection algorithms
- Severity level assessment (None/Minor/Moderate/Severe)
- Detected issues text generation
- Visual difference highlighting
- Admin review integration
- Automatic penalty triggering (if severe)

**Related Files:**
- `includes/image_comparison.php`
- `admin/return-verification.php`

---

### **Use Case No. 023 – Session Management**
**Description:** Secure session handling across the system

**Functional Requirements:**
- Secure session initialization
- Session ID regeneration
- Session timeout enforcement
- Httponly cookie flags
- Session validation on each request
- Automatic logout on timeout
- Session cleanup on logout
- CSRF protection

**Related Files:**
- All admin PHP files (session checks)
- `admin/logout.php`

---

### **Use Case No. 024 – Database Backup & Maintenance**
**Description:** System provides database management capabilities

**Functional Requirements:**
- View database statistics
- Database size monitoring
- Table count tracking
- Backup database option
- Cache clearing
- Database optimization
- Data integrity checks

**Related Files:**
- `admin/admin-settings.php` (Database tab)

---

### **Use Case No. 025 – Responsive Design**
**Description:** System works across all device types

**Functional Requirements:**
- Desktop optimization (1920x1080+)
- Tablet support (768px-1024px)
- Mobile support (320px-767px)
- Touch-friendly interfaces
- Responsive navigation
- Adaptive layouts
- Mobile-first approach

**Related Files:**
- All admin and user interface files
- `admin/includes/sidebar.php`
- CSS files

---

## Technical Requirements

### **Database Tables:**
1. `admin_users` - Admin authentication
2. `users` - Registered borrowers
3. `equipment` - Equipment inventory
4. `categories` - Equipment categories
5. `transactions` - Borrow/Return records
6. `transaction_photos` - Transaction images
7. `penalties` - Penalty records
8. `penalty_guidelines` - Penalty rules
9. `penalty_damage_assessments` - Damage assessments
10. `notifications` - System notifications
11. `system_settings` - Configuration
12. `kiosk_logs` - Kiosk activity (if applicable)

### **Security Requirements:**
- Password hashing (bcrypt/argon2)
- SQL injection prevention (prepared statements)
- XSS prevention (htmlspecialchars)
- CSRF protection
- Session security
- Input validation
- File upload validation
- Access control (admin-only pages)

### **Performance Requirements:**
- Page load time < 2 seconds
- Database query optimization
- Image optimization
- Caching strategy
- Pagination for large datasets
- Lazy loading for images
- Minified CSS/JS (production)

### **Browser Compatibility:**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

---

## Summary of Use Cases

### **Admin Panel (16 Use Cases):**
1. LOG IN
2. Manage Equipment Inventory
3. Manage Categories
4. Manage Transactions
5. Generate Reports
6. View Borrowing and Returning History
7. LOGOUT
8. View Dashboard Statistics
9. Manage Authorized Users
10. Return Verification
11. Penalty Management
12. Maintenance Tracker
13. Kiosk Monitoring
14. User Activity Tracking
15. Notifications Management
16. System Settings

### **User/Kiosk (4 Use Cases):**
17. View Available Equipment
18. Borrow Equipment
19. Return Equipment
20. View Borrowing History

### **System-Wide (5 Use Cases):**
21. Automatic Notifications
22. Image Comparison & Damage Detection
23. Session Management
24. Database Backup & Maintenance
25. Responsive Design

**Total: 25 Functional Use Cases**

---

## Document History

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 1.0 | Initial | Original requirements | Development Team |
| 2.0 | Oct 30, 2025 | Phase 2 features added | Development Team |

---

## Appendix: Feature Implementation Status

### ✅ Fully Implemented:
- All 16 Admin Panel use cases
- All 4 User/Kiosk use cases
- All 5 System-wide use cases
- Dashboard with 8 statistics
- Notifications system
- Settings management
- Return verification with image comparison
- Penalty management
- User activity tracking
- Sidebar optimization
- Logout functionality

### 🔄 Partially Implemented:
- Email notifications (system ready, SMTP not configured)
- Automatic overdue notifications (helper function ready, cron job needed)
- Database backup (UI ready, export script needed)

### 📋 Planned Enhancements:
- Mobile app integration
- QR code scanning
- Email alerts
- SMS notifications
- Advanced analytics
- Predictive maintenance
- AI-powered recommendations

---

**Document Status:** ✅ Complete and Up-to-Date  
**Last Updated:** October 30, 2025  
**Phase:** 2 (Complete)  
**Total Use Cases:** 25
