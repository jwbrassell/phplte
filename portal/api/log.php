<?php
require_once __DIR__ . '/../includes/init.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Get POST data
$type = $_POST['type'] ?? 'CLIENT_ERROR';
$message = $_POST['message'] ?? 'Unknown error';
$url = $_POST['url'] ?? '';
$line = $_POST['line'] ?? '';
$column = $_POST['column'] ?? '';

try {
    // Log the error
    logError($message, $type, $url, $line);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to log error'
    ]);
}
