<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/includes/init.php');
require_once(__DIR__ . '/includes/logging_bootstrap.php');

// Set up test session
$_SESSION[$APP.'_user_name'] = 'Test User';
$_SESSION[$APP.'_adom_groups'] = 'admin,user';

// Write test entries to each log type
logAccess('test_page.php', 'GET', 200, [
    'test' => true,
    'nested' => ['key' => 'value']
]);

logError('Test error message', [
    'error_code' => 'TEST_001',
    'details' => ['source' => 'test_script']
]);

logActivity('test_action', [
    'action_id' => 123,
    'metadata' => ['key' => 'value']
], 'success');

logPerformance('test_operation', 150, [
    'operation_id' => 456,
    'metrics' => ['memory' => '10MB']
]);

logAudit('test_update', 
    ['before' => 'old_value'],
    ['after' => 'new_value'],
    'test_entity'
);

echo "Test logs written. Check admin_logs.php to view them.";
