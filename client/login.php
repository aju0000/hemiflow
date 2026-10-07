<?php
// client/login.php - Dedicated Client Portal Login Interface

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth.php';

$error = '';
$successMsg = '';

// If already logged in as client, redirect directly to dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'CLIENT' && !empty($_SESSION['client_id'])) {
    header('Location: /client/dashboard.php');
    exit;
}

if (isset($_GET['logout'])) {
    $successMsg = 'You have logged out of your Client Portal.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter your Mobile Number / Email and Password.';
    } else {
        $res = loginClientUser($identifier, $password);
        if ($res['success']) {
            header('Location: /client/dashboard.php');
            exit;
        } else {
            $error = $res['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal Login — HemiFlow</title>
    <link rel="stylesheet" href="/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
        }
        .client-login-card {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            overflow: hidden;
        }
        .client-login-header {
            background: #0f172a;
            color: #ffffff;
            padding: 35px 30px;
            text-align: center;
            position: relative;
        }
        .client-login-body {
            padding: 30px;
        }
        .portal-badge {
            display: inline-block;
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.3);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }
        .quick-client-btn {
            display: block;
            width: 100%;
            text-align: left;
            padding: 10px 14px;
            margin-top: 8px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .quick-client-btn:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
        }
    </style>
</head>
<body>

<div class="client-login-card">
    <div class="client-login-header">
        <img src="/asset/Hemiflow Blue Wave Logo.png" alt="HemiFlow Logo" style="height:48px; max-width:180px; object-fit:contain; margin-bottom:12px;">
        <h2 style="font-size: 24px; margin: 0; font-weight:800; color:#ffffff;">HemiFlow Portal</h2>
        <div class="portal-badge">Restricted Client Access Portal</div>
    </div>

    <div class="client-login-body">
        <?php if ($error): ?>
            <div style="background: #fee2e2; border-left: 4px solid #dc2626; color: #991b1b; padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div style="background: #dcfce7; border-left: 4px solid #16a34a; color: #166534; padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 20px;">
                <?= htmlspecialchars($successMsg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/client/login.php">
            <div class="form-group" style="margin-bottom: 18px;">
                <label class="form-label" style="font-weight:600; font-size:13px; color:#334155;">Mobile Number or Email</label>
                <div style="position:relative;">
                    <input type="text" name="identifier" id="identifier" class="form-control" placeholder="e.g. rajesh@abccompany.com or +91 98765 43210" required style="padding-left:38px;">
                    <i data-feather="mail" style="position:absolute; left:12px; top:12px; width:16px; height:16px; color:#94a3b8;"></i>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" style="font-weight:600; font-size:13px; color:#334155;">Password</label>
                <div style="position:relative;">
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required style="padding-left:38px;">
                    <i data-feather="lock" style="position:absolute; left:12px; top:12px; width:16px; height:16px; color:#94a3b8;"></i>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size:14px; font-weight:700;">
                <i data-feather="log-in" style="width:16px; height:16px;"></i> Log In to Client Dashboard
            </button>
        </form>

        <!-- Quick Demo Client Login Switcher -->
        <div style="margin-top: 28px; padding-top: 20px; border-top: 1px dashed #cbd5e1;">
            <p style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px;">⚡ QUICK CLIENT DEMO LOGIN:</p>
            
            <button type="button" class="quick-client-btn" onclick="fillClient('rajesh@abccompany.com', 'password123')">
                🏢 <strong>ABC Company Client Account</strong><br>
                <span style="color:#64748b; font-size:11px;">User: rajesh@abccompany.com / Pass: password123</span>
            </button>

            <button type="button" class="quick-client-btn" onclick="fillClient('client_abc', 'password123')">
                📱 <strong>Mobile Access Username Login</strong><br>
                <span style="color:#64748b; font-size:11px;">User: client_abc / Pass: password123</span>
            </button>
        </div>
    </div>
</div>

<script>
    function fillClient(user, pass) {
        document.getElementById('identifier').value = user;
        document.getElementById('password').value = pass;
    }
    if (window.feather) feather.replace();
</script>
</body>
</html>
