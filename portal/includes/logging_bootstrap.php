<?php
// Ensure error reporting is properly configured
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG);

// Initialize logging directories if they don't exist
$logDirs = [
    LOG_DIR . '/errors',
    LOG_DIR . '/access',
    LOG_DIR . '/python',
    LOG_DIR . '/client'
];

foreach ($logDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

// Set up error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $logMessage = date('[Y-m-d H:i:s]') . " PHP {$errno}: {$errstr} in {$errfile} on line {$errline}\n";
    error_log($logMessage, 3, ERROR_LOG);
    
    // Don't execute PHP's internal error handler
    return true;
}

// Set up exception handler
function customExceptionHandler($exception) {
    $logMessage = date('[Y-m-d H:i:s]') . " Uncaught Exception: " . $exception->getMessage() . 
                 " in " . $exception->getFile() . " on line " . $exception->getLine() . 
                 "\nStack trace: " . $exception->getTraceAsString() . "\n";
    error_log($logMessage, 3, ERROR_LOG);
    
    // Display user-friendly error message in production
    if (!DEBUG_MODE) {
        http_response_code(500);
        die("An error occurred. Please try again later.");
    } else {
        // In debug mode, show detailed error
        throw $exception;
    }
}

// Set up shutdown function to catch fatal errors
function shutdownHandler() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logMessage = date('[Y-m-d H:i:s]') . " Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}\n";
        error_log($logMessage, 3, ERROR_LOG);
        
        if (!DEBUG_MODE) {
            http_response_code(500);
            die("An error occurred. Please try again later.");
        }
    }
}

// Register handlers
set_error_handler("customErrorHandler");
set_exception_handler("customExceptionHandler");
register_shutdown_function("shutdownHandler");

// Function to log application events
function logEvent($type, $message, $context = []) {
    $timestamp = date('[Y-m-d H:i:s]');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logMessage = "{$timestamp} {$type}: {$message}{$contextStr}\n";
    
    switch (strtolower($type)) {
        case 'error':
            error_log($logMessage, 3, ERROR_LOG);
            break;
        case 'access':
            error_log($logMessage, 3, ACCESS_LOG);
            break;
        default:
            error_log($logMessage, 3, ERROR_LOG);
    }
}

// Initialize session handling
ini_set('session.save_handler', 'files');
ini_set('session.save_path', '/var/lib/php/session');
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.cookie_lifetime', 0); // Until browser closes
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (IS_PRODUCTION) {
    ini_set('session.cookie_secure', 1);
}
