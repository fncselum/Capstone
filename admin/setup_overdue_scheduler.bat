@echo off
REM Automated Overdue Notification Scheduler Setup
REM This batch file sets up a Windows Task Scheduler job to run overdue notifications

echo Setting up automated overdue notification scheduler...
echo.

REM Create scheduled tasks to run 3 times daily: 7:00 AM, 12:00 PM, and 5:00 PM
echo Creating 7:00 AM notification task...
schtasks /create /tn "Equipment_Overdue_Notifications_7AM" /tr "C:\xampp\php\php.exe \"C:\xampp\htdocs\Capstone\admin\scripts\send_overdue_notifications.php\"" /sc daily /st 07:00 /f

echo Creating 12:00 PM notification task...
schtasks /create /tn "Equipment_Overdue_Notifications_12PM" /tr "C:\xampp\php\php.exe \"C:\xampp\htdocs\Capstone\admin\scripts\send_overdue_notifications.php\"" /sc daily /st 12:00 /f

echo Creating 5:00 PM notification task...
schtasks /create /tn "Equipment_Overdue_Notifications_5PM" /tr "C:\xampp\php\php.exe \"C:\xampp\htdocs\Capstone\admin\scripts\send_overdue_notifications.php\"" /sc daily /st 17:00 /f

if %errorlevel% == 0 (
    echo ✓ Successfully created 3 scheduled tasks for overdue notifications:
    echo   - Equipment_Overdue_Notifications_7AM  (7:00 AM daily)
    echo   - Equipment_Overdue_Notifications_12PM (12:00 PM daily)
    echo   - Equipment_Overdue_Notifications_5PM  (5:00 PM daily)
    echo.
    echo To modify schedules, use Windows Task Scheduler or run:
    echo schtasks /change /tn "Equipment_Overdue_Notifications_7AM" /st [NEW_TIME]
    echo.
    echo To delete all tasks, run:
    echo schtasks /delete /tn "Equipment_Overdue_Notifications_7AM" /f
    echo schtasks /delete /tn "Equipment_Overdue_Notifications_12PM" /f
    echo schtasks /delete /tn "Equipment_Overdue_Notifications_5PM" /f
) else (
    echo ✗ Failed to create scheduled task
    echo Please run this batch file as Administrator
)

echo.
echo You can also run the notification script manually:
echo php "C:\xampp\htdocs\Capstone\admin\scripts\send_overdue_notifications.php"
echo.
pause
