<?php
/**
 * Error logger that integrates with PythonLogger
 */

// Initialize error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include PythonLogger
require_once(__DIR__ . '/PythonLogger.php');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Get error details
$type = $_POST['type'] ?? 'UNKNOWN';
$message = $_POST['message'] ?? 'No message provided';
$url = $_POST['url'] ?? 'No URL provided';
$line = $_POST['line'] ?? null;
$column = $_POST['column'] ?? null;
$error = $_POST['error'] ?? null;
$page = $_POST['page'] ?? 'unknown';

// Create context for logging
$context = [
    'type' => 'client_error',
    'error_type' => $type,
    'url' => $url,
    'page' => $page
];

if ($line !== null) {
    $context['line'] = $line;
}

if ($column !== null) {
    $context['column'] = $column;
}

if ($error !== null) {
    $context['stack_trace'] = $error;
}

// Initialize Python logger
$logger = new PythonLogger('errors');

// Log the error
$logger->log($message, 'ERROR', $context);

// Always return success to prevent client-side errors
http_response_code(200);
echo json_encode(['status' => 'success']);
?>
