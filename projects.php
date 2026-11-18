<?php
// projects.php - Module 4 Project & Task Management Engine

require_once __DIR__ . '/auth.php';
checkAuth('task-management');

$user = getCurrentUser();
$db = getDbConnection();

$isLead = isLeadOrAdmin();
$handledProjectIds = getUserHandledProjectIds($db, $user['id']);
$isAnyHandler = !empty($handledProjectIds);
$canManageAnyTasks = $isLead || $isAnyHandler;
$activeView = (($_GET['view'] ?? '') === 'projects') ? 'projects' : 'tasks';

$pageBreadcrumb = "Module 4";
$pageTitle = !$canManageAnyTasks ? 'My Assigned Tasks Workspace' : 'Current Projects & Task Hub';
$pageSubtitle = !$canManageAnyTasks 
    ? 'Manage your assigned tasks, review current project briefs, and submit completed work.' 
    : 'Manage current projects, project handlers, sub-tasks, approvals, and client sign-off.';
require_once __DIR__ . '/header.php';

// Fetch active internal staff (exclude Client accounts) for task & lead assignment
$developers = $db->query("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 AND r.name != 'Client' ORDER BY u.full_name ASC")->fetchAll();

// Fetch clients & projects & departments
$clients = $db->query("SELECT * FROM clients ORDER BY company_name ASC")->fetchAll();
$projects = $db->query("SELECT p.*, c.company_name, u.full_name as team_lead_name FROM projects p JOIN clients c ON p.client_id = c.id LEFT JOIN users u ON p.team_lead_id = u.id ORDER BY COALESCE(p.is_current, 1) DESC, p.id DESC")->fetchAll();
$departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$selectedProjectId = intval($_GET['project_id'] ?? 0);
?>

<style>
    .ws-tabs-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .ws-switcher {
        display: inline-flex;
        background: #f1f5f9;
        padding: 5px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        gap: 4px;
    }
    .ws-tab-btn {
        border: none;
        background: transparent;
        padding: 9px 18px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 700;
        color: #64748b;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }
    .ws-tab-btn.active {
        background: #ffffff;
        color: #1d4ed8;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
    }
    .projects-portfolio-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 20px;
    }
    .proj-portfolio-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 14px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .proj-portfolio-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.08);
        border-color: #bfdbfe;
    }
    .cp-comp-row {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 10px;
    }
</style>

<!-- Workspace View Tabs & Lead Action Pills -->
<div class="ws-tabs-bar">
    <div class="ws-switcher">
        <button type="button" id="tabBtn_projects" class="ws-tab-btn <?= $activeView === 'projects' ? 'active' : '' ?>" onclick="switchWorkspaceView('projects')">
            <span>📁</span>
            <span>Current Projects List</span>
            <span id="currentProjCountBadge" class="badge" style="background:#dbeafe; color:#1e40af; font-size:11px; padding:2px 8px;"><?= count(array_filter($projects, fn($pr) => intval($pr['is_current'] ?? 1) === 1)) ?></span>
        </button>
        <button type="button" id="tabBtn_tasks" class="ws-tab-btn <?= $activeView === 'tasks' ? 'active' : '' ?>" onclick="switchWorkspaceView('tasks')">
            <span>📋</span>
            <span>Tasks Workspace</span>
        </button>
    </div>

    <div class="action-pills-row" style="margin-bottom:0;">
        <?php if ($isLead): ?>
            <button class="btn-pill-primary" onclick="openCreateProjectModal()">
                <i data-feather="folder-plus" class="icon-prefix"></i>
                <span>+ Add Current Project</span>
            </button>
            <button class="btn-pill-white" onclick="openImportProjectsModal()">
                <i data-feather="upload" class="icon-folder"></i>
                <span>📥 Import Projects</span>
            </button>
            <button class="btn-pill-white" onclick="openCreateTaskModal()">
                <i data-feather="plus" class="icon-tag"></i>
                <span>+ Create & Assign Task</span>
            </button>
            <button class="btn-pill-white tasks-only-pill" style="<?= $activeView === 'projects' ? 'display:none;' : '' ?>" onclick="document.getElementById('filterStatus').value=''; loadTasks();">
                <i data-feather="list" class="icon-clock"></i>
                <span>All Tasks Queue</span>
            </button>
            <button class="btn-pill-white tasks-only-pill" style="<?= $activeView === 'projects' ? 'display:none;' : '' ?>" onclick="document.getElementById('filterStatus').value='IN PROGRESS'; loadTasks();">
                <i data-feather="clock" class="icon-edit"></i>
                <span>Active SLAs</span>
            </button>
        <?php else: ?>
            <button class="btn-pill-primary" onclick="switchWorkspaceView('tasks'); document.getElementById('filterStatus').value='IN PROGRESS'; loadTasks();">
                <i data-feather="play-circle" class="icon-prefix"></i>
                <span>My Active Tasks</span>
                <i data-feather="arrow-right" style="width:16px; height:16px; margin-left:4px;"></i>
            </button>
            <button class="btn-pill-white" onclick="switchWorkspaceView('tasks'); document.getElementById('filterStatus').value='REVISION REQUIRED'; loadTasks();">
                <i data-feather="alert-triangle" class="icon-edit"></i>
                <span>Revisions Needed</span>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- ====================================================================== -->
<!-- VIEW 1: CURRENT PROJECTS PORTFOLIO LIST (Overview, About & Competitors) -->
<!-- ====================================================================== -->
<div id="viewSection_projects" style="display:<?= $activeView === 'projects' ? 'block' : 'none' ?>;">
    <div class="card" style="padding:16px; margin-bottom:20px;">
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <div style="flex:2; min-width:220px;">
                <input type="text" id="projFilterSearch" class="form-control" placeholder="🔍 Search current projects, client, overview..." oninput="debounceLoadProjects()">
            </div>
            <div style="flex:1; min-width:170px;">
                <select id="projFilterCurrent" class="form-select" onchange="loadCurrentProjects()">
                    <option value="1" selected>Current Projects Only</option>
                    <option value="">All Projects (Current & Archived)</option>
                    <option value="0">Archived / Completed Only</option>
                </select>
            </div>
            <div style="flex:1; min-width:170px;">
                <select id="projFilterClient" class="form-select" onchange="loadCurrentProjects()">
                    <option value="0">All Clients</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1; min-width:160px;">
                <select id="projFilterDept" class="form-select" onchange="loadCurrentProjects()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-outline btn-sm" onclick="loadCurrentProjects()">
                <i data-feather="refresh-cw"></i> Refresh
            </button>
            <?php if ($isLead): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="openImportProjectsModal()">
                📥 Import Projects CSV
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="currentProjectsGrid" class="projects-portfolio-grid">
        <div class="card" style="grid-column:1 / -1; text-align:center; padding:40px; color:var(--text-muted);">
            Loading current projects portfolio...
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- VIEW 2: TASKS WORKSPACE                                                -->
<!-- ====================================================================== -->
<div id="viewSection_tasks" style="display:<?= $activeView === 'tasks' ? 'block' : 'none' ?>;">

<!-- Metrics Cards Bar -->
<div class="grid-4" style="margin-bottom: 24px;" id="metricsCardsArea">
    <!-- Loaded dynamically via JS -->
</div>

<!-- Filter Bar -->
<div class="card" style="padding:16px; margin-bottom:20px;">
    <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
        <div style="flex:1; min-width:180px;">
            <select id="filterClient" class="form-select" onchange="onClientFilterChanged()">
                <option value="0">All Clients</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex:1; min-width:200px;">
            <select id="filterProject" class="form-select" onchange="loadTasks()">
                <option value="0">All Projects</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>" data-client="<?= $p['client_id'] ?>" <?= ($p['id'] == $selectedProjectId) ? 'selected' : '' ?>><?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['company_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex:1; min-width:160px;">
            <select id="filterStatus" class="form-select" onchange="loadTasks()">
                <option value="">All Task Statuses</option>
                <option value="PENDING">Pending</option>
                <option value="IN PROGRESS">In Progress</option>
                <option value="UNDER REVIEW">Under Review</option>
                <option value="REVISION REQUIRED">Revision Required</option>
                <option value="COMPLETED">Completed</option>
            </select>
        </div>

        <div style="flex:1; min-width:160px;">
            <select id="filterDepartment" class="form-select" onchange="loadTasks()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($canManageAnyTasks): ?>
            <div style="flex:1; min-width:160px;">
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

<?php if ($selectedProjectId > 0): ?>
    <?php $canManageSelectedProject = $isLead || in_array($selectedProjectId, $handledProjectIds); ?>
    <!-- Handover Project Loaded Highlight Banner for Team Lead -->
    <div class="card" style="background:#eff6ff; border:1px solid #bfdbfe; padding:14px 18px; margin-bottom:20px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:40px; height:40px; border-radius:10px; background:#dbeafe; color:#1d4ed8; display:flex; align-items:center; justify-content:center; font-size:20px;">🚀</div>
            <div>
                <strong style="font-size:14px; color:#1e40af;">Project Handover Workspace Loaded</strong>
                <p style="font-size:12px; color:#3b82f6; margin:2px 0 0 0;">
                    Viewing deliverables for Project #<?= $selectedProjectId ?>. Leads & Project Handlers can create tasks/sub-tasks or review approvals.
                </p>
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <a href="project_details.php?id=<?= $selectedProjectId ?>" class="btn btn-primary btn-sm">📊 Overview & Competitors</a>
            <?php if ($isLead): ?>
                <button type="button" class="btn btn-outline btn-sm" style="background:#fff; color:#0f766e; border-color:#99f6e4; font-weight:700;" onclick="openAssignProjectHandlersById(<?= $selectedProjectId ?>)">🛡️ Assign Project Handler</button>
            <?php endif; ?>
            <?php if ($canManageSelectedProject): ?>
                <button type="button" class="btn btn-outline btn-sm" style="background:#fff; color:#1d4ed8; border-color:#93c5fd;" onclick="openCreateProjectTask(<?= $selectedProjectId ?>)">+ Create Task on Project</button>
                <button type="button" class="btn btn-outline btn-sm" style="background:#fff; color:#7c3aed; border-color:#ddd6fe;" onclick="openCreateProjectTask(<?= $selectedProjectId ?>, '', 0, true)">↳ + Create Sub-Task</button>
            <?php endif; ?>
            <a href="projects.php?view=projects" class="btn btn-outline btn-sm" style="color:#2563eb; border-color:#93c5fd; background:#ffffff;">Current Projects List</a>
        </div>
    </div>
<?php endif; ?>

<!-- DEVELOPER KANBAN BOARD / TEAM LEAD TASK TABLE -->
<?php if (!$canManageAnyTasks): ?>

    <!-- Developer Column Workflow Layout -->
    <div class="grid-4" style="align-items:start;">
        <!-- Column 1: Pending -->
        <div style="background:#f8fafc; border-radius:10px; padding:16px; border:1px solid #e2e8f0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h4 style="font-size:13px; font-weight:700; color:#475569; margin:0;">PENDING ACCEPTANCE</h4>
                <span id="cntPending" class="badge badge-secondary">0</span>
            </div>
            <div id="colPending" style="display:flex; flex-direction:column; gap:12px;"></div>
        </div>

        <!-- Column 2: In Progress -->
        <div style="background:#f0f9ff; border-radius:10px; padding:16px; border:1px solid #bae6fd;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h4 style="font-size:13px; font-weight:700; color:#0369a1; margin:0;">ACTIVE WORK</h4>
                <span id="cntInProgress" class="badge badge-primary">0</span>
            </div>
            <div id="colInProgress" style="display:flex; flex-direction:column; gap:12px;"></div>
        </div>

        <!-- Column 3: Under Review -->
        <div style="background:#fefce8; border-radius:10px; padding:16px; border:1px solid #fef08a;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h4 style="font-size:13px; font-weight:700; color:#854d0e; margin:0;">UNDER REVIEW</h4>
                <span id="cntUnderReview" class="badge badge-warning">0</span>
            </div>
            <div id="colUnderReview" style="display:flex; flex-direction:column; gap:12px;"></div>
        </div>

        <!-- Column 4: Revision Required -->
        <div style="background:#fef2f2; border-radius:10px; padding:16px; border:1px solid #fecaca;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h4 style="font-size:13px; font-weight:700; color:#991b1b; margin:0;">REVISION REQUIRED</h4>
                <span id="cntRevision" class="badge badge-danger">0</span>
            </div>
            <div id="colRevision" style="display:flex; flex-direction:column; gap:12px;"></div>
        </div>
    </div>

<?php else: ?>

    <!-- Team Lead / Super Admin Table View -->
    <div class="card" style="padding:0; overflow:hidden;">
        <div class="table-responsive">
            <table class="table" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th style="width:100px;">Task Code</th>
                        <th>Task Deliverable</th>
                        <th>Project / Client</th>
                        <th>Assigned Staff & Observers</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>SLA Status</th>
                        <th>Deadline</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="taskTableBody">
                    <tr><td colspan="9" style="text-align:center; padding:30px; color:var(--text-muted);">Loading tasks workspace...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>
</div><!-- /#viewSection_tasks -->

