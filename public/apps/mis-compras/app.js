/* global navigator, localStorage, window, document */

const API_BASE = '/api/pwa/mis-compras';
const APP_PUBLIC_PATH = '/apps/mis-compras/';
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
  pendingNewRubroId: null,
  productSuggestions: [],
  deferredPrompt: null,
  pendingExcludeId: null,
};

const els = {
  appShell: document.querySelector('.app-shell'),
  listName: document.getElementById('list-name'),
  statusText: document.getElementById('status-text'),
  refreshButton: document.getElementById('refresh-button'),
  shareButton: document.getElementById('share-button'),
  form: document.getElementById('item-form'),
  productInput: document.getElementById('product-input'),
  productSuggestions: document.getElementById('product-suggestions'),
  quantityInput: document.getElementById('quantity-input'),
  rubroSelect: document.getElementById('rubro-select'),
  newRubroButton: document.getElementById('new-rubro-button'),
  rubroFilter: document.getElementById('rubro-filter'),
  userSelect: document.getElementById('user-select'),
  addButton: document.getElementById('add-button'),
  stateFilters: document.getElementById('state-filters'),
  itemsList: document.getElementById('items-list'),
  summaryCount: document.getElementById('summary-count'),
  lastUpdated: document.getElementById('last-updated'),
  viewListButton: document.getElementById('view-list-button'),
  backListButton: document.getElementById('back-list-button'),
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
  excludeModal: document.getElementById('exclude-modal'),
  excludeConfirmButton: document.getElementById('exclude-confirm-button'),
  excludeCancelButton: document.getElementById('exclude-cancel-button'),
  rubroModal: document.getElementById('rubro-modal'),
  rubroForm: document.getElementById('rubro-form'),
  rubroNameInput: document.getElementById('rubro-name-input'),
  rubroMessage: document.getElementById('rubro-message'),
  rubroCreateButton: document.getElementById('rubro-create-button'),
  rubroCancelButton: document.getElementById('rubro-cancel-button'),
};

