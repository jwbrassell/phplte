<?php
// Get POST data and initialize variables
$uname = $_POST["login_user"];
$passwd = $_POST["login_passwd"];
$APP = $_POST["APP"];
$TS = date("Y-m-d H:i:s");
$DATE = date("Ymd");

// Debug logging
error_log("Starting authentication process for user: " . $uname);

// Include config for path resolution
require_once(__DIR__ . '/../../../../portal/config.php');

// Set up log file path
$FILE = PROJECT_ROOT . "/portal/logs/access/" . $DATE . "_access.log";

// Execute ldapcheck and capture only stdout
$cmd = PROJECT_ROOT . "/shared/venv/bin/python " . PROJECT_ROOT . "/shared/scripts/modules/ldap/ldapcheck.py " . escapeshellarg($uname) . " " . escapeshellarg($passwd) . " " . escapeshellarg($APP);
error_log("Executing command: " . $cmd);
$descriptorspec = array(
   0 => array("pipe", "r"),  // stdin
   1 => array("pipe", "w"),  // stdout
   2 => array("file", PROJECT_ROOT . "/portal/logs/python/ldap_debug.log", "a")  // stderr to file
);

$process = proc_open($cmd, $descriptorspec, $pipes);
if (is_resource($process)) {
    $ldapcheck = trim(stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    $status = proc_close($process);
}

// Look for LDAP response in format: OK!|field1|field2|...
if (preg_match('/^OK!\|([^\n]+)$/', $ldapcheck, $matches)) {
    $parts = explode('|', $matches[1]);
    
    if (count($parts) === 6) {
        list($employee_num, $employee_name, $employee_email, $adom_group, $vzid, $adom_groups) = $parts;
        
        // Set session variables
        $_SESSION[$APP . "_user_session"] = $uname;
        $_SESSION[$APP . "_user_num"] = $employee_num;
        $_SESSION[$APP . "_user_name"] = $employee_name;
        $_SESSION[$APP . "_user_vzid"] = $vzid;
        $_SESSION[$APP . "_user_email"] = $employee_email;
        $_SESSION[$APP . "_adom_groups"] = $adom_groups;
        
        // Set admin status based on groups
        $_SESSION[$APP . "_is_admin"] = in_array('admin', explode(',', $adom_groups));
        
        // Log success
        file_put_contents($FILE, "$TS|SUCCESS|$uname|$adom_group\n", FILE_APPEND | LOCK_EX);
        
        // Debug logging
        error_log("Session variables set: " . print_r($_SESSION, true));
        
        // Handle redirection with next parameter
        $basePath = dirname(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))));
        if ($basePath === '\\') $basePath = '/';
        
        if (isset($_POST['next'])) {
            $next_url = filter_var(urldecode($_POST['next']), FILTER_SANITIZE_URL);
            header("Location: " . $next_url);
        } else {
            header("Location: " . $basePath . "/index.php");
        }
        exit;
    } else {
        $error = "Invalid response format";
    }
} else {
    // Look for error message in format: ERROR! ...
    if (preg_match('/^ERROR!\s+(.+)$/m', $ldapcheck, $matches)) {
        $error = $matches[1];
    } else {
        $error = "Authentication failed";
    }
}

// Log failure and redirect with error
file_put_contents($FILE, "$TS|ERROR|$uname|$error\n", FILE_APPEND | LOCK_EX);

// Use proper base path for error redirect
$basePath = dirname(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))));
if ($basePath === '\\') $basePath = '/';
header("Location: " . $basePath . "/login.php?error=" . urlencode($error));
exit;
