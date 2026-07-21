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
    $items = $body['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        json_fail('Add at least one batch to the sale');
    }
    $saleDate = opt_date($body, 'sale_date');
    $method = (string)($body['payment_method'] ?? 'cash');
    if (!in_array($method, ['mpesa', 'cash', 'credit', 'mixed'], true)) {
        json_fail('Choose how the customer paid');
    }
    $mpesaRef = trim((string)($body['mpesa_ref'] ?? '')) ?: null;
    $notes = trim((string)($body['notes'] ?? '')) ?: null;
    $customerId = !empty($body['customer_id']) ? (int)$body['customer_id'] : null;
    $preorderId = !empty($body['preorder_id']) ? (int)$body['preorder_id'] : null;

    // Validate + total the items before touching stock
    $total = 0.0;
    $clean = [];
    foreach ($items as $it) {
        $batchId = (int)($it['batch_id'] ?? 0);
        $qty = $it['qty'] ?? 0;
        $price = $it['unit_price'] ?? -1;
        if ($batchId <= 0 || !is_numeric($qty) || (int)$qty <= 0) {
            json_fail('Each line needs a batch and a quantity');
        }
        if (!is_numeric($price) || (float)$price < 0) {
            json_fail('Each line needs a price of 0 or more');
        }
        $qty = (int)$qty;
        $price = round((float)$price, 2);
        $line = round($qty * $price, 2);
        $total += $line;
        $clean[] = ['batch_id' => $batchId, 'qty' => $qty, 'price' => $price, 'line' => $line];
    }
    $total = round($total, 2);

    $paid = need_money($body, 'amount_paid', 'Amount paid');
    if ($method === 'credit') $paid = 0.0;

    $pdo->beginTransaction();

    // Pre-order fulfilment: deposit counts toward what is paid (§6.6)
    $preorder = null;
    if ($preorderId) {
        $stmt = $pdo->prepare("SELECT * FROM preorders WHERE id = ? AND status = 'open' FOR UPDATE");
        $stmt->execute([$preorderId]);
        $preorder = $stmt->fetch();
        if (!$preorder) { $pdo->rollBack(); json_fail('That pre-order is not open any more'); }
        $customerId = (int)$preorder['customer_id'];
        $paid = round($paid + (float)$preorder['deposit_paid'], 2);
    }

    if ($paid > $total) { $pdo->rollBack(); json_fail('Amount paid is more than the total'); }
    $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'credit');

    // Credit needs a named customer with a phone number (§6.5)
    if ($status !== 'paid') {
        if (!$customerId) { $pdo->rollBack(); json_fail('Credit sales need a customer name and phone number'); }
        $stmt = $pdo->prepare('SELECT phone FROM customers WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$customerId]);
        $phone = $stmt->fetchColumn();
        if ($phone === false) { $pdo->rollBack(); json_fail('Customer not found'); }
        if (!trim((string)$phone)) { $pdo->rollBack(); json_fail('Credit sales need the customer\'s phone number. Add it first.'); }
    } elseif ($customerId) {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$customerId]);
        if (!$stmt->fetch()) { $pdo->rollBack(); json_fail('Customer not found'); }
    }

    // Take stock from each batch, rejecting oversell (§5 integrity)
    foreach ($clean as &$it) {
        $stmt = $pdo->prepare(
            'SELECT b.current_qty, b.stage, b.batch_code, s.name AS species_name
             FROM batches b JOIN species s ON s.id = b.species_id
             WHERE b.id = ? AND b.deleted_at IS NULL FOR UPDATE'
        );
        $stmt->execute([$it['batch_id']]);
        $batch = $stmt->fetch();
        if (!$batch) { $pdo->rollBack(); json_fail('Batch not found'); }
        if ($it['qty'] > (int)$batch['current_qty']) {
            $pdo->rollBack();
            json_fail('Only ' . number_format($batch['current_qty']) . " seedlings left in batch {$batch['batch_code']}");
        }
        $it['species_name'] = $batch['species_name'];
        $it['batch_code'] = $batch['batch_code'];
        $pdo->prepare(
            "UPDATE batches SET current_qty = current_qty - ?,
                    stage = IF(current_qty = 0 AND stage = 'ready', 'sold_out', stage)
             WHERE id = ?"
        )->execute([$it['qty'], $it['batch_id']]);
    }
    unset($it);

    $pdo->prepare(
        'INSERT INTO sales (customer_id, sale_date, total_amount, amount_paid, payment_method,
                            mpesa_ref, status, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$customerId, $saleDate, $total, $paid, $method, $mpesaRef, $status, $notes, $_SESSION['user_id']]);
    $saleId = (int)$pdo->lastInsertId();

    $ins = $pdo->prepare(
        'INSERT INTO sale_items (sale_id, batch_id, qty, unit_price, line_total, created_by)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($clean as $it) {
        $ins->execute([$saleId, $it['batch_id'], $it['qty'], $it['price'], $it['line'], $_SESSION['user_id']]);
    }

    // Record what was actually received now (deposit was recorded at pre-order time)
    $receivedNow = $preorder ? round($paid - (float)$preorder['deposit_paid'], 2) : $paid;
    if ($receivedNow > 0) {
        $pdo->prepare(
            'INSERT INTO payments (sale_id, amount, payment_method, mpesa_ref, created_by)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$saleId, $receivedNow, $method === 'mpesa' ? 'mpesa' : 'cash', $mpesaRef, $_SESSION['user_id']]);
    }

    if ($preorder) {
        $pdo->prepare("UPDATE preorders SET status = 'fulfilled', fulfilled_sale_id = ? WHERE id = ?")
            ->execute([$saleId, $preorderId]);
        activity_log('update', 'preorders', $preorderId, ['status' => 'open'], ['status' => 'fulfilled']);
    }

    activity_log('create', 'sales', $saleId, null,
        ['total_amount' => $total, 'amount_paid' => $paid, 'status' => $status]);
    $pdo->commit();
    json_ok(['id' => $saleId], true);
}

case 'delete': {
    require_owner_api();
    $id = (int)need($body, 'id', 'Sale');
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $sale = $stmt->fetch();
    if (!$sale) { $pdo->rollBack(); json_fail('Sale not found', 404); }

    // Give the seedlings back to their batches
    $items = $pdo->prepare('SELECT batch_id, qty FROM sale_items WHERE sale_id = ?');
    $items->execute([$id]);
    foreach ($items->fetchAll() as $it) {
        $pdo->prepare(
            "UPDATE batches SET current_qty = current_qty + ?,
                    stage = IF(stage = 'sold_out', 'ready', stage)
             WHERE id = ?"
        )->execute([$it['qty'], $it['batch_id']]);
    }
    $pdo->prepare("UPDATE preorders SET status = 'open', fulfilled_sale_id = NULL WHERE fulfilled_sale_id = ?")
        ->execute([$id]);
    $pdo->prepare('DELETE FROM payments WHERE sale_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM sale_items WHERE sale_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM sales WHERE id = ?')->execute([$id]);
    activity_log('delete', 'sales', $id, $sale, null);
    $pdo->commit();
    json_ok();
}

default:
    json_fail('Unknown action');
}
