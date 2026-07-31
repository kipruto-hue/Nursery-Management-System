<?php
require_once __DIR__ . '/../includes/layout.php';
require_login_page();
page_top('Change Password');
?>
<?php if (!empty($_GET['first'])): ?>
<div class="card" style="border-left:4px solid var(--color-accent)">
  <strong>Welcome!</strong> Please choose your own password before you continue.
  Your current password is the temporary one you were given.
</div>
<?php endif; ?>
<div class="card">
  <form id="pw-form">
    <div class="field">
      <label for="current">Current password</label>
      <input type="password" id="current" autocomplete="current-password" required>
    </div>
    <div class="field">
      <label for="new">New password</label>
      <input type="password" id="new" autocomplete="new-password" required>
      <p class="hint">At least 6 characters</p>
    </div>
    <div class="field">
      <label for="confirm">Repeat new password</label>
      <input type="password" id="confirm" autocomplete="new-password" required>
    </div>
    <button type="submit" class="btn" id="pw-btn">Save New Password</button>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('pw-btn');
  document.getElementById('pw-form').addEventListener('submit', busy(btn, async (ev) => {
    ev.preventDefault();
    const cur = document.getElementById('current').value;
    const nw = document.getElementById('new').value;
    if (nw !== document.getElementById('confirm').value) {
      toast('The two new passwords do not match', true);
      return;
    }
    await apiPost('/api/password', { current: cur, new: nw });
    toast('Password changed');
    setTimeout(() => location.href = '/app/more', 900);
  }));
});
</script>
<?php page_bottom('more'); ?>
