<?php
// index.php - Main Agency OS Portal Hub

require_once __DIR__ . '/auth.php';
checkAuth();

$user = getCurrentUser();
$db = getDbConnection();

$pageBreadcrumb = "Main Portal";
$pageTitle = "Agency Operations Hub";
$pageSubtitle = "Central workspace for proposals, client contracts, engineering tasks, and business performance.";
require_once __DIR__ . '/header.php';

// Fetch quick metrics
$clientCount = $db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$templateCount = $db->query("SELECT COUNT(*) FROM document_templates WHERE is_active=1")->fetchColumn();
$documentCount = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$projectCount = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();

// Fetch recent documents
$recentDocs = $db->query("SELECT d.*, c.company_name FROM documents d JOIN clients c ON d.client_id = c.id ORDER BY d.id DESC LIMIT 5")->fetchAll();
?>

<div style="margin-bottom: 30px;">
    <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color: white; padding: 25px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-size: 22px; margin-bottom: 6px;">Welcome back, <?= htmlspecialchars($user['full_name']) ?></h2>
            <p style="color: #94a3b8; font-size: 14px; margin: 0;">Role: <strong><?= htmlspecialchars($user['role']) ?></strong> | Department: <strong><?= htmlspecialchars($user['department']) ?></strong></p>
        </div>
        <div>
            <?php if (hasModuleAccess('template-generator')): ?>
                <a href="template_generator.php" class="btn btn-primary">
                    <i data-feather="plus"></i> Open Template Generator
                </a>
            <?php elseif (hasModuleAccess('client-management')): ?>
                <a href="clients.php" class="btn btn-primary">
                    <i data-feather="users"></i> View Client Directory
                </a>
            <?php elseif (hasModuleAccess('task-management')): ?>
                <a href="projects.php" class="btn btn-primary">
                    <i data-feather="check-square"></i> Open Task Hub
                </a>
            <?php elseif (hasModuleAccess('reports')): ?>
                <a href="reports.php" class="btn btn-primary">
                    <i data-feather="bar-chart-2"></i> View Management Reports
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Metrics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <?php if (hasModuleAccess('client-management') || hasModuleAccess('sales')): ?>
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Active Clients</p>
                <h3 style="font-size:24px; font-weight:700; margin-top:4px;"><?= $clientCount ?></h3>
            </div>
            <div style="background:#dbeafe; color:#2563eb; padding:12px; border-radius:8px;">
                <i data-feather="users"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (hasModuleAccess('template-generator')): ?>
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Document Templates</p>
                <h3 style="font-size:24px; font-weight:700; margin-top:4px;"><?= $templateCount ?></h3>
            </div>
            <div style="background:#fef3c7; color:#d97706; padding:12px; border-radius:8px;">
                <i data-feather="file-text"></i>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Generated Documents</p>
                <h3 style="font-size:24px; font-weight:700; margin-top:4px;"><?= $documentCount ?></h3>
            </div>
            <div style="background:#dcfce7; color:#16a34a; padding:12px; border-radius:8px;">
                <i data-feather="check-circle"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (hasModuleAccess('task-management')): ?>
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Work Handover Projects</p>
                <h3 style="font-size:24px; font-weight:700; margin-top:4px;"><?= $projectCount ?></h3>
            </div>
            <div style="background:#f3e8ff; color:#9333ea; padding:12px; border-radius:8px;">
                <i data-feather="cpu"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Live Office Presence Overview Card -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 style="font-size:16px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                <span class="presence-pulse-dot"></span>
                <span>Live Office & Workplace Presence</span>
            </h3>
            <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Real-time tracking of employees who are at their desks, in meetings, or on tea breaks.</p>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <button type="button" class="btn btn-outline btn-sm" onclick="openTeamPresenceModal()" style="border-radius:8px; font-size:12px; display:flex; align-items:center; gap:5px;">
                <i data-feather="users" style="width:13px; height:13px;"></i> View Full Team Status
            </button>
        </div>
    </div>

    <!-- Live Counter Row -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:12px; margin-bottom:14px;" id="dashPresenceChips">
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:10px; text-align:center; cursor:pointer;" onclick="openTeamPresenceModal(); filterTeamPresence('IN_OFFICE');">
            <div style="font-size:22px; font-weight:800; color:#15803d;" id="dashPresInOffice">--</div>
            <div style="font-size:11px; font-weight:700; color:#166534; margin-top:2px;">🟢 At Desk</div>
        </div>
        <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:10px; text-align:center; cursor:pointer;" onclick="openTeamPresenceModal(); filterTeamPresence('TEA_BREAK');">
            <div style="font-size:22px; font-weight:800; color:#b45309;" id="dashPresTea">--</div>
            <div style="font-size:11px; font-weight:700; color:#92400e; margin-top:2px;">☕ Tea Break</div>
        </div>
        <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:10px; text-align:center; cursor:pointer;" onclick="openTeamPresenceModal(); filterTeamPresence('AFK');">
            <div style="font-size:22px; font-weight:800; color:#c2410c;" id="dashPresAfk">--</div>
            <div style="font-size:11px; font-weight:700; color:#9a3412; margin-top:2px;">⏸️ AFK</div>
        </div>
        <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:10px; padding:10px; text-align:center; cursor:pointer;" onclick="openTeamPresenceModal(); filterTeamPresence('MEETING');">
            <div style="font-size:22px; font-weight:800; color:#7e22ce;" id="dashPresMeeting">--</div>
            <div style="font-size:11px; font-weight:700; color:#6b21a8; margin-top:2px;">👥 In Meeting</div>
        </div>
        <div style="background:#fdf2f8; border:1px solid #fbcfe8; border-radius:10px; padding:10px; text-align:center; cursor:pointer;" onclick="openTeamPresenceModal(); filterTeamPresence('LUNCH');">
            <div style="font-size:22px; font-weight:800; color:#be185d;" id="dashPresLunch">--</div>
            <div style="font-size:11px; font-weight:700; color:#9d174d; margin-top:2px;">🍽️ Lunch</div>
        </div>
    </div>

    <!-- Away Staff Notice or Summary -->
    <div id="dashAwayStaffNotice" style="padding:10px 14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; font-size:12px; color:#475569; display:flex; justify-content:space-between; align-items:center;">
        <span>Loading live staff status...</span>
    </div>
