const dlg = document.getElementById('new-batch');

document.getElementById('fab-new').addEventListener('click', (ev) => {
  ev.preventDefault();
  dlg.showModal();
  initSteppers(dlg);
});
document.getElementById('nb-cancel').addEventListener('click', () => dlg.close());

// Suggest expected ready date from the species' usual lead time
const speciesSel = document.getElementById('nb-species');
speciesSel.addEventListener('change', () => {
  const lead = Number(speciesSel.selectedOptions[0]?.dataset.lead || 0);
  const hint = document.getElementById('nb-ready-hint');
  const readyInput = document.getElementById('nb-ready');
  if (lead > 0) {
    const sown = new Date(document.getElementById('nb-date').value || Date.now());
    sown.setDate(sown.getDate() + lead);
    readyInput.value = sown.toISOString().slice(0, 10);
    hint.textContent = `This plant is usually ready after about ${lead} days.`;
    hint.hidden = false;
  } else {
    hint.hidden = true;
  }
});

const saveBtn = document.getElementById('nb-save');
document.getElementById('new-batch-form').addEventListener('submit', busy(saveBtn, async (ev) => {
  ev.preventDefault();
  const data = await apiPost('/api/batches.php', {
    action: 'create',
    species_id: speciesSel.value,
    sown_date: document.getElementById('nb-date').value,
    initial_qty: document.getElementById('nb-qty').value,
    expected_ready_date: document.getElementById('nb-ready').value,
    notes: document.getElementById('nb-notes').value,
  });
  toast(`Batch ${data.batch_code} saved`);
  setTimeout(() => location.href = `/app/batch.php?id=${data.id}`, 700);
}));
