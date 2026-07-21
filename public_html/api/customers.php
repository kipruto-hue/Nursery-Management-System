<?php
require_once __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Not allowed', 405);
}
require_login_api();
require_csrf();

$body = read_json_body();
$action = $body['action'] ?? '';
$pdo = db();

switch ($action) {

case 'create': {
    $name = trim((string)need($body, 'name', 'Customer name'));
    $phone = trim((string)($body['phone'] ?? '')) ?: null;
    $notes = trim((string)($body['notes'] ?? '')) ?: null;
    $pdo->prepare('INSERT INTO customers (name, phone, notes, created_by) VALUES (?, ?, ?, ?)')
        ->execute([$name, $phone, $notes, $_SESSION['user_id']]);
    $id = (int)$pdo->lastInsertId();
    activity_log('create', 'customers', $id, null, ['name' => $name, 'phone' => $phone]);
    json_ok(['id' => $id, 'name' => $name, 'phone' => $phone]);
}

case 'update': {
    require_owner_api();
    $id = (int)need($body, 'id', 'Customer');
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $cust = $stmt->fetch();
    if (!$cust) json_fail('Customer not found', 404);
    $name = trim((string)need($body, 'name', 'Customer name'));
    $phone = trim((string)($body['phone'] ?? '')) ?: null;
    $notes = trim((string)($body['notes'] ?? '')) ?: null;
    $pdo->prepare('UPDATE customers SET name = ?, phone = ?, notes = ? WHERE id = ?')
        ->execute([$name, $phone, $notes, $id]);
    activity_log('update', 'customers', $id,
        ['name' => $cust['name'], 'phone' => $cust['phone']],
        ['name' => $name, 'phone' => $phone]);
    json_ok();
}

case 'delete': {
    require_owner_api();
    $id = (int)need($body, 'id', 'Customer');
    $stmt = $pdo->prepare('SELECT name FROM customers WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $cust = $stmt->fetch();
    if (!$cust) json_fail('Customer not found', 404);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM sales WHERE customer_id = ? AND status IN ('partial','credit')"
    );
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        json_fail('This customer still owes money. Collect the debt first.');
    }
    $pdo->prepare('UPDATE customers SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    activity_log('delete', 'customers', $id, $cust, null);
    json_ok();
}

default:
    json_fail('Unknown action');
}
