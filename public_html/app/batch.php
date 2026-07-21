<?php
require_once __DIR__ . '/../includes/layout.php';
require_login_page();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT b.*, s.name AS species_name, s.default_sale_price
     FROM batches b JOIN species s ON s.id = b.species_id
     WHERE b.id = ? AND b.deleted_at IS NULL"
);
$stmt->execute([$id]);
$batch = $stmt->fetch();
if (!$batch) {
    header('Location: /app/batches.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT ev.*, u.name AS user_name
     FROM batch_events ev LEFT JOIN users u ON u.id = ev.created_by
     WHERE ev.batch_id = ?
     ORDER BY ev.event_date DESC, ev.id DESC"
);
$stmt->execute([$id]);
$events = $stmt->fetchAll();

if (is_owner()) {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE batch_id = ?');
    $stmt->execute([$id]);
    $directExpenses = (float)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM labour WHERE batch_id = ?');
    $stmt->execute([$id]);
    $directLabour = (float)$stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(line_total),0), COALESCE(SUM(qty),0) FROM sale_items WHERE batch_id = ?');
    $stmt->execute([$id]);
    [$revenue, $soldQty] = $stmt->fetch(PDO::FETCH_NUM);
}

$stageLabels = [
    'sown' => 'Sown', 'germinating' => 'Germinating', 'potted' => 'Potted',
    'hardening' => 'Hardening', 'ready' => 'Ready for Sale',
    'sold_out' => 'Sold Out', 'written_off' => 'Written Off',
];
$stageOrder = ['sown', 'germinating', 'potted', 'hardening', 'ready'];
$nextStage = null;
$idx = array_search($batch['stage'], $stageOrder, true);
if ($idx !== false && $idx < count($stageOrder) - 1) {
    $nextStage = $stageOrder[$idx + 1];
}

$eventLabels = [
    'watering' => 'Watering', 'potting' => 'Potting', 'loss' => 'Seedlings lost',
    'stage_change' => 'Stage changed', 'input_usage' => 'Input applied', 'note' => 'Note',
];
$reasonLabels = [
    'dried' => 'Dried up', 'disease' => 'Disease', 'pests' => 'Pests',
    'damage' => 'Damage', 'other' => 'Other',
];
$salePrice = $batch['sale_price_override'] ?? $batch['default_sale_price'];

page_top($batch['batch_code']);
?>

<div class="card">
  <h2><?= e($batch['species_name']) ?></h2>
  <p><span class="badge stage-<?= e($batch['stage']) ?>"><?= e($stageLabels[$batch['stage']]) ?></span></p>
  <p class="mt-2" style="font-size:26px;font-weight:800"><?= number_format($batch['current_qty']) ?>
    <span style="font-size:14px;font-weight:600" class="muted">of <?= number_format($batch['initial_qty']) ?> seedlings left</span></p>
  <p class="muted mt-2" style="font-size:14px">
    Sown <?= e(date('j M Y', strtotime($batch['sown_date']))) ?>
    <?php if ($batch['expected_ready_date']): ?> · Ready around <?= e(date('j M Y', strtotime($batch['expected_ready_date']))) ?><?php endif; ?>
    · Sale price <?= e(money($salePrice)) ?> each
  </p>
  <?php if ($batch['notes']): ?><p class="muted mt-2" style="font-size:14px"><?= e($batch['notes']) ?></p><?php endif; ?>
</div>

<?php if (is_owner()): ?>
<div class="stat-grid">
  <div class="stat-card">
    <div class="label">Money spent on this batch</div>
    <div class="value"><?= e(money($directExpenses + $directLabour)) ?></div>
    <div class="label mt-2">Inputs <?= e(money($directExpenses)) ?> · Labour <?= e(money($directLabour)) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Money earned (<?= number_format($soldQty) ?> sold)</div>
    <div class="value success"><?= e(money($revenue)) ?></div>
  </div>
</div>
<?php endif; ?>

<?php if ($nextStage): ?>
<button class="btn mb-3" id="advance-btn" data-stage="<?= e($nextStage) ?>">
  Mark as <?= e($stageLabels[$nextStage]) ?>
</button>
<?php endif; ?>

<div class="btn-row mb-3">
  <button class="btn danger" id="loss-btn">Seedlings Lost</button>
  <button class="btn secondary" id="activity-btn">Record Activity</button>
</div>

<?php if (is_owner()): ?>
<div class="btn-row mb-3">
  <button class="btn secondary" id="edit-btn">Edit Batch</button>
  <?php if ($batch['stage'] !== 'written_off'): ?>
  <button class="btn secondary" id="writeoff-btn">Write Off</button>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="section-title">What has happened</div>
