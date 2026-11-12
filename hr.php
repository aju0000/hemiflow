<?php
// hr.php - Module 5 HR & Workforce Management Master Hub

require_once __DIR__ . '/auth.php';
checkAuth('hr-management');

$user = getCurrentUser();
$db = getDbConnection();
$isHr = isHrAdmin();
$isLead = isTeamLeadOrManager();
$currEmp = getCurrentEmployee();

$pageBreadcrumb = "Module 5";
$pageTitle = $isHr ? "HR Management & Workforce Hub" : "Employee Workspace & HR Portal";
$pageSubtitle = $isHr 
    ? "Agency-wide employee lifecycle management, attendance tracking, leave governance, holiday calendar, and document vault."
    : "Your personal workspace for web clock-in/out, leave balance tracking, request submissions, and agency holiday schedules.";

require_once __DIR__ . '/header.php';

// Pre-load active departments and designations for dropdowns
$departments = $db->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$designations = $db->query("SELECT des.*, d.name as dept_name FROM designations des LEFT JOIN departments d ON des.department_id = d.id WHERE des.is_active = 1 ORDER BY des.title ASC")->fetchAll();
$managers = $db->query("SELECT id, full_name, employee_code FROM employees WHERE employment_status = 'Active' ORDER BY full_name ASC")->fetchAll();
$leaveTypes = $db->query("SELECT * FROM leave_types WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
?>

<!-- Premium Attendance Calendar Stylesheet -->
<style>
.att-calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
    margin-bottom: 8px;
}
.att-calendar-weekdays div {
    text-align: center;
    font-weight: 800;
    font-size: 11px;
    color: #64748b;
    padding: 8px 0;
    letter-spacing: 0.05em;
}
.att-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
}
.att-day-cell {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    min-height: 118px;
    padding: 8px 9px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    user-select: none;
}
.att-day-cell:hover {
    border-color: #3b82f6 !important;
    box-shadow: 0 8px 20px -3px rgba(59, 130, 246, 0.16);
    transform: translateY(-2px);
    z-index: 2;
}
.att-day-cell.is-today {
    border: 2px solid #2563eb !important;
    background: #f8faff;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.12);
}
.att-day-cell.is-weekend {
    background: #fbfcfe;
    border-color: #f1f5f9;
}
.att-day-cell.is-other-month {
    background: #f8fafc;
    opacity: 0.35;
    cursor: default;
    pointer-events: none;
}
.att-day-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}
.att-day-num {
    font-weight: 800;
    font-size: 14px;
    color: #0f172a;
}
.att-today-tag {
    font-size: 9px;
    font-weight: 800;
    background: #2563eb;
    color: #ffffff;
    padding: 1px 6px;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.att-weekend-tag {
    font-size: 9px;
    font-weight: 700;
    color: #94a3b8;
    background: #f1f5f9;
    padding: 1px 5px;
    border-radius: 4px;
}
.att-sec-tags-row {
    display: flex;
    gap: 4px;
    margin-bottom: 6px;
}
.sec-tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 5px;
    letter-spacing: 0.02em;
}
.sec-tag.sec-p {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.sec-tag.sec-hd {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}
.sec-tag.sec-l {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.sec-tag.sec-wfh {
    background: #ede9fe;
    color: #6d28d9;
    border: 1px solid #ddd6fe;
}
.sec-tag.sec-empty {
    background: #f8fafc;
    color: #94a3b8;
    border: 1px dashed #cbd5e1;
}
.day-status-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 6px;
    border-radius: 6px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.day-status-pill.st-present {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}
.day-status-pill.st-halfday {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.day-status-pill.st-wfh {
    background: #e0e7ff;
    color: #3730a3;
    border: 1px solid #c7d2fe;
}
.day-status-pill.st-leave {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
.day-status-pill.st-mixed {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.holiday-banner {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    color: #b45309;
    border: 1px solid #fde68a;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 5px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
}
.preset-pill-btn {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.preset-pill-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.06);
}
.preset-present:hover { border-color: #10b981; color: #047857; background: #ecfdf5; }
.preset-half:hover { border-color: #f59e0b; color: #b45309; background: #fffbeb; }
.preset-wfh:hover { border-color: #6366f1; color: #4338ca; background: #eef2ff; }
.preset-leave:hover { border-color: #ef4444; color: #b91c1c; background: #fef2f2; }

.sec-choice-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px;
}
.sec-btn-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.sec-opt-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
    margin: 0;
}
.sec-opt-btn input[type="radio"] {
    accent-color: #2563eb;
    margin: 0;
}
.sec-opt-btn.is-selected-p {
    background: #dcfce7 !important;
    border-color: #10b981 !important;
    color: #15803d !important;
}
.sec-opt-btn.is-selected-l {
    background: #fee2e2 !important;
    border-color: #ef4444 !important;
    color: #b91c1c !important;
}
.sec-opt-btn.is-selected-wfh {
    background: #ede9fe !important;
    border-color: #8b5cf6 !important;
    color: #6d28d9 !important;
}
.sec-opt-btn.is-selected-none {
    background: #f1f5f9 !important;
    border-color: #94a3b8 !important;
    color: #64748b !important;
}
.grid-5 {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
}
@media (max-width: 900px) {
    .grid-5 {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<!-- Action Pills Navigation Bar -->
<div class="action-pills-row">
    <!-- Interactive Mark Attendance Pill Widget -->
    <button id="markAttPillBtn" class="btn-pill-primary" onclick="openMarkAttendanceModal()" style="display:flex; align-items:center; gap:8px;">
        <i data-feather="calendar" class="icon-prefix"></i>
        <span id="markAttPillText">+ Mark Attendance</span>
        <span id="todayAttStatusBadge" style="display:none; font-size:11px; padding:2px 8px; border-radius:12px; background:rgba(255,255,255,0.22); font-weight:700;"></span>
    </button>

    <button class="btn-pill-white" onclick="openApplyLeaveModal()">
        <i data-feather="calendar" class="icon-tag"></i>
        <span>+ Apply Leave</span>
    </button>

    <?php if ($isHr): ?>
        <button class="btn-pill-white" onclick="openEmployeeModal()">
            <i data-feather="user-plus" class="icon-folder"></i>
            <span>+ Add Employee</span>
        </button>

        <button class="btn-pill-white" onclick="switchHrTab('attendance')">
            <i data-feather="calendar" class="icon-clock"></i>
            <span>Attendance Calendar</span>
        </button>

        <a href="hr_export.php?type=employees" class="btn-pill-white" style="text-decoration:none;">
            <i data-feather="download" class="icon-edit"></i>
            <span>Export Employees CSV</span>
        </a>
    <?php endif; ?>

    <button class="btn-pill-white" onclick="refreshCurrentTab()">
        <i data-feather="refresh-cw" class="icon-edit"></i>
        <span>Refresh</span>
    </button>
</div>

<!-- Tab Navigation Header -->
<div class="card" style="padding: 12px 20px; margin-bottom: 20px;">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <button id="hrTab_dashboard" class="btn btn-outline" style="border:none; border-bottom:3px solid #2563eb; border-radius:0; font-weight:700; color:#2563eb;" onclick="switchHrTab('dashboard')">
            <i data-feather="grid" style="width:14px; height:14px; margin-right:4px;"></i> Dashboard
        </button>
        <button id="hrTab_employees" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0; color:#64748b;" onclick="switchHrTab('employees')">
            <i data-feather="users" style="width:14px; height:14px; margin-right:4px;"></i> Employee Directory
        </button>
        <button id="hrTab_attendance" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0; color:#64748b;" onclick="switchHrTab('attendance')">
            <i data-feather="calendar" style="width:14px; height:14px; margin-right:4px;"></i> Attendance Calendar
        </button>
        <button id="hrTab_leaves" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0; color:#64748b;" onclick="switchHrTab('leaves')">
            <i data-feather="calendar" style="width:14px; height:14px; margin-right:4px;"></i> Leaves & Balances
        </button>
        <?php if ($isHr): ?>
            <button id="hrTab_departments" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0; color:#64748b;" onclick="switchHrTab('departments')">
                <i data-feather="briefcase" style="width:14px; height:14px; margin-right:4px;"></i> Dept & Designations
            </button>
        <?php endif; ?>
        <button id="hrTab_holidays" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0; color:#64748b;" onclick="switchHrTab('holidays')">
            <i data-feather="flag" style="width:14px; height:14px; margin-right:4px;"></i> Holiday Calendar
        </button>
        <button id="hrTab_documents" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0; color:#64748b;" onclick="switchHrTab('documents')">
            <i data-feather="folder" style="width:14px; height:14px; margin-right:4px;"></i> Employee Documents
        </button>
        <?php if ($isHr || $isLead): ?>
            <button id="hrTab_reports" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0; color:#64748b;" onclick="switchHrTab('reports')">
                <i data-feather="bar-chart-2" style="width:14px; height:14px; margin-right:4px;"></i> HR Reports
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- ==================================================================== -->
<!-- TAB 1: DASHBOARD                                                     -->
<!-- ==================================================================== -->
<div id="tabView_dashboard">
    <!-- Live KPI Stat Cards Row -->
    <div class="grid-4" style="margin-bottom: 24px;">
        <div class="card" style="margin-bottom:0;">
            <p style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Total Workforce</p>
            <h2 id="kpi_total_employees" style="font-size:26px; font-weight:800; color:#2563eb; margin-top:4px;">--</h2>
            <small id="kpi_active_sub" style="color:#64748b; font-size:12px;">Active Employees: --</small>
        </div>

        <div class="card" style="margin-bottom:0;">
            <p style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Present Today</p>
            <h2 id="kpi_present_today" style="font-size:26px; font-weight:800; color:#16a34a; margin-top:4px;">--</h2>
            <small id="kpi_present_sub" style="color:#16a34a; font-size:12px;">Clocked in at Office / Remote</small>
        </div>

        <div class="card" style="margin-bottom:0;">
            <p style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">On Leave Today</p>
            <h2 id="kpi_on_leave_today" style="font-size:26px; font-weight:800; color:#d97706; margin-top:4px;">--</h2>
            <small style="color:#d97706; font-size:12px;">Approved Time Off</small>
        </div>

        <div class="card" style="margin-bottom:0;">
            <p style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;"><?= ($isHr || $isLead) ? 'Pending Leave Approvals' : 'My Pending Leaves' ?></p>
            <h2 id="kpi_pending_leaves" style="font-size:26px; font-weight:800; color:#dc2626; margin-top:4px;">--</h2>
            <small style="color:#dc2626; font-size:12px; font-weight:600;"><?= ($isHr || $isLead) ? 'Requires Manager Review' : 'Awaiting Approval' ?></small>
        </div>
    </div>

    <!-- Personal Balance Pills Banner for Employees -->
    <div id="personalLeaveBanner" class="card" style="display:none; background:linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color:#fff; margin-bottom:24px; border:1px solid rgba(255,255,255,0.08);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h4 style="font-size:15px; font-weight:700; color:#38bdf8;">My Available Leave Balances (<?= date('Y') ?>)</h4>
                <p style="font-size:12px; color:#94a3b8; margin:2px 0 0 0;">Check your annual entitlements before applying for time off</p>
            </div>
            <div id="personalLeavePills" style="display:flex; gap:10px; flex-wrap:wrap;">
                <!-- Populated via JS -->
            </div>
        </div>
    </div>

    <!-- Main Grid: 2 Columns -->
    <div class="grid-2">
        <!-- Left Column -->
        <div style="display:flex; flex-direction:column; gap:20px;">
            <!-- Pending Leave Approvals Card -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <h3 style="font-size:15px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i data-feather="clock" style="width:16px; height:16px; color:#d97706;"></i> <?= ($isHr || $isLead) ? 'Pending Leave Approvals' : 'My Pending Leaves' ?>
                    </h3>
                    <button class="btn btn-outline btn-sm" onclick="switchHrTab('leaves')"><?= ($isHr || $isLead) ? 'View All' : 'My Leaves' ?></button>
                </div>
                <div id="dashPendingLeavesList">
                    <p style="text-align:center; padding:20px; color:#64748b; font-size:13px;">Loading pending approvals...</p>
                </div>
            </div>

            <!-- Today's Attendance Snapshot Card -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                    <h3 style="font-size:15px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i data-feather="check-circle" style="width:16px; height:16px; color:#10b981;"></i> Today's Attendance Overview
                    </h3>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span class="badge badge-primary"><?= date('D, M d, Y') ?></span>
                        <button class="btn btn-outline btn-sm" onclick="switchHrTab('attendance')" style="border-radius:6px; font-size:11px;">
                            <i data-feather="calendar" style="width:12px; height:12px; margin-right:4px;"></i> Open Calendar
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Live Presence</th>
                                <th>Section 1 (Morning)</th>
                                <th>Section 2 (Afternoon)</th>
                                <th>Overall Status</th>
                                <th>Work Hours</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="dashTodayAttendanceBody">
                            <tr><td colspan="8" style="text-align:center; padding:20px;">Loading today's attendance...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div style="display:flex; flex-direction:column; gap:20px;">
            <!-- Upcoming Agency Holidays Card -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <h3 style="font-size:15px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i data-feather="flag" style="width:16px; height:16px; color:#6366f1;"></i> Upcoming Agency Holidays
                    </h3>
                    <button class="btn btn-outline btn-sm" onclick="switchHrTab('holidays')">Full Calendar</button>
                </div>
                <div id="dashUpcomingHolidaysList">
                    <p style="text-align:center; padding:20px; color:#64748b; font-size:13px;">Loading holidays...</p>
                </div>
            </div>

            <!-- Birthdays & Work Anniversaries Card -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <h3 style="font-size:15px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i data-feather="gift" style="width:16px; height:16px; color:#ec4899;"></i> Celebrations & Milestones (Next 30 Days)
                    </h3>
                </div>
                <div id="dashUpcomingEventsList">
                    <p style="text-align:center; padding:20px; color:#64748b; font-size:13px;">Checking milestones...</p>
                </div>
            </div>

            <!-- Recent HR Activities Audit Feed -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <h3 style="font-size:15px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i data-feather="activity" style="width:16px; height:16px; color:#0284c7;"></i> Recent HR Activity Audit Trail
                    </h3>
                </div>
                <div id="dashHrActivitiesList" style="max-height: 260px; overflow-y: auto;">
                    <p style="text-align:center; padding:20px; color:#64748b; font-size:13px;">Loading activities...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================================================================== -->
<!-- TAB 2: EMPLOYEE DIRECTORY                                            -->
<!-- ==================================================================== -->
<div id="tabView_employees" style="display:none;">
    <div class="card">
        <!-- Filters Bar -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; flex:1;">
                <input type="text" id="empSearch" class="form-control" style="max-width:240px;" placeholder="Search name, code, email..." onkeyup="if(event.key==='Enter') loadEmployees();">
                <select id="empFilterDept" class="form-select" style="max-width:200px;" onchange="loadEmployees()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="empFilterStatus" class="form-select" style="max-width:160px;" onchange="loadEmployees()">
                    <option value="">All Statuses</option>
                    <option value="Active" selected>Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <button class="btn btn-outline" onclick="loadEmployees()">Filter</button>
            </div>
            <?php if ($isHr): ?>
                <button class="btn btn-primary" onclick="openEmployeeModal()">
                    <i data-feather="plus" style="width:14px; height:14px; margin-right:4px;"></i> Add New Employee
                </button>
            <?php endif; ?>
        </div>

        <!-- Employees Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Full Name</th>
                        <th>Department & Title</th>
                        <th>Contact Email & Phone</th>
                        <th>Joined Date</th>
                        <th>Employment Type</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="employeesTableBody">
                    <tr><td colspan="8" style="text-align:center; padding:30px;">Loading employee records...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================================================================== -->
<!-- TAB 3: ATTENDANCE CALENDAR & MATRIX                                  -->
<!-- ==================================================================== -->
<div id="tabView_attendance" style="display:none;">
    
    <!-- Top Attendance Control Bar Card -->
    <div class="card" style="margin-bottom: 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
            
            <!-- Left: View Switcher (Calendar vs Table) -->
            <div style="display:flex; align-items:center; gap:6px; background:#f1f5f9; padding:4px; border-radius:10px;">
                <button id="btnAttViewCalendar" class="btn btn-sm btn-primary" onclick="setAttendanceSubView('calendar')" style="border-radius:8px; display:flex; align-items:center; gap:6px;">
                    <i data-feather="calendar" style="width:14px; height:14px;"></i>
                    <span>Calendar View</span>
                </button>
                <button id="btnAttViewTable" class="btn btn-sm btn-outline" onclick="setAttendanceSubView('table')" style="border-radius:8px; border:none; display:flex; align-items:center; gap:6px; color:#64748b;">
                    <i data-feather="list" style="width:14px; height:14px;"></i>
                    <span>Table Log</span>
                </button>
            </div>

            <!-- Center: Month Navigator -->
            <div style="display:flex; align-items:center; gap:8px;">
                <button class="btn btn-outline btn-sm" onclick="changeAttendanceMonth(-1)" title="Previous Month" style="width:34px; height:34px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:8px;">
                    <i data-feather="chevron-left" style="width:16px; height:16px;"></i>
                </button>
                <div id="attCalendarMonthTitle" style="font-size:16px; font-weight:800; min-width:170px; text-align:center; color:#0f172a; letter-spacing:-0.01em;">
                    <?= date('F Y') ?>
                </div>
                <button class="btn btn-outline btn-sm" onclick="changeAttendanceMonth(1)" title="Next Month" style="width:34px; height:34px; padding:0; display:flex; align-items:center; justify-content:center; border-radius:8px;">
                    <i data-feather="chevron-right" style="width:16px; height:16px;"></i>
                </button>
                <button class="btn btn-outline btn-sm" onclick="jumpAttendanceToToday()" style="border-radius:8px; font-weight:600;">Today</button>
            </div>

            <!-- Right: Employee Filter & Mark Action -->
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <?php if ($isHr || $isLead): ?>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <label style="font-size:12px; font-weight:700; color:#64748b;">Staff:</label>
                        <select id="attCalendarEmployeeSelect" class="form-select form-select-sm" style="min-width:210px; max-width:260px;" onchange="onAttendanceEmployeeChanged()">
                            <?php foreach ($managers as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= ($currEmp && $currEmp['id'] == $m['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['employee_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" id="attCalendarEmployeeSelect" value="<?= $currEmp ? $currEmp['id'] : 0 ?>">
                <?php endif; ?>

                <button class="btn btn-primary btn-sm" onclick="openMarkAttendanceModal()" style="display:flex; align-items:center; gap:6px; border-radius:8px; padding:8px 14px;">
                    <i data-feather="plus" style="width:15px; height:15px;"></i>
                    <span>+ Mark Attendance</span>
                </button>

                <?php if ($isHr): ?>
                    <a href="hr_export.php?type=attendance" id="attExportCsvBtn" class="btn btn-outline btn-sm" style="text-decoration:none; border-radius:8px; display:flex; align-items:center; gap:6px;">
                        <i data-feather="download" style="width:14px; height:14px;"></i>
                        <span>Export CSV</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CALENDAR SUBVIEW -->
    <div id="subView_attCalendar">
        <!-- Monthly Statistics Header Cards -->
        <div class="grid-5" style="margin-bottom: 20px;">
            <div class="card att-kpi-card" style="border-left: 4px solid #10b981; margin-bottom:0; padding:14px 18px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <p style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:0;">Present (P)</p>
                        <h3 id="cal_stat_present" style="font-size:24px; font-weight:800; color:#10b981; margin:4px 0 0 0;">--</h3>
                    </div>
                    <span class="badge" style="background:#dcfce7; color:#15803d; font-weight:800;">Full Day</span>
                </div>
                <small style="color:#94a3b8; font-size:11px;">Section 1 & 2 Marked P</small>
            </div>

            <div class="card att-kpi-card" style="border-left: 4px solid #f59e0b; margin-bottom:0; padding:14px 18px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <p style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:0;">Half Day (HD)</p>
                        <h3 id="cal_stat_halfday" style="font-size:24px; font-weight:800; color:#f59e0b; margin:4px 0 0 0;">--</h3>
                    </div>
                    <span class="badge" style="background:#fef3c7; color:#b45309; font-weight:800;">0.5 Day</span>
                </div>
                <small style="color:#94a3b8; font-size:11px;">1st Section Marked Only</small>
            </div>

            <div class="card att-kpi-card" style="border-left: 4px solid #6366f1; margin-bottom:0; padding:14px 18px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <p style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:0;">Work From Home</p>
                        <h3 id="cal_stat_wfh" style="font-size:24px; font-weight:800; color:#6366f1; margin:4px 0 0 0;">--</h3>
                    </div>
                    <span class="badge" style="background:#e0e7ff; color:#4338ca; font-weight:800;">Remote</span>
                </div>
                <small style="color:#94a3b8; font-size:11px;">WFH Recorded</small>
            </div>

            <div class="card att-kpi-card" style="border-left: 4px solid #ef4444; margin-bottom:0; padding:14px 18px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <p style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin:0;">Leave (L)</p>
                        <h3 id="cal_stat_leave" style="font-size:24px; font-weight:800; color:#ef4444; margin:4px 0 0 0;">--</h3>
                    </div>
                    <span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:800;">Absence</span>
                </div>
                <small style="color:#94a3b8; font-size:11px;">Approved Leaves</small>
            </div>

            <div class="card att-kpi-card" style="border-left: 4px solid #0ea5e9; margin-bottom:0; padding:14px 18px; background:linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <p style="font-size:11px; font-weight:700; color:#0369a1; text-transform:uppercase; margin:0;">Effective Days</p>
                        <h3 id="cal_stat_effective" style="font-size:24px; font-weight:800; color:#0284c7; margin:4px 0 0 0;">--</h3>
                    </div>
                    <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:800;">Credit</span>
                </div>
                <small style="color:#64748b; font-size:11px;">P + WFH + (0.5 &times; HD)</small>
            </div>
        </div>

        <!-- Legend and Info Bar -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px; padding:10px 16px; background:#ffffff; border:1px solid #e2e8f0; border-radius:10px;">
            <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; font-size:12px;">
                <span style="font-weight:700; color:#475569;">Attendance Legend:</span>
                <span style="display:inline-flex; align-items:center; gap:5px;"><span class="sec-tag sec-p">P</span> <strong>Present</strong> (Full Day)</span>
                <span style="display:inline-flex; align-items:center; gap:5px;"><span class="sec-tag sec-hd">HD</span> <strong>Half Day</strong> (1st Section Only)</span>
                <span style="display:inline-flex; align-items:center; gap:5px;"><span class="sec-tag sec-wfh">WFH</span> <strong>Work From Home</strong></span>
                <span style="display:inline-flex; align-items:center; gap:5px;"><span class="sec-tag sec-l">L</span> <strong>Leave</strong></span>
                <span style="display:inline-flex; align-items:center; gap:5px;"><span style="display:inline-block; width:10px; height:10px; border-radius:3px; background:#f59e0b;"></span> <strong>Holiday</strong></span>
                <span style="display:inline-flex; align-items:center; gap:5px;"><span style="display:inline-block; width:10px; height:10px; border-radius:3px; background:#94a3b8;"></span> <strong>Weekend</strong></span>
            </div>
            <div style="font-size:11px; color:#64748b; display:flex; align-items:center; gap:4px;">
                <i data-feather="info" style="width:13px; height:13px; color:#3b82f6;"></i>
                <span>Click any calendar date to mark or edit Section 1 & Section 2</span>
            </div>
        </div>

        <!-- Calendar Container Card -->
        <div class="card" style="padding: 16px; border-radius:14px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.04);">
            <!-- Weekday Header Row -->
            <div class="att-calendar-weekdays">
                <div>MON</div>
                <div>TUE</div>
                <div>WED</div>
                <div>THU</div>
                <div>FRI</div>
                <div style="color:#ef4444;">SAT</div>
                <div style="color:#ef4444;">SUN</div>
            </div>

            <!-- Calendar Days Grid -->
            <div id="attendanceCalendarGrid" class="att-calendar-grid">
                <!-- Injected via JavaScript -->
                <div style="grid-column: span 7; text-align:center; padding:50px; color:#64748b;">
                    Loading Attendance Calendar...
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE LOG SUBVIEW -->
    <div id="subView_attTable" style="display:none;">
        <div class="card">
            <!-- Table Filters -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; flex:1;">
                    <input type="date" id="attFilterDate" class="form-control" style="max-width:170px;" value="<?= date('Y-m-d') ?>" onchange="loadAttendanceRecords()">
                    <?php if ($isHr || $isLead): ?>
                        <select id="attFilterDept" class="form-select" style="max-width:180px;" onchange="loadAttendanceRecords()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="attFilterStatus" class="form-select" style="max-width:160px;" onchange="loadAttendanceRecords()">
                            <option value="">All Statuses</option>
                            <option value="Present">Present</option>
                            <option value="Half Day">Half Day</option>
                            <option value="WFH">Work From Home</option>
                            <option value="On Leave">On Leave</option>
                            <option value="Absent">Absent</option>
                        </select>
                    <?php endif; ?>
                    <button class="btn btn-outline" onclick="loadAttendanceRecords()">Filter</button>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Section 1 (Morning)</th>
                            <th>Section 2 (Afternoon)</th>
                            <th>Status</th>
                            <th>Work Hours</th>
                            <th>Notes / Remarks</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody">
                        <tr><td colspan="9" style="text-align:center; padding:30px;">Loading attendance records...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ==================================================================== -->
<!-- TAB 4: LEAVES & BALANCES                                             -->
<!-- ==================================================================== -->
<div id="tabView_leaves" style="display:none;">
    <!-- Leave Allocations Grid for Selected Employee / Self -->
    <div class="card" style="margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
            <div>
                <h3 style="font-size:16px; font-weight:700;">Leave Entitlements & Usage (<?= date('Y') ?>)</h3>
                <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Configured policy balances and accumulated leaves</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <?php if ($isHr): ?>
                    <select id="leaveBalanceEmpSelect" class="form-select" style="max-width:220px;" onchange="loadLeaveAllocations()">
                        <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ($currEmp && $currEmp['id'] == $m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['employee_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button class="btn btn-primary" onclick="openApplyLeaveModal()">
                    <i data-feather="plus" style="width:14px; height:14px; margin-right:4px;"></i> Apply for Leave
                </button>
            </div>
        </div>
        <div id="leaveAllocationsCardsRow" class="grid-4" style="margin-bottom:0;">
            <!-- Dynamically populated leave cards -->
        </div>
    </div>

    <!-- Leave Requests Master Table -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <select id="leaveFilterStatus" class="form-select" style="max-width:160px;" onchange="loadLeaveRequests()">
                    <option value="">All Statuses</option>
                    <option value="Pending" selected>Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <select id="leaveFilterType" class="form-select" style="max-width:180px;" onchange="loadLeaveRequests()">
                    <option value="">All Leave Types</option>
                    <?php foreach ($leaveTypes as $lt): ?>
                        <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name']) ?> (<?= htmlspecialchars($lt['code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline" onclick="loadLeaveRequests()">Filter</button>
            </div>

            <?php if ($isHr || $isLead): ?>
                <a href="hr_export.php?type=leaves" class="btn btn-outline" style="text-decoration:none;">
                    <i data-feather="download" style="width:14px; height:14px; margin-right:4px;"></i> Export Leaves CSV
                </a>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Req #</th>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>From Date</th>
                        <th>To Date</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Decision Note</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="leaveRequestsTableBody">
                    <tr><td colspan="10" style="text-align:center; padding:30px;">Loading leave requests...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================================================================== -->
<!-- TAB 5: DEPARTMENTS & DESIGNATIONS                                    -->
<!-- ==================================================================== -->
<?php if ($isHr): ?>
<div id="tabView_departments" style="display:none;">
    <div class="grid-2">
        <!-- Departments Column -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div>
                    <h3 style="font-size:16px; font-weight:700;">Agency Departments</h3>
                    <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Manage functional units and headcounts</p>
                </div>
                <button class="btn btn-primary btn-sm" onclick="openDepartmentModal()">+ Add Dept</button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Headcount</th>
                            <th>Status</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="departmentsTableBody">
                        <tr><td colspan="5" style="text-align:center; padding:20px;">Loading departments...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Designations Column -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div>
                    <h3 style="font-size:16px; font-weight:700;">Designations & Job Titles</h3>
                    <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Organizational roles linked to departments</p>
                </div>
                <button class="btn btn-primary btn-sm" onclick="openDesignationModal()">+ Add Title</button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Department</th>
                            <th>Assigned</th>
                            <th>Status</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="designationsTableBody">
                        <tr><td colspan="5" style="text-align:center; padding:20px;">Loading designations...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ==================================================================== -->
<!-- TAB 6: HOLIDAY CALENDAR                                              -->
<!-- ==================================================================== -->
<div id="tabView_holidays" style="display:none;">
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
            <div>
                <h3 style="font-size:16px; font-weight:700;">Agency Holiday Calendar (<?= date('Y') ?>)</h3>
                <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Official non-working days reflected in attendance and leaves</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <select id="holidayYearSelect" class="form-select" style="max-width:140px;" onchange="loadHolidays()">
                    <option value="<?= date('Y') - 1 ?>"><?= date('Y') - 1 ?></option>
                    <option value="<?= date('Y') ?>" selected><?= date('Y') ?></option>
                    <option value="<?= date('Y') + 1 ?>"><?= date('Y') + 1 ?></option>
                </select>
                <?php if ($isHr): ?>
                    <button class="btn btn-primary" onclick="openHolidayModal()">
                        <i data-feather="plus" style="width:14px; height:14px; margin-right:4px;"></i> Add Holiday
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Holiday Date</th>
                        <th>Day</th>
                        <th>Holiday Title</th>
                        <th>Description & Scope</th>
                        <th>Type</th>
                        <?php if ($isHr): ?><th style="text-align:right;">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="holidaysTableBody">
                    <tr><td colspan="<?= $isHr ? 6 : 5 ?>" style="text-align:center; padding:30px;">Loading holiday calendar...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================================================================== -->
<!-- TAB 7: EMPLOYEE DOCUMENTS VAULT                                      -->
<!-- ==================================================================== -->
<div id="tabView_documents" style="display:none;">
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
            <div>
                <h3 style="font-size:16px; font-weight:700;">Secure Employee Document Vault</h3>
                <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Offer letters, appointment letters, IDs, certificates, and NDA agreements</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <?php if ($isHr): ?>
                    <select id="docEmpSelect" class="form-select" style="max-width:240px;" onchange="loadEmployeeDocuments()">
                        <option value="">-- All Employees --</option>
                        <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['employee_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button class="btn btn-primary" onclick="openUploadDocModal()">
                    <i data-feather="upload" style="width:14px; height:14px; margin-right:4px;"></i> Upload Document
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Document Title</th>
                        <th>Category</th>
                        <th>Employee</th>
                        <th>File Size</th>
                        <th>Uploaded On</th>
                        <th>Classification</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="documentsTableBody">
                    <tr><td colspan="7" style="text-align:center; padding:30px;">Loading document records...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==================================================================== -->
<!-- TAB 8: HR REPORTS & EXECUTIVE BI                                     -->
<!-- ==================================================================== -->
<?php if ($isHr || $isLead): ?>
<div id="tabView_reports" style="display:none;">
    <div class="card" style="margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <select id="hrReportType" class="form-select" style="max-width:240px;" onchange="loadHrReport()">
                    <option value="employees_status">Workforce Master Roster</option>
                    <option value="department_headcount">Department Headcount BI</option>
                    <option value="monthly_attendance">Monthly Attendance Summary</option>
                    <option value="leave_usage">Leave Accrual & Utilization</option>
                </select>
                <select id="hrReportDept" class="form-select" style="max-width:180px;" onchange="loadHrReport()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="month" id="hrReportMonth" class="form-control" style="max-width:160px;" value="<?= date('Y-m') ?>" onchange="loadHrReport()">
                <button class="btn btn-outline" onclick="loadHrReport()">Generate</button>
            </div>
            <div>
                <button class="btn btn-primary" onclick="exportCurrentReportCsv()">
                    <i data-feather="download" style="width:14px; height:14px; margin-right:4px;"></i> Export Report CSV
                </button>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 id="reportTitleHeader" style="font-size:16px; font-weight:700; margin-bottom:14px;">Workforce Master Roster</h3>
        <div class="table-responsive">
            <table class="table" id="reportDynamicTable">
                <thead id="reportTableHead"></thead>
                <tbody id="reportTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ==================================================================== -->
<!-- MODALS                                                               -->
<!-- ==================================================================== -->

<!-- MODAL 1: ADD / EDIT EMPLOYEE -->
<div id="employeeModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:800px;">
        <div class="modal-header">
            <h3 class="modal-title" id="empModalTitle">Add New Employee</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('employeeModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitSaveEmployee();">
            <input type="hidden" id="emp_id" value="">
            <div class="modal-body" style="max-height: 75vh; overflow-y:auto;">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" id="emp_full_name" class="form-control" required placeholder="e.g. Arun Kumar">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Official Email Address *</label>
                        <input type="email" id="emp_email" class="form-control" required placeholder="e.g. arun@hemitodigital.com">
                    </div>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Employee Code (Optional)</label>
                        <input type="text" id="emp_code" class="form-control" placeholder="Auto-generated if blank">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" id="emp_phone" class="form-control" placeholder="+91 98765 43210">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Joining *</label>
                        <input type="date" id="emp_doj" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Department *</label>
                        <select id="emp_dept_id" class="form-select" required onchange="filterDesignationsByDept()">
                            <option value="">-- Choose Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Designation *</label>
                        <select id="emp_desig_id" class="form-select" required>
                            <option value="">-- Choose Designation --</option>
                            <?php foreach ($designations as $dg): ?>
                                <option value="<?= $dg['id'] ?>" data-dept="<?= $dg['department_id'] ?>">
                                    <?= htmlspecialchars($dg['title']) ?> (<?= htmlspecialchars($dg['dept_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reporting Manager</label>
                        <select id="emp_manager_id" class="form-select">
                            <option value="">-- No Direct Manager --</option>
                            <?php foreach ($managers as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Employment Type</label>
                        <select id="emp_type" class="form-select">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                            <option value="Internship">Internship</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Employment Status</label>
                        <select id="emp_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="On Leave">On Leave</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Work Location</label>
                        <select id="emp_location" class="form-select">
                            <option value="Office">Office</option>
                            <option value="Remote">Remote</option>
                            <option value="Hybrid">Hybrid</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" id="emp_dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Emergency Contact Name</label>
                        <input type="text" id="emp_em_name" class="form-control" placeholder="Contact person name">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Emergency Contact Phone</label>
                        <input type="text" id="emp_em_phone" class="form-control" placeholder="Emergency phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Emergency Relationship</label>
                        <input type="text" id="emp_em_rel" class="form-control" placeholder="e.g. Spouse / Parent">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea id="emp_address" class="form-control" rows="2" placeholder="Residential address"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Confidential HR Notes (HR Only)</label>
                    <textarea id="emp_notes" class="form-control" rows="2" placeholder="Internal notes, appraisals, or observations..."></textarea>
                </div>

                <!-- Create Login User Option for New Employees -->
                <div id="createUserLoginBox" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-top:10px;">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer;">
                        <input type="checkbox" id="emp_create_login" value="1" checked onchange="document.getElementById('roleSelectRow').style.display = this.checked ? 'block' : 'none';">
                        <span>Provision Linked Agency OS Login Credentials</span>
                    </label>
                    <div id="roleSelectRow" style="margin-top:10px;">
                        <label class="form-label" style="font-size:12px;">Portal Role for Login Account</label>
                        <select id="emp_user_role" class="form-select">
                            <option value="5" selected>Developer (Standard Staff)</option>
                            <option value="4">Team Lead</option>
                            <option value="3">Sales Representative</option>
                            <option value="16">HR Manager</option>
                        </select>
                        <small style="color:#64748b; font-size:11px;">Default password will be <code>password123</code>. The employee can change it upon logging in.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('employeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSaveEmployee">Save Employee</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: VIEW EMPLOYEE PROFILE DRAWER -->
<div id="employeeViewModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:850px;">
        <div class="modal-header">
            <h3 class="modal-title" id="viewEmpTitle">Employee Dossier</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('employeeViewModal')">&times;</button>
        </div>
        <div class="modal-body" id="viewEmpBody" style="max-height: 75vh; overflow-y:auto;">
            <!-- Rendered via JS -->
        </div>
    </div>
</div>

<!-- MODAL 3: APPLY FOR LEAVE -->
<div id="leaveRequestModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:550px;">
        <div class="modal-header">
            <h3 class="modal-title">Submit Leave Request</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('leaveRequestModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitLeaveApplication();">
            <div class="modal-body">
                <?php if ($isHr): ?>
                    <div class="form-group">
                        <label class="form-label">Employee *</label>
                        <select id="lr_employee_id" class="form-select" required>
                            <?php foreach ($managers as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= ($currEmp && $currEmp['id'] == $m['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['employee_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Leave Category *</label>
                    <select id="lr_leave_type_id" class="form-select" required>
                        <option value="">-- Choose Leave Category --</option>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name']) ?> (<?= htmlspecialchars($lt['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Start Date *</label>
                        <input type="date" id="lr_start_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date *</label>
                        <input type="date" id="lr_end_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason / Purpose of Leave *</label>
                    <textarea id="lr_reason" class="form-control" rows="3" required placeholder="Describe reason for time off..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('leaveRequestModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitLeave">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 4: LEAVE APPROVAL / REJECTION DECISION -->
<div id="leaveDecisionModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="leaveDecisionTitle">Process Leave Request</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('leaveDecisionModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitLeaveDecision();">
            <input type="hidden" id="dec_req_id" value="">
            <input type="hidden" id="dec_status" value="">
            <div class="modal-body">
                <div id="dec_req_info" style="background:#f8fafc; padding:12px; border-radius:8px; margin-bottom:14px; font-size:13px;"></div>
                <div class="form-group">
                    <label class="form-label">Decision Comment / Reason (Mandatory for Rejections)</label>
                    <textarea id="dec_comment" class="form-control" rows="3" placeholder="Provide note to employee..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('leaveDecisionModal')">Cancel</button>
                <button type="submit" class="btn" id="dec_submit_btn">Confirm Decision</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================== -->
<!-- MODAL: MARK / EDIT ATTENDANCE (SECTION 1 & SECTION 2 CALENDAR TYPE)   -->
<!-- ==================================================================== -->
<div id="markAttendanceModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:580px; border-radius:16px;">
        <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding-bottom:14px;">
            <div>
                <h3 class="modal-title" id="markAttModalTitle" style="font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <i data-feather="calendar" style="width:20px; height:20px; color:#2563eb;"></i> Mark Attendance
                </h3>
                <p id="markAttModalSubtitle" style="font-size:12px; color:#64748b; margin:2px 0 0 0;">
                    Select Section 1 (Morning) and Section 2 (Afternoon) status
                </p>
            </div>
            <button class="btn btn-outline btn-sm" onclick="closeModal('markAttendanceModal')" style="border-radius:50%; width:32px; height:32px; padding:0; display:flex; align-items:center; justify-content:center;">&times;</button>
        </div>

        <form onsubmit="event.preventDefault(); submitMarkAttendance();">
            <input type="hidden" id="mark_att_existing_id" value="">
            <div class="modal-body" style="padding:20px 24px;">

                <!-- Row 1: Target Employee & Date -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:12px; font-weight:700;">Employee *</label>
                        <?php if ($isHr): ?>
                            <select id="mark_att_emp_id" class="form-select" required onchange="onMarkModalEmployeeChanged()">
                                <?php foreach ($managers as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= ($currEmp && $currEmp['id'] == $m['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['employee_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" id="mark_att_emp_display" class="form-control" value="<?= htmlspecialchars($currEmp['full_name'] ?? 'Self') ?> (<?= htmlspecialchars($currEmp['employee_code'] ?? '') ?>)" readonly>
                            <input type="hidden" id="mark_att_emp_id" value="<?= $currEmp['id'] ?? 0 ?>">
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:12px; font-weight:700;">Date *</label>
                        <input type="date" id="mark_att_date" class="form-control" value="<?= date('Y-m-d') ?>" required onchange="onMarkModalDateChanged()">
                    </div>
                </div>

                <!-- Row 2: 1-Click Fast Presets -->
                <div style="margin-bottom: 18px;">
                    <label class="form-label" style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:8px; display:block;">
                        ⚡ 1-Click Quick Presets
                    </label>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <button type="button" class="preset-pill-btn preset-present" onclick="applyAttendancePreset('FULL_P')">
                            🟢 Full Day (P + P)
                        </button>
                        <button type="button" class="preset-pill-btn preset-half" onclick="applyAttendancePreset('HALF_S1')">
                            🟡 Half Day (S1: P only)
                        </button>
                        <button type="button" class="preset-pill-btn preset-half" onclick="applyAttendancePreset('HALF_S2')">
                            🟡 Half Day (S2: P only)
                        </button>
                        <button type="button" class="preset-pill-btn preset-wfh" onclick="applyAttendancePreset('FULL_WFH')">
                            🟣 Full Day WFH
                        </button>
                        <button type="button" class="preset-pill-btn preset-leave" onclick="applyAttendancePreset('FULL_L')">
                            🔴 Full Day Leave (L + L)
                        </button>
                    </div>
                </div>

                <!-- Row 3: Section 1 & Section 2 Interactive Switchers -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:18px;">
                    
                    <!-- SECTION 1 (Morning / First Half) -->
                    <div class="sec-choice-box" id="box_sec_1">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <span style="font-weight:800; font-size:13px; color:#0f172a;">Section 1 (Morning)</span>
                            <span style="font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; background:#e2e8f0; color:#475569;">1st Half</span>
                        </div>
                        <div class="sec-btn-group">
                            <label class="sec-opt-btn opt-p" id="lbl_s1_P">
                                <input type="radio" name="mark_sec_1" value="P" onchange="updateAttendanceOutcomePreview()">
                                <span>P &bull; Present</span>
                            </label>
                            <label class="sec-opt-btn opt-l" id="lbl_s1_L">
                                <input type="radio" name="mark_sec_1" value="L" onchange="updateAttendanceOutcomePreview()">
                                <span>L &bull; Leave</span>
                            </label>
                            <label class="sec-opt-btn opt-wfh" id="lbl_s1_WFH">
                                <input type="radio" name="mark_sec_1" value="WFH" onchange="updateAttendanceOutcomePreview()">
                                <span>WFH</span>
                            </label>
                            <label class="sec-opt-btn opt-none" id="lbl_s1_NONE">
                                <input type="radio" name="mark_sec_1" value="" onchange="updateAttendanceOutcomePreview()" checked>
                                <span>- Off</span>
                            </label>
                        </div>
                    </div>

                    <!-- SECTION 2 (Afternoon / Second Half) -->
                    <div class="sec-choice-box" id="box_sec_2">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <span style="font-weight:800; font-size:13px; color:#0f172a;">Section 2 (Afternoon)</span>
                            <span style="font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; background:#e2e8f0; color:#475569;">2nd Half</span>
                        </div>
                        <div class="sec-btn-group">
                            <label class="sec-opt-btn opt-p" id="lbl_s2_P">
                                <input type="radio" name="mark_sec_2" value="P" onchange="updateAttendanceOutcomePreview()">
                                <span>P &bull; Present</span>
                            </label>
                            <label class="sec-opt-btn opt-l" id="lbl_s2_L">
                                <input type="radio" name="mark_sec_2" value="L" onchange="updateAttendanceOutcomePreview()">
                                <span>L &bull; Leave</span>
                            </label>
                            <label class="sec-opt-btn opt-wfh" id="lbl_s2_WFH">
                                <input type="radio" name="mark_sec_2" value="WFH" onchange="updateAttendanceOutcomePreview()">
                                <span>WFH</span>
                            </label>
                            <label class="sec-opt-btn opt-none" id="lbl_s2_NONE">
                                <input type="radio" name="mark_sec_2" value="" onchange="updateAttendanceOutcomePreview()" checked>
                                <span>- Off</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Real-time Calculated Outcome Preview Banner -->
                <div id="markOutcomePreviewBox" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:12px 16px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <small style="font-size:10px; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:0.04em;">Calculated Attendance Result</small>
                        <div id="markOutcomeText" style="font-weight:800; font-size:14px; color:#0f172a; margin-top:2px;">
                            -- Select Sections Above --
                        </div>
                    </div>
                    <div id="markOutcomeBadge">
                        <!-- Injected via JS -->
                    </div>
                </div>

                <!-- Row 5: Notes / Remarks -->
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" style="font-size:12px; font-weight:700;">Notes / Remarks (Optional)</label>
                    <input type="text" id="mark_att_notes" class="form-control" placeholder="e.g. Client visit, Medical half-day, Remote project sprint...">
                </div>

            </div>

            <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #f1f5f9; padding:14px 24px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <button type="button" id="btnDeleteAttendanceDate" class="btn btn-outline btn-sm" onclick="deleteCurrentAttendanceDate()" style="color:#dc2626; border-color:#fca5a5; display:none;">
                        <i data-feather="trash-2" style="width:14px; height:14px; margin-right:4px;"></i> Clear Entry
                    </button>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('markAttendanceModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveAttendance">
                        <i data-feather="check" style="width:14px; height:14px; margin-right:4px;"></i> Save Attendance
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 5: ATTENDANCE CORRECTION (HR ONLY) -->
<div id="attendanceCorrectionModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:550px;">
        <div class="modal-header">
            <h3 class="modal-title">Attendance Record Correction</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('attendanceCorrectionModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitAttendanceCorrection();">
            <input type="hidden" id="att_corr_id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Employee *</label>
                    <select id="att_corr_emp_id" class="form-select" required>
                        <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['employee_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Attendance Date *</label>
                    <input type="date" id="att_corr_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Check In Timestamp</label>
                        <input type="datetime-local" id="att_corr_in" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Check Out Timestamp</label>
                        <input type="datetime-local" id="att_corr_out" class="form-control">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Status *</label>
                        <select id="att_corr_status" class="form-select" required>
                            <option value="Present">Present</option>
                            <option value="Half Day">Half Day</option>
                            <option value="On Leave">On Leave</option>
                            <option value="Absent">Absent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Hours</label>
                        <input type="number" step="0.1" id="att_corr_hours" class="form-control" placeholder="e.g. 8.5">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mandatory Audit Reason *</label>
                    <textarea id="att_corr_reason" class="form-control" rows="2" required placeholder="State justification for attendance correction..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('attendanceCorrectionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Correction</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 6: DEPARTMENT MODAL -->
<div id="departmentModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="deptModalTitle">Department Setup</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('departmentModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitSaveDepartment();">
            <input type="hidden" id="dept_id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Department Name *</label>
                    <input type="text" id="dept_name" class="form-control" required placeholder="e.g. Mobile Engineering">
                </div>
                <div class="form-group">
                    <label class="form-label">Department Code (Short)</label>
                    <input type="text" id="dept_code" class="form-control" placeholder="e.g. MOB">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="dept_description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('departmentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Department</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 7: DESIGNATION MODAL -->
<div id="designationModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="desigModalTitle">Designation Setup</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('designationModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitSaveDesignation();">
            <input type="hidden" id="desig_id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Job Title *</label>
                    <input type="text" id="desig_title" class="form-control" required placeholder="e.g. Senior Frontend Engineer">
                </div>
                <div class="form-group">
                    <label class="form-label">Department *</label>
                    <select id="desig_dept_id" class="form-select" required>
                        <option value="">-- Choose Department --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Role Description</label>
                    <textarea id="desig_description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('designationModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Designation</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 8: HOLIDAY MODAL -->
<div id="holidayModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="holidayModalTitle">Add Agency Holiday</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('holidayModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitSaveHoliday();">
            <input type="hidden" id="hol_id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Holiday Name *</label>
                    <input type="text" id="hol_name" class="form-control" required placeholder="e.g. Diwali / Republic Day">
                </div>
                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" id="hol_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description (Optional)</label>
                    <textarea id="hol_desc" class="form-control" rows="2" placeholder="Public festival or observance"></textarea>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" id="hol_is_optional" value="1">
                        <span>Optional / Restricted Holiday</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('holidayModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Holiday</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 9: UPLOAD EMPLOYEE DOCUMENT -->
<div id="uploadDocModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:550px;">
        <div class="modal-header">
            <h3 class="modal-title">Upload Employee Document</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('uploadDocModal')">&times;</button>
        </div>
        <form id="uploadDocForm" onsubmit="event.preventDefault(); submitUploadDocument();" enctype="multipart/form-data">
            <div class="modal-body">
                <?php if ($isHr): ?>
                    <div class="form-group">
                        <label class="form-label">Target Employee *</label>
                        <select id="doc_employee_id" name="employee_id" class="form-select" required>
                            <?php foreach ($managers as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= ($currEmp && $currEmp['id'] == $m['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['employee_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Document Title *</label>
                    <input type="text" id="doc_title" name="document_title" class="form-control" required placeholder="e.g. Signed Appointment Letter 2026">
                </div>

                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select id="doc_category" name="category" class="form-select" required>
                        <option value="Appointment Letter">Appointment Letter</option>
                        <option value="Offer Letter">Offer Letter</option>
                        <option value="Identity Document">Identity Document (Aadhaar / Passport)</option>
                        <option value="Certificates">Degree / Certification</option>
                        <option value="NDA & Agreement">NDA & Confidentiality Agreement</option>
                        <option value="General Document">General Document</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">File to Upload * (Max 10MB; PDF, DOC, DOCX, JPG, PNG)</label>
                    <input type="file" id="doc_file" name="document_file" class="form-control" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
                </div>

                <?php if ($isHr): ?>
                    <div class="form-group" style="margin-top:12px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="doc_is_confidential" name="is_confidential" value="1">
                            <span style="font-weight:600; color:#dc2626;">HR Confidential Document (Restricted: Employee cannot view)</span>
                        </label>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadDocModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitDoc">Upload File</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================================================================== -->
<!-- JAVASCRIPT LOGIC & API INTERACTION                                   -->
<!-- ==================================================================== -->
<script>
    let currentTab = 'dashboard';
    let isHrAdmin = <?= $isHr ? 'true' : 'false' ?>;
    let isLeadManager = <?= $isLead ? 'true' : 'false' ?>;
    let canApproveLeaves = (isHrAdmin || isLeadManager);
    let currentEmployeeId = <?= ($currEmp ? $currEmp['id'] : 0) ?>;
    let todayAttendanceRecord = null;
    let currentAttendanceSubView = 'calendar';
    let currentCalendarMonth = '<?= date('Y-m') ?>';
    let attendanceCalendarData = null;
    let todayAttendanceByEmp = {};

    document.addEventListener('DOMContentLoaded', () => {
        loadTodayAttendancePill();
        loadDashboardMetrics();
    });

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/[&<>"']/g, m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[m]);
    }

    // Modal Helpers
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    function openEmployeeModal() {
        document.getElementById('emp_id').value = '';
        document.getElementById('empModalTitle').textContent = 'Add New Employee';
        document.getElementById('emp_full_name').value = '';
        document.getElementById('emp_email').value = '';
        document.getElementById('emp_code').value = '';
        document.getElementById('emp_phone').value = '';
        document.getElementById('emp_doj').value = '<?= date('Y-m-d') ?>';
        document.getElementById('emp_dept_id').value = '';
        document.getElementById('emp_desig_id').value = '';
        document.getElementById('emp_manager_id').value = '';
        document.getElementById('emp_type').value = 'Full-time';
        document.getElementById('emp_status').value = 'Active';
        document.getElementById('emp_location').value = 'Office';
        document.getElementById('emp_dob').value = '';
        document.getElementById('emp_em_name').value = '';
        document.getElementById('emp_em_phone').value = '';
        document.getElementById('emp_em_rel').value = '';
        document.getElementById('emp_address').value = '';
        document.getElementById('emp_notes').value = '';
        document.getElementById('createUserLoginBox').style.display = 'block';
        document.getElementById('employeeModal').style.display = 'flex';
    }

    function openApplyLeaveModal() {
        document.getElementById('lr_leave_type_id').value = '';
        document.getElementById('lr_start_date').value = '';
        document.getElementById('lr_end_date').value = '';
        document.getElementById('lr_reason').value = '';
        document.getElementById('leaveRequestModal').style.display = 'flex';
    }

    function openAttendanceCorrectionModal() {
        document.getElementById('att_corr_id').value = '';
        document.getElementById('att_corr_in').value = '';
        document.getElementById('att_corr_out').value = '';
        document.getElementById('att_corr_hours').value = '';
        document.getElementById('att_corr_reason').value = '';
        document.getElementById('att_corr_status').value = 'Present';
        document.getElementById('attendanceCorrectionModal').style.display = 'flex';
    }

    function openDepartmentModal() {
        document.getElementById('dept_id').value = '';
        document.getElementById('deptModalTitle').textContent = 'Add New Department';
        document.getElementById('dept_name').value = '';
        document.getElementById('dept_code').value = '';
        document.getElementById('dept_description').value = '';
        document.getElementById('departmentModal').style.display = 'flex';
    }

    function openDesignationModal() {
        document.getElementById('desig_id').value = '';
        document.getElementById('desigModalTitle').textContent = 'Add New Designation';
        document.getElementById('desig_title').value = '';
        document.getElementById('desig_dept_id').value = '';
        document.getElementById('desig_description').value = '';
        document.getElementById('designationModal').style.display = 'flex';
    }

    function openHolidayModal() {
        document.getElementById('hol_id').value = '';
        document.getElementById('holidayModalTitle').textContent = 'Add Agency Holiday';
        document.getElementById('hol_name').value = '';
        document.getElementById('hol_date').value = '';
        document.getElementById('hol_desc').value = '';
        document.getElementById('hol_is_optional').checked = false;
        document.getElementById('holidayModal').style.display = 'flex';
    }

    function openUploadDocModal() {
        document.getElementById('uploadDocForm').reset();
        document.getElementById('uploadDocModal').style.display = 'flex';
    }

    // Tab Switching
    function switchHrTab(tab) {
        currentTab = tab;
        const tabs = ['dashboard', 'employees', 'attendance', 'leaves', 'departments', 'holidays', 'documents', 'reports'];
        tabs.forEach(t => {
            const btn = document.getElementById(`hrTab_${t}`);
            const view = document.getElementById(`tabView_${t}`);
            if (btn) {
                if (t === tab) {
                    btn.style.borderBottom = '3px solid #2563eb';
                    btn.style.color = '#2563eb';
                    btn.style.fontWeight = '700';
                } else {
                    btn.style.borderBottom = '3px solid transparent';
                    btn.style.color = '#64748b';
                    btn.style.fontWeight = '500';
                }
            }
            if (view) {
                view.style.display = (t === tab) ? 'block' : 'none';
            }
        });

        refreshCurrentTab();
    }

    function refreshCurrentTab() {
        if (currentTab === 'dashboard') loadDashboardMetrics();
        else if (currentTab === 'employees') loadEmployees();
        else if (currentTab === 'attendance') {
            if (currentAttendanceSubView === 'calendar') loadAttendanceCalendar();
            else loadAttendanceRecords();
        }
        else if (currentTab === 'leaves') { loadLeaveAllocations(); loadLeaveRequests(); }
        else if (currentTab === 'departments') { loadDepartments(); loadDesignations(); }
        else if (currentTab === 'holidays') loadHolidays();
        else if (currentTab === 'documents') loadEmployeeDocuments();
        else if (currentTab === 'reports') loadHrReport();
    }

    // Interactive Mark Attendance Header Pill
    function loadTodayAttendancePill() {
        fetch('api.php?action=get_today_attendance_status')
            .then(res => res.json())
            .then(data => {
                const btn = document.getElementById('markAttPillBtn');
                const txt = document.getElementById('markAttPillText');
                const badge = document.getElementById('todayAttStatusBadge');
                if (!btn || !txt) return;

                if (data.success && data.record) {
                    todayAttendanceRecord = data.record;
                    const s1 = (data.record.section_1 || '').toUpperCase();
                    const s2 = (data.record.section_2 || '').toUpperCase();
                    const status = data.record.status || 'Present';

                    let tag = status;
                    if (s1 === 'P' && s2 === 'P') tag = 'Present (P)';
                    else if (s1 === 'P' && (!s2 || s2 === 'L')) tag = 'Half Day (S1)';
                    else if (s1 === 'WFH' && s2 === 'WFH') tag = 'WFH';
                    else if (s1 === 'L' && s2 === 'L') tag = 'On Leave';

                    txt.textContent = '📅 Today Marked';
                    if (badge) {
                        badge.style.display = 'inline-block';
                        badge.textContent = tag;
                    }
                    btn.className = 'btn-pill-white';
                    btn.style.borderColor = '#10b981';
                    btn.style.color = '#10b981';
                } else {
                    todayAttendanceRecord = null;
                    txt.textContent = '📅 + Mark Attendance';
                    if (badge) badge.style.display = 'none';
                    btn.className = 'btn-pill-primary';
                    btn.style.borderColor = '';
                    btn.style.color = '';
                }
                if (window.feather) feather.replace();
            });
    }

    // Subview Switcher (Calendar vs Table)
    function setAttendanceSubView(view) {
        currentAttendanceSubView = view;
        const btnCal = document.getElementById('btnAttViewCalendar');
        const btnTab = document.getElementById('btnAttViewTable');
        const viewCal = document.getElementById('subView_attCalendar');
        const viewTab = document.getElementById('subView_attTable');

        if (view === 'calendar') {
            btnCal.className = 'btn btn-sm btn-primary';
            btnCal.style.color = '#ffffff';
            btnTab.className = 'btn btn-sm btn-outline';
            btnTab.style.border = 'none';
            btnTab.style.color = '#64748b';
            viewCal.style.display = 'block';
            viewTab.style.display = 'none';
            loadAttendanceCalendar();
        } else {
            btnTab.className = 'btn btn-sm btn-primary';
            btnTab.style.color = '#ffffff';
            btnCal.className = 'btn btn-sm btn-outline';
            btnCal.style.border = 'none';
            btnCal.style.color = '#64748b';
            viewCal.style.display = 'none';
            viewTab.style.display = 'block';
            loadAttendanceRecords();
        }
        if (window.feather) feather.replace();
    }

    // Month Navigation
    function changeAttendanceMonth(delta) {
        const parts = currentCalendarMonth.split('-');
        let y = parseInt(parts[0], 10);
        let m = parseInt(parts[1], 10) + delta;
        if (m > 12) {
            m = 1;
            y++;
        } else if (m < 1) {
            m = 12;
            y--;
        }
        currentCalendarMonth = `${y}-${String(m).padStart(2, '0')}`;
        loadAttendanceCalendar();
    }

    function jumpAttendanceToToday() {
        const now = new Date();
        currentCalendarMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        loadAttendanceCalendar();
    }

    function onAttendanceEmployeeChanged() {
        loadAttendanceCalendar();
    }

    // =========================================================
    // PREMIUM ATTENDANCE CALENDAR ENGINE
    // =========================================================
    function loadAttendanceCalendar(month, empId) {
        const m = month || currentCalendarMonth;
        const empSelect = document.getElementById('attCalendarEmployeeSelect');
        const eId = empId || (empSelect ? empSelect.value : currentEmployeeId);

        const exportBtn = document.getElementById('attExportCsvBtn');
        if (exportBtn) {
            exportBtn.href = `hr_export.php?type=attendance&month=${m}`;
        }

        const grid = document.getElementById('attendanceCalendarGrid');
        if (!grid) return;
        grid.innerHTML = '<div style="grid-column: span 7; text-align:center; padding:45px; color:#64748b;"><div style="font-size:14px; font-weight:700;">Loading Attendance Calendar...</div></div>';

        fetch(`api.php?action=get_attendance_calendar&month=${m}&employee_id=${eId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    grid.innerHTML = `<div style="grid-column: span 7; text-align:center; padding:40px; color:#ef4444;">${data.message || 'Failed to load attendance calendar.'}</div>`;
                    return;
                }

                attendanceCalendarData = data;

                // Month Title
                const parts = m.split('-');
                const yearNum = parseInt(parts[0], 10);
                const monthNum = parseInt(parts[1], 10) - 1;
                const dateObj = new Date(yearNum, monthNum, 1);
                const monthName = dateObj.toLocaleString('default', { month: 'long' });
                document.getElementById('attCalendarMonthTitle').textContent = `${monthName} ${yearNum}`;

                // Summary KPI Cards
                document.getElementById('cal_stat_present').textContent = `${data.summary.present_days} Days`;
                document.getElementById('cal_stat_halfday').textContent = `${data.summary.half_days} Days`;
                document.getElementById('cal_stat_wfh').textContent = `${data.summary.wfh_days} Days`;
                document.getElementById('cal_stat_leave').textContent = `${data.summary.leave_days} Days`;
                document.getElementById('cal_stat_effective').textContent = `${Number(data.summary.effective_days).toFixed(1)} Days`;

                // Calculate Day Cells
                const daysInMonth = new Date(yearNum, monthNum + 1, 0).getDate();
                let startDay = dateObj.getDay(); // 0 = Sun
                startDay = (startDay + 6) % 7;   // Convert Mon=0 ... Sun=6

                const prevMonthDays = new Date(yearNum, monthNum, 0).getDate();
                const todayObj = new Date();
                const todayStr = `${todayObj.getFullYear()}-${String(todayObj.getMonth() + 1).padStart(2, '0')}-${String(todayObj.getDate()).padStart(2, '0')}`;

                let html = '';

                // Previous month padding cells
                for (let i = startDay - 1; i >= 0; i--) {
                    const dayNum = prevMonthDays - i;
                    html += `
                        <div class="att-day-cell is-other-month">
                            <div class="att-day-top">
                                <span class="att-day-num" style="color:#cbd5e1;">${dayNum}</span>
                            </div>
                        </div>
                    `;
                }

                const canEditCalEmp = isHrAdmin || (currentEmployeeId > 0 && parseInt(eId, 10) === parseInt(currentEmployeeId, 10));

                // Current month active days
                for (let day = 1; day <= daysInMonth; day++) {
                    const dateStr = `${yearNum}-${String(monthNum + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const cellDate = new Date(yearNum, monthNum, day);
                    const dayOfWeek = (cellDate.getDay() + 6) % 7;
                    const isWeekend = (dayOfWeek === 5 || dayOfWeek === 6);
                    const isToday = (dateStr === todayStr);

                    const rec = (data.records_by_date && data.records_by_date[dateStr]) ? data.records_by_date[dateStr] : null;
                    const hol = (data.holidays_by_date && data.holidays_by_date[dateStr]) ? data.holidays_by_date[dateStr] : null;

                    let classes = 'att-day-cell';
                    if (isToday) classes += ' is-today';
                    if (isWeekend) classes += ' is-weekend';

                    let topTags = '';
                    if (isToday) topTags += '<span class="att-today-tag">Today</span>';
                    else if (isWeekend) topTags += '<span class="att-weekend-tag">Weekend</span>';

                    let bodyContent = '';

                    if (hol) {
                        bodyContent += `<div class="holiday-banner" title="${escapeHtml(hol.holiday_name)}">🎉 ${escapeHtml(hol.holiday_name)}</div>`;
                    }

                    if (rec) {
                        const s1 = (rec.section_1 || '').toUpperCase();
                        const s2 = (rec.section_2 || '').toUpperCase();
                        const status = rec.status || 'Present';

                        let s1Class = 'sec-empty', s1Text = 'S1: -';
                        if (s1 === 'P') { s1Class = 'sec-p'; s1Text = 'S1: P'; }
                        else if (s1 === 'L') { s1Class = 'sec-l'; s1Text = 'S1: L'; }
                        else if (s1 === 'WFH') { s1Class = 'sec-wfh'; s1Text = 'S1: WFH'; }

                        let s2Class = 'sec-empty', s2Text = 'S2: -';
                        if (s2 === 'P') { s2Class = 'sec-p'; s2Text = 'S2: P'; }
                        else if (s2 === 'L') { s2Class = 'sec-l'; s2Text = 'S2: L'; }
                        else if (s2 === 'WFH') { s2Class = 'sec-wfh'; s2Text = 'S2: WFH'; }

                        let stClass = 'st-present';
                        let stLabel = '● Present (Full)';
                        if (status === 'Half Day' || ((s1 === 'P' || s1 === 'WFH') && (!s2 || s2 === 'L')) || ((s2 === 'P' || s2 === 'WFH') && (!s1 || s1 === 'L'))) {
                            stClass = 'st-halfday';
                            stLabel = s1 ? '◐ Half Day (S1)' : '◑ Half Day (S2)';
                        } else if (status === 'WFH' || (s1 === 'WFH' && s2 === 'WFH')) {
                            stClass = 'st-wfh';
                            stLabel = '🏠 WFH (Full)';
                        } else if (status === 'On Leave' || (s1 === 'L' && s2 === 'L')) {
                            stClass = 'st-leave';
                            stLabel = '🌴 Leave (Full)';
                        } else if (status === 'Present / WFH' || status === 'WFH / Present') {
                            stClass = 'st-mixed';
                            stLabel = '🏢/🏠 Mixed';
                        }

                        bodyContent += `
                            <div class="att-sec-tags-row">
                                <span class="sec-tag ${s1Class}">${s1Text}</span>
                                <span class="sec-tag ${s2Class}">${s2Text}</span>
                            </div>
                            <div class="day-status-pill ${stClass}">
                                ${stLabel}
                            </div>
                        `;

                        if (rec.notes) {
                            bodyContent += `<div style="font-size:9px; color:#64748b; margin-top:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(rec.notes)}">💬 ${escapeHtml(rec.notes)}</div>`;
                        }
                    } else if (canEditCalEmp && !isWeekend && dateStr <= todayStr) {
                        bodyContent += `
                            <div style="text-align:center; padding:10px 0; color:#94a3b8; font-size:11px;">
                                <span style="opacity:0.75;">+ Mark</span>
                            </div>
                        `;
                    }

                    const clickAttr = canEditCalEmp ? `onclick="openMarkAttendanceModal('${dateStr}', ${eId})"` : `style="cursor:default;"`;
                    html += `
                        <div class="${classes}" ${clickAttr}>
                            <div class="att-day-top">
                                <span class="att-day-num">${day}</span>
                                ${topTags}
                            </div>
                            <div style="flex:1; display:flex; flex-direction:column; justify-content:center;">
                                ${bodyContent}
                            </div>
                        </div>
                    `;
                }

                // Next month padding cells
                const totalRendered = startDay + daysInMonth;
                const remaining = (7 - (totalRendered % 7)) % 7;
                for (let i = 1; i <= remaining; i++) {
                    html += `
                        <div class="att-day-cell is-other-month">
                            <div class="att-day-top">
                                <span class="att-day-num" style="color:#cbd5e1;">${i}</span>
                            </div>
                        </div>
                    `;
                }

                grid.innerHTML = html;
                if (window.feather) feather.replace();
            })
            .catch(err => {
                grid.innerHTML = `<div style="grid-column: span 7; text-align:center; padding:40px; color:#ef4444;">Error loading calendar: ${err.message}</div>`;
            });
    }

    // =========================================================
    // MARK ATTENDANCE MODAL CONTROLS
    // =========================================================
    function openMarkAttendanceModal(date, empId) {
        const requestedEmpId = empId ? parseInt(empId, 10) : parseInt(currentEmployeeId, 10);
        if (!isHrAdmin && requestedEmpId && requestedEmpId !== parseInt(currentEmployeeId, 10)) {
            alert('Access Denied: Only HR and Super Admin can mark or edit attendance for other employees.');
            return;
        }
        const effectiveEmpId = isHrAdmin ? requestedEmpId : parseInt(currentEmployeeId, 10);
        if (!effectiveEmpId) {
            alert('No linked employee profile found for your account.');
            return;
        }

        const todayDateStr = '<?= date('Y-m-d') ?>';
        const targetDate = date || todayDateStr;
        document.getElementById('mark_att_date').value = targetDate;

        const empSelect = document.getElementById('mark_att_emp_id');
        if (effectiveEmpId && empSelect) {
            empSelect.value = effectiveEmpId;
        }

        updateMarkModalHeader(targetDate);

        // Find existing record for this exact employee if present
        let existing = null;
        if (attendanceCalendarData && attendanceCalendarData.employee && parseInt(attendanceCalendarData.employee.id, 10) === effectiveEmpId && attendanceCalendarData.records_by_date && attendanceCalendarData.records_by_date[targetDate]) {
            existing = attendanceCalendarData.records_by_date[targetDate];
        } else if (targetDate === todayDateStr && todayAttendanceByEmp && todayAttendanceByEmp[effectiveEmpId]) {
            const row = todayAttendanceByEmp[effectiveEmpId];
            if (row.section_1 || row.section_2 || row.attendance_status) {
                existing = {
                    id: '',
                    section_1: row.section_1 || '',
                    section_2: row.section_2 || '',
                    notes: ''
                };
            }
        }

        const delBtn = document.getElementById('btnDeleteAttendanceDate');
        const notesInput = document.getElementById('mark_att_notes');

        if (existing) {
            document.getElementById('mark_att_existing_id').value = existing.id || '';
            notesInput.value = existing.notes || '';
            if (delBtn) delBtn.style.display = 'inline-flex';

            setRadioValue('mark_sec_1', (existing.section_1 || '').toUpperCase());
            setRadioValue('mark_sec_2', (existing.section_2 || '').toUpperCase());
        } else {
            document.getElementById('mark_att_existing_id').value = '';
            notesInput.value = '';
            if (delBtn) delBtn.style.display = 'none';

            // Default preset: Full day Present (P + P)
            setRadioValue('mark_sec_1', 'P');
            setRadioValue('mark_sec_2', 'P');
        }

        updateAttendanceOutcomePreview();
        document.getElementById('markAttendanceModal').style.display = 'flex';
        if (window.feather) feather.replace();
    }

    function updateMarkModalHeader(date) {
        const empSelect = document.getElementById('mark_att_emp_id');
        const empDisplay = document.getElementById('mark_att_emp_display');
        let empName = 'Staff';
        if (empSelect && empSelect.options && empSelect.selectedIndex >= 0) {
            empName = empSelect.options[empSelect.selectedIndex].text;
        } else if (empDisplay && empDisplay.value) {
            empName = empDisplay.value;
        }
        document.getElementById('markAttModalSubtitle').textContent = `Mark Section 1 & Section 2 for ${empName} on ${date}`;
    }

    function onMarkModalEmployeeChanged() {
        const date = document.getElementById('mark_att_date').value;
        updateMarkModalHeader(date);
    }

    function onMarkModalDateChanged() {
        const date = document.getElementById('mark_att_date').value;
        const empSelect = document.getElementById('mark_att_emp_id');
        const empId = empSelect ? empSelect.value : currentEmployeeId;
        openMarkAttendanceModal(date, empId);
    }

    function setRadioValue(name, val) {
        const radios = document.querySelectorAll(`input[name="${name}"]`);
        radios.forEach(r => {
            r.checked = (r.value === val);
        });
    }

    function getRadioValue(name) {
        const checked = document.querySelector(`input[name="${name}"]:checked`);
        return checked ? checked.value : '';
    }

    function applyAttendancePreset(preset) {
        if (preset === 'FULL_P') {
            setRadioValue('mark_sec_1', 'P');
            setRadioValue('mark_sec_2', 'P');
        } else if (preset === 'HALF_S1') {
            setRadioValue('mark_sec_1', 'P');
            setRadioValue('mark_sec_2', '');
        } else if (preset === 'HALF_S2') {
            setRadioValue('mark_sec_1', '');
            setRadioValue('mark_sec_2', 'P');
        } else if (preset === 'FULL_WFH') {
            setRadioValue('mark_sec_1', 'WFH');
            setRadioValue('mark_sec_2', 'WFH');
        } else if (preset === 'FULL_L') {
            setRadioValue('mark_sec_1', 'L');
            setRadioValue('mark_sec_2', 'L');
        }
        updateAttendanceOutcomePreview();
    }

    function updateAttendanceOutcomePreview() {
        const s1 = getRadioValue('mark_sec_1');
        const s2 = getRadioValue('mark_sec_2');

        ['mark_sec_1', 'mark_sec_2'].forEach(name => {
            const radios = document.querySelectorAll(`input[name="${name}"]`);
            radios.forEach(r => {
                const parent = r.closest('.sec-opt-btn');
                if (parent) {
                    parent.classList.remove('is-selected-p', 'is-selected-l', 'is-selected-wfh', 'is-selected-none');
                    if (r.checked) {
                        if (r.value === 'P') parent.classList.add('is-selected-p');
                        else if (r.value === 'L') parent.classList.add('is-selected-l');
                        else if (r.value === 'WFH') parent.classList.add('is-selected-wfh');
                        else parent.classList.add('is-selected-none');
                    }
                }
            });
        });

        const outText = document.getElementById('markOutcomeText');
        const outBadge = document.getElementById('markOutcomeBadge');

        if (s1 === 'P' && s2 === 'P') {
            outText.innerHTML = '<span style="color:#15803d; font-weight:800;">🟢 Full Day Present &bull; 1.0 Day Credit (8.5 Hours)</span>';
            outBadge.innerHTML = '<span class="badge" style="background:#dcfce7; color:#15803d; font-weight:800; font-size:12px;">Present (P)</span>';
        } else if (s1 === 'WFH' && s2 === 'WFH') {
            outText.innerHTML = '<span style="color:#4338ca; font-weight:800;">🟣 Full Day Work From Home &bull; 1.0 Day Credit (8.5 Hours)</span>';
            outBadge.innerHTML = '<span class="badge" style="background:#ede9fe; color:#6d28d9; font-weight:800; font-size:12px;">WFH Full</span>';
        } else if (s1 === 'L' && s2 === 'L') {
            outText.innerHTML = '<span style="color:#b91c1c; font-weight:800;">🔴 Full Day Leave &bull; 0.0 Day Credit (0 Hours)</span>';
            outBadge.innerHTML = '<span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:800; font-size:12px;">On Leave (L)</span>';
        } else if (s1 === 'P' && (!s2 || s2 === 'L')) {
            outText.innerHTML = '<span style="color:#b45309; font-weight:800;">🟡 Half Day (Section 1 Marked Only) &bull; 0.5 Day Credit (4.25 Hours)</span>';
            outBadge.innerHTML = '<span class="badge" style="background:#fef3c7; color:#b45309; font-weight:800; font-size:12px;">Half Day (S1)</span>';
        } else if ((!s1 || s1 === 'L') && s2 === 'P') {
            outText.innerHTML = '<span style="color:#b45309; font-weight:800;">🟡 Half Day (Section 2 Marked Only) &bull; 0.5 Day Credit (4.25 Hours)</span>';
            outBadge.innerHTML = '<span class="badge" style="background:#fef3c7; color:#b45309; font-weight:800; font-size:12px;">Half Day (S2)</span>';
        } else if (s1 === 'WFH' && (!s2 || s2 === 'L')) {
            outText.innerHTML = '<span style="color:#b45309; font-weight:800;">🟣 Half Day WFH (Section 1 Only) &bull; 0.5 Day Credit (4.25 Hours)</span>';
            outBadge.innerHTML = '<span class="badge" style="background:#ede9fe; color:#6d28d9; font-weight:800; font-size:12px;">Half Day WFH</span>';
        } else if ((!s1 || s1 === 'L') && s2 === 'WFH') {
            outText.innerHTML = '<span style="color:#b45309; font-weight:800;">🟣 Half Day WFH (Section 2 Only) &bull; 0.5 Day Credit (4.25 Hours)</span>';
            outBadge.innerHTML = '<span class="badge" style="background:#ede9fe; color:#6d28d9; font-weight:800; font-size:12px;">Half Day WFH</span>';
        } else if (s1 === 'P' && s2 === 'WFH') {
            outText.innerHTML = '<span style="color:#15803d; font-weight:800;">🏢/🏠 Morning Office (P) + Afternoon WFH &bull; 1.0 Day Credit</span>';
            outBadge.innerHTML = '<span class="badge" style="background:#f0fdf4; color:#15803d; font-weight:800; font-size:12px;">P + WFH</span>';
        } else if (s1 === 'WFH' && s2 === 'P') {
            outText.innerHTML = '<span style="color:#15803d; font-weight:800;">🏠/🏢 Morning WFH + Afternoon Office (P) &bull; 1.0 Day Credit</span>';
            outBadge.innerHTML = '<span class="badge" style="background:#f0fdf4; color:#15803d; font-weight:800; font-size:12px;">WFH + P</span>';
        } else {
            outText.innerHTML = '<span style="color:#64748b; font-weight:700;">⚪ Off / Not Marked (Will clear attendance for date)</span>';
            outBadge.innerHTML = '<span class="badge" style="background:#f1f5f9; color:#64748b; font-weight:700; font-size:12px;">Unmarked / Off</span>';
        }
    }

    function submitMarkAttendance() {
        const empSelect = document.getElementById('mark_att_emp_id');
        const empId = empSelect ? empSelect.value : currentEmployeeId;
        const date = document.getElementById('mark_att_date').value;
        const s1 = getRadioValue('mark_sec_1');
        const s2 = getRadioValue('mark_sec_2');
        const notes = document.getElementById('mark_att_notes').value;

        const payload = {
            employee_id: empId,
            attendance_date: date,
            section_1: s1,
            section_2: s2,
            notes: notes
        };

        fetch('api.php?action=mark_attendance', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('markAttendanceModal');
                alert('✅ ' + data.message);
                loadAttendanceCalendar();
                loadTodayAttendancePill();
                if (currentTab === 'dashboard') loadDashboardMetrics();
                if (currentAttendanceSubView === 'table') loadAttendanceRecords();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function deleteCurrentAttendanceDate() {
        const empSelect = document.getElementById('mark_att_emp_id');
        const empId = empSelect ? empSelect.value : currentEmployeeId;
        const date = document.getElementById('mark_att_date').value;

        if (!confirm(`Are you sure you want to remove the attendance entry for ${date}?`)) return;

        fetch('api.php?action=delete_attendance_record', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ employee_id: empId, date: date })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('markAttendanceModal');
                alert('✅ ' + data.message);
                loadAttendanceCalendar();
                loadTodayAttendancePill();
                if (currentTab === 'dashboard') loadDashboardMetrics();
                if (currentAttendanceSubView === 'table') loadAttendanceRecords();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // 1. DASHBOARD DATA LOADER
    function loadDashboardMetrics() {
        fetch('api.php?action=get_hr_dashboard_metrics')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                // Stat Cards
                document.getElementById('kpi_total_employees').textContent = data.metrics.total_employees;
                document.getElementById('kpi_active_sub').textContent = `Active Employees: ${data.metrics.active_employees}`;
                document.getElementById('kpi_present_today').textContent = data.metrics.present_today;
                document.getElementById('kpi_on_leave_today').textContent = data.metrics.on_leave_today;
                document.getElementById('kpi_pending_leaves').textContent = data.metrics.pending_leave_requests;

                // Personal Leave Balances Banner
                if (data.my_leave_balances && data.my_leave_balances.length > 0) {
                    const banner = document.getElementById('personalLeaveBanner');
                    const container = document.getElementById('personalLeavePills');
                    banner.style.display = 'block';
                    let phtml = '';
                    data.my_leave_balances.forEach(b => {
                        phtml += `
                            <div style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); padding:6px 12px; border-radius:8px; text-align:center;">
                                <span style="font-size:10px; color:#94a3b8; text-transform:uppercase; font-weight:700;">${b.leave_type_code}</span>
                                <div style="font-size:16px; font-weight:800; color:#38bdf8;">${b.remaining_days} <small style="font-size:11px; color:#cbd5e1;">/ ${b.total_allocated}</small></div>
                            </div>
                        `;
                    });
                    container.innerHTML = phtml;
                }

                // Pending Leaves Queue
                const pBox = document.getElementById('dashPendingLeavesList');
                if (data.pending_leaves.length === 0) {
                    pBox.innerHTML = '<p style="text-align:center; padding:20px; color:#64748b; font-size:13px;">No pending leave requests awaiting approval.</p>';
                } else {
                    let html = '<div style="display:flex; flex-direction:column; gap:10px;">';
                    data.pending_leaves.forEach(l => {
                        html += `
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:700; font-size:13px; color:#0f172a;">${escapeHtml(l.employee_name)} (${l.employee_code})</div>
                                    <div style="font-size:12px; color:#64748b;">${l.leave_type_name} • ${l.start_date} to ${l.end_date} (${l.total_days} days)</div>
                                    <div style="font-size:11px; color:#475569; font-style:italic; margin-top:3px;">"${escapeHtml(l.reason)}"</div>
                                </div>
                                ${canApproveLeaves ? `
                                    <div style="display:flex; gap:6px;">
                                        <button class="btn btn-outline btn-sm" style="color:#16a34a; border-color:#86efac; font-size:11px;" onclick="openLeaveDecisionModal(${l.id}, 'Approved', '${escapeHtml(l.employee_name)}')">Approve</button>
                                        <button class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fca5a5; font-size:11px;" onclick="openLeaveDecisionModal(${l.id}, 'Rejected', '${escapeHtml(l.employee_name)}')">Reject</button>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    });
                    html += '</div>';
                    pBox.innerHTML = html;
                }

                // Today's Attendance Overview
                todayAttendanceByEmp = {};
                const tBody = document.getElementById('dashTodayAttendanceBody');
                if (!data.today_attendance || data.today_attendance.length === 0) {
                    tBody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#64748b;">No active employees found.</td></tr>';
                } else {
                    let html = '';
                    data.today_attendance.forEach(a => {
                        todayAttendanceByEmp[parseInt(a.employee_id, 10)] = a;
                        const canMarkRow = isHrAdmin || (currentEmployeeId > 0 && parseInt(a.employee_id, 10) === parseInt(currentEmployeeId, 10));
                        const s1 = (a.section_1 || '').toUpperCase();
                        const s2 = (a.section_2 || '').toUpperCase();

                        let s1Pill = '<span class="sec-tag sec-empty">S1: -</span>';
                        if (s1 === 'P') s1Pill = '<span class="sec-tag sec-p">S1: P</span>';
                        else if (s1 === 'L') s1Pill = '<span class="sec-tag sec-l">S1: L</span>';
                        else if (s1 === 'WFH') s1Pill = '<span class="sec-tag sec-wfh">S1: WFH</span>';

                        let s2Pill = '<span class="sec-tag sec-empty">S2: -</span>';
                        if (s2 === 'P') s2Pill = '<span class="sec-tag sec-p">S2: P</span>';
                        else if (s2 === 'L') s2Pill = '<span class="sec-tag sec-l">S2: L</span>';
                        else if (s2 === 'WFH') s2Pill = '<span class="sec-tag sec-wfh">S2: WFH</span>';

                        let badgeClass = 'badge-secondary';
                        let statusText = a.attendance_status || 'Not Marked';
                        if (statusText === 'Present') badgeClass = 'badge-primary';
                        else if (statusText === 'Half Day') badgeClass = 'badge-warning';
                        else if (statusText === 'On Leave') badgeClass = 'badge-warning';
                        else if (statusText === 'WFH') badgeClass = 'badge-primary';

                        // Live Presence indicator
                        const wStatus = (a.work_status || 'IN_OFFICE').toUpperCase();
                        let presPill = '<span class="badge badge-success" style="font-size:11px; padding:3px 8px; font-weight:700;">🟢 At Desk</span>';
                        if (wStatus === 'TEA_BREAK') {
                            presPill = '<span class="badge badge-warning" style="font-size:11px; padding:3px 8px; font-weight:700;">☕ Tea Break</span>';
                        } else if (wStatus === 'AFK') {
                            presPill = '<span class="badge badge-warning" style="font-size:11px; padding:3px 8px; font-weight:700; background:#ffedd5; color:#c2410c;">⏸️ AFK</span>';
                        } else if (wStatus === 'MEETING') {
                            presPill = '<span class="badge badge-primary" style="font-size:11px; padding:3px 8px; font-weight:700; background:#f3e8ff; color:#7e22ce;">👥 Meeting</span>';
                        } else if (wStatus === 'LUNCH') {
                            presPill = '<span class="badge" style="font-size:11px; padding:3px 8px; font-weight:700; background:#fce7f3; color:#be185d;">🍽️ Lunch</span>';
                        } else if (wStatus === 'OUT_OF_OFFICE') {
                            presPill = '<span class="badge badge-secondary" style="font-size:11px; padding:3px 8px; font-weight:700;">🚪 Out of Office</span>';
                        }
                        if (a.status_note) {
                            presPill += `<div style="font-size:10px; color:#64748b; margin-top:2px; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(a.status_note)}">"${escapeHtml(a.status_note)}"</div>`;
                        }

                        html += `
                            <tr>
                                <td><strong>${escapeHtml(a.full_name)}</strong> <small style="color:#64748b;">(${a.employee_code})</small></td>
                                <td>${escapeHtml(a.department_name || 'General')}</td>
                                <td>${presPill}</td>
                                <td>${s1Pill}</td>
                                <td>${s2Pill}</td>
                                <td><span class="badge ${badgeClass}">${statusText}</span></td>
                                <td>${a.total_hours ? a.total_hours + ' hrs' : '--'}</td>
                                <td style="text-align:right;">
                                    ${canMarkRow
                                        ? `<button class="btn btn-outline btn-sm" onclick="openMarkAttendanceModal('<?= date('Y-m-d') ?>', ${a.employee_id})" style="border-radius:6px; font-size:11px;">Mark / Edit</button>`
                                        : `<span style="color:#94a3b8; font-size:11px;">View Only</span>`
                                    }
                                </td>
                            </tr>
                        `;
                    });
                    tBody.innerHTML = html;
                }

                // Upcoming Holidays
                const hBox = document.getElementById('dashUpcomingHolidaysList');
                if (data.upcoming_holidays.length === 0) {
                    hBox.innerHTML = '<p style="text-align:center; padding:15px; color:#64748b; font-size:13px;">No upcoming holidays in the immediate period.</p>';
                } else {
                    let html = '<div style="display:flex; flex-direction:column; gap:8px;">';
                    data.upcoming_holidays.forEach(h => {
                        html += `
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                                <div>
                                    <strong style="font-size:13px; color:#0f172a;">${escapeHtml(h.holiday_name)}</strong>
                                    <div style="font-size:11px; color:#64748b;">${escapeHtml(h.description || 'Public Holiday')}</div>
                                </div>
                                <span class="badge badge-primary" style="font-size:11px;">${h.holiday_date}</span>
                            </div>
                        `;
                    });
                    html += '</div>';
                    hBox.innerHTML = html;
                }

                // Upcoming Events / Celebrations
                const eBox = document.getElementById('dashUpcomingEventsList');
                if (data.upcoming_events.length === 0) {
                    eBox.innerHTML = '<p style="text-align:center; padding:15px; color:#64748b; font-size:13px;">No birthdays or work anniversaries in the next 30 days.</p>';
                } else {
                    let html = '<div style="display:flex; flex-direction:column; gap:8px;">';
                    data.upcoming_events.forEach(ev => {
                        const icon = ev.type === 'Birthday' ? '🎂' : '🏆';
                        html += `
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:#fdf2f8; border-radius:8px; border:1px solid #fbcfe8;">
                                <div>
                                    <span style="font-size:14px; margin-right:4px;">${icon}</span>
                                    <strong style="font-size:13px; color:#831843;">${escapeHtml(ev.employee)}</strong>
                                    <span style="font-size:11px; color:#9d174d;">(${ev.type}${ev.years ? ` • ${ev.years} Years` : ''})</span>
                                </div>
                                <span style="font-size:12px; font-weight:700; color:#db2777;">${ev.date}</span>
                            </div>
                        `;
                    });
                    html += '</div>';
                    eBox.innerHTML = html;
                }

                // Recent HR Activities
                const aBox = document.getElementById('dashHrActivitiesList');
                if (data.hr_activities.length === 0) {
                    aBox.innerHTML = '<p style="text-align:center; padding:15px; color:#64748b; font-size:13px;">No recent activities logged.</p>';
                } else {
                    let html = '<ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">';
                    data.hr_activities.forEach(act => {
                        html += `
                            <li style="font-size:12px; color:#475569; padding-bottom:6px; border-bottom:1px solid #f1f5f9;">
                                <strong style="color:#0f172a;">${escapeHtml(act.user_name || act.username)}</strong>: ${escapeHtml(act.details || act.action)}
                                <div style="font-size:10px; color:#94a3b8; margin-top:2px;">${act.created_at}</div>
                            </li>
                        `;
                    });
                    html += '</ul>';
                    aBox.innerHTML = html;
                }

                if (window.feather) feather.replace();
            });
    }

    // 2. EMPLOYEE DIRECTORY LOADER
    function loadEmployees() {
        const search = document.getElementById('empSearch').value;
        const deptId = document.getElementById('empFilterDept').value;
        const status = document.getElementById('empFilterStatus').value;

        fetch(`api.php?action=get_employees&search=${encodeURIComponent(search)}&department_id=${deptId}&status=${status}`)
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('employeesTableBody');
                if (!data.success || data.employees.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:#64748b;">No employees match the criteria.</td></tr>';
                    return;
                }

                let html = '';
                data.employees.forEach(e => {
                    const statusClass = e.employment_status === 'Active' ? 'badge-primary' : (e.employment_status === 'On Leave' ? 'badge-warning' : 'badge-danger');
                    html += `
                        <tr>
                            <td><strong style="color:#2563eb;">${e.employee_code}</strong></td>
                            <td>
                                <div style="font-weight:700; color:#0f172a;">${escapeHtml(e.full_name)}</div>
                                ${e.linked_username ? `<small style="color:#64748b;">Login: @${escapeHtml(e.linked_username)}</small>` : ''}
                            </td>
                            <td>
                                <div>${escapeHtml(e.designation_title || 'Staff')}</div>
                                <small style="color:#64748b;">${escapeHtml(e.department_name || 'General')}</small>
                            </td>
                            <td>
                                <div>${escapeHtml(e.email)}</div>
                                <small style="color:#64748b;">${escapeHtml(e.phone || '--')}</small>
                            </td>
                            <td>${e.date_of_joining}</td>
                            <td>${escapeHtml(e.employment_type || 'Full-time')}</td>
                            <td><span class="badge ${statusClass}">${e.employment_status}</span></td>
                            <td style="text-align:right;">
                                <div style="display:flex; justify-content:flex-end; gap:6px;">
                                    <button class="btn btn-outline btn-sm" onclick="viewEmployeeDossier(${e.id})">Profile</button>
                                    ${isHrAdmin ? `
                                        <button class="btn btn-outline btn-sm" onclick="editEmployeeRecord(${e.id})">Edit</button>
                                        <button class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fca5a5;" onclick="toggleEmployeeStatus(${e.id})">
                                            ${e.employment_status === 'Active' ? 'Deactivate' : 'Activate'}
                                        </button>
                                    ` : ''}
                                </div>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
                if (window.feather) feather.replace();
            });
    }

    function viewEmployeeDossier(id) {
        fetch(`api.php?action=get_employee_details&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message);
                    return;
                }
                const emp = data.employee;
                document.getElementById('viewEmpTitle').textContent = `${emp.full_name} (${emp.employee_code})`;

                let html = `
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; background:#f8fafc; padding:16px; border-radius:10px; border:1px solid #e2e8f0;">
                        <div>
                            <h2 style="font-size:18px; font-weight:800; color:#0f172a; margin:0;">${escapeHtml(emp.full_name)}</h2>
                            <p style="color:#2563eb; font-weight:600; margin:3px 0 0 0;">${escapeHtml(emp.designation_title || 'Staff')} • ${escapeHtml(emp.department_name || 'General')}</p>
                            <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;">Reporting Manager: <strong>${escapeHtml(emp.reporting_manager_name || 'None')}</strong></p>
                        </div>
                        <div style="text-align:right;">
                            <span class="badge ${emp.employment_status === 'Active' ? 'badge-primary' : 'badge-danger'}">${emp.employment_status}</span>
                            <div style="font-size:12px; color:#64748b; margin-top:6px;">Joined: ${emp.date_of_joining}</div>
                        </div>
                    </div>

                    <div class="grid-3" style="margin-bottom:20px; font-size:13px;">
                        <div><strong>Official Email:</strong><br>${escapeHtml(emp.email)}</div>
                        <div><strong>Phone:</strong><br>${escapeHtml(emp.phone || '--')}</div>
                        <div><strong>Work Location:</strong><br>${escapeHtml(emp.work_location || 'Office')}</div>
                    </div>

                    <div class="grid-3" style="margin-bottom:20px; font-size:13px;">
                        <div><strong>Emergency Contact:</strong><br>${escapeHtml(emp.emergency_contact_name || '--')} (${escapeHtml(emp.emergency_contact_relation || '--')})</div>
                        <div><strong>Emergency Phone:</strong><br>${escapeHtml(emp.emergency_contact_phone || '--')}</div>
                        <div><strong>Address:</strong><br>${escapeHtml(emp.address || '--')}</div>
                    </div>

                    ${emp.hr_notes ? `
                        <div style="background:#fffbeb; border:1px solid #fde68a; padding:12px; border-radius:8px; margin-bottom:20px; font-size:12px;">
                            <strong style="color:#b45309;">Confidential HR Notes:</strong>
                            <p style="margin:4px 0 0 0; color:#78350f;">${escapeHtml(emp.hr_notes)}</p>
                        </div>
                    ` : ''}

                    <h4 style="font-size:14px; font-weight:700; margin-bottom:8px;">Leave Allocations (<?= date('Y') ?>)</h4>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
                        ${data.leave_allocations.map(la => `
                            <div style="background:#f1f5f9; padding:8px 14px; border-radius:8px; font-size:12px;">
                                <strong>${la.leave_type_name}</strong>: ${la.remaining_days} remaining (${la.used_days} used of ${la.total_allocated})
                            </div>
                        `).join('')}
                    </div>

                    <h4 style="font-size:14px; font-weight:700; margin-bottom:8px;">Uploaded Documents (${data.documents.length})</h4>
                    <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:20px;">
                        ${data.documents.length === 0 ? '<p style="color:#64748b; font-size:12px;">No documents uploaded.</p>' : data.documents.map(d => `
                            <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; padding:8px 12px; border-radius:6px; border:1px solid #e2e8f0; font-size:12px;">
                                <span>📄 <strong>${escapeHtml(d.document_title)}</strong> <small style="color:#64748b;">(${d.category})</small></span>
                                <a href="hr_download.php?id=${d.id}" target="_blank" class="btn btn-outline btn-sm" style="font-size:11px;">Download</a>
                            </div>
                        `).join('')}
                    </div>
                `;

                document.getElementById('viewEmpBody').innerHTML = html;
                document.getElementById('employeeViewModal').style.display = 'flex';
            });
    }

    function editEmployeeRecord(id) {
        fetch(`api.php?action=get_employee_details&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message);
                    return;
                }
                const emp = data.employee;
                document.getElementById('emp_id').value = emp.id;
                document.getElementById('empModalTitle').textContent = `Edit Employee: ${emp.full_name}`;
                document.getElementById('emp_full_name').value = emp.full_name;
                document.getElementById('emp_email').value = emp.email;
                document.getElementById('emp_code').value = emp.employee_code;
                document.getElementById('emp_phone').value = emp.phone || '';
                document.getElementById('emp_doj').value = emp.date_of_joining || '';
                document.getElementById('emp_dept_id').value = emp.department_id || '';
                document.getElementById('emp_desig_id').value = emp.designation_id || '';
                document.getElementById('emp_manager_id').value = emp.reporting_manager_id || '';
                document.getElementById('emp_type').value = emp.employment_type || 'Full-time';
                document.getElementById('emp_status').value = emp.employment_status || 'Active';
                document.getElementById('emp_location').value = emp.work_location || 'Office';
                document.getElementById('emp_dob').value = emp.date_of_birth || '';
                document.getElementById('emp_em_name').value = emp.emergency_contact_name || '';
                document.getElementById('emp_em_phone').value = emp.emergency_contact_phone || '';
                document.getElementById('emp_em_rel').value = emp.emergency_contact_relation || '';
                document.getElementById('emp_address').value = emp.address || '';
                document.getElementById('emp_notes').value = emp.hr_notes || '';
                document.getElementById('createUserLoginBox').style.display = 'none';

                document.getElementById('employeeModal').style.display = 'flex';
            });
    }

    function submitSaveEmployee() {
        const payload = {
            id: document.getElementById('emp_id').value,
            full_name: document.getElementById('emp_full_name').value,
            email: document.getElementById('emp_email').value,
            employee_code: document.getElementById('emp_code').value,
            phone: document.getElementById('emp_phone').value,
            date_of_joining: document.getElementById('emp_doj').value,
            department_id: document.getElementById('emp_dept_id').value,
            designation_id: document.getElementById('emp_desig_id').value,
            reporting_manager_id: document.getElementById('emp_manager_id').value,
            employment_type: document.getElementById('emp_type').value,
            employment_status: document.getElementById('emp_status').value,
            work_location: document.getElementById('emp_location').value,
            date_of_birth: document.getElementById('emp_dob').value,
            emergency_contact_name: document.getElementById('emp_em_name').value,
            emergency_contact_phone: document.getElementById('emp_em_phone').value,
            emergency_contact_relation: document.getElementById('emp_em_rel').value,
            address: document.getElementById('emp_address').value,
            hr_notes: document.getElementById('emp_notes').value,
            create_user_login: document.getElementById('emp_create_login') ? (document.getElementById('emp_create_login').checked ? 1 : 0) : 0,
            user_role_id: document.getElementById('emp_user_role') ? document.getElementById('emp_user_role').value : 5
        };

        fetch('api.php?action=save_employee', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('employeeModal');
                alert('✅ ' + data.message);
                loadEmployees();
                loadDashboardMetrics();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function toggleEmployeeStatus(id) {
        if (!confirm('Toggle active/inactive status for this employee?')) return;
        fetch('api.php?action=deactivate_employee', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            loadEmployees();
            loadDashboardMetrics();
        });
    }

    // 3. ATTENDANCE MANAGEMENT LOADER (TABLE VIEW)
    function loadAttendanceRecords() {
        const date = document.getElementById('attFilterDate').value;
        const deptId = document.getElementById('attFilterDept') ? document.getElementById('attFilterDept').value : '';
        const status = document.getElementById('attFilterStatus') ? document.getElementById('attFilterStatus').value : '';

        fetch(`api.php?action=get_attendance_records&date=${date}&department_id=${deptId}&status=${status}`)
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('attendanceTableBody');
                if (!data.success || data.records.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:30px; color:#64748b;">No attendance records found for ${date}.</td></tr>`;
                    return;
                }

                let html = '';
                data.records.forEach(r => {
                    const canEditAttRow = isHrAdmin || (currentEmployeeId > 0 && parseInt(r.employee_id, 10) === parseInt(currentEmployeeId, 10));
                    const s1 = (r.section_1 || '').toUpperCase();
                    const s2 = (r.section_2 || '').toUpperCase();

                    let s1Pill = '<span class="sec-tag sec-empty">S1: -</span>';
                    if (s1 === 'P') s1Pill = '<span class="sec-tag sec-p">S1: P</span>';
                    else if (s1 === 'L') s1Pill = '<span class="sec-tag sec-l">S1: L</span>';
                    else if (s1 === 'WFH') s1Pill = '<span class="sec-tag sec-wfh">S1: WFH</span>';

                    let s2Pill = '<span class="sec-tag sec-empty">S2: -</span>';
                    if (s2 === 'P') s2Pill = '<span class="sec-tag sec-p">S2: P</span>';
                    else if (s2 === 'L') s2Pill = '<span class="sec-tag sec-l">S2: L</span>';
                    else if (s2 === 'WFH') s2Pill = '<span class="sec-tag sec-wfh">S2: WFH</span>';

                    let statusClass = 'badge-secondary';
                    if (r.status === 'Present') statusClass = 'badge-primary';
                    else if (r.status === 'Half Day') statusClass = 'badge-warning';
                    else if (r.status === 'On Leave') statusClass = 'badge-warning';
                    else if (r.status === 'Absent') statusClass = 'badge-danger';
                    else if (r.status === 'WFH') statusClass = 'badge-primary';

                    html += `
                        <tr>
                            <td><strong>${r.attendance_date}</strong></td>
                            <td>
                                <div style="font-weight:700; color:#0f172a;">${escapeHtml(r.employee_name)}</div>
                                <small style="color:#64748b;">${r.employee_code}</small>
                            </td>
                            <td>${escapeHtml(r.department_name || 'General')}</td>
                            <td>${s1Pill}</td>
                            <td>${s2Pill}</td>
                            <td><span class="badge ${statusClass}">${r.status}</span></td>
                            <td>${r.total_hours ? r.total_hours + ' hrs' : '--'}</td>
                            <td>
                                <div style="font-size:12px; color:#475569;">${escapeHtml(r.notes || '--')}</div>
                                ${r.is_corrected ? `<small style="color:#d97706; font-weight:600;">[Corrected by ${escapeHtml(r.corrected_by_name || 'HR')}]</small>` : ''}
                            </td>
                            <td style="text-align:right;">
                                ${canEditAttRow
                                    ? `<button class="btn btn-outline btn-sm" onclick="openMarkAttendanceModal('${r.attendance_date}', ${r.employee_id})" style="border-radius:6px; font-size:11px;">Edit</button>`
                                    : `<span style="color:#94a3b8; font-size:11px;">View Only</span>`
                                }
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
                if (window.feather) feather.replace();
            });
    }

    function editAttendanceDirect(id, empId, date, checkIn, checkOut, status, hours) {
        document.getElementById('att_corr_id').value = id;
        document.getElementById('att_corr_emp_id').value = empId;
        document.getElementById('att_corr_date').value = date;
        document.getElementById('att_corr_in').value = checkIn ? checkIn.replace(' ', 'T') : '';
        document.getElementById('att_corr_out').value = checkOut ? checkOut.replace(' ', 'T') : '';
        document.getElementById('att_corr_status').value = status;
        document.getElementById('att_corr_hours').value = hours;
        document.getElementById('att_corr_reason').value = '';
        document.getElementById('attendanceCorrectionModal').style.display = 'flex';
    }

    function submitAttendanceCorrection() {
        const payload = {
            id: document.getElementById('att_corr_id').value,
            employee_id: document.getElementById('att_corr_emp_id').value,
            date: document.getElementById('att_corr_date').value,
            check_in: document.getElementById('att_corr_in').value ? document.getElementById('att_corr_in').value.replace('T', ' ') : null,
            check_out: document.getElementById('att_corr_out').value ? document.getElementById('att_corr_out').value.replace('T', ' ') : null,
            status: document.getElementById('att_corr_status').value,
            total_hours: document.getElementById('att_corr_hours').value,
            correction_reason: document.getElementById('att_corr_reason').value
        };

        fetch('api.php?action=correct_attendance', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('attendanceCorrectionModal');
                alert('✅ ' + data.message);
                loadAttendanceRecords();
                loadDashboardMetrics();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // 4. LEAVE MANAGEMENT LOADER
    function loadLeaveAllocations() {
        let empId = currentEmployeeId;
        const empSelect = document.getElementById('leaveBalanceEmpSelect');
        if (empSelect && empSelect.value) {
            empId = empSelect.value;
        }

        fetch(`api.php?action=get_leave_allocations&employee_id=${empId}`)
            .then(res => res.json())
            .then(data => {
                const box = document.getElementById('leaveAllocationsCardsRow');
                if (!data.success || data.allocations.length === 0) {
                    box.innerHTML = '<p style="color:#64748b; padding:10px;">No leave balances configured.</p>';
                    return;
                }

                let html = '';
                data.allocations.forEach(a => {
                    html += `
                        <div class="card" style="margin-bottom:0; background:#f8fafc; border:1px solid #e2e8f0; text-align:center;">
                            <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">${escapeHtml(a.leave_type_name)} (${a.code})</span>
                            <h2 style="font-size:26px; font-weight:800; color:#2563eb; margin:6px 0 2px 0;">${a.remaining_days}</h2>
                            <small style="color:#64748b; font-size:12px;">Remaining (${a.used_days} used / ${a.total_allocated} total)</small>
                        </div>
                    `;
                });
                box.innerHTML = html;
            });
    }

    function loadLeaveRequests() {
        const status = document.getElementById('leaveFilterStatus').value;
        const typeId = document.getElementById('leaveFilterType').value;

        fetch(`api.php?action=get_leave_requests&status=${status}&leave_type_id=${typeId}`)
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('leaveRequestsTableBody');
                if (!data.success || data.leave_requests.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:30px; color:#64748b;">No leave requests found.</td></tr>';
                    return;
                }

                let html = '';
                data.leave_requests.forEach(lr => {
                    let statusClass = 'badge-warning';
                    if (lr.status === 'Approved') statusClass = 'badge-primary';
                    else if (lr.status === 'Rejected') statusClass = 'badge-danger';
                    else if (lr.status === 'Cancelled') statusClass = 'badge-secondary';

                    const canApprove = (canApproveLeaves && lr.status === 'Pending');
                    const canCancel = (lr.status === 'Pending' || canApproveLeaves) && lr.status !== 'Cancelled';

                    html += `
                        <tr>
                            <td><strong>#${lr.id}</strong></td>
                            <td>
                                <div style="font-weight:700; color:#0f172a;">${escapeHtml(lr.employee_name)}</div>
                                <small style="color:#64748b;">${lr.employee_code}</small>
                            </td>
                            <td><span class="badge badge-secondary">${escapeHtml(lr.leave_type_name)}</span></td>
                            <td>${lr.start_date}</td>
                            <td>${lr.end_date}</td>
                            <td><strong>${lr.total_days}</strong></td>
                            <td><div style="font-size:12px; max-width:200px; color:#475569;">${escapeHtml(lr.reason)}</div></td>
                            <td><span class="badge ${statusClass}">${lr.status}</span></td>
                            <td>
                                <div style="font-size:12px; color:#64748b;">${escapeHtml(lr.manager_comment || '--')}</div>
                                ${lr.reviewed_by_name ? `<small style="color:#94a3b8;">By: ${escapeHtml(lr.reviewed_by_name)}</small>` : ''}
                            </td>
                            <td style="text-align:right;">
                                <div style="display:flex; justify-content:flex-end; gap:6px;">
                                    ${canApprove ? `
                                        <button class="btn btn-outline btn-sm" style="color:#16a34a; border-color:#86efac;" onclick="openLeaveDecisionModal(${lr.id}, 'Approved', '${escapeHtml(lr.employee_name)}')">Approve</button>
                                        <button class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fca5a5;" onclick="openLeaveDecisionModal(${lr.id}, 'Rejected', '${escapeHtml(lr.employee_name)}')">Reject</button>
                                    ` : ''}
                                    ${canCancel ? `
                                        <button class="btn btn-outline btn-sm" style="color:#64748b;" onclick="cancelLeaveRequest(${lr.id})">Cancel</button>
                                    ` : ''}
                                </div>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
                if (window.feather) feather.replace();
            });
    }

    function submitLeaveApplication() {
        const payload = {
            employee_id: document.getElementById('lr_employee_id') ? document.getElementById('lr_employee_id').value : currentEmployeeId,
            leave_type_id: document.getElementById('lr_leave_type_id').value,
            start_date: document.getElementById('lr_start_date').value,
            end_date: document.getElementById('lr_end_date').value,
            reason: document.getElementById('lr_reason').value
        };

        fetch('api.php?action=submit_leave_request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('leaveRequestModal');
                alert('✅ ' + data.message);
                loadLeaveRequests();
                loadLeaveAllocations();
                loadDashboardMetrics();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function openLeaveDecisionModal(reqId, decision, empName) {
        document.getElementById('dec_req_id').value = reqId;
        document.getElementById('dec_status').value = decision;
        document.getElementById('dec_comment').value = '';
        document.getElementById('leaveDecisionTitle').textContent = `${decision} Leave Request #${reqId}`;
        document.getElementById('dec_req_info').innerHTML = `Processing leave decision for <strong>${empName}</strong> as <strong>${decision}</strong>.`;
        
        const btn = document.getElementById('dec_submit_btn');
        btn.textContent = `Confirm ${decision}`;
        btn.className = decision === 'Approved' ? 'btn btn-primary' : 'btn btn-danger';
        document.getElementById('leaveDecisionModal').style.display = 'flex';
    }

    function submitLeaveDecision() {
        const payload = {
            id: document.getElementById('dec_req_id').value,
            status: document.getElementById('dec_status').value,
            manager_comment: document.getElementById('dec_comment').value
        };

        fetch('api.php?action=process_leave_request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('leaveDecisionModal');
                alert('✅ ' + data.message);
                loadLeaveRequests();
                loadDashboardMetrics();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function cancelLeaveRequest(id) {
        if (!confirm('Are you sure you want to cancel this leave request?')) return;
        fetch('api.php?action=cancel_leave_request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            loadLeaveRequests();
            loadLeaveAllocations();
            loadDashboardMetrics();
        });
    }

    // 5. DEPARTMENTS & DESIGNATIONS
    function loadDepartments() {
        fetch('api.php?action=get_departments')
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('departmentsTableBody');
                if (!tbody) return;
                let html = '';
                data.departments.forEach(d => {
                    html += `
                        <tr>
                            <td><strong style="color:#2563eb;">${escapeHtml(d.code || d.name.substring(0,4).toUpperCase())}</strong></td>
                            <td>${escapeHtml(d.name)}</td>
                            <td><span class="badge badge-primary">${d.employee_count} Employees</span></td>
                            <td><span class="badge ${d.is_active ? 'badge-primary' : 'badge-secondary'}">${d.is_active ? 'Active' : 'Inactive'}</span></td>
                            <td style="text-align:right;">
                                <button class="btn btn-outline btn-sm" onclick="editDeptModal(${d.id}, '${escapeHtml(d.name)}', '${escapeHtml(d.code||'')}', '${escapeHtml(d.description||'')}')">Edit</button>
                                <button class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fca5a5;" onclick="deleteDepartment(${d.id})">Delete</button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
    }

    function editDeptModal(id, name, code, desc) {
        document.getElementById('dept_id').value = id;
        document.getElementById('deptModalTitle').textContent = `Edit Department: ${name}`;
        document.getElementById('dept_name').value = name;
        document.getElementById('dept_code').value = code;
        document.getElementById('dept_description').value = desc;
        document.getElementById('departmentModal').style.display = 'flex';
    }

    function submitSaveDepartment() {
        const payload = {
            id: document.getElementById('dept_id').value,
            name: document.getElementById('dept_name').value,
            code: document.getElementById('dept_code').value,
            description: document.getElementById('dept_description').value
        };
        fetch('api.php?action=save_department', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeModal('departmentModal');
                alert('✅ ' + d.message);
                loadDepartments();
            } else {
                alert('Error: ' + d.message);
            }
        });
    }

    function deleteDepartment(id) {
        if (!confirm('Are you sure you want to delete this department?')) return;
        fetch('api.php?action=delete_department', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(r => r.json())
        .then(d => {
            alert(d.message);
            if (d.success) loadDepartments();
        });
    }

    function loadDesignations() {
        fetch('api.php?action=get_designations')
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('designationsTableBody');
                if (!tbody) return;
                let html = '';
                data.designations.forEach(dg => {
                    html += `
                        <tr>
                            <td><strong>${escapeHtml(dg.title)}</strong></td>
                            <td>${escapeHtml(dg.department_name || '--')}</td>
                            <td><span class="badge badge-primary">${dg.employee_count} Assigned</span></td>
                            <td><span class="badge ${dg.is_active ? 'badge-primary' : 'badge-secondary'}">${dg.is_active ? 'Active' : 'Inactive'}</span></td>
                            <td style="text-align:right;">
                                <button class="btn btn-outline btn-sm" onclick="editDesigModal(${dg.id}, '${escapeHtml(dg.title)}', ${dg.department_id||0}, '${escapeHtml(dg.description||'')}')">Edit</button>
                                <button class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fca5a5;" onclick="deleteDesignation(${dg.id})">Delete</button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
    }

    function editDesigModal(id, title, deptId, desc) {
        document.getElementById('desig_id').value = id;
        document.getElementById('desigModalTitle').textContent = `Edit Designation: ${title}`;
        document.getElementById('desig_title').value = title;
        document.getElementById('desig_dept_id').value = deptId;
        document.getElementById('desig_description').value = desc;
        document.getElementById('designationModal').style.display = 'flex';
    }

    function submitSaveDesignation() {
        const payload = {
            id: document.getElementById('desig_id').value,
            title: document.getElementById('desig_title').value,
            department_id: document.getElementById('desig_dept_id').value,
            description: document.getElementById('desig_description').value
        };
        fetch('api.php?action=save_designation', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeModal('designationModal');
                alert('✅ ' + d.message);
                loadDesignations();
            } else {
                alert('Error: ' + d.message);
            }
        });
    }

    function deleteDesignation(id) {
        if (!confirm('Are you sure you want to delete this designation?')) return;
        fetch('api.php?action=delete_designation', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(r => r.json())
        .then(d => {
            alert(d.message);
            if (d.success) loadDesignations();
        });
    }

    // 6. HOLIDAYS LOADER
    function loadHolidays() {
        const year = document.getElementById('holidayYearSelect').value;
        fetch(`api.php?action=get_holidays&year=${year}`)
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('holidaysTableBody');
                if (!data.success || data.holidays.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${isHrAdmin ? 6 : 5}" style="text-align:center; padding:30px; color:#64748b;">No holidays scheduled for ${year}.</td></tr>`;
                    return;
                }

                let html = '';
                data.holidays.forEach(h => {
                    const dateObj = new Date(h.holiday_date);
                    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
                    html += `
                        <tr>
                            <td><strong style="color:#2563eb;">${h.holiday_date}</strong></td>
                            <td>${dayName}</td>
                            <td><strong>${escapeHtml(h.holiday_name)}</strong></td>
                            <td><span style="color:#64748b; font-size:12px;">${escapeHtml(h.description || 'General Agency Holiday')}</span></td>
                            <td><span class="badge ${h.is_optional ? 'badge-warning' : 'badge-primary'}">${h.is_optional ? 'Optional' : 'Public'}</span></td>
                            ${isHrAdmin ? `
                                <td style="text-align:right;">
                                    <button class="btn btn-outline btn-sm" onclick="editHolidayModal(${h.id}, '${escapeHtml(h.holiday_name)}', '${h.holiday_date}', '${escapeHtml(h.description||'')}', ${h.is_optional})">Edit</button>
                                    <button class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fca5a5;" onclick="deleteHoliday(${h.id})">Delete</button>
                                </td>
                            ` : ''}
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
    }

    function editHolidayModal(id, name, date, desc, isOpt) {
        document.getElementById('hol_id').value = id;
        document.getElementById('holidayModalTitle').textContent = `Edit Holiday: ${name}`;
        document.getElementById('hol_name').value = name;
        document.getElementById('hol_date').value = date;
        document.getElementById('hol_desc').value = desc;
        document.getElementById('hol_is_optional').checked = (isOpt == 1);
        document.getElementById('holidayModal').style.display = 'flex';
    }

    function submitSaveHoliday() {
        const payload = {
            id: document.getElementById('hol_id').value,
            holiday_name: document.getElementById('hol_name').value,
            holiday_date: document.getElementById('hol_date').value,
            description: document.getElementById('hol_desc').value,
            is_optional: document.getElementById('hol_is_optional').checked ? 1 : 0
        };
        fetch('api.php?action=save_holiday', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeModal('holidayModal');
                alert('✅ ' + d.message);
                loadHolidays();
                loadDashboardMetrics();
            } else {
                alert('Error: ' + d.message);
            }
        });
    }

    function deleteHoliday(id) {
        if (!confirm('Are you sure you want to remove this holiday?')) return;
        fetch('api.php?action=delete_holiday', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(r => r.json())
        .then(d => {
            alert(d.message);
            loadHolidays();
            loadDashboardMetrics();
        });
    }

    // 7. EMPLOYEE DOCUMENTS VAULT
    function loadEmployeeDocuments() {
        const empSelect = document.getElementById('docEmpSelect');
        const empId = empSelect ? empSelect.value : currentEmployeeId;

        fetch(`api.php?action=get_employees`)
            .then(r => r.json())
            .then(data => {
                // Fetch employee details which includes documents
                if (empId) {
                    fetch(`api.php?action=get_employee_details&id=${empId}`)
                        .then(r => r.json())
                        .then(res => {
                            renderDocumentsTable(res.documents || [], res.employee ? res.employee.full_name : '');
                        });
                } else {
                    // Load all documents
                    fetch(`api.php?action=get_employee_details&id=${currentEmployeeId || 1}`)
                        .then(r => r.json())
                        .then(res => {
                            renderDocumentsTable(res.documents || [], res.employee ? res.employee.full_name : '');
                        });
                }
            });
    }

    function renderDocumentsTable(docs, empName) {
        const tbody = document.getElementById('documentsTableBody');
        if (docs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:#64748b;">No documents uploaded in repository.</td></tr>';
            return;
        }

        let html = '';
        docs.forEach(d => {
            const sizeKb = (d.file_size / 1024).toFixed(1) + ' KB';
            html += `
                <tr>
                    <td>📄 <strong>${escapeHtml(d.document_title)}</strong></td>
                    <td><span class="badge badge-secondary">${escapeHtml(d.category)}</span></td>
                    <td>${escapeHtml(empName || 'Employee')}</td>
                    <td>${sizeKb}</td>
                    <td>${d.created_at}</td>
                    <td>
                        <span class="badge ${d.is_confidential ? 'badge-danger' : 'badge-primary'}">
                            ${d.is_confidential ? 'Confidential' : 'Standard'}
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <a href="hr_download.php?id=${d.id}" target="_blank" class="btn btn-outline btn-sm">Download</a>
                        ${isHrAdmin ? `
                            <button class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fca5a5;" onclick="deleteEmployeeDoc(${d.id})">Delete</button>
                        ` : ''}
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function submitUploadDocument() {
        const form = document.getElementById('uploadDocForm');
        const formData = new FormData(form);

        fetch('api.php?action=upload_employee_document', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeModal('uploadDocModal');
                alert('✅ ' + d.message);
                loadEmployeeDocuments();
            } else {
                alert('Error: ' + d.message);
            }
        });
    }

    function deleteEmployeeDoc(id) {
        if (!confirm('Are you sure you want to permanently delete this document?')) return;
        fetch('api.php?action=delete_employee_document', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(r => r.json())
        .then(d => {
            alert(d.message);
            loadEmployeeDocuments();
        });
    }

    // 8. HR REPORTS
    function loadHrReport() {
        const type = document.getElementById('hrReportType').value;
        const deptId = document.getElementById('hrReportDept').value;
        const month = document.getElementById('hrReportMonth').value;

        fetch(`api.php?action=get_hr_reports_data&report_type=${type}&department_id=${deptId}&month=${month}`)
            .then(r => r.json())
            .then(data => {
                const thead = document.getElementById('reportTableHead');
                const tbody = document.getElementById('reportTableBody');
                const title = document.getElementById('reportTitleHeader');

                if (type === 'employees_status') {
                    title.textContent = 'Workforce Master Roster';
                    thead.innerHTML = `<tr><th>Code</th><th>Name</th><th>Email</th><th>Phone</th><th>Department</th><th>Title</th><th>Type</th><th>Location</th><th>Joined</th><th>Status</th></tr>`;
                    let rows = '';
                    data.data.forEach(r => {
                        rows += `<tr><td><strong>${r.employee_code}</strong></td><td>${escapeHtml(r.full_name)}</td><td>${r.email}</td><td>${r.phone||'--'}</td><td>${r.department_name||'--'}</td><td>${r.designation_title||'--'}</td><td>${r.employment_type}</td><td>${r.work_location}</td><td>${r.date_of_joining}</td><td><span class="badge ${r.employment_status==='Active'?'badge-primary':'badge-danger'}">${r.employment_status}</span></td></tr>`;
                    });
                    tbody.innerHTML = rows || '<tr><td colspan="10" style="text-align:center; padding:20px;">No records</td></tr>';
                } else if (type === 'department_headcount') {
                    title.textContent = 'Department Headcount BI Breakdown';
                    thead.innerHTML = `<tr><th>Department</th><th>Code</th><th>Total Staff</th><th>Active</th><th>Inactive</th></tr>`;
                    let rows = '';
                    data.data.forEach(r => {
                        rows += `<tr><td><strong>${escapeHtml(r.name)}</strong></td><td>${r.code||'--'}</td><td><strong>${r.total_headcount}</strong></td><td><span class="badge badge-primary">${r.active_count}</span></td><td><span class="badge badge-secondary">${r.inactive_count}</span></td></tr>`;
                    });
                    tbody.innerHTML = rows || '<tr><td colspan="5" style="text-align:center; padding:20px;">No records</td></tr>';
                } else if (type === 'monthly_attendance') {
                    title.textContent = `Monthly Attendance Matrix (${month})`;
                    thead.innerHTML = `<tr><th>Date</th><th>Status Category</th><th>Employee Count</th><th>Avg Work Hours</th></tr>`;
                    let rows = '';
                    data.data.forEach(r => {
                        rows += `<tr><td><strong>${r.attendance_date}</strong></td><td><span class="badge badge-primary">${r.status}</span></td><td>${r.count}</td><td>${r.avg_hours ? Number(r.avg_hours).toFixed(1) + ' hrs' : '--'}</td></tr>`;
                    });
                    tbody.innerHTML = rows || '<tr><td colspan="4" style="text-align:center; padding:20px;">No records</td></tr>';
                } else if (type === 'leave_usage') {
                    title.textContent = 'Leave Usage & Accrual Analytics';
                    thead.innerHTML = `<tr><th>Leave Type</th><th>Code</th><th>Total Allocated</th><th>Used Days</th><th>Remaining Days</th></tr>`;
                    let rows = '';
                    data.data.forEach(r => {
                        rows += `<tr><td><strong>${escapeHtml(r.leave_type)}</strong></td><td>${r.code}</td><td>${r.total_allocated||0}</td><td>${r.total_used||0}</td><td><strong>${r.total_remaining||0}</strong></td></tr>`;
                    });
                    tbody.innerHTML = rows || '<tr><td colspan="5" style="text-align:center; padding:20px;">No records</td></tr>';
                }
            });
    }

    function exportCurrentReportCsv() {
        const type = document.getElementById('hrReportType').value;
        const month = document.getElementById('hrReportMonth').value;
        if (type === 'employees_status') window.location.href = 'hr_export.php?type=employees';
        else if (type === 'monthly_attendance') window.location.href = `hr_export.php?type=attendance&month=${month}`;
        else if (type === 'leave_usage') window.location.href = 'hr_export.php?type=leaves';
        else window.location.href = 'hr_export.php?type=employees';
    }

    function filterDesignationsByDept() {
        const deptId = document.getElementById('emp_dept_id').value;
        const desigSelect = document.getElementById('emp_desig_id');
        const options = desigSelect.querySelectorAll('option');
        options.forEach(opt => {
            if (!opt.value) return;
            const optDept = opt.getAttribute('data-dept');
            if (!deptId || optDept === deptId) {
                opt.style.display = 'block';
            } else {
                opt.style.display = 'none';
            }
        });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
