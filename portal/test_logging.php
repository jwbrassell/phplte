<?php
$TITLE = "Test Logging System";
require_once(__DIR__ . '/header.php');

// Buffer to store test results
$results = [];

function addResult($type, $message, $success = true, $details = []) {
    global $results;
    $results[] = [
        'type' => $type,
        'message' => $message,
        'success' => $success,
        'details' => $details
    ];
}

// Test different log types
$types = ['access', 'errors', 'client', 'audit', 'performance', 'general'];
foreach ($types as $type) {
    try {
        $logger = getLogger($type);
        $testId = uniqid();
        $message = "Test message for $type logging";
        $context = [
            'test_id' => $testId,
            'timestamp' => date('Y-m-d H:i:s'),
            'test_run' => true
        ];
        
        $result = $logger->log($message, 'INFO', $context);
        
        // Get the log file path
        $root = dirname(__DIR__);
        $logDir = $root . '/shared/data/logs/system/' . $type;
        $logFile = $logDir . '/' . date('Y-m-d') . '.json';
        
        $details = [
            'log_dir' => $logDir,
            'log_file' => $logFile,
            'test_id' => $testId
        ];
        
        if (file_exists($logFile)) {
            $details['file_exists'] = true;
            $details['permissions'] = substr(sprintf('%o', fileperms($logFile)), -4);
            $details['size'] = filesize($logFile);
            
            $content = file_get_contents($logFile);
            $logs = json_decode($content, true);
            $details['entries'] = count($logs);
            
            addResult($type, 'Logging test', $result, $details);
        } else {
            $details['file_exists'] = false;
            addResult($type, 'Logging test', false, $details);
        }
    } catch (Exception $e) {
        addResult($type, 'Logging test', false, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

// Test error logging specifically
try {
    throw new Exception("Test error");
} catch (Exception $e) {
    $result = logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    addResult('error', 'Error logging test', $result);
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Logging System Test Results</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php foreach ($results as $result): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?= ucfirst($result['type']) ?> Test
                        <?php if ($result['success']): ?>
                            <span class="badge badge-success">Success</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Failed</span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-body">
                    <p><?= htmlspecialchars($result['message']) ?></p>
                    <?php if (!empty($result['details'])): ?>
                        <pre><?= htmlspecialchars(json_encode($result['details'], JSON_PRETTY_PRINT)) ?></pre>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Next Steps</h3>
                </div>
                <div class="card-body">
                    <p>After testing, you can:</p>
                    <a href="admin_logs.php" class="btn btn-primary">View Logs Dashboard</a>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once(__DIR__ . '/footer.php'); ?>