function setStatus(message, kind = 'neutral', detail = '') {
  const icons = {
    ok: '🟢',
    error: '🔴',
    loading: '🔵',
    neutral: '⚪',
  };
  const icon = icons[kind] || icons.neutral;
  els.statusText.innerHTML = `
    <span class="status-line-main">${icon} ${escapeHtml(message)}</span>
    ${detail ? `<span class="status-line-sub">${escapeHtml(detail)}</span>` : ''}
  `;
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

function formatDateAR(dateValue) {
  if (!dateValue) return '';

  const date = dateValue instanceof Date
    ? dateValue
    : new Date(String(dateValue).replace(' ', 'T'));

  if (Number.isNaN(date.getTime())) return '';

  const parts = new Intl.DateTimeFormat('es-AR', {
    timeZone: 'America/Argentina/Buenos_Aires',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(date).reduce((acc, part) => {
    acc[part.type] = part.value;
    return acc;
  }, {});

  return `${parts.day}/${parts.month}/${parts.year} ${parts.hour}:${parts.minute}`;
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

function normalizeProduct(value) {
  return String(value || '')
    .replace(/\s+/g, ' ')
    .trim()
    .toLocaleUpperCase('es-AR');
}

function normalizeRubroName(value) {
  const clean = String(value || '').replace(/\s+/g, ' ').trim();
  if (!clean) return '';
  return clean.charAt(0).toLocaleUpperCase('es-AR') + clean.slice(1);
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

function renderRubros(selectedId = null) {
  const currentRubro = selectedId || state.pendingNewRubroId || els.rubroSelect.value;
  const currentFilter = els.rubroFilter.value;
  const currentEdit = els.editRubro.value;
  const options = state.rubros.map((rubro) => `<option value="${rubro.id}">${rubro.nombre}</option>`).join('');
  els.rubroSelect.innerHTML = `<option value="">Sin rubro</option>${options}`;
  els.editRubro.innerHTML = `<option value="">Sin rubro</option>${options}`;
  els.rubroFilter.innerHTML = `<option value="">Todos los rubros</option>${options}`;

  if (currentRubro) els.rubroSelect.value = String(currentRubro);
  if (currentFilter) els.rubroFilter.value = currentFilter;
  if (currentEdit) els.editRubro.value = currentEdit;
  state.pendingNewRubroId = null;
}

function mergeProductSuggestions(items) {
  const names = new Set(state.productSuggestions);
  items.forEach((item) => {
    const product = normalizeProduct(item.producto);
    if (product) names.add(product);
  });
  state.productSuggestions = [...names].sort((a, b) => a.localeCompare(b, 'es-AR'));
}

function renderProductSuggestions() {
  const query = normalizeProduct(els.productInput.value);
  if (!query) {
    hideProductSuggestions();
    return;
  }

  const matches = state.productSuggestions
    .filter((product) => product.includes(query) && product !== query)
    .slice(0, 8);

  if (matches.length === 0) {
    hideProductSuggestions();
    return;
  }

  els.productSuggestions.innerHTML = matches
    .map((product) => `<button class="suggestion-button" type="button" data-product="${escapeHtml(product)}">${escapeHtml(product)}</button>`)
    .join('');
  els.productSuggestions.hidden = false;
}

function hideProductSuggestions() {
  els.productSuggestions.hidden = true;
  els.productSuggestions.innerHTML = '';
}

function normalizeProductInput(input) {
  const normalized = normalizeProduct(input.value);
  if (input.value !== normalized) {
    input.value = normalized;
  }
}

function renderSummary() {
  const pendientes = state.items.filter((item) => item.estado === 'PENDIENTE').length;
  const total = state.items.length;
  if (state.estado === 'PENDIENTE') {
    els.summaryCount.textContent = `${pendientes} pendiente${pendientes === 1 ? '' : 's'}`;
  } else {
    els.summaryCount.textContent = `${total} producto${total === 1 ? '' : 's'}`;
  }
  els.lastUpdated.textContent = `Act. ${formatDateAR(new Date())}`;
}

function actionButton(label, action, extraClass = '') {
  return `<button class="item-action ${extraClass}" type="button" data-action="${action}">${label}</button>`;
}

function renderItem(item) {
  const meta = [
    item.cantidad ? item.cantidad : '',
    item.rubro ? item.rubro : 'Sin rubro',
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
        <strong>${escapeHtml(normalizeProduct(item.producto))}</strong>
        <p>${escapeHtml(meta)}</p>
      </div>
      <span class="state-pill state-pill--${item.estado.toLowerCase()}">${estadoLabel(item.estado)}</span>
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

async function loadRubros(selectedId = null) {
  const data = await apiRequest(`rubros.php?${encodeQuery({ lista: state.lista })}`);
  state.rubros = data.rubros || [];
  renderRubros(selectedId);
}

async function loadItems() {
  const query = {
    lista: state.lista,
    estado: state.estado,
  };
  if (state.rubroId) query.rubro_id = state.rubroId;

  const data = await apiRequest(`items.php?${encodeQuery(query)}`);
  state.items = data.items || [];
  mergeProductSuggestions(state.items);
  renderItems();
}

async function refreshAll() {
  els.refreshButton.disabled = true;
  setStatus('Actualizando lista', 'loading');

  try {
    await loadList();
    await loadRubros();
    await loadItems();
    setStatus('Actualizada', 'ok', formatDateAR(new Date()));
  } catch (error) {
    console.warn(error);
    setStatus('Sin conexión', 'error', 'No se pudo actualizar');
  } finally {
    els.refreshButton.disabled = false;
  }
}

async function addItem(event) {
  event.preventDefault();
  const producto = normalizeProduct(els.productInput.value);
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
    hideProductSuggestions();
    restoreUser();
    await loadItems();
    setStatus('Actualizada', 'ok', formatDateAR(new Date()));
  } catch (error) {
    console.warn(error);
    setStatus('Sin conexión', 'error', 'No se pudo actualizar');
  } finally {
    els.addButton.disabled = false;
    els.productInput.focus();
  }
}

function openListMode() {
  els.appShell.classList.add('is-list-mode');
  els.backListButton.hidden = false;
  els.itemsList.scrollTop = 0;
}

function closeListMode() {
  els.appShell.classList.remove('is-list-mode');
  els.backListButton.hidden = true;
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
  state.pendingExcludeId = id;
  openExcludeModal();
}

async function confirmExcludeItem() {
  const id = state.pendingExcludeId;
  if (!id) {
    closeExcludeModal();
    return;
  }

  persistUser();
  els.excludeConfirmButton.disabled = true;

  try {
    await apiRequest(`excluir.php?id=${encodeURIComponent(id)}`, {
      method: 'PATCH',
      body: JSON.stringify({
        lista: state.lista,
        usuario: currentUser(),
      }),
    });
    closeExcludeModal();
    await loadItems();
    setStatus('Actualizada', 'ok', formatDateAR(new Date()));
  } catch (error) {
    console.warn(error);
    setStatus('Sin conexión', 'error', 'No se pudo actualizar');
  } finally {
    els.excludeConfirmButton.disabled = false;
  }
}

function openExcludeModal() {
  els.excludeModal.hidden = false;
  els.excludeConfirmButton.focus();
}

function closeExcludeModal() {
  els.excludeModal.hidden = true;
  state.pendingExcludeId = null;
}

function openRubroModal() {
  els.rubroMessage.hidden = true;
  els.rubroMessage.textContent = '';
  els.rubroNameInput.value = '';
  els.rubroModal.hidden = false;
  els.rubroNameInput.focus();
}

function closeRubroModal() {
  els.rubroModal.hidden = true;
  els.rubroForm.reset();
  els.rubroMessage.hidden = true;
  els.rubroMessage.textContent = '';
}

async function createRubro(event) {
  event.preventDefault();
  const nombre = normalizeRubroName(els.rubroNameInput.value);

  if (!nombre) {
    els.rubroMessage.textContent = 'Ingresá un nombre de rubro.';
    els.rubroMessage.hidden = false;
    return;
  }

  const exists = state.rubros.find((rubro) => rubro.nombre.toLocaleLowerCase('es-AR') === nombre.toLocaleLowerCase('es-AR'));
  if (exists) {
    els.rubroMessage.textContent = 'Ese rubro ya existe.';
    els.rubroMessage.hidden = false;
    els.rubroSelect.value = String(exists.id);
    return;
  }

  els.rubroCreateButton.disabled = true;
  persistUser();

  try {
    const data = await apiRequest('rubros.php', {
      method: 'POST',
      body: JSON.stringify({
        lista: state.lista,
        nombre,
        usuario: currentUser(),
      }),
    });
    state.pendingNewRubroId = data.rubro.id;
    await loadRubros(data.rubro.id);
    closeRubroModal();
    setStatus('Actualizada', 'ok', formatDateAR(new Date()));
  } catch (error) {
    console.warn(error);
    els.rubroMessage.textContent = error.message || 'No se pudo crear el rubro.';
    els.rubroMessage.hidden = false;
  } finally {
    els.rubroCreateButton.disabled = false;
  }
}

function openEdit(item) {
  els.editId.value = item.id;
  els.editProduct.value = normalizeProduct(item.producto);
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
  const producto = normalizeProduct(els.editProduct.value);
  persistUser();

  try {
    await apiRequest(`item.php?id=${encodeURIComponent(id)}`, {
      method: 'PATCH',
      body: JSON.stringify({
        lista: state.lista,
        producto,
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
  const url = new URL(APP_PUBLIC_PATH, window.location.origin);
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
    console.info('Mis Compras PWA: beforeinstallprompt detectado');
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
    navigator.serviceWorker.register(`${APP_PUBLIC_PATH}sw.js`, { scope: APP_PUBLIC_PATH }).catch((error) => {
      console.warn('No se pudo registrar el service worker', error);
    });
  }
}

function bindEvents() {
  els.form.addEventListener('submit', addItem);
  els.productInput.addEventListener('input', () => {
    normalizeProductInput(els.productInput);
    renderProductSuggestions();
  });
  els.productInput.addEventListener('focus', renderProductSuggestions);
  els.productSuggestions.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-product]');
    if (!button) return;
    els.productInput.value = button.dataset.product;
    hideProductSuggestions();
    els.productInput.focus();
  });
  els.editProduct.addEventListener('input', () => normalizeProductInput(els.editProduct));
  document.addEventListener('click', (event) => {
    if (!els.productInput.contains(event.target) && !els.productSuggestions.contains(event.target)) {
      hideProductSuggestions();
    }
  });
  els.refreshButton.addEventListener('click', refreshAll);
  els.shareButton.addEventListener('click', shareList);
  els.newRubroButton.addEventListener('click', openRubroModal);
  els.rubroForm.addEventListener('submit', createRubro);
  els.rubroCancelButton.addEventListener('click', closeRubroModal);
  els.rubroModal.addEventListener('click', (event) => {
    if (event.target === els.rubroModal || event.target.classList.contains('confirm-modal__backdrop')) {
      closeRubroModal();
    }
  });
  els.viewListButton.addEventListener('click', openListMode);
  els.backListButton.addEventListener('click', closeListMode);
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
  els.excludeConfirmButton.addEventListener('click', confirmExcludeItem);
  els.excludeCancelButton.addEventListener('click', closeExcludeModal);
  els.excludeModal.addEventListener('click', (event) => {
    if (event.target === els.excludeModal || event.target.classList.contains('confirm-modal__backdrop')) {
      closeExcludeModal();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !els.excludeModal.hidden) {
      closeExcludeModal();
    }
    if (event.key === 'Escape' && !els.rubroModal.hidden) {
      closeRubroModal();
    }
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
