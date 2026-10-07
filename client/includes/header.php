<?php
// client/includes/header.php - Dedicated Client Portal Layout Header & Navigation

require_once __DIR__ . '/client_auth.php';
requireClientAuth();

$db = getDbConnection();
$clientId = $_SESSION['client_id'];
$currentScript = basename($_SERVER['PHP_SELF']);

// Fetch client profile
$stmtClient = $db->prepare("SELECT company_name, contact_person FROM clients WHERE id = ?");
$stmtClient->execute([$clientId]);
$clientData = $stmtClient->fetch() ?: ['company_name' => 'ABC Company', 'contact_person' => 'Client'];

// Fetch unread notification count
$unreadCount = $db->prepare("SELECT COUNT(*) FROM client_notifications WHERE client_id = ? AND is_read = 0");
$unreadCount->execute([$clientId]);
$unreadNum = $unreadCount->fetchColumn();

// Fetch pending approvals count
$pendingApprCount = $db->prepare("SELECT COUNT(*) FROM tasks WHERE client_id = ? AND status = 'SENT FOR CLIENT APPROVAL' AND is_deleted = 0");
$pendingApprCount->execute([$clientId]);
$pendingApprNum = $pendingApprCount->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — Client Portal' : 'Client Dashboard — HemiFlow' ?></title>
    <link rel="stylesheet" href="/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        :root {
            --client-bg: #0f172a;
            --client-card-bg: #ffffff;
            --client-accent: #2563eb;
            --client-primary: #1e293b;
        }
        .client-layout {
            display: flex;
            min-height: 100vh;
            background: #f8fafc;
        }
        .client-sidebar {
            width: 260px;
            background: #0f172a;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            border-right: 1px solid rgba(255,255,255,0.08);
        }
        .client-brand {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-decoration: none;
        }
        .client-brand img {
            height: 36px;
            max-width: 120px;
            object-fit: contain;
        }
        .client-brand span {
            font-size: 17px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        .client-nav {
            padding: 20px 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .client-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .client-nav-item:hover {
            color: #ffffff;
            background: rgba(255,255,255,0.06);
        }
        .client-nav-item.active {
            color: #ffffff;
            background: #2563eb;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }
        .nav-badge {
            margin-left: auto;
            background: #ef4444;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .nav-badge-warn {
            margin-left: auto;
            background: #f59e0b;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .client-sidebar-footer {
            padding: 20px 16px;
            border-top: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .client-main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .client-topbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .client-content {
            padding: 30px;
            flex: 1;
        }
        @media (max-width: 768px) {
            .client-layout { flex-direction: column; }
            .client-sidebar { width: 100%; }
            .client-topbar { padding: 14px 16px; }
            .client-content { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="client-layout">

    <!-- Client Sidebar Navigation -->
    <aside class="client-sidebar">
        <a href="/client/dashboard.php" class="client-brand">
            <img src="/asset/Hemiflow Blue Wave Logo.png" alt="HemiFlow Logo">
            <div>
                <span>HemiFlow</span>
                <div style="font-size:10px; color:#38bdf8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Client Portal</div>
            </div>
        </a>

        <nav class="client-nav">
            <a href="/client/dashboard.php" class="client-nav-item <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i data-feather="grid"></i>
                <span>Dashboard</span>
            </a>

            <a href="/client/projects.php" class="client-nav-item <?= strpos($currentScript, 'project') === 0 ? 'active' : '' ?>">
                <i data-feather="folder"></i>
                <span>Projects</span>
            </a>

            <a href="/client/tasks.php" class="client-nav-item <?= strpos($currentScript, 'task') === 0 && $currentScript !== 'tasks.php' ? '' : ($currentScript === 'tasks.php' ? 'active' : '') ?>">
                <i data-feather="check-circle"></i>
                <span>Tasks & Progress</span>
            </a>

            <a href="/client/approvals.php" class="client-nav-item <?= $currentScript === 'approvals.php' ? 'active' : '' ?>">
                <i data-feather="check-square"></i>
                <span>Approvals</span>
                <?php if ($pendingApprNum > 0): ?>
                    <span class="nav-badge-warn"><?= $pendingApprNum ?></span>
                <?php endif; ?>
            </a>

            <a href="/client/meetings.php" class="client-nav-item <?= $currentScript === 'meetings.php' ? 'active' : '' ?>">
                <i data-feather="video"></i>
                <span>Meetings</span>
            </a>

            <a href="/client/documents.php" class="client-nav-item <?= $currentScript === 'documents.php' ? 'active' : '' ?>">
                <i data-feather="file-text"></i>
                <span>Documents</span>
            </a>

            <a href="/client/notifications.php" class="client-nav-item <?= $currentScript === 'notifications.php' ? 'active' : '' ?>">
                <i data-feather="bell"></i>
                <span>Notifications</span>
                <?php if ($unreadNum > 0): ?>
                    <span class="nav-badge"><?= $unreadNum ?></span>
                <?php endif; ?>
            </a>

            <a href="/client/profile.php" class="client-nav-item <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <i data-feather="user"></i>
                <span>Company Profile</span>
            </a>
        </nav>

        <div class="client-sidebar-footer">
            <div style="min-width:0;">
                <div style="font-size:13px; font-weight:700; color:#ffffff; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?= htmlspecialchars($clientData['company_name']) ?>
                </div>
                <div style="font-size:11px; color:#94a3b8; margin-top:2px;">Client Account</div>
            </div>
            <a href="/client/logout.php" style="color:#ef4444; padding:6px;" title="Logout">
                <i data-feather="log-out"></i>
            </a>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="client-main-wrapper">
        <header class="client-topbar">
            <div>
                <h1 style="font-size:20px; font-weight:800; color:#0f172a; margin:0; letter-spacing:-0.5px;">
                    <?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Client Dashboard' ?>
                </h1>
            </div>
            <div style="display:flex; align-items:center; gap:16px;">
                <a href="/client/notifications.php" style="position:relative; color:#64748b; padding:8px; text-decoration:none; display:flex; align-items:center;" title="Notifications">
                    <i data-feather="bell"></i>
                    <?php if ($unreadNum > 0): ?>
                        <span style="position:absolute; top:4px; right:4px; width:10px; height:10px; background:#ef4444; border-radius:50%; border:2px solid #ffffff;"></span>
                    <?php endif; ?>
                </a>

                <div style="display:flex; align-items:center; gap:10px; border-left:1px solid #e2e8f0; padding-left:16px;">
                    <div style="width:36px; height:36px; background:#dbeafe; color:#2563eb; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px;">
                        <?= strtoupper(substr($clientData['company_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-size:13px; font-weight:700; color:#0f172a; line-height:1.2;"><?= htmlspecialchars($clientData['company_name']) ?></div>
                        <div style="font-size:11px; color:#64748b; margin-top:2px;"><?= htmlspecialchars($clientData['contact_person']) ?></div>
                    </div>
                </div>
            </div>
        </header>

        <main class="client-content">
