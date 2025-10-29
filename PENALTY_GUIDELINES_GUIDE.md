# 📋 Penalty Guidelines System

## 📋 Overview

The **Penalty Guidelines** module allows administrators to define and manage penalty rules based on the Instructional Media Center's official policy. This system **tracks penalty amounts** but does **NOT process payments** - it only displays what students owe.

---

## 🎯 Purpose & Use Cases

### **What It Does**
- **Defines penalty rules** based on official IMC policy
- **Tracks penalty amounts** owed by students
- **Displays total penalties** per student
- **Documents penalty policies** with supporting files
- **Issues penalty notices** (no payment processing)

### **Why It's Important**
1. **Policy Enforcement**: Standardized penalties for all violations
2. **Transparency**: Students know exactly what they owe
3. **Accountability**: Clear documentation of penalty rules
4. **Tracking**: Monitor accumulated penalties per student
5. **Reporting**: Generate penalty reports for office records

---

## 🔑 Official IMC Penalty Policy

Based on the **Office of the Instructional Media Center** policy:

### **1. Overdue (Late Return)**
- **Amount**: ₱10.00 per day
- **Note**: To be reviewed if Saturday and Sunday are included
- **Application**: Calculated automatically based on days late

### **2. Damaged Item**
- **Requirement**: To be repaired by the borrower
- **Process**: 
  - Admin assesses damage
  - Repair cost estimated
  - Student arranges repair
  - Item returned after repair verification

### **3. Lost Item**
- **Requirement**: Replace the lost item with the same unit
- **Process**:
  - Student must purchase exact same model
  - Bring replacement to office
  - Admin verifies match
  - Original transaction closed

---

## ⚠️ **IMPORTANT: No Payment Processing**

**This system does NOT:**
- ❌ Accept payments
- ❌ Process credit cards
- ❌ Handle cash transactions
- ❌ Generate receipts
- ❌ Track payment status

**This system ONLY:**
- ✅ Displays penalty amounts owed
- ✅ Shows total penalties per student
- ✅ Tracks penalty history
- ✅ Issues penalty notices
- ✅ Generates penalty reports

**Payment Collection**: Handled separately by the IMC office staff through their own cashier/accounting system.

---

## 🛠️ How to Use

### **Step 1: Create Penalty Guidelines**

1. Click **"+ Add Penalty Guideline"** button
2. Fill in the form:
   - **Title**: e.g., "Overdue Equipment - Daily Rate"
   - **Penalty Type**: Select from dropdown
     - Late Return
     - Damage
     - Loss
   - **Penalty Amount**: Enter amount in pesos (₱)
   - **Penalty Points**: Accumulated violation points
   - **Description**: Detailed explanation of the penalty
   - **Supporting Document**: Upload policy document (optional)
   - **Status**: 
     - Draft (not yet active)
     - Active (currently enforced)
     - Archived (no longer used)
3. Click **"Save Guideline"**

**Example: Overdue Penalty**
```
Title: Daily Overdue Fee
Type: Late Return
Amount: ₱10.00
Points: 1 per day
Description: ₱10.00 charged per day for late returns. 
             Calculated from expected return date.
Status: Active
```

### **Step 2: View Penalty Guidelines**

- **Grid View**: See all guidelines as cards
- **Filter by Type**: Late Return, Damage, Loss
- **Filter by Status**: Draft, Active, Archived
- **Search**: Find specific guidelines by title/description

### **Step 3: Edit Penalty Guidelines**

1. Click **edit icon** (✏️) on guideline card
2. Modify fields as needed
3. Click **"Save Guideline"**

**Use Case**: Update overdue rate from ₱10 to ₱15

### **Step 4: View Full Details**

1. Click **eye icon** (👁️) on guideline card
2. Modal shows complete information:
   - Full description
   - All amounts and points
   - Supporting documents
   - Creation/update dates

### **Step 5: Print/Export Guidelines**

- **Print Single**: Click print icon on specific guideline
- **Export All**: Click "Export PDF" button
- **Use Case**: Print for office bulletin board or student handbook

