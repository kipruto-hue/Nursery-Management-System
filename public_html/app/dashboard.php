<?php
require_once __DIR__ . '/../includes/layout.php';
require_login_page();

$pdo = db();

// Batches grouped by stage (both roles see quantities)
$batches = $pdo->query(
    "SELECT b.id, b.batch_code, b.stage, b.current_qty, s.name AS species_name
     FROM batches b JOIN species s ON s.id = b.species_id
     WHERE b.deleted_at IS NULL AND b.stage NOT IN ('sold_out','written_off')
     ORDER BY FIELD(b.stage,'ready','hardening','potted','germinating','sown'), b.sown_date"
)->fetchAll();

$openPreorders = $pdo->query(
    "SELECT p.id, p.qty, p.expected_date, s.name AS species_name, c.name AS customer_name
     FROM preorders p
     JOIN species s ON s.id = p.species_id
     JOIN customers c ON c.id = p.customer_id
     WHERE p.status = 'open' ORDER BY p.expected_date"
)->fetchAll();

if (is_owner()) {
    $readyStock = (int)$pdo->query(
        "SELECT COALESCE(SUM(current_qty),0) FROM batches
         WHERE stage = 'ready' AND deleted_at IS NULL"
    )->fetchColumn();
    $salesToday = (float)$pdo->query(
        "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date = CURDATE()"
    )->fetchColumn();
    $salesMonth = (float)$pdo->query(
        "SELECT COALESCE(SUM(total_amount),0) FROM sales
         WHERE YEAR(sale_date) = YEAR(CURDATE()) AND MONTH(sale_date) = MONTH(CURDATE())"
    )->fetchColumn();
    $moneyOwed = (float)$pdo->query(
        "SELECT COALESCE(SUM(total_amount - amount_paid),0) FROM sales
         WHERE status IN ('partial','credit')"
    )->fetchColumn();
    $activity = $pdo->query(
        "SELECT a.action, a.entity, a.created_at, u.name AS user_name
         FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.id DESC LIMIT 10"
    )->fetchAll();
}

$stageLabels = [
    'sown' => 'Sown', 'germinating' => 'Germinating', 'potted' => 'Potted',
    'hardening' => 'Hardening', 'ready' => 'Ready for Sale',
];
$actionLabels = [
    'create' => 'added', 'update' => 'changed', 'delete' => 'removed',
    'login' => 'logged in', 'logout' => 'logged out', 'change_password' => 'changed a password',
];
$entityLabels = [
    'batches' => 'a batch', 'batch_events' => 'a batch activity', 'sales' => 'a sale',
    'payments' => 'a payment', 'expenses' => 'an expense', 'labour' => 'a labour payment',
    'customers' => 'a customer', 'species' => 'a plant type', 'users' => '',
    'preorders' => 'a pre-order', 'settings' => 'settings',
];

page_top('Home');
?>

<?php if (is_owner()): ?>
<div class="stat-grid">
  <div class="stat-card">
    <div class="label">Stock Ready for Sale</div>
    <div class="value"><?= number_format($readyStock) ?> <span style="font-size:14px;font-weight:600">seedlings</span></div>
  </div>
  <div class="stat-card">
    <div class="label">Sales Today</div>
    <div class="value"><?= e(money($salesToday)) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Sales This Month</div>
    <div class="value"><?= e(money($salesMonth)) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Money Owed to You</div>
    <div class="value <?= $moneyOwed > 0 ? 'danger' : '' ?>"><?= e(money($moneyOwed)) ?></div>
  </div>
</div>
<?php else: ?>
<div class="section-title">Quick actions</div>
<div class="list mb-3">
  <a class="btn" href="/app/sale-new.php"><?= icon('sale', 20) ?> Record a Sale</a>
  <a class="btn secondary" href="/app/batches.php">Record Seedlings Lost / Activity</a>
</div>
<?php endif; ?>

<div class="section-title">Batches</div>
<?php if (!$batches): ?>
<div class="card empty-state"><?= icon('sprout', 44) ?><br>No batches yet. Tap Batches below to record your first sowing.</div>
<?php else: ?>
<div class="list">
<?php foreach ($batches as $b): ?>
  <a class="list-item" href="/app/batch.php?id=<?= (int)$b['id'] ?>">
    <div>
      <div class="main-line"><?= e($b['species_name']) ?></div>
      <div class="sub-line"><?= e($b['batch_code']) ?></div>
    </div>
    <div class="right">
      <div class="big"><?= number_format($b['current_qty']) ?></div>
      <span class="badge stage-<?= e($b['stage']) ?>"><?= e($stageLabels[$b['stage']] ?? $b['stage']) ?></span>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($openPreorders): ?>
<div class="section-title">Open pre-orders</div>
<div class="list">
<?php foreach ($openPreorders as $p): ?>
  <a class="list-item" href="/app/preorders.php">
    <div>
      <div class="main-line"><?= e($p['customer_name']) ?></div>
      <div class="sub-line"><?= number_format($p['qty']) ?> × <?= e($p['species_name']) ?></div>
    </div>
    <div class="right"><div class="small">Needed by<br><?= e($p['expected_date'] ?? '—') ?></div></div>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (is_owner() && $activity): ?>
<div class="section-title">Recent activity</div>
<div class="card">
  <ul class="timeline">
  <?php foreach ($activity as $a): ?>
    <li>
      <?= e(($a['user_name'] ?? 'Someone') . ' ' . ($actionLabels[$a['action']] ?? $a['action']) . ' ' . ($entityLabels[$a['entity']] ?? $a['entity'])) ?>
      <div class="when"><?= e(date('D j M, g:ia', strtotime($a['created_at']))) ?></div>
    </li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php page_bottom('dashboard'); ?>
