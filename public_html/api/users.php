<?php
require_once __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Not allowed', 405);
}
require_owner_api();
require_csrf();

$body = read_json_body();
$action = $body['action'] ?? '';
$pdo = db();

function temp_password(): string {
    // Easy to read out over the phone: no confusing characters
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    $p = '';
    for ($i = 0; $i < 8; $i++) $p .= $chars[random_int(0, strlen($chars) - 1)];
    return $p;
}

switch ($action) {

case 'create': {
    $name = trim((string)need($body, 'name', 'Worker name'));
    $phone = preg_replace('/\s+/', '', (string)need($body, 'phone', 'Phone number'));
    if (!preg_match('/^0\d{9}$/', $phone)) {
        json_fail('Phone number should look like 07XX XXX XXX');
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ?');
    $stmt->execute([$phone]);
    if ($stmt->fetch()) json_fail('That phone number is already registered');

    $temp = temp_password();
    $pdo->prepare(
        'INSERT INTO users (name, phone, role, password_hash, active, must_change_password, created_by)
         VALUES (?, ?, "worker", ?, 1, 1, ?)'
    )->execute([$name, $phone, password_hash($temp, PASSWORD_DEFAULT), $_SESSION['user_id']]);
    $id = (int)$pdo->lastInsertId();
    activity_log('create', 'users', $id, null, ['name' => $name, 'phone' => $phone]);
    json_ok(['id' => $id, 'temp_password' => $temp]);
}

case 'set_active': {
    $id = (int)need($body, 'id', 'Worker');
    $active = (int)!!($body['active'] ?? 0);
    if ($id === (int)$_SESSION['user_id']) json_fail('You cannot deactivate yourself');
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'worker'");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) json_fail('Worker not found', 404);
    $pdo->prepare('UPDATE users SET active = ? WHERE id = ?')->execute([$active, $id]);
    activity_log('update', 'users', $id, ['active' => $u['active']], ['active' => $active]);
    json_ok();
}

case 'reset_password': {
    $id = (int)need($body, 'id', 'Worker');
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'worker'");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) json_fail('Worker not found', 404);
    $temp = temp_password();
    $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?')
        ->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
    activity_log('update', 'users', $id, null, ['password_reset' => true]);
    json_ok(['temp_password' => $temp]);
}

default:
    json_fail('Unknown action');
}
