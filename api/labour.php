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

switch ($action) {

case 'create':
case 'update': {
    $workerName = trim((string)need($body, 'worker_name', 'Who was paid'));
    $task = trim((string)need($body, 'task', 'What work was done'));
    $amount = need_money($body, 'amount', 'Amount', false);
    $date = opt_date($body, 'labour_date');
    $batchId = null;
    if (!empty($body['batch_id'])) {
        $batchId = (int)$body['batch_id'];
        $stmt = $pdo->prepare('SELECT id FROM batches WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$batchId]);
        if (!$stmt->fetch()) json_fail('Batch not found');
    }

    if ($action === 'create') {
        $pdo->prepare(
            'INSERT INTO labour (worker_name, task, amount, labour_date, batch_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$workerName, $task, $amount, $date, $batchId, $_SESSION['user_id']]);
        $id = (int)$pdo->lastInsertId();
        activity_log('create', 'labour', $id, null, ['worker_name' => $workerName, 'amount' => $amount]);
        json_ok(['id' => $id]);
    }

    $id = (int)need($body, 'id', 'Record');
    $stmt = $pdo->prepare('SELECT * FROM labour WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) json_fail('Record not found', 404);
    $pdo->prepare(
        'UPDATE labour SET worker_name = ?, task = ?, amount = ?, labour_date = ?, batch_id = ? WHERE id = ?'
    )->execute([$workerName, $task, $amount, $date, $batchId, $id]);
    activity_log('update', 'labour', $id,
        ['worker_name' => $old['worker_name'], 'amount' => $old['amount']],
        ['worker_name' => $workerName, 'amount' => $amount]);
    json_ok();
}

case 'delete': {
    $id = (int)need($body, 'id', 'Record');
    $stmt = $pdo->prepare('SELECT * FROM labour WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) json_fail('Record not found', 404);
    $pdo->prepare('DELETE FROM labour WHERE id = ?')->execute([$id]);
    activity_log('delete', 'labour', $id, $old, null);
    json_ok();
}

default:
    json_fail('Unknown action');
}
