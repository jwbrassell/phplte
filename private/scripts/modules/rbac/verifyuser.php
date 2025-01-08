<?php
// Get POST data and initialize variables
$uname = $_POST["login_user"];
$passwd = $_POST["login_passwd"];
$APP = $_POST["APP"];
$TS = date("Y-m-d H:i:s");

// Debug logging
error_log("Starting authentication process for user: " . $uname);

// Set up authentication log file
$authLogFile = dirname(dirname(dirname(dirname(__FILE__)))) . "/logs/access/" . date("Ymd") . "_auth.log";
$logEntry = date("Y-m-d H:i:s") . "|AUTH_START|" . $uname . "\n";
file_put_contents($authLogFile, $logEntry, FILE_APPEND | LOCK_EX);

// Include config for path resolution
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config/config.php');

// Create log directories if they don't exist
$logDirs = [
    dirname(dirname(dirname(dirname(__FILE__)))) . "/logs",
    dirname(dirname(dirname(dirname(__FILE__)))) . "/logs/access",
    dirname(dirname(dirname(dirname(__FILE__)))) . "/logs/python"
];
foreach ($logDirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0775, true);
        error_log("Created log directory: " . $dir);
    }
    // Ensure web server can write to directory
    chmod($dir, 0775);
    $webUser = exec('whoami');
    error_log("Setting permissions for $dir to 0775, current user: $webUser");
}

// Execute ldapcheck and capture only stdout
$pythonPath = VENV_DIR . "/bin/python";
if (!file_exists($pythonPath)) {
    $pythonPath = "/usr/bin/python3"; // Fallback to system Python
}

$ldapScript = dirname(dirname(__FILE__)) . "/ldap/ldapcheck.py";

// Debug environment
error_log("Python Environment Check:");
error_log("- Python path: " . $pythonPath . " (exists: " . (file_exists($pythonPath) ? 'yes' : 'no') . ")");
error_log("- LDAP script path: " . $ldapScript . " (exists: " . (file_exists($ldapScript) ? 'yes' : 'no') . ")");
error_log("- Current working directory: " . getcwd());
error_log("- Script owner/group: " . exec("ls -l " . escapeshellarg($ldapScript)));
error_log("- PHP process user: " . exec('whoami'));
error_log("- Project root: " . PROJECT_ROOT);

// Check Python modules
$checkModules = $pythonPath . ' -c "import ldap, hvac; print(\'Modules OK\')"';
$moduleCheck = [];
exec($checkModules . " 2>&1", $moduleCheck, $moduleStatus);
error_log("Python Module Check:");
error_log("- Status: " . ($moduleStatus === 0 ? 'OK' : 'Failed'));
error_log("- Output: " . implode("\n", $moduleCheck));

// Set environment variables
putenv("PYTHONPATH=" . dirname(dirname(__FILE__))); // Set to modules directory
putenv("PATH=/usr/local/bin:/usr/bin:/bin");

// Try to get Python version
$pythonVersion = [];
exec($pythonPath . ' --version 2>&1', $pythonVersion);
error_log("Python Version: " . implode("\n", $pythonVersion));

// Build command with explicit Python interpreter
$cmd = sprintf('PYTHONPATH=%s %s -u %s %s %s %s',
    escapeshellarg(dirname(dirname(__FILE__))),
    escapeshellarg($pythonPath),
    escapeshellarg($ldapScript),
    escapeshellarg($uname),
    escapeshellarg($passwd),
    escapeshellarg($APP)
);

error_log("Command Execution:");
error_log("- Full command: " . $cmd);
error_log("- PYTHONPATH: " . getenv('PYTHONPATH'));
error_log("- PATH: " . getenv('PATH'));
$descriptorspec = array(
   0 => array("pipe", "r"),  // stdin
   1 => array("pipe", "w"),  // stdout
   2 => array("pipe", "w")   // stderr to capture Python errors
);

$process = proc_open($cmd, $descriptorspec, $pipes);
if (is_resource($process)) {
    $ldapcheck = trim(stream_get_contents($pipes[1]));
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    
    error_log("Command execution results:");
    error_log("- Exit status: " . $status);
    error_log("- STDOUT: " . $ldapcheck);
    if (!empty($stderr)) {
        error_log("- STDERR: " . $stderr);
    }
    
    // Log raw LDAP response
    $logEntry = date("Y-m-d H:i:s") . "|LDAP_RESPONSE|" . $ldapcheck . "\n";
    if (!empty($stderr)) {
        $logEntry .= date("Y-m-d H:i:s") . "|LDAP_ERROR|" . $stderr . "\n";
    }
    file_put_contents($authLogFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Look for LDAP response in format: OK!|field1|field2|...
if (preg_match('/^OK!\|([^\n]+)$/', $ldapcheck, $matches)) {
    $parts = explode('|', $matches[1]);
    
    if (count($parts) >= 6) {  // Allow for extra fields
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
        
        // Set authenticated flag
        $_SESSION['authenticated'] = true;
        
        // Log success using error_log
        error_log("Login successful for user: $uname (Groups: $adom_groups)");
        $logEntry = date("Y-m-d H:i:s") . "|AUTH_SUCCESS|" . $uname . "|" . $adom_groups . "\n";
        file_put_contents($authLogFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Debug logging
        error_log("Session variables set: " . print_r($_SESSION, true));
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // Return JSON response for AJAX requests
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit;
        }
        
        // Regular form submission - redirect to portal
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];
        $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/portal');
        
        if (isset($_POST['next'])) {
            $next_url = filter_var(urldecode($_POST['next']), FILTER_SANITIZE_URL);
            // Ensure next_url starts with a slash
            if (strpos($next_url, '/') !== 0) {
                $next_url = '/' . $next_url;
            }
            header("Location: " . $protocol . $domain . $basePath . $next_url);
        } else {
            header("Location: " . $protocol . $domain . $basePath . "/portal/index.php");
        }
        exit;
    } else {
        $error = "Invalid response format";
        error_log("Invalid LDAP response format. Got " . count($parts) . " parts, expected 6");
    }
} else {
    // Look for error message in format: ERROR! ...
    if (preg_match('/^ERROR!\s+(.+)$/m', $ldapcheck, $matches)) {
        $error = $matches[1];
    } else {
        $error = "Authentication failed";
    }
    error_log("Authentication failed. LDAP response: " . $ldapcheck);
}

// Log failure
error_log("Login failed for user: $uname - Error: $error");
$logEntry = date("Y-m-d H:i:s") . "|AUTH_FAIL|" . $uname . "|" . $error . "\n";
file_put_contents($authLogFile, $logEntry, FILE_APPEND | LOCK_EX);

// Check if this is an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Return JSON response for AJAX requests
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $error]);
    exit;
}

// Regular form submission - redirect to login with error
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$domain = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/portal');

$redirectUrl = $protocol . $domain . $basePath . "/portal/login.php?error=" . urlencode($error);
error_log("Redirecting to: " . $redirectUrl);
header("Location: " . $redirectUrl);
exit;
