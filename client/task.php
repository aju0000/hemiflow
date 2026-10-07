<?php
// client/task.php - Client Task Details, Visual Stage Timeline & Approval Interface

$taskId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/includes/client_auth.php';
verifyClientOwnership('tasks', $taskId);

$pageTitle = "Task Workspace";
require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="/client/tasks.php" class="btn btn-outline btn-sm">
        <i data-feather="arrow-left"></i> Back to Tasks
    </a>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 380px; align-items: start; gap:20px;">

    <!-- Left Main Column: Task Details, Visual Timeline & Approval Action Box -->
    <div>
        <!-- Task Header Card -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px; flex-wrap:wrap; gap:12px;">
                <div>
                    <span id="t_code" class="badge badge-primary" style="font-size:13px;">Loading...</span>
                    <h2 id="t_title" style="font-size:22px; font-weight:800; color:#0f172a; margin-top:8px;">Loading task details...</h2>
                    <p style="color:#64748b; font-size:13px; margin-top:4px;">
                        Project: <strong id="t_project_name" style="color:#0f172a;">-</strong>
                    </p>
                </div>
                <div>
                    <span id="t_status_badge" class="badge badge-warning" style="font-size:13px;">Status</span>
                </div>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:18px; margin:20px 0;">
                <h4 style="font-size:14px; font-weight:700; color:#334155; margin:0 0 8px 0;">Task Overview & Client Scope</h4>
                <p id="t_description" style="font-size:14px; color:#475569; line-height:1.6; white-space:pre-wrap; margin:0;">Loading requirements...</p>
            </div>
        </div>

        <!-- CLIENT VISUAL TIMELINE CARD -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i data-feather="git-commit" style="width:18px; height:18px;"></i> Visual Work Progress Timeline</h3>
            </div>

            <div id="visualTimelineContainer" style="padding: 15px 10px;">
                <!-- Dynamically rendered timeline stages -->
            </div>
        </div>

        <!-- ACTION REQUIRED APPROVAL BOX (Rendered when client sign-off is needed) -->
        <div id="approvalActionBox" class="card" style="display:none; background: #fffbebf5; border: 2px solid #f59e0b;">
            <div style="display:flex; align-items:flex-start; gap:16px;">
                <div style="background:#fef3c7; color:#d97706; padding:12px; border-radius:50%; flex-shrink:0;">
                    <i data-feather="alert-triangle" style="width:28px; height:28px;"></i>
                </div>
                <div style="flex:1;">
                    <h3 style="margin:0 0 6px 0; font-size:18px; font-weight:800; color:#92400e;">
                        ACTION REQUIRED: Work Ready for Your Approval
                    </h3>
                    <p style="font-size:14px; color:#b45309; line-height:1.5; margin:0 0 18px 0;">
                        Our development team has completed this task deliverable and submitted it for your final review and sign-off. Please review the completed work and choose an action below.
                    </p>

                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <button class="btn btn-success" onclick="openApproveModal()" style="padding:10px 20px; font-weight:700;">
                            <i data-feather="check-circle"></i> APPROVE WORK
                        </button>
                        <button class="btn btn-danger" onclick="openRevisionModal()" style="padding:10px 20px; font-weight:700;">
                            <i data-feather="refresh-cw"></i> REQUEST REVISION
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attachments & Deliverables Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i data-feather="paperclip" style="width:18px; height:18px;"></i> Attachments & Deliverables</h3>
            </div>
            <div id="attachmentsList" style="display:flex; flex-direction:column; gap:10px;">
                <p style="color:var(--text-muted); font-size:13px;">No file attachments uploaded for this task.</p>
            </div>
        </div>
    </div>

    <!-- Right Sidebar Column: Client-Safe Activity Audit History -->
    <div>
        <!-- Summary Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Task Info</h3>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px; font-size:13px;">
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--text-muted);">Department:</span>
                    <strong id="m_department">Development</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--text-muted);">Priority:</span>
                    <strong id="m_priority">Medium</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--text-muted);">Expected Completion:</span>
                    <strong id="m_deadline">-</strong>
                </div>
            </div>
        </div>

        <!-- Client Activity Audit Timeline (Only Client-visible logs) -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i data-feather="clock" style="width:18px; height:18px;"></i> Milestone History</h3>
            </div>
            <div id="clientActivitiesTimeline" style="display:flex; flex-direction:column; gap:12px; font-size:13px;">
                <p style="color:var(--text-muted);">Loading activity history...</p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 1: APPROVE WORK CONFIRMATION -->
