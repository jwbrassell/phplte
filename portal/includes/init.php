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

// Start session
session_start();

// Load required files
require_once __DIR__ . '/logging_bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/PythonLogger.php';

// Initialize global variables
$_SESSION['authenticated'] = $_SESSION['authenticated'] ?? false;
$_SESSION['user'] = $_SESSION['user'] ?? null;

// Set up error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    return false;
}
set_error_handler("customErrorHandler");

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
?>
