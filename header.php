<?php
// header.php - Modern Ultra-Premium Navigation Header & Sidebar Component

require_once __DIR__ . '/auth.php';

$user = getCurrentUser();
$currentScript = basename($_SERVER['PHP_SELF']);
$chatPriv = $user ? getUserChatPrivileges($user['id']) : ['can_chat' => false, 'can_create_group' => false];
$canUserChat = !empty($chatPriv['can_chat']);
$canUserCreateGroup = !empty($chatPriv['can_create_group']);
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

    <style>
    /* ========================================================= */
    /* LIVE PRESENCE & WORK STATUS SYSTEM CSS                    */
    /* ========================================================= */
    .presence-group-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
    }

    @keyframes pulseGreenDot {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    @keyframes pulseOrangeDot {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
    }

    @keyframes pulseBackBtn {
        0% { transform: translateY(0); box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35); }
        50% { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(16, 185, 129, 0.6); }
        100% { transform: translateY(0); box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35); }
    }

    .presence-pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        display: inline-block;
        animation: pulseGreenDot 2s infinite;
        flex-shrink: 0;
    }
    .presence-pulse-dot.dot-away {
        background: #f59e0b;
        animation: pulseOrangeDot 2s infinite;
    }

    .team-presence-pill {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 6px 13px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        font-size: 12px;
        color: #334155;
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
        white-space: nowrap;
    }
    .team-presence-pill:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    }

    .my-presence-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        font-size: 12px;
        color: #1e293b;
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
        white-space: nowrap;
    }
    .my-presence-btn:hover {
        border-color: #94a3b8;
        background: #f8fafc;
    }
    .my-presence-btn.status-away {
        background: #fffbeb;
        border-color: #fde68a;
        color: #92400e;
    }

    .btn-im-back {
        display: none;
        align-items: center;
        gap: 5px;
        padding: 6px 13px;
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
        border-radius: 20px;
        color: #ffffff;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        animation: pulseBackBtn 2.2s infinite;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .btn-im-back:hover {
        background: linear-gradient(135deg, #059669, #047857);
        transform: translateY(-1px);
    }

    .presence-dropdown-menu {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 300px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 15px 30px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
        display: none;
        flex-direction: column;
        overflow: hidden;
        z-index: 9999;
        animation: fadeInDownPres 0.18s ease-out;
    }
    @keyframes fadeInDownPres {
        from { opacity: 0; transform: translateY(-6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .presence-opt-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 13px;
        cursor: pointer;
        transition: background 0.15s ease;
        border-bottom: 1px solid #f8fafc;
    }
    .presence-opt-item:last-child {
        border-bottom: none;
    }
    .presence-opt-item:hover {
        background: #f1f5f9;
    }
    .presence-opt-item.active {
        background: #ecfdf5;
        border-left: 3px solid #10b981;
    }
    .presence-opt-item .opt-icon {
        font-size: 17px;
        line-height: 1;
        width: 24px;
        text-align: center;
    }
    .presence-opt-item .opt-info {
        flex: 1;
        min-width: 0;
    }
    .presence-opt-item .opt-title {
        font-size: 12px;
        font-weight: 700;
        color: #0f172a;
    }
    .presence-opt-item .opt-desc {
        font-size: 10.5px;
        color: #64748b;
        margin-top: 1px;
    }

    .presence-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 100000;
        padding: 20px;
    }
    .presence-modal-dialog {
        background: #ffffff;
        width: 100%;
        max-width: 820px;
        max-height: 88vh;
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: zoomModalPres 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes zoomModalPres {
        from { opacity: 0; transform: scale(0.96); }
        to { opacity: 1; transform: scale(1); }
    }

    .presence-stat-chip {
        flex: 1;
        min-width: 90px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .presence-stat-chip:hover, .presence-stat-chip.active {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .presence-stat-chip .chip-count {
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }
    .presence-stat-chip .chip-label {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 3px;
    }

    .staff-presence-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-radius: 10px;
        border: 1px solid #f1f5f9;
        background: #ffffff;
        transition: all 0.15s ease;
    }
    .staff-presence-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }
    .staff-presence-card.is-away {
        background: #fffdfa;
        border-color: #fed7aa;
    }

    /* ========================================================= */
    /* GLOBAL EMPLOYEE & GROUP CHAT BOX CSS                      */
    /* ========================================================= */
    .hf-chat-launcher {
        position: fixed;
        bottom: 20px;
        right: 22px;
        z-index: 9990;
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 11px 18px;
        border-radius: 999px;
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
        color: #ffffff;
        border: none;
        box-shadow: 0 10px 28px rgba(37, 99, 235, 0.35);
        font-size: 13.5px;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.16s ease, box-shadow 0.16s ease;
    }
    .hf-chat-launcher:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px rgba(37, 99, 235, 0.45);
    }
    .hf-chat-box {
        position: fixed;
        bottom: 20px;
        right: 22px;
        width: 396px;
        height: 560px;
        max-width: calc(100vw - 24px);
        max-height: calc(100vh - 88px);
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 18px;
        box-shadow: 0 22px 55px rgba(15, 23, 42, 0.22);
        z-index: 9998;
        display: none;
        flex-direction: column;
        overflow: hidden;
        font-family: 'Inter', sans-serif;
    }
    .hf-chat-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
        color: #ffffff;
        padding: 13px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }
    .hf-chat-subtabs {
        display: flex;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 4px 6px;
        gap: 4px;
        flex-shrink: 0;
    }
    .hf-chat-subtab {
        flex: 1;
        border: none;
        background: transparent;
        padding: 7px 6px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        cursor: pointer;
    }
    .hf-chat-subtab.active {
        background: #ffffff;
        color: #1d4ed8;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
    }
    .hf-chat-body {
        flex: 1;
        overflow-y: auto;
        background: #f8fafc;
    }
    .hf-chat-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 11px 14px;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: background 0.14s;
    }
    .hf-chat-list-item:hover {
        background: #eff6ff;
    }
    .hf-chat-msgs {
        flex: 1;
        overflow-y: auto;
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: #f1f5f9;
    }
    .hf-chat-bubble-row {
        display: flex;
        flex-direction: column;
        max-width: 82%;
    }
    .hf-chat-bubble-row.mine {
        align-self: flex-end;
        align-items: flex-end;
    }
    .hf-chat-bubble-row.theirs {
        align-self: flex-start;
        align-items: flex-start;
    }
    .hf-chat-bubble {
        padding: 9px 13px;
        border-radius: 14px;
        font-size: 13px;
        line-height: 1.45;
        word-break: break-word;
        white-space: pre-wrap;
    }
    .hf-chat-bubble-row.mine .hf-chat-bubble {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #ffffff;
        border-bottom-right-radius: 4px;
    }
    .hf-chat-bubble-row.theirs .hf-chat-bubble {
        background: #ffffff;
        color: #0f172a;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
    }
    .hf-chat-system-msg {
        align-self: center;
        background: #e2e8f0;
        color: #475569;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 999px;
        text-align: center;
        max-width: 90%;
    }
    </style>
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
            <?php
                $isCurrentProjectsPage = ($currentScript === 'project_details.php') || ($currentScript === 'projects.php' && (($_GET['view'] ?? '') === 'projects'));
                $isTasksWorkspacePage = ($currentScript === 'projects.php' && (($_GET['view'] ?? '') !== 'projects')) || ($currentScript === 'task_details.php');
            ?>
            <a href="projects.php?view=projects" class="nav-item <?= $isCurrentProjectsPage ? 'active' : '' ?>">
                <i data-feather="folder"></i>
                <span>Current Projects</span>
            </a>
            <a href="projects.php" class="nav-item <?= $isTasksWorkspacePage ? 'active' : '' ?>">
                <i data-feather="check-square"></i>
                <span>Task Management</span>
            </a>
            <a href="calendar.php" class="nav-item <?= $currentScript === 'calendar.php' ? 'active' : '' ?>">
                <i data-feather="calendar"></i>
                <span>Task Calendar</span>
            </a>
            <?php endif; ?>

            <?php if (hasModuleAccess('hr-management')): ?>
            <!-- Module 5: HR Management -->
            <a href="hr.php" class="nav-item <?= in_array($currentScript, ['hr.php', 'hr_employee.php']) ? 'active' : '' ?>">
                <i data-feather="briefcase"></i>
                <span>HR & Employees</span>
            </a>
            <?php endif; ?>

            <?php if (canAccessOperations($user)): ?>
            <!-- Operations & Assets: Shoot Schedules and Domain & Server Renewals -->
            <div class="nav-label" style="margin-top:10px;">Operations & Assets</div>
            <a href="operations.php?tab=shoots" class="nav-item <?= ($currentScript === 'operations.php' && (($_GET['tab'] ?? 'shoots') === 'shoots')) ? 'active' : '' ?>">
                <i data-feather="video"></i>
                <span>Shoot Schedules</span>
            </a>
            <a href="operations.php?tab=renewals" class="nav-item <?= ($currentScript === 'operations.php' && (($_GET['tab'] ?? '') === 'renewals')) ? 'active' : '' ?>">
                <i data-feather="globe"></i>
                <span>Domain & Renewals</span>
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

                <!-- Live Team Presence Pill (Office Count) -->
                <div class="team-presence-pill" onclick="openTeamPresenceModal()" id="teamPresencePill" title="Click to view live team & office presence">
                    <span class="presence-pulse-dot" id="teamPulseDot"></span>
                    <span id="teamPresenceSummaryText"><strong>Loading...</strong></span>
                </div>

                <!-- Personal Presence Status & "I'm Back" Button -->
                <div class="presence-group-wrapper" id="myPresenceGroupWrapper">
                    <!-- Prominent "I'm Back" Button (visible only when away) -->
                    <button type="button" class="btn-im-back" id="btnImBack" onclick="onImBackClicked(event)" title="Mark that you are back at your desk">
                        <span>👋 I'm Back</span>
                    </button>

                    <!-- Personal Status Button -->
                    <div class="my-presence-btn" onclick="toggleMyPresenceDropdown(event)" id="myPresenceBtn" title="Set your work status (At Desk, Tea Break, AFK, Meeting)">
                        <span id="myStatusEmoji">🟢</span>
                        <span id="myStatusLabel" style="font-weight:600;">At Desk</span>
                        <span id="myStatusElapsedTag" style="display:none; font-size:10px; font-weight:700; background:rgba(0,0,0,0.06); padding:1px 5px; border-radius:4px; margin-left:2px;"></span>
                        <i data-feather="chevron-down" style="width:12px; height:12px; color:#64748b; margin-left:1px;"></i>
                    </div>

                    <!-- Personal Status Dropdown -->
                    <div class="presence-dropdown-menu" id="myPresenceDropdownMenu" onclick="event.stopPropagation()">
                        <div style="padding:10px 14px; font-weight:700; font-size:11.5px; color:#475569; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                            <span>My Presence Status</span>
                            <span style="font-size:10.5px; color:#94a3b8; font-weight:normal; text-transform:none;">Live tracking</span>
                        </div>

                        <div style="max-height:300px; overflow-y:auto; padding:4px 0;">
                            <div class="presence-opt-item" id="opt_IN_OFFICE" onclick="onStatusOptionSelected('IN_OFFICE')">
                                <span class="opt-icon">🟢</span>
                                <div class="opt-info">
                                    <div class="opt-title">In Office / At Desk</div>
                                    <div class="opt-desc">Working actively at my workstation</div>
                                </div>
                            </div>
                            <div class="presence-opt-item" id="opt_TEA_BREAK" onclick="onStatusOptionSelected('TEA_BREAK')">
                                <span class="opt-icon">☕</span>
                                <div class="opt-info">
                                    <div class="opt-title">Tea / Coffee Break</div>
                                    <div class="opt-desc">Stepping out for a quick beverage</div>
                                </div>
                            </div>
                            <div class="presence-opt-item" id="opt_AFK" onclick="onStatusOptionSelected('AFK')">
                                <span class="opt-icon">⏸️</span>
                                <div class="opt-info">
                                    <div class="opt-title">Away From Keyboard (AFK)</div>
                                    <div class="opt-desc">Stepped away briefly from desk</div>
                                </div>
                            </div>
                            <div class="presence-opt-item" id="opt_MEETING" onclick="onStatusOptionSelected('MEETING')">
                                <span class="opt-icon">👥</span>
                                <div class="opt-info">
                                    <div class="opt-title">In a Meeting</div>
                                    <div class="opt-desc">In conference, client call, or huddle</div>
                                </div>
                            </div>
                            <div class="presence-opt-item" id="opt_LUNCH" onclick="onStatusOptionSelected('LUNCH')">
                                <span class="opt-icon">🍽️</span>
                                <div class="opt-info">
                                    <div class="opt-title">Lunch Break</div>
                                    <div class="opt-desc">Out for lunch / dining</div>
                                </div>
                            </div>
                            <div class="presence-opt-item" id="opt_OUT_OF_OFFICE" onclick="onStatusOptionSelected('OUT_OF_OFFICE')">
                                <span class="opt-icon">🚪</span>
                                <div class="opt-info">
                                    <div class="opt-title">Out of Office</div>
                                    <div class="opt-desc">Out of premises / on field duty</div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Note Footer -->
                        <div style="padding:10px 14px; border-top:1px solid #f1f5f9; background:#f8fafc;">
                            <div style="display:flex; gap:6px;">
                                <input type="text" id="quickPresenceNoteInput" placeholder="Optional note (e.g. 10m tea, Room B)..." style="flex:1; font-size:11.5px; padding:6px 9px; border:1px solid #cbd5e1; border-radius:6px; background:#ffffff; outline:none;" onkeydown="if(event.key==='Enter') savePresenceNoteOnly()">
                                <button type="button" class="btn btn-sm btn-primary" onclick="savePresenceNoteOnly()" style="font-size:11px; padding:4px 9px; border-radius:6px;">Save</button>
                            </div>
                        </div>
                    </div>
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

                <?php if ($canUserChat): ?>
                <!-- Employee & Group Chat Header Button -->
                <div class="icon-circle-btn" onclick="toggleEmployeeChatBox()" title="Team & Group Chat" id="headerChatBtn" style="position:relative; width:38px; height:38px; border-radius:50%; border:1px solid #e2e8f0; background:#ffffff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.15s ease;">
                    <i data-feather="message-circle" style="width:18px; height:18px; color:#2563eb;"></i>
                    <span id="headerChatBadge" style="display:none; position:absolute; top:-4px; right:-4px; background:#ef4444; color:#ffffff; font-size:10px; font-weight:800; border-radius:10px; padding:1px 5px; min-width:18px; text-align:center; border:2px solid #ffffff; line-height:1.2;">0</span>
                </div>
                <?php endif; ?>

                <!-- Notification Bell Icon Button with Dropdown -->
                <div class="icon-circle-btn" onclick="toggleNotificationDropdown(event)" title="Notifications" id="notificationBtn" style="position:relative; width:38px; height:38px; border-radius:50%; border:1px solid #e2e8f0; background:#ffffff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.15s ease;">
                    <i data-feather="bell" style="width:18px; height:18px; color:#475569;"></i>
                    <span id="headerNotifBadge" style="display:none; position:absolute; top:-4px; right:-4px; background:#ef4444; color:#ffffff; font-size:10px; font-weight:800; border-radius:10px; padding:1px 5px; min-width:18px; text-align:center; border:2px solid #ffffff; line-height:1.2;">0</span>

                    <div id="notificationDropdownMenu" class="dropdown-menu-premium" style="width:340px; right:0; left:auto; max-height:420px; display:none; flex-direction:column; box-shadow:0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; z-index:9999;">
                        <div style="padding:12px 16px; font-weight:700; font-size:13px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span>🔔 Notifications</span>
                                <span id="headerNotifUnreadCountBadge" class="badge" style="background:#eff6ff; color:#2563eb; font-size:10px; font-weight:700;">0 New</span>
                            </div>
                            <button type="button" onclick="markAllNotificationsAsRead(event)" style="background:none; border:none; color:#2563eb; font-size:11px; font-weight:600; cursor:pointer; padding:0;">Mark all read</button>
                        </div>
                        <div id="headerNotificationList" style="max-height:300px; overflow-y:auto; padding:0;">
                            <div style="padding:24px 16px; text-align:center; color:#94a3b8; font-size:12px;">Loading notifications...</div>
                        </div>
                        <div style="padding:8px 14px; text-align:center; border-top:1px solid #f1f5f9; background:#f8fafc;">
                            <?php if (hasModuleAccess('task-management')): ?>
                                <a href="projects.php" style="font-size:11.5px; font-weight:600; color:#2563eb; text-decoration:none;">View Task Workspace &rarr;</a>
                            <?php elseif (hasModuleAccess('template-generator')): ?>
                                <a href="template_generator.php" style="font-size:11.5px; font-weight:600; color:#2563eb; text-decoration:none;">Open Template Generator &rarr;</a>
                            <?php else: ?>
                                <a href="index.php" style="font-size:11.5px; font-weight:600; color:#2563eb; text-decoration:none;">View Dashboard &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Settings Gear Icon Button -->
                <div class="icon-circle-btn" onclick="openChangeMyPasswordModal()" title="Settings">
                    <i data-feather="settings"></i>
                </div>
            </div>
        </header>

        <!-- ========================================================= -->
        <!-- GLOBAL MODAL: LIVE TEAM & OFFICE PRESENCE                 -->
        <!-- ========================================================= -->
        <div id="teamPresenceModal" class="presence-modal-overlay" onclick="if(event.target===this) closeTeamPresenceModal()">
            <div class="presence-modal-dialog">
                <!-- Modal Header -->
                <div style="padding:18px 24px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#ffffff;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:38px; height:38px; border-radius:10px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; font-size:18px;">
                            🏢
                        </div>
                        <div>
                            <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">Live Office & Staff Presence</h3>
                            <p style="margin:0; font-size:11.5px; color:#64748b;">Real-time visibility into who is at their desk, on break, or in meetings.</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeTeamPresenceModal()" style="background:none; border:none; font-size:24px; color:#94a3b8; cursor:pointer; padding:0; line-height:1;">&times;</button>
                </div>

                <!-- Metric Counter Chips -->
                <div style="padding:12px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; gap:8px; overflow-x:auto;">
                    <div class="presence-stat-chip active" id="chipFilterAll" onclick="filterTeamPresence('ALL')">
                        <div class="chip-count" id="chipCountTotal">0</div>
                        <div class="chip-label">All Staff</div>
                    </div>
                    <div class="presence-stat-chip" id="chipFilterInOffice" onclick="filterTeamPresence('IN_OFFICE')">
                        <div class="chip-count" style="color:#10b981;" id="chipCountInOffice">0</div>
                        <div class="chip-label">🟢 At Desk</div>
                    </div>
                    <div class="presence-stat-chip" id="chipFilterTea" onclick="filterTeamPresence('TEA_BREAK')">
                        <div class="chip-count" style="color:#f59e0b;" id="chipCountTea">0</div>
                        <div class="chip-label">☕ Tea Break</div>
                    </div>
                    <div class="presence-stat-chip" id="chipFilterAfk" onclick="filterTeamPresence('AFK')">
                        <div class="chip-count" style="color:#f97316;" id="chipCountAfk">0</div>
                        <div class="chip-label">⏸️ AFK</div>
                    </div>
                    <div class="presence-stat-chip" id="chipFilterMeeting" onclick="filterTeamPresence('MEETING')">
                        <div class="chip-count" style="color:#8b5cf6;" id="chipCountMeeting">0</div>
                        <div class="chip-label">👥 Meeting</div>
                    </div>
                    <div class="presence-stat-chip" id="chipFilterLunch" onclick="filterTeamPresence('LUNCH')">
                        <div class="chip-count" style="color:#ec4899;" id="chipCountLunch">0</div>
                        <div class="chip-label">🍽️ Lunch</div>
                    </div>
                </div>

                <!-- Search Bar -->
                <div style="padding:12px 20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; gap:12px; background:#ffffff;">
                    <div style="position:relative; flex:1;">
                        <input type="text" id="teamPresenceSearchInput" placeholder="Search staff by name, department, designation, or note..." style="width:100%; padding:8px 12px; font-size:12.5px; border:1px solid #cbd5e1; border-radius:8px; outline:none;" oninput="renderTeamStaffList()">
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="fetchAndRenderTeamPresence()" style="font-size:12px; display:flex; align-items:center; gap:4px; border-radius:8px;">
                        🔄 Refresh
                    </button>
                </div>

                <!-- Staff List Container -->
                <div id="teamPresenceStaffContainer" style="padding:14px 20px; max-height:420px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
                    <div style="text-align:center; padding:30px; color:#94a3b8; font-size:13px;">Loading live team presence...</div>
                </div>

                <!-- Modal Footer -->
                <div style="padding:12px 20px; border-top:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:11px; color:#94a3b8;">Updates automatically in real time • Status duration calculated live</span>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeTeamPresenceModal()" style="border-radius:6px; font-size:12px;">Close</button>
                </div>
            </div>
        </div>

        <?php if ($canUserChat): ?>
        <!-- ========================================================= -->
        <!-- GLOBAL FLOATING EMPLOYEE & GROUP CHAT BOX                 -->
        <!-- ========================================================= -->
        <button type="button" id="employeeChatLauncher" class="hf-chat-launcher" onclick="openEmployeeChatBox()">
            <span style="font-size:16px;">💬</span>
            <span>Team Chat</span>
            <span id="launcherChatBadge" style="display:none; background:#ef4444; color:#ffffff; font-size:10.5px; font-weight:800; padding:1px 7px; border-radius:999px; line-height:1.4;">0</span>
        </button>

        <div id="employeeChatBox" class="hf-chat-box">
            <!-- Top Chat Box Header -->
            <div class="hf-chat-header">
                <div style="display:flex; align-items:center; gap:9px; min-width:0;">
                    <span style="font-size:18px;">💬</span>
                    <div style="min-width:0;">
                        <div style="font-size:14px; font-weight:800; line-height:1.2;">Team & Group Chat</div>
                        <div id="hfChatPrivSubtitle" style="font-size:10.5px; color:#bfdbfe; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= $canUserCreateGroup ? 'Direct & Group Create Enabled' : 'Employee Direct Chat' ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">
                    <button type="button" id="hfHeaderNewGroupBtn" onclick="openChatCreateGroupView()" style="display:<?= $canUserCreateGroup ? 'inline-flex' : 'none' ?>; align-items:center; gap:4px; background:rgba(255,255,255,0.16); color:#ffffff; border:1px solid rgba(255,255,255,0.28); border-radius:8px; padding:5px 9px; font-size:11px; font-weight:700; cursor:pointer;" title="Create a new group chat">
                        + Group
                    </button>
                    <button type="button" onclick="refreshEmployeeChatNow()" style="background:rgba(255,255,255,0.12); color:#ffffff; border:none; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:13px;" title="Refresh Chat">↻</button>
                    <button type="button" onclick="closeEmployeeChatBox()" style="background:rgba(255,255,255,0.12); color:#ffffff; border:none; border-radius:8px; width:28px; height:28px; cursor:pointer; font-size:17px; line-height:1;" title="Minimize Chat">&times;</button>
                </div>
            </div>

            <!-- SCREEN 1: HOME (Chats / Employees / Groups) -->
            <div id="hfChatHomeScreen" style="display:flex; flex-direction:column; flex:1; min-height:0;">
                <div style="padding:9px 12px; background:#ffffff; border-bottom:1px solid #f1f5f9;">
                    <input type="text" id="hfChatSearchInput" class="form-control" style="font-size:12.5px; padding:7px 11px; border-radius:10px;" placeholder="🔍 Search employees, groups, or chats..." oninput="renderChatHomeLists()">
                </div>

                <div class="hf-chat-subtabs">
                    <button type="button" id="hfSubTab_chats" class="hf-chat-subtab active" onclick="switchChatHomeTab('chats')">💬 Chats</button>
                    <button type="button" id="hfSubTab_employees" class="hf-chat-subtab" onclick="switchChatHomeTab('employees')">👤 Employees</button>
                    <button type="button" id="hfSubTab_groups" class="hf-chat-subtab" onclick="switchChatHomeTab('groups')">👥 Groups</button>
                </div>

                <div id="hfChatGroupPrivBanner" style="display:none; padding:8px 12px; background:#fefce8; border-bottom:1px solid #fef08a; font-size:11.5px; color:#854d0e; font-weight:600;">
                    🔒 Create Group privilege is disabled for your account by Admin. You can still participate in groups you're added to.
                </div>

                <div id="hfChatHomeBody" class="hf-chat-body">
                    <div style="padding:30px 16px; text-align:center; color:#94a3b8; font-size:12.5px;">Loading conversations...</div>
                </div>
            </div>

            <!-- SCREEN 2: CREATE GROUP -->
            <div id="hfChatCreateGroupScreen" style="display:none; flex-direction:column; flex:1; min-height:0; background:#f8fafc;">
                <div style="padding:10px 14px; background:#ffffff; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between;">
                    <button type="button" onclick="backToChatHome()" style="background:none; border:none; color:#2563eb; font-weight:700; font-size:12.5px; cursor:pointer; padding:0;">&larr; Back</button>
                    <strong style="font-size:13px; color:#0f172a;">Create New Group Chat</strong>
                    <span id="hfNewGroupSelCount" class="badge" style="background:#dbeafe; color:#1e40af; font-size:10.5px;">0 Selected</span>
                </div>

                <div style="flex:1; overflow-y:auto; padding:12px 14px;">
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:11.5px; font-weight:700; color:#334155; margin-bottom:4px;">Group Name *</label>
                        <input type="text" id="hfNewGroupTitle" class="form-control" style="font-size:13px; padding:8px 10px;" placeholder="e.g. SEO Growth Squad / Website Launch">
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="display:block; font-size:11.5px; font-weight:700; color:#334155; margin-bottom:4px;">Description / Topic (Optional)</label>
                        <input type="text" id="hfNewGroupDesc" class="form-control" style="font-size:12.5px; padding:7px 10px;" placeholder="Project deliverables & quick updates">
                    </div>
                    <div style="margin-bottom:8px;">
                        <label style="display:block; font-size:11.5px; font-weight:700; color:#334155; margin-bottom:4px;">Select Employees *</label>
                        <input type="text" id="hfNewGroupEmpSearch" class="form-control" style="font-size:12px; padding:6px 10px; margin-bottom:8px;" placeholder="Filter employees..." oninput="renderCreateGroupMembersPicker()">
                        <div id="hfNewGroupMemberList" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; max-height:220px; overflow-y:auto;"></div>
                    </div>
                </div>

                <div style="padding:10px 14px; background:#ffffff; border-top:1px solid #e2e8f0; display:flex; gap:8px;">
                    <button type="button" class="btn btn-outline btn-sm" style="flex:1;" onclick="backToChatHome()">Cancel</button>
                    <button type="button" id="hfCreateGroupSubmitBtn" class="btn btn-primary btn-sm" style="flex:2; font-weight:700;" onclick="submitChatCreateGroup()">Create Group Chat</button>
                </div>
            </div>

            <!-- SCREEN 3: ACTIVE CONVERSATION THREAD -->
            <div id="hfChatThreadScreen" style="display:none; flex-direction:column; flex:1; min-height:0;">
                <div style="padding:10px 14px; background:#ffffff; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; gap:8px; flex-shrink:0;">
                    <div style="display:flex; align-items:center; gap:9px; min-width:0;">
                        <button type="button" onclick="backToChatHome()" style="background:#f1f5f9; border:none; color:#334155; width:28px; height:28px; border-radius:8px; cursor:pointer; font-weight:800; flex-shrink:0;" title="Back to Chats">&larr;</button>
                        <div style="min-width:0;">
                            <div id="hfThreadTitle" style="font-size:13.5px; font-weight:800; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Chat</div>
                            <div id="hfThreadSubtitle" style="font-size:11px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Loading...</div>
                        </div>
                    </div>
                    <button type="button" id="hfThreadMembersBtn" class="btn btn-outline btn-sm" style="display:none; font-size:11px; padding:4px 9px;" onclick="toggleChatGroupPanel()">👥 Members</button>
                </div>

                <!-- Collapsible Group Members Management Drawer -->
                <div id="hfChatGroupPanel" style="display:none; background:#f8fafc; border-bottom:1px solid #cbd5e1; padding:10px 12px; max-height:210px; overflow-y:auto; flex-shrink:0;">
                    <div id="hfGroupParticipantsList" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px;"></div>
                    <div id="hfAddGroupMemberRow" style="display:none; gap:6px; align-items:center; margin-bottom:8px;">
                        <select id="hfAddMemberSelect" class="form-select" style="font-size:11.5px; padding:5px 8px; flex:1;">
                            <option value="">+ Add Employee to Group...</option>
                        </select>
                        <button type="button" class="btn btn-primary btn-sm" style="font-size:11.5px; padding:5px 10px;" onclick="addSelectedMemberToGroup()">Add</button>
                    </div>
                    <div style="text-align:right;">
                        <button type="button" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fecaca; font-size:11px; padding:3px 9px;" onclick="leaveActiveGroupChat()">Leave Group</button>
                    </div>
                </div>

                <div id="hfChatMessagesFeed" class="hf-chat-msgs"></div>

                <form onsubmit="event.preventDefault(); sendActiveChatMessage();" style="padding:10px 12px; background:#ffffff; border-top:1px solid #e2e8f0; display:flex; gap:8px; align-items:flex-end; flex-shrink:0;">
                    <textarea id="hfChatMsgInput" class="form-control" rows="1" style="flex:1; font-size:13px; padding:8px 11px; border-radius:12px; resize:none; max-height:84px; line-height:1.4;" placeholder="Type a message... (Enter to send)" onkeydown="if(event.key==='Enter' && !event.shiftKey){ event.preventDefault(); sendActiveChatMessage(); }"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm" style="border-radius:12px; padding:8px 14px; font-weight:700;">Send ➤</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

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
        const isHidden = (menu.style.display === 'none' || !menu.style.display);
        menu.style.display = isHidden ? 'flex' : 'none';
        if (isHidden) {
            loadHeaderNotifications();
        }
    }

    if (typeof escapeHtml !== 'function') {
        window.escapeHtml = function(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        };
    }

    function loadHeaderNotifications() {
        fetch('api.php?action=get_user_notifications')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const notifs = data.notifications || [];
                const unread = data.unread_count || 0;

                const badge = document.getElementById('headerNotifBadge');
                const unreadBadge = document.getElementById('headerNotifUnreadCountBadge');
                if (badge) {
                    if (unread > 0) {
                        badge.textContent = unread > 99 ? '99+' : unread;
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                if (unreadBadge) {
                    unreadBadge.textContent = `${unread} New`;
                }

                const list = document.getElementById('headerNotificationList');
                if (!list) return;

                if (notifs.length === 0) {
                    list.innerHTML = `<div style="padding:24px 16px; text-align:center; color:#94a3b8; font-size:12px;">No notifications yet.</div>`;
                    return;
                }

                let html = '';
                notifs.forEach(n => {
                    const isUnread = parseInt(n.is_read) === 0;
                    const targetUrl = n.link_url || '<?= hasModuleAccess('task-management') ? 'projects.php' : (hasModuleAccess('template-generator') ? 'template_generator.php' : 'index.php') ?>';
                    const iconMap = {
                        project_handover: '🚀',
                        project_member_added: '👥',
                        project_handler_assigned: '🛡️',
                        shoot_assigned: '🎬',
                        renewal_assigned: '🌐',
                        leave: '🏖️'
                    };
                    const icon = iconMap[n.type] || '📋';
                    const timeStr = formatNotificationTime(n.created_at);

                    html += `
                        <div onclick="onNotificationItemClick(${n.id}, '${targetUrl}')" style="padding:11px 15px; border-bottom:1px solid #f1f5f9; cursor:pointer; background:${isUnread ? '#f0f9ff' : '#ffffff'}; transition:background 0.15s ease; display:flex; gap:10px; align-items:flex-start;">
                            <div style="font-size:16px; line-height:1; padding-top:2px;">${icon}</div>
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                    <strong style="font-size:12px; color:${isUnread ? '#0369a1' : '#334155'}; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px;">${escapeHtml(n.title)}</strong>
                                    <span style="font-size:10px; color:#94a3b8; white-space:nowrap;">${timeStr}</span>
                                </div>
                                <div style="font-size:11.5px; color:#475569; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                                    ${escapeHtml(n.message)}
                                </div>
                            </div>
                            ${isUnread ? '<span style="width:7px; height:7px; border-radius:50%; background:#0284c7; flex-shrink:0; margin-top:5px;"></span>' : ''}
                        </div>
                    `;
                });
                list.innerHTML = html;
            })
            .catch(err => {
                console.error("Failed to fetch notifications", err);
            });
    }

    function onNotificationItemClick(notifId, targetUrl) {
        fetch('api.php?action=mark_user_notification_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: notifId })
        }).finally(() => {
            window.location.href = targetUrl;
        });
    }

    function markAllNotificationsAsRead(e) {
        if (e) e.stopPropagation();
        fetch('api.php?action=mark_user_notification_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: 0 })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadHeaderNotifications();
            }
        });
    }

    function formatNotificationTime(dateStr) {
        if (!dateStr) return '';
        const dt = new Date(dateStr.replace(/-/g, '/'));
        const now = new Date();
        const diffSec = Math.floor((now - dt) / 1000);
        if (diffSec < 60) return 'Just now';
        const diffMin = Math.floor(diffSec / 60);
        if (diffMin < 60) return `${diffMin}m ago`;
        const diffHr = Math.floor(diffMin / 60);
        if (diffHr < 24) return `${diffHr}h ago`;
        const diffDay = Math.floor(diffHr / 24);
        if (diffDay === 1) return 'Yesterday';
        if (diffDay < 7) return `${diffDay}d ago`;
        return dt.toLocaleDateString();
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadHeaderNotifications();
        setInterval(loadHeaderNotifications, 30000);
        loadMyPresence();
        setInterval(loadMyPresence, 25000);
        updateTeamPresenceBadge();
        setInterval(updateTeamPresenceBadge, 30000);
    });

    document.addEventListener('click', () => {
        const userMenu = document.getElementById('userDropdownMenu');
        const notifMenu = document.getElementById('notificationDropdownMenu');
        const presMenu = document.getElementById('myPresenceDropdownMenu');
        if (userMenu) userMenu.style.display = 'none';
        if (notifMenu) notifMenu.style.display = 'none';
        if (presMenu) presMenu.style.display = 'none';
    });

    // =========================================================
    // WORK PRESENCE & AWAY TRACKING SYSTEM LOGIC
    // =========================================================
    let currentWorkStatus = 'IN_OFFICE';
    let teamPresenceData = null;
    let currentPresenceFilter = 'ALL';
    const currentAuthUserId = <?= json_encode(intval($user['id'] ?? 0)) ?>;
    const currentUserFullName = <?= json_encode($user['full_name'] ?? 'User') ?>;
    const isSuperAdminOrLead = <?= json_encode(($user['role'] === 'Super Admin' || ($user['role_id'] ?? 0) == 1 || $user['role'] === 'Team Lead' || ($user['role_id'] ?? 0) == 4 || isHrAdmin())) ?>;

    function loadMyPresence() {
        fetch('api.php?action=get_my_presence')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                currentWorkStatus = data.status || 'IN_OFFICE';
                const btnBack = document.getElementById('btnImBack');
                const myBtn = document.getElementById('myPresenceBtn');
                const lbl = document.getElementById('myStatusLabel');
                const emj = document.getElementById('myStatusEmoji');
                const elp = document.getElementById('myStatusElapsedTag');
                const noteInput = document.getElementById('quickPresenceNoteInput');

                if (lbl) lbl.textContent = data.status_short || 'At Desk';
                if (emj) emj.textContent = data.status_emoji || '🟢';
                if (noteInput && data.status_note && !noteInput.value) noteInput.value = data.status_note;

                if (data.is_away) {
                    if (btnBack) btnBack.style.display = 'inline-flex';
                    if (myBtn) myBtn.classList.add('status-away');
                    if (elp) {
                        elp.textContent = (data.minutes_elapsed > 0) ? `${data.minutes_elapsed}m` : 'Just now';
                        elp.style.display = 'inline-block';
                    }
                } else {
                    if (btnBack) btnBack.style.display = 'none';
                    if (myBtn) myBtn.classList.remove('status-away');
                    if (elp) elp.style.display = 'none';
                }

                document.querySelectorAll('.presence-opt-item').forEach(el => el.classList.remove('active'));
                const activeOpt = document.getElementById('opt_' + currentWorkStatus);
                if (activeOpt) activeOpt.classList.add('active');
            })
            .catch(err => console.error("Error loading presence:", err));
    }

    function updateTeamPresenceBadge() {
        fetch('api.php?action=get_team_presence')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                teamPresenceData = data;
                const s = data.summary;
                const txt = document.getElementById('teamPresenceSummaryText');
                const dot = document.getElementById('teamPulseDot');
                if (txt) {
                    if (s.total_away_count > 0) {
                        txt.innerHTML = `<strong>${s.in_office_count}</strong> In Office • <span style="color:#d97706; font-weight:700;">${s.total_away_count} Away</span>`;
                        if (dot) dot.classList.add('dot-away');
                    } else {
                        txt.innerHTML = `<strong>${s.in_office_count}</strong> In Office • <span style="color:#10b981; font-weight:600;">All at Desk</span>`;
                        if (dot) dot.classList.remove('dot-away');
                    }
                }
            })
            .catch(err => console.error("Error updating team badge:", err));
    }

    function toggleMyPresenceDropdown(e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('myPresenceDropdownMenu');
        const userMenu = document.getElementById('userDropdownMenu');
        const notifMenu = document.getElementById('notificationDropdownMenu');
        if (userMenu) userMenu.style.display = 'none';
        if (notifMenu) notifMenu.style.display = 'none';
        const isHidden = (menu.style.display === 'none' || !menu.style.display);
        menu.style.display = isHidden ? 'flex' : 'none';
    }

    function onImBackClicked(e) {
        if (e) e.stopPropagation();
        setMyStatus('IN_OFFICE', '');
    }

    function onStatusOptionSelected(status) {
        const noteInput = document.getElementById('quickPresenceNoteInput');
        const note = (status === 'IN_OFFICE') ? '' : (noteInput ? noteInput.value.trim() : '');
        setMyStatus(status, note);
        const menu = document.getElementById('myPresenceDropdownMenu');
        if (menu) menu.style.display = 'none';
    }

    function savePresenceNoteOnly() {
        const noteInput = document.getElementById('quickPresenceNoteInput');
        const note = noteInput ? noteInput.value.trim() : '';
        setMyStatus(currentWorkStatus, note);
        const menu = document.getElementById('myPresenceDropdownMenu');
        if (menu) menu.style.display = 'none';
    }

    function setMyStatus(status, note) {
        fetch('api.php?action=update_work_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: status, status_note: note })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadMyPresence();
                updateTeamPresenceBadge();
                showPresenceToast(data.message || 'Status updated');
                if (typeof loadHrDashboard === 'function') loadHrDashboard();
            } else {
                alert('Error updating status: ' + data.message);
            }
        })
        .catch(err => {
            console.error("Failed to update status:", err);
        });
    }

    function openTeamPresenceModal() {
        const modal = document.getElementById('teamPresenceModal');
        if (modal) {
            modal.style.display = 'flex';
            fetchAndRenderTeamPresence();
        }
    }

    function closeTeamPresenceModal() {
        const modal = document.getElementById('teamPresenceModal');
        if (modal) modal.style.display = 'none';
    }

    function filterTeamPresence(filter) {
        currentPresenceFilter = filter;
        ['All', 'InOffice', 'Tea', 'Afk', 'Meeting', 'Lunch'].forEach(k => {
            const chip = document.getElementById('chipFilter' + k);
            if (chip) chip.classList.remove('active');
        });
        if (filter === 'ALL') document.getElementById('chipFilterAll')?.classList.add('active');
        else if (filter === 'IN_OFFICE') document.getElementById('chipFilterInOffice')?.classList.add('active');
        else if (filter === 'TEA_BREAK') document.getElementById('chipFilterTea')?.classList.add('active');
        else if (filter === 'AFK') document.getElementById('chipFilterAfk')?.classList.add('active');
        else if (filter === 'MEETING') document.getElementById('chipFilterMeeting')?.classList.add('active');
        else if (filter === 'LUNCH') document.getElementById('chipFilterLunch')?.classList.add('active');

        renderTeamStaffList();
    }

    function fetchAndRenderTeamPresence() {
        const container = document.getElementById('teamPresenceStaffContainer');
        if (container) container.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8; font-size:13px;">Refreshing live presence...</div>';

        fetch('api.php?action=get_team_presence')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                teamPresenceData = data;
                const s = data.summary;
                if (document.getElementById('chipCountTotal')) document.getElementById('chipCountTotal').textContent = s.total_staff || 0;
                if (document.getElementById('chipCountInOffice')) document.getElementById('chipCountInOffice').textContent = s.in_office_count || 0;
                if (document.getElementById('chipCountTea')) document.getElementById('chipCountTea').textContent = s.tea_break_count || 0;
                if (document.getElementById('chipCountAfk')) document.getElementById('chipCountAfk').textContent = s.afk_count || 0;
                if (document.getElementById('chipCountMeeting')) document.getElementById('chipCountMeeting').textContent = s.meeting_count || 0;
                if (document.getElementById('chipCountLunch')) document.getElementById('chipCountLunch').textContent = s.lunch_count || 0;

                renderTeamStaffList();
            });
    }

    function renderTeamStaffList() {
        const container = document.getElementById('teamPresenceStaffContainer');
        if (!container || !teamPresenceData) return;

        const query = (document.getElementById('teamPresenceSearchInput')?.value || '').trim().toLowerCase();
        let staff = teamPresenceData.staff || [];

        if (currentPresenceFilter !== 'ALL') {
            staff = staff.filter(s => s.work_status === currentPresenceFilter);
        }

        if (query) {
            staff = staff.filter(s => 
                s.full_name.toLowerCase().includes(query) ||
                (s.department && s.department.toLowerCase().includes(query)) ||
                (s.designation && s.designation.toLowerCase().includes(query)) ||
                (s.status_note && s.status_note.toLowerCase().includes(query))
            );
        }

        if (staff.length === 0) {
            container.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8; font-size:13px;">No employees match the current filter or search.</div>';
            return;
        }

        let html = '';
        staff.forEach(st => {
            const isSelf = (st.user_id === currentAuthUserId);
            const cardClass = st.is_away ? 'staff-presence-card is-away' : 'staff-presence-card';
            const initial = (st.full_name || 'U').charAt(0).toUpperCase();
            const avatarBg = st.is_away ? '#fef3c7' : '#dcfce7';
            const avatarColor = st.is_away ? '#b45309' : '#15803d';
            const noteHtml = st.status_note ? `<div style="font-size:11px; color:#64748b; margin-top:3px; background:#f8fafc; padding:2px 8px; border-radius:6px; display:inline-block; border-left:2px solid #cbd5e1;">💬 "${escapeHtml(st.status_note)}"</div>` : '';

            let actionBtn = '';
            if (st.is_away) {
                if (isSelf) {
                    actionBtn = `<button type="button" class="btn btn-sm btn-success" onclick="setMyStatus('IN_OFFICE', ''); setTimeout(fetchAndRenderTeamPresence, 400);" style="font-size:11px; padding:4px 10px; border-radius:6px; background:#10b981; border:none; color:#fff; font-weight:700; cursor:pointer;">👋 I'm Back</button>`;
                } else if (isSuperAdminOrLead) {
                    actionBtn = `<button type="button" class="btn btn-outline btn-sm" onclick="adminSetStaffAtDesk(${st.user_id}, '${escapeHtml(st.full_name)}')" style="font-size:11px; padding:3px 8px; border-radius:6px; color:#475569;" title="Override and mark staff back at desk">Mark At Desk</button>`;
                }
            }

            html += `
                <div class="${cardClass}">
                    <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                        <div style="position:relative; width:40px; height:40px; border-radius:50%; background:${avatarBg}; color:${avatarColor}; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:15px; flex-shrink:0;">
                            ${initial}
                            <span style="position:absolute; bottom:0; right:0; width:11px; height:11px; border-radius:50%; background:${st.status_color}; border:2px solid #ffffff;"></span>
                        </div>
                        <div style="min-width:0;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <strong style="font-size:13.5px; color:#0f172a;">${escapeHtml(st.full_name)}</strong>
                                ${isSelf ? '<span class="badge" style="background:#eff6ff; color:#2563eb; font-size:10px; font-weight:700;">You</span>' : ''}
                            </div>
                            <div style="font-size:11.5px; color:#64748b;">
                                ${escapeHtml(st.designation || 'Staff')} • <span style="color:#475569; font-weight:500;">${escapeHtml(st.department || 'General')}</span>
                            </div>
                            ${noteHtml}
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:12px; flex-shrink:0;">
                        <div style="text-align:right;">
                            <span class="badge ${st.status_badge}" style="font-size:11.5px; padding:4px 9px; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
                                <span>${st.status_emoji}</span> ${escapeHtml(st.status_label)}
                            </span>
                            <div style="font-size:10.5px; color:#94a3b8; margin-top:2px;">
                                ${st.is_away ? 'Away: ' + st.time_formatted : 'Active now'}
                            </div>
                        </div>
                        ${actionBtn}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function adminSetStaffAtDesk(targetUserId, staffName) {
        if (!confirm(`Mark ${staffName} as Back In Office / At Desk?`)) return;
        fetch('api.php?action=update_work_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: targetUserId, status: 'IN_OFFICE', note: 'Marked at desk by Lead/Admin' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showPresenceToast(`${staffName} marked In Office`);
                fetchAndRenderTeamPresence();
                updateTeamPresenceBadge();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function showPresenceToast(msg) {
        let toast = document.getElementById('presenceToastNotice');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'presenceToastNotice';
            toast.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#0f172a; color:#ffffff; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600; box-shadow:0 10px 25px rgba(0,0,0,0.2); z-index:999999; display:flex; align-items:center; gap:8px; transition:opacity 0.2s, transform 0.2s; opacity:0; transform:translateY(10px); pointer-events:none;';
            document.body.appendChild(toast);
        }
        toast.innerHTML = `<span>⚡</span> <span>${escapeHtml(msg)}</span>`;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
        }, 3200);
    }

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

    <?php if ($canUserChat): ?>
    // =========================================================
    // GLOBAL EMPLOYEE & GROUP CHAT ENGINE
    // =========================================================
    const hfCurrentChatUserId = <?= intval($user['id'] ?? 0) ?>;
    let hfChatCanCreateGroup = <?= json_encode($canUserCreateGroup) ?>;
    let hfChatOpen = false;
    let hfChatScreen = 'home'; // 'home' | 'create_group' | 'thread'
    let hfChatHomeTab = 'chats'; // 'chats' | 'employees' | 'groups'
    let hfChatEmployees = [];
    let hfChatConversations = [];
    let hfActiveConvId = 0;
    let hfActiveConvMeta = null;
    let hfSelectedGroupMembers = new Set();
    let hfChatLastMsgCount = 0;
    let hfChatPollTimer = null;

    document.addEventListener('DOMContentLoaded', () => {
        pollChatUnreadSummary();
        hfChatPollTimer = setInterval(() => {
            if (!hfChatOpen) {
                pollChatUnreadSummary();
            } else if (hfChatScreen === 'thread' && hfActiveConvId > 0) {
                loadActiveChatThread(false);
            } else if (hfChatScreen === 'home') {
                loadChatInitState(false);
            }
        }, 5500);
    });

    function updateChatBadgeCounts(count) {
        const c = parseInt(count) || 0;
        const txt = c > 99 ? '99+' : String(c);
        ['headerChatBadge', 'launcherChatBadge'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (c > 0) {
                el.textContent = txt;
                el.style.display = 'inline-block';
            } else {
                el.style.display = 'none';
            }
        });
    }

    function pollChatUnreadSummary() {
        fetch('api.php?action=chat_get_unread_summary')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                updateChatBadgeCounts(d.total_unread || 0);
                hfChatCanCreateGroup = !!d.can_create_group;
                syncCreateGroupPrivilegeUI();
            })
            .catch(() => {});
    }

    function syncCreateGroupPrivilegeUI() {
        const hdrBtn = document.getElementById('hfHeaderNewGroupBtn');
        const banner = document.getElementById('hfChatGroupPrivBanner');
        const sub = document.getElementById('hfChatPrivSubtitle');
        if (hdrBtn) hdrBtn.style.display = hfChatCanCreateGroup ? 'inline-flex' : 'none';
        if (banner) banner.style.display = (!hfChatCanCreateGroup && hfChatHomeTab === 'groups') ? 'block' : 'none';
        if (sub) sub.textContent = hfChatCanCreateGroup ? 'Direct & Group Create Enabled' : 'Employee Direct Chat';
    }

    function toggleEmployeeChatBox() {
        if (hfChatOpen) {
            closeEmployeeChatBox();
        } else {
            openEmployeeChatBox();
        }
    }

    function openEmployeeChatBox() {
        const box = document.getElementById('employeeChatBox');
        const launcher = document.getElementById('employeeChatLauncher');
        if (!box) return;
        hfChatOpen = true;
        box.style.display = 'flex';
        if (launcher) launcher.style.display = 'none';
        loadChatInitState(true);
    }

    function closeEmployeeChatBox() {
        const box = document.getElementById('employeeChatBox');
        const launcher = document.getElementById('employeeChatLauncher');
        if (!box) return;
        hfChatOpen = false;
        box.style.display = 'none';
        if (launcher) launcher.style.display = 'inline-flex';
    }

    function refreshEmployeeChatNow() {
        if (hfChatScreen === 'thread' && hfActiveConvId > 0) {
            loadActiveChatThread(true);
        } else {
            loadChatInitState(true);
        }
    }

    function setChatScreen(screen) {
        hfChatScreen = screen;
        const sHome = document.getElementById('hfChatHomeScreen');
        const sGrp = document.getElementById('hfChatCreateGroupScreen');
        const sThr = document.getElementById('hfChatThreadScreen');
        if (sHome) sHome.style.display = screen === 'home' ? 'flex' : 'none';
        if (sGrp) sGrp.style.display = screen === 'create_group' ? 'flex' : 'none';
        if (sThr) sThr.style.display = screen === 'thread' ? 'flex' : 'none';
    }

    function backToChatHome() {
        hfActiveConvId = 0;
        hfChatLastMsgCount = 0;
        const gp = document.getElementById('hfChatGroupPanel');
        if (gp) gp.style.display = 'none';
        setChatScreen('home');
        loadChatInitState(true);
    }

    function switchChatHomeTab(tab) {
        hfChatHomeTab = tab;
        ['chats', 'employees', 'groups'].forEach(t => {
            const btn = document.getElementById('hfSubTab_' + t);
            if (btn) btn.classList.toggle('active', t === tab);
        });
        syncCreateGroupPrivilegeUI();
        renderChatHomeLists();
    }

    function loadChatInitState(renderNow = true) {
        fetch('api.php?action=chat_init_state')
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    if (data.can_chat === false) {
                        const body = document.getElementById('hfChatHomeBody');
                        if (body) body.innerHTML = `<div style="padding:30px 16px; text-align:center; color:#dc2626; font-size:12.5px;">${escapeHtml(data.message || 'Chat disabled')}</div>`;
                    }
                    return;
                }
                hfChatCanCreateGroup = !!data.can_create_group;
                hfChatEmployees = data.employees || [];
                hfChatConversations = data.conversations || [];
                updateChatBadgeCounts(data.total_unread || 0);
                syncCreateGroupPrivilegeUI();
                if (renderNow) renderChatHomeLists();
            })
            .catch(() => {});
    }

    function formatChatTimeShort(dt) {
        if (!dt) return '';
        const parts = dt.split(' ');
        if (parts.length < 2) return dt;
        return parts[1].substring(0, 5);
    }

    function renderChatHomeLists() {
        const body = document.getElementById('hfChatHomeBody');
        if (!body) return;
        const q = (document.getElementById('hfChatSearchInput')?.value || '').trim().toLowerCase();

        if (hfChatHomeTab === 'employees') {
            const list = hfChatEmployees.filter(e =>
                !q ||
                (e.full_name || '').toLowerCase().includes(q) ||
                (e.department || '').toLowerCase().includes(q) ||
                (e.role_name || '').toLowerCase().includes(q)
            );
            if (!list.length) {
                body.innerHTML = `<div style="padding:32px 16px; text-align:center; color:#94a3b8; font-size:12.5px;">No matching employees found.</div>`;
                return;
            }
            let html = '';
            list.forEach(e => {
                const disabledChat = parseInt(e.can_chat ?? 1) === 0;
                html += `
                    <div class="hf-chat-list-item" onclick="${disabledChat ? "alert('Chat is disabled for this employee account.');" : `openDirectChatWith(${e.id})`}">
                        <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                            <div style="width:36px; height:36px; border-radius:50%; background:#dbeafe; color:#1e40af; font-weight:800; font-size:13px; display:flex; align-items:center; justify-content:center; flex-shrink:0; position:relative;">
                                ${escapeHtml((e.full_name || 'U').substring(0, 2).toUpperCase())}
                                <span style="position:absolute; bottom:-2px; right:-2px; font-size:11px; line-height:1;">${e.status_emoji || '🟢'}</span>
                            </div>
                            <div style="min-width:0;">
                                <div style="font-size:13px; font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(e.full_name)}</div>
                                <div style="font-size:11px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(e.role_name || 'Staff')} • ${escapeHtml(e.department || 'General')}</div>
                            </div>
                        </div>
                        <span class="btn btn-outline btn-sm" style="font-size:11px; padding:3px 9px; flex-shrink:0; ${disabledChat ? 'opacity:0.5;' : 'color:#2563eb; border-color:#bfdbfe;'}">
                            ${disabledChat ? 'Disabled' : 'Chat'}
                        </span>
                    </div>
                `;
            });
            body.innerHTML = html;
            return;
        }

        // 'chats' or 'groups'
        let convs = hfChatConversations.slice();
        if (hfChatHomeTab === 'groups') {
            convs = convs.filter(c => c.type === 'group');
        }
        if (q) {
            convs = convs.filter(c =>
                (c.display_title || '').toLowerCase().includes(q) ||
                (c.display_subtitle || '').toLowerCase().includes(q) ||
                (c.last_message || '').toLowerCase().includes(q)
            );
        }

        let topActionHtml = '';
        if (hfChatHomeTab === 'groups' && hfChatCanCreateGroup) {
            topActionHtml = `
                <div style="padding:10px 12px; background:#eff6ff; border-bottom:1px solid #bfdbfe; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:12px; font-weight:700; color:#1e40af;">Start a team group discussion</span>
                    <button type="button" class="btn btn-primary btn-sm" style="font-size:11.5px; padding:5px 11px;" onclick="openChatCreateGroupView()">+ Create Group</button>
                </div>
            `;
        }

        if (!convs.length) {
            const emptyMsg = hfChatHomeTab === 'groups'
                ? 'No group chats yet.'
                : 'No chats started yet. Pick an employee from 👤 Employees or create a group!';
            body.innerHTML = topActionHtml + `<div style="padding:34px 18px; text-align:center; color:#94a3b8; font-size:12.5px;">${emptyMsg}</div>`;
            return;
        }

        let html = topActionHtml;
        convs.forEach(c => {
            const unread = parseInt(c.unread_count || 0);
            const previewText = c.last_message
                ? ((c.last_sender_name ? c.last_sender_name.split(' ')[0] + ': ' : '') + c.last_message)
                : (c.display_subtitle || 'No messages yet');
            html += `
                <div class="hf-chat-list-item" onclick="openConversationThread(${c.id})">
                    <div style="display:flex; align-items:center; gap:10px; min-width:0; flex:1;">
                        <div style="width:38px; height:38px; border-radius:50%; background:${c.type === 'group' ? '#f3e8ff' : '#dbeafe'}; color:${c.type === 'group' ? '#6b21a8' : '#1d4ed8'}; font-weight:800; font-size:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            ${c.type === 'group' ? '👥' : (c.peer_status_emoji || '💬')}
                        </div>
                        <div style="min-width:0; flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:6px;">
                                <span style="font-size:13px; font-weight:${unread > 0 ? '800' : '700'}; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(c.display_title)}</span>
                                <span style="font-size:10.5px; color:#94a3b8; flex-shrink:0;">${formatChatTimeShort(c.last_message_at)}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; margin-top:2px;">
                                <span style="font-size:11.5px; color:${unread > 0 ? '#0f172a' : '#64748b'}; font-weight:${unread > 0 ? '700' : '400'}; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(previewText)}</span>
                                ${unread > 0 ? `<span style="background:#ef4444; color:#ffffff; font-size:10px; font-weight:800; padding:1px 7px; border-radius:999px; flex-shrink:0;">${unread}</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        body.innerHTML = html;
    }

    function openDirectChatWith(targetUserId) {
        fetch('api.php?action=chat_open_direct', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_user_id: targetUserId })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.conversation_id) {
                alert(data.message || 'Could not open direct chat.');
                return;
            }
            openConversationThread(data.conversation_id);
        })
        .catch(() => alert('Network error opening direct chat.'));
    }

    function openChatCreateGroupView() {
        if (!hfChatCanCreateGroup) {
            alert('You do not have Create Group privilege enabled.');
            return;
        }
        hfSelectedGroupMembers = new Set();
        document.getElementById('hfNewGroupTitle').value = '';
        document.getElementById('hfNewGroupDesc').value = '';
        document.getElementById('hfNewGroupEmpSearch').value = '';
        setChatScreen('create_group');

        if (!hfChatEmployees.length) {
            fetch('api.php?action=chat_init_state')
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        hfChatEmployees = d.employees || [];
                        renderCreateGroupMembersPicker();
                    }
                });
        } else {
            renderCreateGroupMembersPicker();
        }
    }

    function toggleNewGroupMember(empId) {
        const id = parseInt(empId);
        if (hfSelectedGroupMembers.has(id)) {
            hfSelectedGroupMembers.delete(id);
        } else {
            hfSelectedGroupMembers.add(id);
        }
        renderCreateGroupMembersPicker();
    }

    function renderCreateGroupMembersPicker() {
        const listEl = document.getElementById('hfNewGroupMemberList');
        const cntEl = document.getElementById('hfNewGroupSelCount');
        if (cntEl) cntEl.textContent = `${hfSelectedGroupMembers.size} Selected`;
        if (!listEl) return;

        const q = (document.getElementById('hfNewGroupEmpSearch')?.value || '').trim().toLowerCase();
        const filtered = hfChatEmployees.filter(e =>
            parseInt(e.can_chat ?? 1) === 1 &&
            (!q || (e.full_name || '').toLowerCase().includes(q) || (e.department || '').toLowerCase().includes(q) || (e.role_name || '').toLowerCase().includes(q))
        );

        if (!filtered.length) {
            listEl.innerHTML = `<div style="padding:16px; text-align:center; color:#94a3b8; font-size:12px;">No employees match search.</div>`;
            return;
        }

        let html = '';
        filtered.forEach(e => {
            const checked = hfSelectedGroupMembers.has(parseInt(e.id));
            html += `
                <label style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; border-bottom:1px solid #f1f5f9; cursor:pointer; background:${checked ? '#eff6ff' : '#ffffff'}; margin:0;">
                    <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                        <input type="checkbox" ${checked ? 'checked' : ''} onchange="toggleNewGroupMember(${e.id})" style="width:15px; height:15px; cursor:pointer;">
                        <div style="min-width:0;">
                            <div style="font-size:12.5px; font-weight:700; color:#0f172a;">${e.status_emoji || '🟢'} ${escapeHtml(e.full_name)}</div>
                            <div style="font-size:10.5px; color:#64748b;">${escapeHtml(e.role_name || 'Staff')} • ${escapeHtml(e.department || 'General')}</div>
                        </div>
                    </div>
                </label>
            `;
        });
        listEl.innerHTML = html;
    }

    function submitChatCreateGroup() {
        const title = (document.getElementById('hfNewGroupTitle')?.value || '').trim();
        const description = (document.getElementById('hfNewGroupDesc')?.value || '').trim();
        const memberIds = Array.from(hfSelectedGroupMembers);

        if (!title) {
            alert('Please enter a Group Name.');
            return;
        }
        if (memberIds.length === 0) {
            alert('Please select at least 1 employee to add to the group.');
            return;
        }

        const btn = document.getElementById('hfCreateGroupSubmitBtn');
        if (btn) btn.disabled = true;

        fetch('api.php?action=chat_create_group', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, description, member_ids: memberIds })
        })
        .then(r => r.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (!data.success) {
                alert(data.message || 'Could not create group.');
                return;
            }
            loadChatInitState(false);
            openConversationThread(data.conversation_id);
        })
        .catch(() => {
            if (btn) btn.disabled = false;
            alert('Network error creating group.');
        });
    }

    function openConversationThread(convId) {
        hfActiveConvId = parseInt(convId);
        hfChatLastMsgCount = 0;
        const gp = document.getElementById('hfChatGroupPanel');
        if (gp) gp.style.display = 'none';
        setChatScreen('thread');
        loadActiveChatThread(true);
    }

    function loadActiveChatThread(forceScrollBottom = false) {
        if (!hfActiveConvId) return;
        fetch('api.php?action=chat_get_messages&conversation_id=' + encodeURIComponent(hfActiveConvId))
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Unable to load conversation.');
                    return;
                }
                const conv = data.conversation || {};
                const parts = data.participants || [];
                const msgs = data.messages || [];
                hfChatCanCreateGroup = !!data.can_create_group;
                hfActiveConvMeta = { conv, participants: parts, myGroupRole: data.my_group_role };

                const titleEl = document.getElementById('hfThreadTitle');
                const subEl = document.getElementById('hfThreadSubtitle');
                const grpBtn = document.getElementById('hfThreadMembersBtn');

                if (conv.type === 'group') {
                    if (titleEl) titleEl.textContent = '👥 ' + (conv.title || 'Group Chat');
                    if (subEl) subEl.textContent = parts.length + ' members' + (conv.description ? ' • ' + conv.description : '');
                    if (grpBtn) grpBtn.style.display = 'inline-flex';
                    renderGroupPanelParticipants(parts, data.my_group_role);
                } else {
                    const peer = parts.find(p => parseInt(p.id) !== hfCurrentChatUserId);
                    if (titleEl) titleEl.textContent = peer ? peer.full_name : 'Direct Chat';
                    if (subEl) subEl.textContent = peer ? ((peer.role_name || 'Staff') + ' • ' + (peer.department || 'General')) : '';
                    if (grpBtn) grpBtn.style.display = 'none';
                }

                const feed = document.getElementById('hfChatMessagesFeed');
                if (feed) {
                    const wasNearBottom = (feed.scrollHeight - feed.scrollTop - feed.clientHeight) < 80;
                    if (!msgs.length) {
                        feed.innerHTML = `<div class="hf-chat-system-msg">No messages yet. Say hello! 👋</div>`;
                    } else {
                        let html = '';
                        msgs.forEach(m => {
                            if (m.message_type === 'system') {
                                html += `<div class="hf-chat-system-msg">${escapeHtml(m.message)}</div>`;
                                return;
                            }
                            const isMine = parseInt(m.sender_id) === hfCurrentChatUserId;
                            html += `
                                <div class="hf-chat-bubble-row ${isMine ? 'mine' : 'theirs'}">
                                    ${!isMine ? `<div style="font-size:10.5px; font-weight:700; color:#475569; margin:0 0 2px 4px;">${escapeHtml(m.sender_name || '')} <span style="font-weight:500; color:#94a3b8;">${escapeHtml(m.sender_role || '')}</span></div>` : ''}
                                    <div class="hf-chat-bubble">${escapeHtml(m.message)}</div>
                                    <div style="font-size:10px; color:#94a3b8; margin-top:2px; padding:0 4px;">${formatChatTimeShort(m.created_at)}</div>
                                </div>
                            `;
                        });
                        feed.innerHTML = html;
                    }
                    if (forceScrollBottom || wasNearBottom || msgs.length !== hfChatLastMsgCount) {
                        feed.scrollTop = feed.scrollHeight;
                    }
                    hfChatLastMsgCount = msgs.length;
                }

                pollChatUnreadSummary();
            })
            .catch(() => {});
    }

    function toggleChatGroupPanel() {
        const gp = document.getElementById('hfChatGroupPanel');
        if (!gp) return;
        gp.style.display = gp.style.display === 'none' ? 'block' : 'none';
    }

    function renderGroupPanelParticipants(participants, myGroupRole) {
        const listEl = document.getElementById('hfGroupParticipantsList');
        const addRow = document.getElementById('hfAddGroupMemberRow');
        const addSel = document.getElementById('hfAddMemberSelect');
        if (listEl) {
            listEl.innerHTML = participants.map(p => `
                <span style="display:inline-flex; align-items:center; gap:4px; background:#ffffff; border:1px solid #cbd5e1; border-radius:999px; padding:3px 9px; font-size:11px; font-weight:600; color:#1e293b;">
                    ${escapeHtml(p.full_name)}${p.group_role === 'admin' ? ' <small style="color:#2563eb; font-weight:800;">★Admin</small>' : ''}
                </span>
            `).join('');
        }
        const canManageGroup = (myGroupRole === 'admin' || hfChatCanCreateGroup);
        if (addRow && addSel) {
            addRow.style.display = canManageGroup ? 'flex' : 'none';
            const existingIds = new Set(participants.map(p => parseInt(p.id)));
            const candidates = hfChatEmployees.filter(e => !existingIds.has(parseInt(e.id)) && parseInt(e.can_chat ?? 1) === 1);
            addSel.innerHTML = '<option value="">+ Add Employee to Group...</option>' +
                candidates.map(e => `<option value="${e.id}">${escapeHtml(e.full_name)} (${escapeHtml(e.role_name || 'Staff')})</option>`).join('');
        }
    }

    function addSelectedMemberToGroup() {
        const sel = document.getElementById('hfAddMemberSelect');
        const targetId = parseInt(sel?.value || 0);
        if (!targetId || !hfActiveConvId) return;

        fetch('api.php?action=chat_add_group_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: hfActiveConvId, user_id: targetId })
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                alert(d.message || 'Could not add member.');
                return;
            }
            loadActiveChatThread(true);
        });
    }

    function leaveActiveGroupChat() {
        if (!hfActiveConvId || !confirm('Leave this group chat?')) return;
        fetch('api.php?action=chat_leave_group', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: hfActiveConvId })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                backToChatHome();
            } else {
                alert(d.message || 'Could not leave group.');
            }
        });
    }

    function sendActiveChatMessage() {
        const input = document.getElementById('hfChatMsgInput');
        if (!input || !hfActiveConvId) return;
        const text = input.value.trim();
        if (!text) return;

        input.value = '';
        fetch('api.php?action=chat_send_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: hfActiveConvId, message: text })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadActiveChatThread(true);
            } else {
                input.value = text;
                alert(data.message || 'Failed to send message.');
            }
        })
        .catch(() => {
            input.value = text;
            alert('Network error sending message.');
        });
    }
    <?php endif; ?>
</script>
