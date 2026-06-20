/* global navigator, localStorage, window, document, confirm */

const API_BASE = '/api/pwa/mis-compras';
const STORAGE = {
  user: 'mis_compras_user_v1',
  installSeen: 'mis_compras_install_seen_v1',
  installed: 'mis_compras_installed_v1',
};

const state = {
  lista: new URLSearchParams(window.location.search).get('lista') || '',
  listaNombre: 'Lista compartida',
  rubros: [],
  items: [],
  estado: 'PENDIENTE',
  rubroId: '',
  deferredPrompt: null,
};

const els = {
  listName: document.getElementById('list-name'),
  statusText: document.getElementById('status-text'),
  refreshButton: document.getElementById('refresh-button'),
  shareButton: document.getElementById('share-button'),
  form: document.getElementById('item-form'),
  productInput: document.getElementById('product-input'),
  quantityInput: document.getElementById('quantity-input'),
  rubroSelect: document.getElementById('rubro-select'),
  rubroFilter: document.getElementById('rubro-filter'),
  userSelect: document.getElementById('user-select'),
  addButton: document.getElementById('add-button'),
  stateFilters: document.getElementById('state-filters'),
  itemsList: document.getElementById('items-list'),
  summaryCount: document.getElementById('summary-count'),
  lastUpdated: document.getElementById('last-updated'),
  emptyTemplate: document.getElementById('empty-template'),
  installModal: document.getElementById('install-modal'),
  installButton: document.getElementById('install-button'),
  dismissButton: document.getElementById('dismiss-button'),
  editModal: document.getElementById('edit-modal'),
  editForm: document.getElementById('edit-form'),
  editId: document.getElementById('edit-id'),
  editProduct: document.getElementById('edit-product'),
  editQuantity: document.getElementById('edit-quantity'),
  editRubro: document.getElementById('edit-rubro'),
  editCancel: document.getElementById('edit-cancel'),
};

function setStatus(message, kind = 'neutral') {
  els.statusText.textContent = message;
  els.statusText.dataset.kind = kind;
}

function encodeQuery(params) {
  return new URLSearchParams(params).toString();
}

async function apiRequest(path, options = {}) {
  const response = await fetch(`${API_BASE}/${path}`, {
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  });

  const data = await response.json().catch(() => null);

  if (!response.ok || !data?.ok) {
    throw new Error(data?.error || `HTTP ${response.status}`);
  }

  return data;
}

