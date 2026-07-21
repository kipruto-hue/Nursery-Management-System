<?php
require_once __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Not allowed', 405);
}
require_login_api();
require_csrf();

$body = read_json_body();
$current = (string)($body['current'] ?? '');
$new     = (string)($body['new'] ?? '');

if (strlen($new) < 6) {
    json_fail('New password must be at least 6 characters');
}

$stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$hash = $stmt->fetchColumn();
if (!password_verify($current, $hash)) {
    json_fail('Your current password is wrong');
}

db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
    ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
$_SESSION['must_change_password'] = 0;
activity_log('change_password', 'users', (int)$_SESSION['user_id']);
json_ok();
