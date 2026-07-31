<?php
require_once __DIR__ . '/db.php';

/**
 * Database-backed session storage.
 *
 * Serverless instances do not share a filesystem and are recycled when idle,
 * so PHP's default file sessions silently disappear. Measured on this
 * deployment: a session survived 34 requests while warm, then reset after
 * roughly ten minutes of inactivity -- which logs a user out mid-visit, with
 * no error to explain it.
 *
 * Keeping session data in MySQL makes it survive instance recycling and lets
 * concurrent instances share one login. $_SESSION keeps working exactly as
 * before, so auth, CSRF and every endpoint are untouched.
 *
 * Every method swallows database errors on purpose: if the database is
 * unreachable the session simply does not persist, rather than throwing out of
 * session_start() and taking down pages that render fine without it (the login
 * screen in particular).
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    /**
     * Set when the session store could not be reached.
     *
     * Without this, an unreachable database looks exactly like an expired
     * session: nothing persists, so the CSRF token issued with the login page
     * is gone by the time the form is submitted, and the user is told to
     * refresh forever. Callers use this to report the real cause.
     */
    public static bool $failed = false;

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        try {
            $stmt = db()->prepare('SELECT data FROM sessions WHERE id = ?');
            $stmt->execute([$id]);
            $data = $stmt->fetchColumn();
        } catch (Throwable $ex) {
            self::$failed = true;
            error_log('session read failed: ' . $ex->getMessage());
            return '';
        }
        return ($data === false || $data === null) ? '' : (string)$data;
    }

    public function write(string $id, string $data): bool
    {
        try {
            db()->prepare(
                'INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE data = VALUES(data),
                                         last_activity = VALUES(last_activity)'
            )->execute([$id, $data, time()]);
        } catch (Throwable $ex) {
            self::$failed = true;
            error_log('session write failed: ' . $ex->getMessage());
            return false;
        }
        return true;
    }

    public function destroy(string $id): bool
    {
        try {
            db()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
        } catch (Throwable $ex) {
            error_log('session destroy failed: ' . $ex->getMessage());
        }
        return true;
    }

    /** Called occasionally by PHP; also pruned by cron.php on Hostinger. */
    #[\ReturnTypeWillChange]
    public function gc(int $maxLifetime)
    {
        try {
            $stmt = db()->prepare('DELETE FROM sessions WHERE last_activity < ?');
            $stmt->execute([time() - $maxLifetime]);
            return $stmt->rowCount();
        } catch (Throwable $ex) {
            error_log('session gc failed: ' . $ex->getMessage());
            return 0;
        }
    }
}
