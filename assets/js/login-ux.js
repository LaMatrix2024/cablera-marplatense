const password = document.querySelector('#lcm-password');
const toggle = document.querySelector('.lcm-password-toggle');
toggle?.addEventListener('click', () => {
  const visible = password.type === 'text';
  password.type = visible ? 'password' : 'text';
  toggle.textContent = visible ? 'Mostrar' : 'Ocultar';
  toggle.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
  toggle.setAttribute('aria-pressed', visible ? 'false' : 'true');
});
const form = document.querySelector('#lcm-login-form');
form?.addEventListener('lcm-login-started', () => {
  const button = form.querySelector('button[type="submit"]');
  const spinner = form.querySelector('.lcm-login-spinner');
  const label = form.querySelector('.lcm-login-btn-label');
  if (!button) return;
  button.classList.add('is-loading');
  if (spinner) spinner.hidden = false;
  if (label) label.textContent = 'Ingresando…';
  window.clearTimeout(form._lcmVisualTimeout);
  form._lcmVisualTimeout = window.setTimeout(() => form.dispatchEvent(new CustomEvent('lcm-login-finished')), 9000);
});
form?.addEventListener('lcm-login-finished', () => {
  window.clearTimeout(form._lcmVisualTimeout);
  const button = form.querySelector('button[type="submit"]');
  const spinner = form.querySelector('.lcm-login-spinner');
  const label = form.querySelector('.lcm-login-btn-label');
  button?.classList.remove('is-loading');
  if (spinner) spinner.hidden = true;
  if (label) label.textContent = 'Ingresar';
});
