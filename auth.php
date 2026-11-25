<?php
// auth.php - Centralized Authentication & Module Permission Enforcement

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Authenticate user credentials and check module permission if specified.
 */
function loginUser($username, $password, $requiredModule = null) {
    $db = getDbConnection();
    
    $cleanUsername = ltrim(trim($username), '@');
    $stmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? OR u.username = ? OR u.email = ?");
    $stmt->execute([$username, $cleanUsername, $username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid username/email or password.'];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'User account is deactivated.'];
    }

    // Get user module access
    $stmtMod = $db->prepare("SELECT module_code, can_view, can_edit, can_delete FROM user_module_access WHERE user_id = ?");
    $stmtMod->execute([$user['id']]);
    $accessList = $stmtMod->fetchAll();

    $allowedModules = [];
    foreach ($accessList as $acc) {
        if ($acc['can_view']) {
            $allowedModules[] = $acc['module_code'];
        }
    }

    // Super Admin has access to all modules
    if ($user['role_name'] === 'Super Admin') {
        $allowedModules = ['sales', 'client-management', 'template-generator', 'task-management', 'reports', 'user-management', 'hr-management'];
    }

    if ($user['role_name'] === 'HR') {
        if (!in_array('hr-management', $allowedModules)) {
            $allowedModules[] = 'hr-management';
        }
    }

    // Sales team does not need access to Task Management dashboard/module
    if (stripos($user['role_name'] ?? '', 'Sales') !== false && $user['role_name'] !== 'Super Admin') {
        $allowedModules = array_values(array_filter($allowedModules, function ($m) {
            return $m !== 'task-management';
        }));
    }

    // Backend Module Permission Enforcement
    if ($requiredModule && !in_array($requiredModule, $allowedModules) && $user['role_name'] !== 'Super Admin') {
        return [
            'success' => false,
            'message' => "Access Denied: Your role ({$user['role_name']}) does not have permission to access the " . strtoupper($requiredModule) . " module."
        ];
    }

    
    // Set Session
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role_name'],
        'role_id' => $user['role_id'],
        'department' => $user['department'],
        'allowed_modules' => $allowedModules
    ];

    // Log login activity
    logActivity($user['id'], 'auth', 'LOGIN', 'users', $user['id'], "User logged into " . ($requiredModule ? $requiredModule : "system"));

    return [
        'success' => true,
        'user' => $_SESSION['user']
    ];
}

/**
 * Get current logged in user
 */
function getCurrentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * Check if current user has a Sales role (non-Super Admin)
 */
function isSalesUser($user = null) {
    if ($user === null) $user = getCurrentUser();
    if (!$user) return false;
    $role = trim($user['role'] ?? ($user['role_name'] ?? ''));
    if (in_array($role, ['Super Admin', 'System Super Admin'], true) || intval($user['role_id'] ?? 0) === 1) {
        return false;
    }
    return stripos($role, 'Sales') !== false;
}

/**
 * Check if current user has access to a specific module code
 */
function hasModuleAccess($moduleCode) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Super Admin has unrestricted access to all modules
    if (isset($user['role']) && $user['role'] === 'Super Admin') {
        return true;
    }

    // Sales team does not see or access the Task Management module/dashboard
    if ($moduleCode === 'task-management' && isSalesUser($user)) {
        return false;
    }

    // Admin / User Management restriction
    if ($moduleCode === 'user-management' || $moduleCode === 'users') {
        return isset($user['role']) && in_array($user['role'], ['Super Admin', 'Admin']);
    }

    // Module 5: HR Management access rule
    // Internal agency staff (Super Admin, HR, Leads, Developers, Sales) have access to HR (admin or employee self-service).
    // Clients are strictly denied.
    if ($moduleCode === 'hr-management' || $moduleCode === 'hr') {
        if (isset($user['role']) && ($user['role'] === 'Client' || !empty($user['client_id']))) {
            return false;
        }
        return true;
    }

    if (!isset($user['allowed_modules']) || !is_array($user['allowed_modules'])) {
        return false;
    }

    return in_array($moduleCode, $user['allowed_modules']);
}

/**
 * Check if current user has HR administrative privileges (Super Admin, Agency Admin, HR role)
 */
function isHrAdmin() {
    $user = getCurrentUser();
    if (!$user) return false;
    if (isset($user['role']) && in_array($user['role'], ['Super Admin', 'Agency Admin', 'HR'])) {
        return true;
    }
    return false;
}

/**
 * Check if current user is a Team Lead or Manager (includes DM Lead and any *Lead* role)
 */
function isTeamLeadOrManager() {
    $user = getCurrentUser();
    if (!$user) return false;
    if (isHrAdmin()) return true;
    $role = $user['role'] ?? '';
    $roleId = intval($user['role_id'] ?? 0);
    if (in_array($role, ['Team Lead', 'DM Lead', 'Manager', 'Admin']) || in_array($roleId, [1, 2, 4, 18])) {
        return true;
    }
    return stripos($role, 'Lead') !== false;
}

/**
 * Check if current user has Lead or Admin privileges for project & task management
 */
