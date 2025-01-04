<?php

$uname = $_POST["login_user"];
$passwd = $_POST["login_passwd"];
$APP = $_POST["APP"];
$TS = date("Y-m-d H:i:s");
$DATE = date("Ymd");
$FILE = "/var/www/html/portal/logs/access/" . $DATE . "_access.log";

echo $APP;

$ldapcheck = rtrim(`/var/www/html/shared/scripts/modules/ldap/ldapcheck.py $uname '$passwd' $APP`);
echo $ldapcheck;

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
} else {
    $error = $resp;
    file_put_contents($FILE, "$TS|$error|$uname\n", FILE_APPEND | LOCK_EX);
}