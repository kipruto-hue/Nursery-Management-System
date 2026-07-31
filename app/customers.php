<?php
require_once __DIR__ . '/../includes/layout.php';
require_login_page();

$pdo = db();
$customers = $pdo->query(
    "SELECT c.id, c.name, c.phone, c.notes,
            COALESCE(SUM(CASE WHEN sa.status IN ('partial','credit')
                              THEN sa.total_amount - sa.amount_paid ELSE 0 END), 0) AS balance
     FROM customers c
     LEFT JOIN sales sa ON sa.customer_id = c.id
     WHERE c.deleted_at IS NULL
     GROUP BY c.id, c.name, c.phone, c.notes
     ORDER BY c.name"
)->fetchAll();

page_top('Customers');
?>

<?php if (!$customers): ?>
<div class="card empty-state">No customers yet. Tap ＋ to add one.</div>
<?php else: ?>
<div class="list">
<?php foreach ($customers as $c): ?>
  <div class="list-item" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>"
       data-phone="<?= e($c['phone'] ?? '') ?>" data-notes="<?= e($c['notes'] ?? '') ?>">
    <div>
      <div class="main-line"><?= e($c['name']) ?></div>
      <div class="sub-line"><?= e($c['phone'] ?? 'No phone') ?></div>
    </div>
    <div class="right">
      <?php if (is_owner() && $c['balance'] > 0): ?>
      <div class="big danger-text"><?= e(money($c['balance'])) ?></div>
      <div class="small">owed</div>
      <?php endif; ?>
      <?php if (is_owner()): ?>
      <a href="#" class="cust-edit" style="font-size:14px">Edit</a>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<a href="#" class="fab" id="fab-new" aria-label="New customer">＋</a>

<dialog class="sheet" id="cust-dlg">
  <h3 id="cust-title">New Customer</h3>
  <form id="cust-form">
    <input type="hidden" id="cust-id">
    <div class="field">
      <label for="cust-name">Name</label>
      <input type="text" id="cust-name" required>
    </div>
    <div class="field">
      <label for="cust-phone">Phone</label>
      <input type="tel" id="cust-phone" inputmode="tel" placeholder="07XX XXX XXX">
    </div>
    <div class="field">
      <label for="cust-notes">Notes (optional)</label>
      <textarea id="cust-notes" rows="2"></textarea>
    </div>
    <div class="btn-row">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn" id="cust-save">Save</button>
    </div>
    <?php if (is_owner()): ?>
    <button type="button" class="btn danger mt-3" id="cust-delete" hidden>Remove Customer</button>
    <?php endif; ?>
  </form>
</dialog>

<script src="/assets/js/customers.js" defer></script>
<?php page_bottom('more'); ?>
