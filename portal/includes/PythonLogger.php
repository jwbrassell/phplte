<?php
/**
 * PythonLogger Class
 * Minimal PHP interface that passes logging to Python backend
 */
class PythonLogger {
    private $logType;
    private $pythonScript;
    private $projectRoot;
    private $venvPath;

    public function __construct($type = 'general') {
        global $projectRoot;
        
        $validTypes = ['access', 'errors', 'client', 'audit', 'performance'];
        $this->logType = in_array($type, $validTypes) ? $type : 'general';
        
        // Use centralized path configuration
        $this->projectRoot = PROJECT_ROOT;
        $this->pythonScript = resolvePath('logging/logger.py', 'module');
        
        // Use configured virtual environment
        $this->venvPath = VENV_DIR ? VENV_DIR . '/bin/python3' : '/usr/bin/python3';
        
        error_log("PythonLogger Configuration:");
        error_log("- Project Root: " . $this->projectRoot);
        error_log("- Python Script: " . $this->pythonScript);
        error_log("- Virtual Env: " . $this->venvPath);
        
        // Verify Python setup
        if (!file_exists($this->pythonScript)) {
            error_log("WARNING: Python logging script not found at {$this->pythonScript}");
            error_log("Available files in modules/logging:");
            if (is_dir(dirname($this->pythonScript))) {
                error_log(print_r(scandir(dirname($this->pythonScript)), true));
            }
            $this->pythonScript = null;
        }
    }

    /**
     * Main logging method that passes data to Python
     */
    public function log($message, $level = 'INFO', $context = []) {
        global $PAGE;
        
        // Get comprehensive user info
        $userInfo = [
            'name' => $_SESSION[$GLOBALS['APP']."_user_name"] ?? null,
            'id' => $_SESSION[$GLOBALS['APP']."_user_vzid"] ?? null,
            'email' => $_SESSION[$GLOBALS['APP']."_user_email"] ?? null,
            'groups' => $_SESSION[$GLOBALS['APP']."_adom_groups"] ?? null
        ];
        
        // Add standard context data
        $context = array_merge([
            'user' => $userInfo['name'] ?: 'anonymous',
            'user_id' => $userInfo['id'] ?: 'unknown',
            'user_email' => $userInfo['email'] ?: 'unknown',
            'user_groups' => $userInfo['groups'] ?: 'none',
            'page' => $PAGE ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id() ?? 'no_session'
        ], $context);

        // Escape message and encode context as JSON
        $escapedMessage = escapeshellarg($message);
        $jsonContext = escapeshellarg(json_encode($context));
        
        // If Python logging is not available, fallback to PHP logging
        if (!$this->pythonScript || !$this->venvPath) {
            error_log("LOG: [$this->logType] $message " . json_encode($context));
            return false;
        }
        
        // Build command using discovered paths
        $command = sprintf('%s %s %s %s %s 2>&1',
            escapeshellarg($this->venvPath),
            escapeshellarg($this->pythonScript),
            escapeshellarg($this->logType),
            $escapedMessage,
            $jsonContext
        );

        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            // Only log critical failures
            if ($returnCode !== 1) { // Ignore minor failures
                error_log("CRITICAL: Logging system failure");
            }
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
        global $PAGE;
        $context = array_merge([
            'type' => 'performance',
            'operation' => $operation,
            'duration_ms' => $duration,
            'memory_usage' => memory_get_usage(true),
            'page' => $PAGE ?? 'unknown'
        ], $context);
        return $this->log("Performance: $operation took {$duration}ms", 'INFO', $context);
    }

    /**
     * Log data modifications
     */
    public function logAudit($action, $beforeState, $afterState, $entity) {
        global $PAGE;
        $context = [
            'type' => 'audit',
            'action' => $action,
            'entity' => $entity,
            'before' => $beforeState,
            'after' => $afterState,
            'page' => $PAGE ?? 'unknown'
        ];

        return $this->log("Audit: $action on $entity", 'INFO', $context);
    }
}
?>
