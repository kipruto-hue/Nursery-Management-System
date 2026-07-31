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

// Managed MySQL providers (Railway, Aiven, TiDB) hand out a non-standard port,
// so this cannot be assumed to be 3306.
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));

// Some providers require TLS. Set DB_SSL=1 to enable it; point DB_SSL_CA at a
// bundled CA file to verify the certificate properly. Without a CA the
// connection is still encrypted but the certificate is not verified, which is
// acceptable only for a short-lived demo.
define('DB_SSL', (bool)(getenv('DB_SSL') ?: false));
define('DB_SSL_CA', getenv('DB_SSL_CA') ?: '');

// 'production' hides error details from visitors; 'development' shows them.
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Session lifetime in seconds (12 hours per spec §6.1).
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 12 * 3600));
