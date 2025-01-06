<?php
/**
 * OnCallCalendar class for interfacing with Python calendar management scripts
 */
class OnCallCalendar {
    private $pythonPath;
    private $modulePath;
    private $dataPath;
    private $uploadPath;

    public function __construct() {
        $this->pythonPath = '/usr/bin/python3';
        $this->modulePath = __DIR__ . '/../../shared/scripts/modules/oncall_calendar';
        $this->dataPath = __DIR__ . '/../../shared/data/oncall_calendar';
        $this->uploadPath = $this->dataPath . '/uploads';
        
        // Ensure directories exist with proper permissions
        foreach ([$this->dataPath, $this->uploadPath] as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    error_log("Failed to create directory: $dir");
                    throw new Exception("Failed to initialize calendar storage");
                }
                chmod($dir, 0777);
            } elseif (!is_writable($dir)) {
                chmod($dir, 0777);
            }
        }
    }

    /**
     * Execute a Python script and return JSON response
     */
    private function executePythonScript($script, $args = []) {
        $scriptPath = $this->modulePath . '/' . $script;
        $command = escapeshellcmd($this->pythonPath) . ' ' . escapeshellarg($scriptPath);
        
        // Add data directory as first argument
        array_unshift($args, $this->dataPath);
        
        // Add arguments
        foreach ($args as $arg) {
            if ($arg === null) {
                $command .= ' ' . escapeshellarg('null');
            } else {
                $command .= ' ' . escapeshellarg($arg);
            }
        }
        
        // Set PYTHONPATH to include the modules directory
        $env = ['PYTHONPATH' => dirname($this->modulePath)];
        $envStr = 'PYTHONPATH=' . escapeshellarg($env['PYTHONPATH']);
        
        // Execute command
        $output = [];
        $returnVar = 0;
        exec($envStr . ' ' . $command . ' 2>&1', $output, $returnVar);
        
        // Process output
        $jsonOutput = implode("\n", $output);
        $result = json_decode($jsonOutput, true);
        
        if ($returnVar !== 0) {
            error_log("Error executing Python script: $command");
            error_log("Output: " . print_r($output, true));
            throw new Exception("Failed to execute calendar operation");
        }
        
        if (!$result) {
            error_log("Invalid JSON output from Python script: $jsonOutput");
            throw new Exception("Invalid response from calendar operation");
        }
        
        if (isset($result['error'])) {
            throw new Exception($result['error']);
        }
        
        return $result;
    }

    /**
     * Handle file upload for CSV import
     */
    private function handleFileUpload($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No file uploaded');
        }
        
        // Validate file type
        if (!in_array($file['type'], ['text/csv', 'application/vnd.ms-excel'])) {
            throw new Exception('Invalid file type. Please upload a CSV file.');
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . basename($file['name']);
        $uploadFile = $this->uploadPath . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadFile)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        return $uploadFile;
    }

    /**
     * Team Operations
     */
    public function getTeams() {
        return $this->executePythonScript('calendar_api.py', ['teams', 'get']);
    }

    public function addTeam($name, $color = 'primary') {
        $data = json_encode(['name' => $name, 'color' => $color]);
        return $this->executePythonScript('calendar_api.py', ['teams', 'add', $data]);
    }

    public function updateTeam($teamId, $name = null, $color = null) {
        $data = ['id' => $teamId];
        if ($name !== null) $data['name'] = $name;
        if ($color !== null) $data['color'] = $color;
        
        return $this->executePythonScript('calendar_api.py', ['teams', 'update', json_encode($data)]);
    }

    public function deleteTeam($teamId) {
        $data = json_encode(['id' => $teamId]);
        return $this->executePythonScript('calendar_api.py', ['teams', 'delete', $data]);
    }

    /**
     * Calendar Event Operations
     */
    public function getEvents($start, $end, $teamId = null) {
        $data = [
            'start' => $start,
            'end' => $end
        ];
        if ($teamId !== null) {
            $data['team'] = $teamId;
        }
        
        return $this->executePythonScript('calendar_api.py', ['events', 'get', json_encode($data)]);
    }

    public function getCurrentOnCall($teamId = null) {
        $data = $teamId !== null ? json_encode(['team' => $teamId]) : null;
        return $this->executePythonScript('calendar_api.py', ['events', 'current', $data]);
    }

    /**
     * Holiday Operations
     */
    public function getHolidays($year = null) {
        $data = $year !== null ? json_encode(['year' => $year]) : null;
        return $this->executePythonScript('calendar_api.py', ['holidays', 'get', $data]);
    }

    /**
     * Schedule Upload Operations
     */
    public function uploadSchedule($file, $teamId, $year = null, $autoGenerate = false, $weeklyRotation = false) {
        $uploadedFile = $this->handleFileUpload($file);
        
        try {
            $args = ['upload_schedule', $teamId, $uploadedFile];
            if ($year !== null) {
                $args[] = $year;
            }
            if ($autoGenerate) {
                $args[] = $weeklyRotation ? 'weekly' : 'auto';
            }
            
            $result = $this->executePythonScript('csv_handler.py', $args);
            
            // Clean up uploaded file
            unlink($uploadedFile);
            
            return $result;
            
        } catch (Exception $e) {
            // Clean up on error
            if (file_exists($uploadedFile)) {
                unlink($uploadedFile);
            }
            throw $e;
        }
    }

    /**
     * Holiday Upload Operations
     */
    public function uploadHolidays($file) {
        $uploadedFile = $this->handleFileUpload($file);
        
        try {
            $result = $this->executePythonScript('csv_handler.py', ['upload_holidays', '0', $uploadedFile]);
            
            // Clean up uploaded file
            unlink($uploadedFile);
            
            return $result;
            
        } catch (Exception $e) {
            // Clean up on error
            if (file_exists($uploadedFile)) {
                unlink($uploadedFile);
            }
            throw $e;
        }
    }
}
