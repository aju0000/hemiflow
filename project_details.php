<?php
// project_details.php - Project Overview, About Project, Competitor Details & Project Tasks Workspace

require_once __DIR__ . '/auth.php';
checkAuth('task-management');

$user = getCurrentUser();
$db = getDbConnection();

$projectId = intval($_GET['id'] ?? ($_GET['project_id'] ?? 0));
$isLead = isLeadOrAdmin();
$isHandlerForProject = ($projectId > 0) ? isProjectHandler($db, $projectId, $user['id']) : false;
$canManageProject = $isLead || $isHandlerForProject;

// Fetch active projects for switcher dropdown
$allProjects = $db->query("SELECT p.id, p.project_name, c.company_name, COALESCE(p.is_current, 1) as is_current FROM projects p JOIN clients c ON p.client_id = c.id ORDER BY COALESCE(p.is_current, 1) DESC, p.id DESC")->fetchAll();

if ($projectId <= 0 && !empty($allProjects)) {
    $projectId = intval($allProjects[0]['id']);
}

$clients = $db->query("SELECT id, company_name FROM clients ORDER BY company_name ASC")->fetchAll();
$departments = $db->query("SELECT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($departments)) {
    $departments = ['Development', 'Marketing', 'Creative', 'Design / UI/UX', 'Management', 'Sales'];
}
$staffUsers = $db->query("SELECT u.id, u.full_name, u.username, u.department, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 AND u.role_id != 7 ORDER BY u.full_name ASC")->fetchAll();

$pageBreadcrumb = "Project Portfolio";
$pageTitle = "Project Overview & Competitors";
$pageSubtitle = "Complete project brief, market competitor intelligence, and direct task execution.";
require_once __DIR__ . '/header.php';
?>

<!-- Top Action Navigation -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <a href="projects.php?view=projects" class="btn btn-outline btn-sm">
            <i data-feather="arrow-left"></i> Back to Current Projects
        </a>
        <a href="projects.php" class="btn btn-outline btn-sm">
            <i data-feather="list"></i> All Tasks Queue
        </a>
        <select id="projectSwitcherSelect" class="form-select" style="width:auto; min-width:260px; font-size:13px; font-weight:600; padding:6px 12px;" onchange="if(this.value) window.location.href='project_details.php?id=' + this.value">
            <option value="">-- Switch Current Project --</option>
            <?php foreach ($allProjects as $ap): ?>
                <option value="<?= $ap['id'] ?>" <?= ($ap['id'] == $projectId) ? 'selected' : '' ?>>
                    <?= $ap['is_current'] ? '🟢 ' : '📁 ' ?><?= htmlspecialchars($ap['project_name']) ?> (<?= htmlspecialchars($ap['company_name']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($canManageProject): ?>
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <?php if ($isLead): ?>
        <button class="btn btn-outline btn-sm" onclick="openPdAssignHandlersModal()" style="border-radius:8px; color:#0f766e; border-color:#99f6e4; background:#f0fdfa; font-weight:700;">
            🛡️ Assign Project Handlers
        </button>
        <?php endif; ?>
        <button class="btn btn-outline btn-sm" onclick="openPdWorkingTeamModal()" style="border-radius:8px; color:#1d4ed8; border-color:#93c5fd; background:#eff6ff; font-weight:700;">
            👥 Assign Working Team
        </button>
        <button class="btn btn-outline btn-sm" onclick="openEditProjectModal()" style="border-radius:8px;">
            ✏️ Edit Project<?= $isLead ? ' & Handlers' : '' ?>
        </button>
        <button class="btn btn-outline btn-sm" onclick="openCompetitorModal(0)" style="border-radius:8px; color:#b45309; border-color:#fcd34d; background:#fffbeb;">
            🏆 + Add Competitor
        </button>
        <button class="btn btn-outline btn-sm" onclick="openCreateProjectTaskModal(0, true)" style="border-radius:8px; font-weight:700; color:#7c3aed; border-color:#c4b5fd; background:#f5f3ff;">
            ↳ + Create Sub-Task
        </button>
        <button class="btn btn-primary btn-sm" onclick="openCreateProjectTaskModal(0, false)" style="border-radius:8px; font-weight:700; padding:8px 16px;">
            + Create Task on Current Project
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if ($projectId <= 0): ?>
    <div class="card" style="text-align:center; padding:50px 24px;">
        <div style="font-size:42px; margin-bottom:10px;">📁</div>
        <h3 style="margin-bottom:8px;">No Projects Found Yet</h3>
        <p style="color:#64748b; font-size:14px; margin-bottom:20px;">Create your first current project to add project details, competitor insights, and tasks.</p>
        <a href="projects.php?view=projects" class="btn btn-primary">Go to Current Projects List</a>
    </div>
<?php else: ?>

<!-- 1. HERO PROJECT OVERVIEW BANNER -->
<div class="card" style="padding:24px; margin-bottom:24px; border:1px solid #e2e8f0; border-radius:16px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
        <div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
                <span id="pd_current_badge" class="badge badge-success" style="font-size:11px;">🟢 Current Project</span>
                <span id="pd_dept_badge" class="badge badge-primary" style="font-size:11px;">📁 Department</span>
                <span id="pd_priority_badge" class="badge badge-warning" style="font-size:11px;">Priority</span>
            </div>
            <h2 id="pd_title" style="font-size:24px; font-weight:800; color:#0f172a; margin:0 0 6px 0;">Loading project...</h2>
            <p style="font-size:13.5px; color:#64748b; margin:0 0 8px 0;">
                Client: <strong id="pd_client_name" style="color:#1e293b;">--</strong> &bull;
                Lead: <strong id="pd_lead_name" style="color:#2563eb;">--</strong> &bull;
                Timeline: <strong id="pd_timeline">--</strong>
            </p>
            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:12.5px; margin-bottom:6px;">
                <span style="color:#475569; font-weight:700;">🛡️ Project Handlers:</span>
                <div id="pd_handlers_badges" style="display:flex; flex-wrap:wrap; gap:6px;">--</div>
            </div>
            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:12.5px; margin-bottom:6px;">
                <span style="color:#1d4ed8; font-weight:700;">👥 Working on Project:</span>
                <div id="pd_working_badges" style="display:flex; flex-wrap:wrap; gap:6px;">--</div>
                <?php if ($canManageProject): ?>
                    <button type="button" onclick="openPdWorkingTeamModal()" style="background:#eff6ff; color:#1d4ed8; border:1px dashed #93c5fd; border-radius:12px; font-size:11px; font-weight:700; padding:2px 9px; cursor:pointer;">+ Add / Edit</button>
                <?php endif; ?>
            </div>
            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; font-size:12px;">
                <span style="color:#6b21a8; font-weight:700;">👁️ Default Observers:</span>
                <div id="pd_observers_badges" style="display:flex; flex-wrap:wrap; gap:6px;">--</div>
            </div>
        </div>

        <div style="display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap;">
            <?php if ($canManageProject): ?>
            <div id="pd_client_approval_box" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:8px 14px; display:flex; align-items:center; gap:10px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin:0; font-size:13px; font-weight:700; color:#0f172a;">
                    <input type="checkbox" id="pd_client_approved_chk" style="width:18px; height:18px; accent-color:#16a34a; cursor:pointer;" onchange="toggleProjectClientApproval(this.checked)">
                    <span id="pd_client_approved_label">Approved by Client</span>
                </label>
            </div>
            <?php endif; ?>
            <div>
                <label style="display:block; font-size:10.5px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:3px;">Project Status</label>
                <?php if ($canManageProject): ?>
                <select id="pd_status_select" class="form-select" style="font-weight:700; font-size:13px; padding:6px 12px; min-width:150px;" onchange="quickUpdateProjectStatus(this.value)">
                    <option value="Planning">📝 Planning</option>
                    <option value="In Progress">⚡ In Progress</option>
                    <option value="On Hold">⏸️ On Hold</option>
                    <option value="Completed">✅ Completed</option>
                </select>
                <?php else: ?>
                <span id="pd_status_badge" class="badge badge-primary" style="font-size:13px;">In Progress</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Progress Bar & Stats Row -->
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-size:12.5px; font-weight:700; color:#334155;">Project Execution Progress</span>
            <span id="pd_progress_text" style="font-size:13px; font-weight:800; color:#2563eb;">0% (0 / 0 Tasks Done)</span>
        </div>
        <div style="width:100%; height:9px; background:#e2e8f0; border-radius:99px; overflow:hidden; margin-bottom:16px;">
            <div id="pd_progress_bar" style="width:0%; height:100%; background:linear-gradient(90deg, #2563eb, #10b981); border-radius:99px; transition:width 0.4s ease;"></div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(120px, 1fr)); gap:12px;">
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px;">
                <div style="font-size:11px; color:#64748b; font-weight:600;">Total Tasks</div>
                <div id="pd_stat_total" style="font-size:20px; font-weight:800; color:#0f172a;">0</div>
            </div>
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px;">
                <div style="font-size:11px; color:#0284c7; font-weight:600;">In Progress</div>
                <div id="pd_stat_inprog" style="font-size:20px; font-weight:800; color:#0284c7;">0</div>
            </div>
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px;">
                <div style="font-size:11px; color:#d97706; font-weight:600;">Pending / Review</div>
                <div id="pd_stat_pending" style="font-size:20px; font-weight:800; color:#d97706;">0</div>
            </div>
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px;">
                <div style="font-size:11px; color:#16a34a; font-weight:600;">Completed</div>
                <div id="pd_stat_done" style="font-size:20px; font-weight:800; color:#16a34a;">0</div>
            </div>
            <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:10px 14px;">
                <div style="font-size:11px; color:#b45309; font-weight:600;">🏆 Competitors</div>
                <div id="pd_stat_comp" style="font-size:20px; font-weight:800; color:#b45309;">0</div>
            </div>
        </div>
    </div>
</div>

<!-- 2. ABOUT THE PROJECT & CLIENT DETAILS -->
<div class="grid-2" style="grid-template-columns: 1fr 340px; align-items:start; gap:20px; margin-bottom:24px;">
    <!-- Left: About Project & Scope -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3 class="card-title" style="margin:0;">📖 About the Project & Overview</h3>
            <?php if ($canManageProject): ?>
                <button class="btn btn-outline btn-sm" onclick="openEditProjectModal()" style="font-size:11.5px;">✏️ Edit Details</button>
            <?php endif; ?>
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px;">
                <h4 style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#475569; margin:0 0 6px 0;">About the Project (Brief & Objectives)</h4>
                <div id="pd_about_project" style="font-size:13.5px; color:#1e293b; line-height:1.6; white-space:pre-wrap;">Loading...</div>
            </div>

            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px;">
                <h4 style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#475569; margin:0 0 6px 0;">Project Scope & Summary</h4>
                <div id="pd_description" style="font-size:13.5px; color:#475569; line-height:1.6; white-space:pre-wrap;">Loading...</div>
            </div>

            <div class="grid-2" style="gap:14px;">
                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:14px;">
                    <h4 style="font-size:11.5px; text-transform:uppercase; letter-spacing:0.5px; color:#0369a1; margin:0 0 6px 0;">🎯 Target Audience</h4>
                    <div id="pd_target_audience" style="font-size:13px; color:#0c4a6e; line-height:1.5; white-space:pre-wrap;">Not specified</div>
                </div>
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:14px;">
                    <h4 style="font-size:11.5px; text-transform:uppercase; letter-spacing:0.5px; color:#15803d; margin:0 0 6px 0;">📦 Key Deliverables</h4>
                    <div id="pd_deliverables" style="font-size:13px; color:#14532d; line-height:1.5; white-space:pre-wrap;">Not specified</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Client & Project Metadata -->
    <div style="display:flex; flex-direction:column; gap:18px;">
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <h3 class="card-title" style="margin:0;">🏢 Client & Reference Info</h3>
            </div>
            <div style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
                <div>
                    <span style="color:#64748b; font-size:11.5px; display:block;">Company Name</span>
                    <strong id="pd_side_client" style="color:#0f172a; font-size:14px;">--</strong>
                </div>
                <div>
                    <span style="color:#64748b; font-size:11.5px; display:block;">Client Contact Person</span>
                    <span id="pd_side_contact" style="color:#334155;">--</span>
                </div>
                <div>
                    <span style="color:#64748b; font-size:11.5px; display:block;">Client Email / Phone</span>
                    <span id="pd_side_comm" style="color:#334155;">--</span>
                </div>
                <div style="border-top:1px solid #f1f5f9; padding-top:10px;">
                    <span style="color:#64748b; font-size:11.5px; display:block;">Website / Assets / Drive Link</span>
                    <div id="pd_side_url" style="margin-top:3px;">--</div>
                </div>
                <div>
                    <span style="color:#64748b; font-size:11.5px; display:block;">Budget / Package Notes</span>
                    <strong id="pd_side_budget" style="color:#0f172a;">--</strong>
                </div>
                <div id="pd_client_portal_link_wrap" style="padding-top:6px;"></div>
            </div>
        </div>

        <!-- Working Employees on Project Card -->
        <div class="card" style="margin-bottom:0;">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <h3 class="card-title" style="margin:0; font-size:15px;">👥 Working on Project</h3>
                <?php if ($canManageProject): ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="openPdWorkingTeamModal()" style="font-size:11px; color:#1d4ed8; border-color:#93c5fd; background:#eff6ff; font-weight:700; padding:3px 9px;">
                        + Manage Team
                    </button>
                <?php endif; ?>
            </div>
            <div id="pd_side_working_list" style="display:flex; flex-direction:column; gap:8px; font-size:12.5px;">
                <span style="color:#94a3b8; font-style:italic;">Loading working team...</span>
            </div>
        </div>
    </div>
</div>

<!-- 3. COMPETITORS DETAILS & INTELLIGENCE -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 class="card-title" style="margin:0;">🏆 Competitors Details & Market Analysis</h3>
            <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Track competitor websites, strengths, weaknesses, and positioning notes for the team.</p>
        </div>
        <?php if ($canManageProject): ?>
            <button class="btn btn-primary btn-sm" onclick="openCompetitorModal(0)" style="background:#d97706; border-color:#d97706;">
                + Add Competitor Detail
            </button>
        <?php endif; ?>
    </div>

    <div id="pd_competitors_container" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(330px, 1fr)); gap:16px;">
        <div style="grid-column:1/-1; text-align:center; padding:25px; color:#94a3b8;">Loading competitor details...</div>
    </div>
