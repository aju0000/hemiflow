<?php
// client/includes/client_auth.php - Client Portal Authorization Middleware & Ownership Verification

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';

/**
 * Require active Client session. Redirects to /client/login.php if unauthorized.
 */
function requireClientAuth() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'CLIENT' || empty($_SESSION['client_id'])) {
        if (isset($_GET['action']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized client access. Please login.']);
            exit;
        } else {
            header('Location: /client/login.php');
            exit;
        }
    }
}

/**
 * Fetch current client details from DB
 */
function getClientPortalUser() {
    requireClientAuth();
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT c.*, u.username, u.email as user_email, u.full_name as contact_name FROM clients c JOIN users u ON u.client_id = c.id WHERE c.id = ?");
    $stmt->execute([$_SESSION['client_id']]);
    return $stmt->fetch() ?: [
        'id' => $_SESSION['client_id'],
        'company_name' => $_SESSION['user']['company_name'] ?? 'Client Portal',
        'contact_person' => $_SESSION['user']['full_name'] ?? 'Valued Client',
        'email' => $_SESSION['user']['email'] ?? ''
    ];
}

/**
 * Strictly verify that a database resource (project, task, document, meeting, notification)
 * belongs to the currently logged-in client.
 * Returns 403 Access Denied if ownership verification fails.
 */
function verifyClientOwnership($tableName, $recordId, $clientIdColumn = 'client_id') {
    requireClientAuth();
    $db = getDbConnection();
    $recordId = intval($recordId);
    $clientId = intval($_SESSION['client_id']);

    if ($recordId <= 0) {
        denyClientAccess("Invalid resource identifier specified.");
    }

    $allowedTables = ['projects', 'tasks', 'documents', 'client_meetings', 'client_notifications', 'client_approvals', 'task_attachments'];
    if (!in_array($tableName, $allowedTables)) {
        denyClientAccess("Access prohibited to internal system entity.");
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM {$tableName} WHERE id = ? AND {$clientIdColumn} = ?");
    $stmt->execute([$recordId, $clientId]);
    
    if ($stmt->fetchColumn() == 0) {
        denyClientAccess("403 Access Denied: You do not have permission to access or view this resource.");
    }
}

/**
 * Output client-safe 403 error page
 */
function denyClientAccess($message = "403 Access Denied") {
    if (isset($_GET['action']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    die('<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 Access Denied — Client Portal</title>
        <link rel="stylesheet" href="/styles.css">
        <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
        <style>
            body {
                background: #0f172a;
                color: #ffffff;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                padding: 20px;
            }
            .access-card {
                background: #1e293b;
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 16px;
                padding: 40px;
                max-width: 480px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
            }
        </style>
    </head>
    <body>
        <div class="access-card">
            <div style="width:64px; height:64px; background:rgba(239,68,68,0.15); border:2px solid #ef4444; color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px auto;">
                <i data-feather="shield-off" style="width:32px; height:32px;"></i>
            </div>
            <h2 style="font-size:24px; font-weight:800; margin-bottom:12px;">403 — Access Restricted</h2>
            <p style="color:#94a3b8; font-size:14px; line-height:1.6; margin-bottom:24px;">' . htmlspecialchars($message) . '</p>
            <a href="/client/dashboard.php" class="btn btn-primary" style="padding:10px 20px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                <i data-feather="home"></i> Return to Client Dashboard
            </a>
        </div>
        <script>if(window.feather) feather.replace();</script>
    </body>
    </html>');
}
