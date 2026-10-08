<?php
// client/access.php - Secure Magic Link Access Token Processor

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$rawToken = trim($_GET['token'] ?? '');
$errorMessage = '';

if (!empty($rawToken)) {
    $db = getDbConnection();
    $tokenHash = hash('sha256', $rawToken);

    $stmt = $db->prepare("
        SELECT ct.*, u.id as user_id, u.username, u.full_name, u.email, c.company_name
        FROM client_tokens ct
        JOIN clients c ON ct.client_id = c.id
        JOIN users u ON u.client_id = c.id
        WHERE ct.token_hash = ? AND ct.used_at IS NULL AND ct.expires_at > CURRENT_TIMESTAMP
        ORDER BY u.id ASC LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $tokenRecord = $stmt->fetch();

    if ($tokenRecord) {
        // Mark token as used
        $stmtUpd = $db->prepare("UPDATE client_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmtUpd->execute([$tokenRecord['id']]);

        // Set Client Session
        $_SESSION['user_id'] = $tokenRecord['user_id'];
        $_SESSION['role'] = 'CLIENT';
        $_SESSION['client_id'] = $tokenRecord['client_id'];
        $_SESSION['module'] = 'client_dashboard';

        $_SESSION['user'] = [
            'id' => $tokenRecord['user_id'],
            'username' => $tokenRecord['username'],
            'full_name' => $tokenRecord['full_name'],
            'email' => $tokenRecord['email'],
            'role' => 'CLIENT',
            'client_id' => $tokenRecord['client_id'],
            'company_name' => $tokenRecord['company_name'],
            'allowed_modules' => ['client_dashboard']
        ];

        logActivity($tokenRecord['user_id'], 'client_dashboard', 'MAGIC_LINK_LOGIN', 'client_tokens', $tokenRecord['id'], "Client authenticated via secure SMS access token");

        header('Location: /client/dashboard.php');
        exit;
    } else {
        $errorMessage = 'Invalid or expired secure access link. Access tokens are single-use and expire after 48 hours.';
    }
} else {
    $errorMessage = 'No access token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Access — HemiFlow Client Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&family=Google+Sans+Text:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&display=swap" rel="stylesheet">
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
            font-family: 'Google Sans', 'Google Sans Text', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 20px;
        }
        .card-box {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body>
    <div class="card-box">
        <div style="width:64px; height:64px; background:rgba(239,68,68,0.15); border:2px solid #ef4444; color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px auto;">
            <i data-feather="key" style="width:32px; height:32px;"></i>
        </div>
        <h2 style="font-size:22px; font-weight:800; margin-bottom:12px;">Access Link Expired</h2>
        <p style="color:#94a3b8; font-size:14px; line-height:1.6; margin-bottom:24px;"><?= htmlspecialchars($errorMessage) ?></p>
        <a href="/client/login.php" class="btn btn-primary" style="padding:12px 24px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
            <i data-feather="log-in"></i> Go to Client Login
        </a>
    </div>
    <script>if(window.feather) feather.replace();</script>
</body>
</html>
