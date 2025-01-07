<?php
// Prevent multiple initialization
if (defined('APP_INITIALIZED')) {
    return;
}
define('APP_INITIALIZED', true);

// Get the base path and domain from the current request
$basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
if ($basePath === '\\') $basePath = '/'; // Fix for Windows paths

$domain = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($domain, ':') !== false) {
    $domain = strstr($domain, ':', true); // Remove port number if present
}

// Configure all session settings before starting the session
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters
    session_set_cookie_params([
        'path' => $basePath,
        'domain' => $domain,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Set additional session settings
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    
    // Start the session with all settings configured
    session_start();
}

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get config first for $APP and $DIR variables
require_once(__DIR__ . "/../config.php");

// Initialize logging system
require_once(__DIR__ . '/logging_bootstrap.php');

// Include authentication functions and checks
require_once(__DIR__ . '/auth.php');

// Get current page name and URI from URL
$PAGE = basename($_SERVER['PHP_SELF']);
$URI = str_replace('%20', ' ', $_SERVER['REQUEST_URI']);

// Log page access
logAccess($PAGE, $_SERVER['REQUEST_METHOD']);

// Handle authentication redirects
if (!isAuthenticated() && $PAGE !== 'login.php' && $PAGE !== '403.php' && $PAGE !== '404.php') {
    $requested_page = urlencode($_SERVER['REQUEST_URI']);
    error_log("Redirecting unauthenticated user to login. Requested page: " . $requested_page);
    header("Location: " . $basePath . "/login.php?next=" . $requested_page);
    exit;
}

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

// Log session state for debugging
error_log("Session state in init.php - Authenticated: " . (isAuthenticated() ? 'yes' : 'no'));

// Initialize menu data
$data = array();
$configFile = $DIR.'/config/rbac_config.json';

if (file_exists($configFile)) {
    $jsonContent = file_get_contents($configFile);
    if ($jsonContent !== false) {
        $configData = json_decode($jsonContent, true);
        if ($configData !== null && isset($configData['menu_structure'])) {
            $menuData = $configData['menu_structure'];
            
            // Validate and sanitize menu data
            $validatedData = array();
            
            // Keep description and summary
            $data = array(
                'description' => $menuData['description'] ?? '',
                'summary' => $menuData['summary'] ?? ''
            );

            // Process menu categories
            foreach ($menuData as $key => $value) {
                // Skip non-menu items
                if ($key === 'description' || $key === 'summary') {
                    continue;
                }

                // Basic validation
                if (!is_array($value) || !isset($value['type']) || !isset($value['urls']) || !is_array($value['urls'])) {
                    continue;
                }

                // Copy the entire menu item structure
                $data[$key] = $value;
            }

            // Sort menu items while keeping description and summary at the top
            $description = $data['description'] ?? '';
            $summary = $data['summary'] ?? '';
            unset($data['description'], $data['summary']);
            ksort($data);
            $data = array_merge(
                array(
                    'description' => $description,
                    'summary' => $summary
                ),
                $data
            );
        }
    }
}

$alertMessage = '';
?>