<?php if (!$events): ?>
<div class="card empty-state">Nothing recorded yet for this batch.</div>
<?php else: ?>
<div class="card">
  <ul class="timeline">
  <?php foreach ($events as $ev): ?>
    <li class="<?= $ev['event_type'] === 'loss' ? 'loss' : '' ?>">
      <strong><?= e($eventLabels[$ev['event_type']] ?? $ev['event_type']) ?></strong>
      <?php if ($ev['event_type'] === 'loss'): ?>
        — <?= number_format($ev['qty']) ?> (<?= e($reasonLabels[$ev['loss_reason']] ?? '') ?>)
      <?php elseif ($ev['event_type'] === 'stage_change'): ?>
        — now <?= e($stageLabels[$ev['new_stage']] ?? $ev['new_stage']) ?>
      <?php elseif ($ev['qty']): ?>
        — <?= number_format($ev['qty']) ?>
      <?php endif; ?>
      <?php if ($ev['notes']): ?><div style="font-size:13px"><?= e($ev['notes']) ?></div><?php endif; ?>
      <div class="when">
        <?= e(date('j M Y', strtotime($ev['event_date']))) ?> · <?= e($ev['user_name'] ?? '') ?>
        <?php if (is_owner()): ?>
        · <a href="#" class="danger-text ev-delete" data-id="<?= (int)$ev['id'] ?>">remove</a>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<dialog class="sheet" id="loss-dlg">
  <h3>Seedlings Lost</h3>
  <form id="loss-form">
    <div class="field">
      <label for="loss-qty">How many were lost?</label>
      <div class="stepper" data-stepper>
        <button data-delta="-">−</button>
        <input type="number" id="loss-qty" min="1" step="1" value="1" inputmode="numeric" required>
        <button data-delta="+">＋</button>
      </div>
      <p class="hint"><?= number_format($batch['current_qty']) ?> seedlings in this batch now</p>
    </div>
    <div class="field">
      <label for="loss-reason">Why were they lost?</label>
      <select id="loss-reason" required>
        <option value="">Choose…</option>
        <option value="dried">Dried up</option>
        <option value="disease">Disease</option>
        <option value="pests">Pests</option>
        <option value="damage">Damage</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div class="field">
      <label for="loss-date">Date</label>
      <input type="date" id="loss-date" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="field">
      <label for="loss-notes">Notes (optional)</label>
      <textarea id="loss-notes" rows="2"></textarea>
    </div>
    <div class="btn-row">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn danger" id="loss-save">Save</button>
    </div>
  </form>
</dialog>

<dialog class="sheet" id="activity-dlg">
  <h3>Record Activity</h3>
  <form id="activity-form">
    <div class="field">
      <label for="act-type">What was done?</label>
      <select id="act-type" required>
        <option value="watering">Watering</option>
        <option value="potting">Potting</option>
        <option value="input_usage">Applied fertilizer / chemical</option>
        <option value="note">Other note</option>
      </select>
    </div>
    <div class="field" id="act-qty-field" hidden>
      <label for="act-qty">How many were potted? (optional)</label>
      <input type="number" id="act-qty" min="1" inputmode="numeric">
    </div>
    <div class="field">
      <label for="act-date">Date</label>
      <input type="date" id="act-date" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="field">
      <label for="act-notes">Notes (e.g. "Applied DAP fertilizer")</label>
      <textarea id="act-notes" rows="2"></textarea>
    </div>
    <div class="btn-row">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn" id="act-save">Save</button>
    </div>
  </form>
</dialog>

<?php if (is_owner()): ?>
<dialog class="sheet" id="edit-dlg">
  <h3>Edit Batch</h3>
  <form id="edit-form">
    <div class="field">
      <label for="edit-ready">Expected ready date</label>
      <input type="date" id="edit-ready" value="<?= e($batch['expected_ready_date'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="edit-price">Sale price for this batch (KES each)</label>
      <input type="number" id="edit-price" min="0" step="0.5" value="<?= e($batch['sale_price_override'] ?? '') ?>" inputmode="decimal">
      <p class="hint">Leave empty to use the normal price of <?= e(money($batch['default_sale_price'])) ?></p>
    </div>
    <div class="field">
      <label for="edit-notes">Notes</label>
      <textarea id="edit-notes" rows="2"><?= e($batch['notes'] ?? '') ?></textarea>
    </div>
    <div class="btn-row">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn" id="edit-save">Save</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<script>
const BATCH = { id: <?= (int)$batch['id'] ?>, currentQty: <?= (int)$batch['current_qty'] ?> };
</script>
<script src="/assets/js/batch.js" defer></script>
<?php page_bottom('batches'); ?>
