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

<!-- Client Monthly Strategy & Deliverables Plan -->
<div id="clientMonthlyPlanCard" class="card" style="display:none; margin-bottom: 25px; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05); padding: 0;">
    <!-- Top Gradient Header -->
    <div style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 20px 24px; color: #ffffff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                📅
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <h3 id="cmpClientTitle" style="margin: 0; font-size: 18px; font-weight: 800; color: #ffffff;">Monthly Strategy &amp; Deliverables Plan</h3>
                    <span id="cmpClientMonthBadge" class="badge" style="background: rgba(255,255,255,0.22); color: #ffffff; font-weight: 800; font-size: 11px; padding: 4px 10px; border-radius: 999px;">
                        🗓️ Current Month
                    </span>
                    <span id="cmpClientStatusBadge" class="badge" style="background: #22c55e; color: #ffffff; font-weight: 800; font-size: 11px; padding: 4px 10px; border-radius: 999px;">
                        ● Active
                    </span>
                </div>
                <div id="cmpClientFinancialsRow" style="display:none; align-items:center; gap:8px; flex-wrap:wrap; margin-top:6px;"></div>
                <p id="cmpClientSubtitle" style="margin: 6px 0 0 0; font-size: 13px; color: rgba(255,255,255,0.88);">
                    Your tailored growth deliverables, channel targets, and ongoing campaign roadmap.
                </p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div id="cmpClientStatsBox" style="text-align: right; background: rgba(255,255,255,0.18); padding: 8px 16px; border-radius: 10px; backdrop-filter: blur(8px);">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255,255,255,0.85);">Overall Completion</div>
                <div id="cmpClientProgressText" style="font-size: 16px; font-weight: 800; color: #ffffff;">0% Completed</div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div style="background: #f1f5f9; height: 6px; width: 100%; overflow: hidden;">
        <div id="cmpClientProgressBar" style="background: linear-gradient(90deg, #10b981, #06b6d4); height: 100%; width: 0%; transition: width 0.6s ease;"></div>
    </div>

    <!-- Deliverables Modern Table View -->
    <div style="padding: 22px 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 14px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">📋 Deliverables &amp; Milestones Table</span>
                <span id="cmpClientCountBadge" class="badge" style="background: #e0e7ff; color: #4338ca; font-weight: 800; font-size: 11px; padding: 3px 9px; border-radius: 999px;">0 Deliverables</span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; font-size: 12px; color: #64748b;">
                <span>✨ Real-time updates by your dedicated agency team</span>
            </div>
        </div>

        <div id="cmpClientTableWrapper" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow-x: auto; box-shadow: 0 1px 4px rgba(0,0,0,0.02);">
            <!-- Rendered Table -->
        </div>
    </div>
</div>

<div id="cmpClientEmptyBanner" style="display:none; margin-bottom: 25px; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 20px 24px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    <div style="display:flex; align-items:center; gap:14px;">
        <div style="font-size:28px;">📅</div>
        <div>
            <h4 style="margin:0 0 4px 0; color:#0f172a; font-size:15px; font-weight:800;">Monthly Strategy &amp; Deliverables Roadmap</h4>
            <p style="margin:0; font-size:13px; color:#64748b;">Your monthly roadmap &amp; deliverables plan is being customized with your dedicated account lead. Live progress will appear here once published.</p>
        </div>
    </div>
</div>

<!-- Active Projects, Services & Deliverables Status -->
<div class="card" style="margin-bottom: 25px;">
    <div class="card-header">
        <h3 class="card-title"><i data-feather="briefcase" style="width:18px; height:18px; color:#2563eb;"></i> Projects, Services &amp; Deliverables Status</h3>
        <a href="/client/projects.php" class="btn btn-outline btn-sm">All Projects &rarr;</a>
    </div>
    <div id="projectsProgressList" style="display:flex; flex-direction:column; gap:20px; padding:18px 20px;">
        <p style="color:var(--text-muted);">Loading projects and service status...</p>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 1fr; align-items: start; margin-bottom: 25px;">
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

    <!-- Recent Client Activity Audit Log -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i data-feather="clock" style="width:18px; height:18px;"></i> Work Activity &amp; Status Updates</h3>
            <a href="/client/tasks.php" class="btn btn-outline btn-sm">View All Tasks</a>
        </div>
        <div id="activitiesList" style="display:flex; flex-direction:column; gap:12px;">
            <p style="color:var(--text-muted);">Loading recent activity timeline...</p>
        </div>
    </div>
