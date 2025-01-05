<?php
// Prevent multiple initialization
if (defined('APP_INITIALIZED')) {
    return;
}
define('APP_INITIALIZED', true);

// Configure session settings
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

// Get the base path and domain from the current request
$basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
if ($basePath === '\\') $basePath = '/'; // Fix for Windows paths

$domain = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($domain, ':') !== false) {
    $domain = strstr($domain, ':', true); // Remove port number if present
}

error_log("Session configuration:");
error_log("Base path: " . $basePath);
error_log("Domain: " . $domain);
error_log("Full URL: " . $_SERVER['REQUEST_URI']);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_path' => $basePath,
        'cookie_domain' => $domain,
        'cookie_httponly' => true,
        'use_only_cookies' => true,
        'cookie_samesite' => 'Lax'
    ]);
    error_log("Session started:");
    error_log("Session ID: " . session_id());
    error_log("Session cookie params: " . print_r(session_get_cookie_params(), true));
}

date_default_timezone_set('UTC');
error_reporting(E_ERROR | E_PARSE);

// Initialize logging system
require_once(__DIR__ . '/logging_bootstrap.php');

// Function to ensure logging directories exist and are writable
function ensure_logging_directories() {
    $base_dir = __DIR__ . '/../logs';
    error_log("Base logging directory path: " . $base_dir);
    
    $required_dirs = [
        '',          // Main logs directory
        '/access',   // Access logs
        '/errors',   // PHP error logs
        '/client',   // Client-side error logs
        '/python',   // Python script logs
        '/audit',    // Audit logs
        '/performance' // Performance logs
    ];
    
    foreach ($required_dirs as $dir) {
        $path = $dir ? $base_dir . $dir : $base_dir;
        error_log("Checking directory: " . $path);
        
        if (!file_exists($path)) {
            error_log("Directory doesn't exist, attempting to create: " . $path);
            if (!@mkdir($path, 0755, true)) {
                error_log("Failed to create directory: " . $path . " - Error: " . error_get_last()['message']);
                continue;
            }
            error_log("Successfully created directory: " . $path);
        }
        
        if (!is_writable($path)) {
            error_log("Directory not writable, attempting to set permissions: " . $path);
            if (!@chmod($path, 0755)) {
                error_log("Failed to set permissions: " . $path . " - Error: " . error_get_last()['message']);
            } else {
                error_log("Successfully set permissions for: " . $path);
            }
        }
        
        // Debug: Show current permissions and ownership
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $owner = posix_getpwuid(fileowner($path));
        $group = posix_getgrgid(filegroup($path));
        error_log(sprintf(
            "Directory %s - Permissions: %s, Owner: %s, Group: %s",
            $path,
            $perms,
            $owner['name'],
            $group['name']
        ));
    }
}

// Ensure logging directories exist before any operations
ensure_logging_directories();

// Get current page name and URI from URL
$PAGE = basename($_SERVER['PHP_SELF']);
$URI = str_replace('%20', ' ', $_SERVER['REQUEST_URI']);

// Log page access
logAccess($PAGE, $_SERVER['REQUEST_METHOD']);

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
        error_log("Raw menu data: " . print_r($data, true));
        
        if ($data !== null) {
            // Validate and sanitize menu data
            $menuData = array();
            
            // First pass: identify valid menu categories
            foreach ($data as $key => $value) {
                // Skip metadata fields
                if ($key === 'description' || $key === 'summary') {
                    error_log("Skipping metadata field: $key");
                    continue;
                }
                
                // Validate menu category structure
                if (!is_array($value)) {
                    error_log("Invalid menu category (not an array): $key");
                    continue;
                }
                
                if (!isset($value['type'])) {
                    error_log("Invalid menu category (missing type): $key");
                    continue;
                }
                
                if (!isset($value['urls']) || !is_array($value['urls'])) {
                    error_log("Invalid menu category (invalid urls): $key");
                    continue;
                }
                
                // Validate and sanitize URLs
                $validUrls = array();
                foreach ($value['urls'] as $pageName => $pageDetails) {
                    if (!is_array($pageDetails)) {
                        error_log("Invalid page details for $key -> $pageName");
                        continue;
                    }
                    
                    if (!isset($pageDetails['url'])) {
                        error_log("Missing URL for $key -> $pageName");
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
            error_log("Processed menu data: " . print_r($data, true));
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