function isLeadOrAdmin() {
    $user = getCurrentUser();
    if (!$user) return false;
    $role = $user['role'] ?? '';
    $roleId = intval($user['role_id'] ?? 0);
    if (in_array($role, ['Super Admin', 'Agency Admin', 'Admin', 'Team Lead', 'DM Lead', 'Manager']) || in_array($roleId, [1, 2, 4, 18])) {
        return true;
    }
    return stripos($role, 'Lead') !== false;
}

/**
 * Get chat and group creation privileges for a user (live from DB so admin updates apply immediately)
 */
function getUserChatPrivileges($userId = null) {
    $user = getCurrentUser();
    $uid = $userId ? intval($userId) : intval($user['id'] ?? 0);
    if ($uid <= 0) {
        return ['can_chat' => false, 'can_create_group' => false];
    }
    $db = getDbConnection();
    try {
        $stmt = $db->prepare("SELECT role_id, is_active, COALESCE(can_chat, 1) as can_chat, COALESCE(can_create_group, 1) as can_create_group FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!$row || !$row['is_active'] || intval($row['role_id']) === 7) {
            return ['can_chat' => false, 'can_create_group' => false];
        }
        return [
            'can_chat' => intval($row['can_chat']) === 1,
            'can_create_group' => intval($row['can_chat']) === 1 && intval($row['can_create_group']) === 1
        ];
    } catch (Exception $e) {
        $isClient = ($user && (($user['role'] ?? '') === 'Client' || ($user['role'] ?? '') === 'CLIENT' || intval($user['role_id'] ?? 0) === 7));
        return ['can_chat' => !$isClient, 'can_create_group' => !$isClient];
    }
}

/**
 * Get current employee record for logged-in user
 */
