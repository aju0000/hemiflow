<?php
// client_details.php - Super Admin & Management Client Overview, Projects & Done Tasks Progress Hub

require_once __DIR__ . '/auth.php';

// Handle Super Admin Live Client Portal Preview Mode
$clientId = intval($_GET['id'] ?? ($_GET['client_id'] ?? 0));

if (isset($_GET['exit_preview'])) {
    if (isset($_SESSION['impersonator_user'])) {
        $_SESSION['user'] = $_SESSION['impersonator_user'];
        $_SESSION['role'] = $_SESSION['impersonator_user']['role'];
        unset($_SESSION['impersonator_user']);
        unset($_SESSION['client_id']);
    }
    header('Location: client_details.php?id=' . $clientId);
    exit;
}

if (isset($_GET['preview_portal'])) {
    $currentUser = getCurrentUser();
    if ($currentUser && ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1 || hasModuleAccess('client-management'))) {
        $_SESSION['impersonator_user'] = $_SESSION['user'];
        $_SESSION['role'] = 'CLIENT';
        $_SESSION['client_id'] = $clientId;
        $_SESSION['module'] = 'client_dashboard';
        header('Location: /client/dashboard.php');
        exit;
    }
}

checkAuth('client-management');
$user = getCurrentUser();
$db = getDbConnection();

if ($clientId <= 0) {
    header('Location: clients.php');
    exit;
}

// 1. Fetch Client Profile & Linked Portal Account
$stmtClient = $db->prepare("
    SELECT c.*, u.full_name as created_by_name,
           usr.id as client_user_id, usr.username as client_username, usr.email as client_user_email, usr.is_active as client_user_active
    FROM clients c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN users usr ON usr.client_id = c.id
    WHERE c.id = ?
");
$stmtClient->execute([$clientId]);
$client = $stmtClient->fetch();

if (!$client) {
    die("Client not found. <a href='clients.php'>Back to Clients</a>");
}

// 2. Fetch Client Service Packages
$stmtPkg = $db->prepare("SELECT * FROM client_services WHERE client_id = ? ORDER BY id DESC");
$stmtPkg->execute([$clientId]);
$packages = $stmtPkg->fetchAll();

// 3. Fetch Projects with Task Counts
$stmtProj = $db->prepare("
    SELECT p.*, cs.service_name, cs.package_name,
           u.full_name as team_lead_name,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted = 0) as total_tasks,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'COMPLETED' AND t.is_deleted = 0) as completed_tasks,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'IN PROGRESS' AND t.is_deleted = 0) as in_progress_tasks,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status IN ('UNDER REVIEW', 'SENT FOR CLIENT APPROVAL') AND t.is_deleted = 0) as review_tasks,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status IN ('REVISION REQUIRED', 'CLIENT REVISION REQUESTED') AND t.is_deleted = 0) as revision_tasks,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'PENDING' AND t.is_deleted = 0) as pending_tasks
    FROM projects p
    LEFT JOIN client_services cs ON p.service_id = cs.id
    LEFT JOIN users u ON p.team_lead_id = u.id
    WHERE p.client_id = ?
    ORDER BY p.id DESC
");
$stmtProj->execute([$clientId]);
$projects = $stmtProj->fetchAll();

foreach ($projects as &$p) {
    $p['progress_pct'] = $p['total_tasks'] > 0 ? round(($p['completed_tasks'] / $p['total_tasks']) * 100) : 0;
}
unset($p);

