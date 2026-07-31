document.querySelectorAll('dialog [data-close]').forEach(b =>
  b.addEventListener('click', () => b.closest('dialog').close()));

// Nursery name
const nameSave = document.getElementById('name-save');
document.getElementById('name-form').addEventListener('submit', busy(nameSave, async (ev) => {
  ev.preventDefault();
  await apiPost('/api/settings', { nursery_name: document.getElementById('nursery-name').value.trim() });
  toast('Name saved');
  setTimeout(() => location.reload(), 700);
}));

// Species
const spDlg = document.getElementById('sp-dlg');
function openSpecies(d) {
  document.getElementById('sp-title').textContent = d ? 'Edit Plant' : 'Add a Plant';
  document.getElementById('sp-id').value = d?.id || '';
  document.getElementById('sp-name').value = d?.name || '';
  document.getElementById('sp-category').value = d?.category || 'fruit';
  document.getElementById('sp-price').value = d?.price || '';
  document.getElementById('sp-lead').value = d?.lead || '';
  document.getElementById('sp-active').value = d ? d.active : '1';
  document.getElementById('sp-prefix-field').hidden = !!d;   // prefix fixed after creation
  document.getElementById('sp-active-field').hidden = !d;
  spDlg.showModal();
}
document.getElementById('sp-new').addEventListener('click', () => openSpecies(null));
document.querySelectorAll('.sp-item').forEach(el =>
  el.addEventListener('click', () => openSpecies(el.dataset)));

const spSave = document.getElementById('sp-save');
document.getElementById('sp-form').addEventListener('submit', busy(spSave, async (ev) => {
  ev.preventDefault();
  const id = document.getElementById('sp-id').value;
  await apiPost('/api/species', {
    action: id ? 'update' : 'create',
    id: id || undefined,
    name: document.getElementById('sp-name').value.trim(),
    category: document.getElementById('sp-category').value,
    default_sale_price: document.getElementById('sp-price').value,
    lead_time_days: document.getElementById('sp-lead').value,
    code_prefix: document.getElementById('sp-prefix').value.trim(),
    active: id ? document.getElementById('sp-active').value === '1' : true,
  });
  toast('Plant saved');
  setTimeout(() => location.reload(), 700);
}));

// Workers
const wDlg = document.getElementById('w-dlg');
const tempDlg = document.getElementById('temp-dlg');
document.getElementById('w-new').addEventListener('click', () => wDlg.showModal());
document.getElementById('temp-ok').addEventListener('click', () => {
  tempDlg.close();
  location.reload();
});

function showTemp(pw) {
  document.getElementById('temp-pw').textContent = pw;
  tempDlg.showModal();
}

const wSave = document.getElementById('w-save');
document.getElementById('w-form').addEventListener('submit', busy(wSave, async (ev) => {
  ev.preventDefault();
  const data = await apiPost('/api/users', {
    action: 'create',
    name: document.getElementById('w-name').value.trim(),
    phone: document.getElementById('w-phone').value.trim(),
  });
  wDlg.close();
  showTemp(data.temp_password);
}));

document.querySelectorAll('.w-reset').forEach(a => {
  a.addEventListener('click', async (ev) => {
    ev.preventDefault();
    const yes = await confirmSheet('Reset this worker\'s password?',
      'They will get a new temporary password shown only once.', 'Yes, reset');
    if (!yes) return;
    try {
      const data = await apiPost('/api/users', { action: 'reset_password', id: Number(a.dataset.id) });
      showTemp(data.temp_password);
    } catch (e) { toast(e.message, true); }
  });
});

document.querySelectorAll('.w-toggle').forEach(a => {
  a.addEventListener('click', async (ev) => {
    ev.preventDefault();
    const activating = a.dataset.active !== '1';
    const yes = await confirmSheet(
      activating ? 'Reactivate this worker?' : 'Deactivate this worker?',
      activating ? 'They will be able to log in again.' : 'They will no longer be able to log in.',
      activating ? 'Yes, reactivate' : 'Yes, deactivate');
    if (!yes) return;
    try {
      await apiPost('/api/users', { action: 'set_active', id: Number(a.dataset.id), active: activating });
      location.reload();
    } catch (e) { toast(e.message, true); }
  });
});
