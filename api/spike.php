<?php
// STEP 0 SPIKE — temporary. Delete before any real deploy.
// Answers the three questions that decide whether the Vercel plan is viable:
//   1. Does Vercel route/execute .php at all, or does the WAF keep denying it?
//   2. Is pdo_mysql present? (no pdo_mysql => includes/db.php can never work)
//   3. Do file-based sessions survive between requests? (expected: no)
// Deliberately NOT phpinfo() — this is a public URL and env vars will hold DB creds.

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "SPIKE OK\n";
echo 'php_version=' . PHP_VERSION . "\n";
echo 'sapi=' . PHP_SAPI . "\n";
echo 'pdo_mysql=' . (extension_loaded('pdo_mysql') ? 'yes' : 'NO — PLAN IS DEAD') . "\n";
echo 'session_save_path=' . (session_save_path() ?: '(default)') . "\n";
echo 'tmp_dir=' . sys_get_temp_dir() . "\n";
echo 'tmp_writable=' . (is_writable(sys_get_temp_dir()) ? 'yes' : 'no') . "\n";

// Session persistence probe. Hit this repeatedly reusing the returned cookie:
// if session_hits stays at 1, file sessions are dead and Step 4 (DB-backed
// session handler) is mandatory — which is what we expect on serverless.
session_start();
$_SESSION['hits'] = ($_SESSION['hits'] ?? 0) + 1;
echo 'session_id=' . session_id() . "\n";
echo 'session_hits=' . $_SESSION['hits'] . "\n";
