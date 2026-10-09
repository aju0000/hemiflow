<?php
// projects.php - Module 4 Project & Task Management Engine

require_once __DIR__ . '/auth.php';
checkAuth('task-management');

$user = getCurrentUser();
$db = getDbConnection();

$pageTitle = "Module 4 — Project & Task Management Hub";
require_once __DIR__ . '/header.php';

// Fetch developers and staff for task assignment
$developers = $db->query("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 ORDER BY u.full_name ASC")->fetchAll();

// Fetch clients & active projects
$clients = $db->query("SELECT * FROM clients ORDER BY company_name ASC")->fetchAll();
$projects = $db->query("SELECT p.*, c.company_name, u.full_name as team_lead_name FROM projects p JOIN clients c ON p.client_id = c.id LEFT JOIN users u ON p.team_lead_id = u.id ORDER BY p.id DESC")->fetchAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
    <div>
        <h2 style="font-size:20px; font-weight:700;">
            <?= $user['role'] === 'Developer' ? ' My Assigned Tasks Workspace' : 'Team Lead Project & Task Management Hub' ?>
        </h2>
        <p style="color:var(--text-muted); font-size:14px;">
            <?= $user['role'] === 'Developer' 
                ? 'Manage assigned development tasks, submit completed work for Team Lead review, and respond to revisions.' 
                : 'Assign tasks to developers, enforce server-side SLAs, review submitted code, and manage client approvals.' ?>
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <?php if ($user['role'] !== 'Developer'): ?>
            <button class="btn btn-primary" onclick="openCreateTaskModal()">
                <i data-feather="plus-circle"></i> + Create & Assign Task
            </button>
            <button class="btn btn-outline" onclick="openCreateProjectModal()">
                <i data-feather="folder-plus"></i> + New Project
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Metrics Cards Bar -->
<div class="grid-4" style="margin-bottom: 24px;" id="metricsCardsArea">
    <!-- Loaded dynamically via JS -->
</div>

