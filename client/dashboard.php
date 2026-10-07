<?php
// client/dashboard.php - Client Portal Main Dashboard Hub

$pageTitle = "Client Dashboard";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 25px 30px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h2 style="font-size: 22px; font-weight: 800; margin: 0 0 6px 0; color:#ffffff; letter-spacing:-0.5px;">
            Welcome back, <?= htmlspecialchars($clientData['company_name']) ?> 👋
        </h2>
        <p style="color: #94a3b8; font-size: 14px; margin: 0;">
            Track your ongoing projects, review work progress, approve completed deliverables, and join scheduled meetings.
        </p>
    </div>
    <div>
        <a href="/client/approvals.php" class="btn btn-warning" style="padding:10px 18px; text-decoration:none; font-weight:700;">
            <i data-feather="check-square"></i> Review Approvals
        </a>
    </div>
</div>

<!-- Prominent Action Required Alert Banner (if pending approvals exist) -->
<div id="actionRequiredAlert" style="display:none; background: #fffbebf5; border: 1px solid #fde68a; border-left: 5px solid #f59e0b; padding: 18px 22px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:14px;">
            <div style="background:#fef3c7; color:#d97706; padding:10px; border-radius:50%;">
                <i data-feather="alert-triangle" style="width:24px; height:24px;"></i>
            </div>
            <div>
                <h4 style="margin:0; font-size:16px; font-weight:700; color:#92400e;">
                    ⚠ Action Required — Deliverables Waiting for Your Approval
                </h4>
                <p id="actionRequiredText" style="margin:4px 0 0 0; font-size:13px; color:#b45309;">
                    You have work waiting for sign-off. Please review and approve or request revisions.
                </p>
            </div>
        </div>
        <a href="/client/approvals.php" class="btn btn-primary btn-sm" style="padding:8px 16px;">
            Review Now &rarr;
        </a>
    </div>
</div>

<!-- Real-time Summary Cards -->
<div class="grid-4" style="margin-bottom: 30px;">
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Active Projects</p>
                <h3 id="m_active_projects" style="font-size:26px; font-weight:800; margin-top:4px; color:#0f172a;">-</h3>
            </div>
            <div style="background:#dbeafe; color:#2563eb; padding:14px; border-radius:10px;">
                <i data-feather="folder" style="width:22px; height:22px;"></i>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Work In Progress</p>
                <h3 id="m_in_progress" style="font-size:26px; font-weight:800; margin-top:4px; color:#0f172a;">-</h3>
            </div>
            <div style="background:#e0e7ff; color:#4f46e5; padding:14px; border-radius:10px;">
                <i data-feather="loader" style="width:22px; height:22px;"></i>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Awaiting Your Approval</p>
                <h3 id="m_awaiting_approval" style="font-size:26px; font-weight:800; margin-top:4px; color:#d97706;">-</h3>
            </div>
            <div style="background:#fef3c7; color:#d97706; padding:14px; border-radius:10px;">
                <i data-feather="check-square" style="width:22px; height:22px;"></i>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Completed Work</p>
                <h3 id="m_completed_tasks" style="font-size:26px; font-weight:800; margin-top:4px; color:#16a34a;">-</h3>
            </div>
            <div style="background:#dcfce7; color:#16a34a; padding:14px; border-radius:10px;">
                <i data-feather="check-circle" style="width:22px; height:22px;"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 1fr; align-items: start; margin-bottom: 25px;">
    <!-- Active Projects Progress Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-feather="activity" style="width:18px; height:18px;"></i> Project Status & Progress</h3>
            <a href="/client/projects.php" class="btn btn-outline btn-sm">View All Projects</a>
        </div>
        <div id="projectsProgressList" style="display:flex; flex-direction:column; gap:16px;">
            <p style="color:var(--text-muted);">Loading project status...</p>
        </div>
    </div>

    <!-- Upcoming Meetings Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-feather="video" style="width:18px; height:18px;"></i> Upcoming Google Meetings</h3>
            <a href="/client/meetings.php" class="btn btn-outline btn-sm">View Schedule</a>
        </div>
        <div id="upcomingMeetingsList" style="display:flex; flex-direction:column; gap:14px;">
            <p style="color:var(--text-muted);">Loading meeting schedule...</p>
        </div>
    </div>
</div>

