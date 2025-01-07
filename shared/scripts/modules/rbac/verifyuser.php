<?php
session_start();

// Get POST data and initialize variables
$uname = $_POST["login_user"];
$passwd = $_POST["login_passwd"];
$APP = $_POST["APP"];
$TS = date("Y-m-d H:i:s");
$DATE = date("Ymd");

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
    error_log("LDAP check output: " . $ldapcheck);
    error_log("LDAP check status: " . $status);
    
    // Debug the response parsing
    $parts = explode('|', $ldapcheck);
    error_log("Number of parts: " . count($parts));
    error_log("Status part: " . $parts[0]);
    if (count($parts) > 1) {
        error_log("All parts: " . print_r($parts, true));
    }
}

// Parse the response
$parts = explode('|', $ldapcheck);
$status = array_shift($parts);

error_log("Status check:");
error_log("- Raw status: '" . $status . "'");
error_log("- Status length: " . strlen($status));
error_log("- Status bytes: " . bin2hex($status));
error_log("- Comparison: " . ($status === "OK!" ? "true" : "false"));

if(trim($status) === "OK!") {
    error_log("LDAP auth successful, setting up session...");
    list($employee_num, $employee_name, $employee_email, $adom_group, $vzid, $adom_groups) = $parts;
    
    // Set session variables
    $_SESSION[$APP . "_user_session"] = $uname;
    $_SESSION[$APP . "_user_num"] = $employee_num;
    $_SESSION[$APP . "_user_name"] = $employee_name;
    $_SESSION[$APP . "_user_vzid"] = $vzid;
    $_SESSION[$APP . "_user_email"] = $employee_email;
    // adom_groups is already a comma-separated string from Python
    $_SESSION[$APP . "_adom_groups"] = $adom_groups;
    
    error_log("Session variables set:");
    error_log("- user_session: " . $_SESSION[$APP . "_user_session"]);
    error_log("- user_name: " . $_SESSION[$APP . "_user_name"]);
    error_log("- adom_groups: " . $_SESSION[$APP . "_adom_groups"]);
    
    // Log success and redirect
    error_log("Writing to access log and redirecting to index.php");
    file_put_contents($FILE, "$TS|SUCCESS|$uname|$adom_group\n", FILE_APPEND | LOCK_EX);
    header("Location: /index.php");
    exit;
} else {
    error_log("LDAP auth failed:");
    error_log("- Status: " . $status);
    error_log("- Parts count: " . count($parts));
    error_log("- Full response: " . $ldapcheck);
    
    // Log failure and redirect with error
    file_put_contents($FILE, "$TS|$ldapcheck|$uname\n", FILE_APPEND | LOCK_EX);
    $error = trim($ldapcheck);
    error_log("Error message: '" . $error . "'");
    header("Location: /login.php?error=" . urlencode($error));
    exit;
}
