<?php
session_start();
date_default_timezone_set('UTC');
error_reporting(E_ERROR | E_PARSE);

// Function to ensure logging directories exist and are writable
function ensure_logging_directories() {
    $base_dir = __DIR__ . '/../logs';
    $required_dirs = [
        '',          // Main logs directory
        '/access',   // Access logs
        '/errors',   // PHP error logs
        '/client',   // Client-side error logs
        '/python'    // Python script logs
    ];
    
    foreach ($required_dirs as $dir) {
        $path = $dir ? $base_dir . $dir : $base_dir;
        if (!file_exists($path)) {
            if (!@mkdir($path, 0755, true)) {
                error_log("Failed to create logging directory: " . $path);
                continue;
            }
        }
        
        if (!is_writable($path)) {
            if (!@chmod($path, 0755)) {
                error_log("Failed to set permissions on logging directory: " . $path);
            }
        }
    }
}

// Ensure logging directories exist before any operations
ensure_logging_directories();

// Get current page name and URI from URL
$PAGE = basename($_SERVER['PHP_SELF']);
$URI = str_replace('%20', ' ', $_SERVER['REQUEST_URI']);

require_once(__DIR__ . "/../config.php");

// Initialize session variables if not set
if (!isset($_SESSION[$APP."_user_name"])) {
    $uswin = '';
    $username = '';
    $vzid = '';
    $fname = '';
    $lname = '';
    $user_email = '';
    $adom_groups = array();
}

// Initialize menu data
$data = array();
$menuFile = $DIR.'/config/menu-bar.json';

// Debug output
error_log("Attempting to read menu file: " . $menuFile);

if (file_exists($menuFile)) {
    $jsonContent = file_get_contents($menuFile);
    if ($jsonContent !== false) {
        $data = json_decode($jsonContent, true);
        if ($data !== null) {
            ksort($data);
        } else {
            error_log("Failed to decode JSON from menu-bar.json");
            $data = array(); // Fallback to empty array
        }
    } else {
        error_log("Failed to read contents of menu-bar.json");
        $data = array(); // Fallback to empty array
    }
} else {
    error_log("Menu file not found at: " . $menuFile);
    $data = array(); // Fallback to empty array
}

$alertMessage = '';
?>
