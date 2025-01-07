<?php
// Temporary simplified logging system
function logAccess($page, $method, $statusCode = 200, $additionalData = []) {
    $message = "ACCESS: $page [$method] Status: $statusCode";
    if (!empty($additionalData)) {
        $message .= " Data: " . json_encode($additionalData);
    }
    error_log($message);
}

function logActivity($action, $details = [], $status = 'success') {
    $message = "ACTIVITY: $action Status: $status";
    if (!empty($details)) {
        $message .= " Details: " . json_encode($details);
    }
    error_log($message);
}

function logError($message, $context = []) {
    $errorMsg = "ERROR: $message";
    if (!empty($context)) {
        $errorMsg .= " Context: " . json_encode($context);
    }
    error_log($errorMsg);
    return true;
}

function logAudit($action, $beforeState, $afterState, $entity) {
    $message = "AUDIT: $action on $entity";
    error_log($message);
}

function logPerformance($operation, $duration = null, $context = []) {
    if ($duration === null) {
        $duration = 0;
    }
    $message = "PERFORMANCE: $operation Duration: {$duration}ms";
    if (!empty($context)) {
        $message .= " Context: " . json_encode($context);
    }
    error_log($message);
}

// Start output buffering to capture response code
ob_start();

// Record script start time for performance logging
$scriptStartTime = microtime(true);

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
