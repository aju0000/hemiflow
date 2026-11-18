<?php
// reports.php - Module 9 Reports & Management BI Dashboard Suite

require_once __DIR__ . '/auth.php';
checkAuth('reports');

$user = getCurrentUser();
$isSuperAdmin = (($user['role'] ?? '') === 'Super Admin');
$db = getDbConnection();

$pageBreadcrumb = "Module 9";
$pageTitle = "Reports & Executive BI Dashboard";
$pageSubtitle = "Real-time aggregation of transactional data across Clients, Revenue, Tasks, SLA records, and Developer Productivity.";
require_once __DIR__ . '/header.php';
?>

<!-- Action Pills Navigation Bar -->
<div class="action-pills-row">
    <button class="btn-pill-primary" onclick="loadExecutiveSummary()">
        <i data-feather="refresh-cw" class="icon-prefix"></i>
        <span>Refresh BI Analytics</span>
        <i data-feather="arrow-right" style="width:16px; height:16px; margin-left:4px;"></i>
    </button>
    <button class="btn-pill-white" onclick="openDrilldownModal('delayed_tasks')">
        <i data-feather="search" class="icon-edit"></i>
        <span>Executive Drill-Down</span>
    </button>
    <button class="btn-pill-white" onclick="window.print()">
        <i data-feather="printer" class="icon-clock"></i>
        <span>Print Summary Report</span>
    </button>
</div>

<!-- Key Performance Metrics Row (Dynamically calculated from SQL DB) -->
<div class="grid-4" style="margin-bottom: 24px;" id="execMetricsBar">
    <div class="card" style="margin-bottom:0;">
        <p style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Active Clients</p>
        <h2 id="m_active_clients" style="font-size:24px; font-weight:800; color:#2563eb; margin-top:4px;">--</h2>
        <small id="m_client_sub" style="color:#64748b; font-size:12px;">Loading...</small>
    </div>

    <div class="card" style="margin-bottom:0;">
        <p style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Total Billed Revenue</p>
        <h2 id="m_billed_revenue" style="font-size:24px; font-weight:800; color:#16a34a; margin-top:4px;">--</h2>
        <small id="m_revenue_sub" style="color:#64748b; font-size:12px;">Loading...</small>
    </div>

    <div class="card" style="margin-bottom:0;">
        <p style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Task SLA Pass Rate</p>
        <h2 id="m_sla_pass" style="font-size:24px; font-weight:800; color:#d97706; margin-top:4px;">--</h2>
        <small id="m_sla_sub" style="color:#64748b; font-size:12px;">Loading...</small>
    </div>

    <div class="card" style="margin-bottom:0;">
        <p style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">SLA Breaches</p>
        <h2 id="m_sla_breaches" style="font-size:24px; font-weight:800; color:#dc2626; margin-top:4px;">--</h2>
        <small style="color:#dc2626; font-size:12px; font-weight:600;">Requires Executive Attention</small>
    </div>
</div>

