<?php
/**
 * HRMS - Reusable Header Include
 * Contains HTML head, navbar, and sidebar opening
 */

if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Dashboard');
}

$notificationCount = getUnreadNotificationCount();
$currentUser = $_SESSION['full_name'] ?? 'User';
$currentRole = $_SESSION['role'] ?? 'employee';
$userAvatar = $_SESSION['avatar'] ?? null;
$initials = '';
if ($currentUser) {
    $parts = explode(' ', $currentUser);
    $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}

// Determine active page
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('hrms_theme') || 'light');</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="HRMS - Human Resource Management System for efficient workforce management">
    <title><?= sanitize(PAGE_TITLE) ?> | <?= APP_NAME ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Boxicons -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <!-- App CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    
    <!-- Chart.js for Data Visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="logo-icon">
                    <i class='bx bxs-briefcase-alt-2'></i>
                </div>
                <span class="logo-text"><?= APP_NAME ?></span>
            </div>
            <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
                <i class='bx bx-x'></i>
            </button>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-section">
                <span class="nav-section-title">Main</span>
                
                <?php if ($currentRole === 'admin'): ?>
                <!-- Admin Navigation -->
                <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-link <?= ($currentPage === 'dashboard' && $currentDir === 'admin') ? 'active' : '' ?>">
                    <i class='bx bxs-dashboard'></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= APP_URL ?>/admin/employees.php" class="nav-link <?= ($currentPage === 'employees') ? 'active' : '' ?>">
                    <i class='bx bxs-user-detail'></i>
                    <span>Employees</span>
                </a>
                <a href="<?= APP_URL ?>/admin/departments.php" class="nav-link <?= ($currentPage === 'departments') ? 'active' : '' ?>">
                    <i class='bx bxs-buildings'></i>
                    <span>Departments</span>
                </a>
                <a href="<?= APP_URL ?>/admin/attendance.php" class="nav-link <?= ($currentPage === 'attendance') ? 'active' : '' ?>">
                    <i class='bx bxs-calendar-check'></i>
                    <span>Attendance</span>
                </a>
                <a href="<?= APP_URL ?>/admin/leaves.php" class="nav-link <?= ($currentPage === 'leaves') ? 'active' : '' ?>">
                    <i class='bx bxs-calendar-minus'></i>
                    <span>Leave Management</span>
                    <?php
                    $pdo = getDBConnection();
                    $pendingLeaves = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn();
                    if ($pendingLeaves > 0):
                    ?>
                    <span class="nav-badge"><?= $pendingLeaves ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= APP_URL ?>/admin/payroll.php" class="nav-link <?= ($currentPage === 'payroll') ? 'active' : '' ?>">
                    <i class='bx bxs-wallet'></i>
                    <span>Payroll</span>
                </a>
                <a href="<?= APP_URL ?>/admin/reports.php" class="nav-link <?= ($currentPage === 'reports') ? 'active' : '' ?>">
                    <i class='bx bxs-report'></i>
                    <span>Reports</span>
                </a>
                
                <?php else: ?>
                <!-- Employee Navigation -->
                <a href="<?= APP_URL ?>/employee/dashboard.php" class="nav-link <?= ($currentPage === 'dashboard' && $currentDir === 'employee') ? 'active' : '' ?>">
                    <i class='bx bxs-dashboard'></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= APP_URL ?>/employee/profile.php" class="nav-link <?= ($currentPage === 'profile') ? 'active' : '' ?>">
                    <i class='bx bxs-user-circle'></i>
                    <span>My Profile</span>
                </a>
                <a href="<?= APP_URL ?>/employee/attendance.php" class="nav-link <?= ($currentPage === 'attendance' && $currentDir === 'employee') ? 'active' : '' ?>">
                    <i class='bx bxs-calendar-check'></i>
                    <span>Attendance</span>
                </a>
                <a href="<?= APP_URL ?>/employee/leaves.php" class="nav-link <?= ($currentPage === 'leaves' && $currentDir === 'employee') ? 'active' : '' ?>">
                    <i class='bx bxs-calendar-minus'></i>
                    <span>Leave</span>
                </a>
                <a href="<?= APP_URL ?>/employee/payroll.php" class="nav-link <?= ($currentPage === 'payroll' && $currentDir === 'employee') ? 'active' : '' ?>">
                    <i class='bx bxs-wallet'></i>
                    <span>Salary / Payslips</span>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="nav-section">
                <span class="nav-section-title">System</span>
                <a href="<?= APP_URL ?>/<?= $currentRole ?>/notifications.php" class="nav-link <?= ($currentPage === 'notifications') ? 'active' : '' ?>">
                    <i class='bx bxs-bell'></i>
                    <span>Notifications</span>
                    <?php if ($notificationCount > 0): ?>
                    <span class="nav-badge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= APP_URL ?>/<?= $currentRole ?>/settings.php" class="nav-link <?= ($currentPage === 'settings') ? 'active' : '' ?>">
                    <i class='bx bxs-cog'></i>
                    <span>Settings</span>
                </a>
            </div>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar-sm">
                    <?= $initials ?>
                </div>
                <div class="user-info-sm">
                    <span class="user-name-sm"><?= sanitize($currentUser) ?></span>
                    <span class="user-role-sm"><?= ucfirst($currentRole) ?></span>
                </div>
            </div>
        </div>
    </aside>
    
    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Navbar -->
        <header class="navbar" id="navbar">
            <div class="navbar-left">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class='bx bx-menu'></i>
                </button>
                <div class="page-title">
                    <h1><?= sanitize(PAGE_TITLE) ?></h1>
                </div>
            </div>
            
            <div class="navbar-right">
                <!-- Search -->
                <div class="navbar-search" id="navbarSearch">
                    <i class='bx bx-search'></i>
                    <input type="text" placeholder="Search..." id="globalSearch" autocomplete="off">
                </div>
                
                <!-- Dark Mode Toggle -->
                <button class="navbar-btn" id="themeToggle" aria-label="Toggle dark mode" title="Toggle theme">
                    <i class='bx bx-moon' id="themeIcon"></i>
                </button>
                
                <!-- Notifications -->
                <div class="navbar-dropdown" id="notifDropdown">
                    <button class="navbar-btn" aria-label="Notifications">
                        <i class='bx bx-bell'></i>
                        <?php if ($notificationCount > 0): ?>
                        <span class="notification-dot"><?= $notificationCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu notification-menu">
                        <div class="dropdown-header">
                            <h4>Notifications</h4>
                            <?php if ($notificationCount > 0): ?>
                            <a href="<?= APP_URL ?>/<?= $currentRole ?>/notifications.php?action=read_all" class="mark-read">Mark all read</a>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-body" id="notificationList">
                            <!-- Loaded via JS -->
                            <div class="dropdown-empty">
                                <i class='bx bx-bell-off'></i>
                                <p>No new notifications</p>
                            </div>
                        </div>
                        <div class="dropdown-footer">
                            <a href="<?= APP_URL ?>/<?= $currentRole ?>/notifications.php">View All Notifications</a>
                        </div>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="navbar-dropdown" id="userDropdown">
                    <button class="user-menu-btn" aria-label="User menu">
                        <div class="user-avatar-xs">
                            <?= $initials ?>
                        </div>
                        <span class="user-name-nav"><?= sanitize($currentUser) ?></span>
                        <i class='bx bx-chevron-down'></i>
                    </button>
                    <div class="dropdown-menu user-menu">
                        <div class="dropdown-user-info">
                            <div class="user-avatar-sm">
                                <?= $initials ?>
                            </div>
                            <div>
                                <strong><?= sanitize($currentUser) ?></strong>
                                <span><?= sanitize($_SESSION['email'] ?? '') ?></span>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="<?= APP_URL ?>/<?= $currentRole === 'admin' ? 'admin/settings.php' : 'employee/profile.php' ?>" class="dropdown-item">
                            <i class='bx bx-user'></i> My Profile
                        </a>
                        <a href="<?= APP_URL ?>/<?= $currentRole ?>/settings.php" class="dropdown-item">
                            <i class='bx bx-cog'></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?= APP_URL ?>/logout.php" class="dropdown-item text-danger">
                            <i class='bx bx-log-out'></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Page Content -->
        <main class="main-content">
            <?php
            // Display flash messages
            $flash = getFlashMessage();
            if ($flash):
            ?>
            <div class="alert alert-<?= $flash['type'] ?>" id="flashAlert">
                <div class="alert-content">
                    <i class='bx <?php
                        echo match($flash['type']) {
                            'success' => 'bx-check-circle',
                            'danger' => 'bx-error-circle',
                            'warning' => 'bx-error',
                            default => 'bx-info-circle'
                        };
                    ?>'></i>
                    <span><?= sanitize($flash['message']) ?></span>
                </div>
                <button class="alert-close" onclick="this.parentElement.remove()">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <?php endif; ?>
