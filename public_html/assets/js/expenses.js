const dlg = document.getElementById('ex-dlg');
dlg.querySelector('[data-close]').addEventListener('click', () => dlg.close());

function openForm(d) {
  document.getElementById('ex-title').textContent = d ? 'Edit Expense' : 'New Purchase / Expense';
  document.getElementById('ex-id').value = d?.id || '';
  document.getElementById('ex-category').value = d?.category || 'seed';
  document.getElementById('ex-description').value = d?.description || '';
  document.getElementById('ex-amount').value = d?.amount || '';
  document.getElementById('ex-date').value = d?.date || new Date().toISOString().slice(0, 10);
  document.getElementById('ex-supplier').value = d?.supplier || '';
  document.getElementById('ex-batch').value = d?.batch && d.batch !== '0' ? d.batch : '';
  document.getElementById('ex-delete').hidden = !d;
  dlg.showModal();
}

document.getElementById('fab-new').addEventListener('click', (ev) => {
  ev.preventDefault();
  openForm(null);
});
document.querySelectorAll('.ex-item').forEach(el =>
  el.addEventListener('click', () => openForm(el.dataset)));

const saveBtn = document.getElementById('ex-save');
document.getElementById('ex-form').addEventListener('submit', busy(saveBtn, async (ev) => {
  ev.preventDefault();
  const id = document.getElementById('ex-id').value;
  await apiPost('/api/expenses.php', {
    action: id ? 'update' : 'create',
    id: id || undefined,
    category: document.getElementById('ex-category').value,
    description: document.getElementById('ex-description').value.trim(),
    amount: document.getElementById('ex-amount').value,
    expense_date: document.getElementById('ex-date').value,
    supplier: document.getElementById('ex-supplier').value.trim(),
    batch_id: document.getElementById('ex-batch').value || null,
  });
  toast('Expense saved');
  setTimeout(() => location.reload(), 700);
}));

const delBtn = document.getElementById('ex-delete');
delBtn.addEventListener('click', busy(delBtn, async () => {
  const yes = await confirmSheet('Delete this expense?', 'This cannot be undone.', 'Yes, delete');
  if (!yes) return;
  await apiPost('/api/expenses.php', { action: 'delete', id: document.getElementById('ex-id').value });
  location.reload();
}));
