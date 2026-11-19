<?php
// index.php - Main Agency OS Portal Hub

require_once __DIR__ . '/auth.php';
checkAuth();

$user = getCurrentUser();
$db = getDbConnection();
ensureProjectWorkflowSchema($db);

$isSuperAdmin = isSuperAdminUser($user);

if ($isSuperAdmin) {
    $pageBreadcrumb = "";
    $pageTitle = "Dashboard";
    $pageSubtitle = "Welcome back! Here's what's happening at Hemito Digital.";
} else {
    $pageBreadcrumb = "Main Portal";
    $pageTitle = "Agency Operations Hub";
    $pageSubtitle = "Central workspace for proposals, client contracts, engineering tasks, and business performance.";
}
require_once __DIR__ . '/header.php';

if ($isSuperAdmin):
    // =========================================================================
    // SUPER ADMIN MODERN EXECUTIVE DASHBOARD DATA
    // =========================================================================
    $totalProjects = intval($db->query("SELECT COUNT(*) FROM projects")->fetchColumn());
    $inProgressProjects = intval($db->query("SELECT COUNT(*) FROM projects WHERE status IN ('In Progress', 'Review')")->fetchColumn());
    $completedProjects = intval($db->query("SELECT COUNT(*) FROM projects WHERE status = 'Completed'")->fetchColumn());
    $pendingProjects = max(0, $totalProjects - $inProgressProjects - $completedProjects);

    $totalClients = intval($db->query("SELECT COUNT(*) FROM clients")->fetchColumn());
    $activeClients = intval($db->query("SELECT COUNT(*) FROM clients WHERE COALESCE(status, 'Active') NOT IN ('Inactive', 'Archived')")->fetchColumn());

    // Percentages for donut & KPI pills
    $inProgPct = $totalProjects > 0 ? round(($inProgressProjects / $totalProjects) * 100) : 0;
    $compPct = $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100) : 0;
    $pendPct = $totalProjects > 0 ? max(0, 100 - $inProgPct - $compPct) : 0;

    // Fetch Projects with real task progress for Current Projects & All Projects tabs
    $projSql = "SELECT p.*, c.company_name, u.full_name as team_lead_name,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted = 0) as total_tasks,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'COMPLETED' AND t.is_deleted = 0) as completed_tasks
                FROM projects p
                JOIN clients c ON p.client_id = c.id
                LEFT JOIN users u ON p.team_lead_id = u.id
                ORDER BY COALESCE(p.is_current, 1) DESC, p.id DESC
                LIMIT 20";
    $allDashProjects = $db->query($projSql)->fetchAll();

    $activeDashProjects = array_values(array_filter($allDashProjects, function ($p) {
        return intval($p['is_current'] ?? 1) === 1 && ($p['status'] ?? '') !== 'Completed';
    }));
    if (empty($activeDashProjects)) {
        $activeDashProjects = array_slice($allDashProjects, 0, 5);
    } else {
        $activeDashProjects = array_slice($activeDashProjects, 0, 5);
    }
    $allDashProjectsTop = array_slice($allDashProjects, 0, 6);

    // Fetch Recent Activity (prefer meaningful non-LOGIN events, fallback to all)
    $recentActivities = [];
    try {
        $recentActivities = $db->query("SELECT a.*, u.full_name as actor_name
                                        FROM activity_logs a
                                        LEFT JOIN users u ON a.user_id = u.id
                                        WHERE a.action != 'LOGIN'
                                        ORDER BY a.id DESC
                                        LIMIT 5")->fetchAll();
        if (empty($recentActivities)) {
            $recentActivities = $db->query("SELECT a.*, u.full_name as actor_name
                                            FROM activity_logs a
                                            LEFT JOIN users u ON a.user_id = u.id
                                            ORDER BY a.id DESC
                                            LIMIT 5")->fetchAll();
        }
    } catch (Exception $e) {}

    // Helper for relative date label (Today, 3:42 PM / Yesterday, ... / dd MMM)
    function formatSaActivityTime($tsStr) {
        if (empty($tsStr)) return '';
        $ts = strtotime($tsStr);
        if (!$ts) return $tsStr;
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $d = date('Y-m-d', $ts);
        if ($d === $today) return 'Today, ' . date('g:i A', $ts);
        if ($d === $yesterday) return 'Yesterday, ' . date('g:i A', $ts);
        return date('M d, g:i A', $ts);
    }

    // Donut SVG geometry (radius 58, circumference = 2 * pi * 58 = 364.42)
    $circ = 364.42;
    $inProgLen = $totalProjects > 0 ? ($inProgressProjects / $totalProjects) * $circ : 0;
    $compLen   = $totalProjects > 0 ? ($completedProjects / $totalProjects) * $circ : 0;
    $pendLen   = $totalProjects > 0 ? ($pendingProjects / $totalProjects) * $circ : 0;
    $compOffset = -$inProgLen;
    $pendOffset = -($inProgLen + $compLen);
?>

<style>
.sa-dash-wrap {
    font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}
.sa-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}
@media (max-width: 1100px) {
    .sa-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 600px) {
    .sa-kpi-grid { grid-template-columns: 1fr; }
}
.sa-kpi-card {
    position: relative;
    overflow: hidden;
    border-radius: 18px;
    padding: 20px 22px;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 122px;
    border: 1px solid rgba(255,255,255,0.85);
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.045);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.sa-kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
}
.sa-kpi-blue   { background: linear-gradient(135deg, #f2f8ff 0%, #e1efff 100%); }
.sa-kpi-purple { background: linear-gradient(135deg, #f7f4ff 0%, #ece4ff 100%); }
.sa-kpi-green  { background: linear-gradient(135deg, #effdf5 0%, #d8f8e7 100%); }
.sa-kpi-peach  { background: linear-gradient(135deg, #fff8f0 0%, #ffebd6 100%); }

.sa-kpi-top {
    display: flex;
    align-items: center;
    gap: 11px;
    position: relative;
    z-index: 2;
}
.sa-kpi-icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.sa-kpi-icon svg { width: 18px; height: 18px; }
.sa-kpi-label {
    font-size: 14px;
    font-weight: 700;
    color: #334155;
}
.sa-kpi-bottom {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
    position: relative;
    z-index: 2;
}
.sa-kpi-num {
    font-size: 32px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
    letter-spacing: -0.8px;
}
.sa-kpi-pill {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(16, 185, 129, 0.14);
    color: #047857;
}
.sa-kpi-wave {
    position: absolute;
    right: -10px;
    bottom: -14px;
    width: 120px;
    height: 75px;
    opacity: 0.45;
    pointer-events: none;
    z-index: 1;
}

.sa-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(300px, 1fr);
    gap: 22px;
    align-items: start;
}
@media (max-width: 1024px) {
    .sa-main-grid { grid-template-columns: 1fr; }
}
.sa-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 22px 24px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 6px 24px rgba(15, 23, 42, 0.04);
    margin-bottom: 22px;
}
.sa-card:last-child { margin-bottom: 0; }
.sa-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}
.sa-card-title {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.3px;
}
.sa-seg-tabs {
    display: inline-flex;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 999px;
    gap: 4px;
}
.sa-seg-btn {
    border: none;
    background: transparent;
    color: #475569;
    font-size: 12.5px;
    font-weight: 700;
    padding: 6px 16px;
    border-radius: 999px;
    cursor: pointer;
    transition: all 0.18s ease;
}
.sa-seg-btn.active {
    background: linear-gradient(135deg, #6366f1, #7c3aed);
    color: #ffffff;
    box-shadow: 0 3px 10px rgba(99, 102, 241, 0.35);
}
.sa-view-all {
    font-size: 13px;
    font-weight: 700;
    color: #6366f1;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: color 0.15s ease;
}
.sa-view-all:hover { color: #4338ca; }

.sa-proj-table {
    width: 100%;
    border-collapse: collapse;
}
.sa-proj-table thead th {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-align: left;
    padding: 10px 12px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}
.sa-proj-table thead th:first-child { border-radius: 10px 0 0 10px; }
.sa-proj-table thead th:last-child  { border-radius: 0 10px 10px 0; text-align: right; }
.sa-proj-table tbody td {
    padding: 14px 12px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.sa-proj-table tbody tr:last-child td { border-bottom: none; }
.sa-proj-table tbody tr:hover td { background: #fafbff; }

.sa-proj-icon {
    width: 36px;
    height: 36px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.sa-proj-icon svg { width: 17px; height: 17px; }
.sa-client-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11.5px;
    font-weight: 800;
    flex-shrink: 0;
}
.sa-prog-track {
    width: 100%;
    min-width: 90px;
    max-width: 130px;
    height: 6px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 5px;
}
.sa-prog-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
}
.sa-status-pill {
    display: inline-block;
    font-size: 11.5px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 8px;
    white-space: nowrap;
}
.sa-status-progress  { background: #e0f2fe; color: #0369a1; }
.sa-status-completed { background: #dcfce7; color: #15803d; }
.sa-status-pending   { background: #fef3c7; color: #b45309; }

.sa-qa-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}
@media (max-width: 768px) {
    .sa-qa-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.sa-qa-tile {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    padding: 20px 12px;
    text-align: center;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 11px;
    transition: all 0.2s ease;
}
.sa-qa-tile:hover {
    background: #ffffff;
    border-color: #c7d2fe;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.1);
}
.sa-qa-icon-circle {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sa-qa-label {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
}

.sa-donut-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}
.sa-donut-svg-box {
    position: relative;
    width: 156px;
    height: 156px;
    flex-shrink: 0;
    margin: 0 auto;
}
.sa-donut-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}
.sa-donut-legend {
    flex: 1;
    min-width: 145px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.sa-legend-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13.5px;
}
.sa-legend-left {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #334155;
    font-weight: 600;
}
.sa-legend-dot {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    flex-shrink: 0;
}

.sa-act-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.sa-act-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.sa-act-icon {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.sa-act-icon svg { width: 15px; height: 15px; }
.sa-act-text {
    font-size: 13px;
    color: #1e293b;
    line-height: 1.45;
    font-weight: 500;
}
.sa-act-time {
    font-size: 11.5px;
    color: #94a3b8;
    margin-top: 2px;
}

.sa-util-strip {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
}
.sa-util-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 5px 12px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sa-util-chip:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}
</style>

<div class="sa-dash-wrap">

    <!-- 1. TOP 4 PASTEL KPI CARDS -->
    <div class="sa-kpi-grid">
        <a href="projects.php?view=projects" class="sa-kpi-card sa-kpi-blue">
            <div class="sa-kpi-top">
                <div class="sa-kpi-icon" style="background:rgba(59,130,246,0.14); color:#2563eb;">
                    <i data-feather="folder"></i>
                </div>
                <span class="sa-kpi-label">Total Projects</span>
            </div>
            <div class="sa-kpi-bottom">
                <span class="sa-kpi-num"><?= $totalProjects ?></span>
                <span class="sa-kpi-pill">&uarr; <?= count($activeDashProjects) ?> Active</span>
            </div>
            <svg class="sa-kpi-wave" viewBox="0 0 120 75" fill="none">
                <path d="M0 55 C30 20, 75 70, 120 15 L120 75 L0 75 Z" fill="#93c5fd"/>
            </svg>
        </a>

        <a href="projects.php?view=projects" class="sa-kpi-card sa-kpi-purple">
            <div class="sa-kpi-top">
                <div class="sa-kpi-icon" style="background:rgba(124,58,237,0.14); color:#7c3aed;">
                    <i data-feather="clock"></i>
                </div>
                <span class="sa-kpi-label">In Progress</span>
            </div>
            <div class="sa-kpi-bottom">
                <span class="sa-kpi-num"><?= $inProgressProjects ?></span>
                <span class="sa-kpi-pill"><?= $inProgPct ?>%</span>
            </div>
            <svg class="sa-kpi-wave" viewBox="0 0 120 75" fill="none">
                <path d="M0 60 C40 25, 75 65, 120 20 L120 75 L0 75 Z" fill="#c4b5fd"/>
            </svg>
        </a>

        <a href="projects.php?view=projects" class="sa-kpi-card sa-kpi-green">
            <div class="sa-kpi-top">
                <div class="sa-kpi-icon" style="background:rgba(16,185,129,0.15); color:#059669;">
                    <i data-feather="check-circle"></i>
                </div>
                <span class="sa-kpi-label">Completed</span>
            </div>
            <div class="sa-kpi-bottom">
                <span class="sa-kpi-num"><?= $completedProjects ?></span>
                <span class="sa-kpi-pill">&uarr; <?= $compPct ?>%</span>
            </div>
            <svg class="sa-kpi-wave" viewBox="0 0 120 75" fill="none">
                <path d="M0 58 C35 28, 70 62, 120 18 L120 75 L0 75 Z" fill="#86efac"/>
            </svg>
        </a>

        <a href="clients.php" class="sa-kpi-card sa-kpi-peach">
            <div class="sa-kpi-top">
                <div class="sa-kpi-icon" style="background:rgba(249,115,22,0.14); color:#ea580c;">
                    <i data-feather="users"></i>
                </div>
                <span class="sa-kpi-label">Clients</span>
            </div>
            <div class="sa-kpi-bottom">
                <span class="sa-kpi-num"><?= $totalClients ?></span>
                <span class="sa-kpi-pill"><?= $activeClients ?> Active</span>
            </div>
            <svg class="sa-kpi-wave" viewBox="0 0 120 75" fill="none">
                <path d="M0 55 C35 30, 80 65, 120 20 L120 75 L0 75 Z" fill="#fdba74"/>
            </svg>
        </a>
    </div>

    <!-- 2. MAIN TWO-COLUMN EXECUTIVE LAYOUT -->
    <div class="sa-main-grid">

        <!-- LEFT COLUMN: Current Projects + Quick Actions -->
        <div>
            <!-- Current Projects Table Card -->
            <div class="sa-card">
                <div class="sa-card-head">
                    <h3 class="sa-card-title">Current Projects</h3>
                    <div class="sa-seg-tabs">
                        <button type="button" id="saProjTabActive" class="sa-seg-btn active" onclick="switchSaProjectTab('active')">Active Projects</button>
                        <button type="button" id="saProjTabAll" class="sa-seg-btn" onclick="switchSaProjectTab('all')">All Projects</button>
                    </div>
                    <a href="projects.php?view=projects" class="sa-view-all">View All &rarr;</a>
                </div>

                <?php
                $renderSaProjectRows = function ($list) {
                    if (empty($list)) {
                        echo '<tr><td colspan="4" style="text-align:center; padding:32px; color:#94a3b8; font-size:13.5px;">No projects yet. Click <strong>Add New Project</strong> below to get started.</td></tr>';
                        return;
                    }
                    $iconStyles = [
                        ['bg' => '#eef2ff', 'fg' => '#4f46e5', 'ico' => 'layers'],
                        ['bg' => '#ecfeff', 'fg' => '#0891b2', 'ico' => 'bar-chart-2'],
                        ['bg' => '#f5f3ff', 'fg' => '#7c3aed', 'ico' => 'monitor'],
                        ['bg' => '#fdf2f8', 'fg' => '#db2777', 'ico' => 'share-2'],
                        ['bg' => '#f0fdf4', 'fg' => '#16a34a', 'ico' => 'briefcase'],
                    ];
                    $clientColors = [
                        ['bg' => '#dbeafe', 'fg' => '#1d4ed8'],
                        ['bg' => '#ede9fe', 'fg' => '#6d28d9'],
                        ['bg' => '#dcfce7', 'fg' => '#15803d'],
                        ['bg' => '#ffedd5', 'fg' => '#c2410c'],
                        ['bg' => '#fce7f3', 'fg' => '#be185d'],
                    ];
                    foreach ($list as $idx => $p) {
                        $tTotal = intval($p['total_tasks'] ?? 0);
                        $tDone = intval($p['completed_tasks'] ?? 0);
                        $status = $p['status'] ?? 'In Progress';
                        if ($status === 'Completed') {
                            $pct = 100;
                        } elseif ($tTotal > 0) {
                            $pct = round(($tDone / $tTotal) * 100);
                        } else {
                            $pct = ($status === 'In Progress') ? 25 : 0;
                        }
                        $stClass = ($status === 'Completed') ? 'sa-status-completed' : (in_array($status, ['Planning', 'On Hold', 'Review'], true) ? 'sa-status-pending' : 'sa-status-progress');
                        $ic = $iconStyles[$idx % count($iconStyles)];
                        $cc = $clientColors[$idx % count($clientColors)];
                        $cInit = strtoupper(substr(trim($p['company_name'] ?? 'C'), 0, 1));
                        echo '<tr>';
                        echo '<td>
                                <a href="project_details.php?id=' . intval($p['id']) . '" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
                                    <div class="sa-proj-icon" style="background:' . $ic['bg'] . '; color:' . $ic['fg'] . ';"><i data-feather="' . $ic['ico'] . '"></i></div>
                                    <div style="min-width:0;">
                                        <div style="font-weight:700; color:#0f172a; font-size:13.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:230px;">' . htmlspecialchars($p['project_name']) . '</div>
                                        <div style="font-size:11.5px; color:#64748b; margin-top:2px;">' . htmlspecialchars($p['department'] ?: 'General') . '</div>
                                    </div>
                                </a>
                              </td>';
                        echo '<td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="sa-client-dot" style="background:' . $cc['bg'] . '; color:' . $cc['fg'] . ';">' . htmlspecialchars($cInit) . '</span>
                                    <span style="font-size:13px; font-weight:600; color:#334155;">' . htmlspecialchars($p['company_name']) . '</span>
                                </div>
                              </td>';
                        echo '<td>
                                <div style="font-size:12.5px; font-weight:800; color:#0f172a;">' . $pct . '%</div>
                                <div class="sa-prog-track"><div class="sa-prog-fill" style="width:' . $pct . '%;"></div></div>
                              </td>';
                        echo '<td style="text-align:right;">
                                <span class="sa-status-pill ' . $stClass . '">' . htmlspecialchars($status) . '</span>
                              </td>';
                        echo '</tr>';
                    }
                };
                ?>

                <div class="table-responsive">
                    <table class="sa-proj-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="saProjBodyActive">
                            <?php $renderSaProjectRows($activeDashProjects); ?>
                        </tbody>
                        <tbody id="saProjBodyAll" style="display:none;">
                            <?php $renderSaProjectRows($allDashProjectsTop); ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="sa-card">
                <div class="sa-card-head" style="margin-bottom:16px;">
                    <h3 class="sa-card-title">Quick Actions</h3>
                </div>

                <div class="sa-qa-grid">
                    <a href="projects.php?view=projects&open=new_project" class="sa-qa-tile">
                        <div class="sa-qa-icon-circle" style="background:linear-gradient(135deg, #6366f1, #7c3aed); color:#ffffff; box-shadow:0 6px 14px rgba(99,102,241,0.3);">
                            <i data-feather="plus" style="width:20px; height:20px;"></i>
                        </div>
                        <span class="sa-qa-label">Add New Project</span>
                    </a>

                    <a href="projects.php?view=projects&open=import_projects" class="sa-qa-tile">
                        <div class="sa-qa-icon-circle" style="background:#eef2ff; color:#4f46e5;">
                            <i data-feather="upload" style="width:20px; height:20px;"></i>
                        </div>
                        <span class="sa-qa-label">Import Projects</span>
                    </a>

                    <a href="clients.php?open=add_client" class="sa-qa-tile">
                        <div class="sa-qa-icon-circle" style="background:#eff6ff; color:#2563eb;">
                            <i data-feather="user-plus" style="width:20px; height:20px;"></i>
                        </div>
                        <span class="sa-qa-label">Create Client</span>
                    </a>

                    <a href="projects.php?open=new_task" class="sa-qa-tile">
                        <div class="sa-qa-icon-circle" style="background:#f5f3ff; color:#7c3aed;">
                            <i data-feather="check-square" style="width:20px; height:20px;"></i>
                        </div>
                        <span class="sa-qa-label">Assign Task</span>
                    </a>
                </div>

                <!-- Clean Secondary Shortcuts + Live Presence Summary -->
                <div class="sa-util-strip">
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="operations.php?tab=shoots" class="sa-util-chip">🎬 Shoot Schedules</a>
                        <a href="operations.php?tab=renewals" class="sa-util-chip">🌐 Domain &amp; Renewals</a>
                        <a href="reports.php" class="sa-util-chip">📊 Reports &amp; BI</a>
                        <a href="hr.php" class="sa-util-chip">💼 HR &amp; Attendance</a>
                    </div>
                    <button type="button" onclick="openTeamPresenceModal()" class="sa-util-chip" id="saLivePresenceBtn" style="background:#f0fdf4; border-color:#bbf7d0; color:#15803d;">
                        <span class="presence-pulse-dot" style="width:7px; height:7px;"></span>
                        <span id="saLivePresenceText">Live Office Status</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Project Overview Donut + Recent Activity -->
        <div>
            <!-- Project Overview Donut Card -->
            <div class="sa-card">
                <div class="sa-card-head" style="margin-bottom:16px;">
                    <h3 class="sa-card-title">Project Overview</h3>
                </div>

                <div class="sa-donut-wrap">
                    <div class="sa-donut-svg-box">
                        <svg width="156" height="156" viewBox="0 0 156 156" style="transform:rotate(-90deg);">
                            <circle cx="78" cy="78" r="58" fill="none" stroke="#f1f5f9" stroke-width="20"/>
                            <?php if ($totalProjects > 0): ?>
                                <?php if ($inProgLen > 0): ?>
                                <circle cx="78" cy="78" r="58" fill="none" stroke="#6366f1" stroke-width="20"
                                        stroke-dasharray="<?= number_format($inProgLen, 2, '.', '') ?> <?= number_format($circ, 2, '.', '') ?>"
                                        stroke-dashoffset="0"/>
                                <?php endif; ?>
                                <?php if ($compLen > 0): ?>
                                <circle cx="78" cy="78" r="58" fill="none" stroke="#34d399" stroke-width="20"
                                        stroke-dasharray="<?= number_format($compLen, 2, '.', '') ?> <?= number_format($circ, 2, '.', '') ?>"
                                        stroke-dashoffset="<?= number_format($compOffset, 2, '.', '') ?>"/>
                                <?php endif; ?>
                                <?php if ($pendLen > 0): ?>
                                <circle cx="78" cy="78" r="58" fill="none" stroke="#cbd5e1" stroke-width="20"
                                        stroke-dasharray="<?= number_format($pendLen, 2, '.', '') ?> <?= number_format($circ, 2, '.', '') ?>"
                                        stroke-dashoffset="<?= number_format($pendOffset, 2, '.', '') ?>"/>
                                <?php endif; ?>
                            <?php endif; ?>
                        </svg>
                        <div class="sa-donut-center">
                            <div style="font-size:30px; font-weight:800; color:#0f172a; line-height:1;"><?= $totalProjects ?></div>
                            <div style="font-size:11.5px; font-weight:600; color:#64748b; margin-top:4px;">Total Projects</div>
                        </div>
                    </div>

                    <div class="sa-donut-legend">
                        <div class="sa-legend-row">
                            <span class="sa-legend-left"><span class="sa-legend-dot" style="background:#6366f1;"></span>In Progress</span>
                            <span><strong style="color:#0f172a;"><?= $inProgressProjects ?></strong> <span style="color:#64748b; font-size:12px;">(<?= $inProgPct ?>%)</span></span>
                        </div>
                        <div class="sa-legend-row">
                            <span class="sa-legend-left"><span class="sa-legend-dot" style="background:#34d399;"></span>Completed</span>
                            <span><strong style="color:#0f172a;"><?= $completedProjects ?></strong> <span style="color:#64748b; font-size:12px;">(<?= $compPct ?>%)</span></span>
                        </div>
                        <div class="sa-legend-row">
                            <span class="sa-legend-left"><span class="sa-legend-dot" style="background:#cbd5e1;"></span>Pending</span>
                            <span><strong style="color:#0f172a;"><?= $pendingProjects ?></strong> <span style="color:#64748b; font-size:12px;">(<?= $pendPct ?>%)</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Card -->
            <div class="sa-card">
                <div class="sa-card-head" style="margin-bottom:16px;">
                    <h3 class="sa-card-title">Recent Activity</h3>
                    <a href="reports.php" class="sa-view-all">View All &rarr;</a>
                </div>

                <div class="sa-act-list">
                    <?php if (empty($recentActivities)): ?>
                        <div style="text-align:center; padding:24px 10px; color:#94a3b8; font-size:13px;">No recent activity recorded yet.</div>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $act): ?>
                            <?php
                                $actKey = strtoupper($act['action'] ?? '');
                                $actBg = '#f5f3ff'; $actFg = '#7c3aed'; $actIco = 'edit-3';
                                if (strpos($actKey, 'APPROV') !== false || strpos($actKey, 'COMPLET') !== false) {
                                    $actBg = '#dcfce7'; $actFg = '#16a34a'; $actIco = 'check-circle';
                                } elseif (strpos($actKey, 'CLIENT') !== false || strpos($actKey, 'USER') !== false || strpos($actKey, 'MEMBER') !== false || strpos($actKey, 'HANDLER') !== false) {
                                    $actBg = '#e0f2fe'; $actFg = '#0284c7'; $actIco = 'users';
                                } elseif (strpos($actKey, 'HANDOVER') !== false) {
                                    $actBg = '#fef3c7'; $actFg = '#d97706'; $actIco = 'send';
                                }
                                $detailText = trim($act['details'] ?? '');
                                if ($detailText === '') {
                                    $detailText = ($act['actor_name'] ?: 'System') . ' performed ' . strtolower(str_replace('_', ' ', $actKey));
                                }
                            ?>
                            <div class="sa-act-item">
                                <div class="sa-act-icon" style="background:<?= $actBg ?>; color:<?= $actFg ?>;">
                                    <i data-feather="<?= $actIco ?>"></i>
                                </div>
                                <div style="min-width:0; flex:1;">
                                    <div class="sa-act-text"><?= htmlspecialchars($detailText) ?></div>
                                    <div class="sa-act-time"><?= htmlspecialchars(formatSaActivityTime($act['created_at'] ?? '')) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function switchSaProjectTab(tab) {
    const btnActive = document.getElementById('saProjTabActive');
    const btnAll = document.getElementById('saProjTabAll');
    const bodyActive = document.getElementById('saProjBodyActive');
    const bodyAll = document.getElementById('saProjBodyAll');
    if (tab === 'all') {
        btnActive.classList.remove('active');
        btnAll.classList.add('active');
        bodyActive.style.display = 'none';
        bodyAll.style.display = '';
    } else {
        btnActive.classList.add('active');
        btnAll.classList.remove('active');
        bodyActive.style.display = '';
        bodyAll.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    fetch('api.php?action=get_team_presence')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.summary) {
                const el = document.getElementById('saLivePresenceText');
                if (el) el.textContent = `${data.summary.in_office_count || 0} At Desk • ${data.summary.total_staff || 0} Staff`;
            }
        })
        .catch(() => {});
});
</script>

<?php else: ?>

<?php
// =========================================================================
// STANDARD ROLE-BASED DASHBOARD (EMPLOYEES, LEADS, SALES, HR)
// =========================================================================
$clientCount = $db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$templateCount = $db->query("SELECT COUNT(*) FROM document_templates WHERE is_active=1")->fetchColumn();
$documentCount = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$projectCount = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();

$recentDocs = $db->query("SELECT d.*, c.company_name FROM documents d JOIN clients c ON d.client_id = c.id ORDER BY d.id DESC LIMIT 5")->fetchAll();

// Fetch projects the current employee is working on (Working Member or Project Handler)
$myWorkingProjectIds = getUserWorkingProjectIds($db, $user['id']);
$myHandledProjectIds = getUserHandledProjectIds($db, $user['id']);
$myAllAssignedProjIds = array_values(array_unique(array_merge($myWorkingProjectIds, $myHandledProjectIds)));
$myWorkingProjects = [];
if (!empty($myAllAssignedProjIds)) {
    try {
        $phMyP = implode(',', array_fill(0, count($myAllAssignedProjIds), '?'));
        $stMyP = $db->prepare("SELECT p.*, c.company_name, u.full_name as team_lead_name
                               FROM projects p
                               JOIN clients c ON p.client_id = c.id
                               LEFT JOIN users u ON p.team_lead_id = u.id
                               WHERE p.id IN ($phMyP)
                               ORDER BY COALESCE(p.is_current, 1) DESC, p.id DESC");
        $stMyP->execute($myAllAssignedProjIds);
        $myWorkingProjects = $stMyP->fetchAll();
    } catch (Exception $e) {}
}

// Fetch unread project assignment notifications for this employee's dashboard
$myUnreadProjectNotifs = [];
try {
    $stNotifDash = $db->prepare("SELECT * FROM user_notifications WHERE user_id = ? AND is_read = 0 AND type IN ('project_member_added', 'project_handler_assigned', 'task_assigned', 'project_handover') ORDER BY id DESC LIMIT 5");
    $stNotifDash->execute([$user['id']]);
    $myUnreadProjectNotifs = $stNotifDash->fetchAll();
} catch (Exception $e) {}
?>

<div style="margin-bottom: 30px;">
    <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color: white; padding: 25px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-size: 22px; margin-bottom: 6px;">Welcome back, <?= htmlspecialchars($user['full_name']) ?></h2>
            <p style="color: #94a3b8; font-size: 14px; margin: 0;">Role: <strong><?= htmlspecialchars($user['role']) ?></strong> | Department: <strong><?= htmlspecialchars($user['department']) ?></strong></p>
        </div>
        <div>
            <?php if (hasModuleAccess('template-generator')): ?>
                <a href="template_generator.php" class="btn btn-primary">
                    <i data-feather="plus"></i> Open Template Generator
                </a>
            <?php elseif (hasModuleAccess('client-management')): ?>
                <a href="clients.php" class="btn btn-primary">
                    <i data-feather="users"></i> View Client Directory
                </a>
            <?php elseif (hasModuleAccess('task-management')): ?>
                <a href="projects.php" class="btn btn-primary">
                    <i data-feather="check-square"></i> Open Task Hub
                </a>
            <?php elseif (hasModuleAccess('reports')): ?>
                <a href="reports.php" class="btn btn-primary">
                    <i data-feather="bar-chart-2"></i> View Management Reports
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($myUnreadProjectNotifs)): ?>
<!-- Dashboard Assignment Notifications Banner -->
<div class="card" style="margin-bottom: 24px; border: 1px solid #bfdbfe; background: #eff6ff;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
        <h3 style="margin:0; font-size:15px; font-weight:800; color:#1e40af; display:flex; align-items:center; gap:8px;">
            <span>🔔 New Project &amp; Work Notifications</span>
            <span class="badge" style="background:#2563eb; color:#ffffff; font-size:11px;"><?= count($myUnreadProjectNotifs) ?> Unread</span>
        </h3>
        <button type="button" class="btn btn-outline btn-sm" onclick="markAllNotificationsAsRead(event); setTimeout(() => location.reload(), 350);" style="background:#ffffff; font-size:11.5px;">Mark All Read</button>
    </div>
    <div style="display:flex; flex-direction:column; gap:8px;">
        <?php foreach ($myUnreadProjectNotifs as $pn): ?>
            <?php $pnUrl = !empty($pn['link_url']) ? $pn['link_url'] : 'projects.php'; ?>
            <div onclick="onNotificationItemClick(<?= intval($pn['id']) ?>, '<?= htmlspecialchars($pnUrl, ENT_QUOTES) ?>')" style="background:#ffffff; border:1px solid #dbeafe; border-radius:8px; padding:10px 14px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <div style="font-weight:700; font-size:13px; color:#0f172a;"><?= htmlspecialchars($pn['title']) ?></div>
                    <div style="font-size:12px; color:#475569; margin-top:2px;"><?= htmlspecialchars($pn['message']) ?></div>
                </div>
                <span class="btn btn-primary btn-sm" style="font-size:11.5px; white-space:nowrap;">Open &rarr;</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($myWorkingProjects)): ?>
<!-- Working Employee's Active Projects Card -->
<div class="card" style="margin-bottom: 24px; border: 1px solid #e2e8f0;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 class="card-title" style="margin:0; display:flex; align-items:center; gap:8px;">
                <span>👥 Projects You Are Working On</span>
                <span class="badge badge-primary" style="font-size:11px;"><?= count($myWorkingProjects) ?></span>
            </h3>
            <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Active projects where you have been assigned by the Team Lead or Project Handler.</p>
        </div>
        <?php if (hasModuleAccess('task-management')): ?>
            <a href="projects.php?view=projects" class="btn btn-outline btn-sm">View All Projects &rarr;</a>
        <?php endif; ?>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:14px;">
        <?php foreach ($myWorkingProjects as $mp): ?>
            <?php
                $isMpHandler = in_array(intval($mp['id']), $myHandledProjectIds, true);
                $isMpWorker = in_array(intval($mp['id']), $myWorkingProjectIds, true);
            ?>
            <div style="border:1px solid #e2e8f0; border-radius:10px; padding:15px; background:#f8fafc; display:flex; flex-direction:column; justify-content:space-between; gap:10px;">
                <div>
                    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:6px;">
                        <?php if ($isMpWorker): ?>
                            <span class="badge" style="background:#dbeafe; color:#1e40af; font-size:10.5px;">👤 Working Member</span>
                        <?php endif; ?>
                        <?php if ($isMpHandler): ?>
                            <span class="badge" style="background:#ccfbf1; color:#0f766e; font-size:10.5px;">🛡️ Project Handler</span>
                        <?php endif; ?>
                        <span class="badge" style="background:#ffffff; color:#475569; border:1px solid #e2e8f0; font-size:10.5px;"><?= htmlspecialchars($mp['status'] ?? 'In Progress') ?></span>
                    </div>
                    <h4 style="margin:0 0 4px 0; font-size:15px; font-weight:800; color:#0f172a;"><?= htmlspecialchars($mp['project_name']) ?></h4>
                    <div style="font-size:12px; color:#64748b;">
                        Client: <strong style="color:#334155;"><?= htmlspecialchars($mp['company_name']) ?></strong>
                        <?php if (!empty($mp['department'])): ?>
                            &bull; Dept: <strong><?= htmlspecialchars($mp['department']) ?></strong>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($mp['deadline'])): ?>
                        <div style="font-size:11.5px; color:#64748b; margin-top:4px;">📅 Deadline: <strong><?= htmlspecialchars($mp['deadline']) ?></strong></div>
                    <?php endif; ?>
                </div>
                <div style="padding-top:8px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end;">
                    <a href="project_details.php?id=<?= intval($mp['id']) ?>" class="btn btn-primary btn-sm" style="font-size:12px;">Open Project Details &rarr;</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Metrics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <?php if (hasModuleAccess('client-management') || hasModuleAccess('sales')): ?>
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Active Clients</p>
                <h3 style="font-size:24px; font-weight:700; margin-top:4px;"><?= $clientCount ?></h3>
            </div>
            <div style="background:#dbeafe; color:#2563eb; padding:12px; border-radius:8px;">
                <i data-feather="users"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (hasModuleAccess('template-generator')): ?>
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Document Templates</p>
                <h3 style="font-size:24px; font-weight:700; margin-top:4px;"><?= $templateCount ?></h3>
            </div>
            <div style="background:#fef3c7; color:#d97706; padding:12px; border-radius:8px;">
                <i data-feather="file-text"></i>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Generated Documents</p>
                <h3 style="font-size:24px; font-weight:700; margin-top:4px;"><?= $documentCount ?></h3>
            </div>
            <div style="background:#dcfce7; color:#16a34a; padding:12px; border-radius:8px;">
                <i data-feather="check-circle"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (hasModuleAccess('task-management')): ?>
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <p style="font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Work Handover Projects</p>
                <h3 style="font-size:24px; font-weight:700; margin-top:4px;"><?= $projectCount ?></h3>
            </div>
            <div style="background:#f3e8ff; color:#9333ea; padding:12px; border-radius:8px;">
                <i data-feather="cpu"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Live Office Presence Overview Card -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 style="font-size:16px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                <span class="presence-pulse-dot"></span>
                <span>Live Office & Workplace Presence</span>
            </h3>
            <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Real-time tracking of employees who are at their desks, in meetings, or on tea breaks.</p>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <button type="button" class="btn btn-outline btn-sm" onclick="openTeamPresenceModal()" style="border-radius:8px; font-size:12px; display:flex; align-items:center; gap:5px;">
                <i data-feather="users" style="width:13px; height:13px;"></i> View Full Team Status
            </button>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:12px; margin-bottom:14px;" id="dashPresenceChips">
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:10px; text-align:center; cursor:pointer;" onclick="openTeamPresenceModal(); filterTeamPresence('IN_OFFICE');">
            <div style="font-size:22px; font-weight:800; color:#15803d;" id="dashPresInOffice">--</div>
            <div style="font-size:11px; font-weight:700; color:#166534; margin-top:2px;">🟢 At Desk</div>
        </div>
        <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:10px; text-align:center; cursor:pointer;" onclick="openTeamPresenceModal(); filterTeamPresence('TEA_BREAK');">
            <div style="font-size:22px; font-weight:800; color:#b45309;" id="dashPresTea">--</div>
            <div style="font-size:11px; font-weight:700; color:#92400e; margin-top:2px;">☕ Tea Break</div>
        </div>
        <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:10px; text-align:center; cursor:pointer;" onclick="openTeamPresenceModal(); filterTeamPresence('AFK');">
            <div style="font-size:22px; font-weight:800; color:#c2410c;" id="dashPresAfk">--</div>
            <div style="font-size:11px; font-weight:700; color:#9a3412; margin-top:2px;">⏸️ AFK</div>
        </div>
        <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:10px; padding:10px; text-align:center; cursor:pointer;" onclick="openTeamPresenceModal(); filterTeamPresence('MEETING');">
            <div style="font-size:22px; font-weight:800; color:#7e22ce;" id="dashPresMeeting">--</div>
            <div style="font-size:11px; font-weight:700; color:#6b21a8; margin-top:2px;">👥 In Meeting</div>
        </div>
        <div style="background:#fdf2f8; border:1px solid #fbcfe8; border-radius:10px; padding:10px; text-align:center; cursor:pointer;" onclick="openTeamPresenceModal(); filterTeamPresence('LUNCH');">
            <div style="font-size:22px; font-weight:800; color:#be185d;" id="dashPresLunch">--</div>
            <div style="font-size:11px; font-weight:700; color:#9d174d; margin-top:2px;">🍽️ Lunch</div>
        </div>
    </div>

    <div id="dashAwayStaffNotice" style="padding:10px 14px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; font-size:12px; color:#475569; display:flex; justify-content:space-between; align-items:center;">
        <span>Loading live staff status...</span>
    </div>
</div>

<script>
function updateDashboardPresenceCard() {
    fetch('api.php?action=get_team_presence')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const s = data.summary;
            const elInOffice = document.getElementById('dashPresInOffice');
            const elTea = document.getElementById('dashPresTea');
            const elAfk = document.getElementById('dashPresAfk');
            const elMeeting = document.getElementById('dashPresMeeting');
            const elLunch = document.getElementById('dashPresLunch');
            if (elInOffice) elInOffice.textContent = s.in_office_count || 0;
            if (elTea) elTea.textContent = s.tea_break_count || 0;
            if (elAfk) elAfk.textContent = s.afk_count || 0;
            if (elMeeting) elMeeting.textContent = s.meeting_count || 0;
            if (elLunch) elLunch.textContent = s.lunch_count || 0;

            const notice = document.getElementById('dashAwayStaffNotice');
            if (notice) {
                const awayStaff = (data.staff || []).filter(st => st.is_away);
                if (awayStaff.length === 0) {
                    notice.innerHTML = `<span style="color:#10b981; font-weight:600;">✅ All ${s.total_staff} team members are currently active at their desks in office.</span>`;
                } else {
                    const names = awayStaff.map(st => `<strong>${escapeHtml(st.full_name)}</strong> (${st.status_short}${st.status_note ? ': ' + escapeHtml(st.status_note) : ''})`).join(', ');
                    notice.innerHTML = `<span style="color:#b45309;">⚠️ <strong>${awayStaff.length} member(s) currently stepped away:</strong> ${names}</span>`;
                }
            }
        })
        .catch(err => console.error("Error updating dash presence:", err));
}
document.addEventListener('DOMContentLoaded', () => {
    updateDashboardPresenceCard();
    setInterval(updateDashboardPresenceCard, 25000);
});
</script>

<!-- Accessible Modules Grid -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">System Modules & Roles</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
        <?php if (hasModuleAccess('template-generator')): ?>
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: #ffffff;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="background:#dbeafe; color:#2563eb; padding:8px; border-radius:6px;">
                    <i data-feather="file-text"></i>
                </div>
                <h4 style="margin:0; font-size:16px;">Module 3 — Template Generator</h4>
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; line-height:1.5;">
                Generate proposals, invoices, contracts, MOMs, and service agreements from client data. Export to PDF/Word & handover to Dev team.
            </p>
            <a href="template_generator.php" class="btn btn-primary btn-sm">Launch Module 3</a>
        </div>
        <?php endif; ?>

        <?php if (hasModuleAccess('client-management')): ?>
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: #ffffff;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="background:#e0f2fe; color:#0284c7; padding:8px; border-radius:6px;">
                    <i data-feather="users"></i>
                </div>
                <h4 style="margin:0; font-size:16px;">Client Management & Repository</h4>
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; line-height:1.5;">
                Manage corporate clients, contact details, tax identifiers, client portal credentials, and service package subscriptions.
            </p>
            <a href="clients.php" class="btn btn-outline btn-sm">View Client Directory</a>
        </div>
        <?php endif; ?>

        <?php if (hasModuleAccess('task-management')): ?>
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: #ffffff;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="background:#f3e8ff; color:#9333ea; padding:8px; border-radius:6px;">
                    <i data-feather="check-square"></i>
                </div>
                <h4 style="margin:0; font-size:16px;">Module 4 — Task Management</h4>
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; line-height:1.5;">
                Team Lead task assignment, developer workflows, SLA tracking, code reviews, and client revisions.
            </p>
            <a href="projects.php" class="btn btn-outline btn-sm">Phase 2 Task Hub</a>
        </div>
        <?php endif; ?>

        <?php if (hasModuleAccess('reports')): ?>
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: #ffffff;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="background:#dcfce7; color:#16a34a; padding:8px; border-radius:6px;">
                    <i data-feather="bar-chart-2"></i>
                </div>
                <h4 style="margin:0; font-size:16px;">Module 9 — Management Reports</h4>
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; line-height:1.5;">
                Executive analytics dashboard, client overview, revenue metrics, and employee productivity reports.
            </p>
            <a href="reports.php" class="btn btn-outline btn-sm">Phase 3 BI Hub</a>
        </div>
        <?php endif; ?>

        <?php if (hasModuleAccess('user-management')): ?>
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: #ffffff;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                <div style="background:#fef3c7; color:#d97706; padding:8px; border-radius:6px;">
                    <i data-feather="user-check"></i>
                </div>
                <h4 style="margin:0; font-size:16px;">User & Team Lead Management</h4>
            </div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:15px; line-height:1.5;">
                System user account management, role assignments, department allocation, and Team Lead provisioning.
            </p>
            <a href="users.php" class="btn btn-outline btn-sm">Manage System Users</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (hasModuleAccess('template-generator') || hasModuleAccess('sales') || hasModuleAccess('client-management')): ?>
<!-- Recent Generated Documents -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Generated Documents</h3>
        <a href="template_generator.php" class="btn btn-outline btn-sm">View All Documents</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Doc #</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentDocs)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No generated documents found yet. Use Template Generator to create one!</td></tr>
                <?php else: ?>
                    <?php foreach ($recentDocs as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['document_number']) ?></strong></td>
                            <td><?= htmlspecialchars($d['company_name']) ?></td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($d['document_type']) ?></span></td>
                            <td><?= htmlspecialchars($d['title']) ?></td>
                            <td><span class="badge badge-success"><?= htmlspecialchars($d['status']) ?></span></td>
                            <td><?= date('d M Y H:i', strtotime($d['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
