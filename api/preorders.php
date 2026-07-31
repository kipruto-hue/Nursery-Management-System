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
    $customerId = (int)need($body, 'customer_id', 'Customer');
    $speciesId = (int)need($body, 'species_id', 'Plant type');
    $qty = need_qty($body, 'qty', 'Quantity');
    $price = need_money($body, 'agreed_unit_price', 'Agreed price');
    $deposit = isset($body['deposit_paid']) && $body['deposit_paid'] !== ''
        ? need_money($body, 'deposit_paid', 'Deposit') : 0.0;
    $expected = ($body['expected_date'] ?? '') !== '' ? opt_date($body, 'expected_date') : null;

    if ($deposit > $qty * $price) json_fail('Deposit is more than the total order value');

    $stmt = $pdo->prepare('SELECT id FROM customers WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$customerId]);
    if (!$stmt->fetch()) json_fail('Choose a customer from the list');
    $stmt = $pdo->prepare('SELECT id FROM species WHERE id = ? AND active = 1');
    $stmt->execute([$speciesId]);
    if (!$stmt->fetch()) json_fail('Choose a plant type from the list');

    $pdo->prepare(
        'INSERT INTO preorders (customer_id, species_id, qty, agreed_unit_price, deposit_paid,
                                expected_date, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, "open", ?)'
    )->execute([$customerId, $speciesId, $qty, $price, $deposit, $expected, $_SESSION['user_id']]);
    $id = (int)$pdo->lastInsertId();
    activity_log('create', 'preorders', $id, null, ['qty' => $qty, 'deposit_paid' => $deposit]);
    json_ok(['id' => $id], true);
}

case 'cancel': {
    require_owner_api();
    $id = (int)need($body, 'id', 'Pre-order');
    $stmt = $pdo->prepare("SELECT * FROM preorders WHERE id = ? AND status = 'open'");
    $stmt->execute([$id]);
    $po = $stmt->fetch();
    if (!$po) json_fail('Pre-order not found or already closed', 404);
    $pdo->prepare("UPDATE preorders SET status = 'cancelled' WHERE id = ?")->execute([$id]);
    activity_log('update', 'preorders', $id, ['status' => 'open'], ['status' => 'cancelled']);
    json_ok();
}

default:
    json_fail('Unknown action');
}
