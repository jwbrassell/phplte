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
error_reporting(E_ERROR | E_PARSE);

// Get config first for $APP and $DIR variables
require_once(__DIR__ . "/../config.php");

// Initialize logging system
require_once(__DIR__ . '/logging_bootstrap.php');

// Get current page name and URI from URL
$PAGE = basename($_SERVER['PHP_SELF']);
$URI = str_replace('%20', ' ', $_SERVER['REQUEST_URI']);

// Log page access
logAccess($PAGE, $_SERVER['REQUEST_METHOD']);

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

if (file_exists($menuFile)) {
    $jsonContent = file_get_contents($menuFile);
    if ($jsonContent !== false) {
        $data = json_decode($jsonContent, true);
        if ($data !== null) {
            // Validate and sanitize menu data
            $menuData = array();
            
            // First pass: identify valid menu categories
            foreach ($data as $key => $value) {
                // Skip metadata fields
                if ($key === 'description' || $key === 'summary') {
                    continue;
                }
                
                // Validate menu category structure
                if (!is_array($value)) {
                    continue;
                }
                
                if (!isset($value['type'])) {
                    continue;
                }
                
                if (!isset($value['urls']) || !is_array($value['urls'])) {
                    continue;
                }
                
                // Validate and sanitize URLs
                $validUrls = array();
                foreach ($value['urls'] as $pageName => $pageDetails) {
                    if (!is_array($pageDetails)) {
                        continue;
                    }
                    
                    if (!isset($pageDetails['url'])) {
                        continue;
                    }
                    
                    // Ensure URL is properly formatted
                    $validUrls[$pageName] = array(
                        'url' => $pageDetails['url'],
                        'roles' => isset($pageDetails['roles']) ? $pageDetails['roles'] : array()
                    );
                }
                
                if (!empty($validUrls)) {
                    $menuData[$key] = array(
                        'type' => $value['type'],
                        'img' => isset($value['img']) ? $value['img'] : '',
                        'urls' => $validUrls
                    );
                }
            }
            
            $data = $menuData;
            ksort($data);
        } else {
            $data = array(); // Fallback to empty array
        }
    } else {
        $data = array(); // Fallback to empty array
    }
} else {
    $data = array(); // Fallback to empty array
}

$alertMessage = '';
?>
