<?php
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
        
        // Log successful test login
        error_log("Test user logged in with groups: " . $_SESSION[$APP."_adom_groups"]);
        logActivity('login_success', ['username' => 'test', 'type' => 'test_account']);
        header("Location: index.php");
        exit;
    } else {
        // Get the document root from server variables or fallback to default
        $DOC_ROOT = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '/var/www/html';
        include($DOC_ROOT . "/shared/scripts/modules/rbac/verifyuser.php");
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
    error_log("Current page: " . $PAGE);
    error_log("Available menu data: " . print_r($data, true));
    
    if (!is_array($data)) {
        error_log("Menu data is not an array");
        $data = array();
    }
    
    foreach ($data as $category => $details) {
        error_log("Processing category: " . $category);
        
        if (!is_array($details)) {
            error_log("Category details is not an array for: " . $category);
            continue;
        }
        
        if (!isset($details['urls']) || !is_array($details['urls'])) {
            error_log("Invalid or missing urls for category: " . $category);
            continue;
        }
        
        foreach ($details['urls'] as $pageName => $pageDetails) {
            error_log("Checking page: " . $pageName);
            
            if (!isset($pageDetails['url'])) {
                error_log("Missing url for page: " . $pageName);
                continue;
            }
            
            $urlPath = parse_url($pageDetails['url'], PHP_URL_PATH);
            error_log("Comparing paths: " . $urlPath . " vs " . $PAGE);
            
            if ($PAGE === $urlPath) {
                $pageExists = true;
                $pageAllowed = true;
                error_log("Page match found: " . $PAGE);
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
