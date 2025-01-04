<?php
/**
 * RBAC User Verification
 * Handles LDAP authentication and session management
 */

// Get user credentials
$uname = $_POST['login_user'];
$passwd = $_POST['login_passwd'];
$APP = $_POST['APP'];

// Set up logging variables
$TS = date('Y,m,d,H,i,s');
$DATE = date('Ymd');
$FILE = "/var/www/html/" . $APP . "/portal/logs/access/" . $DATE . "_access.log";

// Verify user through LDAP
$ldapcheck = rtrim(`/var/www/html/shared/scripts/modules/ldap/ldapcheck.py $uname '$passwd' $APP`);
list($status, $resp) = explode('||', $ldapcheck);

if($status === "OK") {
    // Parse successful LDAP response
    list($employee_num, 
         $employee_name, 
         $employee_email,
         $adom_group,
         $vzid, 
         $adom_groups) = explode('|', $resp);

    // Set session variables
    $_SESSION[$APP . "_user_session"] = $uname;
    $_SESSION[$APP . "_user_num"] = $employee_num;
    $_SESSION[$APP . "_user_name"] = $employee_name;
    $_SESSION[$APP . "_user_vzid"] = $vzid;
    $_SESSION[$APP . "_user_email"] = $employee_email;
    $_SESSION[$APP . "_adom_groups"] = $adom_groups;

    // Clear any existing error
    if(isset($error)) {
        unset($error);
    }

    // Log successful login
    file_put_contents(
        $FILE, 
        "$TS||SUCCESS||$uname||$adom_group\n", 
        FILE_APPEND | LOCK_EX
    );
} else {
    // Handle failed login
    $error = $resp;
    
    // Log failed attempt
    file_put_contents(
        $FILE, 
        "$TS||$error||$uname\n", 
        FILE_APPEND | LOCK_EX
    );
}