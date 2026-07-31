<?php
// Local development uses config.php (gitignored, holds real credentials).
// Hosted deployments have no such file and fall back to environment variables.
require_once is_file(__DIR__ . '/config.php')
    ? __DIR__ . '/config.php'
    : __DIR__ . '/config.env.php';

/**
 * Demo mode: no database server configured.
 *
 * With no DB_NAME set there is nothing to connect to, so rather than failing
 * the app runs against a self-building SQLite file (see db_sqlite.php). Set
 * DB_NAME and the app uses MySQL exactly as before -- Hostinger is unaffected.
 */
function db_demo_mode(): bool {
    if (defined('DEMO_MODE') && DEMO_MODE) {
        return true;
    }
    return !defined('DB_NAME') || DB_NAME === '';
}

function db(): PDO {
    // While the demo database is being built, sql/seed.php must reuse the
    // connection under construction instead of opening a second one.
    if (isset($GLOBALS['__demo_pdo'])) {
        return $GLOBALS['__demo_pdo'];
    }

    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (db_demo_mode()) {
        require_once __DIR__ . '/db_sqlite.php';
        $pdo = sqlite_demo_connect(sys_get_temp_dir() . '/nursery-demo.sqlite');
        return $pdo;
    }

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
            // Encrypted, but the certificate is not verified. Acceptable only
            // for a short-lived demo; supply DB_SSL_CA otherwise.
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    return $pdo;
}
