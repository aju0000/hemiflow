<?php
// worklog.php - Module: Employee & Management Daily Worklog Engine

require_once __DIR__ . '/auth.php';
checkAuth();

$user = getCurrentUser();
// Daily worklog feature hidden as requested - redirecting to projects
header('Location: projects.php');
exit;

$db = getDbConnection();

$isSuperAdmin = isSuperAdminUser($user);
$isHr = isHrAdmin();
$isLead = isLeadOrAdmin() || isTeamLeadOrManager();
$canViewOthers = $isSuperAdmin || $isHr || $isLead;

$pageBreadcrumb = "Employee Portal";
$pageTitle = "Daily Worklog";
$pageSubtitle = "Track daily work tasks, completion status, uncompleted work reasons, and employee accountability across teams.";
require_once __DIR__ . '/header.php';

// Fetch departments for filtering
$departments = $db->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Fetch internal staff for filtering (Super Admin, HR, Leads)
$allStaff = [];
if ($canViewOthers) {
    $allStaff = $db->query("
        SELECT u.id, u.full_name, u.department, r.name as role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.is_active = 1 AND r.name != 'Client' 
        ORDER BY u.full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    /* Top Statistics Bar */
    .wl-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }
    .wl-stat-card {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 18px;
        padding: 18px 20px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.035);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .wl-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
    }
    .wl-stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .wl-stat-label {
        font-size: 11.5px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .wl-stat-icon {
        width: 36px;
        height: 36px;
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
    }
    .wl-stat-val {
        font-size: 28px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }
    .wl-stat-desc {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 4px;
        font-weight: 500;
    }

    /* Missing staff alert bar */
    .wl-missing-banner {
        background: #fffbeb;
        border: 1px solid #fef3c7;
        border-left: 4px solid #f59e0b;
        border-radius: 14px;
        padding: 12px 18px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        box-shadow: 0 2px 10px rgba(245, 158, 11, 0.06);
    }

    /* Filter Toolbar */
    .wl-filter-bar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 16px 20px;
        margin-bottom: 22px;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.03);
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .wl-filter-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .wl-scope-tabs {
        display: inline-flex;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 13px;
        gap: 4px;
    }
    .wl-scope-btn {
        border: none;
        background: transparent;
        padding: 7px 16px;
        border-radius: 10px;
        font-size: 12.5px;
        font-weight: 700;
        color: #64748b;
        cursor: pointer;
        transition: all 0.16s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .wl-scope-btn.active {
        background: #ffffff;
        color: #4338ca;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
    }
    .wl-date-pills {
        display: inline-flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .wl-date-pill {
        border: 1px solid #e2e8f0;
        background: #ffffff;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        cursor: pointer;
        transition: all 0.16s ease;
    }
    .wl-date-pill:hover, .wl-date-pill.active {
        border-color: #818cf8;
        background: #eff6ff;
        color: #2563eb;
    }
    .wl-filter-bottom {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        border-top: 1px dashed #f1f5f9;
        padding-top: 12px;
    }

    /* Employee Worklog List Container */
    .wl-employees-wrapper {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Individual Employee Card */
    .wl-emp-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.035);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .wl-emp-card:hover {
        box-shadow: 0 8px 28px rgba(99, 102, 241, 0.06);
    }

    /* Employee Card Header */
    .wl-emp-header {
        padding: 16px 22px;
        background: #fafafa;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .wl-emp-profile {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .wl-emp-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        color: #ffffff;
        font-weight: 800;
        font-size: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        flex-shrink: 0;
    }
    .wl-emp-name {
        font-size: 15.5px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.25;
    }
    .wl-emp-badges {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 3px;
        flex-wrap: wrap;
    }
    .wl-emp-stat-badges {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* Clean Items Table */
    .wl-items-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    .wl-items-table th {
        background: #ffffff;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        padding: 12px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .wl-items-table td {
        padding: 14px 18px;
        font-size: 13px;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
    }
    .wl-items-table tr:last-child td {
        border-bottom: none;
    }
    .wl-items-table tr:hover td {
        background: #fbfcfe;
    }

    /* Work Title & Details */
    .wl-item-work {
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.35;
    }
    .wl-item-detail {
        font-size: 13px;
        color: #475569;
        line-height: 1.55;
        white-space: pre-line;
    }

    /* Badges */
    .wl-status-badge {
        font-weight: 700;
        font-size: 11.5px;
        padding: 4px 12px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    .wl-status-completed {
        background: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    .wl-status-notcompleted {
        background: #fef3c7;
        color: #b45309;
        border: 1px solid #fde68a;
    }

    /* Reason Chip */
    .wl-reason-chip {
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
        border-left: 3px solid #f59e0b;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.45;
        display: block;
        max-width: 380px;
    }

    /* Dynamic Add Modal Rows */
    .wl-dynamic-row {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px;
        margin-bottom: 14px;
        position: relative;
        transition: border-color 0.16s ease;
    }
    .wl-dynamic-row:hover {
        border-color: #cbd5e1;
    }
    .wl-dynamic-row-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .wl-row-counter {
        font-size: 12px;
        font-weight: 800;
        color: #4f46e5;
        background: #eef2ff;
        padding: 3px 10px;
        border-radius: 999px;
    }

    @media print {
        body * { visibility: hidden !important; }
        #worklogsPrintArea, #worklogsPrintArea * { visibility: visible !important; }
        #worklogsPrintArea { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>

<!-- Top Statistics Cards -->
<div class="wl-stats-grid no-print">
    <!-- Total Work Items -->
    <div class="wl-stat-card">
        <div class="wl-stat-header">
            <span class="wl-stat-label" id="lblTotalItems"><?= $canViewOthers ? 'Total Work Items (Filtered)' : 'My Work Items Today' ?></span>
            <div class="wl-stat-icon" style="background:#eff6ff; color:#2563eb;">📋</div>
        </div>
        <div class="wl-stat-val" id="statTotalItems">0</div>
        <div class="wl-stat-desc" id="statTotalSubtext">Work tasks recorded</div>
    </div>

    <!-- Completed Items -->
    <div class="wl-stat-card">
        <div class="wl-stat-header">
            <span class="wl-stat-label">Completed Work</span>
            <div class="wl-stat-icon" style="background:#dcfce7; color:#15803d;">✅</div>
        </div>
        <div class="wl-stat-val" id="statCompletedItems" style="color:#15803d;">0</div>
        <div class="wl-stat-desc" id="statCompletedSubtext">0% completion rate</div>
    </div>

    <!-- Not Completed Items -->
    <div class="wl-stat-card">
        <div class="wl-stat-header">
            <span class="wl-stat-label">Not Completed / Pending</span>
            <div class="wl-stat-icon" style="background:#fef3c7; color:#d97706;">⏳</div>
        </div>
        <div class="wl-stat-val" id="statNotCompletedItems" style="color:#d97706;">0</div>
        <div class="wl-stat-desc" id="statNotCompletedSubtext">With documented reasons</div>
    </div>

    <?php if ($canViewOthers): ?>
    <!-- Submissions Count Today -->
    <div class="wl-stat-card">
        <div class="wl-stat-header">
            <span class="wl-stat-label">Staff Submitted Today</span>
            <div class="wl-stat-icon" style="background:#f3e8ff; color:#7e22ce;">👥</div>
        </div>
        <div class="wl-stat-val" id="statSubmissionsToday">0</div>
        <div class="wl-stat-desc" id="statMissingText">Checking submission status...</div>
    </div>
    <?php else: ?>
    <!-- My Week Items -->
    <div class="wl-stat-card">
        <div class="wl-stat-header">
            <span class="wl-stat-label">My Work This Week</span>
            <div class="wl-stat-icon" style="background:#ecfdf5; color:#047857;">📈</div>
        </div>
        <div class="wl-stat-val" id="statWeekItems">0</div>
        <div class="wl-stat-desc">Monday – Sunday period</div>
    </div>
    <?php endif; ?>
</div>

<!-- Missing Staff Alert (For Admin, HR, Leads) -->
<?php if ($canViewOthers): ?>
<div id="missingStaffAlert" class="wl-missing-banner no-print" style="display:none;">
    <div style="display:flex; align-items:flex-start; gap:10px;">
        <span style="font-size:18px; line-height:1;">⚠️</span>
        <div>
            <strong style="color:#92400e; font-size:13px;" id="missingStaffTitle">Staff Missing Today's Worklog</strong>
            <div id="missingStaffList" style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;"></div>
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline" style="border:none; color:#b45309; font-size:16px; padding:0 6px;" onclick="document.getElementById('missingStaffAlert').style.display='none'">&times;</button>
</div>
<?php endif; ?>

<!-- Main Action & Filter Bar -->
<div class="wl-filter-bar no-print">
    <div class="wl-filter-top">
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <?php if ($canViewOthers): ?>
            <!-- Scope Switcher: My Logs vs Team/Company Logs -->
            <div class="wl-scope-tabs">
                <button type="button" class="wl-scope-btn active" id="scopeBtn_all" onclick="setWorklogScope('all')">
                    <i data-feather="users" style="width:14px; height:14px;"></i>
                    <span><?= $isSuperAdmin ? '🏢 All Company Logs' : ($isHr ? '🏢 All Employee Logs' : '👥 Team Worklogs') ?></span>
                </button>
                <button type="button" class="wl-scope-btn" id="scopeBtn_my" onclick="setWorklogScope('my')">
                    <i data-feather="user" style="width:14px; height:14px;"></i>
                    <span>👤 My Worklog Only</span>
                </button>
            </div>
            <?php endif; ?>

            <!-- Date Quick Selectors -->
            <div class="wl-date-pills">
                <button type="button" class="wl-date-pill active" id="datePill_today" onclick="setQuickDate('today')">Today</button>
                <button type="button" class="wl-date-pill" id="datePill_yesterday" onclick="setQuickDate('yesterday')">Yesterday</button>
                <button type="button" class="wl-date-pill" id="datePill_this_week" onclick="setQuickDate('this_week')">This Week</button>
                <button type="button" class="wl-date-pill" id="datePill_this_month" onclick="setQuickDate('this_month')">This Month</button>
                <button type="button" class="wl-date-pill" id="datePill_all" onclick="setQuickDate('all')">All Time</button>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" class="btn btn-outline btn-sm" onclick="exportWorklogsCsv()" title="Export filtered worklogs as CSV">
                <span>📥 Export CSV</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()" title="Print / PDF View">
                <span>🖨️ Print / PDF</span>
            </button>
            <button type="button" class="btn btn-primary" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; font-weight:700; border-radius:12px; padding:8px 18px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 14px rgba(99,102,241,0.3);" onclick="openAddWorklogModal()">
                <span>+ Add Daily Worklog</span>
            </button>
        </div>
    </div>

    <!-- Secondary Filters (Date, Role, Department, Staff, Status, Search) -->
    <div class="wl-filter-bottom">
        <div style="min-width:140px;">
            <input type="date" id="filterSpecificDate" class="form-control form-control-sm" style="height:36px; font-size:12.5px;" onchange="onDateInputChange()" value="<?= date('Y-m-d') ?>">
        </div>

        <?php if ($isSuperAdmin): ?>
        <!-- Super Admin Role Filter: Employees vs Leads vs HR -->
        <div style="min-width:160px;">
            <select id="filterRoleGroup" class="form-select form-select-sm" style="height:36px; font-size:12.5px; font-weight:700; color:#4338ca;" onchange="loadWorklogs()">
                <option value="all">Role: All Roles (Company)</option>
                <option value="employee">👤 Regular Employees</option>
                <option value="lead">🛡️ Team Leads</option>
                <option value="hr">💼 HR Members</option>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($canViewOthers): ?>
        <div style="min-width:150px;">
            <select id="filterDepartment" class="form-select form-select-sm" style="height:36px; font-size:12.5px;" onchange="loadWorklogs()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width:160px;">
            <select id="filterStaffMember" class="form-select form-select-sm" style="height:36px; font-size:12.5px;" onchange="loadWorklogs()">
                <option value="0">All Staff Members</option>
                <?php foreach ($allStaff as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['role_name'] ?? $s['department']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div style="min-width:140px;">
            <select id="filterStatus" class="form-select form-select-sm" style="height:36px; font-size:12.5px;" onchange="loadWorklogs()">
                <option value="">All Statuses</option>
                <option value="Completed">✅ Completed</option>
                <option value="Not Completed">⏳ Not Completed</option>
            </select>
        </div>

        <div style="flex:1; min-width:180px;">
            <input type="text" id="filterSearch" class="form-control form-control-sm" style="height:36px; font-size:12.5px;" placeholder="Search work, detail, reason, employee..." onkeyup="debounceLoadWorklogs()">
        </div>

        <button type="button" class="btn btn-outline btn-sm" style="height:36px; padding:0 12px;" onclick="resetWorklogFilters()">
            Reset
        </button>
    </div>
</div>

<!-- ========================================================= -->
<!-- WORKLOGS DISPLAY: LIST TYPE WITH EACH EMPLOYEE            -->
<!-- ========================================================= -->
<div id="worklogsPrintArea">
    <div id="worklogsContainer" class="wl-employees-wrapper">
        <!-- Rendered via JS -->
    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL: ADD DAILY WORKLOG (LIST TYPE ADD)                  -->
<!-- ========================================================= -->
<div id="addWorklogModal" class="modal-overlay" style="display:none; z-index:99999;" onclick="if(event.target === this) closeModal('addWorklogModal')">
    <div class="modal-content" style="max-width:760px; width:95%; border-radius:20px; overflow:hidden; padding:0;">
        <div class="modal-header" style="padding:18px 24px; background:linear-gradient(135deg, #4f46e5, #7c3aed); color:#ffffff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:18px;">
                    📝
                </div>
                <div>
                    <h3 class="modal-title" style="margin:0; color:#ffffff; font-size:17px; font-weight:800;" id="addModalTitle">Add Daily Worklog</h3>
                    <small style="color:rgba(255,255,255,0.85); font-size:11.5px;">Add your work items, completion status, and reasons in a list</small>
                </div>
            </div>
            <button class="btn btn-outline btn-sm" onclick="closeModal('addWorklogModal')" style="color:#ffffff; border-color:rgba(255,255,255,0.4); background:rgba(255,255,255,0.1); font-size:18px; line-height:1; padding:4px 10px; border-radius:8px;">&times;</button>
        </div>

        <form id="addWorklogForm" onsubmit="submitAddWorklogBatch(event)">
            <div class="modal-body" style="padding:22px 24px; max-height:75vh; overflow-y:auto;">
                <!-- Top Options: Date & Optional Staff Member (Admin) -->
                <div style="display:grid; grid-template-columns:<?= $isSuperAdmin ? '1fr 1.5fr' : '1fr' ?>; gap:14px; margin-bottom:18px;">
                    <div>
                        <label style="font-size:12px; font-weight:800; color:#334155; display:block; margin-bottom:5px;">Work Date *</label>
                        <input type="date" id="add_log_date" class="form-control" required style="height:38px; font-size:13px; font-weight:600;" value="<?= date('Y-m-d') ?>">
                    </div>

                    <?php if ($isSuperAdmin): ?>
                    <div>
                        <label style="font-size:12px; font-weight:800; color:#334155; display:block; margin-bottom:5px;">Logging For (Staff Member)</label>
                        <select id="add_user_id" class="form-select" style="height:38px; font-size:13px;">
                            <option value="<?= $user['id'] ?>">Self (<?= htmlspecialchars($user['full_name']) ?>)</option>
                            <?php foreach ($allStaff as $s): if ($s['id'] == $user['id']) continue; ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['role_name'] ?? $s['department']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" id="add_user_id" value="<?= $user['id'] ?>">
                    <?php endif; ?>
                </div>

                <!-- Work Items Section Header -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
                    <div style="font-size:13.5px; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:6px;">
                        <span>📋 Work Items List</span>
                        <span class="badge" id="itemCountBadge" style="background:#e0e7ff; color:#3730a3; font-weight:700; font-size:11px;">1 Item</span>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" style="font-size:12px; font-weight:700; color:#4338ca; border-color:#c7d2fe; padding:4px 12px; border-radius:8px;" onclick="addWorkItemRow()">
                        + Add Another Work Item
                    </button>
                </div>

                <!-- Dynamic Rows Container -->
                <div id="dynamicRowsContainer">
                    <!-- Rows generated via JS -->
                </div>
            </div>

            <div class="modal-footer" style="padding:14px 24px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addWorklogModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; font-weight:700; padding:9px 24px; border-radius:10px;" id="btnAddSave">
                    💾 Save Daily Worklog
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL: EDIT SINGLE WORK ITEM                              -->
<!-- ========================================================= -->
<div id="editWorkItemModal" class="modal-overlay" style="display:none; z-index:99999;" onclick="if(event.target === this) closeModal('editWorkItemModal')">
    <div class="modal-content" style="max-width:620px; width:95%; border-radius:20px; overflow:hidden; padding:0;">
        <div class="modal-header" style="padding:18px 24px; background:linear-gradient(135deg, #4f46e5, #7c3aed); color:#ffffff; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:18px;">
                    ✏️
                </div>
                <div>
                    <h3 class="modal-title" style="margin:0; color:#ffffff; font-size:17px; font-weight:800;">Edit Work Item</h3>
                    <small style="color:rgba(255,255,255,0.85); font-size:11.5px;">Update task details, completion status, or reason</small>
                </div>
            </div>
            <button class="btn btn-outline btn-sm" onclick="closeModal('editWorkItemModal')" style="color:#ffffff; border-color:rgba(255,255,255,0.4); background:rgba(255,255,255,0.1); font-size:18px; line-height:1; padding:4px 10px; border-radius:8px;">&times;</button>
        </div>

        <form id="editWorkItemForm" onsubmit="submitEditWorkItem(event)">
            <input type="hidden" id="edit_item_id" value="0">
            <div class="modal-body" style="padding:22px 24px; max-height:75vh; overflow-y:auto;">
                <!-- Date & Status Row -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                    <div>
                        <label style="font-size:12px; font-weight:800; color:#334155; display:block; margin-bottom:5px;">Work Date *</label>
                        <input type="date" id="edit_log_date" class="form-control" required style="height:38px; font-size:13px; font-weight:600;">
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:800; color:#334155; display:block; margin-bottom:5px;">Completion Status *</label>
                        <select id="edit_status" class="form-select" style="height:38px; font-size:13px; font-weight:700;" onchange="onEditStatusChange()">
                            <option value="Completed">✅ Completed</option>
                            <option value="Not Completed">⏳ Not Completed</option>
                        </select>
                    </div>
                </div>

                <!-- Work Title -->
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:800; color:#334155; display:block; margin-bottom:5px;">Work / Task *</label>
                    <input type="text" id="edit_title" class="form-control" required placeholder="e.g. Redesigned landing page navbar" style="height:38px; font-size:13.5px; font-weight:600;">
                </div>

                <!-- Work Detail -->
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:800; color:#334155; display:block; margin-bottom:5px;">Work Detail *</label>
                    <textarea id="edit_description" class="form-control" rows="3" required placeholder="Describe what was executed or milestones completed..." style="font-size:13px;"></textarea>
                </div>

                <!-- Reason Container (Visible if Not Completed) -->
                <div id="edit_reason_container" style="display:none; margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:800; color:#b45309; display:block; margin-bottom:5px;">⚠️ Reason Why Not Completed *</label>
                    <textarea id="edit_reason" class="form-control" rows="2" placeholder="State reason (e.g. Waiting for client assets, API limit, pending design sign-off...)" style="font-size:13px; border-color:#fde68a; background:#fffdf7;"></textarea>
                </div>
            </div>

            <div class="modal-footer" style="padding:14px 24px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editWorkItemModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; font-weight:700; padding:8px 22px; border-radius:10px;" id="btnEditSave">
                    💾 Update Work Item
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const currentUserId = <?= intval($user['id']) ?>;
    const currentUserName = <?= json_encode($user['full_name']) ?>;
    const currentUserRole = <?= json_encode($user['role'] ?? '') ?>;
    const isSuperAdmin = <?= json_encode($isSuperAdmin) ?>;
    const isHr = <?= json_encode($isHr) ?>;
    const isLead = <?= json_encode($isLead) ?>;
    const canViewOthers = <?= json_encode($canViewOthers) ?>;

    let currentWorklogScope = canViewOthers ? 'all' : 'my';
    let currentQuickDate = 'today';
    let worklogSearchTimer = null;
    let loadedWorklogs = [];
    let loadedEmployees = [];

    document.addEventListener('DOMContentLoaded', () => {
        loadWorklogs();
    });

    function setWorklogScope(scope) {
        currentWorklogScope = scope;
        const btnAll = document.getElementById('scopeBtn_all');
        const btnMy = document.getElementById('scopeBtn_my');
        if (btnAll) btnAll.classList.toggle('active', scope === 'all');
        if (btnMy) btnMy.classList.toggle('active', scope === 'my');
        
        const lbl = document.getElementById('lblTotalItems');
        if (lbl) {
            lbl.textContent = (scope === 'all' && canViewOthers) ? 'Total Work Items (Filtered)' : 'My Work Items Today';
        }

        loadWorklogs();
    }

    function setQuickDate(period) {
        currentQuickDate = period;
        document.querySelectorAll('.wl-date-pill').forEach(btn => btn.classList.remove('active'));
        const activeBtn = document.getElementById(`datePill_${period}`);
        if (activeBtn) activeBtn.classList.add('active');

        const dateInput = document.getElementById('filterSpecificDate');
        if (period === 'today') {
            const now = new Date();
            if (dateInput) dateInput.value = now.toISOString().substring(0, 10);
        } else if (period === 'yesterday') {
            const d = new Date();
            d.setDate(d.getDate() - 1);
            if (dateInput) dateInput.value = d.toISOString().substring(0, 10);
        } else {
            if (dateInput) dateInput.value = '';
        }

        loadWorklogs();
    }

    function onDateInputChange() {
        document.querySelectorAll('.wl-date-pill').forEach(btn => btn.classList.remove('active'));
        currentQuickDate = 'custom';
        loadWorklogs();
    }

    function debounceLoadWorklogs() {
        clearTimeout(worklogSearchTimer);
        worklogSearchTimer = setTimeout(() => loadWorklogs(), 260);
    }

    function resetWorklogFilters() {
        const dateInp = document.getElementById('filterSpecificDate');
        if (dateInp) dateInp.value = new Date().toISOString().substring(0, 10);
        
        const roleSel = document.getElementById('filterRoleGroup');
        if (roleSel) roleSel.value = 'all';

        const deptSel = document.getElementById('filterDepartment');
        if (deptSel) deptSel.value = '';

        const staffSel = document.getElementById('filterStaffMember');
        if (staffSel) staffSel.value = '0';

        const statusSel = document.getElementById('filterStatus');
        if (statusSel) statusSel.value = '';

        const searchInp = document.getElementById('filterSearch');
        if (searchInp) searchInp.value = '';

        setQuickDate('today');
    }

    function loadWorklogs() {
        const params = new URLSearchParams({ action: 'get_daily_worklogs' });
        params.set('view_scope', currentWorklogScope);

        const specificDate = document.getElementById('filterSpecificDate')?.value;
        if (specificDate) {
            params.set('date', specificDate);
        } else if (currentQuickDate === 'this_week') {
            const curr = new Date();
            const first = curr.getDate() - curr.getDay() + 1;
            const last = first + 6;
            const firstday = new Date(curr.setDate(first)).toISOString().substring(0, 10);
            const lastday = new Date(curr.setDate(last)).toISOString().substring(0, 10);
            params.set('start_date', firstday);
            params.set('end_date', lastday);
        } else if (currentQuickDate === 'this_month') {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            params.set('start_date', `${y}-${m}-01`);
            params.set('end_date', `${y}-${m}-31`);
        }

        const roleGroup = document.getElementById('filterRoleGroup')?.value;
        if (roleGroup && roleGroup !== 'all') params.set('role_filter', roleGroup);

        const dept = document.getElementById('filterDepartment')?.value;
        if (dept) params.set('department', dept);

        const staffId = document.getElementById('filterStaffMember')?.value;
        if (staffId && staffId !== '0') params.set('user_id', staffId);

        const status = document.getElementById('filterStatus')?.value;
        if (status) params.set('status', status);

        const search = document.getElementById('filterSearch')?.value?.trim();
        if (search) params.set('search', search);

        fetch('api.php?' + params.toString())
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Could not load daily worklogs.');
                    return;
                }
                loadedWorklogs = data.worklogs || [];
                loadedEmployees = data.worklogs_by_employee || [];
                updateStats(data.stats, data.missing_users);
                renderEmployeesList(loadedEmployees);
            })
            .catch(err => {
                console.error('Worklogs fetch error:', err);
            });
    }

    function updateStats(stats, missingUsers) {
        if (!stats) return;

        const statTotal = document.getElementById('statTotalItems');
        if (statTotal) {
            statTotal.textContent = stats.total_items || 0;
        }

        const statCompleted = document.getElementById('statCompletedItems');
        const statCompletedSub = document.getElementById('statCompletedSubtext');
        if (statCompleted) {
            const comp = stats.completed_count || 0;
            const total = stats.total_items || 0;
            const pct = total > 0 ? Math.round((comp / total) * 100) : 0;
            statCompleted.textContent = comp;
            if (statCompletedSub) statCompletedSub.textContent = `${pct}% completion rate`;
        }

        const statNotCompleted = document.getElementById('statNotCompletedItems');
        const statNotCompletedSub = document.getElementById('statNotCompletedSubtext');
        if (statNotCompleted) {
            const notComp = stats.not_completed_count || 0;
            statNotCompleted.textContent = notComp;
            if (statNotCompletedSub) {
                statNotCompletedSub.textContent = notComp > 0 ? `${notComp} pending task(s)` : 'All tasks completed';
            }
        }

        const statSubs = document.getElementById('statSubmissionsToday');
        if (statSubs) {
            statSubs.textContent = stats.submissions_today_count || '0';
        }

        const statWeekItems = document.getElementById('statWeekItems');
        if (statWeekItems) {
            statWeekItems.textContent = stats.my_items_week || '0';
        }

        const statMissingTxt = document.getElementById('statMissingText');
        const alertBox = document.getElementById('missingStaffAlert');
        const missingList = document.getElementById('missingStaffList');

        if (statMissingTxt) {
            const missingCnt = (missingUsers || []).length;
            statMissingTxt.textContent = `${missingCnt} staff have not logged work today`;
            statMissingTxt.style.color = missingCnt > 0 ? '#b45309' : '#15803d';
        }

        if (alertBox && missingList) {
            const missingCnt = (missingUsers || []).length;
            if (missingCnt > 0 && currentQuickDate === 'today' && currentWorklogScope === 'all') {
                alertBox.style.display = 'flex';
                let pillsHtml = '';
                missingUsers.slice(0, 12).forEach(u => {
                    pillsHtml += `<span class="badge" style="background:#fef3c7; color:#92400e; font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px;">${escapeHtml(u.full_name)} (${escapeHtml(u.department || 'Staff')})</span>`;
                });
                if (missingCnt > 12) {
                    pillsHtml += `<span class="badge" style="background:#e2e8f0; color:#475569; font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px;">+${missingCnt - 12} more</span>`;
                }
                missingList.innerHTML = pillsHtml;
            } else {
                alertBox.style.display = 'none';
            }
        }
    }

    // =========================================================
    // RENDER BY LIST TYPE WITH EACH EMPLOYEE
    // =========================================================
    function renderEmployeesList(employeeGroups) {
        const container = document.getElementById('worklogsContainer');
        if (!container) return;

        if (!employeeGroups || !employeeGroups.length) {
            container.innerHTML = `
                <div class="card" style="text-align:center; padding:54px 24px; border-radius:20px; border:1px dashed #cbd5e1; background:#ffffff;">
                    <div style="font-size:46px; margin-bottom:12px;">📋</div>
                    <h4 style="margin:0 0 6px 0; color:#0f172a; font-size:18px; font-weight:800;">No Daily Worklogs Found</h4>
                    <p style="font-size:13.5px; color:#64748b; margin:0 0 18px 0;">No work entries found matching your selected date or employee filter.</p>
                    <button type="button" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; font-weight:700; padding:8px 20px; border-radius:10px;" onclick="openAddWorklogModal()">
                        + Add Today's Worklog
                    </button>
                </div>
            `;
            return;
        }

        let html = '';
        employeeGroups.forEach(emp => {
            const isMe = (parseInt(emp.user_id) === currentUserId);
            const canManage = isMe || isSuperAdmin;
            const initials = getInitials(emp.user_name || 'Staff');
            const avatarGrad = getStaffGradient(emp.user_name || '');

            const roleBadgeStyle = (emp.role_name === 'Super Admin' || emp.role_name === 'System Super Admin') ? 'background:#fef3c7; color:#b45309; border:1px solid #fde68a;' : 
                                   (emp.role_name === 'Team Lead' || emp.role_name === 'DM Lead' || emp.role_name === 'Manager') ? 'background:#ede9fe; color:#6d28d9; border:1px solid #ddd6fe;' :
                                   (emp.role_name === 'HR') ? 'background:#fce7f3; color:#be185d; border:1px solid #fbcfe8;' :
                                   'background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;';

            html += `
                <div class="wl-emp-card" id="emp_card_${emp.user_id}">
                    <!-- Employee Header -->
                    <div class="wl-emp-header">
                        <div class="wl-emp-profile">
                            <div class="wl-emp-avatar" style="background:${avatarGrad};">
                                ${escapeHtml(initials)}
                            </div>
                            <div>
                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                    <span class="wl-emp-name">${escapeHtml(emp.user_name)}</span>
                                    <span class="badge" style="${roleBadgeStyle} font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px;">
                                        ${escapeHtml(emp.role_name || 'Employee')}
                                    </span>
                                    ${isMe ? `<span class="badge" style="background:#e0e7ff; color:#3730a3; font-weight:800; font-size:10.5px; padding:3px 8px; border-radius:999px;">You</span>` : ''}
                                </div>
                                <div class="wl-emp-badges">
                                    <span class="badge" style="background:#f8fafc; color:#64748b; font-size:11.5px; font-weight:600; border:1px solid #e2e8f0;">
                                        🏢 ${escapeHtml(emp.department || 'General')}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Badges & Quick Action -->
                        <div class="wl-emp-stat-badges">
                            <span class="badge" style="background:#f1f5f9; color:#334155; font-size:12px; font-weight:700; padding:5px 12px; border-radius:10px;">
                                📋 ${emp.total_items} Work ${emp.total_items === 1 ? 'Item' : 'Items'}
                            </span>
                            <span class="badge" style="background:#dcfce7; color:#15803d; font-size:12px; font-weight:700; padding:5px 12px; border-radius:10px; border:1px solid #bbf7d0;">
                                ✅ ${emp.completed_items} Completed
                            </span>
                            ${emp.not_completed_items > 0 ? `
                                <span class="badge" style="background:#fef3c7; color:#b45309; font-size:12px; font-weight:700; padding:5px 12px; border-radius:10px; border:1px solid #fde68a;">
                                    ⏳ ${emp.not_completed_items} Not Completed
                                </span>
                            ` : ''}

                            ${canManage ? `
                                <button type="button" class="btn btn-outline btn-sm no-print" style="font-size:12px; font-weight:700; border-radius:10px; border-color:#c7d2fe; color:#4338ca; padding:5px 12px;" onclick="openAddWorklogModalForUser(${emp.user_id})">
                                    + Add Work
                                </button>
                            ` : ''}
                        </div>
                    </div>

                    <!-- Clean Table of Work Items (List Type) -->
                    <div class="table-responsive">
                        <table class="wl-items-table">
                            <thead>
                                <tr>
                                    <th style="width:40px; text-align:center;">#</th>
                                    <th style="width:26%;">Work / Task</th>
                                    <th style="width:34%;">Work Detail</th>
                                    <th style="width:130px;">Status</th>
                                    <th>Reason (If Not Completed)</th>
                                    <th style="width:95px;">Date</th>
                                    <th style="width:90px; text-align:right;" class="no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            emp.items.forEach((item, idx) => {
                const isCompleted = (item.status === 'Completed');
                const reasonText = (item.reason || item.challenges || '').trim();

                html += `
                    <tr id="row_item_${item.id}">
                        <td style="text-align:center; font-weight:800; color:#94a3b8; font-size:12px;">
                            ${idx + 1}
                        </td>
                        <td>
                            <div class="wl-item-work">${escapeHtml(item.title)}</div>
                        </td>
                        <td>
                            <div class="wl-item-detail">${escapeHtml(item.description || '-')}</div>
                        </td>
                        <td>
                            ${isCompleted ? `
                                <span class="wl-status-badge wl-status-completed">
                                    ✅ Completed
                                </span>
                            ` : `
                                <span class="wl-status-badge wl-status-notcompleted">
                                    ⏳ Not Completed
                                </span>
                            `}
                        </td>
                        <td>
                            ${!isCompleted ? (
                                reasonText ? `
                                    <div class="wl-reason-chip">
                                        ⚠️ ${escapeHtml(reasonText)}
                                    </div>
                                ` : `
                                    <span style="color:#94a3b8; font-size:12px; font-style:italic;">No reason provided</span>
                                `
                            ) : `
                                <span style="color:#cbd5e1; font-weight:600;">—</span>
                            `}
                        </td>
                        <td style="font-size:12px; color:#64748b; font-weight:600;">
                            ${escapeHtml(item.log_date || '')}
                        </td>
                        <td style="text-align:right;" class="no-print">
                            ${canManage ? `
                                <div style="display:inline-flex; gap:4px;">
                                    <button type="button" class="btn btn-outline btn-sm" style="padding:3px 8px; font-size:12px; border-radius:7px; color:#4f46e5; border-color:#c7d2fe;" onclick="openEditWorkItemModal(${item.id})" title="Edit work item">
                                        ✏️
                                    </button>
                                    <button type="button" class="btn btn-outline btn-sm" style="padding:3px 8px; font-size:12px; border-radius:7px; color:#ef4444; border-color:#fecaca;" onclick="deleteWorklogEntry(${item.id})" title="Delete work item">
                                        🗑️
                                    </button>
                                </div>
                            ` : `
                                <span style="color:#cbd5e1;">-</span>
                            `}
                        </td>
                    </tr>
                `;
            });

            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
        if (window.feather) feather.replace();
    }

    // =========================================================
    // DYNAMIC LIST-TYPE ADD WORKLOG
    // =========================================================
    let dynamicRowCount = 0;

    function openAddWorklogModal() {
        openAddWorklogModalForUser(currentUserId);
    }

    function openAddWorklogModalForUser(targetUid) {
        document.getElementById('add_log_date').value = new Date().toISOString().substring(0, 10);
        
        const userSel = document.getElementById('add_user_id');
        if (userSel) userSel.value = String(targetUid || currentUserId);

        const container = document.getElementById('dynamicRowsContainer');
        container.innerHTML = '';
        dynamicRowCount = 0;

        // Initialize with 1 clean work item row
        addWorkItemRow();

        const modal = document.getElementById('addWorklogModal');
        if (modal) modal.style.display = 'flex';
    }

    function addWorkItemRow() {
        dynamicRowCount++;
        const rowId = dynamicRowCount;
        const container = document.getElementById('dynamicRowsContainer');
        if (!container) return;

        const rowDiv = document.createElement('div');
        rowDiv.className = 'wl-dynamic-row';
        rowDiv.id = `dyn_row_${rowId}`;
        rowDiv.innerHTML = `
            <div class="wl-dynamic-row-header">
                <span class="wl-row-counter">Work Item #${rowId}</span>
                <button type="button" class="btn btn-outline btn-sm btn-remove-row" style="padding:1px 7px; font-size:13px; color:#ef4444; border-color:#fecaca; border-radius:6px; line-height:1.2;" onclick="removeWorkItemRow(${rowId})" title="Remove item">
                    &times; Remove
                </button>
            </div>

            <!-- Work Title & Status Grid -->
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom:10px;">
                <div>
                    <label style="font-size:12px; font-weight:800; color:#334155; display:block; margin-bottom:4px;">Work / Task Title *</label>
                    <input type="text" class="form-control item-work-title" required placeholder="e.g. Redesigned landing page navbar &amp; mobile menu" style="height:38px; font-size:13px; font-weight:600;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:800; color:#334155; display:block; margin-bottom:4px;">Status *</label>
                    <select class="form-select item-work-status" style="height:38px; font-size:13px; font-weight:700;" onchange="onDynamicStatusChange(${rowId})">
                        <option value="Completed" selected>✅ Completed</option>
                        <option value="Not Completed">⏳ Not Completed</option>
                    </select>
                </div>
            </div>

            <!-- Work Detail -->
            <div style="margin-bottom:8px;">
                <label style="font-size:12px; font-weight:800; color:#334155; display:block; margin-bottom:4px;">Work Detail *</label>
                <textarea class="form-control item-work-detail" rows="2" required placeholder="Describe what tasks were executed, key updates made, or accomplishments..." style="font-size:13px;"></textarea>
            </div>

            <!-- Reason (Shown only when Not Completed) -->
            <div class="item-reason-container" id="reason_container_${rowId}" style="display:none; margin-top:8px;">
                <label style="font-size:12px; font-weight:800; color:#b45309; display:block; margin-bottom:4px;">⚠️ Reason Why Not Completed *</label>
                <input type="text" class="form-control item-work-reason" placeholder="State reason (e.g. Waiting for client assets, pending API response, etc.)" style="height:36px; font-size:13px; border-color:#fde68a; background:#fffdf7;">
            </div>
        `;

        container.appendChild(rowDiv);
        updateRowNumbers();
    }

    function removeWorkItemRow(rowId) {
        const rows = document.querySelectorAll('.wl-dynamic-row');
        if (rows.length <= 1) {
            alert('At least one work item is required.');
            return;
        }
        const row = document.getElementById(`dyn_row_${rowId}`);
        if (row) row.remove();
        updateRowNumbers();
    }

    function updateRowNumbers() {
        const rows = document.querySelectorAll('.wl-dynamic-row');
        rows.forEach((r, idx) => {
            const counter = r.querySelector('.wl-row-counter');
            if (counter) counter.textContent = `Work Item #${idx + 1}`;
        });
        const badge = document.getElementById('itemCountBadge');
        if (badge) badge.textContent = `${rows.length} ${rows.length === 1 ? 'Item' : 'Items'}`;
    }

    function onDynamicStatusChange(rowId) {
        const row = document.getElementById(`dyn_row_${rowId}`);
        if (!row) return;
        const statusSel = row.querySelector('.item-work-status');
        const reasonBox = document.getElementById(`reason_container_${rowId}`);
        const reasonInp = row.querySelector('.item-work-reason');

        if (statusSel && reasonBox) {
            if (statusSel.value === 'Not Completed') {
                reasonBox.style.display = 'block';
                if (reasonInp) reasonInp.required = true;
            } else {
                reasonBox.style.display = 'none';
                if (reasonInp) {
                    reasonInp.required = false;
                    reasonInp.value = '';
                }
            }
        }
    }

    function submitAddWorklogBatch(e) {
        e.preventDefault();

        const logDate = document.getElementById('add_log_date').value;
        const targetUserId = document.getElementById('add_user_id')?.value || currentUserId;

        const rows = document.querySelectorAll('.wl-dynamic-row');
        if (!rows.length) {
            alert('Please add at least one work item.');
            return;
        }

        const items = [];
        let hasError = false;

        rows.forEach(r => {
            const title = r.querySelector('.item-work-title')?.value.trim();
            const detail = r.querySelector('.item-work-detail')?.value.trim();
            const status = r.querySelector('.item-work-status')?.value;
            const reason = r.querySelector('.item-work-reason')?.value.trim() || '';

            if (!title) {
                alert('Work / task title is required for all items.');
                hasError = true;
                return;
            }

            if (status === 'Not Completed' && !reason) {
                alert('Please state the reason why the task is not completed.');
                hasError = true;
                return;
            }

            items.push({
                work: title,
                work_detail: detail,
                status: status,
                reason: reason
            });
        });

        if (hasError) return;

        const saveBtn = document.getElementById('btnAddSave');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving Work Items...';
        }

        const payload = {
            user_id: targetUserId,
            log_date: logDate,
            items: items
        };

        fetch('api.php?action=save_daily_worklog', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Save Daily Worklog';
            }
            if (!data.success) {
                alert('Save Error: ' + (data.message || 'Could not save worklog.'));
                return;
            }
            closeModal('addWorklogModal');
            loadWorklogs();
        })
        .catch(err => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Save Daily Worklog';
            }
            console.error('Worklog save error:', err);
            alert('Network error while saving daily worklog.');
        });
    }

    // =========================================================
    // EDIT SINGLE WORK ITEM
    // =========================================================
    function openEditWorkItemModal(itemId) {
        const item = loadedWorklogs.find(w => parseInt(w.id) === parseInt(itemId));
        if (!item) return;

        document.getElementById('edit_item_id').value = String(item.id);
        document.getElementById('edit_log_date').value = item.log_date || '';
        document.getElementById('edit_title').value = item.title || '';
        document.getElementById('edit_description').value = item.description || '';
        
        const statusVal = (item.status === 'Completed') ? 'Completed' : 'Not Completed';
        document.getElementById('edit_status').value = statusVal;

        const reasonVal = (item.reason || item.challenges || '');
        document.getElementById('edit_reason').value = reasonVal;

        onEditStatusChange();

        const modal = document.getElementById('editWorkItemModal');
        if (modal) modal.style.display = 'flex';
    }

    function onEditStatusChange() {
        const status = document.getElementById('edit_status').value;
        const reasonBox = document.getElementById('edit_reason_container');
        const reasonInp = document.getElementById('edit_reason');

        if (status === 'Not Completed') {
            reasonBox.style.display = 'block';
            reasonInp.required = true;
        } else {
            reasonBox.style.display = 'none';
            reasonInp.required = false;
        }
    }

    function submitEditWorkItem(e) {
        e.preventDefault();

        const itemId = parseInt(document.getElementById('edit_item_id').value) || 0;
        const status = document.getElementById('edit_status').value;
        const reason = document.getElementById('edit_reason').value.trim();

        if (status === 'Not Completed' && !reason) {
            alert('Please provide a reason why this work item is not completed.');
            return;
        }

        const payload = {
            id: itemId,
            log_date: document.getElementById('edit_log_date').value,
            work: document.getElementById('edit_title').value.trim(),
            work_detail: document.getElementById('edit_description').value.trim(),
            status: status,
            reason: reason
        };

        const saveBtn = document.getElementById('btnEditSave');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Updating...';
        }

        fetch('api.php?action=save_daily_worklog', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Update Work Item';
            }
            if (!data.success) {
                alert('Update Error: ' + (data.message || 'Could not update work item.'));
                return;
            }
            closeModal('editWorkItemModal');
            loadWorklogs();
        })
        .catch(err => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Update Work Item';
            }
            console.error('Worklog update error:', err);
            alert('Network error while updating work item.');
        });
    }

    // =========================================================
    // DELETE WORK ITEM
    // =========================================================
    function deleteWorklogEntry(id) {
        if (!confirm('Are you sure you want to delete this daily work item?')) return;

        fetch('api.php?action=delete_daily_worklog', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Delete Error: ' + (data.message || 'Could not delete item.'));
                return;
            }
            loadWorklogs();
        })
        .catch(() => alert('Network error while deleting worklog item.'));
    }

    // =========================================================
    // CSV EXPORT
    // =========================================================
    function exportWorklogsCsv() {
        if (!loadedWorklogs.length) {
            alert('No worklog records to export.');
            return;
        }

        let csv = 'ID,Date,Employee,Role,Department,Work,Work Detail,Status,Reason\n';
        loadedWorklogs.forEach(wl => {
            const row = [
                wl.id,
                `"${(wl.log_date || '').replace(/"/g, '""')}"`,
                `"${(wl.user_name || '').replace(/"/g, '""')}"`,
                `"${(wl.role_name || '').replace(/"/g, '""')}"`,
                `"${(wl.user_dept || '').replace(/"/g, '""')}"`,
                `"${(wl.title || '').replace(/"/g, '""')}"`,
                `"${(wl.description || '').replace(/"/g, '""')}"`,
                `"${(wl.status || '').replace(/"/g, '""')}"`,
                `"${(wl.reason || wl.challenges || '').replace(/"/g, '""')}"`
            ];
            csv += row.join(',') + '\n';
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', `daily_worklogs_${new Date().toISOString().substring(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function getInitials(name) {
        if (!name) return 'U';
        const parts = name.trim().split(/[\s\-_]+/);
        if (parts.length >= 2 && parts[0] && parts[1]) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.trim().substring(0, 2).toUpperCase();
    }

    function getStaffGradient(name) {
        const grads = [
            'linear-gradient(135deg, #6366f1, #8b5cf6)',
            'linear-gradient(135deg, #3b82f6, #06b6d4)',
            'linear-gradient(135deg, #059669, #10b981)',
            'linear-gradient(135deg, #f59e0b, #d97706)',
            'linear-gradient(135deg, #ec4899, #8b5cf6)',
            'linear-gradient(135deg, #8b5cf6, #3b82f6)'
        ];
        let hash = 0;
        for (let i = 0; i < (name || '').length; i++) {
            hash = name.charCodeAt(i) + ((hash << 5) - hash);
        }
        return grads[Math.abs(hash) % grads.length];
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
