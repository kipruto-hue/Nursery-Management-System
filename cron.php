<?php
// Nightly database backup (§7). Point a Hostinger cron job at this file, e.g.:
//   /usr/bin/php /home/USERNAME/nursery/cron.php
// Backups go to ../backups relative to this file (NOT inside public_html).
// Keeps the last 14 days.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Run from cron only.');
}

require_once __DIR__ . '/includes/config.php';

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) {
    fwrite(STDERR, "Cannot create backup folder $backupDir\n");
    exit(1);
}

$file = $backupDir . '/nursery-' . date('Y-m-d') . '.sql.gz';

$cmd = sprintf(
    'mysqldump --host=%s --user=%s --password=%s %s | gzip > %s',
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_NAME),
    escapeshellarg($file)
);
exec($cmd, $out, $code);

if ($code !== 0 || !file_exists($file) || filesize($file) < 100) {
    fwrite(STDERR, "Backup FAILED (exit $code)\n");
    @unlink($file);
    exit(1);
}
echo 'Backup OK: ' . basename($file) . ' (' . filesize($file) . " bytes)\n";

// Delete backups older than 14 days
foreach (glob($backupDir . '/nursery-*.sql.gz') as $old) {
    if (filemtime($old) < time() - 14 * 86400) {
        unlink($old);
        echo 'Removed old backup: ' . basename($old) . "\n";
    }
}
