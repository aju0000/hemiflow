<?php
// client/approvals.php - Client Approvals History & Pending Sign-offs

$pageTitle = "Approvals & Sign-offs";
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i data-feather="check-square" style="width:18px; height:18px;"></i> Approvals Audit History</h3>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Task / Deliverable</th>
                    <th>Project</th>
                    <th>Approval Status</th>
                    <th>Feedback / Comment</th>
                    <th>Timestamp</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="approvalsList">
                <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Loading approval history...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('/client/api.php?action=get_approvals')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const container = document.getElementById('approvalsList');
            if (!data.approvals || data.approvals.length === 0) {
                container.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No approval records found yet.</td></tr>';
                return;
            }

            let html = '';
            data.approvals.forEach(a => {
                const isApproved = a.approval_status === 'Approved';
                html += `
                    <tr>
                        <td>
                            <strong>${a.task_code}</strong><br>
                            <span style="font-size:12px; color:#475569;">${a.task_title}</span>
                        </td>
                        <td>${a.project_name || '-'}</td>
                        <td>
                            <span class="badge ${isApproved ? 'badge-success' : 'badge-danger'}" style="font-size:12px;">
                                ${isApproved ? '✅ Approved' : '❌ Revision Requested'}
                            </span>
                        </td>
                        <td>
                            <div style="font-size:13px; color:#334155; max-width:300px; white-space:pre-wrap;">${a.client_feedback || 'No comments'}</div>
                        </td>
                        <td>${a.created_at}</td>
                        <td>
                            <a href="/client/task.php?id=${a.task_id}" class="btn btn-outline btn-sm">
                                <i data-feather="eye"></i> View Task
                            </a>
                        </td>
                    </tr>
                `;
            });
            container.innerHTML = html;
            if (window.feather) feather.replace();
        });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
