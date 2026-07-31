<?php
/**
 * Front controller (Vercel only).
 *
 * Vercel's firewall rejects any URL ending in ".php" with 403 before routing
 * ever happens -- a request for /api/login.php never reaches a function, even
 * when one is registered there. The firewall inspects the *incoming* URL and
 * not the rewrite target, so the browser must never see a ".php" URL.
 *
 * vercel.json rewrites clean URLs (/, /app/foo, /api/foo) here, and this
 * dispatches to the real file. Apache does the same job via .htaccess, so a
 * single set of clean URLs works on both hosts.
 */

// Vercel passes the matched path as __r; fall back to REQUEST_URI so the
// router still works under a catch-all rewrite or a direct include.
$route = (string)($_GET['__r'] ?? '');
if ($route === '') {
    $route = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
}
// Never leak the routing param into the page's own $_GET.
unset($_GET['__r'], $_REQUEST['__r']);

$route = trim($route, '/');

if ($route === '' || $route === 'index') {
    $file = dirname(__DIR__) . '/index.php';
} elseif (preg_match('#^(app|api)/([a-z0-9-]+)$#', $route, $m)) {
    // Whitelisted shape only -- no dots, no slashes, so no traversal.
    $file = dirname(__DIR__) . '/' . $m[1] . '/' . $m[2] . '.php';
} else {
    $file = null;
}

if ($file === null || !is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<body style="font-family:system-ui;padding:40px;text-align:center">'
       . '<h2>Page not found</h2><p><a href="/">Go to the app</a></p></body>';
    exit;
}

require $file;
