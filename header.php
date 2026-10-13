<?php
// header.php - Modern Ultra-Premium Navigation Header & Sidebar Component

require_once __DIR__ . '/auth.php';

$user = getCurrentUser();
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HemiFlow - Management System</title>
    <!-- Plus Jakarta Sans & Inter Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <!-- Feather Icons for UI -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body>
<div class="app-layout">

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="brand-logo" style="text-decoration:none;">
                <img src="asset/Hemiflow Blue Wave Logo.png" alt="hemiFlow" class="brand-logo-img">
            </a>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Main Portal</div>
            <a href="index.php" class="nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                <i data-feather="home"></i>
                <span>Dashboard</span>
            </a>

            <div class="nav-label" style="margin-top:10px;">Modules</div>
            
            <?php if (hasModuleAccess('template-generator')): ?>
            <!-- Module 3: Template Generator -->
            <a href="template_generator.php" class="nav-item <?= $currentScript === 'template_generator.php' ? 'active' : '' ?>">
                <i data-feather="file-text"></i>
                <span>Template Generator</span>
            </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('client-management')): ?>
            <!-- Clients Management -->
            <a href="clients.php" class="nav-item <?= $currentScript === 'clients.php' ? 'active' : '' ?>">
                <i data-feather="users"></i>
                <span>Client Management</span>
            </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('task-management')): ?>
            <!-- Module 4: Project & Task Management -->
            <a href="projects.php" class="nav-item <?= $currentScript === 'projects.php' ? 'active' : '' ?>">
                <i data-feather="check-square"></i>
                <span>Task Management</span>
            </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('reports')): ?>
            <!-- Module 9: Reports & Analytics -->
            <a href="reports.php" class="nav-item <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <i data-feather="bar-chart-2"></i>
                <span>Reports & BI</span>
            </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('user-management')): ?>
            <!-- User Management & Team Leads -->
            <a href="users.php" class="nav-item <?= $currentScript === 'users.php' ? 'active' : '' ?>">
                <i data-feather="user-check"></i>
                <span>User Management</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'Manu') ?></span>
                <span class="user-role"><?= htmlspecialchars($user['role'] ?? 'Sales / Account Manager') ?></span>
            </div>
            <a href="login.php?action=logout" style="color:#ef4444; display:flex; align-items:center;" title="Logout">
                <i data-feather="log-out" style="width:16px; height:16px;"></i>
            </a>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <header class="top-header no-print">
            <div class="page-header-info">
                <?php if (isset($pageBreadcrumb) && !empty($pageBreadcrumb)): ?>
                    <span class="module-badge-tag"><?= htmlspecialchars($pageBreadcrumb) ?></span>
                <?php endif; ?>
                <h1 class="page-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'HemiFlow Portal' ?></h1>
                <?php if (isset($pageSubtitle) && !empty($pageSubtitle)): ?>
                    <p class="page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>

            <div class="header-tools-area">
                <!-- Search Pill Input -->
                <div class="header-search-wrapper">
                    <i data-feather="search" class="header-search-icon"></i>
                    <input type="text" class="header-search-input" id="globalHeaderSearch" placeholder="Search documents, clients or templates..." onkeydown="if(event.key==='Enter') executeQuickSearch(this.value)">
                </div>

                <!-- User Profile Pill with Interactive Dropdown -->
                <div class="user-profile-pill" onclick="toggleUserDropdown(event)" id="userProfileBtn">
                    <div class="user-avatar-circle">
                        <?= strtoupper(substr($user['full_name'] ?? ($user['username'] ?? 'M'), 0, 1)) ?>
                    </div>
                    <div class="user-pill-text">
                        <span class="user-pill-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Manu') ?></span>
                        <span class="user-pill-role"><?= htmlspecialchars($user['role'] ?? 'Sales / Account Manager') ?></span>
                    </div>
                    <i data-feather="chevron-down" style="width:14px; height:14px; color:#64748b; margin-left:2px;"></i>

                    <!-- Dropdown Menu -->
                    <div id="userDropdownMenu" class="dropdown-menu-premium">
                        <div style="padding:8px 12px; border-bottom:1px solid #f1f5f9; margin-bottom:4px;">
                            <strong style="display:block; font-size:13px; color:#0f172a;"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></strong>
                            <span style="font-size:11px; color:#64748b;"><?= htmlspecialchars($user['email'] ?? 'user@hemiflow.com') ?></span>
                        </div>
                        <a class="dropdown-item" href="javascript:void(0)" onclick="openChangeMyPasswordModal()">
                            <i data-feather="key"></i>
                            <span>Change Password</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="login.php?action=logout">
                            <i data-feather="log-out"></i>
                            <span>Log Out</span>
                        </a>
                    </div>
                </div>

                <!-- Notification Bell Icon Button with Dropdown -->
                <div class="icon-circle-btn" onclick="toggleNotificationDropdown(event)" title="Notifications" id="notificationBtn">
                    <i data-feather="bell"></i>
                    <span class="notification-badge-dot"></span>

                    <div id="notificationDropdownMenu" class="dropdown-menu-premium" style="width:280px;">
                        <div style="padding:10px 14px; font-weight:700; font-size:13px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                            <span>Recent Activity & SLAs</span>
                            <span class="badge badge-primary" style="font-size:10px;">Live</span>
                        </div>
                        <div style="padding:10px 14px; font-size:12px; color:#334155; line-height:1.4;">
                            <div style="margin-bottom:8px;">
                                <strong style="color:#0f172a;">ABC Corporate Website:</strong> Task SLA active (8h remaining).
                            </div>
                            <div style="color:#64748b; font-size:11px;">System status operational. All services running.</div>
                        </div>
                    </div>
                </div>

                <!-- Settings Gear Icon Button -->
                <div class="icon-circle-btn" onclick="openChangeMyPasswordModal()" title="Settings">
                    <i data-feather="settings"></i>
                </div>
            </div>
        </header>

        <main class="content-container">