function formatDateTime(value) {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('es-AR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

function estadoLabel(estado) {
  const labels = {
    PENDIENTE: 'Pendiente',
    COMPRADO: 'Comprado',
    CANCELADO: 'Cancelado',
    EXCLUIDO: 'Excluido',
  };
  return labels[estado] || estado;
}

function currentUser() {
  return els.userSelect.value || 'sin_identificar';
}

function persistUser() {
  localStorage.setItem(STORAGE.user, currentUser());
}

function restoreUser() {
  const saved = localStorage.getItem(STORAGE.user);
  if (saved) {
    els.userSelect.value = saved;
  }
}

function renderRubros() {
  const options = state.rubros.map((rubro) => `<option value="${rubro.id}">${rubro.nombre}</option>`).join('');
  els.rubroSelect.innerHTML = `<option value="">Sin rubro</option>${options}`;
  els.editRubro.innerHTML = `<option value="">Sin rubro</option>${options}`;
  els.rubroFilter.innerHTML = `<option value="">Todos los rubros</option>${options}`;
}

function renderSummary() {
  const pendientes = state.items.filter((item) => item.estado === 'PENDIENTE').length;
  const total = state.items.length;
  if (state.estado === 'PENDIENTE') {
    els.summaryCount.textContent = `${pendientes} pendiente${pendientes === 1 ? '' : 's'}`;
  } else {
    els.summaryCount.textContent = `${total} producto${total === 1 ? '' : 's'}`;
  }
  els.lastUpdated.textContent = `Actualizado ${new Intl.DateTimeFormat('es-AR', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date())}`;
}

function actionButton(label, action, extraClass = '') {
  return `<button class="item-action ${extraClass}" type="button" data-action="${action}">${label}</button>`;
}

function renderItem(item) {
  const meta = [
    item.cantidad ? item.cantidad : '',
    item.rubro ? item.rubro : 'Sin rubro',
    item.updated_at ? `Act. ${formatDateTime(item.updated_at)}` : '',
  ].filter(Boolean).join(' · ');

  const actions = [];
  if (item.estado !== 'COMPRADO') actions.push(actionButton('Comprar', 'comprar', 'item-action--success'));
  if (item.estado !== 'PENDIENTE') actions.push(actionButton('Pendiente', 'pendiente'));
  if (item.estado !== 'CANCELADO') actions.push(actionButton('Cancelar', 'cancelar'));
  actions.push(actionButton('Editar', 'editar'));
  actions.push(actionButton('Excluir', 'excluir', 'item-action--danger'));

  const article = document.createElement('article');
  article.className = `item-card item-card--${item.estado.toLowerCase()}`;
  article.dataset.id = item.id;
  article.innerHTML = `
    <div class="item-card__main">
      <div>
        <strong>${escapeHtml(item.producto)}</strong>
        <p>${escapeHtml(meta)}</p>
      </div>
      <span class="state-pill">${estadoLabel(item.estado)}</span>
    </div>
    <div class="item-card__actions">${actions.join('')}</div>
  `;

  return article;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}

function renderItems() {
  els.itemsList.innerHTML = '';

  if (state.items.length === 0) {
    els.itemsList.appendChild(els.emptyTemplate.content.firstElementChild.cloneNode(true));
    renderSummary();
    return;
  }

  state.items.forEach((item) => {
    els.itemsList.appendChild(renderItem(item));
  });
  renderSummary();
}

function setVisiblePendingFilter() {
  state.estado = 'PENDIENTE';
  state.rubroId = '';
  els.rubroFilter.value = '';

  [...els.stateFilters.querySelectorAll('.segment')].forEach((button) => {
    button.classList.toggle('is-active', button.dataset.estado === 'PENDIENTE');
  });
}

async function loadList() {
  if (!state.lista) {
    setStatus('Falta el parametro lista en el link', 'error');
    throw new Error('Falta parametro lista');
  }

  const data = await apiRequest(`lista.php?${encodeQuery({ lista: state.lista })}`);
  state.listaNombre = data.lista.nombre;
  els.listName.textContent = data.lista.nombre;
}

async function loadRubros() {
  const data = await apiRequest(`rubros.php?${encodeQuery({ lista: state.lista })}`);
  state.rubros = data.rubros || [];
  renderRubros();
}

async function loadItems() {
  const query = {
    lista: state.lista,
    estado: state.estado,
  };
  if (state.rubroId) query.rubro_id = state.rubroId;

  const data = await apiRequest(`items.php?${encodeQuery(query)}`);
  state.items = data.items || [];
  renderItems();
}

async function refreshAll() {
  els.refreshButton.disabled = true;
  setStatus('Actualizando lista', 'loading');

  try {
    await loadList();
    await loadRubros();
    await loadItems();
    setStatus('Lista actualizada', 'ok');
  } catch (error) {
    console.warn(error);
    setStatus(error.message || 'No se pudo actualizar', 'error');
  } finally {
    els.refreshButton.disabled = false;
  }
}

async function addItem(event) {
  event.preventDefault();
  const producto = els.productInput.value.trim();
  if (!producto) return;

  els.addButton.disabled = true;
  persistUser();

  try {
    await apiRequest('items.php', {
      method: 'POST',
      body: JSON.stringify({
        lista: state.lista,
        producto,
        cantidad: els.quantityInput.value.trim(),
        rubro_id: els.rubroSelect.value || null,
        usuario: currentUser(),
      }),
    });
    setVisiblePendingFilter();
    els.form.reset();
    restoreUser();
    setStatus('Producto agregado', 'ok');
    await loadItems();
  } catch (error) {
    console.warn(error);
    setStatus(error.message || 'No se pudo agregar', 'error');
  } finally {
    els.addButton.disabled = false;
    els.productInput.focus();
  }
}

async function changeState(id, estado) {
  persistUser();
  await apiRequest(`estado.php?id=${encodeURIComponent(id)}`, {
    method: 'PATCH',
    body: JSON.stringify({
      lista: state.lista,
      estado,
      usuario: currentUser(),
    }),
  });
  await loadItems();
}

async function excludeItem(id) {
  if (!confirm('¿Excluir este producto por error de carga?')) return;
  persistUser();
  await apiRequest(`excluir.php?id=${encodeURIComponent(id)}`, {
    method: 'PATCH',
    body: JSON.stringify({
      lista: state.lista,
      usuario: currentUser(),
    }),
  });
  await loadItems();
}

function openEdit(item) {
  els.editId.value = item.id;
  els.editProduct.value = item.producto || '';
  els.editQuantity.value = item.cantidad || '';
  els.editRubro.value = item.rubro_id || '';
  els.editModal.hidden = false;
  els.editProduct.focus();
}

function closeEdit() {
  els.editModal.hidden = true;
  els.editForm.reset();
}

async function saveEdit(event) {
  event.preventDefault();
  const id = els.editId.value;
  persistUser();

  try {
    await apiRequest(`item.php?id=${encodeURIComponent(id)}`, {
      method: 'PATCH',
      body: JSON.stringify({
        lista: state.lista,
        producto: els.editProduct.value.trim(),
        cantidad: els.editQuantity.value.trim(),
        rubro_id: els.editRubro.value || null,
        usuario: currentUser(),
      }),
    });
    closeEdit();
    setStatus('Producto actualizado', 'ok');
    await loadItems();
  } catch (error) {
    console.warn(error);
    setStatus(error.message || 'No se pudo actualizar', 'error');
  }
}

async function handleItemAction(event) {
  const button = event.target.closest('button[data-action]');
  if (!button) return;

  const card = button.closest('.item-card');
  const id = card?.dataset.id;
  const item = state.items.find((entry) => String(entry.id) === String(id));
  if (!id || !item) return;

  button.disabled = true;
  try {
    if (button.dataset.action === 'comprar') await changeState(id, 'COMPRADO');
    if (button.dataset.action === 'pendiente') await changeState(id, 'PENDIENTE');
    if (button.dataset.action === 'cancelar') await changeState(id, 'CANCELADO');
    if (button.dataset.action === 'excluir') await excludeItem(id);
    if (button.dataset.action === 'editar') openEdit(item);
  } catch (error) {
    console.warn(error);
    setStatus(error.message || 'No se pudo procesar la accion', 'error');
  } finally {
    button.disabled = false;
  }
}

function setEstadoFilter(estado) {
  state.estado = estado;
  [...els.stateFilters.querySelectorAll('.segment')].forEach((button) => {
    button.classList.toggle('is-active', button.dataset.estado === estado);
  });
  loadItems().catch((error) => {
    console.warn(error);
    setStatus(error.message || 'No se pudo filtrar', 'error');
  });
}

async function shareList() {
  const url = new URL(window.location.href);
  if (state.lista) url.searchParams.set('lista', state.lista);
  const text = `${state.listaNombre}\n${url.toString()}`;

  try {
    if (navigator.share) {
      await navigator.share({
        title: 'Mis Compras',
        text,
        url: url.toString(),
      });
      return;
    }
  } catch (error) {
    console.warn('share() fallo', error);
  }

  try {
    await navigator.clipboard.writeText(text);
    setStatus('Link copiado', 'ok');
  } catch (error) {
    console.warn('clipboard fallo', error);
    window.prompt('Copiar link de la lista', text);
  }
}

function openInstallModal() {
  if (localStorage.getItem(STORAGE.installSeen) === 'dismissed') return;
  els.installModal.hidden = false;
}

function closeInstallModal(markSeen = true) {
  els.installModal.hidden = true;
  if (markSeen) localStorage.setItem(STORAGE.installSeen, 'dismissed');
}

function registerInstallPrompt() {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    state.deferredPrompt = event;
    openInstallModal();
  });

  window.addEventListener('appinstalled', () => {
    localStorage.setItem(STORAGE.installed, 'yes');
    state.deferredPrompt = null;
    closeInstallModal(true);
  });
}

