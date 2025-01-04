<?php
/**
 * Python Script Execution Handler
 * Manages execution of Python scripts with logging and error handling
 */

include('config.php');

// Configure logging
$log_file = '/var/www/html/shared/scripts/modules/debug.log';

/**
 * Logs a message to the debug log file
 * @param string $message Message to log
 */
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(
        $log_file, 
        "[$timestamp] $message" . PHP_EOL, 
        FILE_APPEND
    );
}

// Log current user information
$current_user_name = get_current_user();
log_message("Current User Running the Script: $current_user_name");

// Get and parse request data
$rawData = file_get_contents('php://input');
log_message("Raw Request Payload: $rawData");

$data = json_decode($rawData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_message("JSON Decode Error: " . json_last_error_msg());
    die("Invalid JSON payload");
}

// Process request payload
$request_payload = $data['request_payload'] ?? null;
if (!$request_payload) {
    log_message("Error: No request payload found");
    die("Missing request payload");
}

// Format request for processing
$request_to_process = is_array($request_payload) 
    ? json_encode($request_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    : $request_payload;

log_message("Processed Request Payload: $request_to_process");

// Get request type and script directory
$request_type = $data['request_payload']['request_type'] ?? null;
$script_directory = $data['request_payload']['script_directory'] ?? null;

// Determine script path based on request type
if ($request_type) {
    switch ($request_type) {
        case 'documents':
            $script_directory = "/var/www/html/shared/scripts/modules/documents";
            $script = 'documents.py';
            break;
            
        case 'rbac':
            $script_directory = "/var/www/html/shared/scripts/modules/rbac";
            $script = 'rbac.py';
            break;
            
        case 'example':
            $script = 'example_script.py';
            break;
            
        default:
            $script = 'default_script.py';
            break;
    }
} else {
    $script = 'default_script.py';
}

// Validate script directory or use default
$directory = isset($script_directory) && !empty($script_directory) 
    ? $script_directory 
    : "$DIR/scripts";

// Prepare command with proper escaping
$escaped_request = escapeshellarg($request_to_process);
$command = "/opt/python-venv/bin/python3 $directory/$script $escaped_request";
log_message("Command to Execute: $command");

// Execute command and capture output
exec($command . ' 2>&1', $output, $status);
$output_text = implode("\n", $output);

log_message("Command Output: $output_text");
log_message("Command Status: $status");

// Return results
if ($status === 0) {
    echo $output_text;
} else {
    log_message("Command execution failed");
    echo $output_text;
    echo "\nExecution was not successful\n";
    echo $command;
}
