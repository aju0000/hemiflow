<?php
// setup_hostinger.php - 1-Click Hostinger Live Installer Script

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = trim($_POST['db_pass'] ?? '');

    if (empty($dbName) || empty($dbUser)) {
        $error = "Please provide Hostinger Database Name and Database Username.";
    } else {
        try {
            // Test Connection to Hostinger MySQL
            $dsn = "mysql:host={$dbHost};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}`; USE `{$dbName}`;");

            // Read SQL Dump
            $sqlFile = __DIR__ . '/hostinger_agency_os.sql';
            if (!file_exists($sqlFile)) {
                $error = "SQL Dump file hostinger_agency_os.sql not found!";
            } else {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);

                // Re-seed Document Templates
                if (file_exists(__DIR__ . '/seed_templates.php')) {
                    include __DIR__ . '/seed_templates.php';
                }

                // Update db.php file contents
                $dbPhpContent = "<?php\n"
                    . "define('DB_TYPE', 'mysql');\n"
                    . "define('DB_HOST', '" . addslashes($dbHost) . "');\n"
                    . "define('DB_NAME', '" . addslashes($dbName) . "');\n"
                    . "define('DB_USER', '" . addslashes($dbUser) . "');\n"
                    . "define('DB_PASS', '" . addslashes($dbPass) . "');\n"
                    . "define('DB_FILE', __DIR__ . '/agency_os.db');\n\n"
                    . "function getDbConnection() {\n"
                    . "    static \$pdo = null;\n"
                    . "    if (\$pdo === null) {\n"
                    . "        \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\";\n"
                    . "        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [\n"
                    . "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n"
                    . "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC\n"
                    . "        ]);\n"
                    . "    }\n"
                    . "    return \$pdo;\n"
                    . "}\n";

                file_put_contents(__DIR__ . '/db.php', $dbPhpContent);

                $msg = "🚀 SUCCESS! HemiFlow Database successfully configured and imported into Hostinger MySQL! You can now log in.";
            }

        } catch (Exception $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HemiFlow - Hostinger Live Installer</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { background: #0f172a; color: white; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: sans-serif; }
        .setup-card { background: white; color: #0f172a; padding: 35px; border-radius: 12px; width: 100%; max-width: 520px; box-shadow: 0 20px 25px rgba(0,0,0,0.3); }
    </style>
</head>
<body>

<div class="setup-card">
    <div style="text-align:center; margin-bottom:15px;">
        <img src="asset/Hemiflow Blue Wave Logo.png" alt="HemiFlow Logo" style="height:50px; max-width:200px; object-fit:contain;">
    </div>
    <h2 style="color:#2563eb; margin-bottom:5px; text-align:center;">HemiFlow Hostinger Setup</h2>
    <p style="color:#64748b; font-size:14px; margin-bottom:25px; text-align:center;">Enter your Hostinger MySQL Database credentials created in Hostinger hPanel.</p>


    <?php if ($error): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:12px; border-radius:6px; font-size:13px; margin-bottom:15px; border-left:4px solid #dc2626;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:6px; font-size:14px; margin-bottom:20px; border-left:4px solid #16a34a;">
            <?= $msg ?>
            <div style="margin-top:15px;">
                <a href="login.php" class="btn btn-primary" style="display:block; text-align:center;">Go to Login Portal</a>
            </div>
        </div>
    <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Database Host</label>
                <input type="text" name="db_host" class="form-control" value="localhost" required>
                <small style="color:#64748b;">Usually <code>localhost</code> on Hostinger.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Hostinger Database Name *</label>
                <input type="text" name="db_name" class="form-control" placeholder="u123456789_agencyos" required>
            </div>

            <div class="form-group">
                <label class="form-label">Hostinger Database User *</label>
                <input type="text" name="db_user" class="form-control" placeholder="u123456789_user" required>
            </div>

            <div class="form-group">
                <label class="form-label">Hostinger Database Password *</label>
                <input type="password" name="db_pass" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; margin-top:10px;">
                Connect & Install Database
            </button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
