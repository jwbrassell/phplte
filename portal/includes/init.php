<?php
// Load environment configuration
require_once __DIR__ . '/env.php';

// Initialize error reporting
error_reporting(E_ALL);
ini_set('display_errors', IS_PRODUCTION ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', PROJECT_ROOT . '/portal/logs/php_errors.log');

// Set up autoloading
set_include_path(get_include_path() . PATH_SEPARATOR . PROJECT_ROOT);

// Configure session settings
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
if (IS_PRODUCTION) {
    ini_set('session.cookie_secure', '1');
}

// Start session
session_start();

// Debug session state
error_log("Init.php - Session ID: " . session_id());
error_log("Init.php - Initial Session State: " . print_r($_SESSION, true));

// Load required files
require_once __DIR__ . '/logging_bootstrap.php';

// Load additional files
require_once __DIR__ . '/auth.php';

// Initialize session variables only if they don't exist
if (!isset($_SESSION['initialized'])) {
    $_SESSION['authenticated'] = false;
    $_SESSION['user'] = null;
    $_SESSION['initialized'] = true;
    error_log("Init.php - New session initialized");
}

// Verify critical directories exist
$criticalDirs = [
    SHARED_DIR,
    DATA_DIR,
    SCRIPTS_DIR,
    PROJECT_ROOT . '/portal/logs'
];

foreach ($criticalDirs as $dir) {
    if (!is_dir($dir)) {
        error_log("Critical directory missing: $dir");
    }
}

// Debug final session state
error_log("Init.php - Final Session State: " . print_r($_SESSION, true));
?>
