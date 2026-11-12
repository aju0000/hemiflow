<?php
// login.php - Centralized Module-Level Authentication Interface

require_once __DIR__ . '/auth.php';

$module = $_GET['module'] ?? $_POST['module'] ?? null;
$error = '';
$successMsg = '';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    session_start();
    $successMsg = 'You have been logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $module = $_POST['module'] ?? null;

    $res = loginUser($username, $password, $module);
    if ($res['success']) {
        if ($module === 'template-generator' || !$module) {
            header('Location: template_generator.php');
        } elseif ($module === 'task-management') {
            header('Location: projects.php');
        } elseif ($module === 'hr-management') {
            header('Location: hr.php');
        } elseif ($module === 'reports') {
            header('Location: reports.php');
        } elseif ($module === 'client-management' || $module === 'sales') {
            header('Location: clients.php');
        } else {
            header('Location: index.php');
        }
        exit;
    } else {
        $error = $res['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HemiFlow - Portal Login</title>
    <!-- Google Sans Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&family=Google+Sans+Text:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 12px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .login-header {
            background: #0f172a;
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header" style="display:flex; flex-direction:column; align-items:center;">
        <img src="asset/Hemiflow Blue Wave Logo.png" alt="HemiFlow Logo" style="height:48px; max-width:180px; object-fit:contain; margin-bottom:10px;">
        <h2 style="font-size: 24px; margin-bottom: 4px; font-weight:800;">HemiFlow</h2>
        <p style="color: #94a3b8; font-size: 13px;">Centralized Agency Portal</p>
    </div>

    <div class="login-body">
        <?php if ($error): ?>
            <div style="background: #fee2e2; border-left: 4px solid #dc2626; color: #991b1b; padding: 12px; border-radius: 4px; font-size: 13px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div style="background: #dcfce7; border-left: 4px solid #16a34a; color: #166534; padding: 12px; border-radius: 4px; font-size: 13px; margin-bottom: 20px;">
                <?= htmlspecialchars($successMsg) ?>
            </div>
        <?php endif; ?>

        <?php if ($module): ?>
            <div style="background: #eff6ff; color: #1e40af; padding: 8px 12px; border-radius: 6px; font-size: 12px; margin-bottom: 16px; font-weight: 600;">
                Target Module: <?= strtoupper(htmlspecialchars($module)) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="module" value="<?= htmlspecialchars($module ?? '') ?>">
            
            <div class="form-group">
                <label class="form-label">Username or Email</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username or email" required>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; margin-top: 10px;">
                Log In to <?= $module ? strtoupper(htmlspecialchars($module)) : 'Agency OS' ?>
            </button>
        </form>
    </div>
</div>
</body>
</html>