</div>

<script>
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatClientMonthLabel(mStr) {
    if (!mStr || mStr.length < 7) return mStr || '';
    const [y, m] = mStr.split('-');
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const idx = parseInt(m, 10) - 1;
    return (months[idx] || m) + ' ' + y;
}

function getClientChannelBadgeHtml(ch) {
    if (!ch || !ch.trim()) return '<span style="color:#cbd5e1; font-weight:600;">—</span>';
    const c = ch.trim();
    const cl = c.toLowerCase();
    let icon = '📱';
    let bg = '#eff6ff', fg = '#1e40af', border = '#bfdbfe';
    if (cl.includes('seo')) { icon = '🔍'; bg = '#ecfdf5'; fg = '#065f46'; border = '#a7f3d0'; }
    else if (cl.includes('web') || cl.includes('dev')) { icon = '💻'; bg = '#f0f9ff'; fg = '#0369a1'; border = '#bae6fd'; }
    else if (cl.includes('google') || cl.includes('ad') || cl.includes('ppc')) { icon = '📢'; bg = '#fef2f2'; fg = '#b91c1c'; border = '#fecaca'; }
    else if (cl.includes('insta') || cl.includes('reel') || cl.includes('social') || cl.includes('smm')) { icon = '📱'; bg = '#fdf2f8'; fg = '#be185d'; border = '#fbcfe8'; }
    else if (cl.includes('content') || cl.includes('copy') || cl.includes('blog') || cl.includes('writ')) { icon = '✍️'; bg = '#faf5ff'; fg = '#7e22ce'; border = '#e9d5ff'; }
    else if (cl.includes('video') || cl.includes('youtube')) { icon = '🎬'; bg = '#fff1f2'; fg = '#be123c'; border = '#fecdd3'; }
    else if (cl.includes('design') || cl.includes('ui') || cl.includes('graphic')) { icon = '🎨'; bg = '#fff7ed'; fg = '#c2410c'; border = '#ffedd5'; }
    return `<span class="badge" style="background:${bg}; color:${fg}; border:1px solid ${border}; font-size:11.5px; font-weight:700; padding:4px 10px; border-radius:999px; display:inline-flex; align-items:center; gap:5px;"><span>${icon}</span> ${escapeHtml(c)}</span>`;
}

function getClientMemberInitials(name) {
    if (!name || name === 'Unassigned') return 'AG';
    const parts = name.trim().split(/[\s\-_]+/);
    if (parts.length >= 2 && parts[0] && parts[1]) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.trim().substring(0, 2).toUpperCase();
}

