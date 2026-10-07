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
            
            <!-- Module 3: Template Generator -->
            <a href="template_generator.php" class="nav-item <?= $currentScript === 'template_generator.php' ? 'active' : '' ?>">
                <i data-feather="file-text"></i>
                <span>Template Generator</span>
            </a>

            <!-- Clients Management -->
            <a href="clients.php" class="nav-item <?= $currentScript === 'clients.php' ? 'active' : '' ?>">
                <i data-feather="users"></i>
                <span>Client Management</span>
            </a>

            <!-- Module 4: Project & Task Management (Phase 2 Ready) -->
            <a href="projects.php" class="nav-item <?= $currentScript === 'projects.php' ? 'active' : '' ?>">
                <i data-feather="check-square"></i>
                <span>Task Management</span>
            </a>

            <!-- Module 9: Reports & Analytics (Phase 3 Ready) -->
            <a href="reports.php" class="nav-item <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <i data-feather="bar-chart-2"></i>
                <span>Reports & BI</span>
            </a>

            <!-- User Management & Team Leads -->
            <a href="users.php" class="nav-item <?= $currentScript === 'users.php' ? 'active' : '' ?>">
                <i data-feather="user-check"></i>
                <span>User Management</span>
            </a>
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
        <header class="top-header no-print">
            <div>
                <h1 class="page-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'HemiFlow Portal' ?></h1>
            </div>
            <div class="header-actions">
                <span class="badge badge-primary">
                    <i data-feather="shield" style="width:12px; height:12px;"></i>
                    <?= htmlspecialchars($user['role'] ?? 'Guest') ?> Mode
                </span>
                <a href="login.php" class="btn btn-outline btn-sm">Switch Role / Module Login</a>
            </div>
        </header>

        <main class="content-container">
