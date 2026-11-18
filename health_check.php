<?php
// health_check.php - Live Server Diagnostics & Environment Inspector for HemiFlow

error_reporting(E_ALL);
ini_set('display_errors', 1);

$checks = [];

// 1. PHP Version
$phpVersion = phpversion();
$checks[] = [
    'title' => 'PHP Version',
    'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'OK' : 'WARN',
    'detail' => 'Current PHP ' . $phpVersion . ' (Recommended: PHP 8.0+)'
];

// 2. PDO Extension
$pdoLoaded = extension_loaded('pdo');
$checks[] = [
    'title' => 'PDO Core Extension',
    'status' => $pdoLoaded ? 'OK' : 'FAIL',
    'detail' => $pdoLoaded ? 'Installed & Active' : 'Missing PDO extension in PHP'
];

// 3. PDO MySQL Extension
$pdoMysql = extension_loaded('pdo_mysql');
$checks[] = [
    'title' => 'PDO MySQL Driver',
    'status' => $pdoMysql ? 'OK' : 'WARN',
    'detail' => $pdoMysql ? 'Installed & Ready for Hostinger MySQL' : 'pdo_mysql extension not enabled'
];

// 4. PDO SQLite Extension
$pdoSqlite = extension_loaded('pdo_sqlite');
$checks[] = [
    'title' => 'PDO SQLite Driver',
    'status' => $pdoSqlite ? 'OK' : 'WARN',
    'detail' => $pdoSqlite ? 'Installed & Ready for Local SQLite' : 'pdo_sqlite not enabled (use MySQL instead)'
];

// 5. Uploads directory writable
$uploadsDir = __DIR__ . '/uploads';
$isUploadsWritable = is_writable($uploadsDir) || (!file_exists($uploadsDir) && is_writable(__DIR__));
$checks[] = [
    'title' => 'Uploads Directory Writable',
    'status' => $isUploadsWritable ? 'OK' : 'WARN',
    'detail' => $isUploadsWritable ? 'Directory is writable' : 'Directory uploads/ needs write permissions (chmod 755 or 775)'
];

// 6. DB Connection Test
require_once __DIR__ . '/db.php';
$dbStatus = 'FAIL';
$dbDetail = '';
try {
    $db = getDbConnection();
    $dbType = defined('DB_TYPE') ? DB_TYPE : 'unknown';
    $dbStatus = 'OK';
    $dbDetail = "Successfully connected to {$dbType} database!";
} catch (Throwable $e) {
    $dbStatus = 'FAIL';
    $dbDetail = $e->getMessage();
}
$checks[] = [
    'title' => 'Database Connection (' . (defined('DB_TYPE') ? DB_TYPE : 'none') . ')',
    'status' => $dbStatus,
    'detail' => $dbDetail
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HemiFlow - Server Environment Diagnostics</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; }
        .container { max-width: 650px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 30px; box-shadow: 0 20px 25px rgba(0,0,0,0.4); }
        h1 { font-size: 20px; margin-top: 0; color: #38bdf8; display: flex; align-items: center; gap: 10px; }
        .check-item { background: #0f172a; border-radius: 8px; padding: 14px 18px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        .badge { font-size: 11px; font-weight: bold; padding: 4px 10px; border-radius: 9999px; }
        .badge-OK { background: #166534; color: #86efac; }
        .badge-WARN { background: #854d0e; color: #fde047; }
        .badge-FAIL { background: #991b1b; color: #fca5a5; }
        .links { margin-top: 25px; display: flex; gap: 12px; }
        .btn { flex: 1; text-align: center; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: #334155; color: #cbd5e1; }
    </style>
</head>
<body>
<div class="container">
    <h1>🚀 HemiFlow System Diagnostics</h1>
    <p style="color:#94a3b8; font-size:14px; margin-bottom:20px;">Live server health check for Hostinger / cPanel deployment.</p>

    <?php foreach ($checks as $c): ?>
        <div class="check-item">
            <div>
                <strong style="display:block; font-size:14px; color:#f1f5f9;"><?= htmlspecialchars($c['title']) ?></strong>
                <span style="font-size:12px; color:#94a3b8;"><?= htmlspecialchars($c['detail']) ?></span>
            </div>
            <span class="badge badge-<?= $c['status'] ?>"><?= $c['status'] ?></span>
        </div>
    <?php endforeach; ?>

    <div class="links">
        <a href="setup_hostinger.php" class="btn btn-secondary">Run Hostinger Setup</a>
        <a href="login.php" class="btn btn-primary">Go to Login Portal</a>
    </div>
</div>
</body>
</html>

