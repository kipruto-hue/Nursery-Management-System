<?php
// Local development uses config.php (gitignored, holds real credentials).
// Hosted deployments have no such file and fall back to environment variables.
require_once is_file(__DIR__ . '/config.php')
    ? __DIR__ . '/config.php'
    : __DIR__ . '/config.env.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // DB_PORT / DB_SSL only exist in config.env.php. A local config.php
        // predates them, so fall back rather than fataling on an undefined
        // constant.
        $port = defined('DB_PORT') ? DB_PORT : 3306;
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . $port
             . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (defined('DB_SSL') && DB_SSL) {
            if (defined('DB_SSL_CA') && DB_SSL_CA !== '') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
            } else {
                // Encrypted, but the certificate is not verified. Acceptable
                // only for a short-lived demo; supply DB_SSL_CA otherwise.
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
        }

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