function getCurrentEmployee() {
    $user = getCurrentUser();
    if (!$user) return null;
    $db = getDbConnection();
    try {
        $stmt = $db->prepare("SELECT e.*, d.name as department_name, des.title as designation_title,
                              m.full_name as reporting_manager_name
                              FROM employees e
                              LEFT JOIN departments d ON e.department_id = d.id
                              LEFT JOIN designations des ON e.designation_id = des.id
                              LEFT JOIN employees m ON e.reporting_manager_id = m.id
                              WHERE e.user_id = ?");
        $stmt->execute([$user['id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Authenticate client user credentials (Mobile / Email / Username)
 */
function loginClientUser($identifier, $password) {
    $db = getDbConnection();
    
    $cleanIdentifier = ltrim(trim($identifier), '@');
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name, c.company_name, c.phone as client_phone
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        LEFT JOIN clients c ON u.client_id = c.id
        WHERE (u.username = ? OR u.username = ? OR u.email = ? OR (c.phone IS NOT NULL AND c.phone != '' AND c.phone = ?))
          AND (r.name = 'Client' OR u.client_id IS NOT NULL)
    ");
    $stmt->execute([$identifier, $cleanIdentifier, $identifier, $identifier]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email, mobile number, or password.'];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Your client portal account is deactivated. Please contact support.'];
    }

    if (empty($user['client_id'])) {
        return ['success' => false, 'message' => 'No client profile associated with this account.'];
    }

    // Set Session Variables as per Module Spec
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = 'CLIENT';
    $_SESSION['client_id'] = $user['client_id'];
    $_SESSION['module'] = 'client_dashboard';
    
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => 'CLIENT',
        'role_id' => $user['role_id'],
        'client_id' => $user['client_id'],
        'company_name' => $user['company_name'] ?? 'Client Account',
        'allowed_modules' => ['client_dashboard']
    ];

    logActivity($user['id'], 'client_dashboard', 'CLIENT_LOGIN', 'users', $user['id'], "Client logged into Client Portal Dashboard");

    return [
        'success' => true,
        'user' => $_SESSION['user']
    ];
}

/**
 * Require active login or throw 401/redirect
 */
function checkAuth($requiredModule = null) {
    $user = getCurrentUser();
    if (!$user) {
        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
            exit;
        } else {
            $redirect = $requiredModule ? "login.php?module={$requiredModule}" : "login.php";
            header("Location: {$redirect}");
            exit;
        }
    }

    // STRICT SERVER-SIDE AUTHORIZATION: Deny Client Role Access to ALL Internal Agency Modules
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'CLIENT') {
        if ($requiredModule !== 'client_dashboard') {
            if (isApiRequest()) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => '403 Forbidden: Client sessions are restricted strictly to the Client Dashboard portal.']);
                exit;
            } else {
                header('Location: /client/dashboard.php');
                exit;
            }
        }
    }

    if ($requiredModule && $requiredModule !== 'client_dashboard' && !hasModuleAccess($requiredModule)) {
        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => "Forbidden: Access denied to module {$requiredModule}."]);
            exit;
        } else {
            $userRole = htmlspecialchars($user['role']);
            $reqModName = htmlspecialchars(ucwords(str_replace('-', ' ', $requiredModule)));
            
            $allowedPillsHtml = '';
            if (!empty($user['allowed_modules'])) {
                $allowedPillsHtml = '<div style="margin-top:20px; background:rgba(15,23,42,0.4); padding:14px; border-radius:10px; border:1px solid rgba(255,255,255,0.05);"><p style="font-size:11px; color:#94a3b8; margin:0 0 8px 0; text-transform:uppercase; letter-spacing:0.5px; font-weight:700;">Modules Accessible with your Role:</p><div style="display:flex; flex-wrap:wrap; justify-content:center; gap:8px;">';
                $modLinks = [
                    'sales' => ['Sales & CRM', 'index.php'],
                    'client-management' => ['Client Management', 'clients.php'],
                    'template-generator' => ['Proposal', 'template_generator.php'],
                    'task-management' => ['Task Management', 'projects.php'],
                    'reports' => ['Reports & BI', 'reports.php']
                ];
                foreach ($user['allowed_modules'] as $mCode) {
                    if (isset($modLinks[$mCode])) {
                        $allowedPillsHtml .= '<a href="' . $modLinks[$mCode][1] . '" style="background:rgba(56,189,248,0.1); color:#38bdf8; padding:5px 12px; border-radius:20px; text-decoration:none; font-size:12px; border:1px solid rgba(56,189,248,0.3); font-weight:600; transition:all 0.2s;">' . $modLinks[$mCode][0] . '</a>';
                    }
                }
                $allowedPillsHtml .= '</div></div>';
            }

            die('<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>403 Access Denied — HemiFlow</title>
                <link rel="preconnect" href="https://fonts.googleapis.com">
                <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&family=Google+Sans+Text:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&display=swap" rel="stylesheet">
                <link rel="stylesheet" href="styles.css">
                <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
                <style>
                    body {
                        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                        color: #ffffff;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        margin: 0;
                        font-family: "Google Sans", "Google Sans Text", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        padding: 20px;
                    }
                    .access-denied-card {
                        background: rgba(30, 41, 59, 0.85);
                        border: 1px solid rgba(255, 255, 255, 0.1);
                        backdrop-filter: blur(12px);
                        border-radius: 16px;
                        padding: 45px 35px;
                        max-width: 520px;
                        width: 100%;
                        text-align: center;
                        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                    }
                    .shield-icon-wrap {
                        width: 76px;
                        height: 76px;
                        background: rgba(239, 68, 68, 0.15);
                        border: 2px solid rgba(239, 68, 68, 0.4);
                        color: #ef4444;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin: 0 auto 20px auto;
                        box-shadow: 0 0 25px rgba(239, 68, 68, 0.2);
                    }
                </style>
            </head>
            <body>
                <div class="access-denied-card">
                    <img src="asset/Hemiflow Blue Wave Logo.png" alt="HemiFlow Logo" style="height:46px; max-width:180px; object-fit:contain; margin-bottom:20px;">
                    
                    <div class="shield-icon-wrap">
                        <i data-feather="shield-off" style="width:36px; height:36px;"></i>
                    </div>

                    <h1 style="font-size: 26px; font-weight: 800; color: #ffffff; margin: 0 0 10px 0; letter-spacing:-0.5px;">403 — Access Denied</h1>
                    
                    <p style="color: #94a3b8; font-size: 14px; line-height: 1.6; margin-bottom: 20px;">
                        Your role (<strong style="color:#f87171;">' . $userRole . '</strong>) does not have authorization to view the <strong style="color:#38bdf8;">' . $reqModName . '</strong> module.
                    </p>

                    ' . $allowedPillsHtml . '

                    <div style="display:flex; gap:12px; justify-content:center; margin-top:28px; flex-wrap:wrap;">
                        <a href="index.php" class="btn btn-primary" style="padding:10px 20px; text-decoration:none;">
                            <i data-feather="grid"></i> Return to Portal Hub
                        </a>
                        <a href="login.php" class="btn btn-outline" style="padding:10px 20px; color:#ffffff; border-color:rgba(255,255,255,0.2); text-decoration:none;">
                            <i data-feather="user"></i> Switch Role / Login
                        </a>
                    </div>
                </div>
                <script>if(window.feather) feather.replace();</script>
            </body>
            </html>');

        }
    }
}

/**
 * Log activity helper
 */
function logActivity($userId, $module, $action, $entityType = null, $entityId = null, $details = null) {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, module, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt->execute([$userId, $module, $action, $entityType, $entityId, $details, $ip]);
    } catch (Exception $e) {
        // Silently log or ignore
    }
}

