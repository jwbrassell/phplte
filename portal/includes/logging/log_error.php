<?php
require_once __DIR__ . '/../PythonLogger.php';

class ErrorLogger {
    private $pythonLogger;
    
    public function __construct() {
        $this->pythonLogger = new PythonLogger();
    }
    
    public function logError($error_message, $error_type = 'ERROR', $file = '', $line = '') {
        $context = [
            'type' => $error_type,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return $this->pythonLogger->log('error', $error_message, $context);
    }
    
    public function getErrorLogs($limit = 100) {
        // Use Python logger to fetch logs
        return $this->pythonLogger->getLogs('error', $limit);
    }
}

// Initialize logger
$logger = new ErrorLogger();

// Handle AJAX requests for client-side logging
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $type = $_POST['type'] ?? 'CLIENT_ERROR';
    $message = $_POST['message'] ?? 'Unknown error';
    $url = $_POST['url'] ?? '';
    $line = $_POST['line'] ?? '';
    $column = $_POST['column'] ?? '';
    
    $logger->logError($message, $type, $url, $line);
    
    echo json_encode(['status' => 'success']);
    exit;
}

// Global functions for backward compatibility
function logError($error_message, $error_type = 'ERROR', $file = '', $line = '') {
    global $logger;
    return $logger->logError($error_message, $error_type, $file, $line);
}

function getErrorLogs($limit = 100) {
    global $logger;
    return $logger->getErrorLogs($limit);
}
?>
