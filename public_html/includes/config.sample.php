<?php
// Copy this file to config.php and fill in your Hostinger database details.
// (hPanel → Databases → MySQL Databases)

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// 'production' hides error details from visitors; 'development' shows them.
define('APP_ENV', 'production');

// Session lifetime in seconds (12 hours per spec §6.1).
define('SESSION_LIFETIME', 12 * 3600);
