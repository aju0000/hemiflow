<?php
// attendance_pdf.php - Executive Printable / Downloadable PDF Attendance Report
require_once __DIR__ . '/auth.php';
checkAuth();

$user = getCurrentUser();
$db = getDbConnection();

$isHr = isHrAdmin();
$isSuperAdmin = (($user['role'] ?? '') === 'Super Admin');
$isLead = isTeamLeadOrManager();

// Get current user's employee record
$stCurr = $db->prepare("SELECT * FROM employees WHERE user_id = ?");
$stCurr->execute([$user['id']]);
$currEmp = $stCurr->fetch(PDO::FETCH_ASSOC);

// Employee selection logic & permission enforcement
$employeeId = intval($_GET['employee_id'] ?? 0);
if (!$isHr && !$isSuperAdmin && !$isLead) {
    if (!$currEmp) {
        die("<div style='font-family:sans-serif; padding:40px; text-align:center;'><h3>Employee Record Not Found</h3><p>Your user account is not linked to an employee profile.</p></div>");
    }
    $employeeId = $currEmp['id']; // Regular employee can only view their own
}

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

// Fetch all active employees for filter dropdown
$allEmployees = $db->query("
    SELECT e.id, e.employee_code, e.full_name, d.name as dept_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.employment_status = 'Active'
    ORDER BY e.employee_code ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch departments for filter dropdown
$departments = $db->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// If a single employee is chosen
$targetEmp = null;
if ($employeeId > 0) {
    $stTgt = $db->prepare("
        SELECT e.*, d.name as dept_name, des.title as desig_title, m.full_name as manager_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN designations des ON e.designation_id = des.id
        LEFT JOIN employees m ON e.reporting_manager_id = m.id
        WHERE e.id = ?
    ");
    $stTgt->execute([$employeeId]);
    $targetEmp = $stTgt->fetch(PDO::FETCH_ASSOC);
}

// Holidays in selected month
$stHolidays = $db->prepare("
    SELECT holiday_date, holiday_name, is_optional
    FROM holidays
    WHERE holiday_date BETWEEN ? AND ?
");
$stHolidays->execute([$startDate, $endDate]);
$holidaysMap = [];
while ($h = $stHolidays->fetch(PDO::FETCH_ASSOC)) {
    $holidaysMap[$h['holiday_date']] = $h['holiday_name'];
}

// Attendance data query
if ($targetEmp) {
    // Single Employee Day-by-Day Map
    $stAtt = $db->prepare("
        SELECT * FROM attendance
        WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date ASC
    ");
    $stAtt->execute([$targetEmp['id'], $startDate, $endDate]);
    $attRows = $stAtt->fetchAll(PDO::FETCH_ASSOC);
    $dailyAttMap = [];
    foreach ($attRows as $r) {
        $dailyAttMap[$r['attendance_date']] = $r;
    }

    // Leave requests in month for target employee
    $stL = $db->prepare("
        SELECT lr.*, lt.name as leave_name, lt.code as leave_code
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.employee_id = ? AND lr.status = 'Approved'
          AND ((lr.start_date BETWEEN ? AND ?) OR (lr.end_date BETWEEN ? AND ?))
    ");
    $stL->execute([$targetEmp['id'], $startDate, $endDate, $startDate, $endDate]);
    $approvedLeaves = $stL->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Monthly KPIs for this employee
    $cntPresent = 0;
    $cntHalfDay = 0;
    $cntWFH = 0;
    $cntLeave = 0;
    $cntAbsent = 0;
    $cntWeekend = 0;
    $cntHoliday = 0;
    $totalHoursLogged = 0;

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
        $dayOfWeek = date('N', strtotime($dateStr)); // 1 (Mon) to 7 (Sun)
        $isHoliday = isset($holidaysMap[$dateStr]);
        $isSunday = ($dayOfWeek == 7);

        if (isset($dailyAttMap[$dateStr])) {
            $rec = $dailyAttMap[$dateStr];
            $st = $rec['status'] ?? '';
            $s1 = strtoupper($rec['section_1'] ?? '');
            $s2 = strtoupper($rec['section_2'] ?? '');
            $hrs = floatval($rec['total_hours'] ?? 0);
            $totalHoursLogged += $hrs;

            if ($st === 'Present' || $s1 === 'P' || $s2 === 'P') {
                if ($st === 'Half Day' || ($s1 === 'P' && $s2 !== 'P') || ($s2 === 'P' && $s1 !== 'P')) {
                    $cntHalfDay++;
                } else {
                    $cntPresent++;
                }
            } elseif ($st === 'WFH' || $s1 === 'WFH' || $s2 === 'WFH') {
                $cntWFH++;
            } elseif ($st === 'On Leave' || $s1 === 'L' || $s2 === 'L') {
                $cntLeave++;
            } elseif ($st === 'Absent' || $s1 === 'A' || $s2 === 'A') {
                $cntAbsent++;
            } else {
                if ($isSunday) $cntWeekend++;
                elseif ($isHoliday) $cntHoliday++;
                else $cntAbsent++;
            }
        } else {
            if ($isSunday) $cntWeekend++;
            elseif ($isHoliday) $cntHoliday++;
            else {
                // If past date and no record, count as absent
                if ($dateStr <= date('Y-m-d')) {
                    $cntAbsent++;
                }
            }
        }
    }

    $effectivePayableDays = $cntPresent + $cntWFH + ($cntHalfDay * 0.5) + $cntLeave + $cntWeekend + $cntHoliday;
    $workingDaysInMonth = $daysInMonth - $cntWeekend - $cntHoliday;

} else {
    // All Employees Master Attendance Matrix
    $sqlMaster = "
        SELECT e.id as employee_id, e.employee_code, e.full_name, e.date_of_joining,
               d.name as dept_name, des.title as desig_title,
               COUNT(a.id) as total_records,
               SUM(CASE WHEN a.status = 'Present' OR a.section_1 = 'P' OR a.section_2 = 'P' THEN 1 ELSE 0 END) as present_days,
               SUM(CASE WHEN a.status = 'Half Day' OR a.section_1 = 'HD' OR a.section_2 = 'HD' THEN 1 ELSE 0 END) as half_days,
               SUM(CASE WHEN a.status = 'WFH' OR a.section_1 = 'WFH' OR a.section_2 = 'WFH' THEN 1 ELSE 0 END) as wfh_days,
               SUM(CASE WHEN a.status = 'On Leave' OR a.section_1 = 'L' OR a.section_2 = 'L' THEN 1 ELSE 0 END) as leave_days,
               SUM(CASE WHEN a.status = 'Absent' OR a.section_1 = 'A' OR a.section_2 = 'A' THEN 1 ELSE 0 END) as absent_days,
               COALESCE(SUM(a.total_hours), 0) as total_hours
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN designations des ON e.designation_id = des.id
        LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date BETWEEN '$startDate' AND '$endDate'
        WHERE e.employment_status = 'Active'
    ";
    if ($deptId > 0) {
        $sqlMaster .= " AND e.department_id = $deptId";
    }
    $sqlMaster .= " GROUP BY e.id ORDER BY e.employee_code ASC";
    $masterList = $db->query($sqlMaster)->fetchAll(PDO::FETCH_ASSOC);

    // Compute company-wide KPI totals
    $totalActiveStaff = count($masterList);
    $sumPresent = 0;
    $sumHalfDays = 0;
    $sumWFH = 0;
    $sumLeaves = 0;
    $sumHours = 0;
    foreach ($masterList as $m) {
        $sumPresent += $m['present_days'];
        $sumHalfDays += $m['half_days'];
        $sumWFH += $m['wfh_days'];
        $sumLeaves += $m['leave_days'];
        $sumHours += $m['total_hours'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Attendance Report - <?= htmlspecialchars($monthName) ?> - Hemito Digital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.5;
        }

        /* Interactive Top Toolbar (hidden on print) */
        .pdf-toolbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .pdf-toolbar .title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pdf-toolbar .controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pdf-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
        }
        .pdf-btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(37,99,235,0.25);
        }
        .pdf-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37,99,235,0.35);
        }
        .pdf-btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(239,68,68,0.25);
        }
        .pdf-btn-outline {
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .pdf-btn-outline:hover {
            background: #f8fafc;
            color: #0f172a;
        }
        .pdf-select {
            padding: 7px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            background: #ffffff;
        }

        /* Printable Document Sheet */
        .pdf-container {
            max-width: 1060px;
            margin: 24px auto;
            background: #ffffff;
            padding: 40px 48px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }

        /* Agency Letterhead */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .doc-header .logo-area img {
            height: 48px;
            width: auto;
            object-fit: contain;
            margin-bottom: 6px;
        }
        .doc-header .company-name {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .doc-header .company-desc {
            font-size: 11.5px;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }
        .doc-header .report-meta {
            text-align: right;
        }
        .doc-header .report-title {
            font-size: 19px;
            font-weight: 800;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin: 0;
        }
        .doc-header .report-badge {
            display: inline-block;
            background: #eff6ff;
            color: #2563eb;
            font-weight: 700;
            font-size: 11.5px;
            padding: 3px 10px;
            border-radius: 999px;
            margin-top: 4px;
            border: 1px solid #bfdbfe;
        }
        .doc-header .meta-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 6px;
        }

        /* Info Profile Card */
        .profile-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 22px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        .profile-item .label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.03em;
        }
        .profile-item .val {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
        }

        /* KPI Grid */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .kpi-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .kpi-box.primary { border-top: 3px solid #2563eb; background: #f8faff; }
        .kpi-box.success { border-top: 3px solid #10b981; background: #f0fdf4; }
        .kpi-box.warning { border-top: 3px solid #f59e0b; background: #fffbeb; }
        .kpi-box.info { border-top: 3px solid #06b6d4; background: #ecfeff; }
        .kpi-box.danger { border-top: 3px solid #ef4444; background: #fef2f2; }
        .kpi-box.purple { border-top: 3px solid #8b5cf6; background: #faf5ff; }

        .kpi-box .kpi-num {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 2px 0;
            font-family: 'JetBrains Mono', monospace;
        }
        .kpi-box .kpi-lbl {
            font-size: 10.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        /* Tables */
        .doc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
            margin-bottom: 24px;
        }
        .doc-table th {
            background: #0f172a;
            color: #ffffff;
            font-weight: 700;
            text-align: left;
            padding: 8px 10px;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: 1px solid #0f172a;
        }
        .doc-table td {
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .doc-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .doc-table tr.weekend-row td {
            background: #fffbeb !important;
            color: #92400e;
        }
        .doc-table tr.holiday-row td {
            background: #eff6ff !important;
            color: #1e40af;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 700;
            text-align: center;
        }
        .badge-present { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-halfday { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-wfh { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .badge-leave { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
        .badge-absent { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-weekend { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        /* Signatures block */
        .signature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 40px;
            padding-top: 20px;
            page-break-inside: avoid;
        }
        .signature-box {
            border-top: 1px dashed #94a3b8;
            padding-top: 10px;
            text-align: center;
        }
        .signature-box .role-title {
            font-size: 11.5px;
            font-weight: 700;
            color: #0f172a;
        }
        .signature-box .sub {
            font-size: 10px;
            color: #64748b;
        }

        /* Print media query */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm 12mm 12mm 12mm;
            }
            body {
                background: #ffffff !important;
                font-size: 11px;
            }
            .no-print {
                display: none !important;
            }
            .pdf-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            .doc-table th {
                background: #1e293b !important;
                color: #ffffff !important;
            }
            .kpi-box {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
            }
            .signature-grid {
                margin-top: 30px;
            }
        }
    </style>
</head>
<body>

<!-- TOP ACTION TOOLBAR (Hidden when printed or exported as PDF) -->
<div class="pdf-toolbar no-print">
    <div class="title">
        <span style="font-size:18px;">📄</span>
        <span>Monthly Attendance Audit &amp; PDF Export</span>
    </div>
    <div class="controls">
        <form method="GET" action="attendance_pdf.php" style="display:inline-flex; align-items:center; gap:8px; margin:0;">
            <label style="font-size:12px; font-weight:700; color:#475569;">Month:</label>
            <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="pdf-select" onchange="this.form.submit()">

            <?php if ($isHr || $isSuperAdmin || $isLead): ?>
                <label style="font-size:12px; font-weight:700; color:#475569; margin-left:6px;">Staff:</label>
                <select name="employee_id" class="pdf-select" onchange="this.form.submit()" style="max-width:240px;">
                    <option value="0" <?= ($employeeId == 0) ? 'selected' : '' ?>>★ All Employees (Master Summary)</option>
                    <?php foreach ($allEmployees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= ($employeeId == $emp['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['full_name']) ?> (<?= htmlspecialchars($emp['employee_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </form>

        <button type="button" class="pdf-btn pdf-btn-danger" onclick="window.print()">
            <span>🖨️ Save as PDF / Print</span>
        </button>

        <a href="hr.php" class="pdf-btn pdf-btn-outline">
            <span>← Back to HR Management</span>
        </a>
    </div>
</div>

<!-- PRINTABLE CONTAINER -->
<div class="pdf-container">
    
    <!-- AGENCY LETTERHEAD HEADER -->
    <div class="doc-header">
        <div class="logo-area">
            <img src="asset/Hemiflow Blue Wave Logo.png" alt="Hemiflow Logo" onerror="this.src='asset/hemitologo-dark-blue.webp'">
            <div class="company-name">HEMITO DIGITAL PVT LTD</div>
            <div class="company-desc">2nd Floor, ACEL Tower, Kadavanthra, Kochi, Kerala - 682020<br>CIN: U72900KL2021PTC068412 • hr@hemitodigital.com • +91 98470 66322</div>
        </div>
        <div class="report-meta">
            <h1 class="report-title">Monthly Attendance Audit</h1>
            <div class="report-badge">Period: <?= htmlspecialchars($monthName) ?></div>
            <div class="meta-sub">
                Generated: <?= date('d M Y, h:i A') ?><br>
                Audited By: <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['role'] ?? 'HR') ?>)
            </div>
        </div>
    </div>

    <?php if ($targetEmp): ?>
        <!-- ========================================================= -->
        <!-- SINGLE EMPLOYEE ATTENDANCE REPORT VIEW                    -->
        <!-- ========================================================= -->

        <!-- Employee Info Card -->
        <div class="profile-card">
            <div class="profile-item">
                <div class="label">Employee Name</div>
                <div class="val"><?= htmlspecialchars($targetEmp['full_name']) ?></div>
            </div>
            <div class="profile-item">
                <div class="label">Employee ID</div>
                <div class="val" style="font-family:'JetBrains Mono', monospace; color:#2563eb;"><?= htmlspecialchars($targetEmp['employee_code']) ?></div>
            </div>
            <div class="profile-item">
                <div class="label">Department</div>
                <div class="val"><?= htmlspecialchars($targetEmp['dept_name'] ?: 'General') ?></div>
            </div>
            <div class="profile-item">
                <div class="label">Designation</div>
                <div class="val"><?= htmlspecialchars($targetEmp['desig_title'] ?: 'Staff') ?></div>
            </div>
            <div class="profile-item">
                <div class="label">Date of Joining</div>
                <div class="val"><?= $targetEmp['date_of_joining'] ? date('d M Y', strtotime($targetEmp['date_of_joining'])) : '--' ?></div>
            </div>
            <div class="profile-item">
                <div class="label">Employment Type</div>
                <div class="val"><?= htmlspecialchars($targetEmp['employment_type'] ?: 'Full Time') ?></div>
            </div>
            <div class="profile-item">
                <div class="label">Work Location</div>
                <div class="val"><?= htmlspecialchars($targetEmp['work_location'] ?: 'Office') ?></div>
            </div>
            <div class="profile-item">
                <div class="label">Reporting Manager</div>
                <div class="val"><?= htmlspecialchars($targetEmp['manager_name'] ?: 'Management') ?></div>
            </div>
        </div>

        <!-- Monthly KPI Metrics Row -->
        <div class="kpi-row">
            <div class="kpi-box primary">
                <div class="kpi-lbl">Calendar Days</div>
                <div class="kpi-num"><?= $daysInMonth ?></div>
                <small style="color:#64748b; font-size:10px;">Working: <?= $workingDaysInMonth ?></small>
            </div>
            <div class="kpi-box success">
                <div class="kpi-lbl">Present (P)</div>
                <div class="kpi-num" style="color:#16a34a;"><?= $cntPresent ?></div>
                <small style="color:#15803d; font-size:10px;">Full Days</small>
            </div>
            <div class="kpi-box warning">
                <div class="kpi-lbl">Half Day (HD)</div>
                <div class="kpi-num" style="color:#d97706;"><?= $cntHalfDay ?></div>
                <small style="color:#b45309; font-size:10px;">0.5 Day Each</small>
            </div>
            <div class="kpi-box info">
                <div class="kpi-lbl">Remote (WFH)</div>
                <div class="kpi-num" style="color:#0891b2;"><?= $cntWFH ?></div>
                <small style="color:#0e7490; font-size:10px;">Home Office</small>
            </div>
            <div class="kpi-box purple">
                <div class="kpi-lbl">Approved Leaves (L)</div>
                <div class="kpi-num" style="color:#7e22ce;"><?= $cntLeave ?></div>
                <small style="color:#6b21a8; font-size:10px;">Formal Leave</small>
            </div>
            <div class="kpi-box danger">
                <div class="kpi-lbl">Absent (A)</div>
                <div class="kpi-num" style="color:#dc2626;"><?= $cntAbsent ?></div>
                <small style="color:#b91c1c; font-size:10px;">Unexcused</small>
            </div>
            <div class="kpi-box primary">
                <div class="kpi-lbl">Total Work Hours</div>
                <div class="kpi-num" style="color:#2563eb;"><?= number_format($totalHoursLogged, 1) ?>h</div>
                <small style="color:#2563eb; font-size:10px;">Avg: <?= ($cntPresent + $cntWFH + $cntHalfDay) > 0 ? number_format($totalHoursLogged / ($cntPresent + $cntWFH + $cntHalfDay), 1) : 0 ?>h/day</small>
            </div>
            <div class="kpi-box success">
                <div class="kpi-lbl">Payable Days</div>
                <div class="kpi-num" style="color:#16a34a;"><?= number_format($effectivePayableDays, 1) ?></div>
                <small style="color:#15803d; font-size:10px;">Payroll Basis</small>
            </div>
        </div>

        <!-- Day-by-Day Detailed Log Table -->
        <h3 style="font-size:13.5px; font-weight:800; color:#0f172a; margin:0 0 10px 0; text-transform:uppercase; letter-spacing:0.02em;">
            Daily Attendance Matrix (<?= htmlspecialchars($monthName) ?>)
        </h3>
        <table class="doc-table">
            <thead>
                <tr>
                    <th style="width:105px;">Date &amp; Day</th>
                    <th style="width:90px; text-align:center;">Section 1 (AM)</th>
                    <th style="width:90px; text-align:center;">Section 2 (PM)</th>
                    <th style="width:100px; text-align:center;">Final Status</th>
                    <th style="width:90px; text-align:center;">Check-In</th>
                    <th style="width:90px; text-align:center;">Check-Out</th>
                    <th style="width:85px; text-align:center;">Total Hours</th>
                    <th>Activity / Notes / Approval</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                    $dateStr = $month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                    $ts = strtotime($dateStr);
                    $dayName = date('D', $ts);
                    $isSunday = (date('N', $ts) == 7);
                    $isHoliday = isset($holidaysMap[$dateStr]);
                    $att = $dailyAttMap[$dateStr] ?? null;

                    $rowClass = '';
                    if ($isSunday) $rowClass = 'weekend-row';
                    elseif ($isHoliday) $rowClass = 'holiday-row';
                ?>
                    <tr class="<?= $rowClass ?>">
                        <td><strong><?= date('d M Y', $ts) ?></strong> (<?= $dayName ?>)</td>
                        <td style="text-align:center;">
                            <?php if ($att && !empty($att['section_1'])): ?>
                                <span class="badge badge-<?= strtolower($att['section_1']) == 'p' ? 'present' : (strtolower($att['section_1']) == 'l' ? 'leave' : 'absent') ?>">
                                    <?= htmlspecialchars($att['section_1']) ?>
                                </span>
                            <?php elseif ($isSunday): ?>
                                <span class="badge badge-weekend">SUN</span>
                            <?php elseif ($isHoliday): ?>
                                <span class="badge badge-wfh">HOL</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">--</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($att && !empty($att['section_2'])): ?>
                                <span class="badge badge-<?= strtolower($att['section_2']) == 'p' ? 'present' : (strtolower($att['section_2']) == 'l' ? 'leave' : 'absent') ?>">
                                    <?= htmlspecialchars($att['section_2']) ?>
                                </span>
                            <?php elseif ($isSunday): ?>
                                <span class="badge badge-weekend">SUN</span>
                            <?php elseif ($isHoliday): ?>
                                <span class="badge badge-wfh">HOL</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">--</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($att && !empty($att['status'])): ?>
                                <?php
                                    $stClass = 'badge-present';
                                    if ($att['status'] == 'Half Day') $stClass = 'badge-halfday';
                                    elseif ($att['status'] == 'WFH') $stClass = 'badge-wfh';
                                    elseif ($att['status'] == 'On Leave') $stClass = 'badge-leave';
                                    elseif ($att['status'] == 'Absent') $stClass = 'badge-absent';
                                ?>
                                <span class="badge <?= $stClass ?>"><?= htmlspecialchars($att['status']) ?></span>
                            <?php elseif ($isSunday): ?>
                                <span class="badge badge-weekend">Weekend</span>
                            <?php elseif ($isHoliday): ?>
                                <span class="badge badge-wfh">Holiday</span>
                            <?php elseif ($dateStr <= date('Y-m-d')): ?>
                                <span class="badge badge-absent">Absent</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">--</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; font-family:'JetBrains Mono', monospace; font-size:11px;">
                            <?= ($att && !empty($att['check_in'])) ? date('h:i A', strtotime($att['check_in'])) : '--' ?>
                        </td>
                        <td style="text-align:center; font-family:'JetBrains Mono', monospace; font-size:11px;">
                            <?= ($att && !empty($att['check_out'])) ? date('h:i A', strtotime($att['check_out'])) : '--' ?>
                        </td>
                        <td style="text-align:center; font-weight:700;">
                            <?= ($att && floatval($att['total_hours']) > 0) ? number_format($att['total_hours'], 1) . 'h' : '--' ?>
                        </td>
                        <td>
                            <?php if ($isHoliday): ?>
                                <strong style="color:#1d4ed8;">Public Holiday: <?= htmlspecialchars($holidaysMap[$dateStr]) ?></strong>
                            <?php elseif ($att && !empty($att['notes'])): ?>
                                <?= htmlspecialchars($att['notes']) ?>
                            <?php elseif ($isSunday): ?>
                                <span style="color:#94a3b8;">Weekly Off</span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">--</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

    <?php else: ?>
        <!-- ========================================================= -->
        <!-- MASTER WORKFORCE MONTHLY ATTENDANCE SUMMARY               -->
        <!-- ========================================================= -->

        <!-- Company-Wide Attendance KPI Summary -->
        <div class="kpi-row">
            <div class="kpi-box primary">
                <div class="kpi-lbl">Active Workforce</div>
                <div class="kpi-num"><?= $totalActiveStaff ?></div>
                <small style="color:#64748b; font-size:10px;">Total Employees</small>
            </div>
            <div class="kpi-box success">
                <div class="kpi-lbl">Total Present Logs</div>
                <div class="kpi-num" style="color:#16a34a;"><?= $sumPresent ?></div>
                <small style="color:#15803d; font-size:10px;">Full Days</small>
            </div>
            <div class="kpi-box warning">
                <div class="kpi-lbl">Total Half Days</div>
                <div class="kpi-num" style="color:#d97706;"><?= $sumHalfDays ?></div>
                <small style="color:#b45309; font-size:10px;">Part-Time Shifts</small>
            </div>
            <div class="kpi-box info">
                <div class="kpi-lbl">Remote (WFH) Logs</div>
                <div class="kpi-num" style="color:#0891b2;"><?= $sumWFH ?></div>
                <small style="color:#0e7490; font-size:10px;">Home Shifts</small>
            </div>
            <div class="kpi-box purple">
                <div class="kpi-lbl">Approved Leaves</div>
                <div class="kpi-num" style="color:#7e22ce;"><?= $sumLeaves ?></div>
                <small style="color:#6b21a8; font-size:10px;">Formal Leaves</small>
            </div>
            <div class="kpi-box primary">
                <div class="kpi-lbl">Total Agency Hours</div>
                <div class="kpi-num" style="color:#2563eb;"><?= number_format($sumHours, 1) ?>h</div>
                <small style="color:#2563eb; font-size:10px;">Productive Time</small>
            </div>
        </div>

        <!-- Master Table of Employees -->
        <h3 style="font-size:13.5px; font-weight:800; color:#0f172a; margin:0 0 10px 0; text-transform:uppercase; letter-spacing:0.02em;">
            Workforce Attendance Master Ledger (<?= htmlspecialchars($monthName) ?>)
        </h3>
        <table class="doc-table">
            <thead>
                <tr>
                    <th style="width:75px;">Code</th>
                    <th>Employee Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th style="width:65px; text-align:center;">Present</th>
                    <th style="width:65px; text-align:center;">Half Day</th>
                    <th style="width:65px; text-align:center;">WFH</th>
                    <th style="width:65px; text-align:center;">Leave</th>
                    <th style="width:65px; text-align:center;">Absent</th>
                    <th style="width:75px; text-align:center;">Hours</th>
                    <th style="width:80px; text-align:center;">Payable Days</th>
                    <th style="width:75px; text-align:center;">Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($masterList)): ?>
                    <tr><td colspan="12" style="text-align:center; padding:30px;">No attendance records found for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($masterList as $row): 
                        $pres = intval($row['present_days']);
                        $hd = intval($row['half_days']);
                        $wfh = intval($row['wfh_days']);
                        $l = intval($row['leave_days']);
                        $a = intval($row['absent_days']);
                        $hrs = floatval($row['total_hours']);
                        $payDays = $pres + $wfh + ($hd * 0.5) + $l;
                        $rate = ($daysInMonth > 0) ? round(($pres + $wfh + ($hd * 0.5)) / ($daysInMonth) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td style="font-family:'JetBrains Mono', monospace; font-weight:700; color:#2563eb;">
                                <a href="attendance_pdf.php?month=<?= urlencode($month) ?>&employee_id=<?= $row['employee_id'] ?>" style="color:#2563eb; text-decoration:none;">
                                    <?= htmlspecialchars($row['employee_code']) ?>
                                </a>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($row['full_name']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($row['dept_name'] ?: 'General') ?></td>
                            <td><?= htmlspecialchars($row['desig_title'] ?: 'Staff') ?></td>
                            <td style="text-align:center; font-weight:700; color:#16a34a;"><?= $pres ?></td>
                            <td style="text-align:center; color:#d97706;"><?= $hd ?></td>
                            <td style="text-align:center; color:#0891b2;"><?= $wfh ?></td>
                            <td style="text-align:center; color:#7e22ce;"><?= $l ?></td>
                            <td style="text-align:center; color:#dc2626;"><?= $a ?></td>
                            <td style="text-align:center; font-weight:700;"><?= number_format($hrs, 1) ?>h</td>
                            <td style="text-align:center; font-weight:800; color:#1e40af;"><?= number_format($payDays, 1) ?></td>
                            <td style="text-align:center; font-weight:700;">
                                <span class="badge <?= $rate >= 80 ? 'badge-present' : ($rate >= 50 ? 'badge-halfday' : 'badge-absent') ?>">
                                    <?= $rate ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- OFFICIAL VERIFICATION AND SIGNATURE BLOCK -->
    <div class="signature-grid">
        <div class="signature-box">
            <div class="role-title">Prepared By (HR Department)</div>
            <div class="sub">Human Resources Executive</div>
            <div style="height:32px;"></div>
            <div class="sub">Signature &amp; Date</div>
        </div>
        <div class="signature-box">
            <div class="role-title">Verified By (Operations Head)</div>
            <div class="sub">Operations / Team Lead</div>
            <div style="height:32px;"></div>
            <div class="sub">Signature &amp; Date</div>
        </div>
        <div class="signature-box">
            <div class="role-title">Authorized Signatory</div>
            <div class="sub">Managing Director / Super Admin</div>
            <div style="height:32px;"></div>
            <div class="sub">Official Agency Seal &amp; Signature</div>
        </div>
    </div>

    <!-- DOCUMENT FOOTER -->
    <div style="margin-top:28px; padding-top:12px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; font-size:10px; color:#94a3b8;">
        <span>Document ID: HEMI-ATT-<?= strtoupper(substr(md5($month . ($targetEmp['id'] ?? 'all')), 0, 8)) ?> • Confidential</span>
        <span>Hemito Digital Agency OS ERP v3.2 • Page 1 of 1</span>
        <span>www.hemitodigital.com</span>
    </div>

</div>

<?php if (!empty($_GET['autoprint'])): ?>
<script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 500);
    });
</script>
<?php endif; ?>

</body>
</html>
