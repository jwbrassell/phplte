<?php
$logFile = __DIR__ . '/logs/access/2024-01-05.log';
$message = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'type' => 'test',
    'message' => 'Direct write test',
    'details' => ['test' => true]
]) . "\n";

echo "Attempting to write to: " . $logFile . "\n";
echo "Current permissions: " . substr(sprintf('%o', fileperms($logFile)), -4) . "\n";
echo "Current owner: " . fileowner($logFile) . "\n";
echo "Current process owner: " . posix_getuid() . "\n";

$fp = fopen($logFile, 'a');
if (!$fp) {
    echo "Failed to open file: " . error_get_last()['message'] . "\n";
    exit(1);
}

if (flock($fp, LOCK_EX)) {
    $result = fwrite($fp, $message);
    if ($result === false) {
        echo "Failed to write: " . error_get_last()['message'] . "\n";
    } else {
        echo "Successfully wrote " . $result . " bytes\n";
    }
    fflush($fp);
    flock($fp, LOCK_UN);
} else {
    echo "Failed to acquire lock: " . error_get_last()['message'] . "\n";
}
fclose($fp);

echo "Current file contents:\n";
echo file_get_contents($logFile);
