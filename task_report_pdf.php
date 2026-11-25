<?php
// task_report_pdf.php - Executive Printable / Downloadable PDF Super Admin Task Work Report
require_once __DIR__ . '/auth.php';
checkAuth('reports');

$user = getCurrentUser();
$db = getDbConnection();
$isSuperAdmin = (($user['role'] ?? '') === 'Super Admin');

// Parameters
$mode = strtolower(trim($_GET['mode'] ?? 'month'));
if (!in_array($mode, ['month', 'week', 'day', 'range'], true)) {
    $mode = 'month';
}

$dateParam = trim($_GET['date'] ?? date('Y-m-d'));
$monthParam = trim($_GET['month'] ?? date('Y-m'));
$weekParam = trim($_GET['week'] ?? date('Y-\WW'));
$startParam = trim($_GET['start_date'] ?? date('Y-m-01'));
$endParam = trim($_GET['end_date'] ?? date('Y-m-d'));
$deptFilter = trim($_GET['department'] ?? '');
$empFilter = intval($_GET['employee_id'] ?? 0);

// Resolve start and end dates based on mode
if ($mode === 'day') {
    $dTs = strtotime($dateParam) ?: time();
    $startDate = date('Y-m-d', $dTs);
    $endDate = $startDate;
    $periodLabel = "Daily Task Report: " . date('d M Y', $dTs);
} elseif ($mode === 'week') {
    // Parse week like 2026-W47 or just W47
    if (preg_match('/^(\d{4})-W(\d{1,2})$/', $weekParam, $m)) {
        $yr = intval($m[1]);
        $wk = intval($m[2]);
    } else {
        $yr = intval(date('Y'));
        $wk = intval(date('W'));
    }
    $dto = new DateTime();
    $dto->setISODate($yr, $wk);
    $startDate = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $endDate = $dto->format('Y-m-d');
    $periodLabel = "Week {$wk}, {$yr} (" . date('d M', strtotime($startDate)) . " - " . date('d M Y', strtotime($endDate)) . ")";
} elseif ($mode === 'month') {
    $mTs = strtotime($monthParam . '-01') ?: time();
    $startDate = date('Y-m-01', $mTs);
    $endDate = date('Y-m-t', $mTs);
    $periodLabel = "Monthly Task Report: " . date('F Y', $mTs);
} else {
    $sTs = strtotime($startParam) ?: strtotime(date('Y-m-01'));
    $eTs = strtotime($endParam) ?: time();
    if ($sTs > $eTs) { $tmp = $sTs; $sTs = $eTs; $eTs = $tmp; }
    $startDate = date('Y-m-d', $sTs);
    $endDate = date('Y-m-d', $eTs);
    $periodLabel = "Custom Period: " . date('d M Y', $sTs) . " to " . date('d M Y', $eTs);
}

$rangeStartTs = strtotime($startDate . ' 00:00:00');
$rangeEndTs = strtotime($endDate . ' 23:59:59');

// Query Tasks in scope
$sql = "
    SELECT t.*, u.full_name as assignee_name, p.project_name, c.company_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN clients c ON t.client_id = c.id
    WHERE t.is_deleted = 0
";
$params = [];
if (!empty($deptFilter)) {
    $sql .= " AND t.department = :dept";
    $params[':dept'] = $deptFilter;
}
if ($empFilter > 0) {
    $sql .= " AND t.assigned_to = :emp";
    $params[':emp'] = $empFilter;
}
$sql .= " ORDER BY COALESCE(t.completion_time, t.updated_at, t.created_at) DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$allTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter by selected period range
$tasksInRange = [];
$totalCreated = 0;
$totalCompleted = 0;
$totalInProgress = 0;
$totalReview = 0;
$totalRevision = 0;
$totalPending = 0;
$slaPassCount = 0;
$slaBreachCount = 0;

$staffMap = [];
$periodMap = [];

