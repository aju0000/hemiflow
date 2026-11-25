<?php
// client/project.php - Project Details & Task Progress Breakdown

$projectId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/includes/client_auth.php';
verifyClientOwnership('projects', $projectId);

$pageTitle = "Project Details";
require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="/client/projects.php" class="btn btn-outline btn-sm">
        <i data-feather="arrow-left"></i> Back to Projects
    </a>
</div>

<!-- Project Details Header Card -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px; flex-wrap:wrap; gap:12px;">
        <div>
            <span id="p_service_badge" class="badge badge-primary" style="font-size:12px;">Service Package</span>
            <h2 id="p_name" style="font-size:24px; font-weight:800; color:#0f172a; margin-top:8px;">Loading project details...</h2>
            <p id="p_description" style="color:#475569; font-size:14px; margin-top:6px; line-height:1.6; white-space:pre-wrap;">Loading description...</p>
        </div>
        <div style="text-align:right;">
            <span id="p_status_badge" class="badge badge-warning" style="font-size:13px;">In Progress</span>
            <div style="margin-top:8px; font-size:13px; color:#64748b;">
                Target Deadline: <strong id="p_deadline" style="color:#0f172a;">-</strong>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:18px; margin-top:20px;">
        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; color:#334155; margin-bottom:8px;">
            <span>Overall Project Progress</span>
            <span id="p_pct_text">0% Completed</span>
        </div>
        <div style="background:#cbd5e1; height:10px; border-radius:5px; overflow:hidden;">
            <div id="p_pct_bar" style="background:linear-gradient(90deg, #2563eb, #3b82f6); width:0%; height:100%; border-radius:5px; transition:width 0.6s ease;"></div>
        </div>
    </div>
</div>

<!-- Services & Deliverables Stage Breakdown Card -->
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title"><i data-feather="briefcase" style="width:18px; height:18px; color:#2563eb;"></i> Services &amp; Deliverables Breakdown</h3>
        <span id="p_services_count_badge" class="badge badge-primary" style="font-size:12px;">Services Active</span>
    </div>
    
    <div id="projectServicesWorkspace" style="padding:16px; display:flex; flex-direction:column; gap:16px;">
        <p style="color:var(--text-muted); text-align:center;">Loading services and deliverables...</p>
    </div>
</div>

<script>
const projectId = <?= $projectId ?>;

