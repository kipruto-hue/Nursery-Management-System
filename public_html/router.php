<?php
// Dev-only router for `php -S`. Do NOT upload to Hostinger (.htaccess covers prod).
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Mirror .htaccess protections
if (preg_match('#^/(includes|sql)/#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // let the built-in server handle static files / direct PHP
}
if (is_dir($file) && file_exists($file . '/index.php')) {
    require $file . '/index.php';
    return true;
}
require __DIR__ . '/index.php';
