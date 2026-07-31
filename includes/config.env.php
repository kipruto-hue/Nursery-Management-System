<?php
/**
 * Deployment configuration, read from environment variables.
 *
 * Used whenever includes/config.php is absent -- which is always on a hosted
 * deployment, because that file holds real credentials and is gitignored.
 * Keeping secrets in the environment rather than in a file matters more now
 * that the app sits at the document root: this file is inside the deployment
 * and web-reachable, so it must never contain a password.
 *
 * Set these in Vercel: Project Settings -> Environment Variables.
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');

// 'production' hides error details from visitors; 'development' shows them.
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Session lifetime in seconds (12 hours per spec §6.1).
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 12 * 3600));
