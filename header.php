<?php
// header.php - Navigation Header & Sidebar Component

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
    <!-- Google Sans Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&family=Google+Sans+Text:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <!-- Feather Icons for UI -->
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body>
<div class="app-layout">

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="brand-logo" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
                <img src="asset/Hemiflow Blue Wave Logo.png" alt="HemiFlow Logo" style="height:36px; max-width:130px; object-fit:contain; border-radius:4px;">
                <span class="brand-name" style="font-size:18px; font-weight:800; color:#ffffff; letter-spacing:-0.5px;">HemiFlow</span>
            </a>
        </div>


        <nav class="sidebar-nav">
            <div class="nav-label">Main Portal</div>
            <a href="index.php" class="nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                <i data-feather="grid"></i>
                <span>Dashboard Hub</span>
            </a>

            <div class="nav-label" style="margin-top:15px;">Modules</div>
            
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
            <!-- Module 4: Project & Task Management (Phase 2 Ready) -->
            <a href="projects.php" class="nav-item <?= $currentScript === 'projects.php' ? 'active' : '' ?>">
                <i data-feather="check-square"></i>
                <span>Task Management</span>
            </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('reports')): ?>
            <!-- Module 9: Reports & Analytics (Phase 3 Ready) -->
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
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'Guest User') ?></span>
                <span class="user-role"><?= htmlspecialchars($user['role'] ?? 'Not logged in') ?></span>
            </div>
            <a href="login.php?action=logout" style="color:#ef4444;" title="Logout">
                <i data-feather="log-out"></i>
            </a>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <header class="top-header no-print" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 class="page-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'HemiFlow Portal' ?></h1>
            </div>
            <div class="header-actions" style="display:flex; align-items:center; gap:12px;">
                <!-- User Account Profile Badge -->
                <div style="display:flex; align-items:center; gap:10px; background:#f8fafc; border:1px solid #cbd5e1; padding:6px 14px; border-radius:30px;">
                    <div style="width:28px; height:28px; border-radius:50%; background:#2563eb; color:#ffffff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px;">
                        <?= strtoupper(substr($user['full_name'] ?? ($user['username'] ?? 'U'), 0, 1)) ?>
                    </div>
                    <div style="display:flex; flex-direction:column; line-height:1.2;">
                        <span style="font-size:13px; font-weight:700; color:#0f172a;"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'User') ?></span>
                        <span style="font-size:11px; color:#64748b; font-weight:600;"><?= htmlspecialchars($user['role'] ?? 'Guest') ?></span>
                    </div>
                </div>

                <!-- Change Password Action Button -->
                <button type="button" class="btn btn-outline btn-sm" onclick="openChangeMyPasswordModal()" style="display:flex; align-items:center; gap:6px; font-weight:600; border-color:#cbd5e1; color:#0f172a;">
                    <i data-feather="key" style="width:14px; height:14px; color:#2563eb;"></i>
                    <span>Change Password</span>
                </button>

                <a href="login.php?action=logout" class="btn btn-outline-danger btn-sm" style="display:flex; align-items:center; gap:4px;" title="Logout">
                    <i data-feather="log-out" style="width:14px; height:14px;"></i>
                    <span>Logout</span>
                </a>
            </div>
        </header>

        <main class="content-container">
