<?php
// Set error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/errors/php_error.log');
error_reporting(E_ALL);

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/includes/init.php');
require_once(__DIR__ . '/includes/logging_bootstrap.php');

error_log("=== Test Log Script Start ===");
error_log("APP variable: " . print_r($APP, true));
error_log("Current directory: " . __DIR__);
error_log("Log directory should be: " . __DIR__ . '/logs');
error_log("PHP error_log path: " . ini_get('error_log'));

try {
    // Test writing to each type of log
    error_log("Attempting to write access log...");
    logAccess('test_log.php', 'GET', 200, ['test' => true]);
    
    error_log("Attempting to write error log...");
    logError('Test error message', ['test' => true]);
    
    error_log("Attempting to write activity log...");
    logActivity('test_activity', ['test' => true]);
    
    error_log("Attempting to write performance log...");
    logPerformance('test_operation', 100, ['test' => true]);
    
    error_log("Attempting to write audit log...");
    logAudit('test_audit', ['before' => 'test'], ['after' => 'test'], 'test_entity');
} catch (Exception $e) {
    error_log("Exception caught: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}

echo "Test logs written. Check the log files in portal/logs/";
