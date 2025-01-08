<?php
session_start();

// Get POST data
$uname = $_POST["login_user"];
$passwd = $_POST["login_passwd"];

// Execute ldapcheck
$pythonPath = "/usr/bin/python3";
$ldapScript = dirname(dirname(__FILE__)) . "/ldap/ldapcheck.py";

$cmd = sprintf('%s -u %s %s %s %s',
    escapeshellarg($pythonPath),
    escapeshellarg($ldapScript),
    escapeshellarg($uname),
    escapeshellarg($passwd),
    escapeshellarg('Portal')
);

$process = proc_open($cmd, [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
if (is_resource($process)) {
    $ldapcheck = trim(stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}

// Look for LDAP response in format: OK!|field1|field2|...
if (preg_match('/^OK!\|([^\n]+)$/', $ldapcheck, $matches)) {
    $parts = explode('|', $matches[1]);
    
    if (count($parts) >= 6) {
        list($employee_num, $employee_name, $employee_email, $adom_group, $vzid, $adom_groups) = $parts;
        
        // Set session variables
        $_SESSION['authenticated'] = true;
        $_SESSION['user_name'] = $employee_name;
        $_SESSION['user_email'] = $employee_email;
        $_SESSION['user_groups'] = $adom_groups;
        
        // Return success response with absolute path
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'redirect' => '/portal/index.php']);
        exit;
    }
}

// Return error response
header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Authentication failed']);
exit;
