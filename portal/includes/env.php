<?php
// Environment Detection
$hostname = gethostname();
$is_production = (strpos($hostname, 'prod') !== false || strpos($hostname, 'live') !== false);

// Environment Type
define('IS_PRODUCTION', $is_production);
define('APP_ENV', $is_production ? 'production' : 'development');

// Directory Structure
define('PROJECT_ROOT', '/var/www/html');
define('BASE_PATH', PROJECT_ROOT);
define('SHARED_DIR', PROJECT_ROOT . '/shared');
define('DATA_DIR', SHARED_DIR . '/data');
define('SCRIPTS_DIR', SHARED_DIR . '/scripts');
define('MODULES_DIR', SCRIPTS_DIR . '/modules');

// Python Environment
define('PYTHON_VENV', SHARED_DIR . '/venv');
define('PYTHON_BIN', PYTHON_VENV . '/bin/python3');

// Logging Configuration
define('LOG_DIR', PROJECT_ROOT . '/portal/logs');
define('ERROR_LOG', LOG_DIR . '/errors/php_errors.log');
define('ACCESS_LOG', LOG_DIR . '/access/access.log');
define('PYTHON_LOG', LOG_DIR . '/python/python.log');

// Debug Configuration
define('DEBUG_MODE', !IS_PRODUCTION);
error_reporting(IS_PRODUCTION ? E_ALL & ~E_DEPRECATED & ~E_STRICT : E_ALL);
ini_set('display_errors', IS_PRODUCTION ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOG);

// Session Configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
if (IS_PRODUCTION) {
    ini_set('session.cookie_secure', '1');
}

// Performance Settings
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '300');

// Timezone
date_default_timezone_set('UTC');

// Application Configuration
$_ENV['APP'] = [
    'name' => 'Portal',
    'version' => '1.0.0',
    'debug' => DEBUG_MODE,
    'environment' => APP_ENV,
    'log_dir' => LOG_DIR,
    'python_venv' => PYTHON_VENV,
    'python_bin' => PYTHON_BIN
];

// Ensure critical directories exist
$required_dirs = [
    LOG_DIR,
    LOG_DIR . '/errors',
    LOG_DIR . '/access',
    LOG_DIR . '/python',
    SHARED_DIR,
    DATA_DIR,
    SCRIPTS_DIR,
    MODULES_DIR
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}
