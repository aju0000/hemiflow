<?php
// client/tasks.php - Client Task Overview & Filterable Work View

$pageTitle = "My Tasks";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-feather="check-circle" style="width:18px; height:18px;"></i> Task Progress & Deliverables</h3>
    </div>

    <!-- Filter Tabs -->
    <div style="display:flex; gap:10px; border-bottom:1px solid #e2e8f0; padding-bottom:15px; margin-bottom:20px; flex-wrap:wrap;">
        <button class="btn btn-outline btn-sm filter-tab active" data-status="" onclick="filterTasks('')">All Tasks</button>
        <button class="btn btn-warning btn-sm filter-tab" data-status="ACTION_REQUIRED" onclick="filterTasks('ACTION_REQUIRED')">⚠ Action Required (Approvals)</button>
        <button class="btn btn-outline btn-sm filter-tab" data-status="IN_PROGRESS" onclick="filterTasks('IN_PROGRESS')">Work in Progress</button>
        <button class="btn btn-outline btn-sm filter-tab" data-status="COMPLETED" onclick="filterTasks('COMPLETED')">Completed Tasks</button>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Task Code</th>
                    <th>Task Title & Project</th>
                    <th>Client Status Stage</th>
                    <th>Target Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="clientTasksList">
                <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">Loading tasks...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let currentStatus = '';

document.addEventListener('DOMContentLoaded', () => {
    loadTasks('');
});

function filterTasks(status) {
    currentStatus = status;
    document.querySelectorAll('.filter-tab').forEach(b => {
        if (b.getAttribute('data-status') === status) {
            b.className = 'btn btn-primary btn-sm filter-tab active';
        } else {
            b.className = 'btn btn-outline btn-sm filter-tab';
        }
    });
    loadTasks(status);
}

function loadTasks(status) {
    fetch(`/client/api.php?action=get_tasks&status=${status}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const table = document.getElementById('clientTasksList');
            if (!data.tasks || data.tasks.length === 0) {
                table.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No tasks found in this section.</td></tr>';
                return;
            }

            let html = '';
            data.tasks.forEach(t => {
                const st = getClientStatusLabel(t.status);
                const isActionRequired = t.status === 'SENT FOR CLIENT APPROVAL';

                html += `
                    <tr style="${isActionRequired ? 'background:#fffbebf5;' : ''}">
                        <td><strong>${t.task_code}</strong></td>
                        <td>
                            <div style="font-weight:700; color:#0f172a;">${t.title}</div>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">Project: ${t.project_name}</div>
                        </td>
                        <td>
                            <span class="badge ${st.class}" style="font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                                <i data-feather="${st.icon}" style="width:12px; height:12px;"></i> ${st.text}
                            </span>
                        </td>
                        <td>${t.deadline ? t.deadline.substring(0, 10) : 'N/A'}</td>
                        <td>
                            <a href="/client/task.php?id=${t.id}" class="btn ${isActionRequired ? 'btn-warning' : 'btn-outline'} btn-sm">
                                ${isActionRequired ? '<i data-feather="check-square"></i> Review Work' : '<i data-feather="eye"></i> View Details'}
                            </a>
                        </td>
                    </tr>
                `;
            });
            table.innerHTML = html;
            if (window.feather) feather.replace();
        });
}

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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
