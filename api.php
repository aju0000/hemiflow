<?php
// api.php - Backend REST API Endpoints for Agency OS (Modules 3, 4, 9 Complete)

ob_start();
if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/auth.php';

$action = $_GET['action'] ?? '';
$db = getDbConnection();
ensureProjectWorkflowSchema($db);
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!function_exists('calculateTaskSLAStatus')) {
    function calculateTaskSLAStatus($task) {
        if ($task['status'] === 'COMPLETED') {
            if (!empty($task['completion_time']) && !empty($task['sla_due_time'])) {
                return strtotime($task['completion_time']) <= strtotime($task['sla_due_time']) 
                    ? 'Completed Within SLA' 
                    : 'Completed After SLA';
            }
            return 'Completed';
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

if (!function_exists('ensureDocumentTypesTable')) {
    function ensureDocumentTypesTable($db) {
        try {
            if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
                $db->exec("CREATE TABLE IF NOT EXISTS document_types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) UNIQUE NOT NULL,
                    description TEXT,
                    icon_name VARCHAR(50) DEFAULT 'file-text',
                    is_active TINYINT DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } else {
                $db->exec("CREATE TABLE IF NOT EXISTS document_types (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(100) UNIQUE NOT NULL,
                    description TEXT,
                    icon_name VARCHAR(50) DEFAULT 'file-text',
                    is_active INTEGER DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
            }

        $count = $db->query("SELECT COUNT(*) FROM document_types")->fetchColumn();
        if ($count == 0) {
            $defaults = [
                ['Proposal', 'Sales proposal and project pitch document', 'file-text'],
                ['Invoice', 'Tax & billing invoice document', 'credit-card'],
                ['Quotation', 'Price estimate and quotation', 'dollar-sign'],
                ['Contract', 'Binding commercial agreement', 'file-check'],
                ['Service Agreement', 'Service level and agreement document', 'shield'],
                ['MOM', 'Minutes of Meeting notes', 'clipboard'],
                ['Payment Reminder', 'Outstanding invoice payment reminder', 'bell'],
                ['Renewal Letter', 'Subscription / contract renewal letter', 'refresh-cw']
            ];
            $stmt = $db->prepare("INSERT INTO document_types (name, description, icon_name) VALUES (?, ?, ?)");
            foreach ($defaults as $d) {
                try {
                    $stmt->execute($d);
                } catch (Exception $e) {
                    // Ignore duplicates
                }
            }
        }
    } catch (Exception $ex) {
        // Silently handle DB table creation
    }
}
}

if (!function_exists('ensureUserNotificationsTable')) {
    function ensureUserNotificationsTable($db) {
        try {
            if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
                $db->exec("CREATE TABLE IF NOT EXISTS user_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    type VARCHAR(50) DEFAULT 'general',
                    link_url VARCHAR(255) DEFAULT '',
                    is_read TINYINT DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } else {
                $db->exec("CREATE TABLE IF NOT EXISTS user_notifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    type VARCHAR(50) DEFAULT 'general',
                    link_url VARCHAR(255) DEFAULT '',
                    is_read INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
            }
        } catch (Exception $e) {}
    }
}

if (!function_exists('notifyTaskAssignees')) {
    function notifyTaskAssignees($db, $taskId, $taskCode, $taskTitle, $assigneeIds, $assignedByUser, $projectName = '') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$assigneeIds), function ($x) { return $x > 0; })));
        if (empty($ids) || intval($taskId) <= 0) return;
        try {
            ensureUserNotificationsTable($db);
            $assignerName = trim($assignedByUser['full_name'] ?? ($assignedByUser['username'] ?? 'Team Lead'));
            $projSuffix = !empty($projectName) ? " in {$projectName}" : "";
            $title = "📋 Task Assigned: #{$taskCode}";
            $message = "{$assignerName} assigned task \"{$taskTitle}\"{$projSuffix} to you. Click to open task details.";
            $linkUrl = "task_details.php?id=" . intval($taskId);
            $stmt = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'task_assigned', ?, 0, CURRENT_TIMESTAMP)");
            foreach ($ids as $targetUid) {
                $stmt->execute([$targetUid, $title, $message, $linkUrl]);
            }
        } catch (Exception $e) {
            // Silently ignore optional notification errors
        }
    }
}

if (!function_exists('notifyProjectHandlers')) {
    function notifyProjectHandlers($db, $projectId, $projectName, $handlerIds, $assignedByUser) {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$handlerIds), function ($x) { return $x > 0; })));
        if (empty($ids) || intval($projectId) <= 0) return;
        try {
            ensureUserNotificationsTable($db);
            $assignerName = trim($assignedByUser['full_name'] ?? ($assignedByUser['username'] ?? 'Team Lead'));
            $title = "🛡️ Project Handler Assigned: {$projectName}";
            $message = "{$assignerName} added you as a Project Handler on \"{$projectName}\". Click to manage tasks, sub-tasks, and approvals.";
            $linkUrl = "project_details.php?id=" . intval($projectId);
            $stmt = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'project_handler_assigned', ?, 0, CURRENT_TIMESTAMP)");
            foreach ($ids as $targetUid) {
                $stmt->execute([$targetUid, $title, $message, $linkUrl]);
            }
        } catch (Exception $e) {}
    }
}

if (!function_exists('notifyProjectWorkingEmployees')) {
    function notifyProjectWorkingEmployees($db, $projectId, $projectName, $clientName, $memberIds, $assignedByUser) {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$memberIds), function ($x) { return $x > 0; })));
        if (empty($ids) || intval($projectId) <= 0) return;
        try {
            ensureUserNotificationsTable($db);
            $assignerName = trim($assignedByUser['full_name'] ?? ($assignedByUser['username'] ?? 'Project Handler'));
            $clientSuffix = !empty($clientName) ? " ({$clientName})" : "";
            $title = "👥 Added to Project Team: {$projectName}";
            $message = "{$assignerName} added you as a working team member on project \"{$projectName}\"{$clientSuffix}. Click to view project overview & tasks on your dashboard.";
            $linkUrl = "project_details.php?id=" . intval($projectId);
            $stmt = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'project_member_added', ?, 0, CURRENT_TIMESTAMP)");
            foreach ($ids as $targetUid) {
                $stmt->execute([$targetUid, $title, $message, $linkUrl]);
            }
        } catch (Exception $e) {}
    }
}

if (!function_exists('notifyProjectHandlersForReview')) {
    function notifyProjectHandlersForReview($db, $task, $submittedByUser) {
        if (!$task || empty($task['project_id'])) return;
        try {
            ensureUserNotificationsTable($db);
            $targets = getProjectHandlerIds($db, intval($task['project_id']));
            if (!empty($task['team_lead_id']) && intval($task['team_lead_id']) > 0) {
                $targets[] = intval($task['team_lead_id']);
            }
            $targets = array_values(array_unique(array_filter($targets, function ($uid) use ($submittedByUser) {
                return $uid > 0 && $uid !== intval($submittedByUser['id'] ?? 0);
            })));
            if (empty($targets)) return;
            $empName = trim($submittedByUser['full_name'] ?? ($submittedByUser['username'] ?? 'Employee'));
            $tCode = $task['task_code'] ?? ('TSK-' . intval($task['id']));
            $title = "🔍 Ready for Handler Approval: #{$tCode}";
            $message = "{$empName} submitted \"{$task['title']}\" for Project Handler approval before client review.";
            $linkUrl = "task_details.php?id=" . intval($task['id']);
            $stmt = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'task_review_requested', ?, 0, CURRENT_TIMESTAMP)");
            foreach ($targets as $uid) {
                $stmt->execute([$uid, $title, $message, $linkUrl]);
            }
        } catch (Exception $e) {}
    }
}

if (!function_exists('notifyLeadsForTaskApproval')) {
    function notifyLeadsForTaskApproval($db, $task, $approvedByHandlerUser) {
        if (!$task || empty($task['project_id'])) return;
        try {
            ensureUserNotificationsTable($db);
            $targets = [];
            if (!empty($task['team_lead_id']) && intval($task['team_lead_id']) > 0) {
                $targets[] = intval($task['team_lead_id']);
            }
            $stLeads = $db->query("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE COALESCE(u.is_active, 1) = 1 AND r.name IN ('Team Lead', 'DM Lead')");
            if ($stLeads) {
                while ($r = $stLeads->fetch()) {
                    $targets[] = intval($r['id']);
                }
            }
            $targets = array_values(array_unique(array_filter($targets, function ($uid) use ($approvedByHandlerUser) {
                return $uid > 0 && $uid !== intval($approvedByHandlerUser['id'] ?? 0);
            })));
            if (empty($targets)) return;
            $handlerName = trim($approvedByHandlerUser['full_name'] ?? ($approvedByHandlerUser['username'] ?? 'Project Handler'));
            $tCode = $task['task_code'] ?? ('TSK-' . intval($task['id']));
            $title = "📝 Lead Approval Required: #{$tCode}";
            $message = "{$handlerName} approved \"{$task['title']}\". Your Lead approval is required before the handler can complete this task.";
            $linkUrl = "task_details.php?id=" . intval($task['id']);
            $stmt = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'task_lead_approval_needed', ?, 0, CURRENT_TIMESTAMP)");
            foreach ($targets as $uid) {
                $stmt->execute([$uid, $title, $message, $linkUrl]);
            }
        } catch (Exception $e) {}
    }
}

if (!function_exists('notifyHandlerLeadApproved')) {
    function notifyHandlerLeadApproved($db, $task, $leadUser) {
        if (!$task || empty($task['project_id'])) return;
        try {
            ensureUserNotificationsTable($db);
            $targets = getProjectHandlerIds($db, intval($task['project_id']));
            $targets = array_values(array_unique(array_filter($targets, function ($uid) use ($leadUser) {
                return $uid > 0 && $uid !== intval($leadUser['id'] ?? 0);
            })));
            if (empty($targets)) return;
            $leadName = trim($leadUser['full_name'] ?? ($leadUser['username'] ?? 'Team Lead'));
            $tCode = $task['task_code'] ?? ('TSK-' . intval($task['id']));
            $title = "✅ Lead Approved Task: #{$tCode}";
            $message = "{$leadName} approved \"{$task['title']}\". You can now tick 'Approved by Client' to complete the task.";
            $linkUrl = "task_details.php?id=" . intval($task['id']);
            $stmt = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'task_lead_approved', ?, 0, CURRENT_TIMESTAMP)");
            foreach ($targets as $uid) {
                $stmt->execute([$uid, $title, $message, $linkUrl]);
            }
        } catch (Exception $e) {}
    }
}

if (!function_exists('notifyShootAssignees')) {
    function notifyShootAssignees($db, $shootId, $clientName, $shootDate, $venue, $assigneeIds, $assignedByUser) {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$assigneeIds), function ($x) { return $x > 0; })));
        if (empty($ids) || intval($shootId) <= 0) return;
        try {
            ensureUserNotificationsTable($db);
            $assignerName = trim($assignedByUser['full_name'] ?? ($assignedByUser['username'] ?? 'Super Admin'));
            $formattedDate = date('M d, Y h:i A', strtotime($shootDate));
            $title = "🎬 Shoot Scheduled: {$clientName}";
            $venueText = !empty($venue) ? " at {$venue}" : "";
            $message = "{$assignerName} assigned you to a shoot for {$clientName} on {$formattedDate}{$venueText}. Click to view details.";
            $linkUrl = "operations.php?tab=shoots&id=" . intval($shootId);
            $stmt = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'shoot_assigned', ?, 0, CURRENT_TIMESTAMP)");
            foreach ($ids as $targetUid) {
                $stmt->execute([$targetUid, $title, $message, $linkUrl]);
            }
        } catch (Exception $e) {}
    }
}

if (!function_exists('notifyRenewalAssignees')) {
    function notifyRenewalAssignees($db, $renewalId, $domainName, $clientName, $domainExpiry, $assigneeIds, $assignedByUser) {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$assigneeIds), function ($x) { return $x > 0; })));
        if (empty($ids) || intval($renewalId) <= 0) return;
        try {
            ensureUserNotificationsTable($db);
            $assignerName = trim($assignedByUser['full_name'] ?? ($assignedByUser['username'] ?? 'Super Admin'));
            $expiryStr = !empty($domainExpiry) ? (" (Expires: " . date('M d, Y', strtotime($domainExpiry)) . ")") : "";
            $title = "🌐 Renewal Incharge: {$domainName}";
            $message = "{$assignerName} assigned you as incharge for {$domainName} ({$clientName}){$expiryStr}. Click to view details.";
            $linkUrl = "operations.php?tab=renewals&id=" . intval($renewalId);
            $stmt = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'renewal_assigned', ?, 0, CURRENT_TIMESTAMP)");
            foreach ($ids as $targetUid) {
                $stmt->execute([$targetUid, $title, $message, $linkUrl]);
            }
        } catch (Exception $e) {}
    }
}

if (!function_exists('isTaskVisibleToUser')) {
    function isTaskVisibleToUser($task, $userId, $db = null) {
        $uid = intval($userId);
        if ($uid <= 0 || !$task) return false;
        if (intval($task['assigned_to'] ?? 0) === $uid) return true;
        if ($db && !empty($task['project_id']) && isProjectHandler($db, intval($task['project_id']), $uid)) {
            return true;
        }
        if ($db && !empty($task['project_id']) && function_exists('getProjectObserverIds')) {
            if (in_array($uid, getProjectObserverIds($db, intval($task['project_id'])), true)) {
                return true;
            }
        }

        $assignees = json_decode($task['assignees_json'] ?? '[]', true);
        if (is_array($assignees) && in_array($uid, array_map('intval', $assignees), true)) {
            return true;
        }

        $observers = json_decode($task['observers_json'] ?? '[]', true);
        if (is_array($observers) && in_array($uid, array_map('intval', $observers), true)) {
            return true;
        }

        if ($db && !empty($task['id'])) {
            try {
                $st = $db->prepare("SELECT 1 FROM task_assignments WHERE task_id = ? AND user_id = ? LIMIT 1");
                $st->execute([intval($task['id']), $uid]);
                if ($st->fetchColumn()) return true;
            } catch (Exception $e) {}
        }
        return false;
    }
}

if (!function_exists('buildTaskUserMatchWhere')) {
    function buildTaskUserMatchWhere($userId, &$params, $alias = 't', $db = null, $includeHandledProjects = true) {
        $uid = intval($userId);
        $like = '%' . $uid . '%';
        $params[] = $uid;
        $params[] = $uid;
        $params[] = $like;
        $params[] = $like;
        $clauses = [
            "{$alias}.assigned_to = ?",
            "EXISTS (SELECT 1 FROM task_assignments ta WHERE ta.task_id = {$alias}.id AND ta.user_id = ?)",
            "{$alias}.assignees_json LIKE ?",
            "{$alias}.observers_json LIKE ?"
        ];
        if ($includeHandledProjects && $db) {
            $handledPids = getUserHandledProjectIds($db, $uid);
            if (!empty($handledPids)) {
                $inPids = implode(',', array_map('intval', $handledPids));
                $clauses[] = "{$alias}.project_id IN ({$inPids})";
            }
        }
        return "(" . implode(" OR ", $clauses) . ")";
    }
}

try {
    switch ($action) {

        // --- AUTH API ---
        case 'login':
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            $module = $input['module'] ?? null;
            $res = loginUser($username, $password, $module);
            echo json_encode($res);
            break;

        case 'logout':
            session_destroy();
            echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
            break;

        case 'get_current_user':
            $user = getCurrentUser();
            echo json_encode(['success' => true, 'user' => $user]);
            break;

        case 'change_my_password':
            checkAuth();
            $currentUser = getCurrentUser();
            $userId = intval($currentUser['id'] ?? 0);

            $currentPassword = trim($input['current_password'] ?? '');
            $newPassword = trim($input['new_password'] ?? '');
            $confirmPassword = trim($input['confirm_password'] ?? '');

            if (empty($currentPassword) || empty($newPassword)) {
                echo json_encode(['success' => false, 'message' => 'Current password and new password are required.']);
                exit;
            }

            if (strlen($newPassword) < 6) {
                echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
                exit;
            }

            if (!empty($confirmPassword) && $newPassword !== $confirmPassword) {
                echo json_encode(['success' => false, 'message' => 'New password and confirmation password do not match.']);
                exit;
            }

            $stmtU = $db->prepare("SELECT password_hash, username FROM users WHERE id = ?");
            $stmtU->execute([$userId]);
            $u = $stmtU->fetch();

            if (!$u || !password_verify($currentPassword, $u['password_hash'])) {
                echo json_encode(['success' => false, 'message' => 'Incorrect current password provided.']);
                exit;
            }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmtUpd = $db->prepare("UPDATE users SET password_hash = ?, signup_token = NULL WHERE id = ?");
            $stmtUpd->execute([$newHash, $userId]);

            logActivity($userId, 'user-management', 'CHANGE_MY_PASSWORD', 'users', $userId, "User '@{$u['username']}' changed their account password");

            echo json_encode(['success' => true, 'message' => 'Your password has been updated successfully!']);
            break;

        case 'get_roles':
            checkAuth();
            $stmt = $db->query("SELECT * FROM roles ORDER BY id ASC");
            echo json_encode(['success' => true, 'roles' => $stmt->fetchAll()]);
            break;

        case 'create_role':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);
            $isTeamLead = ($currentUser['role'] === 'Team Lead' || ($currentUser['role_id'] ?? 0) == 4);

            if (!$isSuperAdmin && !$isTeamLead) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admins and Team Leads can create roles.']);
                exit;
            }

            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Role name is required.']);
                exit;
            }

            try {
                $stmtIns = $db->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
                $stmtIns->execute([$name, $description]);
                $newRoleId = $db->lastInsertId();
                logActivity($currentUser['id'], 'user-management', 'CREATE_ROLE', 'roles', $newRoleId, "Created role '{$name}'");
                echo json_encode(['success' => true, 'role_id' => $newRoleId, 'message' => "Role '{$name}' created successfully!"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => "Role '{$name}' already exists or failed to save."]);
            }
            break;

        case 'update_role':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);
            $isTeamLead = ($currentUser['role'] === 'Team Lead' || ($currentUser['role_id'] ?? 0) == 4);

            if (!$isSuperAdmin && !$isTeamLead) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admins and Team Leads can edit roles.']);
                exit;
            }

            $roleId = intval($input['id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');

            if (!$roleId || empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Role ID and Role Name are required.']);
                exit;
            }

            if (!$isSuperAdmin && $roleId == 1) {
                echo json_encode(['success' => false, 'message' => 'Security Error: Team Leads cannot edit the Super Admin role.']);
                exit;
            }

            try {
                $stmtUpd = $db->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
                $stmtUpd->execute([$name, $description, $roleId]);
                logActivity($currentUser['id'], 'user-management', 'UPDATE_ROLE', 'roles', $roleId, "Updated role ID {$roleId} to '{$name}'");
                echo json_encode(['success' => true, 'message' => "Role '{$name}' updated successfully!"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => "Failed to update role: " . $e->getMessage()]);
            }
            break;

        case 'delete_role':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);

            if (!$isSuperAdmin) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admin can delete roles.']);
                exit;
            }

            $roleId = intval($input['id'] ?? 0);
            if ($roleId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid Role ID.']);
                exit;
            }

            if (in_array($roleId, [1, 4, 5, 7])) {
                echo json_encode(['success' => false, 'message' => 'Security Error: Core system roles (Super Admin, Team Lead, Developer, Client) cannot be deleted.']);
                exit;
            }

            try {
                $db->prepare("UPDATE users SET role_id = 5 WHERE role_id = ?")->execute([$roleId]);
                $stmtDel = $db->prepare("DELETE FROM roles WHERE id = ?");
                $stmtDel->execute([$roleId]);

                logActivity($currentUser['id'], 'user-management', 'DELETE_ROLE', 'roles', $roleId, "Deleted role ID {$roleId}");
                echo json_encode(['success' => true, 'message' => "Role deleted successfully."]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => "Failed to delete role: " . $e->getMessage()]);
            }
            break;

        case 'get_users':
            checkAuth();
            $roleId = intval($_GET['role_id'] ?? 0);
            $dept = $_GET['department'] ?? null;

            $sql = "SELECT u.id, u.username, u.full_name, u.email, u.role_id, r.name as role_name, u.department, u.is_active, u.signup_token, u.created_at,
                           COALESCE(u.can_chat, 1) as can_chat, COALESCE(u.can_create_group, 1) as can_create_group
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE 1=1";
            $params = [];
            if ($roleId > 0) {
                $sql .= " AND u.role_id = ?";
                $params[] = $roleId;
            }
            if ($dept) {
                $sql .= " AND u.department = ?";
                $params[] = $dept;
            }
            $sql .= " ORDER BY u.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'users' => $stmt->fetchAll()]);
            break;

        case 'get_departments':
            checkAuth();
            try {
                $stmt = $db->query("SELECT * FROM departments ORDER BY name ASC");
                $depts = $stmt->fetchAll();
            } catch (Exception $e) {
                $depts = [];
            }
            if (empty($depts)) {
                $defaultNames = ['Development', 'Management', 'Sales', 'Design / UI/UX', 'Analytics', 'Support', 'Quality Assurance', 'Marketing'];
                $depts = array_map(function($d) { return ['name' => $d]; }, $defaultNames);
            }
            echo json_encode(['success' => true, 'departments' => $depts]);
            break;

        case 'create_department':
            checkAuth();
            $currentUser = getCurrentUser();
            $deptName = trim($input['name'] ?? '');
            if (empty($deptName)) {
                echo json_encode(['success' => false, 'message' => 'Department name is required.']);
                exit;
            }

            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);
            $isTeamLead = ($currentUser['role'] === 'Team Lead' || ($currentUser['role_id'] ?? 0) == 4);

            if (!$isSuperAdmin && !$isTeamLead) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admins and Team Leads can create departments.']);
                exit;
            }

            try {
                if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
                    $db->exec("CREATE TABLE IF NOT EXISTS departments (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) UNIQUE NOT NULL, created_by INT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                } else {
                    $db->exec("CREATE TABLE IF NOT EXISTS departments (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) UNIQUE NOT NULL, created_by INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
                }
                $stmtIns = $db->prepare("INSERT INTO departments (name, created_by) VALUES (?, ?)");
                $stmtIns->execute([$deptName, $currentUser['id']]);
                logActivity($currentUser['id'], 'user-management', 'CREATE_DEPARTMENT', 'departments', $db->lastInsertId(), "Created department '{$deptName}'");
                echo json_encode(['success' => true, 'name' => $deptName, 'message' => "Department '{$deptName}' created successfully!"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => "Department '{$deptName}' already exists or failed to save."]);
            }
            break;

        case 'update_department':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);
            $isTeamLead = ($currentUser['role'] === 'Team Lead' || ($currentUser['role_id'] ?? 0) == 4);

            if (!$isSuperAdmin && !$isTeamLead) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admins and Team Leads can edit departments.']);
                exit;
            }

            $deptId = intval($input['id'] ?? 0);
            $oldName = trim($input['old_name'] ?? '');
            $newName = trim($input['name'] ?? ($input['new_name'] ?? ''));

            if (empty($newName)) {
                echo json_encode(['success' => false, 'message' => 'New department name is required.']);
                exit;
            }

            try {
                if ($deptId > 0) {
                    $stmtGet = $db->prepare("SELECT name FROM departments WHERE id = ?");
                    $stmtGet->execute([$deptId]);
                    $oldName = $stmtGet->fetchColumn() ?: $oldName;

                    $stmtUpd = $db->prepare("UPDATE departments SET name = ? WHERE id = ?");
                    $stmtUpd->execute([$newName, $deptId]);
                } elseif (!empty($oldName)) {
                    $stmtUpd = $db->prepare("UPDATE departments SET name = ? WHERE name = ?");
                    $stmtUpd->execute([$newName, $oldName]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Department ID or current name required.']);
                    exit;
                }

                if (!empty($oldName)) {
                    $stmtUsers = $db->prepare("UPDATE users SET department = ? WHERE department = ?");
                    $stmtUsers->execute([$newName, $oldName]);
                }

                logActivity($currentUser['id'], 'user-management', 'UPDATE_DEPARTMENT', 'departments', $deptId, "Renamed department '{$oldName}' to '{$newName}'");
                echo json_encode(['success' => true, 'message' => "Department renamed to '{$newName}' successfully!"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => "Failed to update department: " . $e->getMessage()]);
            }
            break;

        case 'delete_department':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);
            $isTeamLead = ($currentUser['role'] === 'Team Lead' || ($currentUser['role_id'] ?? 0) == 4);

            if (!$isSuperAdmin && !$isTeamLead) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admins and Team Leads can delete departments.']);
                exit;
            }

            $deptId = intval($input['id'] ?? 0);
            $deptName = trim($input['name'] ?? '');

            if ($deptId <= 0 && !empty($deptName)) {
                $stmtFind = $db->prepare("SELECT id FROM departments WHERE name = ?");
                $stmtFind->execute([$deptName]);
                $deptId = (int)$stmtFind->fetchColumn();
            }

            if ($deptId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Department ID or name required.']);
                exit;
            }

            // Prevent deletion if employees are assigned
            $empCount = (int)$db->query("SELECT COUNT(*) FROM employees WHERE department_id = $deptId")->fetchColumn();
            if ($empCount > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete department: {$empCount} active employee(s) are assigned to it. Reassign employees first."]);
                exit;
            }

            // Prevent deletion if designations belong to it
            $desigCount = (int)$db->query("SELECT COUNT(*) FROM designations WHERE department_id = $deptId")->fetchColumn();
            if ($desigCount > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete department: {$desigCount} designation(s) belong to it. Reassign or remove designations first."]);
                exit;
            }

            $stmtGet = $db->prepare("SELECT name FROM departments WHERE id = ?");
            $stmtGet->execute([$deptId]);
            $deptName = $stmtGet->fetchColumn() ?: $deptName;
            $stmtDel = $db->prepare("DELETE FROM departments WHERE id = ?");
            $stmtDel->execute([$deptId]);

            if (!empty($deptName)) {
                $stmtRes = $db->prepare("UPDATE users SET department = 'General' WHERE department = ?");
                $stmtRes->execute([$deptName]);
            }

            logActivity($currentUser['id'], 'user-management', 'DELETE_DEPARTMENT', 'departments', $deptId, "Deleted department '{$deptName}'");
            echo json_encode(['success' => true, 'message' => "Department '{$deptName}' deleted successfully."]);
            break;

        case 'create_user':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);
            $isTeamLead = ($currentUser['role'] === 'Team Lead' || ($currentUser['role_id'] ?? 0) == 4);

            if (!$isSuperAdmin && !$isTeamLead) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admins and Team Leads can create user accounts.']);
                exit;
            }

            $username = trim($input['username'] ?? '');
            $password = trim($input['password'] ?? '');
            $fullName = trim($input['full_name'] ?? '');
            $email = trim($input['email'] ?? '');
            $roleId = intval($input['role_id'] ?? 0);
            $department = trim($input['department'] ?? 'General');
            $allowSelfSignup = !empty($input['set_own_password']);

            if (empty($username) || empty($fullName) || empty($email) || !$roleId) {
                echo json_encode(['success' => false, 'message' => 'Username, Full Name, Email Address, and Role are required.']);
                exit;
            }

            // TEAM LEAD SECURITY ENFORCEMENT: Team Leads cannot create Super Admin (role 1) or Team Lead (role 4) accounts!
            if ($isTeamLead && !$isSuperAdmin && ($roleId == 1 || $roleId == 4)) {
                echo json_encode(['success' => false, 'message' => 'Security Error: Team Leads can only create team member roles (Developer, Sales, Agency Admin, etc.) and cannot create Super Admin or Team Lead accounts.']);
                exit;
            }

            $stmtCheckU = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmtCheckU->execute([$username]);
            if ($stmtCheckU->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => "Username '{$username}' is already taken."]);
                exit;
            }

            $stmtCheckE = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmtCheckE->execute([$email]);
            if ($stmtCheckE->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => "Email '{$email}' is already registered."]);
                exit;
            }

            $signupToken = null;
            if ($allowSelfSignup || empty($password)) {
                $signupToken = bin2hex(random_bytes(16));
                $passHash = password_hash('PENDING_SIGNUP_' . bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
            } else {
                $passHash = password_hash($password, PASSWORD_BCRYPT);
            }

            try {
                $db->exec("ALTER TABLE users ADD COLUMN signup_token VARCHAR(100) DEFAULT NULL");
            } catch (Exception $e) {}
            try {
                $db->exec("ALTER TABLE users ADD COLUMN can_chat INTEGER DEFAULT 1");
            } catch (Exception $e) {}
            try {
                $db->exec("ALTER TABLE users ADD COLUMN can_create_group INTEGER DEFAULT 1");
            } catch (Exception $e) {}

            $canChat = ($roleId == 7) ? 0 : (isset($input['can_chat']) ? intval($input['can_chat']) : 1);
            $canCreateGroup = ($roleId == 7) ? 0 : (isset($input['can_create_group']) ? intval($input['can_create_group']) : 1);

            $stmtIns = $db->prepare("INSERT INTO users (username, password_hash, full_name, email, role_id, department, signup_token, can_chat, can_create_group, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmtIns->execute([$username, $passHash, $fullName, $email, $roleId, $department, $signupToken, $canChat, $canCreateGroup]);
            $newUserId = $db->lastInsertId();

            $modulesToGrant = [];
            if ($roleId == 1 || $roleId == 2) {
                $modulesToGrant = ['sales', 'client-management', 'template-generator', 'task-management', 'reports'];
            } elseif ($roleId == 3) {
                $modulesToGrant = ['sales', 'client-management', 'template-generator'];
            } elseif ($roleId == 4) {
                $modulesToGrant = ['task-management', 'client-management', 'template-generator'];
            } elseif ($roleId == 5) {
                $modulesToGrant = ['task-management'];
            } elseif ($roleId == 6) {
                $modulesToGrant = ['reports'];
            } else {
                $modulesToGrant = ['task-management'];
            }

            $stmtAcc = $db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (?, ?, 1, 1, 0)");
            foreach ($modulesToGrant as $mCode) {
                $stmtAcc->execute([$newUserId, $mCode]);
            }

            logActivity($currentUser['id'], 'user-management', 'CREATE_USER', 'users', $newUserId, "Created user {$username} ({$fullName}) with role ID {$roleId} in department {$department}");

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $signupUrl = $signupToken ? "{$protocol}{$host}/signup.php?token={$signupToken}" : null;

            echo json_encode([
                'success' => true,
                'user_id' => $newUserId,
                'signup_token' => $signupToken,
                'signup_url' => $signupUrl,
                'message' => $signupToken
                    ? "User account '{$fullName}' created! Invite link generated so user can create their own password."
                    : "User account '{$fullName}' ({$username}) created successfully with specified password!"
            ]);
            break;

        case 'update_user':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);
            $isTeamLead = ($currentUser['role'] === 'Team Lead' || ($currentUser['role_id'] ?? 0) == 4);

            if (!$isSuperAdmin && !$isTeamLead) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admins and Team Leads can edit user accounts.']);
                exit;
            }

            $targetUserId = intval($input['user_id'] ?? 0);
            $fullName = trim($input['full_name'] ?? '');
            $email = trim($input['email'] ?? '');
            $roleId = intval($input['role_id'] ?? 0);
            $department = trim($input['department'] ?? 'General');

            if (!$targetUserId || empty($fullName) || empty($email) || !$roleId) {
                echo json_encode(['success' => false, 'message' => 'User ID, Full Name, Email, and Role are required.']);
                exit;
            }

            $stmtU = $db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
            $stmtU->execute([$targetUserId]);
            $targetUser = $stmtU->fetch();

            if (!$targetUser) {
                echo json_encode(['success' => false, 'message' => 'User account not found.']);
                exit;
            }

            if (!$isSuperAdmin && ($targetUser['role_id'] == 1 || $targetUser['role_name'] === 'Super Admin')) {
                echo json_encode(['success' => false, 'message' => 'Security Error: Team Leads cannot edit Super Admin accounts.']);
                exit;
            }

            if (!$isSuperAdmin && ($roleId == 1 || $roleId == 4)) {
                echo json_encode(['success' => false, 'message' => 'Security Error: Team Leads cannot assign Super Admin or Team Lead roles.']);
                exit;
            }

            if ($email !== $targetUser['email']) {
                $stmtCheckE = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmtCheckE->execute([$email, $targetUserId]);
                if ($stmtCheckE->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => "Email '{$email}' is already in use by another account."]);
                    exit;
                }
            }

            $canChat = ($roleId == 7) ? 0 : (isset($input['can_chat']) ? intval($input['can_chat']) : intval($targetUser['can_chat'] ?? 1));
            $canCreateGroup = ($roleId == 7) ? 0 : (isset($input['can_create_group']) ? intval($input['can_create_group']) : intval($targetUser['can_create_group'] ?? 1));

            $stmtUpd = $db->prepare("UPDATE users SET full_name = ?, email = ?, role_id = ?, department = ?, can_chat = ?, can_create_group = ? WHERE id = ?");
            $stmtUpd->execute([$fullName, $email, $roleId, $department, $canChat, $canCreateGroup, $targetUserId]);

            if ($roleId != $targetUser['role_id']) {
                $db->prepare("DELETE FROM user_module_access WHERE user_id = ?")->execute([$targetUserId]);
                $modulesToGrant = [];
                if ($roleId == 1 || $roleId == 2) {
                    $modulesToGrant = ['sales', 'client-management', 'template-generator', 'task-management', 'reports'];
                } elseif ($roleId == 3) {
                    $modulesToGrant = ['sales', 'client-management', 'template-generator'];
                } elseif ($roleId == 4) {
                    $modulesToGrant = ['task-management', 'client-management', 'template-generator'];
                } elseif ($roleId == 5) {
                    $modulesToGrant = ['task-management'];
                } elseif ($roleId == 6) {
                    $modulesToGrant = ['reports'];
                } else {
                    $modulesToGrant = ['task-management'];
                }
                $stmtAcc = $db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (?, ?, 1, 1, 0)");
                foreach ($modulesToGrant as $mCode) {
                    $stmtAcc->execute([$targetUserId, $mCode]);
                }
            }

            logActivity($currentUser['id'], 'user-management', 'UPDATE_USER', 'users', $targetUserId, "Updated user details for '@{$targetUser['username']}'");
            echo json_encode(['success' => true, 'message' => "User '@{$targetUser['username']}' updated successfully."]);
            break;

        case 'update_chat_privileges':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);
            $isTeamLead = isTeamLeadOrManager();

            if (!$isSuperAdmin && !$isTeamLead) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admins and Leads can update chat privileges.']);
                exit;
            }

            $targetUserId = intval($input['user_id'] ?? 0);
            $canChat = intval($input['can_chat'] ?? 1);
            $canCreateGroup = intval($input['can_create_group'] ?? 1);
            if (!$canChat) $canCreateGroup = 0;

            if ($targetUserId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Valid User ID required.']);
                exit;
            }

            $stmtTarget = $db->prepare("SELECT id, full_name, role_id FROM users WHERE id = ?");
            $stmtTarget->execute([$targetUserId]);
            $tUser = $stmtTarget->fetch();
            if (!$tUser || intval($tUser['role_id']) === 7) {
                echo json_encode(['success' => false, 'message' => 'Internal employee not found.']);
                exit;
            }

            $db->prepare("UPDATE users SET can_chat = ?, can_create_group = ? WHERE id = ?")->execute([$canChat, $canCreateGroup, $targetUserId]);
            logActivity($currentUser['id'], 'user-management', 'UPDATE_CHAT_PRIVILEGES', 'users', $targetUserId, "Updated chat privileges for {$tUser['full_name']}: chat={$canChat}, create_group={$canCreateGroup}");
            echo json_encode([
                'success' => true,
                'can_chat' => $canChat,
                'can_create_group' => $canCreateGroup,
                'message' => "Chat privileges updated for {$tUser['full_name']}."
            ]);
            break;

        case 'import_users':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);
            $isTeamLead = ($currentUser['role'] === 'Team Lead' || ($currentUser['role_id'] ?? 0) == 4);

            if (!$isSuperAdmin && !$isTeamLead) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admins and Team Leads can import users.']);
                exit;
            }

            $usersList = $input['users'] ?? [];
            $setOwnPassword = !empty($input['set_own_password']);
            $defaultPassword = trim($input['default_password'] ?? 'Welcome@123');

            if (!is_array($usersList) || empty($usersList)) {
                echo json_encode(['success' => false, 'message' => 'No user data provided for import.']);
                exit;
            }

            $rolesList = $db->query("SELECT id, name FROM roles")->fetchAll();
            $roleMap = [];
            foreach ($rolesList as $r) {
                $roleMap[strtolower(trim($r['name']))] = intval($r['id']);
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($usersList as $idx => $row) {
                $fullName = trim($row['full_name'] ?? ($row['name'] ?? ''));
                $email = trim($row['email'] ?? '');
                $username = trim($row['username'] ?? '');
                $roleStr = strtolower(trim($row['role'] ?? ($row['role_name'] ?? 'developer')));
                $department = trim($row['department'] ?? 'General');

                if (empty($fullName) || empty($email)) {
                    $skipped++;
                    $errors[] = "Row #" . ($idx + 1) . ": Name and Email are required.";
                    continue;
                }

                if (empty($username)) {
                    $parts = explode('@', $email);
                    $username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $parts[0]));
                }

                $roleId = $roleMap[$roleStr] ?? 5;

                if ($isTeamLead && !$isSuperAdmin && ($roleId == 1 || $roleId == 4)) {
                    $roleId = 5;
                }

                $stmtCheck = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmtCheck->execute([$username, $email]);
                if ($stmtCheck->fetch()) {
                    $skipped++;
                    $errors[] = "Row #" . ($idx + 1) . ": User with email '{$email}' or username '{$username}' already exists.";
                    continue;
                }

                $signupToken = null;
                if ($setOwnPassword) {
                    $signupToken = bin2hex(random_bytes(16));
                    $passHash = password_hash('PENDING_SIGNUP_' . bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
                } else {
                    $passHash = password_hash($defaultPassword, PASSWORD_BCRYPT);
                }

                try {
                    $stmtIns = $db->prepare("INSERT INTO users (username, password_hash, full_name, email, role_id, department, signup_token, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmtIns->execute([$username, $passHash, $fullName, $email, $roleId, $department, $signupToken]);
                    $newId = $db->lastInsertId();

                    $modulesToGrant = ($roleId == 1 || $roleId == 2) 
                        ? ['sales', 'client-management', 'template-generator', 'task-management', 'reports']
                        : ['task-management'];
                    $stmtAcc = $db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (?, ?, 1, 1, 0)");
                    foreach ($modulesToGrant as $mCode) {
                        $stmtAcc->execute([$newId, $mCode]);
                    }

                    if (!empty($department) && $department !== 'General') {
                        try {
                            $stmtDept = $db->prepare("INSERT OR IGNORE INTO departments (name, created_by) VALUES (?, ?)");
                            $stmtDept->execute([$department, $currentUser['id']]);
                        } catch (Exception $ex) {}
                    }

                    $imported++;
                } catch (Exception $e) {
                    $skipped++;
                    $errors[] = "Row #" . ($idx + 1) . " ('{$email}'): " . $e->getMessage();
                }
            }

            logActivity($currentUser['id'], 'user-management', 'IMPORT_USERS', 'users', 0, "Bulk imported {$imported} users ({$skipped} skipped)");
            echo json_encode([
                'success' => true,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
                'message' => "Successfully imported {$imported} user account(s)." . ($skipped > 0 ? " {$skipped} row(s) skipped." : "")
            ]);
            break;

        case 'create_user':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);
            $isTeamLead = ($currentUser['role'] === 'Team Lead' || ($currentUser['role_id'] ?? 0) == 4);

            if (!$isSuperAdmin && !$isTeamLead) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admins and Team Leads can create user accounts.']);
                exit;
            }

            $username = trim($input['username'] ?? '');
            $password = trim($input['password'] ?? '');
            $fullName = trim($input['full_name'] ?? '');
            $email = trim($input['email'] ?? '');
            $roleId = intval($input['role_id'] ?? 0);
            $department = trim($input['department'] ?? 'General');
            $allowSelfSignup = !empty($input['set_own_password']);

            if (empty($username) || empty($fullName) || empty($email) || !$roleId) {
                echo json_encode(['success' => false, 'message' => 'Username, Full Name, Email Address, and Role are required.']);
                exit;
            }

            // TEAM LEAD SECURITY ENFORCEMENT: Team Leads cannot create Super Admin (role 1) or Team Lead (role 4) accounts!
            if ($isTeamLead && !$isSuperAdmin && ($roleId == 1 || $roleId == 4)) {
                echo json_encode(['success' => false, 'message' => 'Security Error: Team Leads can only create team member roles (Developer, Sales, Agency Admin, etc.) and cannot create Super Admin or Team Lead accounts.']);
                exit;
            }

            $stmtCheckU = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmtCheckU->execute([$username]);
            if ($stmtCheckU->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => "Username '{$username}' is already taken."]);
                exit;
            }

            $stmtCheckE = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmtCheckE->execute([$email]);
            if ($stmtCheckE->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => "Email '{$email}' is already registered."]);
                exit;
            }

            $signupToken = null;
            if ($allowSelfSignup || empty($password)) {
                $signupToken = bin2hex(random_bytes(16));
                $passHash = password_hash('PENDING_SIGNUP_' . bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
            } else {
                $passHash = password_hash($password, PASSWORD_BCRYPT);
            }

            try {
                $db->exec("ALTER TABLE users ADD COLUMN signup_token VARCHAR(100) DEFAULT NULL");
            } catch (Exception $e) {}
            try {
                $db->exec("ALTER TABLE users ADD COLUMN can_chat INTEGER DEFAULT 1");
            } catch (Exception $e) {}
            try {
                $db->exec("ALTER TABLE users ADD COLUMN can_create_group INTEGER DEFAULT 1");
            } catch (Exception $e) {}

            $canChat = ($roleId == 7) ? 0 : (isset($input['can_chat']) ? intval($input['can_chat']) : 1);
            $canCreateGroup = ($roleId == 7) ? 0 : (isset($input['can_create_group']) ? intval($input['can_create_group']) : 1);

            $stmtIns = $db->prepare("INSERT INTO users (username, password_hash, full_name, email, role_id, department, signup_token, can_chat, can_create_group, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmtIns->execute([$username, $passHash, $fullName, $email, $roleId, $department, $signupToken, $canChat, $canCreateGroup]);
            $newUserId = $db->lastInsertId();

            $modulesToGrant = [];
            if ($roleId == 1 || $roleId == 2) {
                $modulesToGrant = ['sales', 'client-management', 'template-generator', 'task-management', 'reports'];
            } elseif ($roleId == 3) {
                $modulesToGrant = ['sales', 'client-management', 'template-generator'];
            } elseif ($roleId == 4) {
                $modulesToGrant = ['task-management', 'client-management', 'template-generator'];
            } elseif ($roleId == 5) {
                $modulesToGrant = ['task-management'];
            } elseif ($roleId == 6) {
                $modulesToGrant = ['reports'];
            } else {
                $modulesToGrant = ['task-management'];
            }

            $stmtAcc = $db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (?, ?, 1, 1, 0)");
            foreach ($modulesToGrant as $mCode) {
                $stmtAcc->execute([$newUserId, $mCode]);
            }

            logActivity($currentUser['id'], 'user-management', 'CREATE_USER', 'users', $newUserId, "Created user {$username} ({$fullName}) with role ID {$roleId} in department {$department}");

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $signupUrl = $signupToken ? "{$protocol}{$host}/signup.php?token={$signupToken}" : null;

            echo json_encode([
                'success' => true,
                'user_id' => $newUserId,
                'signup_token' => $signupToken,
                'signup_url' => $signupUrl,
                'message' => $signupToken
                    ? "User account '{$fullName}' created! Invite link generated so user can create their own password."
                    : "User account '{$fullName}' ({$username}) created successfully with specified password!"
            ]);
            break;

        case 'verify_signup_token':
            $token = trim($_GET['token'] ?? '');
            $identifier = trim($_GET['identifier'] ?? '');

            if (empty($token) && empty($identifier)) {
                echo json_encode(['success' => false, 'message' => 'Token or Username/Email is required.']);
                exit;
            }

            if (!empty($token)) {
                $stmt = $db->prepare("SELECT u.id, u.username, u.full_name, u.email, r.name as role_name, u.department FROM users u JOIN roles r ON u.role_id = r.id WHERE u.signup_token = ?");
                $stmt->execute([$token]);
            } else {
                $stmt = $db->prepare("SELECT u.id, u.username, u.full_name, u.email, r.name as role_name, u.department FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? OR u.email = ?");
                $stmt->execute([$identifier, $identifier]);
            }
            $u = $stmt->fetch();

            if (!$u) {
                echo json_encode(['success' => false, 'message' => 'Account or signup invite link not found.']);
                exit;
            }

            echo json_encode(['success' => true, 'user' => $u]);
            break;

        case 'complete_signup':
            $token = trim($input['token'] ?? '');
            $identifier = trim($input['identifier'] ?? '');
            $newPassword = trim($input['password'] ?? '');

            if (empty($newPassword) || (empty($token) && empty($identifier))) {
                echo json_encode(['success' => false, 'message' => 'Valid invite token/username and new password are required.']);
                exit;
            }

            if (!empty($token)) {
                $stmt = $db->prepare("SELECT id, username, full_name FROM users WHERE signup_token = ?");
                $stmt->execute([$token]);
            } else {
                $stmt = $db->prepare("SELECT id, username, full_name FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$identifier, $identifier]);
            }
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                echo json_encode(['success' => false, 'message' => 'User account not found or invite token expired.']);
                exit;
            }

            $passHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmtUpd = $db->prepare("UPDATE users SET password_hash = ?, signup_token = NULL, is_active = 1 WHERE id = ?");
            $stmtUpd->execute([$passHash, $targetUser['id']]);

            echo json_encode([
                'success' => true,
                'username' => $targetUser['username'],
                'message' => "Password created successfully! You can now log in with your new password."
            ]);
            break;

        case 'toggle_user_status':
            checkAuth();
            $currentUser = getCurrentUser();
            $isSuperAdmin = ($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1);

            $targetUserId = intval($input['user_id'] ?? 0);
            $stmt = $db->prepare("SELECT u.is_active, u.username, u.role_id, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
            $stmt->execute([$targetUserId]);
            $u = $stmt->fetch();
            if (!$u) {
                echo json_encode(['success' => false, 'message' => 'User not found.']);
                exit;
            }

            // TEAM LEAD SECURITY ENFORCEMENT: Team Leads cannot modify Super Admin accounts!
            if (!$isSuperAdmin && ($u['role_id'] == 1 || $u['role_name'] === 'Super Admin')) {
                echo json_encode(['success' => false, 'message' => 'Security Error: Team Leads cannot modify or change status of Super Admin accounts.']);
                exit;
            }

            $newStatus = $u['is_active'] ? 0 : 1;
            $stmtUpd = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmtUpd->execute([$newStatus, $targetUserId]);

            $statusText = $newStatus ? 'activated' : 'deactivated';
            echo json_encode(['success' => true, 'is_active' => $newStatus, 'message' => "User account {$u['username']} has been {$statusText}."]);
            break;

        case 'delete_user':
            checkAuth();
            $currentUser = getCurrentUser();

            if (!($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1)) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admin can delete user accounts.']);
                exit;
            }

            $targetUserId = intval($input['user_id'] ?? 0);
            if ($targetUserId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid User ID provided.']);
                exit;
            }

            if ($targetUserId === intval($currentUser['id'])) {
                echo json_encode(['success' => false, 'message' => 'Security Error: You cannot delete your own active Super Admin account.']);
                exit;
            }

            $stmtU = $db->prepare("SELECT username, full_name FROM users WHERE id = ?");
            $stmtU->execute([$targetUserId]);
            $targetUser = $stmtU->fetch();

            if (!$targetUser) {
                echo json_encode(['success' => false, 'message' => 'User account not found.']);
                exit;
            }

            // Clean up user module access & unassign active tasks
            $db->prepare("DELETE FROM user_module_access WHERE user_id = ?")->execute([$targetUserId]);
            $db->prepare("UPDATE tasks SET assigned_to = NULL WHERE assigned_to = ?")->execute([$targetUserId]);

            // Delete user row
            $stmtDel = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmtDel->execute([$targetUserId]);

            logActivity($currentUser['id'], 'user-management', 'DELETE_USER', 'users', $targetUserId, "Super Admin deleted user account '@{$targetUser['username']}' ({$targetUser['full_name']})");

            echo json_encode([
                'success' => true,
                'user_id' => $targetUserId,
                'message' => "User account '@{$targetUser['username']}' deleted successfully."
            ]);
            break;


        // =========================================================================
        // 🎬 SHOOT SCHEDULE API
        // =========================================================================
        case 'get_shoot_schedules':
            checkAuth();
            ensureOperationsSchema($db);
            $user = getCurrentUser();
            $isSuperAdmin = isSuperAdminUser($user);

            $search = trim($_GET['search'] ?? '');
            $statusFilter = trim($_GET['status'] ?? '');
            $typeFilter = trim($_GET['shoot_type'] ?? '');
            $venueStatusFilter = trim($_GET['venue_blocked_status'] ?? '');

            $sql = "SELECT s.*, u.full_name as created_by_name, c.contact_person, c.phone as client_phone, c.email as client_email
                    FROM shoot_schedules s
                    LEFT JOIN users u ON s.created_by = u.id
                    LEFT JOIN clients c ON s.client_id = c.id";

            $whereClauses = [];
            $params = [];

            if (!$isSuperAdmin) {
                // Regular employees can only see shoots they are assigned to
                $sql .= " INNER JOIN shoot_assignments sa ON sa.shoot_id = s.id AND sa.user_id = ?";
                $params[] = $user['id'];
            }

            if (!empty($search)) {
                $whereClauses[] = "(s.client_name LIKE ? OR s.shoot_venue LIKE ? OR s.shoot_type LIKE ? OR s.notes LIKE ?)";
                $searchParam = "%{$search}%";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
            }

            if (!empty($statusFilter)) {
                $whereClauses[] = "s.status = ?";
                $params[] = $statusFilter;
            }

            if (!empty($typeFilter)) {
                $whereClauses[] = "s.shoot_type = ?";
                $params[] = $typeFilter;
            }

            if (!empty($venueStatusFilter)) {
                $whereClauses[] = "s.venue_blocked_status = ?";
                $params[] = $venueStatusFilter;
            }

            if (!empty($whereClauses)) {
                $sql .= " WHERE " . implode(" AND ", $whereClauses);
            }

            $sql .= " ORDER BY s.shoot_date ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $shoots = $stmt->fetchAll();

            // Attach assigned employee information and time calculations
            $stmtAssignees = $db->prepare("SELECT u.id, u.full_name, u.username, u.department, r.name as role_name 
                                           FROM shoot_assignments sa 
                                           JOIN users u ON sa.user_id = u.id 
                                           JOIN roles r ON u.role_id = r.id 
                                           WHERE sa.shoot_id = ? 
                                           ORDER BY u.full_name ASC");

            $nowTs = time();
            $stats = [
                'total_shoots' => 0,
                'upcoming_shoots' => 0,
                'scripts_approved_count' => 0,
                'venue_blocked_yes' => 0,
                'completed_shoots' => 0
            ];

            foreach ($shoots as &$s) {
                $stmtAssignees->execute([$s['id']]);
                $s['assigned_employees'] = $stmtAssignees->fetchAll();

                $shootTs = strtotime($s['shoot_date']);
                $s['shoot_timestamp'] = $shootTs;
                $s['is_upcoming'] = ($shootTs >= $nowTs);
                $s['formatted_date'] = date('D, M d, Y', $shootTs);
                $s['formatted_time'] = date('h:i A', $shootTs);

                $stats['total_shoots']++;
                if ($s['is_upcoming'] && $s['status'] !== 'Cancelled') $stats['upcoming_shoots']++;
                if (strcasecmp($s['scripts_approved'] ?? '', 'Yes') === 0) $stats['scripts_approved_count']++;
                if (strcasecmp($s['venue_blocked_status'] ?? '', 'Yes') === 0) $stats['venue_blocked_yes']++;
                if (strcasecmp($s['status'] ?? '', 'Completed') === 0) $stats['completed_shoots']++;
            }
            unset($s);

            echo json_encode([
                'success' => true,
                'is_super_admin' => $isSuperAdmin,
                'shoots' => $shoots,
                'stats' => $stats
            ]);
            break;

        case 'save_shoot_schedule':
            checkAuth();
            ensureOperationsSchema($db);
            $user = getCurrentUser();

            if (!isSuperAdminUser($user)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admin can schedule shoots and assign employees.']);
                exit;
            }

            $shootId = intval($input['id'] ?? 0);
            $clientId = !empty($input['client_id']) ? intval($input['client_id']) : null;
            $clientName = trim($input['client_name'] ?? '');
            $shootDate = trim($input['shoot_date'] ?? '');
            $videosPlanned = max(1, intval($input['videos_planned'] ?? 1));
            $scriptsApproved = trim($input['scripts_approved'] ?? 'Pending');
            $shootVenue = trim($input['shoot_venue'] ?? '');
            $venueBlockedStatus = trim($input['venue_blocked_status'] ?? 'Pending from Venue Side');
            $clientParticipants = max(0, intval($input['client_participants_count'] ?? 0));
            $shootType = trim($input['shoot_type'] ?? 'Client Informative Video');
            $status = trim($input['status'] ?? 'Scheduled');
            $notes = trim($input['notes'] ?? '');
            $assignedIds = isset($input['assigned_employee_ids']) ? (array)$input['assigned_employee_ids'] : [];

            if (empty($clientName) || empty($shootDate) || empty($shootType)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Client Name, Shoot Date, and Shoot Type are required.']);
                exit;
            }

            // Standardize shoot date format
            $shootDateFormatted = date('Y-m-d H:i:s', strtotime($shootDate));

            if ($shootId > 0) {
                // Update
                $stmt = $db->prepare("UPDATE shoot_schedules SET
                    client_id = ?,
                    client_name = ?,
                    shoot_date = ?,
                    videos_planned = ?,
                    scripts_approved = ?,
                    shoot_venue = ?,
                    venue_blocked_status = ?,
                    client_participants_count = ?,
                    shoot_type = ?,
                    status = ?,
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?");
                $stmt->execute([
                    $clientId, $clientName, $shootDateFormatted, $videosPlanned, $scriptsApproved,
                    $shootVenue, $venueBlockedStatus, $clientParticipants, $shootType, $status, $notes, $shootId
                ]);
                $actionType = 'UPDATE_SHOOT_SCHEDULE';
                $logMsg = "Updated shoot schedule for client '{$clientName}' ({$shootDateFormatted})";
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO shoot_schedules (
                    client_id, client_name, shoot_date, videos_planned, scripts_approved,
                    shoot_venue, venue_blocked_status, client_participants_count, shoot_type,
                    status, notes, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmt->execute([
                    $clientId, $clientName, $shootDateFormatted, $videosPlanned, $scriptsApproved,
                    $shootVenue, $venueBlockedStatus, $clientParticipants, $shootType,
                    $status, $notes, $user['id']
                ]);
                $shootId = intval($db->lastInsertId());
                $actionType = 'CREATE_SHOOT_SCHEDULE';
                $logMsg = "Scheduled new shoot for client '{$clientName}' on {$shootDateFormatted}";
            }

            // Sync assigned employees
            syncShootAssignments($db, $shootId, $assignedIds);

            // Notify assigned employees
            if (!empty($assignedIds)) {
                notifyShootAssignees($db, $shootId, $clientName, $shootDateFormatted, $shootVenue, $assignedIds, $user);
            }

            logActivity($user['id'], 'operations', $actionType, 'shoot_schedules', $shootId, $logMsg);

            echo json_encode([
                'success' => true,
                'shoot_id' => $shootId,
                'message' => "Shoot schedule for '{$clientName}' saved successfully!"
            ]);
            break;

        case 'delete_shoot_schedule':
            checkAuth();
            ensureOperationsSchema($db);
            $user = getCurrentUser();

            if (!isSuperAdminUser($user)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admin can delete shoot schedules.']);
                exit;
            }

            $shootId = intval($input['id'] ?? 0);
            if ($shootId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid shoot ID.']);
                exit;
            }

            $stmtGet = $db->prepare("SELECT client_name, shoot_date FROM shoot_schedules WHERE id = ?");
            $stmtGet->execute([$shootId]);
            $shoot = $stmtGet->fetch();

            if ($shoot) {
                $db->prepare("DELETE FROM shoot_assignments WHERE shoot_id = ?")->execute([$shootId]);
                $db->prepare("DELETE FROM shoot_schedules WHERE id = ?")->execute([$shootId]);
                logActivity($user['id'], 'operations', 'DELETE_SHOOT_SCHEDULE', 'shoot_schedules', $shootId, "Deleted shoot schedule for '{$shoot['client_name']}'");
            }

            echo json_encode(['success' => true, 'message' => 'Shoot schedule deleted successfully.']);
            break;

        case 'update_shoot_status':
            checkAuth();
            ensureOperationsSchema($db);
            $user = getCurrentUser();
            $shootId = intval($input['id'] ?? 0);

            if ($shootId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid shoot ID.']);
                exit;
            }

            // Permission check: Super Admin or assigned to this shoot
            $isSuperAdmin = isSuperAdminUser($user);
            if (!$isSuperAdmin) {
                $chk = $db->prepare("SELECT 1 FROM shoot_assignments WHERE shoot_id = ? AND user_id = ?");
                $chk->execute([$shootId, $user['id']]);
                if (!$chk->fetchColumn()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access Denied: You are not assigned to this shoot.']);
                    exit;
                }
            }

            $fieldsToUpdate = [];
            $params = [];

            if (isset($input['status'])) {
                $fieldsToUpdate[] = "status = ?";
                $params[] = trim($input['status']);
            }
            if (isset($input['scripts_approved'])) {
                $fieldsToUpdate[] = "scripts_approved = ?";
                $params[] = trim($input['scripts_approved']);
            }
            if (isset($input['venue_blocked_status'])) {
                $fieldsToUpdate[] = "venue_blocked_status = ?";
                $params[] = trim($input['venue_blocked_status']);
            }
            if (isset($input['notes'])) {
                $fieldsToUpdate[] = "notes = ?";
                $params[] = trim($input['notes']);
            }

            if (!empty($fieldsToUpdate)) {
                $fieldsToUpdate[] = "updated_at = CURRENT_TIMESTAMP";
                $params[] = $shootId;
                $sql = "UPDATE shoot_schedules SET " . implode(", ", $fieldsToUpdate) . " WHERE id = ?";
                $db->prepare($sql)->execute($params);
                logActivity($user['id'], 'operations', 'UPDATE_SHOOT_STATUS', 'shoot_schedules', $shootId, "Updated status/progress for shoot #{$shootId}");
            }

            echo json_encode(['success' => true, 'message' => 'Shoot details updated successfully!']);
            break;


        // =========================================================================
        // 🌐 DOMAIN & SERVER RENEWAL API
        // =========================================================================
        case 'get_domain_renewals':
            checkAuth();
            ensureOperationsSchema($db);
            $user = getCurrentUser();
            $isSuperAdmin = isSuperAdminUser($user);

            $search = trim($_GET['search'] ?? '');
            $statusFilter = trim($_GET['renewal_status'] ?? '');
            $expiringFilter = trim($_GET['expiring_window'] ?? ''); // '30_days', '60_days', 'expired'

            $sql = "SELECT r.*, u.full_name as created_by_name, c.company_name as client_company, c.contact_person as client_contact_person
                    FROM domain_server_renewals r
                    LEFT JOIN users u ON r.created_by = u.id
                    LEFT JOIN clients c ON r.client_id = c.id";

            $whereClauses = [];
            $params = [];

            if (!$isSuperAdmin) {
                // Regular employees can only see renewals they are assigned to
                $sql .= " INNER JOIN renewal_assignments ra ON ra.renewal_id = r.id AND ra.user_id = ?";
                $params[] = $user['id'];
            }

            if (!empty($search)) {
                $whereClauses[] = "(r.domain_name LIKE ? OR r.client_name LIKE ? OR r.contact_number LIKE ? OR r.hosting_provider LIKE ? OR r.notes LIKE ?)";
                $searchParam = "%{$search}%";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
            }

            if (!empty($statusFilter)) {
                $whereClauses[] = "r.renewal_status = ?";
                $params[] = $statusFilter;
            }

            if (!empty($whereClauses)) {
                $sql .= " WHERE " . implode(" AND ", $whereClauses);
            }

            $sql .= " ORDER BY COALESCE(r.domain_expiry, r.server_expiry) ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $renewals = $stmt->fetchAll();

            // Attach assigned employee information and renewal calculations
            $stmtAssignees = $db->prepare("SELECT u.id, u.full_name, u.username, u.department, r.name as role_name 
                                           FROM renewal_assignments ra 
                                           JOIN users u ON ra.user_id = u.id 
                                           JOIN roles r ON u.role_id = r.id 
                                           WHERE ra.renewal_id = ? 
                                           ORDER BY u.full_name ASC");

            $nowTs = time();
            $stats = [
                'total_records' => 0,
                'domains_expiring_soon' => 0,
                'servers_expiring_soon' => 0,
                'expired_count' => 0,
                'total_domain_charges' => 0.0,
                'total_server_charges' => 0.0
            ];

            $filteredRenewals = [];

            foreach ($renewals as &$r) {
                $stmtAssignees->execute([$r['id']]);
                $r['assigned_employees'] = $stmtAssignees->fetchAll();

                $domExpiryTs = !empty($r['domain_expiry']) ? strtotime($r['domain_expiry']) : null;
                $srvExpiryTs = !empty($r['server_expiry']) ? strtotime($r['server_expiry']) : null;

                $r['domain_days_left'] = $domExpiryTs ? (int)ceil(($domExpiryTs - $nowTs) / 86400) : null;
                $r['server_days_left'] = $srvExpiryTs ? (int)ceil(($srvExpiryTs - $nowTs) / 86400) : null;

                $r['formatted_domain_expiry'] = $domExpiryTs ? date('M d, Y', $domExpiryTs) : '—';
                $r['formatted_server_expiry'] = $srvExpiryTs ? date('M d, Y', $srvExpiryTs) : '—';

                $stats['total_records']++;
                $stats['total_domain_charges'] += floatval($r['domain_charges'] ?? 0);
                $stats['total_server_charges'] += floatval($r['server_charges'] ?? 0);

                $isDomExpiringSoon = ($r['domain_days_left'] !== null && $r['domain_days_left'] >= 0 && $r['domain_days_left'] <= 30);
                $isSrvExpiringSoon = ($r['server_days_left'] !== null && $r['server_days_left'] >= 0 && $r['server_days_left'] <= 30);
                $isExpired = ($r['domain_days_left'] !== null && $r['domain_days_left'] < 0) || ($r['server_days_left'] !== null && $r['server_days_left'] < 0);

                if ($isDomExpiringSoon) $stats['domains_expiring_soon']++;
                if ($isSrvExpiringSoon) $stats['servers_expiring_soon']++;
                if ($isExpired) $stats['expired_count']++;

                // Optional window filter
                if ($expiringFilter === '30_days' && !($isDomExpiringSoon || $isSrvExpiringSoon)) continue;
                if ($expiringFilter === 'expired' && !$isExpired) continue;

                $filteredRenewals[] = $r;
            }
            unset($r);

            echo json_encode([
                'success' => true,
                'is_super_admin' => $isSuperAdmin,
                'renewals' => $filteredRenewals,
                'stats' => $stats
            ]);
            break;

        case 'save_domain_renewal':
            checkAuth();
            ensureOperationsSchema($db);
            $user = getCurrentUser();

            if (!isSuperAdminUser($user)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admin can manage domain & server renewals.']);
                exit;
            }

            $renewalId = intval($input['id'] ?? 0);
            $clientId = !empty($input['client_id']) ? intval($input['client_id']) : null;
            $clientName = trim($input['client_name'] ?? '');
            $contactNumber = trim($input['contact_number'] ?? '');
            $domainName = trim($input['domain_name'] ?? '');
            $domainExpiry = !empty($input['domain_expiry']) ? date('Y-m-d', strtotime($input['domain_expiry'])) : null;
            $domainCharges = floatval($input['domain_charges'] ?? 0);
            $serverExpiry = !empty($input['server_expiry']) ? date('Y-m-d', strtotime($input['server_expiry'])) : null;
            $serverCharges = floatval($input['server_charges'] ?? 0);
            $renewalStatus = trim($input['renewal_status'] ?? 'Active');
            $hostingProvider = trim($input['hosting_provider'] ?? '');
            $notes = trim($input['notes'] ?? '');
            $assignedIds = isset($input['assigned_employee_ids']) ? (array)$input['assigned_employee_ids'] : [];

            if (empty($domainName) || empty($clientName)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Domain Name and Client Name are required.']);
                exit;
            }

            if ($renewalId > 0) {
                // Update
                $stmt = $db->prepare("UPDATE domain_server_renewals SET
                    client_id = ?,
                    client_name = ?,
                    contact_number = ?,
                    domain_name = ?,
                    domain_expiry = ?,
                    domain_charges = ?,
                    server_expiry = ?,
                    server_charges = ?,
                    renewal_status = ?,
                    hosting_provider = ?,
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?");
                $stmt->execute([
                    $clientId, $clientName, $contactNumber, $domainName,
                    $domainExpiry, $domainCharges, $serverExpiry, $serverCharges,
                    $renewalStatus, $hostingProvider, $notes, $renewalId
                ]);
                $actionType = 'UPDATE_DOMAIN_RENEWAL';
                $logMsg = "Updated domain & server renewal for '{$domainName}' ({$clientName})";
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO domain_server_renewals (
                    client_id, client_name, contact_number, domain_name,
                    domain_expiry, domain_charges, server_expiry, server_charges,
                    renewal_status, hosting_provider, notes, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $stmt->execute([
                    $clientId, $clientName, $contactNumber, $domainName,
                    $domainExpiry, $domainCharges, $serverExpiry, $serverCharges,
                    $renewalStatus, $hostingProvider, $notes, $user['id']
                ]);
                $renewalId = intval($db->lastInsertId());
                $actionType = 'CREATE_DOMAIN_RENEWAL';
                $logMsg = "Added domain & server renewal tracking for '{$domainName}' ({$clientName})";
            }

            // Sync assigned employees
            syncRenewalAssignments($db, $renewalId, $assignedIds);

            // Notify assigned employees
            if (!empty($assignedIds)) {
                notifyRenewalAssignees($db, $renewalId, $domainName, $clientName, $domainExpiry, $assignedIds, $user);
            }

            logActivity($user['id'], 'operations', $actionType, 'domain_server_renewals', $renewalId, $logMsg);

            echo json_encode([
                'success' => true,
                'renewal_id' => $renewalId,
                'message' => "Renewal record for '{$domainName}' saved successfully!"
            ]);
            break;

        case 'delete_domain_renewal':
            checkAuth();
            ensureOperationsSchema($db);
            $user = getCurrentUser();

            if (!isSuperAdminUser($user)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admin can delete renewal records.']);
                exit;
            }

            $renewalId = intval($input['id'] ?? 0);
            if ($renewalId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid renewal ID.']);
                exit;
            }

            $stmtGet = $db->prepare("SELECT domain_name, client_name FROM domain_server_renewals WHERE id = ?");
            $stmtGet->execute([$renewalId]);
            $ren = $stmtGet->fetch();

            if ($ren) {
                $db->prepare("DELETE FROM renewal_assignments WHERE renewal_id = ?")->execute([$renewalId]);
                $db->prepare("DELETE FROM domain_server_renewals WHERE id = ?")->execute([$renewalId]);
                logActivity($user['id'], 'operations', 'DELETE_DOMAIN_RENEWAL', 'domain_server_renewals', $renewalId, "Deleted renewal record for '{$ren['domain_name']}'");
            }

            echo json_encode(['success' => true, 'message' => 'Renewal record deleted successfully.']);
            break;

        case 'update_domain_renewal_status':
            checkAuth();
            ensureOperationsSchema($db);
            $user = getCurrentUser();
            $renewalId = intval($input['id'] ?? 0);

            if ($renewalId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid renewal ID.']);
                exit;
            }

            // Permission check: Super Admin or assigned to this renewal
            $isSuperAdmin = isSuperAdminUser($user);
            if (!$isSuperAdmin) {
                $chk = $db->prepare("SELECT 1 FROM renewal_assignments WHERE renewal_id = ? AND user_id = ?");
                $chk->execute([$renewalId, $user['id']]);
                if (!$chk->fetchColumn()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access Denied: You are not assigned to this renewal record.']);
                    exit;
                }
            }

            $fieldsToUpdate = [];
            $params = [];

            if (isset($input['renewal_status'])) {
                $fieldsToUpdate[] = "renewal_status = ?";
                $params[] = trim($input['renewal_status']);
            }
            if (isset($input['notes'])) {
                $fieldsToUpdate[] = "notes = ?";
                $params[] = trim($input['notes']);
            }

            if (!empty($fieldsToUpdate)) {
                $fieldsToUpdate[] = "updated_at = CURRENT_TIMESTAMP";
                $params[] = $renewalId;
                $sql = "UPDATE domain_server_renewals SET " . implode(", ", $fieldsToUpdate) . " WHERE id = ?";
                $db->prepare($sql)->execute($params);
                logActivity($user['id'], 'operations', 'UPDATE_RENEWAL_STATUS', 'domain_server_renewals', $renewalId, "Updated status/notes for renewal #{$renewalId}");
            }

            echo json_encode(['success' => true, 'message' => 'Renewal status updated successfully!']);
            break;


        // --- CLIENTS & SERVICES API ---
        case 'get_clients':
            checkAuth();
            $status = $_GET['status'] ?? null;
            $sql = "SELECT c.*, u.full_name as created_by_name, 
                    (SELECT COUNT(*) FROM client_services cs WHERE cs.client_id = c.id) as service_count,
                    (SELECT COUNT(*) FROM documents d WHERE d.client_id = c.id) as document_count,
                    (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id) as project_count
                    FROM clients c 
                    LEFT JOIN users u ON c.created_by = u.id";
            $params = [];
            if ($status) {
                $sql .= " WHERE c.status = ?";
                $params[] = $status;
            }
            $sql .= " ORDER BY c.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'clients' => $stmt->fetchAll()]);
            break;

        case 'create_client':
            checkAuth();
            $user = getCurrentUser();
            if (!($user['role'] === 'Super Admin' || ($user['role_id'] ?? 0) == 1 || hasModuleAccess('template-generator') || hasModuleAccess('client-management') || hasModuleAccess('sales'))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to create clients.']);
                exit;
            }

            $companyName = trim($input['company_name'] ?? '');
            $contactPerson = trim($input['contact_person'] ?? '');
            $email = trim($input['email'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $address = trim($input['address'] ?? '');
            $taxId = trim($input['tax_id'] ?? '');
            $status = $input['status'] ?? 'Approved / Active';
            $notes = trim($input['notes'] ?? '');

            $customUsername = trim($input['username'] ?? ($input['client_username'] ?? ''));
            $customPassword = trim($input['password'] ?? ($input['client_password'] ?? ''));

            if (empty($companyName) || empty($contactPerson) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Company Name, Contact Person, and Email are required.']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO clients (company_name, contact_person, email, phone, address, tax_id, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$companyName, $contactPerson, $email, $phone, $address, $taxId, $status, $notes, $user['id']]);
            $clientId = $db->lastInsertId();

            if (!empty($input['service_name'])) {
                $serviceName = trim($input['service_name']);
                $packageName = trim($input['package_name'] ?? 'Standard Package');
                $price = floatval($input['price'] ?? 0);
                $currency = $input['currency'] ?? 'INR';
                $billingTerms = $input['billing_terms'] ?? '50% Advance, 50% on Completion';
                $paymentTerms = $input['payment_terms'] ?? 'Net 15 days';

                $stmtSvc = $db->prepare("INSERT INTO client_services (client_id, service_name, package_name, price, currency, billing_terms, payment_terms) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtSvc->execute([$clientId, $serviceName, $packageName, $price, $currency, $billingTerms, $paymentTerms]);
            }

            // Auto provision Client Portal user account with custom or distinct credentials
            $clientUser = autoProvisionClientUser($db, $clientId, $customUsername, $customPassword);

            logActivity($user['id'], 'client-management', 'CREATE_CLIENT', 'clients', $clientId, "Created client {$companyName}");

            $msg = "Client '{$companyName}' created successfully.";
            if ($clientUser) {
                if ($clientUser['created']) {
                    $msg .= " Client Portal Account Created — Username: @{$clientUser['username']} | Password: {$clientUser['temp_password']}";
                } else {
                    $msg .= " Client Portal Account Linked — Username: @{$clientUser['username']}";
                }
            }

            echo json_encode(['success' => true, 'client_id' => $clientId, 'client_user' => $clientUser, 'message' => $msg]);
            break;

        case 'import_clients':
            checkAuth();
            $user = getCurrentUser();
            if (!($user['role'] === 'Super Admin' || ($user['role_id'] ?? 0) == 1 || hasModuleAccess('client-management') || hasModuleAccess('sales'))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to import clients.']);
                exit;
            }

            $clientsList = $input['clients'] ?? [];
            if (!is_array($clientsList) || empty($clientsList)) {
                echo json_encode(['success' => false, 'message' => 'No client rows provided for import.']);
                exit;
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];

            $existingEmails = [];
            foreach ($db->query("SELECT LOWER(TRIM(email)) FROM clients WHERE email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_COLUMN) as $em) {
                $existingEmails[$em] = true;
            }

            $stmtIns = $db->prepare("INSERT INTO clients (company_name, contact_person, email, phone, address, tax_id, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtSvc = $db->prepare("INSERT INTO client_services (client_id, service_name, package_name, price, currency, billing_terms, payment_terms) VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($clientsList as $idx => $r) {
                $company = trim($r['company_name'] ?? ($r['company'] ?? ''));
                $contact = trim($r['contact_person'] ?? ($r['contact'] ?? ''));
                $email = strtolower(trim($r['email'] ?? ''));
                $phone = trim($r['phone'] ?? '');
                $address = trim($r['address'] ?? '');
                $taxId = trim($r['tax_id'] ?? '');
                $status = trim($r['status'] ?? 'Approved / Active');
                if (!$status) $status = 'Approved / Active';
                $notes = trim($r['notes'] ?? '');

                if (empty($company)) {
                    $skipped++;
                    continue;
                }
                if (empty($contact)) {
                    $contact = $company;
                }
                if (empty($email)) {
                    $emailSlug = strtolower(preg_replace('/[^a-z0-9]/', '', $company));
                    $email = ($emailSlug ?: 'client' . time()) . '@client.local';
                }

                if (isset($existingEmails[$email])) {
                    $skipped++;
                    continue;
                }

                try {
                    $stmtIns->execute([$company, $contact, $email, $phone, $address, $taxId, $status, $notes, $user['id']]);
                    $newClientId = $db->lastInsertId();
                    $existingEmails[$email] = true;

                    $pkgName = trim($r['package_name'] ?? ($r['package'] ?? ($r['service'] ?? '')));
                    if (!empty($pkgName)) {
                        $price = floatval($r['price'] ?? 0);
                        $stmtSvc->execute([$newClientId, $pkgName, $pkgName, $price, 'INR', 'Standard', 'Net 15']);
                    }

                    if (function_exists('autoProvisionClientUser')) {
                        autoProvisionClientUser($db, $newClientId);
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($idx + 1) . ": " . $e->getMessage();
                    $skipped++;
                }
            }

            logActivity($user['id'], 'client-management', 'IMPORT_CLIENTS', 'clients', 0, "Bulk imported {$imported} clients ({$skipped} skipped)");

            echo json_encode([
                'success' => true,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
                'message' => "Successfully imported {$imported} client(s)." . ($skipped > 0 ? " ({$skipped} skipped/duplicate)" : "")
            ]);
            break;

        case 'update_client':
            checkAuth();
            $currentUser = getCurrentUser();
            if (!($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1 || hasModuleAccess('client-management') || hasModuleAccess('sales'))) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to update client details.']);
                exit;
            }

            $clientId = intval($input['client_id'] ?? $input['id'] ?? 0);
            $companyName = trim($input['company_name'] ?? '');
            $contactPerson = trim($input['contact_person'] ?? '');
            $email = trim($input['email'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $taxId = trim($input['tax_id'] ?? '');
            $status = $input['status'] ?? 'Approved / Active';
            $notes = trim($input['notes'] ?? '');

            if (!$clientId || empty($companyName) || empty($contactPerson) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Client ID, Company Name, Contact Person, and Email are required.']);
                exit;
            }

            $stmtUpd = $db->prepare("UPDATE clients SET company_name = ?, contact_person = ?, email = ?, phone = ?, tax_id = ?, status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$companyName, $contactPerson, $email, $phone, $taxId, $status, $notes, $clientId]);

            logActivity($currentUser['id'], 'client-management', 'UPDATE_CLIENT', 'clients', $clientId, "Updated client details for {$companyName}");

            echo json_encode(['success' => true, 'message' => "Client '{$companyName}' updated successfully."]);
            break;

        case 'reset_client_password':
            checkAuth();
            $currentUser = getCurrentUser();

            if (!($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1 || hasModuleAccess('client-management') || hasModuleAccess('sales'))) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to reset client portal passwords.']);
                exit;
            }

            $clientId = intval($input['client_id'] ?? 0);
            $newPassword = trim($input['password'] ?? '');

            if (!$clientId || empty($newPassword)) {
                echo json_encode(['success' => false, 'message' => 'Client ID and New Password are required.']);
                exit;
            }

            // Find client user
            $stmtU = $db->prepare("SELECT id, username FROM users WHERE client_id = ? AND role_id = 7");
            $stmtU->execute([$clientId]);
            $userAcc = $stmtU->fetch();

            if (!$userAcc) {
                // If user account doesn't exist yet, auto-provision with this password
                $clientUser = autoProvisionClientUser($db, $clientId, null, $newPassword);
                echo json_encode([
                    'success' => true,
                    'username' => $clientUser['username'],
                    'message' => "Client Portal account created for user '@{$clientUser['username']}' with the specified password."
                ]);
                exit;
            }

            $passHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmtUpd = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmtUpd->execute([$passHash, $userAcc['id']]);

            logActivity($currentUser['id'], 'client-management', 'RESET_CLIENT_PASSWORD', 'users', $userAcc['id'], "Super Admin reset password for client portal user '@{$userAcc['username']}'");

            echo json_encode([
                'success' => true,
                'username' => $userAcc['username'],
                'message' => "Password for Client Portal account '@{$userAcc['username']}' updated successfully."
            ]);
            break;

        case 'get_client_full_details':
            checkAuth();
            $clientId = intval($_GET['client_id'] ?? 0);
            if ($clientId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Valid Client ID is required.']);
                exit;
            }

            // 1. Client profile & credentials
            $stmt = $db->prepare("SELECT c.*, u.full_name as created_by_name,
                                    usr.id as client_user_id, usr.username as client_username, usr.email as client_user_email, usr.is_active as client_user_active
                                    FROM clients c
                                    LEFT JOIN users u ON c.created_by = u.id
                                    LEFT JOIN users usr ON usr.client_id = c.id
                                    WHERE c.id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch();
            if (!$client) {
                echo json_encode(['success' => false, 'message' => 'Client account not found.']);
                exit;
            }

            // 2. Client Service Packages
            $stmtPkg = $db->prepare("SELECT * FROM client_services WHERE client_id = ? ORDER BY id DESC");
            $stmtPkg->execute([$clientId]);
            $packages = $stmtPkg->fetchAll();

            // 3. Projects for this Client with live task breakdown & progress
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

            // 4. Tasks for this client across all projects
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

            foreach ($tasks as &$t) {
                $t['sla_status'] = calculateTaskSLAStatus($t);
                $t['assignee_ids'] = json_decode($t['assignees_json'] ?? '[]', true) ?: [];
                $t['observer_ids'] = json_decode($t['observers_json'] ?? '[]', true) ?: [];
            }
            unset($t);

            // 5. Client Documents
            $stmtDocs = $db->prepare("SELECT id, document_number, document_type, title, status, version, created_at FROM documents WHERE client_id = ? ORDER BY id DESC");
            $stmtDocs->execute([$clientId]);
            $documents = $stmtDocs->fetchAll();

            // Calculate overall aggregates
            $totalProjects = count($projects);
            $totalTasks = count($tasks);
            $completedTasks = 0;
            $inProgressTasks = 0;
            $reviewTasks = 0;
            $pendingTasks = 0;
            foreach ($tasks as $t) {
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
            $overallProgress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

            echo json_encode([
                'success' => true,
                'client' => $client,
                'metrics' => [
                    'total_projects' => $totalProjects,
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'review_tasks' => $reviewTasks,
                    'pending_tasks' => $pendingTasks,
                    'overall_progress_pct' => $overallProgress
                ],
                'projects' => $projects,
                'tasks' => $tasks,
                'packages' => $packages,
                'documents' => $documents
            ]);
            break;

        case 'reset_user_password':
            checkAuth();
            $currentUser = getCurrentUser();

            if (!($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1)) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admin can change or reset account passwords.']);
                exit;
            }

            $targetUserId = intval($input['user_id'] ?? 0);
            $newPassword = trim($input['password'] ?? '');

            if (!$targetUserId || empty($newPassword)) {
                echo json_encode(['success' => false, 'message' => 'User ID and New Password are required.']);
                exit;
            }

            $stmtU = $db->prepare("SELECT username, full_name FROM users WHERE id = ?");
            $stmtU->execute([$targetUserId]);
            $targetUser = $stmtU->fetch();

            if (!$targetUser) {
                echo json_encode(['success' => false, 'message' => 'User account not found.']);
                exit;
            }

            $passHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmtUpd = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmtUpd->execute([$passHash, $targetUserId]);

            logActivity($currentUser['id'], 'user-management', 'RESET_USER_PASSWORD', 'users', $targetUserId, "Super Admin reset password for user '@{$targetUser['username']}'");

            echo json_encode([
                'success' => true,
                'user_id' => $targetUserId,
                'message' => "Password for user account '@{$targetUser['username']}' ({$targetUser['full_name']}) updated successfully."
            ]);
            break;

        case 'delete_client':
            checkAuth();
            $currentUser = getCurrentUser();

            if (!($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1)) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admin can delete client accounts.']);
                exit;
            }

            $targetClientId = intval($input['client_id'] ?? 0);
            if ($targetClientId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid Client ID provided.']);
                exit;
            }

            $stmtC = $db->prepare("SELECT company_name FROM clients WHERE id = ?");
            $stmtC->execute([$targetClientId]);
            $client = $stmtC->fetch();

            if (!$client) {
                echo json_encode(['success' => false, 'message' => 'Client account not found.']);
                exit;
            }

            // Clean up dependent client records in correct foreign key dependency order
            $db->beginTransaction();
            try {
                // 1. Delete document versions and documents
                // Note: documents.service_id references client_services.id, so documents must be deleted before client_services
                $db->prepare("DELETE FROM document_versions WHERE document_id IN (SELECT id FROM documents WHERE client_id = ?)")->execute([$targetClientId]);
                $db->prepare("DELETE FROM documents WHERE client_id = ?")->execute([$targetClientId]);

                // 2. Delete task dependencies, client approvals, and tasks
                $db->prepare("DELETE FROM task_assignments WHERE task_id IN (SELECT id FROM tasks WHERE client_id = ? OR project_id IN (SELECT id FROM projects WHERE client_id = ?))")->execute([$targetClientId, $targetClientId]);
                $db->prepare("DELETE FROM task_activities WHERE task_id IN (SELECT id FROM tasks WHERE client_id = ? OR project_id IN (SELECT id FROM projects WHERE client_id = ?))")->execute([$targetClientId, $targetClientId]);
                $db->prepare("DELETE FROM task_comments WHERE task_id IN (SELECT id FROM tasks WHERE client_id = ? OR project_id IN (SELECT id FROM projects WHERE client_id = ?))")->execute([$targetClientId, $targetClientId]);
                $db->prepare("DELETE FROM task_attachments WHERE task_id IN (SELECT id FROM tasks WHERE client_id = ? OR project_id IN (SELECT id FROM projects WHERE client_id = ?))")->execute([$targetClientId, $targetClientId]);
                $db->prepare("DELETE FROM task_reviews WHERE task_id IN (SELECT id FROM tasks WHERE client_id = ? OR project_id IN (SELECT id FROM projects WHERE client_id = ?))")->execute([$targetClientId, $targetClientId]);
                $db->prepare("DELETE FROM client_approvals WHERE client_id = ? OR project_id IN (SELECT id FROM projects WHERE client_id = ?)")->execute([$targetClientId, $targetClientId]);
                $db->prepare("DELETE FROM tasks WHERE client_id = ? OR project_id IN (SELECT id FROM projects WHERE client_id = ?)")->execute([$targetClientId, $targetClientId]);

                // 3. Delete projects
                $db->prepare("DELETE FROM projects WHERE client_id = ?")->execute([$targetClientId]);

                // 4. Delete client services (now safe since documents referencing them are deleted)
                $db->prepare("DELETE FROM client_services WHERE client_id = ?")->execute([$targetClientId]);

                // 5. Delete client notifications, meetings, tokens, and logs
                $db->prepare("DELETE FROM client_notifications WHERE client_id = ?")->execute([$targetClientId]);
                $db->prepare("DELETE FROM client_meetings WHERE client_id = ?")->execute([$targetClientId]);
                $db->prepare("DELETE FROM client_tokens WHERE client_id = ?")->execute([$targetClientId]);
                $db->prepare("DELETE FROM sms_logs WHERE client_id = ?")->execute([$targetClientId]);

                // 6. Delete associated client portal user accounts & module access
                $db->prepare("DELETE FROM user_module_access WHERE user_id IN (SELECT id FROM users WHERE client_id = ?)")->execute([$targetClientId]);
                $db->prepare("DELETE FROM users WHERE client_id = ?")->execute([$targetClientId]);

                // 7. Delete client row
                $stmtDel = $db->prepare("DELETE FROM clients WHERE id = ?");
                $stmtDel->execute([$targetClientId]);

                $db->commit();
            } catch (Exception $delEx) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $delEx;
            }

            logActivity($currentUser['id'], 'client-management', 'DELETE_CLIENT', 'clients', $targetClientId, "Super Admin deleted client '{$client['company_name']}' and all associated records");

            echo json_encode([
                'success' => true,
                'client_id' => $targetClientId,
                'message' => "Client '{$client['company_name']}' and all associated records deleted successfully."
            ]);
            break;

        case 'get_client_services':
            checkAuth();
            $clientId = intval($_GET['client_id'] ?? 0);
            if ($clientId > 0) {
                $stmt = $db->prepare("SELECT cs.*, c.company_name FROM client_services cs JOIN clients c ON cs.client_id = c.id WHERE cs.client_id = ? ORDER BY cs.id DESC");
                $stmt->execute([$clientId]);
            } else {
                $stmt = $db->prepare("SELECT cs.*, c.company_name FROM client_services cs JOIN clients c ON cs.client_id = c.id ORDER BY cs.id DESC");
                $stmt->execute();
            }
            echo json_encode(['success' => true, 'services' => $stmt->fetchAll()]);
            break;

        case 'add_service_package':
            checkAuth();
            $currentUser = getCurrentUser();
            $clientId = intval($input['client_id'] ?? 0);
            $serviceName = trim($input['service_name'] ?? '');
            $packageName = trim($input['package_name'] ?? 'Standard Package');
            $price = floatval($input['price'] ?? 0);
            $currency = trim($input['currency'] ?? 'INR');
            $billingTerms = trim($input['billing_terms'] ?? 'Monthly in advance');
            $paymentTerms = trim($input['payment_terms'] ?? 'Net 15 days');
            $status = trim($input['status'] ?? 'Active');

            if ($clientId <= 0 || empty($serviceName)) {
                echo json_encode(['success' => false, 'message' => 'Client ID and Service Name are required.']);
                exit;
            }

            $stmtChk = $db->prepare("SELECT company_name FROM clients WHERE id = ?");
            $stmtChk->execute([$clientId]);
            $clientComp = $stmtChk->fetchColumn();
            if (!$clientComp) {
                echo json_encode(['success' => false, 'message' => 'Selected client does not exist.']);
                exit;
            }

            $stmtIns = $db->prepare("INSERT INTO client_services (client_id, service_name, package_name, price, currency, billing_terms, payment_terms, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([$clientId, $serviceName, $packageName, $price, $currency, $billingTerms, $paymentTerms, $status]);
            $newId = $db->lastInsertId();

            logActivity($currentUser['id'], 'sales', 'ADD_SERVICE_PACKAGE', 'client_services', $newId, "Added service package '{$packageName}' ({$serviceName}) for client {$clientComp}");

            echo json_encode([
                'success' => true,
                'service_id' => $newId,
                'message' => "Service package '{$packageName}' added successfully for {$clientComp}."
            ]);
            break;

        case 'update_service_package':
            checkAuth();
            $currentUser = getCurrentUser();
            $id = intval($input['id'] ?? $input['service_id'] ?? 0);
            $clientId = intval($input['client_id'] ?? 0);
            $serviceName = trim($input['service_name'] ?? '');
            $packageName = trim($input['package_name'] ?? 'Standard Package');
            $price = floatval($input['price'] ?? 0);
            $currency = trim($input['currency'] ?? 'INR');
            $billingTerms = trim($input['billing_terms'] ?? 'Monthly in advance');
            $paymentTerms = trim($input['payment_terms'] ?? 'Net 15 days');
            $status = trim($input['status'] ?? 'Active');

            if ($id <= 0 || empty($serviceName)) {
                echo json_encode(['success' => false, 'message' => 'Valid Service Package ID and Service Name are required.']);
                exit;
            }

            $stmtUpd = $db->prepare("UPDATE client_services SET client_id = ?, service_name = ?, package_name = ?, price = ?, currency = ?, billing_terms = ?, payment_terms = ?, status = ? WHERE id = ?");
            $stmtUpd->execute([$clientId, $serviceName, $packageName, $price, $currency, $billingTerms, $paymentTerms, $status, $id]);

            logActivity($currentUser['id'], 'sales', 'UPDATE_SERVICE_PACKAGE', 'client_services', $id, "Updated service package '{$packageName}'");

            echo json_encode([
                'success' => true,
                'message' => "Service package '{$packageName}' updated successfully."
            ]);
            break;

        case 'delete_service_package':
            checkAuth();
            $currentUser = getCurrentUser();
            $id = intval($input['id'] ?? $input['service_id'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid service package ID.']);
                exit;
            }

            $db->prepare("DELETE FROM client_services WHERE id = ?")->execute([$id]);

            logActivity($currentUser['id'], 'sales', 'DELETE_SERVICE_PACKAGE', 'client_services', $id, "Deleted service package #{$id}");

            echo json_encode(['success' => true, 'message' => 'Service package deleted successfully.']);
            break;

        // --- DOCUMENT TYPES API ---
        case 'get_document_types':
            checkAuth();
            ensureDocumentTypesTable($db);
            $stmt = $db->query("SELECT dt.*, (SELECT COUNT(*) FROM document_templates t WHERE t.document_type = dt.name) as template_count, (SELECT COUNT(*) FROM documents d WHERE d.document_type = dt.name) as document_count FROM document_types dt WHERE dt.is_active = 1 ORDER BY dt.name ASC");
            echo json_encode(['success' => true, 'document_types' => $stmt->fetchAll()]);
            break;

        case 'add_document_type':
            checkAuth();
            ensureDocumentTypesTable($db);
            $currentUser = getCurrentUser();
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Document type name is required.']);
                exit;
            }

            $stmtChk = $db->prepare("SELECT id FROM document_types WHERE LOWER(name) = LOWER(?)");
            $stmtChk->execute([$name]);
            if ($stmtChk->fetch()) {
                echo json_encode(['success' => false, 'message' => "Document type '{$name}' already exists."]);
                exit;
            }

            $stmtIns = $db->prepare("INSERT INTO document_types (name, description, is_active) VALUES (?, ?, 1)");
            $stmtIns->execute([$name, $description]);
            $newId = $db->lastInsertId();

            logActivity($currentUser['id'], 'template-generator', 'ADD_DOCUMENT_TYPE', 'document_types', $newId, "Added document type '{$name}'");

            echo json_encode([
                'success' => true,
                'id' => $newId,
                'name' => $name,
                'message' => "Document type '{$name}' created successfully."
            ]);
            break;

        case 'update_document_type':
            checkAuth();
            ensureDocumentTypesTable($db);
            $currentUser = getCurrentUser();
            $id = intval($input['id'] ?? 0);
            $newName = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');

            if ($id <= 0 || empty($newName)) {
                echo json_encode(['success' => false, 'message' => 'Document type ID and valid Name are required.']);
                exit;
            }

            $stmtOld = $db->prepare("SELECT name FROM document_types WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldName = $stmtOld->fetchColumn();

            if (!$oldName) {
                echo json_encode(['success' => false, 'message' => 'Document type not found.']);
                exit;
            }

            $stmtChk = $db->prepare("SELECT id FROM document_types WHERE LOWER(name) = LOWER(?) AND id != ?");
            $stmtChk->execute([$newName, $id]);
            if ($stmtChk->fetch()) {
                echo json_encode(['success' => false, 'message' => "Another document type named '{$newName}' already exists."]);
                exit;
            }

            $stmtUpd = $db->prepare("UPDATE document_types SET name = ?, description = ? WHERE id = ?");
            $stmtUpd->execute([$newName, $description, $id]);

            if ($oldName !== $newName) {
                $db->prepare("UPDATE document_templates SET document_type = ? WHERE document_type = ?")->execute([$newName, $oldName]);
                $db->prepare("UPDATE documents SET document_type = ? WHERE document_type = ?")->execute([$newName, $oldName]);
            }

            logActivity($currentUser['id'], 'template-generator', 'UPDATE_DOCUMENT_TYPE', 'document_types', $id, "Updated document type from '{$oldName}' to '{$newName}'");

            echo json_encode([
                'success' => true,
                'message' => "Document type '{$newName}' updated successfully."
            ]);
            break;

        case 'delete_document_type':
            checkAuth();
            ensureDocumentTypesTable($db);
            $currentUser = getCurrentUser();
            $id = intval($input['id'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid document type ID.']);
                exit;
            }

            $stmtGet = $db->prepare("SELECT name FROM document_types WHERE id = ?");
            $stmtGet->execute([$id]);
            $typeName = $stmtGet->fetchColumn();

            if ($typeName) {
                $tmplCount = $db->prepare("SELECT COUNT(*) FROM document_templates WHERE document_type = ?");
                $tmplCount->execute([$typeName]);
                if ($tmplCount->fetchColumn() > 0) {
                    $db->prepare("UPDATE document_types SET is_active = 0 WHERE id = ?")->execute([$id]);
                } else {
                    $db->prepare("DELETE FROM document_types WHERE id = ?")->execute([$id]);
                }
            }

            logActivity($currentUser['id'], 'template-generator', 'DELETE_DOCUMENT_TYPE', 'document_types', $id, "Deleted document type #{$id}");

            echo json_encode(['success' => true, 'message' => 'Document type deleted successfully.']);
            break;

        // --- TEMPLATES API ---
        case 'get_templates':
            checkAuth();
            $docType = $_GET['document_type'] ?? null;
            $sql = "SELECT * FROM document_templates WHERE is_active = 1";
            $params = [];
            if ($docType) {
                $sql .= " AND document_type = ?";
                $params[] = $docType;
            }
            $sql .= " ORDER BY document_type, template_name";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'templates' => $stmt->fetchAll()]);
            break;

        case 'get_template_details':
            checkAuth();
            $id = intval($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM document_templates WHERE id = ?");
            $stmt->execute([$id]);
            $template = $stmt->fetch();
            if (!$template) {
                echo json_encode(['success' => false, 'message' => 'Template not found.']);
                exit;
            }
            $template['variables'] = json_decode($template['variables_json'] ?? '[]', true);
            echo json_encode(['success' => true, 'template' => $template]);
            break;

        case 'save_template':
            checkAuth('template-generator');
            $user = getCurrentUser();

            $templateId = intval($input['template_id'] ?? 0);
            $docType = trim($input['document_type'] ?? 'Proposal');
            $templateName = trim($input['template_name'] ?? 'Custom Template');
            $subjectTemplate = trim($input['subject_template'] ?? '');
            $contentTemplate = $input['content_template'] ?? '';
            $variables = $input['variables'] ?? ['CLIENT_COMPANY', 'SERVICE_NAME', 'SERVICE_PRICE'];

            if (empty($templateName) || empty($contentTemplate)) {
                echo json_encode(['success' => false, 'message' => 'Template Name and HTML Content are required.']);
                exit;
            }

            $variablesJson = json_encode(array_values(array_unique($variables)));

            if ($templateId > 0) {
                $stmt = $db->prepare("UPDATE document_templates SET document_type = ?, template_name = ?, subject_template = ?, content_template = ?, variables_json = ?, version = version + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$docType, $templateName, $subjectTemplate, $contentTemplate, $variablesJson, $templateId]);
                logActivity($user['id'], 'template-generator', 'UPDATE_TEMPLATE', 'document_templates', $templateId, "Updated template '{$templateName}'");
                $msg = "Template updated successfully!";
            } else {
                $stmt = $db->prepare("INSERT INTO document_templates (document_type, template_name, subject_template, content_template, variables_json) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$docType, $templateName, $subjectTemplate, $contentTemplate, $variablesJson]);
                $templateId = $db->lastInsertId();
                logActivity($user['id'], 'template-generator', 'CREATE_TEMPLATE', 'document_templates', $templateId, "Created template '{$templateName}'");
                $msg = "New template created successfully!";
            }

            echo json_encode(['success' => true, 'template_id' => $templateId, 'message' => $msg]);
            break;

        case 'preview_document':
            checkAuth('template-generator');

            $templateId = intval($input['template_id'] ?? 0);
            $clientId = intval($input['client_id'] ?? 0);
            $serviceId = intval($input['service_id'] ?? 0);
            $customFields = $input['custom_fields'] ?? [];

            $stmtTmpl = $db->prepare("SELECT * FROM document_templates WHERE id = ?");
            $stmtTmpl->execute([$templateId]);
            $template = $stmtTmpl->fetch();

            if (!$template) {
                echo json_encode(['success' => false, 'message' => 'Invalid template selected.']);
                exit;
            }

            $client = null;
            if ($clientId > 0) {
                $stmtClient = $db->prepare("SELECT * FROM clients WHERE id = ?");
                $stmtClient->execute([$clientId]);
                $client = $stmtClient->fetch();
            }
            if (!$client) {
                $client = [
                    'company_name' => $customFields['CLIENT_COMPANY'] ?? 'Prospective Client Ltd',
                    'contact_person' => $customFields['CLIENT_CONTACT'] ?? 'Client Representative',
                    'email' => $customFields['CLIENT_EMAIL'] ?? 'client@example.com',
                    'phone' => $customFields['CLIENT_PHONE'] ?? '+91-9876543210',
                    'address' => $customFields['CLIENT_ADDRESS'] ?? 'Business Park, Kochi - 682042',
                    'tax_id' => $customFields['CLIENT_TAX_ID'] ?? '32AAACB1234F1Z1'
                ];
            }

            $service = null;
            if ($serviceId) {
                $stmtSvc = $db->prepare("SELECT * FROM client_services WHERE id = ?");
                $stmtSvc->execute([$serviceId]);
                $service = $stmtSvc->fetch();
            }

            $agencyName = $customFields['AGENCY_NAME'] ?? 'Hemito Digital Pvt Ltd';
            $agencyTagline = $customFields['AGENCY_TAGLINE'] ?? 'A Million Possibilities';
            $agencyAddress = $customFields['AGENCY_ADDRESS'] ?? '2nd Floor, ACEL Tower, Ambady Lane, Kadavanthra, Kochi - 682020';
            $agencyPhone = $customFields['AGENCY_PHONE'] ?? '+91-8921992187';
            $agencyEmail = $customFields['AGENCY_EMAIL'] ?? 'sales@hemitodigital.com';
            $agencyWebsite = $customFields['AGENCY_WEBSITE'] ?? 'www.hemitodigital.com';
            $agencyTaxId = $customFields['AGENCY_TAX_ID'] ?? '32AAACH9876K1Z4';

            $docNumber = 'DOC-' . date('Ymd') . '-' . rand(1000, 9999);
            $price = $service ? floatval($service['price']) : floatval($customFields['SERVICE_PRICE'] ?? 70000);
            $taxAmount = round($price * 0.18, 2);
            $totalAmount = round($price + $taxAmount, 2);

            $placeholders = [
                '{{DOCUMENT_NUMBER}}' => $docNumber,
                '{{CURRENT_DATE}}' => date('d M Y'),
                '{{DUE_DATE}}' => date('d M Y', strtotime('+15 days')),
                '{{AGENCY_NAME}}' => $agencyName,
                '{{AGENCY_TAGLINE}}' => $agencyTagline,
                '{{AGENCY_ADDRESS}}' => $agencyAddress,
                '{{AGENCY_PHONE}}' => $agencyPhone,
                '{{AGENCY_EMAIL}}' => $agencyEmail,
                '{{AGENCY_WEBSITE}}' => $agencyWebsite,
                '{{AGENCY_TAX_ID}}' => $agencyTaxId,
                '{{CLIENT_COMPANY}}' => $client['company_name'] ?? 'N/A',
                '{{CLIENT_CONTACT}}' => $client['contact_person'] ?? 'N/A',
                '{{CLIENT_EMAIL}}' => $client['email'] ?? 'N/A',
                '{{CLIENT_PHONE}}' => $client['phone'] ?? 'N/A',
                '{{CLIENT_ADDRESS}}' => $client['address'] ?? 'N/A',
                '{{CLIENT_TAX_ID}}' => $client['tax_id'] ?? 'N/A',
                '{{SERVICE_NAME}}' => $service['service_name'] ?? ($customFields['SERVICE_NAME'] ?? 'Digital Marketing & Social Media'),
                '{{PACKAGE_NAME}}' => $service['package_name'] ?? ($customFields['PACKAGE_NAME'] ?? 'Performance Marketing Suite'),
                '{{SERVICE_PRICE}}' => number_format($price, 2),
                '{{CURRENCY}}' => $service['currency'] ?? ($customFields['CURRENCY'] ?? 'INR'),
                '{{BILLING_TERMS}}' => $service['billing_terms'] ?? ($customFields['BILLING_TERMS'] ?? 'Monthly in advance'),
                '{{PAYMENT_TERMS}}' => $service['payment_terms'] ?? ($customFields['PAYMENT_TERMS'] ?? 'Within 7 days of invoice'),
                '{{TAX_AMOUNT}}' => number_format($taxAmount, 2),
                '{{TOTAL_AMOUNT}}' => number_format($totalAmount, 2),
                '{{CUSTOM_SCOPE}}' => nl2br(htmlspecialchars($customFields['CUSTOM_SCOPE'] ?? 'Full technical scope as negotiated.')),
                '{{CUSTOM_NOTES}}' => nl2br(htmlspecialchars($customFields['CUSTOM_NOTES'] ?? 'Key requirements and milestones confirmed.'))
            ];

            foreach ($customFields as $k => $v) {
                if (!empty($v)) {
                    $placeholders['{{' . strtoupper($k) . '}}'] = htmlspecialchars($v);
                }
            }

            $contentHtml = str_replace(array_keys($placeholders), array_values($placeholders), $template['content_template']);
            $title = str_replace(array_keys($placeholders), array_values($placeholders), $template['subject_template'] ?? $template['template_name']);

            echo json_encode([
                'success' => true,
                'document_number' => $docNumber,
                'title' => $title,
                'content_html' => $contentHtml,
                'placeholders' => $placeholders
            ]);
            break;

        case 'generate_document':
            checkAuth('template-generator');
            $user = getCurrentUser();

            $clientId = intval($input['client_id'] ?? 0);
            $serviceId = intval($input['service_id'] ?? 0) ?: null;
            $templateId = intval($input['template_id'] ?? 0) ?: null;
            $docType = $input['document_type'] ?? 'Proposal';
            $title = trim($input['title'] ?? 'Generated Document');
            $contentHtml = $input['content_html'] ?? '';
            $customFields = json_encode($input['custom_fields'] ?? []);

            if (!$clientId || empty($contentHtml)) {
                echo json_encode(['success' => false, 'message' => 'Client ID and Content are required.']);
                exit;
            }

            $docNumber = 'AGY-' . strtoupper(substr($docType, 0, 3)) . '-' . date('Ymd-His');

            $stmt = $db->prepare("INSERT INTO documents (document_number, client_id, service_id, template_id, document_type, title, custom_fields_json, content_html, status, generated_by, version) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Generated', ?, 1)");
            $stmt->execute([$docNumber, $clientId, $serviceId, $templateId, $docType, $title, $customFields, $contentHtml, $user['id']]);
            $docId = $db->lastInsertId();

            $stmtVer = $db->prepare("INSERT INTO document_versions (document_id, version_number, title, custom_fields_json, content_html, change_summary, created_by) VALUES (?, 1, ?, ?, ?, 'Initial document generation', ?)");
            $stmtVer->execute([$docId, $title, $customFields, $contentHtml, $user['id']]);

            logActivity($user['id'], 'template-generator', 'GENERATE_DOCUMENT', 'documents', $docId, "Generated {$docType} #{$docNumber} for Client #{$clientId}");

            echo json_encode([
                'success' => true,
                'document_id' => $docId,
                'document_number' => $docNumber,
                'message' => 'Document generated and saved successfully under Client record!'
            ]);
            break;

        case 'get_documents':
            checkAuth();
            $clientId = intval($_GET['client_id'] ?? 0);
            $docType = $_GET['document_type'] ?? null;

            $sql = "SELECT d.*, c.company_name as client_name, u.full_name as generated_by_name, p.project_name as handover_project_name
                    FROM documents d
                    JOIN clients c ON d.client_id = c.id
                    JOIN users u ON d.generated_by = u.id
                    LEFT JOIN projects p ON d.handover_project_id = p.id
                    WHERE 1=1";
            $params = [];
            if ($clientId) {
                $sql .= " AND d.client_id = ?";
                $params[] = $clientId;
            }
            if ($docType) {
                $sql .= " AND d.document_type = ?";
                $params[] = $docType;
            }
            $sql .= " ORDER BY d.id DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'documents' => $stmt->fetchAll()]);
            break;

        case 'get_document_versions':
            checkAuth();
            $docId = intval($_GET['document_id'] ?? 0);
            $stmt = $db->prepare("SELECT dv.*, u.full_name as created_by_name FROM document_versions dv JOIN users u ON dv.created_by = u.id WHERE dv.document_id = ? ORDER BY dv.version_number DESC");
            $stmt->execute([$docId]);
            echo json_encode(['success' => true, 'versions' => $stmt->fetchAll()]);
            break;

        case 'get_document_details':
            checkAuth();
            $id = intval($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT d.*, c.company_name as client_name, c.contact_person, c.email as client_email, s.service_name, s.package_name, s.price
                                  FROM documents d
                                  JOIN clients c ON d.client_id = c.id
                                  LEFT JOIN client_services s ON d.service_id = s.id
                                  WHERE d.id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();
            if (!$doc) {
                echo json_encode(['success' => false, 'message' => 'Document not found.']);
                exit;
            }
            $doc['custom_fields'] = json_decode($doc['custom_fields_json'] ?? '{}', true);
            echo json_encode(['success' => true, 'document' => $doc]);
            break;

        case 'update_document':
            checkAuth('template-generator');
            $user = getCurrentUser();

            $docId = intval($input['document_id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $docType = trim($input['document_type'] ?? 'Proposal');
            $clientId = intval($input['client_id'] ?? 0);
            $serviceId = intval($input['service_id'] ?? 0) ?: null;
            $contentHtml = $input['content_html'] ?? '';
            $customFields = json_encode($input['custom_fields'] ?? []);
            $changeSummary = trim($input['change_summary'] ?? 'Updated document content');

            if (!$docId || empty($title) || empty($contentHtml) || !$clientId) {
                echo json_encode(['success' => false, 'message' => 'Document ID, Client, Title, and Content are required.']);
                exit;
            }

            $stmtCur = $db->prepare("SELECT version, document_number FROM documents WHERE id = ?");
            $stmtCur->execute([$docId]);
            $curDoc = $stmtCur->fetch();

            if (!$curDoc) {
                echo json_encode(['success' => false, 'message' => 'Document not found for editing.']);
                exit;
            }

            $newVersion = intval($curDoc['version']) + 1;

            $stmtUpd = $db->prepare("UPDATE documents SET client_id = ?, service_id = ?, document_type = ?, title = ?, custom_fields_json = ?, content_html = ?, version = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$clientId, $serviceId, $docType, $title, $customFields, $contentHtml, $newVersion, $docId]);

            $stmtVer = $db->prepare("INSERT INTO document_versions (document_id, version_number, title, custom_fields_json, content_html, change_summary, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtVer->execute([$docId, $newVersion, $title, $customFields, $contentHtml, $changeSummary, $user['id']]);

            logActivity($user['id'], 'template-generator', 'UPDATE_DOCUMENT', 'documents', $docId, "Updated document #{$curDoc['document_number']} to version {$newVersion}");

            echo json_encode([
                'success' => true,
                'document_id' => $docId,
                'version' => $newVersion,
                'message' => "Document updated successfully! Saved as Version {$newVersion}."
            ]);
            break;


        // --- HANDOVER API ---
        case 'handover_to_project':
            checkAuth('template-generator');
            $user = getCurrentUser();

            $docId = intval($input['document_id'] ?? 0);
            if ($docId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid or unsaved document ID. Please save the generated document first.']);
                exit;
            }

            $stmtDoc = $db->prepare("SELECT d.*, c.company_name, c.contact_person, c.email as client_email, c.phone as client_phone, c.id as client_id FROM documents d JOIN clients c ON d.client_id = c.id WHERE d.id = ?");
            $stmtDoc->execute([$docId]);
            $doc = $stmtDoc->fetch();

            if (!$doc) {
                echo json_encode(['success' => false, 'message' => 'Document not found in system. Please save the document before submitting handover.']);
                exit;
            }

            $projectName = trim($input['project_name'] ?? ($doc['company_name'] . ' - ' . $doc['title']));
            $description = trim($input['description'] ?? ("Work Requirement Handover from approved document {$doc['document_number']}.\nClient: {$doc['company_name']}"));
            $priority = $input['priority'] ?? 'High';
            $deadline = $input['deadline'] ?? date('Y-m-d', strtotime('+14 days'));
            $teamLeadId = intval($input['team_lead_id'] ?? 0);
            $primaryDept = trim($input['primary_department'] ?? ($input['department'] ?? 'Development'));
            if (empty($primaryDept)) $primaryDept = 'Development';

            if ($teamLeadId <= 0) {
                $stmtFallbackLead = $db->query("SELECT id FROM users WHERE role_id IN (4, 1) ORDER BY role_id ASC LIMIT 1");
                $teamLeadId = $stmtFallbackLead->fetchColumn() ?: $user['id'];
            }

            try {
                // Ensure projects has department & observers_json column
                try {
                    $db->exec("ALTER TABLE projects ADD COLUMN department VARCHAR(100) DEFAULT 'Development'");
                } catch (Exception $eCol) {}
                $defaultProjObsIds = getDefaultProjectObserverIds($db, $teamLeadId);

                // Resolve departments list from features if multiple departments are handed over
                $features = $input['features'] ?? [];
                $selectedDeptNames = [];
                if (is_array($features) && !empty($features)) {
                    foreach ($features as $fItem) {
                        $dn = trim($fItem['department'] ?? '');
                        if ($dn !== '' && strtolower($dn) !== 'sales' && !in_array($dn, $selectedDeptNames, true)) {
                            $selectedDeptNames[] = $dn;
                        }
                    }
                }
                $projectDeptStr = !empty($selectedDeptNames) ? implode(', ', $selectedDeptNames) : $primaryDept;

                $stmtProj = $db->prepare("INSERT INTO projects (client_id, service_id, project_name, description, priority, status, team_lead_id, observers_json, department, deadline, created_by) VALUES (?, ?, ?, ?, ?, 'In Progress', ?, ?, ?, ?, ?)");
                $stmtProj->execute([
                    $doc['client_id'],
                    $doc['service_id'],
                    $projectName,
                    $description,
                    $priority,
                    $teamLeadId,
                    json_encode($defaultProjObsIds),
                    $projectDeptStr,
                    $deadline,
                    $user['id']
                ]);
                $projectId = $db->lastInsertId();

                $stmtLink = $db->prepare("UPDATE documents SET handover_project_id = ?, status = 'Approved' WHERE id = ?");
                $stmtLink->execute([$projectId, $docId]);

                $stmtCli = $db->prepare("UPDATE clients SET status = 'Approved / Active' WHERE id = ?");
                $stmtCli->execute([$doc['client_id']]);

                // Create Work Order Tasks for each department with its multiple selected assignees
                if (!is_array($features) || empty($features)) {
                    $fallbackAssignees = !empty($input['assignees']) && is_array($input['assignees'])
                        ? array_values(array_unique(array_filter(array_map('intval', $input['assignees']))))
                        : [$teamLeadId];
                    $features = [[
                        'title' => "Handover Execution: " . $doc['title'],
                        'department' => $primaryDept,
                        'assigned_to' => $fallbackAssignees[0] ?? $teamLeadId,
                        'assignees' => $fallbackAssignees,
                        'priority' => $priority,
                        'sla_hours' => 48,
                        'notes' => $description
                    ]];
                }

                $tasksCreated = 0;
                $assignedDepts = [];
                $allAssignedUsersToNotify = [];

                foreach ($features as $idx => $f) {
                    $fTitle = trim($f['title'] ?? '');
                    if ($fTitle === '') {
                        $fTitle = "Handover Execution: " . $doc['title'];
                    }
                    $fDept = trim($f['department'] ?? $primaryDept);
                    if (empty($fDept) || strtolower($fDept) === 'sales') {
                        $fDept = 'Development';
                    }
                    $assignedDepts[$fDept] = true;

                    // Resolve multiple assignees for this department
                    $fAssignees = [];
                    if (!empty($f['assignees']) && is_array($f['assignees'])) {
                        $fAssignees = array_values(array_unique(array_filter(array_map('intval', $f['assignees']), function ($x) { return $x > 0; })));
                    }

                    // Resolve assigned lead for this department
                    $fTaskLeadId = intval($f['team_lead_id'] ?? ($f['assigned_to'] ?? 0));
                    if ($fTaskLeadId <= 0) {
                        $stmtLead = $db->prepare("
                            SELECT u.id FROM users u 
                            JOIN roles r ON u.role_id = r.id 
                            WHERE u.department = ? AND (u.role_id IN (1, 4) OR r.name LIKE '%Lead%' OR r.name LIKE '%Manager%') 
                            ORDER BY u.role_id ASC LIMIT 1
                        ");
                        $stmtLead->execute([$fDept]);
                        $fTaskLeadId = intval($stmtLead->fetchColumn() ?: 0);

                        if ($fTaskLeadId <= 0 && !empty($fAssignees)) {
                            $fTaskLeadId = $fAssignees[0];
                        }

                        if ($fTaskLeadId <= 0) {
                            $stmtDeptUser = $db->prepare("SELECT id FROM users WHERE department = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
                            $stmtDeptUser->execute([$fDept]);
                            $fTaskLeadId = intval($stmtDeptUser->fetchColumn() ?: 0);
                        }

                        if ($fTaskLeadId <= 0) {
                            $fTaskLeadId = $teamLeadId;
                        }
                    }

                    if (empty($fAssignees)) {
                        $fAssignees = [$fTaskLeadId];
                    }
                    $fPrimaryAssignedTo = $fAssignees[0];
                    $fAssigneesJson = json_encode($fAssignees);

                    foreach ($fAssignees as $aUid) {
                        $allAssignedUsersToNotify[$aUid] = true;
                    }
                    if ($fTaskLeadId > 0) {
                        $allAssignedUsersToNotify[$fTaskLeadId] = true;
                    }

                    // Resolve observers (watchers): include default Lead, Super Admin, and CRM observers
                    $fObservers = getProjectObserverIds($db, $projectId, $fTaskLeadId);
                    if (!empty($f['observers']) && is_array($f['observers'])) {
                        $fObservers = array_merge($fObservers, array_map('intval', $f['observers']));
                    }
                    // Current creator (e.g. Sales user) is automatically added as an observer
                    if (!in_array($user['id'], $fObservers)) {
                        $fObservers[] = $user['id'];
                    }
                    $fObservers = array_values(array_unique(array_filter($fObservers, function ($x) { return $x > 0; })));
                    $fObserversJson = json_encode($fObservers);

                    $fPriority = !empty($f['priority']) ? $f['priority'] : $priority;
                    $fSlaHours = intval($f['sla_hours'] ?? 48);
                    if ($fSlaHours <= 0) $fSlaHours = 48;
                    $fNotes = trim($f['notes'] ?? ($f['description'] ?? $description));

                    $taskCode = 'TSK-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
                    $slaStartTime = date('Y-m-d H:i:s');
                    $slaDueTime = date('Y-m-d H:i:s', time() + ($fSlaHours * 3600));
                    $fDeadline = date('Y-m-d H:i:s', strtotime($deadline));

                    $stmtTask = $db->prepare("INSERT INTO tasks (
                        client_id, project_id, service_id, task_code, title, description, 
                        department, assigned_to, assignees_json, observers_json, team_lead_id, priority, sla_hours, 
                        sla_start_time, sla_due_time, deadline, status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)");
                    
                    $stmtTask->execute([
                        $doc['client_id'],
                        $projectId,
                        $doc['service_id'] ?? null,
                        $taskCode,
                        $fTitle,
                        $fNotes,
                        $fDept,
                        $fPrimaryAssignedTo,
                        $fAssigneesJson,
                        $fObserversJson,
                        $fTaskLeadId,
                        $fPriority,
                        $fSlaHours,
                        $slaStartTime,
                        $slaDueTime,
                        $fDeadline,
                        $user['id']
                    ]);
                    $newTaskId = $db->lastInsertId();

                    // Insert relational task_assignments
                    try {
                        $stmtAssign = $db->prepare("INSERT INTO task_assignments (task_id, user_id, role_type) VALUES (?, ?, ?)");
                        foreach ($fAssignees as $uId) {
                            $stmtAssign->execute([$newTaskId, $uId, 'assignee']);
                        }
                        foreach ($fObservers as $uId) {
                            if (!in_array($uId, $fAssignees)) {
                                $stmtAssign->execute([$newTaskId, $uId, 'observer']);
                            }
                        }
                    } catch (Exception $eRel) {}

                    $tasksCreated++;
                }

                // Auto-register all department assignees into project working members
                if (!empty($allAssignedUsersToNotify)) {
                    syncProjectWorkingEmployees($db, $projectId, array_keys($allAssignedUsersToNotify), $user['id']);
                }

                // Auto-provision or link Client Portal user account
                $clientUser = autoProvisionClientUser($db, $doc['client_id']);

                // Create Client Notification for portal hub
                try {
                    $stmtNotif = $db->prepare("INSERT INTO client_notifications (client_id, project_id, notification_type, title, message, is_read) VALUES (?, ?, 'PROJECT_COMPLETED', 'Project Initiated', ?, 0)");
                    $stmtNotif->execute([
                        $doc['client_id'],
                        $projectId,
                        "Your project '{$projectName}' has been initiated with {$tasksCreated} feature deliverable(s) handed over to teams."
                    ]);
                } catch (Exception $e) {
                    // Ignore optional notification errors
                }

                // Notify Assigned Team Members, Department Leads & Super Admins about the new project handover
                try {
                    ensureUserNotificationsTable($db);

                    $leadsToNotify = $allAssignedUsersToNotify;
                    if ($teamLeadId > 0) {
                        $leadsToNotify[$teamLeadId] = true;
                    }
                    foreach ($features as $feat) {
                        $fLeadId = intval($feat['team_lead_id'] ?? ($feat['assigned_to'] ?? 0));
                        if ($fLeadId > 0) {
                            $leadsToNotify[$fLeadId] = true;
                        }
                        if (!empty($feat['assignees']) && is_array($feat['assignees'])) {
                            foreach ($feat['assignees'] as $aId) {
                                if (intval($aId) > 0) $leadsToNotify[intval($aId)] = true;
                            }
                        }
                    }
                    // Also notify Super Admins
                    $stmtSuper = $db->query("SELECT id FROM users WHERE role_id = 1 AND is_active = 1");
                    if ($stmtSuper) {
                        while ($sRow = $stmtSuper->fetch(PDO::FETCH_ASSOC)) {
                            $leadsToNotify[intval($sRow['id'])] = true;
                        }
                    }

                    $deptListForNotif = implode(', ', array_keys($assignedDepts));
                    $stmtLeadNotif = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'project_handover', ?, 0, CURRENT_TIMESTAMP)");
                    $notifTitle = "🚀 New Project Handover: {$projectName}";
                    $notifMsg = "Sales rep {$user['full_name']} handed over document #{$doc['document_number']} ({$doc['company_name']}) to {$deptListForNotif}. Click to view project & assigned work.";
                    $notifLink = "projects.php?project_id={$projectId}";

                    foreach (array_keys($leadsToNotify) as $targetLeadId) {
                        $stmtLeadNotif->execute([$targetLeadId, $notifTitle, $notifMsg, $notifLink]);
                    }
                } catch (Exception $eLeadNotif) {}

                $deptListStr = implode(', ', array_keys($assignedDepts));
                $totalUniqueAssignees = count($allAssignedUsersToNotify);
                logActivity($user['id'], 'template-generator', 'HANDOVER_TO_DEPARTMENTS', 'projects', $projectId, "Handed over document #{$doc['document_number']} to {$totalUniqueAssignees} assignee(s) across department(s): {$deptListStr}");

                $msg = "Client approval confirmed! Project #{$projectId} created for {$doc['company_name']}. Successfully handed over to {$totalUniqueAssignees} assignee(s) across department(s): {$deptListStr}.";
                if ($clientUser) {
                    if ($clientUser['created']) {
                        $msg .= " Client Portal user created (@{$clientUser['username']}, Temp Password: {$clientUser['temp_password']}).";
                    } else {
                        $msg .= " Client Portal account (@{$clientUser['username']}) active for {$doc['company_name']}.";
                    }
                }

                if (ob_get_length()) ob_clean();
                echo json_encode([
                    'success' => true,
                    'project_id' => $projectId,
                    'tasks_count' => $tasksCreated,
                    'departments' => array_keys($assignedDepts),
                    'client_user' => $clientUser,
                    'message' => $msg
                ]);
            } catch (Exception $e) {
                if (ob_get_length()) ob_clean();
                echo json_encode(['success' => false, 'message' => 'Handover DB error: ' . $e->getMessage()]);
            }
            break;

        case 'reassign_task':
            checkAuth('task-management');
            $currentUser = getCurrentUser();

            $taskId = intval($input['task_id'] ?? 0);
            $assignedTo = intval($input['assigned_to'] ?? 0);
            $assignees = $input['assignees'] ?? [];
            $observers = $input['observers'] ?? null;

            $assigneeIds = [];
            if (!empty($assignees) && is_array($assignees)) {
                $assigneeIds = array_values(array_filter(array_map('intval', $assignees)));
            }
            if ($assignedTo > 0 && !in_array($assignedTo, $assigneeIds)) {
                array_unshift($assigneeIds, $assignedTo);
            }
            if (empty($assigneeIds) && $assignedTo > 0) {
                $assigneeIds = [$assignedTo];
            }
            if ($assignedTo <= 0 && !empty($assigneeIds)) {
                $assignedTo = $assigneeIds[0];
            }

            if (!$taskId || !$assignedTo) {
                echo json_encode(['success' => false, 'message' => 'Task ID and Target User/Developer are required.']);
                exit;
            }

            $stmtT = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmtT->execute([$taskId]);
            $task = $stmtT->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            // Only Team Lead, Super Admin, or Project Handler for this project can reassign tasks
            if (!canManageProject($db, $task['project_id'], $currentUser['id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Leads, Super Admins, or Project Handlers can assign tasks and manage observers.']);
                exit;
            }

            if (array_key_exists('project_handlers', $input)) {
                if (!isLeadOrAdmin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Leads or Super Admins can add or change Project Handlers.']);
                    exit;
                }
                if (is_array($input['project_handlers']) && !empty($task['project_id'])) {
                    $prevH = getProjectHandlerIds($db, $task['project_id']);
                    $newH = syncProjectHandlers($db, $task['project_id'], $input['project_handlers']);
                    $addedH = array_values(array_diff($newH, $prevH));
                    if (!empty($addedH)) {
                        $stPnH = $db->prepare("SELECT project_name FROM projects WHERE id = ?");
                        $stPnH->execute([$task['project_id']]);
                        $pnH = (string)($stPnH->fetchColumn() ?: '');
                        notifyProjectHandlers($db, $task['project_id'], $pnH, $addedH, $currentUser);
                    }
                }
            }

            $stmtU = $db->prepare("SELECT full_name, username FROM users WHERE id = ?");
            $stmtU->execute([$assignedTo]);
            $targetUser = $stmtU->fetch();

            if (!$targetUser) {
                echo json_encode(['success' => false, 'message' => 'Target user account not found.']);
                exit;
            }

            // Resolve observers
            $observerIds = [];
            if ($observers !== null && is_array($observers)) {
                $observerIds = array_values(array_filter(array_map('intval', $observers)));
                $observersJson = json_encode($observerIds);
                $stmtObs = $db->prepare("UPDATE tasks SET observers_json = ? WHERE id = ?");
                $stmtObs->execute([$observersJson, $taskId]);
            } else {
                $observerIds = json_decode($task['observers_json'] ?? '[]', true) ?: [];
            }

            $assigneesJson = json_encode($assigneeIds);
            $stmtUpd = $db->prepare("UPDATE tasks SET assigned_to = ?, assignees_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$assignedTo, $assigneesJson, $taskId]);

            // Sync relational task_assignments (both assignees and observers)
            try {
                $db->prepare("DELETE FROM task_assignments WHERE task_id = ?")->execute([$taskId]);
                $stmtInsRel = $db->prepare("INSERT INTO task_assignments (task_id, user_id, role_type) VALUES (?, ?, ?)");
                foreach ($assigneeIds as $aId) {
                    $stmtInsRel->execute([$taskId, $aId, 'assignee']);
                }
                foreach ($observerIds as $oId) {
                    if (!in_array($oId, $assigneeIds)) {
                        $stmtInsRel->execute([$taskId, $oId, 'observer']);
                    }
                }
            } catch (Exception $eRel) {}

            // Notify newly assigned employee(s)
            $prevAssigneeIds = json_decode($task['assignees_json'] ?? '[]', true);
            $prevAssigneeIds = is_array($prevAssigneeIds) ? array_map('intval', $prevAssigneeIds) : [];
            if (!empty($task['assigned_to'])) {
                $prevAssigneeIds[] = intval($task['assigned_to']);
            }
            $prevAssigneeIds = array_values(array_unique(array_filter($prevAssigneeIds)));
            $notifyIds = array_values(array_diff($assigneeIds, $prevAssigneeIds));
            if ($assignedTo > 0 && intval($task['assigned_to'] ?? 0) !== $assignedTo && !in_array($assignedTo, $notifyIds, true)) {
                $notifyIds[] = $assignedTo;
            }
            if (!empty($notifyIds)) {
                $pName = '';
                if (!empty($task['project_id'])) {
                    $stPn = $db->prepare("SELECT project_name FROM projects WHERE id = ?");
                    $stPn->execute([$task['project_id']]);
                    $pName = (string)($stPn->fetchColumn() ?: '');
                }
                notifyTaskAssignees($db, $taskId, $task['task_code'], $task['title'], $notifyIds, $currentUser, $pName);
            }

            // Record task activity
            try {
                $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'TASK_REASSIGNED', ?, ?, ?)");
                $stmtAct->execute([$taskId, $currentUser['id'], $task['assigned_to'], $assignedTo, "Task #{$task['task_code']} assigned to {$targetUser['full_name']} (Total assignees: " . count($assigneeIds) . ", Observers: " . count($observerIds) . ")"]);
            } catch (Exception $eAct) {}

            logActivity($currentUser['id'], 'task-management', 'REASSIGN_TASK', 'tasks', $taskId, "Assigned task #{$task['task_code']} to {$targetUser['full_name']} (Total assignees: " . count($assigneeIds) . ", Observers: " . count($observerIds) . ")");

            echo json_encode([
                'success' => true,
                'message' => "Task assigned to {$targetUser['full_name']} (@{$targetUser['username']}) successfully!" . (count($assigneeIds) > 1 ? " (" . count($assigneeIds) . " team members assigned)" : "") . (count($observerIds) > 0 ? " with " . count($observerIds) . " observer(s)" : "")
            ]);
            break;

        case 'update_task_observers':
            checkAuth('task-management');
            $user = getCurrentUser();

            // Strictly restricted to Team Lead and Super Admin / Admin
            $allowedRoles = ['Super Admin', 'Admin', 'Team Lead', 'Manager'];
            $userRoleId = intval($user['role_id'] ?? 0);
            if (!in_array($user['role'], $allowedRoles) && !in_array($userRoleId, [1, 4])) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Leads and Super Admins can manage task observers.']);
                exit;
            }

            $taskId = intval($input['task_id'] ?? 0);
            if ($taskId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Valid Task ID is required.']);
                exit;
            }

            $stmtT = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmtT->execute([$taskId]);
            $task = $stmtT->fetch();
            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            $currentObservers = json_decode($task['observers_json'] ?? '[]', true) ?: [];
            $subAction = $input['action'] ?? 'set';
            $targetUserId = intval($input['user_id'] ?? 0);

            if ($subAction === 'add') {
                if ($targetUserId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Valid User ID is required to add an observer.']);
                    exit;
                }
                if (!in_array($targetUserId, $currentObservers)) {
                    $currentObservers[] = $targetUserId;
                }
            } elseif ($subAction === 'remove') {
                $currentObservers = array_values(array_filter($currentObservers, function($id) use ($targetUserId) {
                    return intval($id) !== $targetUserId;
                }));
            } elseif ($subAction === 'set') {
                $newObservers = $input['observers'] ?? [];
                if (is_array($newObservers)) {
                    $currentObservers = array_values(array_filter(array_map('intval', $newObservers)));
                }
            }

            $observersJson = json_encode($currentObservers);
            $stmtUpd = $db->prepare("UPDATE tasks SET observers_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$observersJson, $taskId]);

            // Sync task_assignments for role_type = 'observer'
            try {
                $db->prepare("DELETE FROM task_assignments WHERE task_id = ? AND role_type = 'observer'")->execute([$taskId]);
                $stmtInsRel = $db->prepare("INSERT INTO task_assignments (task_id, user_id, role_type) VALUES (?, ?, 'observer')");
                $assigneeIds = json_decode($task['assignees_json'] ?? '[]', true) ?: [intval($task['assigned_to'])];
                foreach ($currentObservers as $oId) {
                    if (!in_array($oId, $assigneeIds)) {
                        $stmtInsRel->execute([$taskId, $oId]);
                    }
                }
            } catch (Exception $eRel) {}

            // Fetch updated observer details to return to UI
            $observerList = [];
            if (!empty($currentObservers)) {
                $placeholders = implode(',', array_fill(0, count($currentObservers), '?'));
                $stmtO = $db->prepare("SELECT u.id, u.full_name, u.username, u.email, u.department, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id IN ($placeholders)");
                $stmtO->execute($currentObservers);
                $observerList = $stmtO->fetchAll();
            }

            logActivity($user['id'], 'task-management', 'UPDATE_OBSERVERS', 'tasks', $taskId, "Updated observers for task #{$task['task_code']} (Total: " . count($currentObservers) . ")");

            echo json_encode([
                'success' => true,
                'message' => 'Task observers updated successfully.',
                'observers' => $observerList,
                'observer_ids' => $currentObservers
            ]);
            break;

        // --- MODULE 4 API ---
        case 'get_projects':
            checkAuth('task-management');
            $curUser = getCurrentUser();
            $curUid = intval($curUser['id'] ?? 0);
            $isCurrent = isset($_GET['is_current']) && $_GET['is_current'] !== '' ? intval($_GET['is_current']) : null;
            $deptFilter = trim($_GET['department'] ?? '');
            $clientIdFilter = intval($_GET['client_id'] ?? 0);
            $search = trim($_GET['search'] ?? '');

            $where = ["1=1"];
            $params = [];
            if ($isCurrent !== null) {
                $isCurrInt = ($isCurrent === 1) ? 1 : 0;
                $where[] = "COALESCE(p.is_current, 1) = {$isCurrInt}";
            }
            if (!empty($deptFilter)) {
                $where[] = "p.department = ?";
                $params[] = $deptFilter;
            }
            if ($clientIdFilter > 0) {
                $where[] = "p.client_id = ?";
                $params[] = $clientIdFilter;
            }
            if (!empty($search)) {
                $where[] = "(p.project_name LIKE ? OR c.company_name LIKE ? OR p.about_project LIKE ? OR p.description LIKE ?)";
                $like = '%' . $search . '%';
                array_push($params, $like, $like, $like, $like);
            }

            $sql = "SELECT p.*, c.company_name, u.full_name as team_lead_name, cb.full_name as created_by_name,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted=0) as total_tasks,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='COMPLETED' AND t.is_deleted=0) as completed_tasks,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status IN ('IN PROGRESS', 'UNDER REVIEW') AND t.is_deleted=0) as in_progress_tasks,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='PENDING' AND t.is_deleted=0) as pending_tasks,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='SENT FOR CLIENT APPROVAL' AND t.is_deleted=0) as awaiting_client_approval_tasks,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND COALESCE(t.parent_task_id, 0) > 0 AND t.is_deleted=0) as subtasks_count,
                    (SELECT COUNT(*) FROM project_competitors pc WHERE pc.project_id = p.id) as competitors_count
                    FROM projects p
                    JOIN clients c ON p.client_id = c.id
                    LEFT JOIN users u ON p.team_lead_id = u.id
                    LEFT JOIN users cb ON p.created_by = cb.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY COALESCE(p.is_current, 1) DESC, p.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $projectsList = $stmt->fetchAll();

            // Fetch tasks for all projects in scope
            $allProjIds = array_column($projectsList, 'id');
            $tasksByProject = [];
            if (!empty($allProjIds)) {
                $inClause = implode(',', array_map('intval', $allProjIds));
                $stTasks = $db->query("
                    SELECT t.id, t.task_code, t.title, t.project_id, t.service_id, t.department, t.priority, t.status,
                           t.deadline, t.assigned_to, t.task_type, t.parent_task_id, t.completion_time, t.assignees_json,
                           u.full_name as assigned_to_name,
                           ps.service_name,
                           (SELECT COUNT(*) FROM tasks st WHERE st.parent_task_id = t.id AND st.is_deleted = 0) as subtasks_count
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    LEFT JOIN project_services ps ON t.service_id = ps.id
                    WHERE t.project_id IN ($inClause) AND t.is_deleted = 0
                    ORDER BY 
                        CASE 
                            WHEN t.status = 'IN PROGRESS' THEN 1
                            WHEN t.status = 'PENDING' THEN 2
                            WHEN t.status = 'UNDER REVIEW' THEN 3
                            WHEN t.status = 'REVISION REQUIRED' THEN 4
                            ELSE 5
                        END,
                        t.id DESC
                ");
                if ($stTasks) {
                    foreach ($stTasks->fetchAll(PDO::FETCH_ASSOC) as $tk) {
                        $tasksByProject[intval($tk['project_id'])][] = $tk;
                    }
                }
            }

            // Resolve staff and project services
            $allStaffMap = [];
            $stStaff = $db->query("SELECT u.id, u.full_name, u.department, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id");
            if ($stStaff) {
                foreach ($stStaff->fetchAll() as $su) {
                    $allStaffMap[intval($su['id'])] = $su;
                }
            }

            // Fetch project services for all projects
            $servicesByProject = [];
            if (!empty($allProjIds)) {
                $inClause = implode(',', array_map('intval', $allProjIds));
                try {
                    $stSrv = $db->query("SELECT * FROM project_services WHERE project_id IN ($inClause) AND (status IS NULL OR status != 'Deleted') ORDER BY id ASC");
                    if ($stSrv) {
                        foreach ($stSrv->fetchAll(PDO::FETCH_ASSOC) as $sv) {
                            $aIds = json_decode($sv['assignees_json'] ?? '[]', true) ?: [];
                            $sv['assignee_ids'] = $aIds;
                            $sv['assignees'] = array_values(array_filter(array_map(function ($aid) use ($allStaffMap) {
                                return $allStaffMap[$aid] ?? null;
                            }, $aIds)));
                            $servicesByProject[intval($sv['project_id'])][] = $sv;
                        }
                    }
                } catch (Throwable $e) {
                    // table might not exist yet on legacy setups
                }
            }

            foreach ($projectsList as &$projRow) {
                $pid = intval($projRow['id']);
                $projRow['services'] = $servicesByProject[$pid] ?? [];
                $rawTasks = $tasksByProject[$pid] ?? [];
                foreach ($rawTasks as &$rt) {
                    $tAIds = json_decode($rt['assignees_json'] ?? '[]', true) ?: [];
                    if (!empty($tAIds)) {
                        $rt['assignees'] = array_values(array_filter(array_map(function ($aid) use ($allStaffMap) {
                            return $allStaffMap[$aid] ?? null;
                        }, $tAIds)));
                    } else {
                        $rt['assignees'] = $rt['assigned_to'] && isset($allStaffMap[$rt['assigned_to']]) ? [$allStaffMap[$rt['assigned_to']]] : [];
                    }
                }
                $projRow['tasks'] = $rawTasks;
                $hIds = getProjectHandlerIds($db, $projRow['id']);
                $projRow['handler_ids'] = $hIds;
                $projRow['handlers'] = array_values(array_filter(array_map(function ($hid) use ($allStaffMap) {
                    return $allStaffMap[$hid] ?? null;
                }, $hIds)));
                $obsIds = getProjectObserverIds($db, $projRow['id'], $projRow['team_lead_id'] ?? 0);
                $projRow['observer_ids'] = $obsIds;
                $projRow['observers'] = array_values(array_filter(array_map(function ($oid) use ($allStaffMap) {
                    return $allStaffMap[$oid] ?? null;
                }, $obsIds)));
                $workIds = getProjectWorkingEmployeeIds($db, $projRow['id']);
                $projRow['working_employee_ids'] = $workIds;
                $projRow['working_employees'] = array_values(array_filter(array_map(function ($wid) use ($allStaffMap) {
                    return $allStaffMap[$wid] ?? null;
                }, $workIds)));
                $projRow['is_working_member'] = in_array($curUid, $workIds, true);
                $projRow['is_handler'] = in_array($curUid, $hIds, true);
                $projRow['can_manage_project'] = isLeadOrAdmin() || $projRow['is_handler'];
            }

            // Fetch month's plans for all clients in scope (defaults to current month)
            $curMonth = trim($_GET['month'] ?? date('Y-m'));
            if (!preg_match('/^\d{4}-\d{2}$/', $curMonth)) {
                $curMonth = date('Y-m');
            }
            $allClientIds = array_unique(array_filter(array_column($projectsList, 'client_id')));
            $monthlyPlansByClient = [];
            if (!empty($allClientIds)) {
                $inClientIds = implode(',', array_map('intval', $allClientIds));
                $stPlans = $db->query("SELECT * FROM client_monthly_plans WHERE client_id IN ($inClientIds) AND month = '{$curMonth}'");
                if ($stPlans) {
                    foreach ($stPlans->fetchAll(PDO::FETCH_ASSOC) as $mp) {
                        $paidAmount = floatval($mp['paid_amount'] ?? ($mp['budget'] ?? 0));
                        $allocations = json_decode($mp['platform_allocations_json'] ?? '[]', true) ?: [];
                        $totalAllocated = 0;
                        foreach ($allocations as $al) {
                            $totalAllocated += floatval($al['amount'] ?? 0);
                        }
                        $currentBalance = $paidAmount - $totalAllocated;

                        $monthlyPlansByClient[intval($mp['client_id'])] = [
                            'id' => intval($mp['id']),
                            'client_id' => intval($mp['client_id']),
                            'month' => $mp['month'],
                            'title' => $mp['title'],
                            'budget' => $paidAmount,
                            'paid_amount' => $paidAmount,
                            'platform_allocations' => $allocations,
                            'total_allocated' => $totalAllocated,
                            'current_balance' => $currentBalance,
                            'remaining_balance' => $currentBalance,
                            'status' => $mp['status'],
                            'notes' => $mp['notes'],
                            'columns' => json_decode($mp['columns_json'] ?? '[]', true) ?: [],
                            'rows' => json_decode($mp['rows_json'] ?? '[]', true) ?: [],
                            'updated_at' => $mp['updated_at']
                        ];
                    }
                }
            }

            foreach ($projectsList as &$projRow) {
                $projRow['client_monthly_plan'] = $monthlyPlansByClient[intval($projRow['client_id'])] ?? null;
            }

            echo json_encode([
                'success' => true,
                'projects' => $projectsList,
                'monthly_plans_by_client' => $monthlyPlansByClient,
                'current_month' => $curMonth
            ]);
            break;

        case 'get_project_services':
            checkAuth('task-management');
            $projectId = intval($_GET['project_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM project_services WHERE project_id = ? AND (status IS NULL OR status != 'Deleted') ORDER BY id ASC");
            $stmt->execute([$projectId]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stStaff = $db->query("SELECT u.id, u.full_name, u.department, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id");
            $allStaffMap = [];
            if ($stStaff) {
                foreach ($stStaff->fetchAll() as $su) {
                    $allStaffMap[intval($su['id'])] = $su;
                }
            }
            foreach ($services as &$srv) {
                $aIds = json_decode($srv['assignees_json'] ?? '[]', true) ?: [];
                $srv['assignee_ids'] = $aIds;
                $srv['assignees'] = array_values(array_filter(array_map(function ($aid) use ($allStaffMap) {
                    return $allStaffMap[$aid] ?? null;
                }, $aIds)));
            }
            echo json_encode(['success' => true, 'services' => $services]);
            break;

        case 'save_project_service':
            checkAuth('task-management');
            $user = getCurrentUser();
            $serviceId = intval($input['id'] ?? ($_POST['id'] ?? 0));
            $projectId = intval($input['project_id'] ?? ($_POST['project_id'] ?? 0));
            $serviceName = trim($input['service_name'] ?? ($_POST['service_name'] ?? ''));
            $description = trim($input['description'] ?? ($_POST['description'] ?? ''));
            $assignees = $input['assignees'] ?? ($_POST['assignees'] ?? []);
            if (is_string($assignees)) {
                $assignees = json_decode($assignees, true) ?: [];
            }
            $assigneeIds = array_values(array_filter(array_map('intval', (array)$assignees)));
            $assigneesJson = json_encode($assigneeIds);

            if ($projectId <= 0 || empty($serviceName)) {
                echo json_encode(['success' => false, 'message' => 'Project and Service Name are required.']);
                exit;
            }

            $stmtP = $db->prepare("SELECT client_id FROM projects WHERE id = ?");
            $stmtP->execute([$projectId]);
            $clientId = intval($stmtP->fetchColumn() ?: 0);

            if ($serviceId > 0) {
                $stmt = $db->prepare("UPDATE project_services SET service_name = ?, description = ?, assignees_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$serviceName, $description, $assigneesJson, $serviceId]);
            } else {
                $stmt = $db->prepare("INSERT INTO project_services (project_id, client_id, service_name, description, assignees_json) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$projectId, $clientId, $serviceName, $description, $assigneesJson]);
                $serviceId = $db->lastInsertId();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Service saved successfully.',
                'service_id' => $serviceId,
                'service_name' => $serviceName
            ]);
            break;

        case 'delete_project_service':
            checkAuth('task-management');
            $serviceId = intval($input['id'] ?? ($_POST['id'] ?? 0));
            if (!$serviceId) {
                echo json_encode(['success' => false, 'message' => 'Service ID is required.']);
                exit;
            }
            $db->prepare("UPDATE tasks SET service_id = NULL WHERE service_id = ?")->execute([$serviceId]);
            $db->prepare("DELETE FROM project_services WHERE id = ?")->execute([$serviceId]);
            echo json_encode(['success' => true, 'message' => 'Service deleted successfully.']);
            break;

        case 'assign_project_service_members':
            checkAuth('task-management');
            $serviceId = intval($input['service_id'] ?? ($_POST['service_id'] ?? 0));
            $assignees = $input['assignees'] ?? ($_POST['assignees'] ?? []);
            if (is_string($assignees)) {
                $assignees = json_decode($assignees, true) ?: [];
            }
            $assigneeIds = array_values(array_filter(array_map('intval', (array)$assignees)));
            $assigneesJson = json_encode($assigneeIds);

            if (!$serviceId) {
                echo json_encode(['success' => false, 'message' => 'Service ID is required.']);
                exit;
            }
            $db->prepare("UPDATE project_services SET assignees_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$assigneesJson, $serviceId]);
            echo json_encode(['success' => true, 'message' => 'Service members updated successfully.']);
            break;

        case 'update_monthly_plan_item_status':
            checkAuth();
            $clientId = intval($input['client_id'] ?? ($_POST['client_id'] ?? 0));
            $month = trim($input['month'] ?? ($_POST['month'] ?? date('Y-m')));
            $rowId = trim($input['row_id'] ?? ($_POST['row_id'] ?? ''));
            $newStatus = trim($input['status'] ?? ($_POST['status'] ?? 'In Progress'));

            if ($clientId <= 0 || empty($rowId)) {
                echo json_encode(['success' => false, 'message' => 'Client ID and Row ID are required.']);
                exit;
            }

            $stPlan = $db->prepare("SELECT id, rows_json FROM client_monthly_plans WHERE client_id = ? AND month = ?");
            $stPlan->execute([$clientId, $month]);
            $plan = $stPlan->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                echo json_encode(['success' => false, 'message' => 'Monthly plan not found.']);
                exit;
            }

            $rows = json_decode($plan['rows_json'] ?? '[]', true) ?: [];
            $found = false;
            foreach ($rows as &$r) {
                if (($r['id'] ?? '') === $rowId) {
                    $r['col_status'] = $newStatus;
                    $found = true;
                    break;
                }
            }
            unset($r);

            if ($found) {
                $db->prepare("UPDATE client_monthly_plans SET rows_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                   ->execute([json_encode($rows), $plan['id']]);
                echo json_encode(['success' => true, 'message' => "Deliverable status updated to {$newStatus}."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Deliverable row not found in plan.']);
            }
            break;

        case 'get_client_monthly_plan':
            checkAuth();
            $clientId = intval($_GET['client_id'] ?? ($input['client_id'] ?? 0));
            $month = trim($_GET['month'] ?? ($input['month'] ?? date('Y-m')));
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                $month = date('Y-m');
            }

            if ($clientId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Client ID is required.']);
                exit;
            }

            // Get client info
            $stClient = $db->prepare("SELECT id, company_name, contact_person, email, phone, status FROM clients WHERE id = ?");
            $stClient->execute([$clientId]);
            $clientInfo = $stClient->fetch(PDO::FETCH_ASSOC);
            if (!$clientInfo) {
                echo json_encode(['success' => false, 'message' => 'Client not found.']);
                exit;
            }

            // Ensure table exists
            $db->exec("CREATE TABLE IF NOT EXISTS client_monthly_plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                month VARCHAR(10) NOT NULL,
                title VARCHAR(200) DEFAULT 'Monthly Strategy & Deliverables Plan',
                columns_json TEXT,
                rows_json TEXT,
                budget DECIMAL(12, 2) DEFAULT 0,
                paid_amount DECIMAL(12, 2) DEFAULT 0,
                platform_allocations_json TEXT DEFAULT NULL,
                status VARCHAR(50) DEFAULT 'Active',
                notes TEXT,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(client_id, month)
            )");
            try { $db->exec("ALTER TABLE client_monthly_plans ADD COLUMN paid_amount DECIMAL(12, 2) DEFAULT 0"); } catch (Throwable $e) {}
            try { $db->exec("ALTER TABLE client_monthly_plans ADD COLUMN platform_allocations_json TEXT DEFAULT NULL"); } catch (Throwable $e) {}

            // Check if plan exists for this client and month
            $stPlan = $db->prepare("SELECT * FROM client_monthly_plans WHERE client_id = ? AND month = ?");
            $stPlan->execute([$clientId, $month]);
            $plan = $stPlan->fetch(PDO::FETCH_ASSOC);

            // Check if previous month has a plan for copy option
            $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
            $stPrev = $db->prepare("SELECT id, month, columns_json, rows_json FROM client_monthly_plans WHERE client_id = ? AND month = ?");
            $stPrev->execute([$clientId, $prevMonth]);
            $prevPlan = $stPrev->fetch(PDO::FETCH_ASSOC);

            // Default column templates if none exist
            $defaultColumns = [
                ['id' => 'col_activity', 'name' => 'Activity / Deliverable', 'type' => 'text', 'required' => true],
                ['id' => 'col_channel', 'name' => 'Channel / Platform', 'type' => 'text', 'required' => false],
                ['id' => 'col_target', 'name' => 'Target / Quantity', 'type' => 'text', 'required' => false],
                ['id' => 'col_week', 'name' => 'Timeline / Week', 'type' => 'text', 'required' => false],
                ['id' => 'col_assignee', 'name' => 'Assignee', 'type' => 'text', 'required' => false],
                ['id' => 'col_status', 'name' => 'Status', 'type' => 'badge', 'required' => false],
                ['id' => 'col_notes', 'name' => 'Remarks / Output', 'type' => 'text', 'required' => false]
            ];

            if ($plan) {
                $columns = json_decode($plan['columns_json'] ?? '[]', true) ?: $defaultColumns;
                $rows = json_decode($plan['rows_json'] ?? '[]', true) ?: [];
                $paidAmount = floatval($plan['paid_amount'] ?? ($plan['budget'] ?? 0));
                $allocations = json_decode($plan['platform_allocations_json'] ?? '[]', true) ?: [];
                $totalAllocated = 0;
                foreach ($allocations as $al) {
                    $totalAllocated += floatval($al['amount'] ?? 0);
                }
                $currentBalance = $paidAmount - $totalAllocated;
            } else {
                $columns = $defaultColumns;
                $rows = [];
                $paidAmount = 0;
                $allocations = [];
                $totalAllocated = 0;
                $currentBalance = 0;
            }

            echo json_encode([
                'success' => true,
                'client' => $clientInfo,
                'month' => $month,
                'plan' => $plan ? [
                    'id' => intval($plan['id']),
                    'client_id' => intval($plan['client_id']),
                    'month' => $plan['month'],
                    'title' => $plan['title'] ?: "Monthly Strategy & Deliverables Plan",
                    'budget' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'platform_allocations' => $allocations,
                    'total_allocated' => $totalAllocated,
                    'current_balance' => $currentBalance,
                    'remaining_balance' => $currentBalance,
                    'status' => $plan['status'] ?: 'Active',
                    'notes' => $plan['notes'] ?: '',
                    'columns' => $columns,
                    'rows' => $rows,
                    'updated_at' => $plan['updated_at']
                ] : null,
                'columns' => $columns,
                'rows' => $rows,
                'paid_amount' => $paidAmount,
                'platform_allocations' => $allocations,
                'total_allocated' => $totalAllocated,
                'current_balance' => $currentBalance,
                'remaining_balance' => $currentBalance,
                'has_previous_month_plan' => !empty($prevPlan),
                'previous_month' => $prevMonth
            ]);
            break;

        case 'save_client_monthly_plan':
            checkAuth();
            $curUser = getCurrentUser();
            $userId = intval($curUser['id'] ?? 0);

            $clientId = intval($input['client_id'] ?? ($_POST['client_id'] ?? 0));
            $month = trim($input['month'] ?? ($_POST['month'] ?? date('Y-m')));
            if ($clientId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
                echo json_encode(['success' => false, 'message' => 'Valid Client ID and Month are required.']);
                exit;
            }

            $title = trim($input['title'] ?? ($_POST['title'] ?? "Monthly Strategy & Deliverables Plan"));
            $paidAmount = floatval($input['paid_amount'] ?? ($input['budget'] ?? ($_POST['paid_amount'] ?? ($_POST['budget'] ?? 0))));
            $budget = $paidAmount;
            $status = trim($input['status'] ?? ($_POST['status'] ?? 'Active'));
            $notes = trim($input['notes'] ?? ($_POST['notes'] ?? ''));

            $platformAllocations = $input['platform_allocations'] ?? ($_POST['platform_allocations'] ?? []);
            if (is_string($platformAllocations)) $platformAllocations = json_decode($platformAllocations, true) ?: [];
            $platformAllocationsJson = json_encode($platformAllocations);

            $columns = $input['columns'] ?? ($_POST['columns'] ?? []);
            if (is_string($columns)) $columns = json_decode($columns, true) ?: [];
            $columnsJson = json_encode($columns);

            $rows = $input['rows'] ?? ($_POST['rows'] ?? []);
            if (is_string($rows)) $rows = json_decode($rows, true) ?: [];
            $rowsJson = json_encode($rows);

            // Ensure table exists
            $db->exec("CREATE TABLE IF NOT EXISTS client_monthly_plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NOT NULL,
                month VARCHAR(10) NOT NULL,
                title VARCHAR(200) DEFAULT 'Monthly Strategy & Deliverables Plan',
                columns_json TEXT,
                rows_json TEXT,
                budget DECIMAL(12, 2) DEFAULT 0,
                paid_amount DECIMAL(12, 2) DEFAULT 0,
                platform_allocations_json TEXT DEFAULT NULL,
                status VARCHAR(50) DEFAULT 'Active',
                notes TEXT,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(client_id, month)
            )");
            try { $db->exec("ALTER TABLE client_monthly_plans ADD COLUMN paid_amount DECIMAL(12, 2) DEFAULT 0"); } catch (Throwable $e) {}
            try { $db->exec("ALTER TABLE client_monthly_plans ADD COLUMN platform_allocations_json TEXT DEFAULT NULL"); } catch (Throwable $e) {}

            // Check if exists
            $stCheck = $db->prepare("SELECT id FROM client_monthly_plans WHERE client_id = ? AND month = ?");
            $stCheck->execute([$clientId, $month]);
            $existingId = $stCheck->fetchColumn();

            if ($existingId) {
                $stUp = $db->prepare("
                    UPDATE client_monthly_plans
                    SET title = ?, columns_json = ?, rows_json = ?, budget = ?, paid_amount = ?, platform_allocations_json = ?, status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stUp->execute([$title, $columnsJson, $rowsJson, $budget, $paidAmount, $platformAllocationsJson, $status, $notes, $existingId]);
                $planId = $existingId;
            } else {
                $stIn = $db->prepare("
                    INSERT INTO client_monthly_plans (client_id, month, title, columns_json, rows_json, budget, paid_amount, platform_allocations_json, status, notes, created_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stIn->execute([$clientId, $month, $title, $columnsJson, $rowsJson, $budget, $paidAmount, $platformAllocationsJson, $status, $notes, $userId]);
                $planId = $db->lastInsertId();
            }

            logActivity($userId, 'task-management', 'SAVE_MONTHLY_PLAN', 'client_monthly_plans', $planId, "Saved monthly plan for Client #{$clientId} ({$month})");
            echo json_encode(['success' => true, 'plan_id' => $planId, 'message' => 'Monthly plan saved successfully!']);
            break;

        case 'copy_previous_month_plan':
            checkAuth();
            $curUser = getCurrentUser();
            $userId = intval($curUser['id'] ?? 0);

            $clientId = intval($input['client_id'] ?? ($_POST['client_id'] ?? 0));
            $targetMonth = trim($input['target_month'] ?? ($_POST['target_month'] ?? date('Y-m')));
            $sourceMonth = trim($input['source_month'] ?? ($_POST['source_month'] ?? date('Y-m', strtotime($targetMonth . '-01 -1 month'))));

            if ($clientId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Client ID is required.']);
                exit;
            }

            $stSrc = $db->prepare("SELECT * FROM client_monthly_plans WHERE client_id = ? AND month = ?");
            $stSrc->execute([$clientId, $sourceMonth]);
            $srcPlan = $stSrc->fetch(PDO::FETCH_ASSOC);

            if (!$srcPlan) {
                echo json_encode(['success' => false, 'message' => "No previous plan found for month {$sourceMonth}."]);
                exit;
            }

            // Reset row statuses to 'Planned'
            $rows = json_decode($srcPlan['rows_json'] ?? '[]', true) ?: [];
            foreach ($rows as &$r) {
                if (isset($r['col_status'])) $r['col_status'] = 'Planned';
            }
            unset($r);
            $rowsJson = json_encode($rows);

            $srcPaidAmount = floatval($srcPlan['paid_amount'] ?? ($srcPlan['budget'] ?? 0));
            $srcAllocJson = $srcPlan['platform_allocations_json'] ?? '[]';

            // Upsert into target month
            $stCheck = $db->prepare("SELECT id FROM client_monthly_plans WHERE client_id = ? AND month = ?");
            $stCheck->execute([$clientId, $targetMonth]);
            $existingId = $stCheck->fetchColumn();

            if ($existingId) {
                $db->prepare("
                    UPDATE client_monthly_plans
                    SET title = ?, columns_json = ?, rows_json = ?, budget = ?, paid_amount = ?, platform_allocations_json = ?, status = 'Active', updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ")->execute([$srcPlan['title'], $srcPlan['columns_json'], $rowsJson, $srcPaidAmount, $srcPaidAmount, $srcAllocJson, $existingId]);
                $planId = $existingId;
            } else {
                $db->prepare("
                    INSERT INTO client_monthly_plans (client_id, month, title, columns_json, rows_json, budget, paid_amount, platform_allocations_json, status, notes, created_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ")->execute([$clientId, $targetMonth, $srcPlan['title'], $srcPlan['columns_json'], $rowsJson, $srcPaidAmount, $srcPaidAmount, $srcAllocJson, $srcPlan['notes'], $userId]);
                $planId = $db->lastInsertId();
            }

            logActivity($userId, 'task-management', 'COPY_MONTHLY_PLAN', 'client_monthly_plans', $planId, "Copied monthly plan from {$sourceMonth} to {$targetMonth} for Client #{$clientId}");
            echo json_encode(['success' => true, 'plan_id' => $planId, 'columns' => json_decode($srcPlan['columns_json'], true), 'rows' => $rows, 'message' => "Successfully copied plan from {$sourceMonth}!"]);
            break;

        // =========================================================
        // DAILY WORKLOG ENGINE (EMPLOYEES, LEADS, HR, SUPER ADMIN)
        // =========================================================
        case 'get_daily_worklogs':
            checkAuth();
            $curUser = getCurrentUser();
            $curUid = intval($curUser['id'] ?? 0);
            $isSuperAdmin = isSuperAdminUser($curUser);
            $isHr = isHrAdmin();
            $isLead = isLeadOrAdmin() || isTeamLeadOrManager();
            $curDept = trim($curUser['department'] ?? '');

            $viewScope = trim($_GET['view_scope'] ?? 'all');
            $logDate = trim($_GET['date'] ?? '');
            $startDate = trim($_GET['start_date'] ?? '');
            $endDate = trim($_GET['end_date'] ?? '');
            $targetUserId = intval($_GET['user_id'] ?? 0);
            $roleFilter = trim($_GET['role_filter'] ?? 'all');
            $deptFilter = trim($_GET['department'] ?? '');
            $projectId = intval($_GET['project_id'] ?? 0);
            $statusFilter = trim($_GET['status'] ?? '');
            $search = trim($_GET['search'] ?? '');

            // Base WHERE clause
            $where = ["1=1"];
            $params = [];

            // Permission scoping
            if ($viewScope === 'my' || (!$isSuperAdmin && !$isHr && !$isLead)) {
                // Regular employees only see their own worklogs
                $where[] = "dw.user_id = ?";
                $params[] = $curUid;
            } else {
                // Team Lead, HR, or Super Admin viewing team/company worklogs
                if ($isSuperAdmin) {
                    // Super Admin sees ALL (Employees, HR, and Team Leads)
                    if ($roleFilter === 'employee') {
                        $where[] = "r.name NOT IN ('Super Admin', 'System Super Admin', 'HR', 'Team Lead', 'DM Lead', 'Manager', 'Agency Admin')";
                    } elseif ($roleFilter === 'lead') {
                        $where[] = "r.name IN ('Team Lead', 'DM Lead', 'Manager')";
                    } elseif ($roleFilter === 'hr') {
                        $where[] = "(r.name = 'HR' OR u.department = 'HR')";
                    }
                } elseif ($isHr) {
                    // HR sees regular employees across departments + HR's own worklogs
                    $where[] = "(r.name NOT IN ('Super Admin', 'System Super Admin', 'Team Lead', 'DM Lead', 'Manager', 'Agency Admin') OR dw.user_id = ?)";
                    $params[] = $curUid;
                } elseif ($isLead) {
                    // Lead sees employees in their department OR assigned to their handled projects + Lead's own worklogs
                    $handledPids = getUserHandledProjectIds($db, $curUid);
                    $handledIn = !empty($handledPids) ? implode(',', array_map('intval', $handledPids)) : '0';
                    $where[] = "((u.department = ? AND r.name NOT IN ('Super Admin', 'System Super Admin', 'Agency Admin')) OR dw.project_id IN ({$handledIn}) OR dw.user_id = ?)";
                    $params[] = $curDept;
                    $params[] = $curUid;
                }
            }

            // User filter
            if ($targetUserId > 0) {
                // If not Super Admin/HR/Lead, ignore targetUserId unless it's own ID
                if ($isSuperAdmin || $isHr || $isLead || $targetUserId === $curUid) {
                    $where[] = "dw.user_id = ?";
                    $params[] = $targetUserId;
                }
            }

            // Date filtering
            if (!empty($logDate)) {
                $where[] = "dw.log_date = ?";
                $params[] = $logDate;
            } elseif (!empty($startDate) && !empty($endDate)) {
                $where[] = "dw.log_date BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            // Department filter
            if (!empty($deptFilter)) {
                $where[] = "u.department = ?";
                $params[] = $deptFilter;
            }

            // Project filter
            if ($projectId > 0) {
                $where[] = "dw.project_id = ?";
                $params[] = $projectId;
            }

            // Status filter
            if (!empty($statusFilter)) {
                $where[] = "dw.status = ?";
                $params[] = $statusFilter;
            }

            // Search query
            if (!empty($search)) {
                $where[] = "(dw.title LIKE ? OR dw.description LIKE ? OR dw.challenges LIKE ? OR u.full_name LIKE ? OR p.project_name LIKE ?)";
                $like = '%' . $search . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $sqlWhere = implode(' AND ', $where);
            $query = "
                SELECT dw.*, 
                       u.full_name as user_name, u.email as user_email, u.department as user_dept,
                       r.name as role_name,
                       p.project_name, c.company_name as client_name,
                       t.task_code, t.title as task_title
                FROM daily_worklogs dw
                JOIN users u ON dw.user_id = u.id
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN projects p ON dw.project_id = p.id
                LEFT JOIN clients c ON p.client_id = c.id
                LEFT JOIN tasks t ON dw.task_id = t.id
                WHERE {$sqlWhere}
                ORDER BY dw.log_date DESC, dw.id DESC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $worklogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute Statistics
            $today = date('Y-m-d');
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');

            // Personal user item stats
            $stMyToday = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed FROM daily_worklogs WHERE user_id = ? AND log_date = ?");
            $stMyToday->execute([$curUid, $today]);
            $myRow = $stMyToday->fetch(PDO::FETCH_ASSOC);
            $myItemsToday = intval($myRow['total'] ?? 0);
            $myCompletedToday = intval($myRow['completed'] ?? 0);
            $myNotCompletedToday = $myItemsToday - $myCompletedToday;

            $stMyWeek = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed FROM daily_worklogs WHERE user_id = ? AND log_date BETWEEN ? AND ?");
            $stMyWeek->execute([$curUid, $weekStart, $weekEnd]);
            $myWeekRow = $stMyWeek->fetch(PDO::FETCH_ASSOC);
            $myItemsWeek = intval($myWeekRow['total'] ?? 0);
            $myCompletedWeek = intval($myWeekRow['completed'] ?? 0);

            // Company item stats for today
            $stTotalToday = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed FROM daily_worklogs WHERE log_date = ?");
            $stTotalToday->execute([$today]);
            $compRow = $stTotalToday->fetch(PDO::FETCH_ASSOC);
            $compItemsToday = intval($compRow['total'] ?? 0);
            $compCompletedToday = intval($compRow['completed'] ?? 0);
            $compNotCompletedToday = $compItemsToday - $compCompletedToday;

            $stSubsToday = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM daily_worklogs WHERE log_date = ?");
            $stSubsToday->execute([$today]);
            $subsTodayCount = intval($stSubsToday->fetchColumn() ?: 0);

            // Group worklogs by employee for list-type view
            $byEmployee = [];
            $totalCompletedCount = 0;
            $totalNotCompletedCount = 0;

            foreach ($worklogs as $wl) {
                $uid = intval($wl['user_id']);
                if (!isset($byEmployee[$uid])) {
                    $byEmployee[$uid] = [
                        'user_id' => $uid,
                        'user_name' => $wl['user_name'],
                        'user_email' => $wl['user_email'],
                        'department' => $wl['user_dept'],
                        'role_name' => $wl['role_name'],
                        'total_items' => 0,
                        'completed_items' => 0,
                        'not_completed_items' => 0,
                        'items' => []
                    ];
                }
                $byEmployee[$uid]['total_items']++;
                if (($wl['status'] ?? '') === 'Completed') {
                    $byEmployee[$uid]['completed_items']++;
                    $totalCompletedCount++;
                } else {
                    $byEmployee[$uid]['not_completed_items']++;
                    $totalNotCompletedCount++;
                }
                $byEmployee[$uid]['items'][] = $wl;
            }

            // Missing staff logs today (Active non-client staff who have not submitted today)
            $missingUsers = [];
            if ($isSuperAdmin || $isHr) {
                $stMissing = $db->query("
                    SELECT u.id, u.full_name, u.department, r.name as role_name
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.is_active = 1 
                      AND r.name != 'Client'
                      AND u.id NOT IN (SELECT DISTINCT user_id FROM daily_worklogs WHERE log_date = '{$today}')
                    ORDER BY u.department ASC, u.full_name ASC
                ");
                if ($stMissing) {
                    $missingUsers = $stMissing->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            echo json_encode([
                'success' => true,
                'worklogs' => $worklogs,
                'worklogs_by_employee' => array_values($byEmployee),
                'current_user_id' => $curUid,
                'is_super_admin' => $isSuperAdmin,
                'is_hr' => $isHr,
                'is_lead' => $isLead,
                'stats' => [
                    'total_items' => count($worklogs),
                    'completed_count' => $totalCompletedCount,
                    'not_completed_count' => $totalNotCompletedCount,
                    'my_items_today' => $myItemsToday,
                    'my_completed_today' => $myCompletedToday,
                    'my_not_completed_today' => $myNotCompletedToday,
                    'my_items_week' => $myItemsWeek,
                    'my_completed_week' => $myCompletedWeek,
                    'company_items_today' => $compItemsToday,
                    'company_completed_today' => $compCompletedToday,
                    'company_not_completed_today' => $compNotCompletedToday,
                    'submissions_today_count' => $subsTodayCount,
                    'missing_count_today' => count($missingUsers)
                ],
                'missing_users' => $missingUsers
            ]);
            break;

        case 'save_daily_worklog':
            checkAuth();
            $curUser = getCurrentUser();
            $curUid = intval($curUser['id'] ?? 0);
            $isSuperAdmin = isSuperAdminUser($curUser);

            $id = intval($input['id'] ?? ($_POST['id'] ?? 0));
            $targetUserId = intval($input['user_id'] ?? ($_POST['user_id'] ?? $curUid));
            // Only Super Admin can log for another user
            if (!$isSuperAdmin || $targetUserId <= 0) {
                $targetUserId = $curUid;
            }

            $logDate = trim($input['log_date'] ?? ($_POST['log_date'] ?? date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
                $logDate = date('Y-m-d');
            }

            // Check if batch list-type items provided
            $batchItems = $input['items'] ?? null;
            if (is_array($batchItems) && count($batchItems) > 0) {
                $inserted = 0;
                $stBatch = $db->prepare("
                    INSERT INTO daily_worklogs (
                        user_id, log_date, title, description, status, reason, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                foreach ($batchItems as $bItem) {
                    $bTitle = trim($bItem['title'] ?? ($bItem['work'] ?? ''));
                    if (empty($bTitle)) continue;
                    $bDesc = trim($bItem['description'] ?? ($bItem['work_detail'] ?? ''));
                    $bStatus = trim($bItem['status'] ?? 'Completed');
                    $bReason = trim($bItem['reason'] ?? '');
                    $stBatch->execute([$targetUserId, $logDate, $bTitle, $bDesc, $bStatus, $bReason]);
                    $inserted++;
                }
                echo json_encode(['success' => true, 'message' => "Successfully added {$inserted} work items!"]);
                break;
            }

            $title = trim($input['title'] ?? ($_POST['title'] ?? ($input['work'] ?? ($_POST['work'] ?? ''))));
            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Work / task title is required.']);
                exit;
            }

            $description = trim($input['description'] ?? ($_POST['description'] ?? ($input['work_detail'] ?? ($_POST['work_detail'] ?? ''))));
            $status = trim($input['status'] ?? ($_POST['status'] ?? 'Completed'));
            $reason = trim($input['reason'] ?? ($_POST['reason'] ?? ''));

            if ($id > 0) {
                // Check ownership or super admin
                $stCheck = $db->prepare("SELECT user_id FROM daily_worklogs WHERE id = ?");
                $stCheck->execute([$id]);
                $ownerId = $stCheck->fetchColumn();
                if (!$ownerId) {
                    echo json_encode(['success' => false, 'message' => 'Worklog entry not found.']);
                    exit;
                }
                if ($ownerId != $curUid && !$isSuperAdmin) {
                    echo json_encode(['success' => false, 'message' => 'Permission denied to edit this worklog.']);
                    exit;
                }

                $stUp = $db->prepare("
                    UPDATE daily_worklogs
                    SET log_date = ?, title = ?, description = ?, status = ?, reason = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stUp->execute([
                    $logDate, $title, $description, $status, $reason, $id
                ]);
                $worklogId = $id;
                $msg = 'Daily worklog updated successfully!';
            } else {
                $stIn = $db->prepare("
                    INSERT INTO daily_worklogs (
                        user_id, log_date, title, description, status, reason, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stIn->execute([
                    $targetUserId, $logDate, $title, $description, $status, $reason
                ]);
                $worklogId = $db->lastInsertId();
                $msg = 'Daily worklog added successfully!';
            }

            echo json_encode(['success' => true, 'worklog_id' => $worklogId, 'message' => $msg]);
            break;

        case 'delete_daily_worklog':
            checkAuth();
            $curUser = getCurrentUser();
            $curUid = intval($curUser['id'] ?? 0);
            $isSuperAdmin = isSuperAdminUser($curUser);

            $id = intval($input['id'] ?? ($_POST['id'] ?? ($_GET['id'] ?? 0)));
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Worklog ID is required.']);
                exit;
            }

            $stCheck = $db->prepare("SELECT user_id FROM daily_worklogs WHERE id = ?");
            $stCheck->execute([$id]);
            $ownerId = $stCheck->fetchColumn();
            if (!$ownerId) {
                echo json_encode(['success' => false, 'message' => 'Worklog entry not found.']);
                exit;
            }
            if ($ownerId != $curUid && !$isSuperAdmin) {
                echo json_encode(['success' => false, 'message' => 'Permission denied to delete this worklog.']);
                exit;
            }

            $db->prepare("DELETE FROM daily_worklogs WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Daily worklog entry deleted successfully.']);
            break;

        case 'get_worklog_form_context':
            checkAuth();
            $curUser = getCurrentUser();
            $curUid = intval($curUser['id'] ?? 0);
            $isSuperAdmin = isSuperAdminUser($curUser);
            $isHr = isHrAdmin();
            $isLead = isLeadOrAdmin() || isTeamLeadOrManager();

            // Projects list
            $projects = $db->query("
                SELECT p.id, p.project_name, p.department, c.company_name as client_name
                FROM projects p
                LEFT JOIN clients c ON p.client_id = c.id
                WHERE COALESCE(p.is_current, 1) = 1
                ORDER BY p.project_name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Tasks assigned to current user
            $stTasks = $db->prepare("
                SELECT t.id, t.task_code, t.title, t.project_id, p.project_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE t.assigned_to = ? AND t.is_deleted = 0 AND t.status != 'COMPLETED'
                ORDER BY t.id DESC
            ");
            $stTasks->execute([$curUid]);
            $tasks = $stTasks->fetchAll(PDO::FETCH_ASSOC);

            // Active staff list for filters
            $staffList = [];
            if ($isSuperAdmin || $isHr || $isLead) {
                $stStaff = $db->query("
                    SELECT u.id, u.full_name, u.department, r.name as role_name
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.is_active = 1 AND r.name != 'Client'
                    ORDER BY u.full_name ASC
                ");
                if ($stStaff) $staffList = $stStaff->fetchAll(PDO::FETCH_ASSOC);
            }

            // Departments
            $depts = $db->query("SELECT DISTINCT name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];

            echo json_encode([
                'success' => true,
                'projects' => $projects,
                'tasks' => $tasks,
                'staff' => $staffList,
                'departments' => $depts
            ]);
            break;

        case 'get_project_details':
            checkAuth('task-management');
            $projectId = intval($_GET['project_id'] ?? 0);
            if ($projectId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Project ID is required.']);
                exit;
            }

            $stmtP = $db->prepare("SELECT p.*, c.company_name, c.contact_person, c.email as client_email, c.phone as client_phone,
                                   u.full_name as team_lead_name, cb.full_name as created_by_name, ca.full_name as client_approved_by_name,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted=0) as total_tasks,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='COMPLETED' AND t.is_deleted=0) as completed_tasks,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='IN PROGRESS' AND t.is_deleted=0) as in_progress_tasks,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='PENDING' AND t.is_deleted=0) as pending_tasks,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='UNDER REVIEW' AND t.is_deleted=0) as under_review_tasks,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='SENT FOR CLIENT APPROVAL' AND t.is_deleted=0) as awaiting_client_approval_tasks,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND COALESCE(t.parent_task_id, 0) > 0 AND t.is_deleted=0) as subtasks_count
                                   FROM projects p
                                   JOIN clients c ON p.client_id = c.id
                                   LEFT JOIN users u ON p.team_lead_id = u.id
                                   LEFT JOIN users cb ON p.created_by = cb.id
                                   LEFT JOIN users ca ON p.client_approved_by = ca.id
                                   WHERE p.id = ?");
            $stmtP->execute([$projectId]);
            $project = $stmtP->fetch();
            if (!$project) {
                echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            $pdUser = getCurrentUser();
            $pdUid = intval($pdUser['id'] ?? 0);
            $handlerIds = getProjectHandlerIds($db, $projectId);
            $handlersList = [];
            if (!empty($handlerIds)) {
                $ph = implode(',', array_fill(0, count($handlerIds), '?'));
                $stH = $db->prepare("SELECT u.id, u.full_name, u.department, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id IN ($ph) ORDER BY u.full_name ASC");
                $stH->execute($handlerIds);
                $handlersList = $stH->fetchAll();
            }
            $project['handler_ids'] = $handlerIds;
            $project['handlers'] = $handlersList;
            $observerIds = getProjectObserverIds($db, $projectId, $project['team_lead_id'] ?? 0);
            $observersList = [];
            if (!empty($observerIds)) {
                $phO = implode(',', array_fill(0, count($observerIds), '?'));
                $stO = $db->prepare("SELECT u.id, u.full_name, u.department, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id IN ($phO) ORDER BY u.full_name ASC");
                $stO->execute($observerIds);
                $observersList = $stO->fetchAll();
            }
            $project['observer_ids'] = $observerIds;
            $project['observers'] = $observersList;

            $workingIds = getProjectWorkingEmployeeIds($db, $projectId);
            $workingList = [];
            if (!empty($workingIds)) {
                $phW = implode(',', array_fill(0, count($workingIds), '?'));
                $stW = $db->prepare("SELECT u.id, u.full_name, u.department, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id IN ($phW) ORDER BY u.full_name ASC");
                $stW->execute($workingIds);
                $workingList = $stW->fetchAll();
            }
            $project['working_employee_ids'] = $workingIds;
            $project['working_employees'] = $workingList;
            $project['is_working_member'] = in_array($pdUid, $workingIds, true);
            $project['is_handler'] = in_array($pdUid, $handlerIds, true);
            $canManageThisProject = canManageProject($db, $projectId, $pdUid);
            $project['can_manage_project'] = $canManageThisProject;

            $stmtComp = $db->prepare("SELECT pc.*, u.full_name as added_by_name FROM project_competitors pc LEFT JOIN users u ON pc.created_by = u.id WHERE pc.project_id = ? ORDER BY pc.id ASC");
            $stmtComp->execute([$projectId]);
            $competitors = $stmtComp->fetchAll();

            $stmtTasks = $db->prepare("SELECT t.*, u_assign.full_name as assigned_to_name, u_lead.full_name as team_lead_name,
                                       pt.task_code as parent_task_code, pt.title as parent_task_title,
                                       (SELECT COUNT(*) FROM tasks st WHERE st.parent_task_id = t.id AND st.is_deleted = 0) as subtasks_count
                                       FROM tasks t
                                       LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
                                       LEFT JOIN users u_lead ON t.team_lead_id = u_lead.id
                                       LEFT JOIN tasks pt ON t.parent_task_id = pt.id
                                       WHERE t.project_id = ? AND t.is_deleted = 0
                                       ORDER BY 
                                           CASE 
                                               WHEN UPPER(TRIM(t.status)) = 'IN PROGRESS' THEN 1
                                               WHEN UPPER(TRIM(t.status)) = 'UNDER REVIEW' THEN 2
                                               WHEN UPPER(TRIM(t.status)) = 'PENDING LEAD APPROVAL' THEN 3
                                               WHEN UPPER(TRIM(t.status)) = 'SENT FOR CLIENT APPROVAL' THEN 4
                                               WHEN UPPER(TRIM(t.status)) = 'REVISION REQUIRED' THEN 5
                                               WHEN UPPER(TRIM(t.status)) = 'PENDING' THEN 6
                                               WHEN UPPER(TRIM(t.status)) = 'COMPLETED' THEN 7
                                               ELSE 8
                                           END ASC,
                                           CASE t.priority WHEN 'Urgent' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END,
                                           t.id DESC");
            $stmtTasks->execute([$projectId]);
            $projectTasks = $stmtTasks->fetchAll();

            if (!$canManageThisProject) {
                $projectTasks = array_values(array_filter($projectTasks, function ($pt) use ($pdUid, $db) {
                    return isTaskVisibleToUser($pt, $pdUid, $db);
                }));
                $project['total_tasks'] = count($projectTasks);
                $project['completed_tasks'] = 0;
                $project['in_progress_tasks'] = 0;
                $project['pending_tasks'] = 0;
                $project['under_review_tasks'] = 0;
                $project['awaiting_client_approval_tasks'] = 0;
                foreach ($projectTasks as $vpt) {
                    if ($vpt['status'] === 'COMPLETED') $project['completed_tasks']++;
                    elseif ($vpt['status'] === 'IN PROGRESS') $project['in_progress_tasks']++;
                    elseif ($vpt['status'] === 'PENDING') $project['pending_tasks']++;
                    elseif ($vpt['status'] === 'UNDER REVIEW') $project['under_review_tasks']++;
                    elseif ($vpt['status'] === 'SENT FOR CLIENT APPROVAL') $project['awaiting_client_approval_tasks']++;
                }
            }

            foreach ($projectTasks as &$pt) {
                $pt['sla_status'] = calculateTaskSLAStatus($pt);
                $pt['assignee_ids'] = json_decode($pt['assignees_json'] ?? '[]', true) ?: [];
                $pt['observer_ids'] = json_decode($pt['observers_json'] ?? '[]', true) ?: [];
            }

            echo json_encode([
                'success' => true,
                'project' => $project,
                'can_manage_project' => $canManageThisProject,
                'competitors' => $competitors,
                'tasks' => $projectTasks
            ]);
            break;

        case 'create_project':
            checkAuth('task-management');
            $user = getCurrentUser();
            if (!isLeadOrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Leads and Admins can create projects.']);
                exit;
            }

            $clientId = intval($input['client_id'] ?? 0);
            $projectName = trim($input['project_name'] ?? '');
            $description = trim($input['description'] ?? '');
            $aboutProject = trim($input['about_project'] ?? '');
            $projectUrl = trim($input['project_url'] ?? '');
            $deliverablesSummary = trim($input['deliverables_summary'] ?? '');
            $targetAudience = trim($input['target_audience'] ?? '');
            $startDate = trim($input['start_date'] ?? date('Y-m-d'));
            $deadline = !empty($input['deadline']) ? trim($input['deadline']) : date('Y-m-d', strtotime('+14 days'));
            $budgetInfo = trim($input['budget_info'] ?? '');
            $department = trim($input['department'] ?? ($user['department'] ?: 'Development'));
            $priority = $input['priority'] ?? 'High';
            $status = $input['status'] ?? 'In Progress';
            $isCurrent = isset($input['is_current']) ? intval($input['is_current']) : 1;

            $teamLeadId = intval($input['team_lead_id'] ?? 0);
            if ($teamLeadId <= 0) {
                $teamLeadId = intval($user['id']);
            } else {
                $chkLead = $db->prepare("SELECT id FROM users WHERE id = ?");
                $chkLead->execute([$teamLeadId]);
                if (!$chkLead->fetchColumn()) {
                    $teamLeadId = intval($user['id']);
                }
            }

            $handlersInput = $input['handlers'] ?? ($input['handler_ids'] ?? []);
            $handlerIds = is_array($handlersInput) ? array_values(array_unique(array_filter(array_map('intval', $handlersInput)))) : [];

            $observersInput = $input['observers'] ?? ($input['observer_ids'] ?? []);
            $customObsIds = is_array($observersInput) ? array_map('intval', $observersInput) : [];
            $observerIds = array_values(array_unique(array_filter(array_merge(getDefaultProjectObserverIds($db, $teamLeadId), $customObsIds), function ($x) { return $x > 0; })));

            if (!$clientId || empty($projectName)) {
                echo json_encode(['success' => false, 'message' => 'Client and Project Name are required.']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO projects (
                client_id, project_name, description, about_project, project_url, deliverables_summary, target_audience, start_date, budget_info, priority, status, team_lead_id, handlers_json, observers_json, department, deadline, is_current, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $clientId, $projectName, $description, $aboutProject, $projectUrl, $deliverablesSummary, $targetAudience, $startDate, $budgetInfo, $priority, $status, $teamLeadId, json_encode($handlerIds), json_encode($observerIds), $department, $deadline, $isCurrent, $user['id']
            ]);
            $projectId = $db->lastInsertId();

            if (!empty($handlerIds)) {
                syncProjectHandlers($db, $projectId, $handlerIds);
                notifyProjectHandlers($db, $projectId, $projectName, $handlerIds, $user);
            }

            // Optional initial competitors array
            $competitorsInput = $input['competitors'] ?? [];
            if (is_array($competitorsInput) && !empty($competitorsInput)) {
                $stmtComp = $db->prepare("INSERT INTO project_competitors (project_id, competitor_name, website_url, social_links, strengths, weaknesses, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($competitorsInput as $comp) {
                    $cName = trim($comp['competitor_name'] ?? '');
                    if (empty($cName)) continue;
                    $stmtComp->execute([
                        $projectId,
                        $cName,
                        trim($comp['website_url'] ?? ''),
                        trim($comp['social_links'] ?? ''),
                        trim($comp['strengths'] ?? ''),
                        trim($comp['weaknesses'] ?? ''),
                        trim($comp['notes'] ?? ''),
                        $user['id']
                    ]);
                }
            }

            logActivity($user['id'], 'task-management', 'CREATE_PROJECT', 'projects', $projectId, "Created project {$projectName}");

            echo json_encode(['success' => true, 'project_id' => $projectId, 'message' => 'Project created successfully.']);
            break;

        case 'update_project':
            checkAuth('task-management');
            $user = getCurrentUser();

            $projectId = intval($input['project_id'] ?? $input['id'] ?? 0);
            $projectName = trim($input['project_name'] ?? '');
            if ($projectId <= 0 || empty($projectName)) {
                echo json_encode(['success' => false, 'message' => 'Project ID and Project Name are required.']);
                exit;
            }

            if (!canManageProject($db, $projectId, $user['id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Leads, Admins, or Project Handlers can edit project overview & details.']);
                exit;
            }

            $stmtOld = $db->prepare("SELECT * FROM projects WHERE id = ?");
            $stmtOld->execute([$projectId]);
            $existing = $stmtOld->fetch();
            if (!$existing) {
                echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            $clientId = intval($input['client_id'] ?? $existing['client_id']);
            $description = trim($input['description'] ?? $existing['description'] ?? '');
            $aboutProject = trim($input['about_project'] ?? $existing['about_project'] ?? '');
            $projectUrl = trim($input['project_url'] ?? $existing['project_url'] ?? '');
            $deliverablesSummary = trim($input['deliverables_summary'] ?? $existing['deliverables_summary'] ?? '');
            $targetAudience = trim($input['target_audience'] ?? $existing['target_audience'] ?? '');
            $startDate = trim($input['start_date'] ?? $existing['start_date'] ?? '');
            $deadline = trim($input['deadline'] ?? $existing['deadline'] ?? '');
            $budgetInfo = trim($input['budget_info'] ?? $existing['budget_info'] ?? '');
            $department = trim($input['department'] ?? $existing['department'] ?? 'Development');
            $priority = $input['priority'] ?? $existing['priority'] ?? 'High';
            $status = $input['status'] ?? $existing['status'] ?? 'In Progress';
            $teamLeadId = intval($input['team_lead_id'] ?? $existing['team_lead_id']);
            $isCurrent = isset($input['is_current']) ? intval($input['is_current']) : intval($existing['is_current'] ?? 1);

            $stmtUpdP = $db->prepare("UPDATE projects SET 
                client_id = ?, project_name = ?, description = ?, about_project = ?, project_url = ?, 
                deliverables_summary = ?, target_audience = ?, start_date = ?, budget_info = ?, 
                priority = ?, status = ?, team_lead_id = ?, department = ?, deadline = ?, is_current = ?
                WHERE id = ?");
            $stmtUpdP->execute([
                $clientId, $projectName, $description, $aboutProject, $projectUrl,
                $deliverablesSummary, $targetAudience, $startDate ?: null, $budgetInfo,
                $priority, $status, $teamLeadId, $department, $deadline ?: null, $isCurrent,
                $projectId
            ]);

            if (array_key_exists('handlers', $input) || array_key_exists('handler_ids', $input)) {
                if (!isLeadOrAdmin()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Leads or Super Admins can add or change Project Handlers.']);
                    exit;
                }
                $prevH = getProjectHandlerIds($db, $projectId);
                $handlersInput = $input['handlers'] ?? ($input['handler_ids'] ?? []);
                $newH = syncProjectHandlers($db, $projectId, is_array($handlersInput) ? $handlersInput : []);
                $addedH = array_values(array_diff($newH, $prevH));
                if (!empty($addedH)) {
                    notifyProjectHandlers($db, $projectId, $projectName, $addedH, $user);
                }
            }

            if (array_key_exists('working_employees', $input) || array_key_exists('working_employee_ids', $input)) {
                $prevW = getProjectWorkingEmployeeIds($db, $projectId);
                $workInput = $input['working_employees'] ?? ($input['working_employee_ids'] ?? []);
                $newW = syncProjectWorkingEmployees($db, $projectId, is_array($workInput) ? $workInput : [], $user['id']);
                $addedW = array_values(array_diff($newW, $prevW));
                if (!empty($addedW)) {
                    $stClName = $db->prepare("SELECT c.company_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
                    $stClName->execute([$projectId]);
                    $clName = $stClName->fetchColumn() ?: '';
                    notifyProjectWorkingEmployees($db, $projectId, $projectName, $clName, $addedW, $user);
                }
            }

            logActivity($user['id'], 'task-management', 'UPDATE_PROJECT', 'projects', $projectId, "Updated project overview & details for '{$projectName}'");
            echo json_encode(['success' => true, 'message' => 'Project overview & details updated successfully.']);
            break;

        case 'assign_project_handlers':
            checkAuth('task-management');
            $user = getCurrentUser();
            if (!isLeadOrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Leads or Super Admins can add or assign Project Handlers.']);
                exit;
            }

            $projectId = intval($input['project_id'] ?? $input['id'] ?? 0);
            if ($projectId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Project ID is required.']);
                exit;
            }
            $stPr = $db->prepare("SELECT id, project_name FROM projects WHERE id = ?");
            $stPr->execute([$projectId]);
            $projRow = $stPr->fetch();
            if (!$projRow) {
                echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            $handlersInput = $input['handlers'] ?? ($input['handler_ids'] ?? []);
            $prevH = getProjectHandlerIds($db, $projectId);
            $newH = syncProjectHandlers($db, $projectId, is_array($handlersInput) ? $handlersInput : []);
            $addedH = array_values(array_diff($newH, $prevH));
            if (!empty($addedH)) {
                notifyProjectHandlers($db, $projectId, $projRow['project_name'], $addedH, $user);
            }

            $handlersMeta = [];
            if (!empty($newH)) {
                $ph = implode(',', array_fill(0, count($newH), '?'));
                $stHM = $db->prepare("SELECT u.id, u.full_name, u.department, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id IN ($ph) ORDER BY u.full_name ASC");
                $stHM->execute($newH);
                $handlersMeta = $stHM->fetchAll();
            }

            logActivity($user['id'], 'task-management', 'ASSIGN_PROJECT_HANDLERS', 'projects', $projectId, "Updated Project Handlers for '{$projRow['project_name']}' (" . count($newH) . " handler(s))");
            echo json_encode([
                'success' => true,
                'project_id' => $projectId,
                'handler_ids' => $newH,
                'handlers' => $handlersMeta,
                'message' => "Project Handlers updated for '{$projRow['project_name']}' (" . count($newH) . " assigned)."
            ]);
            break;

        case 'assign_project_working_employees':
            checkAuth('task-management');
            $user = getCurrentUser();
            $projectId = intval($input['project_id'] ?? $input['id'] ?? 0);
            if ($projectId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Project ID is required.']);
                exit;
            }
            // Accessible by Team Lead, Super Admin, AND Project Handler
            if (!canManageProject($db, $projectId, $user['id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Leads, Super Admins, or Project Handlers can assign working employees to this project.']);
                exit;
            }

            $stPr = $db->prepare("SELECT p.id, p.project_name, c.company_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
            $stPr->execute([$projectId]);
            $projRow = $stPr->fetch();
            if (!$projRow) {
                echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            $membersInput = $input['working_employees'] ?? ($input['working_employee_ids'] ?? ($input['member_ids'] ?? []));
            $prevW = getProjectWorkingEmployeeIds($db, $projectId);
            $newW = syncProjectWorkingEmployees($db, $projectId, is_array($membersInput) ? $membersInput : [], $user['id']);
            $addedW = array_values(array_diff($newW, $prevW));
            if (!empty($addedW)) {
                notifyProjectWorkingEmployees($db, $projectId, $projRow['project_name'], $projRow['company_name'] ?? '', $addedW, $user);
            }

            $membersMeta = [];
            if (!empty($newW)) {
                $phW = implode(',', array_fill(0, count($newW), '?'));
                $stWM = $db->prepare("SELECT u.id, u.full_name, u.department, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id IN ($phW) ORDER BY u.full_name ASC");
                $stWM->execute($newW);
                $membersMeta = $stWM->fetchAll();
            }

            $addedCount = count($addedW);
            logActivity($user['id'], 'task-management', 'ASSIGN_PROJECT_MEMBERS', 'projects', $projectId, "Updated working team for '{$projRow['project_name']}' (" . count($newW) . " member(s), {$addedCount} newly added)");
            echo json_encode([
                'success' => true,
                'project_id' => $projectId,
                'working_employee_ids' => $newW,
                'working_employees' => $membersMeta,
                'newly_notified_count' => $addedCount,
                'message' => "Working team updated for '{$projRow['project_name']}' (" . count($newW) . " working member(s)" . ($addedCount > 0 ? ", notification sent to {$addedCount} newly added employee(s)" : "") . ")."
            ]);
            break;

        case 'approve_project_client_completion':
            checkAuth('task-management');
            $user = getCurrentUser();
            $projectId = intval($input['project_id'] ?? 0);
            $approved = isset($input['approved']) ? intval($input['approved']) : 1;

            if ($projectId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Project ID is required.']);
                exit;
            }
            if (!canManageProject($db, $projectId, $user['id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Project Handlers, Team Leads, or Super Admins can mark client approval for this project.']);
                exit;
            }

            $stmtP = $db->prepare("SELECT * FROM projects WHERE id = ?");
            $stmtP->execute([$projectId]);
            $proj = $stmtP->fetch();
            if (!$proj) {
                echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            if ($approved === 1) {
                $stmtUnapproved = $db->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND is_deleted = 0 AND status NOT IN ('SENT FOR CLIENT APPROVAL', 'COMPLETED') AND COALESCE(handler_approved, 0) = 0");
                $stmtUnapproved = $db->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND is_deleted = 0 AND status NOT IN ('PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL', 'COMPLETED') AND COALESCE(handler_approved, 0) = 0");
                $stmtUnapproved->execute([$projectId]);
                $pendingHandlerCnt = intval($stmtUnapproved->fetchColumn());
                if ($pendingHandlerCnt > 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Cannot complete project yet: {$pendingHandlerCnt} task(s)/subtask(s) still require Project Handler approval before client approval."
                    ]);
                    exit;
                }

                // Mark any SENT FOR CLIENT APPROVAL tasks as client-approved and COMPLETED
                if (!isLeadOrAdmin()) {
                    $stmtUnlead = $db->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND is_deleted = 0 AND status != 'COMPLETED' AND COALESCE(lead_approved, 0) = 0");
                    $stmtUnlead->execute([$projectId]);
                    $pendingLeadCnt = intval($stmtUnlead->fetchColumn());
                    if ($pendingLeadCnt > 0) {
                        http_response_code(403);
                        echo json_encode([
                            'success' => false,
                            'message' => "Team Lead / Super Admin approval is required on {$pendingLeadCnt} task(s) before Project Handler can complete the project."
                        ]);
                        exit;
                    }
                }

                // Mark any Lead-approved / SENT FOR CLIENT APPROVAL tasks as client-approved and COMPLETED
                $nowTs = date('Y-m-d H:i:s');
                $stmtTasksUpd = $db->prepare("SELECT id, task_code, status FROM tasks WHERE project_id = ? AND is_deleted = 0 AND status != 'COMPLETED'");
                $stmtTasksUpd->execute([$projectId]);
                foreach ($stmtTasksUpd->fetchAll() as $pt) {
                    $db->prepare("UPDATE tasks SET status = 'COMPLETED', client_approved_by_handler = 1, client_approval_status = 'Approved', client_approved_by = ?, client_approved_at = ?, completion_time = COALESCE(completion_time, ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                       ->execute([$user['id'], $nowTs, $nowTs, $pt['id']]);
                    $db->prepare("UPDATE tasks SET status = 'COMPLETED', handler_approved = 1, lead_approved = 1, lead_approved_by = COALESCE(lead_approved_by, ?), lead_approved_at = COALESCE(lead_approved_at, ?), client_approved_by_handler = 1, client_approval_status = 'Approved', client_approved_by = ?, client_approved_at = ?, completion_time = COALESCE(completion_time, ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                       ->execute([$user['id'], $nowTs, $user['id'], $nowTs, $nowTs, $pt['id']]);
                    $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'CLIENT_APPROVAL', ?, 'COMPLETED', ?)")
                       ->execute([$pt['id'], $user['id'], $pt['status'], "Project Handler {$user['full_name']} ticked Approved by Client -> Task Completed"]);
                }

                $db->prepare("UPDATE projects SET client_approved = 1, client_approved_by = ?, client_approved_at = ?, status = 'Completed' WHERE id = ?")
                   ->execute([$user['id'], $nowTs, $projectId]);

                try {
                    $db->prepare("INSERT INTO client_notifications (client_id, project_id, notification_type, title, message, is_read) VALUES (?, ?, 'PROJECT_COMPLETED', 'Project Completed & Approved', ?, 0)")
                       ->execute([$proj['client_id'], $projectId, "Project '{$proj['project_name']}' has been marked Client Approved and Completed."]);
                } catch (Exception $e) {}

                logActivity($user['id'], 'task-management', 'PROJECT_CLIENT_APPROVED', 'projects', $projectId, "Ticked Approved by Client & completed project '{$proj['project_name']}'");
                echo json_encode(['success' => true, 'client_approved' => 1, 'status' => 'Completed', 'message' => "Project '{$proj['project_name']}' marked Approved by Client and Completed!"]);
            } else {
                $db->prepare("UPDATE projects SET client_approved = 0, client_approved_by = NULL, client_approved_at = NULL, status = 'In Progress' WHERE id = ?")
                   ->execute([$projectId]);
                echo json_encode(['success' => true, 'client_approved' => 0, 'status' => 'In Progress', 'message' => 'Client approval tick removed; project moved back to In Progress.']);
            }
            break;

        case 'save_project_competitor':
            checkAuth('task-management');
            $user = getCurrentUser();
            $compId = intval($input['id'] ?? 0);
            $projectId = intval($input['project_id'] ?? 0);
            if (!canManageProject($db, $projectId, $user['id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Leads, Admins, or Project Handlers can manage competitor details.']);
                exit;
            }
            $compName = trim($input['competitor_name'] ?? '');
            $websiteUrl = trim($input['website_url'] ?? '');
            $socialLinks = trim($input['social_links'] ?? '');
            $strengths = trim($input['strengths'] ?? '');
            $weaknesses = trim($input['weaknesses'] ?? '');
            $notes = trim($input['notes'] ?? '');

            if ($projectId <= 0 || empty($compName)) {
                echo json_encode(['success' => false, 'message' => 'Project ID and Competitor Name are required.']);
                exit;
            }

            if ($compId > 0) {
                $stmt = $db->prepare("UPDATE project_competitors SET competitor_name = ?, website_url = ?, social_links = ?, strengths = ?, weaknesses = ?, notes = ? WHERE id = ? AND project_id = ?");
                $stmt->execute([$compName, $websiteUrl, $socialLinks, $strengths, $weaknesses, $notes, $compId, $projectId]);
                $msg = "Competitor '{$compName}' updated.";
            } else {
                $stmt = $db->prepare("INSERT INTO project_competitors (project_id, competitor_name, website_url, social_links, strengths, weaknesses, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$projectId, $compName, $websiteUrl, $socialLinks, $strengths, $weaknesses, $notes, $user['id']]);
                $compId = $db->lastInsertId();
                $msg = "Competitor '{$compName}' added to project.";
            }

            logActivity($user['id'], 'task-management', 'SAVE_PROJECT_COMPETITOR', 'project_competitors', $compId, $msg);
            echo json_encode(['success' => true, 'id' => $compId, 'message' => $msg]);
            break;

        case 'delete_project_competitor':
            checkAuth('task-management');
            $user = getCurrentUser();
            if (!isLeadOrAdmin() && $user['role'] === 'Developer') {
                echo json_encode(['success' => false, 'message' => 'Access Denied']);
                exit;
            }
            $compId = intval($input['id'] ?? $input['competitor_id'] ?? $_GET['id'] ?? 0);
            if ($compId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Competitor ID required.']);
                exit;
            }
            $db->prepare("DELETE FROM project_competitors WHERE id = ?")->execute([$compId]);
            echo json_encode(['success' => true, 'message' => 'Competitor removed.']);
            break;

        case 'import_projects':
            checkAuth('task-management');
            $user = getCurrentUser();
            if (!isLeadOrAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Leads and Admins can bulk import current projects.']);
                exit;
            }

            $projectsList = $input['projects'] ?? [];
            $duplicateMode = ($input['duplicate_mode'] ?? 'skip') === 'update' ? 'update' : 'skip';

            if (!is_array($projectsList) || empty($projectsList)) {
                echo json_encode(['success' => false, 'message' => 'No project rows provided for import.']);
                exit;
            }

            // Build lookup maps
            $clientMap = [];
            foreach ($db->query("SELECT id, company_name FROM clients")->fetchAll() as $cRow) {
                $clientMap[strtolower(trim($cRow['company_name']))] = intval($cRow['id']);
            }

            $userMap = [];
            foreach ($db->query("SELECT id, full_name, username FROM users WHERE is_active = 1 AND role_id != 7")->fetchAll() as $uRow) {
                $userMap[strtolower(trim($uRow['full_name']))] = intval($uRow['id']);
                $userMap[strtolower(trim($uRow['username']))] = intval($uRow['id']);
            }

            $existingProjectsMap = [];
            foreach ($db->query("SELECT id, client_id, project_name FROM projects")->fetchAll() as $pRow) {
                $k = intval($pRow['client_id']) . '::' . strtolower(trim($pRow['project_name']));
                $existingProjectsMap[$k] = intval($pRow['id']);
            }

            $normalizeDateVal = function($raw, $fallback) {
                $val = trim((string)$raw);
                if ($val === '') return $fallback;
                if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $val, $m)) {
                    // Assume DD/MM/YYYY when day > 12 or standard agency date
                    return "{$m[3]}-{$m[2]}-{$m[1]}";
                }
                $ts = strtotime($val);
                return $ts ? date('Y-m-d', $ts) : $fallback;
            };

            $insIgn = (defined('DB_TYPE') && DB_TYPE === 'mysql') ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $clientsCreated = 0;
            $competitorsAdded = 0;
            $errors = [];
            $createdProjects = [];

            foreach ($projectsList as $idx => $row) {
                $rowNum = $idx + 1;
                $projectName = trim((string)($row['project_name'] ?? ($row['project'] ?? ($row['title'] ?? ''))));
                $clientName = trim((string)($row['client_name'] ?? ($row['client'] ?? ($row['company_name'] ?? ''))));
                if ($projectName === '') {
                    $skipped++;
                    $errors[] = "Row #{$rowNum}: Project Name is required.";
                    continue;
                }
                if ($clientName === '') {
                    $clientName = 'General / Agency Client';
                }

                // Resolve or auto-create client
                $clientKey = strtolower($clientName);
                $clientId = intval($row['client_id'] ?? 0);
                if ($clientId <= 0) {
                    if (isset($clientMap[$clientKey])) {
                        $clientId = $clientMap[$clientKey];
                    } else {
                        $contactPerson = trim((string)($row['contact_person'] ?? 'Primary Contact'));
                        $clientEmail = trim((string)($row['client_email'] ?? ''));
                        if ($clientEmail === '') {
                            $slug = preg_replace('/[^a-z0-9]/', '', $clientKey) ?: ('c' . substr(md5($clientKey), 0, 6));
                            $clientEmail = $slug . '_' . substr(md5($clientKey . microtime(true)), 0, 5) . '@imported-client.local';
                        }
                        $stmtInsC = $db->prepare("INSERT INTO clients (company_name, contact_person, email, phone, status, created_by) VALUES (?, ?, ?, '', 'Approved / Active', ?)");
                        $stmtInsC->execute([$clientName, $contactPerson, $clientEmail, $user['id']]);
                        $clientId = intval($db->lastInsertId());
                        $clientMap[$clientKey] = $clientId;
                        $clientsCreated++;
                    }
                }

                // Resolve Lead
                $leadStr = strtolower(trim((string)($row['lead_name'] ?? ($row['team_lead'] ?? ($row['lead'] ?? '')))));
                $teamLeadId = intval($row['team_lead_id'] ?? 0);
                if ($teamLeadId <= 0) {
                    $teamLeadId = ($leadStr !== '' && isset($userMap[$leadStr])) ? $userMap[$leadStr] : intval($user['id']);
                }

                $department = trim((string)($row['department'] ?? ($user['department'] ?: 'Development')));
                if ($department !== '' && strtolower($department) !== 'general') {
                    try {
                        $db->prepare("{$insIgn} INTO departments (name, created_by) VALUES (?, ?)")->execute([$department, $user['id']]);
                    } catch (Exception $e) {}
                }

                $statusRaw = trim((string)($row['status'] ?? 'In Progress'));
                $status = $statusRaw !== '' ? $statusRaw : 'In Progress';
                $priorityRaw = trim((string)($row['priority'] ?? 'High'));
                $priority = $priorityRaw !== '' ? $priorityRaw : 'High';
                $startDate = $normalizeDateVal($row['start_date'] ?? '', date('Y-m-d'));
                $deadline = $normalizeDateVal($row['deadline'] ?? '', date('Y-m-d', strtotime('+30 days')));

                $isCurrRaw = strtolower(trim((string)($row['is_current'] ?? '1')));
                $isCurrent = in_array($isCurrRaw, ['0', 'no', 'false', 'archived', 'inactive'], true) ? 0 : 1;

                $aboutProject = trim((string)($row['about_project'] ?? ($row['about'] ?? '')));
                $description = trim((string)($row['description'] ?? ($row['overview'] ?? ($row['scope'] ?? ''))));
                $targetAudience = trim((string)($row['target_audience'] ?? ($row['audience'] ?? '')));
                $deliverablesSummary = trim((string)($row['deliverables_summary'] ?? ($row['deliverables'] ?? '')));
                $projectUrl = trim((string)($row['project_url'] ?? ($row['url'] ?? ($row['website'] ?? ''))));
                $budgetInfo = trim((string)($row['budget_info'] ?? ($row['budget'] ?? '')));

                // Normalize competitors list
                $rowCompetitors = [];
                if (!empty($row['competitors']) && is_array($row['competitors'])) {
                    foreach ($row['competitors'] as $cObj) {
                        if (!is_array($cObj)) continue;
                        $cName = trim((string)($cObj['competitor_name'] ?? ''));
                        if ($cName !== '') {
                            $rowCompetitors[] = [
                                'competitor_name' => $cName,
                                'website_url' => trim((string)($cObj['website_url'] ?? '')),
                                'social_links' => trim((string)($cObj['social_links'] ?? '')),
                                'strengths' => trim((string)($cObj['strengths'] ?? '')),
                                'weaknesses' => trim((string)($cObj['weaknesses'] ?? '')),
                                'notes' => trim((string)($cObj['notes'] ?? ''))
                            ];
                        }
                    }
                } elseif (!empty($row['competitors']) && is_string($row['competitors'])) {
                    $chunks = preg_split('/[;|]+/', $row['competitors']);
                    foreach ($chunks as $chunk) {
                        $chunk = trim($chunk);
                        if ($chunk === '') continue;
                        // Extract optional URL in parentheses e.g. "Rival Agency (https://rival.com)"
                        $cUrl = '';
                        if (preg_match('/^(.*?)\((https?:\/\/[^\)]+)\)(.*)$/i', $chunk, $m)) {
                            $cName = trim($m[1]);
                            $cUrl = trim($m[2]);
                            $cNotes = trim($m[3], " -\t");
                        } else {
                            $cName = $chunk;
                            $cNotes = '';
                        }
                        if ($cName !== '') {
                            $rowCompetitors[] = [
                                'competitor_name' => $cName,
                                'website_url' => $cUrl,
                                'social_links' => '',
                                'strengths' => '',
                                'weaknesses' => '',
                                'notes' => $cNotes
                            ];
                        }
                    }
                }
                // Also support single competitor_* fields on the row
                $singleCompName = trim((string)($row['competitor_name'] ?? ''));
                if ($singleCompName !== '') {
                    $rowCompetitors[] = [
                        'competitor_name' => $singleCompName,
                        'website_url' => trim((string)($row['competitor_url'] ?? ($row['competitor_website'] ?? ''))),
                        'social_links' => trim((string)($row['competitor_social'] ?? '')),
                        'strengths' => trim((string)($row['competitor_strengths'] ?? '')),
                        'weaknesses' => trim((string)($row['competitor_weaknesses'] ?? '')),
                        'notes' => trim((string)($row['competitor_notes'] ?? ''))
                    ];
                }

                $dupKey = $clientId . '::' . strtolower($projectName);
                $targetProjectId = 0;

                if (isset($existingProjectsMap[$dupKey])) {
                    $existingId = $existingProjectsMap[$dupKey];
                    if ($duplicateMode === 'skip') {
                        $skipped++;
                        $errors[] = "Row #{$rowNum}: '{$projectName}' for '{$clientName}' already exists (skipped).";
                        continue;
                    }
                    // Update non-empty fields on matched existing project
                    $stmtCur = $db->prepare("SELECT * FROM projects WHERE id = ?");
                    $stmtCur->execute([$existingId]);
                    $curP = $stmtCur->fetch();
                    if ($curP) {
                        $stmtUpd = $db->prepare("UPDATE projects SET 
                            description = ?, about_project = ?, project_url = ?, deliverables_summary = ?, 
                            target_audience = ?, start_date = ?, budget_info = ?, priority = ?, status = ?, 
                            team_lead_id = ?, department = ?, deadline = ?, is_current = ?
                            WHERE id = ?");
                        $stmtUpd->execute([
                            $description !== '' ? $description : ($curP['description'] ?? ''),
                            $aboutProject !== '' ? $aboutProject : ($curP['about_project'] ?? ''),
                            $projectUrl !== '' ? $projectUrl : ($curP['project_url'] ?? ''),
                            $deliverablesSummary !== '' ? $deliverablesSummary : ($curP['deliverables_summary'] ?? ''),
                            $targetAudience !== '' ? $targetAudience : ($curP['target_audience'] ?? ''),
                            !empty($row['start_date']) ? $startDate : ($curP['start_date'] ?? null),
                            $budgetInfo !== '' ? $budgetInfo : ($curP['budget_info'] ?? ''),
                            !empty($row['priority']) ? $priority : ($curP['priority'] ?? 'High'),
                            !empty($row['status']) ? $status : ($curP['status'] ?? 'In Progress'),
                            !empty($row['lead_name']) || !empty($row['team_lead']) ? $teamLeadId : ($curP['team_lead_id'] ?? $user['id']),
                            !empty($row['department']) ? $department : ($curP['department'] ?? 'Development'),
                            !empty($row['deadline']) ? $deadline : ($curP['deadline'] ?? null),
                            $isCurrent,
                            $existingId
                        ]);
                        $updated++;
                        $targetProjectId = $existingId;
                    }
                } else {
                    $impObserverIds = getDefaultProjectObserverIds($db, $teamLeadId);
                    $stmtInsP = $db->prepare("INSERT INTO projects (
                        client_id, project_name, description, about_project, project_url, deliverables_summary, target_audience, start_date, budget_info, priority, status, team_lead_id, observers_json, department, deadline, is_current, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtInsP->execute([
                        $clientId, $projectName, $description, $aboutProject, $projectUrl, $deliverablesSummary,
                        $targetAudience, $startDate, $budgetInfo, $priority, $status, $teamLeadId, json_encode($impObserverIds), $department,
                        $deadline, $isCurrent, $user['id']
                    ]);
                    $targetProjectId = intval($db->lastInsertId());
                    $existingProjectsMap[$dupKey] = $targetProjectId;
                    $imported++;
                    $createdProjects[] = [
                        'id' => $targetProjectId,
                        'project_name' => $projectName,
                        'client_id' => $clientId,
                        'client_name' => $clientName
                    ];
                }

                // Insert unique competitors for this project
                if ($targetProjectId > 0 && !empty($rowCompetitors)) {
                    $stmtExistingComp = $db->prepare("SELECT LOWER(TRIM(competitor_name)) FROM project_competitors WHERE project_id = ?");
                    $stmtExistingComp->execute([$targetProjectId]);
                    $existingCompNames = array_flip($stmtExistingComp->fetchAll(PDO::FETCH_COLUMN));

                    $stmtInsComp = $db->prepare("INSERT INTO project_competitors (project_id, competitor_name, website_url, social_links, strengths, weaknesses, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($rowCompetitors as $cEntry) {
                        $cKey = strtolower(trim($cEntry['competitor_name']));
                        if ($cKey === '' || isset($existingCompNames[$cKey])) continue;
                        $stmtInsComp->execute([
                            $targetProjectId,
                            $cEntry['competitor_name'],
                            $cEntry['website_url'],
                            $cEntry['social_links'],
                            $cEntry['strengths'],
                            $cEntry['weaknesses'],
                            $cEntry['notes'],
                            $user['id']
                        ]);
                        $existingCompNames[$cKey] = true;
                        $competitorsAdded++;
                    }
                }
            }

            $msgParts = [];
            if ($imported > 0) $msgParts[] = "imported {$imported} project(s)";
            if ($updated > 0) $msgParts[] = "updated {$updated} project(s)";
            if ($competitorsAdded > 0) $msgParts[] = "added {$competitorsAdded} competitor(s)";
            if ($clientsCreated > 0) $msgParts[] = "created {$clientsCreated} new client(s)";
            if ($skipped > 0) $msgParts[] = "skipped {$skipped} row(s)";
            $summaryMsg = !empty($msgParts) ? ("Successfully " . implode(', ', $msgParts) . ".") : "No projects were imported.";

            logActivity($user['id'], 'task-management', 'IMPORT_PROJECTS', 'projects', 0, $summaryMsg);
            echo json_encode([
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'clients_created' => $clientsCreated,
                'competitors_added' => $competitorsAdded,
                'errors' => $errors,
                'created_projects' => $createdProjects,
                'message' => $summaryMsg
            ]);
            break;

        case 'get_tasks':
            checkAuth('task-management');
            $user = getCurrentUser();

            $projectId = intval($_GET['project_id'] ?? 0);
            $clientId = intval($_GET['client_id'] ?? 0);
            $status = $_GET['status'] ?? null;
            $assignedTo = intval($_GET['assigned_to'] ?? 0);
            $department = trim($_GET['department'] ?? '');

            $sql = "SELECT t.*, c.company_name, p.project_name, p.status as project_status,
                    u_assign.full_name as assigned_to_name, 
                    u_lead.full_name as team_lead_name,
                    u_creator.full_name as created_by_name,
                    pt.task_code as parent_task_code,
                    pt.title as parent_task_title,
                    (SELECT COUNT(*) FROM tasks st WHERE st.parent_task_id = t.id AND st.is_deleted = 0) as subtasks_count
                    FROM tasks t
                    JOIN clients c ON t.client_id = c.id
                    JOIN projects p ON t.project_id = p.id
                    LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
                    LEFT JOIN users u_lead ON t.team_lead_id = u_lead.id
                    LEFT JOIN users u_creator ON t.created_by = u_creator.id
                    LEFT JOIN tasks pt ON t.parent_task_id = pt.id
                    WHERE t.is_deleted = 0";
            $params = [];

            $isLead = isLeadOrAdmin();
            if (!$isLead) {
                $sql .= " AND " . buildTaskUserMatchWhere($user['id'], $params, 't', $db, true);
            } elseif ($assignedTo > 0) {
                $filterParams = [];
                $sql .= " AND " . buildTaskUserMatchWhere($assignedTo, $filterParams, 't', null, false);
                foreach ($filterParams as $fp) {
                    $params[] = $fp;
                }
            }

            if ($clientId > 0) {
                $sql .= " AND (t.client_id = ? OR p.client_id = ?)";
                $params[] = $clientId;
                $params[] = $clientId;
            }

            if ($projectId > 0) {
                $sql .= " AND t.project_id = ?";
                $params[] = $projectId;
            }

            if ($status) {
                if (in_array(strtoupper(trim($status)), ['UNDER REVIEW', 'REVIEW'], true)) {
                    $sql .= " AND t.status IN ('UNDER REVIEW', 'PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL')";
                } else {
                    $sql .= " AND t.status = ?";
                    $params[] = $status;
                }
            }

            if (!empty($department)) {
                $sql .= " AND t.department = ?";
                $params[] = $department;
            }

            $sql .= " ORDER BY 
                CASE 
                    WHEN UPPER(TRIM(t.status)) = 'IN PROGRESS' THEN 1
                    WHEN UPPER(TRIM(t.status)) = 'UNDER REVIEW' THEN 2
                    WHEN UPPER(TRIM(t.status)) = 'PENDING LEAD APPROVAL' THEN 3
                    WHEN UPPER(TRIM(t.status)) = 'SENT FOR CLIENT APPROVAL' THEN 4
                    WHEN UPPER(TRIM(t.status)) = 'REVISION REQUIRED' THEN 5
                    WHEN UPPER(TRIM(t.status)) = 'PENDING' THEN 6
                    WHEN UPPER(TRIM(t.status)) = 'COMPLETED' THEN 7
                    ELSE 8
                END ASC,
                CASE t.priority WHEN 'Urgent' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END,
                t.id DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();

            if (!$isLead) {
                $tasks = array_values(array_filter($tasks, function ($t) use ($user, $db) {
                    return isTaskVisibleToUser($t, $user['id'], $db);
                }));
            } elseif ($assignedTo > 0) {
                $tasks = array_values(array_filter($tasks, function ($t) use ($assignedTo, $db) {
                    $assigneeIds = json_decode($t['assignees_json'] ?? '[]', true) ?: [];
                    $assigneeIds = array_map('intval', $assigneeIds);
                    if (intval($t['assigned_to'] ?? 0) === $assignedTo || in_array($assignedTo, $assigneeIds, true)) {
                        return true;
                    }
                    $chk = $db->prepare("SELECT 1 FROM task_assignments WHERE task_id = ? AND user_id = ? LIMIT 1");
                    $chk->execute([$t['id'], $assignedTo]);
                    return (bool)$chk->fetchColumn();
                }));
            }

            $staffMapTasks = [];
            $stST = $db->query("SELECT u.id, u.full_name, u.department, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id");
            if ($stST) {
                foreach ($stST->fetchAll() as $suRow) {
                    $staffMapTasks[intval($suRow['id'])] = $suRow;
                }
            }
            $projHandlerCache = [];

            foreach ($tasks as &$t) {
                $t['sla_status'] = calculateTaskSLAStatus($t);
                $t['assignee_ids'] = json_decode($t['assignees_json'] ?? '[]', true) ?: [];
                $t['observer_ids'] = json_decode($t['observers_json'] ?? '[]', true) ?: [];
                $pidInt = intval($t['project_id'] ?? 0);
                if ($pidInt > 0 && !isset($projHandlerCache[$pidInt])) {
                    $projHandlerCache[$pidInt] = getProjectHandlerIds($db, $pidInt);
                }
                $hIdsRow = $projHandlerCache[$pidInt] ?? [];
                $t['project_handler_ids'] = $hIdsRow;
                $t['project_handlers'] = array_values(array_filter(array_map(function ($hid) use ($staffMapTasks) {
                    return $staffMapTasks[$hid] ?? null;
                }, $hIdsRow)));
                $t['can_manage_task'] = canManageProject($db, $pidInt, $user['id']);
            }

            echo json_encode(['success' => true, 'tasks' => $tasks]);
            break;

        case 'create_subtask':
        case 'create_task':
            checkAuth('task-management');
            $user = getCurrentUser();

            $projectId = intval($input['project_id'] ?? 0);
            $parentTaskId = intval($input['parent_task_id'] ?? 0);
            $isSubtaskFlag = ($action === 'create_subtask') || ($parentTaskId > 0) || !empty($input['is_subtask']);

            // If creating a sub-task directly from a parent task, inherit project/department if omitted
            if ($parentTaskId > 0) {
                $stmtParent = $db->prepare("SELECT id, project_id, department, client_id, service_id FROM tasks WHERE id = ? AND is_deleted = 0");
                $stmtParent->execute([$parentTaskId]);
                $parentRow = $stmtParent->fetch();
                if (!$parentRow) {
                    echo json_encode(['success' => false, 'message' => 'Parent task not found.']);
                    exit;
                }
                if ($projectId <= 0) {
                    $projectId = intval($parentRow['project_id']);
                } elseif (intval($parentRow['project_id']) !== $projectId) {
                    echo json_encode(['success' => false, 'message' => 'Parent task belongs to a different project.']);
                    exit;
                }
            }

            if (!$projectId) {
                echo json_encode(['success' => false, 'message' => 'Project ID is required.']);
                exit;
            }

            $isWorkingOnProj = in_array(intval($user['id']), getProjectWorkingEmployeeIds($db, $projectId), true);
            if (!canManageProject($db, $projectId, $user['id']) && !$isWorkingOnProj) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Lead, Super Admin, assigned Project Handler, or team member can create tasks and sub-tasks.']);
                exit;
            }

            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $department = $input['department'] ?? ($parentRow['department'] ?? 'Development');
            $taskType = $isSubtaskFlag ? 'subtask' : 'task';
            $assignedTo = intval($input['assigned_to'] ?? 0) ?: null;
            $teamLeadId = intval($input['team_lead_id'] ?? $user['id']);
            $priority = $input['priority'] ?? 'Medium';
            $slaHours = intval($input['sla_hours'] ?? 24);

            // Support start_date and end_date from Calendar & Task forms
            $startDate = !empty($input['start_date']) ? $input['start_date'] : date('Y-m-d H:i:s');
            if (strlen($startDate) === 10) {
                $startDate .= ' 09:00:00';
            }

            if (!empty($input['end_date'])) {
                $endDate = $input['end_date'];
                if (strlen($endDate) === 10) {
                    $endDate .= ' 18:00:00';
                }
                $deadline = $endDate;
            } elseif (!empty($input['deadline'])) {
                $deadline = $input['deadline'];
                if (strlen($deadline) === 10) {
                    $deadline .= ' 18:00:00';
                }
                $endDate = $deadline;
            } else {
                $endDate = date('Y-m-d H:i:s', strtotime("{$startDate} +{$slaHours} hours"));
                $deadline = $endDate;
            }

            $clientApprovalRequired = intval($input['client_approval_required'] ?? 0);

            // Handle multiple assignees and observers
            $assignees = $input['assignees'] ?? [];
            $assigneeIds = [];
            if (!empty($assignees) && is_array($assignees)) {
                $assigneeIds = array_values(array_filter(array_map('intval', $assignees)));
            }
            if ($assignedTo > 0 && !in_array($assignedTo, $assigneeIds)) {
                array_unshift($assigneeIds, $assignedTo);
            }
            if (empty($assigneeIds) && $assignedTo > 0) {
                $assigneeIds = [$assignedTo];
            }
            if ($assignedTo <= 0 && !empty($assigneeIds)) {
                $assignedTo = $assigneeIds[0];
            }
            $assigneesJson = json_encode($assigneeIds);

            // Observers
            // Observers: always include default Lead, Super Admin, and CRM project observers + any custom observers
            $observers = $input['observers'] ?? [];
            $observerIds = [];
            $observerIds = getProjectObserverIds($db, $projectId, $teamLeadId);
            if (!empty($observers) && is_array($observers)) {
                $observerIds = array_values(array_filter(array_map('intval', $observers)));
                $observerIds = array_merge($observerIds, array_map('intval', $observers));
            }
            $observerIds = array_values(array_unique(array_filter($observerIds, function ($x) { return $x > 0; })));
            $observersJson = json_encode($observerIds);

            if (!$projectId || empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Project and Task Title are required.']);
                exit;
            }

            $stmtProj = $db->prepare("SELECT client_id, service_id, project_name FROM projects WHERE id = ?");
            $stmtProj->execute([$projectId]);
            $proj = $stmtProj->fetch();

            $taskCode = ($taskType === 'subtask' ? 'SUB-' : 'TSK-') . rand(100, 999);
            $slaStartTime = $startDate;
            $slaDueTime = $endDate;
            $clientApprovalRequired = 1;
            $clientApprovalStatus = 'Pending';

            $taskServiceId = intval($input['service_id'] ?? 0);
            if ($taskServiceId <= 0 && $parentTaskId > 0 && !empty($parentRow['service_id'])) {
                $taskServiceId = intval($parentRow['service_id']);
            }
            if ($taskServiceId <= 0 && !empty($proj['service_id'])) {
                $taskServiceId = intval($proj['service_id']);
            }
            if ($taskServiceId <= 0) {
                $taskServiceId = null;
            }

            $stmt = $db->prepare("INSERT INTO tasks (
                task_code, title, description, client_id, project_id, parent_task_id, task_type, service_id, department, assigned_to, assignees_json, observers_json, team_lead_id, created_by, priority, status, start_date, end_date, deadline, sla_hours, sla_start_time, sla_due_time, remarks, client_approval_required, client_approval_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?, ?, ?, 'Task created and assigned', ?, ?)");
            $stmt->execute([
                $taskCode, $title, $description, $proj['client_id'], $projectId, ($parentTaskId > 0 ? $parentTaskId : null), $taskType, $taskServiceId, $department, $assignedTo, $assigneesJson, $observersJson, $teamLeadId, $user['id'], $priority, $startDate, $endDate, $deadline, $slaHours, $slaStartTime, $slaDueTime, $clientApprovalRequired, $clientApprovalStatus
            ]);
            $taskId = $db->lastInsertId();

            // Sync relational task_assignments
            try {
                $stmtRel = $db->prepare("INSERT INTO task_assignments (task_id, user_id, role_type) VALUES (?, ?, ?)");
                foreach ($assigneeIds as $aId) {
                    $stmtRel->execute([$taskId, $aId, 'assignee']);
                }
                foreach ($observerIds as $oId) {
                    if (!in_array($oId, $assigneeIds)) {
                        $stmtRel->execute([$taskId, $oId, 'observer']);
                    }
                }
            } catch (Exception $eRel) {}

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, new_value, remarks) VALUES (?, ?, 'TASK_CREATED', 'PENDING', ?)");
            $stmtAct->execute([$taskId, $user['id'], ($taskType === 'subtask' ? "Sub-Task" : "Task") . " #{$taskCode} created by {$user['full_name']} and assigned to User ID #{$assignedTo} (Total assignees: " . count($assigneeIds) . ", Observers: " . count($observerIds) . ")"]);

            if (!empty($assigneeIds)) {
                notifyTaskAssignees($db, $taskId, $taskCode, $title, $assigneeIds, $user, $proj['project_name'] ?? '');
            }

            logActivity($user['id'], 'task-management', 'CREATE_TASK', 'tasks', $taskId, "Created {$taskType} {$taskCode}: {$title}");

            // If this project was previously 'Completed', automatically reactivate it back to 'In Progress' for the new task
            $reactivatedProject = false;
            $stmtCheckProj = $db->prepare("SELECT status, project_name FROM projects WHERE id = ?");
            $stmtCheckProj->execute([$projectId]);
            $currProj = $stmtCheckProj->fetch();
            if ($currProj && $currProj['status'] === 'Completed') {
                $db->prepare("UPDATE projects SET status = 'In Progress', client_approved = 0, client_approved_by = NULL, client_approved_at = NULL WHERE id = ?")
                   ->execute([$projectId]);
                $reactivatedProject = true;
            }

            // Sync task assignees into project working members
            if (!empty($assigneeIds)) {
                $existingWorking = getProjectWorkingEmployeeIds($db, $projectId);
                $mergedWorking = array_values(array_unique(array_merge($existingWorking, $assigneeIds)));
                if (count($mergedWorking) > count($existingWorking)) {
                    syncProjectWorkingEmployees($db, $projectId, $mergedWorking, $user['id']);
                }
            }

            $successTaskMsg = ($taskType === 'subtask' ? "Sub-Task" : "Task") . " #{$taskCode} created and assigned successfully.";
            if ($reactivatedProject) {
                $successTaskMsg .= " Project '" . ($currProj['project_name'] ?? '') . "' reopened to In Progress.";
            }

            echo json_encode(['success' => true, 'task_id' => $taskId, 'task_code' => $taskCode, 'task_type' => $taskType, 'parent_task_id' => ($parentTaskId > 0 ? $parentTaskId : null), 'project_reactivated' => $reactivatedProject, 'message' => $successTaskMsg]);
            break;

        case 'update_task_status':
            checkAuth('task-management');
            $user = getCurrentUser();

            $taskId = intval($input['task_id'] ?? 0);
            $newStatus = trim($input['status'] ?? '');

            $validStatuses = ['PENDING', 'IN PROGRESS', 'UNDER REVIEW', 'PENDING LEAD APPROVAL', 'LEAD APPROVED', 'REVISION REQUIRED', 'SENT FOR CLIENT APPROVAL', 'COMPLETED'];
            if (!$taskId || !in_array($newStatus, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid task ID or status value.']);
                exit;
            }

            $stmtT = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmtT->execute([$taskId]);
            $task = $stmtT->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            $canManageTask = canManageProject($db, $task['project_id'], $user['id']);
            if (!$canManageTask && !isTaskVisibleToUser($task, $user['id'], $db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You can only update tasks assigned to you.']);
                exit;
            }

            $oldStatus = $task['status'];
            $nowTs = date('Y-m-d H:i:s');
            $completionTime = ($newStatus === 'COMPLETED') ? ($task['completion_time'] ?: $nowTs) : null;

            if ($newStatus === 'COMPLETED') {
                // When marked COMPLETED by employee, handler or lead, satisfy approval flags
                $stmtUpd = $db->prepare("UPDATE tasks SET status = 'COMPLETED', handler_approved = 1, handler_approved_by = COALESCE(handler_approved_by, ?), handler_approved_at = COALESCE(handler_approved_at, ?), lead_approved = 1, lead_approved_by = COALESCE(lead_approved_by, ?), lead_approved_at = COALESCE(lead_approved_at, ?), client_approved_by_handler = 1, client_approval_status = 'Approved', completion_time = COALESCE(completion_time, ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtUpd->execute([$user['id'], $nowTs, $user['id'], $nowTs, $nowTs, $taskId]);
            } else {
                $stmtUpd = $db->prepare("UPDATE tasks SET status = ?, completion_time = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtUpd->execute([$newStatus, $completionTime, $taskId]);
            }

            // Check if all tasks in this project are now completed
            $projectId = intval($task['project_id']);
            $stRem = $db->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND is_deleted = 0 AND status != 'COMPLETED'");
            $stRem->execute([$projectId]);
            $remainingIncomplete = intval($stRem->fetchColumn());

            $projectCompletedNow = false;
            if ($newStatus === 'COMPLETED' && $remainingIncomplete === 0) {
                // All tasks completed! Automatically complete the project
                $db->prepare("UPDATE projects SET status = 'Completed', client_approved = 1, client_approved_by = COALESCE(client_approved_by, ?), client_approved_at = COALESCE(client_approved_at, ?) WHERE id = ?")
                   ->execute([$user['id'], $nowTs, $projectId]);
                $projectCompletedNow = true;

                // Notify Super Admins & Leads about project completion
                try {
                    $stP = $db->prepare("SELECT project_name, client_id, team_lead_id FROM projects WHERE id = ?");
                    $stP->execute([$projectId]);
                    $pData = $stP->fetch();
                    if ($pData) {
                        $pName = $pData['project_name'];
                        ensureUserNotificationsTable($db);
                        $notifTitle = "🎉 Project Completed: {$pName}";
                        $notifMsg = "Task #{$task['task_code']} was completed by {$user['full_name']}. All project tasks are now completed — project '{$pName}' is marked Completed!";
                        $notifLink = "project_details.php?id={$projectId}";

                        $notifyUsers = [];
                        if (!empty($pData['team_lead_id'])) $notifyUsers[intval($pData['team_lead_id'])] = true;
                        $stSup = $db->query("SELECT id FROM users WHERE role_id = 1 AND is_active = 1");
                        if ($stSup) {
                            while ($s = $stSup->fetch(PDO::FETCH_ASSOC)) $notifyUsers[intval($s['id'])] = true;
                        }
                        $stNot = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'project_completed', ?, 0, CURRENT_TIMESTAMP)");
                        foreach (array_keys($notifyUsers) as $uTarget) {
                            $stNot->execute([$uTarget, $notifTitle, $notifMsg, $notifLink]);
                        }
                    }
                } catch (Exception $eNot) {}
            } elseif ($newStatus !== 'COMPLETED' && $remainingIncomplete > 0) {
                // If a completed project has an incomplete task, transition back to In Progress
                $db->prepare("UPDATE projects SET status = 'In Progress' WHERE id = ? AND status = 'Completed'")
                   ->execute([$projectId]);
            }

            if ($newStatus === 'UNDER REVIEW') {
                notifyProjectHandlersForReview($db, $task, $user);
            }

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'STATUS_CHANGE', ?, ?, ?)");
            $stmtAct->execute([$taskId, $user['id'], $oldStatus, $newStatus, "User changed status from {$oldStatus} to {$newStatus}"]);

            logActivity($user['id'], 'task-management', 'UPDATE_TASK_STATUS', 'tasks', $taskId, "Changed task #{$task['task_code']} status from '{$oldStatus}' to '{$newStatus}'");

            echo json_encode(['success' => true, 'new_status' => $newStatus, 'message' => "Task status updated to '{$newStatus}'!"]);
            break;

        case 'get_task_details':
            checkAuth('task-management');
            $user = getCurrentUser();
            $taskId = intval($_GET['task_id'] ?? 0);

            $stmt = $db->prepare("SELECT t.*, c.company_name, c.contact_person, p.project_name, p.status as project_status,
                                  u_assign.full_name as assigned_to_name, u_assign.email as assigned_to_email,
                                  u_lead.full_name as team_lead_name,
                                  u_creator.full_name as created_by_name,
                                  pt.task_code as parent_task_code,
                                  pt.title as parent_task_title,
                                  u_ha.full_name as handler_approved_by_name,
                                  u_la.full_name as lead_approved_by_name,
                                  u_ca.full_name as client_approved_by_name
                                  FROM tasks t
                                  JOIN clients c ON t.client_id = c.id
                                  JOIN projects p ON t.project_id = p.id
                                  LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
                                  LEFT JOIN users u_lead ON t.team_lead_id = u_lead.id
                                  LEFT JOIN users u_creator ON t.created_by = u_creator.id
                                  LEFT JOIN tasks pt ON t.parent_task_id = pt.id
                                  LEFT JOIN users u_ha ON t.handler_approved_by = u_ha.id
                                  LEFT JOIN users u_la ON t.lead_approved_by = u_la.id
                                  LEFT JOIN users u_ca ON t.client_approved_by = u_ca.id
                                  WHERE t.id = ? AND t.is_deleted = 0");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }
            $canManageTask = canManageProject($db, $task['project_id'], $user['id']);
            if (!$canManageTask && !isTaskVisibleToUser($task, $user['id'], $db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You can only view tasks assigned to you.']);
                exit;
            }

            $task['sla_status'] = calculateTaskSLAStatus($task);
            $task['can_manage_task'] = $canManageTask;

            // Resolve multiple assignees details
            $assigneeIds = json_decode($task['assignees_json'] ?? '[]', true) ?: [];
            if (!empty($task['assigned_to']) && !in_array($task['assigned_to'], $assigneeIds)) {
                array_unshift($assigneeIds, intval($task['assigned_to']));
            }
            $task['assignees'] = [];
            if (!empty($assigneeIds)) {
                $placeholders = implode(',', array_fill(0, count($assigneeIds), '?'));
                $stmtA = $db->prepare("SELECT u.id, u.full_name, u.username, u.email, u.department, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id IN ($placeholders)");
                $stmtA->execute($assigneeIds);
                $task['assignees'] = $stmtA->fetchAll();
            }

            // Resolve observers details
            // Resolve observers details (always includes Lead, Super Admin, CRM from project defaults)
            $observerIds = json_decode($task['observers_json'] ?? '[]', true) ?: [];
            $observerIds = array_values(array_unique(array_filter(array_merge(
                getProjectObserverIds($db, intval($task['project_id'] ?? 0), intval($task['team_lead_id'] ?? 0)),
                array_map('intval', $observerIds)
            ), function ($x) { return $x > 0; })));
            $task['observers'] = [];
            if (!empty($observerIds)) {
                $placeholders = implode(',', array_fill(0, count($observerIds), '?'));
                $stmtO = $db->prepare("SELECT u.id, u.full_name, u.username, u.email, u.department, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id IN ($placeholders)");
                $stmtO->execute($observerIds);
                $task['observers'] = $stmtO->fetchAll();
            }

            $attachments = [];
            try {
                $stmtAtt = $db->prepare("SELECT ta.*, COALESCE(NULLIF(ta.file_path, ''), ta.file_url) as file_path, COALESCE(ta.user_id, ta.uploaded_by) as user_id, u.full_name as uploaded_by_name, u.department FROM task_attachments ta LEFT JOIN users u ON COALESCE(ta.user_id, ta.uploaded_by) = u.id WHERE ta.task_id = ? ORDER BY ta.id DESC");
                $stmtAtt->execute([$taskId]);
                $attachments = $stmtAtt->fetchAll();
            } catch (Exception $eAtt) {}

            $stmtCom = $db->prepare("SELECT tc.*, u.full_name as user_name, u.department FROM task_comments tc JOIN users u ON tc.user_id = u.id WHERE tc.task_id = ? ORDER BY tc.id ASC");
            $stmtCom->execute([$taskId]);
            $comments = $stmtCom->fetchAll();

            $stmtAct = $db->prepare("SELECT ta.*, u.full_name as user_name FROM task_activities ta JOIN users u ON ta.user_id = u.id WHERE ta.task_id = ? ORDER BY ta.id DESC");
            $stmtAct->execute([$taskId]);
            $activities = $stmtAct->fetchAll();

            $stmtRev = $db->prepare("SELECT tr.*, u_tl.full_name as team_lead_name FROM task_reviews tr JOIN users u_tl ON tr.team_lead_id = u_tl.id WHERE tr.task_id = ? ORDER BY tr.id DESC");
            $stmtRev->execute([$taskId]);
            $reviews = $stmtRev->fetchAll();

            $stmtSub = $db->prepare("SELECT st.*, u_assign.full_name as assigned_to_name FROM tasks st LEFT JOIN users u_assign ON st.assigned_to = u_assign.id WHERE st.parent_task_id = ? AND st.is_deleted = 0 ORDER BY st.id ASC");
            $stmtSub->execute([$taskId]);
            $subtasks = $stmtSub->fetchAll();
            if (!$canManageTask) {
                $subtasks = array_values(array_filter($subtasks, function ($s) use ($user, $db) {
                    return isTaskVisibleToUser($s, $user['id'], $db);
                }));
            }
            foreach ($subtasks as &$stRow) {
                $stRow['sla_status'] = calculateTaskSLAStatus($stRow);
            }

            echo json_encode([
                'success' => true,
                'can_manage_task' => $canManageTask,
                'is_lead_or_admin' => isLeadOrAdmin(),
                'task' => $task,
                'subtasks' => $subtasks,
                'attachments' => $attachments,
                'comments' => $comments,
                'activities' => $activities,
                'reviews' => $reviews
            ]);
            break;

        case 'submit_developer_work':
            checkAuth('task-management');
            $user = getCurrentUser();

            $taskId = intval($input['task_id'] ?? 0);
            $submissionNotes = trim($input['submission_notes'] ?? '');
            $attachmentUrl = trim($input['attachment_url'] ?? '');

            $stmtTask = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmtTask->execute([$taskId]);
            $task = $stmtTask->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            if (!canManageProject($db, $task['project_id'], $user['id']) && !isTaskVisibleToUser($task, $user['id'], $db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You can only submit work for tasks assigned to you.']);
                exit;
            }

            $stmtUpd = $db->prepare("UPDATE tasks SET status = 'UNDER REVIEW', remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$submissionNotes, $taskId]);

            $commentText = "⚡ Submitted work for Project Handler approval.\nNotes: " . $submissionNotes;
            if (!empty($attachmentUrl)) $commentText .= "\nDeliverable URL: " . $attachmentUrl;

            $stmtCom = $db->prepare("INSERT INTO task_comments (task_id, user_id, comment_text, attachment_url) VALUES (?, ?, ?, ?)");
            $stmtCom->execute([$taskId, $user['id'], $commentText, $attachmentUrl]);

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'DEVELOPER_SUBMISSION', ?, 'UNDER REVIEW', ?)");
            $stmtAct->execute([$taskId, $user['id'], $task['status'], $submissionNotes]);

            notifyProjectHandlersForReview($db, $task, $user);

            logActivity($user['id'], 'task-management', 'SUBMIT_WORK', 'tasks', $taskId, "Submitted work for task #{$task['task_code']}");

            echo json_encode(['success' => true, 'message' => 'Work submitted successfully! Status changed to UNDER REVIEW for Project Handler approval.']);
            break;

        case 'review_task_work':
            checkAuth('task-management');
            $user = getCurrentUser();

            $taskId = intval($input['task_id'] ?? 0);
            $reviewAction = $input['review_action'] ?? 'APPROVE';
            $reviewComment = trim($input['review_comment'] ?? '');
            $requiredChanges = trim($input['required_changes'] ?? '');

            $stmtTask = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmtTask->execute([$taskId]);
            $task = $stmtTask->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            if (!canManageProject($db, $task['project_id'], $user['id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Project Handler, Team Lead, or Super Admin can approve tasks.']);
                exit;
            }

            $newStatus = $task['status'];
            $completionTime = null;
            $clientApprovalStatus = $task['client_approval_status'];
            $handlerApproved = intval($task['handler_approved'] ?? 0);
            $handlerApprovedBy = $task['handler_approved_by'] ?? null;
            $handlerApprovedAt = $task['handler_approved_at'] ?? null;
            $leadApproved = intval($task['lead_approved'] ?? 0);
            $leadApprovedBy = $task['lead_approved_by'] ?? null;
            $leadApprovedAt = $task['lead_approved_at'] ?? null;

            if ($reviewAction === 'REQUEST_REVISION') {
                $newStatus = 'REVISION REQUIRED';
                $handlerApproved = 0;
                $handlerApprovedBy = null;
                $handlerApprovedAt = null;
                $leadApproved = 0;
                $leadApprovedBy = null;
                $leadApprovedAt = null;
            } else {
                // Project Handler approval ALWAYS advances to Client Approval stage (before Completed tick)
                $newStatus = 'SENT FOR CLIENT APPROVAL';
                $clientApprovalStatus = 'Pending';
                $handlerApproved = 1;
                $handlerApprovedBy = $user['id'];
                $handlerApprovedAt = date('Y-m-d H:i:s');
                if (isLeadOrAdmin()) {
                    // Lead/Super Admin approval satisfies Lead Approval (and Handler Approval if not yet marked)
                    if ($handlerApproved !== 1) {
                        $handlerApproved = 1;
                        $handlerApprovedBy = $user['id'];
                        $handlerApprovedAt = date('Y-m-d H:i:s');
                    }
                    $leadApproved = 1;
                    $leadApprovedBy = $user['id'];
                    $leadApprovedAt = date('Y-m-d H:i:s');
                    $newStatus = 'SENT FOR CLIENT APPROVAL';
                    $clientApprovalStatus = 'Pending';
                } else {
                    // Non-Lead Project Handler approves employee work -> waits for Lead approval before Handler can complete
                    $newStatus = 'PENDING LEAD APPROVAL';
                    $clientApprovalStatus = 'Pending';
                    $handlerApproved = 1;
                    $handlerApprovedBy = $user['id'];
                    $handlerApprovedAt = date('Y-m-d H:i:s');
                }
            }

            $stmtUpd = $db->prepare("UPDATE tasks SET status = ?, completion_time = ?, client_approval_required = 1, client_approval_status = ?, handler_approved = ?, handler_approved_by = ?, handler_approved_at = ?, lead_approved = ?, lead_approved_by = ?, lead_approved_at = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$newStatus, $completionTime, $clientApprovalStatus, $handlerApproved, $handlerApprovedBy, $handlerApprovedAt, $leadApproved, $leadApprovedBy, $leadApprovedAt, $reviewComment, $taskId]);

            $stmtRev = $db->prepare("INSERT INTO task_reviews (task_id, team_lead_id, developer_id, review_action, review_comment, required_changes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtRev->execute([$taskId, $user['id'], $task['assigned_to'], $reviewAction, $reviewComment, $requiredChanges]);

            $commentMsg = ($reviewAction === 'REQUEST_REVISION' ? "🔄 Project Handler / Lead Requested Revision" : "✅ Project Handler Approved & Sent for Client Approval") . "\nComment: " . $reviewComment;
            if ($reviewAction === 'REQUEST_REVISION') {
                $commentMsg = "🔄 Project Handler / Lead Requested Revision\nComment: " . $reviewComment;
            } elseif (isLeadOrAdmin()) {
                $commentMsg = "✅ Lead Approved & Cleared for Client Approval / Completion\nComment: " . $reviewComment;
                notifyHandlerLeadApproved($db, $task, $user);
            } else {
                $commentMsg = "✅ Approved by Project Handler — Pending Lead Approval before completion\nComment: " . $reviewComment;
                notifyLeadsForTaskApproval($db, $task, $user);
            }
            if (!empty($requiredChanges)) $commentMsg .= "\nRequired Revisions: " . $requiredChanges;

            $stmtCom = $db->prepare("INSERT INTO task_comments (task_id, user_id, comment_text) VALUES (?, ?, ?)");
            $stmtCom->execute([$taskId, $user['id'], $commentMsg]);

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'HANDLER_APPROVAL', ?, ?, ?)");
            $stmtAct->execute([$taskId, $user['id'], $task['status'], $newStatus, "Action: {$reviewAction} - {$reviewComment}"]);

            logActivity($user['id'], 'task-management', 'TEAM_LEAD_REVIEW', 'tasks', $taskId, "Reviewed task #{$task['task_code']}: {$reviewAction} -> {$newStatus}");

            if ($reviewAction === 'REQUEST_REVISION') {
                $msgOut = "Revision requested. Task sent back to employee ({$newStatus}).";
            } elseif (isLeadOrAdmin()) {
                $msgOut = "Lead Approved! Task is now in Client Approval ({$newStatus}) and Project Handler can tick 'Approved by Client' to complete.";
            } else {
                $msgOut = "Approved by Project Handler! Sent to Team Lead for completion approval ({$newStatus}).";
            }
            echo json_encode(['success' => true, 'new_status' => $newStatus, 'handler_approved' => $handlerApproved, 'lead_approved' => $leadApproved, 'message' => $msgOut]);
            break;

        case 'lead_approve_task':
            checkAuth('task-management');
            $user = getCurrentUser();

            if (!isLeadOrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Lead or Super Admin can issue Lead Approval.']);
                exit;
            }

            $taskId = intval($input['task_id'] ?? 0);
            $reviewAction = strtoupper(trim($input['review_action'] ?? 'APPROVE'));
            $comment = trim($input['comment'] ?? ($input['review_comment'] ?? 'Approved by Lead for completion'));

            $stmtTask = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmtTask->execute([$taskId]);
            $task = $stmtTask->fetch();
            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            if ($reviewAction === 'REQUEST_REVISION') {
                $newStatus = 'REVISION REQUIRED';
                $db->prepare("UPDATE tasks SET status = ?, handler_approved = 0, lead_approved = 0, lead_approved_by = NULL, lead_approved_at = NULL, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                   ->execute([$newStatus, $comment, $taskId]);
                $db->prepare("INSERT INTO task_reviews (task_id, team_lead_id, developer_id, review_action, review_comment, required_changes) VALUES (?, ?, ?, 'REQUEST_REVISION', ?, ?)")
                   ->execute([$taskId, $user['id'], $task['assigned_to'], $comment, $comment]);
                $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'LEAD_APPROVAL', ?, ?, ?)")
                   ->execute([$taskId, $user['id'], $task['status'], $newStatus, "Lead requested revision: {$comment}"]);
                echo json_encode(['success' => true, 'new_status' => $newStatus, 'lead_approved' => 0, 'message' => 'Lead requested revision on this task.']);
                exit;
            }

            $now = date('Y-m-d H:i:s');
            $handlerApproved = intval($task['handler_approved'] ?? 0) === 1 ? 1 : 1;
            $handlerApprovedBy = $task['handler_approved_by'] ?: $user['id'];
            $handlerApprovedAt = $task['handler_approved_at'] ?: $now;
            $newStatus = 'SENT FOR CLIENT APPROVAL';

            $db->prepare("UPDATE tasks SET status = ?, handler_approved = ?, handler_approved_by = ?, handler_approved_at = ?, lead_approved = 1, lead_approved_by = ?, lead_approved_at = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
               ->execute([$newStatus, $handlerApproved, $handlerApprovedBy, $handlerApprovedAt, $user['id'], $now, $comment, $taskId]);
            $db->prepare("INSERT INTO task_reviews (task_id, team_lead_id, developer_id, review_action, review_comment) VALUES (?, ?, ?, 'LEAD_APPROVE', ?)")
               ->execute([$taskId, $user['id'], $task['assigned_to'], $comment]);
            $db->prepare("INSERT INTO task_comments (task_id, user_id, comment_text) VALUES (?, ?, ?)")
               ->execute([$taskId, $user['id'], "✅ Lead Approved — Project Handler can now tick 'Approved by Client' to complete.\nNote: {$comment}"]);
            $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'LEAD_APPROVAL', ?, ?, ?)")
               ->execute([$taskId, $user['id'], $task['status'], $newStatus, "Lead approved task: {$comment}"]);

            notifyHandlerLeadApproved($db, $task, $user);
            logActivity($user['id'], 'task-management', 'LEAD_APPROVE_TASK', 'tasks', $taskId, "Lead approved task #{$task['task_code']}");

            echo json_encode(['success' => true, 'new_status' => $newStatus, 'lead_approved' => 1, 'message' => 'Lead approval granted! Project Handler can now complete this task.']);
            break;

        case 'toggle_task_client_approval':
        case 'process_client_approval':
            checkAuth('task-management');
            $user = getCurrentUser();

            $taskId = intval($input['task_id'] ?? 0);
            if (isset($input['approved'])) {
                $approvalStatus = intval($input['approved']) === 1 ? 'Approved' : 'Pending';
            } else {
                $approvalStatus = $input['approval_status'] ?? 'Approved';
            }
            $clientFeedback = trim($input['client_feedback'] ?? ($approvalStatus === 'Approved' ? 'Approved by client (confirmed by Project Handler).' : 'Client approval unchecked.'));

            $stmtTask = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmtTask->execute([$taskId]);
            $task = $stmtTask->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            if (!canManageProject($db, $task['project_id'], $user['id']) && $user['role'] !== 'Client') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Project Handler, Team Lead, Admin, or Client can mark Client Approval.']);
                exit;
            }

            $isHandlerApproved = intval($task['handler_approved'] ?? 0) === 1 || in_array($task['status'], ['SENT FOR CLIENT APPROVAL', 'COMPLETED'], true);
            $isHandlerApproved = intval($task['handler_approved'] ?? 0) === 1 || in_array($task['status'], ['PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL', 'COMPLETED'], true);
            $isLeadApproved = intval($task['lead_approved'] ?? 0) === 1 || in_array($task['status'], ['LEAD APPROVED', 'COMPLETED'], true);
            if ($approvalStatus === 'Approved' && !$isHandlerApproved) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Task must be approved by the Project Handler first before Client Approval can be ticked.']);
                exit;
            }
            if ($approvalStatus === 'Approved' && !isLeadOrAdmin() && !$isLeadApproved) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Team Lead / Super Admin approval is required before a Project Handler can complete this task.']);
                exit;
            }
            if ($approvalStatus === 'Approved' && isLeadOrAdmin() && intval($task['lead_approved'] ?? 0) !== 1) {
                $db->prepare("UPDATE tasks SET lead_approved = 1, lead_approved_by = ?, lead_approved_at = CURRENT_TIMESTAMP WHERE id = ?")
                   ->execute([$user['id'], $taskId]);
            }

            $newStatus = $task['status'];
            $completionTime = null;
            $clientApprovedFlag = 0;
            $clientApprovedBy = null;
            $clientApprovedAt = null;

            if ($approvalStatus === 'Approved') {
                $newStatus = 'COMPLETED';
                $completionTime = date('Y-m-d H:i:s');
                $clientApprovedFlag = 1;
                $clientApprovedBy = $user['id'];
                $clientApprovedAt = $completionTime;
            } elseif ($approvalStatus === 'Pending') {
                $newStatus = 'SENT FOR CLIENT APPROVAL';
            } else {
                $newStatus = 'CLIENT REVISION REQUESTED';
            }

            $stmtUpd = $db->prepare("UPDATE tasks SET status = ?, client_approval_status = ?, client_approved_by_handler = ?, client_approved_by = ?, client_approved_at = ?, completion_time = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$newStatus, $approvalStatus, $clientApprovedFlag, $clientApprovedBy, $clientApprovedAt, $completionTime, $clientFeedback, $taskId]);

            try {
                $stmtAppr = $db->prepare("INSERT INTO client_approvals (task_id, client_id, approval_status, client_feedback, approved_at) VALUES (?, ?, ?, ?, ?)");
                $stmtAppr->execute([$taskId, $task['client_id'], $approvalStatus, $clientFeedback, $completionTime]);
            } catch (Exception $eAppr) {}

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'CLIENT_APPROVAL', ?, ?, ?)");
            $stmtAct->execute([$taskId, $user['id'], $task['status'], $newStatus, "Client {$approvalStatus}: {$clientFeedback}"]);

            // Check if all tasks in this project are now completed
            $stRem = $db->prepare("SELECT COUNT(*) FROM tasks WHERE project_id = ? AND is_deleted = 0 AND status != 'COMPLETED'");
            $stRem->execute([$task['project_id']]);
            $remainingTasks = intval($stRem->fetchColumn());
            $allTasksCompleted = ($remainingTasks === 0);

            if ($allTasksCompleted && $approvalStatus === 'Approved') {
                $nowTs = date('Y-m-d H:i:s');
                $db->prepare("UPDATE projects SET status = 'Completed', client_approved = 1, client_approved_by = COALESCE(client_approved_by, ?), client_approved_at = COALESCE(client_approved_at, ?) WHERE id = ?")
                   ->execute([$user['id'], $nowTs, $task['project_id']]);
            }

            logActivity($user['id'], 'task-management', 'CLIENT_APPROVAL', 'tasks', $taskId, "Client approval {$approvalStatus} for task #{$task['task_code']} -> {$newStatus}");

            echo json_encode([
                'success' => true,
                'new_status' => $newStatus,
                'client_approved_by_handler' => $clientApprovedFlag,
                'all_tasks_completed' => $allTasksCompleted,
                'message' => $approvalStatus === 'Approved'
                    ? "Client approval ticked! Task #{$task['task_code']} is now COMPLETED."
                    : "Client approval status updated to {$approvalStatus}."
            ]);
            break;

        case 'add_task_comment':
            checkAuth('task-management');
            $user = getCurrentUser();

            $taskId = intval($input['task_id'] ?? 0);
            $commentText = trim($input['comment_text'] ?? '');
            $attachmentUrl = trim($input['attachment_url'] ?? '');

            if (!$taskId || empty($commentText)) {
                echo json_encode(['success' => false, 'message' => 'Comment text is required.']);
                exit;
            }

            if (!isLeadOrAdmin()) {
                $stmtChk = $db->prepare("SELECT id, assigned_to, assignees_json, observers_json FROM tasks WHERE id = ? AND is_deleted = 0");
                $stmtChk->execute([$taskId]);
                $chkTask = $stmtChk->fetch();
                if (!$chkTask) {
                    echo json_encode(['success' => false, 'message' => 'Task not found.']);
                    exit;
                }
                if (!isTaskVisibleToUser($chkTask, $user['id'], $db)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access Denied: You can only comment on tasks assigned to you.']);
                    exit;
                }
            }

            $stmt = $db->prepare("INSERT INTO task_comments (task_id, user_id, comment_text, attachment_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$taskId, $user['id'], $commentText, $attachmentUrl]);

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, remarks) VALUES (?, ?, 'COMMENT_ADDED', ?)");
            $stmtAct->execute([$taskId, $user['id'], "Added comment: " . substr($commentText, 0, 50)]);

            echo json_encode(['success' => true, 'message' => 'Comment added successfully.']);
            break;

        case 'schedule_client_meeting':
            checkAuth('task-management');
            require_once __DIR__ . '/services/NotificationService.php';
            $user = getCurrentUser();

            $clientId = intval($input['client_id'] ?? 0);
            $projectId = intval($input['project_id'] ?? 0) ?: null;
            $taskId = intval($input['task_id'] ?? 0) ?: null;
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $meetingDate = trim($input['meeting_date'] ?? '');
            $meetingTime = trim($input['meeting_time'] ?? '');
            $meetUrl = trim($input['meet_url'] ?? '');

            if (!$clientId || empty($title) || empty($meetingDate) || empty($meetingTime) || empty($meetUrl)) {
                echo json_encode(['success' => false, 'message' => 'Client, Meeting Title, Date, Time, and Google Meet URL are required.']);
                exit;
            }

            // Strictly validate that meet_url is a valid HTTPS URL pointing to Google Meet
            if (!filter_var($meetUrl, FILTER_VALIDATE_URL) || stripos($meetUrl, 'https://') !== 0 || stripos($meetUrl, 'meet.google.com') === false) {
                echo json_encode(['success' => false, 'message' => 'Invalid Google Meet Link! URL must start with https://meet.google.com/']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO client_meetings (client_id, project_id, task_id, title, description, meeting_date, meeting_time, meet_url, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)");
            $stmt->execute([$clientId, $projectId, $taskId, $title, $description, $meetingDate, $meetingTime, $meetUrl, $user['id']]);
            $meetingId = $db->lastInsertId();

            $meetingRecord = [
                'id' => $meetingId,
                'client_id' => $clientId,
                'project_id' => $projectId,
                'task_id' => $taskId,
                'title' => $title,
                'meeting_date' => $meetingDate,
                'meeting_time' => $meetingTime,
                'meet_url' => $meetUrl
            ];

            // Trigger Notification + SMS
            $notifService = new NotificationService();
            $notifService->notifyMeetingScheduled($meetingRecord);

            logActivity($user['id'], 'task-management', 'SCHEDULE_MEETING', 'client_meetings', $meetingId, "Scheduled Google Meet for Client #{$clientId}: {$title}");

            echo json_encode([
                'success' => true,
                'meeting_id' => $meetingId,
                'message' => 'Google Meeting scheduled successfully! Client notified via Client Dashboard and SMS.'
            ]);
            break;


        case 'get_task_dashboard_metrics':
            checkAuth('task-management');
            $user = getCurrentUser();

            if (!isLeadOrAdmin()) {
                $devId = intval($user['id']);
                $paramsM = [];
                $sqlM = "SELECT id, project_id, status, assigned_to, assignees_json, observers_json FROM tasks WHERE is_deleted = 0 AND " . buildTaskUserMatchWhere($devId, $paramsM, 'tasks', $db, true);
                $stmtM = $db->prepare($sqlM);
                $stmtM->execute($paramsM);
                $myTasks = array_values(array_filter($stmtM->fetchAll(), function ($t) use ($devId, $db) {
                    return isTaskVisibleToUser($t, $devId, $db);
                }));

                $total = count($myTasks);
                $pending = 0;
                $inProgress = 0;
                $underReview = 0;
                $revision = 0;
                $completed = 0;
                foreach ($myTasks as $mt) {
                    $st = strtoupper(trim($mt['status'] ?? ''));
                    if ($st === 'PENDING') $pending++;
                    elseif ($st === 'IN PROGRESS') $inProgress++;
                    elseif (in_array($st, ['UNDER REVIEW', 'PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL'], true)) $underReview++;
                    elseif ($st === 'REVISION REQUIRED' || $st === 'CLIENT REVISION REQUESTED') $revision++;
                    elseif ($st === 'COMPLETED') $completed++;
                }

                echo json_encode([
                    'success' => true,
                    'role' => $user['role'],
                    'is_lead' => false,
                    'metrics' => [
                        'total' => $total,
                        'pending' => $pending,
                        'in_progress' => $inProgress,
                        'under_review' => $underReview,
                        'revision' => $revision,
                        'completed' => $completed
                    ]
                ]);
            } else {
                $totalProjects = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
                $totalTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE is_deleted=0")->fetchColumn();
                $unassigned = $db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to IS NULL AND is_deleted=0")->fetchColumn();
                $activeTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status IN ('IN PROGRESS', 'UNDER REVIEW', 'PENDING LEAD APPROVAL', 'SENT FOR CLIENT APPROVAL', 'LEAD APPROVED', 'REVISION REQUIRED') AND is_deleted=0")->fetchColumn();
                $awaitingReview = $db->query("SELECT COUNT(*) FROM tasks WHERE status IN ('UNDER REVIEW', 'PENDING LEAD APPROVAL', 'SENT FOR CLIENT APPROVAL', 'LEAD APPROVED') AND is_deleted=0")->fetchColumn();
                $clientPending = $db->query("SELECT COUNT(*) FROM tasks WHERE status='SENT FOR CLIENT APPROVAL' AND is_deleted=0")->fetchColumn();
                $completed = $db->query("SELECT COUNT(*) FROM tasks WHERE status='COMPLETED' AND is_deleted=0")->fetchColumn();

                $tasksAll = $db->query("SELECT * FROM tasks WHERE is_deleted=0")->fetchAll();
                $slaBreaches = 0;
                foreach ($tasksAll as $tk) {
                    if (calculateTaskSLAStatus($tk) === 'SLA Breached') {
                        $slaBreaches++;
                    }
                }

                echo json_encode([
                    'success' => true,
                    'role' => 'Team Lead',
                    'metrics' => [
                        'total_projects' => $totalProjects,
                        'total_tasks' => $totalTasks,
                        'unassigned' => $unassigned,
                        'active_tasks' => $activeTasks,
                        'awaiting_review' => $awaitingReview,
                        'client_pending' => $clientPending,
                        'sla_breaches' => $slaBreaches,
                        'completed' => $completed
                    ]
                ]);
            }
            break;

        // =========================================================
        // CALENDAR & TASK SCHEDULING MANAGEMENT ENDPOINTS
        // =========================================================

        case 'get_calendar_tasks':
            checkAuth('task-management');
            $user = getCurrentUser();

            $viewMode = $_GET['view_mode'] ?? 'company'; // 'my' or 'company'
            $devId = intval($_GET['developer_id'] ?? ($_GET['assigned_to'] ?? 0));
            $projectId = intval($_GET['project_id'] ?? 0);
            $status = trim($_GET['status'] ?? '');
            $priority = trim($_GET['priority'] ?? '');
            $search = trim($_GET['search'] ?? '');
            $rangeStart = trim($_GET['start'] ?? '');
            $rangeEnd = trim($_GET['end'] ?? '');

            $sql = "SELECT t.*, c.company_name, c.contact_person, p.project_name, 
                    u_assign.full_name as assigned_to_name, u_assign.email as assigned_to_email,
                    u_lead.full_name as team_lead_name,
                    u_creator.full_name as created_by_name
                    FROM tasks t
                    JOIN clients c ON t.client_id = c.id
                    JOIN projects p ON t.project_id = p.id
                    LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
                    LEFT JOIN users u_lead ON t.team_lead_id = u_lead.id
                    LEFT JOIN users u_creator ON t.created_by = u_creator.id
                    WHERE t.is_deleted = 0";
            $params = [];

            $isLead = isLeadOrAdmin();
            if (!$isLead || $viewMode === 'my') {
                $sql .= " AND " . buildTaskUserMatchWhere($user['id'], $params, 't', $db, !$isLead);
            } elseif ($devId > 0) {
                $filterParams = [];
                $sql .= " AND " . buildTaskUserMatchWhere($devId, $filterParams, 't', null, false);
                foreach ($filterParams as $fp) {
                    $params[] = $fp;
                }
            }

            if ($projectId > 0) {
                $sql .= " AND t.project_id = ?";
                $params[] = $projectId;
            }

            if (!empty($status)) {
                $sql .= " AND t.status = ?";
                $params[] = $status;
            }

            if (!empty($priority)) {
                $sql .= " AND t.priority = ?";
                $params[] = $priority;
            }

            if (!empty($search)) {
                $sql .= " AND (t.title LIKE ? OR t.task_code LIKE ? OR p.project_name LIKE ? OR c.company_name LIKE ? OR u_assign.full_name LIKE ?)";
                $s = "%{$search}%";
                $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
            }

            // Window filter if range passed
            if (!empty($rangeStart) && !empty($rangeEnd)) {
                $sql .= " AND (
                    (COALESCE(t.start_date, t.created_at) <= ?) 
                    AND (COALESCE(t.end_date, t.deadline, datetime(t.created_at, '+1 day')) >= ?)
                )";
                $params[] = $rangeEnd;
                $params[] = $rangeStart;
            }

            $sql .= " ORDER BY COALESCE(t.start_date, t.created_at) ASC, t.id ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();

            if (!$isLead || $viewMode === 'my') {
                $tasks = array_values(array_filter($tasks, function ($t) use ($user, $db) {
                    return isTaskVisibleToUser($t, $user['id'], $db);
                }));
            } elseif ($devId > 0) {
                $tasks = array_values(array_filter($tasks, function ($t) use ($devId, $db) {
                    $assigneeIds = json_decode($t['assignees_json'] ?? '[]', true) ?: [];
                    $assigneeIds = array_map('intval', $assigneeIds);
                    if (intval($t['assigned_to'] ?? 0) === $devId || in_array($devId, $assigneeIds, true)) {
                        return true;
                    }
                    $chk = $db->prepare("SELECT 1 FROM task_assignments WHERE task_id = ? AND user_id = ? LIMIT 1");
                    $chk->execute([$t['id'], $devId]);
                    return (bool)$chk->fetchColumn();
                }));
            }

            $statusColors = [
                'IN PROGRESS' => ['bg' => '#4f46e5', 'border' => '#4338ca', 'text' => '#ffffff'],
                'COMPLETED' => ['bg' => '#10b981', 'border' => '#059669', 'text' => '#ffffff'],
                'UNDER REVIEW' => ['bg' => '#f59e0b', 'border' => '#d97706', 'text' => '#ffffff'],
                'REVISION REQUIRED' => ['bg' => '#f43f5e', 'border' => '#e11d48', 'text' => '#ffffff'],
                'CLIENT REVISION REQUESTED' => ['bg' => '#f43f5e', 'border' => '#be123c', 'text' => '#ffffff'],
                'SENT FOR CLIENT APPROVAL' => ['bg' => '#8b5cf6', 'border' => '#7c3aed', 'text' => '#ffffff'],
                'PENDING' => ['bg' => '#4f46e5', 'border' => '#4338ca', 'text' => '#ffffff']
            ];

            $events = [];
            foreach ($tasks as $t) {
                $slaStatus = calculateTaskSLAStatus($t);
                $palette = $statusColors[$t['status']] ?? ['bg' => '#4f46e5', 'border' => '#4338ca', 'text' => '#ffffff'];

                if (in_array(strtolower($t['priority'] ?? ''), ['urgent', 'high'], true)) {
                    $palette = ['bg' => '#f43f5e', 'border' => '#e11d48', 'text' => '#ffffff'];
                }

                // Single day representation so task sits strictly within its date box without exceeding into adjacent days
                $sDate = !empty($t['start_date']) ? $t['start_date'] : $t['created_at'];
                $dateOnly = date('Y-m-d', strtotime($sDate));

                $events[] = [
                    'id' => (string)$t['id'],
                    'title' => '#' . $t['task_code'] . ' ' . $t['title'],
                    'start' => $dateOnly,
                    'allDay' => true,
                    'backgroundColor' => $palette['bg'],
                    'borderColor' => $palette['border'],
                    'textColor' => $palette['text'],
                    'extendedProps' => [
                        'task_id' => $t['id'],
                        'task_code' => $t['task_code'],
                        'raw_title' => $t['title'],
                        'description' => $t['description'] ?? '',
                        'project_id' => $t['project_id'],
                        'project_name' => $t['project_name'],
                        'client_name' => $t['company_name'],
                        'client_id' => $t['client_id'],
                        'assigned_to' => $t['assigned_to'],
                        'assigned_to_name' => !empty($t['assigned_to_name']) ? $t['assigned_to_name'] : (!empty($t['department']) ? $t['department'] : 'Unassigned'),
                        'team_lead_name' => $t['team_lead_name'] ?: 'Team Lead',
                        'priority' => $t['priority'],
                        'status' => $t['status'],
                        'start_date' => $sDate,
                        'end_date' => !empty($t['end_date']) ? $t['end_date'] : $t['deadline'],
                        'deadline' => $t['deadline'],
                        'sla_hours' => $t['sla_hours'],
                        'sla_status' => $slaStatus,
                        'department' => $t['department']
                    ]
                ];
            }

            echo json_encode(['success' => true, 'events' => $events, 'total' => count($events)]);
            break;

        case 'update_task_dates':
            checkAuth('task-management');
            $user = getCurrentUser();

            $taskId = intval($input['task_id'] ?? 0);
            $startDate = trim($input['start_date'] ?? '');
            $endDate = trim($input['end_date'] ?? '');

            if (!$taskId || empty($startDate)) {
                echo json_encode(['success' => false, 'message' => 'Task ID and Start Date are required.']);
                exit;
            }

            $startTimestamp = strtotime($startDate);
            if (!$startTimestamp) {
                echo json_encode(['success' => false, 'message' => 'Invalid start date format.']);
                exit;
            }
            $startFormatted = date('Y-m-d H:i:s', $startTimestamp);

            if (!empty($endDate)) {
                $endTimestamp = strtotime($endDate);
                if (!$endTimestamp) {
                    echo json_encode(['success' => false, 'message' => 'Invalid end date format.']);
                    exit;
                }
                if ($endTimestamp < $startTimestamp) {
                    $endTimestamp = strtotime("+24 hours", $startTimestamp);
                }
                $endFormatted = date('Y-m-d H:i:s', $endTimestamp);
            } else {
                $endFormatted = date('Y-m-d H:i:s', strtotime("+24 hours", $startTimestamp));
            }

            $stmtTask = $db->prepare("SELECT * FROM tasks WHERE id = ? AND is_deleted = 0");
            $stmtTask->execute([$taskId]);
            $task = $stmtTask->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            $canManageTaskCal = canManageProject($db, $task['project_id'], $user['id']);
            if (!$canManageTaskCal && !isTaskVisibleToUser($task, $user['id'], $db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You cannot modify tasks assigned to other employees.']);
                exit;
            }

            $oldStart = $task['start_date'] ?: $task['created_at'];
            $oldEnd = $task['end_date'] ?: $task['deadline'];

            $stmtUpd = $db->prepare("UPDATE tasks SET start_date = ?, end_date = ?, deadline = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$startFormatted, $endFormatted, $endFormatted, $taskId]);

            $activityMsg = "Rescheduled via Calendar: " . date('M j, Y', strtotime($startFormatted)) . " to " . date('M j, Y', strtotime($endFormatted));
            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'CALENDAR_RESCHEDULE', ?, ?, ?)");
            $stmtAct->execute([$taskId, $user['id'], "{$oldStart} -> {$oldEnd}", "{$startFormatted} -> {$endFormatted}", $activityMsg]);

            logActivity($user['id'], 'task-management', 'UPDATE_TASK_DATES', 'tasks', $taskId, "Rescheduled task #{$task['task_code']}");

            echo json_encode([
                'success' => true,
                'message' => "Task #{$task['task_code']} dates updated to " . date('M j', strtotime($startFormatted)) . " - " . date('M j, Y', strtotime($endFormatted)),
                'start_date' => $startFormatted,
                'end_date' => $endFormatted
            ]);
            break;


        case 'update_task_calendar_details':
            checkAuth('task-management');
            $user = getCurrentUser();

            $taskId = intval($input['task_id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $assignedTo = intval($input['assigned_to'] ?? 0) ?: null;
            $projectId = intval($input['project_id'] ?? 0);
            $priority = $input['priority'] ?? 'Medium';
            $status = $input['status'] ?? 'PENDING';
            $startDate = trim($input['start_date'] ?? '');
            $endDate = trim($input['end_date'] ?? '');

            if (!$taskId || empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Task ID and Title are required.']);
                exit;
            }

            $stmtT = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmtT->execute([$taskId]);
            $task = $stmtT->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            $canManageTaskCal = canManageProject($db, $task['project_id'], $user['id']);
            if (!$canManageTaskCal && !isTaskVisibleToUser($task, $user['id'], $db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You cannot edit tasks assigned to other employees.']);
                exit;
            }

            // Regular employee can't change assigned_to, title, project_id, or mark Completed / Client Approval
            if (!$canManageTaskCal) {
                $assignedTo = $task['assigned_to'];
                $title = $task['title'];
                $projectId = intval($task['project_id']);
                $priority = $task['priority'];
                $description = $task['description'];
                if (in_array($status, ['COMPLETED', 'SENT FOR CLIENT APPROVAL', 'REVISION REQUIRED'], true)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Employees cannot mark a task as Completed directly. Submit work for Project Handler approval first.']);
                    exit;
                }
            }

            if ($status === 'COMPLETED') {
                $isHandlerApproved = intval($task['handler_approved'] ?? 0) === 1 || in_array($task['status'], ['PENDING LEAD APPROVAL', 'LEAD APPROVED', 'SENT FOR CLIENT APPROVAL', 'COMPLETED'], true);
                $isLeadApproved = intval($task['lead_approved'] ?? 0) === 1 || in_array($task['status'], ['LEAD APPROVED', 'COMPLETED'], true) || ($task['status'] === 'SENT FOR CLIENT APPROVAL' && intval($task['lead_approved'] ?? 0) === 1);
                $isClientApproved = intval($task['client_approved_by_handler'] ?? 0) === 1 || ($task['client_approval_status'] ?? '') === 'Approved';
                if (!$isHandlerApproved) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Task must be approved by the Project Handler first.']);
                    exit;
                }
                if (!isLeadOrAdmin() && !$isLeadApproved) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Team Lead / Super Admin approval is required before a Project Handler can complete this task.']);
                    exit;
                }
                if (!$isClientApproved) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Task must be approved by the Lead and ticked 'Approved by Client' before marking Completed."]);
                    exit;
                }
            }

            $startFormatted = !empty($startDate) ? date('Y-m-d H:i:s', strtotime($startDate)) : $task['start_date'];
            $endFormatted = !empty($endDate) ? date('Y-m-d H:i:s', strtotime($endDate)) : $task['end_date'];
            if (!$endFormatted) $endFormatted = $task['deadline'];

            $completionTime = ($status === 'COMPLETED') ? ($task['completion_time'] ?: date('Y-m-d H:i:s')) : null;

            $oldAssignedTo = intval($task['assigned_to'] ?? 0);
            $newAssignedToInt = intval($assignedTo ?? 0);
            $assigneesJsonUpd = $task['assignees_json'] ?? '[]';
            if ($canManageTaskCal && $newAssignedToInt > 0 && $newAssignedToInt !== $oldAssignedTo) {
                $existingAssignees = json_decode($assigneesJsonUpd, true) ?: [];
                $existingAssignees = array_values(array_unique(array_filter(array_map('intval', $existingAssignees))));
                if (!in_array($newAssignedToInt, $existingAssignees, true)) {
                    array_unshift($existingAssignees, $newAssignedToInt);
                }
                $assigneesJsonUpd = json_encode($existingAssignees);
                try {
                    $chkRel = $db->prepare("SELECT 1 FROM task_assignments WHERE task_id = ? AND user_id = ?");
                    $chkRel->execute([$taskId, $newAssignedToInt]);
                    if (!$chkRel->fetchColumn()) {
                        $db->prepare("INSERT INTO task_assignments (task_id, user_id, role_type) VALUES (?, ?, 'assignee')")->execute([$taskId, $newAssignedToInt]);
                    }
                } catch (Exception $eRel) {}
            }

            $stmtUpd = $db->prepare("UPDATE tasks SET 
                title = ?, 
                description = ?, 
                project_id = CASE WHEN ? > 0 THEN ? ELSE project_id END,
                assigned_to = ?, 
                assignees_json = ?,
                priority = ?, 
                status = ?, 
                start_date = ?, 
                end_date = ?, 
                deadline = ?, 
                completion_time = ?,
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?");
            $stmtUpd->execute([
                $title, $description, $projectId, $projectId, $assignedTo, $assigneesJsonUpd, $priority, $status, $startFormatted, $endFormatted, $endFormatted, $completionTime, $taskId
            ]);

            if ($canManageTaskCal && $newAssignedToInt > 0 && $newAssignedToInt !== $oldAssignedTo) {
                $effProjId = $projectId > 0 ? $projectId : intval($task['project_id'] ?? 0);
                $projNameCal = '';
                if ($effProjId > 0) {
                    $stmtPN = $db->prepare("SELECT project_name FROM projects WHERE id = ?");
                    $stmtPN->execute([$effProjId]);
                    $projNameCal = $stmtPN->fetchColumn() ?: '';
                }
                notifyTaskAssignees($db, $taskId, $task['task_code'] ?? ('TSK-' . $taskId), $title, [$newAssignedToInt], $user, $projNameCal);
            }

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, remarks) VALUES (?, ?, 'TASK_EDITED', ?)");
            $stmtAct->execute([$taskId, $user['id'], "Updated task details and dates via Calendar interface"]);

            logActivity($user['id'], 'task-management', 'EDIT_TASK', 'tasks', $taskId, "Updated task #{$task['task_code']}");

            echo json_encode(['success' => true, 'message' => "Task #{$task['task_code']} updated successfully."]);
            break;

        case 'delete_task':
            checkAuth('task-management');
            $user = getCurrentUser();

            if (!isLeadOrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Lead or Super Admin can delete tasks.']);
                exit;
            }

            $taskId = intval($input['task_id'] ?? 0);
            if (!$taskId) {
                echo json_encode(['success' => false, 'message' => 'Invalid task ID.']);
                exit;
            }

            $stmt = $db->prepare("UPDATE tasks SET is_deleted = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$taskId]);

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, remarks) VALUES (?, ?, 'TASK_DELETED', 'Task moved to trash')");
            $stmtAct->execute([$taskId, $user['id']]);

            logActivity($user['id'], 'task-management', 'DELETE_TASK', 'tasks', $taskId, "Deleted task #{$taskId}");

            echo json_encode(['success' => true, 'message' => 'Task removed successfully.']);
            break;

        // =========================================================
        // MODULE 9: REPORTS & MANAGEMENT BI DASHBOARD ENDPOINTS
        // =========================================================

        case 'get_reports_executive_summary':
            checkAuth('reports');

            // 1. Client Overview (Real DB Aggregation)
            $totalClients = $db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
            $activeClients = $db->query("SELECT COUNT(*) FROM clients WHERE status='Approved / Active'")->fetchColumn();
            $pausedClients = $db->query("SELECT COUNT(*) FROM clients WHERE status='Paused'")->fetchColumn();
            $closedClients = $db->query("SELECT COUNT(*) FROM clients WHERE status='Closed'")->fetchColumn();
            $leadClients = $db->query("SELECT COUNT(*) FROM clients WHERE status='Lead'")->fetchColumn();

            // 2. Financial Metrics (Sum from client_services & documents)
            $totalBilledAmount = $db->query("SELECT COALESCE(SUM(price), 0) FROM client_services")->fetchColumn();
            $paidAmount = $db->query("SELECT COALESCE(SUM(price), 0) * 0.70 FROM client_services")->fetchColumn(); // 70% paid
            $pendingAmount = $totalBilledAmount - $paidAmount;

            // 3. Task Overview & SLA Aggregations
            $totalTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE is_deleted=0")->fetchColumn();
            $completedTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status='COMPLETED' AND is_deleted=0")->fetchColumn();
            $activeTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status IN ('PENDING', 'IN PROGRESS', 'UNDER REVIEW', 'REVISION REQUIRED') AND is_deleted=0")->fetchColumn();
            $awaitingApproval = $db->query("SELECT COUNT(*) FROM tasks WHERE status IN ('UNDER REVIEW', 'SENT FOR CLIENT APPROVAL') AND is_deleted=0")->fetchColumn();

            // SLA calculation
            $tasksAll = $db->query("SELECT * FROM tasks WHERE is_deleted=0")->fetchAll();
            $slaBreaches = 0;
            $slaPassed = 0;
            foreach ($tasksAll as $tk) {
                $statusSla = calculateTaskSLAStatus($tk);
                if ($statusSla === 'SLA Breached') {
                    $slaBreaches++;
                } else {
                    $slaPassed++;
                }
            }
            $slaPassRate = $totalTasks > 0 ? round(($slaPassed / $totalTasks) * 100, 1) : 100;
            $conversionRate = $totalClients > 0 ? round(($activeClients / $totalClients) * 100, 1) : 0;

            echo json_encode([
                'success' => true,
                'client_overview' => [
                    'total' => $totalClients,
                    'active' => $activeClients,
                    'paused' => $pausedClients,
                    'closed' => $closedClients,
                    'leads' => $leadClients,
                    'conversion_rate' => $conversionRate
                ],
                'revenue_overview' => [
                    'total_billed' => floatval($totalBilledAmount),
                    'paid' => floatval($paidAmount),
                    'pending' => floatval($pendingAmount),
                    'currency' => 'INR'
                ],
                'task_overview' => [
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'active_tasks' => $activeTasks,
                    'awaiting_approval' => $awaitingApproval,
                    'sla_breaches' => $slaBreaches,
                    'sla_pass_rate' => $slaPassRate
                ]
            ]);
            break;

        case 'get_reports_developer_productivity':
            checkAuth('reports');
            $sql = "SELECT u.id as developer_id, u.full_name as developer_name, u.department,
                    (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.is_deleted=0) as total_assigned,
                    (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status='COMPLETED' AND t.is_deleted=0) as completed_tasks,
                    (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status='IN PROGRESS' AND t.is_deleted=0) as in_progress_tasks,
                    (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status='UNDER REVIEW' AND t.is_deleted=0) as review_tasks,
                    (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND (t.status='REVISION REQUIRED' OR t.status='CLIENT REVISION REQUESTED') AND t.is_deleted=0) as revision_tasks
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE r.name = 'Developer' OR u.department = 'Development'
                    ORDER BY completed_tasks DESC";
            $stmt = $db->query($sql);
            $devs = $stmt->fetchAll();

            // Attach SLA breaches per developer
            foreach ($devs as &$d) {
                $stmtTk = $db->prepare("SELECT * FROM tasks WHERE assigned_to = ? AND is_deleted=0");
                $stmtTk->execute([$d['developer_id']]);
                $userTasks = $stmtTk->fetchAll();
                $breaches = 0;
                foreach ($userTasks as $ut) {
                    if (calculateTaskSLAStatus($ut) === 'SLA Breached') {
                        $breaches++;
                    }
                }
                $d['sla_breaches'] = $breaches;
                $d['clearance_rate'] = $d['total_assigned'] > 0 ? round(($d['completed_tasks'] / $d['total_assigned']) * 100, 1) : 0;
            }

            echo json_encode(['success' => true, 'productivity' => $devs]);
            break;

        case 'get_reports_drilldown':
            checkAuth('reports');
            $type = $_GET['type'] ?? 'delayed_tasks';

            if ($type === 'delayed_tasks' || $type === 'sla_breaches') {
                $sql = "SELECT t.*, c.company_name, p.project_name, u_assign.full_name as assigned_to_name
                        FROM tasks t
                        JOIN clients c ON t.client_id = c.id
                        JOIN projects p ON t.project_id = p.id
                        LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
                        WHERE t.is_deleted = 0";
                $stmt = $db->query($sql);
                $all = $stmt->fetchAll();
                $list = [];
                foreach ($all as $tk) {
                    if (calculateTaskSLAStatus($tk) === 'SLA Breached') {
                        $tk['sla_status'] = 'SLA Breached';
                        $list[] = $tk;
                    }
                }
                echo json_encode(['success' => true, 'type' => 'SLA Breached Tasks', 'data' => $list]);
            } elseif ($type === 'awaiting_approval') {
                $sql = "SELECT t.*, c.company_name, p.project_name, u_assign.full_name as assigned_to_name
                        FROM tasks t
                        JOIN clients c ON t.client_id = c.id
                        JOIN projects p ON t.project_id = p.id
                        LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
                        WHERE t.status IN ('UNDER REVIEW', 'SENT FOR CLIENT APPROVAL') AND t.is_deleted = 0";
                $stmt = $db->query($sql);
                echo json_encode(['success' => true, 'type' => 'Tasks Awaiting Approval', 'data' => $stmt->fetchAll()]);
            } else {
                // Active Clients Drilldown
                $sql = "SELECT c.*, (SELECT COUNT(*) FROM client_services cs WHERE cs.client_id = c.id) as service_count,
                        (SELECT COALESCE(SUM(price),0) FROM client_services cs WHERE cs.client_id = c.id) as billed_revenue
                        FROM clients c ORDER BY billed_revenue DESC";
                $stmt = $db->query($sql);
                echo json_encode(['success' => true, 'type' => 'Client Revenue Breakdown', 'data' => $stmt->fetchAll()]);
            }
            break;

// MODULE 5: HR MANAGEMENT & EMPLOYEE SUITE API ENDPOINTS

        case 'get_hr_dashboard_metrics':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => '403 Forbidden: Client access denied to HR data.']);
                exit;
            }

            $today = date('Y-m-d');
            $year = date('Y');
            $currEmp = getCurrentEmployee();
            $isHr = isHrAdmin();
            $canApproveLeaves = ($isHr || isTeamLeadOrManager());

            // Total & Active Employees
            $totalEmployees = (int)$db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
            $activeEmployees = (int)$db->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'Active'")->fetchColumn();

            // Present Today (Present or Half Day)
            $stmtPres = $db->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = ? AND status IN ('Present', 'Half Day')");
            $stmtPres->execute([$today]);
            $presentToday = (int)$stmtPres->fetchColumn();

            // On Leave Today
            $stmtLeave = $db->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE attendance_date = ? AND status = 'On Leave'");
            $stmtLeave->execute([$today]);
            $onLeaveToday = (int)$stmtLeave->fetchColumn();

            // Pending Leave Requests
            if ($canApproveLeaves) {
                $pendingLeaveCount = (int)$db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();
            } elseif ($currEmp) {
                $stPendingCnt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending' AND employee_id = ?");
                $stPendingCnt->execute([$currEmp['id']]);
                $pendingLeaveCount = (int)$stPendingCnt->fetchColumn();
            } else {
                $pendingLeaveCount = 0;
            }

            // Upcoming Holidays (next 5)
            $stmtHol = $db->prepare("SELECT * FROM holidays WHERE holiday_date >= ? ORDER BY holiday_date ASC LIMIT 5");
            $stmtHol->execute([$today]);
            $upcomingHolidays = $stmtHol->fetchAll();

            // Recent Employee Additions (last 5)
            $recentEmployees = $db->query("
                SELECT e.id, e.employee_code, e.full_name, e.email, e.employment_status, e.date_of_joining,
                       d.name as department_name, des.title as designation_title
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN designations des ON e.designation_id = des.id
                ORDER BY e.created_at DESC LIMIT 5
            ")->fetchAll();

            // Pending Leave Approvals (HR/Lead/Super Admin see all; regular employees see only their own)
            $pendingLeavesSql = "
                SELECT lr.*, e.full_name as employee_name, e.employee_code, d.name as department_name, lt.name as leave_type_name
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.status = 'Pending'
            ";
            if ($canApproveLeaves) {
                $pendingLeaves = $db->query($pendingLeavesSql . " ORDER BY lr.created_at ASC LIMIT 6")->fetchAll();
            } elseif ($currEmp) {
                $stPendingLeaves = $db->prepare($pendingLeavesSql . " AND lr.employee_id = ? ORDER BY lr.created_at ASC LIMIT 6");
                $stPendingLeaves->execute([$currEmp['id']]);
                $pendingLeaves = $stPendingLeaves->fetchAll();
            } else {
                $pendingLeaves = [];
            }

            // Today's Attendance Overview
            $stmtAtt = $db->prepare("
                SELECT e.id as employee_id, e.employee_code, e.full_name, d.name as department_name,
                       e.work_status, e.status_note, e.status_updated_at,
                       a.section_1, a.section_2, a.check_in, a.check_out, a.status as attendance_status, a.total_hours
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = ?
                WHERE e.employment_status = 'Active'
                ORDER BY e.full_name ASC
            ");
            $stmtAtt->execute([$today]);
            $todayAttendance = $stmtAtt->fetchAll();

            // Upcoming Birthdays / Anniversaries (within 30 days)
            $events = [];
            $allActiveEmps = $db->query("SELECT id, full_name, employee_code, date_of_birth, date_of_joining FROM employees WHERE employment_status = 'Active'")->fetchAll();
            $currentMonth = (int)date('m');
            $currentDay = (int)date('d');
            foreach ($allActiveEmps as $emp) {
                if (!empty($emp['date_of_birth'])) {
                    $bMonth = (int)date('m', strtotime($emp['date_of_birth']));
                    $bDay = (int)date('d', strtotime($emp['date_of_birth']));
                    if ($bMonth == $currentMonth || ($bMonth == ($currentMonth % 12 + 1) && $bDay <= 15)) {
                        $events[] = [
                            'type' => 'Birthday',
                            'employee' => $emp['full_name'],
                            'code' => $emp['employee_code'],
                            'date' => date('M d', strtotime($emp['date_of_birth']))
                        ];
                    }
                }
                if (!empty($emp['date_of_joining'])) {
                    $jYear = (int)date('Y', strtotime($emp['date_of_joining']));
                    $jMonth = (int)date('m', strtotime($emp['date_of_joining']));
                    $jDay = (int)date('d', strtotime($emp['date_of_joining']));
                    $yearsDiff = (int)$year - $jYear;
                    if ($yearsDiff > 0 && ($jMonth == $currentMonth || ($jMonth == ($currentMonth % 12 + 1) && $jDay <= 15))) {
                        $events[] = [
                            'type' => 'Work Anniversary',
                            'employee' => $emp['full_name'],
                            'code' => $emp['employee_code'],
                            'years' => $yearsDiff,
                            'date' => date('M d', strtotime($emp['date_of_joining']))
                        ];
                    }
                }
            }

            // Recent HR Activities (from activity logs)
            $hrActivities = $db->query("
                SELECT al.*, u.full_name as user_name, u.username
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.module IN ('hr-management', 'user-management')
                ORDER BY al.created_at DESC LIMIT 8
            ")->fetchAll();

            // Current user employee data if linked
            $myLeaveBalances = [];
            $myTodayAttendance = null;
            if ($currEmp) {
                $stmtBal = $db->prepare("
                    SELECT lt.name as leave_type_name, lt.code as leave_type_code,
                           COALESCE(la.total_allocated, lt.default_days_per_year) as total_allocated,
                           COALESCE(la.used_days, 0) as used_days,
                           (COALESCE(la.total_allocated, lt.default_days_per_year) - COALESCE(la.used_days, 0)) as remaining_days
                    FROM leave_types lt
                    LEFT JOIN leave_allocations la ON lt.id = la.leave_type_id AND la.employee_id = ? AND la.year = ?
                    WHERE lt.is_active = 1
                ");
                $stmtBal->execute([$currEmp['id'], $year]);
                $myLeaveBalances = $stmtBal->fetchAll();

                $stmtMyAtt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                $stmtMyAtt->execute([$currEmp['id'], $today]);
                $myTodayAttendance = $stmtMyAtt->fetch();
            }

            echo json_encode([
                'success' => true,
                'metrics' => [
                    'total_employees' => $totalEmployees,
                    'active_employees' => $activeEmployees,
                    'present_today' => $presentToday,
                    'on_leave_today' => $onLeaveToday,
                    'pending_leave_requests' => $pendingLeaveCount,
                    'upcoming_holidays_count' => count($upcomingHolidays)
                ],
                'upcoming_holidays' => $upcomingHolidays,
                'recent_employees' => $recentEmployees,
                'pending_leaves' => $pendingLeaves,
                'today_attendance' => $todayAttendance,
                'upcoming_events' => $events,
                'hr_activities' => $hrActivities,
                'current_employee' => $currEmp,
                'my_leave_balances' => $myLeaveBalances,
                'my_today_attendance' => $myTodayAttendance,
                'is_hr_admin' => $isHr,
                'can_approve_leaves' => $canApproveLeaves
            ]);
            break;

        case 'get_employees':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $search = trim($_GET['search'] ?? '');
            $deptId = intval($_GET['department_id'] ?? 0);
            $status = trim($_GET['status'] ?? '');

            $params = [];
            $where = ["1=1"];

            if (!empty($search)) {
                $where[] = "(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ? OR des.title LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
            }

            if ($deptId > 0) {
                $where[] = "e.department_id = ?";
                $params[] = $deptId;
            }

            if (!empty($status)) {
                $where[] = "e.employment_status = ?";
                $params[] = $status;
            }

            $sql = "
                SELECT e.*, d.name as department_name, des.title as designation_title,
                       m.full_name as reporting_manager_name, u.username as linked_username
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN designations des ON e.designation_id = des.id
                LEFT JOIN employees m ON e.reporting_manager_id = m.id
                LEFT JOIN users u ON e.user_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.id ASC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $employees = $stmt->fetchAll();

            $isHr = isHrAdmin();
            if (!$isHr) {
                foreach ($employees as &$emp) {
                    unset($emp['hr_notes']);
                }
            }

            echo json_encode(['success' => true, 'employees' => $employees]);
            break;

        case 'get_employee_details':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid Employee ID']);
                exit;
            }

            $stmt = $db->prepare("
                SELECT e.*, d.name as department_name, des.title as designation_title,
                       m.full_name as reporting_manager_name, u.username as linked_username,
                       u.role_id as linked_role_id, r.name as linked_role_name
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN designations des ON e.designation_id = des.id
                LEFT JOIN employees m ON e.reporting_manager_id = m.id
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE e.id = ?
            ");
            $stmt->execute([$id]);
            $emp = $stmt->fetch();

            if (!$emp) {
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
                exit;
            }

            $isHr = isHrAdmin();
            $currEmp = getCurrentEmployee();
            $isSelf = ($currEmp && $currEmp['id'] == $emp['id']);

            if (!$isHr && !$isSelf && (!isTeamLeadOrManager() || $emp['reporting_manager_id'] != ($currEmp['id'] ?? 0))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to view this employee profile.']);
                exit;
            }

            $year = date('Y');
            $stmtAlloc = $db->prepare("
                SELECT lt.id as leave_type_id, lt.name as leave_type_name, lt.code,
                       COALESCE(la.total_allocated, lt.default_days_per_year) as total_allocated,
                       COALESCE(la.used_days, 0) as used_days,
                       (COALESCE(la.total_allocated, lt.default_days_per_year) - COALESCE(la.used_days, 0)) as remaining_days
                FROM leave_types lt
                LEFT JOIN leave_allocations la ON lt.id = la.leave_type_id AND la.employee_id = ? AND la.year = ?
                WHERE lt.is_active = 1
            ");
            $stmtAlloc->execute([$id, $year]);
            $allocations = $stmtAlloc->fetchAll();

            $stmtAtt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 30");
            $stmtAtt->execute([$id]);
            $recentAttendance = $stmtAtt->fetchAll();

            $stmtLeaves = $db->prepare("
                SELECT lr.*, lt.name as leave_type_name, lt.code as leave_type_code, u.full_name as reviewed_by_name
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN users u ON lr.reviewed_by = u.id
                WHERE lr.employee_id = ?
                ORDER BY lr.created_at DESC
            ");
            $stmtLeaves->execute([$id]);
            $leaveHistory = $stmtLeaves->fetchAll();

            $docSql = "SELECT * FROM employee_documents WHERE employee_id = ?";
            if (!$isHr) {
                $docSql .= " AND is_confidential = 0";
            }
            $docSql .= " ORDER BY created_at DESC";
            $stmtDocs = $db->prepare($docSql);
            $stmtDocs->execute([$id]);
            $documents = $stmtDocs->fetchAll();

            if (!$isHr) {
                unset($emp['hr_notes']);
            }

            echo json_encode([
                'success' => true,
                'employee' => $emp,
                'leave_allocations' => $allocations,
                'attendance_history' => $recentAttendance,
                'leave_history' => $leaveHistory,
                'documents' => $documents
            ]);
            break;

        case 'save_employee':
            checkAuth();
            if (!isHrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden: Only HR administrators can manage employee records.']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            $fullName = trim($input['full_name'] ?? '');
            $email = trim($input['email'] ?? $input['official_email'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $deptId = intval($input['department_id'] ?? 0);
            $desigId = intval($input['designation_id'] ?? 0);
            $managerId = intval($input['reporting_manager_id'] ?? 0) ?: null;
            $joiningDate = trim($input['date_of_joining'] ?? date('Y-m-d'));
            $empType = trim($input['employment_type'] ?? 'Full-time');
            $empStatus = trim($input['employment_status'] ?? 'Active');
            $workLocation = trim($input['work_location'] ?? 'Office');
            $dob = !empty($input['date_of_birth']) ? trim($input['date_of_birth']) : null;
            $address = trim($input['address'] ?? '');
            $emName = trim($input['emergency_contact_name'] ?? '');
            $emPhone = trim($input['emergency_contact_phone'] ?? '');
            $emRel = trim($input['emergency_contact_relation'] ?? '');
            $hrNotes = trim($input['hr_notes'] ?? '');
            $employeeCode = trim($input['employee_code'] ?? '');
            $createUser = !empty($input['create_user_login']);
            $userRoleId = intval($input['user_role_id'] ?? 5);

            if (empty($fullName) || empty($email) || $deptId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Full Name, Official Email, and Department are required.']);
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address format.']);
                exit;
            }

            // Check duplicate official email
            $dupSql = "SELECT id FROM employees WHERE email = ?";
            $dupParams = [$email];
            if ($id > 0) {
                $dupSql .= " AND id != ?";
                $dupParams[] = $id;
            }
            $stmtDup = $db->prepare($dupSql);
            $stmtDup->execute($dupParams);
            if ($stmtDup->fetch()) {
                echo json_encode(['success' => false, 'message' => 'An employee with this official email already exists.']);
                exit;
            }

            // Check / generate employee code
            if (empty($employeeCode)) {
                $maxCode = (int)$db->query("SELECT MAX(CAST(SUBSTR(employee_code, 5) AS INTEGER)) FROM employees WHERE employee_code LIKE 'EMP-%'")->fetchColumn();
                $employeeCode = 'EMP-' . str_pad($maxCode + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $dupCodeSql = "SELECT id FROM employees WHERE employee_code = ?";
                $dupCodeParams = [$employeeCode];
                if ($id > 0) {
                    $dupCodeSql .= " AND id != ?";
                    $dupCodeParams[] = $id;
                }
                $stmtDupCode = $db->prepare($dupCodeSql);
                $stmtDupCode->execute($dupCodeParams);
                if ($stmtDupCode->fetch()) {
                    echo json_encode(['success' => false, 'message' => "Employee code '{$employeeCode}' is already in use."]);
                    exit;
                }
            }

            $parts = explode(' ', $fullName, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';

            $currentUserId = $_SESSION['user_id'] ?? 1;

            if ($id > 0) {
                // UPDATE EMPLOYEE
                $stmtUpd = $db->prepare("
                    UPDATE employees SET
                        first_name = ?, last_name = ?, full_name = ?, email = ?, phone = ?, department_id = ?,
                        designation_id = ?, reporting_manager_id = ?, date_of_joining = ?,
                        employment_type = ?, employment_status = ?, work_location = ?,
                        date_of_birth = ?, address = ?, emergency_contact_name = ?,
                        emergency_contact_phone = ?, emergency_contact_relation = ?, hr_notes = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUpd->execute([
                    $firstName, $lastName, $fullName, $email, $phone, $deptId,
                    $desigId, $managerId, $joiningDate,
                    $empType, $empStatus, $workLocation,
                    $dob, $address, $emName,
                    $emPhone, $emRel, $hrNotes,
                    $id
                ]);

                if ($empStatus === 'Inactive') {
                    $empRow = $db->query("SELECT user_id FROM employees WHERE id = $id")->fetch();
                    if ($empRow && $empRow['user_id']) {
                        $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$empRow['user_id']]);
                    }
                }

                logActivity($currentUserId, 'hr-management', 'UPDATE_EMPLOYEE', 'employees', $id, "Updated employee {$employeeCode} ({$fullName})");
                echo json_encode(['success' => true, 'message' => "Employee '{$fullName}' updated successfully.", 'employee_id' => $id]);
            } else {
                // INSERT NEW EMPLOYEE
                $linkedUserId = null;

                if ($createUser) {
                    $stmtUserCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
                    $stmtUserCheck->execute([$email]);
                    $existingUser = $stmtUserCheck->fetch();

                    if ($existingUser) {
                        $linkedUserId = $existingUser['id'];
                    } else {
                        $baseUsername = strtolower(explode('@', $email)[0]);
                        $cleanUsername = preg_replace('/[^a-z0-9_]/', '', $baseUsername);
                        $finalUsername = $cleanUsername;
                        $counter = 1;
                        while ($db->query("SELECT COUNT(*) FROM users WHERE username = '$finalUsername'")->fetchColumn() > 0) {
                            $finalUsername = $cleanUsername . $counter;
                            $counter++;
                        }

                        $defaultPasswordHash = password_hash('password123', PASSWORD_BCRYPT);
                        $deptName = $db->query("SELECT name FROM departments WHERE id = $deptId")->fetchColumn() ?: 'General';

                        $stmtNewUser = $db->prepare("
                            INSERT INTO users (username, password_hash, full_name, email, role_id, department, is_active)
                            VALUES (?, ?, ?, ?, ?, ?, 1)
                        ");
                        $stmtNewUser->execute([$finalUsername, $defaultPasswordHash, $fullName, $email, $userRoleId, $deptName]);
                        $linkedUserId = $db->lastInsertId();

                        $stmtPerm = $db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (?, 'hr-management', 1, 0, 0)");
                        $stmtPerm->execute([$linkedUserId]);
                    }
                }

                $stmtIns = $db->prepare("
                    INSERT INTO employees (
                        employee_code, user_id, first_name, last_name, full_name, email, phone,
                        department_id, designation_id, reporting_manager_id, date_of_joining,
                        employment_type, employment_status, work_location, date_of_birth,
                        address, emergency_contact_name, emergency_contact_phone,
                        emergency_contact_relation, hr_notes, is_active, created_by
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, 1, ?
                    )
                ");
                $stmtIns->execute([
                    $employeeCode, $linkedUserId, $firstName, $lastName, $fullName, $email, $phone,
                    $deptId, $desigId, $managerId, $joiningDate,
                    $empType, $empStatus, $workLocation, $dob,
                    $address, $emName, $emPhone,
                    $emRel, $hrNotes, $currentUserId
                ]);
                $newEmpId = $db->lastInsertId();

                // Allocate standard leave balances for current year
                $currentYear = date('Y');
                $leaveTypes = $db->query("SELECT id, default_days_per_year FROM leave_types WHERE is_active = 1")->fetchAll();
                $stmtAllocIns = $db->prepare("INSERT INTO leave_allocations (employee_id, leave_type_id, year, total_allocated, used_days) VALUES (?, ?, ?, ?, 0)");
                foreach ($leaveTypes as $lt) {
                    $stmtAllocIns->execute([$newEmpId, $lt['id'], $currentYear, $lt['default_days_per_year']]);
                }

                logActivity($currentUserId, 'hr-management', 'CREATE_EMPLOYEE', 'employees', $newEmpId, "Created employee {$employeeCode} ({$fullName})");
                echo json_encode([
                    'success' => true,
                    'message' => "Employee '{$fullName}' created successfully with ID {$employeeCode}." . ($createUser ? " Login credentials generated (Default: password123)." : ""),
                    'employee_id' => $newEmpId,
                    'employee_code' => $employeeCode
                ]);
            }
            break;

        case 'deactivate_employee':
            checkAuth();
            if (!isHrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid Employee ID']);
                exit;
            }

            $emp = $db->query("SELECT * FROM employees WHERE id = $id")->fetch();
            if (!$emp) {
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
                exit;
            }

            $newStatus = ($emp['employment_status'] === 'Inactive') ? 'Active' : 'Inactive';
            $db->prepare("UPDATE employees SET employment_status = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
               ->execute([$newStatus, ($newStatus === 'Active' ? 1 : 0), $id]);

            if ($emp['user_id']) {
                $userActive = ($newStatus === 'Active') ? 1 : 0;
                $db->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$userActive, $emp['user_id']]);
            }

            $currentUserId = $_SESSION['user_id'] ?? 1;
            logActivity($currentUserId, 'hr-management', 'DEACTIVATE_EMPLOYEE', 'employees', $id, "Toggled status of employee {$emp['employee_code']} to {$newStatus}");
            echo json_encode(['success' => true, 'message' => "Employee {$emp['full_name']} status changed to {$newStatus}.", 'new_status' => $newStatus]);
            break;

        case 'get_departments':
            checkAuth();
            $sql = "
                SELECT d.*, 
                       (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.employment_status = 'Active') as employee_count
                FROM departments d
                ORDER BY d.name ASC
            ";
            $depts = $db->query($sql)->fetchAll();
            echo json_encode(['success' => true, 'departments' => $depts]);
            break;

        case 'save_department':
            checkAuth();
            if (!isHrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $code = trim($input['code'] ?? strtoupper(substr($name, 0, 4)));
            $description = trim($input['description'] ?? '');
            $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Department name is required.']);
                exit;
            }

            $dupSql = "SELECT id FROM departments WHERE name = ?" . ($id > 0 ? " AND id != $id" : "");
            $dupStmt = $db->prepare($dupSql);
            $dupStmt->execute([$name]);
            if ($dupStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => "Department '{$name}' already exists."]);
                exit;
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE departments SET name = ?, code = ?, description = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $code, $description, $isActive, $id]);
                logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'UPDATE_DEPARTMENT', 'departments', $id, "Updated department {$name}");
                echo json_encode(['success' => true, 'message' => 'Department updated successfully.', 'id' => $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO departments (name, code, description, is_active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $code, $description, $isActive]);
                $newId = $db->lastInsertId();
                logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'CREATE_DEPARTMENT', 'departments', $newId, "Created department {$name}");
                echo json_encode(['success' => true, 'message' => 'Department created successfully.', 'id' => $newId]);
            }
            break;

        case 'delete_department':
            checkAuth();
            if (!isHrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            $empCount = (int)$db->query("SELECT COUNT(*) FROM employees WHERE department_id = $id")->fetchColumn();
            if ($empCount > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete department: {$empCount} active or assigned employee(s) are linked to it. Reassign employees first."]);
                exit;
            }

            $desigCount = (int)$db->query("SELECT COUNT(*) FROM designations WHERE department_id = $id")->fetchColumn();
            if ($desigCount > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete department: {$desigCount} designation(s) belong to it. Reassign or remove designations first."]);
                exit;
            }

            $db->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
            logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'DELETE_DEPARTMENT', 'departments', $id, "Deleted department #$id");
            echo json_encode(['success' => true, 'message' => 'Department deleted successfully.']);
            break;

        case 'get_designations':
            checkAuth();
            $sql = "
                SELECT des.*, d.name as department_name,
                       (SELECT COUNT(*) FROM employees e WHERE e.designation_id = des.id AND e.employment_status = 'Active') as employee_count
                FROM designations des
                LEFT JOIN departments d ON des.department_id = d.id
                ORDER BY des.title ASC
            ";
            $designations = $db->query($sql)->fetchAll();
            echo json_encode(['success' => true, 'designations' => $designations]);
            break;

        case 'save_designation':
            checkAuth();
            if (!isHrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $deptId = intval($input['department_id'] ?? 0);
            $desc = trim($input['description'] ?? '');
            $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Designation title is required.']);
                exit;
            }

            if ($deptId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please select a valid Department for this designation.']);
                exit;
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE designations SET title = ?, department_id = ?, description = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$title, $deptId, $desc, $isActive, $id]);
                logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'UPDATE_DESIGNATION', 'designations', $id, "Updated designation {$title}");
                echo json_encode(['success' => true, 'message' => 'Designation updated successfully.', 'id' => $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO designations (title, department_id, description, is_active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $deptId, $desc, $isActive]);
                $newId = $db->lastInsertId();
                logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'CREATE_DESIGNATION', 'designations', $newId, "Created designation {$title}");
                echo json_encode(['success' => true, 'message' => 'Designation created successfully.', 'id' => $newId]);
            }
            break;

        case 'delete_designation':
            checkAuth();
            if (!isHrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            $empCount = (int)$db->query("SELECT COUNT(*) FROM employees WHERE designation_id = $id")->fetchColumn();
            if ($empCount > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete designation: $empCount employee(s) hold this title. Reassign employees first."]);
                exit;
            }

            $db->prepare("DELETE FROM designations WHERE id = ?")->execute([$id]);
            logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'DELETE_DESIGNATION', 'designations', $id, "Deleted designation #$id");
            echo json_encode(['success' => true, 'message' => 'Designation deleted successfully.']);
            break;

        case 'get_today_attendance_status':
            checkAuth();
            $currEmp = getCurrentEmployee();
            if (!$currEmp) {
                echo json_encode(['success' => false, 'message' => 'No employee profile linked to current account.']);
                exit;
            }

            $today = date('Y-m-d');
            $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $stmt->execute([$currEmp['id'], $today]);
            $record = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'today' => $today,
                'employee' => [
                    'id' => $currEmp['id'],
                    'code' => $currEmp['employee_code'],
                    'name' => $currEmp['full_name']
                ],
                'record' => $record ?: null
            ]);
            break;

        case 'clock_in':
            checkAuth();
            $currEmp = getCurrentEmployee();
            if (!$currEmp) {
                echo json_encode(['success' => false, 'message' => 'You must have a linked Employee profile to record attendance.']);
                exit;
            }

            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');

            $stmtCheck = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $stmtCheck->execute([$currEmp['id'], $today]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                if (!empty($existing['check_in'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'You have already clocked in today at ' . date('h:i A', strtotime($existing['check_in'])),
                        'record' => $existing
                    ]);
                    exit;
                }
                $stmtUpd = $db->prepare("UPDATE attendance SET check_in = ?, status = 'Present', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtUpd->execute([$now, $existing['id']]);
                $recId = $existing['id'];
            } else {
                $stmtIns = $db->prepare("
                    INSERT INTO attendance (employee_id, attendance_date, check_in, status, notes)
                    VALUES (?, ?, ?, 'Present', 'Self Web Clock-In')
                ");
                $stmtIns->execute([$currEmp['id'], $today, $now]);
                $recId = $db->lastInsertId();
            }

            logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'CLOCK_IN', 'attendance', $recId, "Employee {$currEmp['employee_code']} ({$currEmp['full_name']}) clocked in");

            echo json_encode([
                'success' => true,
                'message' => 'Clock-in successful at ' . date('h:i A', strtotime($now)),
                'check_in' => $now,
                'status' => 'Present'
            ]);
            break;

        case 'clock_out':
            checkAuth();
            $currEmp = getCurrentEmployee();
            if (!$currEmp) {
                echo json_encode(['success' => false, 'message' => 'You must have a linked Employee profile to record attendance.']);
                exit;
            }

            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');

            $stmtCheck = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $stmtCheck->execute([$currEmp['id'], $today]);
            $existing = $stmtCheck->fetch();

            if (!$existing || empty($existing['check_in'])) {
                echo json_encode(['success' => false, 'message' => 'You have not clocked in today. Please clock in first.']);
                exit;
            }

            if (!empty($existing['check_out'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You have already clocked out today at ' . date('h:i A', strtotime($existing['check_out'])),
                    'record' => $existing
                ]);
                exit;
            }

            $checkInTime = strtotime($existing['check_in']);
            $checkOutTime = strtotime($now);
            $totalHours = round(($checkOutTime - $checkInTime) / 3600, 2);
            $status = ($totalHours >= 4.0) ? 'Present' : 'Half Day';

            $stmtUpd = $db->prepare("
                UPDATE attendance SET
                    check_out = ?, total_hours = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmtUpd->execute([$now, $totalHours, $status, $existing['id']]);

            logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'CLOCK_OUT', 'attendance', $existing['id'], "Employee {$currEmp['employee_code']} ({$currEmp['full_name']}) clocked out. Total: {$totalHours} hrs");

            echo json_encode([
                'success' => true,
                'message' => "Clock-out recorded at " . date('h:i A', strtotime($now)) . ". Total work time: {$totalHours} hours.",
                'check_out' => $now,
                'total_hours' => $totalHours,
                'status' => $status
            ]);
            break;

        case 'get_attendance_records':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $isHr = isHrAdmin();
            $currEmp = getCurrentEmployee();

            $date = trim($_GET['date'] ?? '');
            $month = trim($_GET['month'] ?? '');
            $empId = intval($_GET['employee_id'] ?? 0);
            $deptId = intval($_GET['department_id'] ?? 0);
            $status = trim($_GET['status'] ?? '');

            $params = [];
            $where = ["1=1"];

            if (!$isHr && !isTeamLeadOrManager()) {
                if (!$currEmp) {
                    echo json_encode(['success' => true, 'records' => []]);
                    exit;
                }
                $where[] = "a.employee_id = ?";
                $params[] = $currEmp['id'];
            } elseif ($empId > 0) {
                $where[] = "a.employee_id = ?";
                $params[] = $empId;
            }

            if (!empty($date)) {
                $where[] = "a.attendance_date = ?";
                $params[] = $date;
            } elseif (!empty($month)) {
                $where[] = "a.attendance_date LIKE ?";
                $params[] = "$month%";
            }

            if ($deptId > 0) {
                $where[] = "e.department_id = ?";
                $params[] = $deptId;
            }

            if (!empty($status)) {
                $where[] = "a.status = ?";
                $params[] = $status;
            }

            $sql = "
                SELECT a.*, e.employee_code, e.full_name as employee_name, d.name as department_name,
                       u_corr.full_name as corrected_by_name
                FROM attendance a
                JOIN employees e ON a.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN users u_corr ON a.corrected_by = u_corr.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.attendance_date DESC, e.full_name ASC
                LIMIT 300
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $records = $stmt->fetchAll();

            echo json_encode(['success' => true, 'records' => $records]);
            break;

        case 'get_attendance_calendar':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $isHr = isHrAdmin();
            $isLead = isTeamLeadOrManager();
            $currEmp = getCurrentEmployee();

            $month = trim($_GET['month'] ?? date('Y-m'));
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                $month = date('Y-m');
            }

            $empId = intval($_GET['employee_id'] ?? 0);
            if (!$isHr && !$isLead) {
                $empId = $currEmp ? intval($currEmp['id']) : 0;
            } elseif ($empId <= 0) {
                $empId = $currEmp ? intval($currEmp['id']) : 0;
                if ($empId <= 0) {
                    $firstEmp = $db->query("SELECT id FROM employees WHERE employment_status = 'Active' ORDER BY full_name ASC LIMIT 1")->fetch();
                    $empId = $firstEmp ? intval($firstEmp['id']) : 1;
                }
            }

            // Fetch target employee
            $stmtEmp = $db->prepare("
                SELECT e.*, d.name as department_name, des.title as designation_title
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN designations des ON e.designation_id = des.id
                WHERE e.id = ?
            ");
            $stmtEmp->execute([$empId]);
            $employee = $stmtEmp->fetch();

            if (!$employee) {
                echo json_encode(['success' => false, 'message' => 'Employee profile not found.']);
                exit;
            }

            // Fetch records for this month
            $stmtRec = $db->prepare("
                SELECT * FROM attendance
                WHERE employee_id = ? AND (attendance_date LIKE ? OR strftime('%Y-%m', attendance_date) = ?)
                ORDER BY attendance_date ASC
            ");
            $stmtRec->execute([$empId, "$month%", $month]);
            $rawRecords = $stmtRec->fetchAll();

            $recordsByDate = [];
            $presentCount = 0;
            $halfDayCount = 0;
            $wfhCount = 0;
            $leaveCount = 0;

            foreach ($rawRecords as $r) {
                $d = $r['attendance_date'];
                $recordsByDate[$d] = $r;

                $s1 = strtoupper($r['section_1'] ?? '');
                $s2 = strtoupper($r['section_2'] ?? '');
                $status = $r['status'] ?? '';

                if ($status === 'Present' || ($s1 === 'P' && $s2 === 'P')) {
                    $presentCount++;
                } elseif ($status === 'Half Day' || (($s1 === 'P' || $s1 === 'WFH') && (empty($s2) || $s2 === 'L')) || (($s2 === 'P' || $s2 === 'WFH') && (empty($s1) || $s1 === 'L'))) {
                    $halfDayCount++;
                } elseif ($status === 'WFH' || ($s1 === 'WFH' && $s2 === 'WFH')) {
                    $wfhCount++;
                } elseif ($status === 'On Leave' || ($s1 === 'L' && $s2 === 'L')) {
                    $leaveCount++;
                }
            }

            // Fetch holidays for this month
            $stmtHol = $db->prepare("
                SELECT * FROM holidays
                WHERE holiday_date LIKE ? OR strftime('%Y-%m', holiday_date) = ?
                ORDER BY holiday_date ASC
            ");
            $stmtHol->execute(["$month%", $month]);
            $holidays = $stmtHol->fetchAll();

            $holidaysByDate = [];
            foreach ($holidays as $h) {
                $holidaysByDate[$h['holiday_date']] = $h;
            }

            $effectiveDays = $presentCount + $wfhCount + ($halfDayCount * 0.5);

            echo json_encode([
                'success' => true,
                'month' => $month,
                'employee' => $employee,
                'summary' => [
                    'present_days' => $presentCount,
                    'half_days' => $halfDayCount,
                    'wfh_days' => $wfhCount,
                    'leave_days' => $leaveCount,
                    'effective_days' => $effectiveDays,
                    'total_entries' => count($rawRecords)
                ],
                'records_by_date' => $recordsByDate,
                'holidays_by_date' => $holidaysByDate
            ]);
            break;

        case 'mark_attendance':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $isHr = isHrAdmin();
            $isLead = isTeamLeadOrManager();
            $currEmp = getCurrentEmployee();

            $employeeId = intval($input['employee_id'] ?? ($currEmp ? $currEmp['id'] : 0));
            $date = trim($input['attendance_date'] ?? $input['date'] ?? date('Y-m-d'));
            $rawS1 = trim($input['section_1'] ?? '');
            $rawS2 = trim($input['section_2'] ?? '');
            $notes = trim($input['notes'] ?? '');

            if (!$isHr) {
                if (!$currEmp || $employeeId != $currEmp['id']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access Denied: Only HR and Super Admin can mark or edit attendance for other employees.']);
                    exit;
                }
            }

            if ($employeeId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid employee specified.']);
                exit;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                echo json_encode(['success' => false, 'message' => 'Invalid date format (YYYY-MM-DD required).']);
                exit;
            }

            $allowedMarks = ['P', 'L', 'WFH'];
            $s1 = in_array(strtoupper($rawS1), $allowedMarks) ? strtoupper($rawS1) : null;
            $s2 = in_array(strtoupper($rawS2), $allowedMarks) ? strtoupper($rawS2) : null;

            // Compute overall status and hours based on user's exact specification:
            // "section 1 and section 2 like half day type 1st section mark only it half day and the mark can P for present ,L for leave , wfh for work from home"
            if ($s1 === null && $s2 === null) {
                // If neither is marked, clear the record for this date
                $db->prepare("DELETE FROM attendance WHERE employee_id = ? AND attendance_date = ?")->execute([$employeeId, $date]);
                logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'CLEAR_ATTENDANCE', 'attendance', $employeeId, "Cleared attendance for Employee #$employeeId on $date");
                echo json_encode(['success' => true, 'message' => "Attendance for {$date} cleared."]);
                exit;
            }

            $status = 'Present';
            $totalHours = 8.5;

            if ($s1 === 'P' && $s2 === 'P') {
                $status = 'Present';
                $totalHours = 8.5;
            } elseif ($s1 === 'WFH' && $s2 === 'WFH') {
                $status = 'WFH';
                $totalHours = 8.5;
            } elseif ($s1 === 'L' && $s2 === 'L') {
                $status = 'On Leave';
                $totalHours = 0.0;
            } elseif ($s1 === 'P' && ($s2 === null || $s2 === 'L')) {
                // 1st section marked only -> Half Day!
                $status = 'Half Day';
                $totalHours = 4.25;
            } elseif (($s1 === null || $s1 === 'L') && $s2 === 'P') {
                // 2nd section marked only -> Half Day!
                $status = 'Half Day';
                $totalHours = 4.25;
            } elseif ($s1 === 'WFH' && ($s2 === null || $s2 === 'L')) {
                $status = 'Half Day';
                $totalHours = 4.25;
            } elseif (($s1 === null || $s1 === 'L') && $s2 === 'WFH') {
                $status = 'Half Day';
                $totalHours = 4.25;
            } elseif ($s1 === 'P' && $s2 === 'WFH') {
                $status = 'Present / WFH';
                $totalHours = 8.5;
            } elseif ($s1 === 'WFH' && $s2 === 'P') {
                $status = 'WFH / Present';
                $totalHours = 8.5;
            }

            // Check if record already exists for this employee and date
            $stmtEx = $db->prepare("SELECT id, notes FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $stmtEx->execute([$employeeId, $date]);
            $existing = $stmtEx->fetch();

            if ($existing) {
                $finalNotes = $notes ?: ($existing['notes'] ?? '');
                $stmtUpd = $db->prepare("
                    UPDATE attendance SET
                        section_1 = ?, section_2 = ?, status = ?, total_hours = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUpd->execute([$s1, $s2, $status, $totalHours, $finalNotes, $existing['id']]);
                $recId = $existing['id'];
                $actMsg = "Updated attendance for Employee #$employeeId on $date: S1=$s1, S2=$s2 ($status)";
            } else {
                $stmtIns = $db->prepare("
                    INSERT INTO attendance (employee_id, attendance_date, section_1, section_2, status, total_hours, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtIns->execute([$employeeId, $date, $s1, $s2, $status, $totalHours, $notes]);
                $recId = $db->lastInsertId();
                $actMsg = "Marked attendance for Employee #$employeeId on $date: S1=$s1, S2=$s2 ($status)";
            }

            logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'MARK_ATTENDANCE', 'attendance', $recId, $actMsg);

            echo json_encode([
                'success' => true,
                'message' => "Attendance saved for {$date} ({$status}: S1=" . ($s1 ?: '-') . ", S2=" . ($s2 ?: '-') . ")",
                'record' => [
                    'id' => $recId,
                    'employee_id' => $employeeId,
                    'attendance_date' => $date,
                    'section_1' => $s1,
                    'section_2' => $s2,
                    'status' => $status,
                    'total_hours' => $totalHours,
                    'notes' => $notes
                ]
            ]);
            break;

        case 'delete_attendance_record':
            checkAuth();
            $isHr = isHrAdmin();
            $currEmp = getCurrentEmployee();

            $id = intval($input['id'] ?? 0);
            $empId = intval($input['employee_id'] ?? 0);
            $date = trim($input['date'] ?? '');

            if (!$isHr) {
                if (!$currEmp) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access Denied: Only HR and Super Admin can clear attendance records for other employees.']);
                    exit;
                }
                if ($id > 0) {
                    $stOwn = $db->prepare("SELECT employee_id FROM attendance WHERE id = ?");
                    $stOwn->execute([$id]);
                    $ownerEmpId = $stOwn->fetchColumn();
                    if (!$ownerEmpId || intval($ownerEmpId) !== intval($currEmp['id'])) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'Access Denied: Only HR and Super Admin can clear attendance records for other employees.']);
                        exit;
                    }
                } elseif ($empId > 0 && $empId !== intval($currEmp['id'])) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access Denied: Only HR and Super Admin can clear attendance records for other employees.']);
                    exit;
                } elseif ($id <= 0 && $empId <= 0) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access Denied: Only HR and Super Admin can clear attendance records for other employees.']);
                    exit;
                }
            }

            if ($id > 0) {
                $db->prepare("DELETE FROM attendance WHERE id = ?")->execute([$id]);
            } elseif ($empId > 0 && !empty($date)) {
                $db->prepare("DELETE FROM attendance WHERE employee_id = ? AND attendance_date = ?")->execute([$empId, $date]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Record ID or Employee ID and Date required.']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Attendance record removed.']);
            break;

        case 'correct_attendance':
            checkAuth();
            if (!isHrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden: Only HR administrators can correct attendance records.']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            $employeeId = intval($input['employee_id'] ?? 0);
            $date = trim($input['date'] ?? $input['attendance_date'] ?? date('Y-m-d'));
            $checkIn = !empty($input['check_in']) ? trim($input['check_in']) : null;
            $checkOut = !empty($input['check_out']) ? trim($input['check_out']) : null;
            $status = trim($input['status'] ?? 'Present');
            $reason = trim($input['correction_reason'] ?? '');

            if (empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'A mandatory reason is required to audit attendance corrections.']);
                exit;
            }

            $totalHours = null;
            if ($checkIn && $checkOut) {
                $t1 = strtotime($checkIn);
                $t2 = strtotime($checkOut);
                if ($t2 > $t1) {
                    $totalHours = round(($t2 - $t1) / 3600, 2);
                }
            } elseif (isset($input['total_hours']) && $input['total_hours'] !== '') {
                $totalHours = floatval($input['total_hours']);
            }

            $currentUserId = $_SESSION['user_id'] ?? 1;

            $sec1 = isset($input['section_1']) ? (in_array(strtoupper(trim($input['section_1'])), ['P', 'L', 'WFH']) ? strtoupper(trim($input['section_1'])) : null) : null;
            $sec2 = isset($input['section_2']) ? (in_array(strtoupper(trim($input['section_2'])), ['P', 'L', 'WFH']) ? strtoupper(trim($input['section_2'])) : null) : null;

            if ($id > 0) {
                $stmtOld = $db->prepare("SELECT * FROM attendance WHERE id = ?");
                $stmtOld->execute([$id]);
                $old = $stmtOld->fetch();

                $useS1 = ($sec1 !== null) ? $sec1 : ($old['section_1'] ?? null);
                $useS2 = ($sec2 !== null) ? $sec2 : ($old['section_2'] ?? null);

                $notes = ($old['notes'] ?? '') . " [Corrected by HR: {$reason}]";
                $stmtUpd = $db->prepare("
                    UPDATE attendance SET
                        check_in = ?, check_out = ?, total_hours = ?, status = ?,
                        section_1 = ?, section_2 = ?,
                        is_corrected = 1, corrected_by = ?, correction_reason = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUpd->execute([$checkIn, $checkOut, $totalHours, $status, $useS1, $useS2, $currentUserId, $reason, $notes, $id]);

                logActivity($currentUserId, 'hr-management', 'CORRECT_ATTENDANCE', 'attendance', $id, "Corrected attendance #$id: Reason: $reason");
                echo json_encode(['success' => true, 'message' => 'Attendance record corrected successfully.']);
            } else {
                if ($employeeId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Employee is required for manual entry.']);
                    exit;
                }

                $notes = "Manual HR Entry: {$reason}";
                $stmtIns = $db->prepare("
                    INSERT INTO attendance (employee_id, attendance_date, check_in, check_out, total_hours, status, section_1, section_2, is_corrected, corrected_by, correction_reason, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
                ");
                $stmtIns->execute([$employeeId, $date, $checkIn, $checkOut, $totalHours, $status, $sec1, $sec2, $currentUserId, $reason, $notes]);
                $newId = $db->lastInsertId();

                logActivity($currentUserId, 'hr-management', 'CREATE_MANUAL_ATTENDANCE', 'attendance', $newId, "Created manual attendance for Employee #$employeeId on $date: $reason");
                echo json_encode(['success' => true, 'message' => 'Attendance record added successfully.']);
            }
            break;

        case 'get_leave_types':
            checkAuth();
            $types = $db->query("SELECT * FROM leave_types ORDER BY id ASC")->fetchAll();
            echo json_encode(['success' => true, 'leave_types' => $types]);
            break;

        case 'save_leave_type':
            checkAuth();
            if (!isHrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $code = trim($input['code'] ?? strtoupper(substr($name, 0, 3)));
            $days = intval($input['default_days_per_year'] ?? 10);
            $isPaid = isset($input['is_paid']) ? (int)$input['is_paid'] : 1;
            $desc = trim($input['description'] ?? '');
            $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Leave type name is required.']);
                exit;
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE leave_types SET name = ?, code = ?, default_days_per_year = ?, is_paid = ?, description = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $code, $days, $isPaid, $desc, $isActive, $id]);
                logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'UPDATE_LEAVE_TYPE', 'leave_types', $id, "Updated leave type {$name}");
                echo json_encode(['success' => true, 'message' => 'Leave type updated successfully.']);
            } else {
                $stmt = $db->prepare("INSERT INTO leave_types (name, code, default_days_per_year, is_paid, description, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $code, $days, $isPaid, $desc, $isActive]);
                $newId = $db->lastInsertId();
                logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'CREATE_LEAVE_TYPE', 'leave_types', $newId, "Created leave type {$name}");
                echo json_encode(['success' => true, 'message' => 'Leave type created successfully.']);
            }
            break;

        case 'get_leave_allocations':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $isHr = isHrAdmin();
            $currEmp = getCurrentEmployee();
            $empId = intval($_GET['employee_id'] ?? 0);
            $year = intval($_GET['year'] ?? date('Y'));

            if (!$isHr && !isTeamLeadOrManager()) {
                if (!$currEmp) {
                    echo json_encode(['success' => true, 'allocations' => []]);
                    exit;
                }
                $empId = $currEmp['id'];
            } elseif ($empId <= 0 && $currEmp) {
                $empId = $currEmp['id'];
            }

            $stmt = $db->prepare("
                SELECT lt.id as leave_type_id, lt.name as leave_type_name, lt.code, lt.is_paid,
                       COALESCE(la.id, 0) as allocation_id,
                       COALESCE(la.total_allocated, lt.default_days_per_year) as total_allocated,
                       COALESCE(la.used_days, 0) as used_days,
                       (COALESCE(la.total_allocated, lt.default_days_per_year) - COALESCE(la.used_days, 0)) as remaining_days
                FROM leave_types lt
                LEFT JOIN leave_allocations la ON lt.id = la.leave_type_id AND la.employee_id = ? AND la.year = ?
                WHERE lt.is_active = 1
                ORDER BY lt.id ASC
            ");
            $stmt->execute([$empId, $year]);
            $allocs = $stmt->fetchAll();

            echo json_encode(['success' => true, 'allocations' => $allocs]);
            break;

        case 'get_leave_requests':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $isHr = isHrAdmin();
            $isLead = isTeamLeadOrManager();
            $currEmp = getCurrentEmployee();

            $status = trim($_GET['status'] ?? '');
            $typeId = intval($_GET['leave_type_id'] ?? 0);
            $empId = intval($_GET['employee_id'] ?? 0);
            $deptId = intval($_GET['department_id'] ?? 0);

            $params = [];
            $where = ["1=1"];

            if (!$isHr && !$isLead) {
                if (!$currEmp) {
                    echo json_encode(['success' => true, 'leave_requests' => []]);
                    exit;
                }
                $where[] = "lr.employee_id = ?";
                $params[] = $currEmp['id'];
            } elseif ($empId > 0) {
                $where[] = "lr.employee_id = ?";
                $params[] = $empId;
            }

            if (!empty($status)) {
                $where[] = "lr.status = ?";
                $params[] = $status;
            }

            if ($typeId > 0) {
                $where[] = "lr.leave_type_id = ?";
                $params[] = $typeId;
            }

            if ($deptId > 0) {
                $where[] = "e.department_id = ?";
                $params[] = $deptId;
            }

            $sql = "
                SELECT lr.*, e.employee_code, e.full_name as employee_name, d.name as department_name,
                       lt.name as leave_type_name, lt.code as leave_type_code,
                       u_appr.full_name as reviewed_by_name
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN users u_appr ON lr.reviewed_by = u_appr.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY lr.created_at DESC
                LIMIT 200
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $requests = $stmt->fetchAll();

            echo json_encode(['success' => true, 'leave_requests' => $requests]);
            break;

        case 'submit_leave_request':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $currEmp = getCurrentEmployee();
            $isHr = isHrAdmin();

            $employeeId = intval($input['employee_id'] ?? 0);
            if (!$isHr || $employeeId <= 0) {
                if (!$currEmp) {
                    echo json_encode(['success' => false, 'message' => 'No employee profile linked to current account.']);
                    exit;
                }
                $employeeId = $currEmp['id'];
            }

            $leaveTypeId = intval($input['leave_type_id'] ?? 0);
            $startDate = trim($input['start_date'] ?? '');
            $endDate = trim($input['end_date'] ?? '');
            $reason = trim($input['reason'] ?? '');

            if ($leaveTypeId <= 0 || empty($startDate) || empty($endDate) || empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'All fields (Leave Type, Start Date, End Date, Reason) are required.']);
                exit;
            }

            if ($startDate > $endDate) {
                echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date.']);
                exit;
            }

            // Calculate total days (count working days, skipping Sundays)
            $totalDays = 0;
            $currDate = strtotime($startDate);
            $endTimestamp = strtotime($endDate);
            while ($currDate <= $endTimestamp) {
                if (date('N', $currDate) != 7) {
                    $totalDays++;
                }
                $currDate = strtotime('+1 day', $currDate);
            }
            if ($totalDays <= 0) $totalDays = 1;

            // Check for overlapping active leave requests
            $stmtOverlap = $db->prepare("
                SELECT id FROM leave_requests
                WHERE employee_id = ?
                  AND status IN ('Pending', 'Approved')
                  AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?) OR (start_date >= ? AND end_date <= ?))
            ");
            $stmtOverlap->execute([$employeeId, $startDate, $startDate, $endDate, $endDate, $startDate, $endDate]);
            if ($stmtOverlap->fetch()) {
                echo json_encode(['success' => false, 'message' => 'You already have an active or approved leave request overlapping these dates.']);
                exit;
            }

            // Check leave balance
            $year = date('Y', strtotime($startDate));
            $stmtBal = $db->prepare("
                SELECT COALESCE(la.total_allocated, lt.default_days_per_year) - COALESCE(la.used_days, 0) as remaining_days,
                       lt.name as leave_name, lt.is_paid
                FROM leave_types lt
                LEFT JOIN leave_allocations la ON lt.id = la.leave_type_id AND la.employee_id = ? AND la.year = ?
                WHERE lt.id = ?
            ");
            $stmtBal->execute([$employeeId, $year, $leaveTypeId]);
            $bal = $stmtBal->fetch();

            if ($bal && $bal['is_paid'] && $bal['remaining_days'] < $totalDays) {
                echo json_encode(['success' => false, 'message' => "Insufficient leave balance. You have {$bal['remaining_days']} days remaining for {$bal['leave_name']}, requested {$totalDays} days."]);
                exit;
            }

            $stmtIns = $db->prepare("
                INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, reason, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $stmtIns->execute([$employeeId, $leaveTypeId, $startDate, $endDate, $totalDays, $reason]);
            $reqId = $db->lastInsertId();

            $empName = $db->query("SELECT full_name FROM employees WHERE id = $employeeId")->fetchColumn() ?: 'Employee';
            $stmtNotif = $db->prepare("
                INSERT INTO hr_notifications (user_id, title, message, type, link_url)
                VALUES (1, 'New Leave Request', ?, 'leave', 'hr.php?tab=leaves')
            ");
            $stmtNotif->execute(["{$empName} submitted a leave request for {$totalDays} day(s) from {$startDate} to {$endDate}."]);

            logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'SUBMIT_LEAVE_REQUEST', 'leave_requests', $reqId, "Submitted leave request for {$totalDays} days ({$startDate} to {$endDate})");

            echo json_encode([
                'success' => true,
                'message' => "Leave request for {$totalDays} day(s) submitted successfully.",
                'request_id' => $reqId,
                'total_days' => $totalDays
            ]);
            break;

        case 'process_leave_request':
            checkAuth();
            if (!isHrAdmin() && !isTeamLeadOrManager()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden: Only HR administrators and Managers can approve or reject leaves.']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            $decision = trim($input['status'] ?? '');
            $comment = trim($input['manager_comment'] ?? '');

            if ($id <= 0 || !in_array($decision, ['Approved', 'Rejected'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid request or decision status.']);
                exit;
            }

            $stmtReq = $db->prepare("
                SELECT lr.*, e.user_id, e.full_name as employee_name, lt.name as leave_type_name
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.id = ?
            ");
            $stmtReq->execute([$id]);
            $req = $stmtReq->fetch();

            if (!$req) {
                echo json_encode(['success' => false, 'message' => 'Leave request not found.']);
                exit;
            }

            if ($req['status'] !== 'Pending') {
                echo json_encode(['success' => false, 'message' => "This request has already been {$req['status']}."]);
                exit;
            }

            $currentUserId = $_SESSION['user_id'] ?? 1;
            $now = date('Y-m-d H:i:s');

            $db->beginTransaction();
            try {
                $stmtUpd = $db->prepare("
                    UPDATE leave_requests SET
                        status = ?, reviewed_by = ?, reviewed_at = ?, manager_comment = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmtUpd->execute([$decision, $currentUserId, $now, $comment, $id]);

                if ($decision === 'Approved') {
                    $year = date('Y', strtotime($req['start_date']));
                    $stmtAllocCheck = $db->prepare("SELECT id, used_days FROM leave_allocations WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
                    $stmtAllocCheck->execute([$req['employee_id'], $req['leave_type_id'], $year]);
                    $alloc = $stmtAllocCheck->fetch();

                    if ($alloc) {
                        $newUsed = $alloc['used_days'] + $req['total_days'];
                        $db->prepare("UPDATE leave_allocations SET used_days = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$newUsed, $alloc['id']]);
                    } else {
                        $defaultDays = (int)$db->query("SELECT default_days_per_year FROM leave_types WHERE id = {$req['leave_type_id']}")->fetchColumn();
                        $db->prepare("INSERT INTO leave_allocations (employee_id, leave_type_id, year, total_allocated, used_days) VALUES (?, ?, ?, ?, ?)")
                           ->execute([$req['employee_id'], $req['leave_type_id'], $year, $defaultDays, $req['total_days']]);
                    }

                    // Auto-sync into attendance table: mark all weekdays as 'On Leave'
                    $currDate = strtotime($req['start_date']);
                    $endTimestamp = strtotime($req['end_date']);
                    $stmtAttCheck = $db->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                    $stmtAttUpd = $db->prepare("UPDATE attendance SET status = 'On Leave', notes = ? WHERE id = ?");
                    $stmtAttIns = $db->prepare("INSERT INTO attendance (employee_id, attendance_date, status, notes) VALUES (?, ?, 'On Leave', ?)");

                    while ($currDate <= $endTimestamp) {
                        if (date('N', $currDate) != 7) {
                            $dStr = date('Y-m-d', $currDate);
                            $note = "Approved Leave: {$req['leave_type_name']}";
                            $stmtAttCheck->execute([$req['employee_id'], $dStr]);
                            $attRow = $stmtAttCheck->fetch();
                            if ($attRow) {
                                $stmtAttUpd->execute([$note, $attRow['id']]);
                            } else {
                                $stmtAttIns->execute([$req['employee_id'], $dStr, $note]);
                            }
                        }
                        $currDate = strtotime('+1 day', $currDate);
                    }
                }

                if ($req['user_id']) {
                    $stmtNotif = $db->prepare("
                        INSERT INTO hr_notifications (user_id, title, message, type, link_url)
                        VALUES (?, ?, ?, 'leave', 'hr.php?tab=leaves')
                    ");
                    $notifTitle = "Leave Request " . $decision;
                    $notifMsg = "Your {$req['leave_type_name']} request for {$req['start_date']} to {$req['end_date']} was {$decision}." . (!empty($comment) ? " Note: {$comment}" : "");
                    $stmtNotif->execute([$req['user_id'], $notifTitle, $notifMsg]);
                }

                $db->commit();
                logActivity($currentUserId, 'hr-management', 'PROCESS_LEAVE_REQUEST', 'leave_requests', $id, "{$decision} leave request #$id for {$req['employee_name']}");

                echo json_encode(['success' => true, 'message' => "Leave request has been {$decision}."]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to process leave request: ' . $e->getMessage()]);
            }
            break;

        case 'cancel_leave_request':
            checkAuth();
            $id = intval($input['id'] ?? 0);
            $currEmp = getCurrentEmployee();
            $isHr = isHrAdmin();

            $stmt = $db->prepare("SELECT * FROM leave_requests WHERE id = ?");
            $stmt->execute([$id]);
            $req = $stmt->fetch();

            if (!$req) {
                echo json_encode(['success' => false, 'message' => 'Leave request not found.']);
                exit;
            }

            if (!$isHr && (!$currEmp || $currEmp['id'] != $req['employee_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            if ($req['status'] === 'Cancelled') {
                echo json_encode(['success' => false, 'message' => 'This request is already cancelled.']);
                exit;
            }

            $db->beginTransaction();
            try {
                if ($req['status'] === 'Approved') {
                    $year = date('Y', strtotime($req['start_date']));
                    $stmtAlloc = $db->prepare("SELECT id, used_days FROM leave_allocations WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
                    $stmtAlloc->execute([$req['employee_id'], $req['leave_type_id'], $year]);
                    $alloc = $stmtAlloc->fetch();
                    if ($alloc) {
                        $newUsed = max(0, $alloc['used_days'] - $req['total_days']);
                        $db->prepare("UPDATE leave_allocations SET used_days = ? WHERE id = ?")->execute([$newUsed, $alloc['id']]);
                    }

                    $currDate = strtotime($req['start_date']);
                    $endTimestamp = strtotime($req['end_date']);
                    while ($currDate <= $endTimestamp) {
                        $dStr = date('Y-m-d', $currDate);
                        $db->prepare("DELETE FROM attendance WHERE employee_id = ? AND attendance_date = ? AND status = 'On Leave'")->execute([$req['employee_id'], $dStr]);
                        $currDate = strtotime('+1 day', $currDate);
                    }
                }

                $db->prepare("UPDATE leave_requests SET status = 'Cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
                $db->commit();

                logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'CANCEL_LEAVE_REQUEST', 'leave_requests', $id, "Cancelled leave request #$id");
                echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully.']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to cancel request: ' . $e->getMessage()]);
            }
            break;

        case 'get_holidays':
            checkAuth();
            $year = intval($_GET['year'] ?? date('Y'));
            $month = trim($_GET['month'] ?? '');

            $params = [];
            $where = ["1=1"];

            if ($year > 0 && empty($month)) {
                $where[] = "holiday_date LIKE ?";
                $params[] = "$year-%";
            } elseif (!empty($month)) {
                $where[] = "holiday_date LIKE ?";
                $params[] = "$month%";
            }

            $sql = "SELECT * FROM holidays WHERE " . implode(' AND ', $where) . " ORDER BY holiday_date ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $holidays = $stmt->fetchAll();

            echo json_encode(['success' => true, 'holidays' => $holidays]);
            break;

        case 'save_holiday':
            checkAuth();
            if (!isHrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden: Only HR administrators can manage the holiday calendar.']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            $name = trim($input['holiday_name'] ?? $input['name'] ?? '');
            $date = trim($input['holiday_date'] ?? '');
            $desc = trim($input['description'] ?? '');
            $isOptional = isset($input['is_optional']) ? (int)$input['is_optional'] : 0;

            if (empty($name) || empty($date)) {
                echo json_encode(['success' => false, 'message' => 'Holiday Name and Date are required.']);
                exit;
            }

            $dupSql = "SELECT id FROM holidays WHERE holiday_date = ?";
            $dupParams = [$date];
            if ($id > 0) {
                $dupSql .= " AND id != ?";
                $dupParams[] = $id;
            }
            $stmtDup = $db->prepare($dupSql);
            $stmtDup->execute($dupParams);
            if ($stmtDup->fetch()) {
                echo json_encode(['success' => false, 'message' => "A holiday on {$date} is already scheduled."]);
                exit;
            }

            $currentUserId = $_SESSION['user_id'] ?? 1;

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE holidays SET holiday_name = ?, holiday_date = ?, description = ?, is_optional = ? WHERE id = ?");
                $stmt->execute([$name, $date, $desc, $isOptional, $id]);
                logActivity($currentUserId, 'hr-management', 'UPDATE_HOLIDAY', 'holidays', $id, "Updated holiday {$name} on {$date}");
                echo json_encode(['success' => true, 'message' => 'Holiday updated successfully.']);
            } else {
                $stmt = $db->prepare("INSERT INTO holidays (holiday_name, holiday_date, description, is_optional, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $date, $desc, $isOptional, $currentUserId]);
                $newId = $db->lastInsertId();
                logActivity($currentUserId, 'hr-management', 'CREATE_HOLIDAY', 'holidays', $newId, "Added holiday {$name} on {$date}");
                echo json_encode(['success' => true, 'message' => 'Holiday added successfully.']);
            }
            break;

        case 'delete_holiday':
            checkAuth();
            if (!isHrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            $id = intval($input['id'] ?? 0);
            $db->prepare("DELETE FROM holidays WHERE id = ?")->execute([$id]);
            logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'DELETE_HOLIDAY', 'holidays', $id, "Deleted holiday #$id");
            echo json_encode(['success' => true, 'message' => 'Holiday deleted successfully.']);
            break;

        case 'upload_employee_document':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $isHr = isHrAdmin();
            $currEmp = getCurrentEmployee();

            $employeeId = intval($_POST['employee_id'] ?? 0);
            if (!$isHr || $employeeId <= 0) {
                if (!$currEmp) {
                    echo json_encode(['success' => false, 'message' => 'No employee profile linked to current account.']);
                    exit;
                }
                $employeeId = $currEmp['id'];
            }

            $title = trim($_POST['document_title'] ?? $_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? $_POST['document_type'] ?? 'General Document');
            $isConfidential = (isset($_POST['is_confidential']) && $isHr) ? (int)$_POST['is_confidential'] : 0;

            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Document title is required.']);
                exit;
            }

            if (empty($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Please select a valid document file to upload.']);
                exit;
            }

            $file = $_FILES['document_file'];
            $fileSize = $file['size'];
            $origName = $file['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
            if (!in_array($ext, $allowedExtensions)) {
                echo json_encode(['success' => false, 'message' => 'File extension not allowed. Supported: PDF, DOC, DOCX, JPG, PNG, TXT.']);
                exit;
            }

            if ($fileSize > 10 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File exceeds maximum allowed size of 10MB.']);
                exit;
            }

            $uploadDir = __DIR__ . '/uploads/employee_docs';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $safeFileName = 'doc_emp' . $employeeId . '_' . time() . '_' . substr(md5(uniqid(rand(), true)), 0, 8) . '.' . $ext;
            $destination = $uploadDir . '/' . $safeFileName;
            $relativeDbPath = 'uploads/employee_docs/' . $safeFileName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
                exit;
            }

            $mimeType = mime_content_type($destination) ?: 'application/octet-stream';
            $currentUserId = $_SESSION['user_id'] ?? 1;

            $stmt = $db->prepare("
                INSERT INTO employee_documents (employee_id, document_title, category, file_name, file_path, file_size, file_type, is_confidential, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$employeeId, $title, $category, $origName, $relativeDbPath, $fileSize, $mimeType, $isConfidential, $currentUserId]);
            $docId = $db->lastInsertId();

            logActivity($currentUserId, 'hr-management', 'UPLOAD_DOCUMENT', 'employee_documents', $docId, "Uploaded document '{$title}' for Employee #$employeeId");

            echo json_encode([
                'success' => true,
                'message' => 'Document uploaded successfully.',
                'document_id' => $docId,
                'file_name' => $origName
            ]);
            break;

        case 'delete_employee_document':
            checkAuth();
            $id = intval($input['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM employee_documents WHERE id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            if (!$doc) {
                echo json_encode(['success' => false, 'message' => 'Document not found.']);
                exit;
            }

            $isHr = isHrAdmin();
            $currEmp = getCurrentEmployee();

            if (!$isHr && (!$currEmp || $currEmp['id'] != $doc['employee_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            $filePath = __DIR__ . '/' . ltrim($doc['file_path'], '/');
            if (file_exists($filePath)) {
                @unlink($filePath);
            }

            $db->prepare("DELETE FROM employee_documents WHERE id = ?")->execute([$id]);
            logActivity($_SESSION['user_id'] ?? 1, 'hr-management', 'DELETE_DOCUMENT', 'employee_documents', $id, "Deleted document #$id ({$doc['document_title']})");

            echo json_encode(['success' => true, 'message' => 'Document deleted successfully.']);
            break;

        case 'get_hr_reports_data':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $reportType = trim($_GET['report_type'] ?? 'employees_status');
            $deptId = intval($_GET['department_id'] ?? 0);
            $month = trim($_GET['month'] ?? date('Y-m'));
            $year = intval($_GET['year'] ?? date('Y'));

            $data = [];

            if ($reportType === 'employees_status' || $reportType === 'monthly_employees') {
                $sql = "
                    SELECT e.id, e.employee_code, e.full_name, e.email, e.phone, e.employment_status,
                           e.employment_type, e.work_location, e.date_of_joining,
                           d.name as department_name, des.title as designation_title,
                           m.full_name as reporting_manager_name,
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
                if ($deptId > 0) $sql .= " AND e.department_id = $deptId";
                $sql .= " ORDER BY e.employee_code ASC";
                $data = $db->query($sql)->fetchAll();

                $activeCount = 0; $newJoiners = 0; $onLeave = 0;
                foreach ($data as &$em) {
                    $em['is_new_joiner'] = (!empty($em['date_of_joining']) && strpos($em['date_of_joining'], $month) === 0) ? 1 : 0;
                    if ($em['employment_status'] === 'Active') $activeCount++;
                    if ($em['is_new_joiner']) $newJoiners++;
                    if (floatval($em['month_leave_days']) > 0) $onLeave++;
                }
                unset($em);

                $summary = [
                    'total_headcount' => count($data),
                    'active_count' => $activeCount,
                    'new_joiners' => $newJoiners,
                    'on_leave' => $onLeave
                ];
                echo json_encode(['success' => true, 'report_type' => $reportType, 'month' => $month, 'summary' => $summary, 'data' => $data]);
                exit;

            } elseif ($reportType === 'monthly_leaves') {
                $sql = "
                    SELECT lr.*, e.employee_code, e.full_name as employee_name, d.name as department_name, des.title as designation_title,
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
                if ($deptId > 0) $sql .= " AND e.department_id = $deptId";
                $sql .= " ORDER BY lr.start_date DESC";
                $data = $db->query($sql)->fetchAll();

                $approvedDays = 0; $approvedCount = 0; $pendingCount = 0; $rejectedCount = 0;
                foreach ($data as $lv) {
                    if ($lv['status'] === 'Approved') {
                        $approvedCount++;
                        $approvedDays += floatval($lv['total_days']);
                    } elseif ($lv['status'] === 'Pending') {
                        $pendingCount++;
                    } else {
                        $rejectedCount++;
                    }
                }

                $summary = [
                    'total_requests' => count($data),
                    'approved_count' => $approvedCount,
                    'approved_days' => $approvedDays,
                    'pending_count' => $pendingCount,
                    'rejected_count' => $rejectedCount
                ];
                echo json_encode(['success' => true, 'report_type' => $reportType, 'month' => $month, 'summary' => $summary, 'data' => $data]);
                exit;

            } elseif ($reportType === 'monthly_attendance_matrix') {
                $daysInM = intval(date('t', strtotime($month . '-01')));
                $sql = "
                    SELECT e.id as employee_id, e.employee_code, e.full_name, e.date_of_joining,
                           d.name as department_name, des.title as designation_title,
                           COUNT(a.id) as total_logged_days,
                           SUM(CASE WHEN a.status = 'Present' OR a.section_1 = 'P' OR a.section_2 = 'P' THEN 1 ELSE 0 END) as present_days,
                           SUM(CASE WHEN a.status = 'Half Day' OR a.section_1 = 'HD' OR a.section_2 = 'HD' THEN 1 ELSE 0 END) as half_days,
                           SUM(CASE WHEN a.status = 'WFH' OR a.section_1 = 'WFH' OR a.section_2 = 'WFH' THEN 1 ELSE 0 END) as wfh_days,
                           SUM(CASE WHEN a.status = 'On Leave' OR a.section_1 = 'L' OR a.section_2 = 'L' THEN 1 ELSE 0 END) as leave_days,
                           SUM(CASE WHEN a.status = 'Absent' OR a.section_1 = 'A' OR a.section_2 = 'A' THEN 1 ELSE 0 END) as absent_days,
                           COALESCE(SUM(a.total_hours), 0) as total_hours
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN designations des ON e.designation_id = des.id
                    LEFT JOIN attendance a ON e.id = a.employee_id AND (a.attendance_date LIKE '$month%' OR strftime('%Y-%m', a.attendance_date) = '$month')
                    WHERE e.employment_status = 'Active'
                ";
                if ($deptId > 0) $sql .= " AND e.department_id = $deptId";
                $sql .= " GROUP BY e.id ORDER BY e.employee_code ASC";
                $data = $db->query($sql)->fetchAll();

                foreach ($data as &$row) {
                    $p = intval($row['present_days']);
                    $hd = intval($row['half_days']);
                    $w = intval($row['wfh_days']);
                    $l = intval($row['leave_days']);
                    $row['payable_days'] = $p + $w + ($hd * 0.5) + $l;
                    $row['attendance_pct'] = ($daysInM > 0) ? round(($p + $w + ($hd * 0.5)) / $daysInM * 100, 1) : 0;
                }
                unset($row);

                echo json_encode(['success' => true, 'report_type' => $reportType, 'month' => $month, 'data' => $data]);
                exit;

            } elseif ($reportType === 'department_headcount') {
                $sql = "
                    SELECT d.id, d.name, d.code,
                           COUNT(e.id) as total_headcount,
                           SUM(CASE WHEN e.employment_status = 'Active' THEN 1 ELSE 0 END) as active_count,
                           SUM(CASE WHEN e.employment_status = 'Inactive' THEN 1 ELSE 0 END) as inactive_count
                    FROM departments d
                    LEFT JOIN employees e ON d.id = e.department_id
                    GROUP BY d.id
                    ORDER BY total_headcount DESC
                ";
                $data = $db->query($sql)->fetchAll();
            } elseif ($reportType === 'monthly_attendance') {
                $sql = "
                    SELECT a.attendance_date, a.status, COUNT(a.id) as count,
                           AVG(a.total_hours) as avg_hours
                    FROM attendance a
                    WHERE a.attendance_date LIKE '$month%'
                    GROUP BY a.attendance_date, a.status
                    ORDER BY a.attendance_date ASC
                ";
                $data = $db->query($sql)->fetchAll();
            } elseif ($reportType === 'leave_usage') {
                $sql = "
                    SELECT lt.name as leave_type, lt.code,
                           SUM(la.total_allocated) as total_allocated,
                           SUM(la.used_days) as total_used,
                           (SUM(la.total_allocated) - SUM(la.used_days)) as total_remaining
                    FROM leave_types lt
                    LEFT JOIN leave_allocations la ON lt.id = la.leave_type_id AND la.year = $year
                    GROUP BY lt.id
                    ORDER BY lt.id ASC
                ";
                $data = $db->query($sql)->fetchAll();
            }

            echo json_encode(['success' => true, 'report_type' => $reportType, 'data' => $data]);
            break;

        case 'get_hr_notifications':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = $currUser['id'] ?? 0;

            $stmt = $db->prepare("
                SELECT * FROM hr_notifications
                WHERE (user_id = ? OR (user_id = 1 AND ? = 1))
                ORDER BY created_at DESC LIMIT 20
            ");
            $isHr = isHrAdmin() ? 1 : 0;
            $stmt->execute([$userId, $isHr]);
            $notifs = $stmt->fetchAll();

            $unreadCount = 0;
            foreach ($notifs as $n) {
                if (!$n['is_read']) $unreadCount++;
            }

            echo json_encode(['success' => true, 'notifications' => $notifs, 'unread_count' => $unreadCount]);
            break;

        case 'mark_hr_notification_read':
            checkAuth();
            $id = intval($input['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE hr_notifications SET is_read = 1 WHERE id = ?")->execute([$id]);
            } else {
                $currUser = getCurrentUser();
                $db->prepare("UPDATE hr_notifications SET is_read = 1 WHERE user_id = ?")->execute([$currUser['id']]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'get_user_notifications':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = $currUser['id'] ?? 0;

            try {
                ensureUserNotificationsTable($db);
            } catch (Exception $e) {}

            $stmt = $db->prepare("
                SELECT * FROM user_notifications
                WHERE user_id = ?
                ORDER BY created_at DESC LIMIT 25
            ");
            $stmt->execute([$userId]);
            $notifs = $stmt->fetchAll();

            $unreadCount = 0;
            foreach ($notifs as $n) {
                if (!$n['is_read']) $unreadCount++;
            }

            echo json_encode([
                'success' => true,
                'notifications' => $notifs,
                'unread_count' => $unreadCount
            ]);
            break;

        case 'mark_user_notification_read':
            checkAuth();
            $currUser = getCurrentUser();
            $id = intval($input['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $currUser['id']]);
            } else {
                $db->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?")->execute([$currUser['id']]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'update_work_status':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = intval($currUser['id'] ?? 0);
            if ($userId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            // Allow Super Admin, HR, or Team Lead to update another staff member's status (e.g. mark them back at desk)
            $targetUserId = intval($input['user_id'] ?? $input['target_user_id'] ?? 0);
            $isOtherUser = false;
            if ($targetUserId > 0 && $targetUserId !== $userId) {
                $isSuperAdmin = ($currUser['role'] === 'Super Admin' || ($currUser['role_id'] ?? 0) == 1);
                if ($isSuperAdmin || isHrAdmin() || isTeamLeadOrManager()) {
                    $userId = $targetUserId;
                    $isOtherUser = true;
                }
            }

            $rawStatus = strtoupper(trim($input['status'] ?? 'IN_OFFICE'));
            $validStatuses = [
                'IN_OFFICE' => ['label' => 'In Office / At Desk', 'short' => 'At Desk', 'emoji' => '🟢', 'is_away' => false],
                'TEA_BREAK' => ['label' => 'Tea Break', 'short' => 'Tea Break', 'emoji' => '☕', 'is_away' => true],
                'AFK' => ['label' => 'Away From Keyboard', 'short' => 'AFK', 'emoji' => '⏸️', 'is_away' => true],
                'MEETING' => ['label' => 'In a Meeting', 'short' => 'Meeting', 'emoji' => '👥', 'is_away' => true],
                'LUNCH' => ['label' => 'Lunch Break', 'short' => 'Lunch', 'emoji' => '🍽️', 'is_away' => true],
                'OUT_OF_OFFICE' => ['label' => 'Out of Office', 'short' => 'Out of Office', 'emoji' => '🚪', 'is_away' => true]
            ];

            if (!isset($validStatuses[$rawStatus])) {
                $rawStatus = 'IN_OFFICE';
            }

            $statusMeta = $validStatuses[$rawStatus];
            $note = trim($input['status_note'] ?? $input['note'] ?? '');
            $now = date('Y-m-d H:i:s');

            if ($isOtherUser) {
                $stmtTargetEmp = $db->prepare("SELECT id FROM employees WHERE user_id = ?");
                $stmtTargetEmp->execute([$userId]);
                $empId = $stmtTargetEmp->fetchColumn() ?: null;
            } else {
                $currEmp = getCurrentEmployee();
                $empId = $currEmp ? intval($currEmp['id']) : null;
            }

            // 1. Close any currently open presence log for this user
            try {
                $stmtOpen = $db->prepare("SELECT id, started_at FROM presence_logs WHERE user_id = ? AND ended_at IS NULL ORDER BY id DESC LIMIT 1");
                $stmtOpen->execute([$userId]);
                $openLog = $stmtOpen->fetch();
                if ($openLog) {
                    $startTs = strtotime($openLog['started_at']);
                    $duration = max(0, round((time() - $startTs) / 60));
                    $stmtClose = $db->prepare("UPDATE presence_logs SET ended_at = ?, duration_minutes = ? WHERE id = ?");
                    $stmtClose->execute([$now, $duration, $openLog['id']]);
                }
            } catch (Exception $eLogClose) {}

            // 2. Insert new presence log
            try {
                $stmtInsLog = $db->prepare("
                    INSERT INTO presence_logs (user_id, employee_id, status, status_note, started_at)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtInsLog->execute([$userId, $empId, $rawStatus, $note, $now]);
            } catch (Exception $eLogIns) {}

            // 3. Update users table
            $db->prepare("UPDATE users SET work_status = ?, status_note = ?, status_updated_at = ? WHERE id = ?")
               ->execute([$rawStatus, $note, $now, $userId]);

            // 4. Update employees table if linked
            if ($empId) {
                $db->prepare("UPDATE employees SET work_status = ?, status_note = ?, status_updated_at = ? WHERE id = ?")
                   ->execute([$rawStatus, $note, $now, $empId]);
            }

            // Update session cache if self
            if (!$isOtherUser) {
                $_SESSION['user']['work_status'] = $rawStatus;
                $_SESSION['user']['status_note'] = $note;
                $_SESSION['user']['status_updated_at'] = $now;
            }

            $actionName = ($rawStatus === 'IN_OFFICE') ? 'RETURN_TO_DESK' : 'STEP_AWAY';
            logActivity($userId, 'hr-management', $actionName, 'users', $userId, "User {$currUser['full_name']} marked status: {$statusMeta['label']}" . ($note ? " ({$note})" : ""));

            echo json_encode([
                'success' => true,
                'status' => $rawStatus,
                'status_label' => $statusMeta['label'],
                'status_short' => $statusMeta['short'],
                'status_emoji' => $statusMeta['emoji'],
                'is_away' => $statusMeta['is_away'],
                'status_note' => $note,
                'status_updated_at' => $now,
                'minutes_elapsed' => 0,
                'message' => ($rawStatus === 'IN_OFFICE') ? 'Welcome back! Marked as In Office / At Desk.' : "Status updated to: {$statusMeta['label']}"
            ]);
            break;

        case 'get_my_presence':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = intval($currUser['id'] ?? 0);

            $stmt = $db->prepare("SELECT work_status, status_note, status_updated_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $uRow = $stmt->fetch();

            $status = strtoupper($uRow['work_status'] ?? 'IN_OFFICE');
            $validStatuses = [
                'IN_OFFICE' => ['label' => 'In Office / At Desk', 'short' => 'At Desk', 'emoji' => '🟢', 'is_away' => false],
                'TEA_BREAK' => ['label' => 'Tea Break', 'short' => 'Tea Break', 'emoji' => '☕', 'is_away' => true],
                'AFK' => ['label' => 'Away From Keyboard', 'short' => 'AFK', 'emoji' => '⏸️', 'is_away' => true],
                'MEETING' => ['label' => 'In a Meeting', 'short' => 'Meeting', 'emoji' => '👥', 'is_away' => true],
                'LUNCH' => ['label' => 'Lunch Break', 'short' => 'Lunch', 'emoji' => '🍽️', 'is_away' => true],
                'OUT_OF_OFFICE' => ['label' => 'Out of Office', 'short' => 'Out of Office', 'emoji' => '🚪', 'is_away' => true]
            ];
            $meta = $validStatuses[$status] ?? $validStatuses['IN_OFFICE'];

            $updatedAt = $uRow['status_updated_at'] ?? date('Y-m-d H:i:s');
            $mins = max(0, round((time() - strtotime($updatedAt)) / 60));

            echo json_encode([
                'success' => true,
                'status' => $status,
                'status_label' => $meta['label'],
                'status_short' => $meta['short'],
                'status_emoji' => $meta['emoji'],
                'is_away' => $meta['is_away'],
                'status_note' => $uRow['status_note'] ?? '',
                'status_updated_at' => $updatedAt,
                'minutes_elapsed' => $mins
            ]);
            break;

        case 'get_team_presence':
            checkAuth();
            $currUser = getCurrentUser();
            if ($currUser['role'] === 'CLIENT' || ($currUser['role_id'] ?? 0) == 7) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $deptFilter = trim($_GET['department'] ?? '');
            $statusFilter = strtoupper(trim($_GET['status'] ?? ''));

            $where = ["u.is_active = 1", "(u.role_id != 7 OR u.role_id IS NULL)"];
            $params = [];

            if (!empty($deptFilter)) {
                $where[] = "u.department = ?";
                $params[] = $deptFilter;
            }

            if (!empty($statusFilter)) {
                if ($statusFilter === 'AWAY') {
                    $where[] = "u.work_status IN ('TEA_BREAK', 'AFK', 'MEETING', 'LUNCH', 'OUT_OF_OFFICE')";
                } elseif ($statusFilter === 'IN_OFFICE') {
                    $where[] = "(u.work_status = 'IN_OFFICE' OR u.work_status IS NULL OR u.work_status = '')";
                } else {
                    $where[] = "u.work_status = ?";
                    $params[] = $statusFilter;
                }
            }

            $sql = "
                SELECT u.id as user_id, u.full_name, u.username, u.email, u.department,
                       COALESCE(u.work_status, 'IN_OFFICE') as work_status,
                       u.status_note, u.status_updated_at,
                       e.id as employee_id, e.employee_code, e.avatar_url,
                       des.title as designation_title,
                       r.name as role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                LEFT JOIN employees e ON e.user_id = u.id
                LEFT JOIN designations des ON e.designation_id = des.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY 
                  CASE 
                    WHEN u.work_status = 'IN_OFFICE' OR u.work_status IS NULL THEN 1
                    WHEN u.work_status = 'MEETING' THEN 2
                    WHEN u.work_status = 'TEA_BREAK' THEN 3
                    WHEN u.work_status = 'AFK' THEN 4
                    WHEN u.work_status = 'LUNCH' THEN 5
                    ELSE 6 
                  END ASC,
                  u.full_name ASC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $allStaff = $stmt->fetchAll();

            $statusMetaMap = [
                'IN_OFFICE' => ['label' => 'In Office / At Desk', 'short' => 'At Desk', 'emoji' => '🟢', 'color' => '#10b981', 'badge' => 'badge-success', 'is_away' => false],
                'TEA_BREAK' => ['label' => 'Tea Break', 'short' => 'Tea Break', 'emoji' => '☕', 'color' => '#f59e0b', 'badge' => 'badge-warning', 'is_away' => true],
                'AFK' => ['label' => 'Away From Keyboard', 'short' => 'AFK', 'emoji' => '⏸️', 'color' => '#f97316', 'badge' => 'badge-warning', 'is_away' => true],
                'MEETING' => ['label' => 'In a Meeting', 'short' => 'Meeting', 'emoji' => '👥', 'color' => '#8b5cf6', 'badge' => 'badge-purple', 'is_away' => true],
                'LUNCH' => ['label' => 'Lunch Break', 'short' => 'Lunch', 'emoji' => '🍽️', 'color' => '#ec4899', 'badge' => 'badge-pink', 'is_away' => true],
                'OUT_OF_OFFICE' => ['label' => 'Out of Office', 'short' => 'Out of Office', 'emoji' => '🚪', 'color' => '#64748b', 'badge' => 'badge-secondary', 'is_away' => true]
            ];

            $inOfficeCount = 0;
            $teaBreakCount = 0;
            $afkCount = 0;
            $meetingCount = 0;
            $lunchCount = 0;
            $totalAwayCount = 0;

            $formattedStaff = [];
            $nowTs = time();

            foreach ($allStaff as $st) {
                $statusKey = strtoupper($st['work_status'] ?: 'IN_OFFICE');
                if (!isset($statusMetaMap[$statusKey])) {
                    $statusKey = 'IN_OFFICE';
                }
                $meta = $statusMetaMap[$statusKey];

                if ($meta['is_away']) {
                    $totalAwayCount++;
                    if ($statusKey === 'TEA_BREAK') $teaBreakCount++;
                    elseif ($statusKey === 'AFK') $afkCount++;
                    elseif ($statusKey === 'MEETING') $meetingCount++;
                    elseif ($statusKey === 'LUNCH') $lunchCount++;
                } else {
                    $inOfficeCount++;
                }

                $updatedAt = $st['status_updated_at'] ?: date('Y-m-d H:i:s');
                $elapsedMins = max(0, round(($nowTs - strtotime($updatedAt)) / 60));

                $timeFormatted = '';
                if ($elapsedMins < 1) {
                    $timeFormatted = 'Just now';
                } elseif ($elapsedMins < 60) {
                    $timeFormatted = "{$elapsedMins}m ago";
                } else {
                    $hrs = floor($elapsedMins / 60);
                    $remM = $elapsedMins % 60;
                    $timeFormatted = "{$hrs}h {$remM}m ago";
                }

                $formattedStaff[] = [
                    'user_id' => intval($st['user_id']),
                    'employee_id' => $st['employee_id'] ? intval($st['employee_id']) : null,
                    'full_name' => $st['full_name'],
                    'employee_code' => $st['employee_code'] ?: 'USR-' . $st['user_id'],
                    'department' => $st['department'] ?: 'General',
                    'designation' => $st['designation_title'] ?: ($st['role_name'] ?: 'Staff'),
                    'work_status' => $statusKey,
                    'status_label' => $meta['label'],
                    'status_short' => $meta['short'],
                    'status_emoji' => $meta['emoji'],
                    'status_color' => $meta['color'],
                    'status_badge' => $meta['badge'],
                    'is_away' => $meta['is_away'],
                    'status_note' => $st['status_note'] ?: '',
                    'status_updated_at' => $updatedAt,
                    'minutes_elapsed' => $elapsedMins,
                    'time_formatted' => $timeFormatted,
                    'started_time' => date('h:i A', strtotime($updatedAt))
                ];
            }

            echo json_encode([
                'success' => true,
                'summary' => [
                    'total_staff' => count($allStaff),
                    'in_office_count' => $inOfficeCount,
                    'total_away_count' => $totalAwayCount,
                    'tea_break_count' => $teaBreakCount,
                    'afk_count' => $afkCount,
                    'meeting_count' => $meetingCount,
                    'lunch_count' => $lunchCount
                ],
                'staff' => $formattedStaff
            ]);
            break;

        // =========================================================
        // EMPLOYEE CHAT & GROUP CHAT SYSTEM ENDPOINTS
        // =========================================================
        case 'chat_get_unread_summary':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = intval($currUser['id'] ?? 0);
            $privs = getUserChatPrivileges($userId);
            if (!$privs['can_chat']) {
                echo json_encode(['success' => true, 'can_chat' => false, 'can_create_group' => false, 'total_unread' => 0]);
                break;
            }
            $stmtU = $db->prepare("
                SELECT COUNT(cm.id)
                FROM chat_participants cp
                JOIN chat_messages cm ON cm.conversation_id = cp.conversation_id
                WHERE cp.user_id = ?
                  AND cm.sender_id != ?
                  AND (cp.last_read_at IS NULL OR cm.created_at > cp.last_read_at)
            ");
            $stmtU->execute([$userId, $userId]);
            $totalUnread = intval($stmtU->fetchColumn());
            echo json_encode([
                'success' => true,
                'can_chat' => true,
                'can_create_group' => $privs['can_create_group'],
                'total_unread' => $totalUnread
            ]);
            break;

        case 'chat_init_state':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = intval($currUser['id'] ?? 0);
            $privs = getUserChatPrivileges($userId);

            if (!$privs['can_chat']) {
                echo json_encode([
                    'success' => false,
                    'can_chat' => false,
                    'can_create_group' => false,
                    'message' => 'Chat privilege is disabled for your account.'
                ]);
                break;
            }

            $emojiMap = [
                'IN_OFFICE' => '🟢',
                'TEA_BREAK' => '☕',
                'AFK' => '⏸️',
                'MEETING' => '👥',
                'LUNCH' => '🍽️',
                'OUT_OF_OFFICE' => '🚪'
            ];

            // 1. Fetch active internal employees (excluding self and clients)
            $stmtEmps = $db->prepare("
                SELECT u.id, u.full_name, u.username, u.department, COALESCE(u.work_status, 'IN_OFFICE') as work_status,
                       r.name as role_name, COALESCE(u.can_chat, 1) as can_chat
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.is_active = 1 AND u.role_id != 7 AND u.id != ?
                ORDER BY u.full_name ASC
            ");
            $stmtEmps->execute([$userId]);
            $employees = $stmtEmps->fetchAll();
            foreach ($employees as &$emp) {
                $ws = strtoupper($emp['work_status'] ?: 'IN_OFFICE');
                $emp['status_emoji'] = $emojiMap[$ws] ?? '🟢';
                $emp['work_status'] = $ws;
            }
            unset($emp);

            // 2. Fetch user's conversations
            $stmtConvs = $db->prepare("
                SELECT c.*, cp.last_read_at, cp.role as my_group_role,
                       (SELECT COUNT(*) FROM chat_participants cp2 WHERE cp2.conversation_id = c.id) as member_count,
                       (SELECT COUNT(*) FROM chat_messages cm WHERE cm.conversation_id = c.id AND cm.sender_id != ? AND (cp.last_read_at IS NULL OR cm.created_at > cp.last_read_at)) as unread_count,
                       (SELECT cm2.message FROM chat_messages cm2 WHERE cm2.conversation_id = c.id ORDER BY cm2.id DESC LIMIT 1) as last_message,
                       (SELECT u_last.full_name FROM chat_messages cm3 JOIN users u_last ON cm3.sender_id = u_last.id WHERE cm3.conversation_id = c.id ORDER BY cm3.id DESC LIMIT 1) as last_sender_name
                FROM chat_conversations c
                JOIN chat_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
                ORDER BY c.last_message_at DESC, c.id DESC
            ");
            $stmtConvs->execute([$userId, $userId]);
            $convs = $stmtConvs->fetchAll();

            $totalUnread = 0;
            $stmtPeer = $db->prepare("
                SELECT u.id, u.full_name, u.department, r.name as role_name, COALESCE(u.work_status, 'IN_OFFICE') as work_status
                FROM chat_participants cp
                JOIN users u ON cp.user_id = u.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE cp.conversation_id = ? AND cp.user_id != ?
                LIMIT 1
            ");

            foreach ($convs as &$c) {
                $c['id'] = intval($c['id']);
                $c['unread_count'] = intval($c['unread_count']);
                $totalUnread += $c['unread_count'];

                if ($c['type'] === 'direct') {
                    $stmtPeer->execute([$c['id'], $userId]);
                    $peer = $stmtPeer->fetch();
                    if ($peer) {
                        $ws = strtoupper($peer['work_status'] ?: 'IN_OFFICE');
                        $c['display_title'] = $peer['full_name'];
                        $c['display_subtitle'] = ($peer['role_name'] ?: 'Staff') . ' • ' . ($peer['department'] ?: 'General');
                        $c['peer_user_id'] = intval($peer['id']);
                        $c['peer_work_status'] = $ws;
                        $c['peer_status_emoji'] = $emojiMap[$ws] ?? '🟢';
                    } else {
                        $c['display_title'] = 'Direct Chat';
                        $c['display_subtitle'] = '';
                        $c['peer_status_emoji'] = '💬';
                    }
                } else {
                    $c['display_title'] = $c['title'] ?: 'Group Chat';
                    $c['display_subtitle'] = intval($c['member_count']) . ' members' . ($c['description'] ? ' • ' . $c['description'] : '');
                    $c['peer_status_emoji'] = '👥';
                }
            }
            unset($c);

            echo json_encode([
                'success' => true,
                'can_chat' => true,
                'can_create_group' => $privs['can_create_group'],
                'total_unread' => $totalUnread,
                'employees' => $employees,
                'conversations' => $convs
            ]);
            break;

        case 'chat_open_direct':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = intval($currUser['id'] ?? 0);
            $privs = getUserChatPrivileges($userId);

            if (!$privs['can_chat']) {
                echo json_encode(['success' => false, 'message' => 'Chat privilege is not enabled for your account.']);
                exit;
            }

            $targetUserId = intval($input['target_user_id'] ?? $_GET['target_user_id'] ?? 0);
            if ($targetUserId <= 0 || $targetUserId === $userId) {
                echo json_encode(['success' => false, 'message' => 'Valid target employee required.']);
                exit;
            }

            // Ensure target exists and is an internal employee
            $stmtTarget = $db->prepare("SELECT id, full_name, role_id, is_active FROM users WHERE id = ?");
            $stmtTarget->execute([$targetUserId]);
            $target = $stmtTarget->fetch();
            if (!$target || !$target['is_active'] || intval($target['role_id']) === 7) {
                echo json_encode(['success' => false, 'message' => 'Selected employee is unavailable for internal chat.']);
                exit;
            }

            // Look for existing direct conversation between these two exact users
            $stmtFind = $db->prepare("
                SELECT c.id
                FROM chat_conversations c
                JOIN chat_participants p1 ON p1.conversation_id = c.id AND p1.user_id = ?
                JOIN chat_participants p2 ON p2.conversation_id = c.id AND p2.user_id = ?
                WHERE c.type = 'direct'
                LIMIT 1
            ");
            $stmtFind->execute([$userId, $targetUserId]);
            $convId = intval($stmtFind->fetchColumn());

            if ($convId <= 0) {
                $now = date('Y-m-d H:i:s');
                $stmtNew = $db->prepare("INSERT INTO chat_conversations (type, created_by, last_message_at, created_at) VALUES ('direct', ?, ?, ?)");
                $stmtNew->execute([$userId, $now, $now]);
                $convId = intval($db->lastInsertId());

                $stmtPart = $db->prepare("INSERT INTO chat_participants (conversation_id, user_id, role, last_read_at) VALUES (?, ?, 'member', ?)");
                $stmtPart->execute([$convId, $userId, $now]);
                $stmtPart->execute([$convId, $targetUserId, null]);
            }

            echo json_encode(['success' => true, 'conversation_id' => $convId]);
            break;

        case 'chat_create_group':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = intval($currUser['id'] ?? 0);
            $privs = getUserChatPrivileges($userId);

            if (!$privs['can_chat'] || !$privs['can_create_group']) {
                echo json_encode(['success' => false, 'message' => 'Privilege Denied: You do not have permission to create chat groups.']);
                exit;
            }

            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $memberIds = $input['member_ids'] ?? [];
            if (!is_array($memberIds)) $memberIds = [];
            $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds), function($id) use ($userId) {
                return $id > 0 && $id !== $userId;
            })));

            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Group Name is required.']);
                exit;
            }
            if (empty($memberIds)) {
                echo json_encode(['success' => false, 'message' => 'Please select at least 1 employee to add to the group.']);
                exit;
            }

            $now = date('Y-m-d H:i:s');
            $stmtGrp = $db->prepare("INSERT INTO chat_conversations (type, title, description, created_by, last_message_at, created_at) VALUES ('group', ?, ?, ?, ?, ?)");
            $stmtGrp->execute([$title, $description, $userId, $now, $now]);
            $convId = intval($db->lastInsertId());

            $insIgn = (defined('DB_TYPE') && DB_TYPE === 'mysql') ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
            $stmtPart = $db->prepare("{$insIgn} INTO chat_participants (conversation_id, user_id, role, last_read_at) VALUES (?, ?, ?, ?)");
            $stmtPart->execute([$convId, $userId, 'admin', $now]);

            // Validate internal active members
            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
            $stmtValid = $db->prepare("SELECT id FROM users WHERE id IN ($placeholders) AND is_active = 1 AND role_id != 7");
            $stmtValid->execute($memberIds);
            $validIds = $stmtValid->fetchAll(PDO::FETCH_COLUMN);
            foreach ($validIds as $mId) {
                $stmtPart->execute([$convId, intval($mId), 'member', null]);
            }

            $totalMembers = count($validIds) + 1;
            $sysMsg = "{$currUser['full_name']} created group \"{$title}\" ({$totalMembers} members)";
            $db->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type, created_at) VALUES (?, ?, ?, 'system', ?)")
               ->execute([$convId, $userId, $sysMsg, $now]);

            logActivity($userId, 'hr-management', 'CHAT_CREATE_GROUP', 'chat_conversations', $convId, $sysMsg);

            echo json_encode([
                'success' => true,
                'conversation_id' => $convId,
                'title' => $title,
                'message' => "Group '{$title}' created successfully!"
            ]);
            break;

        case 'chat_get_messages':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = intval($currUser['id'] ?? 0);
            $privs = getUserChatPrivileges($userId);

            if (!$privs['can_chat']) {
                echo json_encode(['success' => false, 'message' => 'Chat privilege is disabled.']);
                exit;
            }

            $convId = intval($_GET['conversation_id'] ?? 0);
            if ($convId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Conversation ID required.']);
                exit;
            }

            // Verify participation
            $stmtCheckPart = $db->prepare("SELECT role FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
            $stmtCheckPart->execute([$convId, $userId]);
            $myPart = $stmtCheckPart->fetch();
            if (!$myPart) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: You are not a member of this conversation.']);
                exit;
            }

            $stmtConv = $db->prepare("SELECT * FROM chat_conversations WHERE id = ?");
            $stmtConv->execute([$convId]);
            $conv = $stmtConv->fetch();

            // Mark as read now
            $now = date('Y-m-d H:i:s');
            $db->prepare("UPDATE chat_participants SET last_read_at = ? WHERE conversation_id = ? AND user_id = ?")
               ->execute([$now, $convId, $userId]);

            // Fetch participants
            $stmtParts = $db->prepare("
                SELECT u.id, u.full_name, u.department, COALESCE(u.work_status, 'IN_OFFICE') as work_status,
                       r.name as role_name, cp.role as group_role
                FROM chat_participants cp
                JOIN users u ON cp.user_id = u.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE cp.conversation_id = ?
                ORDER BY cp.role ASC, u.full_name ASC
            ");
            $stmtParts->execute([$convId]);
            $participants = $stmtParts->fetchAll();

            // Fetch messages
            $stmtMsgs = $db->prepare("
                SELECT cm.*, u.full_name as sender_name, u.department as sender_department, r.name as sender_role
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE cm.conversation_id = ?
                ORDER BY cm.id DESC LIMIT 150
            ");
            $stmtMsgs->execute([$convId]);
            $messages = array_reverse($stmtMsgs->fetchAll());

            echo json_encode([
                'success' => true,
                'conversation' => $conv,
                'my_group_role' => $myPart['role'],
                'participants' => $participants,
                'messages' => $messages,
                'can_create_group' => $privs['can_create_group']
            ]);
            break;

        case 'chat_send_message':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = intval($currUser['id'] ?? 0);
            $privs = getUserChatPrivileges($userId);

            if (!$privs['can_chat']) {
                echo json_encode(['success' => false, 'message' => 'Chat privilege is disabled for your account.']);
                exit;
            }

            $convId = intval($input['conversation_id'] ?? 0);
            $msgText = trim($input['message'] ?? '');

            if ($convId <= 0 || $msgText === '') {
                echo json_encode(['success' => false, 'message' => 'Conversation ID and message text are required.']);
                exit;
            }

            $stmtCheckPart = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
            $stmtCheckPart->execute([$convId, $userId]);
            if (!$stmtCheckPart->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'You are not a participant of this chat.']);
                exit;
            }

            $now = date('Y-m-d H:i:s');
            $stmtIns = $db->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type, created_at) VALUES (?, ?, ?, 'text', ?)");
            $stmtIns->execute([$convId, $userId, $msgText, $now]);
            $msgId = intval($db->lastInsertId());

            $db->prepare("UPDATE chat_conversations SET last_message_at = ? WHERE id = ?")->execute([$now, $convId]);
            $db->prepare("UPDATE chat_participants SET last_read_at = ? WHERE conversation_id = ? AND user_id = ?")->execute([$now, $convId, $userId]);

            echo json_encode([
                'success' => true,
                'message_id' => $msgId,
                'created_at' => $now
            ]);
            break;

        case 'chat_add_group_member':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = intval($currUser['id'] ?? 0);
            $privs = getUserChatPrivileges($userId);

            if (!$privs['can_chat']) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $convId = intval($input['conversation_id'] ?? 0);
            $targetId = intval($input['user_id'] ?? 0);
            if ($convId <= 0 || $targetId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Conversation and User ID are required.']);
                exit;
            }

            $stmtConv = $db->prepare("SELECT type FROM chat_conversations WHERE id = ?");
            $stmtConv->execute([$convId]);
            if ($stmtConv->fetchColumn() !== 'group') {
                echo json_encode(['success' => false, 'message' => 'Members can only be added to group chats.']);
                exit;
            }

            // Verify caller is a group participant & has privilege to manage group
            $stmtMe = $db->prepare("SELECT role FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
            $stmtMe->execute([$convId, $userId]);
            $myRole = $stmtMe->fetchColumn();
            if (!$myRole) {
                echo json_encode(['success' => false, 'message' => 'Access Denied.']);
                exit;
            }
            if ($myRole !== 'admin' && !$privs['can_create_group'] && !isLeadOrAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Only Group Admins or users with Create Group privilege can add members.']);
                exit;
            }

            $stmtT = $db->prepare("SELECT full_name, role_id, is_active FROM users WHERE id = ?");
            $stmtT->execute([$targetId]);
            $tUser = $stmtT->fetch();
            if (!$tUser || !$tUser['is_active'] || intval($tUser['role_id']) === 7) {
                echo json_encode(['success' => false, 'message' => 'Employee not found.']);
                exit;
            }

            $now = date('Y-m-d H:i:s');
            $insIgn = (defined('DB_TYPE') && DB_TYPE === 'mysql') ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
            $stmtAdd = $db->prepare("{$insIgn} INTO chat_participants (conversation_id, user_id, role, last_read_at) VALUES (?, ?, 'member', NULL)");
            $stmtAdd->execute([$convId, $targetId]);
            if ($stmtAdd->rowCount() > 0) {
                $db->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type, created_at) VALUES (?, ?, ?, 'system', ?)")
                   ->execute([$convId, $userId, "{$currUser['full_name']} added {$tUser['full_name']} to the group", $now]);
                $db->prepare("UPDATE chat_conversations SET last_message_at = ? WHERE id = ?")->execute([$now, $convId]);
            }

            echo json_encode(['success' => true, 'message' => "{$tUser['full_name']} added to group."]);
            break;

        case 'chat_leave_group':
            checkAuth();
            $currUser = getCurrentUser();
            $userId = intval($currUser['id'] ?? 0);
            $convId = intval($input['conversation_id'] ?? 0);

            $stmtConv = $db->prepare("SELECT type FROM chat_conversations WHERE id = ?");
            $stmtConv->execute([$convId]);
            if ($stmtConv->fetchColumn() !== 'group') {
                echo json_encode(['success' => false, 'message' => 'You can only leave group conversations.']);
                exit;
            }

            $now = date('Y-m-d H:i:s');
            $db->prepare("DELETE FROM chat_participants WHERE conversation_id = ? AND user_id = ?")->execute([$convId, $userId]);
            $db->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, message_type, created_at) VALUES (?, ?, ?, 'system', ?)")
               ->execute([$convId, $userId, "{$currUser['full_name']} left the group", $now]);
            $db->prepare("UPDATE chat_conversations SET last_message_at = ? WHERE id = ?")->execute([$now, $convId]);

            echo json_encode(['success' => true, 'message' => 'You left the group.']);
            break;

        case 'upload_task_media':
            checkAuth('task-management');
            $user = getCurrentUser();

            $taskId = intval($_POST['task_id'] ?? ($input['task_id'] ?? 0));
            $caption = trim($_POST['caption'] ?? ($input['caption'] ?? ''));
            $attachmentStage = trim($_POST['attachment_stage'] ?? ($input['attachment_stage'] ?? 'work'));
            if (!in_array($attachmentStage, ['work', 'submission', 'review', 'general'], true)) {
                $attachmentStage = 'work';
            }

            if ($taskId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Task ID is required.']);
                exit;
            }

            $stmtT = $db->prepare("SELECT * FROM tasks WHERE id = ? AND is_deleted = 0");
            $stmtT->execute([$taskId]);
            $task = $stmtT->fetch();
            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            if (!canManageProject($db, $task['project_id'], $user['id']) && !isTaskVisibleToUser($task, $user['id'], $db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You can only upload media to your own or managed tasks.']);
                exit;
            }

            // Collect files from $_FILES['file'] or $_FILES['files']
            $filesToProcess = [];
            foreach (['files', 'file'] as $field) {
                if (empty($_FILES[$field])) continue;
                $f = $_FILES[$field];
                if (is_array($f['name'])) {
                    $cnt = count($f['name']);
                    for ($i = 0; $i < $cnt; $i++) {
                        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                        $filesToProcess[] = [
                            'name' => $f['name'][$i],
                            'tmp_name' => $f['tmp_name'][$i],
                            'type' => $f['type'][$i] ?? '',
                            'size' => intval($f['size'][$i] ?? 0),
                            'error' => intval($f['error'][$i] ?? 0)
                        ];
                    }
                } elseif (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $filesToProcess[] = [
                        'name' => $f['name'],
                        'tmp_name' => $f['tmp_name'],
                        'type' => $f['type'] ?? '',
                        'size' => intval($f['size'] ?? 0),
                        'error' => intval($f['error'] ?? 0)
                    ];
                }
            }

            if (empty($filesToProcess)) {
                echo json_encode(['success' => false, 'message' => 'No media file received. Please select a file to upload.']);
                exit;
            }

            $uploadDir = __DIR__ . '/uploads/tasks';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'mp4', 'mov', 'webm', 'mp3'];
            $maxBytes = 25 * 1024 * 1024;
            $savedAttachments = [];
            $errors = [];

            $stmtInsAtt = $db->prepare("INSERT INTO task_attachments (task_id, user_id, uploaded_by, file_name, file_url, file_path, mime_type, file_size, caption, attachment_stage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($filesToProcess as $fileItem) {
                if ($fileItem['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "{$fileItem['name']}: Upload error code {$fileItem['error']}";
                    continue;
                }
                if ($fileItem['size'] > $maxBytes) {
                    $errors[] = "{$fileItem['name']}: Exceeds 25MB size limit";
                    continue;
                }
                $origName = basename($fileItem['name']);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts, true)) {
                    $errors[] = "{$origName}: Unsupported file type (.{$ext})";
                    continue;
                }

                $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $origName);
                $storedName = 'tsk_' . $taskId . '_' . time() . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 6) . '_' . $safeBase;
                $destAbs = $uploadDir . '/' . $storedName;
                $destRel = 'uploads/tasks/' . $storedName;

                if (!move_uploaded_file($fileItem['tmp_name'], $destAbs)) {
                    $errors[] = "{$origName}: Failed to store uploaded file";
                    continue;
                }

                $mimeType = trim($fileItem['type'] ?? '');
                if ($mimeType === '' && function_exists('mime_content_type')) {
                    $mimeType = @mime_content_type($destAbs) ?: 'application/octet-stream';
                }

                $stmtInsAtt->execute([
                    $taskId,
                    $user['id'],
                    $user['id'],
                    $origName,
                    $destRel,
                    $destRel,
                    $mimeType,
                    $fileItem['size'],
                    $caption,
                    $attachmentStage
                ]);
                $attId = intval($db->lastInsertId());
                $savedAttachments[] = [
                    'id' => $attId,
                    'task_id' => $taskId,
                    'user_id' => $user['id'],
                    'uploaded_by_name' => $user['full_name'] ?? $user['username'],
                    'file_name' => $origName,
                    'file_path' => $destRel,
                    'mime_type' => $mimeType,
                    'file_size' => $fileItem['size'],
                    'caption' => $caption,
                    'attachment_stage' => $attachmentStage,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            if (empty($savedAttachments)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => implode('; ', $errors) ?: 'Could not save attachment(s).']);
                exit;
            }

            $namesStr = implode(', ', array_column($savedAttachments, 'file_name'));
            $db->prepare("INSERT INTO task_activities (task_id, user_id, action, new_value, remarks) VALUES (?, ?, 'MEDIA_UPLOADED', ?, ?)")
               ->execute([$taskId, $user['id'], $namesStr, ($caption !== '' ? $caption : "Uploaded " . count($savedAttachments) . " media file(s)")]);
            logActivity($user['id'], 'task-management', 'UPLOAD_TASK_MEDIA', 'tasks', $taskId, "Uploaded media to task #{$task['task_code']}: {$namesStr}");

            echo json_encode([
                'success' => true,
                'attachments' => $savedAttachments,
                'errors' => $errors,
                'message' => count($savedAttachments) . ' media file(s) attached to task.'
            ]);
            break;

        case 'delete_task_media':
            checkAuth('task-management');
            $user = getCurrentUser();
            $attId = intval($input['attachment_id'] ?? ($_GET['attachment_id'] ?? 0));
            if ($attId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Attachment ID is required.']);
                exit;
            }
            $stAtt = $db->prepare("SELECT ta.*, t.project_id FROM task_attachments ta JOIN tasks t ON ta.task_id = t.id WHERE ta.id = ?");
            $stAtt->execute([$attId]);
            $att = $stAtt->fetch();
            if (!$att) {
                echo json_encode(['success' => false, 'message' => 'Attachment not found.']);
                exit;
            }
            if (intval($att['user_id'] ?? ($att['uploaded_by'] ?? 0)) !== intval($user['id']) && !canManageProject($db, $att['project_id'], $user['id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You cannot delete this attachment.']);
                exit;
            }
            $relPath = !empty($att['file_path']) ? $att['file_path'] : ($att['file_url'] ?? '');
            $absPath = $relPath !== '' ? (__DIR__ . '/' . ltrim($relPath, '/\\')) : '';
            if ($absPath !== '' && is_file($absPath)) {
                @unlink($absPath);
            }
            $db->prepare("DELETE FROM task_attachments WHERE id = ?")->execute([$attId]);
            echo json_encode(['success' => true, 'message' => 'Attachment removed.']);
            break;

        case 'superadmin_task_project_analytics':
            checkAuth('reports');
            $user = getCurrentUser();
            if (($user['role'] ?? '') !== 'Super Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admin can view Super Admin Task & Project analytics.']);
                exit;
            }

            $mode = strtolower(trim($_GET['mode'] ?? ($input['mode'] ?? 'month')));
            if (!in_array($mode, ['day', 'week', 'month', 'range'], true)) {
                $mode = 'month';
            }

            $dateParam = trim($_GET['date'] ?? ($input['date'] ?? date('Y-m-d')));
            $monthParam = trim($_GET['month'] ?? ($input['month'] ?? date('Y-m')));
            $weekParam = trim($_GET['week'] ?? ($input['week'] ?? date('Y-\WW')));
            $startParam = trim($_GET['start_date'] ?? ($input['start_date'] ?? date('Y-m-01')));
            $endParam = trim($_GET['end_date'] ?? ($input['end_date'] ?? date('Y-m-d')));
            $deptFilter = trim($_GET['department'] ?? ($input['department'] ?? ''));
            $empFilter = intval($_GET['employee_id'] ?? ($input['employee_id'] ?? 0));

            if ($mode === 'day') {
                $dTs = strtotime($dateParam) ?: time();
                $startDate = date('Y-m-d', $dTs);
                $endDate = $startDate;
            } elseif ($mode === 'week') {
                if (preg_match('/^(\d{4})-W(\d{1,2})$/', $weekParam, $wm)) {
                    $yr = intval($wm[1]);
                    $wk = intval($wm[2]);
                } else {
                    $yr = intval(date('Y'));
                    $wk = intval(date('W'));
                }
                $dto = new DateTime();
                $dto->setISODate($yr, $wk);
                $startDate = $dto->format('Y-m-d');
                $dto->modify('+6 days');
                $endDate = $dto->format('Y-m-d');
            } elseif ($mode === 'month') {
                $mTs = strtotime($monthParam . '-01') ?: time();
                $startDate = date('Y-m-01', $mTs);
                $endDate = date('Y-m-t', $mTs);
            } else {
                $sTs = strtotime($startParam) ?: strtotime(date('Y-m-01'));
                $eTs = strtotime($endParam) ?: time();
                if ($sTs > $eTs) {
                    $tmp = $sTs;
                    $sTs = $eTs;
                    $eTs = $tmp;
                }
                $startDate = date('Y-m-d', $sTs);
                $endDate = date('Y-m-d', $eTs);
            }
            $rangeStartTs = strtotime($startDate . ' 00:00:00');
            $rangeEndTs = strtotime($endDate . ' 23:59:59');

            // 1. Employee Roster for Productivity Tracking
            $staffMap = [];
            $stStaff = $db->query("
                SELECT u.id, u.full_name, u.department, COALESCE(r.name, 'Employee') as role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE COALESCE(u.is_active, 1) = 1 AND u.role_id != 7
                ORDER BY u.full_name ASC
            ");
            foreach ($stStaff->fetchAll() as $su) {
                $uid = intval($su['id']);
                if (!empty($deptFilter) && strcasecmp($su['department'] ?: 'General', $deptFilter) !== 0) continue;
                if ($empFilter > 0 && $uid !== $empFilter) continue;

                $staffMap[$uid] = [
                    'user_id' => $uid,
                    'full_name' => $su['full_name'],
                    'department' => $su['department'] ?: 'General',
                    'role_name' => $su['role_name'],
                    'assigned_count' => 0,
                    'completed_count' => 0,
                    'in_progress_count' => 0,
                    'review_count' => 0,
                    'main_tasks_completed' => 0,
                    'subtasks_completed' => 0,
                    'breached_count' => 0,
                    'tasks' => []
                ];
            }

            $relAssignMap = [];
            try {
                $stRel = $db->query("SELECT task_id, user_id FROM task_assignments WHERE role_type = 'assignee'");
                foreach ($stRel->fetchAll() as $ra) {
                    $relAssignMap[intval($ra['task_id'])][] = intval($ra['user_id']);
                }
            } catch (Exception $eRel) {}

            // 2. Fetch Tasks in period
            $sqlTasks = "
                SELECT t.id, t.task_code, t.title, t.task_type, t.parent_task_id, t.assigned_to, t.assignees_json,
                       t.completion_time, t.client_approved_at, t.updated_at, t.created_at, t.status, t.priority,
                       t.deadline, t.department, p.project_name, c.company_name, u.full_name as assigned_to_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN clients c ON t.client_id = c.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.is_deleted = 0
            ";
            if (!empty($deptFilter)) {
                $sqlTasks .= " AND t.department = " . $db->quote($deptFilter);
            }
            if ($empFilter > 0) {
                $sqlTasks .= " AND t.assigned_to = $empFilter";
            }
            $sqlTasks .= " ORDER BY COALESCE(t.completion_time, t.updated_at, t.created_at) DESC";

            $stAllTasks = $db->query($sqlTasks);
            $rawTasks = $stAllTasks->fetchAll();

            $totalTasksInPeriod = 0;
            $totalCompletedInRange = 0;
            $totalInProgressInRange = 0;
            $totalReviewInRange = 0;
            $totalRevisionInRange = 0;
            $totalPendingInRange = 0;
            $slaPassCount = 0;
            $slaBreachCount = 0;
            $dailyMap = [];
            $taskLedger = [];

            foreach ($rawTasks as $ct) {
                $cTs = strtotime($ct['created_at']);
                $compStr = !empty($ct['completion_time']) ? $ct['completion_time'] : (!empty($ct['client_approved_at']) ? $ct['client_approved_at'] : ($ct['updated_at'] ?: $ct['created_at']));
                $compTs = strtotime($compStr);

                $isCreatedInRange = ($cTs >= $rangeStartTs && $cTs <= $rangeEndTs);
                $isCompletedInRange = (strtoupper($ct['status']) === 'COMPLETED' && $compTs >= $rangeStartTs && $compTs <= $rangeEndTs);
                $isRelevant = ($isCreatedInRange || $isCompletedInRange || (strtoupper($ct['status']) !== 'COMPLETED' && $cTs <= $rangeEndTs));

                if (!$isRelevant) continue;

                $totalTasksInPeriod++;
                $st = strtoupper($ct['status']);

                // SLA Calculation
                $isBreached = false;
                $slaStatusText = 'On Track';
                if (!empty($ct['deadline'])) {
                    $dlTs = strtotime(substr($ct['deadline'], 0, 10) . ' 23:59:59');
                    if ($dlTs) {
                        if ($st === 'COMPLETED') {
                            $isBreached = ($compTs > $dlTs);
                            $slaStatusText = $isBreached ? 'Completed (Overdue)' : 'Completed (On Time)';
                        } else {
                            $isBreached = (time() > $dlTs);
                            $slaStatusText = $isBreached ? 'Breached (Overdue)' : 'Active (On Track)';
                        }
                    }
                } elseif ($st === 'COMPLETED') {
                    $slaStatusText = 'Completed (On Time)';
                }

                if ($isBreached) $slaBreachCount++; else $slaPassCount++;

                if ($st === 'COMPLETED') {
                    $totalCompletedInRange++;
                } elseif ($st === 'IN_PROGRESS' || $st === 'IN PROGRESS') {
                    $totalInProgressInRange++;
                } elseif ($st === 'REVIEW' || $st === 'UNDER_REVIEW') {
                    $totalReviewInRange++;
                } elseif ($st === 'REVISION' || $st === 'CHANGES_REQUESTED') {
                    $totalRevisionInRange++;
                } else {
                    $totalPendingInRange++;
                }

                $bucketDay = date('Y-m-d', $isCompletedInRange ? $compTs : $cTs);
                if (!isset($dailyMap[$bucketDay])) {
                    $dailyMap[$bucketDay] = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'breached' => 0];
                }
                $dailyMap[$bucketDay]['total']++;
                if ($st === 'COMPLETED') $dailyMap[$bucketDay]['completed']++;
                if ($st === 'IN_PROGRESS' || $st === 'IN PROGRESS') $dailyMap[$bucketDay]['in_progress']++;
                if ($isBreached) $dailyMap[$bucketDay]['breached']++;

                $tid = intval($ct['id']);
                $isSub = ($ct['task_type'] === 'subtask' || intval($ct['parent_task_id'] ?? 0) > 0);
                $assigneeUids = [];
                if (intval($ct['assigned_to'] ?? 0) > 0) $assigneeUids[] = intval($ct['assigned_to']);
                $dec = json_decode($ct['assignees_json'] ?? '[]', true);
                if (is_array($dec)) {
                    foreach ($dec as $duid) $assigneeUids[] = intval($duid);
                }
                if (!empty($relAssignMap[$tid])) {
                    foreach ($relAssignMap[$tid] as $ruid) $assigneeUids[] = intval($ruid);
                }
                $assigneeUids = array_values(array_unique(array_filter($assigneeUids, function ($x) { return $x > 0; })));

                foreach ($assigneeUids as $auid) {
                    if (!isset($staffMap[$auid])) continue;
                    $staffMap[$auid]['assigned_count']++;
                    if ($st === 'COMPLETED') {
                        $staffMap[$auid]['completed_count']++;
                        if ($isSub) $staffMap[$auid]['subtasks_completed']++;
                        else $staffMap[$auid]['main_tasks_completed']++;
                    } elseif ($st === 'IN_PROGRESS' || $st === 'IN PROGRESS') {
                        $staffMap[$auid]['in_progress_count']++;
                    } elseif ($st === 'REVIEW' || $st === 'UNDER_REVIEW') {
                        $staffMap[$auid]['review_count']++;
                    }
                    if ($isBreached) {
                        $staffMap[$auid]['breached_count']++;
                    }
                    $staffMap[$auid]['tasks'][] = [
                        'id' => $tid,
                        'task_code' => $ct['task_code'],
                        'title' => $ct['title'],
                        'status' => $ct['status'],
                        'project_name' => $ct['project_name'] ?: '',
                        'completed_at' => ($st === 'COMPLETED') ? date('Y-m-d H:i', $compTs) : ''
                    ];
                }

                $taskLedger[] = [
                    'id' => $tid,
                    'task_code' => $ct['task_code'],
                    'title' => $ct['title'],
                    'project_name' => $ct['project_name'] ?: 'General',
                    'company_name' => $ct['company_name'] ?: 'Internal',
                    'assigned_to_name' => $ct['assigned_to_name'] ?: 'Unassigned',
                    'department' => $ct['department'] ?: 'General',
                    'status' => $ct['status'],
                    'priority' => $ct['priority'] ?: 'Normal',
                    'deadline' => !empty($ct['deadline']) ? substr($ct['deadline'], 0, 10) : '--',
                    'created_at' => substr($ct['created_at'], 0, 10),
                    'completed_at' => ($st === 'COMPLETED') ? date('Y-m-d', $compTs) : '--',
                    'sla_status' => $slaStatusText,
                    'is_breached' => $isBreached
                ];
            }

            $employeeCompletions = array_values($staffMap);
            usort($employeeCompletions, function ($a, $b) {
                if ($b['completed_count'] !== $a['completed_count']) {
                    return $b['completed_count'] - $a['completed_count'];
                }
                return strcmp($a['full_name'], $b['full_name']);
            });

            ksort($dailyMap);
            $dailyBreakdown = [];
            foreach ($dailyMap as $dKey => $dStats) {
                $dailyBreakdown[] = [
                    'date' => $dKey,
                    'total' => $dStats['total'],
                    'completed_count' => $dStats['completed'],
                    'in_progress' => $dStats['in_progress'],
                    'breached' => $dStats['breached']
                ];
            }

            // 3. Project Completion Time Tracking
            $stProjs = $db->query("
                SELECT p.*, c.company_name, u_tl.full_name as team_lead_name,
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted = 0) as total_tasks,
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted = 0 AND t.status = 'COMPLETED') as completed_tasks,
                       (SELECT MAX(COALESCE(t.completion_time, t.updated_at)) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted = 0 AND t.status = 'COMPLETED') as last_task_completed_at
                FROM projects p
                LEFT JOIN clients c ON p.client_id = c.id
                LEFT JOIN users u_tl ON p.team_lead_id = u_tl.id
                ORDER BY p.id DESC
            ");

            $projectDurations = [];
            $completedProjectsCount = 0;
            $completedDurationSumSec = 0;
            $onTimeCount = 0;
            $overdueCount = 0;
            $nowTs = time();

            foreach ($stProjs->fetchAll() as $p) {
                $isCompleted = (strtolower(trim($p['status'] ?? '')) === 'completed') || (intval($p['client_approved'] ?? 0) === 1);
                $startRaw = !empty($p['start_date']) ? $p['start_date'] : ($p['created_at'] ?? date('Y-m-d H:i:s'));
                $startTs = strtotime($startRaw) ?: $nowTs;

                if ($isCompleted) {
                    $endRaw = !empty($p['client_approved_at']) ? $p['client_approved_at'] : (!empty($p['last_task_completed_at']) ? $p['last_task_completed_at'] : ($p['updated_at'] ?? date('Y-m-d H:i:s')));
                    $endTs = strtotime($endRaw) ?: $nowTs;
                    if ($endTs < $startTs) $endTs = $startTs;
                    $completedProjectsCount++;
                    $completedDurationSumSec += ($endTs - $startTs);
                } else {
                    $endRaw = null;
                    $endTs = max($startTs, $nowTs);
                }

                $durSec = max(0, $endTs - $startTs);
                $durHours = round($durSec / 3600, 1);
                $durDays = round($durSec / 86400, 1);
                $daysWhole = intval(floor($durSec / 86400));
                $remHours = intval(floor(($durSec % 86400) / 3600));
                $durLabel = $daysWhole > 0 ? "{$daysWhole}d {$remHours}h" : ($durHours > 0 ? "{$durHours}h" : "< 1h");

                $slaState = $isCompleted ? 'Completed' : 'In Progress';
                if (!empty($p['deadline'])) {
                    $dlTs = strtotime(substr($p['deadline'], 0, 10) . ' 23:59:59');
                    if ($dlTs) {
                        if ($isCompleted) {
                            $slaState = ($endTs <= $dlTs) ? 'On Time' : 'Overdue';
                            if ($endTs <= $dlTs) $onTimeCount++; else $overdueCount++;
                        } else {
                            $slaState = ($nowTs <= $dlTs) ? 'Active (On Track)' : 'Active (Overdue)';
                        }
                    }
                } elseif ($isCompleted) {
                    $slaState = 'On Time';
                    $onTimeCount++;
                }

                $projectDurations[] = [
                    'project_id' => intval($p['id']),
                    'project_name' => $p['project_name'],
                    'company_name' => $p['company_name'] ?: 'N/A',
                    'team_lead_name' => $p['team_lead_name'] ?: 'Unassigned',
                    'status' => $p['status'],
                    'is_completed' => $isCompleted,
                    'start_date' => substr($startRaw, 0, 10),
                    'deadline' => !empty($p['deadline']) ? substr($p['deadline'], 0, 10) : '--',
                    'completed_at' => $endRaw ? date('Y-m-d H:i', $endTs) : 'Active',
                    'duration_hours' => $durHours,
                    'duration_days' => $durDays,
                    'duration_label' => $durLabel,
                    'total_tasks' => intval($p['total_tasks']),
                    'completed_tasks' => intval($p['completed_tasks']),
                    'sla_status' => $slaState
                ];
            }

            $avgHours = $completedProjectsCount > 0 ? round(($completedDurationSumSec / $completedProjectsCount) / 3600, 1) : 0;
            $avgDays = $completedProjectsCount > 0 ? round(($completedDurationSumSec / $completedProjectsCount) / 86400, 1) : 0;
            $clearanceRate = $totalTasksInPeriod > 0 ? round(($totalCompletedInRange / $totalTasksInPeriod) * 100, 1) : 0;
            $slaPassRate = $totalTasksInPeriod > 0 ? round(($slaPassCount / $totalTasksInPeriod) * 100, 1) : 100;

            echo json_encode([
                'success' => true,
                'filter' => [
                    'mode' => $mode,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'date' => $dateParam,
                    'week' => $weekParam,
                    'month' => $monthParam,
                    'department' => $deptFilter,
                    'employee_id' => $empFilter
                ],
                'summary' => [
                    'total_tasks' => $totalTasksInPeriod,
                    'total_completed_tasks' => $totalCompletedInRange,
                    'in_progress_tasks' => $totalInProgressInRange,
                    'review_tasks' => $totalReviewInRange,
                    'revision_tasks' => $totalRevisionInRange,
                    'pending_tasks' => $totalPendingInRange,
                    'clearance_rate' => $clearanceRate,
                    'sla_pass_rate' => $slaPassRate,
                    'sla_breaches' => $slaBreachCount,
                    'active_employees_with_completions' => count(array_filter($employeeCompletions, function ($e) { return $e['completed_count'] > 0; })),
                    'total_staff' => count($employeeCompletions)
                ],
                'employee_completions' => $employeeCompletions,
                'daily_breakdown' => $dailyBreakdown,
                'task_ledger' => $taskLedger,
                'project_duration_summary' => [
                    'total_projects' => count($projectDurations),
                    'completed_projects' => $completedProjectsCount,
                    'avg_completion_hours' => $avgHours,
                    'avg_completion_days' => $avgDays,
                    'on_time_count' => $onTimeCount,
                    'overdue_count' => $overdueCount
                ],
                'project_durations' => $projectDurations
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid API action requested.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
