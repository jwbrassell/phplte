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
    proc_close($process);
    error_log("LDAP check output: " . $ldapcheck);
}

// Parse the response
$parts = explode('|', $ldapcheck);
$status = array_shift($parts);

if($status == "OK!" && count($parts) == 6) {
    list($employee_num, $employee_name, $employee_email, $adom_group, $vzid, $adom_groups) = $parts;
    
    // Set session variables
    $_SESSION[$APP . "_user_session"] = $uname;
    $_SESSION[$APP . "_user_num"] = $employee_num;
    $_SESSION[$APP . "_user_name"] = $employee_name;
    $_SESSION[$APP . "_user_vzid"] = $vzid;
    $_SESSION[$APP . "_user_email"] = $employee_email;
    $_SESSION[$APP . "_adom_groups"] = str_replace(", ", ",", $adom_groups);
    
    // Log success and redirect
    file_put_contents($FILE, "$TS|SUCCESS|$uname|$adom_group\n", FILE_APPEND | LOCK_EX);
    header("Location: /portal/index.php");
    exit;
} else {
    // Log failure and redirect with error
    file_put_contents($FILE, "$TS|$ldapcheck|$uname\n", FILE_APPEND | LOCK_EX);
    header("Location: /portal/login.php?error=" . urlencode($ldapcheck));
    exit;
}
