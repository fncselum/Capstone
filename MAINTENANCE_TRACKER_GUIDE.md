# 🔧 Maintenance Tracker - Complete Guide

## 📋 Overview

The **Maintenance Tracker** is a comprehensive system for tracking equipment maintenance, repairs, and service history. It helps administrators monitor equipment health, schedule preventive maintenance, and maintain detailed records of all maintenance activities.

---

## 🎯 Key Features

### 1. **Dashboard Statistics**
- Real-time overview of maintenance status
- **Pending**: Maintenance requests awaiting action
- **In Progress**: Currently being worked on
- **Completed**: Finished maintenance tasks
- **Total Records**: All maintenance logs in the system

### 2. **Maintenance Log Management**
- Create new maintenance logs
- Track equipment issues and repairs
- Assign technicians to tasks
- Update maintenance status
- Record resolution details
- Track costs and downtime

### 3. **Advanced Filtering & Search**
- Filter by status (Pending, In Progress, Completed, Cancelled)
- Filter by maintenance type (Repair, Preventive, Inspection, etc.)
- Search by equipment name, ID, or issue description
- Real-time search results

### 4. **Detailed Record Keeping**
- Equipment condition tracking (before/after)
- Resolution notes and documentation
- Parts replacement tracking
- Cost tracking
- Downtime monitoring
- Next maintenance scheduling

---

## 🗄️ Database Schema

### **maintenance_logs Table**

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key, auto-increment |
| `equipment_id` | VARCHAR(50) | Foreign key to equipment table |
| `equipment_name` | VARCHAR(255) | Equipment name for quick reference |
| `maintenance_type` | ENUM | Type: Repair, Preventive, Inspection, Cleaning, Calibration, Replacement |
| `issue_description` | TEXT | Detailed description of the issue |
| `severity` | ENUM | Low, Medium, High, Critical |
| `status` | ENUM | Pending, In Progress, Completed, Cancelled |
| `reported_by` | VARCHAR(100) | Admin who created the log |
| `reported_date` | DATETIME | When the issue was reported |
| `assigned_to` | VARCHAR(100) | Technician assigned to the task |
| `started_date` | DATETIME | When work started |
| `completed_date` | DATETIME | When work was completed |
| `resolution_notes` | TEXT | Details of what was done |
| `cost` | DECIMAL(10,2) | Cost of maintenance/repair |
| `parts_replaced` | TEXT | List of parts that were replaced |
| `downtime_hours` | DECIMAL(5,2) | Equipment downtime in hours |
| `before_condition` | ENUM | Equipment condition before maintenance |
| `after_condition` | ENUM | Equipment condition after maintenance |
| `next_maintenance_date` | DATE | Scheduled next maintenance |
| `created_at` | TIMESTAMP | Record creation timestamp |
| `updated_at` | TIMESTAMP | Last update timestamp |

---

## 🚀 Installation & Setup

### **Step 1: Run Database Migration**

Execute the SQL migration file in phpMyAdmin:

```sql
-- File: database/migrations/20251028_create_maintenance_logs.sql
```

This will:
- Create the `maintenance_logs` table
- Set up proper indexes and foreign keys
- Insert sample data for testing

### **Step 2: Verify File Structure**

Ensure these files exist:

```
admin/
├── admin-maintenance-tracker.php          # Main page
├── api/
│   └── maintenance_handler.php            # Backend API
├── assets/
│   ├── css/
│   │   └── maintenance-tracker.css        # Styles
│   └── js/
│       └── maintenance-tracker.js         # JavaScript
```

### **Step 3: Access the Page**

Navigate to: `http://localhost/Capstone/admin/admin-maintenance-tracker.php`

---

## 📖 How to Use

### **1. Creating a Maintenance Log**

1. Click the **"Add Maintenance Log"** button (green button, top right)
2. Fill in the form:
   - **Equipment**: Select from dropdown (shows equipment image preview)
   - **Maintenance Type**: Choose type (Repair, Preventive, etc.)
   - **Severity**: Select severity level (Low, Medium, High, Critical)
   - **Issue Description**: Describe the problem or maintenance needed
   - **Assigned To**: Enter technician name (optional)
   - **Before Condition**: Select equipment condition (optional)
