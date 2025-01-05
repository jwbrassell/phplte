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

// Set error log path
ini_set('error_log', APP_ROOT . '/logs/errors/' . date('Y-m-d') . '.log');

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
    $validLogTypes = ['all', 'access', 'errors', 'client', 'python', 'audit', 'performance'];
    if (!in_array($logType, $validLogTypes)) {
        sendJsonResponse([
            'error' => 'Invalid log type',
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => []
        ], 400);
    }

    // Get log files
    $logDir = APP_ROOT . '/logs';
    $logFiles = [];
    
    if ($logType === 'all') {
        foreach ($validLogTypes as $type) {
            if ($type !== 'all') {
                $logFile = $logDir . '/' . $type . '/' . $dateFilter . '.log';
                if (file_exists($logFile) && is_readable($logFile)) {
                    $logFiles[] = $logFile;
                }
            }
        }
    } else {
        $logFile = $logDir . '/' . $logType . '/' . $dateFilter . '.log';
        if (file_exists($logFile) && is_readable($logFile)) {
            $logFiles[] = $logFile;
        }
    }

    // Process log files
    $allEntries = [];
    foreach ($logFiles as $file) {
        if ($lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) {
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry) {
                    $allEntries[] = [
                        'timestamp' => $entry['timestamp'] ?? date('Y-m-d H:i:s'),
                        'type' => $entry['type'] ?? 'unknown',
                        'user' => $entry['user'] ?? 'system',
                        'message' => $entry['message'] ?? '',
                        'details' => json_encode(array_merge(
                            ['level' => $entry['level'] ?? 'unknown'],
                            ['status' => $entry['status'] ?? null],
                            ['action' => $entry['action'] ?? null],
                            ['details' => $entry['details'] ?? null]
                        ), JSON_PRETTY_PRINT)
                    ];
                }
            }
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
