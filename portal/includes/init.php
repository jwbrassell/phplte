<?php
// Load environment configuration
require_once __DIR__ . '/env.php';

// Configure session
ini_set('session.use_strict_mode', '1');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log("Init.php - Started new session: " . session_id());
} else {
    error_log("Init.php - Using existing session: " . session_id());
}

// Debug session state
error_log("Init.php - Session Contents: " . print_r($_SESSION, true));
error_log("Init.php - Cookie: " . print_r($_COOKIE, true));

// Load required files
require_once __DIR__ . '/logging_bootstrap.php';

// Load additional files
require_once __DIR__ . '/auth.php';

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
