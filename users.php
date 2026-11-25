<?php
// users.php - User & Team Lead Management Portal

require_once __DIR__ . '/auth.php';
checkAuth('user-management');

$user = getCurrentUser();
$db = getDbConnection();

$isSuperAdmin = ($user['role'] === 'Super Admin' || ($user['role_id'] ?? 0) == 1);
$isTeamLead = ($user['role'] === 'Team Lead' || ($user['role_id'] ?? 0) == 4);

if (!$isSuperAdmin && !$isTeamLead) {
    echo "<div style='font-family:sans-serif; padding:40px; text-align:center;'><h2>Access Denied</h2><p>Only Super Admins and Team Leads can access User Management.</p><a href='index.php'>Return to Dashboard</a></div>";
    exit;
}

$pageBreadcrumb = "Module 1";
$pageTitle = "User & Team Lead Management Portal";
$pageSubtitle = "Manage administrative permissions, developer task assignments, roles, and departmental access.";
require_once __DIR__ . '/header.php';

// Fetch all roles
$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

// Fetch department list
try {
    $departments = $db->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $departments = [];
}
if (empty($departments)) {
    $departments = ['Development', 'Management', 'Sales', 'Design / UI/UX', 'Analytics', 'Support', 'Quality Assurance', 'Marketing'];
}
?>

<style>
/* Scoped modern styling for User Management */
.cm-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 22px;
}
@media (max-width: 1100px) {
    .cm-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 600px) {
    .cm-kpi-grid {
        grid-template-columns: 1fr;
    }
}

.cm-kpi-card {
    background: #ffffff;
    border-radius: 18px;
    padding: 22px 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.025);
    border: 1px solid #f1f5f9;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 120px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.cm-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
}
.cm-kpi-card::after {
    content: "";
    position: absolute;
    right: -25px;
    bottom: -25px;
    width: 130px;
    height: 130px;
    border-radius: 50%;
    pointer-events: none;
    opacity: 0.9;
}
.cm-card-purple::after {
    background: radial-gradient(circle, rgba(243, 232, 255, 0.85) 0%, rgba(250, 245, 255, 0) 70%);
}
.cm-card-green::after {
    background: radial-gradient(circle, rgba(236, 253, 245, 0.85) 0%, rgba(240, 253, 250, 0) 70%);
}
.cm-card-orange::after {
    background: radial-gradient(circle, rgba(255, 247, 237, 0.9) 0%, rgba(255, 251, 235, 0) 70%);
}
.cm-card-blue::after {
    background: radial-gradient(circle, rgba(239, 246, 255, 0.85) 0%, rgba(248, 250, 252, 0) 70%);
}

.cm-kpi-top {
    display: flex;
    align-items: center;
    gap: 12px;
}
.cm-kpi-icon-box {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.cm-icon-purple {
    background: #f5f3ff;
    color: #7c3aed;
}
.cm-icon-green {
    background: #ecfdf5;
    color: #10b981;
}
.cm-icon-orange {
    background: #fff7ed;
    color: #f97316;
}
.cm-icon-blue {
    background: #eff6ff;
    color: #0284c7;
}
.cm-kpi-icon-box svg {
    width: 18px;
    height: 18px;
}
.cm-kpi-title {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
}

.cm-kpi-bottom {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-top: 14px;
}
.cm-kpi-number {
    font-size: 30px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
    letter-spacing: -0.5px;
}
.cm-trend-pill {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1;
}

/* Action Buttons Row */
.cm-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.cm-bar-left, .cm-bar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.cm-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #5b21b6 0%, #6d28d9 100%);
    color: #ffffff !important;
    border: none;
    padding: 10px 24px;
    border-radius: 999px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(91, 33, 182, 0.28);
    transition: all 0.2s ease;
    text-decoration: none;
}
.cm-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(91, 33, 182, 0.38);
    color: #ffffff !important;
}
.cm-btn-pill-white {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    color: #1e293b !important;
    border: 1px solid #e2e8f0;
    padding: 9px 20px;
    border-radius: 999px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}
.cm-btn-pill-white:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}
.cm-btn-pill-white svg, .cm-btn-primary svg {
    width: 16px;
    height: 16px;
}

/* Filter & Search Bar */
.cm-filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    padding: 8px 18px;
    border-radius: 999px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.cm-search-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 260px;
}
.cm-search-wrapper svg {
    width: 17px;
    height: 17px;
    color: #94a3b8;
    flex-shrink: 0;
}
.cm-search-input {
    border: none;
    outline: none;
    font-size: 13.5px;
    color: #1e293b;
    width: 100%;
    background: transparent;
}
.cm-search-input::placeholder {
    color: #94a3b8;
}
.cm-filter-dropdowns {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.cm-filter-select {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 7px 16px;
    font-size: 13px;
    font-weight: 500;
    color: #334155;
    outline: none;
    cursor: pointer;
    transition: all 0.15s ease;
}
.cm-filter-select:hover, .cm-filter-select:focus {
    border-color: #cbd5e1;
    background: #f8fafc;
}

/* Table Card */
.cm-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.025);
    overflow: hidden;
}
.cm-table-wrapper {
    overflow-x: auto;
    width: 100%;
}
.cm-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.cm-table th {
    background: #ffffff;
    color: #64748b;
    font-size: 12.5px;
    font-weight: 600;
    padding: 16px 20px;
    text-align: left;
    border-bottom: 1px solid #f1f5f9;
    white-space: nowrap;
}
.cm-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f8fafc;
    vertical-align: middle;
    font-size: 13.5px;
    color: #334155;
}
.cm-table tbody tr {
    transition: background-color 0.15s ease;
}
.cm-table tbody tr:hover {
    background: #fafafa;
}
.cm-table tbody tr:last-child td {
    border-bottom: none;
}

/* Avatar circle */
.cm-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
    letter-spacing: -0.2px;
}
.cm-client-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 200px;
}
.cm-client-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.cm-company-link {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    text-decoration: none;
    transition: color 0.15s ease;
}
.cm-company-link:hover {
    color: #6d28d9;
}
.cm-contact-sub {
    font-size: 12px;
    color: #64748b;
}

