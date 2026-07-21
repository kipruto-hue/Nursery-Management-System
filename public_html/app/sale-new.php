<?php
require_once __DIR__ . '/../includes/layout.php';
require_login_page();

$pdo = db();

$readyBatches = $pdo->query(
    "SELECT b.id, b.batch_code, b.current_qty,
            COALESCE(b.sale_price_override, s.default_sale_price) AS price,
            s.name AS species_name, s.id AS species_id
     FROM batches b JOIN species s ON s.id = b.species_id
     WHERE b.stage = 'ready' AND b.current_qty > 0 AND b.deleted_at IS NULL
     ORDER BY s.name, b.sown_date"
)->fetchAll();

$customers = $pdo->query(
    'SELECT id, name, phone FROM customers WHERE deleted_at IS NULL ORDER BY name'
)->fetchAll();

// Fulfilling a pre-order? (§6.6)
$preorder = null;
if (!empty($_GET['preorder'])) {
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS customer_name, s.name AS species_name, s.id AS species_id
         FROM preorders p JOIN customers c ON c.id = p.customer_id
         JOIN species s ON s.id = p.species_id
         WHERE p.id = ? AND p.status = 'open'"
    );
    $stmt->execute([(int)$_GET['preorder']]);
    $preorder = $stmt->fetch();
}

page_top('Record a Sale');
?>

<?php if (!$readyBatches): ?>
<div class="card empty-state"><?= icon('sprout', 44) ?><br>
No batches are ready for sale yet.<br>Mark a batch as "Ready for Sale" first.</div>
<a class="btn secondary" href="/app/batches.php">Go to Batches</a>
<?php else: ?>

<?php if ($preorder): ?>
<div class="card" style="border-left:4px solid var(--color-accent)">
  <strong>Fulfilling pre-order:</strong> <?= number_format($preorder['qty']) ?> × <?= e($preorder['species_name']) ?>
  for <?= e($preorder['customer_name']) ?><?php if (is_owner() || true): ?>,
  deposit already paid: <?= e(money($preorder['deposit_paid'])) ?><?php endif; ?>
</div>
<?php endif; ?>

<form id="sale-form">
  <div class="card">
    <h2>Customer</h2>
    <div class="field">
      <select id="customer">
        <option value="">Walk-in customer</option>
        <?php foreach ($customers as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $preorder && (int)$preorder['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>>
          <?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
        </option>
        <?php endforeach; ?>
        <option value="__new">＋ New customer…</option>
      </select>
    </div>
    <div id="new-customer" hidden>
      <div class="field">
        <label for="nc-name">Customer name</label>
        <input type="text" id="nc-name">
      </div>
      <div class="field">
        <label for="nc-phone">Customer phone</label>
        <input type="tel" id="nc-phone" inputmode="tel" placeholder="07XX XXX XXX">
      </div>
    </div>
  </div>

  <div class="card">
    <h2>Seedlings</h2>
    <div id="lines"></div>
    <button type="button" class="btn secondary small" id="add-line">＋ Add another batch</button>
  </div>

  <div class="card">
    <h2>Payment</h2>
    <p style="font-size:22px;font-weight:800" class="mb-3">Total: <span id="total">KES 0</span></p>
    <div class="field">
      <label for="pay-method">How is the customer paying?</label>
      <select id="pay-method">
        <option value="mpesa">M-Pesa</option>
        <option value="cash">Cash</option>
        <option value="credit">Credit (pay later)</option>
        <option value="mixed">Part now, part later</option>
      </select>
    </div>
    <div class="field" id="mpesa-field">
      <label for="mpesa-ref">M-Pesa code</label>
      <input type="text" id="mpesa-ref" autocapitalize="characters" placeholder="e.g. SFE8XK21QT">
    </div>
    <div class="field" id="paid-field">
      <label for="amount-paid">Amount paid now (KES)</label>
      <input type="number" id="amount-paid" min="0" step="0.5" inputmode="decimal">
    </div>
    <div class="field">
      <label for="sale-date">Date of sale</label>
      <input type="date" id="sale-date" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="field">
      <label for="sale-notes">Notes (optional)</label>
      <textarea id="sale-notes" rows="2"></textarea>
    </div>
  </div>

  <button type="submit" class="btn" id="sale-save">Save Sale</button>
</form>

<script>
const BATCHES = <?= json_encode(array_map(fn($b) => [
    'id' => (int)$b['id'],
    'label' => $b['species_name'] . ' · ' . $b['batch_code'],
    'qty' => (int)$b['current_qty'],
    'price' => (float)$b['price'],
    'species_id' => (int)$b['species_id'],
], $readyBatches)) ?>;
const PREORDER = <?= $preorder ? json_encode([
    'id' => (int)$preorder['id'],
    'species_id' => (int)$preorder['species_id'],
    'qty' => (int)$preorder['qty'],
    'price' => (float)$preorder['agreed_unit_price'],
    'deposit' => (float)$preorder['deposit_paid'],
]) : 'null' ?>;
</script>
<script src="/assets/js/sale-new.js" defer></script>
<?php endif; ?>
<?php page_bottom('sales'); ?>
