<?php
// clients.php - Client Management & Documents Repository

require_once __DIR__ . '/auth.php';
checkAuth('client-management');

$user = getCurrentUser();
$db = getDbConnection();

$pageBreadcrumb = "Module 2";
$pageTitle = "Client Management & Repository";
$pageSubtitle = "Manage clients, accounts, packages, projects and all related documents in one place.";
require_once __DIR__ . '/header.php';

$clients = $db->query("SELECT c.*, u.full_name as created_by_name,
                        usr.username as client_username,
                        (SELECT COUNT(*) FROM client_services cs WHERE cs.client_id = c.id) as service_count,
                        (SELECT COUNT(*) FROM documents d WHERE d.client_id = c.id) as document_count,
                        (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id) as project_count,
                        (SELECT COUNT(*) FROM tasks t WHERE t.client_id = c.id AND t.is_deleted = 0) as total_task_count,
                        (SELECT COUNT(*) FROM tasks t WHERE t.client_id = c.id AND t.status = 'COMPLETED' AND t.is_deleted = 0) as completed_task_count
                        FROM clients c
                        LEFT JOIN users u ON c.created_by = u.id
                        LEFT JOIN users usr ON usr.client_id = c.id
                        ORDER BY c.id DESC")->fetchAll();

$totalClients = count($clients);
$activeClients = 0;
foreach ($clients as $c) {
    if (stripos($c['status'] ?? '', 'Active') !== false || ($c['status'] ?? '') === 'Approved / Active') {
        $activeClients++;
    }
}
$totalPackages = (int)$db->query("SELECT COUNT(*) FROM client_services")->fetchColumn();
$totalDocuments = (int)$db->query("SELECT COUNT(*) FROM documents")->fetchColumn();

function getClientInitials($companyName, $contactPerson = '') {
    $src = trim($companyName);
    if (empty($src) && !empty($contactPerson)) {
        $src = trim($contactPerson);
    }
    $words = preg_split('/\s+/', $src);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    } elseif (!empty($src)) {
        return strtoupper(substr($src, 0, min(2, strlen($src))));
    }
    return 'CL';
}

$avatarPalettes = [
    ['bg' => '#f3e8ff', 'text' => '#7c3aed'], // Purple
    ['bg' => '#e0f2fe', 'text' => '#0284c7'], // Sky Blue
    ['bg' => '#ffedd5', 'text' => '#ea580c'], // Orange
    ['bg' => '#fce7f3', 'text' => '#db2777'], // Pink
    ['bg' => '#cffafe', 'text' => '#0891b2'], // Cyan
    ['bg' => '#ecfdf5', 'text' => '#059669'], // Emerald
];
?>

<style>
/* Scoped styles for Client Management & Repository matching reference design */
.cm-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 40px;
}

/* 4 KPI Stat Cards */
.cm-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
}
@media (max-width: 1100px) {
    .cm-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 600px) {
    .cm-kpi-grid {
        grid-template-columns: 1fr;
    }
}

.cm-kpi-card {
    background: #ffffff;
    border-radius: 18px;
    padding: 22px 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.025);
    border: 1px solid #f1f5f9;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 120px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.cm-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
}
.cm-kpi-card::after {
    content: "";
    position: absolute;
    right: -25px;
    bottom: -25px;
    width: 130px;
    height: 130px;
    border-radius: 50%;
    pointer-events: none;
    opacity: 0.9;
}
.cm-card-purple::after {
    background: radial-gradient(circle, rgba(243, 232, 255, 0.85) 0%, rgba(250, 245, 255, 0) 70%);
}
.cm-card-green::after {
    background: radial-gradient(circle, rgba(236, 253, 245, 0.85) 0%, rgba(240, 253, 250, 0) 70%);
}
.cm-card-orange::after {
    background: radial-gradient(circle, rgba(255, 247, 237, 0.9) 0%, rgba(255, 251, 235, 0) 70%);
}
.cm-card-blue::after {
    background: radial-gradient(circle, rgba(239, 246, 255, 0.85) 0%, rgba(248, 250, 252, 0) 70%);
}

.cm-kpi-top {
    display: flex;
    align-items: center;
    gap: 12px;
}
.cm-kpi-icon-box {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.cm-icon-purple {
    background: #f5f3ff;
    color: #7c3aed;
}
.cm-icon-green {
    background: #ecfdf5;
    color: #10b981;
}
.cm-icon-orange {
    background: #fff7ed;
    color: #f97316;
}
.cm-icon-blue {
    background: #eff6ff;
    color: #0284c7;
}
.cm-kpi-icon-box svg {
    width: 18px;
    height: 18px;
}
.cm-kpi-title {
    font-size: 14px;
    font-weight: 600;
    color: #475569;
}

.cm-kpi-bottom {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-top: 14px;
}
.cm-kpi-number {
    font-size: 30px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
    letter-spacing: -0.5px;
}
.cm-trend-pill {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 9px;
    border-radius: 999px;
    background: #ecfdf5;
    color: #10b981;
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1;
}

/* Action Buttons Row */
.cm-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.cm-bar-left, .cm-bar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.cm-btn-new-client {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #5b21b6 0%, #6d28d9 100%);
    color: #ffffff !important;
    border: none;
    padding: 10px 24px;
    border-radius: 999px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(91, 33, 182, 0.28);
    transition: all 0.2s ease;
    text-decoration: none;
}
.cm-btn-new-client:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(91, 33, 182, 0.38);
    color: #ffffff !important;
}
.cm-btn-pill-white {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    color: #1e293b !important;
    border: 1px solid #e2e8f0;
    padding: 9px 20px;
    border-radius: 999px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}
.cm-btn-pill-white:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}
.cm-btn-pill-white svg, .cm-btn-new-client svg {
    width: 16px;
    height: 16px;
}

/* Filter & Search Bar */
.cm-filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    padding: 8px 18px;
    border-radius: 999px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    gap: 16px;
    flex-wrap: wrap;
}
.cm-search-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 280px;
}
.cm-search-wrapper svg {
    width: 17px;
    height: 17px;
    color: #94a3b8;
    flex-shrink: 0;
}
.cm-search-input {
    border: none;
    outline: none;
    font-size: 13.5px;
    color: #1e293b;
    width: 100%;
    background: transparent;
}
.cm-search-input::placeholder {
    color: #94a3b8;
}
.cm-filter-dropdowns {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.cm-filter-select {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 7px 16px;
    font-size: 13px;
    font-weight: 500;
    color: #334155;
    outline: none;
    cursor: pointer;
    transition: all 0.15s ease;
}
.cm-filter-select:hover, .cm-filter-select:focus {
    border-color: #cbd5e1;
    background: #f8fafc;
}

/* Table Card */
.cm-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.025);
    overflow: hidden;
}
.cm-table-wrapper {
    overflow-x: auto;
    width: 100%;
}
.cm-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.cm-table th {
    background: #ffffff;
    color: #64748b;
    font-size: 12.5px;
    font-weight: 600;
    padding: 16px 20px;
    text-align: left;
    border-bottom: 1px solid #f1f5f9;
    white-space: nowrap;
}
.cm-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f8fafc;
    vertical-align: middle;
    font-size: 13.5px;
    color: #334155;
}
.cm-table tbody tr {
    transition: background-color 0.15s ease;
}
.cm-table tbody tr:hover {
    background: #fafafa;
}
.cm-table tbody tr:last-child td {
    border-bottom: none;
}