/* Meta info */
.cm-meta-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12.5px;
    min-width: 170px;
}
.cm-meta-line {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #334155;
    white-space: nowrap;
}
.cm-meta-line svg {
    width: 13px;
    height: 13px;
    color: #94a3b8;
    flex-shrink: 0;
}

/* Portal Account Badge */
.cm-portal-pill {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    background: #ede9fe;
    color: #6d28d9;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

/* Status Pill */
.cm-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.cm-status-pill.cm-status-active {
    background: #ecfdf5;
    color: #059669;
}
.cm-status-pill.cm-status-suspended {
    background: #fef2f2;
    color: #dc2626;
}
.cm-status-dot {
    font-size: 10px;
    line-height: 1;
}

/* Action icons */
.cm-action-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
    background: #ffffff;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    padding: 0;
}
.cm-action-icon-btn:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #cbd5e1;
}
.cm-action-icon-btn svg {
    width: 15px;
    height: 15px;
}
</style>

<!-- Action Pills Navigation Bar -->
<div class="cm-action-bar">
    <div class="cm-bar-left">
        <button type="button" class="cm-btn-primary" onclick="openCreateUserModal()">
            <i data-feather="user-plus"></i>
            <span>+ Add New User Account</span>
        </button>
        <button type="button" class="cm-btn-pill-white" onclick="openManageDeptModal()">
            <i data-feather="folder"></i>
            <span>Department Roles &amp; Management</span>
        </button>
    </div>
    <div class="cm-bar-right">
        <button type="button" class="cm-btn-pill-white" onclick="openImportUsersModal()">
            <i data-feather="upload"></i>
            <span>Import Users (CSV)</span>
        </button>
    </div>
</div>

<!-- Metrics Cards -->
<div class="cm-kpi-grid">
    <div class="cm-kpi-card cm-card-purple">
        <div class="cm-kpi-top">
            <div class="cm-kpi-icon-box cm-icon-purple">
                <i data-feather="users"></i>
            </div>
            <span class="cm-kpi-title">Total System Users</span>
        </div>
        <div class="cm-kpi-bottom">
            <span class="cm-kpi-number" id="statTotalUsers">0</span>
            <span class="cm-trend-pill" style="background:#f3e8ff; color:#7c3aed;">All Accounts</span>
        </div>
    </div>

    <div class="cm-kpi-card cm-card-blue">
        <div class="cm-kpi-top">
            <div class="cm-kpi-icon-box cm-icon-blue">
                <i data-feather="shield"></i>
            </div>
            <span class="cm-kpi-title">Super Admins</span>
        </div>
        <div class="cm-kpi-bottom">
            <span class="cm-kpi-number" id="statAdmins">0</span>
            <span class="cm-trend-pill" style="background:#ede9fe; color:#6d28d9;">Privileged</span>
        </div>
    </div>

    <div class="cm-kpi-card cm-card-orange">
        <div class="cm-kpi-top">
            <div class="cm-kpi-icon-box cm-icon-orange">
                <i data-feather="award"></i>
            </div>
            <span class="cm-kpi-title">Team Leads</span>
        </div>
        <div class="cm-kpi-bottom">
            <span class="cm-kpi-number" id="statTeamLeads">0</span>
            <span class="cm-trend-pill" style="background:#fff7ed; color:#ea580c;">Department Heads</span>
        </div>
    </div>

    <div class="cm-kpi-card cm-card-green">
        <div class="cm-kpi-top">
            <div class="cm-kpi-icon-box cm-icon-green">
                <i data-feather="user-check"></i>
            </div>
            <span class="cm-kpi-title">Developers &amp; Staff</span>
        </div>
        <div class="cm-kpi-bottom">
            <span class="cm-kpi-number" id="statDevelopers">0</span>
            <span class="cm-trend-pill" style="background:#ecfdf5; color:#10b981;">Execution Team</span>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="cm-filter-bar">
    <div class="cm-search-wrapper">
        <i data-feather="search"></i>
        <input type="text" id="userSearchInput" class="cm-search-input" placeholder="Search name, username, email..." onkeyup="filterUsersTable()">
    </div>
    <div class="cm-filter-dropdowns">
        <select id="roleFilter" class="cm-filter-select" onchange="fetchUsersList()">
            <option value="0">All System Roles</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="deptFilter" class="cm-filter-select" onchange="fetchUsersList()">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="cm-btn-pill-white" onclick="fetchUsersList()" style="padding:7px 16px; font-size:12.5px;">
            <i data-feather="refresh-cw" style="width:13px; height:13px;"></i>
            <span>Refresh</span>
        </button>
    </div>
</div>

<!-- User Directory Table Card -->
<div class="cm-table-card">
    <div class="cm-table-wrapper">
        <table class="cm-table">
            <thead>
                <tr>
                    <th>User &amp; Identity</th>
                    <th>Email Address</th>
                    <th>System Role</th>
                    <th>Department</th>
                    <th>Account Status</th>
                    <th>Chat Privileges</th>
                    <th>Invite / Token</th>
                    <th>Created</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="usersTbody">
                <tr><td colspan="9" style="text-align:center; padding:35px; color:#64748b;">Loading user accounts...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: CREATE NEW USER & ASSIGN ROLE/DEPT  -->
