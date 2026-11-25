<?php
// task_details.php - Detailed Task Workspace, SLA Tracker, Comments & Audit Log

require_once __DIR__ . '/auth.php';
checkAuth('task-management');

$user = getCurrentUser();
$db = getDbConnection();
$taskId = intval($_GET['id'] ?? 0);

$taskProjId = 0;
if ($taskId > 0) {
    $tpStmt = $db->prepare("SELECT project_id FROM tasks WHERE id = ?");
    $tpStmt->execute([$taskId]);
    $taskProjId = intval($tpStmt->fetchColumn() ?: 0);
}
$canManageThisTask = ($taskProjId > 0) ? canManageProject($db, $taskProjId, $user['id']) : isLeadOrAdmin();
$canManageObservers = $canManageThisTask;
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

            <!-- Task Approval Workflow Chain Banner -->
            <div id="t_workflow_bar" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; margin-bottom:14px; font-size:12px;">
                <span style="font-weight:700; color:#475569;">Approval Flow:</span>
                <span id="wf_step_work" class="badge badge-secondary">1. Employee Work</span>
                <span style="color:#94a3b8;">&rarr;</span>
                <span id="wf_step_handler" class="badge badge-secondary">2. Project Handler Approval</span>
                <span style="color:#94a3b8;">&rarr;</span>
                <span id="wf_step_client" class="badge badge-secondary">3. Client Approved Tick &rarr; Completed</span>
                <span id="wf_step_lead" class="badge badge-secondary">3. Lead Approval</span>
                <span style="color:#94a3b8;">&rarr;</span>
                <span id="wf_step_client" class="badge badge-secondary">4. Client Approved &rarr; Completed</span>
            </div>

            <!-- Task Actions & Quick Status Management Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding-top:15px; border-top:1px solid var(--border);">
                <div style="display:flex; align-items:center; gap:8px;">
                    <label style="font-size:13px; font-weight:700; color:#0f172a;">Change Status:</label>
                    <select id="quickStatusSelect" class="form-select" onchange="updateTaskStatusFromDetails(this.value)" style="font-weight:700; max-width:250px;">
                        <option value="PENDING">⏳ PENDING</option>
                        <option value="IN PROGRESS">⚡ IN PROGRESS</option>
                        <option value="UNDER REVIEW">🔍 UNDER REVIEW</option>
                        <option value="PENDING LEAD APPROVAL">📝 PENDING LEAD APPROVAL</option>
                        <option value="REVISION REQUIRED">🔄 REVISION REQUIRED</option>
                        <option value="SENT FOR CLIENT APPROVAL">📩 SENT FOR CLIENT APPROVAL</option>
                        <option value="COMPLETED">✅ COMPLETED</option>
                    </select>
                </div>

                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <button id="btnMarkInProgress" class="btn btn-outline btn-sm" onclick="updateTaskStatusFromDetails('IN PROGRESS')" style="color:#0284c7; border-color:#93c5fd;">
                        ⚡ In Progress
                    </button>
                    <?php if ($canManageThisTask): ?>
                        <button id="btnHandlerApprove" class="btn btn-primary btn-sm" style="display:none; background:#0f766e; border-color:#0f766e;" onclick="handlerApproveTaskDetails()">
                            ✓ Handler Approve
                        </button>
                        <?php if (isLeadOrAdmin()): ?>
                        <button id="btnLeadApprove" class="btn btn-primary btn-sm" style="display:none; background:#15803d; border-color:#15803d;" onclick="leadApproveTaskDetails()">
                            🛡️ Lead Approve for Completion
                        </button>
                        <?php endif; ?>
                        <span id="tdAwaitingLeadBadge" class="badge badge-warning" style="display:none; font-size:11.5px; padding:6px 10px;">
                            ⏳ Awaiting Lead Approval to Complete
                        </span>
                        <button id="btnRequestRevision" class="btn btn-outline btn-sm" style="display:none; color:#b91c1c; border-color:#fca5a5;" onclick="handlerRequestRevisionDetails()">
                            🔄 Request Revision
                        </button>
                        <label id="tdClientApprovedWrap" style="display:none; align-items:center; gap:6px; background:#f0fdf4; border:1px solid #86efac; padding:5px 10px; border-radius:8px; font-size:12px; font-weight:700; color:#15803d; cursor:pointer;">
                            <input type="checkbox" id="td_client_approved_chk" onchange="toggleTaskDetailsClientApproval(this.checked)" style="width:15px; height:15px; cursor:pointer;">
                            Approved by Client
                        </label>
                    <?php endif; ?>
                    <button id="btnSubmitWork" class="btn btn-success btn-sm" style="display:none;" onclick="openSubmitModal()">
                        <i data-feather="send"></i> Submit for Handler Approval
                    </button>
                </div>
            </div>
        </div>

        <!-- Task Media & Work Attachments Card -->
        <div class="card" id="tdMediaCard">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <h3 class="card-title"><i data-feather="paperclip" style="width:16px; height:16px;"></i> Task Media &amp; Work Attachments</h3>
                <span class="badge badge-primary" id="tdMediaCountBadge">0 Files</span>
            </div>
            <form onsubmit="event.preventDefault(); uploadTdTaskMedia();" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:12px; margin-bottom:14px;">
                <input type="file" id="td_media_file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.mp4,.mov,.webm,.mp3" class="form-control" style="max-width:280px; font-size:12.5px; padding:6px;">
                <input type="text" id="td_media_caption" class="form-control" placeholder="Optional media note / caption..." style="flex:1; min-width:180px; font-size:13px;">
                <button type="submit" id="btnUploadTdMedia" class="btn btn-primary btn-sm" style="font-weight:700;">📎 Attach Media</button>
            </form>
            <div id="tdMediaList" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(210px, 1fr)); gap:12px;">
                <p style="color:var(--text-muted); font-size:13px;">No media attached yet.</p>
            </div>
        </div>

        <!-- Sub-Tasks Card -->
        <div class="card" id="tdSubtasksCard" style="display:none;">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h3 class="card-title"><i data-feather="git-branch" style="width:16px; height:16px;"></i> Sub-Tasks</h3>
                <?php if ($canManageThisTask): ?>
                    <button type="button" id="btnAddChildSubtask" class="btn btn-outline btn-sm" style="color:#7c3aed; border-color:#ddd6fe;" onclick="openTdAddSubtaskModal()">+ Add Sub-Task</button>
                <?php endif; ?>
            </div>
            <div id="tdSubtasksList" style="display:flex; flex-direction:column; gap:10px;">
                <p style="color:var(--text-muted); font-size:13px;">No sub-tasks yet.</p>
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

