<?php
// api.php - Backend REST API Endpoints for Agency OS (Modules 3, 4, 9 Complete)

header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';

$action = $_GET['action'] ?? '';
$db = getDbConnection();
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

/**
 * Server-side SLA Calculation Helper
 */
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

        // --- USER MANAGEMENT API ---
        case 'get_roles':
            checkAuth();
            $stmt = $db->query("SELECT * FROM roles ORDER BY id ASC");
            echo json_encode(['success' => true, 'roles' => $stmt->fetchAll()]);
            break;

        case 'get_users':
            checkAuth();
            $roleId = intval($_GET['role_id'] ?? 0);
            $dept = $_GET['department'] ?? null;

            $sql = "SELECT u.id, u.username, u.full_name, u.email, u.role_id, r.name as role_name, u.department, u.is_active, u.signup_token, u.created_at
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

            $stmtIns = $db->prepare("INSERT INTO users (username, password_hash, full_name, email, role_id, department, signup_token, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmtIns->execute([$username, $passHash, $fullName, $email, $roleId, $department, $signupToken]);
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
            $targetUserId = intval($input['user_id'] ?? 0);
            $stmt = $db->prepare("SELECT is_active, username FROM users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $u = $stmt->fetch();
            if (!$u) {
                echo json_encode(['success' => false, 'message' => 'User not found.']);
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
            checkAuth('template-generator');
            $user = getCurrentUser();

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

        case 'reset_client_password':
            checkAuth();
            $currentUser = getCurrentUser();

            if (!($currentUser['role'] === 'Super Admin' || ($currentUser['role_id'] ?? 0) == 1)) {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Only Super Admin can change or reset client portal passwords.']);
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

            // Clean up dependent client records
            $db->prepare("DELETE FROM client_services WHERE client_id = ?")->execute([$targetClientId]);
            $db->prepare("DELETE FROM client_notifications WHERE client_id = ?")->execute([$targetClientId]);
            $db->prepare("DELETE FROM client_meetings WHERE client_id = ?")->execute([$targetClientId]);
            $db->prepare("DELETE FROM client_tokens WHERE client_id = ?")->execute([$targetClientId]);
            $db->prepare("DELETE FROM sms_logs WHERE client_id = ?")->execute([$targetClientId]);

            // Delete associated client portal user accounts
            $db->prepare("DELETE FROM user_module_access WHERE user_id IN (SELECT id FROM users WHERE client_id = ?)")->execute([$targetClientId]);
            $db->prepare("DELETE FROM users WHERE client_id = ?")->execute([$targetClientId]);

            // Delete tasks, projects, documents associated with client
            $db->prepare("DELETE FROM tasks WHERE client_id = ?")->execute([$targetClientId]);
            $db->prepare("DELETE FROM projects WHERE client_id = ?")->execute([$targetClientId]);
            $db->prepare("DELETE FROM documents WHERE client_id = ?")->execute([$targetClientId]);

            // Delete client row
            $stmtDel = $db->prepare("DELETE FROM clients WHERE id = ?");
            $stmtDel->execute([$targetClientId]);

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
            $stmt = $db->prepare("SELECT * FROM client_services WHERE client_id = ? ORDER BY id DESC");
            $stmt->execute([$clientId]);
            echo json_encode(['success' => true, 'services' => $stmt->fetchAll()]);
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

            $stmtClient = $db->prepare("SELECT * FROM clients WHERE id = ?");
            $stmtClient->execute([$clientId]);
            $client = $stmtClient->fetch();

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
            $stmtDoc = $db->prepare("SELECT d.*, c.company_name, c.contact_person, c.email as client_email, c.phone as client_phone, c.id as client_id FROM documents d JOIN clients c ON d.client_id = c.id WHERE d.id = ?");
            $stmtDoc->execute([$docId]);
            $doc = $stmtDoc->fetch();

            if (!$doc) {
                echo json_encode(['success' => false, 'message' => 'Document not found.']);
                exit;
            }

            $projectName = trim($input['project_name'] ?? ($doc['company_name'] . ' - ' . $doc['title']));
            $description = trim($input['description'] ?? ("Work Requirement Handover from approved document {$doc['document_number']}.\nClient: {$doc['company_name']}"));
            $priority = $input['priority'] ?? 'High';
            $deadline = $input['deadline'] ?? date('Y-m-d', strtotime('+14 days'));
            $teamLeadId = intval($input['team_lead_id'] ?? 3);

            $stmtProj = $db->prepare("INSERT INTO projects (client_id, service_id, project_name, description, priority, status, team_lead_id, deadline, created_by) VALUES (?, ?, ?, ?, ?, 'In Progress', ?, ?, ?)");
            $stmtProj->execute([$doc['client_id'], $doc['service_id'], $projectName, $description, $priority, $teamLeadId, $deadline, $user['id']]);
            $projectId = $db->lastInsertId();

            $stmtLink = $db->prepare("UPDATE documents SET handover_project_id = ?, status = 'Approved' WHERE id = ?");
            $stmtLink->execute([$projectId, $docId]);

            $stmtCli = $db->prepare("UPDATE clients SET status = 'Approved / Active' WHERE id = ?");
            $stmtCli->execute([$doc['client_id']]);

            // Auto-provision or link Client Portal user account
            $clientUser = autoProvisionClientUser($db, $doc['client_id']);

            // Create Client Notification for portal hub
            try {
                $stmtNotif = $db->prepare("INSERT INTO client_notifications (client_id, project_id, notification_type, title, message, is_read) VALUES (?, ?, 'PROJECT_COMPLETED', 'Project Initiated', ?, 0)");
                $stmtNotif->execute([
                    $doc['client_id'],
                    $projectId,
                    "Your project '{$projectName}' has been handed over to our development team and is now active."
                ]);
            } catch (Exception $e) {
                // Ignore optional notification errors
            }

            logActivity($user['id'], 'template-generator', 'HANDOVER_TO_DEVELOPMENT', 'projects', $projectId, "Handed over document #{$doc['document_number']} to Development Team Lead");

            $msg = "Client approval confirmed! Work requirement handed over to Development Team Lead. Project #{$projectId} created for {$doc['company_name']}.";
            if ($clientUser) {
                if ($clientUser['created']) {
                    $msg .= " Client Portal user created (@{$clientUser['username']}, Temp Password: {$clientUser['temp_password']}).";
                } else {
                    $msg .= " Client Portal account (@{$clientUser['username']}) active for {$doc['company_name']}.";
                }
            }

            echo json_encode([
                'success' => true,
                'project_id' => $projectId,
                'client_user' => $clientUser,
                'message' => $msg
            ]);
            break;

        // --- MODULE 4 API ---
        case 'get_projects':
            checkAuth('task-management');
            $sql = "SELECT p.*, c.company_name, u.full_name as team_lead_name, cb.full_name as created_by_name,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted=0) as total_tasks,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status='COMPLETED' AND t.is_deleted=0) as completed_tasks
                    FROM projects p
                    JOIN clients c ON p.client_id = c.id
                    LEFT JOIN users u ON p.team_lead_id = u.id
                    LEFT JOIN users cb ON p.created_by = cb.id
                    ORDER BY p.id DESC";
            $stmt = $db->query($sql);
            echo json_encode(['success' => true, 'projects' => $stmt->fetchAll()]);
            break;

        case 'create_project':
            checkAuth('task-management');
            $user = getCurrentUser();

            $clientId = intval($input['client_id'] ?? 0);
            $projectName = trim($input['project_name'] ?? '');
            $description = trim($input['description'] ?? '');
            $priority = $input['priority'] ?? 'High';
            $teamLeadId = intval($input['team_lead_id'] ?? 3);
            $deadline = $input['deadline'] ?? date('Y-m-d', strtotime('+14 days'));

            if (!$clientId || empty($projectName)) {
                echo json_encode(['success' => false, 'message' => 'Client and Project Name are required.']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO projects (client_id, project_name, description, priority, status, team_lead_id, deadline, created_by) VALUES (?, ?, ?, ?, 'In Progress', ?, ?, ?)");
            $stmt->execute([$clientId, $projectName, $description, $priority, $teamLeadId, $deadline, $user['id']]);
            $projectId = $db->lastInsertId();

            logActivity($user['id'], 'task-management', 'CREATE_PROJECT', 'projects', $projectId, "Created project {$projectName}");

            echo json_encode(['success' => true, 'project_id' => $projectId, 'message' => 'Project created successfully.']);
            break;

        case 'get_tasks':
            checkAuth('task-management');
            $user = getCurrentUser();

            $projectId = intval($_GET['project_id'] ?? 0);
            $status = $_GET['status'] ?? null;
            $assignedTo = intval($_GET['assigned_to'] ?? 0);

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

            if ($user['role'] === 'Developer') {
                $sql .= " AND t.assigned_to = ?";
                $params[] = $user['id'];
            } elseif ($assignedTo > 0) {
                $sql .= " AND t.assigned_to = ?";
                $params[] = $assignedTo;
            }

            if ($projectId > 0) {
                $sql .= " AND t.project_id = ?";
                $params[] = $projectId;
            }

            if ($status) {
                $sql .= " AND t.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY CASE t.priority WHEN 'Urgent' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END, t.id DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();

            foreach ($tasks as &$t) {
                $t['sla_status'] = calculateTaskSLAStatus($t);
            }

            echo json_encode(['success' => true, 'tasks' => $tasks]);
            break;

        case 'create_task':
            checkAuth('task-management');
            $user = getCurrentUser();

            if ($user['role'] === 'Developer') {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Developers cannot create tasks directly. Work must be created by Team Lead.']);
                exit;
            }

            $projectId = intval($input['project_id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $department = $input['department'] ?? 'Development';
            $assignedTo = intval($input['assigned_to'] ?? 4) ?: null;
            $teamLeadId = intval($input['team_lead_id'] ?? $user['id']);
            $priority = $input['priority'] ?? 'Medium';
            $slaHours = intval($input['sla_hours'] ?? 24);
            $deadline = $input['deadline'] ?? date('Y-m-d H:i:s', strtotime("+{$slaHours} hours"));
            $clientApprovalRequired = intval($input['client_approval_required'] ?? 0);

            if (!$projectId || empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Project and Task Title are required.']);
                exit;
            }

            $stmtProj = $db->prepare("SELECT client_id, service_id FROM projects WHERE id = ?");
            $stmtProj->execute([$projectId]);
            $proj = $stmtProj->fetch();

            $taskCode = 'TSK-' . rand(100, 999);
            $slaStartTime = date('Y-m-d H:i:s');
            $slaDueTime = date('Y-m-d H:i:s', strtotime("+{$slaHours} hours"));
            $clientApprovalStatus = $clientApprovalRequired ? 'Pending' : 'Not Required';

            $stmt = $db->prepare("INSERT INTO tasks (
                task_code, title, description, client_id, project_id, service_id, department, assigned_to, team_lead_id, created_by, priority, status, deadline, sla_hours, sla_start_time, sla_due_time, remarks, client_approval_required, client_approval_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?, 'Task created and assigned by Team Lead', ?, ?)");
            $stmt->execute([
                $taskCode, $title, $description, $proj['client_id'], $projectId, $proj['service_id'], $department, $assignedTo, $teamLeadId, $user['id'], $priority, $deadline, $slaHours, $slaStartTime, $slaDueTime, $clientApprovalRequired, $clientApprovalStatus
            ]);
            $taskId = $db->lastInsertId();

            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, new_value, remarks) VALUES (?, ?, 'TASK_CREATED', 'PENDING', ?)");
            $stmtAct->execute([$taskId, $user['id'], "Team Lead created task #{$taskCode} and assigned to Developer ID #{$assignedTo}"]);

            logActivity($user['id'], 'task-management', 'CREATE_TASK', 'tasks', $taskId, "Created task {$taskCode}: {$title}");

            echo json_encode(['success' => true, 'task_id' => $taskId, 'task_code' => $taskCode, 'message' => "Task #{$taskCode} created and assigned successfully."]);
            break;

        case 'get_task_details':
            checkAuth('task-management');
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

            $task['sla_status'] = calculateTaskSLAStatus($task);

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

            if ($user['role'] === 'Developer' && $task['assigned_to'] != $user['id']) {
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

            if ($user['role'] === 'Developer') {
                echo json_encode(['success' => false, 'message' => 'Access Denied: Developers cannot review tasks.']);
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

            if ($user['role'] === 'Developer') {
                $devId = $user['id'];
                $total = $db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to={$devId} AND is_deleted=0")->fetchColumn();
                $pending = $db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to={$devId} AND status='PENDING' AND is_deleted=0")->fetchColumn();
                $inProgress = $db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to={$devId} AND status='IN PROGRESS' AND is_deleted=0")->fetchColumn();
                $underReview = $db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to={$devId} AND status='UNDER REVIEW' AND is_deleted=0")->fetchColumn();
                $revision = $db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to={$devId} AND (status='REVISION REQUIRED' OR status='CLIENT REVISION REQUESTED') AND is_deleted=0")->fetchColumn();
                $completed = $db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to={$devId} AND status='COMPLETED' AND is_deleted=0")->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'role' => 'Developer',
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

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid API action requested.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
