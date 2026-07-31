<?php
// STEP 0 SPIKE — temporary. Delete before any real deploy.
// session_start() MUST come before any output, or it fails with "headers
// already sent" and the persistence probe reports garbage.
session_start();
$_SESSION['hits'] = ($_SESSION['hits'] ?? 0) + 1;

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "SPIKE OK\n";
echo 'php_version=' . PHP_VERSION . "\n";
echo 'sapi=' . PHP_SAPI . "\n";
echo 'pdo_mysql=' . (extension_loaded('pdo_mysql') ? 'yes' : 'NO — PLAN IS DEAD') . "\n";
echo 'session_save_path=' . (session_save_path() ?: '(default)') . "\n";
echo 'tmp_writable=' . (is_writable(sys_get_temp_dir()) ? 'yes' : 'no') . "\n";

// Reuse the returned cookie across several requests. If session_hits climbs,
// some instances are warm and keeping /tmp; if it keeps resetting to 1, file
// sessions cannot back a login and Step 4's DB-backed handler is mandatory.
echo 'session_id=' . session_id() . "\n";
echo 'session_hits=' . $_SESSION['hits'] . "\n";
// Distinguishes "same warm instance" from "new instance" across requests.
echo 'boot_id=' . substr(md5((string)getmypid() . PHP_BINARY . (string)filemtime('/tmp')), 0, 8) . "\n";
