<?php
require_once __DIR__ . '/../includes/layout.php';
require_login_page();

$pdo = db();
$batches = $pdo->query(
    "SELECT b.id, b.batch_code, b.stage, b.current_qty, b.sown_date, b.expected_ready_date,
            s.name AS species_name
     FROM batches b JOIN species s ON s.id = b.species_id
     WHERE b.deleted_at IS NULL
     ORDER BY FIELD(b.stage,'ready','hardening','potted','germinating','sown','sold_out','written_off'),
              b.sown_date DESC"
)->fetchAll();

$speciesList = $pdo->query(
    'SELECT id, name, lead_time_days FROM species WHERE active = 1 ORDER BY name'
)->fetchAll();

$stageLabels = [
    'sown' => 'Sown', 'germinating' => 'Germinating', 'potted' => 'Potted',
    'hardening' => 'Hardening', 'ready' => 'Ready for Sale',
    'sold_out' => 'Sold Out', 'written_off' => 'Written Off',
];

page_top('Batches');
?>

<?php if (!$batches): ?>
<div class="card empty-state"><?= icon('sprout', 44) ?><br>No batches yet. Tap ＋ to record your first sowing.</div>
<?php else: ?>
<?php
$grouped = [];
foreach ($batches as $b) $grouped[$b['stage']][] = $b;
?>
<?php foreach ($stageLabels as $stage => $label): if (empty($grouped[$stage])) continue; ?>
<div class="section-title"><?= e($label) ?></div>
<div class="list mb-3">
<?php foreach ($grouped[$stage] as $b): ?>
  <a class="list-item" href="/app/batch?id=<?= (int)$b['id'] ?>">
    <div>
      <div class="main-line"><?= e($b['species_name']) ?></div>
      <div class="sub-line"><?= e($b['batch_code']) ?> · sown <?= e(date('j M Y', strtotime($b['sown_date']))) ?></div>
    </div>
    <div class="right">
      <div class="big"><?= number_format($b['current_qty']) ?></div>
      <div class="small">seedlings</div>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<a href="#" class="fab" id="fab-new" aria-label="New batch">＋</a>

<dialog class="sheet" id="new-batch">
  <h3>New Batch (Sowing)</h3>
  <form id="new-batch-form">
    <div class="field">
      <label for="nb-species">Plant type</label>
      <select id="nb-species" required>
        <option value="">Choose…</option>
        <?php foreach ($speciesList as $s): ?>
        <option value="<?= (int)$s['id'] ?>" data-lead="<?= (int)($s['lead_time_days'] ?? 0) ?>"><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="nb-date">Date sown</label>
      <input type="date" id="nb-date" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="field">
      <label for="nb-qty">How many seedlings?</label>
      <div class="stepper" data-stepper>
        <button data-delta="-">−</button>
        <input type="number" id="nb-qty" min="1" step="50" value="100" inputmode="numeric" required>
        <button data-delta="+">＋</button>
      </div>
    </div>
    <div class="field">
      <label for="nb-ready">Expected ready date</label>
      <input type="date" id="nb-ready">
      <p class="hint" id="nb-ready-hint" hidden></p>
    </div>
    <div class="field">
      <label for="nb-notes">Notes (optional)</label>
      <textarea id="nb-notes" rows="2"></textarea>
    </div>
    <div class="btn-row">
      <button type="button" class="btn secondary" id="nb-cancel">Cancel</button>
      <button type="submit" class="btn" id="nb-save">Save Batch</button>
    </div>
  </form>
</dialog>

<script src="/assets/js/batches.js" defer></script>
<?php page_bottom('batches'); ?>