foreach ($allTasks as $t) {
    $cTs = strtotime($t['created_at']);
    $compStr = !empty($t['completion_time']) ? $t['completion_time'] : (!empty($t['client_approved_at']) ? $t['client_approved_at'] : ($t['updated_at'] ?: $t['created_at']));
    $compTs = strtotime($compStr);

    $isCreatedInRange = ($cTs >= $rangeStartTs && $cTs <= $rangeEndTs);
    $isCompletedInRange = (strtoupper($t['status']) === 'COMPLETED' && $compTs >= $rangeStartTs && $compTs <= $rangeEndTs);
    $isRelevant = ($isCreatedInRange || $isCompletedInRange || (strtoupper($t['status']) !== 'COMPLETED' && $cTs <= $rangeEndTs));

    if (!$isRelevant) continue;

    $tasksInRange[] = $t;
    $st = strtoupper($t['status']);

    if ($st === 'COMPLETED') $totalCompleted++;
    elseif ($st === 'IN_PROGRESS' || $st === 'IN PROGRESS') $totalInProgress++;
    elseif ($st === 'REVIEW' || $st === 'UNDER_REVIEW') $totalReview++;
    elseif ($st === 'REVISION' || $st === 'CHANGES_REQUESTED') $totalRevision++;
    else $totalPending++;

    // SLA Check
    $isBreached = false;
    if (!empty($t['deadline'])) {
        $dlTs = strtotime(substr($t['deadline'], 0, 10) . ' 23:59:59');
        if ($dlTs) {
            if ($st === 'COMPLETED' && $compTs > $dlTs) $isBreached = true;
            elseif ($st !== 'COMPLETED' && time() > $dlTs) $isBreached = true;
        }
    }
    if ($isBreached) $slaBreachCount++; else $slaPassCount++;

    // Period breakdown bucket
    $bucketKey = '';
    if ($mode === 'day') {
        $bucketKey = date('Y-m-d', $cTs);
    } elseif ($mode === 'week') {
        $bucketKey = date('Y-m-d (D)', $cTs);
    } else {
        $bucketKey = date('Y-W%W', $cTs);
    }
    if (!isset($periodMap[$bucketKey])) {
        $periodMap[$bucketKey] = ['created' => 0, 'completed' => 0, 'in_progress' => 0, 'breached' => 0];
    }
    $periodMap[$bucketKey]['created']++;
    if ($st === 'COMPLETED') $periodMap[$bucketKey]['completed']++;
    if ($st === 'IN_PROGRESS' || $st === 'IN PROGRESS') $periodMap[$bucketKey]['in_progress']++;
    if ($isBreached) $periodMap[$bucketKey]['breached']++;

    // Staff breakdown
    $sName = $t['assignee_name'] ?: 'Unassigned';
    if (!isset($staffMap[$sName])) {
        $staffMap[$sName] = [
            'name' => $sName,
            'department' => $t['department'] ?: 'General',
            'assigned' => 0,
            'completed' => 0,
            'in_progress' => 0,
            'review' => 0,
            'breached' => 0
        ];
    }
    $staffMap[$sName]['assigned']++;
    if ($st === 'COMPLETED') $staffMap[$sName]['completed']++;
    if ($st === 'IN_PROGRESS' || $st === 'IN PROGRESS') $staffMap[$sName]['in_progress']++;
    if ($st === 'REVIEW' || $st === 'UNDER_REVIEW') $staffMap[$sName]['review']++;
    if ($isBreached) $staffMap[$sName]['breached']++;
}