/* Checkbox */
.cm-checkbox-cell {
    width: 40px;
    padding-right: 0 !important;
}
.cm-custom-chk {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    cursor: pointer;
    accent-color: #6d28d9;
}

/* Client & Avatar */
.cm-client-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 210px;
}
.cm-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
    letter-spacing: -0.2px;
}
.cm-client-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.cm-company-link {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    text-decoration: none;
    transition: color 0.15s ease;
}
.cm-company-link:hover {
    color: #6d28d9;
}
.cm-contact-sub {
    font-size: 12px;
    color: #64748b;
}

/* Email & Phone */
.cm-meta-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12.5px;
    min-width: 180px;
}
.cm-meta-line {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #334155;
    white-space: nowrap;
}
.cm-meta-line svg {
    width: 13px;
    height: 13px;
    color: #94a3b8;
    flex-shrink: 0;
}

/* Portal Account Badge */
.cm-portal-pill {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    background: #ede9fe;
    color: #6d28d9;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.cm-portal-pill.no-account {
    background: #f1f5f9;
    color: #94a3b8;
}

/* Status Pill */
.cm-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.cm-status-pill.status-active {
    background: #ecfdf5;
    color: #059669;
}
.cm-status-pill.status-pending {
    background: #fffbeb;
    color: #d97706;
}
.cm-status-pill.status-suspended {
    background: #fef2f2;
    color: #dc2626;
}
.cm-status-dot {
    font-size: 10px;
    line-height: 1;
}

/* Progress */
.cm-progress-cell {
    min-width: 130px;
}
.cm-pct-text {
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 5px;
}
.cm-progress-track {
    background: #f1f5f9;
    height: 6px;
    border-radius: 999px;
    overflow: hidden;
    width: 100%;
}
.cm-progress-bar {
    background: linear-gradient(90deg, #6366f1, #4f46e5);
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}

/* Packages text */
.cm-package-label {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
    cursor: pointer;
}
.cm-package-label:hover {
    color: #6d28d9;
}

/* Documents soft pill */
.cm-doc-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    background: #f5f3ff;
    color: #7c3aed;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

/* Actions */
.cm-action-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.cm-action-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
    background: #ffffff;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    padding: 0;
}
.cm-action-icon-btn:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #cbd5e1;
}
.cm-action-icon-btn svg {
    width: 15px;
    height: 15px;
}

/* Dropdown Menu */
.cm-more-wrap {
    position: relative;
    display: inline-block;
}
.cm-more-dropdown {
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 4px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    padding: 6px;
    min-width: 200px;
    z-index: 1000;
    display: none;
}
.cm-more-dropdown.show {
    display: block;
}
.cm-dropdown-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    font-size: 12.5px;
    color: #334155;
    text-decoration: none;
    border-radius: 8px;
    cursor: pointer;
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
    transition: background 0.15s ease;
}
.cm-dropdown-link:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.cm-dropdown-link.danger-link {
    color: #ef4444;
}
.cm-dropdown-link.danger-link:hover {
    background: #fef2f2;
    color: #dc2626;
}
.cm-dropdown-link svg {
    width: 14px;
    height: 14px;
}

/* Pagination Footer */
.cm-pagination-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 22px;
    border-top: 1px solid #f1f5f9;
    font-size: 13px;
    color: #64748b;
    flex-wrap: wrap;
    gap: 12px;
}
.cm-page-buttons {
    display: flex;
    align-items: center;
    gap: 6px;
}
.cm-page-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.cm-page-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.cm-page-btn.active-page {
    background: #5b21b6;
    color: #ffffff;
    box-shadow: 0 3px 8px rgba(91, 33, 182, 0.3);
}
.cm-page-dots {
    padding: 0 4px;
    color: #94a3b8;
}
</style>