document.addEventListener('DOMContentLoaded', () => {
    fetch(`/client/api.php?action=get_project_details&project_id=${projectId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Error: ' + data.message);
                window.location.href = '/client/projects.php';
                return;
            }
            const p = data.project;

            document.getElementById('p_name').textContent = p.project_name;
            document.getElementById('p_service_badge').textContent = p.service_name ? `${p.service_name} (${p.package_name || 'Standard'})` : 'Service Package';
            document.getElementById('p_description').textContent = p.description || 'Project deliverables and implementation requirements.';
            document.getElementById('p_status_badge').textContent = p.status;
            document.getElementById('p_deadline').textContent = p.deadline || 'N/A';

            // Progress
            document.getElementById('p_pct_text').textContent = `${p.progress_pct}% Completed (${p.completed_tasks}/${p.total_tasks} Tasks)`;
            document.getElementById('p_pct_bar').style.width = `${p.progress_pct}%`;

            // Badge
            const sCount = (p.services || []).length;
            const badgeEl = document.getElementById('p_services_count_badge');
            if (badgeEl) badgeEl.textContent = `${sCount} Service${sCount === 1 ? '' : 's'}`;

            // Render Tasks & Services
            renderProjectServicesAndTasks(data.tasks, p.services || []);
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
        case 'PENDING': return { text: 'Waiting to Start', class: 'badge-secondary', icon: 'clock' };
        case 'IN PROGRESS': return { text: 'Work in Progress', class: 'badge-primary', icon: 'loader' };
        case 'UNDER REVIEW': return { text: 'Being Reviewed', class: 'badge-info', icon: 'eye' };
        case 'SENT FOR CLIENT APPROVAL': return { text: 'Action Required — Your Approval', class: 'badge-warning', icon: 'alert-circle' };
        case 'REVISION REQUIRED':
        case 'CLIENT REVISION REQUESTED': return { text: 'Changes Requested', class: 'badge-danger', icon: 'refresh-cw' };
        case 'COMPLETED': return { text: 'Completed', class: 'badge-success', icon: 'check-circle' };
        default: return { text: status, class: 'badge-secondary', icon: 'circle' };
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
        return '<p style="color:var(--text-muted); font-size:12.5px; margin:8px 0; font-style:italic;">No tasks created under this section yet.</p>';
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
                    <span class="badge ${st.class}" style="font-size:11px; font-weight:700; padding:3px 9px;">
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
                                                <span class="badge ${subSt.class}" style="font-size:10.5px; font-weight:700;">
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

function renderProjectServicesAndTasks(tasks, services) {
    const container = document.getElementById('projectServicesWorkspace');
    if (!container) return;

    const rawTasks = tasks || [];
    const mainTasks = rawTasks.filter(t => !t.parent_task_id || parseInt(t.parent_task_id) === 0);
    const allSubtasks = rawTasks.filter(t => parseInt(t.parent_task_id || 0) > 0);
    const subtasksByParent = {};
    allSubtasks.forEach(st => {
        const pid = parseInt(st.parent_task_id);
        if (!subtasksByParent[pid]) subtasksByParent[pid] = [];
        subtasksByParent[pid].push(st);
    });

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

    let html = '';

    if (services.length > 0) {
        services.forEach(srv => {
            const srvMainTasks = mainTasks.filter(t => {
                if (t.task_type === 'subtask') return false;
                const sId = parseInt(t.service_id || 0);
                if (sId > 0 && sId === parseInt(srv.id)) return true;
                if (t.service_name && t.service_name.trim().toLowerCase() === srv.service_name.trim().toLowerCase()) return true;
                return false;
            });
            const sIcon = getServiceIcon(srv.service_name);

            html += `
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <div style="padding:12px 18px; background:#f8fafc; border-bottom:1px solid #edf2f7; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:18px;">${sIcon}</span>
                            <strong style="font-size:15px; color:#0f172a;">${srv.service_name}</strong>
                            <div style="display:flex; align-items:center; gap:5px; margin-left:12px; flex-wrap:wrap;">
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

                    <div style="padding:12px 18px;">
                        ${renderClientTasksTableHtml(srvMainTasks, subtasksByParent)}
                    </div>
                </div>
            `;
        });
    }

    const generalMainTasks = mainTasks.filter(t => {
        if (t.task_type === 'subtask') return false;
        const sId = parseInt(t.service_id || 0);
        const matched = services.some(s => parseInt(s.id) === sId || (t.service_name && t.service_name.trim().toLowerCase() === s.service_name.trim().toLowerCase()));
        return !matched;
    });

    if (services.length === 0) {
        html += `
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px;">
                ${renderClientTasksTableHtml(mainTasks, subtasksByParent)}
            </div>
        `;
    } else if (generalMainTasks.length > 0) {
        html += `
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
                <div style="padding:10px 16px; background:#f8fafc; border-bottom:1px solid #edf2f7; display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span>📦</span>
                        <strong style="font-size:13.5px; color:#0f172a;">General / Other Project Deliverables</strong>
                    </div>
                    <span class="badge" style="background:#f1f5f9; color:#475569; font-size:11px; font-weight:700;">
                        ${generalMainTasks.length} Deliverables
                    </span>
                </div>
                <div style="padding:12px 18px;">
                    ${renderClientTasksTableHtml(generalMainTasks, subtasksByParent)}
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
    if (window.feather) feather.replace();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