<!-- ========================================== -->
<div id="createUserModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">Create New User / Team Lead Account</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('createUserModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateUser();">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" id="nu_full_name" class="form-control" required placeholder="e.g. Rahul Developer">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" id="nu_username" class="form-control" required placeholder="e.g. dev_rahul">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" id="nu_email" class="form-control" required placeholder="e.g. rahul@hemitodigital.com">
                </div>

                <!-- Password Setup Mode Selection -->
                <div class="form-group" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:14px; margin-bottom:18px;">
                    <label class="form-label" style="color:#0f172a; font-weight:700;">Password Creation Mode</label>
                    <div style="display:flex; gap:16px; margin-top:8px;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:13px;">
                            <input type="radio" name="nu_pass_mode" value="set_now" checked onchange="togglePasswordInputMode()">
                            <span>Set Initial Password Now</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:13px; color:#2563eb; font-weight:600;">
                            <input type="radio" name="nu_pass_mode" value="user_signup" onchange="togglePasswordInputMode()">
                            <span>User Will Set Own Password via Signup Link 🔗</span>
                        </label>
                    </div>

                    <div id="passwordInputContainer" style="margin-top:12px;">
                        <input type="password" id="nu_password" class="form-control" placeholder="Enter initial custom password">
                    </div>
                    <div id="selfSignupNotice" style="display:none; margin-top:10px; font-size:12px; color:#1d4ed8; background:#eff6ff; padding:8px 12px; border-radius:6px;">
                        💡 <strong>Self Signup Enabled:</strong> A unique setup invite link will be generated. The created user can open this link to set their own custom password!
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">System Role *</label>
                        <select id="nu_role_id" class="form-select" required>
                            <option value="">-- Choose Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <?php 
                                    if ($isTeamLead && !$isSuperAdmin && ($r['id'] == 1 || $r['id'] == 4)) continue;
                                ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isTeamLead && !$isSuperAdmin): ?>
                            <small style="color:#64748b; font-size:11px;">(Team Leads can create Developers, Sales, Agency Admins, etc.)</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department Selection *</label>
                        <select id="nu_department" class="form-select" required>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= htmlspecialchars($d) ?>" <?= $d === 'Development' ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Chat & Create Group Privileges -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:14px;">
                    <div style="font-size:12px; font-weight:700; color:#1e293b; margin-bottom:8px;">💬 Internal Employee Chat & Group Privileges</div>
                    <div style="display:flex; gap:18px; flex-wrap:wrap;">
                        <label style="display:flex; align-items:center; gap:7px; font-size:13px; cursor:pointer; margin:0;">
                            <input type="checkbox" id="nu_can_chat" checked style="width:16px; height:16px;" onchange="if(!this.checked){ document.getElementById('nu_can_create_group').checked = false; }">
                            <span>Allow Employee Chat</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:7px; font-size:13px; cursor:pointer; margin:0;">
                            <input type="checkbox" id="nu_can_create_group" checked style="width:16px; height:16px;" onchange="if(this.checked){ document.getElementById('nu_can_chat').checked = true; }">
                            <span>Allow Create Group in Chat Box</span>
                        </label>
                    </div>
                </div>

                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:12px; font-size:12px; color:#1e40af;">
                    <strong>Module Access Auto-Assignment:</strong> Selecting a role will automatically grant relevant module permissions (e.g. Team Leads get Project & Task Management access; Sales Managers get Client & Proposal access).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User Account</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT USER ACCOUNT                   -->
<!-- ========================================== -->
<div id="editUserModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit User Account</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitEditUser();">
            <div class="modal-body">
                <input type="hidden" id="eu_user_id">
                
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" id="eu_full_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" id="eu_email" class="form-control" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">System Role *</label>
                        <select id="eu_role_id" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <?php 
                                    if ($isTeamLead && !$isSuperAdmin && ($r['id'] == 1 || $r['id'] == 4)) continue;
                                ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department *</label>
                        <select id="eu_department" class="form-select" required>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Chat & Create Group Privileges -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-top:4px;">
                    <div style="font-size:12px; font-weight:700; color:#1e293b; margin-bottom:8px;">💬 Internal Employee Chat & Group Privileges</div>
                    <div style="display:flex; gap:18px; flex-wrap:wrap;">
                        <label style="display:flex; align-items:center; gap:7px; font-size:13px; cursor:pointer; margin:0;">
                            <input type="checkbox" id="eu_can_chat" style="width:16px; height:16px;" onchange="if(!this.checked){ document.getElementById('eu_can_create_group').checked = false; }">
                            <span>Allow Employee Chat</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:7px; font-size:13px; cursor:pointer; margin:0;">
                            <input type="checkbox" id="eu_can_create_group" style="width:16px; height:16px;" onchange="if(this.checked){ document.getElementById('eu_can_chat').checked = true; }">
                            <span>Allow Create Group in Chat Box</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: DEPARTMENT ROLES & MANAGEMENT       -->
<!-- ========================================== -->
<div id="manageDeptModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:750px;">
        <div class="modal-header">
            <h3 class="modal-title">🏢 Department & System Roles Management</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('manageDeptModal')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Modal Navigation Tabs -->
            <div style="display:flex; gap:10px; border-bottom:2px solid #e2e8f0; margin-bottom:18px;">
                <button type="button" id="tabBtnDept" class="btn btn-sm btn-primary" onclick="switchDeptModalTab('dept')" style="border-radius:6px 6px 0 0; padding:8px 16px; font-weight:700;">
                    📁 Department Roles
                </button>
                <button type="button" id="tabBtnRoles" class="btn btn-sm btn-outline" onclick="switchDeptModalTab('roles')" style="border-radius:6px 6px 0 0; padding:8px 16px; font-weight:700;">
                    🛡️ System Roles (Edit Roles)
                </button>
            </div>

            <!-- Tab 1: Department Roles -->
            <div id="deptTabContent">
                <form onsubmit="event.preventDefault(); submitCreateDeptInManager();" style="display:flex; gap:10px; margin-bottom:16px;">
                    <input type="text" id="md_new_dept_name" class="form-control" placeholder="Enter new department name..." required>
                    <button type="submit" class="btn btn-primary" style="white-space:nowrap;">+ Add Department</button>
                </form>

                <h4 style="font-size:13px; font-weight:700; color:#475569; margin-bottom:8px;">Existing Departments List</h4>
                <div class="table-responsive" style="max-height:280px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:6px;">
                    <table class="table" style="font-size:13px; margin:0;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th>Department Name</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="deptListTbody">
                            <tr><td colspan="2" style="text-align:center; padding:15px;">Loading departments...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: System Roles -->
            <div id="rolesTabContent" style="display:none;">
                <form onsubmit="event.preventDefault(); submitCreateRoleInManager();" style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
                    <input type="text" id="mr_new_role_name" class="form-control" placeholder="Role Name (e.g. Quality Manager)" required style="flex:1; min-width:180px;">
                    <input type="text" id="mr_new_role_desc" class="form-control" placeholder="Description (optional)" style="flex:2; min-width:220px;">
                    <button type="submit" class="btn btn-primary" style="white-space:nowrap;">+ Add Role</button>
                </form>

                <h4 style="font-size:13px; font-weight:700; color:#475569; margin-bottom:8px;">System Roles List</h4>
                <div class="table-responsive" style="max-height:280px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:6px;">
                    <table class="table" style="font-size:13px; margin:0;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="width:50px;">ID</th>
                                <th>Role Title</th>
                                <th>Description</th>
                                <th style="text-align:right; width:160px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="rolesListTbody">
                            <tr><td colspan="4" style="text-align:center; padding:15px;">Loading roles...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('manageDeptModal')">Close</button>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: BULK IMPORT USERS (CSV)             -->