3. Click **"Save Log"**

**Example Use Case:**
```
Equipment: Laptop (ID: 2)
Type: Repair
Severity: High
Issue: Screen flickering and battery not charging
Assigned To: Tech Team A
Before Condition: Fair
```

### **2. Viewing Maintenance Details**

1. Click the **👁 View** button (blue) on any maintenance record
2. Modal opens showing complete details:
   - Equipment information
   - Issue description
   - Status and severity
   - Timeline (reported, started, completed dates)
   - Resolution notes (if completed)
   - Cost and downtime information
   - Parts replaced
   - Next maintenance date

### **3. Updating Maintenance Status**

**Method A: From Table**
1. Click the **✏️ Edit** button (orange) on any record
2. Update modal opens

**Method B: From Details View**
1. Click **👁 View** to open details
2. Click **"Update Status"** button at bottom

**Update Form Fields:**
- **Status**: Change to In Progress, Completed, or Cancelled
- **After Condition**: Equipment condition after maintenance
- **Resolution Notes**: What was done to fix the issue
- **Cost**: Total cost of maintenance (₱)
- **Downtime**: How long equipment was unavailable (hours)
- **Parts Replaced**: List of parts that were replaced
- **Next Maintenance Date**: Schedule next maintenance

**Example Update:**
```
Status: Completed
After Condition: Excellent
Resolution Notes: Replaced screen panel and battery. Tested all functions.
Cost: ₱15,000.00
Downtime: 4.5 hours
Parts Replaced: LCD screen, battery pack
Next Maintenance: 2025-12-28
```

### **4. Filtering & Searching**

**Filter by Status:**
- All Status
- Pending only
- In Progress only
- Completed only
- Cancelled only

**Filter by Type:**
- All Types
- Repair
- Preventive
- Inspection
- Cleaning
- Calibration
- Replacement

**Search:**
- Type in search box to find by:
  - Equipment name
  - Equipment ID
  - Issue description

**Filters work together** - you can combine status filter + type filter + search for precise results.

### **5. Deleting Maintenance Logs**

1. Click the **🗑️ Delete** button (red) on any record
2. Confirm deletion in the popup
3. Record is permanently removed

⚠️ **Warning**: Deletion cannot be undone!

---

## 🎨 Visual Elements

### **Status Badges**
- 🟠 **Pending**: Orange badge - awaiting action
- 🔵 **In Progress**: Blue badge - currently being worked on
- 🟢 **Completed**: Green badge - finished
- 🔴 **Cancelled**: Red badge - cancelled/abandoned

### **Severity Badges**
- 🟢 **Low**: Green - minor issues
- 🟡 **Medium**: Orange - moderate issues
- 🔴 **High**: Red - serious issues
- 🟣 **Critical**: Purple - urgent/critical issues

### **Type Badges**
- 🔵 **All Types**: Blue badges for maintenance types

---

## 🔄 Workflow Examples

### **Example 1: Equipment Repair Workflow**

1. **Report Issue** (Status: Pending)
   ```
   Equipment: Keyboard
   Type: Repair
   Severity: Medium
   Issue: Several keys not responding
   Assigned To: Tech Team B
   ```

2. **Start Work** (Status: In Progress)
   - Technician begins diagnosis
   - System auto-records `started_date`

3. **Complete Repair** (Status: Completed)
   ```
   Resolution: Cleaned keyboard contacts, replaced membrane
   Cost: ₱500.00
   Downtime: 2.0 hours
   Parts: Keyboard membrane
   After Condition: Excellent
   Next Maintenance: 2026-01-28
   ```

### **Example 2: Preventive Maintenance**

1. **Schedule Maintenance**
   ```
   Equipment: Projector
   Type: Preventive
   Severity: Low
   Issue: Scheduled 6-month maintenance check
   Assigned To: Maintenance Team
   ```

2. **Perform Maintenance**
   ```
   Status: Completed
   Resolution: Cleaned filters, checked lamp hours, calibrated display
   Cost: ₱0.00
   Downtime: 1.0 hours
   After Condition: Excellent
   Next Maintenance: 2025-07-28
   ```

### **Example 3: Equipment Inspection**

