<?php
/**
 * PythonLogger Class
 * Minimal PHP interface that passes logging to Python backend
 */
class PythonLogger {
    private $logType;
    private $pythonScript;

    public function __construct($type = 'general') {
        $validTypes = ['access', 'errors', 'client', 'audit', 'performance'];
        $this->logType = in_array($type, $validTypes) ? $type : 'general';
        
        // Get path to Python script by navigating from current file
        $portalDir = dirname(dirname(__DIR__)); // Up from includes to portal to root
        $this->pythonScript = $portalDir . '/shared/scripts/modules/logging/logger.py';
        
        if (!file_exists($this->pythonScript)) {
            error_log("Python logging script not found at: " . $this->pythonScript);
            throw new Exception("Logging system not properly configured");
        }
    }

    /**
     * Main logging method that passes data to Python
     */
    public function log($message, $level = 'INFO', $context = []) {
        // Add standard context data
        $context = array_merge([
            'user' => $_SESSION[$GLOBALS['APP']."_user_name"] ?? 'anonymous',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id() ?? 'no_session'
        ], $context);

        // Escape message and encode context as JSON
        $escapedMessage = escapeshellarg($message);
        $jsonContext = escapeshellarg(json_encode($context));
        
        // Build and execute command
        $command = sprintf('python3 %s %s %s %s 2>&1',
            escapeshellarg($this->pythonScript),
            escapeshellarg($this->logType),
            $escapedMessage,
            $jsonContext
        );

        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            error_log("Python logger failed with return code $returnCode");
            error_log("Command: $command");
            error_log("Output: " . implode("\n", $output));
            return false;
        }
        
        return true;
    }

    /**
     * Log page access
     */
    public function logAccess($page, $method, $statusCode = 200, $additionalData = []) {
        $context = [
            'type' => 'page_access',
            'page' => $page,
            'method' => $method,
            'status_code' => $statusCode,
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
            'query_string' => $_SERVER['QUERY_STRING'] ?? ''
        ];

        if (!empty($additionalData)) {
            $context['additional_data'] = $additionalData;
        }

        return $this->log("Page accessed: $page", 'INFO', $context);
    }

    /**
     * Log user activity
     */
    public function logActivity($action, $details = [], $status = 'success') {
        $context = [
            'type' => 'user_activity',
            'action' => $action,
            'status' => $status,
            'details' => $details
        ];

        return $this->log("User activity: $action", 'INFO', $context);
    }

    /**
     * Log performance metrics
     */
    public function logPerformance($operation, $duration, $context = []) {
        $perfData = [
            'type' => 'performance',
            'operation' => $operation,
            'duration_ms' => $duration,
            'memory_usage' => memory_get_usage(true)
        ];

        $context = array_merge($perfData, $context);
        return $this->log("Performance: $operation took {$duration}ms", 'INFO', $context);
    }

    /**
     * Log data modifications
     */
    public function logAudit($action, $beforeState, $afterState, $entity) {
        $context = [
            'type' => 'audit',
            'action' => $action,
            'entity' => $entity,
            'before' => $beforeState,
            'after' => $afterState
        ];

        return $this->log("Audit: $action on $entity", 'INFO', $context);
    }
}
?>
