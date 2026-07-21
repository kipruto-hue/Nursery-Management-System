document.querySelectorAll('dialog [data-close]').forEach(b =>
  b.addEventListener('click', () => b.closest('dialog').close()));

function openDlg(id) {
  const d = document.getElementById(id);
  d.showModal();
  initSteppers(d);
}

document.getElementById('loss-btn').addEventListener('click', () => openDlg('loss-dlg'));
document.getElementById('activity-btn').addEventListener('click', () => openDlg('activity-dlg'));
document.getElementById('edit-btn')?.addEventListener('click', () => openDlg('edit-dlg'));

// One-tap stage advance
const advanceBtn = document.getElementById('advance-btn');
advanceBtn?.addEventListener('click', busy(advanceBtn, async () => {
  await apiPost('/api/batches.php', { action: 'set_stage', id: BATCH.id, stage: advanceBtn.dataset.stage });
  location.reload();
}));

// Write off (owner)
const writeoffBtn = document.getElementById('writeoff-btn');
writeoffBtn?.addEventListener('click', busy(writeoffBtn, async () => {
  const yes = await confirmSheet('Write off this batch?',
    'The batch will be closed and removed from stock. This is for batches that failed completely.',
    'Yes, write off');
  if (!yes) return;
  await apiPost('/api/batches.php', { action: 'set_stage', id: BATCH.id, stage: 'written_off' });
  location.reload();
}));

// Record loss with confirmation step showing remaining quantity (§6.3)
const lossSave = document.getElementById('loss-save');
document.getElementById('loss-form').addEventListener('submit', busy(lossSave, async (ev) => {
  ev.preventDefault();
  const qty = Number(document.getElementById('loss-qty').value);
  const remaining = BATCH.currentQty - qty;
  if (remaining < 0) {
    toast(`Only ${BATCH.currentQty.toLocaleString()} seedlings left in this batch`, true);
    return;
  }
  const yes = await confirmSheet('Confirm loss',
    `${qty.toLocaleString()} seedlings lost. The batch will have ${remaining.toLocaleString()} left. Save this?`,
    'Yes, save');
  if (!yes) return;
  await apiPost('/api/batch-events.php', {
    action: 'add',
    batch_id: BATCH.id,
    event_type: 'loss',
    qty,
    loss_reason: document.getElementById('loss-reason').value,
    event_date: document.getElementById('loss-date').value,
    notes: document.getElementById('loss-notes').value,
  });
  toast('Loss recorded');
  setTimeout(() => location.reload(), 700);
}));

// Record activity
const actType = document.getElementById('act-type');
actType.addEventListener('change', () => {
  document.getElementById('act-qty-field').hidden = actType.value !== 'potting';
});
const actSave = document.getElementById('act-save');
document.getElementById('activity-form').addEventListener('submit', busy(actSave, async (ev) => {
  ev.preventDefault();
  await apiPost('/api/batch-events.php', {
    action: 'add',
    batch_id: BATCH.id,
    event_type: actType.value,
    qty: document.getElementById('act-qty').value || undefined,
    event_date: document.getElementById('act-date').value,
    notes: document.getElementById('act-notes').value,
  });
  toast('Activity saved');
  setTimeout(() => location.reload(), 700);
}));

// Owner: edit batch
const editSave = document.getElementById('edit-save');
document.getElementById('edit-form')?.addEventListener('submit', busy(editSave, async (ev) => {
  ev.preventDefault();
  await apiPost('/api/batches.php', {
    action: 'update',
    id: BATCH.id,
    expected_ready_date: document.getElementById('edit-ready').value,
    sale_price_override: document.getElementById('edit-price').value,
    notes: document.getElementById('edit-notes').value,
  });
  toast('Batch updated');
  setTimeout(() => location.reload(), 700);
}));

// Owner: remove an event record
document.querySelectorAll('.ev-delete').forEach(a => {
  a.addEventListener('click', async (ev) => {
    ev.preventDefault();
    const yes = await confirmSheet('Remove this record?',
      'If it was a loss, the seedlings will be added back to the batch.', 'Yes, remove');
    if (!yes) return;
    try {
      await apiPost('/api/batch-events.php', { action: 'delete', id: Number(a.dataset.id) });
      location.reload();
    } catch (e) { toast(e.message, true); }
  });
});
