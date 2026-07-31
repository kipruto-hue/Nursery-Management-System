<?php
require_once __DIR__ . '/../includes/layout.php';
require_owner_page();

$pdo = db();
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 50;
$rows = $pdo->prepare(
    'SELECT a.*, u.name AS user_name
     FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.id DESC LIMIT ? OFFSET ?'
);
$rows->bindValue(1, $per, PDO::PARAM_INT);
$rows->bindValue(2, ($page - 1) * $per, PDO::PARAM_INT);
$rows->execute();
$rows = $rows->fetchAll();

$actionLabels = [
    'create' => 'added', 'update' => 'changed', 'delete' => 'removed',
    'login' => 'logged in', 'logout' => 'logged out', 'change_password' => 'changed a password',
];
$entityLabels = [
    'batches' => 'batch', 'batch_events' => 'batch activity', 'sales' => 'sale',
    'payments' => 'payment', 'expenses' => 'expense', 'labour' => 'labour payment',
    'customers' => 'customer', 'species' => 'plant', 'users' => 'account',
    'preorders' => 'pre-order', 'settings' => 'settings',
];

page_top('Activity Log');
?>

<?php if (!$rows): ?>
<div class="card empty-state">Nothing recorded yet.</div>
<?php else: ?>
<div class="card">
  <ul class="timeline">
  <?php foreach ($rows as $a): ?>
    <li>
      <strong><?= e($a['user_name'] ?? 'Someone') ?></strong>
      <?= e($actionLabels[$a['action']] ?? $a['action']) ?>
      <?= e($entityLabels[$a['entity']] ?? $a['entity']) ?><?= $a['entity_id'] ? ' #' . (int)$a['entity_id'] : '' ?>
      <?php if ($a['before_json'] || $a['after_json']): ?>
      <div class="muted" style="font-size:12px;word-break:break-all">
        <?php if ($a['before_json']): ?>Before: <?= e($a['before_json']) ?><br><?php endif; ?>
        <?php if ($a['after_json']): ?>After: <?= e($a['after_json']) ?><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="when"><?= e(date('D j M Y, g:ia', strtotime($a['created_at']))) ?></div>
    </li>
  <?php endforeach; ?>
  </ul>
</div>
<div class="btn-row">
  <?php if ($page > 1): ?><a class="btn secondary small" href="?page=<?= $page - 1 ?>">‹ Newer</a><?php endif; ?>
  <?php if (count($rows) === $per): ?><a class="btn secondary small" href="?page=<?= $page + 1 ?>">Older ›</a><?php endif; ?>
</div>
<?php endif; ?>
<?php page_bottom('more'); ?>
