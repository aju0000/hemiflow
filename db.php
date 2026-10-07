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
                $pdo->exec("PRAGMA foreign_keys = ON;");
            }
        } catch (PDOException $e) {
            die("<div style='font-family:sans-serif; padding:30px; border:2px solid #ef4444; border-radius:8px; max-width:600px; margin:40px auto;'>
                <h3 style='color:#dc2626;'>Database Connection Error</h3>
                <p>Could not connect to database type <strong>" . DB_TYPE . "</strong>.</p>
                <p>Error details: " . htmlspecialchars($e->getMessage()) . "</p>
                <hr>
                <p><strong>Hostinger Deployment Tip:</strong> Make sure you have created the MySQL Database in Hostinger hPanel and updated <code>DB_NAME</code>, <code>DB_USER</code>, and <code>DB_PASS</code> inside <code>db.php</code>.</p>
            </div>");
        }
    }
    return $pdo;
}
