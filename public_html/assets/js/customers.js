const dlg = document.getElementById('cust-dlg');
dlg.querySelector('[data-close]').addEventListener('click', () => dlg.close());

function openForm(data) {
  document.getElementById('cust-title').textContent = data ? 'Edit Customer' : 'New Customer';
  document.getElementById('cust-id').value = data?.id || '';
  document.getElementById('cust-name').value = data?.name || '';
  document.getElementById('cust-phone').value = data?.phone || '';
  document.getElementById('cust-notes').value = data?.notes || '';
  const del = document.getElementById('cust-delete');
  if (del) del.hidden = !data;
  dlg.showModal();
}

document.getElementById('fab-new').addEventListener('click', (ev) => {
  ev.preventDefault();
  openForm(null);
});

document.querySelectorAll('.cust-edit').forEach(a => {
  a.addEventListener('click', (ev) => {
    ev.preventDefault();
    openForm(a.closest('.list-item').dataset);
  });
});

const saveBtn = document.getElementById('cust-save');
document.getElementById('cust-form').addEventListener('submit', busy(saveBtn, async (ev) => {
  ev.preventDefault();
  const id = document.getElementById('cust-id').value;
  await apiPost('/api/customers.php', {
    action: id ? 'update' : 'create',
    id: id || undefined,
    name: document.getElementById('cust-name').value.trim(),
    phone: document.getElementById('cust-phone').value.trim(),
    notes: document.getElementById('cust-notes').value.trim(),
  });
  toast('Customer saved');
  setTimeout(() => location.reload(), 700);
}));

const delBtn = document.getElementById('cust-delete');
delBtn?.addEventListener('click', busy(delBtn, async () => {
  const yes = await confirmSheet('Remove this customer?',
    'Their past sales stay in the records.', 'Yes, remove');
  if (!yes) return;
  await apiPost('/api/customers.php', {
    action: 'delete',
    id: document.getElementById('cust-id').value,
  });
  location.reload();
}));
