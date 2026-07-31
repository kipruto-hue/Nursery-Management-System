<?php
// Temporary diagnostic. Reports which storage backend is live and whether the
// login rate limiter is currently blocking the demo account. No credentials,
// no data. Delete once the demo is settled.

require_once dirname(__DIR__) . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo 'demo_mode=' . var_export(db_demo_mode(), true) . "\n";
echo 'DB_NAME_set=' . var_export(defined('DB_NAME') && DB_NAME !== '', true) . "\n";

try {
    $pdo = db();
    echo 'driver=' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
    echo 'users=' . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . "\n";

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE phone = ? AND success = 0
           AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    $stmt->execute(['0700000001']);
    $recent = (int)$stmt->fetchColumn();
    echo "recent_failed_attempts=$recent (locked out at 5)\n";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