<!-- Tabbed BI Reports Suite -->
<div class="card">
    <div style="display:flex; border-bottom:2px solid #e2e8f0; margin-bottom:20px;">
        <button id="tabBtn1" class="btn btn-outline" style="border:none; border-bottom:3px solid #2563eb; border-radius:0; font-weight:700; color:#2563eb;" onclick="switchTab(1)">
            Developer Productivity Leaderboard
        </button>
        <button id="tabBtn2" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0;" onclick="switchTab(2)">
            Client & Billed Revenue BI
        </button>
        <button id="tabBtn3" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0;" onclick="switchTab(3)">
            Task Status & SLA Analytics
        </button>
        <button id="tabBtn4" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0;" onclick="switchTab(4)">
            Workforce & HR Operations
        </button>
        <?php if ($isSuperAdmin): ?>
        <button id="tabBtn5" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0;" onclick="switchTab(5)">
            🛡️ Super Admin: Task & Project Time Tracking
        </button>
        <?php endif; ?>
    </div>

    <!-- TAB 1: Developer Productivity Leaderboard -->
    <div id="tabContent1">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="font-size:16px; font-weight:700;">Developer Workload & KPI Performance Metrics</h3>
            <span class="badge badge-primary">Calculated from Real Transactional Data</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Developer Name</th>
                        <th>Department</th>
                        <th>Assigned Tasks</th>
                        <th>Completed Tasks</th>
                        <th>In Progress</th>
                        <th>Under Review</th>
                        <th>Revisions Needed</th>
                        <th>SLA Breaches</th>
                        <th>Clearance Rate</th>
                    </tr>
                </thead>
                <tbody id="devProductivityBody">
                    <tr><td colspan="9" style="text-align:center; padding:30px;">Loading developer KPI metrics...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: Client & Revenue BI -->
    <div id="tabContent2" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="font-size:16px; font-weight:700;">Client Account & Revenue Allocation</h3>
            <button class="btn btn-outline btn-sm" onclick="openDrilldownModal('client_revenue')">View Detailed Breakdown</button>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Client / Company</th>
                        <th>Contact Person</th>
                        <th>Status</th>
                        <th>Subscribed Packages</th>
                        <th>Total Billed Amount</th>
                    </tr>
                </thead>
                <tbody id="clientRevenueBody">
                    <tr><td colspan="5" style="text-align:center; padding:30px;">Loading client financial metrics...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 3: Task & SLA Analytics -->
    <div id="tabContent3" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="font-size:16px; font-weight:700;">Task Status Breakdown & SLA Performance</h3>
            <button class="btn btn-outline btn-sm" onclick="openDrilldownModal('awaiting_approval')">Tasks Awaiting Approval</button>
        </div>

        <div class="grid-2" style="margin-bottom:20px;">
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:20px;">
                <h4 style="font-size:14px; font-weight:700; color:#334155; margin-bottom:10px;">Task Execution Pipeline</h4>
                <div id="taskPipelineChart" style="display:flex; flex-direction:column; gap:8px;"></div>
            </div>

            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:20px;">
                <h4 style="font-size:14px; font-weight:700; color:#334155; margin-bottom:10px;">SLA Compliance Metrics</h4>
                <div id="slaComplianceDetails" style="display:flex; flex-direction:column; gap:8px;"></div>
            </div>
        </div>
    </div>

    <!-- TAB 4: Workforce & HR Operations -->
    <div id="tabContent4" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="font-size:16px; font-weight:700;">Workforce Headcount & Agency HR Analytics</h3>
            <a href="hr.php" class="btn btn-primary btn-sm">Open Module 5 HR Hub</a>
        </div>

        <div class="grid-3" style="margin-bottom:20px;">
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:16px; text-align:center;">
                <p style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Total Workforce</p>
                <h2 id="hr_total_emps" style="font-size:24px; font-weight:800; color:#2563eb; margin:4px 0;">--</h2>
                <small id="hr_active_sub" style="color:#64748b; font-size:12px;">Active Employees</small>
            </div>
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:16px; text-align:center;">
                <p style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Present Today</p>
                <h2 id="hr_present_today" style="font-size:24px; font-weight:800; color:#16a34a; margin:4px 0;">--</h2>
                <small style="color:#16a34a; font-size:12px;">Office & Remote Attendance</small>
            </div>
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:16px; text-align:center;">
                <p style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Pending Leave Approvals</p>
                <h2 id="hr_pending_leaves" style="font-size:24px; font-weight:800; color:#d97706; margin:4px 0;">--</h2>
                <small style="color:#d97706; font-size:12px;">Pending Manager Review</small>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Code</th>
                        <th>Total Assigned</th>
                        <th>Active Staff</th>
                        <th>Inactive Staff</th>
                    </tr>
                </thead>
                <tbody id="hrDeptHeadcountBody">
                    <tr><td colspan="5" style="text-align:center; padding:20px;">Loading HR headcount metrics...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($isSuperAdmin): ?>
    <!-- TAB 5: Super Admin Task Completion (Day / Month / Date Range) & Project Time Tracking -->
    <div id="tabContent5" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin-bottom:20px;">
            <div>
                <h3 style="font-size:16px; font-weight:800; color:#0f172a; margin:0 0 4px 0;">🛡️ Super Admin: Employee Completed Tasks Tracker</h3>
                <p style="font-size:12.5px; color:#64748b; margin:0;">Filter completed tasks per employee by Day, Month, or Custom Date Range.</p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <button type="button" id="saBtnDay" class="btn btn-outline btn-sm" onclick="setSaAnalyticsMode('day')">📅 Per Day</button>
                <button type="button" id="saBtnMonth" class="btn btn-primary btn-sm" onclick="setSaAnalyticsMode('month')">🗓️ Per Month</button>
                <button type="button" id="saBtnRange" class="btn btn-outline btn-sm" onclick="setSaAnalyticsMode('range')">📊 Date Range</button>

                <input type="date" id="sa_date" class="form-control" value="<?= date('Y-m-d') ?>" style="display:none; width:150px; padding:5px 8px; font-size:12.5px;">
                <input type="month" id="sa_month" class="form-control" value="<?= date('Y-m') ?>" style="display:inline-block; width:155px; padding:5px 8px; font-size:12.5px;">
                <span id="sa_range_inputs" style="display:none; align-items:center; gap:6px;">
                    <input type="date" id="sa_start_date" class="form-control" value="<?= date('Y-m-01') ?>" style="width:140px; padding:5px 8px; font-size:12.5px;">
                    <span style="font-size:12px; color:#64748b;">to</span>
                    <input type="date" id="sa_end_date" class="form-control" value="<?= date('Y-m-d') ?>" style="width:140px; padding:5px 8px; font-size:12.5px;">
                </span>
                <button type="button" class="btn btn-primary btn-sm" onclick="loadSuperAdminAnalytics()">Apply Filter</button>
            </div>
        </div>

        <div class="grid-4" style="margin-bottom:20px;">
            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px; text-align:center;">
                <p style="font-size:11px; font-weight:700; color:#1e40af; text-transform:uppercase;">Tasks Completed in Period</p>
                <h2 id="sa_total_completed" style="font-size:24px; font-weight:800; color:#1d4ed8; margin:4px 0;">--</h2>
                <small id="sa_period_label" style="color:#2563eb; font-size:11.5px;">--</small>
            </div>
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:14px; text-align:center;">
                <p style="font-size:11px; font-weight:700; color:#15803d; text-transform:uppercase;">Employees With Completions</p>
                <h2 id="sa_active_staff" style="font-size:24px; font-weight:800; color:#16a34a; margin:4px 0;">--</h2>
                <small style="color:#15803d; font-size:11.5px;">Within Selected Range</small>
            </div>
            <div style="background:#fefce8; border:1px solid #fde047; border-radius:8px; padding:14px; text-align:center;">
                <p style="font-size:11px; font-weight:700; color:#a16207; text-transform:uppercase;">Completed Projects</p>
                <h2 id="sa_completed_projects" style="font-size:24px; font-weight:800; color:#ca8a04; margin:4px 0;">--</h2>
                <small id="sa_sla_projects_sub" style="color:#a16207; font-size:11.5px;">--</small>
            </div>
            <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; padding:14px; text-align:center;">
                <p style="font-size:11px; font-weight:700; color:#6b21a8; text-transform:uppercase;">Avg Project Time Taken</p>
                <h2 id="sa_avg_proj_days" style="font-size:24px; font-weight:800; color:#7e22ce; margin:4px 0;">--</h2>
                <small id="sa_avg_proj_hours" style="color:#6b21a8; font-size:11.5px;">--</small>
            </div>
        </div>

        <h4 style="font-size:14.5px; font-weight:700; color:#0f172a; margin:0 0 10px 0;">Employee Completed Tasks Count</h4>
        <div class="table-responsive" style="margin-bottom:26px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Completed Tasks</th>
                        <th>Main Tasks</th>
                        <th>Sub-Tasks</th>
                        <th>Recent Completed Task(s)</th>
                    </tr>
                </thead>
                <tbody id="saEmployeeCompletionsBody">
                    <tr><td colspan="7" style="text-align:center; padding:20px;">Loading employee completion metrics...</td></tr>
                </tbody>
            </table>
        </div>

        <h4 style="font-size:14.5px; font-weight:700; color:#0f172a; margin:0 0 10px 0;">⏱️ Project Completion Time Tracker</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Team Lead</th>
                        <th>Start Date</th>
                        <th>Deadline</th>
                        <th>Completed At</th>
                        <th>Time Taken</th>
                        <th>Tasks Done</th>
                        <th>Status / SLA</th>
                    </tr>
                </thead>
                <tbody id="saProjectDurationsBody">
                    <tr><td colspan="9" style="text-align:center; padding:20px;">Loading project completion durations...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL: EXECUTIVE DRILL-DOWN TOOL -->
