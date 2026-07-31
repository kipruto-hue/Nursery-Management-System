<?php
require_once __DIR__ . '/../includes/layout.php';
require_login_page();

$pdo = db();
$preorders = $pdo->query(
    "SELECT p.*, c.name AS customer_name, s.name AS species_name
     FROM preorders p
     JOIN customers c ON c.id = p.customer_id
     JOIN species s ON s.id = p.species_id
     ORDER BY FIELD(p.status,'open','fulfilled','cancelled'), p.expected_date"
)->fetchAll();

$customers = $pdo->query(
    'SELECT id, name FROM customers WHERE deleted_at IS NULL ORDER BY name'
)->fetchAll();
$speciesList = $pdo->query(
    'SELECT id, name, default_sale_price FROM species WHERE active = 1 ORDER BY name'
)->fetchAll();

$statusLabels = ['open' => 'Open', 'fulfilled' => 'Fulfilled', 'cancelled' => 'Cancelled'];

page_top('Pre-orders');
?>

<?php if (!$preorders): ?>
<div class="card empty-state">No pre-orders yet. Tap ＋ when a customer books seedlings in advance.</div>
<?php else: ?>
<div class="list">
<?php foreach ($preorders as $p): ?>
  <div class="list-item">
    <div>
      <div class="main-line"><?= e($p['customer_name']) ?></div>
      <div class="sub-line">
        <?= number_format($p['qty']) ?> × <?= e($p['species_name']) ?>
        @ <?= e(money($p['agreed_unit_price'])) ?>
        <?php if ((float)$p['deposit_paid'] > 0): ?> · Deposit <?= e(money($p['deposit_paid'])) ?><?php endif; ?>
        <?php if ($p['expected_date']): ?> · Needed by <?= e(date('j M', strtotime($p['expected_date']))) ?><?php endif; ?>
      </div>
    </div>
    <div class="right">
      <span class="badge <?= $p['status'] === 'open' ? 'stage-ready' : '' ?>"><?= e($statusLabels[$p['status']]) ?></span>
      <?php if ($p['status'] === 'open'): ?>
      <div class="mt-2"><a class="btn small" href="/app/sale-new?preorder=<?= (int)$p['id'] ?>">Fulfil</a></div>
      <?php if (is_owner()): ?>
      <a href="#" class="po-cancel danger-text" data-id="<?= (int)$p['id'] ?>" style="font-size:13px">Cancel</a>
      <?php endif; ?>
      <?php elseif ($p['status'] === 'fulfilled' && $p['fulfilled_sale_id']): ?>
      <div class="small mt-2"><a href="/app/sale?id=<?= (int)$p['fulfilled_sale_id'] ?>">View sale</a></div>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<a href="#" class="fab" id="fab-new" aria-label="New pre-order">＋</a>

<dialog class="sheet" id="po-dlg">
  <h3>New Pre-order</h3>
  <form id="po-form">
    <div class="field">
      <label for="po-customer">Customer</label>
      <select id="po-customer" required>
        <option value="">Choose…</option>
        <?php foreach ($customers as $c): ?>
        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">New customer? Add them on the Customers page first.</p>
    </div>
    <div class="field">
      <label for="po-species">Plant type</label>
      <select id="po-species" required>
        <option value="">Choose…</option>
        <?php foreach ($speciesList as $s): ?>
        <option value="<?= (int)$s['id'] ?>" data-price="<?= e((string)$s['default_sale_price']) ?>"><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="po-qty">How many?</label>
      <div class="stepper" data-stepper>
        <button data-delta="-">−</button>
        <input type="number" id="po-qty" min="1" step="10" value="50" inputmode="numeric" required>
        <button data-delta="+">＋</button>
      </div>
    </div>
    <div class="field">
      <label for="po-price">Agreed price each (KES)</label>
      <input type="number" id="po-price" min="0" step="0.5" inputmode="decimal" required>
    </div>
    <div class="field">
      <label for="po-deposit">Deposit paid now (KES)</label>
      <input type="number" id="po-deposit" min="0" step="0.5" value="0" inputmode="decimal">
    </div>
    <div class="field">
      <label for="po-date">When do they need them?</label>
      <input type="date" id="po-date">
    </div>
    <div class="btn-row">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn" id="po-save">Save</button>
    </div>
  </form>
</dialog>

<script src="/assets/js/preorders.js" defer></script>
<?php page_bottom('more'); ?>