<!-- Filter Bar -->
<div class="card" style="padding:16px; margin-bottom:20px;">
    <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
        <div style="flex:1; min-width:200px;">
            <select id="filterProject" class="form-select" onchange="loadTasks()">
                <option value="0">All Projects</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['company_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex:1; min-width:180px;">
            <select id="filterStatus" class="form-select" onchange="loadTasks()">
                <option value="">All Task Statuses</option>
                <option value="PENDING">Pending</option>
                <option value="IN PROGRESS">In Progress</option>
                <option value="UNDER REVIEW">Under Review</option>
                <option value="REVISION REQUIRED">Revision Required</option>
                <option value="COMPLETED">Completed</option>
            </select>
        </div>

        <?php if ($user['role'] !== 'Developer'): ?>
            <div style="flex:1; min-width:180px;">
                <select id="filterDeveloper" class="form-select" onchange="loadTasks()">
                    <option value="0">All Developers</option>
                    <?php foreach ($developers as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <button class="btn btn-outline btn-sm" onclick="loadTasks()">
            <i data-feather="refresh-cw"></i> Refresh
        </button>
    </div>
</div>

<!-- DEVELOPER KANBAN BOARD / TEAM LEAD TASK TABLE -->
<?php if ($user['role'] === 'Developer'): ?>

    <!-- Developer Column Workflow Layout -->
    <div class="grid-4" style="align-items:start;">
        <!-- Column 1: Pending -->
        <div class="card" style="background:#f8fafc; border-top:3px solid #64748b;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h4 style="font-size:15px; font-weight:700; color:#334155;">Pending</h4>
                <span id="cntPending" class="badge badge-secondary">0</span>
            </div>
            <div id="colPending" style="display:flex; flex-direction:column; gap:12px;"></div>
        </div>

        <!-- Column 2: In Progress -->
        <div class="card" style="background:#f0f9ff; border-top:3px solid #0284c7;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h4 style="font-size:15px; font-weight:700; color:#0369a1;">In Progress</h4>
                <span id="cntInProgress" class="badge badge-primary">0</span>
            </div>
            <div id="colInProgress" style="display:flex; flex-direction:column; gap:12px;"></div>
        </div>

        <!-- Column 3: Under Review -->
        <div class="card" style="background:#fefce8; border-top:3px solid #d97706;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h4 style="font-size:15px; font-weight:700; color:#b45309;">Under Review</h4>
                <span id="cntUnderReview" class="badge badge-warning">0</span>
            </div>
            <div id="colUnderReview" style="display:flex; flex-direction:column; gap:12px;"></div>
        </div>

        <!-- Column 4: Revision Required -->
        <div class="card" style="background:#fef2f2; border-top:3px solid #dc2626;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h4 style="font-size:15px; font-weight:700; color:#b91c1c;">Revision Needed</h4>
                <span id="cntRevision" class="badge badge-danger">0</span>
            </div>
            <div id="colRevision" style="display:flex; flex-direction:column; gap:12px;"></div>
        </div>
    </div>

<?php else: ?>

    <!-- Team Lead / Admin Detailed Table Layout -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tasks & Work Orders Queue</h3>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Task Title</th>
                        <th>Project & Client</th>
                        <th>Assigned Developer</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>SLA Status</th>
                        <th>Deadline</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="taskTableBody">
                    <tr><td colspan="9" style="text-align:center; padding:30px;">Loading tasks queue...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<!-- MODAL 1: CREATE TASK (Team Lead Only) -->
<div id="createTaskModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h3 class="modal-title">Create & Assign New Task</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('createTaskModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateTask();">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Project *</label>
                    <select id="ct_project_id" class="form-select" required>
                        <option value="">-- Choose Project --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['company_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" id="ct_title" class="form-control" required placeholder="e.g. Develop API Authentication Controller">
                </div>

                <div class="form-group">
                    <label class="form-label">Task Description & Requirements</label>
                    <textarea id="ct_description" class="form-control" rows="3" placeholder="Provide clear technical requirements for developer..."></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Assign To Developer *</label>
                        <select id="ct_assigned_to" class="form-select" required>
                            <?php foreach ($developers as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $d['username'] === 'dev_rahul' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['department']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Priority *</label>
                        <select id="ct_priority" class="form-select" required>
                            <option value="Urgent">Urgent</option>
                            <option value="High" selected>High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">SLA Hours (Manual Entry) *</label>
                    <input type="number" id="ct_sla_hours" class="form-control" required min="1" max="1000" value="24" placeholder="Enter SLA hours (e.g. 12, 24, 36, 72)">
                    <small style="color:var(--text-muted); font-size:11px;">(Enter custom SLA duration in hours)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create & Assign Task</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: SUBMIT WORK (Developer Only) -->
<div id="submitWorkModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Submit Work for Team Lead Review</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('submitWorkModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitWork();">
            <div class="modal-body">
                <input type="hidden" id="sw_task_id" value="0">
                
                <div class="form-group">
                    <label class="form-label">Task: <strong id="sw_task_title"></strong></label>
                </div>

                <div class="form-group">
                    <label class="form-label">Developer Work Notes & Submission Remarks *</label>
                    <textarea id="sw_notes" class="form-control" rows="4" required placeholder="Describe what was implemented, tested scenarios, and key files changed..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Attachment / Deliverable / Pull Request URL</label>
                    <input type="url" id="sw_attachment_url" class="form-control" placeholder="https://github.com/agency/repo/pull/42 or live URL">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('submitWorkModal')">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i data-feather="send"></i> Submit for Review
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: TEAM LEAD REVIEW (Team Lead Only) -->
<div id="reviewWorkModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">Team Lead Work Review</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('reviewWorkModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitTeamLeadReview();">
            <div class="modal-body">
                <input type="hidden" id="rw_task_id" value="0">

                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:12px; margin-bottom:15px;">
                    <p style="margin:0 0 4px 0; font-size:13px; font-weight:700;" id="rw_task_code"></p>
                    <p style="margin:0; font-size:13px; color:#475569;" id="rw_task_remarks"></p>
                </div>

                <div class="form-group">
                    <label class="form-label">Review Action *</label>
                    <select id="rw_action" class="form-select" onchange="onReviewActionChanged()" required>
                        <option value="APPROVE">APPROVE (Mark Completed / Pass Internal Review)</option>
                        <option value="REQUEST_REVISION">REQUEST REVISION (Send Back to Developer with Changes Needed)</option>
                        <option value="SEND_CLIENT_APPROVAL">SEND FOR CLIENT APPROVAL (Send to Client)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Review Comment *</label>
                    <textarea id="rw_comment" class="form-control" rows="3" required placeholder="Enter review remarks for developer..."></textarea>
                </div>

                <div class="form-group" id="rw_changes_box" style="display:none;">
                    <label class="form-label" style="color:#b91c1c;">Required Revisions & Specific Changes Needed *</label>
                    <textarea id="rw_required_changes" class="form-control" rows="3" placeholder="Specify exact changes required before re-submission..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reviewWorkModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Review</button>
            </div>
        </form>
<!-- MODAL 4: REASSIGN TASK (Team Lead / Admin Only) -->
<div id="reassignTaskModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title">👤 Assign / Re-assign Task</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('reassignTaskModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitReassignTask();">
            <div class="modal-body">
                <input type="hidden" id="rt_task_id" value="0">
                <p style="font-size:13px; color:#475569; margin-bottom:12px;">
                    Assigning task: <strong id="rt_task_title"></strong>
                </p>
                <div class="form-group">
                    <label class="form-label">Assign To Developer / User *</label>
                    <select id="rt_assigned_to" class="form-select" required>
                        <option value="">-- Select Developer / User --</option>
                        <?php foreach ($developers as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['role_name'] ?? $d['department']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reassignTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Assignment</button>
            </div>
        </form>
    </div>
</div>

<script>
    const userRole = <?= json_encode($user['role']) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        loadMetrics();
        loadTasks();
    });

    function loadMetrics() {
        fetch('api.php?action=get_task_dashboard_metrics')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const m = data.metrics;
                const area = document.getElementById('metricsCardsArea');

                if (userRole === 'Developer') {
                    area.innerHTML = `
                        <div class="card" style="margin-bottom:0;"><p class="form-label">MY TOTAL TASKS</p><h2>${m.total}</h2></div>
                        <div class="card" style="margin-bottom:0;"><p class="form-label">IN PROGRESS</p><h2 style="color:#0284c7">${m.in_progress}</h2></div>
                        <div class="card" style="margin-bottom:0;"><p class="form-label">UNDER REVIEW</p><h2 style="color:#d97706">${m.under_review}</h2></div>
                        <div class="card" style="margin-bottom:0;"><p class="form-label">REVISION NEEDED</p><h2 style="color:#dc2626">${m.revision}</h2></div>
                    `;
                } else {
                    area.innerHTML = `
                        <div class="card" style="margin-bottom:0;"><p class="form-label">ACTIVE PROJECTS</p><h2>${m.total_projects}</h2></div>
                        <div class="card" style="margin-bottom:0;"><p class="form-label">AWAITING REVIEW</p><h2 style="color:#d97706">${m.awaiting_review}</h2></div>
                        <div class="card" style="margin-bottom:0;"><p class="form-label">SLA BREACHES</p><h2 style="color:#dc2626">${m.sla_breaches}</h2></div>
                        <div class="card" style="margin-bottom:0;"><p class="form-label">COMPLETED TASKS</p><h2 style="color:#16a34a">${m.completed}</h2></div>
                    `;
                }
            });
    }

    function loadTasks() {
        const projectId = document.getElementById('filterProject').value;
        const status = document.getElementById('filterStatus').value;
        const devElem = document.getElementById('filterDeveloper');
        const assignedTo = devElem ? devElem.value : 0;

        let url = `api.php?action=get_tasks&project_id=${projectId}&assigned_to=${assignedTo}`;
        if (status) url += `&status=${encodeURIComponent(status)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const tasks = data.tasks;

                if (userRole === 'Developer') {
                    renderDeveloperKanban(tasks);
                } else {
                    renderTeamLeadTable(tasks);
                }
            });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function updateTaskStatusDirect(taskId, newStatus) {
        if (!taskId || !newStatus) return;
        fetch('api.php?action=update_task_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId, status: newStatus })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadMetrics();
                loadTasks();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Failed to update task status.');
        });
    }

    function renderDeveloperKanban(tasks) {
        const cols = {
            PENDING: { elem: document.getElementById('colPending'), cnt: document.getElementById('cntPending'), items: [] },
            'IN PROGRESS': { elem: document.getElementById('colInProgress'), cnt: document.getElementById('cntInProgress'), items: [] },
            'UNDER REVIEW': { elem: document.getElementById('colUnderReview'), cnt: document.getElementById('cntUnderReview'), items: [] },
            REVISION: { elem: document.getElementById('colRevision'), cnt: document.getElementById('cntRevision'), items: [] }
        };

        tasks.forEach(t => {
            if (t.status === 'PENDING') cols.PENDING.items.push(t);
            else if (t.status === 'IN PROGRESS') cols['IN PROGRESS'].items.push(t);
            else if (t.status === 'UNDER REVIEW') cols['UNDER REVIEW'].items.push(t);
            else if (t.status === 'REVISION REQUIRED' || t.status === 'CLIENT REVISION REQUESTED') cols.REVISION.items.push(t);
        });

        for (let key in cols) {
            cols[key].cnt.textContent = cols[key].items.length;
            let html = '';
            if (cols[key].items.length === 0) {
                html = '<p style="font-size:12px; color:var(--text-muted); text-align:center; padding:10px;">No tasks</p>';
            } else {
                cols[key].items.forEach(t => {
                    const slaClass = t.sla_status === 'SLA Breached' ? 'badge-danger' : (t.sla_status === 'Near SLA Warning' ? 'badge-warning' : 'badge-primary');
                    const safeTitle = (t.title || '').replace(/'/g, "\\'");
                    html += `
                        <div style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:14px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:12px; color:#2563eb;">${t.task_code}</strong>
                                <span class="badge ${slaClass}" style="font-size:10px;">${t.sla_status}</span>
                            </div>
                            <h5 style="margin:8px 0; font-size:14px; color:#0f172a; font-weight:600;">${escapeHtml(t.title)}</h5>
                            <p style="font-size:12px; color:#64748b; margin-bottom:12px;">Project: ${escapeHtml(t.project_name)}</p>

                            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
                                <a href="task_details.php?id=${t.id}" class="btn btn-outline btn-sm" style="font-size:11px;">View Details</a>
                                ${t.status === 'PENDING' ? `
                                    <button class="btn btn-outline btn-sm" style="font-size:11px; color:#0284c7; border-color:#93c5fd;" onclick="updateTaskStatusDirect(${t.id}, 'IN PROGRESS')">⚡ Start</button>
                                ` : ''}
                                ${t.status === 'IN PROGRESS' || t.status === 'REVISION REQUIRED' ? `
                                    <button class="btn btn-success btn-sm" style="font-size:11px;" onclick="openSubmitWorkModal(${t.id}, '${safeTitle}')">Submit Work</button>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
            }
            cols[key].elem.innerHTML = html;
        }
    }

    function renderTeamLeadTable(tasks) {
        const tbody = document.getElementById('taskTableBody');
        if (tasks.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:30px; color:var(--text-muted);">No tasks found matching filter criteria.</td></tr>';
            return;
        }

        let html = '';
        tasks.forEach(t => {
            const slaClass = t.sla_status === 'SLA Breached' ? 'badge-danger' : (t.sla_status === 'Near SLA Warning' ? 'badge-warning' : 'badge-primary');
            const safeTitle = escapeHtml(t.title);

            html += `
                <tr>
                    <td><strong style="color:#2563eb;">${t.task_code}</strong></td>
                    <td>
                        <a href="task_details.php?id=${t.id}" style="font-weight:700; color:#0f172a; text-decoration:none;">${safeTitle}</a>
                    </td>
                    <td>
                        <strong style="color:#334155;">${escapeHtml(t.project_name)}</strong><br>
                        <small style="color:var(--text-muted);">${escapeHtml(t.company_name)}</small>
                    </td>
                    <td><span class="badge badge-secondary">${escapeHtml(t.assigned_to_name || 'Unassigned')}</span></td>
                    <td><span class="badge ${t.priority === 'Urgent' ? 'badge-danger' : 'badge-warning'}">${t.priority}</span></td>
                    <td>
                        <select class="form-select" style="font-size:12px; font-weight:700; padding:4px 8px; width:135px; border-radius:6px; cursor:pointer;" onchange="updateTaskStatusDirect(${t.id}, this.value)">
                            <option value="PENDING" ${t.status === 'PENDING' ? 'selected' : ''}>⏳ Pending</option>
                            <option value="IN PROGRESS" ${t.status === 'IN PROGRESS' ? 'selected' : ''}>⚡ In Progress</option>
                            <option value="UNDER REVIEW" ${t.status === 'UNDER REVIEW' ? 'selected' : ''}>🔍 Under Review</option>
                            <option value="REVISION REQUIRED" ${t.status === 'REVISION REQUIRED' ? 'selected' : ''}>🔄 Revision Needed</option>
                            <option value="COMPLETED" ${t.status === 'COMPLETED' ? 'selected' : ''}>✅ Completed</option>
                        </select>
                    </td>
                    <td><span class="badge ${slaClass}">${t.sla_status}</span></td>
                    <td><small style="color:#475569;">${t.deadline ? t.deadline.substring(0, 16) : 'N/A'}</small></td>
                    <td>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <a href="task_details.php?id=${t.id}" class="btn btn-outline btn-sm" title="View details & discussion">Details</a>
                            <button class="btn btn-outline btn-sm" onclick="openReassignModal(${t.id}, '${safeTitle.replace(/'/g, "\\'")}', ${t.assigned_to || 0})" style="color:#2563eb; border-color:#93c5fd;" title="Assign or reassign task to developer">
                                👤 Assign
                            </button>
                            ${t.status === 'UNDER REVIEW' ? `
                                <button class="btn btn-primary btn-sm" onclick="openReviewModal(${t.id}, '${t.task_code}', '${(t.remarks || '').replace(/'/g, "\\'")}')">Review</button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        if (window.feather) feather.replace();
    }

    function openReassignModal(taskId, title, currentAssignee) {
        document.getElementById('rt_task_id').value = taskId;
        document.getElementById('rt_task_title').textContent = title;
        if (document.getElementById('rt_assigned_to')) {
            document.getElementById('rt_assigned_to').value = currentAssignee || '';
        }
        document.getElementById('reassignTaskModal').style.display = 'flex';
    }

    function submitReassignTask() {
        const taskId = document.getElementById('rt_task_id').value;
        const assignedTo = document.getElementById('rt_assigned_to').value;

        if (!taskId || !assignedTo) {
            alert('Please select a target developer or user.');
            return;
        }

        fetch('api.php?action=reassign_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId, assigned_to: assignedTo })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('reassignTaskModal');
                alert('✅ ' + data.message);
                loadTasks();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // Modal Helpers
    function openCreateTaskModal() { document.getElementById('createTaskModal').style.display = 'flex'; }
    function openSubmitWorkModal(taskId, title) {
        document.getElementById('sw_task_id').value = taskId;
        document.getElementById('sw_task_title').textContent = title;
        document.getElementById('submitWorkModal').style.display = 'flex';
    }
    function openReviewModal(taskId, code, remarks) {
        document.getElementById('rw_task_id').value = taskId;
        document.getElementById('rw_task_code').textContent = `Task #${code}`;
        document.getElementById('rw_task_remarks').textContent = `Developer Submission: ${remarks}`;
        document.getElementById('reviewWorkModal').style.display = 'flex';
    }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function onReviewActionChanged() {
        const action = document.getElementById('rw_action').value;
        document.getElementById('rw_changes_box').style.display = action === 'REQUEST_REVISION' ? 'block' : 'none';
    }

    function submitCreateTask() {
        const payload = {
            project_id: document.getElementById('ct_project_id').value,
            title: document.getElementById('ct_title').value,
            description: document.getElementById('ct_description').value,
            assigned_to: document.getElementById('ct_assigned_to').value,
            priority: document.getElementById('ct_priority').value,
            sla_hours: document.getElementById('ct_sla_hours').value
        };

        fetch('api.php?action=create_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('createTaskModal');
                alert('✅ ' + data.message);
                loadMetrics();
                loadTasks();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function submitWork() {
        const payload = {
            task_id: document.getElementById('sw_task_id').value,
            submission_notes: document.getElementById('sw_notes').value,
            attachment_url: document.getElementById('sw_attachment_url').value
        };

        fetch('api.php?action=submit_developer_work', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('submitWorkModal');
                alert('🚀 ' + data.message);
                loadMetrics();
                loadTasks();
            } else {
                alert(data.message);
            }
        });
    }

    function submitTeamLeadReview() {
        const payload = {
            task_id: document.getElementById('rw_task_id').value,
            review_action: document.getElementById('rw_action').value,
            review_comment: document.getElementById('rw_comment').value,
            required_changes: document.getElementById('rw_required_changes').value
        };

        fetch('api.php?action=review_task_work', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('reviewWorkModal');
                alert('✅ ' + data.message);
                loadMetrics();
                loadTasks();
            } else {
                alert(data.message);
            }
        });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
