<?php
// db.php - Database connection helper supporting both Hostinger MySQL & Local SQLite

// =========================================================================
// HOSTINGER MYSQL DATABASE CONFIGURATION
// Fill these details from Hostinger hPanel -> Databases -> MySQL Databases
// =========================================================================
define('DB_TYPE', 'sqlite'); // Change to 'mysql' for Hostinger live deployment

define('DB_HOST', 'localhost');          // Hostinger DB Host (usually 'localhost')
define('DB_NAME', 'u123456789_agencyos'); // Hostinger Database Name
define('DB_USER', 'u123456789_user');     // Hostinger Database Username
define('DB_PASS', 'YourHostingerPasswordHere'); // Hostinger Database Password

// SQLite Local File Path
define('DB_FILE', __DIR__ . '/agency_os.db');

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            if (DB_TYPE === 'mysql') {
                // Connect to Hostinger MySQL Database
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]);
            } else {
                // Connect to Local SQLite Database
                $pdo = new PDO('sqlite:' . DB_FILE);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
                $pdo->exec("PRAGMA busy_timeout = 5000;");
                $pdo->exec("PRAGMA foreign_keys = ON;");
            }
        } catch (Throwable $e) {
            http_response_code(200); // Prevent raw browser 500 error, show friendly configuration screen
            die("<div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif; background:#f8fafc; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;'>
                <div style='background:white; max-width:560px; width:100%; padding:32px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.08); border-top:5px solid #2563eb;'>
                    <div style='text-align:center; margin-bottom:15px;'>
                        <img src='asset/Hemiflow Blue Wave Logo.png' alt='HemiFlow' style='height:40px; max-width:180px; object-fit:contain;' onerror=\"this.style.display='none'\">
                    </div>
                    <h2 style='color:#0f172a; margin-top:0; font-size:20px; text-align:center;'>HemiFlow Database Setup Needed</h2>
                    <p style='color:#475569; font-size:14px; line-height:1.6;'>
                        Your files are running on Hostinger, but the database connection needs your Hostinger MySQL credentials.
                    </p>
                    <div style='background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:12px 16px; margin:18px 0; color:#991b1b; font-size:13px; font-family:monospace; word-break:break-all;'>
                        " . htmlspecialchars($e->getMessage()) . "
                    </div>
                    <div style='margin-top:24px; text-align:center;'>
                        <a href='setup_hostinger.php' style='display:inline-block; background:#2563eb; color:white; font-weight:600; padding:12px 24px; border-radius:8px; text-decoration:none; font-size:15px; box-shadow:0 4px 12px rgba(37,99,235,0.25);'>
                            Launch 1-Click Hostinger Setup &rarr;
                        </a>
                    </div>
                    <p style='color:#94a3b8; font-size:12px; text-align:center; margin-top:16px; margin-bottom:0;'>
                        Or open <code>db.php</code> in Hostinger File Manager to enter your MySQL credentials.
                    </p>
                </div>
            </div>");
        }
    }
    return $pdo;
}