<div id="drilldownModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:900px;">
        <div class="modal-header">
            <h3 class="modal-title" id="dd_title">Executive Drill-Down Analysis</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('drilldownModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display:flex; gap:10px; margin-bottom:15px;">
                <button class="btn btn-outline btn-sm" onclick="loadDrilldown('delayed_tasks')">SLA Breached Tasks</button>
                <button class="btn btn-outline btn-sm" onclick="loadDrilldown('awaiting_approval')">Tasks Awaiting Approval</button>
                <button class="btn btn-outline btn-sm" onclick="loadDrilldown('client_revenue')">Client Revenue</button>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead id="dd_table_head"></thead>
                    <tbody id="dd_table_body">
                        <tr><td style="text-align:center; padding:30px;">Select metric to drill down...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('drilldownModal')">Close</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadExecutiveSummary();
        loadDeveloperProductivity();
        loadClientRevenue();
    });

    function loadExecutiveSummary() {
        fetch('api.php?action=get_reports_executive_summary')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const c = data.client_overview;
                const r = data.revenue_overview;
                const t = data.task_overview;

                // Client metrics
                document.getElementById('m_active_clients').textContent = `${c.active} / ${c.total}`;
                document.getElementById('m_client_sub').textContent = `Conversion Rate: ${c.conversion_rate}% (${c.leads} Leads)`;

                // Revenue metrics
                document.getElementById('m_billed_revenue').textContent = `INR ${r.total_billed.toLocaleString('en-IN')}`;
                document.getElementById('m_revenue_sub').textContent = `Paid: INR ${r.paid.toLocaleString('en-IN')} | Pending: INR ${r.pending.toLocaleString('en-IN')}`;

                // Task & SLA metrics
                document.getElementById('m_sla_pass').textContent = `${t.sla_pass_rate}%`;
                document.getElementById('m_sla_sub').textContent = `Completed: ${t.completed_tasks} / ${t.total_tasks} Tasks`;

                document.getElementById('m_sla_breaches').textContent = t.sla_breaches;

                // Task Pipeline Chart
                document.getElementById('taskPipelineChart').innerHTML = `
                    <div style="display:flex; justify-content:space-between; font-size:13px;"><span>Total Tasks Created:</span> <strong>${t.total_tasks}</strong></div>
                    <div style="display:flex; justify-content:space-between; font-size:13px;"><span>Active Tasks:</span> <strong>${t.active_tasks}</strong></div>
                    <div style="display:flex; justify-content:space-between; font-size:13px;"><span>Tasks Awaiting Approval:</span> <strong style="color:#d97706;">${t.awaiting_approval}</strong></div>
                    <div style="display:flex; justify-content:space-between; font-size:13px;"><span>Completed Tasks:</span> <strong style="color:#16a34a;">${t.completed_tasks}</strong></div>
                `;

                document.getElementById('slaComplianceDetails').innerHTML = `
                    <div style="display:flex; justify-content:space-between; font-size:13px;"><span>SLA Pass Rate:</span> <strong style="color:#16a34a;">${t.sla_pass_rate}%</strong></div>
                    <div style="display:flex; justify-content:space-between; font-size:13px;"><span>SLA Breaches Count:</span> <strong style="color:#dc2626;">${t.sla_breaches}</strong></div>
                `;
            });
    }

    function loadDeveloperProductivity() {
        fetch('api.php?action=get_reports_developer_productivity')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const tbody = document.getElementById('devProductivityBody');
                if (data.productivity.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:20px;">No developer records found.</td></tr>';
                    return;
                }

                let html = '';
                data.productivity.forEach(d => {
                    html += `
                        <tr>
                            <td><strong>${d.developer_name}</strong></td>
                            <td>${d.department}</td>
                            <td><span class="badge badge-secondary">${d.total_assigned}</span></td>
                            <td><span class="badge badge-success">${d.completed_tasks}</span></td>
                            <td><span class="badge badge-primary">${d.in_progress_tasks}</span></td>
                            <td><span class="badge badge-warning">${d.review_tasks}</span></td>
                            <td><span class="badge badge-danger">${d.revision_tasks}</span></td>
                            <td><span class="badge ${d.sla_breaches > 0 ? 'badge-danger' : 'badge-success'}">${d.sla_breaches}</span></td>
                            <td><strong>${d.clearance_rate}%</strong></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
    }

    function loadClientRevenue() {
        fetch('api.php?action=get_clients')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const tbody = document.getElementById('clientRevenueBody');
                let html = '';
                data.clients.forEach(c => {
                    html += `
                        <tr>
                            <td><strong>${c.company_name}</strong></td>
                            <td>${c.contact_person} (${c.email})</td>
                            <td><span class="badge badge-success">${c.status}</span></td>
                            <td><span class="badge badge-primary">${c.service_count} Services</span></td>
                            <td><strong>INR 1,50,000</strong></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
    }

    function switchTab(num) {
        for (let i = 1; i <= 5; i++) {
            const content = document.getElementById(`tabContent${i}`);
            if (content) content.style.display = i === num ? 'block' : 'none';
            const btn = document.getElementById(`tabBtn${i}`);
            if (btn) {
                btn.style.borderBottomColor = i === num ? '#2563eb' : 'transparent';
                btn.style.fontWeight = i === num ? '700' : '500';
                btn.style.color = i === num ? '#2563eb' : 'var(--text)';
            }
        }
        if (num === 4) {
            loadHrBiAnalytics();
        } else if (num === 5) {
            loadSuperAdminAnalytics();
        }
    }

    let saAnalyticsMode = 'month';
    function setSaAnalyticsMode(mode) {
        saAnalyticsMode = mode;
        const map = { day: 'saBtnDay', month: 'saBtnMonth', range: 'saBtnRange' };
        Object.keys(map).forEach(k => {
            const b = document.getElementById(map[k]);
            if (b) b.className = k === mode ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
        });
        document.getElementById('sa_date').style.display = mode === 'day' ? 'inline-block' : 'none';
        document.getElementById('sa_month').style.display = mode === 'month' ? 'inline-block' : 'none';
        document.getElementById('sa_range_inputs').style.display = mode === 'range' ? 'inline-flex' : 'none';
        loadSuperAdminAnalytics();
    }

    function escapeReportHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function loadSuperAdminAnalytics() {
        const params = new URLSearchParams({ action: 'superadmin_task_project_analytics', mode: saAnalyticsMode });
        if (saAnalyticsMode === 'day') {
            params.set('date', document.getElementById('sa_date').value);
        } else if (saAnalyticsMode === 'month') {
            params.set('month', document.getElementById('sa_month').value);
        } else {
            params.set('start_date', document.getElementById('sa_start_date').value);
            params.set('end_date', document.getElementById('sa_end_date').value);
        }

        fetch('api.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Could not load Super Admin analytics.');
                    return;
                }
                const s = data.summary || {};
                const f = data.filter || {};
                const ps = data.project_duration_summary || {};
                document.getElementById('sa_total_completed').textContent = s.total_completed_tasks ?? 0;
                document.getElementById('sa_period_label').textContent = `${f.start_date || ''} → ${f.end_date || ''}`;
                document.getElementById('sa_active_staff').textContent = `${s.active_employees_with_completions ?? 0} / ${s.total_staff ?? 0}`;
                document.getElementById('sa_completed_projects').textContent = `${ps.completed_projects ?? 0} / ${ps.total_projects ?? 0}`;
                document.getElementById('sa_sla_projects_sub').textContent = `On Time: ${ps.on_time_count ?? 0} • Overdue: ${ps.overdue_count ?? 0}`;
                document.getElementById('sa_avg_proj_days').textContent = `${ps.avg_completion_days ?? 0} Days`;
                document.getElementById('sa_avg_proj_hours').textContent = `${ps.avg_completion_hours ?? 0} Hours Average`;

                const empBody = document.getElementById('saEmployeeCompletionsBody');
                const emps = data.employee_completions || [];
                if (emps.length === 0) {
                    empBody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">No employees found.</td></tr>';
                } else {
                    empBody.innerHTML = emps.map(e => {
                        const taskLinks = (e.tasks || []).slice(0, 4).map(t => `<a href="task_details.php?id=${t.id}" class="badge badge-secondary" style="text-decoration:none; margin:2px; font-size:10.5px;">${escapeReportHtml(t.task_code)}: ${escapeReportHtml(t.title)}</a>`).join(' ');
                        const extra = (e.tasks || []).length > 4 ? ` <small style="color:#64748b;">+${e.tasks.length - 4} more</small>` : '';
                        return `
                            <tr>
                                <td><strong>${escapeReportHtml(e.full_name)}</strong></td>
                                <td>${escapeReportHtml(e.department)}</td>
                                <td><span class="badge badge-secondary">${escapeReportHtml(e.role_name)}</span></td>
                                <td><span class="badge ${e.completed_count > 0 ? 'badge-success' : 'badge-secondary'}" style="font-size:12.5px;">${e.completed_count}</span></td>
                                <td>${e.main_tasks_completed}</td>
                                <td>${e.subtasks_completed}</td>
                                <td>${taskLinks || '<span style="color:#94a3b8;">--</span>'}${extra}</td>
                            </tr>
                        `;
                    }).join('');
                }

                const projBody = document.getElementById('saProjectDurationsBody');
                const projs = data.project_durations || [];
                if (projs.length === 0) {
                    projBody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:20px;">No projects recorded yet.</td></tr>';
                } else {
                    projBody.innerHTML = projs.map(p => {
                        const slaClass = (p.sla_status === 'On Time' || p.sla_status === 'Active (On Track)') ? 'badge-success' : (p.sla_status.includes('Overdue') ? 'badge-danger' : 'badge-primary');
                        return `
                            <tr>
                                <td><a href="project_details.php?id=${p.project_id}" style="font-weight:700; color:#1d4ed8; text-decoration:none;">${escapeReportHtml(p.project_name)}</a></td>
                                <td>${escapeReportHtml(p.company_name)}</td>
                                <td>${escapeReportHtml(p.team_lead_name)}</td>
                                <td>${escapeReportHtml(p.start_date)}</td>
                                <td>${escapeReportHtml(p.deadline)}</td>
                                <td>${escapeReportHtml(p.completed_at)}</td>
                                <td><strong>${escapeReportHtml(p.duration_label)}</strong> <small style="color:#64748b;">(${p.duration_days}d)</small></td>
                                <td><span class="badge badge-secondary">${p.completed_tasks} / ${p.total_tasks}</span></td>
                                <td><span class="badge ${slaClass}">${escapeReportHtml(p.sla_status)}</span></td>
                            </tr>
                        `;
                    }).join('');
                }
            });
    }

    function loadHrBiAnalytics() {
        // Fetch HR metrics
        fetch('api.php?action=get_hr_dashboard_metrics')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                document.getElementById('hr_total_emps').textContent = data.metrics.total_employees;
                document.getElementById('hr_active_sub').textContent = `Active Staff: ${data.metrics.active_employees}`;
                document.getElementById('hr_present_today').textContent = data.metrics.present_today;
                document.getElementById('hr_pending_leaves').textContent = data.metrics.pending_leave_requests;
            });

        // Fetch department headcount
        fetch('api.php?action=get_hr_reports_data&report_type=department_headcount')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const tbody = document.getElementById('hrDeptHeadcountBody');
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">No department records found.</td></tr>';
                    return;
                }
                let html = '';
                data.data.forEach(d => {
                    html += `
                        <tr>
                            <td><strong>${d.name}</strong></td>
                            <td>${d.code || '--'}</td>
                            <td><strong>${d.total_headcount}</strong></td>
                            <td><span class="badge badge-primary">${d.active_count}</span></td>
                            <td><span class="badge badge-secondary">${d.inactive_count}</span></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            });
    }

    function openDrilldownModal(type) {
        document.getElementById('drilldownModal').style.display = 'flex';
        loadDrilldown(type);
    }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function loadDrilldown(type) {
        fetch(`api.php?action=get_reports_drilldown&type=${type}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                document.getElementById('dd_title').textContent = `Drill-Down: ${data.type}`;
                const head = document.getElementById('dd_table_head');
                const body = document.getElementById('dd_table_body');

                if (type === 'client_revenue') {
                    head.innerHTML = '<tr><th>Company Name</th><th>Contact</th><th>Status</th><th>Services</th><th>Revenue</th></tr>';
                    let html = '';
                    data.data.forEach(c => {
                        html += `<tr><td><strong>${c.company_name}</strong></td><td>${c.contact_person}</td><td><span class="badge badge-success">${c.status}</span></td><td>${c.service_count} Services</td><td><strong>INR ${Number(c.billed_revenue).toLocaleString('en-IN')}</strong></td></tr>`;
                    });
                    body.innerHTML = html || '<tr><td colspan="5">No records found.</td></tr>';
                } else {
                    head.innerHTML = '<tr><th>Task Code</th><th>Title</th><th>Client & Project</th><th>Assigned Developer</th><th>Status</th><th>SLA Status</th></tr>';
                    let html = '';
                    data.data.forEach(t => {
                        html += `<tr><td><strong>${t.task_code}</strong></td><td>${t.title}</td><td>${t.company_name} - ${t.project_name}</td><td>${t.assigned_to_name || 'Unassigned'}</td><td><span class="badge badge-warning">${t.status}</span></td><td><span class="badge badge-danger">${t.sla_status || 'SLA Breached'}</span></td></tr>`;
                    });
                    body.innerHTML = html || '<tr><td colspan="6" style="text-align:center; padding:20px;">No breached tasks found. All tasks are within SLA!</td></tr>';
                }
            });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
