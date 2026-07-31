const form = document.getElementById('login-form');
const errEl = document.getElementById('login-error');
const btn = document.getElementById('login-btn');

form.addEventListener('submit', busy(btn, async (ev) => {
  ev.preventDefault();
  errEl.hidden = true;
  const phone = document.getElementById('phone').value.trim().replace(/\s+/g, '');
  const password = document.getElementById('password').value;
  try {
    await apiPost('/api/login', { phone, password });
    location.href = '/app/dashboard';
  } catch (e) {
    errEl.textContent = e.message;
    errEl.hidden = false;
  }
}));
