<?php
require_once __DIR__ . '/../includes/layout.php';
require_owner_page();

$pdo = db();
$debtors = $pdo->query(
    "SELECT sa.id, sa.sale_date, sa.total_amount, sa.amount_paid,
            (sa.total_amount - sa.amount_paid) AS balance,
            DATEDIFF(CURDATE(), sa.sale_date) AS days_out,
            c.name AS customer_name, c.phone AS customer_phone
     FROM sales sa LEFT JOIN customers c ON c.id = sa.customer_id
     WHERE sa.status IN ('partial','credit')
     ORDER BY days_out DESC"
)->fetchAll();

$total = array_sum(array_column($debtors, 'balance'));

page_top('Money Owed to You');
?>

<?php if (!$debtors): ?>
<div class="card empty-state">Nobody owes you money right now. Well done!</div>
<?php else: ?>
<div class="stat-card mb-3">
  <div class="label">Total owed</div>
  <div class="value danger"><?= e(money($total)) ?></div>
</div>
<div class="list">
<?php foreach ($debtors as $d): ?>
  <a class="list-item" href="/app/sale.php?id=<?= (int)$d['id'] ?>">
    <div>
      <div class="main-line"><?= e($d['customer_name'] ?? 'Walk-in customer') ?></div>
      <div class="sub-line">
        Sale #<?= (int)$d['id'] ?> · <?= (int)$d['days_out'] ?> day<?= $d['days_out'] == 1 ? '' : 's' ?> ago
        <?= $d['customer_phone'] ? ' · ' . e($d['customer_phone']) : '' ?>
      </div>
    </div>
    <div class="right">
      <div class="big danger-text"><?= e(money($d['balance'])) ?></div>
      <div class="small">Tap to record payment</div>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_bottom('sales'); ?>
