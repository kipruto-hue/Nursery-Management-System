<?php
require_once __DIR__ . '/../includes/layout.php';
require_login_page();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT sa.*, c.name AS customer_name, c.phone AS customer_phone, u.name AS seller_name
     FROM sales sa
     LEFT JOIN customers c ON c.id = sa.customer_id
     LEFT JOIN users u ON u.id = sa.created_by
     WHERE sa.id = ?'
);
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) {
    header('Location: /app/sales.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT si.qty, si.unit_price, si.line_total, b.batch_code, s.name AS species_name
     FROM sale_items si
     JOIN batches b ON b.id = si.batch_id
     JOIN species s ON s.id = b.species_id
     WHERE si.sale_id = ?'
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM payments WHERE sale_id = ? ORDER BY paid_at');
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

$balance = round((float)$sale['total_amount'] - (float)$sale['amount_paid'], 2);
$statusLabels = ['paid' => 'Paid', 'partial' => 'Part paid', 'credit' => 'Credit'];
$isNew = !empty($_GET['new']);

// Plain-text receipt, formatted for WhatsApp paste (§6.5)
$r = [];
$r[] = strtoupper(setting('nursery_name', 'Nursery'));
$r[] = 'Receipt #' . $sale['id'] . ' — ' . date('j M Y', strtotime($sale['sale_date']));
$r[] = 'Customer: ' . ($sale['customer_name'] ?? 'Walk-in');
$r[] = str_repeat('-', 28);
foreach ($items as $it) {
    $r[] = $it['species_name'] . ' x' . $it['qty'] . ' @ ' . number_format($it['unit_price']);
    $r[] = '  = ' . money($it['line_total']);
}
$r[] = str_repeat('-', 28);
$r[] = 'TOTAL: ' . money($sale['total_amount']);
$r[] = 'Paid:  ' . money($sale['amount_paid']);
if ($balance > 0) $r[] = 'Balance owed: ' . money($balance);
if ($sale['mpesa_ref']) $r[] = 'M-Pesa: ' . $sale['mpesa_ref'];
$r[] = 'Served by ' . ($sale['seller_name'] ?? '');
$r[] = 'Asante sana!';
$receipt = implode("\n", $r);

page_top('Sale #' . $sale['id']);
?>

<?php if ($isNew): ?>
<div class="card" style="border-left:4px solid var(--color-success)">
  <strong>Sale saved!</strong> You can share the receipt below on WhatsApp.
</div>
<?php endif; ?>

<div class="card">
  <p><span class="badge status-<?= e($sale['status']) ?>"><?= e($statusLabels[$sale['status']]) ?></span></p>
  <p class="mt-2" style="font-size:26px;font-weight:800"><?= e(money($sale['total_amount'])) ?></p>
  <p class="muted" style="font-size:14px">
    <?= e($sale['customer_name'] ?? 'Walk-in customer') ?> ·
    <?= e(date('j M Y', strtotime($sale['sale_date']))) ?> ·
    by <?= e($sale['seller_name'] ?? '') ?>
  </p>
  <?php if ($balance > 0): ?>
  <p class="danger-text mt-2" style="font-weight:700">Still owed: <?= e(money($balance)) ?></p>
  <?php endif; ?>
</div>

<div class="receipt" id="receipt-text"><?= e($receipt) ?></div>
<div class="btn-row mb-3">
  <button class="btn secondary" id="copy-receipt">Copy Receipt</button>
  <a class="btn" id="wa-share" href="https://wa.me/<?= e(preg_replace('/^0/', '254', (string)($sale['customer_phone'] ?? ''))) ?>?text=<?= rawurlencode($receipt) ?>" target="_blank" rel="noopener">Send on WhatsApp</a>
</div>

<?php if ($balance > 0): ?>
<div class="card">
  <h2>Record a Payment</h2>
  <form id="pay-form">
    <div class="field">
      <label for="pay-amount">Amount received (KES)</label>
      <input type="number" id="pay-amount" min="0.5" max="<?= e((string)$balance) ?>" step="0.5" value="<?= e((string)$balance) ?>" inputmode="decimal" required>
    </div>
    <div class="field">
      <label for="pay-method">How did they pay?</label>
      <select id="pay-method">
        <option value="mpesa">M-Pesa</option>
        <option value="cash">Cash</option>
      </select>
    </div>
    <div class="field" id="pay-mpesa-field">
      <label for="pay-ref">M-Pesa code</label>
      <input type="text" id="pay-ref" autocapitalize="characters">
    </div>
    <button type="submit" class="btn" id="pay-save">Save Payment</button>
  </form>
</div>
<?php endif; ?>

<?php if ($payments): ?>
<div class="section-title">Payments received</div>
<div class="card">
  <ul class="timeline">
  <?php foreach ($payments as $p): ?>
    <li><?= e(money($p['amount'])) ?> — <?= $p['payment_method'] === 'mpesa' ? 'M-Pesa' : 'Cash' ?>
      <?= $p['mpesa_ref'] ? '(' . e($p['mpesa_ref']) . ')' : '' ?>
      <div class="when"><?= e(date('j M Y, g:ia', strtotime($p['paid_at']))) ?></div>
    </li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if (is_owner()): ?>
<button class="btn danger mb-3" id="delete-sale">Delete This Sale</button>
<?php endif; ?>

<script>
const SALE_ID = <?= (int)$sale['id'] ?>;
document.getElementById('copy-receipt').addEventListener('click', async () => {
  await navigator.clipboard.writeText(document.getElementById('receipt-text').textContent);
  toast('Receipt copied. Paste it anywhere.');
});
const payMethod = document.getElementById('pay-method');
payMethod?.addEventListener('change', () => {
  document.getElementById('pay-mpesa-field').hidden = payMethod.value !== 'mpesa';
});
const payBtn = document.getElementById('pay-save');
document.getElementById('pay-form')?.addEventListener('submit', busy(payBtn, async (ev) => {
  ev.preventDefault();
  await apiPost('/api/payments.php', {
    sale_id: SALE_ID,
    amount: document.getElementById('pay-amount').value,
    payment_method: payMethod.value,
    mpesa_ref: document.getElementById('pay-ref').value.trim(),
  });
  toast('Payment saved');
  setTimeout(() => location.reload(), 700);
}));
const delBtn = document.getElementById('delete-sale');
delBtn?.addEventListener('click', busy(delBtn, async () => {
  const yes = await confirmSheet('Delete this sale?',
    'The seedlings will be returned to their batches and any payments removed.', 'Yes, delete');
  if (!yes) return;
  await apiPost('/api/sales.php', { action: 'delete', id: SALE_ID });
  location.href = '/app/sales.php';
}));
</script>
<?php page_bottom('sales'); ?>
