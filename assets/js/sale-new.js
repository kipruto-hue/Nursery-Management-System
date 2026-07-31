const linesEl = document.getElementById('lines');
const totalEl = document.getElementById('total');

function addLine(batchId = '', qty = 1, price = null) {
  const row = document.createElement('div');
  row.className = 'sale-line';
  row.style.cssText = 'border-bottom:1px solid #EDEFEA;padding-bottom:12px;margin-bottom:12px';
  const opts = BATCHES.map(b =>
    `<option value="${b.id}" ${b.id == batchId ? 'selected' : ''}>${b.label} (${b.qty.toLocaleString()} left)</option>`
  ).join('');
  row.innerHTML = `
    <div class="field">
      <label>Batch</label>
      <select class="line-batch"><option value="">Choose…</option>${opts}</select>
    </div>
    <div class="field">
      <label>How many?</label>
      <div class="stepper" data-stepper>
        <button data-delta="-">−</button>
        <input type="number" class="line-qty" min="1" step="1" value="${qty}" inputmode="numeric">
        <button data-delta="+">＋</button>
      </div>
    </div>
    <div class="field">
      <label>Price each (KES)</label>
      <input type="number" class="line-price" min="0" step="0.5" inputmode="decimal" value="${price ?? ''}">
    </div>
    <a href="#" class="danger-text line-remove" style="font-size:14px">Remove this line</a>`;
  linesEl.appendChild(row);
  initSteppers(row);

  const batchSel = row.querySelector('.line-batch');
  batchSel.addEventListener('change', () => {
    const b = BATCHES.find(x => x.id == batchSel.value);
    if (b) row.querySelector('.line-price').value = b.price;
    recalc();
  });
  row.querySelector('.line-qty').addEventListener('input', recalc);
  row.querySelector('.line-price').addEventListener('input', recalc);
  row.querySelector('.line-remove').addEventListener('click', (ev) => {
    ev.preventDefault();
    row.remove();
    recalc();
  });
  recalc();
}

function readLines() {
  return [...linesEl.querySelectorAll('.sale-line')].map(row => ({
    batch_id: row.querySelector('.line-batch').value,
    qty: Number(row.querySelector('.line-qty').value) || 0,
    unit_price: row.querySelector('.line-price').value,
  })).filter(l => l.batch_id);
}

function recalc() {
  const total = readLines().reduce((s, l) => s + l.qty * (Number(l.unit_price) || 0), 0);
  totalEl.textContent = kes(total);
  const method = document.getElementById('pay-method').value;
  const paidEl = document.getElementById('amount-paid');
  if (method === 'mpesa' || method === 'cash') paidEl.value = total || '';
  if (method === 'credit') paidEl.value = 0;
}

// Customer select → optional inline new-customer fields
const custSel = document.getElementById('customer');
custSel.addEventListener('change', () => {
  document.getElementById('new-customer').hidden = custSel.value !== '__new';
});

const methodSel = document.getElementById('pay-method');
methodSel.addEventListener('change', () => {
  document.getElementById('mpesa-field').hidden = !(methodSel.value === 'mpesa' || methodSel.value === 'mixed');
  document.getElementById('paid-field').hidden = methodSel.value === 'credit';
  recalc();
});

// First line: prefill from pre-order if present
if (PREORDER) {
  const match = BATCHES.find(b => b.species_id === PREORDER.species_id);
  addLine(match ? match.id : '', PREORDER.qty, PREORDER.price);
  if (match) recalc();
} else {
  addLine();
}
methodSel.dispatchEvent(new Event('change'));

document.getElementById('add-line').addEventListener('click', () => addLine());

const saveBtn = document.getElementById('sale-save');
document.getElementById('sale-form').addEventListener('submit', busy(saveBtn, async (ev) => {
  ev.preventDefault();
  const items = readLines();
  if (!items.length) { toast('Add at least one batch to the sale', true); return; }

  let customerId = custSel.value;
  if (customerId === '__new') {
    const name = document.getElementById('nc-name').value.trim();
    if (!name) { toast('Enter the customer\'s name', true); return; }
    const c = await apiPost('/api/customers', {
      action: 'create',
      name,
      phone: document.getElementById('nc-phone').value.trim(),
    });
    customerId = c.id;
  }

  const data = await apiPost('/api/sales', {
    action: 'create',
    customer_id: customerId || null,
    preorder_id: PREORDER ? PREORDER.id : null,
    items,
    payment_method: methodSel.value,
    amount_paid: methodSel.value === 'credit' ? 0 : (document.getElementById('amount-paid').value || 0),
    mpesa_ref: document.getElementById('mpesa-ref').value.trim(),
    sale_date: document.getElementById('sale-date').value,
    notes: document.getElementById('sale-notes').value,
  });
  location.href = `/app/sale?id=${data.id}&new=1`;
}));
