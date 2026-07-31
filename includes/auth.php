<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// ---- Session bootstrap: secure cookie flags, 12h lifetime (§2, §6.1) ----
if (session_status() === PHP_SESSION_NONE) {
    // Store sessions in the database. Serverless instances have no shared or
    // persistent disk, so the default file sessions vanish when an instance is
    // recycled and the user is silently logged out.
    require_once __DIR__ . '/session_db.php';
    session_set_save_handler(new DbSessionHandler(), true);

    // Behind Vercel's proxy $_SERVER['HTTPS'] is unset even on HTTPS, so the
    // cookie would never be marked secure. Trust the forwarded protocol too.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,               // session cookie; server enforces 12h below
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('nurserysid');
    session_start();
}

// Server-side 12h timeout
if (isset($_SESSION['user_id'])) {
    if (time() - ($_SESSION['auth_at'] ?? 0) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
    } else {
        $_SESSION['last_seen'] = time();
    }
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['name'],
        'role' => $_SESSION['role'],
    ];
}

function is_owner(): bool {
    return ($_SESSION['role'] ?? '') === 'owner';
}

/** Pages: bounce to login if not signed in. */
function require_login_page(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /');
        exit;
    }
    // Temporary passwords must be changed before doing anything else
    if (!empty($_SESSION['must_change_password'])
        && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'password.php') {
        header('Location: /app/password?first=1');
        exit;
    }
}

/** APIs: 401 JSON if not signed in. */
function require_login_api(): void {
    if (empty($_SESSION['user_id'])) {
        json_fail('Please log in again', 401);
    }
}

/** Owner-only pages. */
function require_owner_page(): void {
    require_login_page();
    if (!is_owner()) {
        header('Location: /app/dashboard');
        exit;
    }
}

/** Owner-only APIs — every permission check is server-side (§3). */
function require_owner_api(): void {
    require_login_api();
    if (!is_owner()) {
        json_fail('Only the owner can do this', 403);
    }
}

// ---- CSRF (§7): token in session, sent back via X-CSRF-Token header ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Call at the top of every state-changing API endpoint. */
function require_csrf(): void {
    // An unreachable session store is indistinguishable from an expired
    // session -- nothing persists, so the token issued with the page is gone by
    // the time the form is submitted. Report that plainly instead of sending
    // the user round a refresh loop that can never succeed.
    if (class_exists('DbSessionHandler') && DbSessionHandler::$failed) {
        json_fail('The database is not reachable, so nothing can be saved yet. '
            . 'Check the deployment configuration.', 503);
    }
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        json_fail('Your session expired. Please refresh the page and try again.', 403);
    }
}

// ---- Login rate limiting (§6.1): 5 attempts / 15 min / phone ----
function login_rate_limited(string $phone): bool {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE phone = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    $stmt->execute([$phone]);
    return (int)$stmt->fetchColumn() >= 5;
}

function record_login_attempt(string $phone, bool $success): void {
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (phone, ip, success) VALUES (?, ?, ?)'
    );
    $stmt->execute([$phone, $_SERVER['REMOTE_ADDR'] ?? null, $success ? 1 : 0]);
}

/** Attempt login; returns user row on success, null on failure. */
function attempt_login(string $phone, string $password): ?array {
    $stmt = db()->prepare(
        'SELECT id, name, phone, role, password_hash, active, must_change_password
         FROM users WHERE phone = ?'
    );
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    if (!$user || !$user['active'] || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['auth_at'] = time();
    $_SESSION['must_change_password'] = (int)$user['must_change_password'];
    unset($_SESSION['csrf']);   // fresh token for the new session
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
        ->execute([$user['id']]);
    return $user;
}

function logout(): void {
    session_unset();
    session_destroy();
}
