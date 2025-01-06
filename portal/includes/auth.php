<?php
/**
 * Check if user has access to a specific feature/page
 */
function check_access($feature) {
    global $APP;
    if (!isset($_SESSION[$APP."_adom_groups"])) {
        return false;
    }
    
    $adom_groups_string = $_SESSION[$APP."_adom_groups"];
    $adom_groups = explode(",", str_replace(["[", "]", "'", " "], "", $adom_groups_string));
    
    // Get RBAC configuration
    $configFile = dirname(__FILE__) . '/../config/rbac_config.json';
    if (!file_exists($configFile)) {
        return false;
    }
    
    $configData = json_decode(file_get_contents($configFile), true);
    if (!$configData || !isset($configData['menu_structure'])) {
        return false;
    }
    
    // Check each menu category for the feature
    foreach ($configData['menu_structure'] as $category => $value) {
        if (isset($value['urls'])) {
            foreach ($value['urls'] as $title => $info) {
                if (strpos($info['url'], $feature) !== false) {
                    // Check if user has any of the required roles
                    foreach ($adom_groups as $userRole) {
                        if (in_array($userRole, $info['roles'])) {
                            return true;
                        }
                    }
                }
            }
        }
    }
    
    return false;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    global $APP;
    return isset($_SESSION[$APP."_user_name"]);
}

// Login and authentication handling
if (($PAGE == "login.php") && (!isset($_SESSION[$APP."_user_name"])) && (isset($_POST['login_submit']))) {
    // Log login attempt
    logActivity('login_attempt', ['username' => $_POST['login_user']]);
    // Test authentication bypass
    if ($_POST['login_user'] === 'test' && $_POST['login_passwd'] === 'test123') {
        // Set test session variables
        $_SESSION[$APP."_user_name"] = "Test User";
        $_SESSION[$APP."_user_session"] = "test";
        $_SESSION[$APP."_user_vzid"] = "test123";
        $_SESSION[$APP."_user_email"] = "test@example.com";
        // Set admin groups in a consistent format
        $_SESSION[$APP."_adom_groups"] = "admin,user";
        $_SESSION[$APP."_is_admin"] = true;
        
        logActivity('login_success', ['username' => 'test', 'type' => 'test_account']);
        header("Location: index.php");
        exit;
    } else {
        // Use project relative path for verifyuser.php
        include(__DIR__ . "/../../shared/scripts/modules/rbac/verifyuser.php");
    }
}

// Page access control
$alwaysAllowedPages = ['login.php', 'index.php', 'logout.php', '403.php', '404.php'];
$pageExists = false;
$pageAllowed = false;

if (in_array($PAGE, $alwaysAllowedPages)) {
    $pageExists = true;
    $pageAllowed = true;
} else {
    // Get menu configuration
    $configFile = dirname(__FILE__) . '/../config/rbac_config.json';
    if (!file_exists($configFile)) {
        $data = array();
    } else {
        $configData = json_decode(file_get_contents($configFile), true);
        $data = isset($configData['menu_structure']) ? $configData['menu_structure'] : array();
    }
    
    if (!is_array($data)) {
        $data = array();
    }
    
    foreach ($data as $category => $details) {
        if (!is_array($details)) {
            continue;
        }
        
        if (!isset($details['urls']) || !is_array($details['urls'])) {
            continue;
        }
        
        foreach ($details['urls'] as $pageName => $pageDetails) {
            if (!isset($pageDetails['url'])) {
                continue;
            }
            
            $urlPath = parse_url($pageDetails['url'], PHP_URL_PATH);
            
            if ($PAGE === $urlPath) {
                $pageExists = true;
                $pageAllowed = true;
                break 2;
            }
        }
    }
}

// Handle 404 errors
if (!$pageExists) {
    logError("404 Error", [
        'page' => $PAGE,
        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct'
    ]);
    header("Location: /404.php?page=".urlencode($PAGE));
    exit;
}

// Handle unauthorized access
if (!$pageAllowed) {
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    logError("403 Error - Unauthorized Access", [
        'page' => $PAGE,
        'referrer' => $referrer,
        'user_groups' => $_SESSION[$APP."_adom_groups"] ?? 'none'
    ]);
    header("Location: /403.php?page=".urlencode($PAGE)."&referrer=".urlencode($referrer));
    exit;
}

// Handle login redirects
if (($PAGE == "login.php") && (isset($_SESSION[$APP."_user_name"]))) {
    logActivity('redirect_logged_in_user', [
        'username' => $_SESSION[$APP."_user_name"],
        'destination' => isset($_GET['next']) ? $_GET['next'] : 'index.php'
    ]);
    if (isset($_GET['next'])) {
        $next_url = $_GET['next'];
        header("location: $next_url");
    } else {
        header("location: index.php");
    }
}

// Set user session variables
if (isset($_SESSION[$APP."_user_name"])) {
    $uswin = $_SESSION[$APP."_user_session"];
    $username = $_SESSION[$APP."_user_name"];
    $vzid = $_SESSION[$APP."_user_vzid"];
    $fname = trim(explode(' ', $username)[1]);
    $lname = trim(explode(' ', $username)[0]);
    $user_email = $_SESSION[$APP."_user_email"];
    $adom_groups_string = $_SESSION[$APP."_adom_groups"];
    $adom_groups_string_cleaned = str_replace(["[", "]", "'"], "", $adom_groups_string);
    $adom_groups = explode(",", $adom_groups_string_cleaned);
}
?>