</div>

<!-- 4. TASKS ON CURRENT PROJECT -->
<div class="card" style="padding:0; overflow:hidden; margin-bottom:30px;">
    <div style="padding:18px 22px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h3 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">✅ Tasks on Current Project</h3>
            <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;">Assign deliverables, monitor SLAs, and create new tasks directly on this project.</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <select id="pdTaskStatusFilter" class="form-select" style="font-size:12.5px; padding:6px 10px; width:180px;" onchange="renderProjectTasks()">
                <option value="">All Statuses</option>
                <option value="PENDING">Pending</option>
                <option value="IN PROGRESS">In Progress</option>
                <option value="UNDER REVIEW">Under Review (Handler)</option>
                <option value="REVISION REQUIRED">Revision Needed</option>
                <option value="SENT FOR CLIENT APPROVAL">Client Approval Pending</option>
                <option value="COMPLETED">Completed</option>
            </select>
            <?php if ($canManageProject): ?>
                <button class="btn btn-outline btn-sm" onclick="openCreateProjectTaskModal(0, true)" style="font-weight:700; color:#7c3aed; border-color:#c4b5fd; background:#f5f3ff;">
                    ↳ + Create Sub-Task
                </button>
                <button class="btn btn-primary btn-sm" onclick="openCreateProjectTaskModal(0, false)" style="font-weight:700;">
                    + Create Task on Project
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th style="width:100px;">Code</th>
                    <th>Task Deliverable</th>
                    <th>Assigned Staff & Observers</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>SLA Status</th>
                    <th>Deadline</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="pdTasksTbody">
                <tr><td colspan="8" style="text-align:center; padding:30px; color:#94a3b8;">Loading project tasks...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL A: EDIT PROJECT OVERVIEW & ABOUT -->