$totalTasks = count($tasksInRange);
$clearanceRate = $totalTasks > 0 ? round(($totalCompleted / $totalTasks) * 100, 1) : 0;
$slaRate = $totalTasks > 0 ? round(($slaPassCount / $totalTasks) * 100, 1) : 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Task Work Report - <?= htmlspecialchars($periodLabel) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body {
            margin: 0; padding: 0;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: #f1f5f9; color: #0f172a; font-size: 12px; line-height: 1.5;
        }
        .pdf-toolbar {
            position: sticky; top: 0; z-index: 1000;
            background: #ffffff; border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .pdf-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px;
            border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; border: none;
        }
        .pdf-btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #ffffff; }
        .pdf-btn-outline { background: #ffffff; color: #475569; border: 1px solid #cbd5e1; }
        .pdf-container {
            max-width: 1060px; margin: 24px auto; background: #ffffff; padding: 40px 48px;
            border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;
        }
        .doc-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 24px;
        }
        .doc-header img { height: 48px; width: auto; object-fit: contain; margin-bottom: 6px; }
        .doc-header .company-name { font-size: 18px; font-weight: 800; color: #0f172a; }
        .doc-header .report-title { font-size: 19px; font-weight: 800; color: #1e40af; margin: 0; text-transform: uppercase; }
        .report-badge {
            display: inline-block; background: #eff6ff; color: #2563eb; font-weight: 700;
            font-size: 11.5px; padding: 3px 10px; border-radius: 999px; margin-top: 4px; border: 1px solid #bfdbfe;
        }
        .kpi-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 24px;
        }
        .kpi-box {
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; text-align: center;
        }
        .kpi-box.primary { border-top: 3px solid #2563eb; background: #f8faff; }
        .kpi-box.success { border-top: 3px solid #10b981; background: #f0fdf4; }
        .kpi-box.warning { border-top: 3px solid #f59e0b; background: #fffbeb; }
        .kpi-box.danger { border-top: 3px solid #ef4444; background: #fef2f2; }
        .kpi-box.purple { border-top: 3px solid #8b5cf6; background: #faf5ff; }
        .kpi-num { font-size: 20px; font-weight: 800; margin: 2px 0; font-family: 'JetBrains Mono', monospace; }
        .kpi-lbl { font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .doc-table { width: 100%; border-collapse: collapse; font-size: 11.5px; margin-bottom: 24px; }
        .doc-table th {
            background: #0f172a; color: #ffffff; font-weight: 700; text-align: left; padding: 8px 10px;
            font-size: 10.5px; text-transform: uppercase; border: 1px solid #0f172a;
        }
        .doc-table td { padding: 7px 10px; border: 1px solid #e2e8f0; color: #1e293b; }
        .doc-table tr:nth-child(even) td { background: #f8fafc; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700; }
        .badge-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-primary { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .signature-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 40px; padding-top: 20px; page-break-inside: avoid;
        }
        .signature-box { border-top: 1px dashed #94a3b8; padding-top: 10px; text-align: center; }
        @media print {
            @page { size: A4 portrait; margin: 10mm 12mm; }
            body { background: #ffffff !important; }
            .no-print { display: none !important; }
            .pdf-container { max-width: 100% !important; margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>

<div class="pdf-toolbar no-print">
    <div style="font-size:15px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
        <span>📊</span>
        <span>Super Admin Task Work Report (PDF Export)</span>
    </div>
    <div style="display:flex; align-items:center; gap:10px;">
        <button type="button" class="pdf-btn pdf-btn-danger" onclick="window.print()">
            <span>🖨️ Save as PDF / Print</span>
        </button>
        <a href="reports.php" class="pdf-btn pdf-btn-outline">
            <span>← Back to Reports</span>
        </a>
    </div>
</div>

<div class="pdf-container">
    <div class="doc-header">
        <div>
            <img src="asset/Hemiflow Blue Wave Logo.png" alt="Hemiflow Logo" onerror="this.src='asset/hemitologo-dark-blue.webp'">
            <div class="company-name">HEMITO DIGITAL PVT LTD</div>
            <div style="font-size:11.5px; color:#64748b; margin-top:2px;">
                Executive Task Work &amp; Productivity Audit Suite<br>
                2nd Floor, ACEL Tower, Kadavanthra, Kochi | CIN: U72900KL2021PTC068412
            </div>
        </div>
        <div style="text-align:right;">
            <h1 class="report-title">Task Work Report</h1>
            <div class="report-badge"><?= htmlspecialchars($periodLabel) ?></div>
            <div style="font-size:11px; color:#64748b; margin-top:6px;">
                Granularity: <strong><?= strtoupper(htmlspecialchars($mode)) ?>-WISE</strong><br>
                Generated: <?= date('d M Y, h:i A') ?><br>
                Super Admin: <?= htmlspecialchars($user['full_name']) ?>
            </div>
        </div>
    </div>

    <!-- KPI ROW -->
    <div class="kpi-row">
        <div class="kpi-box primary">
            <div class="kpi-lbl">Total Tasks</div>
            <div class="kpi-num" style="color:#1d4ed8;"><?= $totalTasks ?></div>
            <small style="color:#2563eb; font-size:10px;">In Period</small>
        </div>
        <div class="kpi-box success">
            <div class="kpi-lbl">Completed</div>
            <div class="kpi-num" style="color:#16a34a;"><?= $totalCompleted ?></div>
            <small style="color:#15803d; font-size:10px;">Clearance: <?= $clearanceRate ?>%</small>
        </div>
        <div class="kpi-box warning">
            <div class="kpi-lbl">In Progress</div>
            <div class="kpi-num" style="color:#d97706;"><?= $totalInProgress ?></div>
            <small style="color:#b45309; font-size:10px;">Active Work</small>
        </div>
        <div class="kpi-box purple">
            <div class="kpi-lbl">Under Review / Rev</div>
            <div class="kpi-num" style="color:#7e22ce;"><?= $totalReview + $totalRevision ?></div>
            <small style="color:#6b21a8; font-size:10px;">QA &amp; Revisions</small>
        </div>
        <div class="kpi-box success">
            <div class="kpi-lbl">SLA Pass Rate</div>
            <div class="kpi-num" style="color:#16a34a;"><?= $slaRate ?>%</div>
            <small style="color:#15803d; font-size:10px;">On Time: <?= $slaPassCount ?></small>
        </div>
        <div class="kpi-box danger">
            <div class="kpi-lbl">SLA Breaches</div>
            <div class="kpi-num" style="color:#dc2626;"><?= $slaBreachCount ?></div>
            <small style="color:#b91c1c; font-size:10px;">Delayed Delivery</small>
        </div>
    </div>

    <!-- STAFF PRODUCTIVITY TABLE -->
    <h3 style="font-size:13.5px; font-weight:800; color:#0f172a; margin:0 0 10px 0; text-transform:uppercase; letter-spacing:0.02em;">
        Staff Workload &amp; Task Clearance Summary
    </h3>
    <table class="doc-table">
        <thead>
            <tr>
                <th>Developer / Staff</th>
                <th>Department</th>
                <th style="width:75px; text-align:center;">Assigned</th>
                <th style="width:75px; text-align:center;">Completed</th>
                <th style="width:75px; text-align:center;">In Progress</th>
                <th style="width:75px; text-align:center;">Review</th>
                <th style="width:75px; text-align:center;">Breached</th>
                <th style="width:85px; text-align:center;">Clearance %</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($staffMap)): ?>
                <tr><td colspan="8" style="text-align:center; padding:20px;">No staff task assignments in this period.</td></tr>
            <?php else: ?>
                <?php foreach ($staffMap as $sm): 
                    $cRate = $sm['assigned'] > 0 ? round(($sm['completed'] / $sm['assigned']) * 100, 1) : 0;
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sm['name']) ?></strong></td>
                        <td><?= htmlspecialchars($sm['department']) ?></td>
                        <td style="text-align:center; font-weight:700;"><?= $sm['assigned'] ?></td>
                        <td style="text-align:center; font-weight:700; color:#16a34a;"><?= $sm['completed'] ?></td>
                        <td style="text-align:center; color:#2563eb;"><?= $sm['in_progress'] ?></td>
                        <td style="text-align:center; color:#d97706;"><?= $sm['review'] ?></td>
                        <td style="text-align:center; font-weight:700; color:#dc2626;"><?= $sm['breached'] ?></td>
                        <td style="text-align:center;">
                            <span class="badge <?= $cRate >= 70 ? 'badge-success' : ($cRate >= 40 ? 'badge-warning' : 'badge-danger') ?>">
                                <?= $cRate ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- DETAILED TASK LEDGER TABLE -->
    <h3 style="font-size:13.5px; font-weight:800; color:#0f172a; margin:20px 0 10px 0; text-transform:uppercase; letter-spacing:0.02em;">
        Detailed Task Ledger
    </h3>
    <table class="doc-table">
        <thead>
            <tr>
                <th style="width:85px;">Code</th>
                <th>Task Title</th>
                <th>Client &amp; Project</th>
                <th>Assignee</th>
                <th style="width:85px; text-align:center;">Status</th>
                <th style="width:85px; text-align:center;">Deadline</th>
                <th style="width:85px; text-align:center;">Completed</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tasksInRange)): ?>
                <tr><td colspan="7" style="text-align:center; padding:20px;">No task records found in this timeframe.</td></tr>
            <?php else: ?>
                <?php foreach ($tasksInRange as $tk): 
                    $tkSt = strtoupper($tk['status']);
                    $stBadge = 'badge-secondary';
                    if ($tkSt === 'COMPLETED') $stBadge = 'badge-success';
                    elseif ($tkSt === 'IN_PROGRESS' || $tkSt === 'IN PROGRESS') $stBadge = 'badge-primary';
                    elseif ($tkSt === 'REVIEW' || $tkSt === 'UNDER_REVIEW') $stBadge = 'badge-warning';
                    elseif ($tkSt === 'REVISION') $stBadge = 'badge-danger';
                ?>
                    <tr>
                        <td style="font-family:'JetBrains Mono', monospace; font-weight:700; color:#2563eb;"><?= htmlspecialchars($tk['task_code']) ?></td>
                        <td><strong><?= htmlspecialchars($tk['title']) ?></strong></td>
                        <td><?= htmlspecialchars($tk['company_name'] ?: 'Internal') ?> - <?= htmlspecialchars($tk['project_name'] ?: 'General') ?></td>
                        <td><?= htmlspecialchars($tk['assignee_name'] ?: 'Unassigned') ?></td>
                        <td style="text-align:center;"><span class="badge <?= $stBadge ?>"><?= htmlspecialchars($tk['status']) ?></span></td>
                        <td style="text-align:center;"><?= !empty($tk['deadline']) ? date('d M Y', strtotime($tk['deadline'])) : '--' ?></td>
                        <td style="text-align:center; font-weight:700; color:#16a34a;"><?= !empty($tk['completion_time']) ? date('d M Y', strtotime($tk['completion_time'])) : '--' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- SIGNATURE BLOCK -->
    <div class="signature-grid">
        <div class="signature-box">
            <div style="font-weight:700;">Compiled By (BI Analyst)</div>
            <div style="font-size:10px; color:#64748b;">Reporting &amp; Analytics Dept</div>
            <div style="height:32px;"></div>
            <div style="font-size:10px; color:#64748b;">Signature &amp; Date</div>
        </div>
        <div class="signature-box">
            <div style="font-weight:700;">Verified By (Technical Director)</div>
            <div style="font-size:10px; color:#64748b;">Production &amp; Development</div>
            <div style="height:32px;"></div>
            <div style="font-size:10px; color:#64748b;">Signature &amp; Date</div>
        </div>
        <div class="signature-box">
            <div style="font-weight:700;">Executive Approval</div>
            <div style="font-size:10px; color:#64748b;">Super Admin / Managing Director</div>
            <div style="height:32px;"></div>
            <div style="font-size:10px; color:#64748b;">Official Seal &amp; Signature</div>
        </div>
    </div>
</div>

<?php if (!empty($_GET['autoprint'])): ?>
<script>
    window.addEventListener('load', function() {
        setTimeout(function() { window.print(); }, 500);
    });
</script>
<?php endif; ?>

</body>
</html>
