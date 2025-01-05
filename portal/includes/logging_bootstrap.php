<?php
require_once(__DIR__ . '/Logger.php');

// Initialize different logger instances
$accessLogger = new Logger('access');
$errorLogger = new Logger('errors');
$auditLogger = new Logger('audit');
$perfLogger = new Logger('performance');

// Start output buffering to capture response code
ob_start();

// Record script start time for performance logging
$scriptStartTime = microtime(true);

// Helper functions for easy logging access
function logAccess($page, $method, $statusCode = 200, $additionalData = []) {
    global $accessLogger;
    $accessLogger->logAccess($page, $method, $statusCode, $additionalData);
}

function logActivity($action, $details = [], $status = 'success') {
    global $accessLogger;
    $accessLogger->logActivity($action, $details, $status);
}

function logError($message, $context = []) {
    global $errorLogger;
    $errorLogger->log($message, 'ERROR', $context);
}

function logAudit($action, $beforeState, $afterState, $entity) {
    global $auditLogger;
    $auditLogger->logAudit($action, $beforeState, $afterState, $entity);
}

function logPerformance($operation, $duration = null, $context = []) {
    global $perfLogger, $scriptStartTime;
    
    if ($duration === null) {
        $duration = (microtime(true) - $scriptStartTime) * 1000; // Convert to milliseconds
    }
    
    $perfLogger->logPerformance($operation, $duration, $context);
}

// Register shutdown function to log performance metrics
register_shutdown_function(function() {
    global $scriptStartTime;
    $duration = (microtime(true) - $scriptStartTime) * 1000;
    logPerformance('page_execution', $duration);
});

// Function to get response code from output buffer
function getResponseCode() {
    $status_line = $http_response_header[0] ?? '';
    preg_match('{HTTP/\S*\s(\d{3})}', $status_line, $match);
    return $match[1] ?? 200;
}
?>