<div class="cm-container">

    <!-- 4 KPI Stat Cards -->
    <div class="cm-kpi-grid">
        <!-- Card 1: Total Clients -->
        <div class="cm-kpi-card cm-card-purple">
            <div class="cm-kpi-top">
                <div class="cm-kpi-icon-box cm-icon-purple">
                    <i data-feather="users"></i>
                </div>
                <span class="cm-kpi-title">Total Clients</span>
            </div>
            <div class="cm-kpi-bottom">
                <span class="cm-kpi-number"><?= $totalClients ?></span>
                <span class="cm-trend-pill">↑ +12%</span>
            </div>
        </div>

        <!-- Card 2: Active Clients -->
        <div class="cm-kpi-card cm-card-green">
            <div class="cm-kpi-top">
                <div class="cm-kpi-icon-box cm-icon-green">
                    <i data-feather="folder"></i>
                </div>
                <span class="cm-kpi-title">Active Clients</span>
            </div>
            <div class="cm-kpi-bottom">
                <span class="cm-kpi-number"><?= $activeClients ?></span>
                <span class="cm-trend-pill">↑ +8%</span>
            </div>
        </div>

        <!-- Card 3: Service Packages -->
        <div class="cm-kpi-card cm-card-orange">
            <div class="cm-kpi-top">
                <div class="cm-kpi-icon-box cm-icon-orange">
                    <i data-feather="package"></i>
                </div>
                <span class="cm-kpi-title">Service Packages</span>
            </div>
            <div class="cm-kpi-bottom">
                <span class="cm-kpi-number"><?= $totalPackages ?></span>
                <span class="cm-trend-pill">↑ +20%</span>
            </div>
        </div>

        <!-- Card 4: Documents -->
        <div class="cm-kpi-card cm-card-blue">
            <div class="cm-kpi-top">
                <div class="cm-kpi-icon-box cm-icon-blue">
                    <i data-feather="file-text"></i>
                </div>
                <span class="cm-kpi-title">Documents</span>
            </div>
            <div class="cm-kpi-bottom">
                <span class="cm-kpi-number"><?= $totalDocuments ?></span>
                <span class="cm-trend-pill">↑ +18%</span>
            </div>
        </div>
    </div>

    <!-- Action Buttons Row -->
    <div class="cm-action-bar">
        <div class="cm-bar-left">
            <button type="button" class="cm-btn-new-client" onclick="openAddClientModal()">
                <i data-feather="user-plus"></i>
                <span>+ New Client</span>
            </button>
            <button type="button" class="cm-btn-pill-white" onclick="openServicePackagesModal()">
                <i data-feather="package"></i>
                <span>Service Packages</span>
            </button>
            <a href="template_generator.php" class="cm-btn-pill-white">
                <i data-feather="file-plus"></i>
                <span>Generate Document</span>
            </a>
        </div>
        <div class="cm-bar-right">
            <button type="button" class="cm-btn-pill-white" onclick="openImportClientsModal()">
                <i data-feather="upload"></i>
                <span>Import Clients</span>
            </button>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="cm-filter-bar">
        <div class="cm-search-wrapper">
            <i data-feather="search"></i>
            <input type="text" id="cmSearchInput" class="cm-search-input" placeholder="Search client by name, email or account..." oninput="handleClientFilterChange()">
        </div>
        <div class="cm-filter-dropdowns">
            <select id="cmStatusFilter" class="cm-filter-select" onchange="handleClientFilterChange()">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="suspended">Suspended</option>
            </select>
            <select id="cmPackageFilter" class="cm-filter-select" onchange="handleClientFilterChange()">
                <option value="all">All Packages</option>
                <option value="with">With Packages</option>
                <option value="without">0 Packages</option>
            </select>
            <select id="cmTimeFilter" class="cm-filter-select" onchange="handleClientFilterChange()">
                <option value="all">📅 All Time</option>
                <option value="month">This Month</option>
                <option value="year">This Year</option>
            </select>
        </div>
    </div>

    <!-- Client Management Table Card -->
    <div class="cm-table-card">
        <div class="cm-table-wrapper">
            <table class="cm-table" id="cmClientTable">
                <thead>
                    <tr>
                        <th class="cm-checkbox-cell">
                            <input type="checkbox" id="cmMasterCheckbox" class="cm-custom-chk" onchange="toggleSelectAllRows(this)">
                        </th>
                        <th>Client / Contact Person</th>
                        <th>Email &amp; Phone</th>
                        <th>Portal Account</th>
                        <th>Status</th>
                        <th>Project Progress</th>
                        <th>Packages</th>
                        <th>Documents</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="cmClientTbody">
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding:35px 20px; color:#64748b;">
                                <i data-feather="users" style="width:36px; height:36px; stroke:#cbd5e1; margin-bottom:8px;"></i>
                                <div style="font-weight:600; font-size:14px; color:#334155;">No clients found</div>
                                <div style="font-size:12.5px; color:#94a3b8; margin-top:4px;">Click '+ New Client' above to create your first client.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $idx => $c): ?>
                            <?php 
                                $pct = $c['total_task_count'] > 0 ? round(($c['completed_task_count'] / $c['total_task_count']) * 100) : 0; 
                                $palette = $avatarPalettes[$idx % count($avatarPalettes)];
                                $initials = getClientInitials($c['company_name'], $c['contact_person']);

                                $st = strtolower($c['status'] ?? '');
                                $statusClass = 'status-active';
                                $statusLabel = 'Active';
                                if (strpos($st, 'pending') !== false) {
                                    $statusClass = 'status-pending';
                                    $statusLabel = 'Pending';
                                } elseif (strpos($st, 'suspend') !== false) {
                                    $statusClass = 'status-suspended';
                                    $statusLabel = 'Suspended';
                                }
                            ?>
                            <tr class="cm-row" 
                                data-client-id="<?= $c['id'] ?>"
                                data-company="<?= htmlspecialchars(strtolower($c['company_name'])) ?>"
                                data-contact="<?= htmlspecialchars(strtolower($c['contact_person'])) ?>"
                                data-email="<?= htmlspecialchars(strtolower($c['email'])) ?>"
                                data-username="<?= htmlspecialchars(strtolower($c['client_username'] ?? '')) ?>"
                                data-status="<?= $statusLabel ?>"
                                data-has-package="<?= $c['service_count'] > 0 ? 'yes' : 'no' ?>"
                                data-created="<?= htmlspecialchars($c['created_at'] ?? '') ?>">
                                <td class="cm-checkbox-cell">
                                    <input type="checkbox" class="cm-custom-chk cm-row-chk" value="<?= $c['id'] ?>">
                                </td>
                                <td>
                                    <div class="cm-client-cell">
                                        <div class="cm-avatar" style="background: <?= $palette['bg'] ?>; color: <?= $palette['text'] ?>;">
                                            <?= htmlspecialchars($initials) ?>
                                        </div>
                                        <div class="cm-client-info">
                                            <a href="client_details.php?id=<?= $c['id'] ?>" class="cm-company-link" title="Open Full Client Details">
                                                <?= htmlspecialchars($c['company_name']) ?>
                                            </a>
                                            <span class="cm-contact-sub"><?= htmlspecialchars($c['contact_person']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cm-meta-cell">
                                        <div class="cm-meta-line" title="Email">
                                            <i data-feather="mail"></i>
                                            <span><?= htmlspecialchars($c['email']) ?></span>
                                        </div>
                                        <div class="cm-meta-line" title="Phone">
                                            <i data-feather="phone"></i>
                                            <span><?= htmlspecialchars($c['phone'] ?: 'N/A') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($c['client_username'])): ?>
                                        <span class="cm-portal-pill">@<?= htmlspecialchars($c['client_username']) ?></span>
                                    <?php else: ?>
                                        <span class="cm-portal-pill no-account">No Account</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="cm-status-pill <?= $statusClass ?>">
                                        <span class="cm-status-dot">●</span> <?= $statusLabel ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="cm-progress-cell">
                                        <div class="cm-pct-text"><?= $pct ?>%</div>
                                        <div class="cm-progress-track">
                                            <div class="cm-progress-bar" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="cm-package-label" onclick="openServicePackagesModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'])) ?>')" title="Manage Client Packages">
                                        <?= (int)$c['service_count'] ?> Package<?= $c['service_count'] == 1 ? '' : 's' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cm-doc-badge">
                                        <?= (int)$c['document_count'] ?> Document<?= $c['document_count'] == 1 ? '' : 's' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="cm-action-group">
                                        <button type="button" class="cm-action-icon-btn" onclick="openClientQuickModal(<?= $c['id'] ?>)" title="Quick View Projects & Tasks">
                                            <i data-feather="eye"></i>
                                        </button>
                                        <button type="button" class="cm-action-icon-btn" onclick="openEditClientModal(<?= htmlspecialchars(json_encode($c)) ?>)" title="Edit Client Profile">
                                            <i data-feather="edit-2"></i>
                                        </button>
                                        <div class="cm-more-wrap">
                                            <button type="button" class="cm-action-icon-btn" onclick="toggleRowMenu(event, <?= $c['id'] ?>)" title="More Options">
                                                <i data-feather="more-horizontal"></i>
                                            </button>
                                            <div class="cm-more-dropdown" id="cmDropdown_<?= $c['id'] ?>">
                                                <a href="client_details.php?id=<?= $c['id'] ?>" class="cm-dropdown-link">
                                                    <i data-feather="bar-chart-2"></i> Client Progress &amp; Details
                                                </a>
                                                <button type="button" class="cm-dropdown-link" onclick="openServicePackagesModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'])) ?>'); closeAllDropdowns();">
                                                    <i data-feather="package"></i> Service Packages
                                                </button>
                                                <?php if (!empty($c['client_username'])): ?>
                                                    <button type="button" class="cm-dropdown-link" onclick="openResetPasswordModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'])) ?>', '<?= htmlspecialchars($c['client_username']) ?>'); closeAllDropdowns();">
                                                        <i data-feather="key"></i> Reset Portal Password
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($user['role'] === 'Super Admin' || ($user['role_id'] ?? 0) == 1): ?>
                                                    <button type="button" class="cm-dropdown-link danger-link" onclick="deleteClientAccount(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['company_name'])) ?>'); closeAllDropdowns();">
                                                        <i data-feather="trash-2"></i> Delete Client
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="cm-pagination-row">
            <div id="cmPaginationInfo">Showing 1 - <?= min(10, $totalClients) ?> of <?= $totalClients ?> clients</div>
            <div class="cm-page-buttons" id="cmPageButtons">
                <!-- Injected via JavaScript -->
            </div>
        </div>
    </div>

