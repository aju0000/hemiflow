<?php
// task_details.php - Detailed Task Workspace, SLA Tracker, Comments & Audit Log

require_once __DIR__ . '/auth.php';
checkAuth('task-management');

$user = getCurrentUser();
$db = getDbConnection();
$taskId = intval($_GET['id'] ?? 0);

$canManageObservers = in_array($user['role'], ['Super Admin', 'Admin', 'Team Lead', 'Manager']) || in_array(intval($user['role_id'] ?? 0), [1, 4]);
$allStaffUsers = $canManageObservers ? $db->query("SELECT u.id, u.full_name, u.username, u.department, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 ORDER BY u.full_name ASC")->fetchAll() : [];

$pageTitle = "Task Workspace Details";
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="projects.php" class="btn btn-outline btn-sm">
        <i data-feather="arrow-left"></i> Back to Task Workspace
    </a>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 380px; align-items: start;">

    <!-- Left Main Column: Task Details, Comments & Review History -->
    <div>
        <!-- Task Header & Details Card -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
                <div>
                    <span id="t_code" class="badge badge-primary" style="font-size:13px;">Loading...</span>
                    <h2 id="t_title" style="font-size:22px; font-weight:700; margin-top:8px; color:var(--secondary);">Loading task details...</h2>
                    <p style="color:var(--text-muted); font-size:13px; margin-top:4px;">
                        Hierarchy: <span id="t_hierarchy">Client &rarr; Project</span>
                    </p>
                </div>
                <div style="text-align:right;">
                    <span id="t_status_badge" class="badge badge-warning" style="font-size:13px;">Status</span><br>
                    <span id="t_sla_badge" class="badge badge-danger" style="margin-top:6px; font-size:12px;">SLA Status</span>
                </div>
            </div>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin:20px 0;">
                <h4 style="font-size:14px; font-weight:700; color:#334155; margin-bottom:6px;">Task Description & Technical Requirements</h4>
                <p id="t_description" style="font-size:14px; color:#475569; line-height:1.6; white-space:pre-wrap;">Loading...</p>
            </div>

            <!-- Task Actions & Quick Status Management Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding-top:15px; border-top:1px solid var(--border);">
                <div style="display:flex; align-items:center; gap:8px;">
                    <label style="font-size:13px; font-weight:700; color:#0f172a;">Change Status:</label>
                    <select id="quickStatusSelect" class="form-select" onchange="updateTaskStatusFromDetails(this.value)" style="font-weight:700; max-width:210px;">
                        <option value="PENDING">⏳ PENDING</option>
                        <option value="IN PROGRESS">⚡ IN PROGRESS</option>
                        <option value="UNDER REVIEW">🔍 UNDER REVIEW</option>
                        <option value="REVISION REQUIRED">🔄 REVISION REQUIRED</option>
                        <option value="COMPLETED">✅ COMPLETED</option>
                    </select>
                </div>

                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button id="btnMarkInProgress" class="btn btn-outline btn-sm" onclick="updateTaskStatusFromDetails('IN PROGRESS')" style="color:#0284c7; border-color:#93c5fd;">
                        ⚡ In Progress
                    </button>
                    <button id="btnMarkCompleted" class="btn btn-success btn-sm" onclick="updateTaskStatusFromDetails('COMPLETED')">
                        ✅ Mark Completed
                    </button>
                    <button id="btnSubmitWork" class="btn btn-outline btn-sm" style="display:none;" onclick="openSubmitModal()">
                        <i data-feather="send"></i> Submit Work Notes
                    </button>
                </div>
            </div>
        </div>

        <!-- Task Discussion & Comments Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i data-feather="message-square" style="width:16px; height:16px;"></i> Task Discussion & Attachments</h3>
            </div>

            <div id="commentsList" style="display:flex; flex-direction:column; gap:15px; margin-bottom:20px;">
                <p style="color:var(--text-muted);">Loading comments...</p>
            </div>

            <!-- Add Comment Form -->
            <form onsubmit="event.preventDefault(); submitComment();" style="border-top:1px solid var(--border); padding-top:20px;">
                <div class="form-group">
                    <label class="form-label">Add Comment / Technical Note</label>
                    <textarea id="newCommentText" class="form-control" rows="3" required placeholder="Type a comment or update..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Attachment / Deliverable URL (optional)</label>
                    <input type="url" id="newCommentUrl" class="form-control" placeholder="https://github.com/org/repo or link to deliverable">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-feather="send"></i> Post Comment
                </button>
            </form>
        </div>

        <!-- Team Lead Review Records -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i data-feather="award" style="width:16px; height:16px;"></i> Team Lead Review History</h3>
            </div>
            <div id="reviewsList">
                <p style="color:var(--text-muted);">No review records yet.</p>
            </div>
        </div>
    </div>

    <!-- Right Sidebar Column: Metadata, SLA Timer & Activity Timeline -->
    <div>
        <!-- Task Metadata Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Task Metadata</h3>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px; font-size:13px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <span style="color:var(--text-muted);">Assigned Members:</span>
                    <div id="m_assigned_to" style="text-align:right; font-weight:700;">Loading...</div>
                </div>
                <div style="border-top:1px dashed #e2e8f0; padding-top:10px; margin-top:2px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="color:var(--text-muted); font-weight:600;">Observers (Watchers):</span>
                        <span id="m_observers_count" class="badge" style="background:#fae8ff; color:#a21caf; font-size:10px; font-weight:700;">0 Observers</span>
                    </div>
                    <div id="m_observers" style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px;">None</div>
                    <?php if ($canManageObservers): ?>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <select id="dt_add_observer_select" class="form-select" style="font-size:11.5px; padding:3px 8px; border-color:#d8b4fe;" onchange="handleTaskObserverAdd(this.value)">
                            <option value="">+ Add Observer...</option>
                            <?php foreach ($allStaffUsers as $su): ?>
                                <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['full_name']) ?> (<?= htmlspecialchars($su['role_name'] ?? $su['department']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--text-muted);">Team Lead:</span>
                    <strong id="m_team_lead">Loading...</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--text-muted);">Department:</span>
                    <strong id="m_department">Development</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--text-muted);">Priority:</span>
                    <strong id="m_priority">High</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--text-muted);">SLA Duration:</span>
                    <strong id="m_sla_hours">24 Hours</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--text-muted);">SLA Due Date:</span>
                    <strong id="m_sla_due">Loading...</strong>
                </div>
            </div>
        </div>

        <!-- Activity History Timeline Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i data-feather="activity" style="width:16px; height:16px;"></i> Activity Audit Timeline</h3>
            </div>
            <div id="activitiesTimeline" style="display:flex; flex-direction:column; gap:12px; font-size:12px;">
                <p style="color:var(--text-muted);">Loading activity log...</p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: CLIENT APPROVAL PROCESS -->
