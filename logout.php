<?php
require_once __DIR__ . '/php/core/config.php'; // For BASE_URL and session start
require_once __DIR__ . '/php/includes/functions.php'; // For redirect and set_flash_message

// No need to check if logged in, just destroy session if it exists
// session_start(); // Already started in config.php

$_SESSION = array(); // Unset all session variables

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

set_flash_message('You have been logged out successfully.', 'success');
redirect(BASE_URL . 'login.php');
?>
