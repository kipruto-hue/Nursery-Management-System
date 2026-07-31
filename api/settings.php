<?php
require_once __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Not allowed', 405);
}
require_owner_api();
require_csrf();

$body = read_json_body();
$name = trim((string)need($body, 'nursery_name', 'Nursery name'));
if (mb_strlen($name) > 60) json_fail('Nursery name is too long');
save_setting('nursery_name', $name);
activity_log('update', 'settings', null, null, ['nursery_name' => $name]);
json_ok();
