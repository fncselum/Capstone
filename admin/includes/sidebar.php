<?php
/**
 * Admin Sidebar Navigation Component
 * Hierarchical navigation structure for all admin pages
 */

// Get current page to highlight active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <img src="../uploads/De lasalle ASMC.png" alt="De La Salle ASMC Logo" class="main-logo">
            <span class="logo-text">Equipment System</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <ul class="nav-menu">
        <!-- Dashboard -->
        <li class="nav-item <?= $current_page === 'admin-dashboard.php' ? 'active' : '' ?>">
            <a href="admin-dashboard.php">
                <i class="fas fa-tachometer-alt" style="color: #4caf50;"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- Equipment Management -->
        <li class="nav-item has-submenu <?= in_array($current_page, ['admin-equipment-inventory.php', 'admin-maintenance-tracker.php']) ? 'active' : '' ?>">
            <a href="#" class="submenu-toggle">
                <i class="fas fa-boxes" style="color: #ff9800;"></i>
                <span>Equipment Management</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu">
                <li class="<?= $current_page === 'admin-equipment-inventory.php' ? 'active' : '' ?>">
                    <a href="admin-equipment-inventory.php">
                        <i class="fas fa-box"></i>
                        <span>Equipment Inventory</span>
                    </a>
                </li>
                <li class="<?= $current_page === 'admin-maintenance-tracker.php' ? 'active' : '' ?>">
                    <a href="admin-maintenance-tracker.php">
                        <i class="fas fa-wrench"></i>
                        <span>Maintenance Tracker</span>
                    </a>
                </li>
                <li class="<?= $current_page === 'admin-authorized-users.php' ? 'active' : '' ?>">
                    <a href="admin-authorized-users.php">
                        <i class="fas fa-user-check"></i>
                        <span>Authorized Users</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Transactions -->
        <li class="nav-item has-submenu <?= in_array($current_page, ['admin-all-transaction.php', 'admin-return-verification.php']) ? 'active' : '' ?>">
            <a href="#" class="submenu-toggle">
                <i class="fas fa-exchange-alt" style="color: #2196f3;"></i>
                <span>Transactions</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu">
                <li class="<?= $current_page === 'admin-all-transaction.php' ? 'active' : '' ?>">
                    <a href="admin-all-transaction.php">
                        <i class="fas fa-list"></i>
                        <span>All Transactions</span>
                    </a>
                </li>
                <li class="<?= $current_page === 'admin-return-verification.php' ? 'active' : '' ?>">
                    <a href="admin-return-verification.php">
                        <i class="fas fa-check-circle"></i>
                        <span>Return Verification</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Penalty Management -->
        <li class="nav-item has-submenu <?= in_array($current_page, ['admin-penalty-guideline.php', 'admin-penalty-management.php']) ? 'active' : '' ?>">
            <a href="#" class="submenu-toggle">
                <i class="fas fa-gavel" style="color: #f44336;"></i>
                <span>Penalty Management</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu">
                <li class="<?= $current_page === 'admin-penalty-guideline.php' ? 'active' : '' ?>">
                    <a href="admin-penalty-guideline.php">
                        <i class="fas fa-book"></i>
                        <span>Penalty Guidelines</span>
                    </a>
                </li>
                <li class="<?= $current_page === 'admin-penalty-management.php' ? 'active' : '' ?>">
                    <a href="admin-penalty-management.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Penalty Records</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Kiosk Monitoring -->
        <li class="nav-item has-submenu <?= in_array($current_page, ['admin-kiosk-status.php', 'admin-kiosk-logs.php']) ? 'active' : '' ?>">
            <a href="#" class="submenu-toggle">
                <i class="fas fa-desktop" style="color: #9c27b0;"></i>
                <span>Kiosk Monitoring</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu">
                <li class="<?= $current_page === 'admin-kiosk-status.php' ? 'active' : '' ?>">
                    <a href="admin-kiosk-status.php">
                        <i class="fas fa-signal"></i>
                        <span>Kiosk Status</span>
                    </a>
                </li>
                <li class="<?= $current_page === 'admin-kiosk-logs.php' ? 'active' : '' ?>">
                    <a href="admin-kiosk-logs.php">
                        <i class="fas fa-history"></i>
                        <span>Kiosk Logs</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Reports -->
        <li class="nav-item has-submenu <?= in_array($current_page, ['reports.php', 'admin-user-activity.php']) ? 'active' : '' ?>">
            <a href="#" class="submenu-toggle">
                <i class="fas fa-chart-bar" style="color: #00bcd4;"></i>
                <span>Reports</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <ul class="submenu">
                <li class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">
                    <a href="reports.php">
                        <i class="fas fa-file-alt"></i>
                        <span>Transaction Reports</span>
                    </a>
                </li>
                <li class="<?= $current_page === 'admin-user-activity.php' ? 'active' : '' ?>">
                    <a href="admin-user-activity.php">
                        <i class="fas fa-users"></i>
                        <span>System Activity Log</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Notifications -->
        <li class="nav-item <?= $current_page === 'admin-notifications.php' ? 'active' : '' ?>">
            <a href="admin-notifications.php" style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                <span style="display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-bell" style="color: #ff5722;"></i>
                    <span>Notifications</span>
                </span>
                <span id="notifBadge" style="display:none; min-width:20px; height:20px; padding:0 6px; border-radius:999px; background:#e53935; color:#fff; font-size:12px; font-weight:700; line-height:20px; text-align:center;">0</span>
            </a>
        </li>

        <script>
        (function(){
            const badge = document.getElementById('notifBadge');
            if (!badge) return;
            async function refreshNotif(){
                try {
                    const res = await fetch('notifications_api.php', { cache: 'no-store' });
                    if (!res.ok) return;
                    const data = await res.json();
                    const unread = (data && data.ok) ? (data.unread||0) : 0;
                    if (unread > 0) {
                        badge.textContent = unread > 99 ? '99+' : unread;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                } catch (e) {
                    // ignore errors to avoid breaking sidebar
                }
            }
            refreshNotif();
            setInterval(refreshNotif, 15000);
        })();
        </script>

        <!-- System Settings -->
        <li class="nav-item <?= $current_page === 'admin-settings.php' ? 'active' : '' ?>">
            <a href="admin-settings.php">
                <i class="fas fa-cog" style="color: #607d8b;"></i>
                <span>System Settings</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <button class="logout-btn" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </button>
    </div>
</nav>

<style>
    /* Header Hamburger Button (visible when sidebar is hidden) */
    .header-hamburger {
        display: none;
        width: 40px;
        height: 40px;
        background: #2e7d32;
        border: none;
        border-radius: 6px;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        transition: all 0.3s ease;
    }

    .header-hamburger:hover {
        background: #1b5e20;
    }

    body.sidebar-hidden .header-hamburger {
        display: flex;
    }

    body.sidebar-hidden .top-header {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 12px;
    }

    /* Sidebar Styles */
    .sidebar {
        width: 260px;
        background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
        color: white;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        overflow-y: hidden;
        overflow-x: hidden;
        transition: transform 0.3s ease;
        z-index: 1000;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
    }

    .sidebar.hidden {
        transform: translateX(-100%);
    }

    .admin-container {
        transition: margin-left 0.3s ease;
    }

    body:not(.sidebar-hidden) .main-content {
        margin-left: 260px;
        max-width: calc(100% - 260px);
    }

    body.sidebar-hidden .main-content {
        margin-left: auto;
        margin-right: auto;
        max-width: 1400px;
    }

    .sidebar-header {
        padding: 12px 16px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }

    .logo-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .logo-container .main-logo {
        height: 26px;
        width: auto;
    }

    .logo-container i {
        font-size: 1.3rem;
        color: #4caf50;
    }

    .logo-text {
        font-size: 0.95rem;
        font-weight: 600;
    }

    .sidebar-toggle {
        background: rgba(255,255,255,0.1);
        border: none;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .sidebar-toggle:hover {
        background: rgba(255,255,255,0.2);
    }

    .nav-menu {
        list-style: none;
        padding: 6px 0;
        margin: 0;
        flex: 1;
        overflow-y: auto;
    }

    .nav-item {
        margin: 2px 0;
    }

    .nav-item > a,
    .submenu-toggle {
        display: flex;
        align-items: center;
        padding: 10px 16px;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: all 0.3s;
        cursor: pointer;
        border-left: 3px solid transparent;
        font-size: 0.9rem;
    }

    .nav-item > a:hover,
    .submenu-toggle:hover {
        background: rgba(255,255,255,0.1);
        color: white;
    }

    .nav-item.active > a,
    .nav-item.active > .submenu-toggle {
        background: rgba(76, 175, 80, 0.15);
        border-left-color: #4caf50;
        color: white;
    }

    .nav-item i {
        width: 20px;
        margin-right: 10px;
        font-size: 1rem;
    }

    .submenu-arrow {
        margin-left: auto;
        font-size: 0.75rem;
        transition: transform 0.3s;
    }

    .nav-item.has-submenu.open .submenu-arrow {
        transform: rotate(180deg);
    }

    .submenu {
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        background: rgba(0,0,0,0.3);
    }

    .nav-item.has-submenu.open .submenu {
        max-height: 500px;
    }

    .submenu li {
        margin: 0;
    }

    .submenu a {
        display: flex;
        align-items: center;
        padding: 9px 16px 9px 46px;
        color: rgba(255,255,255,0.65);
        text-decoration: none;
        transition: all 0.2s;
        font-size: 0.85rem;
        border-left: 3px solid transparent;
    }

    .submenu a:hover {
        background: rgba(0,0,0,0.2);
        color: rgba(255,255,255,0.9);
    }

    .submenu li.active a {
        background: rgba(0,0,0,0.15);
        border-left-color: transparent;
        color: rgba(255,255,255,0.85);
    }

    .submenu i {
        width: 20px;
        margin-right: 10px;
        font-size: 0.9rem;
        opacity: 0.7;
    }

    .sidebar-footer {
        padding: 12px 16px;
        border-top: 1px solid rgba(255,255,255,0.1);
        margin-top: auto;
        flex-shrink: 0;
    }

    .logout-btn {
        width: 100%;
        padding: 10px;
        background: rgba(244, 67, 54, 0.2);
        border: 1px solid rgba(244, 67, 54, 0.5);
        color: white;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .logout-btn:hover {
        background: rgba(244, 67, 54, 0.3);
        border-color: #f44336;
        transform: translateY(-1px);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .sidebar {
            width: 70px;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: 70px !important;
        }
    }

    /* Scrollbar */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(0,0,0,0.2);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.3);
        border-radius: 3px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.5);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Submenu toggle functionality
        const submenuToggles = document.querySelectorAll('.submenu-toggle');
        
        submenuToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const parentItem = this.closest('.nav-item');
                const wasOpen = parentItem.classList.contains('open');
                
                // Close all other submenus
                document.querySelectorAll('.nav-item.has-submenu').forEach(item => {
                    item.classList.remove('open');
                });
                
                // Toggle current submenu
                if (!wasOpen) {
                    parentItem.classList.add('open');
                }
            });
        });

        // Auto-open active submenu
        const activeSubmenuItem = document.querySelector('.submenu li.active');
        if (activeSubmenuItem) {
            const parentNavItem = activeSubmenuItem.closest('.nav-item.has-submenu');
            if (parentNavItem) {
                parentNavItem.classList.add('open');
            }
        }

        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const topHeader = document.querySelector('.top-header');
        
        // Create header hamburger button
        let headerHamburger = document.querySelector('.header-hamburger');
        if (!headerHamburger && topHeader) {
            headerHamburger = document.createElement('button');
            headerHamburger.className = 'header-hamburger';
            headerHamburger.innerHTML = '<i class="fas fa-bars"></i>';
            topHeader.insertBefore(headerHamburger, topHeader.firstChild);
        }
        
        function toggleSidebar() {
            sidebar.classList.toggle('hidden');
            document.body.classList.toggle('sidebar-hidden');
        }
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        if (headerHamburger && sidebar) {
            headerHamburger.addEventListener('click', toggleSidebar);
        }
    });

    function logout() {
        if (confirm('Are you sure you want to logout?')) {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = 'logout.php';
        }
    }
</script>
