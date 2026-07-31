<?php
// Demo-mode self-test. Temporary -- delete before handing over.
// Exercises every MySQL-specific query the app relies on, against the SQLite
// compatibility layer, and reports each one independently so a single deploy
// surfaces all failures rather than only the first.

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/reports.php';

echo "php=" . PHP_VERSION . "\n";
echo 'demo_mode=' . var_export(db_demo_mode(), true) . "\n";

$pass = 0;
$fail = 0;

function check(string $label, callable $fn): void
{
    global $pass, $fail;
    try {
        $result = $fn();
        $pass++;
        printf("  PASS  %-26s %s\n", $label, $result);
    } catch (Throwable $e) {
        $fail++;
        printf("  FAIL  %-26s %s\n", $label, $e->getMessage());
    }
}

try {
    $pdo = db();
    echo 'driver=' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n\n";
} catch (Throwable $e) {
    echo "\nFATAL: could not open demo database\n" . $e->getMessage() . "\n";
    exit;
}

echo "-- seed data --\n";
foreach (['users', 'species', 'batches', 'sales', 'sale_items', 'customers',
          'expenses', 'labour', 'preorders', 'batch_events'] as $t) {
    check($t, fn() => 'rows=' . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn());
}

echo "\n-- dashboard queries --\n";
check('FIELD() stage ordering', fn() => 'rows=' . count($pdo->query(
    "SELECT b.id, b.batch_code, s.name AS species_name FROM batches b
     JOIN species s ON s.id = b.species_id
     WHERE b.deleted_at IS NULL AND b.stage NOT IN ('sold_out','written_off')
     ORDER BY FIELD(b.stage,'ready','hardening','potted','germinating','sown'), b.sown_date"
)->fetchAll()));

check('CURDATE() sales today', fn() => 'total=' . $pdo->query(
    "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date = CURDATE()"
)->fetchColumn());

check('YEAR()/MONTH() this month', fn() => 'total=' . $pdo->query(
    "SELECT COALESCE(SUM(total_amount),0) FROM sales
     WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE())"
)->fetchColumn());

check('money owed', fn() => 'owed=' . $pdo->query(
    "SELECT COALESCE(SUM(total_amount - amount_paid),0) FROM sales
     WHERE status IN ('partial','credit')"
)->fetchColumn());

echo "\n-- reports --\n";
check('profit per batch', fn() => 'rows=' . count(report_profit_per_batch()));
check('stock ready',      fn() => 'rows=' . count(report_stock_ready()));
check('debtors DATEDIFF', fn() => 'rows=' . count(report_debtors()));
check('monthly DATE_FORMAT', fn() => 'rows=' . count(report_monthly()));
check('losses',           fn() => 'rows=' . count(report_losses()));

echo "\n-- helpers / auth --\n";
check('DATE_SUB INTERVAL', fn() => 'count=' . (function () use ($pdo) {
    $s = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE phone = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    $s->execute(['0700000001']);
    return $s->fetchColumn();
})());

check('SUBSTRING_INDEX + CAST', fn() => 'next=' . generate_batch_code(1));
check('setting() read', fn() => 'name=' . setting('nursery_name', 'fallback'));
check('ON DUPLICATE upsert', function () {
    save_setting('nursery_name', 'Eldoret Greenline Nursery');
    return 'now=' . setting('nursery_name');
});

check('IF() stock update', function () use ($pdo) {
    $pdo->prepare(
        "UPDATE batches SET current_qty = current_qty - ?,
                stage = IF(current_qty = 0 AND stage = 'ready', 'sold_out', stage)
         WHERE id = ?"
    )->execute([0, 1]);
    return 'ok';
});

check('password_verify seed hash', function () use ($pdo) {
    $s = $pdo->prepare('SELECT password_hash FROM users WHERE phone = ?');
    $s->execute(['0700000001']);
    $h = $s->fetchColumn();
    return password_verify('owner1234', (string)$h) ? 'owner1234 verifies' : 'MISMATCH';
});

echo "\n" . ($fail === 0 ? "ALL $pass CHECKS PASSED\n" : "$pass passed, $fail FAILED\n");