function getClientMemberGradient(name) {
    if (!name || name === 'Unassigned') return 'linear-gradient(135deg, #f59e0b, #d97706)';
    const grads = [
        'linear-gradient(135deg, #6366f1, #8b5cf6)',
        'linear-gradient(135deg, #3b82f6, #06b6d4)',
        'linear-gradient(135deg, #059669, #10b981)',
        'linear-gradient(135deg, #ec4899, #f43f5e)',
        'linear-gradient(135deg, #8b5cf6, #d946ef)',
        'linear-gradient(135deg, #0284c7, #2563eb)'
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return grads[Math.abs(hash) % grads.length];
}

function renderClientMonthlyPlan(plan) {
    const card = document.getElementById('clientMonthlyPlanCard');
    const emptyBanner = document.getElementById('cmpClientEmptyBanner');
    if (!card) return;

    if (!plan || !plan.rows || plan.rows.length === 0) {
        card.style.display = 'none';
        if (emptyBanner) emptyBanner.style.display = 'block';
        return;
    }

    if (emptyBanner) emptyBanner.style.display = 'none';
    card.style.display = 'block';

    const rows = plan.rows || [];
    const columns = plan.columns || [];
    const totalDeliverables = rows.length;
    const doneRows = rows.filter(r => (r.col_status || '').toLowerCase() === 'completed');
    const inProgRows = rows.filter(r => (r.col_status || '').toLowerCase() === 'in progress');
    const underReviewRows = rows.filter(r => (r.col_status || '').toLowerCase() === 'under review');
    const pct = totalDeliverables > 0 ? Math.round((doneRows.length / totalDeliverables) * 100) : 0;

    const monthLabel = formatClientMonthLabel(plan.month);

    document.getElementById('cmpClientTitle').textContent = plan.title || `Monthly Strategy & Deliverables Plan — ${monthLabel}`;
    document.getElementById('cmpClientMonthBadge').innerHTML = `🗓️ ${escapeHtml(monthLabel)}`;
    document.getElementById('cmpClientStatusBadge').textContent = `● ${plan.status || 'Active'}`;
    
    const paidAmount = Number(plan.paid_amount !== undefined ? plan.paid_amount : (plan.budget || 0));
    const platformAllocations = Array.isArray(plan.platform_allocations) ? plan.platform_allocations : (typeof plan.platform_allocations_json === 'string' ? (JSON.parse(plan.platform_allocations_json || '[]') || []) : []);
    let totalAllocated = 0;
    platformAllocations.forEach(pa => {
        totalAllocated += Number(pa.amount || 0);
    });
    const currentRemaining = paidAmount - totalAllocated;

    const finRow = document.getElementById('cmpClientFinancialsRow');
    if (finRow) {
        if (paidAmount > 0) {
            let finHtml = `
                <span class="badge" style="background:rgba(255,255,255,0.25); color:#ffffff; font-weight:800; font-size:11.5px; padding:4px 10px; border-radius:999px;">
                    💵 Client Paid: ₹${Number(paidAmount).toLocaleString()}
                </span>
                ${totalAllocated > 0 ? `
                    <span class="badge" style="background:rgba(255,255,255,0.2); color:#ffffff; font-weight:700; font-size:11px; padding:4px 9px; border-radius:999px;">
                        📊 Platform Spends: ₹${Number(totalAllocated).toLocaleString()}
                    </span>
                ` : ''}
                <span class="badge" style="background:${currentRemaining >= 0 ? '#22c55e' : '#ef4444'}; color:#ffffff; font-weight:800; font-size:11.5px; padding:4px 10px; border-radius:999px;">
                    ${currentRemaining >= 0 ? '✨ Current Amount: ₹' + Number(currentRemaining).toLocaleString() : '⚠️ Over-allocated: -₹' + Number(Math.abs(currentRemaining)).toLocaleString()}
                </span>
            `;
            if (platformAllocations.length > 0) {
                finHtml += `
                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; width:100%; margin-top:5px;">
                        <span style="font-size:11px; color:rgba(255,255,255,0.85); font-weight:700;">Platform Allocations:</span>
                        ${platformAllocations.map(pa => `
                            <span class="badge" style="background:rgba(255,255,255,0.18); color:#ffffff; border:1px solid rgba(255,255,255,0.3); font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px;">
                                ${escapeHtml(pa.platform)}: <strong>₹${Number(pa.amount || 0).toLocaleString()}</strong>
                            </span>
                        `).join('')}
                    </div>
                `;
            }
            finRow.innerHTML = finHtml;
            finRow.style.display = 'flex';
        } else {
            finRow.style.display = 'none';
        }
    }

    document.getElementById('cmpClientProgressText').textContent = `${pct}% (${doneRows.length} of ${totalDeliverables} Completed)`;
    document.getElementById('cmpClientProgressBar').style.width = `${pct}%`;
    document.getElementById('cmpClientCountBadge').textContent = `${totalDeliverables} Deliverable${totalDeliverables === 1 ? '' : 's'}`;

    // Filter out standard columns to find any custom columns
    const customCols = columns.filter(c => !['col_activity', 'col_channel', 'col_target', 'col_week', 'col_assignee', 'col_status', 'col_notes'].includes(c.id));

    let html = `
        <table style="width:100%; border-collapse:collapse; min-width:880px; font-size:13px;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0; color:#475569; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.6px; text-align:left;">
                    <th style="padding:13px 15px; width:40px; text-align:center;">#</th>
                    <th style="padding:13px 15px; min-width:220px;">Activity / Deliverable</th>
                    <th style="padding:13px 15px; min-width:130px;">Platform / Channel</th>
                    <th style="padding:13px 15px; min-width:130px;">Target / Milestone</th>
                    <th style="padding:13px 15px; min-width:110px;">Timeline</th>
                    <th style="padding:13px 15px; min-width:160px;">Assigned Specialist</th>
                    <th style="padding:13px 15px; min-width:140px;">Status</th>
                    ${customCols.map(c => `<th style="padding:13px 15px;">${escapeHtml(c.name)}</th>`).join('')}
                    <th style="padding:13px 15px; min-width:150px;">Remarks / Output</th>
                </tr>
            </thead>
            <tbody>
    `;

    rows.forEach((r, idx) => {
        const st = (r.col_status || 'Planned').trim();
        const ass = (r.col_assignee || '').trim() || 'Agency Specialist';
        const tg = (r.col_target || '').trim();
        const wk = (r.col_week || '').trim();
        const notes = (r.col_notes || '').trim();

        // Modern status styling
        let stBg = '#f1f5f9', stFg = '#475569', stBorder = '#e2e8f0', stIcon = '⚪';
        const stLower = st.toLowerCase();
        if (stLower === 'completed') {
            stBg = '#dcfce7'; stFg = '#15803d'; stBorder = '#bbf7d0'; stIcon = '✅';
        } else if (stLower === 'in progress') {
            stBg = '#eff6ff'; stFg = '#1d4ed8'; stBorder = '#bfdbfe'; stIcon = '🔵';
        } else if (stLower === 'under review') {
            stBg = '#fef3c7'; stFg = '#b45309'; stBorder = '#fde68a'; stIcon = '🟡';
        } else if (stLower === 'approved') {
            stBg = '#ecfdf5'; stFg = '#047857'; stBorder = '#a7f3d0'; stIcon = '🟢';
        } else if (stLower === 'delayed') {
            stBg = '#fee2e2'; stFg = '#b91c1c'; stBorder = '#fecaca'; stIcon = '🔴';
        }

        const avatarGrad = getClientMemberGradient(ass);
        const initials = getClientMemberInitials(ass);

        html += `
            <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.15s ease;" onmouseover="this.style.background='#fafbfc';" onmouseout="this.style.background='#ffffff';">
                <td style="padding:13px 15px; text-align:center; font-weight:800; color:#94a3b8; font-size:12px;">
                    ${idx + 1}
                </td>
                <td style="padding:13px 15px;">
                    <div style="font-weight:700; color:#0f172a; font-size:13.5px; line-height:1.4;">
                        ${escapeHtml(r.col_activity || '')}
                    </div>
                </td>
                <td style="padding:13px 15px;">
                    ${getClientChannelBadgeHtml(r.col_channel)}
                </td>
                <td style="padding:13px 15px;">
                    ${tg ? `
                        <span class="badge" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; font-size:11.5px; font-weight:800; padding:4px 10px; border-radius:999px; display:inline-flex; align-items:center; gap:4px;">
                            <span>🎯</span> ${escapeHtml(tg)}
                        </span>
                    ` : '<span style="color:#cbd5e1; font-weight:600;">—</span>'}
                </td>
                <td style="padding:13px 15px;">
                    ${wk ? `
                        <span class="badge" style="background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; font-size:11px; font-weight:700; padding:4px 9px; border-radius:999px; display:inline-flex; align-items:center; gap:4px;">
                            <span>🗓️</span> ${escapeHtml(wk)}
                        </span>
                    ` : '<span style="color:#cbd5e1; font-weight:600;">—</span>'}
                </td>
                <td style="padding:13px 15px;">
                    <div style="display:inline-flex; align-items:center; gap:8px;">
                        <span style="width:26px; height:26px; border-radius:50%; background:${avatarGrad}; color:#ffffff; display:inline-flex; align-items:center; justify-content:center; font-size:10.5px; font-weight:800; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                            ${escapeHtml(initials)}
                        </span>
                        <span style="font-weight:700; color:#1e293b; font-size:12.5px;">${escapeHtml(ass)}</span>
                    </div>
                </td>
                <td style="padding:13px 15px;">
                    <span class="badge" style="background:${stBg}; color:${stFg}; border:1px solid ${stBorder}; font-size:11px; font-weight:800; padding:4px 10px; border-radius:999px; display:inline-flex; align-items:center; gap:5px;">
                        <span>${stIcon}</span> ${escapeHtml(st)}
                    </span>
                </td>
                ${customCols.map(c => `
                    <td style="padding:13px 15px; font-size:12.5px; color:#334155;">
                        ${escapeHtml(r[c.id] || '—')}
                    </td>
                `).join('')}
                <td style="padding:13px 15px; font-size:12px; color:#64748b;">
                    ${notes ? `
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:6px 10px; font-size:11.5px; color:#334155; line-height:1.4;">
                            ${escapeHtml(notes)}
                        </div>
                    ` : '<span style="color:#cbd5e1; font-weight:600;">—</span>'}
                </td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>
    `;

    document.getElementById('cmpClientTableWrapper').innerHTML = html;
}

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

            // Render Monthly Strategy & Deliverables Plan Table
            renderClientMonthlyPlan(data.monthly_plan);

            // Render Projects
            renderProjects(data.projects);

            // Render Meetings
            renderMeetings(data.upcoming_meetings);

            // Render Activities
            renderActivities(data.recent_activities);
        });
});

