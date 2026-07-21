<?php
require_once __DIR__ . '/../includes/layout.php';
require_owner_page();

$pdo = db();
$rows = $pdo->query(
    "SELECT l.*, b.batch_code
     FROM labour l LEFT JOIN batches b ON b.id = l.batch_id
     ORDER BY l.labour_date DESC, l.id DESC LIMIT 200"
)->fetchAll();

$batches = $pdo->query(
    "SELECT b.id, b.batch_code, s.name AS species_name
     FROM batches b JOIN species s ON s.id = b.species_id
     WHERE b.deleted_at IS NULL AND b.stage NOT IN ('sold_out','written_off')
     ORDER BY b.sown_date DESC"
)->fetchAll();

$monthTotal = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM labour
     WHERE YEAR(labour_date) = YEAR(CURDATE()) AND MONTH(labour_date) = MONTH(CURDATE())"
)->fetchColumn();

page_top('Labour Payments');
?>

<div class="stat-card mb-3">
  <div class="label">Paid for labour this month</div>
  <div class="value"><?= e(money($monthTotal)) ?></div>
</div>

<?php if (!$rows): ?>
<div class="card empty-state">No labour payments yet. Tap ＋ when you pay a casual worker.</div>
<?php else: ?>
<div class="list">
<?php foreach ($rows as $l): ?>
  <div class="list-item lb-item"
       data-id="<?= (int)$l['id'] ?>" data-worker="<?= e($l['worker_name']) ?>"
       data-task="<?= e($l['task']) ?>" data-amount="<?= e((string)$l['amount']) ?>"
       data-date="<?= e($l['labour_date']) ?>" data-batch="<?= (int)($l['batch_id'] ?? 0) ?>">
    <div>
      <div class="main-line"><?= e($l['worker_name']) ?></div>
      <div class="sub-line">
        <?= e($l['task']) ?> · <?= e(date('j M Y', strtotime($l['labour_date']))) ?>
        <?= $l['batch_code'] ? ' · ' . e($l['batch_code']) : '' ?>
      </div>
    </div>
    <div class="right"><div class="big"><?= e(money($l['amount'])) ?></div></div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<a href="#" class="fab" id="fab-new" aria-label="New labour payment">＋</a>

<dialog class="sheet" id="lb-dlg">
  <h3 id="lb-title">New Labour Payment</h3>
  <form id="lb-form">
    <input type="hidden" id="lb-id">
    <div class="field">
      <label for="lb-worker">Who did the work?</label>
      <input type="text" id="lb-worker" required placeholder="e.g. Kimutai">
    </div>
    <div class="field">
      <label for="lb-task">What work was done?</label>
      <input type="text" id="lb-task" required placeholder="e.g. Potting seedlings">
    </div>
    <div class="field">
      <label for="lb-amount">Amount paid (KES)</label>
      <input type="number" id="lb-amount" min="1" step="0.5" inputmode="decimal" required>
    </div>
    <div class="field">
      <label for="lb-date">Date</label>
      <input type="date" id="lb-date" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="field">
      <label for="lb-batch">For a specific batch? (optional)</label>
      <select id="lb-batch">
        <option value="">Whole nursery (general)</option>
        <?php foreach ($batches as $b): ?>
        <option value="<?= (int)$b['id'] ?>"><?= e($b['species_name'] . ' · ' . $b['batch_code']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="btn-row">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn" id="lb-save">Save</button>
    </div>
    <button type="button" class="btn danger mt-3" id="lb-delete" hidden>Delete This Payment</button>
  </form>
</dialog>

<script src="/assets/js/labour.js" defer></script>
<?php page_bottom('more'); ?>
