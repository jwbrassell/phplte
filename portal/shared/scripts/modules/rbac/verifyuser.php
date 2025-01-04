<?php
session_start();

// Get POST data and initialize variables
$uname = $_POST["login_user"];
$passwd = $_POST["login_passwd"];
$APP = $_POST["APP"];
$TS = date("Y-m-d H:i:s");
$DATE = date("Ymd");

// Get the document root and set up paths
$DOC_ROOT = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '/var/www/html';
$FILE = dirname(dirname(dirname(dirname(__FILE__)))) . "/logs/access/" . $DATE . "_access.log";
$PORTAL_ROOT = dirname(dirname(dirname(dirname(__FILE__))));

// Execute ldapcheck and capture only stdout, redirect stderr to null
$cmd = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . "/shared/scripts/modules/ldap/ldapcheck.py " . escapeshellarg($uname) . " " . escapeshellarg($passwd) . " " . escapeshellarg($APP);
$ldapcheck = trim(shell_exec("$cmd 2>/dev/null"));

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