async function handleInstall() {
  if (!state.deferredPrompt) {
    closeInstallModal(true);
    return;
  }

  state.deferredPrompt.prompt();
  try {
    await state.deferredPrompt.userChoice;
  } finally {
    localStorage.setItem(STORAGE.installed, 'yes');
    state.deferredPrompt = null;
    closeInstallModal(true);
  }
}

function registerServiceWorker() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js').catch((error) => {
      console.warn('No se pudo registrar el service worker', error);
    });
  }
}

function bindEvents() {
  els.form.addEventListener('submit', addItem);
  els.refreshButton.addEventListener('click', refreshAll);
  els.shareButton.addEventListener('click', shareList);
  els.userSelect.addEventListener('change', persistUser);
  els.itemsList.addEventListener('click', handleItemAction);
  els.rubroFilter.addEventListener('change', () => {
    state.rubroId = els.rubroFilter.value;
    loadItems().catch((error) => setStatus(error.message || 'No se pudo filtrar', 'error'));
  });
  els.stateFilters.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-estado]');
    if (button) setEstadoFilter(button.dataset.estado);
  });
  els.editForm.addEventListener('submit', saveEdit);
  els.editCancel.addEventListener('click', closeEdit);
  els.editModal.addEventListener('click', (event) => {
    if (event.target === els.editModal || event.target.classList.contains('edit-modal__backdrop')) closeEdit();
  });
  els.installButton.addEventListener('click', handleInstall);
  els.dismissButton.addEventListener('click', () => closeInstallModal(true));
  els.installModal.addEventListener('click', (event) => {
    if (event.target === els.installModal || event.target.classList.contains('install-modal__backdrop')) {
      closeInstallModal(true);
    }
  });
}

function init() {
  restoreUser();
  registerServiceWorker();
  registerInstallPrompt();
  bindEvents();
  refreshAll();
}

init();
