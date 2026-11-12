<?php
// hr_export.php - Secure CSV Export Utility for HR Management

require_once __DIR__ . '/auth.php';
checkAuth('hr-management');

$user = getCurrentUser();
$db = getDbConnection();
$isHr = isHrAdmin();

if (!$isHr && !isTeamLeadOrManager()) {
    http_response_code(403);
    die("Access Denied: Only HR staff and managers can export HR reports.");
}

$type = $_GET['type'] ?? 'employees';
$filename = "hr_export_" . $type . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

if ($type === 'employees') {
    // Export Employees Master List
    fputcsv($output, ['Employee ID', 'Full Name', 'Email', 'Phone', 'Department', 'Designation', 'Joining Date', 'Employment Type', 'Status', 'Work Location']);

    $stmt = $db->query("SELECT e.employee_code, e.full_name, e.email, e.phone, d.name as dept_name, des.title as desig_title,
                        e.date_of_joining, e.employment_type, e.employment_status, e.work_location
                        FROM employees e
                        LEFT JOIN departments d ON e.department_id = d.id
                        LEFT JOIN designations des ON e.designation_id = des.id
                        ORDER BY e.employee_code ASC");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['employee_code'],
            $row['full_name'],
            $row['email'],
            $row['phone'],
            $row['dept_name'] ?: 'General',
            $row['desig_title'] ?: 'Staff',
            $row['date_of_joining'],
            $row['employment_type'],
            $row['employment_status'],
            $row['work_location']
        ]);
    }

} elseif ($type === 'attendance') {
    // Export Attendance Summary
    $month = $_GET['month'] ?? date('Y-m');
    fputcsv($output, ['Employee ID', 'Full Name', 'Department', 'Date', 'Section 1 (Morning)', 'Section 2 (Afternoon)', 'Status', 'Total Hours', 'Notes / Audit']);

    $stmt = $db->prepare("SELECT e.employee_code, e.full_name, d.name as dept_name, a.attendance_date, a.section_1, a.section_2, a.check_in, a.check_out, a.total_hours, a.status, a.notes
                          FROM attendance a
                          JOIN employees e ON a.employee_id = e.id
                          LEFT JOIN departments d ON e.department_id = d.id
                          WHERE strftime('%Y-%m', a.attendance_date) = ? OR a.attendance_date LIKE ?
                          ORDER BY a.attendance_date DESC, e.full_name ASC");
    $stmt->execute([$month, "{$month}%"]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['employee_code'],
            $row['full_name'],
            $row['dept_name'] ?: 'General',
            $row['attendance_date'],
            $row['section_1'] ?: '-',
            $row['section_2'] ?: '-',
            $row['status'],
            $row['total_hours'],
            $row['notes'] ?: ''
        ]);
    }

} elseif ($type === 'leaves') {
    // Export Leave Requests
    fputcsv($output, ['Employee ID', 'Full Name', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Status', 'Reason', 'Reviewed By', 'Manager Comment', 'Applied At']);

    $stmt = $db->query("SELECT e.employee_code, e.full_name, d.name as dept_name, lt.name as leave_type_name,
                        lr.start_date, lr.end_date, lr.total_days, lr.status, lr.reason, u.full_name as reviewer_name,
                        lr.manager_comment, lr.created_at
                        FROM leave_requests lr
                        JOIN employees e ON lr.employee_id = e.id
                        JOIN leave_types lt ON lr.leave_type_id = lt.id
                        LEFT JOIN departments d ON e.department_id = d.id
                        LEFT JOIN users u ON lr.reviewed_by = u.id
                        ORDER BY lr.id DESC");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['employee_code'],
            $row['full_name'],
            $row['dept_name'] ?: 'General',
            $row['leave_type_name'],
            $row['start_date'],
            $row['end_date'],
            $row['total_days'],
            $row['status'],
            $row['reason'],
            $row['reviewer_name'] ?: 'N/A',
            $row['manager_comment'] ?: '',
            $row['created_at']
        ]);
    }

} elseif ($type === 'holidays') {
    // Export Holidays List
    fputcsv($output, ['Holiday Name', 'Date', 'Day', 'Description', 'Optional']);

    $stmt = $db->query("SELECT holiday_name, holiday_date, description, is_optional FROM holidays ORDER BY holiday_date ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['holiday_name'],
            $row['holiday_date'],
            date('l', strtotime($row['holiday_date'])),
            $row['description'] ?: '',
            $row['is_optional'] ? 'Yes' : 'No'
        ]);
    }
}

fclose($output);
logActivity($user['id'], 'hr-management', 'EXPORT_REPORT', 'reports', 0, "Exported HR CSV Report: {$type}");
exit;
