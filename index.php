<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    header('Location: /app/dashboard');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="#2E7D32">
<title>Log in · <?= e(setting('nursery_name', 'Nursery')) ?></title>
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-logo">
    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#2E7D32" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22V12m0 0C12 7 8 4 3 4c0 5 3 8 9 8zm0-2c0-4 3-7 8-7 0 4-3 7-8 7z"/></svg>
    <h1><?= e(setting('nursery_name', 'Nursery')) ?></h1>
    <p>Log in to continue</p>
  </div>
  <div class="card">
    <form id="login-form">
      <div class="field">
        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" autocomplete="username" inputmode="tel" placeholder="07XX XXX XXX" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <p class="field-error" id="login-error" hidden></p>
      <button type="submit" class="btn" id="login-btn">Log in</button>
    </form>
  </div>
  <p class="center muted" style="font-size:13px">Forgot your password? Ask the owner to reset it.</p>
</div>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/login.js"></script>
</body>
</html>
