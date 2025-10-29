# 📋 Penalty Management System (Penalty Records)

## 📋 Overview

The **Penalty Management** module (also called "Penalty Records") is where administrators **issue, track, and resolve penalties** based on the guidelines defined in the Penalty Guidelines module. This system **tracks amounts owed** but does **NOT process payments**.

---

## 🎯 Purpose & Use Cases

### **What It Does**
- **Issues penalties** to students based on violations
- **Tracks amounts owed** by each student
- **Documents penalty details** (type, severity, notes)
- **Monitors penalty status** (Pending, Under Review, Resolved)
- **Links to transactions** and equipment
- **Generates penalty notices** for students

### **Why It's Important**
1. **Enforcement**: Apply penalty guidelines to actual violations
2. **Accountability**: Track who owes what and why
3. **Documentation**: Complete audit trail of penalties
4. **Resolution Tracking**: Monitor which penalties are resolved
5. **Student Records**: View penalty history per student

---

## 🔑 Key Difference: Guidelines vs. Management

| Feature | Penalty Guidelines | Penalty Management (Records) |
|---------|-------------------|------------------------------|
| **Purpose** | Define rules and policies | Apply rules to actual cases |
| **Content** | Policy templates (₱10/day, repair requirement) | Specific penalty instances |
| **Example** | "Overdue: ₱10/day" | "Juan owes ₱50 for 5 days late" |
| **Action** | Create/edit policy | Issue/resolve penalty |
| **Users** | Policy makers | Frontline staff |

**Think of it this way:**
- **Guidelines** = The rulebook
- **Management** = The scorecard

---

## 🛠️ How to Use

### **Step 1: Issue a Penalty**

#### **Method A: From Return Verification (Automatic)**
1. Admin marks item as "Damaged" during return verification
2. System automatically creates penalty record
3. Admin reviews and confirms

#### **Method B: Manual Creation**
1. Click **"Create Penalty"** button
2. Fill in form:
   - **Student RFID/ID**: Scan or enter manually
   - **Transaction ID**: Link to borrowing transaction
   - **Penalty Type**: Select from dropdown (Late Return, Damage, Loss, or custom)
   - **Equipment**: Auto-filled from transaction
   - **Amount Owed**: Enter amount (or auto-calculated for overdue)
   - **Notes**: Document details

**Example: Late Return Penalty**
```
Student: 0066629842
Transaction: #123
Type: Late Return
Days Overdue: 5
Daily Rate: ₱10.00
Amount Owed: ₱50.00
Notes: Laptop returned 5 days after expected date
```

**Example: Damage Penalty**
```
Student: 0066629843
Transaction: #456
Type: Damage
Severity: Moderate
Damage Notes: Screen cracked in upper right corner
Amount Owed: ₱2,500.00 (repair estimate)
Notes: Student must arrange repair at authorized service center
```

**Example: Lost Item Penalty**
```
Student: 0066629844
Transaction: #789
Type: Loss
Equipment: Tablet (Model XYZ-123)
Amount Owed: ₱15,000.00 (replacement value)
Notes: Student must purchase exact same model and bring to office
```

---

### **Step 2: View Penalty Records**

**Filter Options:**
- **Status**: Pending, Under Review, Resolved, Cancelled
- **Type**: Late Return, Damage, Loss, or custom types
- **Search**: By student ID, transaction ID, equipment

**Table Columns:**
- ID
- Student
- Equipment
- Type
- Severity (for damage)
- Status
- Date Imposed
- Actions (View, Edit Status)

---

### **Step 3: Update Penalty Status**

1. Click **edit icon** (✏️) on penalty record
2. Select new status:
   - **Pending**: Just issued, awaiting action
   - **Under Review**: Being investigated/appealed
   - **Resolved**: Student fulfilled requirement
   - **Cancelled**: Penalty waived/removed
3. Add notes explaining status change
4. Click **"Update Status"**

**Status Workflow:**
```
Pending → Under Review → Resolved
   ↓
Cancelled (if waived)
```

---

### **Step 4: Resolve Penalties**

**For Late Return (₱10/day):**
1. Student pays amount at IMC office cashier
2. Cashier collects payment (outside system)
3. Admin marks penalty as "Resolved"
4. Resolution Type: "Paid"
5. Resolution Notes: "Payment received on [date]"

**For Damage:**
1. Student arranges repair at shop
2. Student brings repaired item to office
3. Admin verifies repair quality
4. Admin marks penalty as "Resolved"
5. Resolution Type: "Repaired"
6. Resolution Notes: "Item repaired and verified on [date]"

**For Loss:**
1. Student purchases replacement unit
2. Student brings replacement to office
3. Admin verifies exact match
4. Admin marks penalty as "Resolved"
5. Resolution Type: "Replaced"
6. Resolution Notes: "Replacement unit accepted on [date]"

---