---

## 🔄 Workflow Examples

### **Scenario 1: Setting Up Overdue Penalties**

**Situation**: Need to implement ₱10/day overdue policy

**Steps**:
1. Admin clicks "Add Penalty Guideline"
2. Fills form:
   - Title: "Daily Overdue Fee"
   - Type: Late Return
   - Amount: ₱10.00
   - Points: 1
   - Description: "₱10.00 per day for equipment returned after expected return date"
   - Status: Active
3. Saves guideline
4. System now references this when calculating overdue penalties

**Result**: All overdue transactions automatically calculate ₱10/day

---

### **Scenario 2: Damaged Equipment Policy**

**Situation**: Student returns laptop with cracked screen

**Steps**:
1. Admin already has "Damaged Equipment" guideline:
   - Type: Damage
   - Description: "Borrower must repair damaged items"
   - Amount: Variable (depends on damage)
2. During return verification:
   - Admin marks item as "Damaged"
   - System references damage guideline
   - Admin estimates repair cost: ₱2,500
   - Penalty record created showing ₱2,500 owed
3. Student sees penalty notice
4. Student arranges repair at shop
5. Student brings repaired item back
6. Admin verifies repair and closes penalty

**Result**: Student fulfilled penalty by repairing item (no cash payment to school)

---

### **Scenario 3: Lost Equipment**

**Situation**: Student lost borrowed tablet

**Steps**:
1. Admin has "Lost Equipment" guideline:
   - Type: Loss
   - Description: "Replace with same unit"
   - Amount: ₱15,000 (tablet value)
2. After 30 days unreturned:
   - Admin marks transaction as "Lost"
   - System creates penalty: ₱15,000
   - Student notified to replace item
3. Student purchases exact same tablet model
4. Student brings replacement to office
5. Admin verifies match and accepts
6. Penalty marked as "Resolved"

**Result**: School inventory restored with replacement unit

---

## 📊 Penalty Types Explained

### **1. Late Return**
- **When Applied**: Item returned after expected return date
- **Calculation**: ₱10.00 × number of days late
- **Example**: 5 days late = ₱50.00
- **Resolution**: Student pays amount to office cashier

### **2. Damage**
- **When Applied**: Item returned with damage
- **Calculation**: Estimated repair cost
- **Example**: Cracked screen = ₱2,500
- **Resolution**: Student repairs item OR pays repair cost

### **3. Loss**
- **When Applied**: Item not returned after 30 days
- **Calculation**: Replacement cost of item
- **Example**: Lost projector = ₱25,000
- **Resolution**: Student provides exact replacement unit

---

## 💰 **How Penalty Amounts Work**

### **System Displays:**
```
Student: Juan Dela Cruz (0066629842)
Penalties:
1. Overdue Laptop (5 days) - ₱50.00
2. Damaged Mouse - ₱150.00
3. Lost Charger - ₱500.00
─────────────────────────────
TOTAL OWED: ₱700.00
```

### **What Happens Next:**
1. **System generates penalty notice** (printable)
2. **Student receives notice** showing amount owed
3. **Student goes to IMC office** to settle
4. **Office staff collects payment** (outside this system)
5. **Office staff marks penalty as paid** in penalty management module

**The penalty guidelines system only defines the rules and amounts - actual payment happens separately!**

---

## 🗄️ Database Structure

### **Table: `penalty_guidelines`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Auto-increment primary key |
| `title` | VARCHAR(255) | Guideline name |
| `penalty_type` | VARCHAR(100) | Late Return, Damage, Loss |
| `penalty_description` | TEXT | Detailed explanation |
| `penalty_amount` | DECIMAL(10,2) | Amount in pesos |
| `penalty_points` | INT | Violation points |
| `document_path` | VARCHAR(255) | Supporting document file |
| `status` | ENUM | draft, active, archived |
| `created_by` | INT | Admin who created it |
| `created_at` | TIMESTAMP | Creation date |
| `updated_at` | TIMESTAMP | Last modification |

---

