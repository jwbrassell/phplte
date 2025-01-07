<?php
/**
 * Temporary simplified error logger to prevent blocking authentication
 */

// Initialize error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Simple logging to PHP error_log
$type = $_POST['type'] ?? 'UNKNOWN';
$message = $_POST['message'] ?? 'No message provided';
$url = $_POST['url'] ?? 'No URL provided';

// Log to PHP error_log instead of Python logger
error_log("Client Error [$type]: $message at $url");

// Always return success to prevent client-side errors
http_response_code(200);
echo json_encode(['status' => 'success']);
