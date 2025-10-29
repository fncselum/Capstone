# 👥 Authorized Users Management System

## 📋 Overview

The **Authorized Users** module allows administrators to manage which students can borrow equipment by pre-registering their RFID cards and Student IDs in the system.

---

## 🎯 Purpose & Use Cases

### **What It Does**
- **Pre-register** student RFID cards before they can borrow equipment
- **Manage** user status (Active/Inactive/Suspended)
- **Track** penalty points for rule violations
- **Control access** to the borrowing kiosk

### **Why It's Important**
1. **Security**: Only authorized students can borrow equipment
2. **Accountability**: Track who has borrowing privileges
3. **Compliance**: Enforce borrowing policies and penalties
4. **Audit Trail**: Know when users were registered and by whom

---

## 🔑 Key Features

### **1. User Registration**
- Add students by scanning or entering their RFID tag
- Link RFID to Student ID number
- Set initial status (Active/Inactive)
- Auto-timestamp registration date

### **2. Status Management**
- **Active**: Can borrow equipment normally
- **Inactive**: Cannot borrow (temporary suspension)
- **Suspended**: Blocked due to violations or penalties

### **3. Penalty Tracking**
- View accumulated penalty points per user
- Points increase when equipment is damaged/lost
- High penalty points may trigger suspension

### **4. Search & Filter**
- Search by RFID tag or Student ID
- Filter by status (Active/Inactive/Suspended)
- Real-time search results

### **5. Bulk Operations**
- Quick status toggle (Active ↔ Inactive)
- Edit user details
- Delete users (with safety checks)

---

## 📊 Dashboard Statistics

The page displays 4 key metrics:

| Stat | Description |
|------|-------------|
| **Total Users** | All registered users in the system |
| **Active** | Users who can currently borrow equipment |
| **Inactive** | Users temporarily disabled |
| **Suspended** | Users blocked due to violations |

---

## 🛠️ How to Use

### **Adding a New User**

1. Click **"+ Add User"** button
2. Fill in the form:
   - **RFID Tag**: Scan the student's RFID card or enter manually
   - **Student ID**: Enter the student's ID number (e.g., 2024-12345)
   - **Status**: Choose Active or Inactive
3. Click **"Add User"**
4. Success message appears and statistics update

**Example:**
```
RFID Tag: 0123456789AB
Student ID: 2024-12345
Status: Active
```

### **Editing a User**

1. Click the **edit icon** (✏️) next to the user
2. Modal opens with current details
3. Modify any field:
   - RFID Tag
   - Student ID
   - Status
   - Penalty Points
4. Click **"Update User"**
5. Changes save and table refreshes

### **Toggling User Status**

1. Click the **toggle icon** (🔘) next to the user
2. Status switches:
   - Active → Inactive
   - Inactive → Active
3. Confirmation message shows new status
4. Statistics update automatically

**Use Case:** Quickly disable a user without deleting their record

### **Deleting a User**

1. Click the **delete icon** (🗑️) next to the user
2. Confirmation dialog appears
3. Click **"OK"** to confirm
4. User is removed from the system

**Safety Check:** Cannot delete users with active transactions

### **Searching Users**

1. Type in the search box:
   - RFID tag (e.g., "0123456789AB")
   - Student ID (e.g., "2024-12345")
2. Results filter in real-time
3. Clear search to see all users

### **Filtering by Status**

1. Use the **status dropdown**
2. Select:
   - All Status
   - Active
   - Inactive
   - Suspended
3. Table updates to show only matching users

---

## 🔄 Workflow Examples

### **Scenario 1: New Student Registration**

**Situation:** A new student wants to borrow equipment

**Steps:**
1. Admin clicks "Add User"
2. Student scans their RFID card → `0123456789AB`
3. Admin enters Student ID → `2024-12345`
4. Sets status to "Active"
5. Clicks "Add User"
6. Student can now use the kiosk to borrow equipment

**Result:** Student is authorized and can borrow immediately

---

### **Scenario 2: Temporary Suspension**

**Situation:** Student has overdue equipment

**Steps:**
1. Admin searches for student ID: `2024-12345`
2. Clicks toggle icon to set status to "Inactive"
3. Student tries to borrow at kiosk → **Access Denied**
4. After equipment is returned, admin toggles back to "Active"
5. Student can borrow again

**Result:** Temporary access control without deleting the user

---

### **Scenario 3: Penalty Management**

**Situation:** Student damaged equipment and received penalty points

**Steps:**
1. Admin clicks edit icon for the user
2. Updates "Penalty Points" from 0 to 5
3. Clicks "Update User"
4. System tracks the penalty
5. If points exceed threshold, admin can suspend the user

**Result:** Penalty is recorded and visible in the table

---

### **Scenario 4: Bulk Status Update**

**Situation:** End of semester - disable all users

**Steps:**
1. Filter by "Active" status
2. For each user, click toggle icon
3. All users become "Inactive"
4. Next semester, toggle back to "Active" as needed

