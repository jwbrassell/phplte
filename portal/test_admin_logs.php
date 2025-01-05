<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/includes/init.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up test session
session_start();
$_SESSION[$APP.'_user_name'] = 'Test User';
$_SESSION[$APP.'_user_session'] = 'test';
$_SESSION[$APP.'_user_vzid'] = 'test123';
$_SESSION[$APP.'_user_email'] = 'test@example.com';
$_SESSION[$APP.'_adom_groups'] = 'admin,user';

echo "Session data:\n";
print_r($_SESSION);

// Write some test logs
require_once(__DIR__ . '/includes/logging_bootstrap.php');

echo "\nWriting test logs...\n";
logAccess('test_page.php', 'GET', 200, ['test' => true]);
logError('Test error message', ['error_code' => 'TEST_001']);
logActivity('test_action', ['action_id' => 123], 'success');
logPerformance('test_operation', 150, ['operation_id' => 456]);
logAudit('test_update', ['before' => 'old'], ['after' => 'new'], 'test_entity');

// Set up POST data
$_POST = [
    'draw' => 1,
    'start' => 0,
    'length' => 10,
    'logType' => 'all',
    'dateFilter' => '2024-01-05'
];
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "\nPOST data:\n";
print_r($_POST);

echo "\nChecking log files:\n";
$logTypes = ['access', 'errors', 'performance', 'audit'];
foreach ($logTypes as $type) {
    $logFile = __DIR__ . "/logs/$type/2024-01-05.log";
    echo "$type log file exists: " . (file_exists($logFile) ? 'yes' : 'no') . "\n";
    if (file_exists($logFile)) {
        echo "Content:\n" . file_get_contents($logFile) . "\n";
    }
}

// Capture output
ob_start();
require_once(__DIR__ . '/api/get_logs.php');
$response = ob_get_clean();

echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT);
