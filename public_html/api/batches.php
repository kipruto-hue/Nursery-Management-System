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

$STAGES = ['sown', 'germinating', 'potted', 'hardening', 'ready', 'sold_out', 'written_off'];

switch ($action) {

case 'create': {
    $speciesId = (int)need($body, 'species_id', 'Plant type');
    $sownDate  = opt_date($body, 'sown_date');
    $qty       = need_qty($body, 'initial_qty', 'Number of seedlings');
    $expected  = ($body['expected_ready_date'] ?? '') !== '' ? opt_date($body, 'expected_ready_date') : null;
    $notes     = trim((string)($body['notes'] ?? '')) ?: null;

    $stmt = $pdo->prepare('SELECT id FROM species WHERE id = ? AND active = 1');
    $stmt->execute([$speciesId]);
    if (!$stmt->fetch()) json_fail('Choose a plant type from the list');

    $pdo->beginTransaction();
    $code = generate_batch_code($speciesId);
    $pdo->prepare(
        'INSERT INTO batches (batch_code, species_id, sown_date, initial_qty, current_qty,
                              stage, expected_ready_date, notes, created_by)
         VALUES (?, ?, ?, ?, ?, "sown", ?, ?, ?)'
    )->execute([$code, $speciesId, $sownDate, $qty, $qty, $expected, $notes, $_SESSION['user_id']]);
    $id = (int)$pdo->lastInsertId();
    activity_log('create', 'batches', $id, null, ['batch_code' => $code, 'initial_qty' => $qty]);
    $pdo->commit();
    json_ok(['id' => $id, 'batch_code' => $code]);
}

case 'set_stage': {
    $id = (int)need($body, 'id', 'Batch');
    $stage = (string)need($body, 'stage', 'Stage');
    if (!in_array($stage, $STAGES, true)) json_fail('That stage is not valid');

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM batches WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
    $stmt->execute([$id]);
    $batch = $stmt->fetch();
    if (!$batch) { $pdo->rollBack(); json_fail('Batch not found', 404); }

    // Writing off a batch is destructive — owner only (§3: workers append, never edit)
    if ($stage === 'written_off' && !is_owner()) {
        $pdo->rollBack();
        json_fail('Only the owner can write off a batch', 403);
    }

    $pdo->prepare('UPDATE batches SET stage = ? WHERE id = ?')->execute([$stage, $id]);
    $pdo->prepare(
        'INSERT INTO batch_events (batch_id, event_type, new_stage, event_date, created_by)
         VALUES (?, "stage_change", ?, CURDATE(), ?)'
    )->execute([$id, $stage, $_SESSION['user_id']]);
    activity_log('update', 'batches', $id, ['stage' => $batch['stage']], ['stage' => $stage]);
    $pdo->commit();
    json_ok(['stage' => $stage]);
}

case 'update': {
    require_owner_api();
    $id = (int)need($body, 'id', 'Batch');
    $stmt = $pdo->prepare('SELECT * FROM batches WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $batch = $stmt->fetch();
    if (!$batch) json_fail('Batch not found', 404);

    $expected = ($body['expected_ready_date'] ?? '') !== '' ? opt_date($body, 'expected_ready_date') : null;
    $override = ($body['sale_price_override'] ?? '') !== ''
        ? need_money($body, 'sale_price_override', 'Sale price') : null;
    $notes = trim((string)($body['notes'] ?? '')) ?: null;

    $pdo->prepare(
        'UPDATE batches SET expected_ready_date = ?, sale_price_override = ?, notes = ? WHERE id = ?'
    )->execute([$expected, $override, $notes, $id]);
    activity_log('update', 'batches', $id,
        ['expected_ready_date' => $batch['expected_ready_date'],
         'sale_price_override' => $batch['sale_price_override'], 'notes' => $batch['notes']],
        ['expected_ready_date' => $expected, 'sale_price_override' => $override, 'notes' => $notes]);
    json_ok();
}

case 'delete': {
    require_owner_api();
    $id = (int)need($body, 'id', 'Batch');
    $stmt = $pdo->prepare('SELECT batch_code FROM batches WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $batch = $stmt->fetch();
    if (!$batch) json_fail('Batch not found', 404);
    $pdo->prepare('UPDATE batches SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    activity_log('delete', 'batches', $id, $batch, null);
    json_ok();
}

default:
    json_fail('Unknown action');
}
