<?php
require_once(__DIR__ . '/includes/init.php');
require_once(__DIR__ . '/includes/logging_bootstrap.php');

echo "Testing logging system...\n\n";

try {
    // Test access logging
    logAccess('test_write.php', 'GET', 200, [
        'test_id' => uniqid(),
        'source' => 'test_write.php'
    ]);
    echo "Access log written\n";

    // Test error logging
    logError("Test error message", [
        'error_code' => 500,
        'test_id' => uniqid(),
        'source' => 'test_write.php'
    ]);
    echo "Error log written\n";

    // Test performance logging
    logPerformance("test_operation", 123.45, [
        'test_id' => uniqid(),
        'source' => 'test_write.php'
    ]);
    echo "Performance log written\n";

    // Test audit logging
    logAudit(
        "test_update",
        ['old_value' => 'before'],
        ['new_value' => 'after'],
        'test_entity'
    );
    echo "Audit log written\n";

    // Test client logging
    $logger = getLogger('client');
    $logger->log("Test client message", 'INFO', [
        'test_id' => uniqid(),
        'source' => 'test_write.php',
        'browser' => 'test'
    ]);
    echo "Client log written\n";

    // List all log files
    $logDir = dirname(__DIR__) . '/shared/data/logs/system';
    echo "\nChecking log files in: $logDir\n";
    
    if (is_dir($logDir)) {
        foreach (scandir($logDir) as $type) {
            if ($type === '.' || $type === '..') continue;
            
            $typeDir = $logDir . '/' . $type;
            if (!is_dir($typeDir)) continue;
            
            echo "\n$type logs:\n";
            foreach (scandir($typeDir) as $file) {
                if (preg_match('/\.json$/', $file)) {
                    $filePath = $typeDir . '/' . $file;
                    $content = file_get_contents($filePath);
                    $logs = json_decode($content, true);
                    echo "  $file: " . count($logs) . " entries\n";
                }
            }
        }
    }

    echo "\nAll tests completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
