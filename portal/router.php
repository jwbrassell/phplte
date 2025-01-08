<?php
// Development router to mimic .htaccess behavior
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// If file exists, serve it directly
if (is_file(__DIR__ . $uri)) {
    return false;
}

// If directory exists with index.php, serve it
if (is_dir(__DIR__ . $uri) && is_file(__DIR__ . $uri . '/index.php')) {
    include __DIR__ . $uri . '/index.php';
    return true;
}

// Handle clean URLs (remove .php extension)
if (!preg_match('/\.php$/', $uri)) {
    $phpFile = __DIR__ . $uri . '.php';
    if (is_file($phpFile)) {
        include $phpFile;
        return true;
    }
}

// If no matches, serve 404
if (!is_file(__DIR__ . $uri)) {
    include __DIR__ . '/404.php';
    return true;
}

return false;
