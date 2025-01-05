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

require_once(__DIR__ . '/includes/logging_bootstrap.php');

// Validate and sanitize input
$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING) ?: 'UNKNOWN';
$message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING) ?: 'No message provided';
$url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL) ?: 'No URL provided';
$line = filter_input(INPUT_POST, 'line', FILTER_SANITIZE_NUMBER_INT) ?: 'No line number';
$column = filter_input(INPUT_POST, 'column', FILTER_SANITIZE_NUMBER_INT);
$error = filter_input(INPUT_POST, 'error', FILTER_SANITIZE_STRING);
$page = filter_input(INPUT_POST, 'page', FILTER_SANITIZE_STRING);
$duration = filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

// Handle different types of client-side events
switch ($type) {
    case 'JS_ERROR':
        $context = [
            'url' => $url,
            'line' => $line,
            'column' => $column,
            'stack_trace' => $error,
            'page' => $page
        ];
        logError($message, array_filter($context));
        break;

    case 'PERFORMANCE':
        if ($duration !== false) {
            logPerformance('client_side_' . strtolower($message), $duration, [
                'page' => $page,
                'url' => $url
            ]);
        }
        break;

    default:
        logActivity('client_event', [
            'type' => $type,
            'message' => $message,
            'url' => $url,
            'page' => $page
        ]);
}

http_response_code(200);
echo json_encode(['status' => 'success']);