## 🔄 Workflow Examples

### **Scenario 1: Overdue Laptop**

**Situation**: Student returns laptop 3 days late

**Steps**:
1. **Return Verification**: Admin marks return as verified
2. **System Auto-Calculates**: 3 days × ₱10 = ₱30
3. **Penalty Created**: Status = Pending
4. **Student Notified**: "You owe ₱30 for late return"
5. **Student Pays**: Goes to IMC office cashier
6. **Cashier Collects**: ₱30 (manual, outside system)
7. **Admin Updates**: Status → Resolved, Type → Paid
8. **Complete**: Penalty record archived

**Result**: Student's record shows ₱30 penalty resolved

---

### **Scenario 2: Damaged Mouse**

**Situation**: Student returns mouse with broken button

**Steps**:
1. **Return Verification**: Admin marks as "Damaged"
2. **Penalty Created**: Type = Damage, Severity = Minor
3. **Admin Estimates**: Repair cost = ₱150
4. **Amount Set**: ₱150 owed
5. **Student Notified**: "Repair required, estimated ₱150"
6. **Student Options**:
   - Option A: Pay ₱150, office arranges repair
   - Option B: Student repairs at own shop, brings back
7. **Student Chooses B**: Takes to repair shop
8. **Student Returns**: Brings repaired mouse
9. **Admin Verifies**: Button works, quality OK
10. **Admin Updates**: Status → Resolved, Type → Repaired
11. **Complete**: Penalty record archived

**Result**: Student fulfilled repair requirement

---

### **Scenario 3: Lost Charger**

**Situation**: Student loses laptop charger

**Steps**:
1. **After 30 Days**: Transaction marked as "Lost"
2. **Penalty Created**: Type = Loss
3. **Admin Sets**: Amount = ₱500 (charger value)
4. **Student Notified**: "Replace with same charger model"
5. **Student Purchases**: Buys exact same charger
6. **Student Brings**: Shows receipt and charger
7. **Admin Verifies**: Model matches, condition new
8. **Admin Accepts**: Charger added to inventory
9. **Admin Updates**: Status → Resolved, Type → Replaced
10. **Complete**: Original transaction closed

**Result**: Inventory restored, penalty resolved

---

## 📊 Penalty Statistics

### **Dashboard Cards**
```
┌─────────────┬─────────────┬─────────────┬─────────────┐
│ Pending: 12 │ Damage: 5   │ Review: 3   │ Resolved: 45│
│ ⏰ Orange   │ ⚠️ Red      │ 🔍 Blue     │ ✅ Green    │
└─────────────┴─────────────┴─────────────┴─────────────┘
```

**Metrics Tracked:**
- Pending Decisions: Penalties awaiting action
- Damage Cases: Items returned with damage
- Under Review: Penalties being investigated
- Resolved: Completed penalties

---

## 💰 **Amount Tracking (No Payment Processing)**

### **What the System Shows:**
```
Student: Juan Dela Cruz (0066629842)

Penalty Records:
┌────┬──────────┬─────────┬────────┬──────────┐
│ ID │ Type     │ Amount  │ Status │ Date     │
├────┼──────────┼─────────┼────────┼──────────┤
│ 1  │ Overdue  │ ₱50.00  │ Pending│ Oct 20   │
│ 2  │ Damage   │ ₱2,500  │ Pending│ Oct 22   │
│ 3  │ Loss     │ ₱500.00 │ Pending│ Oct 25   │
└────┴──────────┴─────────┴────────┴──────────┘

Total Owed: ₱3,050.00
```

### **What Happens Next:**
1. **System generates notice** (printable)
2. **Student receives notice** showing breakdown
3. **Student goes to IMC office** to settle
4. **Office staff handles collection** (manual process)
5. **Office staff updates system** to mark as resolved

**The system only tracks - it does NOT collect payments!**

---

## 🗄️ Database Structure

### **Table: `penalties`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Auto-increment primary key |
| `user_id` | INT | Student who received penalty |
| `transaction_id` | INT | Related borrowing transaction |
| `guideline_id` | INT | Link to penalty guideline |
| `equipment_id` | VARCHAR(50) | Equipment involved |
| `equipment_name` | VARCHAR(255) | Equipment name |
| `penalty_type` | VARCHAR(100) | Late Return, Damage, Loss, etc. |
| `penalty_amount` | DECIMAL(10,2) | Amount from guideline |
| `amount_owed` | DECIMAL(10,2) | Actual amount owed |
| `amount_note` | TEXT | Explanation of amount |
| `damage_severity` | ENUM | minor, moderate, severe, total_loss |
| `damage_notes` | TEXT | Damage description |
| `detected_issues` | TEXT | Auto-detected issues |
| `similarity_score` | FLOAT | Image comparison score |
| `days_overdue` | INT | Days late (for overdue) |
| `daily_rate` | DECIMAL(10,2) | Rate per day (default ₱10) |
| `description` | TEXT | Penalty description |
| `status` | ENUM | Pending, Under Review, Resolved, Cancelled |
| `date_imposed` | TIMESTAMP | When penalty was issued |
| `date_resolved` | DATETIME | When penalty was resolved |
| `resolution_type` | ENUM | Paid, Repaired, Replaced, Waived |
| `resolution_notes` | TEXT | How penalty was resolved |
| `imposed_by` | INT | Admin who issued penalty |
| `resolved_by` | VARCHAR(100) | Admin who resolved penalty |