</div>

<!-- MODAL: ADD NEW CLIENT -->
<div id="addClientModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">👤 New Client &amp; Set Portal Credentials</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('addClientModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitCreateClient();">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Company Name *</label>
                        <input type="text" id="ac_company_name" class="form-control" required placeholder="e.g. Acme Tech Solutions">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Person *</label>
                        <input type="text" id="ac_contact_person" class="form-control" required placeholder="e.g. John Doe">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" id="ac_email" class="form-control" required placeholder="john@acme.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" id="ac_phone" class="form-control" placeholder="+91 98765 43210">
                    </div>
                </div>

                <!-- Custom Portal Login Credentials Section -->
                <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:14px; margin-top:10px;">
                    <strong style="color:#0369a1; font-size:13px; display:block; margin-bottom:8px;">🔑 Separate Portal Login Credentials for this Client:</strong>
                    <div class="grid-2">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Portal Username (Optional)</label>
                            <input type="text" id="ac_username" class="form-control" placeholder="e.g. acme_client">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Portal Password (Optional)</label>
                            <input type="text" id="ac_password" class="form-control" placeholder="e.g. AcmePass#2026">
                        </div>
                    </div>
                    <small style="color:#0284c7; display:block; margin-top:6px;">If left blank, a unique username (@client_company) and a unique random password (e.g. Client#8942) will be auto-generated.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addClientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Client Account</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT CLIENT -->
<div id="editClientModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Client Profile &amp; Details</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('editClientModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitUpdateClient();">
            <div class="modal-body">
                <input type="hidden" id="ec_client_id" value="0">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Company Name *</label>
                        <input type="text" id="ec_company_name" class="form-control" required placeholder="e.g. Acme Tech Solutions">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Person *</label>
                        <input type="text" id="ec_contact_person" class="form-control" required placeholder="e.g. John Doe">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" id="ec_email" class="form-control" required placeholder="john@acme.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" id="ec_phone" class="form-control" placeholder="+91 98765 43210">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tax ID / GSTIN</label>
                        <input type="text" id="ec_tax_id" class="form-control" placeholder="Tax ID">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select id="ec_status" class="form-select">
                            <option value="Approved / Active">Approved / Active</option>
                            <option value="Pending Approval">Pending Approval</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Client Notes &amp; Remarks</label>
                    <textarea id="ec_notes" class="form-control" rows="3" placeholder="Additional client notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editClientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Client Profile</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: CLIENT QUICK VIEW & PROJECT PROGRESS -->
<div id="clientQuickModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:850px; width:95%; max-height:90vh; display:flex; flex-direction:column;">
        <div class="modal-header" style="border-bottom:1px solid #e2e8f0; padding-bottom:14px;">
            <div>
                <h3 class="modal-title" id="cq_company_name" style="font-size:18px; font-weight:800; color:#0f172a;">Client Progress Overview</h3>
                <p id="cq_contact_info" style="margin:2px 0 0 0; font-size:13px; color:#64748b;">Loading client data...</p>
            </div>
            <button class="btn btn-outline btn-sm" onclick="closeModal('clientQuickModal')">&times;</button>
        </div>
        <div class="modal-body" style="overflow-y:auto; padding:20px;">
            <!-- Metrics Row -->
            <div class="grid-4" style="margin-bottom:20px;">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px;">
                    <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Projects</span>
                    <h3 id="cq_total_projects" style="font-size:22px; font-weight:800; color:#0f172a; margin-top:4px;">-</h3>
                </div>
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:12px;">
                    <span style="font-size:11px; font-weight:700; color:#1e40af; text-transform:uppercase;">Overall Progress</span>
                    <h3 id="cq_overall_progress" style="font-size:22px; font-weight:800; color:#2563eb; margin-top:4px;">-%</h3>
                </div>
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:12px;">
                    <span style="font-size:11px; font-weight:700; color:#166534; text-transform:uppercase;">Tasks Done</span>
                    <h3 id="cq_completed_tasks" style="font-size:22px; font-weight:800; color:#16a34a; margin-top:4px;">-</h3>
                </div>
                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:12px;">
                    <span style="font-size:11px; font-weight:700; color:#92400e; text-transform:uppercase;">In Progress</span>
                    <h3 id="cq_in_progress_tasks" style="font-size:22px; font-weight:800; color:#d97706; margin-top:4px;">-</h3>
                </div>
            </div>

            <!-- Projects Progress Section -->
            <h4 style="font-size:14px; font-weight:700; color:#0f172a; margin-bottom:10px;">📁 Projects &amp; Progress Breakdown</h4>
            <div id="cq_projects_container" style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
                <p style="color:var(--text-muted); font-size:13px;">Loading projects...</p>
            </div>

            <!-- Tasks Done & Deliverables Section -->
            <h4 style="font-size:14px; font-weight:700; color:#0f172a; margin-bottom:10px;">✓ Tasks &amp; Completed Deliverables</h4>
            <div class="table-responsive">
                <table class="table" style="font-size:12.5px;">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Task Title</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>SLA</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="cq_tasks_tbody">
                        <tr><td colspan="7" style="text-align:center; padding:16px;">Loading task deliverables...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid #e2e8f0; padding-top:12px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <a id="cq_live_portal_link" href="#" target="_blank" class="btn btn-outline btn-sm" style="color:#0284c7; border-color:#93c5fd;">
                    <i data-feather="external-link"></i> Live Client Portal View
                </a>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('clientQuickModal')">Close</button>
                <a id="cq_full_details_link" href="#" class="btn btn-primary btn-sm">
                    Open Full Client Details &amp; Progress Dashboard &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: MANAGE SERVICE PACKAGES -->
<div id="servicePackagesModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:850px; width:95%;">
        <div class="modal-header">
            <h3 class="modal-title">📦 Service Packages Directory &amp; Management</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('servicePackagesModal')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Filter & Action Controls -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <label style="font-weight:600; font-size:13px; color:var(--text-muted);">Client Filter:</label>
                    <select id="sp_filter_client" class="form-select" style="min-width:220px;" onchange="onSpClientFilterChange()">
                        <option value="0">-- All Clients Service Packages --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="showAddServicePackageForm()">
                    <i data-feather="plus"></i> + Add Service Package
                </button>
            </div>

            <!-- Form Card for Add/Edit -->
            <div id="sp_form_card" style="display:none; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:18px; margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h4 id="sp_form_title" style="margin:0; font-size:15px; color:#0f172a; font-weight:700;">+ Add New Service Package</h4>
                    <button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px;" onclick="hideServicePackageForm()">&times;</button>
                </div>
                <form onsubmit="event.preventDefault(); submitSaveServicePackage();">
                    <input type="hidden" id="sp_package_id" value="0">
                    
                    <div class="form-group">
                        <label class="form-label">Client Company *</label>
                        <select id="sp_client_id" class="form-select" required>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Service Name *</label>
                            <input type="text" id="sp_service_name" class="form-control" required placeholder="e.g. Website Development">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Package Name *</label>
                            <input type="text" id="sp_package_name" class="form-control" required placeholder="e.g. Corporate Web Suite">
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label class="form-label">Price *</label>
                            <input type="number" step="0.01" id="sp_price" class="form-control" required placeholder="150000">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Currency</label>
                            <select id="sp_currency" class="form-select">
                                <option value="INR">INR (₹)</option>
                                <option value="USD">USD ($)</option>
                                <option value="EUR">EUR (€)</option>
                                <option value="GBP">GBP (£)</option>
                                <option value="AED">AED</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Package Status</label>
                            <select id="sp_status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Paused">Paused</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Billing Terms</label>
                            <input type="text" id="sp_billing_terms" class="form-control" placeholder="e.g. 50% Advance, 50% on Completion">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Terms</label>
                            <input type="text" id="sp_payment_terms" class="form-control" placeholder="e.g. Net 15 days from Invoice">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="hideServicePackageForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Service Package</button>
                    </div>
                </form>
            </div>

            <!-- Table of Packages -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Package Name</th>
                            <th>Service Category</th>
                            <th>Price</th>
                            <th>Terms</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sp_table_body">
                        <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:20px;">Loading service packages...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: RESET CLIENT PASSWORD -->
<div id="resetPasswordModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">🔑 Reset Client Portal Password</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('resetPasswordModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitResetClientPassword();">
            <div class="modal-body">
                <input type="hidden" id="rp_client_id" value="0">
                <p style="font-size:13px; color:#475569; margin-bottom:12px;">
                    Setting new password for <strong id="rp_client_name">Client</strong> (<code id="rp_username">@username</code>)
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

<!-- MODAL: IMPORT CLIENTS (CSV / Paste) -->
<div id="importClientsModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:750px; width:95%; max-height:90vh; overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">📥 Bulk Import Clients (CSV / Excel Paste)</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('importClientsModal')">&times;</button>
        </div>
        <form onsubmit="event.preventDefault(); submitImportClients();">
            <div class="modal-body">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; margin-bottom:15px; font-size:12.5px; color:#475569;">
                    <strong style="color:#0f172a; display:block; margin-bottom:4px;">Supported Format (Tab or Comma Delimited):</strong>
                    <code>Company Name, Contact Person, Email, Phone, Status (Optional), Package Name (Optional)</code>
                    <div style="margin-top:6px; color:#64748b;">You can copy and paste rows directly from Excel or Google Sheets.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Paste Clients Data</label>
                    <textarea id="ic_paste_data" class="form-control" rows="6" placeholder="Hound Mobility, Marketing Head, marketing@houndmobility.com, +91 98950 22311, Active, Digital Growth Package
Aura Home Studio, Business Head, info@aurahomestudio.com, +91 98470 66322, Active, Branding Suite" oninput="parseImportClientsData()"></textarea>
                </div>

                <div id="ic_preview_box" style="display:none; margin-top:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-weight:700; font-size:13px; color:#0f172a;">Preview: <span id="ic_preview_count">0</span> Client(s) Ready</span>
                    </div>
                    <div class="table-responsive" style="max-height:180px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px;">
                        <table class="table" style="font-size:12px; margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Package</th>
                                </tr>
                            </thead>
                            <tbody id="ic_preview_tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('importClientsModal')">Cancel</button>
                <button type="submit" id="ic_submit_btn" class="btn btn-primary" disabled>Import Clients Now</button>
            </div>
        </form>
    </div>
</div>

<script>
let allClients = <?= json_encode($clients) ?>;
let loadedServices = [];
let parsedImportClients = [];

// Client-side pagination & filter state
let currentPage = 1;
const pageSize = 10;
let filteredRows = [];

function initClientTable() {
    filteredRows = Array.from(document.querySelectorAll('#cmClientTbody tr.cm-row'));
    renderPage(1);
}

function handleClientFilterChange() {
    const searchVal = (document.getElementById('cmSearchInput')?.value || '').toLowerCase().trim();
    const statusVal = document.getElementById('cmStatusFilter')?.value || 'all';
    const pkgVal = document.getElementById('cmPackageFilter')?.value || 'all';
    const timeVal = document.getElementById('cmTimeFilter')?.value || 'all';

    const allRows = Array.from(document.querySelectorAll('#cmClientTbody tr.cm-row'));
    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();

    filteredRows = allRows.filter(row => {
        const company = row.getAttribute('data-company') || '';
        const contact = row.getAttribute('data-contact') || '';
        const email = row.getAttribute('data-email') || '';
        const username = row.getAttribute('data-username') || '';
        const status = (row.getAttribute('data-status') || '').toLowerCase();
        const hasPkg = row.getAttribute('data-has-package') || 'no';
        const createdStr = row.getAttribute('data-created') || '';

        // Text search
        if (searchVal && !company.includes(searchVal) && !contact.includes(searchVal) && !email.includes(searchVal) && !username.includes(searchVal)) {
            return false;
        }

        // Status filter
        if (statusVal !== 'all' && status !== statusVal) {
            return false;
        }

        // Package filter
        if (pkgVal === 'with' && hasPkg !== 'yes') return false;
        if (pkgVal === 'without' && hasPkg === 'yes') return false;

        // Time filter
        if (timeVal !== 'all' && createdStr) {
            const dt = new Date(createdStr);
            if (!isNaN(dt.getTime())) {
                if (timeVal === 'month') {
                    if (dt.getMonth() !== currentMonth || dt.getFullYear() !== currentYear) return false;
                } else if (timeVal === 'year') {
                    if (dt.getFullYear() !== currentYear) return false;
                }
            }
        }

        return true;
    });

    renderPage(1);
}

function renderPage(page) {
    currentPage = page;
    const allRows = document.querySelectorAll('#cmClientTbody tr.cm-row');
    allRows.forEach(r => r.style.display = 'none');

    const total = filteredRows.length;
    const startIdx = (currentPage - 1) * pageSize;
    const endIdx = Math.min(startIdx + pageSize, total);

    for (let i = startIdx; i < endIdx; i++) {
        if (filteredRows[i]) {
            filteredRows[i].style.display = '';
        }
    }

    // Update pagination info
    const infoEl = document.getElementById('cmPaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.textContent = 'Showing 0 of 0 clients';
        } else {
            infoEl.textContent = `Showing ${startIdx + 1} - ${endIdx} of ${total} client${total === 1 ? '' : 's'}`;
        }
    }

    // Render pagination buttons
    renderPaginationButtons(total);
    if (window.feather) feather.replace();
}

function renderPaginationButtons(total) {
    const container = document.getElementById('cmPageButtons');
    if (!container) return;
    container.innerHTML = '';

    const totalPages = Math.ceil(total / pageSize) || 1;
    if (totalPages <= 1) return;

    // Prev button
    const prevBtn = document.createElement('button');
    prevBtn.className = 'cm-page-btn';
    prevBtn.innerHTML = '&lsaquo;';
    prevBtn.disabled = (currentPage === 1);
    prevBtn.onclick = () => { if (currentPage > 1) renderPage(currentPage - 1); };
    container.appendChild(prevBtn);

    // Page numbers
    for (let p = 1; p <= totalPages; p++) {
        if (p === 1 || p === totalPages || (p >= currentPage - 1 && p <= currentPage + 1)) {
            const btn = document.createElement('button');
            btn.className = 'cm-page-btn' + (p === currentPage ? ' active-page' : '');
            btn.textContent = p;
            btn.onclick = () => renderPage(p);
            container.appendChild(btn);
        } else if (p === currentPage - 2 || p === currentPage + 2) {
            const dots = document.createElement('span');
            dots.className = 'cm-page-dots';
            dots.textContent = '...';
            container.appendChild(dots);
        }
    }

    // Next button
    const nextBtn = document.createElement('button');
    nextBtn.className = 'cm-page-btn';
    nextBtn.innerHTML = '&rsaquo;';
    nextBtn.disabled = (currentPage === totalPages);
    nextBtn.onclick = () => { if (currentPage < totalPages) renderPage(currentPage + 1); };
    container.appendChild(nextBtn);
}

function toggleSelectAllRows(masterCheckbox) {
    const isChecked = masterCheckbox.checked;
    document.querySelectorAll('.cm-row-chk').forEach(chk => {
        if (chk.closest('tr').style.display !== 'none') {
            chk.checked = isChecked;
        }
    });
}

function toggleRowMenu(event, clientId) {
    event.stopPropagation();
    const targetDropdown = document.getElementById('cmDropdown_' + clientId);
    const wasOpen = targetDropdown.classList.contains('show');
    closeAllDropdowns();
    if (!wasOpen) {
        targetDropdown.classList.add('show');
    }
}

function closeAllDropdowns() {
    document.querySelectorAll('.cm-more-dropdown.show').forEach(d => d.classList.remove('show'));
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.cm-more-wrap')) {
        closeAllDropdowns();
    }
});

