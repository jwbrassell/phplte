<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate POST variables
$_POST = array(
    'login_user' => 'test',
    'login_passwd' => 'test123',
    'APP' => 'framework'
);

// Include config for path resolution
require_once(__DIR__ . '/portal/config.php');

// Debug information
echo "Environment Information:\n";
echo "PROJECT_ROOT: " . PROJECT_ROOT . "\n";
echo "VENV_DIR: " . VENV_DIR . "\n";
echo "IS_PRODUCTION: " . (IS_PRODUCTION ? 'true' : 'false') . "\n";

// Verify paths exist
$pythonPath = VENV_DIR . "/bin/python";
$scriptPath = PROJECT_ROOT . "/shared/scripts/modules/ldap/ldapcheck.py";

echo "\nPath Verification:\n";
echo "Python Path: $pythonPath " . (file_exists($pythonPath) ? "(exists)" : "(missing)") . "\n";
echo "Script Path: $scriptPath " . (file_exists($scriptPath) ? "(exists)" : "(missing)") . "\n";

// Set up Python command with full error output and Python traceback
putenv("PYTHONPATH=" . PROJECT_ROOT . "/shared/scripts/modules");
$cmd = sprintf('%s -u %s test test123 framework 2>&1',
    escapeshellarg($pythonPath),
    escapeshellarg($scriptPath)
);

// Execute command and capture all output
echo "\nExecuting command: $cmd\n";
$output = shell_exec($cmd);
echo "Output:\n$output\n";