function getServiceIcon(serviceName) {
    const s = (serviceName || '').toLowerCase();
    if (s.includes('seo')) return '🔍';
    if (s.includes('web') || s.includes('develop')) return '💻';
    if (s.includes('google') || s.includes('ad') || s.includes('ppc')) return '📢';
    if (s.includes('social') || s.includes('smm') || s.includes('media') || s.includes('insta')) return '📱';
    if (s.includes('design') || s.includes('ui') || s.includes('graphic')) return '🎨';
    if (s.includes('content') || s.includes('copy') || s.includes('writ')) return '✍️';
    if (s.includes('video') || s.includes('shoot')) return '🎬';
    return '⚡';
}

function getClientStatusLabel(status) {
    switch ((status || '').toUpperCase().trim()) {
        case 'PENDING': return { text: 'Waiting to Start', bg: '#f1f5f9', fg: '#475569', icon: 'clock' };
        case 'IN PROGRESS': return { text: 'Work in Progress', bg: '#eff6ff', fg: '#2563eb', icon: 'loader' };
        case 'UNDER REVIEW': return { text: 'Being Reviewed', bg: '#fef3c7', fg: '#d97706', icon: 'eye' };
        case 'SENT FOR CLIENT APPROVAL': return { text: 'Action Required — Your Approval', bg: '#fef3c7', fg: '#b45309', icon: 'alert-circle' };
        case 'REVISION REQUIRED':
        case 'CLIENT REVISION REQUESTED': return { text: 'Changes Requested', bg: '#fee2e2', fg: '#dc2626', icon: 'refresh-cw' };
        case 'COMPLETED': return { text: 'Completed', bg: '#dcfce7', fg: '#15803d', icon: 'check-circle' };
        default: return { text: status, bg: '#f1f5f9', fg: '#475569', icon: 'circle' };
    }
}

