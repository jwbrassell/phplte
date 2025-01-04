<?php
/**
 * RBAC User Verification
 * Handles LDAP authentication and session management with robust error handling
 */

// Initialize error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Function to ensure log directory exists and is writable
function ensure_log_directory($path) {
    if (!file_exists($path)) {
        if (!mkdir($path, 0755, true)) {
            error_log("Failed to create directory: $path");
            return false;
        }
    }
    if (!is_writable($path)) {
        error_log("Directory not writable: $path");
        return false;
    }
    return true;
}

// Function to safely write to log file
function write_log($file, $message) {
    try {
        if (file_put_contents($file, $message, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write to log file: $file");
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log("Exception writing to log file: " . $e->getMessage());
        return false;
    }
}

// Validate and sanitize input
if (!isset($_POST['login_user']) || !isset($_POST['login_passwd']) || !isset($_POST['APP'])) {
    $error = "Missing required login parameters";
    error_log($error);
    return;
}

$uname = filter_var($_POST['login_user'], FILTER_SANITIZE_STRING);
$passwd = $_POST['login_passwd'];  // Don't sanitize password as it might contain special chars
$APP = filter_var($_POST['APP'], FILTER_SANITIZE_STRING);

// Set up logging variables
$TS = date('Y,m,d,H,i,s');
$DATE = date('Ymd');
$LOG_DIR = "/var/www/html/" . $APP . "/portal/logs/access";
$FILE = $LOG_DIR . "/" . $DATE . "_access.log";

// Ensure log directory exists
if (!ensure_log_directory($LOG_DIR)) {
    $error = "Failed to access log directory";
    error_log($error);
    return;
}

// Verify user through LDAP with error handling
try {
    $python_script = "/var/www/html/shared/scripts/modules/ldap/ldapcheck.py";
    if (!file_exists($python_script)) {
        throw new Exception("LDAP check script not found");
    }

    $ldapcheck = rtrim(shell_exec("$python_script " . escapeshellarg($uname) . " " . escapeshellarg($passwd) . " " . escapeshellarg($APP) . " 2>&1"));
    if ($ldapcheck === null) {
        throw new Exception("Failed to execute LDAP check script");
    }

    if (strpos($ldapcheck, '||') === false) {
        throw new Exception("Invalid LDAP check response format");
    }

    list($status, $resp) = explode('||', $ldapcheck);

    if($status === "OK") {
        // Parse successful LDAP response
        $response_parts = explode('|', $resp);
        if (count($response_parts) < 6) {
            throw new Exception("Incomplete LDAP response data");
        }

        list($employee_num, 
             $employee_name, 
             $employee_email,
             $adom_group,
             $vzid, 
             $adom_groups) = $response_parts;

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

        // Log successful login with detailed information
        $log_message = sprintf(
            "%s||SUCCESS||%s||%s||%s||%s\n",
            $TS,
            $uname,
            $adom_group,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        );
        write_log($FILE, $log_message);

    } else {
        // Handle failed login
        $error = $resp;
        
        // Log failed attempt with details
        $log_message = sprintf(
            "%s||FAILED||%s||%s||%s||%s\n",
            $TS,
            $uname,
            $error,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        );
        write_log($FILE, $log_message);
    }
} catch (Exception $e) {
    $error = "Authentication error: " . $e->getMessage();
    error_log($error);
    
    // Log system error
    $log_message = sprintf(
        "%s||ERROR||%s||%s||%s||%s\n",
        $TS,
        $uname,
        $e->getMessage(),
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    );
    write_log($FILE, $log_message);
}
