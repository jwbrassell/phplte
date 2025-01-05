<?php
// Set up CLI environment
$_SERVER['REQUEST_URI'] = '/test_logging.php';
$_SERVER['SCRIPT_NAME'] = '/test_logging.php';

// Required includes
require_once(__DIR__ . '/includes/init.php');
require_once(__DIR__ . '/includes/logging_bootstrap.php');
require_once(__DIR__ . '/includes/PythonLogger.php');

// Initialize session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set up test session data
$_SESSION[$APP."_user_name"] = "test";
$_SESSION[$APP."_user_vzid"] = "test123";
$_SESSION[$APP."_user_email"] = "test@example.com";
$_SESSION[$APP."_adom_groups"] = "admin,user";
$PAGE = "test_logging.php";

// Only include header if not running from CLI
if (php_sapi_name() !== 'cli') {
    $TITLE = "Test Logging System";
    require_once(__DIR__ . '/header.php');
}

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

// Test user info and page name in different log types
$types = ['access', 'errors', 'client', 'audit', 'performance', 'general'];
foreach ($types as $type) {
    try {
        $logger = getLogger($type);
        $testId = uniqid();
        $message = "Test message for $type logging";
        $context = [
            'test_id' => $testId,
            'timestamp' => date('Y-m-d H:i:s'),
            'test_run' => true,
            'test_type' => 'user_info_verification'
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
            
            // Get the last log entry for verification
            $lastLog = end($logs);
            $details['last_log'] = $lastLog;
            
            // Verify user info and page name
            $success = true;
            $verificationErrors = [];
            
            if ($lastLog['user'] !== "test") {
                $success = false;
                $verificationErrors[] = "User name not correctly logged";
            }
            if ($lastLog['user_id'] !== "test123") {
                $success = false;
                $verificationErrors[] = "User ID not correctly logged";
            }
            if ($lastLog['user_email'] !== "test@example.com") {
                $success = false;
                $verificationErrors[] = "User email not correctly logged";
            }
            if ($lastLog['user_groups'] !== "admin,user") {
                $success = false;
                $verificationErrors[] = "User groups not correctly logged";
            }
            if ($lastLog['page'] !== "test_logging.php") {
                $success = false;
                $verificationErrors[] = "Page name not correctly logged";
            }
            
            if (!empty($verificationErrors)) {
                $details['verification_errors'] = $verificationErrors;
            }
            
            addResult($type, 'Logging test', $success, $details);
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

// Test specific logging functions
try {
    // Test access logging
    $logger = getLogger('access');
    $result = $logger->logAccess('test_page.php', 'GET', 200, ['test' => true]);
    addResult('access_specific', 'Access logging test', $result);

    // Test activity logging
    $logger = getLogger('audit');
    $result = $logger->logActivity('test_action', ['test' => true], 'success');
    addResult('activity_specific', 'Activity logging test', $result);

    // Test performance logging
    $logger = getLogger('performance');
    $result = $logger->logPerformance('test_operation', 100, ['test' => true]);
    addResult('performance_specific', 'Performance logging test', $result);

    // Test audit logging
    $logger = getLogger('audit');
    $result = $logger->logAudit('test_change', ['old' => 1], ['new' => 2], 'test_entity');
    addResult('audit_specific', 'Audit logging test', $result);

    // Test error logging
    $e = new Exception("Test error");
    $result = logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    addResult('error_specific', 'Error logging test', $result);
} catch (Exception $e) {
    addResult('specific_tests', 'Specific logging tests', false, [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
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

<?php
// Only include footer if not running from CLI
if (php_sapi_name() !== 'cli') {
    require_once(__DIR__ . '/footer.php');
} else {
    // CLI output
    echo "\nTest Results:\n";
    echo "=============\n\n";
    foreach ($results as $result) {
        echo sprintf(
            "%s: %s\n",
            str_pad($result['type'], 20),
            $result['success'] ? '✓ Success' : '✗ Failed'
        );
        if (!empty($result['details'])) {
            if (isset($result['details']['verification_errors'])) {
                echo "  Errors:\n";
                foreach ($result['details']['verification_errors'] as $error) {
                    echo "  - $error\n";
                }
            }
            if (isset($result['details']['error'])) {
                echo "  Error: " . $result['details']['error'] . "\n";
            }
        }
        echo "\n";
    }
}
?>