<!-- Recent Client Activity Audit Log -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-feather="clock" style="width:18px; height:18px;"></i> Work Activity & Status Updates</h3>
        <a href="/client/tasks.php" class="btn btn-outline btn-sm">View All Tasks</a>
    </div>
    <div id="activitiesList" style="display:flex; flex-direction:column; gap:12px;">
        <p style="color:var(--text-muted);">Loading recent activity timeline...</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('/client/api.php?action=get_dashboard_summary')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const m = data.metrics;

            // Render Metrics
            document.getElementById('m_active_projects').textContent = m.active_projects;
            document.getElementById('m_in_progress').textContent = m.in_progress_tasks;
            document.getElementById('m_awaiting_approval').textContent = m.awaiting_approval;
            document.getElementById('m_completed_tasks').textContent = m.completed_tasks;

            // Action Required Banner
            if (m.awaiting_approval > 0) {
                document.getElementById('actionRequiredAlert').style.display = 'block';
                document.getElementById('actionRequiredText').textContent = `You have ${m.awaiting_approval} item(s) awaiting your review and approval.`;
            }

            // Render Projects
            renderProjects(data.projects);

            // Render Meetings
            renderMeetings(data.upcoming_meetings);

            // Render Activities
            renderActivities(data.recent_activities);
        });
});

function renderProjects(projects) {
    const container = document.getElementById('projectsProgressList');
    if (!projects || projects.length === 0) {
        container.innerHTML = '<p style="color:var(--text-muted); font-size:13px;">No active projects found.</p>';
        return;
    }

    let html = '';
    projects.forEach(p => {
        html += `
            <div style="border:1px solid #e2e8f0; border-radius:8px; padding:16px; background:#ffffff;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <div>
                        <h4 style="margin:0; font-size:15px; font-weight:700; color:#0f172a;">${p.project_name}</h4>
                        <span class="badge badge-primary" style="font-size:11px; margin-top:4px;">Status: ${p.status}</span>
                    </div>
                    <a href="/client/project.php?id=${p.id}" class="btn btn-outline btn-sm">View Project &rarr;</a>
                </div>
                <div style="margin-top:12px;">
                    <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:600; color:#64748b; margin-bottom:4px;">
                        <span>Overall Progress</span>
                        <span>${p.progress_pct}% (${p.completed_tasks}/${p.total_tasks} Tasks)</span>
                    </div>
                    <div style="background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                        <div style="background:linear-gradient(90deg, #2563eb, #3b82f6); width:${p.progress_pct}%; height:100%; border-radius:4px; transition:width 0.5s ease;"></div>
                    </div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function renderMeetings(meetings) {
    const container = document.getElementById('upcomingMeetingsList');
    if (!meetings || meetings.length === 0) {
        container.innerHTML = '<p style="color:var(--text-muted); font-size:13px;">No upcoming meetings scheduled.</p>';
        return;
    }

    let html = '';
    meetings.forEach(m => {
        html += `
            <div style="border:1px solid #cbd5e1; border-radius:8px; padding:14px; background:#f0f9ff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <h5 style="margin:0; font-size:14px; font-weight:700; color:#0369a1;">${m.title}</h5>
                    <div style="font-size:12px; color:#475569; margin-top:4px;">
                        📅 <strong>${m.meeting_date}</strong> at ⏰ <strong>${m.meeting_time}</strong>
                    </div>
                </div>
                <a href="${m.meet_url}" target="_blank" rel="noopener" class="btn btn-primary btn-sm" style="background:#0284c7; border-color:#0284c7;">
                    <i data-feather="video"></i> Join Google Meet
                </a>
            </div>
        `;
    });
    container.innerHTML = html;
    if (window.feather) feather.replace();
}

function renderActivities(activities) {
    const container = document.getElementById('activitiesList');
    if (!activities || activities.length === 0) {
        container.innerHTML = '<p style="color:var(--text-muted); font-size:13px;">No public activities recorded yet.</p>';
        return;
    }

    let html = '';
    activities.forEach(a => {
        html += `
            <div style="border-left:3px solid #2563eb; padding-left:12px; background:#ffffff; border-radius:0 6px 6px 0; padding-top:8px; padding-bottom:8px;">
                <div style="display:flex; justify-content:space-between; font-size:12px;">
                    <strong style="color:#0f172a;">${a.task_code}: ${a.task_title}</strong>
                    <span style="color:#64748b;">${a.created_at}</span>
                </div>
                <div style="font-size:13px; color:#334155; margin-top:2px;">${a.remarks || a.action}</div>
            </div>
        `;
    });
    container.innerHTML = html;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
