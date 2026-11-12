<?php
// project_details.php - Project Overview, About Project, Competitor Details & Project Tasks Workspace

require_once __DIR__ . '/auth.php';
checkAuth('task-management');

$user = getCurrentUser();
$db = getDbConnection();

$projectId = intval($_GET['id'] ?? ($_GET['project_id'] ?? 0));
$isLead = isLeadOrAdmin();

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

    <?php if ($isLead): ?>
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <button class="btn btn-outline btn-sm" onclick="openEditProjectModal()" style="border-radius:8px;">
            ✏️ Edit Project & About
        </button>
        <button class="btn btn-outline btn-sm" onclick="openCompetitorModal(0)" style="border-radius:8px; color:#b45309; border-color:#fcd34d; background:#fffbeb;">
            🏆 + Add Competitor
        </button>
        <button class="btn btn-primary btn-sm" onclick="openCreateProjectTaskModal()" style="border-radius:8px; font-weight:700; padding:8px 16px;">
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
            <p style="font-size:13.5px; color:#64748b; margin:0;">
                Client: <strong id="pd_client_name" style="color:#1e293b;">--</strong> &bull;
                Lead: <strong id="pd_lead_name" style="color:#2563eb;">--</strong> &bull;
                Timeline: <strong id="pd_timeline">--</strong>
            </p>
        </div>

        <div style="display:flex; align-items:center; gap:12px;">
            <div>
                <label style="display:block; font-size:10.5px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:3px;">Project Status</label>
                <?php if ($isLead): ?>
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
            <?php if ($isLead): ?>
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
    </div>
</div>

<!-- 3. COMPETITORS DETAILS & INTELLIGENCE -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 class="card-title" style="margin:0;">🏆 Competitors Details & Market Analysis</h3>
            <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Track competitor websites, strengths, weaknesses, and positioning notes for the team.</p>
        </div>
        <?php if ($isLead): ?>
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
            <select id="pdTaskStatusFilter" class="form-select" style="font-size:12.5px; padding:6px 10px; width:160px;" onchange="renderProjectTasks()">
                <option value="">All Statuses</option>
                <option value="PENDING">Pending</option>
                <option value="IN PROGRESS">In Progress</option>
                <option value="UNDER REVIEW">Under Review</option>
                <option value="REVISION REQUIRED">Revision Needed</option>
                <option value="COMPLETED">Completed</option>
            </select>
            <?php if ($isLead): ?>
                <button class="btn btn-primary btn-sm" onclick="openCreateProjectTaskModal()" style="font-weight:700;">
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
                <h3 class="modal-title" style="margin:0;">+ Create Task on Current Project</h3>
                <small id="cpt_project_subtitle" style="color:#2563eb; font-weight:600;"></small>
            </div>
            <button class="btn btn-outline btn-sm" onclick="closePdModal('createProjectTaskModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateProjectTask();">
            <div class="modal-body">
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

<script>
const pdProjectId = <?= json_encode($projectId) ?>;
const pdIsLead = <?= json_encode($isLead) ?>;
const pdStaffUsers = <?= json_encode($staffUsers) ?>;
let pdProjectData = null;
let pdCompetitors = [];
let pdTasks = [];

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
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:#94a3b8;">No tasks found on this project yet. ${pdIsLead ? 'Click "+ Create Task on Project" above to create one!' : ''}</td></tr>`;
        return;
    }

    let html = '';
    filtered.forEach(t => {
        const slaClass = t.sla_status === 'SLA Breached' ? 'badge-danger' : (t.sla_status === 'Near SLA Warning' ? 'badge-warning' : 'badge-primary');
        const safeTitle = escapeHtml(t.title);
        html += `
            <tr>
                <td><strong style="color:#2563eb;">${escapeHtml(t.task_code)}</strong></td>
                <td>
                    <a href="task_details.php?id=${t.id}" style="font-weight:700; color:#0f172a; text-decoration:none;">${safeTitle}</a>
                    ${t.department ? `<br><span class="badge" style="background:#eff6ff; color:#1e40af; font-size:10px; margin-top:2px;">📁 ${escapeHtml(t.department)}</span>` : ''}
                </td>
                <td>
                    <span class="badge badge-secondary">${escapeHtml(t.assigned_to_name || 'Unassigned')}</span>
                    ${t.assignee_ids && t.assignee_ids.length > 1 ? `<span class="badge" style="background:#dbeafe; color:#1e40af; font-size:10px;">+${t.assignee_ids.length - 1}</span>` : ''}
                    ${t.observer_ids && t.observer_ids.length > 0 ? `<br><small style="color:#7e22ce; font-size:10.5px;">👁️ ${t.observer_ids.length} Observer(s)</small>` : ''}
                </td>
                <td><span class="badge ${t.priority === 'Urgent' ? 'badge-danger' : 'badge-warning'}">${escapeHtml(t.priority)}</span></td>
                <td>
                    <select class="form-select" style="font-size:11.5px; font-weight:700; padding:4px 6px; width:130px;" onchange="updatePdTaskStatus(${t.id}, this.value)">
                        <option value="PENDING" ${t.status === 'PENDING' ? 'selected' : ''}>⏳ Pending</option>
                        <option value="IN PROGRESS" ${t.status === 'IN PROGRESS' ? 'selected' : ''}>⚡ In Progress</option>
                        <option value="UNDER REVIEW" ${t.status === 'UNDER REVIEW' ? 'selected' : ''}>🔍 Under Review</option>
                        <option value="REVISION REQUIRED" ${t.status === 'REVISION REQUIRED' ? 'selected' : ''}>🔄 Revision Needed</option>
                        <option value="COMPLETED" ${t.status === 'COMPLETED' ? 'selected' : ''}>✅ Completed</option>
                    </select>
                </td>
                <td><span class="badge ${slaClass}">${escapeHtml(t.sla_status)}</span></td>
                <td><small style="color:#475569;">${t.deadline ? t.deadline.substring(0, 16) : 'N/A'}</small></td>
                <td style="text-align:right; white-space:nowrap;">
                    <a href="task_details.php?id=${t.id}" class="btn btn-outline btn-sm" style="font-size:11px;">Details</a>
                    ${pdIsLead ? `
                        <button class="btn btn-outline btn-sm" onclick="openPdReassignModal(${t.id})" style="font-size:11px; color:#2563eb; border-color:#93c5fd;">👤 Assign</button>
                    ` : ''}
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
        }
    });
}

// --- EDIT PROJECT MODAL ---
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

// --- CREATE TASK ON CURRENT PROJECT MODAL ---
function openCreateProjectTaskModal() {
    if (!pdProjectData) return;
    document.getElementById('cpt_project_subtitle').textContent = `Project: ${pdProjectData.project_name} (${pdProjectData.company_name})`;
    document.getElementById('cpt_title').value = '';
    document.getElementById('cpt_description').value = '';
    if (pdProjectData.department && document.getElementById('cpt_department')) {
        document.getElementById('cpt_department').value = pdProjectData.department;
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

    const payload = {
        project_id: pdProjectId,
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
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

