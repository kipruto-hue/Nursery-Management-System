<?php
require_once __DIR__ . '/../includes/layout.php';
require_owner_page();

$pdo = db();
$speciesList = $pdo->query('SELECT * FROM species ORDER BY active DESC, name')->fetchAll();
$workers = $pdo->query(
    "SELECT id, name, phone, active, last_login_at FROM users WHERE role = 'worker' ORDER BY active DESC, name"
)->fetchAll();

$catLabels = [
    'fruit' => 'Fruit', 'indigenous' => 'Indigenous', 'ornamental' => 'Ornamental',
    'vegetable' => 'Vegetable', 'other' => 'Other',
];

page_top('Settings');
?>

<div class="section-title">Nursery</div>
<div class="card">
  <form id="name-form">
    <div class="field">
      <label for="nursery-name">Nursery name (shown at the top of every page)</label>
      <input type="text" id="nursery-name" value="<?= e(setting('nursery_name')) ?>" required>
    </div>
    <button type="submit" class="btn small" id="name-save">Save Name</button>
  </form>
</div>

<div class="section-title">Plants & prices</div>
<div class="list mb-3">
<?php foreach ($speciesList as $s): ?>
  <div class="list-item sp-item"
       data-id="<?= (int)$s['id'] ?>" data-name="<?= e($s['name']) ?>"
       data-category="<?= e($s['category']) ?>" data-price="<?= e((string)$s['default_sale_price']) ?>"
       data-lead="<?= e((string)($s['lead_time_days'] ?? '')) ?>" data-active="<?= (int)$s['active'] ?>">
    <div>
      <div class="main-line"><?= e($s['name']) ?> <?= $s['active'] ? '' : '<span class="badge">Hidden</span>' ?></div>
      <div class="sub-line"><?= e($catLabels[$s['category']] ?? '') ?> · code <?= e($s['code_prefix']) ?></div>
    </div>
    <div class="right"><div class="big"><?= e(money($s['default_sale_price'])) ?></div><div class="small">each</div></div>
  </div>
<?php endforeach; ?>
</div>
<button class="btn secondary small mb-3" id="sp-new">＋ Add a Plant</button>

<div class="section-title">Workers</div>
<div class="list mb-3">
<?php foreach ($workers as $w): ?>
  <div class="list-item">
    <div>
      <div class="main-line"><?= e($w['name']) ?> <?= $w['active'] ? '' : '<span class="badge">Deactivated</span>' ?></div>
      <div class="sub-line"><?= e($w['phone']) ?> · last login
        <?= $w['last_login_at'] ? e(date('j M, g:ia', strtotime($w['last_login_at']))) : 'never' ?></div>
    </div>
    <div class="right">
      <a href="#" class="w-reset" data-id="<?= (int)$w['id'] ?>" style="font-size:14px">Reset password</a><br>
      <a href="#" class="w-toggle <?= $w['active'] ? 'danger-text' : '' ?>" data-id="<?= (int)$w['id'] ?>" data-active="<?= (int)$w['active'] ?>" style="font-size:14px">
        <?= $w['active'] ? 'Deactivate' : 'Reactivate' ?></a>
    </div>
  </div>
<?php endforeach; ?>
</div>
<button class="btn secondary small mb-3" id="w-new">＋ Add a Worker</button>

<div class="section-title">Records</div>
<a class="list-item mb-3" href="/app/activity.php">
  <div>
    <div class="main-line">Activity Log</div>
    <div class="sub-line">Everything recorded: who, what, when</div>
  </div>
  <div class="right muted">›</div>
</a>

<dialog class="sheet" id="sp-dlg">
  <h3 id="sp-title">Add a Plant</h3>
  <form id="sp-form">
    <input type="hidden" id="sp-id">
    <div class="field">
      <label for="sp-name">Plant name</label>
      <input type="text" id="sp-name" required>
    </div>
    <div class="field">
      <label for="sp-category">Type</label>
      <select id="sp-category">
        <?php foreach ($catLabels as $val => $label): ?>
        <option value="<?= e($val) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sp-price">Normal sale price (KES each)</label>
      <input type="number" id="sp-price" min="0" step="0.5" inputmode="decimal" required>
    </div>
    <div class="field">
      <label for="sp-lead">Days from sowing until ready (optional)</label>
      <input type="number" id="sp-lead" min="0" inputmode="numeric">
    </div>
    <div class="field" id="sp-prefix-field">
      <label for="sp-prefix">Short code for batch numbers (2-5 letters)</label>
      <input type="text" id="sp-prefix" maxlength="5" autocapitalize="characters" placeholder="e.g. AVO">
    </div>
    <div class="field" id="sp-active-field" hidden>
      <label for="sp-active">Show in lists?</label>
      <select id="sp-active"><option value="1">Yes</option><option value="0">No (hide it)</option></select>
    </div>
    <div class="btn-row">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn" id="sp-save">Save</button>
    </div>
  </form>
</dialog>

<dialog class="sheet" id="w-dlg">
  <h3>Add a Worker</h3>
  <form id="w-form">
    <div class="field">
      <label for="w-name">Worker's name</label>
      <input type="text" id="w-name" required>
    </div>
    <div class="field">
      <label for="w-phone">Phone number (they log in with this)</label>
      <input type="tel" id="w-phone" inputmode="tel" placeholder="07XX XXX XXX" required>
    </div>
    <div class="btn-row">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn" id="w-save">Create Account</button>
    </div>
  </form>
</dialog>

<dialog class="sheet" id="temp-dlg">
  <h3>Temporary password</h3>
  <p>Give this password to the worker. It is shown <strong>only once</strong>.</p>
  <p class="receipt center" id="temp-pw" style="font-size:22px;letter-spacing:2px"></p>
  <button class="btn" id="temp-ok">Done, I have shared it</button>
</dialog>

<script src="/assets/js/settings.js" defer></script>
<?php page_bottom('more'); ?>