</div>

<script>
function updateDashboardPresenceCard() {
    fetch('api.php?action=get_team_presence')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const s = data.summary;
            const elInOffice = document.getElementById('dashPresInOffice');
            const elTea = document.getElementById('dashPresTea');
            const elAfk = document.getElementById('dashPresAfk');
            const elMeeting = document.getElementById('dashPresMeeting');
            const elLunch = document.getElementById('dashPresLunch');
            if (elInOffice) elInOffice.textContent = s.in_office_count || 0;
            if (elTea) elTea.textContent = s.tea_break_count || 0;
            if (elAfk) elAfk.textContent = s.afk_count || 0;
            if (elMeeting) elMeeting.textContent = s.meeting_count || 0;
            if (elLunch) elLunch.textContent = s.lunch_count || 0;

            const notice = document.getElementById('dashAwayStaffNotice');
            if (notice) {
                const awayStaff = (data.staff || []).filter(st => st.is_away);
                if (awayStaff.length === 0) {
                    notice.innerHTML = `<span style="color:#10b981; font-weight:600;">✅ All ${s.total_staff} team members are currently active at their desks in office.</span>`;
                } else {
                    const names = awayStaff.map(st => `<strong>${escapeHtml(st.full_name)}</strong> (${st.status_short}${st.status_note ? ': ' + escapeHtml(st.status_note) : ''})`).join(', ');
                    notice.innerHTML = `<span style="color:#b45309;">⚠️ <strong>${awayStaff.length} member(s) currently stepped away:</strong> ${names}</span>`;
                }
            }
        })
        .catch(err => console.error("Error updating dash presence:", err));
}
document.addEventListener('DOMContentLoaded', () => {
    updateDashboardPresenceCard();
    setInterval(updateDashboardPresenceCard, 25000);
});
</script>

