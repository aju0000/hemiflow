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

<!-- Task Status List Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-feather="check-square" style="width:18px; height:18px;"></i> Tasks & Deliverables Stage</h3>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Stage / Status</th>
                    <th>Deliverable Stage</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="projectTasksTable">
                <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">Loading task list...</td></tr>
            </tbody>
        </table>
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
            document.getElementById('p_service_badge').textContent = p.service_name ? `${p.service_name} (${p.package_name || 'Standard'})` : 'Service Handover';
            document.getElementById('p_description').textContent = p.description || 'Project deliverables and implementation requirements.';
            document.getElementById('p_status_badge').textContent = p.status;
            document.getElementById('p_deadline').textContent = p.deadline || 'N/A';

            // Progress
            document.getElementById('p_pct_text').textContent = `${p.progress_pct}% Completed (${p.completed_tasks}/${p.total_tasks} Tasks)`;
            document.getElementById('p_pct_bar').style.width = `${p.progress_pct}%`;

            // Render Tasks
            renderProjectTasks(data.tasks);
        });
});

function getClientStatusLabel(status) {
    switch (status) {
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

function renderProjectTasks(tasks) {
    const table = document.getElementById('projectTasksTable');
    if (!tasks || tasks.length === 0) {
        table.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No tasks created under this project yet.</td></tr>';
        return;
    }

    let html = '';
    tasks.forEach(t => {
        const st = getClientStatusLabel(t.status);
        const isActionRequired = t.status === 'SENT FOR CLIENT APPROVAL';
        
        html += `
            <tr style="${isActionRequired ? 'background:#fffbebf5;' : ''}">
                <td>
                    <div style="font-weight:700; color:#0f172a;">${t.task_code}: ${t.title}</div>
                    <div style="font-size:12px; color:#64748b; margin-top:2px;">Target: ${t.deadline ? t.deadline.substring(0, 10) : 'N/A'}</div>
                </td>
                <td>
                    <span class="badge ${st.class}" style="font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                        <i data-feather="${st.icon}" style="width:12px; height:12px;"></i> ${st.text}
                    </span>
                </td>
                <td>
                    <div style="font-size:13px; color:#334155;">
                        ${isActionRequired ? '<strong style="color:#d97706;">⚠ Ready for Your Sign-off</strong>' : (t.status === 'COMPLETED' ? '<span style="color:#16a34a;">✓ Completed & Approved</span>' : 'Development / Review Phase')}
                    </div>
                </td>
                <td>
                    <a href="/client/task.php?id=${t.id}" class="btn ${isActionRequired ? 'btn-warning' : 'btn-outline'} btn-sm">
                        ${isActionRequired ? '<i data-feather="check-square"></i> Review Work' : '<i data-feather="eye"></i> View Timeline'}
                    </a>
                </td>
            </tr>
        `;
    });
    table.innerHTML = html;
    if (window.feather) feather.replace();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
