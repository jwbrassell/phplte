<?php
// Start output buffering and session before anything else
ob_start();
session_start();

// Enable error reporting but prevent display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define the application root path
define('APP_ROOT', dirname(dirname(__FILE__)));

// Include required files
require_once(APP_ROOT . "/includes/init.php");

// Function to send JSON response and exit
function sendJsonResponse($data, $statusCode = 200) {
    $buffer = ob_get_clean(); // Capture and discard any output
    if ($buffer) {
        error_log("Unexpected output before JSON response: " . $buffer);
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

// Check session and admin access first
if (!isset($_SESSION[$APP.'_user_name'])) {
    sendJsonResponse([
        'error' => 'No user session found',
        'draw' => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ], 401);
}

// Parse admin groups from session
$adom_groups_string = $_SESSION[$APP.'_adom_groups'] ?? '';
$adom_groups = array_map('trim', explode(',', str_replace(["[", "]", "'", "\""], "", $adom_groups_string)));

if (!in_array('admin', $adom_groups)) {
    sendJsonResponse([
        'error' => 'Admin access required',
        'draw' => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ], 403);
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Invalid request method'], 405);
}

try {
    // Validate and sanitize request parameters
    $draw = filter_input(INPUT_POST, 'draw', FILTER_VALIDATE_INT) ?: 1;
    $start = filter_input(INPUT_POST, 'start', FILTER_VALIDATE_INT) ?: 0;
    $length = filter_input(INPUT_POST, 'length', FILTER_VALIDATE_INT) ?: 10;
    $logType = filter_input(INPUT_POST, 'logType', FILTER_SANITIZE_STRING) ?: 'all';
    $dateFilter = filter_input(INPUT_POST, 'dateFilter', FILTER_SANITIZE_STRING) ?: date('Y-m-d');

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter) || !strtotime($dateFilter)) {
        sendJsonResponse([
            'error' => 'Invalid date format',
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => []
        ], 400);
    }

    // Validate log type
    $validLogTypes = ['all', 'access', 'errors', 'client', 'audit', 'performance'];
    if (!in_array($logType, $validLogTypes)) {
        sendJsonResponse([
            'error' => 'Invalid log type',
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => []
        ], 400);
    }

    // Get log files from new location
    $logDir = APP_ROOT . '/../shared/data/logs/system';
    error_log("Looking for logs in: $logDir");
    error_log("Log type: $logType");
    error_log("Date filter: $dateFilter");
    
    $logFiles = [];
    
    if ($logType === 'all') {
        foreach ($validLogTypes as $type) {
            if ($type !== 'all') {
                $logFile = $logDir . '/' . $type . '/' . $dateFilter . '.json';
                error_log("Checking file: $logFile");
                if (file_exists($logFile) && is_readable($logFile)) {
                    error_log("Found log file: $logFile");
                    $logFiles[] = [
                        'file' => $logFile,
                        'type' => $type
                    ];
                } else {
                    error_log("File not found or not readable: $logFile");
                }
            }
        }
    } else {
        $logFile = $logDir . '/' . $logType . '/' . $dateFilter . '.json';
        error_log("Checking file: $logFile");
        if (file_exists($logFile) && is_readable($logFile)) {
            error_log("Found log file: $logFile");
            $logFiles[] = [
                'file' => $logFile,
                'type' => $logType
            ];
        } else {
            error_log("File not found or not readable: $logFile");
        }
    }

    // Process log files
    $allEntries = [];
    foreach ($logFiles as $fileInfo) {
        try {
            error_log("Reading file: {$fileInfo['file']}");
            $content = file_get_contents($fileInfo['file']);
            $entries = json_decode($content, true);
            if ($entries === null) {
                error_log("JSON decode error: " . json_last_error_msg());
                continue;
            }
            error_log("Found " . count($entries) . " entries");
            
            if (is_array($entries)) {
                foreach ($entries as $entry) {
                    // Format timestamp to local time
                    try {
                        $timestamp = new DateTime($entry['timestamp']);
                        $timestamp->setTimezone(new DateTimeZone(date_default_timezone_get()));
                        
                        $formattedEntry = [
                            'timestamp' => $timestamp->format('Y-m-d H:i:s'),
                            'type' => $entry['type'] ?? $fileInfo['type'],
                            'user' => $entry['user'] ?? 'system',
                            'message' => $entry['message'] ?? '',
                            'details' => $entry['details'] ?? []
                        ];
                        error_log("Processed entry: " . json_encode($formattedEntry));
                        $allEntries[] = $formattedEntry;
                    } catch (Exception $e) {
                        error_log("Error processing entry: " . json_encode($entry));
                        error_log("Error: " . $e->getMessage());
                        continue;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error reading log file {$fileInfo['file']}: " . $e->getMessage());
            continue;
        }
    }

    // If no entries found, return empty response with message
    if (empty($allEntries)) {
        sendJsonResponse([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'message' => 'No log entries found for the selected date and type.'
        ]);
    }
    
    // Sort entries by timestamp descending
    usort($allEntries, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Apply search if present
    $filteredEntries = $allEntries;
    if (isset($_POST['search']['value']) && !empty($_POST['search']['value'])) {
        $search = strtolower($_POST['search']['value']);
        $filteredEntries = array_filter($allEntries, function($entry) use ($search) {
            return strpos(strtolower(json_encode($entry)), $search) !== false;
        });
        $filteredEntries = array_values($filteredEntries); // Reset array keys
    }
    
    // Prepare response
    $response = [
        'draw' => $draw,
        'recordsTotal' => count($allEntries),
        'recordsFiltered' => count($filteredEntries),
        'data' => array_slice($filteredEntries, $start, $length)
    ];

    sendJsonResponse($response);
    
} catch (Exception $e) {
    error_log("Error processing logs request: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    sendJsonResponse([
        'error' => 'Failed to process logs: ' . $e->getMessage(),
        'draw' => $draw ?? 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ], 500);
}
?>
