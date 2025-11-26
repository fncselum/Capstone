# Automated Overdue Notification Scheduler Setup (PowerShell)
# This script sets up Windows Task Scheduler jobs to run overdue notifications 3 times daily

Write-Host "Setting up automated overdue notification scheduler..." -ForegroundColor Green
Write-Host ""

# Define the PHP executable path and script path
$phpPath = "C:\xampp\php\php.exe"
$scriptPath = "C:\xampp\htdocs\Capstone\admin\scripts\send_overdue_notifications.php"

# Check if PHP exists
if (-not (Test-Path $phpPath)) {
    Write-Host "Warning: PHP not found at $phpPath" -ForegroundColor Yellow
    Write-Host "Please adjust the path in this script if XAMPP is installed elsewhere." -ForegroundColor Yellow
    Write-Host ""
}

# Check if script exists
if (-not (Test-Path $scriptPath)) {
    Write-Host "Error: Notification script not found at $scriptPath" -ForegroundColor Red
    exit 1
}

try {
    # Create 7:00 AM task
    Write-Host "Creating 7:00 AM notification task..." -ForegroundColor Cyan
    schtasks /create /tn "Equipment_Overdue_Notifications_7AM" /tr "`"$phpPath`" `"$scriptPath`"" /sc daily /st 07:00 /f
    
    # Create 12:00 PM task
    Write-Host "Creating 12:00 PM notification task..." -ForegroundColor Cyan
    schtasks /create /tn "Equipment_Overdue_Notifications_12PM" /tr "`"$phpPath`" `"$scriptPath`"" /sc daily /st 12:00 /f
    
    # Create 5:00 PM task
    Write-Host "Creating 5:00 PM notification task..." -ForegroundColor Cyan
    schtasks /create /tn "Equipment_Overdue_Notifications_5PM" /tr "`"$phpPath`" `"$scriptPath`"" /sc daily /st 17:00 /f
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "✓ Successfully created 3 scheduled tasks for overdue notifications:" -ForegroundColor Green
        Write-Host "  - Equipment_Overdue_Notifications_7AM  (7:00 AM daily)" -ForegroundColor White
        Write-Host "  - Equipment_Overdue_Notifications_12PM (12:00 PM daily)" -ForegroundColor White
        Write-Host "  - Equipment_Overdue_Notifications_5PM  (5:00 PM daily)" -ForegroundColor White
        Write-Host ""
        Write-Host "Notification Schedule:" -ForegroundColor Yellow
        Write-Host "  7:00 AM  - Morning reminder (NOTICE level)" -ForegroundColor White
        Write-Host "  12:00 PM - Afternoon reminder (URGENT level)" -ForegroundColor White
        Write-Host "  5:00 PM  - Evening reminder (FINAL NOTICE level)" -ForegroundColor White
        Write-Host ""
        Write-Host "To view tasks in Task Scheduler:" -ForegroundColor Cyan
        Write-Host "  taskschd.msc" -ForegroundColor White
        Write-Host ""
        Write-Host "To delete all tasks:" -ForegroundColor Cyan
        Write-Host "  schtasks /delete /tn `"Equipment_Overdue_Notifications_7AM`" /f" -ForegroundColor White
        Write-Host "  schtasks /delete /tn `"Equipment_Overdue_Notifications_12PM`" /f" -ForegroundColor White
        Write-Host "  schtasks /delete /tn `"Equipment_Overdue_Notifications_5PM`" /f" -ForegroundColor White
    } else {
        Write-Host "✗ Failed to create scheduled tasks" -ForegroundColor Red
        Write-Host "Please run PowerShell as Administrator" -ForegroundColor Yellow
    }
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "You can also run the notification script manually:" -ForegroundColor Cyan
Write-Host "  php `"$scriptPath`"" -ForegroundColor White
Write-Host ""
Write-Host "Press any key to continue..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
