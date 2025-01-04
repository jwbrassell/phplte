<?php

$uname = $_POST["login_user"];
$passwd = $_POST["login_passwd"];
$APP = $_POST["APP"];
$TS = date("Y-m-d H:i:s");
$DATE = date("Ymd");
// Get the document root from server variables or fallback to default
$DOC_ROOT = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '/var/www/html';

$FILE = $DOC_ROOT . "/portal/logs/access/" . $DATE . "_access.log";

$ldapcheck = rtrim(`$DOC_ROOT/shared/scripts/modules/ldap/ldapcheck.py $uname '$passwd' $APP`);

list($status, $resp) = explode('|', $ldapcheck);

if($status == "OK") {
    list($employee_num, $employee_name, $employee_email, $adom_group, $vzid, $adom_groups) = explode('|', $resp);
    
    // Store username as session variable
    $_SESSION[$APP . "_user_session"] = $uname;
    $_SESSION[$APP . "_user_num"] = $employee_num;
    $_SESSION[$APP . "_user_name"] = $employee_name;
    $_SESSION[$APP . "_user_vzid"] = $vzid;
    $_SESSION[$APP . "_user_email"] = $employee_email;
    $_SESSION[$APP . "_adom_groups"] = $adom_groups;
    
    // Log successful login
    if(isset($error)) {
        unset($error);
    }
    file_put_contents($FILE, "$TS|SUCCESS|$uname|$adom_group\n", FILE_APPEND | LOCK_EX);
    header("Location: /index.php");
    exit;
} else {
    $error = $resp;
    file_put_contents($FILE, "$TS|$error|$uname\n", FILE_APPEND | LOCK_EX);
    header("Location: /login.php?error=" . urlencode($error));
    exit;
}
