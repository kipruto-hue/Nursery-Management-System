<?php
/**
 * No-database demo mode.
 *
 * When no DB_HOST is configured the app runs against a throwaway SQLite file
 * in the system temp directory, built from sql/schema.sqlite.sql and seeded
 * with sql/seed.php on first use. That makes the hosted demo work with no
 * database server, no signup and no credentials.
 *
 * This is a FALLBACK, not a fork: configure DB_HOST and the app uses MySQL
 * exactly as before, so nothing here affects the Hostinger deployment.
 *
 * The point of the compatibility layer below is that no application query had
 * to be rewritten. MySQL-only functions are registered as SQLite functions,
 * and the handful of statements SQLite cannot parse are rewritten on the way
 * through. Application code stays MySQL-flavoured and portable.
 *
 * Limitation, by design: the temp directory is per-instance and is recycled
 * when the instance goes idle, so anything entered during a demo eventually
 * disappears and the seed data returns. Fine for viewing; not a datastore.
 */

/**
 * PDO wrapper that rewrites the few constructs SQLite cannot parse.
 */
class SqliteCompatPdo extends PDO
{
    /** settings and sessions use a non-"id" primary key. */
    private const PRIMARY_KEYS = ['settings' => 'k', 'sessions' => 'id'];

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(self::translate($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, ...$args): PDOStatement|false
    {
        if ($fetchMode === null) {
            return parent::query(self::translate($query));
        }
        return parent::query(self::translate($query), $fetchMode, ...$args);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(self::translate($statement));
    }

    /** Rewrite MySQL syntax SQLite rejects. Function names are handled by UDFs. */
    public static function translate(string $sql): string
    {
        // Row locking: SQLite locks the whole file, so this is a no-op.
        $sql = preg_replace('/\s+FOR\s+UPDATE\b/i', '', $sql);

        // MySQL upsert -> SQLite upsert, including VALUES(col) -> excluded.col.
        if (preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i', $sql)) {
            $table = '';
            if (preg_match('/\bINSERT\s+INTO\s+`?(\w+)`?/i', $sql, $m)) {
                $table = strtolower($m[1]);
            }
            $pk = self::PRIMARY_KEYS[$table] ?? 'id';
            $sql = preg_replace(
                '/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i',
                'ON CONFLICT(' . $pk . ') DO UPDATE SET',
                $sql
            );
            $sql = preg_replace('/\bVALUES\s*\(\s*`?(\w+)`?\s*\)/i', 'excluded.$1', $sql);
        }

        // DATE_SUB(NOW(), INTERVAL 15 MINUTE): the INTERVAL literal is not
        // parseable by SQLite at all, so it cannot be handled by a UDF.
        $sql = preg_replace_callback(
            '/\bDATE_SUB\s*\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+(\w+)\s*\)/i',
            static fn($m) => "datetime('now','-{$m[1]} " . strtolower($m[2]) . "s')",
            $sql
        );

        // IF(a,b,c) -> IIF(a,b,c). Guarded so it cannot match inside a word.
        $sql = preg_replace('/(?<![A-Za-z0-9_.])IF\s*\(/i', 'IIF(', $sql);

        // CAST(x AS UNSIGNED) has no SQLite equivalent type.
        $sql = preg_replace('/\bAS\s+UNSIGNED\b/i', 'AS INTEGER', $sql);

        // Seed-only statements.
        $sql = preg_replace('/\bTRUNCATE\s+TABLE\b/i', 'DELETE FROM', $sql);
        $sql = preg_replace('/\bSET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]\b/i', 'SELECT 1', $sql);
        $sql = preg_replace(
            '/\bSHOW\s+TABLES\b/i',
            "SELECT name FROM sqlite_master WHERE type='table'",
            $sql
        );

        return $sql;
    }
}

/**
 * Register the MySQL functions the application's SQL relies on.
 *
 * PDO::sqliteCreateFunction is deprecated in PHP 8.5 in favour of
 * Pdo\Sqlite::createFunction, but that class does not exist on the PHP 8.1-8.3
 * that shared hosting runs, and it is unavailable on a PDO subclass. The
 * deprecation is silenced rather than branching on version.
 */
function sqlite_register_functions(PDO $pdo): void
{
    $fn = static function (string $name, callable $cb, int $argc = -1) use ($pdo): void {
        @$pdo->sqliteCreateFunction($name, $cb, $argc);
    };

    $fn('now',     static fn() => date('Y-m-d H:i:s'), 0);
    $fn('curdate', static fn() => date('Y-m-d'), 0);
    $fn('year',    static fn($d) => $d === null ? null : (int)date('Y', strtotime((string)$d)), 1);
    $fn('month',   static fn($d) => $d === null ? null : (int)date('n', strtotime((string)$d)), 1);

    $fn('datediff', static function ($a, $b) {
        if ($a === null || $b === null) return null;
        return (int)floor((strtotime((string)$a) - strtotime((string)$b)) / 86400);
    }, 2);

    // Only the subset of MySQL's format specifiers this app actually uses.
    $fn('date_format', static function ($d, $f) {
        if ($d === null) return null;
        $map = ['%Y' => 'Y', '%y' => 'y', '%m' => 'm', '%d' => 'd',
                '%H' => 'H', '%i' => 'i', '%s' => 's'];
        return date(strtr((string)$f, $map), strtotime((string)$d));
    }, 2);

    // FIELD(x, a, b, c) -> 1-based position of x, or 0. Drives stage ordering.
    $fn('field', static function (...$args) {
        $needle = array_shift($args);
        foreach ($args as $i => $v) {
            if ((string)$needle === (string)$v) return $i + 1;
        }
        return 0;
    }, -1);

    $fn('substring_index', static function ($s, $delim, $count) {
        $parts = explode((string)$delim, (string)$s);
        if ((int)$count < 0) {
            return implode((string)$delim, array_slice($parts, (int)$count));
        }
        return implode((string)$delim, array_slice($parts, 0, (int)$count));
    }, 3);
}

/**
 * Open the demo database, building and seeding it if it does not exist yet.
 *
 * Built at a unique temporary path and renamed into place, so two concurrent
 * cold starts cannot serve a half-built file.
 */
function sqlite_demo_connect(string $path): PDO
{
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    if (!is_file($path) || filesize($path) === 0) {
        $building = $path . '.' . getmypid() . '.tmp';
        @unlink($building);

        $pdo = new SqliteCompatPdo('sqlite:' . $building, null, null, $options);
        sqlite_register_functions($pdo);

        $schema = file_get_contents(dirname(__DIR__) . '/sql/schema.sqlite.sql');
        if ($schema === false) {
            throw new RuntimeException('sql/schema.sqlite.sql is missing');
        }
        // Strip comments, then run statement by statement: no statement in this
        // file contains a semicolon inside a string literal.
        $schema = preg_replace('/^\s*--.*$/m', '', $schema);
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
            $pdo->exec($stmt);
        }

        sqlite_demo_seed($pdo);

        unset($pdo);
        @rename($building, $path);
    }

    $pdo = new SqliteCompatPdo('sqlite:' . $path, null, null, $options);
    sqlite_register_functions($pdo);
    return $pdo;
}

/** Load the demo data using the existing seed script. */
function sqlite_demo_seed(PDO $pdo): void
{
    // seed.php calls db(); hand it this connection rather than reconnecting.
    $GLOBALS['__demo_pdo'] = $pdo;
    if (!defined('SEED_VIA_SETUP')) {
        define('SEED_VIA_SETUP', true);
    }
    ob_start();
    try {
        require dirname(__DIR__) . '/sql/seed.php';
    } finally {
        ob_end_clean();
        unset($GLOBALS['__demo_pdo']);
    }
}
