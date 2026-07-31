<?php
// STEP 0 SPIKE — temporary. Delete before any real deploy.
// Root-level counterpart to api/spike.php. The 403s we saw hit BOTH /index.php
// (root) and /api/login.php, so we probe both path shapes to find out whether
// Vercel's WAF denies .php everywhere or only where no function is registered.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo "SPIKE OK (root)\n";
echo 'php_version=' . PHP_VERSION . "\n";
