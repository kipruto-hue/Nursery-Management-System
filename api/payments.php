<?php
require_once __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Not allowed', 405);
}
require_login_api();
require_csrf();

$body = read_json_body();
$pdo = db();

$saleId = (int)need($body, 'sale_id', 'Sale');
$amount = need_money($body, 'amount', 'Amount', false);
$method = (string)($body['payment_method'] ?? 'cash');
if (!in_array($method, ['mpesa', 'cash'], true)) {
    json_fail('Choose how the customer paid');
}
$mpesaRef = trim((string)($body['mpesa_ref'] ?? '')) ?: null;

$pdo->beginTransaction();
$stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ? FOR UPDATE');
$stmt->execute([$saleId]);
$sale = $stmt->fetch();
if (!$sale) { $pdo->rollBack(); json_fail('Sale not found', 404); }

$balance = round((float)$sale['total_amount'] - (float)$sale['amount_paid'], 2);
if ($balance <= 0) { $pdo->rollBack(); json_fail('This sale is already fully paid'); }
if ($amount > $balance) {
    $pdo->rollBack();
    json_fail('That is more than the ' . money($balance) . ' still owed');
}

$pdo->prepare(
    'INSERT INTO payments (sale_id, amount, payment_method, mpesa_ref, created_by)
     VALUES (?, ?, ?, ?, ?)'
)->execute([$saleId, $amount, $method, $mpesaRef, $_SESSION['user_id']]);

$newPaid = round((float)$sale['amount_paid'] + $amount, 2);
$newStatus = $newPaid >= (float)$sale['total_amount'] ? 'paid' : 'partial';
$pdo->prepare('UPDATE sales SET amount_paid = ?, status = ? WHERE id = ?')
    ->execute([$newPaid, $newStatus, $saleId]);

activity_log('create', 'payments', (int)$pdo->lastInsertId(), null,
    ['sale_id' => $saleId, 'amount' => $amount, 'new_status' => $newStatus]);
$pdo->commit();
json_ok(['status' => $newStatus, 'amount_paid' => $newPaid], true);