function toggleClientSubtasks(taskId) {
    const row = document.getElementById('client_subtasks_' + taskId);
    const chev = document.getElementById('client_subtask_chev_' + taskId);
    if (!row) return;
    if (row.style.display === 'none' || !row.style.display) {
        row.style.display = 'table-row';
        if (chev) chev.textContent = '▼';
    } else {
        row.style.display = 'none';
        if (chev) chev.textContent = '▶';
    }
}

function renderClientTasksTableHtml(tasksList, subtasksByParent) {
    if (!tasksList || tasksList.length === 0) {
        return '<p style="color:var(--text-muted); font-size:12.5px; margin:8px 0; font-style:italic;">No tasks created under this service yet.</p>';
    }

    let rowsHtml = '';
    tasksList.forEach(t => {
        const st = getClientStatusLabel(t.status);
        const isActionRequired = (t.status || '').toUpperCase().trim() === 'SENT FOR CLIENT APPROVAL';
        const subtasks = subtasksByParent[t.id] || [];
        const hasSubtasks = subtasks.length > 0;

        rowsHtml += `
            <tr style="${isActionRequired ? 'background:#fffbebf5;' : ''} border-bottom:1px solid #f1f5f9;">
                <td style="padding:10px 12px; width:110px;">
                    <span class="badge" style="background:#f1f5f9; color:#0f172a; font-family:monospace; font-weight:800; font-size:11px;">
                        ${t.task_code || ('#' + t.id)}
                    </span>
                </td>
                <td style="padding:10px 12px;">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <span style="font-weight:700; color:#0f172a; font-size:13.5px;">${t.title}</span>
                        ${hasSubtasks ? `
                            <button type="button" class="badge" style="background:#f3e8ff; color:#7e22ce; border:1px solid #ddd6fe; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; cursor:pointer;" onclick="toggleClientSubtasks(${t.id})">
                                <span id="client_subtask_chev_${t.id}">▶</span> ${subtasks.length} Subtask${subtasks.length === 1 ? '' : 's'}
                            </button>
                        ` : ''}
                    </div>
                </td>
                <td style="padding:10px 12px; width:190px;">
                    <span class="badge" style="background:${st.bg}; color:${st.fg}; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px;">
                        ${isActionRequired ? '⚠ ' : ''}${st.text}
                    </span>
                </td>
                <td style="padding:10px 12px; width:110px; color:#64748b; font-size:12px;">
                    ${t.deadline ? t.deadline.substring(0, 10) : 'N/A'}
                </td>
                <td style="padding:10px 12px; width:120px; text-align:right;">
                    <a href="/client/task.php?id=${t.id}" class="btn ${isActionRequired ? 'btn-warning' : 'btn-outline'} btn-sm" style="padding:3px 10px; font-size:11.5px; font-weight:700; text-decoration:none;">
                        ${isActionRequired ? 'Review &amp; Sign-Off' : 'View Timeline'}
                    </a>
                </td>
            </tr>

            <!-- Collapsible Subtasks Row -->
            <tr id="client_subtasks_${t.id}" style="display:none; background:#faf5ff;">
                <td colspan="5" style="padding:12px 18px; border-top:1px dashed #e9d5ff; border-bottom:1px solid #e9d5ff;">
                    <div style="background:#ffffff; border:1px solid #e9d5ff; border-left:4px solid #7c3aed; border-radius:8px; padding:12px 16px;">
                        <div style="font-size:12.5px; font-weight:800; color:#581c87; margin-bottom:8px;">
                            ↳ Nested Subtasks for "${t.title}" (${subtasks.length})
                        </div>
                        <table style="width:100%; border-collapse:collapse; font-size:12px;">
                            <thead>
                                <tr style="color:#64748b; border-bottom:1px solid #f1f5f9; text-align:left; font-size:10.5px; text-transform:uppercase;">
                                    <th style="padding:5px 8px; width:90px;">Code</th>
                                    <th style="padding:5px 8px;">Subtask Title</th>
                                    <th style="padding:5px 8px; width:160px;">Status</th>
                                    <th style="padding:5px 8px; width:90px;">Target</th>
                                    <th style="padding:5px 8px; width:80px; text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${subtasks.map(st => {
                                    const subSt = getClientStatusLabel(st.status);
                                    const isSubAction = (st.status || '').toUpperCase().trim() === 'SENT FOR CLIENT APPROVAL';
                                    return `
                                        <tr style="border-bottom:1px solid #f8fafc;">
                                            <td style="padding:5px 8px;">
                                                <span class="badge" style="background:#f1f5f9; color:#581c87; font-family:monospace; font-weight:800; font-size:10.5px;">
                                                    ${st.task_code || ('#' + st.id)}
                                                </span>
                                            </td>
                                            <td style="padding:5px 8px; font-weight:600; color:#1e293b;">
                                                ${st.title}
                                            </td>
                                            <td style="padding:5px 8px;">
                                                <span class="badge" style="background:${subSt.bg}; color:${subSt.fg}; font-size:10.5px; font-weight:700;">
                                                    ${subSt.text}
                                                </span>
                                            </td>
                                            <td style="padding:5px 8px; color:#64748b; font-size:11px;">
                                                ${st.deadline ? st.deadline.substring(0, 10) : 'N/A'}
                                            </td>
                                            <td style="padding:5px 8px; text-align:right;">
                                                <a href="/client/task.php?id=${st.id}" class="btn ${isSubAction ? 'btn-warning' : 'btn-outline'} btn-sm" style="padding:1px 6px; font-size:10.5px;">
                                                    ${isSubAction ? 'Sign-Off' : 'View'}
                                                </a>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        `;
    });

    return `
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid #e2e8f0; color:#64748b; text-align:left; font-size:11px; text-transform:uppercase;">
                        <th style="padding:8px 12px;">Task Code</th>
                        <th style="padding:8px 12px;">Deliverable Title</th>
                        <th style="padding:8px 12px;">Progress Stage</th>
                        <th style="padding:8px 12px;">Target Date</th>
                        <th style="padding:8px 12px; text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>
        </div>
    `;
}

function renderProjects(projects) {
    const container = document.getElementById('projectsProgressList');
    if (!projects || projects.length === 0) {
        container.innerHTML = '<p style="color:var(--text-muted); font-size:13px;">No active projects found.</p>';
        return;
    }

    let html = '';
    projects.forEach(p => {
        const rawTasks = p.tasks || [];
        const mainTasks = rawTasks.filter(t => !t.parent_task_id || parseInt(t.parent_task_id) === 0);
        const allSubtasks = rawTasks.filter(t => parseInt(t.parent_task_id || 0) > 0);
        const subtasksByParent = {};
        allSubtasks.forEach(st => {
            const pid = parseInt(st.parent_task_id);
            if (!subtasksByParent[pid]) subtasksByParent[pid] = [];
            subtasksByParent[pid].push(st);
        });

        // Ensure orphan subtasks link to first main task
        rawTasks.forEach(t => {
            if (t.task_type === 'subtask' && (!t.parent_task_id || parseInt(t.parent_task_id) === 0)) {
                if (mainTasks.length > 0) {
                    const firstId = mainTasks[0].id;
                    if (!subtasksByParent[firstId]) subtasksByParent[firstId] = [];
                    if (!subtasksByParent[firstId].some(x => x.id === t.id)) {
                        subtasksByParent[firstId].push(t);
                    }
                }
            }
        });

        const services = p.services || [];

        html += `
            <div style="border:1px solid #cbd5e1; border-radius:12px; background:#ffffff; box-shadow:0 2px 6px rgba(15,23,42,0.04); overflow:hidden;">
                <!-- PROJECT HEADER -->
                <div style="padding:18px 22px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <h4 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">${p.project_name}</h4>
                            <span class="badge ${p.status === 'Completed' ? 'badge-success' : 'badge-primary'}" style="font-size:11px;">${p.status}</span>
                        </div>
                        <div style="font-size:12.5px; color:#64748b; margin-top:4px;">
                            Target Deadline: <strong style="color:#1e293b;">${p.deadline || 'Ongoing'}</strong>
                        </div>
                    </div>
                    <a href="/client/project.php?id=${p.id}" class="btn btn-outline btn-sm" style="font-weight:700;">View Full Project Details &rarr;</a>
                </div>

                <!-- PROGRESS BAR -->
                <div style="padding:14px 22px; border-bottom:1px solid #f1f5f9; background:#ffffff;">
                    <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                        <span>Overall Project Progress</span>
                        <span>${p.progress_pct}% (${p.completed_tasks}/${p.total_tasks} Deliverables Completed)</span>
                    </div>
                    <div style="background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                        <div style="background:linear-gradient(90deg, #2563eb, #3b82f6); width:${p.progress_pct}%; height:100%; border-radius:4px; transition:width 0.5s ease;"></div>
                    </div>
                </div>

                <!-- SERVICES & TASKS HIERARCHY -->
                <div style="padding:16px 22px; background:#fcfdff;">
                    <div style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:13px; font-weight:800; color:#334155; text-transform:uppercase; letter-spacing:0.5px;">
                            Services &amp; Team Breakdown
                        </span>
                        <span class="badge" style="background:#e0e7ff; color:#3730a3; font-weight:700; font-size:11px;">
                            ${services.length} Service${services.length === 1 ? '' : 's'}
                        </span>
                    </div>

                    ${services.length > 0 ? `
                        <div style="display:flex; flex-direction:column; gap:14px;">
                            ${services.map(srv => {
                                const srvMainTasks = mainTasks.filter(t => {
                                    if (t.task_type === 'subtask') return false;
                                    const sId = parseInt(t.service_id || 0);
                                    if (sId > 0 && sId === parseInt(srv.id)) return true;
                                    if (t.service_name && t.service_name.trim().toLowerCase() === srv.service_name.trim().toLowerCase()) return true;
                                    return false;
                                });
                                const sIcon = getServiceIcon(srv.service_name);

                                return `
                                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                                        <div style="padding:10px 16px; background:#f8fafc; border-bottom:1px solid #edf2f7; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span style="font-size:16px;">${sIcon}</span>
                                                <strong style="font-size:14px; color:#0f172a;">${srv.service_name}</strong>
                                                <div style="display:flex; align-items:center; gap:5px; margin-left:10px; flex-wrap:wrap;">
                                                    <span style="font-size:11px; color:#64748b; font-weight:600;">👥 Team:</span>
                                                    ${(srv.assignees && srv.assignees.length) ? srv.assignees.map(m => `
                                                        <span class="badge" style="background:#e0e7ff; color:#3730a3; font-size:10.5px; font-weight:700; padding:2px 7px; border-radius:999px;">
                                                            👤 ${m.full_name}
                                                        </span>
                                                    `).join('') : '<span style="color:#94a3b8; font-size:11px; font-style:italic;">Agency Team Assigned</span>'}
                                                </div>
                                            </div>
                                            <span class="badge" style="background:#f1f5f9; color:#475569; font-size:11px; font-weight:700;">
                                                ${srvMainTasks.length} Deliverable${srvMainTasks.length === 1 ? '' : 's'}
                                            </span>
                                        </div>

                                        <div style="padding:10px 16px;">
                                            ${renderClientTasksTableHtml(srvMainTasks, subtasksByParent)}
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    ` : ''}

                    ${(() => {
                        const generalMainTasks = mainTasks.filter(t => {
                            if (t.task_type === 'subtask') return false;
                            const sId = parseInt(t.service_id || 0);
                            const matched = services.some(s => parseInt(s.id) === sId || (t.service_name && t.service_name.trim().toLowerCase() === s.service_name.trim().toLowerCase()));
                            return !matched;
                        });

                        if (services.length === 0) {
                            return `
                                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px;">
                                    ${renderClientTasksTableHtml(mainTasks, subtasksByParent)}
                                </div>
                            `;
                        } else if (generalMainTasks.length > 0) {
                            return `
                                <div style="margin-top:14px; background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
                                    <div style="padding:10px 16px; background:#f8fafc; border-bottom:1px solid #edf2f7; display:flex; justify-content:space-between; align-items:center;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <span>📦</span>
                                            <strong style="font-size:13.5px; color:#0f172a;">General / Other Project Deliverables</strong>
                                        </div>
                                        <span class="badge" style="background:#f1f5f9; color:#475569; font-size:11px; font-weight:700;">
                                            ${generalMainTasks.length} Deliverables
                                        </span>
                                    </div>
                                    <div style="padding:10px 16px;">
                                        ${renderClientTasksTableHtml(generalMainTasks, subtasksByParent)}
                                    </div>
                                </div>
                            `;
                        }
                        return '';
                    })()}
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
