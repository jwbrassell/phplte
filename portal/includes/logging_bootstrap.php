<?php
require_once(__DIR__ . '/PythonLogger.php');

// Initialize different logger instances
$accessLogger = new PythonLogger('access');
$errorLogger = new PythonLogger('errors');
$auditLogger = new PythonLogger('audit');
$perfLogger = new PythonLogger('performance');

// Remove old logger if it exists
if (file_exists(__DIR__ . '/Logger.php')) {
    unlink(__DIR__ . '/Logger.php');
}

// Remove old log directory and its contents
$oldLogDir = dirname(dirname(__DIR__)) . '/portal/logs';
if (file_exists($oldLogDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($oldLogDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($oldLogDir);
}

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
    global $errorLogger, $PAGE;
    
    // Ensure error context includes page name if not already set
    if (!isset($context['page'])) {
        $context['page'] = $PAGE ?? 'unknown';
    }
    
    return $errorLogger->log($message, 'ERROR', $context);
}

function logAudit($action, $beforeState, $afterState, $entity) {
    global $auditLogger;
    $auditLogger->logAudit($action, $beforeState, $afterState, $entity);
}

function logPerformance($operation, $duration = null, $context = []) {
    global $perfLogger, $scriptStartTime, $PAGE;
    
    if ($duration === null) {
        $duration = (microtime(true) - $scriptStartTime) * 1000; // Convert to milliseconds
    }
    
    // Include page name in operation for clarity
    $pageContext = $PAGE ?? 'unknown';
    if ($operation === 'page_execution') {
        $operation = "page_execution:{$pageContext}";
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

// Function to get a logger instance
function getLogger($type) {
    return new PythonLogger($type);
}
?>
