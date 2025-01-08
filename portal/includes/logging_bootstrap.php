<?php
// Include error logging functionality
require_once __DIR__ . '/logging/log_error.php';

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
    logError($errstr, 'PHP_ERROR', $errfile, $errline);
    
    // Don't execute PHP's internal error handler
    return true;
}

// Set up exception handler
function customExceptionHandler($exception) {
    $message = "Uncaught Exception: " . $exception->getMessage() . "\nStack trace: " . $exception->getTraceAsString();
    logError($message, 'EXCEPTION', $exception->getFile(), $exception->getLine());
    
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
        logError($error['message'], 'FATAL_ERROR', $error['file'], $error['line']);
        
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
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $fullMessage = $message . $contextStr;
    
    switch (strtolower($type)) {
        case 'error':
            logError($fullMessage, 'ERROR');
            break;
        case 'access':
            logError($fullMessage, 'ACCESS');
            break;
        default:
            logError($fullMessage, strtoupper($type));
    }
}

// Initialize session handling
ini_set('session.save_handler', 'files');
ini_set('session.save_path', PROJECT_ROOT . '/portal/sessions');

// Ensure sessions directory exists
if (!is_dir(PROJECT_ROOT . '/portal/sessions')) {
    mkdir(PROJECT_ROOT . '/portal/sessions', 0775, true);
}
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.cookie_lifetime', 0); // Until browser closes
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (IS_PRODUCTION) {
    ini_set('session.cookie_secure', 1);
}