<div id="approveModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.7); backdrop-filter:blur(4px); align-items:center; justify-content:center; z-index:9999;">
    <div class="modal-content" style="background:#ffffff; border-radius:12px; max-width:480px; width:90%; padding:25px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.4);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#166534;">✅ Confirm Work Approval</h3>
            <button onclick="closeModal('approveModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <p style="font-size:14px; color:#374151; line-height:1.5;">
            Are you sure you want to approve this work deliverable? This will mark the task as <strong>Completed</strong> and notify our development team.
        </p>
        <div class="form-group" style="margin-top:15px;">
            <label class="form-label" style="font-size:13px; font-weight:600;">Optional Feedback / Approval Comments</label>
            <textarea id="approveComment" class="form-control" rows="3" placeholder="e.g. Looks great! Approved as discussed."></textarea>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
            <button class="btn btn-success" onclick="submitApproval()">Confirm & Approve</button>
        </div>
    </div>
</div>

<!-- MODAL 2: REQUEST REVISION -->
<div id="revisionModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.7); backdrop-filter:blur(4px); align-items:center; justify-content:center; z-index:9999;">
    <div class="modal-content" style="background:#ffffff; border-radius:12px; max-width:500px; width:90%; padding:25px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.4);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#991b1b;">❌ Request Work Revision</h3>
            <button onclick="closeModal('revisionModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <p style="font-size:14px; color:#374151; line-height:1.5;">
            Please describe the specific changes, modifications, or fixes you require. Our development team lead will be alerted immediately.
        </p>
        <div class="form-group" style="margin-top:15px;">
            <label class="form-label" style="font-size:13px; font-weight:600; color:#b91c1c;">Required Changes Description *</label>
            <textarea id="revisionComment" class="form-control" rows="4" required placeholder="Describe what needs to be changed..."></textarea>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
            <button class="btn btn-secondary" onclick="closeModal('revisionModal')">Cancel</button>
            <button class="btn btn-danger" onclick="submitRevision()">Submit Revision Request</button>
        </div>
    </div>
</div>

<script>
const taskId = <?= $taskId ?>;

document.addEventListener('DOMContentLoaded', () => {
    loadTaskWorkspace();
});

