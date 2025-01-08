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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log("Init.php - Started new session: " . session_id());
} else {
    error_log("Init.php - Using existing session: " . session_id());
}

// Debug session state
error_log("Init.php - Current Session State: " . print_r($_SESSION, true));

// Load required files
require_once __DIR__ . '/logging_bootstrap.php';

// Load additional files
require_once __DIR__ . '/auth.php';

// Initialize session variables only if they don't exist and we're not already authenticated
if (!isset($_SESSION['initialized']) && !isset($_SESSION['authenticated'])) {
    $_SESSION['initialized'] = true;
    error_log("Init.php - Initialized new session");
} else {
    error_log("Init.php - Using existing initialized session");
}

// Debug final session state
error_log("Init.php - Final Session State: " . print_r($_SESSION, true));

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
