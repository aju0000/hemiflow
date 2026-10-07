<?php
// client/logout.php - Client Portal Logout Handler

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['user_id']);
unset($_SESSION['role']);
unset($_SESSION['client_id']);
unset($_SESSION['module']);
unset($_SESSION['user']);

session_destroy();

header('Location: /client/login.php?logout=1');
exit;
