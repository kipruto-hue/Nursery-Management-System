const dlg = document.getElementById('po-dlg');
dlg.querySelector('[data-close]').addEventListener('click', () => dlg.close());

document.getElementById('fab-new').addEventListener('click', (ev) => {
  ev.preventDefault();
  dlg.showModal();
  initSteppers(dlg);
});

const speciesSel = document.getElementById('po-species');
speciesSel.addEventListener('change', () => {
  const price = speciesSel.selectedOptions[0]?.dataset.price;
  if (price) document.getElementById('po-price').value = price;
});

const saveBtn = document.getElementById('po-save');
document.getElementById('po-form').addEventListener('submit', busy(saveBtn, async (ev) => {
  ev.preventDefault();
  await apiPost('/api/preorders', {
    action: 'create',
    customer_id: document.getElementById('po-customer').value,
    species_id: speciesSel.value,
    qty: document.getElementById('po-qty').value,
    agreed_unit_price: document.getElementById('po-price').value,
    deposit_paid: document.getElementById('po-deposit').value,
    expected_date: document.getElementById('po-date').value,
  });
  toast('Pre-order saved');
  setTimeout(() => location.reload(), 700);
}));

document.querySelectorAll('.po-cancel').forEach(a => {
  a.addEventListener('click', async (ev) => {
    ev.preventDefault();
    const yes = await confirmSheet('Cancel this pre-order?',
      'Remember to refund any deposit to the customer.', 'Yes, cancel it');
    if (!yes) return;
    try {
      await apiPost('/api/preorders', { action: 'cancel', id: Number(a.dataset.id) });
      location.reload();
    } catch (e) { toast(e.message, true); }
  });
});
