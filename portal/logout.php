<?php
require_once 'includes/init.php';

// Log logout event if we know who is logging out
if (isset($_SESSION[$APP.'_user_name'])) {
    logEvent('access', 'logout', [
        'username' => $_SESSION[$APP.'_user_name']
    ]);
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Get base path for redirection
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/portal');
if ($basePath === '\\') $basePath = '/';

// Redirect to login page
header("Location: " . $basePath . "/portal/login.php");
exit;
