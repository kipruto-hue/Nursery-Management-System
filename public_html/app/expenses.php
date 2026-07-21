<?php
require_once __DIR__ . '/../includes/layout.php';
require_owner_page();

$pdo = db();
$expenses = $pdo->query(
    "SELECT ex.*, b.batch_code
     FROM expenses ex LEFT JOIN batches b ON b.id = ex.batch_id
     ORDER BY ex.expense_date DESC, ex.id DESC LIMIT 200"
)->fetchAll();

$batches = $pdo->query(
    "SELECT b.id, b.batch_code, s.name AS species_name
     FROM batches b JOIN species s ON s.id = b.species_id
     WHERE b.deleted_at IS NULL AND b.stage NOT IN ('sold_out','written_off')
     ORDER BY b.sown_date DESC"
)->fetchAll();

$catLabels = [
    'seed' => 'Seeds', 'media_soil' => 'Soil / planting media', 'bags' => 'Bags & pots',
    'fertilizer' => 'Fertilizer', 'chemical' => 'Chemicals', 'water' => 'Water',
    'transport' => 'Transport', 'equipment' => 'Equipment', 'other' => 'Other',
];

$monthTotal = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM expenses
     WHERE YEAR(expense_date) = YEAR(CURDATE()) AND MONTH(expense_date) = MONTH(CURDATE())"
)->fetchColumn();

page_top('Purchases & Expenses');
?>

<div class="stat-card mb-3">
  <div class="label">Spent this month</div>
  <div class="value"><?= e(money($monthTotal)) ?></div>
</div>

<?php if (!$expenses): ?>
<div class="card empty-state">No purchases recorded yet. Tap ＋ to add the first one.</div>
<?php else: ?>
<div class="list">
<?php foreach ($expenses as $ex): ?>
  <div class="list-item ex-item"
       data-id="<?= (int)$ex['id'] ?>" data-category="<?= e($ex['category']) ?>"
       data-description="<?= e($ex['description']) ?>" data-supplier="<?= e($ex['supplier'] ?? '') ?>"
       data-amount="<?= e((string)$ex['amount']) ?>" data-date="<?= e($ex['expense_date']) ?>"
       data-batch="<?= (int)($ex['batch_id'] ?? 0) ?>">
    <div>
      <div class="main-line"><?= e($ex['description']) ?></div>
      <div class="sub-line">
        <?= e($catLabels[$ex['category']] ?? $ex['category']) ?>
        · <?= e(date('j M Y', strtotime($ex['expense_date']))) ?>
        <?= $ex['batch_code'] ? ' · ' . e($ex['batch_code']) : '' ?>
        <?= $ex['supplier'] ? ' · ' . e($ex['supplier']) : '' ?>
      </div>
    </div>
    <div class="right"><div class="big"><?= e(money($ex['amount'])) ?></div></div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<a href="#" class="fab" id="fab-new" aria-label="New expense">＋</a>

<dialog class="sheet" id="ex-dlg">
  <h3 id="ex-title">New Purchase / Expense</h3>
  <form id="ex-form">
    <input type="hidden" id="ex-id">
    <div class="field">
      <label for="ex-category">What kind of expense?</label>
      <select id="ex-category" required>
        <?php foreach ($catLabels as $val => $label): ?>
        <option value="<?= e($val) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="ex-description">What was bought?</label>
      <input type="text" id="ex-description" required placeholder="e.g. DAP fertilizer 50kg">
    </div>
    <div class="field">
      <label for="ex-amount">Amount (KES)</label>
      <input type="number" id="ex-amount" min="1" step="0.5" inputmode="decimal" required>
    </div>
    <div class="field">
      <label for="ex-date">Date</label>
      <input type="date" id="ex-date" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="field">
      <label for="ex-supplier">Bought from (optional)</label>
      <input type="text" id="ex-supplier">
    </div>
    <div class="field">
      <label for="ex-batch">For a specific batch? (optional)</label>
      <select id="ex-batch">
        <option value="">Whole nursery (general)</option>
        <?php foreach ($batches as $b): ?>
        <option value="<?= (int)$b['id'] ?>"><?= e($b['species_name'] . ' · ' . $b['batch_code']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="btn-row">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn" id="ex-save">Save</button>
    </div>
    <button type="button" class="btn danger mt-3" id="ex-delete" hidden>Delete This Expense</button>
  </form>
</dialog>

<script src="/assets/js/expenses.js" defer></script>
<?php page_bottom('more'); ?>
