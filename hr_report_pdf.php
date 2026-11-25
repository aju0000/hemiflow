<?php
// hr_report_pdf.php - Executive Printable / Downloadable PDF for HR Month-Wise Reports (Leaves & Employees)
require_once __DIR__ . '/auth.php';
checkAuth();

$user = getCurrentUser();
$db = getDbConnection();

$isHr = isHrAdmin();
$isSuperAdmin = (($user['role'] ?? '') === 'Super Admin');
$isLead = isTeamLeadOrManager();

if (!$isHr && !$isSuperAdmin && !$isLead) {
    die("<div style='font-family:sans-serif; padding:40px; text-align:center;'><h3>Access Denied</h3><p>Only HR administrators and team leads can generate HR executive reports.</p></div>");
}

$type = strtolower(trim($_GET['type'] ?? 'leaves')); // 'leaves' or 'employees'
$month = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$monthTs = strtotime($month . '-01');
$monthName = date('F Y', $monthTs);
$daysInMonth = intval(date('t', $monthTs));
$startDate = $month . '-01';
$endDate = $month . '-' . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT);

$deptId = intval($_GET['department_id'] ?? 0);

// Department name if filtered
$deptName = 'All Departments';
if ($deptId > 0) {
    $stDept = $db->prepare("SELECT name FROM departments WHERE id = ?");
    $stDept->execute([$deptId]);
    $deptName = $stDept->fetchColumn() ?: 'Department';
}