<!-- Accessible Modules Grid -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">System Modules & Roles</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
        <?php if (hasModuleAccess('template-generator')): ?>
        <!-- Module 3: Template Generator -->
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: #ffffff;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="background:#dbeafe; color:#2563eb; padding:8px; border-radius:6px;">
                    <i data-feather="file-text"></i>
                </div>
                <h4 style="margin:0; font-size:16px;">Module 3 — Template Generator</h4>
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; line-height:1.5;">
                Generate proposals, invoices, contracts, MOMs, and service agreements from client data. Export to PDF/Word & handover to Dev team.
            </p>
            <a href="template_generator.php" class="btn btn-primary btn-sm">Launch Module 3</a>
        </div>
        <?php endif; ?>

        <?php if (hasModuleAccess('client-management')): ?>
        <!-- Client Management -->
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: #ffffff;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="background:#e0f2fe; color:#0284c7; padding:8px; border-radius:6px;">
                    <i data-feather="users"></i>
                </div>
                <h4 style="margin:0; font-size:16px;">Client Management & Repository</h4>
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; line-height:1.5;">
                Manage corporate clients, contact details, tax identifiers, client portal credentials, and service package subscriptions.
            </p>
            <a href="clients.php" class="btn btn-outline btn-sm">View Client Directory</a>
        </div>
        <?php endif; ?>

        <?php if (hasModuleAccess('task-management')): ?>
        <!-- Module 4: Project & Task Management -->
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: #ffffff;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="background:#f3e8ff; color:#9333ea; padding:8px; border-radius:6px;">
                    <i data-feather="check-square"></i>
                </div>
                <h4 style="margin:0; font-size:16px;">Module 4 — Task Management</h4>
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; line-height:1.5;">
                Team Lead task assignment, developer workflows, SLA tracking, code reviews, and client revisions.
            </p>
            <a href="projects.php" class="btn btn-outline btn-sm">Phase 2 Task Hub</a>
        </div>
        <?php endif; ?>

        <?php if (hasModuleAccess('reports')): ?>
        <!-- Module 9: Reports & BI -->
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: #ffffff;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="background:#dcfce7; color:#16a34a; padding:8px; border-radius:6px;">
                    <i data-feather="bar-chart-2"></i>
                </div>
                <h4 style="margin:0; font-size:16px;">Module 9 — Management Reports</h4>
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; line-height:1.5;">
                Executive analytics dashboard, client overview, revenue metrics, and employee productivity reports.
            </p>
            <a href="reports.php" class="btn btn-outline btn-sm">Phase 3 BI Hub</a>
        </div>
        <?php endif; ?>

        <?php if (hasModuleAccess('user-management')): ?>
        <!-- User Management -->
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: #ffffff;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="background:#fef3c7; color:#d97706; padding:8px; border-radius:6px;">
                    <i data-feather="user-check"></i>
                </div>
                <h4 style="margin:0; font-size:16px;">User & Team Lead Management</h4>
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; line-height:1.5;">
                System user account management, role assignments, department allocation, and Team Lead provisioning.
            </p>
            <a href="users.php" class="btn btn-outline btn-sm">Manage System Users</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (hasModuleAccess('template-generator') || hasModuleAccess('sales') || hasModuleAccess('client-management')): ?>
<!-- Recent Generated Documents -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Generated Documents</h3>
        <a href="template_generator.php" class="btn btn-outline btn-sm">View All Documents</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Doc #</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentDocs)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No generated documents found yet. Use Template Generator to create one!</td></tr>
                <?php else: ?>
                    <?php foreach ($recentDocs as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['document_number']) ?></strong></td>
                            <td><?= htmlspecialchars($d['company_name']) ?></td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($d['document_type']) ?></span></td>
                            <td><?= htmlspecialchars($d['title']) ?></td>
                            <td><span class="badge badge-success"><?= htmlspecialchars($d['status']) ?></span></td>
                            <td><?= date('d M Y H:i', strtotime($d['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