function isApiRequest() {
    return isset($_GET['action']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

/**
 * Automatically provision or link a Client Portal user account for a client with custom or distinct credentials.
 */
function autoProvisionClientUser($db, $clientId, $customUsername = null, $customPassword = null) {
    if (!$clientId) return null;

    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();
    if (!$client) return null;

    // Check if client user account already exists specifically for THIS client
    $stmtUser = $db->prepare("
        SELECT u.* FROM users u
        WHERE u.client_id = ? 
           OR (u.email IS NOT NULL AND u.email != '' AND u.email = ? AND (u.client_id IS NULL OR u.client_id = ?))
        LIMIT 1
    ");
    $stmtUser->execute([$clientId, $client['email'], $clientId]);
    $existingUser = $stmtUser->fetch();

    if ($existingUser) {
        // Ensure client_id is set
        if (empty($existingUser['client_id'])) {
            $stmtUpd = $db->prepare("UPDATE users SET client_id = ? WHERE id = ?");
            $stmtUpd->execute([$clientId, $existingUser['id']]);
        }

        // If custom password provided, update user password
        if (!empty($customPassword)) {
            $passHash = password_hash($customPassword, PASSWORD_BCRYPT);
            $stmtPass = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmtPass->execute([$passHash, $existingUser['id']]);
        }

        // Ensure client_dashboard access row exists
        $stmtChkAcc = $db->prepare("SELECT COUNT(*) FROM user_module_access WHERE user_id = ? AND module_code = 'client_dashboard'");
        $stmtChkAcc->execute([$existingUser['id']]);
        if ($stmtChkAcc->fetchColumn() == 0) {
            $stmtAcc = $db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (?, 'client_dashboard', 1, 1, 0)");
            $stmtAcc->execute([$existingUser['id']]);
        }

        return [
            'created' => false,
            'user_id' => $existingUser['id'],
            'username' => $existingUser['username'],
            'email' => $existingUser['email'],
            'temp_password' => !empty($customPassword) ? $customPassword : '(Existing Password Preserved)',
            'is_existing' => true
        ];
    }

    // Get Role ID for Client
    $stmtRole = $db->prepare("SELECT id FROM roles WHERE name = 'Client'");
    $stmtRole->execute();
    $clientRoleId = $stmtRole->fetchColumn() ?: 7;

    // Determine Username
    if (!empty($customUsername)) {
        $usernameCandidate = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($customUsername));
        if (empty($usernameCandidate)) {
            $usernameCandidate = 'client_' . $clientId;
        }
    } else {
        $cleanComp = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $client['company_name']));
        if (empty($cleanComp)) {
            $cleanComp = 'client';
        }
        $usernameCandidate = 'client_' . $cleanComp;
    }

    $username = $usernameCandidate;
    $suffix = 1;
    while (true) {
        $stmtChk = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmtChk->execute([$username]);
        if ($stmtChk->fetchColumn() == 0) {
            break;
        }
        $username = $usernameCandidate . '_' . $suffix;
        $suffix++;
    }

    // Ensure email is set & unique
    $email = trim($client['email']);
    if (empty($email)) {
        $email = $username . '@clientportal.agency';
    } else {
        $stmtChkE = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND client_id != ?");
        $stmtChkE->execute([$email, $clientId]);
        if ($stmtChkE->fetchColumn() > 0) {
            $emailParts = explode('@', $email);
            $email = $emailParts[0] . '+client' . $clientId . '@' . ($emailParts[1] ?? 'agency.com');
        }
    }

    // Determine Password (custom or unique random password per client)
    if (!empty($customPassword)) {
        $defaultPassword = $customPassword;
    } else {
        // Generate a unique, distinct password for this client
        $randNum = sprintf("%04d", rand(1000, 9999));
        $defaultPassword = 'Client#' . $randNum;
    }

    $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);
    $fullName = !empty($client['contact_person'])
        ? ($client['contact_person'] . ' (' . $client['company_name'] . ')')
        : $client['company_name'];

    // Insert user row
    $stmtIns = $db->prepare("
        INSERT INTO users (username, password_hash, full_name, email, role_id, client_id, department, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 'Client Portal', 1)
    ");
    $stmtIns->execute([$username, $passwordHash, $fullName, $email, $clientRoleId, $clientId]);
    $newUserId = $db->lastInsertId();

    // Grant client_dashboard module access
    $stmtAcc = $db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (?, 'client_dashboard', 1, 1, 0)");
    $stmtAcc->execute([$newUserId]);

    logActivity(1, 'user-management', 'AUTO_CREATE_CLIENT_USER', 'users', $newUserId, "Auto-created client user account '{$username}' for client #{$clientId} ({$client['company_name']})");

    return [
        'created' => true,
        'user_id' => $newUserId,
        'username' => $username,
        'email' => $email,
        'temp_password' => $defaultPassword,
        'is_existing' => false
    ];
}

