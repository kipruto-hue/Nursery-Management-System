<?php
// Dev-only router for `php -S`. Not deployed (.htaccess covers Apache,
// vercel.json + api/_router.php cover Vercel).
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Mirror the .htaccess protections
if (preg_match('#^/(includes|sql|backups)/#', $path) || $path === '/cron.php') {
    http_response_code(403);
    exit('Forbidden');
}

// Clean URLs, matching .htaccess and the Vercel front controller: the app
// links to /app/foo and /api/foo with no extension.
if (preg_match('#^/(app|api)/([a-z0-9-]+)$#', $path, $m)) {
    $target = __DIR__ . '/' . $m[1] . '/' . $m[2] . '.php';
    if (is_file($target)) {
        require $target;
        return true;
    }
    http_response_code(404);
    exit('Not found');
}

$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // let the built-in server handle static files
}
require __DIR__ . '/index.php';
