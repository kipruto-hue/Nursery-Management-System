<?php
require_once __DIR__ . '/../includes/layout.php';
require_login_page();

$links = [
    ['/app/customers.php', 'Customers', 'People who buy from you'],
    ['/app/preorders.php', 'Pre-orders', 'Seedlings booked in advance'],
];
if (is_owner()) {
    $links[] = ['/app/expenses.php', 'Purchases & Expenses', 'Seeds, bags, fertilizer, water…'];
    $links[] = ['/app/labour.php', 'Labour Payments', 'Payments to casual workers'];
    $links[] = ['/app/debtors.php', 'Money Owed to You', 'Customers with unpaid balances'];
    $links[] = ['/app/settings.php', 'Settings', 'Plants, prices, workers, activity log'];
}
$links[] = ['/app/password.php', 'Change Password', 'Change your own password'];

page_top('More');
?>

<div class="list">
<?php foreach ($links as [$href, $title, $sub]): ?>
  <a class="list-item" href="<?= e($href) ?>">
    <div>
      <div class="main-line"><?= e($title) ?></div>
      <div class="sub-line"><?= e($sub) ?></div>
    </div>
    <div class="right muted">›</div>
  </a>
<?php endforeach; ?>
</div>

<button class="btn secondary mt-3" id="logout-btn">Log Out</button>

<script>
document.getElementById('logout-btn').addEventListener('click', async () => {
  try { await apiPost('/api/logout.php', {}); } catch (e) {}
  location.href = '/index.php';
});
</script>
<?php page_bottom('more'); ?>