<div id="editProjectOverviewModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:740px; width:95%; max-height:92vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Current Project Overview & About</h3>
            <button class="btn btn-outline btn-sm" onclick="closePdModal('editProjectOverviewModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitEditProject();">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Client *</label>
                        <select id="ep_client_id" class="form-select" required>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Name *</label>
                        <input type="text" id="ep_project_name" class="form-control" required>
                    </div>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select id="ep_department" class="form-select">
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Lead *</label>
                        <select id="ep_team_lead_id" class="form-select" required>
                            <?php foreach ($staffUsers as $su): ?>
                                <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['full_name']) ?> (<?= htmlspecialchars($su['role_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="ep_status" class="form-select">
                            <option value="Planning">Planning</option>
                            <option value="In Progress">In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="ep_priority" class="form-select">
                            <option value="Urgent">Urgent</option>
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" id="ep_start_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target Deadline</label>
                        <input type="date" id="ep_deadline" class="form-control">
                    </div>
                </div>

                <?php if ($isLead): ?>
                <div class="form-group" style="background:#f0fdfa; border:1px solid #99f6e4; border-radius:10px; padding:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="form-label" style="margin:0; font-size:12px; font-weight:700; color:#0f766e;">🛡️ Project Handlers (Lead Access Only — Can Create Sub-Tasks & Approve Work) <span id="ep_handlers_badge" class="badge badge-primary" style="font-size:10px;">0 Selected</span></label>
                    </div>
                    <div id="ep_handlers_pills" style="display:flex; flex-wrap:wrap; gap:6px;"></div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700; color:#1e40af;">📖 About the Project (Brief, Background & Objectives)</label>
                    <textarea id="ep_about_project" class="form-control" rows="4" placeholder="Detailed project background, goals, brand tone, key selling points..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Project Scope & Description</label>
                    <textarea id="ep_description" class="form-control" rows="2" placeholder="Concise scope summary..."></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">🎯 Target Audience</label>
                        <textarea id="ep_target_audience" class="form-control" rows="2" placeholder="Age group, location, industry, buyer persona..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">📦 Key Deliverables</label>
                        <textarea id="ep_deliverables_summary" class="form-control" rows="2" placeholder="Website, 12 reels, SEO audit, monthly ad campaigns..."></textarea>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">🔗 Website / Reference / Drive URL</label>
                        <input type="text" id="ep_project_url" class="form-control" placeholder="https://example.com or Google Drive link">
                    </div>
                    <div class="form-group">
                        <label class="form-label">💰 Budget / Package Info</label>
                        <input type="text" id="ep_budget_info" class="form-control" placeholder="e.g. Growth Retainer - INR 45,000/mo">
                    </div>
                </div>

                <div class="form-group" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px 14px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin:0; font-size:13px; font-weight:600; color:#166534;">
                        <input type="checkbox" id="ep_is_current" value="1" checked>
                        <span>List as Current Active Project for Leads & Team</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePdModal('editProjectOverviewModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight:700;">Save Project Details</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL B: ADD / EDIT COMPETITOR DETAIL -->
<div id="competitorModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:600px; width:95%;">
        <div class="modal-header">
            <h3 class="modal-title" id="compModalTitle">🏆 Add Competitor Detail</h3>
            <button class="btn btn-outline btn-sm" onclick="closePdModal('competitorModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitSaveCompetitor();">
            <div class="modal-body">
                <input type="hidden" id="comp_id" value="0">
                <div class="form-group">
                    <label class="form-label">Competitor Name *</label>
                    <input type="text" id="comp_name" class="form-control" required placeholder="e.g. Acme Digital Agency">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Website URL</label>
                        <input type="text" id="comp_website" class="form-control" placeholder="https://competitor.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Social / IG / LinkedIn Links</label>
                        <input type="text" id="comp_social" class="form-control" placeholder="@competitor or social URL">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" style="color:#15803d;">Strengths (What they do well)</label>
                        <textarea id="comp_strengths" class="form-control" rows="3" placeholder="Strong SEO authority, clean reels, fast site speed..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:#b45309;">Weaknesses / Opportunities</label>
                        <textarea id="comp_weaknesses" class="form-control" rows="3" placeholder="Poor mobile UX, high pricing, weak local content..."></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Strategy / Comparison Notes</label>
                    <textarea id="comp_notes" class="form-control" rows="2" placeholder="How our campaign / product outperforms this competitor..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePdModal('competitorModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:#d97706; border-color:#d97706; font-weight:700;">Save Competitor</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL C: CREATE TASK ON CURRENT PROJECT -->
<div id="createProjectTaskModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:720px; width:95%; max-height:92vh; overflow-y:auto;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="cpt_modal_title" style="margin:0;">+ Create Task on Current Project</h3>
                <small id="cpt_project_subtitle" style="color:#2563eb; font-weight:600;"></small>
            </div>
            <button class="btn btn-outline btn-sm" onclick="closePdModal('createProjectTaskModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateProjectTask();">
            <div class="modal-body">
                <div class="grid-2" style="background:#f5f3ff; border:1px solid #ddd6fe; border-radius:8px; padding:10px 12px; margin-bottom:14px; align-items:center;">
                    <label style="display:flex; align-items:center; gap:8px; margin:0; font-size:12.5px; font-weight:700; color:#5b21b6; cursor:pointer;">
                        <input type="checkbox" id="cpt_is_subtask" style="width:16px; height:16px; accent-color:#7c3aed;" onchange="onPdSubtaskToggle()">
                        <span>↳ Create as Project Sub-Task</span>
                    </label>
                    <div>
                        <select id="cpt_parent_task_id" class="form-select" style="font-size:12px;">
                            <option value="0">-- No Parent Task (Standalone Project Sub-Task/Task) --</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" id="cpt_title" class="form-control" required placeholder="e.g. Competitor Keyword Benchmark & Landing Page Wireframe">
                </div>

                <div class="form-group">
                    <label class="form-label">Task Instructions & Requirements</label>
                    <textarea id="cpt_description" class="form-control" rows="3" placeholder="Detailed execution brief for assigned team members..."></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Target Department *</label>
                        <select id="cpt_department" class="form-select" required onchange="renderPdCreatePills()">
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Primary Assignee *</label>
                        <select id="cpt_assigned_to" class="form-select" required onchange="onPdNewPrimaryChanged(this.value)">
                            <?php foreach ($staffUsers as $su): ?>
                                <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['full_name']) ?> (<?= htmlspecialchars($su['role_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Multiple Assignees Pills -->
                <div class="form-group" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="form-label" style="margin:0; font-size:12px; font-weight:700;">👥 Assign Team Members <span id="cpt_members_badge" class="badge badge-primary" style="font-size:10px;">1 Selected</span></label>
                        <small style="color:#64748b; font-size:11px;">Click to toggle members</small>
                    </div>
                    <div id="cpt_members_pills" style="display:flex; flex-wrap:wrap; gap:6px;"></div>
                </div>

                <!-- Observers -->
                <div class="form-group" style="background:#fdf4ff; border:1px solid #f0abfc; border-radius:8px; padding:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="form-label" style="margin:0; font-size:12px; font-weight:700; color:#86198f;">👁️ Observers <span id="cpt_observers_badge" class="badge" style="background:#fae8ff; color:#a21caf; font-size:10px;">0 Observers</span></label>
                    </div>
                    <div id="cpt_observers_pills" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;"></div>
                    <select id="cpt_observer_select" class="form-select" style="font-size:12px; border-color:#d8b4fe;" onchange="addPdTaskObserver(this.value)">
                        <option value="">+ Add Observer...</option>
                        <?php foreach ($staffUsers as $su): ?>
                            <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['full_name']) ?> (<?= htmlspecialchars($su['role_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="cpt_priority" class="form-select">
                            <option value="Urgent">Urgent</option>
                            <option value="High" selected>High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="datetime-local" id="cpt_start_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deadline</label>
                        <input type="datetime-local" id="cpt_end_date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+48 hours')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">SLA Hours</label>
                    <input type="number" id="cpt_sla_hours" class="form-control" value="48" min="1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePdModal('createProjectTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight:700;">Create & Assign Task</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL D: REASSIGN TASK / ADD OBSERVERS -->
<div id="pdReassignModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:650px; width:95%;">
        <div class="modal-header">
            <h3 class="modal-title">👤 Assign Members & Observers</h3>
            <button class="btn btn-outline btn-sm" onclick="closePdModal('pdReassignModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitPdReassignTask();">
            <div class="modal-body">
                <input type="hidden" id="pdr_task_id">
                <p style="font-size:13px; margin-bottom:12px;">Task: <strong id="pdr_task_title"></strong></p>

                <div class="form-group">
                    <label class="form-label">Primary Assignee *</label>
                    <select id="pdr_assigned_to" class="form-select" required onchange="if(this.value && !pdReassignMembers.includes(parseInt(this.value))) { pdReassignMembers.push(parseInt(this.value)); renderPdReassignPills(); }">
                        <?php foreach ($staffUsers as $su): ?>
                            <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['full_name']) ?> (<?= htmlspecialchars($su['role_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                    <label class="form-label" style="font-size:12px; font-weight:700;">👥 Additional Assignees</label>
                    <div id="pdr_members_pills" style="display:flex; flex-wrap:wrap; gap:6px;"></div>
                </div>

                <div class="form-group" style="background:#fdf4ff; border:1px solid #f0abfc; border-radius:8px; padding:12px;">
                    <label class="form-label" style="font-size:12px; font-weight:700; color:#86198f;">👁️ Observers</label>
                    <div id="pdr_observers_pills" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;"></div>
                    <select id="pdr_observer_select" class="form-select" style="font-size:12px;" onchange="if(this.value && !pdReassignObservers.includes(parseInt(this.value))) { pdReassignObservers.push(parseInt(this.value)); renderPdReassignObserverPills(); } this.value='';">
                        <option value="">+ Add Observer...</option>
                        <?php foreach ($staffUsers as $su): ?>
                            <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePdModal('pdReassignModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Assignment</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL E: EMPLOYEE SUBMIT WORK FOR HANDLER APPROVAL -->
<div id="pdSubmitWorkModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:540px; width:95%;">
        <div class="modal-header">
            <h3 class="modal-title">📤 Submit Work for Handler Approval</h3>
            <button class="btn btn-outline btn-sm" onclick="closePdModal('pdSubmitWorkModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitPdEmployeeWork();">
            <div class="modal-body">
                <input type="hidden" id="pd_sw_task_id" value="0">
                <p style="font-size:13px; margin-bottom:10px;">Task: <strong id="pd_sw_task_title"></strong></p>
                <div class="form-group">
                    <label class="form-label">Work Completion Notes *</label>
                    <textarea id="pd_sw_notes" class="form-control" rows="3" required placeholder="Summary of work done for Project Handler review..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Deliverable / PR / Reference Link</label>
                    <input type="url" id="pd_sw_url" class="form-control" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label class="form-label">Attach Media / Screenshots / Files (Optional)</label>
                    <input type="file" id="pd_sw_files" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.mp4,.mov,.webm,.mp3" class="form-control" style="padding:6px; font-size:12.5px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePdModal('pdSubmitWorkModal')">Cancel</button>
                <button type="submit" class="btn btn-success" style="font-weight:700;">Submit for Handler Approval</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL F: PROJECT HANDLER REVIEW / APPROVE TASK -->
<div id="pdReviewModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:560px; width:95%;">
        <div class="modal-header">
            <h3 class="modal-title">🛡️ Project Handler Approval</h3>
            <button class="btn btn-outline btn-sm" onclick="closePdModal('pdReviewModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitPdHandlerReview();">
            <div class="modal-body">
                <input type="hidden" id="pd_rw_task_id" value="0">
                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:10px 12px; margin-bottom:12px;">
                    <strong id="pd_rw_task_title" style="font-size:13px; color:#0f172a; display:block;"></strong>
                    <small id="pd_rw_remarks" style="color:#475569;"></small>
                </div>
                <div class="form-group">
                    <label class="form-label">Decision *</label>
                    <select id="pd_rw_action" class="form-select" onchange="document.getElementById('pd_rw_changes_wrap').style.display = this.value === 'REQUEST_REVISION' ? 'block' : 'none';" required>
                        <option value="APPROVE">✅ Approve (Project Handler Approved -> Send for Client Approval)</option>
                        <option value="REQUEST_REVISION">🔄 Request Revision (Return to Employee)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Review Notes *</label>
                    <textarea id="pd_rw_comment" class="form-control" rows="3" required placeholder="Handler approval notes..."></textarea>
                </div>
                <div class="form-group" id="pd_rw_changes_wrap" style="display:none;">
                    <label class="form-label" style="color:#b91c1c;">Required Changes</label>
                    <textarea id="pd_rw_changes" class="form-control" rows="2" placeholder="Specify changes required from employee..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePdModal('pdReviewModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight:700;">Confirm Handler Decision</button>
            </div>
        </form>
    </div>
</div>

<?php if ($isLead): ?>
<!-- MODAL G: LEAD-ONLY SEPARATE PROJECT HANDLER ASSIGNMENT -->
<div id="pdAssignHandlersModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:580px; width:95%; max-height:90vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">🛡️ Assign Project Handlers (Lead Access Only)</h3>
            <button class="btn btn-outline btn-sm" onclick="closePdModal('pdAssignHandlersModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitPdSeparateHandlers();">
            <div class="modal-body">
                <div style="background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px; padding:11px 14px; margin-bottom:14px; font-size:12.5px; color:#115e59;">
                    Select the <strong>Project Handler(s)</strong> for this project separately from task assignees. Only Leads/Admins can add handlers. Handlers can create sub-tasks for employees and must approve completed work.
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label class="form-label" style="margin:0; font-size:12.5px; font-weight:700;">Project Handlers</label>
                    <span id="pd_sep_handlers_badge" class="badge" style="background:#ccfbf1; color:#0f766e; font-size:11px; font-weight:700;">0 Selected</span>
                </div>
                <div id="pd_sep_handlers_pills" style="display:flex; flex-wrap:wrap; gap:6px; border:1px solid #e2e8f0; border-radius:8px; padding:12px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePdModal('pdAssignHandlersModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:#0f766e; border-color:#0f766e; font-weight:700;">Save Project Handlers</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canManageProject): ?>
<!-- MODAL H: ASSIGN WORKING EMPLOYEES ON PROJECT (LEAD & PROJECT HANDLER ACCESS) -->
<div id="pdWorkingTeamModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:640px; width:95%; max-height:90vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">👥 Assign Working Employees on Project</h3>
            <button class="btn btn-outline btn-sm" onclick="closePdModal('pdWorkingTeamModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitPdWorkingTeam();">
            <div class="modal-body">
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:11px 14px; margin-bottom:14px; font-size:12.5px; color:#1e40af;">
                    Select all employees currently working on this project. Both <strong>Team Leads</strong> and <strong>Project Handlers</strong> can manage this list. Newly added employees automatically receive a notification and see this project on their dashboard.
                </div>

                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
                    <input type="text" id="pd_wt_search" class="form-control" placeholder="🔍 Search employee by name or role..." oninput="renderPdWorkingTeamPills()" style="flex:1; min-width:180px; font-size:12.5px; height:36px;">
                    <select id="pd_wt_dept_filter" class="form-select" onchange="renderPdWorkingTeamPills()" style="width:auto; min-width:150px; font-size:12.5px; height:36px;">
                        <option value="">All Departments</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label class="form-label" style="margin:0; font-size:12.5px; font-weight:700;">Working Team Members (Multiple Allowed)</label>
                    <span id="pd_wt_badge" class="badge" style="background:#dbeafe; color:#1d4ed8; font-size:11px; font-weight:700;">0 Selected</span>
                </div>
                <div id="pd_wt_pills" style="display:flex; flex-wrap:wrap; gap:6px; border:1px solid #e2e8f0; border-radius:8px; padding:12px; max-height:240px; overflow-y:auto; background:#f8fafc;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePdModal('pdWorkingTeamModal')">Cancel</button>
                <button type="submit" id="pd_wt_submit_btn" class="btn btn-primary" style="font-weight:700;">Save &amp; Notify Working Employees</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
const pdProjectId = <?= json_encode($projectId) ?>;
const pdIsActualLead = <?= json_encode($isLead) ?>;
const pdIsLead = <?= json_encode($canManageProject) ?>;
let pdCanManage = <?= json_encode($canManageProject) ?>;
const pdStaffUsers = <?= json_encode($staffUsers) ?>;
let pdProjectData = null;
let pdCompetitors = [];
let pdTasks = [];

let pdEditHandlers = [];
let pdSepHandlers = [];
let pdWorkingMembers = [];
let pdCreateMembers = [];
let pdCreateObservers = [];
let pdReassignMembers = [];
let pdReassignObservers = [];

document.addEventListener('DOMContentLoaded', () => {
    loadProjectDetails();
});

function closePdModal(id) {
    document.getElementById(id).style.display = 'none';
}

function loadProjectDetails() {
    fetch(`api.php?action=get_project_details&project_id=${pdProjectId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Error: ' + data.message);
                return;
            }
            pdProjectData = data.project;
            pdCanManage = Boolean(data.can_manage_project ?? pdIsLead);
            pdCompetitors = data.competitors || [];
            pdTasks = data.tasks || [];
            renderProjectOverview();
            renderCompetitors();
            renderProjectTasks();
        })
        .catch(err => console.error('Failed to load project details:', err));
}

function renderProjectOverview() {
    const p = pdProjectData;
    if (!p) return;

    document.getElementById('pd_title').textContent = p.project_name;
    document.getElementById('pd_client_name').textContent = p.company_name || 'N/A';
    document.getElementById('pd_lead_name').textContent = p.team_lead_name || 'Unassigned';
    document.getElementById('pd_timeline').textContent = `${p.start_date || 'Started'} → ${p.deadline || 'TBD'}`;

    document.getElementById('pd_dept_badge').textContent = '📁 ' + (p.department || 'General');
    document.getElementById('pd_priority_badge').textContent = (p.priority || 'Medium') + ' Priority';
    document.getElementById('pd_priority_badge').className = 'badge ' + (p.priority === 'Urgent' ? 'badge-danger' : (p.priority === 'High' ? 'badge-warning' : 'badge-primary'));

    const isCurr = parseInt(p.is_current ?? 1) === 1;
    const currBadge = document.getElementById('pd_current_badge');
    if (currBadge) {
        currBadge.textContent = isCurr ? '🟢 Current Project' : '📁 Archived / Past Project';
        currBadge.className = isCurr ? 'badge badge-success' : 'badge badge-secondary';
    }

    const statusSel = document.getElementById('pd_status_select');
    if (statusSel) statusSel.value = p.status || 'In Progress';
    const statusBadge = document.getElementById('pd_status_badge');
    if (statusBadge) statusBadge.textContent = p.status || 'In Progress';

    // Project Handlers Badges
    const handlersWrap = document.getElementById('pd_handlers_badges');
    if (handlersWrap) {
        const handlers = Array.isArray(p.handlers) ? p.handlers : [];
        if (handlers.length > 0) {
            handlersWrap.innerHTML = handlers.map(h => `<span class="badge" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; font-size:11px; padding:3px 9px;">🛡️ ${escapeHtml(h.full_name)} <small style="opacity:0.75;">(${escapeHtml(h.role_name || h.department || '')})</small></span>`).join('');
        } else {
            handlersWrap.innerHTML = `<span style="color:#94a3b8; font-style:italic; font-size:12px;">No extra handlers added yet</span>`;
        }
    }

    // Project Observers Badges (Lead, Super Admin, CRM)
    const obsWrap = document.getElementById('pd_observers_badges');
    if (obsWrap) {
        const obsList = Array.isArray(p.observers) ? p.observers : [];
        if (obsList.length > 0) {
            obsWrap.innerHTML = obsList.map(o => `<span class="badge" style="background:#faf5ff; color:#6b21a8; border:1px solid #e9d5ff; font-size:10.5px; padding:2px 8px;">👁️ ${escapeHtml(o.full_name)} <small style="opacity:0.8;">(${escapeHtml(o.role_name || o.department || '')})</small></span>`).join('');
        } else {
            obsWrap.innerHTML = `<span style="color:#94a3b8; font-style:italic; font-size:12px;">Lead, Super Admin &amp; CRM</span>`;
        }
    }

    // Working Employees on Project (Hero Badges + Sidebar Card)
    const workingList = Array.isArray(p.working_employees) ? p.working_employees : [];
    const workingWrap = document.getElementById('pd_working_badges');
    if (workingWrap) {
        if (workingList.length > 0) {
            workingWrap.innerHTML = workingList.map(w => `<span class="badge" style="background:#dbeafe; color:#1e40af; border:1px solid #93c5fd; font-size:11px; padding:3px 9px;">👤 ${escapeHtml(w.full_name)} <small style="opacity:0.8;">(${escapeHtml(w.role_name || w.department || '')})</small></span>`).join('');
        } else {
            workingWrap.innerHTML = `<span style="color:#94a3b8; font-style:italic; font-size:12px;">No working employees assigned yet</span>`;
        }
    }
    const sideWorking = document.getElementById('pd_side_working_list');
    if (sideWorking) {
        if (workingList.length > 0) {
            sideWorking.innerHTML = workingList.map(w => {
                const init = (w.full_name || 'U').trim().charAt(0).toUpperCase();
                return `
                    <div style="display:flex; align-items:center; gap:10px; padding:7px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                        <div style="width:30px; height:30px; border-radius:50%; background:#2563eb; color:#ffffff; font-weight:800; font-size:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            ${escapeHtml(init)}
                        </div>
                        <div style="min-width:0; flex:1;">
                            <div style="font-weight:700; color:#0f172a; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(w.full_name)}</div>
                            <div style="font-size:11px; color:#64748b;">${escapeHtml(w.role_name || 'Member')}${w.department ? ' &bull; ' + escapeHtml(w.department) : ''}</div>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            sideWorking.innerHTML = `
                <div style="text-align:center; padding:14px 10px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; color:#64748b; font-size:12px;">
                    No working employees added yet.
                    ${pdCanManage ? '<div style="margin-top:8px;"><button type="button" class="btn btn-primary btn-sm" onclick="openPdWorkingTeamModal()" style="font-size:11.5px;">+ Add Working Team</button></div>' : ''}
                </div>
            `;
        }
    }

    // Project Client Approval Tick Box
    const caChk = document.getElementById('pd_client_approved_chk');
    const caLbl = document.getElementById('pd_client_approved_label');
    const caBox = document.getElementById('pd_client_approval_box');
    const isClientApprovedProj = parseInt(p.client_approved || 0) === 1;
    if (caChk && caLbl && caBox) {
        caChk.checked = isClientApprovedProj;
        caLbl.textContent = isClientApprovedProj ? '✅ Approved by Client (Completed)' : 'Tick: Approved by Client';
        caBox.style.background = isClientApprovedProj ? '#f0fdf4' : '#f8fafc';
        caBox.style.borderColor = isClientApprovedProj ? '#86efac' : '#cbd5e1';
        caLbl.style.color = isClientApprovedProj ? '#15803d' : '#0f172a';
    }

    const totalT = parseInt(p.total_tasks || 0);
    const doneT = parseInt(p.completed_tasks || 0);
    const pct = totalT > 0 ? Math.round((doneT / totalT) * 100) : 0;

    document.getElementById('pd_progress_text').textContent = `${pct}% (${doneT} / ${totalT} Tasks Done)`;
    document.getElementById('pd_progress_bar').style.width = `${pct}%`;
    document.getElementById('pd_stat_total').textContent = totalT;
    document.getElementById('pd_stat_inprog').textContent = p.in_progress_tasks || 0;
    document.getElementById('pd_stat_pending').textContent = (parseInt(p.pending_tasks || 0) + parseInt(p.under_review_tasks || 0));
    document.getElementById('pd_stat_done').textContent = doneT;
    document.getElementById('pd_stat_comp').textContent = pdCompetitors.length;

    // About & Scope
    const aboutEl = document.getElementById('pd_about_project');
    aboutEl.textContent = p.about_project || 'No detailed project brief added yet. Click "Edit Project & About" to write project goals, background, and brand guidelines.';
    aboutEl.style.color = p.about_project ? '#1e293b' : '#94a3b8';

    document.getElementById('pd_description').textContent = p.description || 'No short scope description provided.';
    document.getElementById('pd_target_audience').textContent = p.target_audience || 'Not specified yet.';
    document.getElementById('pd_deliverables').textContent = p.deliverables_summary || 'Not specified yet.';

    // Side Card
    document.getElementById('pd_side_client').textContent = p.company_name || '--';
    document.getElementById('pd_side_contact').textContent = p.contact_person || '--';
    document.getElementById('pd_side_comm').textContent = `${p.client_email || ''} ${p.client_phone ? '• ' + p.client_phone : ''}`;
    document.getElementById('pd_side_budget').textContent = p.budget_info || 'Not specified';

    const urlBox = document.getElementById('pd_side_url');
    if (p.project_url) {
        const href = p.project_url.startsWith('http') ? p.project_url : 'https://' + p.project_url;
        urlBox.innerHTML = `<a href="${escapeHtml(href)}" target="_blank" style="color:#2563eb; font-weight:600; word-break:break-all;">🔗 ${escapeHtml(p.project_url)}</a>`;
    } else {
        urlBox.innerHTML = `<span style="color:#94a3b8;">No website or link added</span>`;
    }

    const cWrap = document.getElementById('pd_client_portal_link_wrap');
    if (cWrap && p.client_id) {
        cWrap.innerHTML = `<a href="client_details.php?id=${p.client_id}" class="btn btn-outline btn-sm" style="width:100%; justify-content:center;">View Full Client Profile &rarr;</a>`;
    }
}

function quickUpdateProjectStatus(newStatus) {
    if (!pdProjectData) return;
    fetch('api.php?action=update_project', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            project_id: pdProjectId,
            project_name: pdProjectData.project_name,
            status: newStatus
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadProjectDetails();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function renderCompetitors() {
    const container = document.getElementById('pd_competitors_container');
    if (!container) return;

    if (pdCompetitors.length === 0) {
        container.innerHTML = `
            <div style="grid-column:1/-1; text-align:center; padding:32px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px;">
                <div style="font-size:28px; margin-bottom:6px;">🏆</div>
                <strong style="color:#334155; display:block; margin-bottom:4px;">No Competitor Details Added Yet</strong>
                <p style="font-size:12.5px; color:#64748b; margin:0 0 12px 0;">Add competitors for this project so the team understands positioning, strengths, and market gaps.</p>
                ${pdIsLead ? `<button class="btn btn-outline btn-sm" onclick="openCompetitorModal(0)" style="color:#b45309; border-color:#fcd34d; background:#fffbeb;">+ Add First Competitor</button>` : ''}
            </div>
        `;
        return;
    }

    let html = '';
    pdCompetitors.forEach(c => {
        const webHref = c.website_url ? (c.website_url.startsWith('http') ? c.website_url : 'https://' + c.website_url) : '';
        html += `
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:16px; display:flex; flex-direction:column; gap:10px; box-shadow:0 1px 4px rgba(0,0,0,0.03);">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                    <div>
                        <h4 style="margin:0; font-size:15px; font-weight:800; color:#0f172a;">🏆 ${escapeHtml(c.competitor_name)}</h4>
                        ${webHref ? `<a href="${escapeHtml(webHref)}" target="_blank" style="font-size:12px; color:#2563eb; text-decoration:none; word-break:break-all;">🌐 ${escapeHtml(c.website_url)}</a>` : ''}
                        ${c.social_links ? `<div style="font-size:11.5px; color:#64748b; margin-top:2px;">📱 ${escapeHtml(c.social_links)}</div>` : ''}
                    </div>
                    ${pdIsLead ? `
                        <div style="display:flex; gap:4px;">
                            <button class="btn btn-outline btn-sm" onclick="openCompetitorModal(${c.id})" style="padding:3px 8px; font-size:11px;">✏️</button>
                            <button class="btn btn-outline btn-sm" onclick="deleteCompetitor(${c.id}, '${escapeHtml(c.competitor_name)}')" style="padding:3px 8px; font-size:11px; color:#dc2626; border-color:#fecaca;">🗑️</button>
                        </div>
                    ` : ''}
                </div>

                ${c.strengths ? `
                    <div style="background:#f0fdf4; border:1px solid #dcfce7; border-radius:8px; padding:8px 10px;">
                        <strong style="font-size:10.5px; color:#15803d; text-transform:uppercase; display:block; margin-bottom:2px;">✅ Strengths</strong>
                        <div style="font-size:12.5px; color:#14532d; white-space:pre-wrap;">${escapeHtml(c.strengths)}</div>
                    </div>
                ` : ''}

                ${c.weaknesses ? `
                    <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:8px; padding:8px 10px;">
                        <strong style="font-size:10.5px; color:#b45309; text-transform:uppercase; display:block; margin-bottom:2px;">⚡ Weaknesses / Gaps</strong>
                        <div style="font-size:12.5px; color:#78350f; white-space:pre-wrap;">${escapeHtml(c.weaknesses)}</div>
                    </div>
                ` : ''}

                ${c.notes ? `
                    <div style="background:#f8fafc; border-left:3px solid #94a3b8; padding:6px 10px; font-size:12px; color:#475569;">
                        <strong>Notes:</strong> ${escapeHtml(c.notes)}
                    </div>
                ` : ''}
            </div>
        `;
    });
    container.innerHTML = html;
}

function renderProjectTasks() {
    const tbody = document.getElementById('pdTasksTbody');
    const filter = document.getElementById('pdTaskStatusFilter')?.value || '';
    let filtered = pdTasks;
    if (filter) {
        filtered = pdTasks.filter(t => t.status === filter);
    }

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:#94a3b8;">No tasks found on this project yet. ${pdCanManage ? 'Click "+ Create Task on Project" above to create one!' : ''}</td></tr>`;
        return;
    }

    let html = '';
    filtered.forEach(t => {
        const slaClass = t.sla_status === 'SLA Breached' ? 'badge-danger' : (t.sla_status === 'Near SLA Warning' ? 'badge-warning' : 'badge-primary');
        const safeTitle = escapeHtml(t.title);
        const isSubtask = (t.task_type === 'subtask') || parseInt(t.parent_task_id || 0) > 0;
        const isHandlerApp = parseInt(t.handler_approved || 0) === 1 || ['PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL', 'COMPLETED'].includes(t.status);
        const isLeadApp = parseInt(t.lead_approved || 0) === 1 || ['LEAD APPROVED', 'COMPLETED'].includes(t.status) || (t.status === 'SENT FOR CLIENT APPROVAL' && parseInt(t.lead_approved || 0) === 1);
        const isClientApp = parseInt(t.client_approved_by_handler || 0) === 1 || t.client_approval_status === 'Approved';

        let statusControlHtml = '';
        if (pdCanManage) {
            statusControlHtml = `
                <select class="form-select" style="font-size:11.5px; font-weight:700; padding:4px 6px; width:175px;" onchange="updatePdTaskStatus(${t.id}, this.value)">
                    <option value="PENDING" ${t.status === 'PENDING' ? 'selected' : ''}>⏳ Pending</option>
                    <option value="IN PROGRESS" ${t.status === 'IN PROGRESS' ? 'selected' : ''}>⚡ In Progress</option>
                    <option value="UNDER REVIEW" ${t.status === 'UNDER REVIEW' ? 'selected' : ''}>🔍 Under Review</option>
                    <option value="PENDING LEAD APPROVAL" ${t.status === 'PENDING LEAD APPROVAL' ? 'selected' : ''}>📝 Pending Lead Approval</option>
                    <option value="REVISION REQUIRED" ${t.status === 'REVISION REQUIRED' ? 'selected' : ''}>🔄 Revision Needed</option>
                    <option value="SENT FOR CLIENT APPROVAL" ${t.status === 'SENT FOR CLIENT APPROVAL' ? 'selected' : ''}>📩 Client Approval</option>
                    <option value="COMPLETED" ${t.status === 'COMPLETED' ? 'selected' : ''}>✅ Completed</option>
                </select>
                <div style="margin-top:4px; font-size:10.5px;">
                    ${isLeadApp ? '<span style="color:#15803d; font-weight:700;">✓ Handler &amp; Lead Approved</span>' : (isHandlerApp ? '<span style="color:#d97706; font-weight:700;">⏳ Handler Approved &bull; Needs Lead</span>' : '<span style="color:#64748b;">Awaiting Handler Approval</span>')}
                </div>
            `;
        } else {
            statusControlHtml = `
                <select class="form-select" style="font-size:11.5px; font-weight:700; padding:4px 6px; width:165px;" onchange="updatePdTaskStatus(${t.id}, this.value)">
                    <option value="PENDING" ${t.status === 'PENDING' ? 'selected' : ''}>⏳ Pending</option>
                    <option value="IN PROGRESS" ${t.status === 'IN PROGRESS' ? 'selected' : ''}>⚡ In Progress</option>
                    <option value="UNDER REVIEW" ${t.status === 'UNDER REVIEW' ? 'selected' : ''}>🔍 Under Review</option>
                    ${t.status === 'PENDING LEAD APPROVAL' ? '<option value="PENDING LEAD APPROVAL" selected disabled>📝 Pending Lead Approval</option>' : ''}
                    ${t.status === 'REVISION REQUIRED' ? '<option value="REVISION REQUIRED" selected>🔄 Revision Needed</option>' : ''}
                    ${t.status === 'SENT FOR CLIENT APPROVAL' ? '<option value="SENT FOR CLIENT APPROVAL" selected disabled>📩 Client Approval</option>' : ''}
                    ${t.status === 'COMPLETED' ? '<option value="COMPLETED" selected disabled>✅ Completed</option>' : ''}
                </select>
            `;
        }

        let actionBtnsHtml = `<a href="task_details.php?id=${t.id}" class="btn btn-outline btn-sm" style="font-size:11px;">Details / Media</a>`;
        if (pdCanManage) {
            actionBtnsHtml += `
                <button class="btn btn-outline btn-sm" onclick="openPdReassignModal(${t.id})" style="font-size:11px; color:#2563eb; border-color:#93c5fd;">👤 Assign</button>
                <button class="btn btn-outline btn-sm" onclick="openCreateProjectTaskModal(${t.id}, true)" style="font-size:11px; color:#7c3aed; border-color:#c4b5fd;" title="Add sub-task under this task">↳ Sub-Task</button>
            `;
            if (t.status === 'UNDER REVIEW' || (!isHandlerApp && (t.status === 'IN PROGRESS' || t.status === 'PENDING'))) {
                actionBtnsHtml += `
                    <button class="btn btn-primary btn-sm" onclick="openPdReviewModal(${t.id}, '${safeTitle.replace(/'/g, "\\'")}', '${escapeHtml(t.remarks || '').replace(/'/g, "\\'")}')" style="font-size:11px; background:#0f766e; border-color:#0f766e;">✓ Handler Approve</button>
                `;
            }
            if (pdIsActualLead && !isLeadApp && t.status !== 'COMPLETED') {
                actionBtnsHtml += `
                    <button class="btn btn-success btn-sm" onclick="leadApprovePdTask(${t.id})" style="font-size:11px; background:#15803d; border-color:#15803d;">🛡️ Lead Approve</button>
                `;
            }
            if (isLeadApp || (pdIsActualLead && isHandlerApp)) {
                actionBtnsHtml += `
                    <label style="display:inline-flex; align-items:center; gap:5px; padding:3px 8px; border-radius:6px; border:1px solid ${isClientApp ? '#86efac' : '#cbd5e1'}; background:${isClientApp ? '#f0fdf4' : '#ffffff'}; cursor:pointer; font-size:11px; font-weight:700; color:${isClientApp ? '#15803d' : '#1e293b'}; margin-left:2px;">
                        <input type="checkbox" ${isClientApp ? 'checked' : ''} onchange="togglePdTaskClientApproval(${t.id}, this.checked)" style="accent-color:#16a34a; cursor:pointer;">
                        <span>Approved by Client</span>
                    </label>
                `;
            }
        } else if (t.status === 'IN PROGRESS' || t.status === 'REVISION REQUIRED' || t.status === 'PENDING') {
            actionBtnsHtml += `
                <button class="btn btn-success btn-sm" onclick="openPdSubmitWorkModal(${t.id}, '${safeTitle.replace(/'/g, "\\'")}')" style="font-size:11px;">📤 Submit Work</button>
            `;
        }

        html += `
            <tr style="${isSubtask ? 'background:#faf5ff;' : ''}">
                <td>
                    <strong style="color:${isSubtask ? '#7c3aed' : '#2563eb'};">${escapeHtml(t.task_code)}</strong>
                    ${isSubtask ? '<br><span class="badge" style="background:#ede9fe; color:#6d28d9; font-size:9.5px;">SUB-TASK</span>' : ''}
                </td>
                <td style="${isSubtask ? 'padding-left:26px;' : ''}">
                    ${isSubtask && t.parent_task_code ? `<div style="font-size:11px; color:#7c3aed; font-weight:700;">↳ Sub-task of #${escapeHtml(t.parent_task_code)}</div>` : ''}
                    <a href="task_details.php?id=${t.id}" style="font-weight:700; color:#0f172a; text-decoration:none;">${safeTitle}</a>
                    ${t.department ? `<br><span class="badge" style="background:#eff6ff; color:#1e40af; font-size:10px; margin-top:2px;">📁 ${escapeHtml(t.department)}</span>` : ''}
                </td>
                <td>
                    <span class="badge badge-secondary">${escapeHtml(t.assigned_to_name || 'Unassigned')}</span>
                    ${t.assignee_ids && t.assignee_ids.length > 1 ? `<span class="badge" style="background:#dbeafe; color:#1e40af; font-size:10px;">+${t.assignee_ids.length - 1}</span>` : ''}
                    ${t.observer_ids && t.observer_ids.length > 0 ? `<br><small style="color:#7e22ce; font-size:10.5px;">👁️ ${t.observer_ids.length} Observer(s)</small>` : ''}
                </td>
                <td><span class="badge ${t.priority === 'Urgent' ? 'badge-danger' : 'badge-warning'}">${escapeHtml(t.priority)}</span></td>
                <td>${statusControlHtml}</td>
                <td><span class="badge ${slaClass}">${escapeHtml(t.sla_status)}</span></td>
                <td><small style="color:#475569;">${t.deadline ? t.deadline.substring(0, 16) : 'N/A'}</small></td>
                <td style="text-align:right; white-space:nowrap;">
                    <div style="display:inline-flex; gap:5px; flex-wrap:wrap; align-items:center; justify-content:flex-end;">
                        ${actionBtnsHtml}
                    </div>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function updatePdTaskStatus(taskId, newStatus) {
    fetch('api.php?action=update_task_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: taskId, status: newStatus })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadProjectDetails();
        } else {
            alert('Error: ' + data.message);
            loadProjectDetails();
        }
    });
}

function toggleProjectClientApproval(checked) {
    fetch('api.php?action=approve_project_client_completion', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ project_id: pdProjectId, approved: checked ? 1 : 0 })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            loadProjectDetails();
        } else {
            alert('⚠️ ' + (data.message || 'Could not update client approval.'));
            loadProjectDetails();
        }
    });
}

function togglePdTaskClientApproval(taskId, checked) {
    fetch('api.php?action=toggle_task_client_approval', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: taskId, approved: checked ? 1 : 0 })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadProjectDetails();
        } else {
            alert('⚠️ ' + (data.message || 'Failed to update client approval.'));
            loadProjectDetails();
        }
    });
}

function openPdSubmitWorkModal(taskId, title) {
    document.getElementById('pd_sw_task_id').value = taskId;
    document.getElementById('pd_sw_task_title').textContent = title;
    document.getElementById('pd_sw_notes').value = '';
    document.getElementById('pd_sw_url').value = '';
    const f = document.getElementById('pd_sw_files');
    if (f) f.value = '';
    document.getElementById('pdSubmitWorkModal').style.display = 'flex';
}

async function submitPdEmployeeWork() {
    const taskIdVal = document.getElementById('pd_sw_task_id').value;
    const f = document.getElementById('pd_sw_files');
    if (f && f.files && f.files.length > 0) {
        const fd = new FormData();
        fd.append('task_id', taskIdVal);
        fd.append('caption', 'Work submission attachment');
        fd.append('attachment_stage', 'submission');
        for (let i = 0; i < f.files.length; i++) {
            fd.append('files[]', f.files[i]);
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
            task_id: taskIdVal,
            submission_notes: document.getElementById('pd_sw_notes').value,
            attachment_url: document.getElementById('pd_sw_url').value
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closePdModal('pdSubmitWorkModal');
            alert('✅ ' + data.message);
            loadProjectDetails();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function leadApprovePdTask(taskId) {
    const comment = prompt('Lead approval note (clears Project Handler to complete task):', 'Approved by Lead for completion');
    if (comment === null) return;
    fetch('api.php?action=lead_approve_task', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: taskId, review_action: 'APPROVE', comment: comment || 'Approved by Lead' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            loadProjectDetails();
        } else {
            alert('⚠️ ' + (data.message || 'Lead approval failed.'));
        }
    });
}

function openPdReviewModal(taskId, title, remarks) {
    document.getElementById('pd_rw_task_id').value = taskId;
    document.getElementById('pd_rw_task_title').textContent = title;
    document.getElementById('pd_rw_remarks').textContent = remarks ? ('Submission Notes: ' + remarks) : 'Pending Project Handler approval';
    document.getElementById('pd_rw_action').value = 'APPROVE';
    document.getElementById('pd_rw_comment').value = '';
    document.getElementById('pd_rw_changes').value = '';
    document.getElementById('pd_rw_changes_wrap').style.display = 'none';
    document.getElementById('pdReviewModal').style.display = 'flex';
}

function submitPdHandlerReview() {
    fetch('api.php?action=review_task_work', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            task_id: document.getElementById('pd_rw_task_id').value,
            review_action: document.getElementById('pd_rw_action').value,
            review_comment: document.getElementById('pd_rw_comment').value,
            required_changes: document.getElementById('pd_rw_changes').value
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closePdModal('pdReviewModal');
            alert('✅ ' + data.message);
            loadProjectDetails();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function openPdAssignHandlersModal() {
    if (!pdIsActualLead || !pdProjectData) return;
    pdSepHandlers = Array.isArray(pdProjectData.handler_ids) ? pdProjectData.handler_ids.map(x => parseInt(x)) : [];
    renderPdSeparateHandlersPills();
    document.getElementById('pdAssignHandlersModal').style.display = 'flex';
}

function renderPdSeparateHandlersPills() {
    const container = document.getElementById('pd_sep_handlers_pills');
    const badge = document.getElementById('pd_sep_handlers_badge');
    if (!container) return;
    if (badge) badge.textContent = `${pdSepHandlers.length} Selected`;
    container.innerHTML = pdStaffUsers.map(u => {
        const uid = parseInt(u.id);
        const sel = pdSepHandlers.includes(uid);
        return `<button type="button" onclick="const idx=pdSepHandlers.indexOf(${uid}); if(idx>-1) pdSepHandlers.splice(idx,1); else pdSepHandlers.push(${uid}); renderPdSeparateHandlersPills();" style="background:${sel ? '#0f766e' : '#ffffff'}; color:${sel ? '#ffffff' : '#334155'}; border:1px solid ${sel ? '#0d9488' : '#cbd5e1'}; font-size:11.5px; padding:4px 10px; border-radius:14px; font-weight:600; cursor:pointer;">${sel ? '✓' : '+'} ${escapeHtml(u.full_name)} <small style="opacity:0.75;">(${escapeHtml(u.role_name)})</small></button>`;
    }).join('');
}

function submitPdSeparateHandlers() {
    fetch('api.php?action=assign_project_handlers', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ project_id: pdProjectId, handlers: pdSepHandlers })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closePdModal('pdAssignHandlersModal');
            alert('🛡️ ' + data.message);
            loadProjectDetails();
        } else {
            alert('Error: ' + (data.message || 'Failed to update project handlers'));
        }
    });
}

// --- EDIT PROJECT MODAL ---
function renderPdEditHandlersPills() {
    const container = document.getElementById('ep_handlers_pills');
    const badge = document.getElementById('ep_handlers_badge');
    if (!container) return;
    if (badge) badge.textContent = `${pdEditHandlers.length} Selected`;
    container.innerHTML = pdStaffUsers.map(u => {
        const uid = parseInt(u.id);
        const sel = pdEditHandlers.includes(uid);
        return `<button type="button" onclick="const idx=pdEditHandlers.indexOf(${uid}); if(idx>-1) pdEditHandlers.splice(idx,1); else pdEditHandlers.push(${uid}); renderPdEditHandlersPills();" style="background:${sel ? '#0f766e' : '#ffffff'}; color:${sel ? '#ffffff' : '#334155'}; border:1px solid ${sel ? '#0d9488' : '#cbd5e1'}; font-size:11.5px; padding:3px 9px; border-radius:14px; font-weight:600; cursor:pointer;">${sel ? '✓' : '+'} ${escapeHtml(u.full_name)} <small style="opacity:0.75;">(${escapeHtml(u.role_name)})</small></button>`;
    }).join('');
}

function openEditProjectModal() {
    if (!pdProjectData) return;
    const p = pdProjectData;
    document.getElementById('ep_client_id').value = p.client_id || '';
    document.getElementById('ep_project_name').value = p.project_name || '';
    document.getElementById('ep_department').value = p.department || 'Development';
    document.getElementById('ep_team_lead_id').value = p.team_lead_id || '';
    document.getElementById('ep_status').value = p.status || 'In Progress';
    document.getElementById('ep_priority').value = p.priority || 'High';
    document.getElementById('ep_start_date').value = p.start_date || '';
    document.getElementById('ep_deadline').value = p.deadline ? p.deadline.substring(0, 10) : '';
    document.getElementById('ep_about_project').value = p.about_project || '';
    document.getElementById('ep_description').value = p.description || '';
    document.getElementById('ep_target_audience').value = p.target_audience || '';
    document.getElementById('ep_deliverables_summary').value = p.deliverables_summary || '';
    document.getElementById('ep_project_url').value = p.project_url || '';
    document.getElementById('ep_budget_info').value = p.budget_info || '';
    document.getElementById('ep_is_current').checked = parseInt(p.is_current ?? 1) === 1;
    pdEditHandlers = Array.isArray(p.handler_ids) ? p.handler_ids.map(x => parseInt(x)) : [];
    renderPdEditHandlersPills();
    document.getElementById('editProjectOverviewModal').style.display = 'flex';
}

function submitEditProject() {
    const payload = {
        project_id: pdProjectId,
        client_id: document.getElementById('ep_client_id').value,
        project_name: document.getElementById('ep_project_name').value,
        department: document.getElementById('ep_department').value,
        team_lead_id: document.getElementById('ep_team_lead_id').value,
        status: document.getElementById('ep_status').value,
        priority: document.getElementById('ep_priority').value,
        start_date: document.getElementById('ep_start_date').value,
        deadline: document.getElementById('ep_deadline').value,
        about_project: document.getElementById('ep_about_project').value,
        description: document.getElementById('ep_description').value,
        target_audience: document.getElementById('ep_target_audience').value,
        deliverables_summary: document.getElementById('ep_deliverables_summary').value,
        project_url: document.getElementById('ep_project_url').value,
        budget_info: document.getElementById('ep_budget_info').value,
        is_current: document.getElementById('ep_is_current').checked ? 1 : 0
    };
    if (pdIsActualLead) {
        payload.handlers = pdEditHandlers;
    }

    fetch('api.php?action=update_project', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closePdModal('editProjectOverviewModal');
            loadProjectDetails();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// --- COMPETITOR MODAL ---
function openCompetitorModal(compId) {
    document.getElementById('comp_id').value = compId || 0;
    if (compId > 0) {
        const c = pdCompetitors.find(x => parseInt(x.id) === parseInt(compId));
        if (!c) return;
        document.getElementById('compModalTitle').textContent = '✏️ Edit Competitor Detail';
        document.getElementById('comp_name').value = c.competitor_name || '';
        document.getElementById('comp_website').value = c.website_url || '';
        document.getElementById('comp_social').value = c.social_links || '';
        document.getElementById('comp_strengths').value = c.strengths || '';
        document.getElementById('comp_weaknesses').value = c.weaknesses || '';
        document.getElementById('comp_notes').value = c.notes || '';
    } else {
        document.getElementById('compModalTitle').textContent = '🏆 Add Competitor Detail';
        document.getElementById('comp_name').value = '';
        document.getElementById('comp_website').value = '';
        document.getElementById('comp_social').value = '';
        document.getElementById('comp_strengths').value = '';
        document.getElementById('comp_weaknesses').value = '';
        document.getElementById('comp_notes').value = '';
    }
    document.getElementById('competitorModal').style.display = 'flex';
}

function submitSaveCompetitor() {
    const payload = {
        id: document.getElementById('comp_id').value,
        project_id: pdProjectId,
        competitor_name: document.getElementById('comp_name').value,
        website_url: document.getElementById('comp_website').value,
        social_links: document.getElementById('comp_social').value,
        strengths: document.getElementById('comp_strengths').value,
        weaknesses: document.getElementById('comp_weaknesses').value,
        notes: document.getElementById('comp_notes').value
    };
    fetch('api.php?action=save_project_competitor', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closePdModal('competitorModal');
            loadProjectDetails();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteCompetitor(compId, compName) {
    if (!confirm(`Remove competitor "${compName}" from this project?`)) return;
    fetch('api.php?action=delete_project_competitor', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: compId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadProjectDetails();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// --- CREATE TASK / SUB-TASK ON CURRENT PROJECT MODAL ---
function onPdSubtaskToggle() {
    const chk = document.getElementById('cpt_is_subtask');
    const titleEl = document.getElementById('cpt_modal_title');
    if (titleEl) {
        titleEl.textContent = (chk && chk.checked) ? '↳ Create Sub-Task on Current Project' : '+ Create Task on Current Project';
    }
}

function openCreateProjectTaskModal(parentTaskId = 0, forceSubtask = false) {
    if (!pdProjectData) return;
    document.getElementById('cpt_project_subtitle').textContent = `Project: ${pdProjectData.project_name} (${pdProjectData.company_name})`;
    document.getElementById('cpt_title').value = '';
    document.getElementById('cpt_description').value = '';
    if (pdProjectData.department && document.getElementById('cpt_department')) {
        document.getElementById('cpt_department').value = pdProjectData.department;
    }

    const parentSel = document.getElementById('cpt_parent_task_id');
    const subChk = document.getElementById('cpt_is_subtask');
    if (parentSel) {
        let pOpts = '<option value="0">-- No Parent Task (Standalone Project Task/Sub-Task) --</option>';
        pdTasks.filter(t => !parseInt(t.parent_task_id || 0)).forEach(t => {
            pOpts += `<option value="${t.id}">#${escapeHtml(t.task_code)} - ${escapeHtml(t.title)}</option>`;
        });
        parentSel.innerHTML = pOpts;
        parentSel.value = String(parentTaskId || 0);
    }
    if (subChk) {
        subChk.checked = Boolean(forceSubtask || parseInt(parentTaskId || 0) > 0);
        onPdSubtaskToggle();
    }

    const assignSel = document.getElementById('cpt_assigned_to');
    const primId = assignSel ? parseInt(assignSel.value) || 0 : 0;
    pdCreateMembers = primId > 0 ? [primId] : [];
    pdCreateObservers = [];
    renderPdCreatePills();
    renderPdCreateObserverPills();
    document.getElementById('createProjectTaskModal').style.display = 'flex';
}

function onPdNewPrimaryChanged(val) {
    const uId = parseInt(val) || 0;
    if (uId > 0 && !pdCreateMembers.includes(uId)) {
        pdCreateMembers.push(uId);
    }
    renderPdCreatePills();
}

function togglePdCreateMember(uId) {
    const pos = pdCreateMembers.indexOf(uId);
    if (pos > -1) {
        pdCreateMembers.splice(pos, 1);
    } else {
        pdCreateMembers.push(uId);
    }
    renderPdCreatePills();
}

function renderPdCreatePills() {
    const container = document.getElementById('cpt_members_pills');
    const badge = document.getElementById('cpt_members_badge');
    if (badge) badge.textContent = `${pdCreateMembers.length} Selected`;

    const deptVal = (document.getElementById('cpt_department')?.value || '').toLowerCase();
    let ordered = pdStaffUsers;
    if (deptVal) {
        ordered = pdStaffUsers.filter(u => (u.department || '').toLowerCase() === deptVal)
            .concat(pdStaffUsers.filter(u => (u.department || '').toLowerCase() !== deptVal));
    }

    let html = '';
    ordered.forEach(u => {
        const isSel = pdCreateMembers.includes(parseInt(u.id));
        html += `<button type="button" onclick="togglePdCreateMember(${u.id})" style="background:${isSel ? '#2563eb' : '#fff'}; color:${isSel ? '#fff' : '#334155'}; border:1px solid ${isSel ? '#1d4ed8' : '#cbd5e1'}; font-size:11.5px; padding:3px 9px; border-radius:14px; font-weight:600; cursor:pointer;">${isSel ? '✓' : '+'} ${escapeHtml(u.full_name)} <small style="opacity:0.75;">(${escapeHtml(u.role_name)})</small></button>`;
    });
    container.innerHTML = html;
}

function addPdTaskObserver(val) {
    const uId = parseInt(val);
    if (uId > 0 && !pdCreateObservers.includes(uId)) {
        pdCreateObservers.push(uId);
        renderPdCreateObserverPills();
    }
    document.getElementById('cpt_observer_select').value = '';
}

function renderPdCreateObserverPills() {
    const container = document.getElementById('cpt_observers_pills');
    const badge = document.getElementById('cpt_observers_badge');
    if (badge) badge.textContent = `${pdCreateObservers.length} Observer(s)`;
    if (pdCreateObservers.length === 0) {
        container.innerHTML = `<span style="font-size:11px; color:#94a3b8; font-style:italic;">No observers added yet.</span>`;
        return;
    }
    let html = '';
    pdCreateObservers.forEach(oId => {
        const u = pdStaffUsers.find(x => parseInt(x.id) === oId);
        html += `<span class="badge" style="background:#f3e8ff; color:#6b21a8; border:1px solid #d8b4fe; font-size:11px; padding:3px 8px; border-radius:12px;">👁️ ${escapeHtml(u ? u.full_name : 'User #' + oId)} <button type="button" onclick="pdCreateObservers = pdCreateObservers.filter(x => x !== ${oId}); renderPdCreateObserverPills();" style="background:none; border:none; color:#7e22ce; font-weight:700; cursor:pointer;">&times;</button></span>`;
    });
    container.innerHTML = html;
}

function submitCreateProjectTask() {
    let assignedTo = parseInt(document.getElementById('cpt_assigned_to').value) || 0;
    if (!assignedTo && pdCreateMembers.length > 0) assignedTo = pdCreateMembers[0];

    const parentTaskId = parseInt(document.getElementById('cpt_parent_task_id')?.value || 0);
    const isSubtask = Boolean(document.getElementById('cpt_is_subtask')?.checked) || parentTaskId > 0;

    const payload = {
        project_id: pdProjectId,
        parent_task_id: parentTaskId,
        is_subtask: isSubtask ? 1 : 0,
        title: document.getElementById('cpt_title').value,
        description: document.getElementById('cpt_description').value,
        department: document.getElementById('cpt_department').value,
        assigned_to: assignedTo,
        assignees: pdCreateMembers,
        observers: pdCreateObservers,
        priority: document.getElementById('cpt_priority').value,
        start_date: document.getElementById('cpt_start_date').value,
        end_date: document.getElementById('cpt_end_date').value,
        sla_hours: document.getElementById('cpt_sla_hours').value
    };

    fetch('api.php?action=create_task', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closePdModal('createProjectTaskModal');
            alert('✅ ' + data.message);
            loadProjectDetails();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// --- REASSIGN TASK ---
function openPdReassignModal(taskId) {
    const t = pdTasks.find(x => parseInt(x.id) === parseInt(taskId));
    if (!t) return;
    document.getElementById('pdr_task_id').value = t.id;
    document.getElementById('pdr_task_title').textContent = t.title;
    document.getElementById('pdr_assigned_to').value = t.assigned_to || '';
    pdReassignMembers = (t.assignee_ids || []).map(x => parseInt(x));
    if (t.assigned_to && !pdReassignMembers.includes(parseInt(t.assigned_to))) {
        pdReassignMembers.unshift(parseInt(t.assigned_to));
    }
    pdReassignObservers = (t.observer_ids || []).map(x => parseInt(x));
    renderPdReassignPills();
    renderPdReassignObserverPills();
    document.getElementById('pdReassignModal').style.display = 'flex';
}

function renderPdReassignPills() {
    const container = document.getElementById('pdr_members_pills');
    let html = '';
    pdStaffUsers.forEach(u => {
        const isSel = pdReassignMembers.includes(parseInt(u.id));
        html += `<button type="button" onclick="const i=pdReassignMembers.indexOf(${u.id}); if(i>-1) pdReassignMembers.splice(i,1); else pdReassignMembers.push(${u.id}); renderPdReassignPills();" style="background:${isSel ? '#2563eb' : '#fff'}; color:${isSel ? '#fff' : '#334155'}; border:1px solid ${isSel ? '#1d4ed8' : '#cbd5e1'}; font-size:11.5px; padding:3px 9px; border-radius:14px; font-weight:600; cursor:pointer;">${isSel ? '✓' : '+'} ${escapeHtml(u.full_name)}</button>`;
    });
    container.innerHTML = html;
}

function renderPdReassignObserverPills() {
    const container = document.getElementById('pdr_observers_pills');
    if (pdReassignObservers.length === 0) {
        container.innerHTML = `<span style="font-size:11px; color:#94a3b8; font-style:italic;">No observers added yet.</span>`;
        return;
    }
    let html = '';
    pdReassignObservers.forEach(oId => {
        const u = pdStaffUsers.find(x => parseInt(x.id) === oId);
        html += `<span class="badge" style="background:#f3e8ff; color:#6b21a8; border:1px solid #d8b4fe; font-size:11px; padding:3px 8px; border-radius:12px;">👁️ ${escapeHtml(u ? u.full_name : 'User #' + oId)} <button type="button" onclick="pdReassignObservers = pdReassignObservers.filter(x => x !== ${oId}); renderPdReassignObserverPills();" style="background:none; border:none; color:#7e22ce; font-weight:700; cursor:pointer;">&times;</button></span>`;
    });
    container.innerHTML = html;
}

function submitPdReassignTask() {
    const taskId = document.getElementById('pdr_task_id').value;
    let assignedTo = parseInt(document.getElementById('pdr_assigned_to').value) || 0;
    if (!assignedTo && pdReassignMembers.length > 0) assignedTo = pdReassignMembers[0];

    fetch('api.php?action=reassign_task', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            task_id: taskId,
            assigned_to: assignedTo,
            assignees: pdReassignMembers,
            observers: pdReassignObservers
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closePdModal('pdReassignModal');
            loadProjectDetails();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// =========================================================================
// 👥 WORKING EMPLOYEES ON PROJECT (LEAD & PROJECT HANDLER ACCESS)
// =========================================================================
function openPdWorkingTeamModal() {
    if (!pdProjectData) return;
    pdWorkingMembers = Array.isArray(pdProjectData.working_employee_ids)
        ? pdProjectData.working_employee_ids.map(x => parseInt(x))
        : [];

    const searchInput = document.getElementById('pd_wt_search');
    if (searchInput) searchInput.value = '';

    const deptSel = document.getElementById('pd_wt_dept_filter');
    if (deptSel) {
        const depts = Array.from(new Set(pdStaffUsers.map(u => (u.department || '').trim()).filter(Boolean))).sort();
        deptSel.innerHTML = '<option value="">All Departments</option>' +
            depts.map(d => `<option value="${escapeHtml(d)}">${escapeHtml(d)}</option>`).join('');
    }

    renderPdWorkingTeamPills();
    document.getElementById('pdWorkingTeamModal').style.display = 'flex';
}

function togglePdWorkingMember(uid) {
    const id = parseInt(uid);
    const idx = pdWorkingMembers.indexOf(id);
    if (idx > -1) pdWorkingMembers.splice(idx, 1);
    else pdWorkingMembers.push(id);
    renderPdWorkingTeamPills();
}

function renderPdWorkingTeamPills() {
    const container = document.getElementById('pd_wt_pills');
    const badge = document.getElementById('pd_wt_badge');
    if (!container) return;

    if (badge) badge.textContent = `${pdWorkingMembers.length} Selected`;

    const query = (document.getElementById('pd_wt_search')?.value || '').trim().toLowerCase();
    const deptFilter = (document.getElementById('pd_wt_dept_filter')?.value || '').trim().toLowerCase();

    const filtered = pdStaffUsers.filter(u => {
        if (deptFilter && (u.department || '').toLowerCase() !== deptFilter) return false;
        if (query) {
            const hay = `${u.full_name || ''} ${u.role_name || ''} ${u.department || ''}`.toLowerCase();
            if (!hay.includes(query)) return false;
        }
        return true;
    });

    if (filtered.length === 0) {
        container.innerHTML = '<span style="font-size:12px; color:#94a3b8; font-style:italic;">No employees match your filter.</span>';
        return;
    }

    let html = '';
    filtered.forEach(u => {
        const uid = parseInt(u.id);
        const isSel = pdWorkingMembers.includes(uid);
        html += `
            <button type="button" onclick="togglePdWorkingMember(${uid})"
                style="background:${isSel ? '#2563eb' : '#ffffff'}; color:${isSel ? '#ffffff' : '#334155'}; border:1px solid ${isSel ? '#1d4ed8' : '#cbd5e1'}; font-size:12px; padding:5px 11px; border-radius:16px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:all 0.12s ease;">
                <span>${isSel ? '✓' : '+'} ${escapeHtml(u.full_name)}</span>
                <small style="opacity:0.8; font-size:10.5px;">(${escapeHtml(u.role_name || u.department || '')})</small>
            </button>
        `;
    });
    container.innerHTML = html;
}

function submitPdWorkingTeam() {
    const btn = document.getElementById('pd_wt_submit_btn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving & Notifying...';
    }
    fetch('api.php?action=assign_project_working_employees', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            project_id: pdProjectId,
            working_employee_ids: pdWorkingMembers
        })
    })
    .then(res => res.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Save & Notify Working Employees';
        }
        if (data.success) {
            closePdModal('pdWorkingTeamModal');
            alert('✅ ' + data.message);
            loadProjectDetails();
        } else {
            alert('Error: ' + (data.message || 'Could not update working team.'));
        }
    })
    .catch(() => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Save & Notify Working Employees';
        }
        alert('Network error updating working team.');
    });
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

