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
$pageTitle = !$canManageAnyTasks ? 'My Assigned Tasks Workspace' : 'Projects & Tasks Hub';
$pageSubtitle = !$canManageAnyTasks 
    ? 'Manage your assigned tasks, review project briefs, and submit completed work.' 
    : 'Manage projects grouped by client, project tasks, monthly planning, approvals, and deliverables.';
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
        margin-bottom: 18px;
    }
    .ws-switcher {
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    .ws-tab-btn {
        border: 1px solid #e2e8f0;
        background: #ffffff;
        padding: 10px 18px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 700;
        color: #334155;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        transition: all 0.2s ease;
    }
    .ws-tab-btn:hover {
        border-color: #c7d2fe;
        color: #4f46e5;
    }
    .ws-tab-btn.active {
        background: linear-gradient(135deg, #5b46f6, #7c3aed);
        border-color: transparent;
        color: #ffffff;
        box-shadow: 0 6px 18px rgba(99, 102, 241, 0.32);
    }
    .ws-tab-btn.active .ws-count-pill {
        background: rgba(255, 255, 255, 0.92);
        color: #4f46e5;
    }
    .ws-count-pill {
        background: #eef2ff;
        color: #4f46e5;
        font-size: 11px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 999px;
        min-width: 22px;
        text-align: center;
    }
    .cp-btn-purple {
        background: linear-gradient(135deg, #5b46f6, #7c3aed) !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 6px 16px rgba(99, 102, 241, 0.28) !important;
    }
    .cp-btn-purple:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 22px rgba(99, 102, 241, 0.38) !important;
    }
    .cp-filter-bar {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        padding: 12px 16px;
        margin-bottom: 20px;
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.035);
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .cp-filter-bar .form-control,
    .cp-filter-bar .form-select {
        height: 40px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        font-size: 13px;
        background-color: #ffffff;
    }
    .cp-filter-bar .form-control:focus,
    .cp-filter-bar .form-select:focus {
        border-color: #818cf8;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
    }
    .projects-portfolio-grid {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .proj-portfolio-card {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 20px;
        padding: 24px 26px;
        box-shadow: 0 6px 24px rgba(15, 23, 42, 0.045);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .proj-portfolio-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
        border-color: #ddd6fe;
    }
    .proj-card-body-split {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(290px, 1fr);
        gap: 28px;
        align-items: center;
    }
    .proj-card-right {
        border-left: 1px solid #f1f5f9;
        padding-left: 28px;
    }
    @media (max-width: 960px) {
        .proj-card-body-split {
            grid-template-columns: 1fr;
            gap: 18px;
        }
        .proj-card-right {
            border-left: none;
            border-top: 1px solid #f1f5f9;
            padding-left: 0;
            padding-top: 18px;
        }
    }
    .proj-mini-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
    }
    .proj-mini-stat-box {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 14px;
        padding: 13px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .proj-mini-stat-box.stat-done {
        background: #f0fdf4;
        border-color: #dcfce7;
    }
    .proj-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        padding-top: 16px;
        margin-top: 20px;
        border-top: 1px solid #f1f5f9;
    }
    .proj-act-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 15px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        border: 1px solid transparent;
        transition: all 0.16s ease;
    }
    .proj-act-btn:hover { transform: translateY(-1px); }
    .proj-act-primary {
        background: linear-gradient(135deg, #5b46f6, #7c3aed);
        color: #ffffff;
        box-shadow: 0 5px 14px rgba(99, 102, 241, 0.28);
    }
    .proj-act-mint {
        background: #ecfdf5;
        color: #047857;
        border-color: #bbf7d0;
    }
    .proj-act-mint:hover { background: #d1fae5; }
    .proj-act-violet {
        background: #f5f3ff;
        color: #6d28d9;
        border-color: #ddd6fe;
    }
    .proj-act-violet:hover { background: #ede9fe; }
    .proj-act-sky {
        background: #eff6ff;
        color: #0369a1;
        border-color: #bae6fd;
    }
    .proj-act-sky:hover { background: #dbeafe; }
    .proj-act-neutral {
        background: #ffffff;
        color: #334155;
        border-color: #e2e8f0;
    }
    .proj-act-neutral:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }
    .cp-comp-row {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 10px;
    }

    /* Client-Grouped Projects & Task Accordion Styling */
    .client-group-card {
        background: #ffffff;
        border: 1px solid #edf2f7;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
        margin-bottom: 22px;
        overflow: hidden;
        transition: all 0.2s ease;
    }
    .client-group-card:hover {
        box-shadow: 0 8px 26px rgba(15, 23, 42, 0.07);
        border-color: #e2e8f0;
    }
    .client-group-header {
        padding: 18px 24px;
        background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        cursor: pointer;
        user-select: none;
        border-bottom: 1px solid transparent;
        transition: background 0.18s ease;
    }
    .client-group-header:hover {
        background: #f8fafc;
    }
    .client-group-card.open .client-group-header {
        border-bottom-color: #f1f5f9;
        background: #ffffff;
    }
    .client-header-left {
        display: flex;
        align-items: center;
        gap: 16px;
        flex: 1;
        min-width: 0;
    }
    .client-avatar-badge {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #ffffff;
        font-weight: 800;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
        flex-shrink: 0;
        letter-spacing: -0.5px;
    }
    .client-header-title-box {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .client-name-title {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        line-height: 1.25;
        letter-spacing: -0.3px;
        display: flex;
        align-items: center;
        gap: 10px;
        word-break: break-word;
    }
    .client-header-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .client-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .client-chevron-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #f1f5f9;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        transition: transform 0.25s ease, background 0.2s ease;
    }
    .client-group-card.open .client-chevron-icon {
        transform: rotate(180deg);
        background: #ede9fe;
        color: #6d28d9;
    }
    .client-group-body {
        padding: 20px 24px 24px 24px;
        background: #f8fafc;
        display: none;
    }
    .client-group-card.open .client-group-body {
        display: block;
    }

    /* Nested Project Card inside Client Group */
    .client-project-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        margin-bottom: 20px;
        overflow: hidden;
        transition: all 0.2s ease;
    }
    .client-project-item:last-child {
        margin-bottom: 0;
    }
    .client-project-item:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
    }

    /* Embedded Tasks Table under Project */
    .proj-nested-tasks-box {
        border-top: 1px solid #f1f5f9;
        background: #ffffff;
        padding: 0 22px 20px 22px;
    }
    .proj-nested-tasks-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0 10px 0;
    }
    .proj-nested-tasks-title {
        font-size: 13px;
        font-weight: 800;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .proj-tasks-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        font-size: 12.5px;
    }
    .proj-tasks-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 10px 14px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
    }
    .proj-tasks-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: middle;
        background: #ffffff;
    }
    .proj-tasks-table tr:last-child td {
        border-bottom: none;
    }
    .proj-tasks-table tr:hover td {
        background: #fbfcfe;
    }
    .proj-task-row-inprogress td {
        background: #faf5ff;
    }
    .proj-task-row-inprogress:hover td {
        background: #f5f3ff;
    }

    /* Monthly Plan Modal & Dynamic Table Styling */
    .cmp-modal-body {
        padding: 20px 24px;
        background: #f8fafc;
        max-height: calc(88vh - 120px);
        overflow-y: auto;
    }
    .cmp-top-toolbar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px 18px;
        margin-bottom: 18px;
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 1px 6px rgba(15, 23, 42, 0.03);
    }
    .cmp-table-wrapper {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow-x: auto;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        margin-bottom: 18px;
    }
    .cmp-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 900px;
    }
    .cmp-table th {
        background: #f1f5f9;
        color: #1e293b;
        font-weight: 700;
        font-size: 12px;
        padding: 12px 14px;
        text-align: left;
        border-bottom: 2px solid #cbd5e1;
        white-space: nowrap;
        position: relative;
    }
    .cmp-col-header-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .cmp-col-del-btn {
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        padding: 0 4px;
        line-height: 1;
        border-radius: 4px;
    }
    .cmp-col-del-btn:hover {
        color: #ef4444;
        background: #fee2e2;
    }
    .cmp-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
        background: #ffffff;
    }
    .cmp-table tr:hover td {
        background: #fafbfc;
    }
    .cmp-cell-input {
        width: 100%;
        border: 1px solid transparent;
        background: transparent;
        padding: 7px 10px;
        border-radius: 8px;
        font-size: 13px;
        color: #1e293b;
        transition: all 0.16s ease;
    }
    .cmp-cell-input:hover {
        border-color: #cbd5e1;
        background: #ffffff;
    }
    .cmp-cell-input:focus {
        border-color: #6366f1;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        outline: none;
    }
    .cmp-status-select {
        cursor: pointer;
        font-weight: 700;
        font-size: 12px;
        border-radius: 20px;
        padding: 5px 10px;
        border: 1px solid #e2e8f0;
    }
    .cmp-status-select[data-val="Planned"] { background:#f1f5f9; color:#475569; }
    .cmp-status-select[data-val="In Progress"] { background:#eff6ff; color:#2563eb; border-color:#bfdbfe; }
    .cmp-status-select[data-val="Under Review"] { background:#fef3c7; color:#d97706; border-color:#fde68a; }
    .cmp-status-select[data-val="Approved"] { background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
    .cmp-status-select[data-val="Completed"] { background:#dcfce7; color:#166534; border-color:#86efac; }
    .cmp-status-select[data-val="Delayed"] { background:#fee2e2; color:#b91c1c; border-color:#fca5a5; }

    /* ========================================================= */
    /* CLIENT MONTHLY PLAN STYLES (MEMBER-SEPARATED & TABLE)     */
    /* ========================================================= */
    .client-monthly-plan-section {
        background: linear-gradient(180deg, #fbfcfe 0%, #ffffff 100%);
        border: 1px solid #e0e7ff;
        border-radius: 18px;
        padding: 18px 20px;
        margin-bottom: 22px;
        box-shadow: 0 3px 14px rgba(99, 102, 241, 0.05);
        transition: all 0.2s ease;
    }
    .cmp-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f1f5f9;
    }
    .cmp-section-title-box {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .cmp-section-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: linear-gradient(135deg, #e0e7ff, #ede9fe);
        color: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        box-shadow: 0 2px 8px rgba(79, 70, 229, 0.12);
        flex-shrink: 0;
    }
    .cmp-section-title {
        font-size: 15.5px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        line-height: 1.3;
    }
    .cmp-section-meta {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        margin-top: 4px;
    }
    .cmp-view-switcher {
        display: inline-flex;
        background: #f1f5f9;
        padding: 3px;
        border-radius: 10px;
        gap: 3px;
    }
    .cmp-view-tab-btn {
        border: none;
        background: transparent;
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        cursor: pointer;
        transition: all 0.16s ease;
    }
    .cmp-view-tab-btn.active {
        background: #ffffff;
        color: #4338ca;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
    }
    .cmp-member-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 14px;
    }
    .cmp-member-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.035);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
    }
    .cmp-member-card:hover {
        border-color: #c7d2fe;
        box-shadow: 0 6px 18px rgba(99, 102, 241, 0.08);
    }
    .cmp-member-card.is-current-user {
        border: 2px solid #818cf8;
        background: linear-gradient(180deg, #fafafe 0%, #ffffff 100%);
        box-shadow: 0 4px 16px rgba(99, 102, 241, 0.12);
    }
    .cmp-member-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 12px;
    }
    .cmp-member-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .cmp-member-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        color: #ffffff;
        font-weight: 800;
        font-size: 13.5px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        flex-shrink: 0;
    }
    .cmp-member-name {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }
    .cmp-member-role {
        font-size: 11.5px;
        color: #64748b;
        font-weight: 600;
    }
    .cmp-member-progress {
        margin-bottom: 12px;
    }
    .cmp-progress-bar-bg {
        height: 6px;
        background: #f1f5f9;
        border-radius: 999px;
        overflow: hidden;
        margin-bottom: 3px;
    }
    .cmp-progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #6366f1, #10b981);
        border-radius: 999px;
        transition: width 0.3s ease;
    }
    .cmp-deliverables-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .cmp-deliverable-item {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 10px;
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        transition: all 0.16s ease;
    }
    .cmp-deliverable-item:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    }
    .cmp-deliverable-title {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.35;
    }
    .cmp-deliverable-meta {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
    }
    .cmp-deliverable-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
        padding-top: 6px;
        border-top: 1px dashed #e2e8f0;
    }
    .cmp-mini-status-select {
        height: 28px;
        font-size: 11.5px;
        font-weight: 700;
        border-radius: 20px;
        padding: 0 8px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        cursor: pointer;
    }
    .cmp-item-task-btn {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #4f46e5;
        font-size: 11.5px;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        cursor: pointer;
        transition: all 0.16s ease;
    }
    .cmp-item-task-btn:hover {
        background: #eef2ff;
        border-color: #a5b4fc;
    }
    .client-monthly-plan-banner-empty {
        background: #ffffff;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
        padding: 16px 20px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .cmp-banner-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .cmp-banner-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .cmp-banner-title {
        margin: 0 0 3px 0;
        font-size: 14px;
        font-weight: 800;
        color: #1e293b;
    }
    .cmp-banner-subtitle {
        margin: 0;
        font-size: 12.5px;
        color: #64748b;
    }

    /* Project Services & Hierarchy Styling */
    .proj-services-wrapper {
        background: #fbfcfe;
        border-top: 1px solid #f1f5f9;
        padding: 16px 20px;
    }
    .proj-service-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(15,23,42,0.03);
        overflow: hidden;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .proj-service-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(15,23,42,0.06);
    }
    .proj-service-header {
        background: #f8fafc;
        border-bottom: 1px solid #edf2f7;
        padding: 12px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .proj-service-title-box {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .proj-service-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #ede9fe;
        color: #6d28d9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    @media print {
        body * { visibility: hidden !important; }
        #cmpPrintArea, #cmpPrintArea * { visibility: visible !important; }
        #cmpPrintArea { position: absolute; left: 0; top: 0; width: 100%; }
        .cmp-col-del-btn, .cmp-row-del-btn, .no-print { display: none !important; }
    }
</style>

<!-- Workspace View Tabs & Lead Action Pills -->
<div class="ws-tabs-bar">
    <div class="ws-switcher">
        <button type="button" id="tabBtn_projects" class="ws-tab-btn <?= $activeView === 'projects' ? 'active' : '' ?>" onclick="switchWorkspaceView('projects')">
            <i data-feather="folder" style="width:16px; height:16px;"></i>
            <span>Projects</span>
            <span id="currentProjCountBadge" class="ws-count-pill"><?= count(array_filter($projects, fn($pr) => intval($pr['is_current'] ?? 1) === 1)) ?></span>
        </button>
        <button type="button" id="tabBtn_tasks" class="ws-tab-btn <?= $activeView === 'tasks' ? 'active' : '' ?>" onclick="switchWorkspaceView('tasks')">
            <i data-feather="check-square" style="width:16px; height:16px;"></i>
            <span>Tasks Workspace</span>
        </button>
    </div>

    <div class="action-pills-row" style="margin-bottom:0; gap:10px;">
        <?php if ($isLead): ?>
            <button class="btn-pill-primary cp-btn-purple" onclick="openCreateProjectModal()">
                <i data-feather="plus" style="width:16px; height:16px;"></i>
                <span>Add Project</span>
            </button>
            <button class="btn-pill-white" onclick="openImportProjectsModal()">
                <i data-feather="upload" style="width:15px; height:15px;"></i>
                <span>Import Projects</span>
            </button>
            <button class="btn-pill-white" onclick="openCreateTaskModal()">
                <i data-feather="plus" style="width:15px; height:15px;"></i>
                <span>Create &amp; Assign Task</span>
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
            <button class="btn-pill-primary cp-btn-purple" onclick="switchWorkspaceView('tasks'); document.getElementById('filterStatus').value='IN PROGRESS'; loadTasks();">
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
<!-- VIEW 1: PROJECTS PORTFOLIO LIST (Grouped by Client, Projects & Tasks)  -->
<!-- ====================================================================== -->
<div id="viewSection_projects" style="display:<?= $activeView === 'projects' ? 'block' : 'none' ?>;">
    <div class="cp-filter-bar">
        <div style="flex:2; min-width:220px; position:relative;">
            <i data-feather="search" style="position:absolute; left:13px; top:50%; transform:translateY(-50%); width:15px; height:15px; color:#94a3b8; pointer-events:none;"></i>
            <input type="text" id="projFilterSearch" class="form-control" style="padding-left:38px;" placeholder="Search projects, clients, or keywords..." oninput="debounceLoadProjects()">
        </div>
        <div style="flex:1; min-width:165px;">
            <select id="projFilterCurrent" class="form-select" onchange="loadCurrentProjects()">
                <option value="1" selected>Active Projects Only</option>
                <option value="">All Projects (Active &amp; Archived)</option>
                <option value="0">Archived / Completed Only</option>
            </select>
        </div>
        <div style="flex:1; min-width:155px;">
            <select id="projFilterClient" class="form-select" onchange="loadCurrentProjects()">
                <option value="0">All Clients</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1; min-width:155px;">
            <select id="projFilterDept" class="form-select" onchange="loadCurrentProjects()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="proj-act-btn proj-act-neutral" style="height:40px; padding:0 14px;" onclick="loadCurrentProjects()">
            <i data-feather="refresh-cw" style="width:14px; height:14px;"></i>
            <span>Refresh</span>
        </button>
        <?php if ($isLead): ?>
        <button type="button" class="proj-act-btn proj-act-primary" style="height:40px; padding:0 16px;" onclick="openImportProjectsModal()">
            <i data-feather="download" style="width:14px; height:14px;"></i>
            <span>Import Projects CSV</span>
        </button>
        <?php endif; ?>
    </div>

    <div id="currentProjectsGrid" class="projects-portfolio-grid">
        <div class="card" style="text-align:center; padding:44px; color:var(--text-muted); border-radius:20px;">
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
            <a href="projects.php?view=projects" class="btn btn-outline btn-sm" style="color:#2563eb; border-color:#93c5fd; background:#ffffff;">Projects List</a>
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

<!-- MODAL 0: ADD / EDIT PROJECT (Overview, About & Competitors) -->
<div id="createProjectModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:780px; width:95%; max-height:92vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title" id="cp_modal_title">Add Project (Overview & Competitors)</h3>
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
                        Show in Projects List (Active Ongoing Project)
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
                <button type="submit" id="cp_submit_btn" class="btn btn-primary">Save Project</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 0B: BULK IMPORT PROJECTS (CSV / Excel Paste) -->
<div id="importProjectsModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:860px; width:95%; max-height:92vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">📥 Import Projects List (Lead)</h3>
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
                    <select id="ct_project_id" class="form-select" required onchange="loadCreateModalParentTasks(); loadCreateModalServices();">
                        <option value="">-- Choose Project --</option>
                        <?php foreach ($projects as $p): ?>
                            <?php if (!$isLead && !in_array(intval($p['id']), $handledProjectIds, true)) continue; ?>
                            <option value="<?= $p['id'] ?>" <?= ($p['id'] == $selectedProjectId) ? 'selected' : '' ?>><?= htmlspecialchars($p['project_name']) ?> (<?= htmlspecialchars($p['company_name']) ?>)<?= $p['status'] === 'Completed' ? ' [Completed - Reopens]' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="ct_service_wrap">
                    <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Project Service *</span>
                        <small style="color:#64748b; font-weight:normal;">Select service (SEO, Website Development, Google Ads, etc.)</small>
                    </label>
                    <select id="ct_service_id" class="form-select">
                        <option value="0">-- General Project Deliverable (No Specific Service) --</option>
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

<!-- ====================================================================== -->
<!-- MODAL: CLIENT MONTHLY PLANNING (CUSTOMIZABLE TABLE STRUCTURE)           -->
<!-- ====================================================================== -->
<div id="clientMonthlyPlanModal" class="modal-overlay" style="display:none; z-index:99999;" onclick="if(event.target === this) closeModal('clientMonthlyPlanModal')">
    <div class="modal-content" style="max-width:1180px; width:96%; max-height:92vh; display:flex; flex-direction:column; padding:0; overflow:hidden; border-radius:20px;">
        <div class="modal-header" style="padding:18px 24px; background:linear-gradient(135deg, #4f46e5, #7c3aed); color:#ffffff; border-radius:20px 20px 0 0; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:42px; height:42px; border-radius:12px; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:22px;">
                    📅
                </div>
                <div>
                    <h3 class="modal-title" style="margin:0; color:#ffffff; font-size:18px; font-weight:800;" id="cmpModalClientName">Client Monthly Plan</h3>
                    <small style="color:rgba(255,255,255,0.88); font-size:12px;" id="cmpModalSubtitle">Monthly Deliverables, Platforms, Strategy &amp; Custom Table Plan</small>
                </div>
            </div>
            <button class="btn btn-outline btn-sm" onclick="closeModal('clientMonthlyPlanModal')" style="color:#ffffff; border-color:rgba(255,255,255,0.4); background:rgba(255,255,255,0.1); font-size:18px; line-height:1; padding:4px 10px; border-radius:8px;">&times;</button>
        </div>

        <div class="cmp-modal-body" id="cmpPrintArea">
            <!-- Plan Controls Top Bar -->
            <div class="cmp-top-toolbar no-print">
                <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap; flex:1;">
                    <div style="min-width:150px;">
                        <label style="font-size:11px; font-weight:800; color:#475569; display:block; margin-bottom:4px; text-transform:uppercase;">Month</label>
                        <input type="month" id="cmp_month" class="form-control" style="height:36px; font-size:13px; font-weight:700;" onchange="onMonthlyPlanMonthChanged()">
                    </div>
                    <div style="flex:2; min-width:220px;">
                        <label style="font-size:11px; font-weight:800; color:#475569; display:block; margin-bottom:4px; text-transform:uppercase;">Plan Title / Strategy Focus</label>
                        <input type="text" id="cmp_title" class="form-control" style="height:36px; font-size:13px;" placeholder="e.g. Q4 Growth &amp; Content Scaling Roadmap">
                    </div>
                    <div style="width:170px;">
                        <label style="font-size:11px; font-weight:800; color:#475569; display:block; margin-bottom:4px; text-transform:uppercase;">Client Paid Amount (₹)</label>
                        <input type="number" id="cmp_paid_amount" class="form-control" style="height:36px; font-size:13px; font-weight:800; color:#0f172a;" placeholder="0.00" step="0.01" oninput="calculateModalBalances()">
                        <input type="hidden" id="cmp_budget" value="0">
                    </div>
                    <div style="width:150px;">
                        <label style="font-size:11px; font-weight:800; color:#475569; display:block; margin-bottom:4px; text-transform:uppercase;">Plan Status</label>
                        <select id="cmp_status" class="form-select" style="height:36px; font-size:13px; font-weight:700;">
                            <option value="Active">🟢 Active</option>
                            <option value="Draft">⚪ Draft</option>
                            <option value="Client Review">🟡 Client Review</option>
                            <option value="Approved">✅ Approved</option>
                            <option value="Completed">🎉 Completed</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Platform Spends & Current Balance Breakdown Card -->
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:16px 18px; margin-bottom:14px; box-shadow:0 1px 4px rgba(15,23,42,0.03);" class="no-print">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:18px;">💳</span>
                        <div>
                            <strong style="font-size:13.5px; color:#0f172a;">Platform Spends &amp; Current Amount Breakdown</strong>
                            <div style="font-size:11.5px; color:#64748b;">Add custom platform amounts (Meta, FB, Google Ads, etc.). The main paid amount automatically subtracts each spend to show the current remaining amount.</div>
                        </div>
                    </div>

                    <!-- Live Financial Summary Metrics -->
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:6px 12px; text-align:center;">
                            <div style="font-size:10px; font-weight:800; color:#0369a1; text-transform:uppercase;">Client Paid</div>
                            <div id="cmpModalPaidSummary" style="font-size:15px; font-weight:800; color:#0c4a6e;">₹0</div>
                        </div>
                        <div style="font-size:15px; font-weight:800; color:#94a3b8;">−</div>
                        <div style="background:#fdf2f8; border:1px solid #fbcfe8; border-radius:8px; padding:6px 12px; text-align:center;">
                            <div style="font-size:10px; font-weight:800; color:#be185d; text-transform:uppercase;">Platform Spends</div>
                            <div id="cmpModalAllocatedSummary" style="font-size:15px; font-weight:800; color:#9d174d;">₹0</div>
                        </div>
                        <div style="font-size:15px; font-weight:800; color:#94a3b8;">=</div>
                        <div id="cmpModalBalanceBox" style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:6px 14px; text-align:center;">
                            <div style="font-size:10px; font-weight:800; color:#065f46; text-transform:uppercase;">Current Amount</div>
                            <div id="cmpModalBalanceSummary" style="font-size:16px; font-weight:800; color:#047857;">₹0</div>
                        </div>
                    </div>
                </div>

                <!-- Platform Allocations List Container -->
                <div id="cmpPlatformAllocationsList" style="display:flex; flex-direction:column; gap:8px; margin-bottom:10px;">
                    <!-- Dynamic platform allocation rows -->
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <button type="button" class="btn btn-outline btn-sm" style="font-size:12px; font-weight:700; color:#4f46e5; border-color:#c7d2fe; border-radius:8px; display:inline-flex; align-items:center; gap:5px; background:#ffffff;" onclick="addPlatformAllocationRow()">
                        <span>+ Add Platform (Meta, FB, Google, etc.)</span>
                    </button>
                    <span id="cmpPlatformBalanceHint" style="font-size:12px; color:#64748b; font-weight:600;"></span>
                </div>
            </div>

            <!-- Table Action Buttons Toolbar -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;" class="no-print">
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <!-- Modal View Tabs -->
                    <div style="display:inline-flex; background:#f1f5f9; padding:3px; border-radius:10px; gap:3px;">
                        <button type="button" id="cmpModalTabTable" class="cmp-view-tab-btn active" onclick="switchModalPlanTab('table')">
                            📊 Master Table &amp; Columns
                        </button>
                        <button type="button" id="cmpModalTabMembers" class="cmp-view-tab-btn" onclick="switchModalPlanTab('members')">
                            👥 By Assigned Member
                        </button>
                    </div>

                    <!-- Assignee Filter in Modal -->
                    <select id="cmpModalFilterAssignee" class="form-select form-select-sm" style="height:32px; font-size:12px; font-weight:700; border-radius:8px; width:auto; min-width:150px;" onchange="applyModalAssigneeFilter()">
                        <option value="">Filter: All Members</option>
                    </select>

                    <button type="button" class="btn btn-outline btn-sm" style="color:#4f46e5; border-color:#c7d2fe; font-weight:700; background:#ffffff; display:inline-flex; align-items:center; gap:5px; border-radius:10px; padding:6px 12px;" onclick="addMonthlyPlanColumn()">
                        <span>+ Add Custom Column</span>
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" style="color:#059669; border-color:#a7f3d0; font-weight:700; background:#ffffff; display:inline-flex; align-items:center; gap:5px; border-radius:10px; padding:6px 12px;" onclick="addMonthlyPlanRow()">
                        <span>+ Add Deliverable Row</span>
                    </button>
                    <button type="button" id="cmpBtnCopyLastMonth" class="btn btn-outline btn-sm" style="color:#d97706; border-color:#fde68a; font-weight:700; background:#ffffff; display:none; align-items:center; gap:5px; border-radius:10px; padding:6px 12px;" onclick="copyPreviousMonthPlan()">
                        <span>📋 Copy Last Month</span>
                    </button>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <button type="button" class="btn btn-outline btn-sm" style="color:#334155; border-color:#cbd5e1; background:#ffffff; border-radius:10px; padding:6px 14px;" onclick="window.print()" title="Print / Save PDF">
                        🖨️ Print / PDF
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; font-weight:700; padding:7px 18px; border-radius:10px;" onclick="saveClientMonthlyPlan()">
                        💾 Save Plan
                    </button>
                </div>
            </div>

            <!-- Dynamic Custom Table Structure (View 1: Master Table) -->
            <div class="cmp-table-wrapper" id="cmpModalTableView">
                <table class="cmp-table" id="cmpDynamicTable">
                    <thead id="cmpTableHead"></thead>
                    <tbody id="cmpTableBody"></tbody>
                </table>
            </div>

            <!-- Dynamic Member Breakdown (View 2: Assigned Members Separately) -->
            <div id="cmpModalMembersView" style="display:none; margin-bottom:18px;">
                <div id="cmpModalMembersGrid" class="cmp-member-grid"></div>
            </div>

            <!-- Monthly Plan Strategic Notes -->
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:16px 18px; box-shadow:0 1px 6px rgba(15, 23, 42, 0.03);">
                <label style="font-size:12px; font-weight:800; color:#1e293b; display:block; margin-bottom:6px; text-transform:uppercase;">
                    📝 Monthly Strategic Objectives &amp; Client Scope Notes
                </label>
                <textarea id="cmp_notes" class="form-control" rows="3" style="font-size:13px;" placeholder="Add monthly goals, KPIs, client requests, campaign themes, or deliverable guidelines..."></textarea>
            </div>
        </div>

        <div class="modal-footer" style="padding:14px 24px; background:#ffffff; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:12px; color:#64748b;" id="cmpLastUpdatedInfo"></div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('clientMonthlyPlanModal')">Close</button>
                <button type="button" class="btn btn-primary" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; font-weight:700; padding:8px 20px; border-radius:10px;" onclick="saveClientMonthlyPlan()">
                    💾 Save Monthly Plan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ADD / CONFIGURE PROJECT SERVICE -->
<div id="addProjectServiceModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:540px; width:95%; max-height:90vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title" id="apsModalTitle" style="font-weight:800; color:#0f172a;">Add Service to Project</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('addProjectServiceModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitSaveProjectService();">
            <div class="modal-body">
                <input type="hidden" id="aps_service_id" value="0">
                <input type="hidden" id="aps_project_id" value="0">

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Project</label>
                    <input type="text" id="aps_project_name_display" class="form-control" readonly style="background:#f1f5f9; color:#334155; font-weight:700;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Quick Select Service Preset or Enter Name *</label>
                    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="setServicePreset('SEO')" style="border-radius:20px; font-size:12px; font-weight:700;">🔍 SEO</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setServicePreset('Website Development')" style="border-radius:20px; font-size:12px; font-weight:700;">💻 Website Development</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setServicePreset('Google Ads')" style="border-radius:20px; font-size:12px; font-weight:700;">📢 Google Ads</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setServicePreset('Social Media')" style="border-radius:20px; font-size:12px; font-weight:700;">📱 Social Media</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setServicePreset('Graphic Design')" style="border-radius:20px; font-size:12px; font-weight:700;">🎨 Graphic Design</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setServicePreset('Content Writing')" style="border-radius:20px; font-size:12px; font-weight:700;">✍️ Content Writing</button>
                    </div>
                    <input type="text" id="aps_service_name" class="form-control" required placeholder="e.g. SEO, Website Development, Google Ads...">
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label">Service Description / Scope (Optional)</label>
                    <textarea id="aps_description" class="form-control" rows="2" placeholder="e.g. Keyword ranking, Technical audit, Monthly backlinks..."></textarea>
                </div>

                <div class="form-group" style="margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="form-label" style="font-size:12px; font-weight:700; margin:0; color:#1e293b;">
                            👥 Assign Team Members to this Service (Multiple Persons)
                        </label>
                        <span id="aps_selected_count" class="badge" style="background:#e0e7ff; color:#3730a3; font-weight:700;">0 Selected</span>
                    </div>
                    <small style="color:#64748b; font-size:11px; display:block; margin-bottom:6px;">Click members below to toggle assignment to this service:</small>
                    <div id="aps_members_container" style="display:flex; flex-wrap:wrap; gap:6px; max-height:180px; overflow-y:auto; padding:10px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc;">
                        <!-- Dynamic staff pills -->
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProjectServiceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; font-weight:700; padding:8px 20px; border-radius:8px;">
                    💾 Save Service
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ASSIGN MEMBERS TO SERVICE -->
<div id="assignServiceMembersModal" class="modal-overlay" style="display:none; z-index:9999;">
    <div class="modal-content" style="max-width:540px; width:95%; max-height:90vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title" id="asmmTitle" style="font-weight:800; color:#0f172a;">Assign Team Members to Service</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('assignServiceMembersModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitAssignServiceMembers();">
            <div class="modal-body">
                <input type="hidden" id="asmm_service_id" value="0">
                <input type="hidden" id="asmm_project_id" value="0">

                <div style="margin-bottom:12px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:12px 14px;">
                    <span style="font-size:13.5px; color:#1e40af; font-weight:800;" id="asmm_service_display">Service: SEO</span>
                    <p style="margin:4px 0 0; font-size:12px; color:#3b82f6;">Select multiple team members who will handle deliverables and tasks under this service.</p>
                </div>

                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label class="form-label" style="font-size:12px; font-weight:700; margin:0; color:#1e293b;">Click Members to Assign/Unassign</label>
                        <span id="asmm_selected_count" class="badge" style="background:#dcfce7; color:#15803d; font-weight:700;">0 Members</span>
                    </div>
                    <div id="asmm_members_container" style="display:flex; flex-wrap:wrap; gap:6px; max-height:220px; overflow-y:auto; padding:10px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc;">
                        <!-- Dynamic staff pills -->
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignServiceMembersModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, #10b981, #059669); border:none; font-weight:700; padding:8px 20px; border-radius:8px;">
                    ✓ Update Service Members
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Autocomplete Datalist for Staff / Member Assignment -->
<datalist id="staffDatalist">
    <?php foreach ($developers as $dev): ?>
        <option value="<?= htmlspecialchars($dev['full_name']) ?>"><?= htmlspecialchars($dev['role_name'] ?? $dev['department']) ?> (<?= htmlspecialchars($dev['department']) ?>)</option>
    <?php endforeach; ?>
</datalist>

<script>
    const userRole = <?= json_encode($user['role']) ?>;
    const isLeadOrAdminUser = <?= json_encode($isLead) ?>;
    const canManageAnyTasksUser = <?= json_encode($canManageAnyTasks) ?>;
    const handledProjectIdsUser = <?= json_encode(array_values($handledProjectIds)) ?>;
    const canCreateTasksUser = <?= json_encode($canManageAnyTasks) ?>;
    const currentUserId = <?= intval($user['id']) ?>;
    const currentUserDept = <?= json_encode($user['department'] ?? 'Development') ?>;
    const currentUserName = <?= json_encode($user['full_name'] ?? '') ?>;
    let currentWorkspaceView = <?= json_encode($activeView) ?>;
    let projSearchTimer = null;

    const allStaffUsers = <?= json_encode($developers) ?>;
    window.allDevelopersList = allStaffUsers;
    window.apsSelectedMembers = [];
    window.asmmSelectedMembers = [];
    window.staffByNameMap = {};
    if (Array.isArray(allStaffUsers)) {
        allStaffUsers.forEach(u => {
            if (u && u.full_name) {
                window.staffByNameMap[u.full_name.toLowerCase().trim()] = u;
            }
        });
    }

    function getServiceIcon(serviceName) {
        const s = (serviceName || '').toLowerCase();
        if (s.includes('seo')) return '🔍';
        if (s.includes('web') || s.includes('develop')) return '💻';
        if (s.includes('google') || s.includes('ad') || s.includes('ppc')) return '📢';
        if (s.includes('social') || s.includes('smm') || s.includes('media') || s.includes('insta')) return '📱';
        if (s.includes('design') || s.includes('ui') || s.includes('graphic')) return '🎨';
        if (s.includes('content') || s.includes('copy') || s.includes('writ')) return '✍️';
        if (s.includes('video') || s.includes('shoot')) return '🎬';
        return '⚡';
    }

    function getStatusBadgeHtml(st) {
        st = (st || 'PENDING').toUpperCase().trim();
        if (st === 'IN PROGRESS') {
            return '<span class="badge" style="background:#eff6ff; color:#2563eb; font-weight:700; border:1px solid #bfdbfe; font-size:11px;">⚡ In Progress</span>';
        } else if (st === 'COMPLETED') {
            return '<span class="badge" style="background:#dcfce7; color:#15803d; font-weight:700; border:1px solid #bbf7d0; font-size:11px;">✓ Completed</span>';
        } else if (st === 'UNDER REVIEW') {
            return '<span class="badge" style="background:#fef3c7; color:#d97706; font-weight:700; border:1px solid #fde68a; font-size:11px;">⏳ Under Review</span>';
        } else if (st === 'PENDING LEAD APPROVAL') {
            return '<span class="badge" style="background:#fef3c7; color:#b45309; font-weight:700; border:1px solid #fde68a; font-size:11px;">📝 Pending Lead</span>';
        } else if (st === 'LEAD APPROVED') {
            return '<span class="badge" style="background:#dcfce7; color:#15803d; font-weight:700; border:1px solid #bbf7d0; font-size:11px;">✓ Lead Approved</span>';
        } else if (st === 'REVISION REQUIRED') {
            return '<span class="badge" style="background:#fee2e2; color:#dc2626; font-weight:700; border:1px solid #fca5a5; font-size:11px;">⚠️ Revision</span>';
        } else if (st === 'SENT FOR CLIENT APPROVAL') {
            return '<span class="badge" style="background:#f5f3ff; color:#7c3aed; font-weight:700; border:1px solid #ddd6fe; font-size:11px;">📩 Client Approval</span>';
        } else {
            return `<span class="badge badge-secondary" style="font-size:11px;">${escapeHtml(st)}</span>`;
        }
    }

    function getPriorityBadgeHtml(pr) {
        pr = (pr || 'Medium').toLowerCase();
        if (pr === 'urgent') return '<span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:700; font-size:11px;">Urgent</span>';
        if (pr === 'high') return '<span class="badge" style="background:#fef3c7; color:#b45309; font-weight:700; font-size:11px;">High</span>';
        return `<span class="badge" style="background:#f1f5f9; color:#475569; font-size:11px;">${escapeHtml(pr.charAt(0).toUpperCase() + pr.slice(1))}</span>`;
    }

    function toggleNestedSubtasks(taskId) {
        const row = document.getElementById('nested_subtasks_row_' + taskId);
        const chev = document.getElementById('subtask_chevron_' + taskId);
        if (!row) return;
        if (row.style.display === 'none' || !row.style.display) {
            row.style.display = 'table-row';
            if (chev) chev.textContent = '▼';
        } else {
            row.style.display = 'none';
            if (chev) chev.textContent = '▶';
        }
    }

    window.openAddServiceModal = function(projectId, projectName) {
        document.getElementById('aps_project_id').value = projectId;
        document.getElementById('aps_service_id').value = '0';
        document.getElementById('aps_project_name_display').value = projectName;
        document.getElementById('aps_service_name').value = '';
        document.getElementById('aps_description').value = '';
        document.getElementById('apsModalTitle').textContent = 'Add Service to ' + projectName;
        window.apsSelectedMembers = [];
        renderApsMembersPills();
        document.getElementById('addProjectServiceModal').style.display = 'flex';
    };

    window.setServicePreset = function(presetName) {
        document.getElementById('aps_service_name').value = presetName;
    };

    window.renderApsMembersPills = function() {
        const container = document.getElementById('aps_members_container');
        const badge = document.getElementById('aps_selected_count');
        if (!container) return;
        if (badge) badge.textContent = `${window.apsSelectedMembers.length} Selected`;
        container.innerHTML = (window.allDevelopersList || []).map(m => {
            const isSel = window.apsSelectedMembers.includes(m.id);
            return `
                <div onclick="toggleApsMember(${m.id})" style="cursor:pointer; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; border:1px solid ${isSel ? '#4f46e5' : '#cbd5e1'}; background:${isSel ? '#e0e7ff' : '#ffffff'}; color:${isSel ? '#3730a3' : '#475569'}; user-select:none; transition:all 0.15s ease;">
                    ${isSel ? '✓ ' : '+ '} ${escapeHtml(m.full_name)} <span style="font-size:10px; opacity:0.75;">(${escapeHtml(m.department || '')})</span>
                </div>
            `;
        }).join('');
    };

    window.toggleApsMember = function(userId) {
        const idx = window.apsSelectedMembers.indexOf(userId);
        if (idx > -1) {
            window.apsSelectedMembers.splice(idx, 1);
        } else {
            window.apsSelectedMembers.push(userId);
        }
        renderApsMembersPills();
    };

    window.submitSaveProjectService = function() {
        const projectId = parseInt(document.getElementById('aps_project_id').value) || 0;
        const serviceId = parseInt(document.getElementById('aps_service_id').value) || 0;
        const serviceName = document.getElementById('aps_service_name').value.trim();
        const description = document.getElementById('aps_description').value.trim();

        if (!projectId || !serviceName) {
            alert('Please enter a service name.');
            return;
        }

        fetch('api.php?action=save_project_service', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: serviceId,
                project_id: projectId,
                service_name: serviceName,
                description: description,
                assignees: window.apsSelectedMembers
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('addProjectServiceModal');
                loadCurrentProjects();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Network error saving service.'));
    };

    window.quickAddProjectService = function(projectId, serviceName) {
        fetch('api.php?action=save_project_service', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                project_id: projectId,
                service_name: serviceName,
                assignees: []
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadCurrentProjects();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Network error adding service.'));
    };

    window.deleteProjectService = function(serviceId, projectId) {
        if (!confirm('Are you sure you want to remove this service from the project? Tasks will remain in the project.')) return;
        fetch('api.php?action=delete_project_service', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: serviceId, project_id: projectId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadCurrentProjects();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Network error deleting service.'));
    };

    window.openAssignServiceMembersModal = function(serviceId, serviceName, currentAssigneeIds, projectId) {
        document.getElementById('asmm_service_id').value = serviceId;
        document.getElementById('asmm_project_id').value = projectId;
        document.getElementById('asmm_service_display').textContent = 'Service: ' + serviceName;
        document.getElementById('asmmTitle').textContent = 'Assign Team to ' + serviceName;
        window.asmmSelectedMembers = Array.isArray(currentAssigneeIds) ? currentAssigneeIds.map(x => parseInt(x)) : [];
        renderAsmmMembersPills();
        document.getElementById('assignServiceMembersModal').style.display = 'flex';
    };

    window.renderAsmmMembersPills = function() {
        const container = document.getElementById('asmm_members_container');
        const badge = document.getElementById('asmm_selected_count');
        if (!container) return;
        if (badge) badge.textContent = `${window.asmmSelectedMembers.length} Members`;
        container.innerHTML = (window.allDevelopersList || []).map(m => {
            const isSel = window.asmmSelectedMembers.includes(m.id);
            return `
                <div onclick="toggleAsmmMember(${m.id})" style="cursor:pointer; padding:5px 12px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid ${isSel ? '#16a34a' : '#cbd5e1'}; background:${isSel ? '#dcfce7' : '#ffffff'}; color:${isSel ? '#15803d' : '#475569'}; user-select:none; transition:all 0.15s ease;">
                    ${isSel ? '✓ ' : '+ '} ${escapeHtml(m.full_name)} <span style="font-size:10px; opacity:0.75;">(${escapeHtml(m.department || '')})</span>
                </div>
            `;
        }).join('');
    };

    window.toggleAsmmMember = function(userId) {
        const idx = window.asmmSelectedMembers.indexOf(userId);
        if (idx > -1) {
            window.asmmSelectedMembers.splice(idx, 1);
        } else {
            window.asmmSelectedMembers.push(userId);
        }
        renderAsmmMembersPills();
    };

    window.submitAssignServiceMembers = function() {
        const serviceId = parseInt(document.getElementById('asmm_service_id').value) || 0;
        fetch('api.php?action=assign_project_service_members', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                service_id: serviceId,
                assignees: window.asmmSelectedMembers
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('assignServiceMembersModal');
                loadCurrentProjects();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('Network error updating service members.'));
    };

    function renderProjectTasksTableHtml(tasksList, subtasksByParent, projId, deptName, srvId = 0, canManage = true) {
        if (!tasksList || tasksList.length === 0) {
            return `
                <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:14px; text-align:center; color:#64748b; font-size:12px; margin:8px 0;">
                    No tasks under this section yet.
                    ${canManage ? `
                        <button type="button" class="btn btn-outline btn-sm" style="margin-left:8px; font-size:11px; color:#4f46e5; border-color:#c7d2fe; font-weight:700;" onclick='openCreateProjectTask(${projId}, ${JSON.stringify(deptName || "")}, 0, false, ${srvId})'>
                            + Create Task
                        </button>
                    ` : ''}
                </div>
            `;
        }

        let rowsHtml = '';
        tasksList.forEach(t => {
            const st = (t.status || 'PENDING').toUpperCase();
            const isInProg = st === 'IN PROGRESS';
            let stBadge = getStatusBadgeHtml(st);
            let prBadge = getPriorityBadgeHtml(t.priority);
            const subtasks = subtasksByParent[t.id] || [];
            const hasSubtasks = subtasks.length > 0;

            rowsHtml += `
                <tr class="${isInProg ? 'proj-task-row-inprogress' : ''}">
                    <td>
                        <a href="task_details.php?id=${t.id}" class="badge" style="background:#f1f5f9; color:#0f172a; font-weight:800; font-family:monospace; text-decoration:none;" title="Open Task Details">
                            ${escapeHtml(t.task_code || ('#' + t.id))}
                        </a>
                    </td>
                    <td>
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <a href="task_details.php?id=${t.id}" style="color:#0f172a; font-weight:700; text-decoration:none;">
                                ${escapeHtml(t.title)}
                            </a>
                            ${hasSubtasks ? `
                                <button type="button" class="badge" style="background:#f3e8ff; color:#7e22ce; border:1px solid #ddd6fe; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; cursor:pointer;" onclick="toggleNestedSubtasks(${t.id})">
                                    <span id="subtask_chevron_${t.id}">▶</span> ${subtasks.length} Subtask${subtasks.length === 1 ? '' : 's'}
                                </button>
                            ` : ''}
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="background:#f1f5f9; color:#334155; font-size:11px;">${escapeHtml(t.department || 'General')}</span>
                    </td>
                    <td>
                        <span style="display:inline-flex; align-items:center; gap:5px; font-weight:600; color:#334155; font-size:12px;">
                            <span style="width:20px; height:20px; border-radius:50%; background:#e2e8f0; color:#475569; display:inline-flex; align-items:center; justify-content:center; font-size:9.5px; font-weight:800;">
                                ${(t.assigned_to_name || 'U')[0].toUpperCase()}
                            </span>
                            ${escapeHtml(t.assigned_to_name || 'Unassigned')}
                        </span>
                    </td>
                    <td>${prBadge}</td>
                    <td>${stBadge}</td>
                    <td>
                        <span style="font-size:11px; color:#64748b;">${t.deadline ? t.deadline.substring(0, 10) : 'N/A'}</span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex; gap:4px; justify-content:flex-end; align-items:center;">
                            <a href="task_details.php?id=${t.id}" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px; border-radius:6px; color:#2563eb; border-color:#bfdbfe;" title="View Details & Discussion">
                                Details
                            </a>
                            ${canManage ? `
                                <button type="button" class="btn btn-outline btn-sm" style="padding:2px 7px; font-size:11px; border-radius:6px; color:#7c3aed; border-color:#ddd6fe; font-weight:700; background:#faf5ff;" onclick='openCreateProjectTask(${projId}, ${JSON.stringify(t.department || "")}, ${t.id}, true, ${srvId})' title="Add subtask directly under this task">
                                    + Subtask
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>

                <!-- NESTED SUBTASKS EXPANDABLE ROW -->
                <tr id="nested_subtasks_row_${t.id}" style="display:none; background:#faf5ff;">
                    <td colspan="8" style="padding:10px 18px; border-top:1px dashed #e9d5ff; border-bottom:1px solid #e9d5ff;">
                        <div style="background:#ffffff; border:1px solid #e9d5ff; border-left:4px solid #7c3aed; border-radius:10px; padding:12px 16px; box-shadow:0 1px 4px rgba(124,58,237,0.06);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:13px; font-weight:800; color:#581c87;">↳ Subtasks of "${escapeHtml(t.title)}"</span>
                                    <span class="badge" style="background:#f3e8ff; color:#7e22ce; font-weight:700; font-size:10.5px;">${subtasks.length} Subtask${subtasks.length === 1 ? '' : 's'}</span>
                                </div>
                                ${canManage ? `
                                    <button type="button" class="btn btn-primary btn-sm" style="background:#7c3aed; border:none; padding:3px 10px; font-size:11px; font-weight:700; border-radius:6px;" onclick='openCreateProjectTask(${projId}, ${JSON.stringify(t.department || "")}, ${t.id}, true, ${srvId})'>
                                        + Add Sub-Task
                                    </button>
                                ` : ''}
                            </div>

                            ${subtasks.length === 0 ? `
                                <p style="margin:0; font-size:12px; color:#64748b; font-style:italic;">No subtasks created for this task yet. Click "+ Add Sub-Task" to create one.</p>
                            ` : `
                                <div style="overflow-x:auto;">
                                    <table style="width:100%; border-collapse:collapse; font-size:12px;">
                                        <thead>
                                            <tr style="border-bottom:1px solid #f1f5f9; color:#64748b; text-align:left; font-size:11px; text-transform:uppercase;">
                                                <th style="padding:6px 8px; width:90px;">Code</th>
                                                <th style="padding:6px 8px;">Subtask Title</th>
                                                <th style="padding:6px 8px; width:130px;">Assignee</th>
                                                <th style="padding:6px 8px; width:80px;">Priority</th>
                                                <th style="padding:6px 8px; width:110px;">Status</th>
                                                <th style="padding:6px 8px; width:95px;">Deadline</th>
                                                <th style="padding:6px 8px; width:70px; text-align:right;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${subtasks.map(st => {
                                                const stSt = (st.status || 'PENDING').toUpperCase();
                                                return `
                                                    <tr style="border-bottom:1px solid #f8fafc;">
                                                        <td style="padding:6px 8px;">
                                                            <a href="task_details.php?id=${st.id}" class="badge" style="background:#f1f5f9; color:#581c87; font-weight:800; font-family:monospace; text-decoration:none;">
                                                                ${escapeHtml(st.task_code || ('#' + st.id))}
                                                            </a>
                                                        </td>
                                                        <td style="padding:6px 8px;">
                                                            <a href="task_details.php?id=${st.id}" style="color:#0f172a; font-weight:600; text-decoration:none;">
                                                                ${escapeHtml(st.title)}
                                                            </a>
                                                        </td>
                                                        <td style="padding:6px 8px; color:#475569;">
                                                            👤 ${escapeHtml(st.assigned_to_name || 'Unassigned')}
                                                        </td>
                                                        <td style="padding:6px 8px;">
                                                            ${getPriorityBadgeHtml(st.priority)}
                                                        </td>
                                                        <td style="padding:6px 8px;">
                                                            ${getStatusBadgeHtml(stSt)}
                                                        </td>
                                                        <td style="padding:6px 8px; color:#64748b; font-size:11px;">
                                                            ${st.deadline ? st.deadline.substring(0, 10) : 'N/A'}
                                                        </td>
                                                        <td style="padding:6px 8px; text-align:right;">
                                                            <a href="task_details.php?id=${st.id}" class="btn btn-outline btn-sm" style="padding:1px 6px; font-size:11px; border-radius:4px; color:#2563eb; border-color:#bfdbfe;">
                                                                Details
                                                            </a>
                                                        </td>
                                                    </tr>
                                                `;
                                            }).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            `}
                        </div>
                    </td>
                </tr>
            `;
        });

        return `
            <div style="overflow-x:auto;">
                <table class="proj-tasks-table" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="width:105px;">Task Code</th>
                            <th>Main Task Title</th>
                            <th style="width:125px;">Department</th>
                            <th style="width:140px;">Assignee</th>
                            <th style="width:90px;">Priority</th>
                            <th style="width:120px;">Status</th>
                            <th style="width:110px;">Deadline</th>
                            <th style="width:130px; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>
        `;
    }
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

        // Support 1-click Quick Action shortcuts from Super Admin Dashboard
        const openParam = new URLSearchParams(window.location.search).get('open');
        if (openParam === 'new_project' && typeof openCreateProjectModal === 'function') {
            setTimeout(() => openCreateProjectModal(), 150);
        } else if (openParam === 'import_projects' && typeof openImportProjectsModal === 'function') {
            setTimeout(() => openImportProjectsModal(), 150);
        } else if (openParam === 'new_task' && typeof openCreateTaskModal === 'function') {
            setTimeout(() => openCreateTaskModal(), 150);
        }
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
                window.monthlyPlansByClient = data.monthly_plans_by_client || {};
                window.cmpCurrentMonth = data.current_month || '';
                window.currentProjectsData = data.projects || [];
                renderCurrentProjects(window.currentProjectsData);
                if (isCurrent === '1' && clientId === '0' && !dept && !search) {
                    const badge = document.getElementById('currentProjCountBadge');
                    if (badge) badge.textContent = (data.projects || []).length;
                }
            })
            .catch(err => console.error('Projects load error:', err));
    }

    let expandedClientIds = new Set();
    let cmpCurrentClientId = 0;
    let cmpCurrentClientName = '';
    let currentPlanColumns = [];
    let currentPlanRows = [];
    let currentPlanPlatformAllocations = [];
    let cmpPreviousMonth = '';
    let currentModalPlanTab = 'table';
    let modalAssigneeFilterValue = '';
    window.clientPlanActiveView = {}; // clientId => 'members' | 'table'

    function getCmpPlatformIcon(name) {
        const s = (name || '').toLowerCase();
        if (s.includes('meta') || s.includes('fb') || s.includes('facebook') || s.includes('insta')) return '📱';
        if (s.includes('google') || s.includes('ad') || s.includes('ppc')) return '📢';
        if (s.includes('link') || s.includes('in')) return '💼';
        if (s.includes('you') || s.includes('tube')) return '▶️';
        if (s.includes('influenc')) return '🌟';
        if (s.includes('seo') || s.includes('tool')) return '🔍';
        if (s.includes('web') || s.includes('dev')) return '💻';
        return '⚡';
    }

    function getClientInitials(name) {
        if (!name) return 'CL';
        const parts = name.trim().split(/[\s\-_]+/);
        if (parts.length >= 2 && parts[0] && parts[1]) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.trim().substring(0, 2).toUpperCase();
    }

    function formatMonthLabel(monthStr) {
        if (!monthStr) return 'Current Month';
        const parts = monthStr.split('-');
        if (parts.length === 2) {
            const year = parseInt(parts[0]);
            const month = parseInt(parts[1]) - 1;
            const date = new Date(year, month, 1);
            if (!isNaN(date.getTime())) {
                return date.toLocaleString('default', { month: 'long', year: 'numeric' });
            }
        }
        return monthStr;
    }

    function getMemberInitials(name) {
        if (!name || name === 'Unassigned') return '?';
        const parts = name.trim().split(/[\s\-_]+/);
        if (parts.length >= 2 && parts[0] && parts[1]) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.trim().substring(0, 2).toUpperCase();
    }

    function getMemberGradient(name) {
        if (!name || name === 'Unassigned') return 'linear-gradient(135deg, #f59e0b, #d97706)';
        const grads = [
            'linear-gradient(135deg, #6366f1, #8b5cf6)',
            'linear-gradient(135deg, #3b82f6, #06b6d4)',
            'linear-gradient(135deg, #059669, #10b981)',
            'linear-gradient(135deg, #ec4899, #f43f5e)',
            'linear-gradient(135deg, #8b5cf6, #d946ef)',
            'linear-gradient(135deg, #0284c7, #2563eb)'
        ];
        let hash = 0;
        for (let i = 0; i < name.length; i++) {
            hash = name.charCodeAt(i) + ((hash << 5) - hash);
        }
        return grads[Math.abs(hash) % grads.length];
    }

    function getCmpChannelBadgeHtml(ch) {
        if (!ch || !ch.trim()) return '<span style="color:#cbd5e1; font-weight:600;">—</span>';
        const c = ch.trim();
        const cl = c.toLowerCase();
        let icon = '📱';
        let bg = '#eff6ff', fg = '#1e40af', border = '#bfdbfe';
        if (cl.includes('seo')) { icon = '🔍'; bg = '#ecfdf5'; fg = '#065f46'; border = '#a7f3d0'; }
        else if (cl.includes('web') || cl.includes('dev')) { icon = '💻'; bg = '#f0f9ff'; fg = '#0369a1'; border = '#bae6fd'; }
        else if (cl.includes('google') || cl.includes('ad') || cl.includes('ppc')) { icon = '📢'; bg = '#fef2f2'; fg = '#b91c1c'; border = '#fecaca'; }
        else if (cl.includes('insta') || cl.includes('reel') || cl.includes('social') || cl.includes('smm')) { icon = '📱'; bg = '#fdf2f8'; fg = '#be185d'; border = '#fbcfe8'; }
        else if (cl.includes('content') || cl.includes('copy') || cl.includes('blog') || cl.includes('writ')) { icon = '✍️'; bg = '#faf5ff'; fg = '#7e22ce'; border = '#e9d5ff'; }
        else if (cl.includes('video') || cl.includes('youtube')) { icon = '🎬'; bg = '#fff1f2'; fg = '#be123c'; border = '#fecdd3'; }
        else if (cl.includes('design') || cl.includes('ui') || cl.includes('graphic')) { icon = '🎨'; bg = '#fff7ed'; fg = '#c2410c'; border = '#ffedd5'; }
        return `<span class="badge" style="background:${bg}; color:${fg}; border:1px solid ${border}; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px; display:inline-flex; align-items:center; gap:4px;"><span>${icon}</span> ${escapeHtml(c)}</span>`;
    }

    function switchClientPlanView(clientId, viewType) {
        const cid = parseInt(clientId);
        if (!window.clientPlanActiveView) window.clientPlanActiveView = {};
        window.clientPlanActiveView[cid] = viewType;
        if (window.currentProjectsData) {
            renderCurrentProjects(window.currentProjectsData);
        }
    }

    function quickUpdateMonthlyDeliverableStatus(clientId, month, rowId, newStatus) {
        const cid = parseInt(clientId);
        fetch('api.php?action=update_monthly_plan_item_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                client_id: cid,
                month: month,
                row_id: rowId,
                status: newStatus
            })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Status Update Error: ' + (data.message || 'Could not update status'));
                return;
            }
            // Update in-memory plan
            if (window.monthlyPlansByClient && window.monthlyPlansByClient[cid] && Array.isArray(window.monthlyPlansByClient[cid].rows)) {
                const targetRow = window.monthlyPlansByClient[cid].rows.find(r => r.id === rowId);
                if (targetRow) {
                    targetRow.col_status = newStatus;
                }
            }
            // Also update currentPlanRows if modal is open for this client
            if (cmpCurrentClientId === cid && Array.isArray(currentPlanRows)) {
                const modalRow = currentPlanRows.find(r => r.id === rowId);
                if (modalRow) modalRow.col_status = newStatus;
            }
            // Re-render project cards to update progress bars and badge colors
            if (window.currentProjectsData) {
                renderCurrentProjects(window.currentProjectsData);
            }
        })
        .catch(err => {
            console.error('Quick status update error:', err);
            alert('Network error while updating deliverable status.');
        });
    }

    function createTaskFromMonthlyDeliverable(clientId, activity, channel, target, assignee) {
        const cid = parseInt(clientId);
        const cGroup = window.clientGroupsMap ? window.clientGroupsMap[cid] : null;
        let projId = 0;
        let projDept = '';
        if (cGroup && cGroup.projects && cGroup.projects.length > 0) {
            projId = cGroup.projects[0].id;
            projDept = cGroup.projects[0].department || '';
        }

        // Open create task modal
        openCreateProjectTask(projId, projDept);

        // Pre-fill Title
        const titleEl = document.getElementById('ct_title');
        if (titleEl) {
            titleEl.value = activity || '';
        }

        // Pre-fill Description with Deliverable Scope
        const descEl = document.getElementById('ct_description');
        if (descEl) {
            const lines = [];
            lines.push(`Deliverable Objective: ${activity || 'Monthly Deliverable'}`);
            if (channel) lines.push(`Channel / Platform: ${channel}`);
            if (target) lines.push(`Target / Quantity: ${target}`);
            lines.push(`Monthly Plan Scope Item (${window.cmpCurrentMonth || ''})`);
            descEl.value = lines.join('\n');
        }

        // Pre-fill Assigned Member
        if (assignee && assignee !== 'Unassigned' && Array.isArray(allStaffUsers)) {
            const matchedStaff = allStaffUsers.find(u => (u.full_name || '').toLowerCase().trim() === assignee.toLowerCase().trim());
            if (matchedStaff) {
                const assignSelect = document.getElementById('ct_assigned_to');
                if (assignSelect) {
                    assignSelect.value = String(matchedStaff.id);
                }
                createSelectedMembers = [matchedStaff.id];
                if (typeof renderCreatePills === 'function') {
                    renderCreatePills();
                }
            }
        }
    }

    function renderClientMonthlyPlanSection(cid, cname, projs) {
        const plan = (window.monthlyPlansByClient && window.monthlyPlansByClient[cid]) ? window.monthlyPlansByClient[cid] : null;
        const curMonth = (plan && plan.month) || window.cmpCurrentMonth || (new Date().toISOString().substring(0, 7));
        const monthLabel = formatMonthLabel(curMonth);
        const viewType = (window.clientPlanActiveView && window.clientPlanActiveView[cid]) || 'table';

        if (!plan || !plan.rows || !plan.rows.length) {
            return `
                <div class="client-monthly-plan-banner-empty">
                    <div class="cmp-banner-left">
                        <div class="cmp-banner-icon">📅</div>
                        <div>
                            <h4 class="cmp-banner-title">Monthly Strategy &amp; Deliverables Plan — ${escapeHtml(monthLabel)}</h4>
                            <p class="cmp-banner-subtitle">No monthly plan created for ${escapeHtml(cname)} in ${escapeHtml(monthLabel)} yet. Build custom tables, set targets, and assign deliverables to members separately.</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm cmp-banner-btn" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; font-weight:700; padding:8px 16px; border-radius:10px; box-shadow:0 2px 10px rgba(99,102,241,0.25); cursor:pointer;" onclick="openClientMonthlyPlanModal(${cid})">
                        <span>+ Create Monthly Plan</span>
                    </button>
                </div>
            `;
        }

        const rows = plan.rows;
        const columns = plan.columns || [];
        const totalDeliverables = rows.length;
        const doneRows = rows.filter(r => (r.col_status || '').toLowerCase() === 'completed');
        const inProgRows = rows.filter(r => (r.col_status || '').toLowerCase() === 'in progress');
        const overallPct = totalDeliverables > 0 ? Math.round((doneRows.length / totalDeliverables) * 100) : 0;
        const paidAmount = Number(plan.paid_amount !== undefined ? plan.paid_amount : (plan.budget || 0));
        const platformAllocations = Array.isArray(plan.platform_allocations) ? plan.platform_allocations : (typeof plan.platform_allocations_json === 'string' ? (JSON.parse(plan.platform_allocations_json || '[]') || []) : []);
        let totalAllocated = 0;
        platformAllocations.forEach(pa => {
            totalAllocated += Number(pa.amount || 0);
        });
        const currentRemaining = paidAmount - totalAllocated;

        // Group rows by assigned member
        const membersMap = {};
        rows.forEach(r => {
            const rawAss = (r.col_assignee || '').trim();
            const assKey = rawAss || 'Unassigned';
            if (!membersMap[assKey]) {
                membersMap[assKey] = [];
            }
            membersMap[assKey].push(r);
        });

        const memberKeys = Object.keys(membersMap);
        // Sort so current logged in user is first, then other members alphabetically, unassigned last
        memberKeys.sort((a, b) => {
            const aIsMe = (a.toLowerCase() === currentUserName.toLowerCase());
            const bIsMe = (b.toLowerCase() === currentUserName.toLowerCase());
            if (aIsMe && !bIsMe) return -1;
            if (!aIsMe && bIsMe) return 1;
            if (a === 'Unassigned') return 1;
            if (b === 'Unassigned') return -1;
            return a.localeCompare(b);
        });

        let bodyContent = '';

        if (viewType === 'members') {
            // RENDER MEMBER-SEPARATED CARDS
            bodyContent = `<div class="cmp-member-grid">`;
            memberKeys.forEach(mName => {
                const mRows = membersMap[mName];
                const isUnassigned = (mName === 'Unassigned');
                const isMe = (!isUnassigned && mName.toLowerCase() === currentUserName.toLowerCase());
                const mDone = mRows.filter(r => (r.col_status || '').toLowerCase() === 'completed').length;
                const mPct = mRows.length > 0 ? Math.round((mDone / mRows.length) * 100) : 0;
                
                // Resolve staff info
                const staffObj = (!isUnassigned && window.staffByNameMap) ? window.staffByNameMap[mName.toLowerCase()] : null;
                const roleOrDept = staffObj ? (staffObj.role_name || staffObj.department) : (isUnassigned ? 'No Team Member Assigned' : 'Team Member');
                const avatarGrad = getMemberGradient(mName);
                const initials = getMemberInitials(mName);

                bodyContent += `
                    <div class="cmp-member-card ${isMe ? 'is-current-user' : ''} ${isUnassigned ? 'is-unassigned' : ''}" style="${isUnassigned ? 'border:1px dashed #f59e0b; background:#fffdfa;' : ''}">
                        <!-- Member Header -->
                        <div class="cmp-member-card-header">
                            <div class="cmp-member-info">
                                <div class="cmp-member-avatar" style="background:${isUnassigned ? '#f59e0b' : avatarGrad};">
                                    ${escapeHtml(initials)}
                                </div>
                                <div>
                                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                        <span class="cmp-member-name">${escapeHtml(mName)}</span>
                                        ${isMe ? `<span class="badge" style="background:#fef3c7; color:#b45309; font-weight:800; font-size:10px; border:1px solid #fde68a;">⭐ Assigned to You</span>` : ''}
                                    </div>
                                    <div class="cmp-member-role">${escapeHtml(roleOrDept)}</div>
                                </div>
                            </div>
                            <span class="badge" style="background:#eef2ff; color:#4338ca; font-weight:800; font-size:11px; padding:3px 9px; border-radius:999px;">
                                ${mRows.length} Deliverable${mRows.length === 1 ? '' : 's'}
                            </span>
                        </div>

                        <!-- Progress Bar for this member -->
                        <div class="cmp-member-progress">
                            <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:700; color:#64748b; margin-bottom:3px;">
                                <span>Progress</span>
                                <span style="color:#10b981;">${mDone}/${mRows.length} Done (${mPct}%)</span>
                            </div>
                            <div class="cmp-progress-bar-bg">
                                <div class="cmp-progress-bar-fill" style="width:${mPct}%;"></div>
                            </div>
                        </div>

                        <!-- Deliverables List assigned to this member -->
                        <div class="cmp-deliverables-list">
                            ${mRows.map(r => {
                                const st = r.col_status || 'Planned';
                                const act = r.col_activity || 'Deliverable Item';
                                const ch = r.col_channel || '';
                                const tg = r.col_target || '';
                                const wk = r.col_week || '';

                                return `
                                    <div class="cmp-deliverable-item">
                                        <div class="cmp-deliverable-title">${escapeHtml(act)}</div>
                                        <div class="cmp-deliverable-meta">
                                            ${ch ? `<span class="badge" style="background:#f1f5f9; color:#1e293b; font-size:10.5px; font-weight:600;">📱 ${escapeHtml(ch)}</span>` : ''}
                                            ${tg ? `<span class="badge" style="background:#ecfdf5; color:#047857; font-size:10.5px; font-weight:700;">🎯 ${escapeHtml(tg)}</span>` : ''}
                                            ${wk ? `<span class="badge" style="background:#f5f3ff; color:#6d28d9; font-size:10.5px; font-weight:600;">🗓️ ${escapeHtml(wk)}</span>` : ''}
                                        </div>
                                        <div class="cmp-deliverable-footer">
                                            <select class="cmp-mini-status-select cmp-status-select" data-val="${escapeHtml(st)}" onchange="quickUpdateMonthlyDeliverableStatus(${cid}, '${escapeHtml(curMonth)}', '${r.id}', this.value); this.setAttribute('data-val', this.value);">
                                                <option value="Planned" ${st === 'Planned' ? 'selected' : ''}>⚪ Planned</option>
                                                <option value="In Progress" ${st === 'In Progress' ? 'selected' : ''}>🔵 In Progress</option>
                                                <option value="Under Review" ${st === 'Under Review' ? 'selected' : ''}>🟡 Under Review</option>
                                                <option value="Approved" ${st === 'Approved' ? 'selected' : ''}>🟢 Approved</option>
                                                <option value="Completed" ${st === 'Completed' ? 'selected' : ''}>✅ Completed</option>
                                                <option value="Delayed" ${st === 'Delayed' ? 'selected' : ''}>🔴 Delayed</option>
                                            </select>
                                            <button type="button" class="cmp-item-task-btn" onclick="createTaskFromMonthlyDeliverable(${cid}, ${JSON.stringify(act)}, ${JSON.stringify(ch)}, ${JSON.stringify(tg)}, ${JSON.stringify(mName === 'Unassigned' ? '' : mName)})" title="Convert deliverable to active project task">
                                                <span>↳ + Task</span>
                                            </button>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>

                        ${isUnassigned ? `
                            <div style="margin-top:10px; text-align:center;">
                                <button type="button" class="btn btn-outline btn-sm" style="color:#d97706; border-color:#fde68a; font-weight:700; width:100%; border-radius:8px; cursor:pointer;" onclick="openClientMonthlyPlanModal(${cid})">
                                    + Assign Team Members
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
            });
            bodyContent += `</div>`;
        } else {
            // RENDER TABLE VIEW
            bodyContent = `
                <div class="cmp-table-wrapper" style="margin-bottom:0; background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; overflow-x:auto; box-shadow:0 1px 4px rgba(15,23,42,0.03);">
                    <table class="cmp-table" style="width:100%; border-collapse:collapse; min-width:880px;">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0; color:#475569; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.6px; text-align:left;">
                                <th style="width:40px; text-align:center; padding:12px 14px;">#</th>
                                <th style="padding:12px 14px; min-width:220px;">Activity / Deliverable</th>
                                <th style="padding:12px 14px; min-width:130px;">Channel / Platform</th>
                                <th style="padding:12px 14px; min-width:130px;">Target / Milestone</th>
                                <th style="padding:12px 14px; min-width:110px;">Timeline</th>
                                <th style="padding:12px 14px; min-width:160px;">Assigned Member</th>
                                <th style="padding:12px 14px; min-width:140px;">Status</th>
                                ${columns.filter(c => !['col_activity', 'col_channel', 'col_target', 'col_week', 'col_assignee', 'col_status', 'col_notes'].includes(c.id)).map(c => `<th style="padding:12px 14px;">${escapeHtml(c.name)}</th>`).join('')}
                                <th style="width:100px; text-align:center; padding:12px 14px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.map((r, rIdx) => {
                                const st = r.col_status || 'Planned';
                                const ass = (r.col_assignee || '').trim() || 'Unassigned';
                                const isMe = (ass.toLowerCase() === currentUserName.toLowerCase());
                                const customCols = columns.filter(c => !['col_activity', 'col_channel', 'col_target', 'col_week', 'col_assignee', 'col_status', 'col_notes'].includes(c.id));
                                const mGrad = getMemberGradient(ass);
                                const mInit = getMemberInitials(ass);

                                return `
                                    <tr style="border-bottom:1px solid #f1f5f9; transition:background 0.15s ease;" onmouseover="this.style.background='#fafbfc';" onmouseout="this.style.background='#ffffff';">
                                        <td style="text-align:center; font-weight:800; color:#94a3b8; font-size:12px; padding:12px 14px;">${rIdx + 1}</td>
                                        <td style="padding:12px 14px;">
                                            <div style="font-weight:700; color:#0f172a; font-size:13.5px; line-height:1.4;">
                                                ${escapeHtml(r.col_activity || '')}
                                            </div>
                                            ${r.col_notes ? `<div style="font-size:11.5px; color:#64748b; margin-top:3px; line-height:1.3;">${escapeHtml(r.col_notes)}</div>` : ''}
                                        </td>
                                        <td style="padding:12px 14px;">
                                            ${getCmpChannelBadgeHtml(r.col_channel)}
                                        </td>
                                        <td style="padding:12px 14px;">
                                            ${r.col_target ? `<span class="badge" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; font-size:11.5px; font-weight:800; padding:3px 10px; border-radius:999px; display:inline-flex; align-items:center; gap:4px;"><span>🎯</span> ${escapeHtml(r.col_target)}</span>` : '<span style="color:#cbd5e1; font-weight:600;">—</span>'}
                                        </td>
                                        <td style="padding:12px 14px;">
                                            ${r.col_week ? `<span class="badge" style="background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px; display:inline-flex; align-items:center; gap:4px;"><span>🗓️</span> ${escapeHtml(r.col_week)}</span>` : '<span style="color:#cbd5e1; font-weight:600;">—</span>'}
                                        </td>
                                        <td style="padding:12px 14px;">
                                            ${ass !== 'Unassigned' ? `
                                                <div style="display:inline-flex; align-items:center; gap:7px;">
                                                    <span style="width:24px; height:24px; border-radius:50%; background:${mGrad}; color:#ffffff; display:inline-flex; align-items:center; justify-content:center; font-size:10.5px; font-weight:800; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                                                        ${escapeHtml(mInit)}
                                                    </span>
                                                    <span style="font-weight:700; color:#0f172a; font-size:12.5px;">${escapeHtml(ass)}</span>
                                                    ${isMe ? `<span class="badge" style="background:#fef3c7; color:#b45309; font-size:9.5px; font-weight:800; padding:1px 5px; border-radius:4px; border:1px solid #fde68a;">⭐ You</span>` : ''}
                                                </div>
                                            ` : `
                                                <span class="badge" style="background:#fffbeb; color:#b45309; border:1px dashed #f59e0b; font-size:11px; font-weight:700; padding:3px 8px; border-radius:999px;">⚠️ Unassigned</span>
                                            `}
                                        </td>
                                        <td style="padding:12px 14px;">
                                            <select class="cmp-mini-status-select cmp-status-select" data-val="${escapeHtml(st)}" onchange="quickUpdateMonthlyDeliverableStatus(${cid}, '${escapeHtml(curMonth)}', '${r.id}', this.value); this.setAttribute('data-val', this.value);">
                                                <option value="Planned" ${st === 'Planned' ? 'selected' : ''}>⚪ Planned</option>
                                                <option value="In Progress" ${st === 'In Progress' ? 'selected' : ''}>🔵 In Progress</option>
                                                <option value="Under Review" ${st === 'Under Review' ? 'selected' : ''}>🟡 Under Review</option>
                                                <option value="Approved" ${st === 'Approved' ? 'selected' : ''}>🟢 Approved</option>
                                                <option value="Completed" ${st === 'Completed' ? 'selected' : ''}>✅ Completed</option>
                                                <option value="Delayed" ${st === 'Delayed' ? 'selected' : ''}>🔴 Delayed</option>
                                            </select>
                                        </td>
                                        ${customCols.map(c => `<td style="padding:12px 14px;"><span style="font-size:12.5px; color:#334155;">${escapeHtml(r[c.id] || '—')}</span></td>`).join('')}
                                        <td style="text-align:center; padding:12px 14px;">
                                            <button type="button" class="cmp-item-task-btn" onclick="createTaskFromMonthlyDeliverable(${cid}, ${JSON.stringify(r.col_activity || '')}, ${JSON.stringify(r.col_channel || '')}, ${JSON.stringify(r.col_target || '')}, ${JSON.stringify(ass === 'Unassigned' ? '' : ass)})" title="Convert deliverable to active project task">
                                                <span>↳ + Task</span>
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                    <div style="padding:10px 16px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                        <span style="font-size:12px; color:#64748b; font-weight:600;">
                            Showing <strong>${rows.length}</strong> Deliverables • <strong>${doneRows.length}</strong> Completed
                        </span>
                        <button type="button" class="btn btn-outline btn-sm" style="font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:6px; cursor:pointer;" onclick="openClientMonthlyPlanModal(${cid})">
                            + Add Deliverable / Customize Table &rarr;
                        </button>
                    </div>
                </div>
            `;
        }

        return `
            <div class="client-monthly-plan-section">
                <div class="cmp-section-header">
                    <div class="cmp-section-title-box">
                        <div class="cmp-section-icon">📅</div>
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <h4 class="cmp-section-title">${escapeHtml(plan.title || ('Monthly Strategy &amp; Deliverables Plan — ' + monthLabel))}</h4>
                                <span class="badge" style="background:#e0e7ff; color:#3730a3; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px;">
                                    🗓️ ${escapeHtml(monthLabel)}
                                </span>
                                <span class="badge" style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px;">
                                    ${escapeHtml(plan.status || 'Active')}
                                </span>
                                ${paidAmount > 0 ? `
                                    <span class="badge" style="background:#f0f9ff; color:#0369a1; border:1px solid #bae6fd; font-size:11px; font-weight:800; padding:3px 10px; border-radius:999px;">
                                        💵 Client Paid: ₹${Number(paidAmount).toLocaleString()}
                                    </span>
                                    ${totalAllocated > 0 ? `
                                        <span class="badge" style="background:#fdf2f8; color:#be185d; border:1px solid #fbcfe8; font-size:11px; font-weight:800; padding:3px 9px; border-radius:999px;">
                                            📊 Platform Spends: ₹${Number(totalAllocated).toLocaleString()}
                                        </span>
                                    ` : ''}
                                    <span class="badge" style="background:${currentRemaining >= 0 ? '#ecfdf5' : '#fef2f2'}; color:${currentRemaining >= 0 ? '#047857' : '#dc2626'}; border:1px solid ${currentRemaining >= 0 ? '#a7f3d0' : '#fecaca'}; font-size:11px; font-weight:800; padding:3px 10px; border-radius:999px;">
                                        ${currentRemaining >= 0 ? '✨ Current Amount: ₹' + Number(currentRemaining).toLocaleString() : '⚠️ Over-allocated: -₹' + Number(Math.abs(currentRemaining)).toLocaleString()}
                                    </span>
                                ` : ''}
                            </div>
                            ${platformAllocations.length > 0 ? `
                                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:6px;">
                                    <span style="font-size:11px; font-weight:700; color:#64748b;">Platform Allocations:</span>
                                    ${platformAllocations.map(pa => `
                                        <span class="badge" style="background:#ffffff; color:#1e293b; border:1px solid #e2e8f0; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; display:inline-flex; align-items:center; gap:4px; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
                                            <span>${getCmpPlatformIcon(pa.platform)}</span> ${escapeHtml(pa.platform)}: <strong>₹${Number(pa.amount || 0).toLocaleString()}</strong>
                                        </span>
                                    `).join('')}
                                </div>
                            ` : ''}
                            <div class="cmp-section-meta" style="margin-top:4px;">
                                <span style="font-size:12px; color:#64748b;">
                                    <strong>${totalDeliverables}</strong> Deliverables (${doneRows.length} Done • ${inProgRows.length} In Progress) — <strong>${overallPct}% Completed</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <!-- View switcher: Deliverables Table vs By Assigned Member -->
                        <div class="cmp-view-switcher">
                            <button type="button" class="cmp-view-tab-btn ${viewType === 'table' ? 'active' : ''}" onclick="switchClientPlanView(${cid}, 'table')">
                                📋 Deliverables Table
                            </button>
                            <button type="button" class="cmp-view-tab-btn ${viewType === 'members' ? 'active' : ''}" onclick="switchClientPlanView(${cid}, 'members')">
                                👥 By Assigned Member (${memberKeys.length})
                            </button>
                        </div>

                        <button type="button" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; font-weight:700; padding:6px 14px; border-radius:10px; display:inline-flex; align-items:center; gap:5px; cursor:pointer;" onclick="openClientMonthlyPlanModal(${cid})" title="Open full customizable table editor and strategic notes">
                            <span>✏️ Manage &amp; Custom Table</span>
                        </button>
                    </div>
                </div>

                <!-- Overall Progress Bar -->
                <div style="margin-bottom:14px;">
                    <div class="cmp-progress-bar-bg" style="height:7px;">
                        <div class="cmp-progress-bar-fill" style="width:${overallPct}%;"></div>
                    </div>
                </div>

                <!-- Body: Member cards or table -->
                ${bodyContent}
            </div>
        `;
    }

    function toggleClientGroup(clientId) {
        const cid = parseInt(clientId);
        const card = document.getElementById(`client_group_${cid}`);
        const body = document.getElementById(`client_body_${cid}`);
        if (!card || !body) return;

        if (expandedClientIds.has(cid)) {
            expandedClientIds.delete(cid);
            card.classList.remove('open');
            body.style.display = 'none';
        } else {
            expandedClientIds.add(cid);
            card.classList.add('open');
            body.style.display = 'block';
        }
    }

    function renderCurrentProjects(projects) {
        const grid = document.getElementById('currentProjectsGrid');
        if (!grid) return;

        if (!projects.length) {
            grid.innerHTML = `
                <div class="card" style="text-align:center; padding:52px 24px; border-radius:20px; border:1px dashed #cbd5e1;">
                    <div style="font-size:42px; margin-bottom:12px;">📁</div>
                    <h4 style="margin:0 0 6px 0; color:#0f172a; font-size:18px; font-weight:800;">No projects match the current filter</h4>
                    <p style="font-size:13.5px; color:#64748b; margin:0 0 18px 0;">Add projects, attach deliverables, or search across client portfolios.</p>
                    <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                        ${isLeadOrAdminUser ? `<button type="button" class="proj-act-btn proj-act-primary" onclick="openCreateProjectModal()">+ Add Project</button>` : ''}
                        ${isLeadOrAdminUser ? `<button type="button" class="proj-act-btn proj-act-neutral" onclick="openImportProjectsModal()">📥 Import Projects CSV</button>` : ''}
                    </div>
                </div>
            `;
            return;
        }

        // Group projects by Client (client_id / company_name)
        const clientGroups = {};
        projects.forEach(p => {
            const cid = parseInt(p.client_id || 0);
            const cname = p.company_name || 'General / Unassigned Client';
            if (!clientGroups[cid]) {
                clientGroups[cid] = {
                    client_id: cid,
                    client_name: cname,
                    projects: []
                };
            }
            clientGroups[cid].projects.push(p);
        });

        window.clientGroupsMap = clientGroups;

        // Default open all clients on first load if none set
        if (expandedClientIds.size === 0) {
            Object.keys(clientGroups).forEach(cid => expandedClientIds.add(parseInt(cid)));
        }

        const prioColors = {
            'Urgent': { bg: '#fee2e2', fg: '#b91c1c' },
            'High': { bg: '#fef3c7', fg: '#b45309' },
            'Medium': { bg: '#e0f2fe', fg: '#0369a1' },
            'Low': { bg: '#f1f5f9', fg: '#475569' }
        };

        const clientGradients = [
            'linear-gradient(135deg, #6366f1, #8b5cf6)',
            'linear-gradient(135deg, #3b82f6, #06b6d4)',
            'linear-gradient(135deg, #059669, #10b981)',
            'linear-gradient(135deg, #f59e0b, #d97706)',
            'linear-gradient(135deg, #ec4899, #8b5cf6)',
            'linear-gradient(135deg, #8b5cf6, #3b82f6)'
        ];

        let html = '';
        let clientIdx = 0;

        Object.values(clientGroups).forEach(cGroup => {
            const cid = cGroup.client_id;
            const cname = cGroup.client_name;
            const projs = cGroup.projects;
            const isOpen = expandedClientIds.has(cid);
            const initials = getClientInitials(cname);
            const avatarGrad = clientGradients[clientIdx % clientGradients.length];
            clientIdx++;

            // Client statistics across all projects
            let totalClientTasks = 0;
            let activeClientTasks = 0;
            let completedClientTasks = 0;

            projs.forEach(p => {
                const pTasks = p.tasks || [];
                totalClientTasks += pTasks.length > 0 ? pTasks.length : parseInt(p.total_tasks || 0);
                activeClientTasks += pTasks.length > 0 ? pTasks.filter(t => (t.status || '').toUpperCase() === 'IN PROGRESS').length : parseInt(p.in_progress_tasks || 0);
                completedClientTasks += pTasks.length > 0 ? pTasks.filter(t => (t.status || '').toUpperCase() === 'COMPLETED').length : parseInt(p.completed_tasks || 0);
            });

            html += `
                <div class="client-group-card ${isOpen ? 'open' : ''}" id="client_group_${cid}">
                    <!-- CLIENT HEADER: Prominently shows Client Name, Badges, Monthly Plan & Toggle -->
                    <div class="client-group-header" onclick="toggleClientGroup(${cid})" title="Click to expand/collapse ${escapeHtml(cname)} projects and tasks">
                        <div class="client-header-left">
                            <div class="client-avatar-badge" style="background:${avatarGrad};">
                                ${escapeHtml(initials)}
                            </div>
                            <div class="client-header-title-box">
                                <h3 class="client-name-title">
                                    <span>${escapeHtml(cname)}</span>
                                </h3>
                                <div class="client-header-meta">
                                    <span class="badge" style="background:#e0e7ff; color:#3730a3; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px;">
                                        📁 ${projs.length} Project${projs.length === 1 ? '' : 's'}
                                    </span>
                                    <span class="badge" style="background:#f3e8ff; color:#7e22ce; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px;">
                                        📋 ${totalClientTasks} Task${totalClientTasks === 1 ? '' : 's'} (${activeClientTasks} Active)
                                    </span>
                                    ${completedClientTasks > 0 ? `
                                        <span class="badge" style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px;">
                                            ✓ ${completedClientTasks} Done
                                        </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>

                        <div class="client-header-actions" onclick="event.stopPropagation();">
                            <button type="button" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; color:#ffffff; font-weight:700; padding:7px 15px; border-radius:12px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 3px 12px rgba(99,102,241,0.28); cursor:pointer;" onclick="openClientMonthlyPlanModal(${cid})" title="View &amp; Edit Monthly Deliverables and Strategy Plan for ${escapeHtml(cname)}">
                                <span>📅 Monthly Plan</span>
                            </button>
                            ${isLeadOrAdminUser ? `
                                <button type="button" class="btn btn-outline btn-sm" style="background:#ffffff; color:#334155; border-color:#cbd5e1; font-weight:600; padding:7px 13px; border-radius:12px; display:inline-flex; align-items:center; gap:5px; cursor:pointer;" onclick="openCreateProjectModal(${cid})" title="Add new project for ${escapeHtml(cname)}">
                                    <span>+ Add Project</span>
                                </button>
                            ` : ''}
                            <div class="client-chevron-icon" onclick="toggleClientGroup(${cid})" title="Toggle Details">
                                ▼
                            </div>
                        </div>
                    </div>

                    <!-- CLIENT BODY: Shows Projects and nested Tasks first, followed by Monthly Plan -->
                    <div class="client-group-body" id="client_body_${cid}" style="${isOpen ? 'display:block;' : 'display:none;'}">
            `;

            // Render each project under this client first
            projs.forEach(p => {
                const total = parseInt(p.total_tasks || 0);
                const done = parseInt(p.completed_tasks || 0);
                const inProg = parseInt(p.in_progress_tasks || 0);
                const pending = parseInt(p.pending_tasks || 0);
                const pct = total > 0 ? Math.round((done / total) * 100) : ((p.status === 'Completed') ? 100 : 0);
                const compCnt = parseInt(p.competitors_count || 0);
                const isCurr = parseInt(p.is_current ?? 1) === 1;
                const pr = prioColors[p.priority] || prioColors['Medium'];
                const canManageProj = p.can_manage_project || isLeadOrAdminUser;

                // Tasks list belonging to this project (sorted: IN PROGRESS first base)
                const projTasks = (p.tasks || []).slice();
                const pjsRank = {
                    'IN PROGRESS': 1,
                    'UNDER REVIEW': 2,
                    'PENDING LEAD APPROVAL': 3,
                    'SENT FOR CLIENT APPROVAL': 4,
                    'REVISION REQUIRED': 5,
                    'PENDING': 6,
                    'COMPLETED': 7
                };
                projTasks.sort((a, b) => {
                    const rA = pjsRank[(a.status || '').toUpperCase().trim()] || 8;
                    const rB = pjsRank[(b.status || '').toUpperCase().trim()] || 8;
                    if (rA !== rB) return rA - rB;
                    return (b.id || 0) - (a.id || 0);
                });

                html += `
                    <div class="client-project-item">
                        <div class="proj-item-header">
                            <div class="proj-card-body-split">
                                <!-- LEFT: Badges, Title, Team & Metadata -->
                                <div>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:10px;">
                                        <span class="badge" style="background:${isCurr ? '#dcfce7' : '#f1f5f9'}; color:${isCurr ? '#15803d' : '#475569'}; font-size:11px; font-weight:800; padding:4px 11px; border-radius:999px;">
                                            ${isCurr ? '● CURRENT' : '📁 ARCHIVED'}
                                        </span>
                                        ${p.department ? `<span class="badge" style="background:#e0e7ff; color:#3730a3; font-size:11px; font-weight:700; padding:4px 11px; border-radius:999px;">${escapeHtml(p.department)}</span>` : ''}
                                        <span class="badge" style="background:${pr.bg}; color:${pr.fg}; font-size:11px; font-weight:700; padding:4px 11px; border-radius:999px;">${escapeHtml(p.priority || 'High')}</span>
                                        <span class="badge" style="background:#f3e8ff; color:#7e22ce; font-size:11px; font-weight:700; padding:4px 11px; border-radius:999px;">
                                            🏆 ${compCnt} Competitor${compCnt === 1 ? '' : 's'}
                                        </span>
                                        ${parseInt(p.client_approved || 0) === 1 ? `<span class="badge" style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:4px 11px; border-radius:999px;">✓ Client Approved</span>` : ''}
                                    </div>

                                    <a href="project_details.php?id=${p.id}" style="display:block; font-size:18px; font-weight:800; color:#0f172a; text-decoration:none; margin-bottom:8px; line-height:1.3; letter-spacing:-0.3px;">
                                        ${escapeHtml(p.project_name)}
                                    </a>

                                    <div style="font-size:13px; color:#475569; margin-bottom:10px; display:flex; flex-wrap:wrap; gap:16px; align-items:center;">
                                        <span style="display:inline-flex; align-items:center; gap:5px;">
                                            <i data-feather="briefcase" style="width:14px; height:14px; color:#64748b;"></i>
                                            <strong style="color:#1e293b;">${escapeHtml(p.company_name || cname)}</strong>
                                        </span>
                                        <span style="display:inline-flex; align-items:center; gap:5px;">
                                            <i data-feather="user" style="width:14px; height:14px; color:#6366f1;"></i>
                                            Lead: <strong style="color:#1e293b;">${escapeHtml(p.team_lead_name || 'Unassigned')}</strong>
                                        </span>
                                    </div>

                                    ${(p.handlers && p.handlers.length) ? `
                                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; align-items:center;">
                                            <span style="font-size:12px; color:#475569; font-weight:600; display:inline-flex; align-items:center; gap:4px;">🛡️ Handlers:</span>
                                            ${p.handlers.map(h => `<span class="badge" style="background:#eef2ff; color:#4338ca; font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:999px;">${escapeHtml(h.full_name)}</span>`).join('')}
                                        </div>
                                    ` : ''}

                                    ${(p.working_employees && p.working_employees.length) ? `
                                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; align-items:center;">
                                            <span style="font-size:12px; color:#1d4ed8; font-weight:600;">👥 Working (${p.working_employees.length}):</span>
                                            ${p.working_employees.slice(0, 5).map(w => `<span class="badge" style="background:#dbeafe; color:#1e40af; font-size:11px; font-weight:700; padding:3px 10px; border-radius:999px;">${escapeHtml(w.full_name)}</span>`).join('')}
                                            ${p.working_employees.length > 5 ? `<span class="badge" style="background:#eff6ff; color:#1d4ed8; font-size:11px; padding:3px 9px; border-radius:999px;">+${p.working_employees.length - 5} more</span>` : ''}
                                        </div>
                                    ` : ''}

                                    ${(p.observers && p.observers.length) ? `
                                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:12px; align-items:center;">
                                            <span style="font-size:12px; color:#475569; font-weight:600;">👁️ Observers (${p.observers.length}):</span>
                                            ${p.observers.slice(0, 4).map(o => `<span class="badge" style="background:#f5f3ff; color:#6d28d9; border:1px solid #ede9fe; font-size:11px; font-weight:600; padding:3px 10px; border-radius:999px;">${escapeHtml(o.full_name)}${o.role_name ? ' (' + escapeHtml(o.role_name) + ')' : ''}</span>`).join('')}
                                            ${p.observers.length > 4 ? `<span class="badge" style="background:#f3e8ff; color:#6b21a8; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px;">+${p.observers.length - 4} more</span>` : ''}
                                        </div>
                                    ` : ''}

                                    <div style="display:flex; gap:10px; flex-wrap:wrap; font-size:12.5px; color:#334155;">
                                        <span style="background:#f8fafc; border:1px solid #e2e8f0; padding:5px 11px; border-radius:10px; display:inline-flex; align-items:center; gap:6px;">
                                            <i data-feather="calendar" style="width:13px; height:13px; color:#64748b;"></i>
                                            Due: <strong>${escapeHtml(p.deadline || 'N/A')}</strong>
                                        </span>
                                        <span style="background:#f5f3ff; border:1px solid #ede9fe; padding:5px 11px; border-radius:10px; display:inline-flex; align-items:center; gap:6px; color:#4c1d95;">
                                            <i data-feather="loader" style="width:13px; height:13px; color:#7c3aed;"></i>
                                            Status: <strong>${escapeHtml(p.status || 'In Progress')}</strong>
                                        </span>
                                    </div>
                                </div>

                                <!-- RIGHT: Project Progress Bar & 3 Mini Stat Boxes -->
                                <div class="proj-card-right">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                        <span style="font-size:14px; font-weight:800; color:#0f172a;">Project Progress</span>
                                        <span style="font-size:12.5px; font-weight:600; color:#64748b;">${done} of ${total} Tasks</span>
                                    </div>

                                    <div style="display:flex; align-items:center; gap:14px;">
                                        <div style="flex:1; height:10px; background:#ede9fe; border-radius:999px; overflow:hidden;">
                                            <div style="height:100%; width:${pct}%; background:linear-gradient(90deg, #5b46f6, #7c3aed); border-radius:999px; transition:width 0.35s ease;"></div>
                                        </div>
                                        <span style="font-size:15px; font-weight:800; color:#5b46f6; min-width:40px; text-align:right;">${pct}%</span>
                                    </div>

                                    <div class="proj-mini-stats" style="margin-top:12px;">
                                        <div class="proj-mini-stat-box">
                                            <div style="width:28px; height:28px; border-radius:8px; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:14px;">⏳</div>
                                            <div>
                                                <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.1;">${pending}</div>
                                                <div style="font-size:11px; color:#64748b; font-weight:600; margin-top:2px;">Pending</div>
                                            </div>
                                        </div>
                                        <div class="proj-mini-stat-box">
                                            <div style="width:28px; height:28px; border-radius:8px; background:#ecfdf5; color:#10b981; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:12px;">▶</div>
                                            <div>
                                                <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.1;">${inProg}</div>
                                                <div style="font-size:11px; color:#64748b; font-weight:600; margin-top:2px;">Active</div>
                                            </div>
                                        </div>
                                        <div class="proj-mini-stat-box stat-done">
                                            <div style="width:28px; height:28px; border-radius:50%; background:#dcfce7; color:#16a34a; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-weight:800; font-size:13px;">✓</div>
                                            <div>
                                                <div style="font-size:16px; font-weight:800; color:#0f172a; line-height:1.1;">${done}</div>
                                                <div style="font-size:11px; color:#15803d; font-weight:600; margin-top:2px;">Done</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- FOOTER ACTION ROW -->
                            <div class="proj-card-footer" style="margin-top:14px;">
                                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <a href="project_details.php?id=${p.id}" class="proj-act-btn proj-act-primary" style="padding:6px 14px; font-size:12px;">
                                        <i data-feather="grid" style="width:13px; height:13px;"></i>
                                        <span>Overview &amp; Competitors</span>
                                    </a>
                                    ${canManageProj ? `
                                        <button type="button" class="proj-act-btn proj-act-mint" style="padding:6px 12px; font-size:12px;" onclick='openCreateProjectTask(${p.id}, ${JSON.stringify(p.department || "")})'>
                                            <span>+ Task</span>
                                        </button>
                                        <button type="button" class="proj-act-btn proj-act-violet" style="padding:6px 12px; font-size:12px;" onclick='openCreateProjectTask(${p.id}, ${JSON.stringify(p.department || "")}, 0, true)'>
                                            <span>+ Sub-Task</span>
                                        </button>
                                    ` : ''}
                                    ${isLeadOrAdminUser ? `
                                        <button type="button" class="proj-act-btn proj-act-sky" style="padding:6px 12px; font-size:12px;" onclick='openAssignProjectHandlersModal(${p.id}, ${JSON.stringify(p.project_name || "")}, ${JSON.stringify(p.handler_ids || [])})' title="Select & Assign Project Handlers separately (Lead Only)">
                                            <i data-feather="users" style="width:13px; height:13px;"></i>
                                            <span>Handlers</span>
                                        </button>
                                    ` : ''}
                                </div>

                                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    ${isLeadOrAdminUser ? `
                                        <button type="button" class="proj-act-btn proj-act-neutral" style="padding:6px 12px; font-size:12px;" onclick="openEditProjectModal(${p.id})" title="Edit Project Overview & Details">
                                            <i data-feather="edit-2" style="width:13px; height:13px; color:#d97706;"></i>
                                            <span>Edit</span>
                                        </button>
                                    ` : ''}
                                    <button type="button" class="proj-act-btn proj-act-neutral" style="padding:6px 12px; font-size:12px;" onclick="viewProjectTasks(${p.id})" title="Filter Tasks Table by this Project">
                                        <i data-feather="clipboard" style="width:13px; height:13px; color:#64748b;"></i>
                                        <span>Tasks View</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- PROJECT SERVICES & TASKS WORKSPACE -->
                        <div class="proj-services-wrapper">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:14px; font-weight:800; color:#0f172a; display:inline-flex; align-items:center; gap:6px;">
                                        <span>🛠️ Project Services &amp; Workspaces</span>
                                    </span>
                                    <span class="badge" style="background:#e0e7ff; color:#3730a3; font-weight:700; font-size:11px; padding:3px 9px; border-radius:999px;">
                                        ${(p.services || []).length} Service${(p.services || []).length === 1 ? '' : 's'} Configured
                                    </span>
                                </div>
                                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                    ${canManageProj ? `
                                        <button type="button" class="btn btn-outline btn-sm" style="background:#ffffff; color:#4f46e5; border-color:#c7d2fe; font-weight:700; padding:5px 12px; border-radius:10px; display:inline-flex; align-items:center; gap:5px;" onclick='openAddServiceModal(${p.id}, ${JSON.stringify(p.project_name || "")})' title="Add or configure service for this project">
                                            <span>+ Add Service</span>
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg, #4f46e5, #7c3aed); border:none; color:#ffffff; font-weight:700; padding:5px 14px; border-radius:10px; display:inline-flex; align-items:center; gap:5px;" onclick='openCreateProjectTask(${p.id}, ${JSON.stringify(p.department || "")})'>
                                            <span>+ Add Task</span>
                                        </button>
                                    ` : ''}
                                </div>
                            </div>

                            ${(() => {
                                const projServices = p.services || [];
                                const mainTasks = projTasks.filter(t => !t.parent_task_id || parseInt(t.parent_task_id) === 0);
                                const allSubtasks = projTasks.filter(t => parseInt(t.parent_task_id || 0) > 0);
                                const subtasksByParent = {};
                                allSubtasks.forEach(st => {
                                    const pid = parseInt(st.parent_task_id);
                                    if (!subtasksByParent[pid]) subtasksByParent[pid] = [];
                                    subtasksByParent[pid].push(st);
                                });

                                // Ensure subtasks without parent_task_id get linked to first main task
                                projTasks.forEach(t => {
                                    if (t.task_type === 'subtask' && (!t.parent_task_id || parseInt(t.parent_task_id) === 0)) {
                                        if (mainTasks.length > 0) {
                                            const firstId = mainTasks[0].id;
                                            if (!subtasksByParent[firstId]) subtasksByParent[firstId] = [];
                                            if (!subtasksByParent[firstId].some(x => x.id === t.id)) {
                                                subtasksByParent[firstId].push(t);
                                            }
                                        }
                                    }
                                });

                                let servicesHtml = '';
                                if (projServices.length > 0) {
                                    servicesHtml += `<div class="proj-services-list">`;
                                    projServices.forEach(srv => {
                                        const srvMainTasks = mainTasks.filter(t => {
                                            if (t.task_type === 'subtask') return false;
                                            const sId = parseInt(t.service_id || 0);
                                            if (sId > 0 && sId === parseInt(srv.id)) return true;
                                            if (t.service_name && t.service_name.trim().toLowerCase() === srv.service_name.trim().toLowerCase()) return true;
                                            return false;
                                        });
                                        const sIcon = getServiceIcon(srv.service_name);

                                        servicesHtml += `
                                            <div class="proj-service-card" id="proj_srv_${srv.id}">
                                                <div class="proj-service-header">
                                                    <div class="proj-service-title-box">
                                                        <div class="proj-service-icon">${sIcon}</div>
                                                        <div>
                                                            <div style="font-size:14px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                                                <span>${escapeHtml(srv.service_name)}</span>
                                                            </div>
                                                            <div style="display:flex; align-items:center; gap:6px; margin-top:4px; flex-wrap:wrap;">
                                                                <span style="font-size:11.5px; color:#475569; font-weight:600;">👥 Team (${srv.assignees ? srv.assignees.length : 0}):</span>
                                                                ${(srv.assignees && srv.assignees.length) ? srv.assignees.map(m => `
                                                                    <span class="badge" style="background:#e0e7ff; color:#3730a3; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px;">
                                                                        👤 ${escapeHtml(m.full_name)}
                                                                    </span>
                                                                `).join('') : '<span style="color:#94a3b8; font-size:11.5px; font-style:italic;">No team members assigned</span>'}
                                                                ${canManageProj ? `
                                                                    <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px; border-radius:6px; color:#4f46e5; border-color:#c7d2fe; font-weight:700; background:#ffffff;" onclick='openAssignServiceMembersModal(${srv.id}, ${JSON.stringify(srv.service_name)}, ${JSON.stringify(srv.assignee_ids || [])}, ${p.id})'>
                                                                        + Assign Members
                                                                    </button>
                                                                ` : ''}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div style="display:flex; align-items:center; gap:8px;">
                                                        <span class="badge" style="background:#f1f5f9; color:#475569; font-size:11px; font-weight:700;">
                                                            📋 ${srvMainTasks.length} Main Task${srvMainTasks.length === 1 ? '' : 's'}
                                                        </span>
                                                        ${canManageProj ? `
                                                            <button type="button" class="btn btn-outline btn-sm" style="padding:4px 10px; font-size:11.5px; border-radius:8px; color:#4f46e5; border-color:#c7d2fe; font-weight:700; background:#ffffff;" onclick='openCreateProjectTask(${p.id}, ${JSON.stringify(p.department || "")}, 0, false, ${srv.id})'>
                                                                + Add Task
                                                            </button>
                                                            <button type="button" class="btn btn-outline btn-sm" style="padding:4px 8px; font-size:11px; color:#dc2626; border-color:#fecaca;" onclick="deleteProjectService(${srv.id}, ${p.id})" title="Remove Service from Project">
                                                                ✕
                                                            </button>
                                                        ` : ''}
                                                    </div>
                                                </div>

                                                <div style="padding:12px 16px;">
                                                    ${renderProjectTasksTableHtml(srvMainTasks, subtasksByParent, p.id, p.department, srv.id, canManageProj)}
                                                </div>
                                            </div>
                                        `;
                                    });
                                    servicesHtml += `</div>`;
                                }

                                const generalMainTasks = mainTasks.filter(t => {
                                    if (t.task_type === 'subtask') return false;
                                    const sId = parseInt(t.service_id || 0);
                                    const matched = projServices.some(s => parseInt(s.id) === sId || (t.service_name && t.service_name.trim().toLowerCase() === s.service_name.trim().toLowerCase()));
                                    return !matched;
                                });

                                if (projServices.length === 0) {
                                    servicesHtml += `
                                        <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; padding:18px; margin-bottom:14px;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                                <div>
                                                    <strong style="color:#0f172a; font-size:13.5px; display:block;">Select services for this project to assign team members &amp; organize tasks:</strong>
                                                    <p style="margin:4px 0 0; color:#64748b; font-size:12px;">Click a common service preset to add it instantly:</p>
                                                </div>
                                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                                    <button type="button" class="btn btn-outline btn-sm" style="border-radius:20px; font-size:11.5px; font-weight:700;" onclick='quickAddProjectService(${p.id}, "SEO")'>🔍 + SEO</button>
                                                    <button type="button" class="btn btn-outline btn-sm" style="border-radius:20px; font-size:11.5px; font-weight:700;" onclick='quickAddProjectService(${p.id}, "Website Development")'>💻 + Web Dev</button>
                                                    <button type="button" class="btn btn-outline btn-sm" style="border-radius:20px; font-size:11.5px; font-weight:700;" onclick='quickAddProjectService(${p.id}, "Google Ads")'>📢 + Google Ads</button>
                                                    <button type="button" class="btn btn-outline btn-sm" style="border-radius:20px; font-size:11.5px; font-weight:700;" onclick='quickAddProjectService(${p.id}, "Social Media")'>📱 + Social Media</button>
                                                    <button type="button" class="btn btn-outline btn-sm" style="border-radius:20px; font-size:11.5px; font-weight:700;" onclick='openAddServiceModal(${p.id}, ${JSON.stringify(p.project_name || "")})'>+ Custom Service</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="proj-service-card">
                                            <div class="proj-service-header">
                                                <div class="proj-service-title-box">
                                                    <div class="proj-service-icon">📋</div>
                                                    <div>
                                                        <div style="font-size:14px; font-weight:800; color:#0f172a;">All Project Tasks</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="padding:12px 16px;">
                                                ${renderProjectTasksTableHtml(mainTasks, subtasksByParent, p.id, p.department, 0, canManageProj)}
                                            </div>
                                        </div>
                                    `;
                                } else if (generalMainTasks.length > 0) {
                                    servicesHtml += `
                                        <div class="proj-service-card" style="margin-top:14px;">
                                            <div class="proj-service-header">
                                                <div class="proj-service-title-box">
                                                    <div class="proj-service-icon">📦</div>
                                                    <div>
                                                        <div style="font-size:14px; font-weight:800; color:#0f172a;">General / Other Project Deliverables</div>
                                                    </div>
                                                </div>
                                                <span class="badge" style="background:#f1f5f9; color:#475569; font-size:11px; font-weight:700;">
                                                    ${generalMainTasks.length} Task${generalMainTasks.length === 1 ? '' : 's'}
                                                </span>
                                            </div>
                                            <div style="padding:12px 16px;">
                                                ${renderProjectTasksTableHtml(generalMainTasks, subtasksByParent, p.id, p.department, 0, canManageProj)}
                                            </div>
                                        </div>
                                    `;
                                }

                                return servicesHtml;
                            })()}
                        </div>
                    </div>
                `;
            });

            // Down below the projects: Render the Client Monthly Strategy & Deliverables Plan Section
            html += renderClientMonthlyPlanSection(cid, cname, projs);

            html += `
                    </div>
                </div>
            `;
        });

        grid.innerHTML = html;
        if (window.feather) feather.replace();
    }

    // =========================================================
    // CLIENT MONTHLY PLANNING (CUSTOM TABLE ENGINE)
    // =========================================================
    function openClientMonthlyPlanModal(clientId, clientName = '') {
        cmpCurrentClientId = parseInt(clientId) || 0;
        if (!clientName && window.clientGroupsMap && window.clientGroupsMap[cmpCurrentClientId]) {
            clientName = window.clientGroupsMap[cmpCurrentClientId].client_name || '';
        }
        if (!clientName) {
            const cardEl = document.getElementById('client_group_' + cmpCurrentClientId);
            if (cardEl) {
                const titleSpan = cardEl.querySelector('.client-name-title span');
                if (titleSpan) clientName = titleSpan.textContent.trim();
            }
        }
        cmpCurrentClientName = clientName || 'Client';

        const modalEl = document.getElementById('clientMonthlyPlanModal');
        if (!modalEl) {
            console.error('Modal #clientMonthlyPlanModal not found in DOM');
            return;
        }

        const nameHeader = document.getElementById('cmpModalClientName');
        if (nameHeader) {
            nameHeader.textContent = `${cmpCurrentClientName} — Monthly Strategy & Deliverables`;
        }
        const subtitle = document.getElementById('cmpModalSubtitle');
        if (subtitle) {
            subtitle.textContent = `Manage deliverables, platforms, custom columns, targets and execution roadmap for ${cmpCurrentClientName}`;
        }

        const monthEl = document.getElementById('cmp_month');
        if (monthEl && !monthEl.value) {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            monthEl.value = `${y}-${m}`;
        }

        // Show modal immediately
        modalEl.style.display = 'flex';

        const tbody = document.getElementById('cmpTableBody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:#64748b;">⏳ Loading monthly plan for ${escapeHtml(cmpCurrentClientName)}...</td></tr>`;
        }

        if (monthEl && monthEl.value) {
            loadClientMonthlyPlan(cmpCurrentClientId, monthEl.value);
        }
    }

    function onMonthlyPlanMonthChanged() {
        const monthVal = document.getElementById('cmp_month')?.value;
        if (cmpCurrentClientId > 0 && monthVal) {
            loadClientMonthlyPlan(cmpCurrentClientId, monthVal);
        }
    }

    function loadClientMonthlyPlan(clientId, month) {
        fetch(`api.php?action=get_client_monthly_plan&client_id=${clientId}&month=${encodeURIComponent(month)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Could not load monthly plan.');
                    return;
                }

                if (data.client && data.client.company_name) {
                    cmpCurrentClientName = data.client.company_name;
                    const mh = document.getElementById('cmpModalClientName');
                    if (mh) mh.textContent = `${cmpCurrentClientName} — Monthly Strategy & Deliverables`;
                }

                const plan = data.plan;
                const titleEl = document.getElementById('cmp_title');
                if (titleEl) titleEl.value = plan ? plan.title : `${cmpCurrentClientName} — Monthly Strategy & Deliverables Plan`;
                
                const valPaid = plan ? (plan.paid_amount !== undefined && plan.paid_amount !== null ? plan.paid_amount : (plan.budget || '')) : '';
                const paidEl = document.getElementById('cmp_paid_amount');
                if (paidEl) paidEl.value = valPaid;
                const budgetEl = document.getElementById('cmp_budget');
                if (budgetEl) budgetEl.value = valPaid;

                currentPlanPlatformAllocations = Array.isArray(plan?.platform_allocations) ? plan.platform_allocations : (Array.isArray(data.platform_allocations) ? data.platform_allocations : []);
                renderPlatformAllocations();
                calculateModalBalances();
                
                const statusEl = document.getElementById('cmp_status');
                if (statusEl) statusEl.value = plan ? (plan.status || 'Active') : 'Active';
                
                const notesEl = document.getElementById('cmp_notes');
                if (notesEl) notesEl.value = plan ? (plan.notes || '') : '';

                const lastUpEl = document.getElementById('cmpLastUpdatedInfo');
                if (lastUpEl) {
                    lastUpEl.textContent = plan && plan.updated_at ? `Last saved: ${plan.updated_at}` : 'New Month Plan (Unsaved)';
                }

                currentPlanColumns = data.columns || [];
                currentPlanRows = data.rows || [];
                cmpPreviousMonth = data.previous_month || '';

                const copyBtn = document.getElementById('cmpBtnCopyLastMonth');
                if (copyBtn) {
                    if (data.has_previous_month_plan && (!plan || !currentPlanRows.length)) {
                        copyBtn.style.display = 'inline-flex';
                        copyBtn.innerHTML = `<span>📋 Copy Last Month (${data.previous_month})</span>`;
                    } else {
                        copyBtn.style.display = 'none';
                    }
                }

                // If brand new plan with 0 rows, initialize with an empty row
                if (!currentPlanRows.length) {
                    currentPlanRows.push(createEmptyPlanRow());
                }

                renderMonthlyPlanTable();
            })
            .catch(err => {
                console.error('Plan load error:', err);
                alert('Network error while loading client monthly plan.');
            });
    }

    function createEmptyPlanRow() {
        const row = { id: 'row_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6) };
        currentPlanColumns.forEach(c => {
            row[c.id] = c.id === 'col_status' ? 'Planned' : '';
        });
        return row;
    }

    function switchModalPlanTab(tab) {
        currentModalPlanTab = tab;
        const tabTable = document.getElementById('cmpModalTabTable');
        const tabMembers = document.getElementById('cmpModalTabMembers');
        const viewTable = document.getElementById('cmpModalTableView');
        const viewMembers = document.getElementById('cmpModalMembersView');

        if (tab === 'members') {
            if (tabTable) tabTable.classList.remove('active');
            if (tabMembers) tabMembers.classList.add('active');
            if (viewTable) viewTable.style.display = 'none';
            if (viewMembers) viewMembers.style.display = 'block';
            scrapeModalTableInputsToMemory();
            renderModalMembersBreakdown();
        } else {
            if (tabTable) tabTable.classList.add('active');
            if (tabMembers) tabMembers.classList.remove('active');
            if (viewTable) viewTable.style.display = 'block';
            if (viewMembers) viewMembers.style.display = 'none';
            renderMonthlyPlanTable();
        }
    }

    function scrapeModalTableInputsToMemory() {
        const tableBody = document.getElementById('cmpTableBody');
        if (tableBody) {
            const rowsEls = tableBody.querySelectorAll('tr');
            rowsEls.forEach((tr, rIdx) => {
                if (currentPlanRows[rIdx]) {
                    const inputs = tr.querySelectorAll('.cmp-cell-input');
                    inputs.forEach((inp, cIdx) => {
                        const col = currentPlanColumns[cIdx];
                        if (col) {
                            currentPlanRows[rIdx][col.id] = inp.value;
                        }
                    });
                }
            });
        }
    }

    function populateModalAssigneeFilter() {
        const sel = document.getElementById('cmpModalFilterAssignee');
        if (!sel) return;
        const currentVal = sel.value;

        // Collect distinct assignees
        const set = new Set();
        currentPlanRows.forEach(r => {
            const ass = (r.col_assignee || '').trim();
            if (ass) set.add(ass);
        });

        let opts = `<option value="">Filter: All Members (${currentPlanRows.length})</option>`;
        if (currentUserName) {
            opts += `<option value="__MY_DELIVERABLES__" ${currentVal === '__MY_DELIVERABLES__' ? 'selected' : ''}>⭐ My Deliverables Only</option>`;
        }
        set.forEach(ass => {
            if (ass !== currentUserName) {
                opts += `<option value="${escapeHtml(ass)}" ${currentVal === ass ? 'selected' : ''}>${escapeHtml(ass)}</option>`;
            }
        });

        sel.innerHTML = opts;
    }

    function applyModalAssigneeFilter() {
        const sel = document.getElementById('cmpModalFilterAssignee');
        modalAssigneeFilterValue = sel ? sel.value : '';
        if (currentModalPlanTab === 'members') {
            renderModalMembersBreakdown();
        } else {
            renderMonthlyPlanTable();
        }
    }

    function renderModalMembersBreakdown() {
        const grid = document.getElementById('cmpModalMembersGrid');
        if (!grid) return;

        scrapeModalTableInputsToMemory();

        if (!currentPlanRows.length) {
            grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:32px; color:#94a3b8;">No deliverables created yet. Add deliverables in the Master Table view.</div>`;
            return;
        }

        // Group rows by assigned member
        const membersMap = {};
        currentPlanRows.forEach(r => {
            const rawAss = (r.col_assignee || '').trim();
            const assKey = rawAss || 'Unassigned';

            // Filter check
            if (modalAssigneeFilterValue === '__MY_DELIVERABLES__') {
                if (rawAss.toLowerCase() !== currentUserName.toLowerCase()) return;
            } else if (modalAssigneeFilterValue && rawAss.toLowerCase() !== modalAssigneeFilterValue.toLowerCase()) {
                return;
            }

            if (!membersMap[assKey]) {
                membersMap[assKey] = [];
            }
            membersMap[assKey].push(r);
        });

        const memberKeys = Object.keys(membersMap);
        if (!memberKeys.length) {
            grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:32px; color:#94a3b8;">No deliverables match the selected member filter.</div>`;
            return;
        }

        memberKeys.sort((a, b) => {
            const aIsMe = (a.toLowerCase() === currentUserName.toLowerCase());
            const bIsMe = (b.toLowerCase() === currentUserName.toLowerCase());
            if (aIsMe && !bIsMe) return -1;
            if (!aIsMe && bIsMe) return 1;
            if (a === 'Unassigned') return 1;
            if (b === 'Unassigned') return -1;
            return a.localeCompare(b);
        });

        let mHtml = '';
        memberKeys.forEach(mName => {
            const mRows = membersMap[mName];
            const isUnassigned = (mName === 'Unassigned');
            const isMe = (!isUnassigned && mName.toLowerCase() === currentUserName.toLowerCase());
            const mDone = mRows.filter(r => (r.col_status || '').toLowerCase() === 'completed').length;
            const mPct = mRows.length > 0 ? Math.round((mDone / mRows.length) * 100) : 0;
            const staffObj = (!isUnassigned && window.staffByNameMap) ? window.staffByNameMap[mName.toLowerCase()] : null;
            const roleOrDept = staffObj ? (staffObj.role_name || staffObj.department) : (isUnassigned ? 'No Team Member Assigned' : 'Team Member');
            const avatarGrad = getMemberGradient(mName);
            const initials = getMemberInitials(mName);

            mHtml += `
                <div class="cmp-member-card ${isMe ? 'is-current-user' : ''}" style="${isUnassigned ? 'border:1px dashed #f59e0b; background:#fffdfa;' : ''}">
                    <div class="cmp-member-card-header">
                        <div class="cmp-member-info">
                            <div class="cmp-member-avatar" style="background:${isUnassigned ? '#f59e0b' : avatarGrad};">
                                ${escapeHtml(initials)}
                            </div>
                            <div>
                                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                    <span class="cmp-member-name">${escapeHtml(mName)}</span>
                                    ${isMe ? `<span class="badge" style="background:#fef3c7; color:#b45309; font-weight:800; font-size:10px; border:1px solid #fde68a;">⭐ Assigned to You</span>` : ''}
                                </div>
                                <div class="cmp-member-role">${escapeHtml(roleOrDept)}</div>
                            </div>
                        </div>
                        <span class="badge" style="background:#eef2ff; color:#4338ca; font-weight:800; font-size:11px; padding:3px 9px; border-radius:999px;">
                            ${mRows.length} Deliverable${mRows.length === 1 ? '' : 's'}
                        </span>
                    </div>

                    <div class="cmp-member-progress">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:700; color:#64748b; margin-bottom:3px;">
                            <span>Progress</span>
                            <span style="color:#10b981;">${mDone}/${mRows.length} Done (${mPct}%)</span>
                        </div>
                        <div class="cmp-progress-bar-bg">
                            <div class="cmp-progress-bar-fill" style="width:${mPct}%;"></div>
                        </div>
                    </div>

                    <div class="cmp-deliverables-list">
                        ${mRows.map(r => {
                            const st = r.col_status || 'Planned';
                            const act = r.col_activity || 'Deliverable Item';
                            const ch = r.col_channel || '';
                            const tg = r.col_target || '';
                            const wk = r.col_week || '';

                            return `
                                <div class="cmp-deliverable-item">
                                    <div class="cmp-deliverable-title">${escapeHtml(act)}</div>
                                    <div class="cmp-deliverable-meta">
                                        ${ch ? `<span class="badge" style="background:#f1f5f9; color:#1e293b; font-size:10.5px; font-weight:600;">📱 ${escapeHtml(ch)}</span>` : ''}
                                        ${tg ? `<span class="badge" style="background:#ecfdf5; color:#047857; font-size:10.5px; font-weight:700;">🎯 ${escapeHtml(tg)}</span>` : ''}
                                        ${wk ? `<span class="badge" style="background:#f5f3ff; color:#6d28d9; font-size:10.5px; font-weight:600;">🗓️ ${escapeHtml(wk)}</span>` : ''}
                                    </div>
                                    <div class="cmp-deliverable-footer">
                                        <select class="cmp-mini-status-select cmp-status-select" data-val="${escapeHtml(st)}" onchange="onPlanRowCellChange('${r.id}', 'col_status', this.value); this.setAttribute('data-val', this.value); renderModalMembersBreakdown();">
                                            <option value="Planned" ${st === 'Planned' ? 'selected' : ''}>⚪ Planned</option>
                                            <option value="In Progress" ${st === 'In Progress' ? 'selected' : ''}>🔵 In Progress</option>
                                            <option value="Under Review" ${st === 'Under Review' ? 'selected' : ''}>🟡 Under Review</option>
                                            <option value="Approved" ${st === 'Approved' ? 'selected' : ''}>🟢 Approved</option>
                                            <option value="Completed" ${st === 'Completed' ? 'selected' : ''}>✅ Completed</option>
                                            <option value="Delayed" ${st === 'Delayed' ? 'selected' : ''}>🔴 Delayed</option>
                                        </select>
                                        <button type="button" class="cmp-item-task-btn" onclick="createTaskFromMonthlyDeliverable(cmpCurrentClientId, ${JSON.stringify(act)}, ${JSON.stringify(ch)}, ${JSON.stringify(tg)}, ${JSON.stringify(mName === 'Unassigned' ? '' : mName)})" title="Convert deliverable to active project task">
                                            <span>↳ + Task</span>
                                        </button>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        });

        grid.innerHTML = mHtml;
    }

    function renderMonthlyPlanTable() {
        const thead = document.getElementById('cmpTableHead');
        const tbody = document.getElementById('cmpTableBody');
        if (!thead || !tbody) return;

        populateModalAssigneeFilter();

        // Header with Custom Columns support
        let hHtml = '<tr><th style="width:40px; text-align:center;">#</th>';
        currentPlanColumns.forEach(c => {
            const canDelete = c.id !== 'col_activity' && c.id !== 'col_status';
            hHtml += `
                <th>
                    <div class="cmp-col-header-box">
                        <span>${escapeHtml(c.name)}</span>
                        ${canDelete ? `<button type="button" class="cmp-col-del-btn" onclick="removeMonthlyPlanColumn('${c.id}')" title="Remove this custom column">&times;</button>` : ''}
                    </div>
                </th>
            `;
        });
        hHtml += '<th style="width:60px; text-align:center;" class="no-print">Action</th></tr>';
        thead.innerHTML = hHtml;

        // Filter rows if an assignee filter is selected
        const displayRows = currentPlanRows.filter(r => {
            if (!modalAssigneeFilterValue) return true;
            const ass = (r.col_assignee || '').toLowerCase().trim();
            if (modalAssigneeFilterValue === '__MY_DELIVERABLES__') {
                return ass === currentUserName.toLowerCase().trim();
            }
            return ass === modalAssigneeFilterValue.toLowerCase().trim();
        });

        // Body with inline cell inputs
        if (!displayRows.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${currentPlanColumns.length + 2}" style="text-align:center; padding:32px; color:#94a3b8;">
                        ${modalAssigneeFilterValue ? 'No deliverables match the selected member filter.' : 'No deliverables planned yet. Click <strong>+ Add Deliverable Row</strong> above to begin!'}
                    </td>
                </tr>
            `;
            return;
        }

        let bHtml = '';
        displayRows.forEach((r, idx) => {
            bHtml += `<tr><td style="text-align:center; font-weight:700; color:#64748b; font-size:12px;">${idx + 1}</td>`;
            currentPlanColumns.forEach(c => {
                const val = r[c.id] ?? '';
                if (c.id === 'col_status') {
                    bHtml += `
                        <td>
                            <select class="cmp-cell-input cmp-status-select" data-val="${escapeHtml(val || 'Planned')}" onchange="onPlanRowCellChange('${r.id}', '${c.id}', this.value); this.setAttribute('data-val', this.value);">
                                <option value="Planned" ${val === 'Planned' ? 'selected' : ''}>⚪ Planned</option>
                                <option value="In Progress" ${val === 'In Progress' ? 'selected' : ''}>🔵 In Progress</option>
                                <option value="Under Review" ${val === 'Under Review' ? 'selected' : ''}>🟡 Under Review</option>
                                <option value="Approved" ${val === 'Approved' ? 'selected' : ''}>🟢 Approved</option>
                                <option value="Completed" ${val === 'Completed' ? 'selected' : ''}>✅ Completed</option>
                                <option value="Delayed" ${val === 'Delayed' ? 'selected' : ''}>🔴 Delayed</option>
                            </select>
                        </td>
                    `;
                } else if (c.id === 'col_assignee') {
                    bHtml += `
                        <td>
                            <input type="text" list="staffDatalist" class="cmp-cell-input" value="${escapeHtml(val)}" placeholder="Assign team member..." onchange="onPlanRowCellChange('${r.id}', '${c.id}', this.value); populateModalAssigneeFilter();">
                        </td>
                    `;
                } else {
                    bHtml += `
                        <td>
                            <input type="text" class="cmp-cell-input" value="${escapeHtml(val)}" placeholder="${escapeHtml(c.name)}..." onchange="onPlanRowCellChange('${r.id}', '${c.id}', this.value)">
                        </td>
                    `;
                }
            });
            bHtml += `
                <td style="text-align:center;" class="no-print">
                    <button type="button" class="btn btn-outline btn-sm cmp-col-del-btn" style="padding:4px 8px; color:#ef4444; border-color:#fecaca;" onclick="deleteMonthlyPlanRow('${r.id}')" title="Delete Row">
                        🗑️
                    </button>
                </td>
            </tr>`;
        });
        tbody.innerHTML = bHtml;
    }

    function onPlanRowCellChange(rowId, colId, value) {
        const row = currentPlanRows.find(r => r.id === rowId);
        if (row) {
            row[colId] = value;
        }
    }

    function addMonthlyPlanColumn() {
        const colName = prompt('Enter New Custom Column Name (e.g. Budget / Spend, Target KPI, Channel Format, Live URL, Designer):');
        if (!colName || !colName.trim()) return;
        const cleanName = colName.trim();
        const newColId = 'col_' + Date.now();
        currentPlanColumns.push({
            id: newColId,
            name: cleanName,
            type: 'text',
            required: false
        });
        currentPlanRows.forEach(r => {
            r[newColId] = '';
        });
        renderMonthlyPlanTable();
    }

    function removeMonthlyPlanColumn(colId) {
        const col = currentPlanColumns.find(c => c.id === colId);
        const colName = col ? col.name : colId;
        if (!confirm(`Are you sure you want to remove the custom column "${colName}"? Any data in this column will be removed.`)) return;
        currentPlanColumns = currentPlanColumns.filter(c => c.id !== colId);
        currentPlanRows.forEach(r => {
            delete r[colId];
        });
        renderMonthlyPlanTable();
    }

    function addMonthlyPlanRow() {
        currentPlanRows.push(createEmptyPlanRow());
        renderMonthlyPlanTable();
    }

    function deleteMonthlyPlanRow(rowId) {
        currentPlanRows = currentPlanRows.filter(r => r.id !== rowId);
        if (!currentPlanRows.length) {
            currentPlanRows.push(createEmptyPlanRow());
        }
        renderMonthlyPlanTable();
    }

    function copyPreviousMonthPlan() {
        if (!cmpCurrentClientId || !cmpPreviousMonth) return;
        const curMonth = document.getElementById('cmp_month').value;
        if (!confirm(`Copy deliverables and columns from ${cmpPreviousMonth} into ${curMonth}? Statuses will be reset to "Planned".`)) return;

        fetch('api.php?action=copy_previous_month_plan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                client_id: cmpCurrentClientId,
                target_month: curMonth,
                source_month: cmpPreviousMonth
            })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Could not copy previous month plan.');
                return;
            }
            alert('✅ Plan copied from previous month successfully!');
            loadClientMonthlyPlan(cmpCurrentClientId, curMonth);
            loadCurrentProjects();
        })
        .catch(() => alert('Network error copying plan.'));
    }

    function addPlatformAllocationRow(platform = '', amount = '', notes = '') {
        scrapePlatformAllocationsFromDom();
        currentPlanPlatformAllocations.push({
            id: 'pa_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
            platform: platform || 'Meta Ads',
            amount: amount || '',
            notes: notes || ''
        });
        renderPlatformAllocations();
        calculateModalBalances();
    }

    function removePlatformAllocationRow(rowId) {
        scrapePlatformAllocationsFromDom();
        currentPlanPlatformAllocations = currentPlanPlatformAllocations.filter(p => p.id !== rowId);
        renderPlatformAllocations();
        calculateModalBalances();
    }

    function scrapePlatformAllocationsFromDom() {
        const rows = document.querySelectorAll('.cmp-platform-alloc-row');
        const updated = [];
        rows.forEach(r => {
            const id = r.getAttribute('data-id') || ('pa_' + Math.random().toString(36).substring(2, 6));
            const pName = r.querySelector('.cmp-pa-name')?.value.trim() || '';
            const pAmt = parseFloat(r.querySelector('.cmp-pa-amount')?.value) || 0;
            const pNotes = r.querySelector('.cmp-pa-notes')?.value.trim() || '';
            if (pName || pAmt > 0 || pNotes) {
                updated.push({ id: id, platform: pName, amount: pAmt, notes: pNotes });
            }
        });
        if (rows.length > 0) {
            currentPlanPlatformAllocations = updated;
        }
    }

    function renderPlatformAllocations() {
        const container = document.getElementById('cmpPlatformAllocationsList');
        if (!container) return;

        if (!currentPlanPlatformAllocations || currentPlanPlatformAllocations.length === 0) {
            container.innerHTML = `
                <div style="font-size:12px; color:#94a3b8; font-style:italic; padding:6px 2px;">
                    No platforms added yet. Click "+ Add Platform" to specify allocations for Meta, FB, Google, etc.
                </div>
            `;
            return;
        }

        const presets = ['Meta Ads (FB & Insta)', 'Google Ads / PPC', 'LinkedIn Ads', 'YouTube Ads', 'Influencer Marketing', 'Twitter / X Ads', 'SEO & Website Tools'];

        let html = '';
        currentPlanPlatformAllocations.forEach((pa, idx) => {
            html += `
                <div class="cmp-platform-alloc-row" data-id="${escapeHtml(pa.id)}" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px;">
                    <div style="width:24px; text-align:center; font-weight:800; color:#94a3b8; font-size:12px;">
                        #${idx + 1}
                    </div>
                    <div style="flex:2; min-width:180px;">
                        <input type="text" class="form-control cmp-pa-name" style="height:34px; font-size:12.5px; font-weight:700;" value="${escapeHtml(pa.platform || '')}" placeholder="Platform (e.g. Meta, FB, Google Ads)" list="cmpPlatformPresets" oninput="calculateModalBalances()">
                    </div>
                    <div style="width:150px;">
                        <div style="position:relative;">
                            <span style="position:absolute; left:10px; top:8px; font-size:12px; font-weight:800; color:#64748b;">₹</span>
                            <input type="number" class="form-control cmp-pa-amount" style="height:34px; font-size:13px; font-weight:800; padding-left:24px; color:#0f172a;" value="${pa.amount !== undefined && pa.amount !== null && pa.amount !== '' ? pa.amount : ''}" placeholder="0.00" step="0.01" oninput="calculateModalBalances()">
                        </div>
                    </div>
                    <div style="flex:2; min-width:160px;">
                        <input type="text" class="form-control cmp-pa-notes" style="height:34px; font-size:12px;" value="${escapeHtml(pa.notes || '')}" placeholder="Campaign notes / Objective (optional)" oninput="calculateModalBalances()">
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" style="color:#ef4444; border-color:#fecaca; background:#ffffff; height:34px; width:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; font-size:18px; line-height:1; cursor:pointer;" onclick="removePlatformAllocationRow('${escapeHtml(pa.id)}')" title="Remove this platform">
                        &times;
                    </button>
                </div>
            `;
        });

        html += `
            <datalist id="cmpPlatformPresets">
                ${presets.map(p => `<option value="${escapeHtml(p)}"></option>`).join('')}
            </datalist>
        `;

        container.innerHTML = html;
    }

    function calculateModalBalances() {
        scrapePlatformAllocationsFromDom();
        const paidEl = document.getElementById('cmp_paid_amount');
        const paidAmount = parseFloat(paidEl ? paidEl.value : 0) || 0;

        let totalAllocated = 0;
        currentPlanPlatformAllocations.forEach(pa => {
            totalAllocated += parseFloat(pa.amount) || 0;
        });

        const currentRemaining = paidAmount - totalAllocated;

        const paidSumEl = document.getElementById('cmpModalPaidSummary');
        if (paidSumEl) paidSumEl.textContent = '₹' + Math.round(paidAmount).toLocaleString();

        const allocSumEl = document.getElementById('cmpModalAllocatedSummary');
        if (allocSumEl) allocSumEl.textContent = '₹' + Math.round(totalAllocated).toLocaleString();

        const balSumEl = document.getElementById('cmpModalBalanceSummary');
        const balBox = document.getElementById('cmpModalBalanceBox');
        const hintEl = document.getElementById('cmpPlatformBalanceHint');

        if (balSumEl) {
            balSumEl.textContent = '₹' + Math.round(currentRemaining).toLocaleString();
        }

        if (balBox) {
            if (currentRemaining < 0) {
                balBox.style.background = '#fef2f2';
                balBox.style.borderColor = '#fecaca';
                if (balSumEl) balSumEl.style.color = '#dc2626';
                if (hintEl) {
                    hintEl.textContent = `⚠️ Warning: Spends exceed client paid amount by ₹${Math.abs(Math.round(currentRemaining)).toLocaleString()}`;
                    hintEl.style.color = '#dc2626';
                }
            } else if (currentRemaining === 0 && paidAmount > 0) {
                balBox.style.background = '#fefce8';
                balBox.style.borderColor = '#fef08a';
                if (balSumEl) balSumEl.style.color = '#ca8a04';
                if (hintEl) {
                    hintEl.textContent = '✓ 100% of Client Paid Amount is fully allocated.';
                    hintEl.style.color = '#15803d';
                }
            } else {
                balBox.style.background = '#ecfdf5';
                balBox.style.borderColor = '#a7f3d0';
                if (balSumEl) balSumEl.style.color = '#047857';
                if (hintEl) {
                    hintEl.textContent = `Current available balance: ₹${Math.round(currentRemaining).toLocaleString()}`;
                    hintEl.style.color = '#64748b';
                }
            }
        }
    }

    function saveClientMonthlyPlan() {
        if (!cmpCurrentClientId) {
            alert('No client selected.');
            return;
        }
        const month = document.getElementById('cmp_month').value;
        if (!month) {
            alert('Please choose a month for this plan.');
            return;
        }

        // Scrape active DOM cell values
        scrapeModalTableInputsToMemory();
        scrapePlatformAllocationsFromDom();

        const paidAmount = parseFloat(document.getElementById('cmp_paid_amount')?.value) || 0;

        const payload = {
            client_id: cmpCurrentClientId,
            month: month,
            title: document.getElementById('cmp_title').value.trim() || `${cmpCurrentClientName} — Monthly Strategy & Deliverables Plan`,
            paid_amount: paidAmount,
            budget: paidAmount,
            platform_allocations: currentPlanPlatformAllocations,
            status: document.getElementById('cmp_status').value,
            notes: document.getElementById('cmp_notes').value.trim(),
            columns: currentPlanColumns,
            rows: currentPlanRows
        };

        fetch('api.php?action=save_client_monthly_plan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Save Error: ' + (data.message || 'Could not save plan.'));
                return;
            }
            alert('🎉 ' + data.message);
            loadClientMonthlyPlan(cmpCurrentClientId, month);
            loadCurrentProjects();
        })
        .catch(() => alert('Network error while saving monthly plan.'));
    }

    function viewProjectTasks(projectId) {
        const fp = document.getElementById('filterProject');
        if (fp) fp.value = String(projectId);
        switchWorkspaceView('tasks', false);
        loadTasks();
    }

    function openCreateProjectTask(projectId, departmentName = '', parentTaskId = 0, forceSubtask = false, serviceId = 0) {
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
        openCreateTaskModal(parentTaskId, forceSubtask, serviceId);
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
                if (data.project_completed) {
                    alert('🎉 ' + data.message);
                }
                loadMetrics();
                loadTasks();
                loadCurrentProjects();
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
            const st = (t.status || '').toUpperCase().trim();
            if (st === 'PENDING') {
                cols.PENDING.items.push(t);
            } else if (st === 'IN PROGRESS') {
                cols['IN PROGRESS'].items.push(t);
            } else if (['UNDER REVIEW', 'PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL'].includes(st)) {
                cols['UNDER REVIEW'].items.push(t);
            } else if (st === 'REVISION REQUIRED' || st === 'CLIENT REVISION REQUESTED') {
                cols.REVISION.items.push(t);
            }
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
                    const st = (t.status || '').toUpperCase().trim();

                    let statusPill = '';
                    if (st === 'PENDING LEAD APPROVAL') {
                        statusPill = '<span class="badge" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; font-size:10.5px; font-weight:700; padding:3px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">⏳ Handler Approved • Pending Lead Approval</span>';
                    } else if (st === 'SENT FOR CLIENT APPROVAL') {
                        statusPill = '<span class="badge" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; font-size:10.5px; font-weight:700; padding:3px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">🌐 Sent for Client Approval</span>';
                    } else if (st === 'LEAD APPROVED') {
                        statusPill = '<span class="badge" style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; font-size:10.5px; font-weight:700; padding:3px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">✓ Lead Approved • Awaiting Client Tick</span>';
                    } else if (st === 'UNDER REVIEW') {
                        statusPill = '<span class="badge" style="background:#fffbeb; color:#92400e; border:1px solid #fef3c7; font-size:10.5px; font-weight:700; padding:3px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">🔍 In Review (Handler Approval Pending)</span>';
                    }

                    html += `
                        <div style="background:white; border:1px solid #e2e8f0; border-radius:10px; padding:14px; box-shadow:0 1px 4px rgba(0,0,0,0.05); display:flex; flex-direction:column; gap:6px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:6px;">
                                <strong style="font-size:12px; color:#2563eb;">${t.task_code}</strong>
                                <div style="display:flex; gap:4px; align-items:center;">
                                    ${(t.is_subtask || t.task_type === 'subtask') ? `<span class="badge" style="background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; font-size:9.5px;">SUB-TASK</span>` : ''}
                                    <span class="badge ${slaClass}" style="font-size:10px;">${t.sla_status}</span>
                                </div>
                            </div>
                            <h5 style="margin:4px 0 2px 0; font-size:14px; color:#0f172a; font-weight:700;">${escapeHtml(t.title)}</h5>
                            <p style="font-size:12px; color:#64748b; margin:0;">Project: ${escapeHtml(t.project_name)}${t.department ? ` &bull; <span style="color:#2563eb; font-weight:600;">📁 ${escapeHtml(t.department)}</span>` : ''}</p>
                            ${statusPill ? `<div style="margin-top:4px;">${statusPill}</div>` : ''}

                            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center; justify-content:space-between; margin-top:8px; padding-top:8px; border-top:1px solid #f1f5f9;">
                                <a href="task_details.php?id=${t.id}" class="btn btn-outline btn-sm" style="font-size:11px;">View Details</a>
                                ${st === 'PENDING' ? `
                                    <button class="btn btn-outline btn-sm" style="font-size:11px; color:#0284c7; border-color:#93c5fd;" onclick="updateTaskStatusDirect(${t.id}, 'IN PROGRESS')">⚡ Start</button>
                                ` : ''}
                                ${st === 'IN PROGRESS' || st === 'REVISION REQUIRED' || st === 'CLIENT REVISION REQUESTED' ? `
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

        // Sort tasks: IN PROGRESS shown first base, then active review/pending, COMPLETED last
        const pjsStatusRank = {
            'IN PROGRESS': 1,
            'UNDER REVIEW': 2,
            'PENDING LEAD APPROVAL': 3,
            'SENT FOR CLIENT APPROVAL': 4,
            'REVISION REQUIRED': 5,
            'PENDING': 6,
            'COMPLETED': 7
        };
        tasks.sort((a, b) => {
            const rA = pjsStatusRank[(a.status || '').toUpperCase().trim()] || 8;
            const rB = pjsStatusRank[(b.status || '').toUpperCase().trim()] || 8;
            if (rA !== rB) return rA - rB;
            return (b.id || 0) - (a.id || 0);
        });

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
                            ` : `
                                ${t.status === 'PENDING LEAD APPROVAL' ? '<option value="PENDING LEAD APPROVAL" selected>📝 Pending Lead Approval</option>' : ''}
                                ${t.status === 'REVISION REQUIRED' ? '<option value="REVISION REQUIRED" selected>🔄 Revision Needed</option>' : ''}
                                ${t.status === 'SENT FOR CLIENT APPROVAL' ? '<option value="SENT FOR CLIENT APPROVAL" selected>📩 Client Approval</option>' : ''}
                                <option value="COMPLETED" ${t.status === 'COMPLETED' ? 'selected' : ''}>✅ Completed</option>
                            `}
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
    function openCreateTaskModal(parentTaskId = 0, forceSubtask = false, serviceId = 0) {
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
        loadCreateModalServices(serviceId);

        const assignSelect = document.getElementById('ct_assigned_to');
        const primId = assignSelect ? parseInt(assignSelect.value) || 0 : 0;
        createSelectedMembers = primId > 0 ? [primId] : [];
        createSelectedObservers = [];

        renderCreatePills();
        renderCreateObserverPills();
        document.getElementById('createTaskModal').style.display = 'flex';
    }

    function loadCreateModalServices(preselectServiceId = 0) {
        const projId = parseInt(document.getElementById('ct_project_id')?.value || '0') || 0;
        const sel = document.getElementById('ct_service_id');
        if (!sel) return;
        sel.innerHTML = '<option value="0">-- General Project Deliverable (No Specific Service) --</option>';
        if (projId <= 0) return;

        fetch(`api.php?action=get_project_services&project_id=${projId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.services)) return;
                data.services.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = String(s.id);
                    opt.textContent = `${getServiceIcon(s.service_name)} ${s.service_name}`;
                    if (parseInt(preselectServiceId) === parseInt(s.id)) opt.selected = true;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
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
            service_id: parseInt(document.getElementById('ct_service_id')?.value || '0') || 0,
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

    function openCreateProjectModal(prefillClientId = 0) {
        document.getElementById('cp_project_id').value = '';
        document.getElementById('cp_modal_title').textContent = 'Add Project (Overview & Competitors)';
        document.getElementById('cp_submit_btn').textContent = 'Save Project';
        document.getElementById('cp_comp_hint').textContent = 'Add key competitors to benchmark against for this project.';

        document.getElementById('cp_client_id').value = prefillClientId ? String(prefillClientId) : '';
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
                document.getElementById('cp_modal_title').textContent = 'Edit Project: ' + (p.project_name || '');
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
        const btn = document.querySelector('#reviewWorkModal button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Submitting...';
        }

        const payload = {
            task_id: document.getElementById('rw_task_id').value,
            review_action: document.getElementById('rw_action').value,
            review_comment: document.getElementById('rw_comment').value,
            required_changes: document.getElementById('rw_required_changes') ? document.getElementById('rw_required_changes').value : ''
        };

        fetch('api.php?action=review_task_work', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Submit Review';
            }
            if (data.success) {
                closeModal('reviewWorkModal');
                alert('✅ ' + data.message);
                loadMetrics();
                loadTasks();
            } else {
                alert('Error: ' + (data.message || 'Review submission failed.'));
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Submit Review';
            }
            console.error('Review submission error:', err);
            alert('Network or server error submitting review: ' + err.message);
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
