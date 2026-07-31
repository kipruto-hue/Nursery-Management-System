<?php
require_once __DIR__ . '/../includes/layout.php';
require_login_page();

$pdo = db();
$sales = $pdo->query(
    "SELECT sa.id, sa.sale_date, sa.total_amount, sa.amount_paid, sa.status,
            c.name AS customer_name,
            (SELECT COALESCE(SUM(qty),0) FROM sale_items si WHERE si.sale_id = sa.id) AS total_qty
     FROM sales sa LEFT JOIN customers c ON c.id = sa.customer_id
     ORDER BY sa.sale_date DESC, sa.id DESC
     LIMIT 100"
)->fetchAll();

$statusLabels = ['paid' => 'Paid', 'partial' => 'Part paid', 'credit' => 'Credit'];

$owedTotal = 0;
if (is_owner()) {
    $owedTotal = (float)$pdo->query(
        "SELECT COALESCE(SUM(total_amount - amount_paid),0) FROM sales WHERE status IN ('partial','credit')"
    )->fetchColumn();
}

page_top('Sales');
?>

<?php if (is_owner() && $owedTotal > 0): ?>
<a class="list-item mb-3" href="/app/debtors" style="border-left:4px solid var(--color-danger)">
  <div>
    <div class="main-line">Money Owed to You</div>
    <div class="sub-line">Tap to see who owes what</div>
  </div>
  <div class="right"><div class="big danger-text"><?= e(money($owedTotal)) ?></div></div>
</a>
<?php endif; ?>

<?php if (!$sales): ?>
<div class="card empty-state"><?= icon('sale', 44) ?><br>No sales yet. Tap ＋ to record your first sale.</div>
<?php else: ?>
<div class="list">
<?php foreach ($sales as $s): ?>
  <a class="list-item" href="/app/sale?id=<?= (int)$s['id'] ?>">
    <div>
      <div class="main-line"><?= e($s['customer_name'] ?? 'Walk-in customer') ?></div>
      <div class="sub-line"><?= e(date('j M Y', strtotime($s['sale_date']))) ?> · <?= number_format($s['total_qty']) ?> seedlings</div>
    </div>
    <div class="right">
      <div class="big"><?= e(money($s['total_amount'])) ?></div>
      <span class="badge status-<?= e($s['status']) ?>"><?= e($statusLabels[$s['status']]) ?></span>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<a href="/app/sale-new" class="fab" aria-label="New sale">＋</a>
<?php page_bottom('sales'); ?>
