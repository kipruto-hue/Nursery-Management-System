<?php
require_once __DIR__ . '/auth.php';

/** Inline SVG icons (§4: no decorative images, inline SVG only). */
function icon(string $name, int $size = 24): string {
    $paths = [
        'home'    => '<path d="M4 11l8-7 8 7v9a1 1 0 0 1-1 1h-5v-6h-4v6H5a1 1 0 0 1-1-1z"/>',
        'leaf'    => '<path d="M6 21c0-9 4-15 14-16-1 10-5 14-12 14 0 0-1 0-2 2zm0 0c2-5 5-8 9-10"/>',
        'sale'    => '<path d="M4 7h16l-1.5 11a2 2 0 0 1-2 1.7h-9A2 2 0 0 1 5.5 18zM9 7a3 3 0 0 1 6 0"/>',
        'report'  => '<path d="M5 21V9m7 12V3m7 18v-8"/>',
        'more'    => '<circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'sprout'  => '<path d="M12 22V12m0 0C12 7 8 4 3 4c0 5 3 8 9 8zm0-2c0-4 3-7 8-7 0 4-3 7-8 7z"/>',
    ];
    $p = $paths[$name] ?? $paths['leaf'];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
         . 'aria-hidden="true">' . $p . '</svg>';
}

/** Opens the HTML page: header bar + <main>. */
function page_top(string $title): void {
    $user = current_user();
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="#2E7D32">
<title><?= e($title) ?> · <?= e(setting('nursery_name', 'Nursery')) ?></title>
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<header class="topbar">
  <div>
    <div class="nursery-name"><?= e(setting('nursery_name', 'Nursery')) ?></div>
    <h1><?= e($title) ?></h1>
  </div>
  <div class="who"><?= e($user['name'] ?? '') ?><br><?= $user && $user['role'] === 'owner' ? 'Owner' : 'Worker' ?></div>
</header>
<main class="page">
<?php
}

/** Closes the page: bottom nav + scripts. $page marks the active tab. */
function page_bottom(string $page = '', array $scripts = []): void {
    $tabs = [
        ['dashboard', '/app/dashboard', 'Home',    'home'],
        ['batches',   '/app/batches',   'Batches', 'sprout'],
        ['sales',     '/app/sales',     'Sales',   'sale'],
    ];
    if (is_owner()) {
        $tabs[] = ['reports', '/app/reports', 'Reports', 'report'];
    }
    $tabs[] = ['more', '/app/more', 'More', 'more'];
    ?>
</main>
<nav class="bottom-nav">
<?php foreach ($tabs as [$id, $href, $label, $ic]): ?>
  <a href="<?= e($href) ?>" class="<?= $id === $page ? 'active' : '' ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
<?php endforeach; ?>
</nav>
<script src="/assets/js/app.js"></script>
<?php foreach ($scripts as $s): ?>
<script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
