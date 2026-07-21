<?php
require_once __DIR__ . '/../includes/layout.php';
require_owner_page();
require_once __DIR__ . '/../includes/reports.php';

$report = $_GET['r'] ?? 'profit';
$reports = [
    'profit'  => 'Profit per Batch',
    'stock'   => 'Stock Ready',
    'debts'   => 'Money Owed',
    'monthly' => 'Income vs Expenses',
    'losses'  => 'Losses',
];
if (!isset($reports[$report])) $report = 'profit';

$reasonLabels = [
    'dried' => 'Dried up', 'disease' => 'Disease', 'pests' => 'Pests',
    'damage' => 'Damage', 'other' => 'Other',
];
$stageLabels = [
    'sown' => 'Sown', 'germinating' => 'Germinating', 'potted' => 'Potted',
    'hardening' => 'Hardening', 'ready' => 'Ready', 'sold_out' => 'Sold out',
    'written_off' => 'Written off',
];

page_top('Reports');
?>

<div class="field">
  <label for="report-pick">Which report?</label>
  <select id="report-pick" onchange="location.href='/app/reports.php?r='+this.value">
    <?php foreach ($reports as $k => $label): ?>
    <option value="<?= e($k) ?>" <?= $k === $report ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<a class="btn secondary small mb-3" href="/api/export.php?report=<?= e($report) ?>">⬇ Download as CSV</a>

<?php if ($report === 'profit'): $rows = report_profit_per_batch(); ?>
<div class="card table-wrap">
  <table class="report" id="sortable">
    <thead><tr>
      <th data-sort="text">Batch</th><th data-sort="num" class="num">Cost</th>
      <th data-sort="num" class="num">Earned</th><th data-sort="num" class="num">Profit</th>
      <th data-sort="num" class="num">Margin</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['species_name']) ?><br><span class="muted" style="font-size:12px"><?= e($r['batch_code']) ?> · <?= e($stageLabels[$r['stage']] ?? '') ?></span></td>
        <td class="num" data-v="<?= e((string)$r['cost']) ?>" title="Inputs <?= e(money($r['direct_expenses'])) ?> + Labour <?= e(money($r['direct_labour'])) ?> + Overheads <?= e(money($r['overhead_share'])) ?>"><?= e(money($r['cost'])) ?></td>
        <td class="num" data-v="<?= e((string)$r['revenue']) ?>"><?= e(money($r['revenue'])) ?></td>
        <td class="num <?= $r['profit'] < 0 ? 'danger-text' : '' ?>" data-v="<?= e((string)$r['profit']) ?>"><?= e(money($r['profit'])) ?></td>
        <td class="num" data-v="<?= e((string)($r['margin'] ?? -999)) ?>"><?= $r['margin'] === null ? '—' : e($r['margin']) . '%' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="hint">Cost = money spent on the batch + labour + a fair share of general costs (split by batch size). Tap a column heading to sort.</p>

<?php elseif ($report === 'stock'): $rows = report_stock_ready(); $tv = array_sum(array_column($rows, 'estimated_value')); ?>
<div class="card table-wrap">
  <table class="report">
    <thead><tr><th>Plant</th><th class="num">Ready</th><th class="num">Worth about</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['species_name']) ?></td>
          <td class="num"><?= number_format($r['qty']) ?></td>
          <td class="num"><?= e(money($r['estimated_value'])) ?></td></tr>
    <?php endforeach; ?>
      <tr class="total"><td>Total</td>
          <td class="num"><?= number_format(array_sum(array_column($rows, 'qty'))) ?></td>
          <td class="num"><?= e(money($tv)) ?></td></tr>
    </tbody>
  </table>
</div>
<?php if (!$rows): ?><div class="card empty-state">Nothing is marked Ready for Sale right now.</div><?php endif; ?>

<?php elseif ($report === 'debts'): $rows = report_debtors();
    $buckets = ['0-7 days' => 0.0, '8-30 days' => 0.0, 'Over 30 days' => 0.0];
    foreach ($rows as $r) $buckets[$r['bucket']] += (float)$r['balance'];
?>
<div class="stat-grid" style="grid-template-columns:1fr 1fr 1fr">
  <?php foreach ($buckets as $label => $amt): ?>
  <div class="stat-card">
    <div class="label"><?= e($label) ?></div>
    <div class="value <?= $label === 'Over 30 days' && $amt > 0 ? 'danger' : '' ?>" style="font-size:16px"><?= e(money($amt)) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<div class="card table-wrap">
  <table class="report">
    <thead><tr><th>Customer</th><th class="num">Owes</th><th class="num">Days</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['customer_name'] ?? 'Walk-in') ?><br><span class="muted" style="font-size:12px"><?= e($r['phone'] ?? '') ?></span></td>
        <td class="num danger-text"><?= e(money($r['balance'])) ?></td>
        <td class="num"><?= (int)$r['days_out'] ?></td>
        <td><a class="btn small" href="/app/sale.php?id=<?= (int)$r['sale_id'] ?>">Record Payment</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if (!$rows): ?><div class="card empty-state">Nobody owes you money right now.</div><?php endif; ?>

