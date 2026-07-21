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

$CATEGORIES = ['seed', 'media_soil', 'bags', 'fertilizer', 'chemical', 'water',
               'transport', 'equipment', 'other'];

function check_batch(?int $batchId): ?int {
    if (!$batchId) return null;
    $stmt = db()->prepare('SELECT id FROM batches WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$batchId]);
    if (!$stmt->fetch()) json_fail('Batch not found');
    return $batchId;
}

switch ($action) {

case 'create':
case 'update': {
    $category = (string)need($body, 'category', 'Category');
    if (!in_array($category, $CATEGORIES, true)) json_fail('Choose a category from the list');
    $description = trim((string)need($body, 'description', 'What was bought'));
    $supplier = trim((string)($body['supplier'] ?? '')) ?: null;
    $amount = need_money($body, 'amount', 'Amount', false);
    $date = opt_date($body, 'expense_date');
    $batchId = check_batch(!empty($body['batch_id']) ? (int)$body['batch_id'] : null);

    if ($action === 'create') {
        $pdo->prepare(
            'INSERT INTO expenses (category, description, supplier, amount, expense_date, batch_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$category, $description, $supplier, $amount, $date, $batchId, $_SESSION['user_id']]);
        $id = (int)$pdo->lastInsertId();
        activity_log('create', 'expenses', $id, null, ['description' => $description, 'amount' => $amount]);
        json_ok(['id' => $id]);
    }

    $id = (int)need($body, 'id', 'Expense');
    $stmt = $pdo->prepare('SELECT * FROM expenses WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) json_fail('Expense not found', 404);
    $pdo->prepare(
        'UPDATE expenses SET category = ?, description = ?, supplier = ?, amount = ?,
                expense_date = ?, batch_id = ? WHERE id = ?'
    )->execute([$category, $description, $supplier, $amount, $date, $batchId, $id]);
    activity_log('update', 'expenses', $id,
        ['description' => $old['description'], 'amount' => $old['amount']],
        ['description' => $description, 'amount' => $amount]);
    json_ok();
}

case 'delete': {
    $id = (int)need($body, 'id', 'Expense');
    $stmt = $pdo->prepare('SELECT * FROM expenses WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) json_fail('Expense not found', 404);
    $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
    activity_log('delete', 'expenses', $id, $old, null);
    json_ok();
}

default:
    json_fail('Unknown action');
}