// --- BULK IMPORT CLIENTS FUNCTIONS ---
function openImportClientsModal() {
    document.getElementById('ic_paste_data').value = '';
    document.getElementById('ic_preview_box').style.display = 'none';
    document.getElementById('ic_submit_btn').disabled = true;
    document.getElementById('importClientsModal').style.display = 'flex';
}

function parseImportClientsData() {
    const raw = (document.getElementById('ic_paste_data').value || '').trim();
    const previewBox = document.getElementById('ic_preview_box');
    const previewCount = document.getElementById('ic_preview_count');
    const tbody = document.getElementById('ic_preview_tbody');
    const submitBtn = document.getElementById('ic_submit_btn');

    if (!raw) {
        previewBox.style.display = 'none';
        submitBtn.disabled = true;
        parsedImportClients = [];
        return;
    }

    const lines = raw.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
    parsedImportClients = [];

    lines.forEach((line, idx) => {
        // Detect tab or comma separated
        const delim = line.includes('\t') ? '\t' : ',';
        const cols = line.split(delim).map(c => c.trim().replace(/^["']|["']$/g, ''));
        if (cols.length >= 1 && cols[0].length > 0) {
            // Check if header row
            if (idx === 0 && (cols[0].toLowerCase().includes('company') || cols[0].toLowerCase().includes('client'))) {
                return;
            }
            parsedImportClients.push({
                company_name: cols[0] || '',
                contact_person: cols[1] || cols[0] || '',
                email: cols[2] || '',
                phone: cols[3] || '',
                status: cols[4] || 'Approved / Active',
                package_name: cols[5] || ''
            });
        }
    });

    if (parsedImportClients.length === 0) {
        previewBox.style.display = 'none';
        submitBtn.disabled = true;
        return;
    }

    previewCount.textContent = parsedImportClients.length;
    tbody.innerHTML = parsedImportClients.map((c, i) => `
        <tr>
            <td>${i + 1}</td>
            <td><strong>${escapeHtml(c.company_name)}</strong></td>
            <td>${escapeHtml(c.contact_person)}</td>
            <td>${escapeHtml(c.email || 'Auto-generated')}</td>
            <td>${escapeHtml(c.phone || '-')}</td>
            <td>${escapeHtml(c.package_name || '-')}</td>
        </tr>
    `).join('');

    previewBox.style.display = 'block';
    submitBtn.disabled = false;
}

function submitImportClients() {
    if (!parsedImportClients || parsedImportClients.length === 0) {
        alert('Please paste at least one client row.');
        return;
    }

    const btn = document.getElementById('ic_submit_btn');
    btn.disabled = true;
    btn.textContent = 'Importing Clients...';

    fetch('api.php?action=import_clients', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ clients: parsedImportClients })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Import Clients Now';
        if (data.success) {
            closeModal('importClientsModal');
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Import Error: ' + (data.message || 'Could not import clients.'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Import Clients Now';
        alert('Network error while importing clients.');
    });
}

// --- EXISTING CLIENT CRUD FUNCTIONS ---
function openAddClientModal() {
    document.getElementById('ac_company_name').value = '';
    document.getElementById('ac_contact_person').value = '';
    document.getElementById('ac_email').value = '';
    document.getElementById('ac_phone').value = '';
    if (document.getElementById('ac_username')) document.getElementById('ac_username').value = '';
    if (document.getElementById('ac_password')) document.getElementById('ac_password').value = '';
    document.getElementById('addClientModal').style.display = 'flex';
}

function submitCreateClient() {
    const payload = {
        company_name: document.getElementById('ac_company_name').value.trim(),
        contact_person: document.getElementById('ac_contact_person').value.trim(),
        email: document.getElementById('ac_email').value.trim(),
        phone: document.getElementById('ac_phone').value.trim(),
        username: document.getElementById('ac_username') ? document.getElementById('ac_username').value.trim() : '',
        password: document.getElementById('ac_password') ? document.getElementById('ac_password').value.trim() : ''
    };

    fetch('api.php?action=create_client', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('addClientModal');
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function openEditClientModal(client) {
    document.getElementById('ec_client_id').value = client.id;
    document.getElementById('ec_company_name').value = client.company_name || '';
    document.getElementById('ec_contact_person').value = client.contact_person || '';
    document.getElementById('ec_email').value = client.email || '';
    document.getElementById('ec_phone').value = client.phone || '';
    document.getElementById('ec_tax_id').value = client.tax_id || '';
    document.getElementById('ec_status').value = client.status || 'Approved / Active';
    document.getElementById('ec_notes').value = client.notes || '';
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

function deleteClientAccount(clientId, companyName) {
    if (!confirm(`Are you sure you want to permanently delete client '${companyName}'?\n\nThis will remove all linked portal data, client user accounts, and document records. This action cannot be undone.`)) {
        return;
    }

    fetch('api.php?action=delete_client', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client_id: clientId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// --- SERVICE PACKAGES MANAGE FUNCTIONS ---
function openServicePackagesModal(clientId = 0, companyName = '') {
    const filterSelect = document.getElementById('sp_filter_client');
    filterSelect.value = clientId;
    hideServicePackageForm();
    document.getElementById('servicePackagesModal').style.display = 'flex';
    loadServicePackages(clientId);
}

function loadServicePackages(clientId = 0) {
    fetch(`api.php?action=get_client_services&client_id=${clientId}`)
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadedServices = data.services;
            renderServicePackagesTable(loadedServices);
        }
    });
}

function renderServicePackagesTable(services) {
    const tbody = document.getElementById('sp_table_body');
    if (!services || services.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:20px;">No service packages found. Click '+ Add Service Package' above to create one!</td></tr>`;
        return;
    }
    tbody.innerHTML = services.map(s => `
        <tr>
            <td><strong>${escapeHtml(s.company_name || 'Client #' + s.client_id)}</strong></td>
            <td><strong>${escapeHtml(s.package_name)}</strong></td>
            <td><span class="badge badge-primary">${escapeHtml(s.service_name)}</span></td>
            <td><strong>${s.currency || 'INR'} ${parseFloat(s.price).toLocaleString()}</strong></td>
            <td>
                <small style="display:block; color:var(--text-muted);">${escapeHtml(s.billing_terms || 'Standard')}</small>
                <small style="display:block; color:var(--text-muted);">${escapeHtml(s.payment_terms || 'Net 15')}</small>
            </td>
            <td><span class="badge ${s.status === 'Active' ? 'badge-success' : 'badge-secondary'}">${escapeHtml(s.status || 'Active')}</span></td>
            <td>
                <div style="display:flex; gap:6px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="editServicePackage(${s.id})">Edit</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteServicePackage(${s.id}, '${escapeHtml(addslashes(s.package_name))}')">Delete</button>
                </div>
            </td>
        </tr>
    `).join('');
    if (window.feather) feather.replace();
}

function showAddServicePackageForm() {
    document.getElementById('sp_package_id').value = 0;
    const filterClientId = document.getElementById('sp_filter_client').value;
    document.getElementById('sp_client_id').value = filterClientId > 0 ? filterClientId : (allClients[0] ? allClients[0].id : 0);
    document.getElementById('sp_service_name').value = '';
    document.getElementById('sp_package_name').value = '';
    document.getElementById('sp_price').value = '';
    document.getElementById('sp_currency').value = 'INR';
    document.getElementById('sp_billing_terms').value = '50% Advance, 50% on Completion';
    document.getElementById('sp_payment_terms').value = 'Net 15 days';
    document.getElementById('sp_status').value = 'Active';
    document.getElementById('sp_form_title').textContent = '+ Add New Service Package';
    document.getElementById('sp_form_card').style.display = 'block';
}

function hideServicePackageForm() {
    document.getElementById('sp_form_card').style.display = 'none';
}

function editServicePackage(packageId) {
    const pkg = loadedServices.find(s => s.id == packageId);
    if (!pkg) return;
    document.getElementById('sp_package_id').value = pkg.id;
    document.getElementById('sp_client_id').value = pkg.client_id;
    document.getElementById('sp_service_name').value = pkg.service_name;
    document.getElementById('sp_package_name').value = pkg.package_name;
    document.getElementById('sp_price').value = pkg.price;
    document.getElementById('sp_currency').value = pkg.currency || 'INR';
    document.getElementById('sp_billing_terms').value = pkg.billing_terms || '';
    document.getElementById('sp_payment_terms').value = pkg.payment_terms || '';
    document.getElementById('sp_status').value = pkg.status || 'Active';
    document.getElementById('sp_form_title').textContent = '✏️ Edit Service Package #' + pkg.id;
    document.getElementById('sp_form_card').style.display = 'block';
}

function submitSaveServicePackage() {
    const pkgId = document.getElementById('sp_package_id').value;
    const payload = {
        id: pkgId,
        client_id: document.getElementById('sp_client_id').value,
        service_name: document.getElementById('sp_service_name').value,
        package_name: document.getElementById('sp_package_name').value,
        price: document.getElementById('sp_price').value,
        currency: document.getElementById('sp_currency').value,
        billing_terms: document.getElementById('sp_billing_terms').value,
        payment_terms: document.getElementById('sp_payment_terms').value,
        status: document.getElementById('sp_status').value
    };

    const action = pkgId > 0 ? 'update_service_package' : 'add_service_package';

    fetch(`api.php?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            hideServicePackageForm();
            alert('✅ ' + data.message);
            const currentFilter = document.getElementById('sp_filter_client').value;
            loadServicePackages(currentFilter);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteServicePackage(id, packageName) {
    if (!confirm(`Are you sure you want to delete service package '${packageName}'?`)) return;
    fetch('api.php?action=delete_service_package', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            const currentFilter = document.getElementById('sp_filter_client').value;
            loadServicePackages(currentFilter);
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function onSpClientFilterChange() {
    const clientId = document.getElementById('sp_filter_client').value;
    loadServicePackages(clientId);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function addslashes(str) {
    if (!str) return '';
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function openClientQuickModal(clientId) {
    document.getElementById('clientQuickModal').style.display = 'flex';
    document.getElementById('cq_company_name').textContent = 'Loading client overview...';
    document.getElementById('cq_contact_info').textContent = 'Fetching project progress and task status...';
    document.getElementById('cq_total_projects').textContent = '-';
    document.getElementById('cq_overall_progress').textContent = '-%';
    document.getElementById('cq_completed_tasks').textContent = '-';
    document.getElementById('cq_in_progress_tasks').textContent = '-';
    document.getElementById('cq_projects_container').innerHTML = '<p style="color:var(--text-muted); font-size:13px; text-align:center; padding:15px;">Loading projects & progress...</p>';
    document.getElementById('cq_tasks_tbody').innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:var(--text-muted);">Loading task deliverables...</td></tr>';
    document.getElementById('cq_full_details_link').href = `client_details.php?id=${clientId}`;
    document.getElementById('cq_live_portal_link').href = `client_details.php?id=${clientId}&preview_portal=1`;

    fetch(`api.php?action=get_client_full_details&client_id=${clientId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Error loading client details: ' + data.message);
                closeModal('clientQuickModal');
                return;
            }

            const c = data.client;
            const m = data.metrics;
            document.getElementById('cq_company_name').textContent = c.company_name;
            document.getElementById('cq_contact_info').textContent = `Contact: ${c.contact_person} | Email: ${c.email} | Phone: ${c.phone || 'N/A'}`;
            document.getElementById('cq_total_projects').textContent = m.total_projects;
            document.getElementById('cq_overall_progress').textContent = `${m.overall_progress_pct}%`;
            document.getElementById('cq_completed_tasks').textContent = `${m.completed_tasks} / ${m.total_tasks}`;
            document.getElementById('cq_in_progress_tasks').textContent = m.in_progress_tasks;

            // Render projects
            const projCont = document.getElementById('cq_projects_container');
            if (!data.projects || data.projects.length === 0) {
                projCont.innerHTML = '<p style="color:var(--text-muted); font-size:13px; padding:10px;">No projects created for this client yet.</p>';
            } else {
                let pHtml = '';
                data.projects.forEach(p => {
                    pHtml += `
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:14px; color:#0f172a;">${escapeHtml(p.project_name)}</strong>
                                <span class="badge ${p.status === 'Completed' ? 'badge-success' : 'badge-primary'}" style="font-size:11px;">${p.status}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:#334155; margin-top:8px; margin-bottom:4px;">
                                <span>Progress</span>
                                <span style="color:#2563eb;">${p.progress_pct}% (${p.completed_tasks}/${p.total_tasks} Tasks Done)</span>
                            </div>
                            <div style="background:#cbd5e1; height:6px; border-radius:3px; overflow:hidden;">
                                <div style="background:linear-gradient(90deg, #2563eb, #10b981); width:${p.progress_pct}%; height:100%; border-radius:3px;"></div>
                            </div>
                        </div>
                    `;
                });
                projCont.innerHTML = pHtml;
            }

            // Render tasks
            const tbody = document.getElementById('cq_tasks_tbody');
            if (!data.tasks || data.tasks.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:var(--text-muted);">No tasks found for this client.</td></tr>';
            } else {
                let tHtml = '';
                data.tasks.forEach(t => {
                    const isDone = (t.status === 'COMPLETED');
                    const slaClass = t.sla_status === 'SLA Breached' ? 'badge-danger' : (t.sla_status === 'Near SLA Warning' ? 'badge-warning' : 'badge-primary');
                    tHtml += `
                        <tr style="${isDone ? 'background:#f0fdf4;' : ''}">
                            <td><strong style="color:${isDone ? '#15803d' : '#2563eb'}; font-family:monospace;">${t.task_code}</strong></td>
                            <td>
                                <a href="task_details.php?id=${t.id}" target="_blank" style="font-weight:700; color:#0f172a; text-decoration:none;">${escapeHtml(t.title)}</a>
                            </td>
                            <td><small style="color:#475569;">${escapeHtml(t.project_name)}</small></td>
                            <td>
                                ${isDone 
                                    ? '<span class="badge badge-success" style="background:#dcfce7; color:#15803d; border:1px solid #86efac; font-weight:700; font-size:11px;">✓ DONE / COMPLETED</span>'
                                    : `<span class="badge ${t.status === 'IN PROGRESS' ? 'badge-primary' : 'badge-secondary'}" style="font-size:11px;">${t.status}</span>`
                                }
                            </td>
                            <td><small style="color:#334155;">${escapeHtml(t.assigned_to_name || 'Unassigned')}</small></td>
                            <td><span class="badge ${slaClass}" style="font-size:10px;">${t.sla_status}</span></td>
                            <td>
                                <a href="task_details.php?id=${t.id}" target="_blank" class="btn btn-outline btn-sm" style="padding:2px 8px; font-size:11px;">
                                    View
                                </a>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = tHtml;
            }
            if (window.feather) feather.replace();
        })
        .catch(err => {
            alert('Failed to fetch client overview.');
        });
}

function closeModal(id) { document.getElementById(id).style.display = 'none'; }

document.addEventListener('DOMContentLoaded', () => {
    initClientTable();
    if (window.feather) feather.replace();

    const openParam = new URLSearchParams(window.location.search).get('open');
    if (openParam === 'add_client' && typeof openAddClientModal === 'function') {
        setTimeout(() => openAddClientModal(), 120);
    } else if (openParam === 'import_clients' && typeof openImportClientsModal === 'function') {
        setTimeout(() => openImportClientsModal(), 120);
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