<div id="clientApprovalModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Process Client Approval</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('clientApprovalModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitClientApproval();">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Client Approval Decision *</label>
                    <select id="ca_status" class="form-select" required>
                        <option value="Approved">Client Approved (Mark Task Completed)</option>
                        <option value="Revision Requested">Client Requested Revision (Send Back to Developer)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Client Feedback / Remarks</label>
                    <textarea id="ca_feedback" class="form-control" rows="3" placeholder="Enter feedback received from client..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('clientApprovalModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Client Decision</button>
            </div>
        </form>
    </div>
</div>

<script>
    const taskId = <?= $taskId ?>;
    const userRole = <?= json_encode($user['role']) ?>;
    const userId = <?= json_encode($user['id']) ?>;
    const isLeadOrAdminUser = <?= json_encode(isLeadOrAdmin()) ?>;
    const canManageObservers = <?= json_encode($canManageObservers) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        if (taskId > 0) {
            loadTaskDetails();
        }
    });

    function loadTaskDetails() {
        fetch(`api.php?action=get_task_details&task_id=${taskId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert('Error: ' + data.message);
                    window.location.href = 'projects.php';
                    return;
                }
                const t = data.task;

                // Render Header
                document.getElementById('t_code').textContent = t.task_code;
                document.getElementById('t_title').textContent = t.title;
                document.getElementById('t_hierarchy').innerHTML = `${t.company_name} &rarr; <strong>${t.project_name}</strong>`;
                document.getElementById('t_description').textContent = t.description || 'No description provided.';
                
                document.getElementById('t_status_badge').textContent = t.status;
                document.getElementById('t_sla_badge').textContent = t.sla_status;
                document.getElementById('t_sla_badge').className = `badge ${t.sla_status === 'SLA Breached' ? 'badge-danger' : (t.sla_status === 'Near SLA Warning' ? 'badge-warning' : 'badge-primary')}`;

                // Sync Quick Status Selector
                const statusSel = document.getElementById('quickStatusSelect');
                if (statusSel) statusSel.value = t.status;

                // Metadata - Multiple Assignees
                if (data.task.assignees && data.task.assignees.length > 0) {
                    document.getElementById('m_assigned_to').innerHTML = data.task.assignees.map(a => 
                        `<span class="badge" style="background:#dbeafe; color:#1e40af; font-size:11.5px; margin:2px 0 2px 4px; display:inline-block;">👤 ${escapeHtml(a.full_name)}</span>`
                    ).join('');
                } else {
                    document.getElementById('m_assigned_to').textContent = t.assigned_to_name || 'Unassigned';
                }

                // Metadata - Observers
                const obsCountBadge = document.getElementById('m_observers_count');
                const obsList = data.task.observers || [];
                if (obsCountBadge) {
                    obsCountBadge.textContent = `${obsList.length} Observer${obsList.length === 1 ? '' : 's'}`;
                }

                if (obsList.length > 0) {
                    document.getElementById('m_observers').innerHTML = obsList.map(o => `
                        <span class="badge" style="background:#f3e8ff; color:#6b21a8; border:1px solid #d8b4fe; font-size:11px; padding:3px 8px; border-radius:12px; display:inline-flex; align-items:center; gap:5px; margin:2px 0;">
                            <span>👁️ ${escapeHtml(o.full_name)}</span>
                            ${canManageObservers ? `<button type="button" onclick="handleTaskObserverRemove(${o.id})" style="background:none; border:none; color:#7e22ce; font-size:13px; font-weight:700; cursor:pointer; padding:0 2px; line-height:1;" title="Remove observer">&times;</button>` : ''}
                        </span>
                    `).join('');
                } else {
                    document.getElementById('m_observers').innerHTML = '<span style="color:#94a3b8; font-style:italic;">No observers</span>';
                }

                document.getElementById('m_team_lead').textContent = t.team_lead_name || 'Ajmal Team Lead';
                document.getElementById('m_department').textContent = t.department;
                document.getElementById('m_priority').textContent = t.priority;
                document.getElementById('m_sla_hours').textContent = `${t.sla_hours} Hours`;
                document.getElementById('m_sla_due').textContent = t.sla_due_time ? t.sla_due_time.substring(0, 16) : 'N/A';

                // Render Buttons (Allow any assigned employee to submit work)
                const isAssignedDev = t.assigned_to == userId || (data.task.assignees && data.task.assignees.some(a => a.id == userId));
                if (!isLeadOrAdminUser && isAssignedDev && t.status !== 'COMPLETED' && t.status !== 'UNDER REVIEW') {
                    if (document.getElementById('btnSubmitWork')) document.getElementById('btnSubmitWork').style.display = 'inline-flex';
                }

                // Render Comments
                renderComments(data.comments);
                renderReviews(data.reviews);
                renderActivities(data.activities);
            });
    }

    function handleTaskObserverAdd(userId) {
        const uId = parseInt(userId);
        if (!uId) return;
        fetch('api.php?action=update_task_observers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                action: 'add',
                user_id: uId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadTaskDetails();
            } else {
                alert('Error: ' + data.message);
            }
            const sel = document.getElementById('dt_add_observer_select');
            if (sel) sel.value = '';
        })
        .catch(err => {
            alert('Failed to add observer.');
        });
    }

    function handleTaskObserverRemove(userId) {
        const uId = parseInt(userId);
        if (!uId) return;
        if (!confirm('Remove this observer from task?')) return;
        fetch('api.php?action=update_task_observers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                action: 'remove',
                user_id: uId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadTaskDetails();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Failed to remove observer.');
        });
    }

    function renderComments(comments) {
        const area = document.getElementById('commentsList');
        if (!comments || comments.length === 0) {
            area.innerHTML = '<p style="color:var(--text-muted); font-size:13px;">No comments posted yet.</p>';
            return;
        }

        let html = '';
        comments.forEach(c => {
            html += `
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                        <strong style="font-size:13px; color:#0f172a;">${c.user_name} (${c.department})</strong>
                        <span style="font-size:11px; color:#64748b;">${c.created_at}</span>
                    </div>
                    <p style="font-size:13px; color:#334155; margin:0; line-height:1.5; white-space:pre-wrap;">${c.comment_text}</p>
                    ${c.attachment_url ? `<p style="margin-top:6px; font-size:12px;"><a href="${c.attachment_url}" target="_blank" style="color:#2563eb; font-weight:600;">Attachment Deliverable Link</a></p>` : ''}
                </div>
            `;
        });
        area.innerHTML = html;
    }

    function renderReviews(reviews) {
        const area = document.getElementById('reviewsList');
        if (!reviews || reviews.length === 0) {
            area.innerHTML = '<p style="color:var(--text-muted); font-size:13px;">No review records yet.</p>';
            return;
        }

        let html = '';
        reviews.forEach(r => {
            html += `
                <div style="border:1px solid #cbd5e1; border-radius:6px; padding:12px; margin-bottom:10px; background:#f0f9ff;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <strong>Review Action: <span style="color:#0284c7;">${r.review_action}</span></strong>
                        <span style="font-size:11px; color:#64748b;">${r.created_at}</span>
                    </div>
                    <p style="font-size:13px; margin:4px 0;">By: ${r.team_lead_name}</p>
                    <p style="font-size:13px; color:#334155; margin:0;">Remarks: ${r.review_comment}</p>
                    ${r.required_changes ? `<p style="font-size:13px; color:#b91c1c; margin-top:4px;">Revisions Required: ${r.required_changes}</p>` : ''}
                </div>
            `;
        });
        area.innerHTML = html;
    }

    function renderActivities(activities) {
        const area = document.getElementById('activitiesTimeline');
        if (!activities || activities.length === 0) {
            area.innerHTML = '<p style="color:var(--text-muted);">No activity logged.</p>';
            return;
        }

        let html = '';
        activities.forEach(a => {
            html += `
                <div style="border-left:2px solid #2563eb; padding-left:10px; margin-bottom:8px;">
                    <strong style="color:#0f172a;">${a.action}</strong> by ${a.user_name}<br>
                    <span style="color:#64748b; font-size:11px;">${a.created_at}</span>
                    ${a.remarks ? `<div style="color:#475569; margin-top:2px;">${a.remarks}</div>` : ''}
                </div>
            `;
        });
        area.innerHTML = html;
    }

    function submitComment() {
        const text = document.getElementById('newCommentText').value;
        const url = document.getElementById('newCommentUrl').value;

        fetch('api.php?action=add_task_comment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId, comment_text: text, attachment_url: url })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('newCommentText').value = '';
                document.getElementById('newCommentUrl').value = '';
                loadTaskDetails();
            }
        });
    }

    function updateTaskStatusFromDetails(newStatus) {
        if (!newStatus) return;
        fetch('api.php?action=update_task_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId, status: newStatus })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('clientApprovalModal');
                alert(data.message);
                loadTaskDetails();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Failed to update task status.');
        });
    }

    function openSubmitModal() { window.location.href = `projects.php`; }
    function openReviewModal() { window.location.href = `projects.php`; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
