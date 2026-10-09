<?php
// reset_database_clean.php - Script to clear all users and clients on Live Server

require_once __DIR__ . '/db.php';
$db = getDbConnection();

echo "<h2>HemiFlow Database Cleanup</h2>";

$tablesToDelete = [
    'sms_logs', 'client_tokens', 'client_notifications', 'client_meetings',
    'client_approvals', 'task_reviews', 'task_status_history', 'task_activities',
    'task_attachments', 'task_comments', 'task_assignments', 'escalations',
    'sla_records', 'tasks', 'projects', 'client_services', 'clients'
];

foreach ($tablesToDelete as $tbl) {
    try {
        $db->exec("DELETE FROM {$tbl}");
    } catch (Exception $e) {}
}

try {
    $db->exec("DELETE FROM user_module_access WHERE user_id != 1 AND user_id NOT IN (SELECT id FROM users WHERE username = 'admin')");
} catch (Exception $e) {}

// Delete all users except Super Admin
$db->exec("DELETE FROM users WHERE id != 1 AND username != 'admin'");

// Ensure Super Admin exists & active
$passHash = password_hash('password123', PASSWORD_BCRYPT);
$db->exec("UPDATE users SET password_hash = '{$passHash}', is_active = 1, role_id = 1 WHERE id = 1 OR username = 'admin'");

// Re-grant full module access to Super Admin
try {
    $adminId = $db->query("SELECT id FROM users WHERE username = 'admin' OR id = 1 LIMIT 1")->fetchColumn();
    if ($adminId) {
        $db->exec("DELETE FROM user_module_access WHERE user_id = {$adminId}");
        $stmtAcc = $db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (?, ?, 1, 1, 1)");
        foreach (['sales', 'client-management', 'template-generator', 'task-management', 'reports'] as $mod) {
            $stmtAcc->execute([$adminId, $mod]);
        }
    }
} catch (Exception $e) {}

echo "<div style='background:#dcfce7; color:#15803d; padding:15px; border-radius:8px; font-family:sans-serif;'>
    <h3>✅ Database Clean Complete!</h3>
    <p>All clients, tasks, projects, and non-admin users have been deleted.</p>
    <p>Primary Super Admin account <strong>admin</strong> (Password: <code>password123</code>) is ready for use.</p>
    <a href='login.php' style='display:inline-block; margin-top:10px; padding:10px 16px; background:#16a34a; color:white; text-decoration:none; border-radius:6px; font-weight:bold;'>Go to Login Page</a>
</div>";
