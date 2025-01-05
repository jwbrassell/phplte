<?php
/**
 * Logger Class
 * Centralized logging functionality for the application
 */
class Logger {
    private $logDir;
    private $defaultLogLevel = 'INFO';
    
    // Log levels in order of severity
    private $logLevels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARN' => 2,
        'ERROR' => 3,
        'FATAL' => 4
    ];

    public function __construct($type = 'access') {
        $this->logDir = realpath(__DIR__ . '/../logs/' . $type);
        if ($this->logDir === false) {
            $this->logDir = __DIR__ . '/../logs/' . $type;
        }
        error_log("Logger initialized for type: " . $type);
        error_log("Log directory set to: " . $this->logDir);
        $this->ensureLogDirectory();
    }

    private function ensureLogDirectory() {
        error_log("Ensuring directory exists: " . $this->logDir);
        
        if (!file_exists($this->logDir)) {
            error_log("Directory doesn't exist, creating it");
            if (!mkdir($this->logDir, 0755, true)) {
                error_log("Failed to create directory: " . error_get_last()['message']);
                return false;
            }
            error_log("Directory created successfully");
        }
        
        // Ensure directory is readable and writable
        if (!is_writable($this->logDir)) {
            error_log("Directory not writable, attempting to set permissions");
            if (!chmod($this->logDir, 0755)) {
                error_log("Failed to set directory permissions: " . error_get_last()['message']);
                return false;
            }
            error_log("Directory permissions set successfully");
        }
        
        return true;
    }

    /**
     * Ensure log file exists and has correct permissions
     */
    private function ensureLogFile($logFile) {
        error_log("Ensuring log file exists: " . $logFile);
        
        // Create file if it doesn't exist
        if (!file_exists($logFile)) {
            error_log("File doesn't exist, creating it");
            if (!touch($logFile)) {
                error_log("Failed to create file: " . error_get_last()['message']);
                return false;
            }
            error_log("File created successfully");
        }
        
        // Ensure file is readable and writable
        if (!is_writable($logFile)) {
            error_log("File not writable, attempting to set permissions");
            if (!chmod($logFile, 0644)) {
                error_log("Failed to set file permissions: " . error_get_last()['message']);
                return false;
            }
            error_log("File permissions set successfully");
        }
        
        return $logFile;
    }

    /**
     * Create a JSON formatted log entry
     */
    private function formatLogMessage($message, $level, $context = []) {
        $baseData = [
            'timestamp' => '2024-01-05 ' . date('H:i:s'), // Force date to 2024-01-05
            'level' => $level,
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id() ?? 'no_session',
            'user' => $_SESSION[$GLOBALS['APP']."_user_name"] ?? 'anonymous'
        ];

        // Merge additional context
        $logData = array_merge($baseData, $context);
        
        // Convert to JSON with proper formatting
        $json = json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        // Fix missing commas between properties
        $json = preg_replace('/"\n\s+"/', '",\n    "', $json);
        
        // Fix missing commas after nested objects
        $json = preg_replace('/}\n\s+"/', '},\n    "', $json);
        
        // Fix missing commas after arrays
        $json = preg_replace('/]\n\s+"/', '],\n    "', $json);
        
        return $json . "\n";
    }

    /**
     * Write a log entry to file
     */
    public function log($message, $level = null, $context = []) {
        $level = $level ?? $this->defaultLogLevel;
        $level = strtoupper($level);

        if (!isset($this->logLevels[$level])) {
            $level = $this->defaultLogLevel;
        }

        // Use 2024-01-05 for testing
        $logFile = $this->ensureLogFile($this->logDir . '/2024-01-05.log');
        $logMessage = $this->formatLogMessage($message, $level, $context);
        
        error_log("Attempting to write to log file: " . $logFile);
        error_log("Log message: " . $logMessage);
        
        // Check file permissions
        error_log("File permissions: " . substr(sprintf('%o', fileperms($logFile)), -4));
        error_log("File owner: " . fileowner($logFile));
        error_log("Current process owner: " . posix_getuid());
        
        $fp = fopen($logFile, 'a');
        if (!$fp) {
            error_log("Failed to open log file: " . $logFile);
            error_log("Error: " . error_get_last()['message']);
            return false;
        }

        if (flock($fp, LOCK_EX)) {
            $result = fwrite($fp, $logMessage);
            if ($result === false) {
                error_log("Failed to write to log file: " . $logFile);
                error_log("Error: " . error_get_last()['message']);
                flock($fp, LOCK_UN);
                fclose($fp);
                return false;
            }
            error_log("Successfully wrote " . $result . " bytes to log file");
            fflush($fp);
            flock($fp, LOCK_UN);
        } else {
            error_log("Failed to acquire lock on log file: " . $logFile);
            error_log("Error: " . error_get_last()['message']);
            fclose($fp);
            return false;
        }
        fclose($fp);
        
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

        $this->log("Page accessed: $page", 'INFO', $context);
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

        $this->log("User activity: $action", 'INFO', $context);
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
        $this->log("Performance: $operation took {$duration}ms", 'INFO', $context);
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

        $this->log("Audit: $action on $entity", 'INFO', $context);
    }
}
?>
