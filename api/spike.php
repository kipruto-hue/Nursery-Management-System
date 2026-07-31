<?php
// Runtime capability probe. Temporary -- delete before handing over.
session_start();
$_SESSION['hits'] = ($_SESSION['hits'] ?? 0) + 1;

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "SPIKE OK\n";
echo 'php_version=' . PHP_VERSION . "\n";
echo 'pdo_mysql=' . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";

// Decides whether the no-database demo mode is possible at all.
echo 'pdo_sqlite=' . (extension_loaded('pdo_sqlite') ? 'yes' : 'NO — DEMO MODE IMPOSSIBLE') . "\n";
echo 'sqlite3_ext=' . (extension_loaded('sqlite3') ? 'yes' : 'no') . "\n";

// Can we actually create and query a SQLite file in /tmp, and register the
// custom functions the MySQL-compatibility shim depends on?
try {
    $f = sys_get_temp_dir() . '/probe.sqlite';
    @unlink($f);
    $p = new PDO('sqlite:' . $f);
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->exec('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    $p->exec("INSERT INTO t (name) VALUES ('x')");
    $p->sqliteCreateFunction('curdate', fn() => date('Y-m-d'), 0);
    $n = $p->query("SELECT curdate()")->fetchColumn();
    echo "sqlite_file_write=yes\n";
    echo "sqlite_udf=yes (curdate returned $n)\n";
    echo 'sqlite_rows=' . $p->query('SELECT COUNT(*) FROM t')->fetchColumn() . "\n";
    @unlink($f);
} catch (Throwable $e) {
    echo 'sqlite_file_write=FAILED: ' . $e->getMessage() . "\n";
}

echo 'session_hits=' . $_SESSION['hits'] . "\n";
