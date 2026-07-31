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

$CATEGORIES = ['fruit', 'indigenous', 'ornamental', 'vegetable', 'other'];

switch ($action) {

case 'create':
case 'update': {
    $name = trim((string)need($body, 'name', 'Plant name'));
    $category = (string)($body['category'] ?? 'other');
    if (!in_array($category, $CATEGORIES, true)) $category = 'other';
    $price = need_money($body, 'default_sale_price', 'Sale price');
    $lead = isset($body['lead_time_days']) && $body['lead_time_days'] !== ''
        ? max(0, (int)$body['lead_time_days']) : null;

    if ($action === 'create') {
        $prefix = strtoupper(trim((string)($body['code_prefix'] ?? '')));
        if (!preg_match('/^[A-Z]{2,5}$/', $prefix)) {
            // Build a prefix from the name if none given
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
            if (strlen($prefix) < 2) json_fail('Give this plant a short code of 2-5 letters');
        }
        $pdo->prepare(
            'INSERT INTO species (name, category, default_sale_price, lead_time_days, code_prefix, active, created_by)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        )->execute([$name, $category, $price, $lead, $prefix, $_SESSION['user_id']]);
        $id = (int)$pdo->lastInsertId();
        activity_log('create', 'species', $id, null, ['name' => $name, 'default_sale_price' => $price]);
        json_ok(['id' => $id]);
    }

    $id = (int)need($body, 'id', 'Plant');
    $stmt = $pdo->prepare('SELECT * FROM species WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) json_fail('Plant not found', 404);
    $active = isset($body['active']) ? (int)!!$body['active'] : (int)$old['active'];
    $pdo->prepare(
        'UPDATE species SET name = ?, category = ?, default_sale_price = ?, lead_time_days = ?, active = ? WHERE id = ?'
    )->execute([$name, $category, $price, $lead, $active, $id]);
    activity_log('update', 'species', $id,
        ['name' => $old['name'], 'default_sale_price' => $old['default_sale_price'], 'active' => $old['active']],
        ['name' => $name, 'default_sale_price' => $price, 'active' => $active]);
    json_ok();
}

default:
    json_fail('Unknown action');
}
