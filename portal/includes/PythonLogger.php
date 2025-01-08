<?php
class PythonLogger {
    private $projectRoot;
    private $pythonPath;
    private $loggerScript;
    
    public function __construct() {
        // Get project root (2 levels up from includes directory)
        $this->projectRoot = dirname(dirname(__DIR__));
        
        // Python interpreter path from virtual environment
        $this->pythonPath = $this->projectRoot . '/shared/venv/bin/python';
        
        // Python logger script path
        $this->loggerScript = $this->projectRoot . '/shared/scripts/modules/logging/logger.py';
        
        // Verify paths exist
        if (!file_exists($this->pythonPath)) {
            throw new Exception("Python interpreter not found at: " . $this->pythonPath);
        }
        
        if (!file_exists($this->loggerScript)) {
            throw new Exception("Logger script not found at: " . $this->loggerScript);
        }
    }
    
    public function log($level, $message, $context = []) {
        // Ensure context is JSON-encodable
        $jsonContext = json_encode($context);
        if ($jsonContext === false) {
            throw new Exception("Failed to encode context as JSON");
        }
        
        // Escape message for shell
        $escapedMessage = escapeshellarg($message);
        
        // Build command
        $cmd = sprintf(
            '%s %s %s %s %s',
            escapeshellcmd($this->pythonPath),
            escapeshellcmd($this->loggerScript),
            escapeshellarg($level),
            $escapedMessage,
            escapeshellarg($jsonContext)
        );
        
        // Execute Python logger
        $output = [];
        $returnVar = 0;
        exec($cmd . " 2>&1", $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Logger execution failed: " . implode("\n", $output));
        }
        
        return true;
    }
    
    public function getLogs($level, $limit = 100) {
        $logDir = $this->projectRoot . '/shared/data/logs/system/' . $level;
        
        if (!is_dir($logDir)) {
            return [];
        }
        
        // Get all JSON log files in the directory
        $files = glob($logDir . '/*.json');
        if (empty($files)) {
            return [];
        }
        
        // Sort files by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $logs = [];
        $count = 0;
        
        foreach ($files as $file) {
            if ($count >= $limit) {
                break;
            }
            
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $logEntry = json_decode($content, true);
            if ($logEntry === null) {
                continue;
            }
            
            $logs[] = $logEntry;
            $count++;
        }
        
        return $logs;
    }
}
?>