<script>
    function toggleUserDropdown(e) {
        e.stopPropagation();
        const menu = document.getElementById('userDropdownMenu');
        const notifMenu = document.getElementById('notificationDropdownMenu');
        if (notifMenu) notifMenu.style.display = 'none';
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }

    function toggleNotificationDropdown(e) {
        e.stopPropagation();
        const menu = document.getElementById('notificationDropdownMenu');
        const userMenu = document.getElementById('userDropdownMenu');
        if (userMenu) userMenu.style.display = 'none';
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }

    document.addEventListener('click', () => {
        const userMenu = document.getElementById('userDropdownMenu');
        const notifMenu = document.getElementById('notificationDropdownMenu');
        if (userMenu) userMenu.style.display = 'none';
        if (notifMenu) notifMenu.style.display = 'none';
    });

    function executeQuickSearch(query) {
        query = query.trim().toLowerCase();
        if (!query) return;
        if (window.location.pathname.includes('template_generator.php')) {
            const clientSel = document.getElementById('clientSelect');
            if (clientSel) {
                let found = false;
                for (let opt of clientSel.options) {
                    if (opt.text.toLowerCase().includes(query)) {
                        clientSel.value = opt.value;
                        if (typeof onClientChanged === 'function') onClientChanged();
                        found = true;
                        break;
                    }
                }
                if (!found && typeof openSavedDocumentsModal === 'function') {
                    openSavedDocumentsModal();
                }
            }
        } else if (window.location.pathname.includes('projects.php')) {
            const filterProject = document.getElementById('filterProject');
            if (filterProject) {
                for (let opt of filterProject.options) {
                    if (opt.text.toLowerCase().includes(query)) {
                        filterProject.value = opt.value;
                        if (typeof loadTasks === 'function') loadTasks();
                        break;
                    }
                }
            }
        } else {
            window.location.href = 'template_generator.php';
        }
    }
</script>
