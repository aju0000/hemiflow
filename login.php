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
        .module-tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .module-tab {
            flex: 1;
            padding: 12px 6px;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        .module-tab.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
            background: #ffffff;
        }
        .quick-user-btn {
            display: block;
            width: 100%;
            text-align: left;
            padding: 8px 12px;
            margin-bottom: 6px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .quick-user-btn:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header" style="display:flex; flex-direction:column; align-items:center;">
        <img src="asset/Hemiflow Blue Wave Logo.png" alt="HemiFlow Logo" style="height:48px; max-width:180px; object-fit:contain; margin-bottom:10px;">
        <h2 style="font-size: 24px; margin-bottom: 4px; font-weight:800;">HemiFlow</h2>
        <p style="color: #94a3b8; font-size: 13px;">Centralized Agency Portal & Module Access</p>
    </div>


    <!-- Module Access Selection Tabs -->
    <div class="module-tabs">
        <a href="login.php?module=sales" class="module-tab <?= $module === 'sales' ? 'active' : '' ?>">Sales / CRM</a>
        <a href="login.php?module=client-management" class="module-tab <?= $module === 'client-management' ? 'active' : '' ?>">Clients</a>
        <a href="login.php?module=template-generator" class="module-tab <?= ($module === 'template-generator' || !$module) ? 'active' : '' ?>">Template Gen</a>
        <a href="login.php?module=task-management" class="module-tab <?= $module === 'task-management' ? 'active' : '' ?>">Tasks</a>
        <a href="login.php?module=reports" class="module-tab <?= $module === 'reports' ? 'active' : '' ?>">Reports</a>
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
                <input type="text" name="username" id="username" class="form-control" placeholder="e.g. sales_john" required>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; margin-top: 10px;">
                Log In to <?= $module ? strtoupper(htmlspecialchars($module)) : 'Agency OS' ?>
            </button>
        </form>

        <!-- Quick Demo Role Switcher -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px dashed #cbd5e1;">
            <p style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 10px;">⚡ QUICK DEMO USER LOGIN (Click to autofill):</p>
            
            <button type="button" class="quick-user-btn" onclick="fillUser('admin', 'password123')">
                👑 <strong>Super Admin</strong> (admin) &rarr; All Modules Access
            </button>
            <button type="button" class="quick-user-btn" onclick="fillUser('sales_john', 'password123')">
                💼 <strong>Sales Manager</strong> (sales_john) &rarr; Sales, Client & Template Generator
            </button>
            <button type="button" class="quick-user-btn" onclick="fillUser('lead_ajmal', 'password123')">
                🛠️ <strong>Team Lead</strong> (lead_ajmal) &rarr; Task & Project Management
            </button>
            <button type="button" class="quick-user-btn" onclick="fillUser('dev_rahul', 'password123')">
                💻 <strong>Developer</strong> (dev_rahul) &rarr; Assigned Tasks Only
            </button>
            <button type="button" class="quick-user-btn" onclick="fillUser('reports_sarah', 'password123')">
                📊 <strong>Reports Admin</strong> (reports_sarah) &rarr; Analytics & BI Dashboard
            </button>
        </div>
    </div>
</div>

<script>
    function fillUser(user, pass) {
        document.getElementById('username').value = user;
        document.getElementById('password').value = pass;
    }
</script>
</body>
</html>
