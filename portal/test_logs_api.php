<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock session and admin access
$APP = 'portal';
$_SESSION = [
    $APP.'_user_name' => 'test_user',
    $APP.'_adom_groups' => '[admin]'
];

// Mock POST parameters that would come from the admin dashboard
$_POST = [
    'draw' => 1,
    'start' => 0,
    'length' => 10,
    'logType' => 'all',
    'dateFilter' => '2025-01-05'
];

// Include required files
require_once(__DIR__ . '/includes/init.php');

try {
    // Get log files from new location
    $logDir = dirname(__DIR__) . '/shared/data/logs/system';
    echo "Looking for logs in: $logDir\n\n";
    
    $validLogTypes = ['all', 'access', 'errors', 'client', 'audit', 'performance'];
    $logType = $_POST['logType'];
    $dateFilter = $_POST['dateFilter'];
    
    echo "Log type: $logType\n";
    echo "Date filter: $dateFilter\n\n";
    
    $logFiles = [];
    
    if ($logType === 'all') {
        foreach ($validLogTypes as $type) {
            if ($type !== 'all') {
                $logFile = $logDir . '/' . $type . '/' . $dateFilter . '.json';
                echo "Checking file: $logFile\n";
                if (file_exists($logFile) && is_readable($logFile)) {
                    echo "Found log file: $logFile\n";
                    $logFiles[] = [
                        'file' => $logFile,
                        'type' => $type
                    ];
                } else {
                    echo "File not found or not readable: $logFile\n";
                }
            }
        }
    }
    
    // Process log files
    $allEntries = [];
    foreach ($logFiles as $fileInfo) {
        try {
            echo "\nReading file: {$fileInfo['file']}\n";
            $content = file_get_contents($fileInfo['file']);
            $entries = json_decode($content, true);
            if ($entries === null) {
                echo "JSON decode error: " . json_last_error_msg() . "\n";
                continue;
            }
            echo "Found " . count($entries) . " entries\n";
            
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
                            'details' => json_encode($entry['details'] ?? [], JSON_PRETTY_PRINT)
                        ];
                        $allEntries[] = $formattedEntry;
                    } catch (Exception $e) {
                        echo "Error processing entry: " . json_encode($entry) . "\n";
                        echo "Error: " . $e->getMessage() . "\n";
                        continue;
                    }
                }
            }
        } catch (Exception $e) {
            echo "Error reading log file {$fileInfo['file']}: " . $e->getMessage() . "\n";
            continue;
        }
    }

    echo "\nTotal entries found: " . count($allEntries) . "\n";
    echo "\nFirst 3 entries:\n";
    print_r(array_slice($allEntries, 0, 3));
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
