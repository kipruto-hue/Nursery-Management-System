<?php
/**
 * Front controller (Vercel only).
 *
 * Vercel's firewall rejects any URL ending in ".php" with 403 before routing
 * happens -- a request for /api/login.php never reaches a function, even when
 * one is registered there. The firewall inspects the *incoming* URL and not the
 * rewrite target, so the browser must never see a ".php" URL.
 *
 * vercel.json sends anything that is not a real static file here, and this
 * dispatches to the actual page. Apache does the same job via .htaccess, so one
 * set of clean URLs works on both hosts.
 *
 * Named index.php rather than _router.php: Vercel skips underscore-prefixed
 * files when detecting functions, so that name matched nothing and failed the
 * build.
 */

// vercel.json cannot carry a "headers" block alongside "routes", so the
// security headers that .htaccess sets on Apache are set here instead.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

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

// Guard against routing this dispatcher back into itself (/api/index).
if ($file !== null && realpath($file) === realpath(__FILE__)) {
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
