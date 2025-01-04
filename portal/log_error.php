<?php
/**
 * Client-side Error Logger
 * Handles logging of JavaScript errors and client-side issues
 */

// Initialize error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Function to safely write to error log
function write_client_error_log($message, $type = 'JS_ERROR') {
    $log_dir = __DIR__ . '/logs/client';
    if (!file_exists($log_dir)) {
        if (!mkdir($log_dir, 0755, true)) {
            error_log("Failed to create client error log directory: $log_dir");
            return false;
        }
    }
    
    $log_file = $log_dir . '/' . date('Ymd') . '_client_error.log';
    $log_message = sprintf(
        "%s||%s||%s||%s||%s\n",
        date('Y,m,d,H,i,s'),
        $type,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        $message
    );
    
    return error_log($log_message, 3, $log_file);
}

// Validate and sanitize input
$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING) ?: 'UNKNOWN';
$message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING) ?: 'No message provided';
$url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL) ?: 'No URL provided';
$line = filter_input(INPUT_POST, 'line', FILTER_SANITIZE_NUMBER_INT) ?: 'No line number';

// Construct error message
$error_message = sprintf(
    "Client Error: %s at %s:%s - %s",
    $type,
    $url,
    $line,
    $message
);

// Log the error
if (write_client_error_log($error_message, $type)) {
    http_response_code(200);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to log error']);
}
