<?php
/**
 * Application Configuration File
 * Contains core settings and constants for the application
 */

// Application settings
$APP = "framework";

// Environment detection
$hostname = php_uname('n');
define('IS_PRODUCTION', strpos($hostname, 'ip-') === 0); // AWS EC2 instances start with 'ip-'

// Path Configuration
$DIR = __DIR__;  // Current directory (portal)
$ROOTDIR = dirname($DIR);  // Up to project root

// Get absolute paths
$projectRoot = realpath($ROOTDIR);
if (!$projectRoot) {
    error_log("Failed to resolve project root from: " . $ROOTDIR);
    throw new Exception("Could not resolve project root directory");
}

// Define path constants
define('PROJECT_ROOT', $projectRoot);
define('BASE_PATH', IS_PRODUCTION ? '/var/www/html' : PROJECT_ROOT);

// Shared directory paths
define('SHARED_DIR', PROJECT_ROOT . '/shared');
define('DATA_DIR', SHARED_DIR . '/data');
define('SCRIPTS_DIR', SHARED_DIR . '/scripts');
define('MODULES_DIR', SCRIPTS_DIR . '/modules');
define('PYTHON_MODULES', [
    'logging' => MODULES_DIR . '/logging',
    'oncall_calendar' => MODULES_DIR . '/oncall_calendar'
]);

// Virtual environment paths
$venvPaths = [
    SHARED_DIR . '/venv',
    '/var/www/html/shared/venv'
];

$activeVenv = null;
foreach ($venvPaths as $venvPath) {
    if (file_exists($venvPath . '/bin/python3')) {
        $activeVenv = $venvPath;
        break;
    }
}

define('VENV_DIR', $activeVenv);

error_log("Directory Structure:");
error_log("- Current Dir: " . $DIR);
error_log("- Project Root: " . PROJECT_ROOT);
error_log("- Shared Dir: " . SHARED_DIR);
error_log("- Scripts Dir: " . SCRIPTS_DIR);
error_log("- Modules Dir: " . MODULES_DIR);
error_log("- Active Venv: " . ($activeVenv ?: 'Not Found'));

// Helper function for path resolution
function resolvePath($path, $type = 'shared') {
    // Always work with clean paths
    $cleanPath = trim($path, '/');
    
    // Handle different path types
    switch ($type) {
        case 'module':
            // For Python modules, ensure we're in the modules directory
            $base = IS_PRODUCTION ? '/var/www/html/shared/scripts/modules' : MODULES_DIR;
            // Remove redundant module path if present
            $cleanPath = preg_replace('#^scripts/modules/#', '', $cleanPath);
            break;
        case 'data':
            // For data files
            $base = IS_PRODUCTION ? '/var/www/html/shared/data' : DATA_DIR;
            // Remove redundant data path if present
            $cleanPath = preg_replace('#^data/#', '', $cleanPath);
            break;
        default:
            // Default shared directory
            $base = IS_PRODUCTION ? '/var/www/html/shared' : SHARED_DIR;
            // Remove redundant shared path if present
            $cleanPath = preg_replace('#^shared/#', '', $cleanPath);
    }
    
    $absolutePath = $base . '/' . $cleanPath;
    
    error_log("Path Resolution:");
    error_log("- Input: " . $path);
    error_log("- Clean: " . $cleanPath);
    error_log("- Base: " . $base);
    error_log("- Output: " . $absolutePath);
    
    return realpath($absolutePath) ?: $absolutePath;
}

// Page refresh settings
$REFRESH = 300;  // Default refresh time in seconds

// Application display settings
$TITLE = "Southlake NMC";  // Default page title

// Debug logging for path resolution
error_log("Environment Configuration:");
error_log("IS_PRODUCTION: " . (IS_PRODUCTION ? 'true' : 'false'));
error_log("PROJECT_ROOT: " . PROJECT_ROOT);
error_log("BASE_PATH: " . BASE_PATH);
error_log("SHARED_DIR: " . SHARED_DIR);
error_log("DATA_DIR: " . DATA_DIR);
error_log("SCRIPTS_DIR: " . SCRIPTS_DIR);
?>
