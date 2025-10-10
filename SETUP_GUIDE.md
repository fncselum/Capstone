# Capstone Equipment Management System - Setup Guide

## Prerequisites
- XAMPP installed and running
- Apache and MySQL services started
- Web browser

## Step-by-Step Setup

### 1. Database Setup
1. Open phpMyAdmin: `http://localhost/phpmyadmin/`
2. Import the database:
   - Click "Import" tab
   - Choose file: `database/capstone_clean_import.sql`
   - Click "Go"
3. Verify the `capstone` database is created with all tables

### 2. Admin User Setup
1. Visit: `http://localhost/Capstone/admin/create_admin.php`
2. This creates the admin user with:
   - Username: `admin`
   - Password: `admin123`

### 3. System Verification
1. Visit: `http://localhost/Capstone/admin/system_check.php`
2. Verify all components show green checkmarks
3. Fix any issues shown in red

### 4. Login Test
1. Visit: `http://localhost/Capstone/admin/login.php`
2. Enter credentials:
   - Username: `admin`
   - Password: `admin123`
3. Complete RFID verification (enter any RFID tag)
4. Should redirect to admin dashboard

### 5. System Features

#### Database Tables Created:
- **users**: RFID tags and student IDs (no personal info)
- **categories**: Equipment categories (Sport, Lab, Digital, Room, School, Others)
- **equipment**: Equipment details with RFID support
- **inventory**: Stock tracking with conditions and availability
- **transactions**: Borrowing/returning records
- **penalties**: Penalty management
- **admin_users**: Admin authentication

#### Admin Features:
- Equipment inventory management
- Transaction tracking
- User activity monitoring
- Penalty system
- Reports generation
- Two-factor authentication (Username/Password + RFID)

### 6. Adding Equipment
1. Login to admin dashboard
2. Go to "Equipment Inventory"
3. Use the form to add new equipment:
   - Equipment Name
   - RFID Tag
   - Category selection
   - Quantity
   - Condition
   - Image URL (optional)
   - Description

### 7. Privacy Compliance
- User table stores ONLY RFID tags and student IDs
- No personal information (names, emails, etc.) stored
- Complies with client privacy requirements

## Troubleshooting

### Login Issues
- Run: `http://localhost/Capstone/admin/test_login.php`
- Check password hash verification
- Recreate admin user if needed

### Database Issues
- Verify XAMPP MySQL is running
- Check database name is `capstone`
- Ensure all tables exist

### File Permissions
- Ensure web server can read all files
- Check uploads directory permissions if using file uploads

## File Structure
```
Capstone/
├── admin/                  # Admin panel files
├── database/              # Database SQL files
├── includes/              # Shared includes
├── config/                # Configuration files
├── uploads/               # File uploads
└── SETUP_GUIDE.md         # This file
```

## Support
If you encounter issues:
1. Check system_check.php for diagnostics
2. Verify all database connections use `capstone`
3. Ensure XAMPP services are running
4. Check PHP error logs