<?php elseif ($report === 'monthly'): $rows = report_monthly();
    $max = max(1, max(array_merge(array_column($rows, 'income'), array_column($rows, 'expenses'))));
    // Inline SVG bar chart, no libraries (§6.7.4)
    $W = 720; $H = 260; $pad = 30; $plotH = $H - 2 * $pad;
    $groupW = ($W - 2 * $pad) / 12; $barW = $groupW * 0.34;
?>
<div class="card">
  <svg class="chart-svg" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Monthly income and expenses">
    <line x1="<?= $pad ?>" y1="<?= $H - $pad ?>" x2="<?= $W - $pad ?>" y2="<?= $H - $pad ?>" stroke="#CBD4CB"/>
    <?php foreach ($rows as $i => $m):
        $x = $pad + $i * $groupW;
        $ih = $m['income'] / $max * $plotH;
        $eh = $m['expenses'] / $max * $plotH;
    ?>
    <rect x="<?= $x + $groupW * 0.12 ?>" y="<?= $H - $pad - $ih ?>" width="<?= $barW ?>" height="<?= $ih ?>" fill="#2E7D32" rx="2"><title>Income <?= e(date('M Y', strtotime($m['month'] . '-01'))) ?>: <?= e(money($m['income'])) ?></title></rect>
    <rect x="<?= $x + $groupW * 0.52 ?>" y="<?= $H - $pad - $eh ?>" width="<?= $barW ?>" height="<?= $eh ?>" fill="#F9A825" rx="2"><title>Expenses <?= e(date('M Y', strtotime($m['month'] . '-01'))) ?>: <?= e(money($m['expenses'])) ?></title></rect>
    <text x="<?= $x + $groupW / 2 ?>" y="<?= $H - $pad + 16 ?>" text-anchor="middle" font-size="10" fill="#5F6B5F"><?= e(date('M', strtotime($m['month'] . '-01'))) ?></text>
    <?php endforeach; ?>
    <rect x="<?= $pad ?>" y="8" width="10" height="10" fill="#2E7D32"/><text x="<?= $pad + 14 ?>" y="17" font-size="11" fill="#1B1B1B">Money in (sales)</text>
    <rect x="<?= $pad + 130 ?>" y="8" width="10" height="10" fill="#F9A825"/><text x="<?= $pad + 144 ?>" y="17" font-size="11" fill="#1B1B1B">Money out (costs)</text>
  </svg>
</div>
<div class="card table-wrap">
  <table class="report">
    <thead><tr><th>Month</th><th class="num">Money In</th><th class="num">Money Out</th><th class="num">Difference</th></tr></thead>
    <tbody>
    <?php foreach (array_reverse($rows) as $m): $diff = $m['income'] - $m['expenses']; ?>
      <tr>
        <td><?= e(date('M Y', strtotime($m['month'] . '-01'))) ?></td>
        <td class="num"><?= e(money($m['income'])) ?></td>
        <td class="num"><?= e(money($m['expenses'])) ?></td>
        <td class="num <?= $diff < 0 ? 'danger-text' : '' ?>"><?= e(money($diff)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php else: $rows = report_losses(); ?>
<div class="card table-wrap">
  <table class="report">
    <thead><tr><th>Plant</th><th>Reason</th><th class="num">Lost</th><th class="num">Times</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['species_name']) ?></td>
          <td><?= e($reasonLabels[$r['loss_reason']] ?? (string)$r['loss_reason']) ?></td>
          <td class="num"><?= number_format($r['qty']) ?></td>
          <td class="num"><?= (int)$r['times'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if (!$rows): ?><div class="card empty-state">No losses recorded. Excellent!</div><?php endif; ?>
<p class="hint">If the same reason keeps appearing for one plant, that is the problem to fix first.</p>
<?php endif; ?>

<script>
// Tap-to-sort for the profit table
document.querySelectorAll('#sortable th').forEach((th, col) => {
  th.style.cursor = 'pointer';
  th.addEventListener('click', () => {
    const tbody = th.closest('table').querySelector('tbody');
    const rows = [...tbody.rows];
    const dir = th.dataset.dir === 'asc' ? -1 : 1;
    th.dataset.dir = dir === 1 ? 'asc' : 'desc';
    rows.sort((a, b) => {
      if (th.dataset.sort === 'num') {
        return dir * (Number(b.cells[col].dataset.v || 0) - Number(a.cells[col].dataset.v || 0));
      }
      return dir * a.cells[col].textContent.localeCompare(b.cells[col].textContent);
    });
    rows.forEach(r => tbody.appendChild(r));
  });
});
</script>
<?php page_bottom('reports'); ?>
