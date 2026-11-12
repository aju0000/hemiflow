<?php
// api.php - Backend REST API Endpoints for Agency OS (Modules 3, 4, 9 Complete)

ob_start();
if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/auth.php';

$action = $_GET['action'] ?? '';
$db = getDbConnection();
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
            $db->exec("CREATE TABLE IF NOT EXISTS document_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) UNIQUE NOT NULL,
                description TEXT,
                icon_name VARCHAR(50) DEFAULT 'file-text',
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

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

if (!function_exists('isTaskVisibleToUser')) {
    function isTaskVisibleToUser($task, $userId, $db = null) {
        $uid = intval($userId);
        if ($uid <= 0 || !$task) return false;
        if (intval($task['assigned_to'] ?? 0) === $uid) return true;

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
    function buildTaskUserMatchWhere($userId, &$params, $alias = 't') {
        $uid = intval($userId);
        $like = '%' . $uid . '%';
        $params[] = $uid;
        $params[] = $uid;
        $params[] = $like;
        $params[] = $like;
        return "({$alias}.assigned_to = ? OR EXISTS (SELECT 1 FROM task_assignments ta WHERE ta.task_id = {$alias}.id AND ta.user_id = ?) OR {$alias}.assignees_json LIKE ? OR {$alias}.observers_json LIKE ?)";
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
                $db->exec("CREATE TABLE IF NOT EXISTS departments (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) UNIQUE NOT NULL, created_by INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
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
                // Ensure projects has department column
                try {
                    $db->exec("ALTER TABLE projects ADD COLUMN department VARCHAR(100) DEFAULT 'Development'");
                } catch (Exception $eCol) {}

                $stmtProj = $db->prepare("INSERT INTO projects (client_id, service_id, project_name, description, priority, status, team_lead_id, department, deadline, created_by) VALUES (?, ?, ?, ?, ?, 'In Progress', ?, ?, ?, ?)");
                $stmtProj->execute([
                    $doc['client_id'],
                    $doc['service_id'],
                    $projectName,
                    $description,
                    $priority,
                    $teamLeadId,
                    $primaryDept,
                    $deadline,
                    $user['id']
                ]);
                $projectId = $db->lastInsertId();

                $stmtLink = $db->prepare("UPDATE documents SET handover_project_id = ?, status = 'Approved' WHERE id = ?");
                $stmtLink->execute([$projectId, $docId]);

                $stmtCli = $db->prepare("UPDATE clients SET status = 'Approved / Active' WHERE id = ?");
                $stmtCli->execute([$doc['client_id']]);

                // Create Work Order Tasks for each feature / deliverable assigned to its respective department
                $features = $input['features'] ?? [];
                if (!is_array($features) || empty($features)) {
                    $features = [[
                        'title' => "Handover Execution: " . $doc['title'],
                        'department' => $primaryDept,
                        'assigned_to' => $teamLeadId,
                        'priority' => $priority,
                        'sla_hours' => 48,
                        'notes' => $description
                    ]];
                }

                $tasksCreated = 0;
                $assignedDepts = [];

                foreach ($features as $idx => $f) {
                    $fTitle = trim($f['title'] ?? '');
                    if ($fTitle === '') {
                        $fTitle = "Feature " . ($idx + 1) . ": " . $doc['title'];
                    }
                    $fDept = trim($f['department'] ?? $primaryDept);
                    if (empty($fDept) || strtolower($fDept) === 'sales') {
                        $fDept = 'Development';
                    }
                    $assignedDepts[$fDept] = true;

                    // Resolve assigned lead and primary assignee for this department deliverable
                    $fTaskLeadId = intval($f['team_lead_id'] ?? ($f['assigned_to'] ?? 0));
                    if ($fTaskLeadId <= 0) {
                        $stmtLead = $db->prepare("
                            SELECT u.id FROM users u 
                            JOIN roles r ON u.role_id = r.id 
                            WHERE u.department = ? AND (u.role_id IN (1, 4) OR r.name LIKE '%Lead%' OR r.name LIKE '%Manager%') 
                            ORDER BY u.role_id ASC LIMIT 1
                        ");
                        $stmtLead->execute([$fDept]);
                        $fTaskLeadId = $stmtLead->fetchColumn();

                        if (!$fTaskLeadId) {
                            $stmtDeptUser = $db->prepare("SELECT id FROM users WHERE department = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
                            $stmtDeptUser->execute([$fDept]);
                            $fTaskLeadId = $stmtDeptUser->fetchColumn();
                        }

                        if (!$fTaskLeadId) {
                            $fTaskLeadId = $teamLeadId;
                        }
                    }

                    $fPrimaryAssignedTo = $fTaskLeadId;
                    $fAssignees = [$fPrimaryAssignedTo];
                    $fAssigneesJson = json_encode($fAssignees);

                    // Resolve observers (watchers)
                    $fObservers = [];
                    if (!empty($f['observers']) && is_array($f['observers'])) {
                        $fObservers = array_values(array_filter(array_map('intval', $f['observers'])));
                    }
                    // Current creator (e.g. Sales user) is automatically added as an observer
                    if (!in_array($user['id'], $fObservers)) {
                        $fObservers[] = $user['id'];
                    }
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

                // Notify Team Leads & Super Admins about the new project handover
                try {
                    $db->exec("CREATE TABLE IF NOT EXISTS user_notifications (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        message TEXT NOT NULL,
                        type VARCHAR(50) DEFAULT 'project_handover',
                        link_url VARCHAR(255) DEFAULT '',
                        is_read INTEGER DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )");

                    $leadsToNotify = [];
                    if ($teamLeadId > 0) {
                        $leadsToNotify[$teamLeadId] = true;
                    }
                    foreach ($features as $feat) {
                        $fLeadId = intval($feat['team_lead_id'] ?? ($feat['assigned_to'] ?? 0));
                        if ($fLeadId > 0) {
                            $leadsToNotify[$fLeadId] = true;
                        }
                    }
                    // Also notify Super Admins
                    $stmtSuper = $db->query("SELECT id FROM users WHERE role_id = 1 AND is_active = 1");
                    if ($stmtSuper) {
                        while ($sRow = $stmtSuper->fetch(PDO::FETCH_ASSOC)) {
                            $leadsToNotify[intval($sRow['id'])] = true;
                        }
                    }

                    $stmtLeadNotif = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'project_handover', ?, 0, CURRENT_TIMESTAMP)");
                    $notifTitle = "🚀 New Project Handover: {$projectName}";
                    $notifMsg = "Sales rep {$user['full_name']} handed over document #{$doc['document_number']} ({$doc['company_name']}) with {$tasksCreated} feature deliverable(s). Click to review tasks, assign team members, and add observers.";
                    $notifLink = "projects.php?project_id={$projectId}";

                    foreach (array_keys($leadsToNotify) as $targetLeadId) {
                        $stmtLeadNotif->execute([$targetLeadId, $notifTitle, $notifMsg, $notifLink]);
                    }
                } catch (Exception $eLeadNotif) {}

                $deptListStr = implode(', ', array_keys($assignedDepts));
                logActivity($user['id'], 'template-generator', 'HANDOVER_TO_DEPARTMENTS', 'projects', $projectId, "Handed over document #{$doc['document_number']} with {$tasksCreated} features to departments ({$deptListStr})");

                $msg = "Client approval confirmed! Project #{$projectId} created for {$doc['company_name']}. Successfully handed over {$tasksCreated} feature deliverable(s) to department(s): {$deptListStr}.";
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

            // Only Team Lead, Super Admin, Admin, and Manager can reassign tasks and manage observers
            if (!isLeadOrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Leads and Super Admins can assign tasks and manage observers.']);
                exit;
            }

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
                    (SELECT COUNT(*) FROM project_competitors pc WHERE pc.project_id = p.id) as competitors_count
                    FROM projects p
                    JOIN clients c ON p.client_id = c.id
                    LEFT JOIN users u ON p.team_lead_id = u.id
                    LEFT JOIN users cb ON p.created_by = cb.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY COALESCE(p.is_current, 1) DESC, p.id DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'projects' => $stmt->fetchAll()]);
            break;

        case 'get_project_details':
            checkAuth('task-management');
            $projectId = intval($_GET['project_id'] ?? 0);
            if ($projectId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Project ID is required.']);
                exit;
            }

            $stmtP = $db->prepare("SELECT p.*, c.company_name, c.contact_person, c.email as client_email, c.phone as client_phone,
                                   u.full_name as team_lead_name, cb.full_name as created_by_name,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted=0) as total_tasks,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='COMPLETED' AND t.is_deleted=0) as completed_tasks,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='IN PROGRESS' AND t.is_deleted=0) as in_progress_tasks,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='PENDING' AND t.is_deleted=0) as pending_tasks,
                                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='UNDER REVIEW' AND t.is_deleted=0) as under_review_tasks
                                   FROM projects p
                                   JOIN clients c ON p.client_id = c.id
                                   LEFT JOIN users u ON p.team_lead_id = u.id
                                   LEFT JOIN users cb ON p.created_by = cb.id
                                   WHERE p.id = ?");
            $stmtP->execute([$projectId]);
            $project = $stmtP->fetch();
            if (!$project) {
                echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            $stmtComp = $db->prepare("SELECT pc.*, u.full_name as added_by_name FROM project_competitors pc LEFT JOIN users u ON pc.created_by = u.id WHERE pc.project_id = ? ORDER BY pc.id ASC");
            $stmtComp->execute([$projectId]);
            $competitors = $stmtComp->fetchAll();

            $stmtTasks = $db->prepare("SELECT t.*, u_assign.full_name as assigned_to_name, u_lead.full_name as team_lead_name
                                       FROM tasks t
                                       LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
                                       LEFT JOIN users u_lead ON t.team_lead_id = u_lead.id
                                       WHERE t.project_id = ? AND t.is_deleted = 0
                                       ORDER BY CASE t.priority WHEN 'Urgent' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, t.id DESC");
            $stmtTasks->execute([$projectId]);
            $projectTasks = $stmtTasks->fetchAll();

            $pdUser = getCurrentUser();
            if (!isLeadOrAdmin()) {
                $pdUid = intval($pdUser['id'] ?? 0);
                $projectTasks = array_values(array_filter($projectTasks, function ($pt) use ($pdUid, $db) {
                    return isTaskVisibleToUser($pt, $pdUid, $db);
                }));
                $project['total_tasks'] = count($projectTasks);
                $project['completed_tasks'] = 0;
                $project['in_progress_tasks'] = 0;
                $project['pending_tasks'] = 0;
                $project['under_review_tasks'] = 0;
                foreach ($projectTasks as $vpt) {
                    if ($vpt['status'] === 'COMPLETED') $project['completed_tasks']++;
                    elseif ($vpt['status'] === 'IN PROGRESS') $project['in_progress_tasks']++;
                    elseif ($vpt['status'] === 'PENDING') $project['pending_tasks']++;
                    elseif ($vpt['status'] === 'UNDER REVIEW') $project['under_review_tasks']++;
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
                'competitors' => $competitors,
                'tasks' => $projectTasks
            ]);
            break;

        case 'create_project':
            checkAuth('task-management');
            $user = getCurrentUser();
            if (!isLeadOrAdmin() && $user['role'] === 'Developer') {
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

            if (!$clientId || empty($projectName)) {
                echo json_encode(['success' => false, 'message' => 'Client and Project Name are required.']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO projects (
                client_id, project_name, description, about_project, project_url, deliverables_summary, target_audience, start_date, budget_info, priority, status, team_lead_id, department, deadline, is_current, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $clientId, $projectName, $description, $aboutProject, $projectUrl, $deliverablesSummary, $targetAudience, $startDate, $budgetInfo, $priority, $status, $teamLeadId, $department, $deadline, $isCurrent, $user['id']
            ]);
            $projectId = $db->lastInsertId();

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
            if (!isLeadOrAdmin() && $user['role'] === 'Developer') {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Leads and Admins can edit project overview & details.']);
                exit;
            }

            $projectId = intval($input['project_id'] ?? $input['id'] ?? 0);
            $projectName = trim($input['project_name'] ?? '');
            if ($projectId <= 0 || empty($projectName)) {
                echo json_encode(['success' => false, 'message' => 'Project ID and Project Name are required.']);
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

            logActivity($user['id'], 'task-management', 'UPDATE_PROJECT', 'projects', $projectId, "Updated project overview & details for '{$projectName}'");
            echo json_encode(['success' => true, 'message' => 'Project overview & details updated successfully.']);
            break;

        case 'save_project_competitor':
            checkAuth('task-management');
            $user = getCurrentUser();
            if (!isLeadOrAdmin() && $user['role'] === 'Developer') {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Leads and Admins can manage competitor details.']);
                exit;
            }

            $compId = intval($input['id'] ?? 0);
            $projectId = intval($input['project_id'] ?? 0);
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
                    $stmtInsP = $db->prepare("INSERT INTO projects (
                        client_id, project_name, description, about_project, project_url, deliverables_summary, target_audience, start_date, budget_info, priority, status, team_lead_id, department, deadline, is_current, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtInsP->execute([
                        $clientId, $projectName, $description, $aboutProject, $projectUrl, $deliverablesSummary,
                        $targetAudience, $startDate, $budgetInfo, $priority, $status, $teamLeadId, $department,
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

            $sql = "SELECT t.*, c.company_name, p.project_name, 
                    u_assign.full_name as assigned_to_name, 
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
            if (!$isLead) {
                $sql .= " AND " . buildTaskUserMatchWhere($user['id'], $params, 't');
            } elseif ($assignedTo > 0) {
                $filterParams = [];
                $sql .= " AND " . buildTaskUserMatchWhere($assignedTo, $filterParams, 't');
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
                $sql .= " AND t.status = ?";
                $params[] = $status;
            }

            if (!empty($department)) {
                $sql .= " AND t.department = ?";
                $params[] = $department;
            }

            $sql .= " ORDER BY CASE t.priority WHEN 'Urgent' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, t.id DESC";

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

            foreach ($tasks as &$t) {
                $t['sla_status'] = calculateTaskSLAStatus($t);
                $t['assignee_ids'] = json_decode($t['assignees_json'] ?? '[]', true) ?: [];
                $t['observer_ids'] = json_decode($t['observers_json'] ?? '[]', true) ?: [];
            }

            echo json_encode(['success' => true, 'tasks' => $tasks]);
            break;

        case 'create_task':
            checkAuth('task-management');
            $user = getCurrentUser();

            if (!isLeadOrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Lead or Super Admin can create tasks.']);
                exit;
            }

            $projectId = intval($input['project_id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $department = $input['department'] ?? 'Development';
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
            $observers = $input['observers'] ?? [];
            $observerIds = [];
            if (!empty($observers) && is_array($observers)) {
                $observerIds = array_values(array_filter(array_map('intval', $observers)));
            }
            $observersJson = json_encode($observerIds);

            if (!$projectId || empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Project and Task Title are required.']);
                exit;
            }

            $stmtProj = $db->prepare("SELECT client_id, service_id, project_name FROM projects WHERE id = ?");
            $stmtProj->execute([$projectId]);
            $proj = $stmtProj->fetch();

            $taskCode = 'TSK-' . rand(100, 999);
            $slaStartTime = $startDate;
            $slaDueTime = $endDate;
            $clientApprovalStatus = $clientApprovalRequired ? 'Pending' : 'Not Required';

            $stmt = $db->prepare("INSERT INTO tasks (
                task_code, title, description, client_id, project_id, service_id, department, assigned_to, assignees_json, observers_json, team_lead_id, created_by, priority, status, start_date, end_date, deadline, sla_hours, sla_start_time, sla_due_time, remarks, client_approval_required, client_approval_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?, ?, ?, 'Task created and assigned', ?, ?)");
            $stmt->execute([
                $taskCode, $title, $description, $proj['client_id'], $projectId, $proj['service_id'], $department, $assignedTo, $assigneesJson, $observersJson, $teamLeadId, $user['id'], $priority, $startDate, $endDate, $deadline, $slaHours, $slaStartTime, $slaDueTime, $clientApprovalRequired, $clientApprovalStatus
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
            $stmtAct->execute([$taskId, $user['id'], "Team Lead created task #{$taskCode} and assigned to User ID #{$assignedTo} (Total assignees: " . count($assigneeIds) . ", Observers: " . count($observerIds) . ")"]);

            if (!empty($assigneeIds)) {
                notifyTaskAssignees($db, $taskId, $taskCode, $title, $assigneeIds, $user, $proj['project_name'] ?? '');
            }

            logActivity($user['id'], 'task-management', 'CREATE_TASK', 'tasks', $taskId, "Created task {$taskCode}: {$title}");

            echo json_encode(['success' => true, 'task_id' => $taskId, 'task_code' => $taskCode, 'message' => "Task #{$taskCode} created and assigned successfully."]);
            break;

        case 'update_task_status':
            checkAuth('task-management');
            $user = getCurrentUser();

            $taskId = intval($input['task_id'] ?? 0);
            $newStatus = trim($input['status'] ?? '');

            $validStatuses = ['PENDING', 'IN PROGRESS', 'UNDER REVIEW', 'REVISION REQUIRED', 'COMPLETED'];
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
            if (!isLeadOrAdmin() && !isTaskVisibleToUser($task, $user['id'], $db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You can only update tasks assigned to you.']);
                exit;
            }

            $oldStatus = $task['status'];
            $completionTime = ($newStatus === 'COMPLETED') ? date('Y-m-d H:i:s') : $task['completion_time'];

            $stmtUpd = $db->prepare("UPDATE tasks SET status = ?, completion_time = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$newStatus, $completionTime, $taskId]);

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'STATUS_CHANGE', ?, ?, ?)");
            $stmtAct->execute([$taskId, $user['id'], $oldStatus, $newStatus, "User changed status from {$oldStatus} to {$newStatus}"]);

            logActivity($user['id'], 'task-management', 'UPDATE_TASK_STATUS', 'tasks', $taskId, "Changed task #{$task['task_code']} status from '{$oldStatus}' to '{$newStatus}'");

            echo json_encode(['success' => true, 'new_status' => $newStatus, 'message' => "Task status updated to '{$newStatus}'!"]);
            break;

        case 'get_task_details':
            checkAuth('task-management');
            $user = getCurrentUser();
            $taskId = intval($_GET['task_id'] ?? 0);

            $stmt = $db->prepare("SELECT t.*, c.company_name, c.contact_person, p.project_name,
                                  u_assign.full_name as assigned_to_name, u_assign.email as assigned_to_email,
                                  u_lead.full_name as team_lead_name,
                                  u_creator.full_name as created_by_name
                                  FROM tasks t
                                  JOIN clients c ON t.client_id = c.id
                                  JOIN projects p ON t.project_id = p.id
                                  LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
                                  LEFT JOIN users u_lead ON t.team_lead_id = u_lead.id
                                  LEFT JOIN users u_creator ON t.created_by = u_creator.id
                                  WHERE t.id = ? AND t.is_deleted = 0");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }
            if (!isLeadOrAdmin() && !isTaskVisibleToUser($task, $user['id'], $db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You can only view tasks assigned to you.']);
                exit;
            }

            $task['sla_status'] = calculateTaskSLAStatus($task);

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
            $observerIds = json_decode($task['observers_json'] ?? '[]', true) ?: [];
            $task['observers'] = [];
            if (!empty($observerIds)) {
                $placeholders = implode(',', array_fill(0, count($observerIds), '?'));
                $stmtO = $db->prepare("SELECT u.id, u.full_name, u.username, u.email, u.department, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id IN ($placeholders)");
                $stmtO->execute($observerIds);
                $task['observers'] = $stmtO->fetchAll();
            }

            $stmtCom = $db->prepare("SELECT tc.*, u.full_name as user_name, u.department FROM task_comments tc JOIN users u ON tc.user_id = u.id WHERE tc.task_id = ? ORDER BY tc.id ASC");
            $stmtCom->execute([$taskId]);
            $comments = $stmtCom->fetchAll();

            $stmtAct = $db->prepare("SELECT ta.*, u.full_name as user_name FROM task_activities ta JOIN users u ON ta.user_id = u.id WHERE ta.task_id = ? ORDER BY ta.id DESC");
            $stmtAct->execute([$taskId]);
            $activities = $stmtAct->fetchAll();

            $stmtRev = $db->prepare("SELECT tr.*, u_tl.full_name as team_lead_name FROM task_reviews tr JOIN users u_tl ON tr.team_lead_id = u_tl.id WHERE tr.task_id = ? ORDER BY tr.id DESC");
            $stmtRev->execute([$taskId]);
            $reviews = $stmtRev->fetchAll();

            echo json_encode([
                'success' => true,
                'task' => $task,
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

            if (!isLeadOrAdmin() && !isTaskVisibleToUser($task, $user['id'], $db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You can only submit work for tasks assigned to you.']);
                exit;
            }

            $stmtUpd = $db->prepare("UPDATE tasks SET status = 'UNDER REVIEW', remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$submissionNotes, $taskId]);

            $commentText = "⚡ Submitted work for Team Lead review.\nNotes: " . $submissionNotes;
            if (!empty($attachmentUrl)) $commentText .= "\nDeliverable URL: " . $attachmentUrl;

            $stmtCom = $db->prepare("INSERT INTO task_comments (task_id, user_id, comment_text, attachment_url) VALUES (?, ?, ?, ?)");
            $stmtCom->execute([$taskId, $user['id'], $commentText, $attachmentUrl]);

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'DEVELOPER_SUBMISSION', ?, 'UNDER REVIEW', ?)");
            $stmtAct->execute([$taskId, $user['id'], $task['status'], $submissionNotes]);

            logActivity($user['id'], 'task-management', 'SUBMIT_WORK', 'tasks', $taskId, "Submitted work for task #{$task['task_code']}");

            echo json_encode(['success' => true, 'message' => 'Work submitted successfully! Status changed to UNDER REVIEW for Team Lead approval.']);
            break;

        case 'review_task_work':
            checkAuth('task-management');
            $user = getCurrentUser();

            if (!isLeadOrAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Team Lead or Super Admin can review tasks.']);
                exit;
            }

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

            $newStatus = $task['status'];
            $completionTime = null;
            $clientApprovalStatus = $task['client_approval_status'];

            if ($reviewAction === 'REQUEST_REVISION') {
                $newStatus = 'REVISION REQUIRED';
            } elseif ($reviewAction === 'SEND_CLIENT_APPROVAL') {
                $newStatus = 'SENT FOR CLIENT APPROVAL';
                $clientApprovalStatus = 'Pending';
            } else {
                if ($task['client_approval_required']) {
                    $newStatus = 'SENT FOR CLIENT APPROVAL';
                    $clientApprovalStatus = 'Pending';
                } else {
                    $newStatus = 'COMPLETED';
                    $completionTime = date('Y-m-d H:i:s');
                }
            }

            $stmtUpd = $db->prepare("UPDATE tasks SET status = ?, completion_time = ?, client_approval_status = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$newStatus, $completionTime, $clientApprovalStatus, $reviewComment, $taskId]);

            $stmtRev = $db->prepare("INSERT INTO task_reviews (task_id, team_lead_id, developer_id, review_action, review_comment, required_changes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtRev->execute([$taskId, $user['id'], $task['assigned_to'], $reviewAction, $reviewComment, $requiredChanges]);

            $commentMsg = "🛠️ Team Lead Review Action: " . $reviewAction . "\nComment: " . $reviewComment;
            if (!empty($requiredChanges)) $commentMsg .= "\nRequired Revisions: " . $requiredChanges;

            $stmtCom = $db->prepare("INSERT INTO task_comments (task_id, user_id, comment_text) VALUES (?, ?, ?)");
            $stmtCom->execute([$taskId, $user['id'], $commentMsg]);

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'TEAM_LEAD_REVIEW', ?, ?, ?)");
            $stmtAct->execute([$taskId, $user['id'], $task['status'], $newStatus, "Action: {$reviewAction} - {$reviewComment}"]);

            logActivity($user['id'], 'task-management', 'TEAM_LEAD_REVIEW', 'tasks', $taskId, "Reviewed task #{$task['task_code']}: {$reviewAction}");

            echo json_encode(['success' => true, 'new_status' => $newStatus, 'message' => "Team Lead review recorded. Task status updated to {$newStatus}."]);
            break;

        case 'process_client_approval':
            checkAuth('task-management');
            $user = getCurrentUser();

            $taskId = intval($input['task_id'] ?? 0);
            $approvalStatus = $input['approval_status'] ?? 'Approved';
            $clientFeedback = trim($input['client_feedback'] ?? '');

            $stmtTask = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmtTask->execute([$taskId]);
            $task = $stmtTask->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            $newStatus = $task['status'];
            $completionTime = null;

            if ($approvalStatus === 'Approved') {
                $newStatus = 'COMPLETED';
                $completionTime = date('Y-m-d H:i:s');
            } else {
                $newStatus = 'CLIENT REVISION REQUESTED';
            }

            $stmtUpd = $db->prepare("UPDATE tasks SET status = ?, client_approval_status = ?, completion_time = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$newStatus, $approvalStatus, $completionTime, $clientFeedback, $taskId]);

            $stmtAppr = $db->prepare("INSERT INTO client_approvals (task_id, client_id, approval_status, client_feedback, approved_at) VALUES (?, ?, ?, ?, ?)");
            $stmtAppr->execute([$taskId, $task['client_id'], $approvalStatus, $clientFeedback, $completionTime]);

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks) VALUES (?, ?, 'CLIENT_APPROVAL', ?, ?, ?)");
            $stmtAct->execute([$taskId, $user['id'], $task['status'], $newStatus, "Client {$approvalStatus}: {$clientFeedback}"]);

            echo json_encode(['success' => true, 'new_status' => $newStatus, 'message' => "Client approval response recorded as {$approvalStatus}."]);
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
                $sqlM = "SELECT id, status, assigned_to, assignees_json, observers_json FROM tasks WHERE is_deleted = 0 AND " . buildTaskUserMatchWhere($devId, $paramsM, 'tasks');
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
                    if ($mt['status'] === 'PENDING') $pending++;
                    elseif ($mt['status'] === 'IN PROGRESS') $inProgress++;
                    elseif ($mt['status'] === 'UNDER REVIEW') $underReview++;
                    elseif ($mt['status'] === 'REVISION REQUIRED' || $mt['status'] === 'CLIENT REVISION REQUESTED') $revision++;
                    elseif ($mt['status'] === 'COMPLETED') $completed++;
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
                $activeTasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status IN ('IN PROGRESS', 'UNDER REVIEW', 'REVISION REQUIRED') AND is_deleted=0")->fetchColumn();
                $awaitingReview = $db->query("SELECT COUNT(*) FROM tasks WHERE status='UNDER REVIEW' AND is_deleted=0")->fetchColumn();
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
                $sql .= " AND " . buildTaskUserMatchWhere($user['id'], $params, 't');
            } elseif ($devId > 0) {
                $filterParams = [];
                $sql .= " AND " . buildTaskUserMatchWhere($devId, $filterParams, 't');
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
                'IN PROGRESS' => ['bg' => '#2563eb', 'border' => '#1d4ed8', 'text' => '#ffffff'],
                'COMPLETED' => ['bg' => '#10b981', 'border' => '#059669', 'text' => '#ffffff'],
                'UNDER REVIEW' => ['bg' => '#f59e0b', 'border' => '#d97706', 'text' => '#ffffff'],
                'REVISION REQUIRED' => ['bg' => '#ef4444', 'border' => '#dc2626', 'text' => '#ffffff'],
                'CLIENT REVISION REQUESTED' => ['bg' => '#e11d48', 'border' => '#be123c', 'text' => '#ffffff'],
                'SENT FOR CLIENT APPROVAL' => ['bg' => '#8b5cf6', 'border' => '#7c3aed', 'text' => '#ffffff'],
                'PENDING' => ['bg' => '#64748b', 'border' => '#475569', 'text' => '#ffffff']
            ];

            $events = [];
            foreach ($tasks as $t) {
                $slaStatus = calculateTaskSLAStatus($t);
                $palette = $statusColors[$t['status']] ?? ['bg' => '#64748b', 'border' => '#475569', 'text' => '#ffffff'];

                $sDate = !empty($t['start_date']) ? $t['start_date'] : $t['created_at'];
                $eDate = !empty($t['end_date']) ? $t['end_date'] : ($t['deadline'] ?? date('Y-m-d H:i:s', strtotime($sDate . ' +1 day')));

                $startISO = date('Y-m-d\TH:i:s', strtotime($sDate));
                $endISO = date('Y-m-d\TH:i:s', strtotime($eDate));

                $events[] = [
                    'id' => (string)$t['id'],
                    'title' => '#' . $t['task_code'] . ' ' . $t['title'],
                    'start' => $startISO,
                    'end' => $endISO,
                    'backgroundColor' => $palette['bg'],
                    'borderColor' => $palette['border'],
                    'textColor' => $palette['text'],
                    'allDay' => false,
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
                        'assigned_to_name' => $t['assigned_to_name'] ?: 'Unassigned',
                        'team_lead_name' => $t['team_lead_name'] ?: 'Team Lead',
                        'priority' => $t['priority'],
                        'status' => $t['status'],
                        'start_date' => $sDate,
                        'end_date' => $eDate,
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

            if (!isLeadOrAdmin() && !isTaskVisibleToUser($task, $user['id'], $db)) {
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

            if (!isLeadOrAdmin() && !isTaskVisibleToUser($task, $user['id'], $db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access Denied: You cannot edit tasks assigned to other employees.']);
                exit;
            }

            // Regular employee can't change assigned_to, title, or project_id
            if (!isLeadOrAdmin()) {
                $assignedTo = $task['assigned_to'];
                $title = $task['title'];
                $projectId = intval($task['project_id']);
                $priority = $task['priority'];
                $description = $task['description'];
            }

            $startFormatted = !empty($startDate) ? date('Y-m-d H:i:s', strtotime($startDate)) : $task['start_date'];
            $endFormatted = !empty($endDate) ? date('Y-m-d H:i:s', strtotime($endDate)) : $task['end_date'];
            if (!$endFormatted) $endFormatted = $task['deadline'];

            $completionTime = ($status === 'COMPLETED' && $task['status'] !== 'COMPLETED') ? date('Y-m-d H:i:s') : $task['completion_time'];

            $oldAssignedTo = intval($task['assigned_to'] ?? 0);
            $newAssignedToInt = intval($assignedTo ?? 0);
            $assigneesJsonUpd = $task['assignees_json'] ?? '[]';
            if (isLeadOrAdmin() && $newAssignedToInt > 0 && $newAssignedToInt !== $oldAssignedTo) {
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

            if (isLeadOrAdmin() && $newAssignedToInt > 0 && $newAssignedToInt !== $oldAssignedTo) {
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

            if ($reportType === 'employees_status') {
                $sql = "
                    SELECT e.employee_code, e.full_name, e.email, e.phone, e.employment_status,
                           e.employment_type, e.work_location, e.date_of_joining,
                           d.name as department_name, des.title as designation_title,
                           m.full_name as reporting_manager_name
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN designations des ON e.designation_id = des.id
                    LEFT JOIN employees m ON e.reporting_manager_id = m.id
                    WHERE 1=1
                ";
                if ($deptId > 0) $sql .= " AND e.department_id = $deptId";
                $sql .= " ORDER BY e.id ASC";
                $data = $db->query($sql)->fetchAll();
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

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid API action requested.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
