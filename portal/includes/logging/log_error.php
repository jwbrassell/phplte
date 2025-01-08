<?php
// Handle AJAX requests for client-side logging
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $type = $_POST['type'] ?? 'CLIENT_ERROR';
    $message = $_POST['message'] ?? 'Unknown error';
    $url = $_POST['url'] ?? '';
    $line = $_POST['line'] ?? '';
    $column = $_POST['column'] ?? '';
    
    logError($message, $type, $url, $line);
    
    echo json_encode(['status' => 'success']);
    exit;
}

// Error logging functionality
function logError($error_message, $error_type = 'ERROR', $file = '', $line = '') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$error_type}] ";
    
    if ($file && $line) {
        $log_entry .= "File: {$file}, Line: {$line} - ";
    }
    
    $log_entry .= $error_message . PHP_EOL;
    
    // Ensure logs directory exists
    $log_dir = dirname(__FILE__) . '/../../logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // Write to error log file
    $log_file = $log_dir . '/error.log';
    error_log($log_entry, 3, $log_file);
    
    return true;
}

// Function to get all error logs
function getErrorLogs($limit = 100) {
    $log_file = dirname(__FILE__) . '/../../logs/error.log';
    if (!file_exists($log_file)) {
        return [];
    }
    
    $logs = file($log_file);
    $logs = array_reverse($logs); // Most recent first
    return array_slice($logs, 0, $limit);
}
?>
