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
        $allowedModules = ['sales', 'client-management', 'template-generator', 'task-management', 'reports', 'user-management'];
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
 * Check if current user has access to a specific module code
 */
function hasModuleAccess($moduleCode) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Super Admin has unrestricted access to all modules
    if (isset($user['role']) && $user['role'] === 'Super Admin') {
        return true;
    }

    // Admin / User Management restriction
    if ($moduleCode === 'user-management' || $moduleCode === 'users') {
        return isset($user['role']) && in_array($user['role'], ['Super Admin', 'Admin']);
    }

    if (!isset($user['allowed_modules']) || !is_array($user['allowed_modules'])) {
        return false;
    }

    return in_array($moduleCode, $user['allowed_modules']);
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
                    'template-generator' => ['Template Generator', 'template_generator.php'],
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


