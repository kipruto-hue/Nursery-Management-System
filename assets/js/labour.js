const dlg = document.getElementById('lb-dlg');
dlg.querySelector('[data-close]').addEventListener('click', () => dlg.close());

function openForm(d) {
  document.getElementById('lb-title').textContent = d ? 'Edit Labour Payment' : 'New Labour Payment';
  document.getElementById('lb-id').value = d?.id || '';
  document.getElementById('lb-worker').value = d?.worker || '';
  document.getElementById('lb-task').value = d?.task || '';
  document.getElementById('lb-amount').value = d?.amount || '';
  document.getElementById('lb-date').value = d?.date || new Date().toISOString().slice(0, 10);
  document.getElementById('lb-batch').value = d?.batch && d.batch !== '0' ? d.batch : '';
  document.getElementById('lb-delete').hidden = !d;
  dlg.showModal();
}

document.getElementById('fab-new').addEventListener('click', (ev) => {
  ev.preventDefault();
  openForm(null);
});
document.querySelectorAll('.lb-item').forEach(el =>
  el.addEventListener('click', () => openForm(el.dataset)));

const saveBtn = document.getElementById('lb-save');
document.getElementById('lb-form').addEventListener('submit', busy(saveBtn, async (ev) => {
  ev.preventDefault();
  const id = document.getElementById('lb-id').value;
  await apiPost('/api/labour', {
    action: id ? 'update' : 'create',
    id: id || undefined,
    worker_name: document.getElementById('lb-worker').value.trim(),
    task: document.getElementById('lb-task').value.trim(),
    amount: document.getElementById('lb-amount').value,
    labour_date: document.getElementById('lb-date').value,
    batch_id: document.getElementById('lb-batch').value || null,
  });
  toast('Labour payment saved');
  setTimeout(() => location.reload(), 700);
}));

const delBtn = document.getElementById('lb-delete');
delBtn.addEventListener('click', busy(delBtn, async () => {
  const yes = await confirmSheet('Delete this payment record?', 'This cannot be undone.', 'Yes, delete');
  if (!yes) return;
  await apiPost('/api/labour', { action: 'delete', id: document.getElementById('lb-id').value });
  location.reload();
}));