---

## 🎨 User Interface Guide

### **Statistics Cards** (Top of page)
```
┌──────────────────────────────────────────────────────┐
│ 📊 Penalty Snapshot                                  │
├──────────────┬──────────────┬──────────────┬─────────┤
│ Pending: 12  │ Damage: 5    │ Review: 3    │ Res: 45 │
│ ⚠️ Orange    │ 🔴 Red       │ 🔵 Blue      │ ✅ Green│
└──────────────┴──────────────┴──────────────┴─────────┘
```

### **Penalty Table**
```
┌────┬──────────┬───────────┬──────────┬──────────┬────────┬──────────┬─────────┐
│ ID │ Student  │ Equipment │ Type     │ Severity │ Status │ Date     │ Actions │
├────┼──────────┼───────────┼──────────┼──────────┼────────┼──────────┼─────────┤
│ 1  │ 00666298 │ Laptop    │ Overdue  │ -        │ Pending│ Oct 20   │ 👁️ ✏️  │
│ 2  │ 00666299 │ Mouse     │ Damage   │ Minor    │ Pending│ Oct 22   │ 👁️ ✏️  │
└────┴──────────┴───────────┴──────────┴──────────┴────────┴──────────┴─────────┘
```

---

## 🔗 Integration with Other Modules

### **1. Penalty Guidelines**
- Penalties reference guidelines for amounts and rules
- Guidelines define policy, Management applies it

### **2. Return Verification**
- Damaged items auto-create penalty records
- Overdue items trigger penalty calculation

### **3. Transactions**
- Penalties linked to borrowing transactions
- Transaction history shows associated penalties

### **4. Authorized Users**
- View penalty history per student
- Track repeat offenders

---

## 📝 Best Practices

### **DO:**
✅ Issue penalties promptly after violation detected  
✅ Document details thoroughly in notes  
✅ Link to transaction for audit trail  
✅ Verify student identity before issuing  
✅ Update status as soon as resolved  
✅ Print penalty notices for students  

### **DON'T:**
❌ Issue penalties without evidence  
❌ Forget to link to transaction  
❌ Leave penalties unresolved indefinitely  
❌ Process payments through this system  
❌ Delete penalty records (cancel instead)  
❌ Issue duplicate penalties for same violation  

---

## 🐛 Troubleshooting

### **Issue: Can't create penalty**
**Cause:** Missing required fields  
**Solution:** Ensure student ID, transaction ID, and type are filled

### **Issue: Amount not calculating for overdue**
**Cause:** Days overdue not set  
**Solution:** Enter number of days late, system will calculate

### **Issue: Student says they paid but status still pending**
**Cause:** Office staff hasn't updated system  
**Solution:** Verify payment with cashier, then update status

### **Issue: Can't find penalty record**
**Cause:** Wrong filter applied  
**Solution:** Clear filters or search by transaction ID

---

## 📚 Common Questions

**Q: Can students pay penalties through this system?**  
**A:** No. This system only tracks what they owe. Payment is collected separately by IMC office cashier.

**Q: How do I know if a student paid?**  
**A:** Check with the cashier. Once confirmed, update penalty status to "Resolved" with resolution type "Paid".

**Q: What if a student disputes a penalty?**  
**A:** Change status to "Under Review" and add notes. Investigate, then either resolve or cancel.

**Q: Can I delete a penalty?**  
**A:** No, but you can cancel it. This maintains audit trail.

**Q: What if the penalty amount changes?**  
**A:** Edit the penalty record and update the amount_owed field with explanation in amount_note.

---

## 🚀 Future Enhancements

Potential features for future versions:
- Student portal to view their penalties
- Email notifications when penalty issued
- Automatic overdue penalty calculation
- Integration with school accounting system
- Payment tracking (if cashier system integrated)
- Penalty appeal workflow
- Bulk penalty issuance

---

## 📞 Support

For questions about:
- **Policy**: Refer to Penalty Guidelines module
- **Technical Issues**: Contact system administrator
- **Payment**: Contact IMC office cashier
- **Appeals**: Contact IMC director

---

**Version:** 1.0.0  
**Last Updated:** October 29, 2025  
**Module:** Penalty Management (Penalty Records)  
**Related Module:** Penalty Guidelines