<!-- MODAL: SUBMIT WORK FOR HANDLER APPROVAL -->
<div id="tdSubmitWorkModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:520px; width:95%;">
        <div class="modal-header">
            <h3 class="modal-title">Submit Work for Project Handler Approval</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('tdSubmitWorkModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitWorkFromDetails();">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Completion Notes *</label>
                    <textarea id="td_sw_notes" class="form-control" rows="3" required placeholder="Describe completed work for Project Handler review..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Deliverable / PR / Doc Link</label>
                    <input type="text" id="td_sw_url" class="form-control" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label class="form-label">Attach Media / Screenshots / Files (Optional)</label>
                    <input type="file" id="td_sw_files" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.mp4,.mov,.webm,.mp3" class="form-control" style="padding:6px; font-size:12.5px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('tdSubmitWorkModal')">Cancel</button>
                <button type="submit" class="btn btn-success">📤 Submit for Handler Approval</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD SUB-TASK (Project Handler / Lead) -->
<div id="tdAddSubtaskModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:540px; width:95%;">
        <div class="modal-header">
            <h3 class="modal-title">Create Sub-Task Under Current Task</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('tdAddSubtaskModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitTdAddSubtask();">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Sub-Task Title *</label>
                    <input type="text" id="td_sub_title" class="form-control" required placeholder="e.g. Build responsive header component">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="td_sub_desc" class="form-control" rows="3" placeholder="Sub-task instructions..."></textarea>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Assign To *</label>
                        <select id="td_sub_assignee" class="form-select" required>
                            <?php foreach ($allStaffUsers as $su): ?>
                                <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['full_name']) ?> (<?= htmlspecialchars($su['role_name'] ?? $su['department']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="td_sub_priority" class="form-select">
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('tdAddSubtaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Sub-Task</button>
            </div>
        </form>
    </div>
</div>

<script>
    const taskId = <?= $taskId ?>;
    const userRole = <?= json_encode($user['role']) ?>;
    const userId = <?= json_encode($user['id']) ?>;
    const isLeadOrAdminUser = <?= json_encode(isLeadOrAdmin()) ?>;
    const canManageThisTask = <?= json_encode($canManageThisTask) ?>;
    const canManageObservers = <?= json_encode($canManageObservers) ?>;
    let currentTaskObj = null;

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
                currentTaskObj = t;

                // Render Header
                const isSubtask = Boolean(t.is_subtask || t.task_type === 'subtask' || parseInt(t.parent_task_id || 0) > 0);
                document.getElementById('t_code').textContent = (isSubtask ? 'SUB-TASK • ' : '') + t.task_code;
                document.getElementById('t_title').textContent = t.title;
                let hierHtml = `${escapeHtml(t.company_name)} &rarr; <a href="project_details.php?id=${t.project_id}" style="color:#1d4ed8; font-weight:700; text-decoration:none;">${escapeHtml(t.project_name)}</a>`;
                if (t.parent_task_id && t.parent_task_title) {
                    hierHtml += ` &rarr; <a href="task_details.php?id=${t.parent_task_id}" style="color:#6d28d9; font-weight:700;">#${escapeHtml(t.parent_task_code || '')} ${escapeHtml(t.parent_task_title)}</a>`;
                }
                document.getElementById('t_hierarchy').innerHTML = hierHtml;
                document.getElementById('t_description').textContent = t.description || 'No description provided.';
                
                document.getElementById('t_status_badge').textContent = t.status;
                document.getElementById('t_sla_badge').textContent = t.sla_status;
                document.getElementById('t_sla_badge').className = `badge ${t.sla_status === 'SLA Breached' ? 'badge-danger' : (t.sla_status === 'Near SLA Warning' ? 'badge-warning' : 'badge-primary')}`;

                // Workflow chain indicator
                const handlerDone = parseInt(t.handler_approved || 0) === 1 || ['PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL', 'COMPLETED'].includes(t.status);
                const leadDone = parseInt(t.lead_approved || 0) === 1 || ['LEAD APPROVED', 'COMPLETED'].includes(t.status) || (t.status === 'SENT FOR CLIENT APPROVAL' && parseInt(t.lead_approved || 0) === 1);
                const clientDone = parseInt(t.client_approved_by_handler || 0) === 1 || t.status === 'COMPLETED';
                const sWork = document.getElementById('wf_step_work');
                const sHandler = document.getElementById('wf_step_handler');
                const sLead = document.getElementById('wf_step_lead');
                const sClient = document.getElementById('wf_step_client');
                if (sWork) sWork.className = `badge ${(t.status !== 'PENDING') ? 'badge-primary' : 'badge-secondary'}`;
                if (sHandler) sHandler.className = `badge ${handlerDone ? 'badge-success' : (t.status === 'UNDER REVIEW' ? 'badge-warning' : 'badge-secondary')}`;
                if (sLead) sLead.className = `badge ${leadDone ? 'badge-success' : (t.status === 'PENDING LEAD APPROVAL' ? 'badge-warning' : 'badge-secondary')}`;
                if (sClient) sClient.className = `badge ${clientDone ? 'badge-success' : (leadDone ? 'badge-warning' : 'badge-secondary')}`;

                // Sync Quick Status Selector
                const statusSel = document.getElementById('quickStatusSelect');
                if (statusSel) {
                    if (!canManageThisTask && ['PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL', 'COMPLETED', 'REVISION REQUIRED'].includes(t.status)) {
                        if (!Array.from(statusSel.options).some(o => o.value === t.status)) {
                            const ro = document.createElement('option');
                            ro.value = t.status;
                            ro.textContent = t.status;
                            statusSel.appendChild(ro);
                        }
                    }
                    statusSel.value = t.status;
                    statusSel.disabled = (!canManageThisTask && ['PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL', 'COMPLETED'].includes(t.status));
                }

                // Manager / Handler / Lead approval controls
                const btnApprove = document.getElementById('btnHandlerApprove');
                const btnLeadApp = document.getElementById('btnLeadApprove');
                const awaitLeadBadge = document.getElementById('tdAwaitingLeadBadge');
                const btnRev = document.getElementById('btnRequestRevision');
                const clientWrap = document.getElementById('tdClientApprovedWrap');
                const clientChk = document.getElementById('td_client_approved_chk');
                if (canManageThisTask) {
                    if (btnApprove) btnApprove.style.display = (!handlerDone && t.status !== 'COMPLETED') ? 'inline-flex' : 'none';
                    if (btnLeadApp) btnLeadApp.style.display = (isLeadOrAdminUser && !leadDone && t.status !== 'COMPLETED') ? 'inline-flex' : 'none';
                    if (awaitLeadBadge) awaitLeadBadge.style.display = (!isLeadOrAdminUser && handlerDone && !leadDone && t.status !== 'COMPLETED') ? 'inline-flex' : 'none';
                    if (btnRev) btnRev.style.display = (['UNDER REVIEW', 'PENDING LEAD APPROVAL', 'SENT FOR CLIENT APPROVAL'].includes(t.status)) ? 'inline-flex' : 'none';
                    if (clientWrap) clientWrap.style.display = (leadDone || (isLeadOrAdminUser && handlerDone)) ? 'inline-flex' : 'none';
                    if (clientChk) clientChk.checked = clientDone;
                }

                // Sub-tasks Card
                const subCard = document.getElementById('tdSubtasksCard');
                const subList = document.getElementById('tdSubtasksList');
                const subtasks = Array.isArray(data.subtasks) ? data.subtasks : [];
                if (subCard && subList) {
                    if (!isSubtask || subtasks.length > 0) {
                        subCard.style.display = 'block';
                        if (subtasks.length === 0) {
                            subList.innerHTML = '<p style="color:var(--text-muted); font-size:13px;">No sub-tasks created yet.</p>';
                        } else {
                            subList.innerHTML = subtasks.map(st => {
                                const stHandlerDone = parseInt(st.handler_approved || 0) === 1 || ['PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL', 'COMPLETED'].includes(st.status);
                                const stLeadDone = parseInt(st.lead_approved || 0) === 1 || ['LEAD APPROVED', 'COMPLETED'].includes(st.status) || (st.status === 'SENT FOR CLIENT APPROVAL' && parseInt(st.lead_approved || 0) === 1);
                                const stClientDone = parseInt(st.client_approved_by_handler || 0) === 1 || st.status === 'COMPLETED';
                                return `
                                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; background:#faf5ff; border:1px solid #ddd6fe; border-radius:8px; padding:10px 12px;">
                                        <div>
                                            <a href="task_details.php?id=${st.id}" style="font-weight:700; color:#5b21b6; text-decoration:none;">
                                                ${escapeHtml(st.task_code)} &mdash; ${escapeHtml(st.title)}
                                            </a>
                                            <div style="font-size:11.5px; color:#64748b; margin-top:2px;">
                                                Assignee: <strong>${escapeHtml(st.assigned_to_name || 'Unassigned')}</strong> &bull; Status: <strong>${escapeHtml(st.status)}</strong>
                                            </div>
                                        </div>
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            ${(canManageThisTask && (stLeadDone || (isLeadOrAdminUser && stHandlerDone))) ? `
                                                <label style="display:inline-flex; align-items:center; gap:4px; font-size:11.5px; font-weight:700; color:${stClientDone ? '#15803d' : '#1d4ed8'}; cursor:pointer;">
                                                    <input type="checkbox" ${stClientDone ? 'checked' : ''} onchange="toggleSubtaskClientApproval(${st.id}, this.checked)">
                                                    Approved by Client
                                                </label>
                                            ` : (canManageThisTask && stHandlerDone && !stLeadDone ? `<span class="badge badge-warning" style="font-size:10px;">⏳ Awaiting Lead Approval</span>` : '')}
                                            <a href="task_details.php?id=${st.id}" class="btn btn-outline btn-sm">Open</a>
                                        </div>
                                    </div>
                                `;
                            }).join('');
                        }
                    }
                    const btnAddSub = document.getElementById('btnAddChildSubtask');
                    if (btnAddSub) btnAddSub.style.display = isSubtask ? 'none' : 'inline-flex';
                }

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

                // Render Buttons (Allow any assigned employee to submit work for Handler approval)
                const isAssignedDev = t.assigned_to == userId || (data.task.assignees && data.task.assignees.some(a => a.id == userId));
                if (document.getElementById('btnSubmitWork')) {
                    const showSubmit = (!canManageThisTask || isAssignedDev) && !['COMPLETED', 'SENT FOR CLIENT APPROVAL', 'UNDER REVIEW'].includes(t.status);
                    document.getElementById('btnSubmitWork').style.display = showSubmit ? 'inline-flex' : 'none';
                }

                // Render Comments
                // Render Media & Comments
                renderTaskMedia(data.attachments || []);
                renderComments(data.comments);
                renderReviews(data.reviews);
                renderActivities(data.activities);
            });
    }

    function renderTaskMedia(attachments) {
        const list = document.getElementById('tdMediaList');
        const badge = document.getElementById('tdMediaCountBadge');
        if (badge) badge.textContent = `${attachments.length} File${attachments.length === 1 ? '' : 's'}`;
        if (!list) return;
        if (!attachments || attachments.length === 0) {
            list.innerHTML = '<p style="color:var(--text-muted); font-size:13px;">No media attached yet. Upload images, videos, or deliverables while working on this task.</p>';
            return;
        }
        list.innerHTML = attachments.map(a => {
            const isImg = (a.mime_type || '').startsWith('image/') || /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(a.file_name || '');
            const isVid = (a.mime_type || '').startsWith('video/') || /\.(mp4|mov|webm)$/i.test(a.file_name || '');
            const safeUrl = escapeHtml(a.file_path);
            const canDel = parseInt(a.user_id) === parseInt(userId) || canManageThisTask;
            const kb = Math.max(1, Math.round((parseInt(a.file_size || 0)) / 1024));
            let previewHtml = `<div style="height:110px; display:flex; align-items:center; justify-content:center; background:#f1f5f9; border-radius:6px; font-size:30px;">📄</div>`;
            if (isImg) {
                previewHtml = `<a href="${safeUrl}" target="_blank"><img src="${safeUrl}" alt="${escapeHtml(a.file_name)}" style="width:100%; height:110px; object-fit:cover; border-radius:6px; display:block;"></a>`;
            } else if (isVid) {
                previewHtml = `<video src="${safeUrl}" controls style="width:100%; height:110px; border-radius:6px; background:#000;"></video>`;
            }
            return `
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:10px; background:#fff; display:flex; flex-direction:column; gap:6px;">
                    ${previewHtml}
                    <div style="font-size:12.5px; font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(a.file_name)}">
                        ${escapeHtml(a.file_name)}
                    </div>
                    ${a.caption ? `<div style="font-size:11.5px; color:#475569;">${escapeHtml(a.caption)}</div>` : ''}
                    <div style="font-size:10.5px; color:#64748b; display:flex; justify-content:space-between; align-items:center;">
                        <span>${escapeHtml(a.uploaded_by_name || 'Staff')} &bull; ${kb} KB</span>
                        <span class="badge badge-secondary" style="font-size:9.5px;">${escapeHtml(a.attachment_stage || 'work')}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:2px;">
                        <a href="${safeUrl}" target="_blank" download class="btn btn-outline btn-sm" style="font-size:11px; padding:3px 8px;">⬇ Open</a>
                        ${canDel ? `<button type="button" class="btn btn-outline btn-sm" onclick="deleteTdTaskMedia(${a.id})" style="font-size:11px; padding:3px 8px; color:#dc2626; border-color:#fecaca;">🗑️</button>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    function uploadTdTaskMedia() {
        const input = document.getElementById('td_media_file');
        const captionEl = document.getElementById('td_media_caption');
        if (!input || !input.files || input.files.length === 0) {
            alert('Please choose at least one media file to attach.');
            return;
        }
        const fd = new FormData();
        fd.append('task_id', taskId);
        fd.append('caption', captionEl ? captionEl.value.trim() : '');
        fd.append('attachment_stage', 'work');
        for (let i = 0; i < input.files.length; i++) {
            fd.append('files[]', input.files[i]);
        }
        const btn = document.getElementById('btnUploadTdMedia');
        if (btn) btn.disabled = true;
        fetch('api.php?action=upload_task_media', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (btn) btn.disabled = false;
                if (data.success) {
                    input.value = '';
                    if (captionEl) captionEl.value = '';
                    loadTaskDetails();
                } else {
                    alert('Error: ' + (data.message || 'Upload failed'));
                }
            })
            .catch(() => {
                if (btn) btn.disabled = false;
                alert('Network error uploading media.');
            });
    }

    function deleteTdTaskMedia(attId) {
        if (!confirm('Remove this media attachment?')) return;
        fetch('api.php?action=delete_task_media', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attachment_id: attId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) loadTaskDetails();
            else alert('Error: ' + data.message);
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
                loadTaskDetails();
            }
        })
        .catch(err => {
            alert('Failed to update task status.');
            loadTaskDetails();
        });
    }

    function openSubmitModal() {
        document.getElementById('td_sw_notes').value = '';
        document.getElementById('td_sw_url').value = '';
        const swFiles = document.getElementById('td_sw_files');
        if (swFiles) swFiles.value = '';
        document.getElementById('tdSubmitWorkModal').style.display = 'flex';
    }

    async function submitWorkFromDetails() {
        const swFiles = document.getElementById('td_sw_files');
        if (swFiles && swFiles.files && swFiles.files.length > 0) {
            const fd = new FormData();
            fd.append('task_id', taskId);
            fd.append('caption', 'Work submission attachment');
            fd.append('attachment_stage', 'submission');
            for (let i = 0; i < swFiles.files.length; i++) {
                fd.append('files[]', swFiles.files[i]);
            }
            try {
                const upRes = await fetch('api.php?action=upload_task_media', { method: 'POST', body: fd });
                const upData = await upRes.json();
                if (!upData.success) {
                    alert('Media Upload Error: ' + (upData.message || 'Could not upload file(s).'));
                    return;
                }
            } catch (e) {
                alert('Network error uploading media.');
                return;
            }
        }
        fetch('api.php?action=submit_developer_work', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                submission_notes: document.getElementById('td_sw_notes').value,
                attachment_url: document.getElementById('td_sw_url').value
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('tdSubmitWorkModal');
                alert('✅ ' + data.message);
                loadTaskDetails();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Network error submitting work.'));
    }

    function handlerApproveTaskDetails() {
        const comment = prompt('Project Handler approval note:', 'Approved by Project Handler');
        if (comment === null) return;
        fetch('api.php?action=review_task_work', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                review_action: 'APPROVE',
                review_comment: comment || 'Approved by Project Handler'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                loadTaskDetails();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function leadApproveTaskDetails() {
        const comment = prompt('Lead approval note (clears Project Handler to complete task):', 'Approved by Lead for completion');
        if (comment === null) return;
        fetch('api.php?action=lead_approve_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                review_action: 'APPROVE',
                comment: comment || 'Approved by Lead'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                loadTaskDetails();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function handlerRequestRevisionDetails() {
        const changes = prompt('Specify required changes to send back to the employee:');
        if (!changes) return;
        fetch('api.php?action=review_task_work', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: taskId,
                review_action: 'REQUEST_REVISION',
                review_comment: changes,
                required_changes: changes
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('🔄 ' + data.message);
                loadTaskDetails();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function toggleTaskDetailsClientApproval(approved) {
        toggleSubtaskClientApproval(taskId, approved);
    }

    function toggleSubtaskClientApproval(targetTaskId, approved) {
        fetch('api.php?action=toggle_task_client_approval', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: targetTaskId, approved: approved ? 1 : 0 })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadTaskDetails();
            } else {
                alert('Error: ' + (data.message || 'Could not update client approval'));
                loadTaskDetails();
            }
        })
        .catch(() => {
            alert('Network error updating client approval.');
            loadTaskDetails();
        });
    }

    function openTdAddSubtaskModal() {
        document.getElementById('td_sub_title').value = '';
        document.getElementById('td_sub_desc').value = '';
        document.getElementById('tdAddSubtaskModal').style.display = 'flex';
    }

    function submitTdAddSubtask() {
        if (!currentTaskObj) return;
        const assigneeId = parseInt(document.getElementById('td_sub_assignee').value) || 0;
        fetch('api.php?action=create_subtask', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                project_id: currentTaskObj.project_id,
                parent_task_id: taskId,
                is_subtask: 1,
                title: document.getElementById('td_sub_title').value,
                description: document.getElementById('td_sub_desc').value,
                department: currentTaskObj.department || 'Development',
                assigned_to: assigneeId,
                assignees: assigneeId > 0 ? [assigneeId] : [],
                priority: document.getElementById('td_sub_priority').value,
                sla_hours: currentTaskObj.sla_hours || 24
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('tdAddSubtaskModal');
                alert('✅ ' + data.message);
                loadTaskDetails();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Network error creating sub-task.'));
    }

    function openReviewModal() { handlerApproveTaskDetails(); }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
