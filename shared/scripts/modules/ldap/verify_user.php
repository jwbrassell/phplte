<?php
/**
 * User Verification Script
 * Handles LDAP authentication and session management
 */

// Get user credentials from POST
$uname = filter_input(INPUT_POST, 'login_user', FILTER_SANITIZE_STRING);
$passwd = filter_input(INPUT_POST, 'login_passwd', FILTER_UNSAFE_RAW); // Don't sanitize password to preserve special characters
$APP = filter_input(INPUT_POST, 'APP', FILTER_SANITIZE_STRING);

// Set up logging variables
$TS = date('Y,m,d,H,i,s');
$DATE = date('Ymd');
$FILE = "/var/www/html/" . $APP . "/portal/logs/access/" . $DATE . "_access.log";

// Verify user credentials through LDAP
$ldapcheck = rtrim(`/var/www/html/shared/scripts/modules/ldap/ldapcheck.py '$uname' '$passwd' '$APP'`);
list($status, $resp) = explode('||', $ldapcheck);

if($status == "OK") {
    // Parse the successful LDAP response
    list($employee_num, 
         $employee_name, 
         $employee_email,
         $adom_group,
         $vzid, 
         $adom_groups) = explode('|', $resp);

    // Set session variables
    $_SESSION[$APP."-user_session"] = $uname;
    $_SESSION[$APP."_user_num"] = $employee_num;
    $_SESSION[$APP."_user_name"] = $employee_name;
    $_SESSION[$APP."_user_vzid"] = $vzid;
    $_SESSION[$APP."_user_email"] = $employee_email;
    $_SESSION[$APP."_adom_groups"] = $adom_groups;

    // Clear any existing error state
    if(isset($error)) {
        unset($error);
    }

    // Log successful login attempt
    file_put_contents(
        $FILE, 
        "$TS||SUCCESS||$uname||$adom_group\n", 
        FILE_APPEND | LOCK_EX
    );
} else {
    // Handle failed login attempt
    $error = $resp;
    
    // Log failed login attempt
    file_put_contents(
        $FILE, 
        "$TS||$error||$uname\n", 
        FILE_APPEND | LOCK_EX
    );
}