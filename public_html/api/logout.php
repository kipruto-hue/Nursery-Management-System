<?php
require_once __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('Not allowed', 405);
}
require_login_api();
require_csrf();
activity_log('logout', 'users', (int)$_SESSION['user_id']);
logout();
json_ok();
