<?php
// index.php - Main Agency OS Portal Hub

require_once __DIR__ . '/auth.php';
checkAuth();

$user = getCurrentUser();
$db = getDbConnection();

$pageTitle = "HemiFlow Portal Hub";
require_once __DIR__ . '/header.php';

// Fetch quick metrics
$clientCount = $db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$templateCount = $db->query("SELECT COUNT(*) FROM document_templates WHERE is_active=1")->fetchColumn();
$documentCount = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$projectCount = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();

// Fetch recent documents
$recentDocs = $db->query("SELECT d.*, c.company_name FROM documents d JOIN clients c ON d.client_id = c.id ORDER BY d.id DESC LIMIT 5")->fetchAll();
?>

<div style="margin-bottom: 30px;">
    <div style="background: linear-gradient(135deg, #0f172a, #1e293b); color: white; padding: 25px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="font-size: 22px; margin-bottom: 6px;">Welcome back, <?= htmlspecialchars($user['full_name']) ?> 👋</h2>
            <p style="color: #94a3b8; font-size: 14px;">Role: <strong><?= htmlspecialchars($user['role']) ?></strong> | Department: <strong><?= htmlspecialchars($user['department']) ?></strong></p>
        </div>
        <div>
            <a href="template_generator.php" class="btn btn-primary">
                <i data-feather="plus"></i> Open Template Generator
            </a>
        </div>
    </div>
</div>

<!-- Metrics Cards -->
<div class="grid-4" style="margin-bottom: 30px;">
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
</div>

<!-- Accessible Modules Grid -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">System Modules & Roles</h3>
    </div>
    <div class="grid-3">
        <!-- Module 3: Template Generator -->
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

        <!-- Module 4: Project & Task Management -->
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

        <!-- Module 9: Reports & BI -->
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
    </div>
</div>

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

<?php require_once __DIR__ . '/footer.php'; ?>