function loadTaskWorkspace() {
    fetch(`/client/api.php?action=get_task_details&task_id=${taskId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Error: ' + data.message);
                window.location.href = '/client/tasks.php';
                return;
            }
            const t = data.task;

            document.getElementById('t_code').textContent = t.task_code;
            document.getElementById('t_title').textContent = t.title;
            document.getElementById('t_project_name').textContent = t.project_name;
            document.getElementById('t_description').textContent = t.description || 'No detailed specifications added.';

            const st = getClientStatusLabel(t.status);
            document.getElementById('t_status_badge').textContent = st.text;
            document.getElementById('t_status_badge').className = `badge ${st.class}`;

            document.getElementById('m_department').textContent = t.department;
            document.getElementById('m_priority').textContent = t.priority;
            document.getElementById('m_deadline').textContent = t.deadline ? t.deadline.substring(0, 10) : 'N/A';

            // Show Action Required Box if task status is SENT FOR CLIENT APPROVAL
            if (t.status === 'SENT FOR CLIENT APPROVAL') {
                document.getElementById('approvalActionBox').style.display = 'block';
            } else {
                document.getElementById('approvalActionBox').style.display = 'none';
            }

            // Render Visual Timeline
            renderVisualTimeline(t.status);

            // Render Activities Timeline
            renderClientActivities(data.activities);

            // Render Attachments
            renderAttachments(data.attachments);
        });
}

function renderVisualTimeline(status) {
    const container = document.getElementById('visualTimelineContainer');
    
    // Stages mapping
    const stages = [
        { name: 'Requirement Received', status: 'COMPLETED' },
        { name: 'Assigned to Development', status: 'COMPLETED' },
        { name: 'Development Started', status: 'COMPLETED' },
        { name: 'Under Review', status: 'UNDER REVIEW' },
        { name: 'Client Approval', status: 'SENT FOR CLIENT APPROVAL' },
        { name: 'Completed', status: 'COMPLETED' }
    ];

    let activeIndex = 0;
    if (status === 'PENDING') activeIndex = 1;
    else if (status === 'IN PROGRESS') activeIndex = 2;
    else if (status === 'UNDER REVIEW') activeIndex = 3;
    else if (status === 'SENT FOR CLIENT APPROVAL') activeIndex = 4;
    else if (status === 'REVISION REQUIRED' || status === 'CLIENT REVISION REQUESTED') activeIndex = 2;
    else if (status === 'COMPLETED') activeIndex = 5;

    let html = '<div style="display:flex; flex-direction:column; gap:16px;">';
    stages.forEach((s, idx) => {
        let isDone = idx < activeIndex || (status === 'COMPLETED' && idx <= 5);
        let isCurrent = idx === activeIndex && status !== 'COMPLETED';
        let icon = isDone ? '✓' : (isCurrent ? '●' : '○');
        let color = isDone ? '#16a34a' : (isCurrent ? '#2563eb' : '#94a3b8');
        let bg = isDone ? '#dcfce7' : (isCurrent ? '#dbeafe' : '#f1f5f9');

        html += `
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:32px; height:32px; border-radius:50%; background:${bg}; color:${color}; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; flex-shrink:0;">
                    ${icon}
                </div>
                <div style="flex:1;">
                    <div style="font-size:14px; font-weight:700; color:${isDone || isCurrent ? '#0f172a' : '#94a3b8'};">
                        ${s.name}
                    </div>
                    <div style="font-size:12px; color:#64748b;">
                        ${isDone ? 'Completed' : (isCurrent ? 'Current Stage' : 'Pending')}
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

function renderClientActivities(activities) {
    const container = document.getElementById('clientActivitiesTimeline');
    if (!activities || activities.length === 0) {
        container.innerHTML = '<p style="color:var(--text-muted); font-size:13px;">No public milestones logged yet.</p>';
        return;
    }

    let html = '';
    activities.forEach(a => {
        html += `
            <div style="border-left:3px solid #2563eb; padding-left:10px; margin-bottom:10px;">
                <div style="font-size:12px; color:#64748b;">${a.created_at}</div>
                <div style="font-size:13px; font-weight:700; color:#0f172a; margin-top:2px;">${a.action}</div>
                <div style="font-size:12px; color:#475569; margin-top:2px;">${a.remarks || ''}</div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function renderAttachments(attachments) {
    const container = document.getElementById('attachmentsList');
    if (!attachments || attachments.length === 0) {
        container.innerHTML = '<p style="color:var(--text-muted); font-size:13px;">No attachments available.</p>';
        return;
    }

    let html = '';
    attachments.forEach(att => {
        html += `
            <div style="border:1px solid #cbd5e1; border-radius:6px; padding:10px 14px; background:#ffffff; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <i data-feather="file" style="color:#2563eb;"></i>
                    <strong style="font-size:13px; color:#0f172a;">${att.file_name}</strong>
                </div>
                <a href="/client/download.php?id=${att.id}&type=task_attachment" class="btn btn-outline btn-sm">
                    <i data-feather="download"></i> Download
                </a>
            </div>
        `;
    });
    container.innerHTML = html;
    if (window.feather) feather.replace();
}

function getClientStatusLabel(status) {
    switch (status) {
        case 'PENDING': return { text: 'Waiting to Start', class: 'badge-secondary' };
        case 'IN PROGRESS': return { text: 'Work in Progress', class: 'badge-primary' };
        case 'UNDER REVIEW': return { text: 'Being Reviewed', class: 'badge-info' };
        case 'SENT FOR CLIENT APPROVAL': return { text: 'Action Required — Your Approval', class: 'badge-warning' };
        case 'REVISION REQUIRED':
        case 'CLIENT REVISION REQUESTED': return { text: 'Changes Requested', class: 'badge-danger' };
        case 'COMPLETED': return { text: 'Completed', class: 'badge-success' };
        default: return { text: status, class: 'badge-secondary' };
    }
}

function openApproveModal() { document.getElementById('approveModal').style.display = 'flex'; }
function openRevisionModal() { document.getElementById('revisionModal').style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function submitApproval() {
    const comment = document.getElementById('approveComment').value;
    fetch('/client/api.php?action=client_approve_task', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: taskId, comment: comment })
    })
    .then(res => res.json())
    .then(data => {
        closeModal('approveModal');
        if (data.success) {
            alert('✅ ' + data.message);
            loadTaskWorkspace();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function submitRevision() {
    const comment = document.getElementById('revisionComment').value;
    if (!comment.trim()) {
        alert('Please describe the changes required.');
        return;
    }

    fetch('/client/api.php?action=client_request_revision', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: taskId, comment: comment })
    })
    .then(res => res.json())
    .then(data => {
        closeModal('revisionModal');
        if (data.success) {
            alert('✅ ' + data.message);
            loadTaskWorkspace();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
