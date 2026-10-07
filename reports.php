<?php
// reports.php - Module 9 Reports & Management BI Dashboard Suite

require_once __DIR__ . '/auth.php';
checkAuth('reports');

$user = getCurrentUser();
$db = getDbConnection();

$pageTitle = "Module 9 — Reports & Executive Management BI Dashboard";
require_once __DIR__ . '/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
    <div>
        <h2 style="font-size:20px; font-weight:700;">⚡ Executive Business Intelligence Dashboard</h2>
        <p style="color:var(--text-muted); font-size:14px;">Real-time aggregation of transactional data across Clients, Revenue, Tasks, SLA records, and Developer Productivity.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-outline" onclick="loadExecutiveSummary()">
            <i data-feather="refresh-cw"></i> Refresh BI Data
        </button>
        <button class="btn btn-primary" onclick="openDrilldownModal('delayed_tasks')">
            <i data-feather="search"></i> Executive Drill-Down
        </button>
    </div>
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
            📊 Developer Productivity Leaderboard
        </button>
        <button id="tabBtn2" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0;" onclick="switchTab(2)">
            💼 Client & Billed Revenue BI
        </button>
        <button id="tabBtn3" class="btn btn-outline" style="border:none; border-bottom:3px solid transparent; border-radius:0;" onclick="switchTab(3)">
            🛠️ Task Status & SLA Analytics
        </button>
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
</div>

<!-- MODAL: EXECUTIVE DRILL-DOWN TOOL -->
<div id="drilldownModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:900px;">
        <div class="modal-header">
            <h3 class="modal-title" id="dd_title">🔍 Executive Drill-Down Analysis</h3>
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
        for (let i = 1; i <= 3; i++) {
            document.getElementById(`tabContent${i}`).style.display = i === num ? 'block' : 'none';
            const btn = document.getElementById(`tabBtn${i}`);
            btn.style.borderBottomColor = i === num ? '#2563eb' : 'transparent';
            btn.style.fontWeight = i === num ? '700' : '500';
            btn.style.color = i === num ? '#2563eb' : 'var(--text)';
        }
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
                document.getElementById('dd_title').textContent = `🔍 Drill-Down: ${data.type}`;
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
