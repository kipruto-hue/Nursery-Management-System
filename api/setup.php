<?php
/**
 * One-time database initialiser for the hosted demo.
 *
 * Vercel gives no shell, so there is no way to run `mariadb < sql/schema.sql`
 * or `php sql/seed.php` the way the README describes for Hostinger. This does
 * both over HTTP instead.
 *
 * Guarded by the SETUP_TOKEN environment variable. Installing DROPS EVERY
 * TABLE, so it additionally requires an explicit action=install; without it
 * this only reports status and changes nothing.
 *
 * Delete this file once the demo is finished -- it is a deliberate back door.
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$expected = (string)(getenv('SETUP_TOKEN') ?: '');
$given    = (string)($_GET['token'] ?? '');

// Without a configured token the endpoint stays completely inert.
if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(404);
    echo "Not found\n";
    exit;
}

try {
    $pdo = db();
    $pdo->query('SELECT 1');
} catch (Throwable $ex) {
    http_response_code(500);
    echo "Cannot reach the database.\n\n";
    echo $ex->getMessage() . "\n\n";
    echo "Check DB_HOST, DB_NAME, DB_USER and DB_PASS in the Vercel project's\n";
    echo "environment variables, then redeploy so they take effect.\n";
    exit;
}

echo "Database connection: OK\n";
echo 'Server: ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n\n";

$action = (string)($_GET['action'] ?? '');

if ($action !== 'install') {
    // Status only.
    try {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $ex) {
        $tables = [];
    }
    echo 'Tables present: ' . count($tables) . "\n";
    foreach ($tables as $t) {
        echo '  - ' . $t . "\n";
    }
    echo "\nTo install the schema and demo data, re-run with &action=install\n";
    echo "WARNING: that DROPS every table and recreates it from sql/schema.sql\n";
    exit;
}

// ---- Install ----------------------------------------------------------
$schemaPath = dirname(__DIR__) . '/sql/schema.sql';
if (!is_file($schemaPath)) {
    http_response_code(500);
    echo "sql/schema.sql is missing from the deployment.\n";
    exit;
}

$sql = (string)file_get_contents($schemaPath);
// Strip line comments before splitting; no statement here contains a
// semicolon inside a string literal, so a naive split is safe for this file.
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$sql = preg_replace('/--[^\n]*$/m', '', $sql);

$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    static fn($s) => $s !== ''
);

$ran = 0;
foreach ($statements as $stmt) {
    try {
        $pdo->exec($stmt);
        $ran++;
    } catch (Throwable $ex) {
        http_response_code(500);
        echo "Schema failed on statement " . ($ran + 1) . ":\n";
        echo substr($stmt, 0, 300) . "\n\n";
        echo $ex->getMessage() . "\n";
        exit;
    }
}
echo "Schema installed: $ran statements\n";

// Demo data. seed.php normally refuses to run outside the CLI; this constant
// is its only sanctioned exception.
define('SEED_VIA_SETUP', true);
ob_start();
try {
    require dirname(__DIR__) . '/sql/seed.php';
    $seedOut = ob_get_clean();
} catch (Throwable $ex) {
    ob_end_clean();
    http_response_code(500);
    echo "Schema is installed, but seeding failed:\n" . $ex->getMessage() . "\n";
    exit;
}

echo "\n" . $seedOut;
echo "\nDone. Open / and log in with the details above.\n";
echo "Delete api/setup.php when the demo is over.\n";