## 🎨 User Interface Guide

### **Policy Info Box** (Top of page)
```
┌─────────────────────────────────────────────────────────┐
│ ℹ️ Penalties from the Office of the IMC                │
├─────────────────────────────────────────────────────────┤
│ Overdue: ₱10.00 per day                                 │
│ Damaged Item: To be repaired by the borrower            │
│ Lost Item: Replace the lost item with the same unit     │
│                                                          │
│ ⚠️ Note: This system does NOT process payments.        │
│    It only tracks and displays penalty amounts owed.    │
└─────────────────────────────────────────────────────────┘
```

### **Guideline Cards**
```
┌────────────────────────────────┐
│ Daily Overdue Fee       [Active]│
├────────────────────────────────┤
│ Type: Late Return              │
│ Amount: ₱10.00                 │
│ Points: 1 pts                  │
│                                │
│ ₱10.00 charged per day for...  │
│                                │
│ Created by: Admin              │
│ [👁️] [✏️] [🗑️] [🖨️]          │
└────────────────────────────────┘
```

---

## 🔗 Integration with Other Modules

### **1. Return Verification**
- When item marked as "Damaged"
- System references damage guideline
- Creates penalty record automatically

### **2. Penalty Management**
- Uses guidelines to calculate amounts
- Displays what student owes
- Tracks penalty status

### **3. Transactions**
- Overdue transactions reference late return guideline
- Calculates daily penalties automatically

### **4. Reports**
- Penalty reports show amounts based on guidelines
- Summary of penalties issued vs. resolved

---

## 📝 Best Practices

### **DO:**
✅ Keep guidelines aligned with official IMC policy  
✅ Update amounts when policy changes  
✅ Document policy changes with supporting files  
✅ Set clear, specific descriptions  
✅ Archive old guidelines instead of deleting  
✅ Print guidelines for office reference  

### **DON'T:**
❌ Create duplicate guidelines for same penalty type  
❌ Delete active guidelines (archive instead)  
❌ Set unrealistic penalty amounts  
❌ Forget to update status (draft → active)  
❌ Use this system to process payments  
❌ Promise students they can pay through the system  

---

## 🐛 Troubleshooting

### **Issue: Can't create guideline**
**Cause:** Required fields missing  
**Solution:** Fill in all fields marked with *

### **Issue: Guideline not showing in penalty calculation**
**Cause:** Status is "Draft" or "Archived"  
**Solution:** Change status to "Active"

### **Issue: Students asking where to pay**
**Cause:** Confusion about payment process  
**Solution:** Clarify that payment is at IMC office, not through system

### **Issue: Need to change penalty amount**
**Cause:** Policy updated  
**Solution:** Edit guideline and update amount, or create new version and archive old one

---

## 📚 Common Questions

**Q: Can students pay penalties through this system?**  
**A:** No. This system only shows what they owe. Payment is collected separately by IMC office staff.

**Q: How do I know if a student paid their penalty?**  
**A:** Check the Penalty Management module (separate from guidelines). Office staff marks penalties as "Paid" there.

**Q: What if the penalty amount needs to change?**  
**A:** Edit the guideline and update the amount. Future penalties will use the new amount.

**Q: Should I delete old guidelines?**  
**A:** No, archive them instead. This maintains historical records.

**Q: Can I have multiple guidelines for the same penalty type?**  
**A:** Yes, but only one should be "Active" at a time to avoid confusion.

---

## 🚀 Future Enhancements

Potential features for future versions:
- Automatic penalty calculation based on guidelines
- Email notifications when penalties issued
- Integration with school accounting system
- Student portal to view their penalties
- Payment tracking (if integrated with cashier system)
- Penalty appeal workflow

---

## 📞 Support

For questions about:
- **Policy**: Contact IMC office director
- **Technical Issues**: Contact system administrator
- **Payment**: Contact IMC office cashier

---

**Version:** 1.0.0  
**Last Updated:** October 29, 2025  
**Module:** Penalty Guidelines Management  
**Policy Source:** Office of the Instructional Media Center