// 4. Fetch Tasks for this Client
$stmtTasks = $db->prepare("
    SELECT t.*, p.project_name, u.full_name as assigned_to_name, u.department as developer_department,
           tl.full_name as team_lead_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users tl ON t.team_lead_id = tl.id
    WHERE t.client_id = ? AND t.is_deleted = 0
    ORDER BY CASE WHEN t.status = 'COMPLETED' THEN 2 ELSE 1 END, t.id DESC
");
$stmtTasks->execute([$clientId]);
$tasks = $stmtTasks->fetchAll();

// Calculate SLA status and aggregates
if (!function_exists('calculateTaskSLAStatus')) {
    function calculateTaskSLAStatus($task) {
        if ($task['status'] === 'COMPLETED') {
            if (!empty($task['completion_time']) && !empty($task['sla_due_time'])) {
                return strtotime($task['completion_time']) <= strtotime($task['sla_due_time']) 
                    ? 'Completed Within SLA' 
                    : 'Completed After SLA';
            }
            return 'Completed Within SLA';
        }
        $now = time();
        $due = !empty($task['sla_due_time']) ? strtotime($task['sla_due_time']) : ($now + ($task['sla_hours'] * 3600));
        if ($now > $due) {
            return 'SLA Breached';
        } elseif (($due - $now) <= (4 * 3600)) {
            return 'Near SLA Warning';
        } else {
            return 'Within SLA';
        }
    }
}

$totalProjects = count($projects);
$totalTasks = count($tasks);
$completedTasks = 0;
$inProgressTasks = 0;
$reviewTasks = 0;
$pendingTasks = 0;

foreach ($tasks as &$t) {
    $t['sla_status'] = calculateTaskSLAStatus($t);
    if ($t['status'] === 'COMPLETED') {
        $completedTasks++;
    } elseif ($t['status'] === 'IN PROGRESS') {
        $inProgressTasks++;
    } elseif (in_array($t['status'], ['UNDER REVIEW', 'SENT FOR CLIENT APPROVAL'])) {
        $reviewTasks++;
    } else {
        $pendingTasks++;
    }
}
unset($t);
$overallProgressPct = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

// Fetch Developers and Team Leads for modal assignment
$developers = $db->query("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 ORDER BY u.full_name ASC")->fetchAll();

$pageBreadcrumb = "Module 2";
$pageTitle = htmlspecialchars($client['company_name']) . " — Overview & Progress";
$pageSubtitle = "Super Admin Hub: Client details, live project progress, completed deliverables, and task performance.";
require_once __DIR__ . '/header.php';
?>

<!-- Action Pills Navigation Bar -->
<div class="action-pills-row">
    <a href="clients.php" class="btn-pill-white" style="text-decoration:none;">
        <i data-feather="arrow-left" class="icon-prefix"></i>
        <span>Back to Clients</span>
    </a>

    <a href="client_details.php?id=<?= $clientId ?>&preview_portal=1" target="_blank" class="btn-pill-primary" style="text-decoration:none;">
        <i data-feather="external-link" class="icon-prefix"></i>
        <span>Live Client Portal View</span>
        <i data-feather="arrow-right" style="width:16px; height:16px; margin-left:4px;"></i>
    </a>

    <button class="btn-pill-white" onclick="openCreateProjectModal()">
        <i data-feather="folder-plus" class="icon-folder"></i>
        <span>+ New Project</span>
    </button>

    <button class="btn-pill-white" onclick="openCreateTaskModal()">
        <i data-feather="plus-circle" class="icon-tag"></i>
        <span>+ Assign Task</span>
    </button>

    <button class="btn-pill-white" onclick="openEditClientModal(<?= htmlspecialchars(json_encode($client)) ?>)">
        <i data-feather="edit-2" class="icon-edit"></i>
        <span>Edit Client Profile</span>
    </button>

    <button class="btn-pill-white" onclick="openServicePackagesModal(<?= $clientId ?>, '<?= htmlspecialchars(addslashes($client['company_name'])) ?>')">
        <i data-feather="package" class="icon-tag"></i>
        <span>Service Packages</span>
    </button>

    <a href="template_generator.php" class="btn-pill-white" style="text-decoration:none;">
        <i data-feather="file-text" class="icon-tag"></i>
        <span>Generate Proposal</span>
    </a>
</div>

<!-- Executive Summary KPI Cards -->
<div class="grid-4" style="margin-bottom: 24px;">
    <!-- Card 1: Total Projects -->
    <div class="card" style="margin-bottom:0; padding:20px; border-radius:14px; position:relative; overflow:hidden;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <span style="font-size:11px; font-weight:800; color:#64748b; letter-spacing:0.5px; text-transform:uppercase;">Client Projects</span>
                <h2 style="font-size:28px; font-weight:800; color:#0f172a; margin-top:6px;"><?= $totalProjects ?></h2>
                <div style="font-size:12px; color:#64748b; margin-top:4px;">
                    <strong><?= count(array_filter($projects, fn($p) => $p['status'] !== 'Completed')) ?></strong> Active &bull;
                    <strong><?= count(array_filter($projects, fn($p) => $p['status'] === 'Completed')) ?></strong> Completed
                </div>
            </div>
            <div style="width:42px; height:42px; border-radius:10px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center;">
                <i data-feather="folder" style="width:20px; height:20px;"></i>
            </div>
        </div>
    </div>

    <!-- Card 2: Overall Progress -->
    <div class="card" style="margin-bottom:0; padding:20px; border-radius:14px; position:relative; overflow:hidden;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div style="flex:1;">
                <span style="font-size:11px; font-weight:800; color:#64748b; letter-spacing:0.5px; text-transform:uppercase;">Overall Project Progress</span>
                <h2 style="font-size:28px; font-weight:800; color:#2563eb; margin-top:6px;"><?= $overallProgressPct ?>%</h2>
                <div style="font-size:12px; color:#475569; margin-top:4px; font-weight:600;">
                    <?= $completedTasks ?> of <?= $totalTasks ?> Tasks Done
                </div>
                <!-- Progress bar -->
                <div style="background:#e2e8f0; height:6px; border-radius:3px; overflow:hidden; margin-top:8px; width:90%;">
                    <div style="background:linear-gradient(90deg, #2563eb, #10b981); width:<?= $overallProgressPct ?>%; height:100%; border-radius:3px;"></div>
                </div>
            </div>
            <div style="width:42px; height:42px; border-radius:10px; background:#f0fdf4; color:#16a34a; display:flex; align-items:center; justify-content:center;">
                <i data-feather="trending-up" style="width:20px; height:20px;"></i>
            </div>
        </div>
    </div>

    <!-- Card 3: Tasks Done / Completed -->
    <div class="card" style="margin-bottom:0; padding:20px; border-radius:14px; position:relative; overflow:hidden; border-left:4px solid #16a34a;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <span style="font-size:11px; font-weight:800; color:#15803d; letter-spacing:0.5px; text-transform:uppercase;">Tasks Done (Completed)</span>
                <h2 style="font-size:28px; font-weight:800; color:#16a34a; margin-top:6px;"><?= $completedTasks ?></h2>
                <div style="font-size:12px; color:#16a34a; margin-top:4px; font-weight:600;">
                    ✓ Deliverables Successfully Completed
                </div>
            </div>
            <div style="width:42px; height:42px; border-radius:10px; background:#dcfce7; color:#15803d; display:flex; align-items:center; justify-content:center;">
                <i data-feather="check-circle" style="width:20px; height:20px;"></i>
            </div>
        </div>
    </div>

    <!-- Card 4: In Progress & Review -->
    <div class="card" style="margin-bottom:0; padding:20px; border-radius:14px; position:relative; overflow:hidden; border-left:4px solid #f59e0b;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <span style="font-size:11px; font-weight:800; color:#b45309; letter-spacing:0.5px; text-transform:uppercase;">Active & Under Review</span>
                <h2 style="font-size:28px; font-weight:800; color:#d97706; margin-top:6px;"><?= ($inProgressTasks + $reviewTasks) ?></h2>
                <div style="font-size:12px; color:#64748b; margin-top:4px;">
                    <strong><?= $inProgressTasks ?></strong> Developing &bull; <strong><?= $reviewTasks ?></strong> Review/Approval
                </div>
            </div>
            <div style="width:42px; height:42px; border-radius:10px; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center;">
                <i data-feather="clock" style="width:20px; height:20px;"></i>
            </div>
        </div>
    </div>
</div>

<!-- CLIENT PROFILE & ACCOUNT OVERVIEW CARD -->
<div class="card" style="margin-bottom:24px; padding:24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="width:56px; height:56px; border-radius:12px; background:linear-gradient(135deg, #2563eb, #1d4ed8); color:#ffffff; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:800; box-shadow:0 4px 10px rgba(37,99,235,0.25);">
                <?= strtoupper(substr($client['company_name'], 0, 2)) ?>
            </div>
            <div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <h2 style="margin:0; font-size:22px; font-weight:800; color:#0f172a; letter-spacing:-0.5px;"><?= htmlspecialchars($client['company_name']) ?></h2>
                    <span class="badge <?= $client['status'] === 'Approved / Active' ? 'badge-success' : 'badge-primary' ?>" style="font-size:12px;">
                        <?= htmlspecialchars($client['status']) ?>
                    </span>
                </div>
                <div style="font-size:13px; color:#64748b; margin-top:4px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <span><i data-feather="user" style="width:13px; height:13px; vertical-align:middle;"></i> <?= htmlspecialchars($client['contact_person']) ?></span>
                    <span>&bull;</span>
                    <span><i data-feather="mail" style="width:13px; height:13px; vertical-align:middle;"></i> <a href="mailto:<?= htmlspecialchars($client['email']) ?>" style="color:#2563eb; text-decoration:none;"><?= htmlspecialchars($client['email']) ?></a></span>
                    <span>&bull;</span>
                    <span><i data-feather="phone" style="width:13px; height:13px; vertical-align:middle;"></i> <?= htmlspecialchars($client['phone'] ?: 'No Phone') ?></span>
                    <?php if (!empty($client['tax_id'])): ?>
                        <span>&bull;</span>
                        <span><i data-feather="hash" style="width:13px; height:13px; vertical-align:middle;"></i> Tax ID: <?= htmlspecialchars($client['tax_id']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Portal Account Info Box -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 18px; text-align:right;">
            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Client Portal Account</div>
            <?php if (!empty($client['client_username'])): ?>
                <div style="margin-top:4px; display:flex; align-items:center; gap:8px; justify-content:flex-end;">
                    <span class="badge badge-info" style="font-family:monospace; font-size:12px;">@<?= htmlspecialchars($client['client_username']) ?></span>
                    <button class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;" onclick="openResetPasswordModal(<?= $client['id'] ?>, '<?= htmlspecialchars(addslashes($client['company_name'])) ?>', '<?= htmlspecialchars($client['client_username']) ?>')">
                        <i data-feather="key" style="width:11px; height:11px;"></i> Reset Password
                    </button>
                </div>
            <?php else: ?>
                <span class="badge badge-secondary" style="margin-top:4px;">No Portal User</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($client['notes'])): ?>
        <div style="margin-top:16px; padding:12px 16px; background:#f0f9ff; border-left:3px solid #0284c7; border-radius:0 8px 8px 0; font-size:13px; color:#0369a1;">
            <strong>Client Remarks & Brief:</strong> <?= nl2br(htmlspecialchars($client['notes'])) ?>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION: PROJECTS & REAL-TIME PROGRESS BARS -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 class="card-title" style="display:flex; align-items:center; gap:8px; font-size:17px; font-weight:700;">
                <i data-feather="folder" style="color:#2563eb; width:20px; height:20px;"></i>
                Projects & Progress Tracking (<?= count($projects) ?>)
            </h3>
            <p style="margin:2px 0 0 0; font-size:13px; color:#64748b;">
                Detailed completion breakdown, milestone statuses, and task execution progress for each project.
            </p>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openCreateProjectModal()">
            <i data-feather="plus"></i> + New Project
        </button>
    </div>

    <?php if (empty($projects)): ?>
        <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
            <div style="width:54px; height:54px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; margin:0 auto 12px auto; color:#64748b;">
                <i data-feather="folder-plus" style="width:26px; height:26px;"></i>
            </div>
            <h4 style="font-size:16px; color:#334155; margin:0 0 6px 0;">No Projects Created Yet</h4>
            <p style="font-size:13px; margin:0 0 16px 0;">Initialize a project to assign tasks, set deliverables, and track work progress.</p>
            <button class="btn btn-primary btn-sm" onclick="openCreateProjectModal()">Create First Project</button>
        </div>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:16px; padding:16px;">
            <?php foreach ($projects as $p): ?>
                <div style="border:1px solid #e2e8f0; border-radius:12px; padding:20px; background:#ffffff; box-shadow:0 2px 6px rgba(0,0,0,0.02); transition:all 0.2s ease;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <h4 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">
                                    <?= htmlspecialchars($p['project_name']) ?>
                                </h4>
                                <span class="badge <?= $p['status'] === 'Completed' ? 'badge-success' : 'badge-primary' ?>" style="font-size:11px;">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                                <?php if (!empty($p['service_name'])): ?>
                                    <span class="badge badge-secondary" style="font-size:11px;">
                                        <?= htmlspecialchars($p['service_name']) ?> (<?= htmlspecialchars($p['package_name'] ?? 'Standard') ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($p['description'])): ?>
                                <p style="font-size:13px; color:#64748b; margin:6px 0 0 0; line-height:1.5;">
                                    <?= htmlspecialchars($p['description']) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div style="text-align:right;">
                            <div style="font-size:12px; color:#64748b;">
                                Team Lead: <strong><?= htmlspecialchars($p['team_lead_name'] ?: 'Unassigned') ?></strong>
                            </div>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">
                                Target Deadline: <strong style="color:#0f172a;"><?= htmlspecialchars($p['deadline'] ?: 'No Deadline') ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar Section -->
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 18px; margin-top:16px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; color:#334155; margin-bottom:6px;">
                            <span>Project Progress</span>
                            <span style="color:#2563eb;"><?= $p['progress_pct'] ?>% Completed (<?= $p['completed_tasks'] ?> / <?= $p['total_tasks'] ?> Tasks Done)</span>
                        </div>
                        <div style="background:#cbd5e1; height:10px; border-radius:5px; overflow:hidden;">
                            <div style="background:linear-gradient(90deg, #2563eb, #10b981); width:<?= $p['progress_pct'] ?>%; height:100%; border-radius:5px; transition:width 0.6s ease;"></div>
                        </div>

                        <!-- Mini Status Pills Row -->
                        <div style="display:flex; gap:12px; margin-top:12px; font-size:12px; flex-wrap:wrap;">
                            <span style="color:#15803d; font-weight:700;">✓ Done: <?= $p['completed_tasks'] ?></span>
                            <span style="color:#2563eb; font-weight:600;">⚡ In Progress: <?= $p['in_progress_tasks'] ?></span>
                            <span style="color:#d97706; font-weight:600;">🔍 Review: <?= $p['review_tasks'] ?></span>
                            <?php if ($p['revision_tasks'] > 0): ?>
                                <span style="color:#dc2626; font-weight:600;">🔄 Revisions: <?= $p['revision_tasks'] ?></span>
                            <?php endif; ?>
                            <span style="color:#64748b;">⏳ Pending: <?= $p['pending_tasks'] ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION: DETAILED TASKS EXPLORER & DONE TASKS PROGRESS -->
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h3 class="card-title" style="display:flex; align-items:center; gap:8px; font-size:17px; font-weight:700;">
                <i data-feather="check-square" style="color:#10b981; width:20px; height:20px;"></i>
                Tasks & Deliverables Breakdown (<?= count($tasks) ?>)
            </h3>
            <p style="margin:2px 0 0 0; font-size:13px; color:#64748b;">
                See which tasks are completed (done), active in development, under review, or awaiting client approval.
            </p>
        </div>

        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <button class="btn btn-primary btn-sm" onclick="openCreateTaskModal()">
                <i data-feather="plus"></i> + Create Task
            </button>
        </div>
    </div>

    <!-- Task Filter & Search Bar -->
    <div style="padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <!-- Status Filter Buttons -->
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <button class="btn btn-sm btn-primary" id="btnFilterAll" onclick="filterTasksByStatus('')">
                All Tasks (<?= count($tasks) ?>)
            </button>
            <button class="btn btn-sm btn-outline" id="btnFilterDone" style="color:#15803d; border-color:#86efac; background:#f0fdf4;" onclick="filterTasksByStatus('COMPLETED')">
                ✓ Done / Completed (<?= $completedTasks ?>)
            </button>
            <button class="btn btn-sm btn-outline" id="btnFilterProgress" onclick="filterTasksByStatus('IN PROGRESS')">
                ⚡ In Progress (<?= $inProgressTasks ?>)
            </button>
            <button class="btn btn-sm btn-outline" id="btnFilterReview" onclick="filterTasksByStatus('UNDER REVIEW')">
                🔍 Review (<?= $reviewTasks ?>)
            </button>
            <button class="btn btn-sm btn-outline" id="btnFilterPending" onclick="filterTasksByStatus('PENDING')">
                ⏳ Pending (<?= $pendingTasks ?>)
            </button>
        </div>

        <!-- Project Selector & Search Input -->
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <select id="taskProjectFilter" class="form-select" style="font-size:12px; width:220px;" onchange="applyTaskFilters()">
                <option value="0">All Projects</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="text" id="taskSearchInput" class="form-control" style="font-size:12px; width:180px;" placeholder="Search task title/code..." oninput="applyTaskFilters()">
        </div>
    </div>

    <!-- Task Table -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Task Code</th>
                    <th>Task Title & Requirements</th>
                    <th>Project</th>
                    <th>Assigned Developer</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>SLA Status</th>
                    <th>Deadline</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="clientTasksTableBody">
                <?php if (empty($tasks)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding:35px; color:var(--text-muted);">
                            No tasks created for this client yet. Click <strong>+ Create Task</strong> to assign deliverables.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tasks as $t): ?>
                        <?php 
                            $isDone = ($t['status'] === 'COMPLETED');
                            $slaClass = $t['sla_status'] === 'SLA Breached' ? 'badge-danger' : ($t['sla_status'] === 'Near SLA Warning' ? 'badge-warning' : 'badge-primary');
                        ?>
                        <tr class="task-row" data-status="<?= htmlspecialchars($t['status']) ?>" data-project="<?= $t['project_id'] ?>" data-search="<?= strtolower(htmlspecialchars($t['task_code'] . ' ' . $t['title'])) ?>" style="<?= $isDone ? 'background:#f0fdf4;' : '' ?>">
                            <td>
                                <strong style="color:<?= $isDone ? '#15803d' : '#2563eb' ?>; font-family:monospace; font-size:13px;">
                                    <?= htmlspecialchars($t['task_code']) ?>
                                </strong>
                            </td>
                            <td>
                                <a href="task_details.php?id=<?= $t['id'] ?>" style="font-weight:700; color:#0f172a; text-decoration:none;">
                                    <?= htmlspecialchars($t['title']) ?>
                                </a>
                                <?php if (!empty($t['description'])): ?>
                                    <div style="font-size:12px; color:#64748b; margin-top:2px; max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        <?= htmlspecialchars($t['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size:13px; font-weight:600; color:#334155;">
                                    <?= htmlspecialchars($t['project_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-size:12px;">
                                    <?= htmlspecialchars($t['assigned_to_name'] ?: 'Unassigned') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $t['priority'] === 'Urgent' ? 'badge-danger' : ($t['priority'] === 'High' ? 'badge-warning' : 'badge-secondary') ?>">
                                    <?= htmlspecialchars($t['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isDone): ?>
                                    <span class="badge badge-success" style="background:#dcfce7; color:#15803d; border:1px solid #86efac; font-weight:800; font-size:12px; display:inline-flex; align-items:center; gap:4px; padding:4px 10px;">
                                        <i data-feather="check-circle" style="width:13px; height:13px;"></i> DONE / COMPLETED
                                    </span>
                                <?php elseif ($t['status'] === 'IN PROGRESS'): ?>
                                    <span class="badge badge-primary" style="font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                                        <i data-feather="loader" style="width:13px; height:13px;"></i> In Progress
                                    </span>
                                <?php elseif ($t['status'] === 'UNDER REVIEW'): ?>
                                    <span class="badge badge-warning" style="font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                                        <i data-feather="eye" style="width:13px; height:13px;"></i> Under Review
                                    </span>
                                <?php elseif ($t['status'] === 'SENT FOR CLIENT APPROVAL'): ?>
                                    <span class="badge badge-warning" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                                        <i data-feather="alert-triangle" style="width:13px; height:13px;"></i> Client Approval
                                    </span>
                                <?php elseif ($t['status'] === 'REVISION REQUIRED' || $t['status'] === 'CLIENT REVISION REQUESTED'): ?>
                                    <span class="badge badge-danger" style="font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                                        <i data-feather="refresh-cw" style="width:13px; height:13px;"></i> Revision Needed
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="font-size:12px;">
                                        <?= htmlspecialchars($t['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $slaClass ?>" style="font-size:11px;">
                                    <?= htmlspecialchars($t['sla_status']) ?>
                                </span>
                            </td>
                            <td>
                                <small style="color:#475569; font-weight:600;">
                                    <?= $t['deadline'] ? substr($t['deadline'], 0, 16) : 'N/A' ?>
                                </small>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <a href="task_details.php?id=<?= $t['id'] ?>" class="btn btn-outline btn-sm" title="View discussion, deliverables & activity log">
                                        <i data-feather="file-text"></i> Details
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL 1: CREATE PROJECT FOR THIS CLIENT -->
<div id="createProjectModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">📁 Create New Project for <?= htmlspecialchars($client['company_name']) ?></h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('createProjectModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateProject();">
            <div class="modal-body">
                <input type="hidden" id="cp_client_id" value="<?= $clientId ?>">
                
                <div class="form-group">
                    <label class="form-label">Project Name *</label>
                    <input type="text" id="cp_project_name" class="form-control" required placeholder="e.g. Website Redesign & SEO Campaign">
                </div>

                <div class="form-group">
                    <label class="form-label">Project Description</label>
                    <textarea id="cp_description" class="form-control" rows="3" placeholder="Outline deliverables, objectives, and scope..."></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Assigned Team Lead</label>
                        <select id="cp_team_lead_id" class="form-select">
                            <?php foreach ($developers as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $d['role_name'] === 'Team Lead' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['role_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="cp_priority" class="form-select">
                            <option value="Urgent">Urgent</option>
                            <option value="High" selected>High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Link Service Package (Optional)</label>
                        <select id="cp_service_id" class="form-select">
                            <option value="0">-- No Linked Package --</option>
                            <?php foreach ($packages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['service_name']) ?> (<?= htmlspecialchars($pkg['package_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Target Completion Deadline</label>
                        <input type="date" id="cp_deadline" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createProjectModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Project</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: CREATE & ASSIGN TASK FOR THIS CLIENT -->
<div id="createTaskModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h3 class="modal-title">✓ Create & Assign Task for <?= htmlspecialchars($client['company_name']) ?></h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('createTaskModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateTask();">
            <div class="modal-body">
                <input type="hidden" id="ct_client_id" value="<?= $clientId ?>">

                <div class="form-group">
                    <label class="form-label">Project *</label>
                    <select id="ct_project_id" class="form-select" required>
                        <?php if (empty($projects)): ?>
                            <option value="">-- No Projects Found. Please Create a Project First --</option>
                        <?php else: ?>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" id="ct_title" class="form-control" required placeholder="e.g. Implement Frontend Contact Form & Validation">
                </div>

                <div class="form-group">
                    <label class="form-label">Task Requirements & Deliverables Description</label>
                    <textarea id="ct_description" class="form-control" rows="3" placeholder="Provide clear requirements and testing instructions for the developer..."></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Assign To Developer *</label>
                        <select id="ct_assigned_to" class="form-select" required>
                            <?php foreach ($developers as $d): ?>
                                <option value="<?= $d['id'] ?>">
                                    <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['department']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="ct_priority" class="form-select">
                            <option value="Urgent">Urgent</option>
                            <option value="High" selected>High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
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
                    <input type="number" id="ct_sla_hours" class="form-control" min="1" max="1000" value="24">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createTaskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create & Assign Task</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: EDIT CLIENT PROFILE -->
<div id="editClientModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Client Profile</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('editClientModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitUpdateClient();">
            <div class="modal-body">
                <input type="hidden" id="ec_client_id" value="<?= $clientId ?>">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Company Name *</label>
                        <input type="text" id="ec_company_name" class="form-control" required value="<?= htmlspecialchars($client['company_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Person *</label>
                        <input type="text" id="ec_contact_person" class="form-control" required value="<?= htmlspecialchars($client['contact_person']) ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" id="ec_email" class="form-control" required value="<?= htmlspecialchars($client['email']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" id="ec_phone" class="form-control" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tax ID / GSTIN</label>
                        <input type="text" id="ec_tax_id" class="form-control" value="<?= htmlspecialchars($client['tax_id'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select id="ec_status" class="form-select">
                            <option value="Approved / Active" <?= $client['status'] === 'Approved / Active' ? 'selected' : '' ?>>Approved / Active</option>
                            <option value="Pending Approval" <?= $client['status'] === 'Pending Approval' ? 'selected' : '' ?>>Pending Approval</option>
                            <option value="Suspended" <?= $client['status'] === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Client Notes & Remarks</label>
                    <textarea id="ec_notes" class="form-control" rows="3"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editClientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 4: RESET PASSWORD -->
<div id="resetPasswordModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">🔑 Reset Client Portal Password</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('resetPasswordModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitResetClientPassword();">
            <div class="modal-body">
                <input type="hidden" id="rp_client_id" value="<?= $clientId ?>">
                <p style="font-size:13px; color:#475569; margin-bottom:12px;">
                    Setting new portal password for <strong id="rp_client_name"><?= htmlspecialchars($client['company_name']) ?></strong> (<code id="rp_username">@<?= htmlspecialchars($client['client_username'] ?? 'user') ?></code>)
                </p>
                <div class="form-group">
                    <label class="form-label">New Custom Password *</label>
                    <input type="text" id="rp_new_password" class="form-control" required placeholder="Enter new custom password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentStatusFilter = '';

function filterTasksByStatus(status) {
    currentStatusFilter = status;

    // Toggle active styles on buttons
    const btns = [
        { id: 'btnFilterAll', status: '' },
        { id: 'btnFilterDone', status: 'COMPLETED' },
        { id: 'btnFilterProgress', status: 'IN PROGRESS' },
        { id: 'btnFilterReview', status: 'UNDER REVIEW' },
        { id: 'btnFilterPending', status: 'PENDING' }
    ];

    btns.forEach(b => {
        const elem = document.getElementById(b.id);
        if (elem) {
            if (b.status === status) {
                elem.className = 'btn btn-sm btn-primary';
            } else {
                elem.className = 'btn btn-sm btn-outline';
            }
        }
    });

    applyTaskFilters();
}

function applyTaskFilters() {
    const projectFilter = document.getElementById('taskProjectFilter').value;
    const searchVal = document.getElementById('taskSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.task-row');

    rows.forEach(r => {
        const rowStatus = r.getAttribute('data-status');
        const rowProj = r.getAttribute('data-project');
        const rowSearch = r.getAttribute('data-search') || '';

        let matchStatus = true;
        if (currentStatusFilter) {
            if (currentStatusFilter === 'UNDER REVIEW') {
                matchStatus = (rowStatus === 'UNDER REVIEW' || rowStatus === 'SENT FOR CLIENT APPROVAL');
            } else {
                matchStatus = (rowStatus === currentStatusFilter);
            }
        }

        let matchProj = true;
        if (projectFilter !== '0' && projectFilter !== '') {
            matchProj = (rowProj === projectFilter);
        }

        let matchSearch = true;
        if (searchVal) {
            matchSearch = rowSearch.includes(searchVal);
        }

        if (matchStatus && matchProj && matchSearch) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function openCreateProjectModal() {
    document.getElementById('createProjectModal').style.display = 'flex';
}

function submitCreateProject() {
    const payload = {
        client_id: document.getElementById('cp_client_id').value,
        project_name: document.getElementById('cp_project_name').value,
        description: document.getElementById('cp_description').value,
        team_lead_id: document.getElementById('cp_team_lead_id').value,
        priority: document.getElementById('cp_priority').value,
        service_id: document.getElementById('cp_service_id').value,
        deadline: document.getElementById('cp_deadline').value
    };

    fetch('api.php?action=create_project', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('createProjectModal');
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function openCreateTaskModal() {
    document.getElementById('createTaskModal').style.display = 'flex';
}

function submitCreateTask() {
    const payload = {
        client_id: document.getElementById('ct_client_id').value,
        project_id: document.getElementById('ct_project_id').value,
        title: document.getElementById('ct_title').value,
        description: document.getElementById('ct_description').value,
        assigned_to: document.getElementById('ct_assigned_to').value,
        priority: document.getElementById('ct_priority').value,
        start_date: document.getElementById('ct_start_date').value,
        end_date: document.getElementById('ct_end_date').value,
        sla_hours: document.getElementById('ct_sla_hours').value
    };

    if (!payload.project_id) {
        alert('Please select or create a project first.');
        return;
    }

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
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function openEditClientModal(client) {
    document.getElementById('editClientModal').style.display = 'flex';
}

function submitUpdateClient() {
    const payload = {
        client_id: document.getElementById('ec_client_id').value,
        company_name: document.getElementById('ec_company_name').value,
        contact_person: document.getElementById('ec_contact_person').value,
        email: document.getElementById('ec_email').value,
        phone: document.getElementById('ec_phone').value,
        tax_id: document.getElementById('ec_tax_id').value,
        status: document.getElementById('ec_status').value,
        notes: document.getElementById('ec_notes').value
    };

    fetch('api.php?action=update_client', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('editClientModal');
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function openResetPasswordModal(clientId, companyName, username) {
    document.getElementById('rp_client_id').value = clientId;
    document.getElementById('rp_client_name').textContent = companyName;
    document.getElementById('rp_username').textContent = '@' + username;
    document.getElementById('rp_new_password').value = '';
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function submitResetClientPassword() {
    const clientId = document.getElementById('rp_client_id').value;
    const newPassword = document.getElementById('rp_new_password').value;

    fetch('api.php?action=reset_client_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client_id: clientId, password: newPassword })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('resetPasswordModal');
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