if ($type === 'leaves') {
    // Month-wise Leave Applications
    $sqlLeaves = "
        SELECT lr.*, e.employee_code, e.full_name as employee_name, d.name as dept_name, des.title as desig_title,
               lt.name as leave_type_name, lt.code as leave_type_code,
               u.full_name as reviewer_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN designations des ON e.designation_id = des.id
        LEFT JOIN users u ON lr.reviewed_by = u.id
        WHERE (lr.start_date LIKE '$month%' OR lr.end_date LIKE '$month%' OR lr.created_at LIKE '$month%')
    ";
    if ($deptId > 0) $sqlLeaves .= " AND e.department_id = $deptId";
    $sqlLeaves .= " ORDER BY lr.start_date DESC";

    $leavesList = $db->query($sqlLeaves)->fetchAll(PDO::FETCH_ASSOC);

    // Summary KPIs
    $totalApps = count($leavesList);
    $totalApprovedDays = 0;
    $countApproved = 0;
    $countPending = 0;
    $countRejected = 0;
    $byType = [];

    foreach ($leavesList as $l) {
        $st = $l['status'];
        if ($st === 'Approved') {
            $countApproved++;
            $totalApprovedDays += floatval($l['total_days']);
        } elseif ($st === 'Pending') {
            $countPending++;
        } else {
            $countRejected++;
        }
        $tName = $l['leave_type_name'];
        $byType[$tName] = ($byType[$tName] ?? 0) + floatval($l['total_days']);
    }

} else {
    // Month-wise Employee Workforce Roster & New Joiners
    $sqlEmps = "
        SELECT e.*, d.name as dept_name, des.title as desig_title, m.full_name as manager_name,
               (SELECT COUNT(*) FROM leave_requests lr WHERE lr.employee_id = e.id AND lr.status = 'Approved' AND (lr.start_date LIKE '$month%' OR lr.end_date LIKE '$month%')) as month_leave_count,
               (SELECT COALESCE(SUM(lr.total_days), 0) FROM leave_requests lr WHERE lr.employee_id = e.id AND lr.status = 'Approved' AND (lr.start_date LIKE '$month%' OR lr.end_date LIKE '$month%')) as month_leave_days,
               (SELECT COUNT(*) FROM attendance a WHERE a.employee_id = e.id AND a.attendance_date LIKE '$month%' AND (a.status = 'Present' OR a.section_1 = 'P' OR a.section_2 = 'P')) as month_present_days,
               (SELECT COALESCE(SUM(a.total_hours), 0) FROM attendance a WHERE a.employee_id = e.id AND a.attendance_date LIKE '$month%') as month_total_hours
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN designations des ON e.designation_id = des.id
        LEFT JOIN employees m ON e.reporting_manager_id = m.id
        WHERE 1=1
    ";
    if ($deptId > 0) $sqlEmps .= " AND e.department_id = $deptId";
    $sqlEmps .= " ORDER BY e.employee_code ASC";

    $empsList = $db->query($sqlEmps)->fetchAll(PDO::FETCH_ASSOC);

    // Summary KPIs
    $totalEmployees = count($empsList);
    $activeCount = 0;
    $newJoinersCount = 0;
    $onLeaveCount = 0;

    foreach ($empsList as $e) {
        if ($e['employment_status'] === 'Active') $activeCount++;
        if (!empty($e['date_of_joining']) && strpos($e['date_of_joining'], $month) === 0) {
            $newJoinersCount++;
        }
        if (floatval($e['month_leave_days']) > 0) {
            $onLeaveCount++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR <?= $type === 'leaves' ? 'Monthly Leave' : 'Monthly Workforce' ?> Report - <?= htmlspecialchars($monthName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body {
            margin: 0; padding: 0;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: #f1f5f9; color: #0f172a; font-size: 12px; line-height: 1.5;
        }
        .pdf-toolbar {
            position: sticky; top: 0; z-index: 1000; background: #ffffff; border-bottom: 1px solid #e2e8f0;
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
        .badge-purple { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
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
        <span>📄</span>
        <span>HR Monthly <?= $type === 'leaves' ? 'Leave' : 'Workforce' ?> Report (PDF Export)</span>
    </div>
    <div style="display:flex; align-items:center; gap:10px;">
        <button type="button" class="pdf-btn pdf-btn-danger" onclick="window.print()">
            <span>🖨️ Save as PDF / Print</span>
        </button>
        <a href="hr.php" class="pdf-btn pdf-btn-outline">
            <span>← Back to HR Management</span>
        </a>
    </div>
</div>

<div class="pdf-container">
    <div class="doc-header">
        <div>
            <img src="asset/Hemiflow Blue Wave Logo.png" alt="Hemiflow Logo" onerror="this.src='asset/hemitologo-dark-blue.webp'">
            <div class="company-name">HEMITO DIGITAL PVT LTD</div>
            <div style="font-size:11.5px; color:#64748b; margin-top:2px;">
                Human Resources &amp; People Operations Suite<br>
                2nd Floor, ACEL Tower, Kadavanthra, Kochi | CIN: U72900KL2021PTC068412
            </div>
        </div>
        <div style="text-align:right;">
            <h1 class="report-title"><?= $type === 'leaves' ? 'Monthly Leave Audit' : 'Monthly Workforce Roster' ?></h1>
            <div class="report-badge">Period: <?= htmlspecialchars($monthName) ?></div>
            <div style="font-size:11px; color:#64748b; margin-top:6px;">
                Department: <strong><?= htmlspecialchars($deptName) ?></strong><br>
                Generated: <?= date('d M Y, h:i A') ?><br>
                Audited By: <?= htmlspecialchars($user['full_name']) ?> (HR)
            </div>
        </div>
    </div>

    <?php if ($type === 'leaves'): ?>
        <!-- LEAVES VIEW -->
        <div class="kpi-row">
            <div class="kpi-box primary">
                <div class="kpi-lbl">Total Leave Requests</div>
                <div class="kpi-num" style="color:#2563eb;"><?= $totalApps ?></div>
                <small style="color:#64748b; font-size:10px;">In <?= htmlspecialchars($monthName) ?></small>
            </div>
            <div class="kpi-box success">
                <div class="kpi-lbl">Approved Requests</div>
                <div class="kpi-num" style="color:#16a34a;"><?= $countApproved ?></div>
                <small style="color:#15803d; font-size:10px;">Days: <?= number_format($totalApprovedDays, 1) ?></small>
            </div>
            <div class="kpi-box warning">
                <div class="kpi-lbl">Pending Approvals</div>
                <div class="kpi-num" style="color:#d97706;"><?= $countPending ?></div>
                <small style="color:#b45309; font-size:10px;">Awaiting Review</small>
            </div>
            <div class="kpi-box danger">
                <div class="kpi-lbl">Rejected Requests</div>
                <div class="kpi-num" style="color:#dc2626;"><?= $countRejected ?></div>
                <small style="color:#b91c1c; font-size:10px;">Declined</small>
            </div>
            <div class="kpi-box purple">
                <div class="kpi-lbl">Total Approved Days</div>
                <div class="kpi-num" style="color:#7e22ce;"><?= number_format($totalApprovedDays, 1) ?>d</div>
                <small style="color:#6b21a8; font-size:10px;">Staff Absences</small>
            </div>
        </div>

        <h3 style="font-size:13.5px; font-weight:800; color:#0f172a; margin:0 0 10px 0; text-transform:uppercase;">
            Monthly Leave Applications Log (<?= htmlspecialchars($monthName) ?>)
        </h3>
        <table class="doc-table">
            <thead>
                <tr>
                    <th style="width:75px;">Code</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Leave Type</th>
                    <th style="width:90px; text-align:center;">Start Date</th>
                    <th style="width:90px; text-align:center;">End Date</th>
                    <th style="width:65px; text-align:center;">Days</th>
                    <th style="width:80px; text-align:center;">Status</th>
                    <th>Reason / Justification</th>
                    <th>Reviewed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leavesList)): ?>
                    <tr><td colspan="10" style="text-align:center; padding:30px;">No leave applications found for this month.</td></tr>
                <?php else: ?>
                    <?php foreach ($leavesList as $lv): 
                        $stBadge = 'badge-warning';
                        if ($lv['status'] === 'Approved') $stBadge = 'badge-success';
                        elseif ($lv['status'] === 'Rejected') $stBadge = 'badge-danger';
                    ?>
                        <tr>
                            <td style="font-family:'JetBrains Mono', monospace; font-weight:700; color:#2563eb;"><?= htmlspecialchars($lv['employee_code']) ?></td>
                            <td><strong><?= htmlspecialchars($lv['employee_name']) ?></strong></td>
                            <td><?= htmlspecialchars($lv['dept_name'] ?: 'General') ?></td>
                            <td><span class="badge badge-purple"><?= htmlspecialchars($lv['leave_type_name']) ?></span></td>
                            <td style="text-align:center;"><?= date('d M Y', strtotime($lv['start_date'])) ?></td>
                            <td style="text-align:center;"><?= date('d M Y', strtotime($lv['end_date'])) ?></td>
                            <td style="text-align:center; font-weight:700;"><?= $lv['total_days'] ?>d</td>
                            <td style="text-align:center;"><span class="badge <?= $stBadge ?>"><?= htmlspecialchars($lv['status']) ?></span></td>
                            <td><?= htmlspecialchars($lv['reason']) ?></td>
                            <td><?= htmlspecialchars($lv['reviewer_name'] ?: '--') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    <?php else: ?>
        <!-- EMPLOYEES VIEW -->
        <div class="kpi-row">
            <div class="kpi-box primary">
                <div class="kpi-lbl">Total Headcount</div>
                <div class="kpi-num" style="color:#2563eb;"><?= $totalEmployees ?></div>
                <small style="color:#64748b; font-size:10px;">Workforce Roster</small>
            </div>
            <div class="kpi-box success">
                <div class="kpi-lbl">Active Employees</div>
                <div class="kpi-num" style="color:#16a34a;"><?= $activeCount ?></div>
                <small style="color:#15803d; font-size:10px;">On Roll</small>
            </div>
            <div class="kpi-box info">
                <div class="kpi-lbl">New Joiners</div>
                <div class="kpi-num" style="color:#0891b2;"><?= $newJoinersCount ?></div>
                <small style="color:#0e7490; font-size:10px;">Joined in <?= htmlspecialchars($monthName) ?></small>
            </div>
            <div class="kpi-box purple">
                <div class="kpi-lbl">Employees on Leave</div>
                <div class="kpi-num" style="color:#7e22ce;"><?= $onLeaveCount ?></div>
                <small style="color:#6b21a8; font-size:10px;">Took Leave in Month</small>
            </div>
        </div>

        <h3 style="font-size:13.5px; font-weight:800; color:#0f172a; margin:0 0 10px 0; text-transform:uppercase;">
            Workforce Master Roster &amp; Monthly Performance (<?= htmlspecialchars($monthName) ?>)
        </h3>
        <table class="doc-table">
            <thead>
                <tr>
                    <th style="width:75px;">Code</th>
                    <th>Employee Name</th>
                    <th>Email &amp; Phone</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th style="width:85px; text-align:center;">Joining Date</th>
                    <th style="width:75px; text-align:center;">Type</th>
                    <th style="width:65px; text-align:center;">Present</th>
                    <th style="width:65px; text-align:center;">Leaves</th>
                    <th style="width:75px; text-align:center;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($empsList)): ?>
                    <tr><td colspan="10" style="text-align:center; padding:30px;">No employees found in this department.</td></tr>
                <?php else: ?>
                    <?php foreach ($empsList as $em): 
                        $isNew = (!empty($em['date_of_joining']) && strpos($em['date_of_joining'], $month) === 0);
                    ?>
                        <tr>
                            <td style="font-family:'JetBrains Mono', monospace; font-weight:700; color:#2563eb;"><?= htmlspecialchars($em['employee_code']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($em['full_name']) ?></strong>
                                <?php if ($isNew): ?>
                                    <span class="badge badge-primary" style="font-size:9.5px; margin-left:4px;">★ New Joiner</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($em['email']) ?><br><small style="color:#64748b;"><?= htmlspecialchars($em['phone'] ?: '--') ?></small></td>
                            <td><?= htmlspecialchars($em['dept_name'] ?: 'General') ?></td>
                            <td><?= htmlspecialchars($em['desig_title'] ?: 'Staff') ?></td>
                            <td style="text-align:center;"><?= !empty($em['date_of_joining']) ? date('d M Y', strtotime($em['date_of_joining'])) : '--' ?></td>
                            <td style="text-align:center;"><?= htmlspecialchars($em['employment_type'] ?: 'Full Time') ?></td>
                            <td style="text-align:center; font-weight:700; color:#16a34a;"><?= intval($em['month_present_days']) ?></td>
                            <td style="text-align:center; font-weight:700; color:#7e22ce;"><?= floatval($em['month_leave_days']) ?>d</td>
                            <td style="text-align:center;">
                                <span class="badge <?= $em['employment_status'] === 'Active' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= htmlspecialchars($em['employment_status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- SIGNATURE BLOCK -->
    <div class="signature-grid">
        <div class="signature-box">
            <div style="font-weight:700;">Prepared By (HR Department)</div>
            <div style="font-size:10px; color:#64748b;">People Operations Executive</div>
            <div style="height:32px;"></div>
            <div style="font-size:10px; color:#64748b;">Signature &amp; Date</div>
        </div>
        <div class="signature-box">
            <div style="font-weight:700;">Verified By (Department Head)</div>
            <div style="font-size:10px; color:#64748b;">HOD / Team Lead</div>
            <div style="height:32px;"></div>
            <div style="font-size:10px; color:#64748b;">Signature &amp; Date</div>
        </div>
        <div class="signature-box">
            <div style="font-weight:700;">Executive Approval</div>
            <div style="font-size:10px; color:#64748b;">Managing Director / Super Admin</div>
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
