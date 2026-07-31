// Shared front-end helpers. No frameworks, no build step.

const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';

async function apiPost(url, data) {
  let res;
  try {
    res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(data || {}),
    });
  } catch (e) {
    throw new Error('No connection. Check your internet and try again.');
  }
  let body;
  try { body = await res.json(); }
  catch (e) { throw new Error('Something went wrong. Please try again.'); }
  if (!body.ok) {
    if (res.status === 401) { location.href = '/'; }
    throw new Error(body.error || 'Something went wrong.');
  }
  return body.data;
}

async function apiGet(url) {
  let res;
  try { res = await fetch(url); }
  catch (e) { throw new Error('No connection. Check your internet and try again.'); }
  const body = await res.json();
  if (!body.ok) {
    if (res.status === 401) { location.href = '/'; }
    throw new Error(body.error || 'Something went wrong.');
  }
  return body.data;
}

function kes(n) {
  const f = Number(n) || 0;
  const opts = Number.isInteger(f) ? {} : { minimumFractionDigits: 2, maximumFractionDigits: 2 };
  return 'KES ' + f.toLocaleString('en-KE', opts);
}

let toastTimer = null;
function toast(msg, isError = false) {
  document.querySelector('.toast')?.remove();
  const el = document.createElement('div');
  el.className = 'toast' + (isError ? ' error' : '');
  el.textContent = msg;
  document.body.appendChild(el);
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.remove(), 3500);
}

// Simple confirm dialog. Returns a promise resolving true/false.
function confirmSheet(title, message, okLabel = 'Yes, continue') {
  return new Promise((resolve) => {
    const dlg = document.createElement('dialog');
    dlg.className = 'sheet';
    dlg.innerHTML = `
      <h3></h3><p></p>
      <div class="btn-row">
        <button class="btn secondary" value="no">Cancel</button>
        <button class="btn" value="yes"></button>
      </div>`;
    dlg.querySelector('h3').textContent = title;
    dlg.querySelector('p').textContent = message;
    dlg.querySelector('button[value=yes]').textContent = okLabel;
    document.body.appendChild(dlg);
    dlg.addEventListener('close', () => {
      resolve(dlg.returnValue === 'yes');
      dlg.remove();
    });
    dlg.querySelectorAll('button').forEach(b =>
      b.addEventListener('click', () => dlg.close(b.value)));
    dlg.showModal();
  });
}

// Wire up any [data-stepper] blocks: buttons adjust the inner number input.
function initSteppers(root = document) {
  root.querySelectorAll('[data-stepper]').forEach(st => {
    const input = st.querySelector('input');
    st.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', (ev) => {
        ev.preventDefault();
        const delta = btn.dataset.delta === '-' ? -1 : 1;
        const step = Number(input.step || 1);
        const next = (Number(input.value) || 0) + delta * step;
        input.value = Math.max(Number(input.min || 0), next);
        input.dispatchEvent(new Event('input'));
      });
    });
  });
}

// Disable a submit button while its handler runs, restoring after.
function busy(btn, fn) {
  return async (...args) => {
    if (btn.disabled) return;
    btn.disabled = true;
    try { await fn(...args); }
    catch (e) { toast(e.message, true); }
    finally { btn.disabled = false; }
  };
}

document.addEventListener('DOMContentLoaded', () => initSteppers());
