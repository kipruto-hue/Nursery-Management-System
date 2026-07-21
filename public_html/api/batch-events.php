<?php
require_once __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Not allowed', 405);
}
require_login_api();
require_csrf();

$body = read_json_body();
$action = $body['action'] ?? 'add';
$pdo = db();

$TYPES = ['watering', 'potting', 'loss', 'input_usage', 'note'];
$LOSS_REASONS = ['dried', 'disease', 'pests', 'damage', 'other'];

switch ($action) {

case 'add': {
    $batchId = (int)need($body, 'batch_id', 'Batch');
    $type = (string)need($body, 'event_type', 'Activity type');
    if (!in_array($type, $TYPES, true)) json_fail('That activity type is not valid');
    $date = opt_date($body, 'event_date');
    $notes = trim((string)($body['notes'] ?? '')) ?: null;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT id, current_qty, stage FROM batches WHERE id = ? AND deleted_at IS NULL FOR UPDATE'
    );
    $stmt->execute([$batchId]);
    $batch = $stmt->fetch();
    if (!$batch) { $pdo->rollBack(); json_fail('Batch not found', 404); }

    $qty = null;
    $reason = null;

    if ($type === 'loss') {
        $qty = need_qty($body, 'qty', 'Seedlings lost');
        $reason = (string)need($body, 'loss_reason', 'Reason');
        if (!in_array($reason, $LOSS_REASONS, true)) json_fail('Choose a reason from the list');
        if ($qty > (int)$batch['current_qty']) {
            $pdo->rollBack();
            json_fail('Only ' . number_format($batch['current_qty']) . ' seedlings left in this batch');
        }
        $pdo->prepare('UPDATE batches SET current_qty = current_qty - ? WHERE id = ?')
            ->execute([$qty, $batchId]);
    } elseif ($type === 'potting' && isset($body['qty']) && $body['qty'] !== '') {
        $qty = need_qty($body, 'qty', 'Seedlings potted');
    }

    $pdo->prepare(
        'INSERT INTO batch_events (batch_id, event_type, qty, loss_reason, event_date, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$batchId, $type, $qty, $reason, $date, $notes, $_SESSION['user_id']]);
    $eventId = (int)$pdo->lastInsertId();
    activity_log('create', 'batch_events', $eventId, null,
        ['batch_id' => $batchId, 'event_type' => $type, 'qty' => $qty]);

    $stmt = $pdo->prepare('SELECT current_qty FROM batches WHERE id = ?');
    $stmt->execute([$batchId]);
    $remaining = (int)$stmt->fetchColumn();
    $pdo->commit();
    json_ok(['remaining' => $remaining]);
}

case 'delete': {
    require_owner_api();
    $id = (int)need($body, 'id', 'Record');
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM batch_events WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $ev = $stmt->fetch();
    if (!$ev) { $pdo->rollBack(); json_fail('Record not found', 404); }

    // Deleting a loss gives the seedlings back to the batch
    if ($ev['event_type'] === 'loss' && $ev['qty']) {
        $pdo->prepare('UPDATE batches SET current_qty = current_qty + ? WHERE id = ?')
            ->execute([$ev['qty'], $ev['batch_id']]);
    }
    $pdo->prepare('DELETE FROM batch_events WHERE id = ?')->execute([$id]);
    activity_log('delete', 'batch_events', $id, $ev, null);
    $pdo->commit();
    json_ok();
}

default:
    json_fail('Unknown action');
}