**Result:** Seasonal access control

---

## 🗄️ Database Structure

### **Table: `users`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Auto-increment primary key |
| `rfid_tag` | VARCHAR(50) | Unique RFID card identifier |
| `student_id` | VARCHAR(20) | Unique student ID number |
| `status` | ENUM | Active, Inactive, or Suspended |
| `penalty_points` | INT | Accumulated penalty points |
| `registered_at` | TIMESTAMP | When user was added |
| `updated_at` | TIMESTAMP | Last modification time |

**Constraints:**
- `rfid_tag` must be unique
- `student_id` must be unique
- Both are required fields

---

## 🔐 Security Features

### **1. Duplicate Prevention**
- System checks for existing RFID tags before adding
- System checks for existing Student IDs before adding
- Error message if duplicate found

### **2. Transaction Safety**
- Cannot delete users with active borrows
- Must complete or cancel transactions first
- Prevents data integrity issues

### **3. Admin Authentication**
- Only logged-in admins can access this page
- Session-based authentication
- Unauthorized users redirected to login

### **4. Input Validation**
- RFID tag and Student ID are required
- Penalty points must be non-negative
- Status must be valid enum value

---

## 🎨 User Interface

### **Statistics Cards**
- **Purple**: Total Users
- **Green**: Active Users
- **Orange**: Inactive Users
- **Red**: Suspended Users

### **Table Columns**
1. **ID**: Database record ID
2. **RFID Tag**: Displayed in monospace font
3. **Student ID**: Bold for emphasis
4. **Status**: Color-coded badge
5. **Penalty Points**: Red if > 0
6. **Registered**: Date added to system
7. **Actions**: Edit, Toggle, Delete buttons

### **Status Badges**
- 🟢 **Active**: Green badge
- 🟠 **Inactive**: Orange badge
- 🔴 **Suspended**: Red badge

---

## 🔗 Integration with Other Modules

### **1. Borrowing Kiosk**
- Kiosk checks if user exists in `users` table
- Verifies status is "Active"
- Rejects if Inactive or Suspended

### **2. Transactions**
- Links transactions to user via `user_id`
- Tracks borrowing history per user
- Prevents deletion if active transactions exist

### **3. Penalties**
- Penalty system updates `penalty_points`
- High points may trigger admin review
- Can lead to suspension

---

## 📝 Best Practices

### **DO:**
✅ Register users before they need to borrow  
✅ Use meaningful Student IDs (e.g., year-number format)  
✅ Regularly review penalty points  
✅ Suspend users with excessive violations  
✅ Keep RFID tags unique and secure  

### **DON'T:**
❌ Delete users with active transactions  
❌ Reuse RFID tags for different students  
❌ Leave suspended users without review  
❌ Manually edit penalty points without reason  
❌ Share RFID cards between students  

---

## 🐛 Troubleshooting

### **Issue: "RFID tag already exists"**
**Cause:** Trying to add a duplicate RFID tag  
**Solution:** Check if user is already registered, or use a different card

### **Issue: "Cannot delete user with active transactions"**
**Cause:** User has pending or borrowed equipment  
**Solution:** Complete or cancel their transactions first

### **Issue: User can't borrow at kiosk**
**Cause:** Status is Inactive or Suspended  
**Solution:** Check user status and toggle to Active if appropriate

### **Issue: Search returns no results**
**Cause:** Typo in search query or user doesn't exist  
**Solution:** Double-check spelling, try partial search

---

## 📚 API Endpoints

### **GET `/api/authorized_users_handler.php?action=get_all`**
- Returns all users with optional filters
- Query params: `search`, `status`

### **POST `/api/authorized_users_handler.php`**
- **action=create**: Add new user
- **action=update**: Modify existing user
- **action=delete**: Remove user
- **action=toggle_status**: Switch Active/Inactive

---

## 🎓 Training Tips

### **For New Admins:**
1. Start by adding a test user
2. Practice toggling status
3. Try editing details
4. Test search and filter
5. Delete the test user

### **Common Tasks:**
- **Daily**: Check for new registration requests
- **Weekly**: Review penalty points
- **Monthly**: Audit inactive users
- **Semester**: Bulk status updates

---

## 📊 Reporting

### **User Statistics**
- Total registered users
- Active vs. Inactive ratio
- Suspended user count
- Penalty point distribution

### **Audit Questions**
- How many users registered this month?
- Which users have penalty points?
- Who was suspended and why?
- Are there inactive users to clean up?

---

## 🚀 Future Enhancements

Potential features for future versions:
- Bulk import from CSV/Excel
- Export user list to PDF
- Email notifications for suspensions
- Automatic suspension based on penalty threshold
- User borrowing history view
- Photo upload for user verification

---

## 📞 Support

For technical issues or questions:
- Check this guide first
- Review error messages in browser console
- Contact system administrator
- Refer to main system documentation

---

**Version:** 1.0.0  
**Last Updated:** October 28, 2025  
**Module:** Authorized Users Management