1. **Create Inspection Log**
   ```
   Equipment: Fire Extinguisher
   Type: Inspection
   Severity: Low
   Issue: Monthly safety inspection
   ```

2. **Record Results**
   ```
   Status: Completed
   Resolution: Pressure gauge normal, no damage, seal intact
   Cost: ₱0.00
   Downtime: 0.5 hours
   After Condition: Excellent
   Next Maintenance: 2025-02-28
   ```

---

## 🔧 API Endpoints

### **Backend API** (`api/maintenance_handler.php`)

| Action | Method | Parameters | Description |
|--------|--------|------------|-------------|
| `get_all` | GET | status, type, search | Get all maintenance logs with filters |
| `get_by_id` | GET | id | Get single maintenance log details |
| `create` | POST | equipment_id, maintenance_type, issue_description, severity, etc. | Create new maintenance log |
| `update` | POST | id, status, resolution_notes, cost, etc. | Update maintenance log |
| `delete` | POST | id | Delete maintenance log |
| `update_status` | POST | id, status | Quick status update |
| `get_equipment_list` | GET | - | Get list of all equipment |
| `get_statistics` | GET | - | Get dashboard statistics |

---

## 📊 Reports & Analytics

### **Available Statistics**
- Total maintenance logs
- Count by status (Pending, In Progress, Completed, Cancelled)
- Count by type (Repair, Preventive, Inspection, etc.)
- Average downtime hours
- Total maintenance costs

### **Future Enhancements**
- Export to PDF/Excel
- Maintenance cost trends
- Equipment reliability reports
- Technician performance metrics
- Predictive maintenance alerts

---

## 🛡️ Security Features

- ✅ Admin authentication required
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (HTML escaping)
- ✅ CSRF protection (session validation)
- ✅ Input validation and sanitization

---

## 🎯 Best Practices

### **For Administrators**

1. **Regular Updates**: Update maintenance logs promptly when status changes
2. **Detailed Notes**: Write clear resolution notes for future reference
3. **Cost Tracking**: Record all costs for budget planning
4. **Schedule Preventive**: Set next maintenance dates to prevent breakdowns
5. **Assign Properly**: Assign tasks to appropriate technicians

### **For Maintenance Workflow**

1. **Report Immediately**: Create logs as soon as issues are discovered
2. **Set Severity Correctly**: Helps prioritize urgent issues
3. **Track Downtime**: Important for equipment availability metrics
4. **Document Parts**: Keep accurate records of replaced parts
5. **Update Conditions**: Record before/after conditions for trend analysis

---

## 🐛 Troubleshooting

### **Issue: Maintenance logs not loading**
- Check database connection in `includes/db_connection.php`
- Verify `maintenance_logs` table exists
- Check browser console for JavaScript errors

### **Issue: Cannot create maintenance log**
- Verify equipment exists in database
- Check required fields are filled
- Ensure admin session is active

### **Issue: Statistics showing 0**
- Run database migration to create sample data
- Create some maintenance logs manually
- Refresh the page

### **Issue: Equipment dropdown is empty**
- Verify equipment table has data
- Check `get_equipment_list` API endpoint
- Ensure proper database connection

---

## 📝 Summary

The Maintenance Tracker provides:

✅ **Complete maintenance lifecycle management**
✅ **Real-time status tracking and updates**
✅ **Detailed record keeping and documentation**
✅ **Cost and downtime monitoring**
✅ **Advanced filtering and search capabilities**
✅ **Equipment condition tracking**
✅ **Preventive maintenance scheduling**
✅ **User-friendly interface with modals**
✅ **Responsive design for mobile devices**
✅ **Secure and validated operations**

---

## 🚀 Quick Start Checklist

- [ ] Run database migration (`20251028_create_maintenance_logs.sql`)
- [ ] Verify all files are in place
- [ ] Access admin-maintenance-tracker.php
- [ ] Create your first maintenance log
- [ ] Test filtering and search
- [ ] Update a log status
- [ ] View detailed maintenance record
- [ ] Check dashboard statistics

---

**Need Help?** Check the code comments in each file for detailed technical documentation.

**Version**: 1.0.0  
**Last Updated**: October 28, 2025