function ensureProjectWorkflowSchema($db = null) {
    static $done = false;
    if ($done) return;
    $done = true;

    if (!$db) $db = getDbConnection();
    // Skip DDL write locks when already migrated (crucial for SQLite concurrency)
    if (!defined('DB_TYPE') || DB_TYPE !== 'mysql') {
        try {
            $hasProjCol = false;
            $hasProjObsCol = false;
            $hasProjWorkCol = false;
            foreach ($db->query("PRAGMA table_info(projects)")->fetchAll() as $col) {
                if (($col['name'] ?? '') === 'client_approved_at') $hasProjCol = true;
                if (($col['name'] ?? '') === 'observers_json') $hasProjObsCol = true;
                if (($col['name'] ?? '') === 'working_employees_json') $hasProjWorkCol = true;
            }
            $hasTaskCol = false;
            $hasTaskLeadCol = false;
            foreach ($db->query("PRAGMA table_info(tasks)")->fetchAll() as $col) {
                if (($col['name'] ?? '') === 'client_approved_at') $hasTaskCol = true;
                if (($col['name'] ?? '') === 'lead_approved_at') $hasTaskLeadCol = true;
            }
            $hasTbl = (bool)$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='project_handlers'")->fetchColumn();
            $hasMembersTbl = (bool)$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='project_members'")->fetchColumn();
            $hasAttachCol = false;
            foreach ($db->query("PRAGMA table_info(task_attachments)")->fetchAll() as $col) {
                if (($col['name'] ?? '') === 'attachment_stage') { $hasAttachCol = true; break; }
            }
            if ($hasProjCol && $hasProjObsCol && $hasProjWorkCol && $hasTaskCol && $hasTaskLeadCol && $hasTbl && $hasMembersTbl && $hasAttachCol) {
                return;
            }
        } catch (Exception $e) {}
    }
    try {
        if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
            $db->exec("CREATE TABLE IF NOT EXISTS project_handlers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                project_id INT NOT NULL,
                user_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_proj_handler (project_id, user_id)
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS project_members (
                id INT PRIMARY KEY AUTO_INCREMENT,
                project_id INT NOT NULL,
                user_id INT NOT NULL,
                added_by INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_proj_member (project_id, user_id),
                INDEX idx_proj_member_user (user_id)
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS task_attachments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                task_id INT NOT NULL,
                user_id INT DEFAULT NULL,
                uploaded_by INT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_url VARCHAR(500) NOT NULL,
                file_path VARCHAR(500) DEFAULT '',
                mime_type VARCHAR(120) DEFAULT '',
                file_size BIGINT DEFAULT 0,
                caption TEXT,
                attachment_stage VARCHAR(50) DEFAULT 'work',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS project_handlers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(project_id, user_id)
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS project_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                added_by INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(project_id, user_id)
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS task_attachments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                user_id INTEGER DEFAULT NULL,
                uploaded_by INTEGER NOT NULL,
                file_name TEXT NOT NULL,
                file_url TEXT NOT NULL,
                file_path TEXT DEFAULT '',
                mime_type TEXT DEFAULT '',
                file_size INTEGER DEFAULT 0,
                caption TEXT,
                attachment_stage TEXT DEFAULT 'work',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {}

    $attachCols = [
        "ALTER TABLE task_attachments ADD COLUMN user_id INTEGER DEFAULT NULL",
        "ALTER TABLE task_attachments ADD COLUMN file_path TEXT DEFAULT ''",
        "ALTER TABLE task_attachments ADD COLUMN mime_type TEXT DEFAULT ''",
        "ALTER TABLE task_attachments ADD COLUMN caption TEXT",
        "ALTER TABLE task_attachments ADD COLUMN attachment_stage VARCHAR(50) DEFAULT 'work'"
    ];
    foreach ($attachCols as $sql) {
        try { $db->exec($sql); } catch (Exception $e) {}
    }

    $projectCols = [
        "ALTER TABLE projects ADD COLUMN handlers_json TEXT",
        "ALTER TABLE projects ADD COLUMN working_employees_json TEXT",
        "ALTER TABLE projects ADD COLUMN observers_json TEXT",
        "ALTER TABLE projects ADD COLUMN client_approved INTEGER DEFAULT 0",
        "ALTER TABLE projects ADD COLUMN client_approved_by INTEGER DEFAULT NULL",
        "ALTER TABLE projects ADD COLUMN client_approved_at DATETIME DEFAULT NULL"
    ];
    foreach ($projectCols as $sql) {
        try { $db->exec($sql); } catch (Exception $e) {}
    }

    $taskCols = [
        "ALTER TABLE tasks ADD COLUMN parent_task_id INTEGER DEFAULT NULL",
        "ALTER TABLE tasks ADD COLUMN task_type VARCHAR(20) DEFAULT 'task'",
        "ALTER TABLE tasks ADD COLUMN handler_approved INTEGER DEFAULT 0",
        "ALTER TABLE tasks ADD COLUMN handler_approved_by INTEGER DEFAULT NULL",
        "ALTER TABLE tasks ADD COLUMN handler_approved_at DATETIME DEFAULT NULL",
        "ALTER TABLE tasks ADD COLUMN lead_approved INTEGER DEFAULT 0",
        "ALTER TABLE tasks ADD COLUMN lead_approved_by INTEGER DEFAULT NULL",
        "ALTER TABLE tasks ADD COLUMN lead_approved_at DATETIME DEFAULT NULL",
        "ALTER TABLE tasks ADD COLUMN client_approved_by_handler INTEGER DEFAULT 0",
        "ALTER TABLE tasks ADD COLUMN client_approved_by INTEGER DEFAULT NULL",
        "ALTER TABLE tasks ADD COLUMN client_approved_at DATETIME DEFAULT NULL"
    ];
    foreach ($taskCols as $sql) {
        try { $db->exec($sql); } catch (Exception $e) {}
    }

    // Backfill default observers (Lead, Super Admin, CRM) onto existing projects & tasks cleanly during migration
    try {
        $projs = $db->query("SELECT id, team_lead_id, observers_json FROM projects")->fetchAll();
        foreach ($projs as $p) {
            $curObs = json_decode($p['observers_json'] ?? '[]', true);
            if (!is_array($curObs)) $curObs = [];
            $defaultObs = getDefaultProjectObserverIds($db, intval($p['team_lead_id'] ?? 0));
            $mergedObs = array_values(array_unique(array_merge(array_map('intval', $curObs), $defaultObs)));
            if (json_encode($mergedObs) !== ($p['observers_json'] ?? '')) {
                $db->prepare("UPDATE projects SET observers_json = ? WHERE id = ?")->execute([json_encode($mergedObs), intval($p['id'])]);
            }
        }
    } catch (Exception $e) {}
}

function getProjectHandlerIds($db, $projectId) {
    $pid = intval($projectId);
    if ($pid <= 0) return [];
    ensureProjectWorkflowSchema($db);
    $ids = [];
    try {
        $st = $db->prepare("SELECT user_id FROM project_handlers WHERE project_id = ?");
        $st->execute([$pid]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            if (intval($uid) > 0) $ids[] = intval($uid);
        }
    } catch (Exception $e) {}

    try {
        $stP = $db->prepare("SELECT handlers_json FROM projects WHERE id = ?");
        $stP->execute([$pid]);
        $row = $stP->fetch();
        if ($row && !empty($row['handlers_json'])) {
            $arr = json_decode($row['handlers_json'], true);
            if (is_array($arr)) {
                foreach ($arr as $uid) {
                    if (intval($uid) > 0) $ids[] = intval($uid);
                }
            }
        }
    } catch (Exception $e) {}

    return array_values(array_unique($ids));
}

function isProjectHandler($db, $projectId, $userId = null) {
    if ($userId === null) {
        $u = getCurrentUser();
        $userId = $u['id'] ?? 0;
    }
    $uid = intval($userId);
    $pid = intval($projectId);
    if ($uid <= 0 || $pid <= 0) return false;
    return in_array($uid, getProjectHandlerIds($db, $pid), true);
}

function getUserHandledProjectIds($db, $userId = null) {
    if ($userId === null) {
        $u = getCurrentUser();
        $userId = $u['id'] ?? 0;
    }
    $uid = intval($userId);
    if ($uid <= 0) return [];
    ensureProjectWorkflowSchema($db);
    $pids = [];
    try {
        $st = $db->prepare("SELECT project_id FROM project_handlers WHERE user_id = ?");
        $st->execute([$uid]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            if (intval($pid) > 0) $pids[] = intval($pid);
        }
    } catch (Exception $e) {}

    try {
        $stP = $db->prepare("SELECT id, handlers_json FROM projects");
        $stP->execute();
        while ($row = $stP->fetch()) {
            $pid = intval($row['id']);
            $arr = json_decode($row['handlers_json'] ?? '[]', true);
            if (is_array($arr) && in_array($uid, array_map('intval', $arr), true)) {
                $pids[] = $pid;
            }
        }
    } catch (Exception $e) {}

    return array_values(array_unique($pids));
}

function canManageProject($db, $projectId, $userId = null) {
    if (isLeadOrAdmin()) return true;
    return isProjectHandler($db, $projectId, $userId);
}

function syncProjectHandlers($db, $projectId, $handlerIds) {
    $pid = intval($projectId);
    if ($pid <= 0) return [];
    ensureProjectWorkflowSchema($db);
    $cleanIds = array_values(array_unique(array_filter(array_map('intval', (array)$handlerIds), function ($x) {
        return $x > 0;
    })));
    try {
        $db->prepare("UPDATE projects SET handlers_json = ? WHERE id = ?")->execute([json_encode($cleanIds), $pid]);
    } catch (Exception $e) {}
    try {
        $db->prepare("DELETE FROM project_handlers WHERE project_id = ?")->execute([$pid]);
        $ins = $db->prepare("INSERT INTO project_handlers (project_id, user_id) VALUES (?, ?)");
        foreach ($cleanIds as $uid) {
            $ins->execute([$pid, $uid]);
        }
    } catch (Exception $e) {}
    return $cleanIds;
}

function getProjectWorkingEmployeeIds($db, $projectId) {
    $pid = intval($projectId);
    if ($pid <= 0) return [];
    ensureProjectWorkflowSchema($db);
    $ids = [];
    try {
        $st = $db->prepare("SELECT user_id FROM project_members WHERE project_id = ?");
        $st->execute([$pid]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            if (intval($uid) > 0) $ids[] = intval($uid);
        }
    } catch (Exception $e) {}

    try {
        $stP = $db->prepare("SELECT working_employees_json FROM projects WHERE id = ?");
        $stP->execute([$pid]);
        $row = $stP->fetch();
        if ($row && !empty($row['working_employees_json'])) {
            $arr = json_decode($row['working_employees_json'], true);
            if (is_array($arr)) {
                foreach ($arr as $uid) {
                    if (intval($uid) > 0) $ids[] = intval($uid);
                }
            }
        }
    } catch (Exception $e) {}

    return array_values(array_unique($ids));
}

function syncProjectWorkingEmployees($db, $projectId, $memberIds, $addedBy = null) {
    $pid = intval($projectId);
    if ($pid <= 0) return [];
    ensureProjectWorkflowSchema($db);
    $cleanIds = array_values(array_unique(array_filter(array_map('intval', (array)$memberIds), function ($x) {
        return $x > 0;
    })));
    try {
        $db->prepare("UPDATE projects SET working_employees_json = ? WHERE id = ?")->execute([json_encode($cleanIds), $pid]);
    } catch (Exception $e) {}
    try {
        $db->prepare("DELETE FROM project_members WHERE project_id = ?")->execute([$pid]);
        $ins = $db->prepare("INSERT INTO project_members (project_id, user_id, added_by) VALUES (?, ?, ?)");
        foreach ($cleanIds as $uid) {
            try { $ins->execute([$pid, $uid, $addedBy ? intval($addedBy) : null]); } catch (Exception $eIns) {}
        }
    } catch (Exception $e) {}
    return $cleanIds;
}

function getUserWorkingProjectIds($db, $userId = null) {
    if ($userId === null) {
        $u = getCurrentUser();
        $userId = $u['id'] ?? 0;
    }
    $uid = intval($userId);
    if ($uid <= 0) return [];
    ensureProjectWorkflowSchema($db);
    $pids = [];
    try {
        $st = $db->prepare("SELECT project_id FROM project_members WHERE user_id = ?");
        $st->execute([$uid]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            if (intval($pid) > 0) $pids[] = intval($pid);
        }
    } catch (Exception $e) {}
    try {
        $stP = $db->prepare("SELECT id, working_employees_json FROM projects");
        $stP->execute();
        while ($row = $stP->fetch()) {
            $pid = intval($row['id']);
            $arr = json_decode($row['working_employees_json'] ?? '[]', true);
            if (is_array($arr) && in_array($uid, array_map('intval', $arr), true)) {
                $pids[] = $pid;
            }
        }
    } catch (Exception $e) {}
    return array_values(array_unique($pids));
}

/**
 * Default observers automatically attached to every project:
 * Team Lead / DM Lead (plus specific $teamLeadId), Super Admin, and CRM.
 * Regular employees are never added here so strict task isolation remains intact.
 */
function getDefaultProjectObserverIds($db, $teamLeadId = 0) {
    $ids = [];
    if (intval($teamLeadId) > 0) {
        $ids[] = intval($teamLeadId);
    }
    try {
        $stmt = $db->query("
            SELECT u.id
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE COALESCE(u.is_active, 1) = 1
              AND r.name IN ('Super Admin', 'CRM', 'Team Lead', 'DM Lead')
        ");
        while ($row = $stmt->fetch()) {
            $uid = intval($row['id'] ?? 0);
            if ($uid > 0) $ids[] = $uid;
        }
    } catch (Exception $e) {}
    return array_values(array_unique(array_filter($ids, function ($x) { return $x > 0; })));
}

/**
 * Return merged list of project observer IDs (saved observers_json + default Lead/SuperAdmin/CRM observers).
 */
function getProjectObserverIds($db, $projectId, $teamLeadId = 0) {
    $pid = intval($projectId);
    $saved = [];
    $projLeadId = intval($teamLeadId);
    if ($pid > 0) {
        try {
            $stmt = $db->prepare("SELECT team_lead_id, observers_json FROM projects WHERE id = ?");
            $stmt->execute([$pid]);
            $p = $stmt->fetch();
            if ($p) {
                if ($projLeadId <= 0) $projLeadId = intval($p['team_lead_id'] ?? 0);
                $decoded = json_decode($p['observers_json'] ?? '[]', true);
                if (is_array($decoded)) {
                    $saved = array_map('intval', $decoded);
                }
            }
        } catch (Exception $e) {}
    }
    $defaults = getDefaultProjectObserverIds($db, $projLeadId);
    return array_values(array_unique(array_filter(array_merge($saved, $defaults), function ($x) { return $x > 0; })));
}

function isSuperAdminUser($user = null) {
    if ($user === null) $user = getCurrentUser();
    if (!$user) return false;
    $role = $user['role'] ?? ($user['role_name'] ?? '');
    $roleId = intval($user['role_id'] ?? 0);
    return in_array($role, ['Super Admin', 'System Super Admin'], true) || $roleId === 1;
}

function ensureOperationsSchema($db = null) {
    static $done = false;
    if ($done) return;
    $done = true;

    if (!$db) $db = getDbConnection();
    try {
        if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
            $db->exec("CREATE TABLE IF NOT EXISTS shoot_schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT DEFAULT NULL,
                client_name VARCHAR(255) NOT NULL,
                shoot_date DATETIME NOT NULL,
                videos_planned INT DEFAULT 1,
                scripts_approved VARCHAR(50) DEFAULT 'Pending',
                shoot_venue VARCHAR(255) DEFAULT '',
                venue_blocked_status VARCHAR(100) DEFAULT 'Pending from Venue Side',
                client_participants_count INT DEFAULT 0,
                shoot_type VARCHAR(100) NOT NULL,
                assigned_employees_json TEXT DEFAULT NULL,
                status VARCHAR(50) DEFAULT 'Scheduled',
                notes TEXT DEFAULT NULL,
                created_by INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_shoot_date (shoot_date),
                INDEX idx_shoot_client (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $db->exec("CREATE TABLE IF NOT EXISTS shoot_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                shoot_id INT NOT NULL,
                user_id INT NOT NULL,
                role_note VARCHAR(100) DEFAULT 'Team Member',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_shoot_user (shoot_id, user_id),
                INDEX idx_shoot_assign_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $db->exec("CREATE TABLE IF NOT EXISTS domain_server_renewals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT DEFAULT NULL,
                client_name VARCHAR(255) NOT NULL,
                contact_number VARCHAR(50) DEFAULT '',
                domain_name VARCHAR(255) NOT NULL,
                domain_expiry DATE DEFAULT NULL,
                domain_charges DECIMAL(10,2) DEFAULT 0.00,
                server_expiry DATE DEFAULT NULL,
                server_charges DECIMAL(10,2) DEFAULT 0.00,
                assigned_employees_json TEXT DEFAULT NULL,
                renewal_status VARCHAR(50) DEFAULT 'Active',
                hosting_provider VARCHAR(150) DEFAULT '',
                notes TEXT DEFAULT NULL,
                created_by INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_domain_expiry (domain_expiry),
                INDEX idx_server_expiry (server_expiry),
                INDEX idx_renewal_client (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $db->exec("CREATE TABLE IF NOT EXISTS renewal_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                renewal_id INT NOT NULL,
                user_id INT NOT NULL,
                role_note VARCHAR(100) DEFAULT 'Assigned Incharge',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_renewal_user (renewal_id, user_id),
                INDEX idx_renewal_assign_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $db->exec("CREATE TABLE IF NOT EXISTS shoot_schedules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER DEFAULT NULL,
                client_name TEXT NOT NULL,
                shoot_date DATETIME NOT NULL,
                videos_planned INTEGER DEFAULT 1,
                scripts_approved TEXT DEFAULT 'Pending',
                shoot_venue TEXT DEFAULT '',
                venue_blocked_status TEXT DEFAULT 'Pending from Venue Side',
                client_participants_count INTEGER DEFAULT 0,
                shoot_type TEXT NOT NULL,
                assigned_employees_json TEXT DEFAULT NULL,
                status TEXT DEFAULT 'Scheduled',
                notes TEXT DEFAULT NULL,
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $db->exec("CREATE TABLE IF NOT EXISTS shoot_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                shoot_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role_note TEXT DEFAULT 'Team Member',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(shoot_id, user_id)
            )");

            $db->exec("CREATE TABLE IF NOT EXISTS domain_server_renewals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER DEFAULT NULL,
                client_name TEXT NOT NULL,
                contact_number TEXT DEFAULT '',
                domain_name TEXT NOT NULL,
                domain_expiry DATE DEFAULT NULL,
                domain_charges REAL DEFAULT 0.00,
                server_expiry DATE DEFAULT NULL,
                server_charges REAL DEFAULT 0.00,
                assigned_employees_json TEXT DEFAULT NULL,
                renewal_status TEXT DEFAULT 'Active',
                hosting_provider TEXT DEFAULT '',
                notes TEXT DEFAULT NULL,
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $db->exec("CREATE TABLE IF NOT EXISTS renewal_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                renewal_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role_note TEXT DEFAULT 'Assigned Incharge',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(renewal_id, user_id)
            )");
        }
    } catch (Exception $e) {}
}

function syncShootAssignments($db, $shootId, $userIds) {
    $sid = intval($shootId);
    if ($sid <= 0) return;
    ensureOperationsSchema($db);
    $cleanIds = array_values(array_unique(array_filter(array_map('intval', (array)$userIds), function ($x) { return $x > 0; })));
    try {
        $db->prepare("DELETE FROM shoot_assignments WHERE shoot_id = ?")->execute([$sid]);
        if (!empty($cleanIds)) {
            $stmt = $db->prepare("INSERT INTO shoot_assignments (shoot_id, user_id, role_note) VALUES (?, ?, 'Team Member')");
            foreach ($cleanIds as $uid) {
                try { $stmt->execute([$sid, $uid]); } catch (Exception $e) {}
            }
        }
        $db->prepare("UPDATE shoot_schedules SET assigned_employees_json = ? WHERE id = ?")->execute([json_encode($cleanIds), $sid]);
    } catch (Exception $e) {}
}

function syncRenewalAssignments($db, $renewalId, $userIds) {
    $rid = intval($renewalId);
    if ($rid <= 0) return;
    ensureOperationsSchema($db);
    $cleanIds = array_values(array_unique(array_filter(array_map('intval', (array)$userIds), function ($x) { return $x > 0; })));
    try {
        $db->prepare("DELETE FROM renewal_assignments WHERE renewal_id = ?")->execute([$rid]);
        if (!empty($cleanIds)) {
            $stmt = $db->prepare("INSERT INTO renewal_assignments (renewal_id, user_id, role_note) VALUES (?, ?, 'Assigned Incharge')");
            foreach ($cleanIds as $uid) {
                try { $stmt->execute([$rid, $uid]); } catch (Exception $e) {}
            }
        }
        $db->prepare("UPDATE domain_server_renewals SET assigned_employees_json = ? WHERE id = ?")->execute([json_encode($cleanIds), $rid]);
    } catch (Exception $e) {}
}

function canAccessOperations($user = null, $db = null) {
    if ($user === null) $user = getCurrentUser();
    if (!$user) return false;
    if (isSuperAdminUser($user)) return true;

    if (!$db) $db = getDbConnection();
    ensureOperationsSchema($db);
    $uid = intval($user['id'] ?? 0);
    if ($uid <= 0) return false;

    try {
        $chkShoot = $db->prepare("SELECT 1 FROM shoot_assignments WHERE user_id = ? LIMIT 1");
        $chkShoot->execute([$uid]);
        if ($chkShoot->fetchColumn()) return true;

        $chkRen = $db->prepare("SELECT 1 FROM renewal_assignments WHERE user_id = ? LIMIT 1");
        $chkRen->execute([$uid]);
        if ($chkRen->fetchColumn()) return true;
    } catch (Exception $e) {}

    return false;
}