<!-- MODAL 0: ADD / EDIT CURRENT PROJECT (Overview, About & Competitors) -->
<div id="createProjectModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:780px; width:95%; max-height:92vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title" id="cp_modal_title">Add Current Project (Overview & Competitors)</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('createProjectModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateProject();">
            <input type="hidden" id="cp_project_id" value="">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Client *</label>
                        <select id="cp_client_id" class="form-select" required>
                            <option value="">-- Choose Client --</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Name *</label>
                        <input type="text" id="cp_project_name" class="form-control" required placeholder="e.g. Modern E-commerce Growth & SEO">
                    </div>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select id="cp_department" class="form-select">
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['name']) ?>" <?= ($user['department'] ?? '') === $dept['name'] ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Lead</label>
                        <select id="cp_team_lead_id" class="form-select">
                            <?php foreach ($developers as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $d['id'] == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['role_name'] ?? $d['department']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Status</label>
                        <select id="cp_status" class="form-select">
                            <option value="Planning">Planning</option>
                            <option value="In Progress" selected>In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Review">Review</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="cp_priority" class="form-select">
                            <option value="Urgent">Urgent</option>
                            <option value="High" selected>High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" id="cp_start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target Deadline *</label>
                        <input type="date" id="cp_deadline" class="form-control" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                </div>

                <div class="form-group" style="display:flex; align-items:center; gap:10px; background:#eff6ff; border:1px solid #bfdbfe; padding:10px 14px; border-radius:10px; margin-bottom:14px;">
                    <input type="checkbox" id="cp_is_current" checked style="width:18px; height:18px; cursor:pointer;">
                    <label for="cp_is_current" style="margin:0; font-size:13px; font-weight:700; color:#1e40af; cursor:pointer;">
                        Show in Current Projects List (Active Ongoing Project)
                    </label>
                </div>

                <div class="form-group" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="form-label" style="margin:0; font-size:12.5px; color:#0f172a; font-weight:700;">
                            🛡️ Project Handlers (Multiple Allowed — Can Assign Tasks, Create Sub-Tasks & Approve Work)
                        </label>
                        <span id="cp_handlers_badge" class="badge" style="background:#eff6ff; color:#1d4ed8; font-size:10.5px;">0 Selected</span>
                    </div>
                    <div id="cp_handlers_pills" style="display:flex; flex-wrap:wrap; gap:6px; max-height:140px; overflow-y:auto;"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">About the Project (Vision, Background & Brand Goals)</label>
                    <textarea id="cp_about_project" class="form-control" rows="3" placeholder="Explain what this project is about, brand voice, core objectives, and business background..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Project Overview & Technical Scope</label>
                    <textarea id="cp_description" class="form-control" rows="3" placeholder="High-level project overview, scope, and execution strategy..."></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Target Audience</label>
                        <input type="text" id="cp_target_audience" class="form-control" placeholder="e.g. D2C shoppers in UAE aged 24-45">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Budget / Retainer Info</label>
                        <input type="text" id="cp_budget_info" class="form-control" placeholder="e.g. Monthly Retainer / Milestone #1">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Website / Staging / Asset URL</label>
                    <input type="text" id="cp_project_url" class="form-control" placeholder="https://example.com or Drive/Figma link">
                </div>

                <div class="form-group">
                    <label class="form-label">Key Deliverables Summary</label>
                    <textarea id="cp_deliverables_summary" class="form-control" rows="2" placeholder="List main expected deliverables, channels, or milestones..."></textarea>
                </div>

                <!-- Competitors Details Section -->
                <div style="background:#fdf4ff; border:1px solid #e9d5ff; border-radius:12px; padding:14px; margin-top:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:10px; flex-wrap:wrap;">
                        <div>
                            <strong style="font-size:13.5px; color:#6b21a8;">🥊 Competitors Details</strong>
                            <div id="cp_comp_hint" style="font-size:11.5px; color:#7e22ce;">Add key competitors to benchmark against for this project.</div>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" style="background:#fff; color:#7e22ce; border-color:#d8b4fe;" onclick="addProjectModalCompetitorRow()">+ Add Competitor</button>
                    </div>
                    <div id="cp_competitors_list"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createProjectModal')">Cancel</button>
                <button type="submit" id="cp_submit_btn" class="btn btn-primary">Save Current Project</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 0B: BULK IMPORT CURRENT PROJECTS (CSV / Excel Paste) -->
<div id="importProjectsModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:860px; width:95%; max-height:92vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">📥 Import Current Projects List (Lead)</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('importProjectsModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitImportProjects();">
            <div class="modal-body">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; background:#f1f5f9; padding:12px 14px; border-radius:10px; margin-bottom:14px;">
                    <div style="flex:1; min-width:260px;">
                        <strong style="font-size:13px; color:#0f172a;">Supported Columns (Header-Mapped or Sample Order):</strong>
                        <div style="font-size:11.5px; color:#475569; margin-top:3px; line-height:1.5;">
                            <code>Project Name *, Client Name, Department, Lead Name, Status, Priority, Deadline, About Project, Overview, Target Audience, Deliverables, Project URL, Budget, Competitors</code>
                        </div>
                        <div style="font-size:11px; color:#64748b; margin-top:2px;">
                            New clients are auto-created if not found; separate multiple competitors with <code>;</code> (e.g. <code>Rival A (https://rival.com); Rival B</code>).
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" style="background:#ffffff;" onclick="downloadSampleProjectsCsv()">
                        📄 Download Sample CSV
                    </button>
                </div>

                <!-- Duplicate Handling Mode -->
                <div class="form-group" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; margin-bottom:14px;">
                    <label class="form-label" style="color:#1e40af; font-weight:700; font-size:12.5px; margin-bottom:6px;">If Same Project Name Exists for Client:</label>
                    <div style="display:flex; gap:18px; flex-wrap:wrap;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px; font-weight:600; color:#1d4ed8; margin:0;">
                            <input type="radio" name="imp_proj_dup_mode" value="skip" checked>
                            <span>Skip Duplicate Row</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12.5px; color:#334155; margin:0;">
                            <input type="radio" name="imp_proj_dup_mode" value="update">
                            <span>Update Overview/Details &amp; Add Any New Competitors</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Option A: Upload CSV / Text File</label>
                    <input type="file" id="impProj_file_input" class="form-control" accept=".csv,.txt,.tsv" onchange="handleProjectsFileUpload(event)">
                </div>

                <div class="form-group">
                    <label class="form-label">Option B: Or Paste CSV / Excel / Google Sheets Rows</label>
                    <textarea id="impProj_csv_text" class="form-control" rows="5" placeholder="Project Name,Client Name,Department,Lead Name,Status,Priority,Deadline,About Project,Overview,Target Audience,Deliverables,Project URL,Budget,Competitors" oninput="parseProjectsCsvInput()"></textarea>
                </div>

                <!-- Live Parsed Preview -->
                <div id="impProjPreviewContainer" style="display:none; margin-top:14px;">
                    <h5 style="font-size:13px; font-weight:700; color:#0f172a; margin-bottom:8px;">
                        Preview Project Rows (<span id="impProjRowCount">0</span> ready)
                    </h5>
                    <div class="table-responsive" style="max-height:240px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:8px;">
                        <table class="table" style="font-size:12px; margin:0;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th>#</th>
                                    <th>Project &amp; Client</th>
                                    <th>Lead / Dept</th>
                                    <th>Status &amp; Deadline</th>
                                    <th>About / Overview</th>
                                    <th>Competitors</th>
                                </tr>
                            </thead>
                            <tbody id="impProjPreviewTbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importProjectsModal')">Cancel</button>
                <button type="submit" id="impProjSubmitBtn" class="btn btn-primary" disabled>Import Projects Now</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 1: CREATE TASK (Team Lead / Admin) -->
<div id="createTaskModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:720px; width:95%; max-height:92vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">Create & Assign New Task</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('createTaskModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateTask();">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Project *</label>
                    <select id="ct_project_id" class="form-select" required onchange="loadCreateModalParentTasks()">
                        <option value="">-- Choose Project --</option>
                        <?php foreach ($projects as $p): ?>
                            <?php if (!$isLead && !in_array(intval($p['id']), $handledProjectIds, true)) continue; ?>
                            <option value="<?= $p['id'] ?>" <?= ($p['id'] == $selectedProjectId) ? 'selected' : '' ?>><?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['company_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="background:#f5f3ff; border:1px solid #ddd6fe; border-radius:8px; padding:10px 12px; margin-bottom:12px;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                        <input type="checkbox" id="ct_is_subtask" onchange="toggleCreateSubtaskFields()" style="width:16px; height:16px; cursor:pointer;">
                        <label for="ct_is_subtask" style="margin:0; font-size:12.5px; font-weight:700; color:#5b21b6; cursor:pointer;">↳ Create as Project Sub-Task (optional parent task link)</label>
                    </div>
                    <div id="ct_parent_task_wrap" style="display:none; margin-top:6px;">
                        <label class="form-label" style="font-size:11.5px; color:#5b21b6;">Parent Task (optional)</label>
                        <select id="ct_parent_task_id" class="form-select" style="font-size:12.5px;">
                            <option value="0">-- Standalone Project Sub-Task --</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" id="ct_title" class="form-control" required placeholder="e.g. Develop API Authentication Controller">
                </div>

                <div class="form-group">
                    <label class="form-label">Task Description & Technical Requirements</label>
                    <textarea id="ct_description" class="form-control" rows="3" placeholder="Provide clear technical requirements for developer..."></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Target Department *</label>
                        <select id="ct_department" class="form-select" required onchange="renderCreatePills()">
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['name']) ?>" <?= $dept['name'] === 'Development' ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Primary Assigned Developer / Lead *</label>
                        <select id="ct_assigned_to" class="form-select" required onchange="onNewTaskPrimaryChanged(this.value)">
                            <?php foreach ($developers as $d): ?>
                                <option value="<?= $d['id'] ?>" data-dept="<?= htmlspecialchars($d['department'] ?? '') ?>">
                                    <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['role_name'] ?? $d['department']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Multiple Person Assignment for Team Lead -->
                <div class="form-group" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="form-label" style="font-size:12px; font-weight:700; color:#1e293b; margin:0; display:flex; align-items:center; gap:6px;">
                            <span>👥 Assign Team Members (Multiple Persons)</span>
                            <span id="ct_members_count_badge" class="badge" style="background:#dbeafe; color:#1e40af; font-size:10px; font-weight:700;">1 Selected</span>
                        </label>
                        <small style="color:#64748b; font-size:11px;">Click members to assign/unassign</small>
                    </div>
                    <div id="ct_members_pills_container" style="display:flex; flex-wrap:wrap; gap:6px;">
                        <!-- Rendered dynamically -->
                    </div>
                </div>

                <!-- Observers (Watchers / Stakeholders) for Team Lead -->
                <div class="form-group" style="background:#fdf4ff; border:1px solid #f0abfc; border-radius:8px; padding:12px; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="form-label" style="font-size:12px; font-weight:700; color:#86198f; margin:0; display:flex; align-items:center; gap:6px;">
                            <span>👁️ Observers (Watchers / Stakeholders)</span>
                            <span id="ct_observers_count_badge" class="badge" style="background:#fae8ff; color:#a21caf; font-size:10px; font-weight:700;">0 Observers</span>
                        </label>
                        <small style="color:#a21caf; font-size:11px;">Receives progress & review updates</small>
                    </div>
                    <div id="ct_observers_pills_container" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; min-height:24px; align-items:center;">
                        <!-- Rendered dynamically -->
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <select id="ct_add_observer_select" class="form-select" style="font-size:12px; border-color:#d8b4fe;" onchange="addNewTaskObserver(this.value)">
                            <option value="">+ Add Observer...</option>
                            <?php foreach ($developers as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['role_name'] ?? $d['department']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Start Date *</label>
                        <input type="datetime-local" id="ct_start_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">End Date / Deadline *</label>
                        <input type="datetime-local" id="ct_end_date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+24 hours')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">SLA Hours</label>
                    <input type="number" id="ct_sla_hours" class="form-control" min="1" max="1000" value="24" placeholder="Enter SLA hours (e.g. 24, 48, 72)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight:700;">Create & Assign Task</button>
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

                <div class="form-group">
                    <label class="form-label">Attach Media / Screenshots / Files (Optional)</label>
                    <input type="file" id="sw_media_files" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.mp4,.mov,.webm,.mp3" class="form-control" style="padding:6px; font-size:12.5px;">
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
                        <option value="APPROVE">APPROVE (Handler Approved &rarr; Send for Client Approval)</option>
                        <option value="REQUEST_REVISION">REQUEST REVISION (Send Back to Employee with Changes Needed)</option>
                        <option value="SEND_CLIENT_APPROVAL">SEND DIRECTLY FOR CLIENT APPROVAL</option>
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
    </div>
</div>

<!-- MODAL 4: REASSIGN TASK & OBSERVERS (Team Lead & Super Admin) -->
<div id="reassignTaskModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:580px; width:95%; max-height:92vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <span>👤 Assign Team Members & Observers to Task</span>
            </h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('reassignTaskModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitReassignTask();">
            <div class="modal-body">
                <input type="hidden" id="rt_task_id" value="0">
                <input type="hidden" id="rt_project_id" value="0">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:14px;">
                    <div style="font-size:13px; color:#1e293b; font-weight:700;" id="rt_task_title">Task Title</div>
                    <div style="display:flex; gap:6px; align-items:center; margin-top:4px; flex-wrap:wrap;">
                        <span id="rt_task_dept_badge" class="badge" style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; font-size:11px;"></span>
                        <span id="rt_task_proj_badge" class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:11px;"></span>
                    </div>
                </div>

                <?php if ($isLead): ?>
                <!-- Lead Only: Separate Project Handler Selector for the Particular Project -->
                <div class="form-group" style="background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px; padding:14px; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label class="form-label" style="font-size:12px; font-weight:700; color:#0f766e; margin:0; display:flex; align-items:center; gap:6px;">
                            <span>🛡️ Project Handler for This Project (Lead Access Only)</span>
                            <span id="rt_handlers_count_badge" class="badge" style="background:#ccfbf1; color:#0f766e; font-size:10px; font-weight:700;">0 Selected</span>
                        </label>
                    </div>
                    <p style="font-size:11px; color:#115e59; margin:0 0 8px 0;">
                        Assigned separately from task employees. Selected Project Handlers can create sub-tasks for employees and must approve completed tasks.
                    </p>
                    <div id="rt_handlers_pills_container" style="display:flex; flex-wrap:wrap; gap:6px;"></div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">Primary Assigned Developer *</label>
                    <select id="rt_assigned_to" class="form-select" required onchange="onReassignPrimaryChanged(this.value)">
                        <option value="">-- Select Primary Assignee --</option>
                        <?php foreach ($developers as $d): ?>
                            <option value="<?= $d['id'] ?>" data-dept="<?= htmlspecialchars($d['department'] ?? '') ?>"><?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['role_name'] ?? $d['department']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Multiple Person Assignment for Team Lead -->
                <div class="form-group" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="form-label" style="font-size:12px; font-weight:700; color:#1e293b; margin:0; display:flex; align-items:center; gap:6px;">
                            <span>👥 Assign Team Members (Multiple Persons)</span>
                            <span id="rt_members_count_badge" class="badge" style="background:#dbeafe; color:#1e40af; font-size:10px; font-weight:700;">1 Selected</span>
                        </label>
                        <small style="color:#64748b; font-size:11px;">Click members to assign/unassign</small>
                    </div>
                    <div id="rt_members_pills_container" style="display:flex; flex-wrap:wrap; gap:6px;">
                        <!-- Injected dynamically based on task department -->
                    </div>
                </div>

                <!-- Observers (Watchers / Stakeholders) for Team Lead & Super Admin -->
                <div class="form-group" style="background:#fdf4ff; border:1px solid #f0abfc; border-radius:8px; padding:14px; margin-bottom:0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="form-label" style="font-size:12px; font-weight:700; color:#86198f; margin:0; display:flex; align-items:center; gap:6px;">
                            <span>👁️ Observers (Watchers / Stakeholders)</span>
                            <span id="rt_observers_count_badge" class="badge" style="background:#fae8ff; color:#a21caf; font-size:10px; font-weight:700;">0 Observers</span>
                        </label>
                        <small style="color:#a21caf; font-size:11px;">Receives progress & review updates</small>
                    </div>
                    <div id="rt_observers_pills_container" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; min-height:28px; align-items:center;">
                        <!-- Injected dynamically -->
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <select id="rt_add_observer_select" class="form-select" style="font-size:12px; border-color:#d8b4fe;" onchange="addReassignObserver(this.value)">
                            <option value="">+ Add Observer...</option>
                            <?php foreach ($developers as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['role_name'] ?? $d['department']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('reassignTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight:700;">Confirm Assignment</button>
            </div>
        </form>
    </div>
</div>

<?php if ($isLead): ?>
<!-- MODAL 5: ASSIGN PROJECT HANDLER SEPARATELY (Lead Access Only) -->
<div id="assignProjectHandlersModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:600px; width:95%; max-height:90vh; overflow-y:auto;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" style="margin:0;">🛡️ Select & Assign Project Handlers</h3>
                <small style="color:#0f766e; font-weight:600;" id="aph_project_subtitle">Lead Access Only — Separate Project Authority</small>
            </div>
            <button class="btn btn-outline btn-sm" onclick="closeModal('assignProjectHandlersModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitAssignProjectHandlers();">
            <div class="modal-body">
                <input type="hidden" id="aph_project_id" value="0">
                <div style="background:#f0fdfa; border:1px solid #99f6e4; border-radius:10px; padding:12px 14px; margin-bottom:14px; font-size:12.5px; color:#115e59;">
                    Select the <strong>Project Handler(s)</strong> for <strong id="aph_project_name">this project</strong>. Only Team Leads and Admins can add Project Handlers. Assigned handlers can create sub-tasks for employees and hold approval control before task completion.
                </div>
                <div class="form-group" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <label class="form-label" style="margin:0; font-size:12.5px; font-weight:700;">Choose Project Handlers (Multiple Allowed)</label>
                        <span id="aph_handlers_badge" class="badge" style="background:#ccfbf1; color:#0f766e; font-size:11px; font-weight:700;">0 Selected</span>
                    </div>
                    <div id="aph_handlers_pills" style="display:flex; flex-wrap:wrap; gap:6px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignProjectHandlersModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:#0f766e; border-color:#0f766e; font-weight:700;">Save Project Handlers</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    const userRole = <?= json_encode($user['role']) ?>;
    const isLeadOrAdminUser = <?= json_encode($isLead) ?>;
    const canManageAnyTasksUser = <?= json_encode($canManageAnyTasks) ?>;
    const handledProjectIdsUser = <?= json_encode(array_values($handledProjectIds)) ?>;
    const canCreateTasksUser = <?= json_encode($canManageAnyTasks) ?>;
    const currentUserId = <?= intval($user['id']) ?>;
    const currentUserDept = <?= json_encode($user['department'] ?? 'Development') ?>;
    let currentWorkspaceView = <?= json_encode($activeView) ?>;
    let projSearchTimer = null;

    const allStaffUsers = <?= json_encode($developers) ?>;
    let projectModalHandlers = [];
    let reassignProjectHandlers = [];
    let aphProjectId = 0;
    let aphSelectedHandlers = [];
    let reassignSelectedMembers = [];
    let reassignSelectedObservers = [];
    let reassignCurrentDept = '';

    let createSelectedMembers = [];
    let createSelectedObservers = [];

    document.addEventListener('DOMContentLoaded', () => {
        switchWorkspaceView(currentWorkspaceView, false);
        loadMetrics();
        loadTasks();
        loadCurrentProjects();
    });

    function switchWorkspaceView(view, shouldRefresh = true) {
        currentWorkspaceView = (view === 'projects') ? 'projects' : 'tasks';
        const secProj = document.getElementById('viewSection_projects');
        const secTasks = document.getElementById('viewSection_tasks');
        const tabProj = document.getElementById('tabBtn_projects');
        const tabTasks = document.getElementById('tabBtn_tasks');

        if (secProj) secProj.style.display = currentWorkspaceView === 'projects' ? 'block' : 'none';
        if (secTasks) secTasks.style.display = currentWorkspaceView === 'tasks' ? 'block' : 'none';
        if (tabProj) tabProj.classList.toggle('active', currentWorkspaceView === 'projects');
        if (tabTasks) tabTasks.classList.toggle('active', currentWorkspaceView === 'tasks');

        document.querySelectorAll('.tasks-only-pill').forEach(el => {
            el.style.display = currentWorkspaceView === 'tasks' ? 'inline-flex' : 'none';
        });

        try {
            const url = new URL(window.location.href);
            if (currentWorkspaceView === 'projects') {
                url.searchParams.set('view', 'projects');
            } else {
                url.searchParams.delete('view');
            }
            window.history.replaceState({}, '', url.toString());
        } catch (e) {}

        if (shouldRefresh) {
            if (currentWorkspaceView === 'projects') {
                loadCurrentProjects();
            } else {
                loadTasks();
            }
        }
    }

    function debounceLoadProjects() {
        clearTimeout(projSearchTimer);
        projSearchTimer = setTimeout(() => loadCurrentProjects(), 260);
    }

    function loadCurrentProjects() {
        const isCurrent = document.getElementById('projFilterCurrent')?.value ?? '1';
        const clientId = document.getElementById('projFilterClient')?.value ?? '0';
        const dept = document.getElementById('projFilterDept')?.value ?? '';
        const search = document.getElementById('projFilterSearch')?.value?.trim() ?? '';

        const params = new URLSearchParams({ action: 'get_projects' });
        if (isCurrent !== '') params.set('is_current', isCurrent);
        if (clientId && clientId !== '0') params.set('client_id', clientId);
        if (dept) params.set('department', dept);
        if (search) params.set('search', search);

        fetch('api.php?' + params.toString())
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                renderCurrentProjects(data.projects || []);
                if (isCurrent === '1' && clientId === '0' && !dept && !search) {
                    const badge = document.getElementById('currentProjCountBadge');
                    if (badge) badge.textContent = (data.projects || []).length;
                }
            })
            .catch(err => console.error('Projects load error:', err));
    }

    function renderCurrentProjects(projects) {
        const grid = document.getElementById('currentProjectsGrid');
        if (!grid) return;

        if (!projects.length) {
            grid.innerHTML = `
                <div class="card" style="grid-column:1 / -1; text-align:center; padding:44px 20px;">
                    <div style="font-size:36px; margin-bottom:10px;">📁</div>
                    <h4 style="margin:0 0 6px 0; color:#0f172a;">No projects match the current filter</h4>
                    <p style="font-size:13px; color:#64748b; margin:0 0 16px 0;">Add or import current projects with overview details, competitors, and deliverables.</p>
                    <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                        ${isLeadOrAdminUser ? `<button type="button" class="btn btn-primary" onclick="openCreateProjectModal()">+ Add Current Project</button>` : ''}
                        ${isLeadOrAdminUser ? `<button type="button" class="btn btn-outline" onclick="openImportProjectsModal()">📥 Import Projects CSV</button>` : ''}
                    </div>
                </div>
            `;
            return;
        }

        const prioColors = {
            'Urgent': { bg: '#fee2e2', fg: '#b91c1c' },
            'High': { bg: '#fef3c7', fg: '#b45309' },
            'Medium': { bg: '#e0f2fe', fg: '#0369a1' },
            'Low': { bg: '#f1f5f9', fg: '#475569' }
        };

        let html = '';
        projects.forEach(p => {
            const total = parseInt(p.total_tasks || 0);
            const done = parseInt(p.completed_tasks || 0);
            const inProg = parseInt(p.in_progress_tasks || 0);
            const pending = parseInt(p.pending_tasks || 0);
            const pct = total > 0 ? Math.round((done / total) * 100) : 0;
            const compCnt = parseInt(p.competitors_count || 0);
            const isCurr = parseInt(p.is_current ?? 1) === 1;
            const pr = prioColors[p.priority] || prioColors['Medium'];
            const aboutPreview = (p.about_project || p.description || '').trim();
            const safeSnippet = aboutPreview ? escapeHtml(aboutPreview.length > 135 ? aboutPreview.substring(0, 135) + '...' : aboutPreview) : '<span style="color:#94a3b8; font-style:italic;">No project summary added yet.</span>';

            html += `
                <div class="proj-portfolio-card">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:10px; flex-wrap:wrap;">
                            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                                <span class="badge" style="background:${isCurr ? '#dcfce7' : '#f1f5f9'}; color:${isCurr ? '#166534' : '#475569'}; font-size:10.5px; font-weight:800;">
                                    ${isCurr ? '● CURRENT' : 'ARCHIVED'}
                                </span>
                                ${p.department ? `<span class="badge" style="background:#eff6ff; color:#1d4ed8; font-size:10.5px;">📁 ${escapeHtml(p.department)}</span>` : ''}
                                <span class="badge" style="background:${pr.bg}; color:${pr.fg}; font-size:10.5px;">${escapeHtml(p.priority || 'High')}</span>
                            </div>
                            <span class="badge" style="background:#fdf4ff; color:#7e22ce; border:1px solid #e9d5ff; font-size:10.5px; font-weight:700;">
                                🥊 ${compCnt} Competitor${compCnt === 1 ? '' : 's'}
                            </span>
                        </div>

                        <a href="project_details.php?id=${p.id}" style="display:block; font-size:17px; font-weight:800; color:#0f172a; text-decoration:none; margin-bottom:4px; line-height:1.3;">
                            ${escapeHtml(p.project_name)}
                        </a>

                        <div style="font-size:12.5px; color:#475569; margin-bottom:8px; display:flex; flex-wrap:wrap; gap:10px;">
                            <span>🏢 <strong>${escapeHtml(p.company_name || 'N/A')}</strong></span>
                            <span>👤 Lead: <strong>${escapeHtml(p.team_lead_name || 'Unassigned')}</strong></span>
                            ${parseInt(p.client_approved || 0) === 1 ? `<span class="badge" style="background:#dcfce7; color:#15803d; font-size:10.5px;">☑ Client Approved</span>` : ''}
                        </div>

                        ${(p.handlers && p.handlers.length) ? `
                            <div style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:8px; align-items:center;">
                                <span style="font-size:11px; color:#64748b; font-weight:700;">🛡️ Handlers:</span>
                                ${p.handlers.map(h => `<span class="badge" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; font-size:10.5px;">${escapeHtml(h.full_name)}</span>`).join('')}
                            </div>
                        ` : ''}
                        ${(p.working_employees && p.working_employees.length) ? `
                            <div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:8px; align-items:center;">
                                <span style="font-size:11px; color:#1d4ed8; font-weight:700;">👥 Working (${p.working_employees.length}):</span>
                                ${p.working_employees.slice(0, 5).map(w => `<span class="badge" style="background:#dbeafe; color:#1e40af; border:1px solid #93c5fd; font-size:10px;">${escapeHtml(w.full_name)}</span>`).join('')}
                                ${p.working_employees.length > 5 ? `<span class="badge" style="background:#eff6ff; color:#1d4ed8; font-size:10px;">+${p.working_employees.length - 5} more</span>` : ''}
                            </div>
                        ` : ''}
                        ${(p.observers && p.observers.length) ? `
                            <div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:10px; align-items:center;">
                                <span style="font-size:10.5px; color:#6b21a8; font-weight:700;">👁️ Observers (${p.observers.length}):</span>
                                ${p.observers.slice(0, 4).map(o => `<span class="badge" style="background:#faf5ff; color:#6b21a8; border:1px solid #e9d5ff; font-size:10px;">${escapeHtml(o.full_name)} (${escapeHtml(o.role_name || '')})</span>`).join('')}
                                ${p.observers.length > 4 ? `<span class="badge" style="background:#f3e8ff; color:#6b21a8; font-size:10px;">+${p.observers.length - 4} more</span>` : ''}
                            </div>
                        ` : ''}

                        <p style="font-size:12.5px; color:#334155; line-height:1.5; margin:0 0 12px 0;">
                            ${safeSnippet}
                        </p>

                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; font-size:11.5px; color:#475569;">
                            <span style="background:#f8fafc; border:1px solid #e2e8f0; padding:3px 8px; border-radius:6px;">
                                📅 Due: <strong>${escapeHtml(p.deadline || 'N/A')}</strong>
                            </span>
                            <span style="background:#f8fafc; border:1px solid #e2e8f0; padding:3px 8px; border-radius:6px;">
                                ⚙️ Status: <strong>${escapeHtml(p.status || 'In Progress')}</strong>
                            </span>
                            ${p.target_audience ? `<span style="background:#f8fafc; border:1px solid #e2e8f0; padding:3px 8px; border-radius:6px;">🎯 ${escapeHtml(p.target_audience.length > 26 ? p.target_audience.substring(0, 26) + '…' : p.target_audience)}</span>` : ''}
                        </div>

                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px;">
                            <div style="display:flex; justify-content:space-between; font-size:11.5px; font-weight:700; color:#334155; margin-bottom:6px;">
                                <span>Deliverable Progress (${done}/${total} tasks)</span>
                                <span style="color:#2563eb;">${pct}%</span>
                            </div>
                            <div style="height:7px; background:#e2e8f0; border-radius:999px; overflow:hidden; margin-bottom:6px;">
                                <div style="height:100%; width:${pct}%; background:linear-gradient(90deg, #2563eb, #10b981); border-radius:999px;"></div>
                            </div>
                            <div style="display:flex; gap:12px; font-size:11px; color:#64748b;">
                                <span>⏳ Pending: <strong>${pending}</strong></span>
                                <span>⚡ Active: <strong>${inProg}</strong></span>
                                <span>✅ Done: <strong>${done}</strong></span>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap; padding-top:10px; border-top:1px solid #f1f5f9;">
                        <a href="project_details.php?id=${p.id}" class="btn btn-primary btn-sm" style="flex:1; text-align:center; justify-content:center;">
                            📊 Overview & Competitors
                        </a>
                        ${(p.can_manage_project || isLeadOrAdminUser) ? `
                            <button type="button" class="btn btn-outline btn-sm" style="color:#15803d; border-color:#86efac; background:#f0fdf4; font-weight:700;" onclick='openCreateProjectTask(${p.id}, ${JSON.stringify(p.department || "")})'>
                                + Task
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" style="color:#7c3aed; border-color:#ddd6fe; background:#f5f3ff; font-weight:700;" onclick='openCreateProjectTask(${p.id}, ${JSON.stringify(p.department || "")}, 0, true)'>
                                + Sub-Task
                            </button>
                        ` : ''}
                        ${isLeadOrAdminUser ? `
                            <button type="button" class="btn btn-outline btn-sm" style="color:#0f766e; border-color:#99f6e4; background:#f0fdfa; font-weight:700;" onclick='openAssignProjectHandlersModal(${p.id}, ${JSON.stringify(p.project_name || "")}, ${JSON.stringify(p.handler_ids || [])})' title="Select & Assign Project Handlers separately (Lead Only)">
                                🛡️ Handlers
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="openEditProjectModal(${p.id})" title="Edit Project Overview & Details">
                                ✏️ Edit
                            </button>
                        ` : ''}
                        <button type="button" class="btn btn-outline btn-sm" onclick="viewProjectTasks(${p.id})" title="Filter Tasks Table by this Project">
                            📋 Tasks
                        </button>
                    </div>
                </div>
            `;
        });
        grid.innerHTML = html;
        if (window.feather) feather.replace();
    }

    function viewProjectTasks(projectId) {
        const fp = document.getElementById('filterProject');
        if (fp) fp.value = String(projectId);
        switchWorkspaceView('tasks', false);
        loadTasks();
    }

    function openCreateProjectTask(projectId, departmentName = '', parentTaskId = 0, forceSubtask = false) {
        const fp = document.getElementById('filterProject');
        if (fp) fp.value = String(projectId);
        const projSelect = document.getElementById('ct_project_id');
        if (projSelect) {
            projSelect.value = String(projectId);
        }
        const deptSelect = document.getElementById('ct_department');
        if (deptSelect && departmentName) {
            for (const opt of deptSelect.options) {
                if (opt.value === departmentName) {
                    deptSelect.value = departmentName;
                    break;
                }
            }
        }
        openCreateTaskModal(parentTaskId, forceSubtask);
    }

    function loadMetrics() {
        fetch('api.php?action=get_task_dashboard_metrics')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const m = data.metrics;
                const area = document.getElementById('metricsCardsArea');

                if (!isLeadOrAdminUser) {
                    area.innerHTML = `
                        <div class="card" style="margin-bottom:0; padding:18px 20px; border-radius:18px; border:1px solid #edf2f7; box-shadow:0 2px 12px rgba(15,23,42,0.03);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <span style="font-size:11px; font-weight:800; color:#64748b; letter-spacing:0.5px;">MY TOTAL TASKS</span>
                                <div style="width:32px; height:32px; border-radius:8px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; font-size:14px;">📋</div>
                            </div>
                            <div style="font-size:26px; font-weight:800; color:#0f172a;">${m.total}</div>
                        </div>
                        <div class="card" style="margin-bottom:0; padding:18px 20px; border-radius:18px; border:1px solid #edf2f7; box-shadow:0 2px 12px rgba(15,23,42,0.03);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <span style="font-size:11px; font-weight:800; color:#0284c7; letter-spacing:0.5px;">IN PROGRESS</span>
                                <div style="width:32px; height:32px; border-radius:8px; background:#e0f2fe; color:#0284c7; display:flex; align-items:center; justify-content:center; font-size:14px;">⚡</div>
                            </div>
                            <div style="font-size:26px; font-weight:800; color:#0284c7;">${m.in_progress}</div>
                        </div>
                        <div class="card" style="margin-bottom:0; padding:18px 20px; border-radius:18px; border:1px solid #edf2f7; box-shadow:0 2px 12px rgba(15,23,42,0.03);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <span style="font-size:11px; font-weight:800; color:#d97706; letter-spacing:0.5px;">UNDER REVIEW</span>
                                <div style="width:32px; height:32px; border-radius:8px; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; font-size:14px;">🔍</div>
                            </div>
                            <div style="font-size:26px; font-weight:800; color:#d97706;">${m.under_review}</div>
                        </div>
                        <div class="card" style="margin-bottom:0; padding:18px 20px; border-radius:18px; border:1px solid #edf2f7; box-shadow:0 2px 12px rgba(15,23,42,0.03);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <span style="font-size:11px; font-weight:800; color:#dc2626; letter-spacing:0.5px;">REVISION NEEDED</span>
                                <div style="width:32px; height:32px; border-radius:8px; background:#fee2e2; color:#dc2626; display:flex; align-items:center; justify-content:center; font-size:14px;">⚠️</div>
                            </div>
                            <div style="font-size:26px; font-weight:800; color:#dc2626;">${m.revision}</div>
                        </div>
                    `;
                } else {
                    area.innerHTML = `
                        <div class="card" style="margin-bottom:0; padding:18px 20px; border-radius:18px; border:1px solid #edf2f7; box-shadow:0 2px 12px rgba(15,23,42,0.03);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <span style="font-size:11px; font-weight:800; color:#64748b; letter-spacing:0.5px;">ACTIVE PROJECTS</span>
                                <div style="width:32px; height:32px; border-radius:8px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; font-size:14px;">📁</div>
                            </div>
                            <div style="font-size:26px; font-weight:800; color:#0f172a;">${m.total_projects}</div>
                        </div>
                        <div class="card" style="margin-bottom:0; padding:18px 20px; border-radius:18px; border:1px solid #edf2f7; box-shadow:0 2px 12px rgba(15,23,42,0.03);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <span style="font-size:11px; font-weight:800; color:#d97706; letter-spacing:0.5px;">AWAITING REVIEW</span>
                                <div style="width:32px; height:32px; border-radius:8px; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; font-size:14px;">⏳</div>
                            </div>
                            <div style="font-size:26px; font-weight:800; color:#d97706;">${m.awaiting_review}</div>
                        </div>
                        <div class="card" style="margin-bottom:0; padding:18px 20px; border-radius:18px; border:1px solid #edf2f7; box-shadow:0 2px 12px rgba(15,23,42,0.03);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <span style="font-size:11px; font-weight:800; color:#dc2626; letter-spacing:0.5px;">SLA BREACHES</span>
                                <div style="width:32px; height:32px; border-radius:8px; background:#fee2e2; color:#dc2626; display:flex; align-items:center; justify-content:center; font-size:14px;">🚨</div>
                            </div>
                            <div style="font-size:26px; font-weight:800; color:#dc2626;">${m.sla_breaches}</div>
                        </div>
                        <div class="card" style="margin-bottom:0; padding:18px 20px; border-radius:18px; border:1px solid #edf2f7; box-shadow:0 2px 12px rgba(15,23,42,0.03);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <span style="font-size:11px; font-weight:800; color:#16a34a; letter-spacing:0.5px;">COMPLETED TASKS</span>
                                <div style="width:32px; height:32px; border-radius:8px; background:#dcfce7; color:#16a34a; display:flex; align-items:center; justify-content:center; font-size:14px;">✅</div>
                            </div>
                            <div style="font-size:26px; font-weight:800; color:#16a34a;">${m.completed}</div>
                        </div>
                    `;
                }
            });
    }

    function loadTasks() {
        const clientElem = document.getElementById('filterClient');
        const clientId = clientElem ? clientElem.value : 0;
        const projectId = document.getElementById('filterProject').value;
        const status = document.getElementById('filterStatus').value;
        const devElem = document.getElementById('filterDeveloper');
        const assignedTo = devElem ? devElem.value : 0;
        const deptElem = document.getElementById('filterDepartment');
        const dept = deptElem ? deptElem.value : '';

        let url = `api.php?action=get_tasks&project_id=${projectId}&client_id=${clientId}&assigned_to=${assignedTo}`;
        if (status) url += `&status=${encodeURIComponent(status)}`;
        if (dept) url += `&department=${encodeURIComponent(dept)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const tasks = data.tasks;

                if (!canManageAnyTasksUser) {
                    renderDeveloperKanban(tasks);
                } else {
                    renderTeamLeadTable(tasks);
                }
            });
    }

    function onClientFilterChanged() {
        const clientSelect = document.getElementById('filterClient');
        const clientId = parseInt(clientSelect.value) || 0;
        const projSelect = document.getElementById('filterProject');

        const options = projSelect.querySelectorAll('option');
        options.forEach(opt => {
            const optClient = parseInt(opt.getAttribute('data-client')) || 0;
            if (opt.value === '0') {
                opt.style.display = '';
            } else if (clientId === 0 || optClient === clientId) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });

        if (clientId > 0) {
            const matchingOpt = Array.from(options).find(o => parseInt(o.getAttribute('data-client')) === clientId);
            if (matchingOpt && projSelect.value === '0') {
                projSelect.value = matchingOpt.value;
            }
        }

        loadTasks();
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
                loadTasks();
            }
        })
        .catch(err => {
            alert('Failed to update task status.');
            loadTasks();
        });
    }

    function toggleTaskClientApprovalInList(taskId, approved) {
        fetch('api.php?action=toggle_task_client_approval', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId, approved: approved ? 1 : 0 })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadMetrics();
                loadTasks();
                loadCurrentProjects();
            } else {
                alert('Error: ' + (data.message || 'Could not update client approval'));
                loadTasks();
            }
        })
        .catch(() => {
            alert('Network error updating client approval.');
            loadTasks();
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
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:6px;">
                                <strong style="font-size:12px; color:#2563eb;">${t.task_code}</strong>
                                <div style="display:flex; gap:4px; align-items:center;">
                                    ${(t.is_subtask || t.task_type === 'subtask') ? `<span class="badge" style="background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; font-size:9.5px;">SUB-TASK</span>` : ''}
                                    <span class="badge ${slaClass}" style="font-size:10px;">${t.sla_status}</span>
                                </div>
                            </div>
                            <h5 style="margin:8px 0; font-size:14px; color:#0f172a; font-weight:600;">${escapeHtml(t.title)}</h5>
                            <p style="font-size:12px; color:#64748b; margin-bottom:12px;">Project: ${escapeHtml(t.project_name)}${t.department ? ` &bull; <span style="color:#2563eb; font-weight:600;">📁 ${escapeHtml(t.department)}</span>` : ''}</p>

                            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center; justify-content:space-between;">
                                <a href="task_details.php?id=${t.id}" class="btn btn-outline btn-sm" style="font-size:11px;">View Details</a>
                                ${t.status === 'PENDING' ? `
                                    <button class="btn btn-outline btn-sm" style="font-size:11px; color:#0284c7; border-color:#93c5fd;" onclick="updateTaskStatusDirect(${t.id}, 'IN PROGRESS')">⚡ Start</button>
                                ` : ''}
                                ${t.status === 'IN PROGRESS' || t.status === 'REVISION REQUIRED' || t.status === 'CLIENT REVISION REQUESTED' ? `
                                    <button class="btn btn-success btn-sm" style="font-size:11px;" onclick="openSubmitWorkModal(${t.id}, '${safeTitle}')">📤 Submit for Approval</button>
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
            const canManageRow = Boolean(t.can_manage_task || isLeadOrAdminUser);
            const isSub = Boolean(t.is_subtask || t.task_type === 'subtask' || parseInt(t.parent_task_id || 0) > 0);
            const isHandlerApproved = parseInt(t.handler_approved || 0) === 1 || ['PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL', 'COMPLETED'].includes(t.status);
            const isLeadApproved = parseInt(t.lead_approved || 0) === 1 || ['LEAD APPROVED', 'COMPLETED'].includes(t.status) || (t.status === 'SENT FOR CLIENT APPROVAL' && parseInt(t.lead_approved || 0) === 1);
            const isClientTicked = parseInt(t.client_approved_by_handler || 0) === 1 || t.status === 'COMPLETED';

            html += `
                <tr style="${isSub ? 'background:#faf5ff;' : ''}">
                    <td><strong style="color:${isSub ? '#7c3aed' : '#2563eb'};">${t.task_code}</strong></td>
                    <td>
                        ${isSub ? `<span class="badge" style="background:#ede9fe; color:#6d28d9; font-size:9.5px; margin-right:4px;">↳ SUB-TASK</span>` : ''}
                        <a href="task_details.php?id=${t.id}" style="font-weight:700; color:#0f172a; text-decoration:none;">${safeTitle}</a>
                        ${t.parent_task_code ? `<br><small style="color:#6d28d9; font-size:10.5px;">Parent: #${escapeHtml(t.parent_task_code)} (${escapeHtml(t.parent_task_title || '')})</small>` : ''}
                        ${isHandlerApproved && t.status === 'SENT FOR CLIENT APPROVAL' ? `<br><span style="font-size:10.5px; color:#15803d; font-weight:700;">✓ Handler Approved &bull; Awaiting Client Tick</span>` : ''}
                        ${(isHandlerApproved && !isLeadApproved && t.status !== 'COMPLETED') ? `<br><span style="font-size:10.5px; color:#d97706; font-weight:700;">⏳ Handler Approved &bull; Pending Lead Approval</span>` : ''}
                        ${(isLeadApproved && t.status === 'SENT FOR CLIENT APPROVAL') ? `<br><span style="font-size:10.5px; color:#15803d; font-weight:700;">✓ Lead Approved &bull; Ready for Client Tick</span>` : ''}
                    </td>
                    <td>
                        <a href="project_details.php?id=${t.project_id}" style="color:#1e40af; font-weight:700; text-decoration:none;" title="Open Project Overview & Competitors">${escapeHtml(t.project_name)}</a><br>
                        <small style="color:var(--text-muted);">${escapeHtml(t.company_name)}</small>
                        ${(t.project_handlers && t.project_handlers.length) ? `<div style="margin-top:3px; display:flex; flex-wrap:wrap; gap:3px;">${t.project_handlers.map(h => `<span class="badge" style="background:#ccfbf1; color:#0f766e; font-size:9.5px; padding:1px 6px;">🛡️ ${escapeHtml(h.full_name)}</span>`).join('')}</div>` : ''}
                    </td>
                    <td>
                        <span class="badge badge-secondary">${escapeHtml(t.assigned_to_name || 'Unassigned')}</span>
                        ${t.assignee_ids && t.assignee_ids.length > 1 ? `<span class="badge" style="background:#dbeafe; color:#1e40af; font-size:10px; margin-left:2px;">+${t.assignee_ids.length - 1} more</span>` : ''}
                        ${t.observer_ids && t.observer_ids.length > 0 ? `<br><small style="color:#7e22ce; font-size:10.5px; font-weight:600;" title="${t.observer_ids.length} observer(s)">👁️ ${t.observer_ids.length} Observer${t.observer_ids.length > 1 ? 's' : ''}</small>` : ''}
                        ${t.department ? `<br><span class="badge" style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; font-size:10px; margin-top:3px; padding:2px 6px;">📁 ${escapeHtml(t.department)}</span>` : ''}
                    </td>
                    <td><span class="badge ${t.priority === 'Urgent' ? 'badge-danger' : 'badge-warning'}">${t.priority}</span></td>
                    <td>
                        <select class="form-select" style="font-size:12px; font-weight:700; padding:4px 8px; width:175px; border-radius:6px; cursor:pointer;" onchange="updateTaskStatusDirect(${t.id}, this.value)">
                            <option value="PENDING" ${t.status === 'PENDING' ? 'selected' : ''}>⏳ Pending</option>
                            <option value="IN PROGRESS" ${t.status === 'IN PROGRESS' ? 'selected' : ''}>⚡ In Progress</option>
                            <option value="UNDER REVIEW" ${t.status === 'UNDER REVIEW' ? 'selected' : ''}>🔍 Under Review</option>
                            ${canManageRow ? `
                                <option value="PENDING LEAD APPROVAL" ${t.status === 'PENDING LEAD APPROVAL' ? 'selected' : ''}>📝 Pending Lead Approval</option>
                                <option value="REVISION REQUIRED" ${t.status === 'REVISION REQUIRED' ? 'selected' : ''}>🔄 Revision Needed</option>
                                <option value="SENT FOR CLIENT APPROVAL" ${t.status === 'SENT FOR CLIENT APPROVAL' ? 'selected' : ''}>📩 Client Approval</option>
                                <option value="COMPLETED" ${t.status === 'COMPLETED' ? 'selected' : ''}>✅ Completed</option>
                            ` : (['PENDING LEAD APPROVAL', 'SENT FOR CLIENT APPROVAL', 'COMPLETED', 'REVISION REQUIRED'].includes(t.status) ? `<option value="${escapeHtml(t.status)}" selected>${escapeHtml(t.status)}</option>` : '')}
                        </select>
                        ${(canManageRow && (isLeadApproved || (isLeadOrAdminUser && isHandlerApproved))) ? `
                            <label style="display:flex; align-items:center; gap:5px; margin-top:5px; font-size:11px; font-weight:700; color:${isClientTicked ? '#15803d' : '#1d4ed8'}; cursor:pointer;">
                                <input type="checkbox" ${isClientTicked ? 'checked' : ''} onchange="toggleTaskClientApprovalInList(${t.id}, this.checked)" style="width:14px; height:14px; cursor:pointer;">
                                Approved by Client
                            </label>
                        ` : ((canManageRow && isHandlerApproved && !isLeadApproved) ? `<div style="margin-top:4px; font-size:10.5px; color:#d97706; font-weight:700;">⏳ Needs Lead Approval</div>` : '')}
                    </td>
                    <td><span class="badge ${slaClass}">${t.sla_status}</span></td>
                    <td><small style="color:#475569;">${t.deadline ? t.deadline.substring(0, 16) : 'N/A'}</small></td>
                    <td>
                        <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                            <a href="task_details.php?id=${t.id}" class="btn btn-outline btn-sm" title="View details & discussion">Details</a>
                            <a href="task_details.php?id=${t.id}" class="btn btn-outline btn-sm" title="View details, media & discussion">Details / Media</a>
                            ${canManageRow ? `
                                ${isLeadOrAdminUser ? `
                                    <button class="btn btn-outline btn-sm" onclick='openAssignProjectHandlersModal(${t.project_id}, ${JSON.stringify(t.project_name || "")}, ${JSON.stringify(t.project_handler_ids || [])})' style="color:#0f766e; border-color:#99f6e4;" title="Select Project Handler separately (Lead Only)">
                                        🛡️ Handler
                                    </button>
                                ` : ''}
                                <button class="btn btn-outline btn-sm" onclick='openReassignModal(${t.id}, ${JSON.stringify(t.title || "")}, ${t.assigned_to || 0}, ${JSON.stringify(t.department || "")}, ${JSON.stringify(t.assignee_ids || [])}, ${JSON.stringify(t.observer_ids || [])}, ${t.project_id || 0}, ${JSON.stringify(t.project_name || "")}, ${JSON.stringify(t.project_handler_ids || [])})' style="color:#2563eb; border-color:#93c5fd;" title="Assign team members, project handler and observers">
                                    👤 Assign
                                </button>
                                ${!isSub ? `
                                    <button class="btn btn-outline btn-sm" onclick='openCreateProjectTask(${t.project_id}, ${JSON.stringify(t.department || "")}, ${t.id}, true)' style="color:#7c3aed; border-color:#ddd6fe;" title="Create Sub-Task under this Task">
                                        ↳ Sub-Task
                                    </button>
                                ` : ''}
                                ${t.status === 'UNDER REVIEW' ? `
                                    <button class="btn btn-primary btn-sm" onclick="openReviewModal(${t.id}, '${t.task_code}', '${(t.remarks || '').replace(/'/g, "\\'")}')">✓ Approve / Review</button>
                                ` : ''}
                                ${(isLeadOrAdminUser && t.status === 'PENDING LEAD APPROVAL') ? `
                                    <button class="btn btn-success btn-sm" onclick="leadApproveTaskInList(${t.id})" style="background:#15803d; border-color:#15803d;">🛡️ Lead Approve</button>
                                ` : ''}
                            ` : `
                                ${(t.status === 'IN PROGRESS' || t.status === 'REVISION REQUIRED' || t.status === 'CLIENT REVISION REQUESTED') ? `
                                    <button class="btn btn-success btn-sm" onclick="openSubmitWorkModal(${t.id}, '${safeTitle.replace(/'/g, "\\'")}')">📤 Submit Work</button>
                                ` : ''}
                            `}
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        if (window.feather) feather.replace();
    }

    function openReassignModal(taskId, title, currentAssignee, dept = '', existingAssignees = [], existingObservers = [], projectId = 0, projectName = '', existingProjHandlers = []) {
        document.getElementById('rt_task_id').value = taskId;
        const rtProjEl = document.getElementById('rt_project_id');
        if (rtProjEl) rtProjEl.value = projectId || 0;
        document.getElementById('rt_task_title').textContent = title;
        reassignCurrentDept = dept || '';

        const deptBadge = document.getElementById('rt_task_dept_badge');
        if (deptBadge) {
            deptBadge.textContent = dept ? `📁 Department: ${dept}` : '📁 General';
        }
        const projBadge = document.getElementById('rt_task_proj_badge');
        if (projBadge) {
            projBadge.textContent = projectName ? `📌 Project: ${projectName}` : '';
            projBadge.style.display = projectName ? 'inline-flex' : 'none';
        }

        const assignSelect = document.getElementById('rt_assigned_to');
        if (assignSelect) {
            assignSelect.value = currentAssignee || '';
        }

        const currId = currentAssignee ? parseInt(currentAssignee) : 0;
        if (Array.isArray(existingAssignees) && existingAssignees.length > 0) {
            reassignSelectedMembers = existingAssignees.map(x => parseInt(x));
            if (currId > 0 && !reassignSelectedMembers.includes(currId)) {
                reassignSelectedMembers.unshift(currId);
            }
        } else if (currId > 0) {
            reassignSelectedMembers = [currId];
        } else {
            reassignSelectedMembers = [];
        }

        if (Array.isArray(existingObservers) && existingObservers.length > 0) {
            reassignSelectedObservers = existingObservers.map(x => parseInt(x));
        } else {
            reassignSelectedObservers = [];
        }

        reassignProjectHandlers = Array.isArray(existingProjHandlers) ? existingProjHandlers.map(x => parseInt(x)).filter(x => x > 0) : [];
        if (isLeadOrAdminUser) {
            renderReassignHandlerPills();
        }

        renderReassignPills();
        renderReassignObserverPills();
        document.getElementById('reassignTaskModal').style.display = 'flex';
    }

    function toggleReassignProjectHandler(uid) {
        const id = parseInt(uid);
        const idx = reassignProjectHandlers.indexOf(id);
        if (idx > -1) reassignProjectHandlers.splice(idx, 1);
        else reassignProjectHandlers.push(id);
        renderReassignHandlerPills();
    }

    function renderReassignHandlerPills() {
        const container = document.getElementById('rt_handlers_pills_container');
        const badge = document.getElementById('rt_handlers_count_badge');
        if (!container) return;
        if (badge) badge.textContent = `${reassignProjectHandlers.length} Selected`;

        let html = '';
        allStaffUsers.forEach(u => {
            const uid = parseInt(u.id);
            const isSel = reassignProjectHandlers.includes(uid);
            html += `
                <button type="button" onclick="toggleReassignProjectHandler(${uid})"
                    style="background:${isSel ? '#0f766e' : '#ffffff'}; color:${isSel ? '#ffffff' : '#334155'}; border:1px solid ${isSel ? '#0d9488' : '#99f6e4'}; font-size:11.5px; padding:4px 10px; border-radius:16px; font-weight:600; display:inline-flex; align-items:center; gap:5px; cursor:pointer;">
                    <span>${isSel ? '✓' : '+'} ${escapeHtml(u.full_name)}</span>
                    <small style="opacity:0.8; font-size:10px;">(${escapeHtml(u.role_name || u.department)})</small>
                </button>
            `;
        });
        container.innerHTML = html;
    }

    function openAssignProjectHandlersById(projectId) {
        fetch('api.php?action=get_project_details&project_id=' + encodeURIComponent(projectId))
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.project) {
                    alert(data.message || 'Could not load project details.');
                    return;
                }
                openAssignProjectHandlersModal(projectId, data.project.project_name || '', data.project.handler_ids || []);
            })
            .catch(() => alert('Network error loading project handlers.'));
    }

    function openAssignProjectHandlersModal(projectId, projectName = '', existingHandlers = []) {
        if (!isLeadOrAdminUser) return;
        aphProjectId = parseInt(projectId) || 0;
        document.getElementById('aph_project_id').value = aphProjectId;
        document.getElementById('aph_project_name').textContent = projectName || `Project #${aphProjectId}`;
        document.getElementById('aph_project_subtitle').textContent = `Project #${aphProjectId} • Lead Access Only`;
        aphSelectedHandlers = Array.isArray(existingHandlers) ? existingHandlers.map(x => parseInt(x)).filter(x => x > 0) : [];
        renderAssignProjectHandlersPills();
        document.getElementById('assignProjectHandlersModal').style.display = 'flex';
    }

    function toggleAphHandler(uid) {
        const id = parseInt(uid);
        const idx = aphSelectedHandlers.indexOf(id);
        if (idx > -1) aphSelectedHandlers.splice(idx, 1);
        else aphSelectedHandlers.push(id);
        renderAssignProjectHandlersPills();
    }

    function renderAssignProjectHandlersPills() {
        const container = document.getElementById('aph_handlers_pills');
        const badge = document.getElementById('aph_handlers_badge');
        if (!container) return;
        if (badge) badge.textContent = `${aphSelectedHandlers.length} Selected`;

        container.innerHTML = allStaffUsers.map(u => {
            const uid = parseInt(u.id);
            const sel = aphSelectedHandlers.includes(uid);
            return `
                <button type="button" onclick="toggleAphHandler(${uid})"
                    style="background:${sel ? '#0f766e' : '#ffffff'}; color:${sel ? '#ffffff' : '#334155'}; border:1px solid ${sel ? '#0d9488' : '#cbd5e1'}; font-size:11.5px; padding:4px 10px; border-radius:16px; font-weight:600; display:inline-flex; align-items:center; gap:5px; cursor:pointer;">
                    <span>${sel ? '✓' : '+'} ${escapeHtml(u.full_name)}</span>
                    <small style="opacity:0.8; font-size:10px;">(${escapeHtml(u.role_name || u.department)})</small>
                </button>
            `;
        }).join('');
    }

    function submitAssignProjectHandlers() {
        fetch('api.php?action=assign_project_handlers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                project_id: aphProjectId,
                handlers: aphSelectedHandlers
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('assignProjectHandlersModal');
                alert('🛡️ ' + data.message);
                loadCurrentProjects();
                loadTasks();
            } else {
                alert('Error: ' + (data.message || 'Could not update project handlers.'));
            }
        })
        .catch(() => alert('Network error saving project handlers.'));
    }

    function addReassignObserver(userId) {
        const uId = parseInt(userId);
        if (!uId) return;
        if (!reassignSelectedObservers.includes(uId)) {
            reassignSelectedObservers.push(uId);
        }
        renderReassignObserverPills();
        const select = document.getElementById('rt_add_observer_select');
        if (select) select.value = '';
    }

    function removeReassignObserver(userId) {
        const uId = parseInt(userId);
        reassignSelectedObservers = reassignSelectedObservers.filter(id => id !== uId);
        renderReassignObserverPills();
    }

    function renderReassignObserverPills() {
        const container = document.getElementById('rt_observers_pills_container');
        const badge = document.getElementById('rt_observers_count_badge');
        if (!container) return;

        if (badge) {
            badge.textContent = `${reassignSelectedObservers.length} Observer${reassignSelectedObservers.length === 1 ? '' : 's'}`;
        }

        if (reassignSelectedObservers.length === 0) {
            container.innerHTML = `<span style="font-size:11.5px; color:#94a3b8; font-style:italic;">No observers added yet.</span>`;
            return;
        }

        let html = '';
        reassignSelectedObservers.forEach(obsId => {
            const u = allStaffUsers.find(x => parseInt(x.id) === obsId);
            const name = u ? u.full_name : `User #${obsId}`;
            const role = u ? (u.role_name || u.department || '') : '';
            html += `
                <span class="badge" style="background:#f3e8ff; color:#6b21a8; border:1px solid #d8b4fe; font-size:11.5px; padding:4px 8px; border-radius:14px; font-weight:600; display:inline-flex; align-items:center; gap:5px;">
                    <span>👁️ ${escapeHtml(name)}</span>
                    ${role ? `<small style="opacity:0.75; font-size:10px;">(${escapeHtml(role)})</small>` : ''}
                    <button type="button" onclick="removeReassignObserver(${obsId})" style="background:none; border:none; color:#7e22ce; font-size:13px; font-weight:700; cursor:pointer; padding:0 2px; line-height:1;" title="Remove observer">&times;</button>
                </span>
            `;
        });
        container.innerHTML = html;
    }

    function onReassignPrimaryChanged(newVal) {
        const uId = parseInt(newVal) || 0;
        if (uId > 0 && !reassignSelectedMembers.includes(uId)) {
            reassignSelectedMembers.push(uId);
        }
        renderReassignPills();
    }

    function toggleReassignMember(userId) {
        const uId = parseInt(userId);
        const pos = reassignSelectedMembers.indexOf(uId);
        if (pos > -1) {
            reassignSelectedMembers.splice(pos, 1);
            const assignSelect = document.getElementById('rt_assigned_to');
            if (assignSelect && parseInt(assignSelect.value) === uId) {
                assignSelect.value = reassignSelectedMembers[0] || '';
            }
        } else {
            reassignSelectedMembers.push(uId);
            const assignSelect = document.getElementById('rt_assigned_to');
            if (assignSelect && !assignSelect.value) {
                assignSelect.value = uId;
            }
        }
        renderReassignPills();
    }

    function renderReassignPills() {
        const container = document.getElementById('rt_members_pills_container');
        const badge = document.getElementById('rt_members_count_badge');
        if (!container) return;

        if (badge) {
            badge.textContent = `${reassignSelectedMembers.length} Selected`;
        }

        const deptNorm = (reassignCurrentDept || '').toLowerCase();
        let displayUsers = allStaffUsers;
        if (deptNorm) {
            const deptUsers = allStaffUsers.filter(u => (u.department || '').toLowerCase() === deptNorm);
            const otherUsers = allStaffUsers.filter(u => (u.department || '').toLowerCase() !== deptNorm);
            displayUsers = deptUsers.concat(otherUsers);
        }

        let html = '';
        displayUsers.forEach(u => {
            const isSel = reassignSelectedMembers.includes(parseInt(u.id));
            const isDept = deptNorm && (u.department || '').toLowerCase() === deptNorm;
            if (isSel) {
                html += `
                    <button type="button" class="btn btn-sm" onclick="toggleReassignMember(${u.id})" 
                        style="background:#2563eb; color:#ffffff; border:1px solid #1d4ed8; font-size:11.5px; padding:4px 10px; border-radius:16px; font-weight:600; display:inline-flex; align-items:center; gap:5px; cursor:pointer;" title="Click to remove">
                        <span>✓ ${escapeHtml(u.full_name)}</span>
                        <small style="opacity:0.85; font-size:10px;">(${escapeHtml(u.role_name || u.department)})</small>
                    </button>
                `;
            } else {
                html += `
                    <button type="button" class="btn btn-sm" onclick="toggleReassignMember(${u.id})" 
                        style="background:#ffffff; color:#334155; border:1px solid ${isDept ? '#93c5fd' : '#cbd5e1'}; font-size:11.5px; padding:4px 10px; border-radius:16px; font-weight:500; display:inline-flex; align-items:center; gap:5px; cursor:pointer;" title="Click to assign">
                        <span>+ ${escapeHtml(u.full_name)}</span>
                        <small style="color:#64748b; font-size:10px;">(${escapeHtml(u.role_name || u.department)})</small>
                    </button>
                `;
            }
        });

        container.innerHTML = html;
    }

    function submitReassignTask() {
        const taskId = document.getElementById('rt_task_id').value;
        let assignedTo = parseInt(document.getElementById('rt_assigned_to').value) || 0;

        if (!assignedTo && reassignSelectedMembers.length > 0) {
            assignedTo = reassignSelectedMembers[0];
        }

        if (!taskId || !assignedTo) {
            alert('Please select at least one target developer or member.');
            return;
        }

        const payloadReassign = { 
            task_id: taskId, 
            assigned_to: assignedTo,
            assignees: reassignSelectedMembers,
            observers: reassignSelectedObservers
        };
        if (isLeadOrAdminUser) {
            payloadReassign.project_handlers = reassignProjectHandlers;
        }

        fetch('api.php?action=reassign_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payloadReassign)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal('reassignTaskModal');
                alert('✅ ' + data.message);
                loadTasks();
                loadCurrentProjects();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    // Modal Helpers for Creating Tasks
    function openCreateTaskModal(parentTaskId = 0, forceSubtask = false) {
        const filterProj = document.getElementById('filterProject');
        const projSelect = document.getElementById('ct_project_id');
        if (filterProj && filterProj.value && filterProj.value !== '0' && projSelect && !projSelect.value) {
            projSelect.value = filterProj.value;
        }

        const isSubChk = document.getElementById('ct_is_subtask');
        if (isSubChk) {
            isSubChk.checked = Boolean(forceSubtask || parentTaskId > 0);
        }
        toggleCreateSubtaskFields();
        loadCreateModalParentTasks(parentTaskId);

        const assignSelect = document.getElementById('ct_assigned_to');
        const primId = assignSelect ? parseInt(assignSelect.value) || 0 : 0;
        createSelectedMembers = primId > 0 ? [primId] : [];
        createSelectedObservers = [];

        renderCreatePills();
        renderCreateObserverPills();
        document.getElementById('createTaskModal').style.display = 'flex';
    }

    function toggleCreateSubtaskFields() {
        const chk = document.getElementById('ct_is_subtask');
        const wrap = document.getElementById('ct_parent_task_wrap');
        if (wrap) wrap.style.display = (chk && chk.checked) ? 'block' : 'none';
    }

    function loadCreateModalParentTasks(preselectId = 0) {
        const projId = parseInt(document.getElementById('ct_project_id')?.value || '0') || 0;
        const sel = document.getElementById('ct_parent_task_id');
        if (!sel) return;
        sel.innerHTML = '<option value="0">-- Standalone Project Sub-Task --</option>';
        if (projId <= 0) return;

        fetch(`api.php?action=get_tasks&project_id=${projId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.tasks)) return;
                data.tasks.forEach(t => {
                    if (parseInt(t.parent_task_id || 0) > 0 || t.task_type === 'subtask') return;
                    const opt = document.createElement('option');
                    opt.value = String(t.id);
                    opt.textContent = `${t.task_code} - ${t.title}`;
                    if (parseInt(preselectId) === parseInt(t.id)) opt.selected = true;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
    }

    function onNewTaskPrimaryChanged(newVal) {
        const uId = parseInt(newVal) || 0;
        if (uId > 0 && !createSelectedMembers.includes(uId)) {
            createSelectedMembers.push(uId);
        }
        renderCreatePills();
    }

    function toggleCreateMember(userId) {
        const uId = parseInt(userId);
        const pos = createSelectedMembers.indexOf(uId);
        if (pos > -1) {
            createSelectedMembers.splice(pos, 1);
            const assignSelect = document.getElementById('ct_assigned_to');
            if (assignSelect && parseInt(assignSelect.value) === uId) {
                assignSelect.value = createSelectedMembers[0] || '';
            }
        } else {
            createSelectedMembers.push(uId);
            const assignSelect = document.getElementById('ct_assigned_to');
            if (assignSelect && !assignSelect.value) {
                assignSelect.value = uId;
            }
        }
        renderCreatePills();
    }

    function renderCreatePills() {
        const container = document.getElementById('ct_members_pills_container');
        const badge = document.getElementById('ct_members_count_badge');
        if (!container) return;

        if (badge) {
            badge.textContent = `${createSelectedMembers.length} Selected`;
        }

        const deptElem = document.getElementById('ct_department');
        const deptNorm = (deptElem ? deptElem.value : '').toLowerCase();
        let displayUsers = allStaffUsers;
        if (deptNorm) {
            const deptUsers = allStaffUsers.filter(u => (u.department || '').toLowerCase() === deptNorm);
            const otherUsers = allStaffUsers.filter(u => (u.department || '').toLowerCase() !== deptNorm);
            displayUsers = deptUsers.concat(otherUsers);
        }

        let html = '';
        displayUsers.forEach(u => {
            const isSel = createSelectedMembers.includes(parseInt(u.id));
            const isDept = deptNorm && (u.department || '').toLowerCase() === deptNorm;
            if (isSel) {
                html += `
                    <button type="button" class="btn btn-sm" onclick="toggleCreateMember(${u.id})" 
                        style="background:#2563eb; color:#ffffff; border:1px solid #1d4ed8; font-size:11.5px; padding:3px 8px; border-radius:14px; font-weight:600; display:inline-flex; align-items:center; gap:4px; cursor:pointer;" title="Click to remove">
                        <span>✓ ${escapeHtml(u.full_name)}</span>
                        <small style="opacity:0.85; font-size:9.5px;">(${escapeHtml(u.role_name || u.department)})</small>
                    </button>
                `;
            } else {
                html += `
                    <button type="button" class="btn btn-sm" onclick="toggleCreateMember(${u.id})" 
                        style="background:#ffffff; color:#334155; border:1px solid ${isDept ? '#93c5fd' : '#cbd5e1'}; font-size:11.5px; padding:3px 8px; border-radius:14px; font-weight:500; display:inline-flex; align-items:center; gap:4px; cursor:pointer;" title="Click to assign">
                        <span>+ ${escapeHtml(u.full_name)}</span>
                        <small style="color:#64748b; font-size:9.5px;">(${escapeHtml(u.role_name || u.department)})</small>
                    </button>
                `;
            }
        });

        container.innerHTML = html;
    }

    function addNewTaskObserver(userId) {
        const uId = parseInt(userId);
        if (!uId) return;
        if (!createSelectedObservers.includes(uId)) {
            createSelectedObservers.push(uId);
        }
        renderCreateObserverPills();
        const sel = document.getElementById('ct_add_observer_select');
        if (sel) sel.value = '';
    }

    function removeNewTaskObserver(userId) {
        const uId = parseInt(userId);
        createSelectedObservers = createSelectedObservers.filter(id => id !== uId);
        renderCreateObserverPills();
    }

    function renderCreateObserverPills() {
        const container = document.getElementById('ct_observers_pills_container');
        const badge = document.getElementById('ct_observers_count_badge');
        if (!container) return;

        if (badge) {
            badge.textContent = `${createSelectedObservers.length} Observer${createSelectedObservers.length === 1 ? '' : 's'}`;
        }

        if (createSelectedObservers.length === 0) {
            container.innerHTML = `<span style="font-size:11px; color:#94a3b8; font-style:italic;">No observers added yet.</span>`;
            return;
        }

        let html = '';
        createSelectedObservers.forEach(obsId => {
            const u = allStaffUsers.find(x => parseInt(x.id) === obsId);
            const name = u ? u.full_name : `User #${obsId}`;
            const role = u ? (u.role_name || u.department || '') : '';
            html += `
                <span class="badge" style="background:#f3e8ff; color:#6b21a8; border:1px solid #d8b4fe; font-size:11px; padding:3px 8px; border-radius:12px; font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                    <span>👁️ ${escapeHtml(name)}</span>
                    ${role ? `<small style="opacity:0.75; font-size:9.5px;">(${escapeHtml(role)})</small>` : ''}
                    <button type="button" onclick="removeNewTaskObserver(${obsId})" style="background:none; border:none; color:#7e22ce; font-size:13px; font-weight:700; cursor:pointer; padding:0 2px; line-height:1;" title="Remove observer">&times;</button>
                </span>
            `;
        });
        container.innerHTML = html;
    }

    function submitCreateTask() {
        let assignedTo = parseInt(document.getElementById('ct_assigned_to').value) || 0;
        if (!assignedTo && createSelectedMembers.length > 0) {
            assignedTo = createSelectedMembers[0];
        }

        const isSubtask = document.getElementById('ct_is_subtask')?.checked ? 1 : 0;
        const parentTaskId = isSubtask ? (parseInt(document.getElementById('ct_parent_task_id')?.value || '0') || 0) : 0;

        const payload = {
            project_id: document.getElementById('ct_project_id').value,
            title: document.getElementById('ct_title').value,
            description: document.getElementById('ct_description').value,
            department: document.getElementById('ct_department') ? document.getElementById('ct_department').value : 'Development',
            assigned_to: assignedTo,
            assignees: createSelectedMembers,
            observers: createSelectedObservers,
            priority: document.getElementById('ct_priority').value,
            start_date: document.getElementById('ct_start_date') ? document.getElementById('ct_start_date').value : '',
            end_date: document.getElementById('ct_end_date') ? document.getElementById('ct_end_date').value : '',
            sla_hours: document.getElementById('ct_sla_hours').value,
            is_subtask: isSubtask,
            parent_task_id: parentTaskId
        };

        if (!payload.project_id || !payload.title) {
            alert('Please choose a Project and enter a Task Title.');
            return;
        }

        const endpoint = isSubtask ? 'api.php?action=create_subtask' : 'api.php?action=create_task';
        fetch(endpoint, {
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
                loadCurrentProjects();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function addProjectModalCompetitorRow(comp = {}) {
        const list = document.getElementById('cp_competitors_list');
        if (!list) return;
        const row = document.createElement('div');
        row.className = 'cp-comp-row';
        row.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong style="font-size:12px; color:#581c87;">Competitor Entry</strong>
                <button type="button" class="btn btn-outline btn-sm" style="color:#dc2626; border-color:#fecaca; padding:2px 8px;" onclick="removeProjectModalCompetitorRow(this)">Remove</button>
            </div>
            <div class="grid-3" style="margin-bottom:8px;">
                <div>
                    <label class="form-label" style="font-size:11px;">Competitor Name *</label>
                    <input type="text" class="form-control cp-comp-name" placeholder="e.g. Acme Agency" value="${escapeHtml(comp.competitor_name || '')}">
                </div>
                <div>
                    <label class="form-label" style="font-size:11px;">Website URL</label>
                    <input type="text" class="form-control cp-comp-url" placeholder="https://competitor.com" value="${escapeHtml(comp.website_url || '')}">
                </div>
                <div>
                    <label class="form-label" style="font-size:11px;">Social Links / Handles</label>
                    <input type="text" class="form-control cp-comp-social" placeholder="IG @brand, LinkedIn..." value="${escapeHtml(comp.social_links || '')}">
                </div>
            </div>
            <div class="grid-3">
                <div>
                    <label class="form-label" style="font-size:11px;">Strengths</label>
                    <input type="text" class="form-control cp-comp-strengths" placeholder="Strong SEO, fast checkout..." value="${escapeHtml(comp.strengths || '')}">
                </div>
                <div>
                    <label class="form-label" style="font-size:11px;">Weaknesses</label>
                    <input type="text" class="form-control cp-comp-weaknesses" placeholder="Dated UI, weak content..." value="${escapeHtml(comp.weaknesses || '')}">
                </div>
                <div>
                    <label class="form-label" style="font-size:11px;">Strategy Notes</label>
                    <input type="text" class="form-control cp-comp-notes" placeholder="How to differentiate..." value="${escapeHtml(comp.notes || '')}">
                </div>
            </div>
        `;
        list.appendChild(row);
    }

    function removeProjectModalCompetitorRow(btn) {
        const row = btn.closest('.cp-comp-row');
        if (row) row.remove();
    }

    function collectModalCompetitors() {
        const rows = document.querySelectorAll('#cp_competitors_list .cp-comp-row');
        const list = [];
        rows.forEach(row => {
            const name = row.querySelector('.cp-comp-name')?.value?.trim() || '';
            if (!name) return;
            list.push({
                competitor_name: name,
                website_url: row.querySelector('.cp-comp-url')?.value?.trim() || '',
                social_links: row.querySelector('.cp-comp-social')?.value?.trim() || '',
                strengths: row.querySelector('.cp-comp-strengths')?.value?.trim() || '',
                weaknesses: row.querySelector('.cp-comp-weaknesses')?.value?.trim() || '',
                notes: row.querySelector('.cp-comp-notes')?.value?.trim() || ''
            });
        });
        return list;
    }

    function toggleProjectModalHandler(uid) {
        const id = parseInt(uid);
        const idx = projectModalHandlers.indexOf(id);
        if (idx > -1) projectModalHandlers.splice(idx, 1);
        else projectModalHandlers.push(id);
        renderProjectModalHandlersPills();
    }

    function renderProjectModalHandlersPills() {
        const container = document.getElementById('cp_handlers_pills');
        const badge = document.getElementById('cp_handlers_badge');
        if (!container) return;
        if (badge) badge.textContent = `${projectModalHandlers.length} Selected`;

        let html = '';
        allStaffUsers.forEach(u => {
            const uid = parseInt(u.id);
            const selected = projectModalHandlers.includes(uid);
            html += `
                <button type="button" onclick="toggleProjectModalHandler(${uid})"
                    style="background:${selected ? '#2563eb' : '#ffffff'}; color:${selected ? '#ffffff' : '#334155'}; border:1px solid ${selected ? '#1d4ed8' : '#cbd5e1'}; font-size:11.5px; padding:4px 10px; border-radius:16px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">
                    <span>${selected ? '✓' : '+'} ${escapeHtml(u.full_name)}</span>
                    <small style="opacity:0.8; font-size:10px;">(${escapeHtml(u.role_name || u.department)})</small>
                </button>
            `;
        });
        container.innerHTML = html;
    }

    function openCreateProjectModal() {
        document.getElementById('cp_project_id').value = '';
        document.getElementById('cp_modal_title').textContent = 'Add Current Project (Overview & Competitors)';
        document.getElementById('cp_submit_btn').textContent = 'Save Current Project';
        document.getElementById('cp_comp_hint').textContent = 'Add key competitors to benchmark against for this project.';

        document.getElementById('cp_client_id').value = '';
        document.getElementById('cp_project_name').value = '';
        document.getElementById('cp_department').value = currentUserDept || 'Development';
        document.getElementById('cp_team_lead_id').value = String(currentUserId || '');
        document.getElementById('cp_status').value = 'In Progress';
        document.getElementById('cp_priority').value = 'High';
        document.getElementById('cp_start_date').value = new Date().toISOString().slice(0, 10);
        const dl = new Date();
        dl.setDate(dl.getDate() + 30);
        document.getElementById('cp_deadline').value = dl.toISOString().slice(0, 10);
        document.getElementById('cp_is_current').checked = true;
        document.getElementById('cp_about_project').value = '';
        document.getElementById('cp_description').value = '';
        document.getElementById('cp_target_audience').value = '';
        document.getElementById('cp_budget_info').value = '';
        document.getElementById('cp_project_url').value = '';
        document.getElementById('cp_deliverables_summary').value = '';

        projectModalHandlers = [];
        renderProjectModalHandlersPills();

        const list = document.getElementById('cp_competitors_list');
        if (list) list.innerHTML = '';
        addProjectModalCompetitorRow();

        document.getElementById('createProjectModal').style.display = 'flex';
    }

    function openEditProjectModal(projectId) {
        fetch('api.php?action=get_project_details&project_id=' + encodeURIComponent(projectId))
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.project) {
                    alert(data.message || 'Could not load project details.');
                    return;
                }
                const p = data.project;
                document.getElementById('cp_project_id').value = p.id;
                document.getElementById('cp_modal_title').textContent = 'Edit Current Project: ' + (p.project_name || '');
                document.getElementById('cp_submit_btn').textContent = 'Update Project Details';
                document.getElementById('cp_comp_hint').textContent = 'Add new competitors below (manage existing competitors inside Project Overview & Competitors).';

                document.getElementById('cp_client_id').value = String(p.client_id || '');
                document.getElementById('cp_project_name').value = p.project_name || '';
                document.getElementById('cp_department').value = p.department || 'Development';
                document.getElementById('cp_team_lead_id').value = String(p.team_lead_id || currentUserId || '');
                document.getElementById('cp_status').value = p.status || 'In Progress';
                document.getElementById('cp_priority').value = p.priority || 'High';
                document.getElementById('cp_start_date').value = p.start_date || new Date().toISOString().slice(0, 10);
                document.getElementById('cp_deadline').value = p.deadline || '';
                document.getElementById('cp_is_current').checked = parseInt(p.is_current ?? 1) === 1;
                document.getElementById('cp_about_project').value = p.about_project || '';
                document.getElementById('cp_description').value = p.description || '';
                document.getElementById('cp_target_audience').value = p.target_audience || '';
                document.getElementById('cp_budget_info').value = p.budget_info || '';
                document.getElementById('cp_project_url').value = p.project_url || '';
                document.getElementById('cp_deliverables_summary').value = p.deliverables_summary || '';

                projectModalHandlers = Array.isArray(p.handler_ids) ? p.handler_ids.map(id => parseInt(id)) : [];
                renderProjectModalHandlersPills();

                const list = document.getElementById('cp_competitors_list');
                if (list) list.innerHTML = '';

                document.getElementById('createProjectModal').style.display = 'flex';
            })
            .catch(() => alert('Network error loading project details.'));
    }

    async function submitCreateProject() {
        const editId = document.getElementById('cp_project_id').value;
        const clientEl = document.getElementById('cp_client_id');
        const projectName = document.getElementById('cp_project_name').value.trim();
        const clientName = clientEl.options[clientEl.selectedIndex]?.text || '';
        const newCompetitors = collectModalCompetitors();

        const payload = {
            project_id: editId ? parseInt(editId) : undefined,
            client_id: clientEl.value,
            project_name: projectName,
            department: document.getElementById('cp_department').value,
            team_lead_id: document.getElementById('cp_team_lead_id').value,
            handlers: projectModalHandlers,
            status: document.getElementById('cp_status').value,
            priority: document.getElementById('cp_priority').value,
            start_date: document.getElementById('cp_start_date').value,
            deadline: document.getElementById('cp_deadline').value,
            is_current: document.getElementById('cp_is_current').checked ? 1 : 0,
            about_project: document.getElementById('cp_about_project').value,
            description: document.getElementById('cp_description').value,
            target_audience: document.getElementById('cp_target_audience').value,
            budget_info: document.getElementById('cp_budget_info').value,
            project_url: document.getElementById('cp_project_url').value,
            deliverables_summary: document.getElementById('cp_deliverables_summary').value
        };

        if (!editId) {
            payload.competitors = newCompetitors;
        }

        const actionUrl = editId ? 'api.php?action=update_project' : 'api.php?action=create_project';

        try {
            const res = await fetch(actionUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) {
                alert('Error: ' + (data.message || 'Could not save project.'));
                return;
            }

            if (editId && newCompetitors.length > 0) {
                for (const comp of newCompetitors) {
                    await fetch('api.php?action=save_project_competitor', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ project_id: parseInt(editId), ...comp })
                    });
                }
            }

            // Keep dropdowns synced for immediate "+ Create Task" on newly created project
            if (!editId && data.project_id) {
                const label = `${projectName} (${clientName})`;
                ['ct_project_id', 'filterProject'].forEach(selId => {
                    const sel = document.getElementById(selId);
                    if (sel && !Array.from(sel.options).some(o => o.value == String(data.project_id))) {
                        const opt = document.createElement('option');
                        opt.value = String(data.project_id);
                        opt.textContent = label;
                        if (selId === 'filterProject') opt.setAttribute('data-client', String(payload.client_id));
                        sel.appendChild(opt);
                    }
                });
            }

            closeModal('createProjectModal');
            alert('🚀 ' + data.message);
            switchWorkspaceView('projects', true);
            loadMetrics();
        } catch (err) {
            alert('Network error while saving project.');
        }
    }

    function openSubmitWorkModal(taskId, title) {
        document.getElementById('sw_task_id').value = taskId;
        document.getElementById('sw_task_title').textContent = title;
        const mf = document.getElementById('sw_media_files');
        if (mf) mf.value = '';
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

    async function submitWork() {
        const taskIdVal = document.getElementById('sw_task_id').value;
        const mf = document.getElementById('sw_media_files');
        if (mf && mf.files && mf.files.length > 0) {
            const fd = new FormData();
            fd.append('task_id', taskIdVal);
            fd.append('caption', 'Work submission attachment');
            fd.append('attachment_stage', 'submission');
            for (let i = 0; i < mf.files.length; i++) {
                fd.append('files[]', mf.files[i]);
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
        const payload = {
            task_id: taskIdVal,
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

    function leadApproveTaskInList(taskId) {
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
                loadMetrics();
                loadTasks();
            } else {
                alert('⚠️ ' + (data.message || 'Lead approval failed.'));
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

    // =========================================================
    // BULK IMPORT CURRENT PROJECTS (CSV / Excel Paste) FOR LEAD
    // =========================================================
    let parsedImportProjects = [];

    function openImportProjectsModal() {
        const fi = document.getElementById('impProj_file_input');
        const tx = document.getElementById('impProj_csv_text');
        const pv = document.getElementById('impProjPreviewContainer');
        const btn = document.getElementById('impProjSubmitBtn');
        if (fi) fi.value = '';
        if (tx) tx.value = '';
        if (pv) pv.style.display = 'none';
        if (btn) btn.disabled = true;
        parsedImportProjects = [];
        document.getElementById('importProjectsModal').style.display = 'flex';
    }

    function downloadSampleProjectsCsv() {
        const rows = [
            ['Project Name', 'Client Name', 'Department', 'Lead Name', 'Status', 'Priority', 'Deadline', 'About Project', 'Overview', 'Target Audience', 'Deliverables', 'Project URL', 'Budget', 'Competitors'],
            ['E-commerce Growth & SEO', 'Acme Retail Ltd', 'Digital Marketing', '', 'In Progress', 'High', '2026-12-31', 'Q4 aggressive organic + paid funnel scaling.', 'Technical SEO fixes, landing pages, Meta & Google Ads.', 'Online apparel buyers aged 20-40', '1. SEO Audit 2. Ad Creatives 3. Checkout CRO', 'https://acmeretail.example', 'Monthly Retainer', 'RivalBrand (https://rivalbrand.example); ShopHub'],
            ['Corporate Web Redesign', 'Nova Health Systems', 'Development', '', 'Planning', 'Urgent', '2027-01-15', 'Modern mobile-first healthcare booking experience.', 'Custom portal redesign with appointment booking workflow.', 'Clinic patients & referring doctors', 'UI/UX Figma, Frontend Build, CMS Migration', 'https://novahealth.example', 'Milestone Project', 'MediPortal (https://mediportal.example)']
        ];
        const csvString = rows.map(r => r.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\r\n');
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'sample_current_projects_import.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function handleProjectsFileUpload(event) {
        const file = event.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('impProj_csv_text').value = e.target.result || '';
            parseProjectsCsvInput();
        };
        reader.readAsText(file);
    }

    function parseDelimitedProjectText(text) {
        const firstLine = (text.split(/\r?\n/)[0] || '');
        const delimiter = (firstLine.split('\t').length > firstLine.split(',').length) ? '\t' : ',';

        const rows = [];
        let row = [];
        let cur = '';
        let inQuotes = false;

        for (let i = 0; i < text.length; i++) {
            const ch = text[i];
            const next = text[i + 1];
            if (inQuotes) {
                if (ch === '"' && next === '"') {
                    cur += '"';
                    i++;
                } else if (ch === '"') {
                    inQuotes = false;
                } else {
                    cur += ch;
                }
            } else {
                if (ch === '"') {
                    inQuotes = true;
                } else if (ch === delimiter) {
                    row.push(cur.trim());
                    cur = '';
                } else if (ch === '\r' && next === '\n') {
                    row.push(cur.trim());
                    rows.push(row);
                    row = [];
                    cur = '';
                    i++;
                } else if (ch === '\n' || ch === '\r') {
                    row.push(cur.trim());
                    rows.push(row);
                    row = [];
                    cur = '';
                } else {
                    cur += ch;
                }
            }
        }
        if (cur.length > 0 || row.length > 0) {
            row.push(cur.trim());
            rows.push(row);
        }
        return rows.filter(r => r.some(cell => cell !== ''));
    }

    function normalizeImportHeader(h) {
        const s = String(h || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
        if (['project name', 'project', 'project title', 'title', 'name'].includes(s)) return 'project_name';
        if (['client name', 'client', 'company', 'company name', 'account'].includes(s)) return 'client_name';
        if (['department', 'dept', 'team'].includes(s)) return 'department';
        if (['lead name', 'lead', 'team lead', 'assigned lead', 'project lead'].includes(s)) return 'lead_name';
        if (['status', 'project status'].includes(s)) return 'status';
        if (['priority'].includes(s)) return 'priority';
        if (['start date', 'start'].includes(s)) return 'start_date';
        if (['deadline', 'due date', 'end date', 'target deadline'].includes(s)) return 'deadline';
        if (['about project', 'about', 'about the project', 'brief', 'vision'].includes(s)) return 'about_project';
        if (['overview', 'description', 'project overview', 'scope', 'details'].includes(s)) return 'description';
        if (['target audience', 'audience'].includes(s)) return 'target_audience';
        if (['deliverables', 'deliverables summary', 'key deliverables'].includes(s)) return 'deliverables_summary';
        if (['project url', 'url', 'website', 'link', 'asset url'].includes(s)) return 'project_url';
        if (['budget', 'budget info', 'retainer'].includes(s)) return 'budget_info';
        if (['competitors', 'competitor', 'competitor list'].includes(s)) return 'competitors';
        if (['competitor name'].includes(s)) return 'competitor_name';
        if (['competitor url', 'competitor website'].includes(s)) return 'competitor_url';
        if (['competitor strengths'].includes(s)) return 'competitor_strengths';
        if (['competitor weaknesses'].includes(s)) return 'competitor_weaknesses';
        if (['competitor notes'].includes(s)) return 'competitor_notes';
        if (['current', 'is current'].includes(s)) return 'is_current';
        return '';
    }

    function parseProjectsCsvInput() {
        const rawText = (document.getElementById('impProj_csv_text')?.value || '').trim();
        const previewContainer = document.getElementById('impProjPreviewContainer');
        const submitBtn = document.getElementById('impProjSubmitBtn');
        const tbody = document.getElementById('impProjPreviewTbody');
        const countSpan = document.getElementById('impProjRowCount');

        if (!rawText) {
            previewContainer.style.display = 'none';
            submitBtn.disabled = true;
            parsedImportProjects = [];
            return;
        }

        const matrix = parseDelimitedProjectText(rawText);
        if (!matrix.length) {
            previewContainer.style.display = 'none';
            submitBtn.disabled = true;
            parsedImportProjects = [];
            return;
        }

        const defaultOrder = [
            'project_name', 'client_name', 'department', 'lead_name', 'status',
            'priority', 'deadline', 'about_project', 'description', 'target_audience',
            'deliverables_summary', 'project_url', 'budget_info', 'competitors'
        ];

        const firstRowMapped = matrix[0].map(normalizeImportHeader);
        const hasHeaderRow = firstRowMapped.includes('project_name') || firstRowMapped.includes('client_name') || firstRowMapped.includes('description') || firstRowMapped.includes('about_project');
        const colMap = hasHeaderRow ? firstRowMapped : defaultOrder;
        const startIdx = hasHeaderRow ? 1 : 0;

        parsedImportProjects = [];
        for (let i = startIdx; i < matrix.length; i++) {
            const cols = matrix[i];
            const obj = {};
            cols.forEach((val, colIdx) => {
                const key = colMap[colIdx] || '';
                if (key && val !== '') {
                    obj[key] = val;
                }
            });
            if (obj.project_name && obj.project_name.trim() !== '') {
                parsedImportProjects.push(obj);
            }
        }

        if (!parsedImportProjects.length) {
            previewContainer.style.display = 'none';
            submitBtn.disabled = true;
            return;
        }

        let html = '';
        parsedImportProjects.forEach((r, idx) => {
            const compSummary = r.competitors || r.competitor_name || '';
            const compCount = compSummary ? compSummary.split(/[;|]+/).filter(x => x.trim()).length : 0;
            const snippet = r.about_project || r.description || '';
            html += `
                <tr>
                    <td>${idx + 1}</td>
                    <td>
                        <strong>${escapeHtml(r.project_name)}</strong><br>
                        <small style="color:#64748b;">🏢 ${escapeHtml(r.client_name || 'General / Agency Client')}</small>
                    </td>
                    <td>
                        <span>${escapeHtml(r.lead_name || 'Self (Default Lead)')}</span><br>
                        <small style="color:#2563eb;">${escapeHtml(r.department || currentUserDept || 'Development')}</small>
                    </td>
                    <td>
                        <span class="badge badge-secondary">${escapeHtml(r.status || 'In Progress')}</span><br>
                        <small style="color:#64748b;">Due: ${escapeHtml(r.deadline || 'Default +30d')}</small>
                    </td>
                    <td style="max-width:220px; font-size:11.5px; color:#334155;">
                        ${snippet ? escapeHtml(snippet.length > 75 ? snippet.substring(0, 75) + '...' : snippet) : '<span style="color:#94a3b8;">-</span>'}
                    </td>
                    <td>
                        ${compCount > 0 ? `<span class="badge" style="background:#fdf4ff; color:#7e22ce;">🥊 ${compCount}</span>` : '<span style="color:#94a3b8;">0</span>'}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        countSpan.textContent = parsedImportProjects.length;
        previewContainer.style.display = 'block';
        submitBtn.disabled = false;
    }

    function submitImportProjects() {
        if (!parsedImportProjects.length) {
            alert('Please upload or paste at least one project row first.');
            return;
        }

        let dupMode = 'skip';
        document.getElementsByName('imp_proj_dup_mode').forEach(r => {
            if (r.checked) dupMode = r.value;
        });

        const btn = document.getElementById('impProjSubmitBtn');
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Importing Projects...';

        fetch('api.php?action=import_projects', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                duplicate_mode: dupMode,
                projects: parsedImportProjects
            })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = origText;
            if (!data.success) {
                alert('Import Error: ' + (data.message || 'Could not import projects.'));
                return;
            }

            if (Array.isArray(data.created_projects)) {
                data.created_projects.forEach(cp => {
                    const label = `${cp.project_name} (${cp.client_name})`;
                    ['ct_project_id', 'filterProject'].forEach(selId => {
                        const sel = document.getElementById(selId);
                        if (sel && !Array.from(sel.options).some(o => o.value == String(cp.id))) {
                            const opt = document.createElement('option');
                            opt.value = String(cp.id);
                            opt.textContent = label;
                            if (selId === 'filterProject') opt.setAttribute('data-client', String(cp.client_id));
                            sel.appendChild(opt);
                        }
                    });
                });
            }

            let alertMsg = '🚀 ' + data.message;
            if (Array.isArray(data.errors) && data.errors.length > 0) {
                alertMsg += '\n\nNotes / Skipped Rows:\n' + data.errors.slice(0, 8).join('\n');
                if (data.errors.length > 8) alertMsg += `\n...and ${data.errors.length - 8} more.`;
            }
            closeModal('importProjectsModal');
            alert(alertMsg);
            switchWorkspaceView('projects', true);
            loadMetrics();
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = origText;
            alert('Network error while importing projects.');
        });
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
