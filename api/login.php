<?php
require_once __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Not allowed', 405);
}
require_csrf();

$body = read_json_body();
$phone = trim((string)($body['phone'] ?? ''));
$password = (string)($body['password'] ?? '');

if ($phone === '' || $password === '') {
    json_fail('Enter your phone number and password');
}
if (login_rate_limited($phone)) {
    json_fail('Too many tries. Please wait 15 minutes and try again.', 429);
}

$user = attempt_login($phone, $password);
record_login_attempt($phone, $user !== null);

if ($user === null) {
    json_fail('Wrong phone number or password');
}
activity_log('login', 'users', (int)$user['id']);
json_ok(['role' => $user['role']]);