<!-- ========================================== -->
<div id="importUsersModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:750px;">
        <div class="modal-header">
            <h3 class="modal-title">📥 Bulk Import User Accounts</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('importUsersModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitImportUsers();">
            <div class="modal-body">
                <div style="display:flex; justify-content:space-between; align-items:center; background:#f1f5f9; padding:12px; border-radius:8px; margin-bottom:16px;">
                    <div>
                        <strong style="font-size:13px; color:#0f172a;">Expected CSV Columns:</strong>
                        <div style="font-size:12px; color:#475569;"><code>Name, Email, Role, Department, Username (Optional)</code></div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="downloadSampleCsv()">
                        📄 Download Sample CSV
                    </button>
                </div>

                <!-- Password Mode Selection -->
                <div class="form-group" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px; margin-bottom:16px;">
                    <label class="form-label" style="color:#1e40af; font-weight:700; font-size:13px;">Import Password Option</label>
                    <div style="display:flex; gap:20px; margin-top:6px;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:13px; font-weight:600; color:#1d4ed8;">
                            <input type="radio" name="imp_pass_mode" value="invite" checked>
                            <span>Generate Self-Signup Invite Links 🔗</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:13px;">
                            <input type="radio" name="imp_pass_mode" value="default">
                            <span>Set Default Password (Welcome@123)</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Option A: Upload CSV File</label>
                    <input type="file" id="imp_file_input" class="form-control" accept=".csv, .txt" onchange="handleCsvFileUpload(event)">
                </div>

                <div class="form-group">
                    <label class="form-label">Option B: Or Paste CSV / Plain Text Rows</label>
                    <textarea id="imp_csv_text" class="form-control" rows="5" placeholder="Name, Email, Role, Department&#10;Rahul Sharma, rahul@hemitodigital.com, Developer, Development&#10;Priya Singh, priya@hemitodigital.com, Sales Manager, Sales" oninput="parseCsvInput()"></textarea>
                </div>

                <!-- Parsed Rows Preview -->
                <div id="impPreviewContainer" style="display:none; margin-top:16px;">
                    <h5 style="font-size:13px; font-weight:700; color:#0f172a; margin-bottom:8px;">Preview Import Rows (<span id="impRowCount">0</span> found)</h5>
                    <div class="table-responsive" style="max-height:200px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:6px;">
                        <table class="table" style="font-size:12px; margin:0;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th>#</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                </tr>
                            </thead>
                            <tbody id="impPreviewTbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importUsersModal')">Cancel</button>
                <button type="submit" id="impSubmitBtn" class="btn btn-primary" disabled>Import Users Now</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: RESET USER PASSWORD (SUPER ADMIN ONLY) -->
<!-- ========================================== -->
<div id="resetUserPasswordModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">🔑 Reset User Account Password</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('resetUserPasswordModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitResetUserPassword();">
            <div class="modal-body">
                <input type="hidden" id="rup_user_id" value="0">
                <p style="font-size:13px; color:#475569; margin-bottom:12px;">
                    Setting new password for <strong id="rup_user_name">User</strong> (<code id="rup_username">@username</code>)
                </p>
                <div class="form-group">
                    <label class="form-label">New Custom Password *</label>
                    <input type="text" id="rup_new_password" class="form-control" required placeholder="Enter new custom password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetUserPasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
    let usersCache = [];
    let parsedImportData = [];
    const isSuperAdmin = <?= json_encode($isSuperAdmin) ?>;
    const isTeamLead = <?= json_encode($isTeamLead) ?>;
    const currentUserId = <?= json_encode(intval($user['id'])) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        fetchUsersList();
        loadDepartmentsDropdown();
    });

    function loadDepartmentsDropdown() {
        fetch('api.php?action=get_departments')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.departments) {
                    const filterSel = document.getElementById('deptFilter');
                    const createSel = document.getElementById('nu_department');
                    const editSel = document.getElementById('eu_department');
                    
                    let html = '<option value="">All Departments</option>';
                    let modalHtml = '';

                    data.departments.forEach(d => {
                        html += `<option value="${escapeHtml(d.name)}">${escapeHtml(d.name)}</option>`;
                        modalHtml += `<option value="${escapeHtml(d.name)}">${escapeHtml(d.name)}</option>`;
                    });

                    if (filterSel) filterSel.innerHTML = html;
                    if (createSel) createSel.innerHTML = modalHtml;
                    if (editSel) editSel.innerHTML = modalHtml;
                }
            });
    }

    function fetchUsersList() {
        const roleId = document.getElementById('roleFilter').value;
        const dept = document.getElementById('deptFilter').value;
        let url = `api.php?action=get_users&role_id=${roleId}`;
        if (dept) url += `&department=${encodeURIComponent(dept)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    usersCache = data.users;
                    renderUsersTable(data.users);
                    updateUserMetrics(data.users);
                } else {
                    alert(data.message);
                }
            });
    }

    function renderUsersTable(users) {
        const tbody = document.getElementById('usersTbody');
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:30px; color:#94a3b8;">No users found matching criteria.</td></tr>';
            return;
        }

        const protocol = window.location.protocol;
        const host = window.location.host;

        const avatarPalettes = [
            { bg: '#f3e8ff', text: '#7c3aed' },
            { bg: '#e0f2fe', text: '#0284c7' },
            { bg: '#ffedd5', text: '#ea580c' },
            { bg: '#fce7f3', text: '#db2777' },
            { bg: '#cffafe', text: '#0891b2' },
            { bg: '#ecfdf5', text: '#059669' }
        ];

        let html = '';
        users.forEach((u, idx) => {
            const palette = avatarPalettes[idx % avatarPalettes.length];
            const names = (u.full_name || 'User').trim().split(/\s+/);
            let initials = names.length >= 2 ? (names[0][0] + names[1][0]).toUpperCase() : names[0].substring(0, 2).toUpperCase();

            let roleBadgeClass = 'cm-portal-pill';
            if (u.role_name.includes('Admin')) roleBadgeClass = 'cm-portal-pill';

            let editBtnHtml = '';
            let resetBtnHtml = '';
            let deleteBtnHtml = '';
            let actionButtonsHtml = '';
            const isTargetSuperAdmin = (parseInt(u.role_id) === 1 || u.role_name === 'Super Admin');

            if (!isSuperAdmin && isTargetSuperAdmin) {
                actionButtonsHtml = `<span class="cm-portal-pill" style="background:#f1f5f9; color:#64748b;">🔒 Protected</span>`;
            } else {
                editBtnHtml = `
                    <button type="button" class="cm-action-icon-btn" onclick="openEditUserModal(${u.id})" title="Edit User Details">
                        <i data-feather="edit-2"></i>
                    </button>
                `;
                if (isSuperAdmin) {
                    resetBtnHtml = `
                        <button type="button" class="cm-action-icon-btn" onclick="openResetUserPasswordModal(${u.id}, '${escapeHtml(u.username)}', '${escapeHtml(u.full_name)}')" title="Reset User Password">
                            <i data-feather="key"></i>
                        </button>
                    `;
                    if (parseInt(u.id) !== currentUserId) {
                        deleteBtnHtml = `
                            <button type="button" class="cm-action-icon-btn" style="color:#dc2626;" onclick="deleteUserAccount(${u.id}, '${escapeHtml(u.username)}')" title="Delete User Account">
                                <i data-feather="trash-2"></i>
                            </button>
                        `;
                    }
                }
                actionButtonsHtml = `
                    <div style="display:flex; justify-content:flex-end; gap:6px; align-items:center;">
                        ${editBtnHtml}
                        <button type="button" class="cm-action-icon-btn" style="color:${u.is_active ? '#dc2626' : '#16a34a'};" onclick="toggleUserStatus(${u.id})" title="${u.is_active ? 'Deactivate User' : 'Activate User'}">
                            <i data-feather="${u.is_active ? 'user-x' : 'user-check'}"></i>
                        </button>
                        ${resetBtnHtml}
                        ${deleteBtnHtml}
                    </div>
                `;
            }

            let signupLinkBtn = '';
            if (u.signup_token) {
                const signupUrl = `${protocol}//${host}/signup.php?token=${u.signup_token}`;
                signupLinkBtn = `
                    <button class="cm-btn-pill-white" style="margin-top:4px; font-size:11px; padding:3px 10px;" onclick="copySignupUrl('${signupUrl}')" title="Copy Signup / Password Link">
                        📋 Copy Invite Link
                    </button>
                `;
            }

            html += `
                <tr>
                    <td>
                        <div class="cm-client-cell">
                            <div class="cm-avatar" style="background:${palette.bg}; color:${palette.text};">
                                ${escapeHtml(initials)}
                            </div>
                            <div class="cm-client-info">
                                <span style="font-size:14px; font-weight:700; color:#0f172a;">${escapeHtml(u.full_name)}</span>
                                <span class="cm-contact-sub" style="font-family:monospace;">@${escapeHtml(u.username)}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="cm-meta-line">
                            <i data-feather="mail"></i>
                            <span>${escapeHtml(u.email)}</span>
                        </div>
                    </td>
                    <td><span class="cm-portal-pill" style="background:#eff6ff; color:#2563eb;">${escapeHtml(u.role_name)}</span></td>
                    <td><span class="cm-portal-pill" style="background:#f1f5f9; color:#475569;">${escapeHtml(u.department || 'General')}</span></td>
                    <td>
                        <span class="cm-status-pill ${u.is_active ? 'cm-status-active' : 'cm-status-suspended'}">
                            <span class="cm-status-dot">●</span> ${u.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        ${parseInt(u.role_id) === 7
                            ? `<span class="cm-portal-pill" style="background:#f1f5f9; color:#94a3b8; font-size:11px;">Client (N/A)</span>`
                            : (() => {
                                const canChat = parseInt(u.can_chat ?? 1) === 1;
                                const canGrp = canChat && parseInt(u.can_create_group ?? 1) === 1;
                                const canToggle = isSuperAdmin || !isTargetSuperAdmin;
                                return `
                                    <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                        <button type="button" ${canToggle ? '' : 'disabled'} onclick="toggleChatPrivilege(${u.id}, 'chat', ${canChat ? 1 : 0}, ${canGrp ? 1 : 0})" class="btn btn-sm" style="padding:2px 8px; font-size:11px; border-radius:999px; font-weight:700; border:1px solid ${canChat ? '#86efac' : '#fecaca'}; background:${canChat ? '#f0fdf4' : '#fef2f2'}; color:${canChat ? '#166534' : '#991b1b'}; cursor:${canToggle ? 'pointer' : 'not-allowed'};" title="Click to toggle Employee Chat privilege">
                                            ${canChat ? '💬 Chat: ON' : '🚫 Chat: OFF'}
                                        </button>
                                        <button type="button" ${canToggle ? '' : 'disabled'} onclick="toggleChatPrivilege(${u.id}, 'group', ${canChat ? 1 : 0}, ${canGrp ? 1 : 0})" class="btn btn-sm" style="padding:2px 8px; font-size:11px; border-radius:999px; font-weight:700; border:1px solid ${canGrp ? '#bfdbfe' : '#e2e8f0'}; background:${canGrp ? '#eff6ff' : '#f8fafc'}; color:${canGrp ? '#1d4ed8' : '#64748b'}; cursor:${canToggle ? 'pointer' : 'not-allowed'};" title="Click to toggle Create Group privilege in Chat Box">
                                            ${canGrp ? '👥 Create Group: ON' : '🔒 Group Create: OFF'}
                                        </button>
                                    </div>
                                `;
                            })()
                        }
                    </td>
                    <td>
                        ${u.signup_token ? '<span class="cm-status-pill cm-status-pending"><span class="cm-status-dot">●</span> Invite Active</span>' : '<span style="color:#64748b; font-size:12px;">Password Set</span>'}
                        <br>${signupLinkBtn}
                    </td>
                    <td style="font-size:12px; color:#64748b;">${u.created_at ? u.created_at.substring(0, 10) : 'N/A'}</td>
                    <td style="text-align:right;">
                        ${actionButtonsHtml}
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        if (window.feather) feather.replace();
    }

    function copySignupUrl(url) {
        navigator.clipboard.writeText(url).then(() => {
            alert('📋 Signup link copied to clipboard!\n\nSend this link to the user so they can create their password:\n' + url);
        }).catch(() => {
            prompt('Copy this signup invite URL:', url);
        });
    }

    function updateUserMetrics(users) {
        document.getElementById('statTotalUsers').textContent = users.length;
        document.getElementById('statAdmins').textContent = users.filter(u => u.role_name.includes('Admin')).length;
        document.getElementById('statTeamLeads').textContent = users.filter(u => u.role_name.includes('Lead')).length;
        document.getElementById('statDevelopers').textContent = users.filter(u => u.role_name.includes('Developer')).length;
    }

    function filterUsersTable() {
        const q = document.getElementById('userSearchInput').value.toLowerCase();
        const filtered = usersCache.filter(u => 
            u.full_name.toLowerCase().includes(q) ||
            u.username.toLowerCase().includes(q) ||
            u.email.toLowerCase().includes(q) ||
            u.role_name.toLowerCase().includes(q) ||
            (u.department && u.department.toLowerCase().includes(q))
        );
        renderUsersTable(filtered);
    }

    function openEditUserModal(userId) {
        const u = usersCache.find(x => parseInt(x.id) === parseInt(userId));
        if (!u) return;

        document.getElementById('eu_user_id').value = u.id;
        document.getElementById('eu_full_name').value = u.full_name;
        document.getElementById('eu_email').value = u.email;
        document.getElementById('eu_role_id').value = u.role_id;
        if (document.getElementById('eu_department')) {
            document.getElementById('eu_department').value = u.department || 'Development';
        }
        const canChat = parseInt(u.can_chat ?? 1) === 1;
        const canGrp = canChat && parseInt(u.can_create_group ?? 1) === 1;
        if (document.getElementById('eu_can_chat')) {
            document.getElementById('eu_can_chat').checked = canChat;
        }
        if (document.getElementById('eu_can_create_group')) {
            document.getElementById('eu_can_create_group').checked = canGrp;
        }

        document.getElementById('editUserModal').style.display = 'flex';
    }

    function toggleChatPrivilege(userId, which, currCanChat, currCanGroup) {
        let nextChat = currCanChat ? 1 : 0;
        let nextGroup = currCanGroup ? 1 : 0;
        if (which === 'chat') {
            nextChat = currCanChat ? 0 : 1;
            if (!nextChat) nextGroup = 0;
        } else {
            nextGroup = currCanGroup ? 0 : 1;
            if (nextGroup) nextChat = 1;
        }

        fetch('api.php?action=update_chat_privileges', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, can_chat: nextChat, can_create_group: nextGroup })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchUsersList();
                if (typeof pollChatUnreadSummary === 'function') pollChatUnreadSummary();
            } else {
                alert('Error: ' + (data.message || 'Could not update chat privilege.'));
            }
        });
    }

    function submitEditUser() {
        const canChat = document.getElementById('eu_can_chat')?.checked ? 1 : 0;
        const canCreateGroup = (canChat && document.getElementById('eu_can_create_group')?.checked) ? 1 : 0;
        const payload = {
            user_id: document.getElementById('eu_user_id').value,
            full_name: document.getElementById('eu_full_name').value,
            email: document.getElementById('eu_email').value,
            role_id: document.getElementById('eu_role_id').value,
            department: document.getElementById('eu_department').value,
            can_chat: canChat,
            can_create_group: canCreateGroup
        };

        fetch('api.php?action=update_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('editUserModal');
                alert('✅ ' + data.message);
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // --- DEPARTMENT & ROLE MANAGEMENT FUNCTIONS ---
    function switchDeptModalTab(tab) {
        const deptBtn = document.getElementById('tabBtnDept');
        const rolesBtn = document.getElementById('tabBtnRoles');
        const deptContent = document.getElementById('deptTabContent');
        const rolesContent = document.getElementById('rolesTabContent');

        if (tab === 'roles') {
            deptBtn.className = 'btn btn-sm btn-outline';
            rolesBtn.className = 'btn btn-sm btn-primary';
            deptContent.style.display = 'none';
            rolesContent.style.display = 'block';
            fetchRolesListForManager();
        } else {
            rolesBtn.className = 'btn btn-sm btn-outline';
            deptBtn.className = 'btn btn-sm btn-primary';
            rolesContent.style.display = 'none';
            deptContent.style.display = 'block';
            fetchDepartmentsList();
        }
    }

    function openManageDeptModal() {
        switchDeptModalTab('dept');
        document.getElementById('md_new_dept_name').value = '';
        document.getElementById('manageDeptModal').style.display = 'flex';
    }

    function fetchRolesListForManager() {
        fetch('api.php?action=get_roles')
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('rolesListTbody');
                if (data.success && data.roles.length > 0) {
                    let html = '';
                    data.roles.forEach(r => {
                        const isProtectedCoreRole = [1, 4, 5, 7].includes(parseInt(r.id));
                        let deleteBtn = '';
                        if (isSuperAdmin && !isProtectedCoreRole) {
                            deleteBtn = `
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteRoleInManager(${r.id}, '${escapeHtml(r.name)}')" style="margin-left:4px;">
                                    Delete
                                </button>
                            `;
                        } else if (isProtectedCoreRole) {
                            deleteBtn = `<span title="Core System Role Protected" style="font-size:11px; color:#94a3b8; margin-left:4px;">🔒 Core Role</span>`;
                        }

                        html += `
                            <tr>
                                <td style="font-weight:700; color:#64748b;">#${r.id}</td>
                                <td>
                                    <input type="text" id="role_name_input_${r.id}" class="form-control form-control-sm" value="${escapeHtml(r.name)}" style="font-weight:600;">
                                </td>
                                <td>
                                    <input type="text" id="role_desc_input_${r.id}" class="form-control form-control-sm" value="${escapeHtml(r.description || '')}" placeholder="Role description">
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button class="btn btn-outline btn-sm" onclick="updateRoleInManager(${r.id})">
                                        Save
                                    </button>
                                    ${deleteBtn}
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:15px; color:#94a3b8;">No system roles found.</td></tr>';
                }
            });
    }

    function submitCreateRoleInManager() {
        const name = document.getElementById('mr_new_role_name').value.trim();
        const desc = document.getElementById('mr_new_role_desc').value.trim();
        if (!name) return;

        fetch('api.php?action=create_role', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name, description: desc })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('mr_new_role_name').value = '';
                document.getElementById('mr_new_role_desc').value = '';
                fetchRolesListForManager();
                alert('✅ ' + data.message);
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function updateRoleInManager(roleId) {
        const nameInput = document.getElementById(`role_name_input_${roleId}`);
        const descInput = document.getElementById(`role_desc_input_${roleId}`);

        const name = nameInput ? nameInput.value.trim() : '';
        const desc = descInput ? descInput.value.trim() : '';

        if (!name) {
            alert('Role name cannot be empty.');
            return;
        }

        fetch('api.php?action=update_role', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: roleId, name: name, description: desc })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                fetchRolesListForManager();
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function deleteRoleInManager(roleId, roleName) {
        if (!confirm(`Are you sure you want to delete system role '${roleName}'?\n\nUsers with this role will be reassigned to Developer role.`)) {
            return;
        }

        fetch('api.php?action=delete_role', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: roleId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                fetchRolesListForManager();
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function fetchDepartmentsList() {
        fetch('api.php?action=get_departments')
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('deptListTbody');
                if (data.success && data.departments.length > 0) {
                    let html = '';
                    data.departments.forEach(d => {
                        const deptId = d.id || 0;
                        const deptName = d.name;
                        html += `
                            <tr>
                                <td>
                                    <input type="text" id="dept_input_${deptId}" class="form-control form-control-sm" value="${escapeHtml(deptName)}" style="max-width:240px; font-weight:600;">
                                </td>
                                <td style="text-align:right;">
                                    <button class="btn btn-outline btn-sm" onclick="renameDepartment(${deptId}, '${escapeHtml(deptName)}')">
                                        Rename
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteDepartment(${deptId}, '${escapeHtml(deptName)}')" style="margin-left:4px;">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding:15px; color:#94a3b8;">No departments found.</td></tr>';
                }
            });
    }

    function submitCreateDeptInManager() {
        const name = document.getElementById('md_new_dept_name').value.trim();
        if (!name) return;

        fetch('api.php?action=create_department', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('md_new_dept_name').value = '';
                fetchDepartmentsList();
                loadDepartmentsDropdown();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function renameDepartment(id, oldName) {
        const inputElem = document.getElementById(`dept_input_${id}`);
        const newName = inputElem ? inputElem.value.trim() : prompt('Enter new department name:', oldName);

        if (!newName || newName === oldName) return;

        fetch('api.php?action=update_department', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, old_name: oldName, new_name: newName })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                fetchDepartmentsList();
                loadDepartmentsDropdown();
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function deleteDepartment(id, name) {
        if (!confirm(`Are you sure you want to delete department '${name}'?\n\nUsers assigned to this department will be moved to 'General'.`)) {
            return;
        }

        fetch('api.php?action=delete_department', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, name: name })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                fetchDepartmentsList();
                loadDepartmentsDropdown();
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // --- BULK CSV USER IMPORT FUNCTIONS ---
    function openImportUsersModal() {
        document.getElementById('imp_file_input').value = '';
        document.getElementById('imp_csv_text').value = '';
        document.getElementById('impPreviewContainer').style.display = 'none';
        document.getElementById('impSubmitBtn').disabled = true;
        parsedImportData = [];
        document.getElementById('importUsersModal').style.display = 'flex';
    }

    function downloadSampleCsv() {
        const csvContent = "data:text/csv;charset=utf-8,Name,Email,Role,Department,Username\nRahul Sharma,rahul@hemitodigital.com,Developer,Development,rahul_s\nPriya Verma,priya@hemitodigital.com,Sales Manager,Sales,priya_v\nAmit Patel,amit@hemitodigital.com,Agency Admin,Management,amit_p";
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "sample_users_import.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function handleCsvFileUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result;
            document.getElementById('imp_csv_text').value = text;
            parseCsvInput();
        };
        reader.readAsText(file);
    }

    function parseCsvInput() {
        const rawText = document.getElementById('imp_csv_text').value.trim();
        if (!rawText) {
            document.getElementById('impPreviewContainer').style.display = 'none';
            document.getElementById('impSubmitBtn').disabled = true;
            parsedImportData = [];
            return;
        }

        const lines = rawText.split(/\r?\n/).filter(line => line.trim().length > 0);
        if (lines.length === 0) return;

        parsedImportData = [];
        let startIndex = 0;

        // Check if line 0 is header
        const firstLineLower = lines[0].toLowerCase();
        if (firstLineLower.includes('name') || firstLineLower.includes('email') || firstLineLower.includes('role')) {
            startIndex = 1; // Skip header line
        }

        for (let i = startIndex; i < lines.length; i++) {
            const cols = lines[i].split(',').map(c => c.trim().replace(/^["']|["']$/g, ''));
            if (cols.length >= 2 && cols[0] && cols[1]) {
                parsedImportData.push({
                    full_name: cols[0],
                    email: cols[1],
                    role: cols[2] || 'Developer',
                    department: cols[3] || 'General',
                    username: cols[4] || ''
                });
            }
        }

        const tbody = document.getElementById('impPreviewTbody');
        const previewContainer = document.getElementById('impPreviewContainer');
        const submitBtn = document.getElementById('impSubmitBtn');
        const countSpan = document.getElementById('impRowCount');

        if (parsedImportData.length > 0) {
            let html = '';
            parsedImportData.forEach((row, idx) => {
                html += `
                    <tr>
                        <td>${idx + 1}</td>
                        <td><strong>${escapeHtml(row.full_name)}</strong></td>
                        <td>${escapeHtml(row.email)}</td>
                        <td><span class="badge badge-primary">${escapeHtml(row.role)}</span></td>
                        <td><span class="badge badge-secondary">${escapeHtml(row.department)}</span></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
            countSpan.textContent = parsedImportData.length;
            previewContainer.style.display = 'block';
            submitBtn.disabled = false;
        } else {
            previewContainer.style.display = 'none';
            submitBtn.disabled = true;
        }
    }

    function submitImportUsers() {
        if (parsedImportData.length === 0) {
            alert('No valid user rows found to import.');
            return;
        }

        const modes = document.getElementsByName('imp_pass_mode');
        let selectedMode = 'invite';
        for (const m of modes) {
            if (m.checked) selectedMode = m.value;
        }

        const payload = {
            users: parsedImportData,
            set_own_password: (selectedMode === 'invite'),
            default_password: 'Welcome@123'
        };

        fetch('api.php?action=import_users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('importUsersModal');
                alert('✅ ' + data.message);
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function togglePasswordInputMode() {
        const modes = document.getElementsByName('nu_pass_mode');
        let selectedMode = 'set_now';
        for (const m of modes) {
            if (m.checked) selectedMode = m.value;
        }

        const passContainer = document.getElementById('passwordInputContainer');
        const passInput = document.getElementById('nu_password');
        const selfNotice = document.getElementById('selfSignupNotice');

        if (selectedMode === 'user_signup') {
            passContainer.style.display = 'none';
            passInput.removeAttribute('required');
            passInput.value = '';
            selfNotice.style.display = 'block';
        } else {
            passContainer.style.display = 'block';
            passInput.setAttribute('required', 'required');
            selfNotice.style.display = 'none';
        }
    }

    function openCreateUserModal() {
        document.getElementById('nu_full_name').value = '';
        document.getElementById('nu_username').value = '';
        document.getElementById('nu_email').value = '';
        document.getElementById('nu_password').value = '';
        if (document.getElementById('nu_can_chat')) document.getElementById('nu_can_chat').checked = true;
        if (document.getElementById('nu_can_create_group')) document.getElementById('nu_can_create_group').checked = true;
        document.getElementById('createUserModal').style.display = 'flex';
    }

    function submitCreateUser() {
        const modes = document.getElementsByName('nu_pass_mode');
        let selectedMode = 'set_now';
        for (const m of modes) {
            if (m.checked) selectedMode = m.value;
        }

        const canChat = document.getElementById('nu_can_chat')?.checked ? 1 : 0;
        const canCreateGroup = (canChat && document.getElementById('nu_can_create_group')?.checked) ? 1 : 0;
        const payload = {
            full_name: document.getElementById('nu_full_name').value,
            username: document.getElementById('nu_username').value,
            email: document.getElementById('nu_email').value,
            password: document.getElementById('nu_password').value,
            role_id: document.getElementById('nu_role_id').value,
            department: document.getElementById('nu_department').value,
            set_own_password: (selectedMode === 'user_signup'),
            can_chat: canChat,
            can_create_group: canCreateGroup
        };

        fetch('api.php?action=create_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('createUserModal');
                if (data.signup_url) {
                    alert('✅ ' + data.message + '\n\nSignup Invite Link:\n' + data.signup_url);
                } else {
                    alert('✅ ' + data.message);
                }
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function toggleUserStatus(userId) {
        fetch('api.php?action=toggle_user_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function openResetUserPasswordModal(userId, username, fullName) {
        document.getElementById('rup_user_id').value = userId;
        document.getElementById('rup_user_name').textContent = fullName;
        document.getElementById('rup_username').textContent = '@' + username;
        document.getElementById('rup_new_password').value = '';
        document.getElementById('resetUserPasswordModal').style.display = 'flex';
    }

    function submitResetUserPassword() {
        const userId = document.getElementById('rup_user_id').value;
        const newPassword = document.getElementById('rup_new_password').value;

        fetch('api.php?action=reset_user_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, password: newPassword })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('resetUserPasswordModal');
                alert(data.message);
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function deleteUserAccount(userId, username) {
        if (!confirm(`Are you sure you want to permanently delete user account '@${username}'?\n\nThis action cannot be undone.`)) {
            return;
        }

        fetch('api.php?action=delete_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                fetchUsersList();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    function escapeHtml(t) { return t ? t.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;") : ''; }
</script>

<!-- MODAL: RESET USER PASSWORD (SUPER ADMIN ONLY) -->
<div id="resetUserPasswordModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">Reset User Account Password</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('resetUserPasswordModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitResetUserPassword();">
            <div class="modal-body">
                <input type="hidden" id="rup_user_id" value="0">
                <p style="font-size:13px; color:#475569; margin-bottom:12px;">
                    Setting new password for <strong id="rup_user_name">User</strong> (<code id="rup_username">@username</code>)
                </p>
                <div class="form-group">
                    <label class="form-label">New Custom Password *</label>
                    <input type="text" id="rup_new_password" class="form-control" required placeholder="Enter new custom password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetUserPasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
