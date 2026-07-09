/* global navigator, localStorage, window, document */

const API_BASE = '/api/pwa/mis-compras';
const APP_PUBLIC_PATH = '/apps/mis-compras/';
const APP_VERSION = document.querySelector('meta[name="app-version"]')?.content || '1.0.0';
const STORAGE = {
  user: 'mis_compras_user_v1',
  installSeen: 'mis_compras_install_seen_v1',
  installed: 'mis_compras_installed_v1',
  acceptedVersion: 'mis_compras_accepted_version_v1',
};

const state = {
  lista: new URLSearchParams(window.location.search).get('lista') || '',
  listaNombre: 'Lista compartida',
  rubros: [],
  items: [],
  estado: 'PENDIENTE',
  rubroId: '',
  pendingNewRubroId: null,
  pendingExcludeId: null,
  serverVersion: APP_VERSION,

};

const els = {
  body: document.body,
  gate: document.getElementById('pwa-gate'),
  gateTitle: document.getElementById('pwa-gate-title'),
  gateText: document.getElementById('pwa-gate-text'),
  gateHint: document.getElementById('pwa-gate-hint'),
  gateAction: document.getElementById('pwa-gate-action'),
  appShell: document.querySelector('.app-shell'),
  listName: document.getElementById('list-name'),
  statusText: document.getElementById('status-text'),
  refreshButton: document.getElementById('refresh-button'),
  shareButton: document.getElementById('share-button'),
  form: document.getElementById('item-form'),
  nuevoProductoInput: document.getElementById('nuevoProductoInput'),
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

let deferredInstallPrompt = null;
let serviceWorkerRegistration = null;

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

function isStandaloneApp() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function getAcceptedVersion() {
  return localStorage.getItem(STORAGE.acceptedVersion) || '';
}

function markVersionAccepted(version) {
  if (version) {
    localStorage.setItem(STORAGE.acceptedVersion, version);
  }
}

function markInstalled(version) {
  localStorage.setItem(STORAGE.installed, 'true');
  if (version) {
    markVersionAccepted(version);
  }
}

function setGateState(kind, title, text, hint, actionLabel, onAction) {
  els.gate.dataset.kind = kind;
  els.gateTitle.textContent = title;
  els.gateText.textContent = text;
  els.gateHint.textContent = hint;
  els.gateAction.textContent = actionLabel;
  els.gateAction.onclick = onAction;
  els.gate.hidden = false;
  els.body.classList.add('is-gated');
}

function hideGate() {
  els.gate.hidden = true;
  els.body.classList.remove('is-gated');
}

async function loadManifestVersion() {
  try {
    const response = await fetch('./manifest.json', { cache: 'no-store' });
    if (!response.ok) return APP_VERSION;
    const manifest = await response.json();
    return manifest.version || APP_VERSION;
  } catch (error) {
    console.warn('No se pudo leer manifest.json', error);
    return APP_VERSION;
  }
}

function needsForcedUpdate(serverVersion) {
  const acceptedVersion = getAcceptedVersion();
  return Boolean(acceptedVersion && serverVersion && acceptedVersion !== serverVersion);
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

function normalizeEstado(value) {
  const compact = String(value || '')
    .trim()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleUpperCase('es-AR');

  if (compact === 'PENDIENTE') return 'PENDIENTE';
  if (compact === 'COMPRADO') return 'COMPRADO';
  if (compact === 'CANCELADO') return 'CANCELADO';
  if (compact === 'EXCLUIDO') return 'EXCLUIDO';
  return compact || 'PENDIENTE';
}

function itemStateCode(item) {
  return normalizeEstado(item?.estado ?? item?.estado_nombre);
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

function renderSummary() {
  const pendientes = state.items.filter((item) => itemStateCode(item) === 'PENDIENTE').length;
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
  const estado = itemStateCode(item);
  const meta = [
    item.cantidad ? item.cantidad : '',
    item.rubro ? item.rubro : 'Sin rubro',
  ].filter(Boolean).join(' · ');

  const actions = [];
  if (estado !== 'COMPRADO') actions.push(actionButton('Comprar', 'comprar', 'item-action--success'));
  if (estado !== 'PENDIENTE') actions.push(actionButton('Pendiente', 'pendiente'));
  if (estado !== 'CANCELADO') actions.push(actionButton('Cancelar', 'cancelar'));
  actions.push(actionButton('Editar', 'editar'));
  actions.push(actionButton('Excluir', 'excluir', 'item-action--danger'));

  const article = document.createElement('article');
  article.className = `item-card item-card--${estado.toLowerCase()}`;
  article.dataset.id = item.id;
  article.innerHTML = `
      <div class="item-card__main">
        <div>
          <strong>${escapeHtml(normalizeProduct(item.producto))}</strong>
          <p>${escapeHtml(meta)}</p>
        </div>
        <span class="state-pill state-pill--${estado.toLowerCase()}">${estadoLabel(estado)}</span>
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
  const items = Array.isArray(data?.items) ? data.items : Array.isArray(data?.data?.items) ? data.data.items : [];
  state.items = items.map((item) => ({
    ...item,
    estado: itemStateCode(item),
  }));
  renderItems();
}

let refreshLock = Promise.resolve();
let refreshGeneration = 0;
let lastRefreshTriggerAt = 0;

function triggerRefresh(event) {
  if (event) event.preventDefault();
  const now = Date.now();
  if (now - lastRefreshTriggerAt < 500) return;
  lastRefreshTriggerAt = now;
  refreshAll().catch((error) => console.warn(error));
}

async function refreshAll() {
  const run = async () => {
    const generation = ++refreshGeneration;
    els.refreshButton.disabled = true;
    setStatus('Actualizando...', 'loading');

    try {
      await loadList();
      await loadRubros();
      await loadItems();
      if (generation === refreshGeneration) {
        setStatus('Actualizada', 'ok', formatDateAR(new Date()));
      }
    } catch (error) {
      console.warn(error);
      if (generation === refreshGeneration) {
        setStatus('Error al actualizar', 'error', error.message || 'No se pudo actualizar');
      }
      throw error;
    } finally {
      if (generation === refreshGeneration) {
        els.refreshButton.disabled = false;
      }
    }
  };

  refreshLock = refreshLock.catch(() => {}).then(run);
  return refreshLock;
}

async function addItem(event) {
  event.preventDefault();
  const producto = normalizeProduct(els.nuevoProductoInput.value);
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
    await loadItems();
    setStatus('Actualizada', 'ok', formatDateAR(new Date()));
  } catch (error) {
    console.warn(error);
    setStatus('Error al guardar', 'error', error.message || 'No se pudo actualizar');
  } finally {
    els.addButton.disabled = false;
    els.nuevoProductoInput.focus();
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
    setStatus('Error al excluir', 'error', error.message || 'No se pudo actualizar');
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

function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) return Promise.resolve(null);

  return navigator.serviceWorker.register(`${APP_PUBLIC_PATH}sw.js`, { scope: APP_PUBLIC_PATH })
    .then(async (registration) => {
      serviceWorkerRegistration = registration;
      console.info('Mis Compras PWA: SW registrado', registration.scope);

      registration.addEventListener('updatefound', () => {
        const { installing } = registration;
        if (!installing) return;

        installing.addEventListener('statechange', () => {
          if (installing.state === 'installed' && navigator.serviceWorker.controller) {
            setGateState(
              'update',
              'Actualización obligatoria',
              'Hay una nueva versión disponible. Debés actualizar la aplicación para seguir usando Mis Compras.',
              'La actualización no se puede postergar.',
              'Actualizar ahora',
              requestForcedUpdate
            );
          }
        });
      });

      navigator.serviceWorker.addEventListener('controllerchange', () => {
        const acceptedVersion = state.serverVersion || APP_VERSION;
        markVersionAccepted(acceptedVersion);
        window.location.reload();
      });

      try {
        await navigator.serviceWorker.ready;
        console.info('Mis Compras PWA: PWA ready');
      } catch (error) {
        console.error('Mis Compras PWA: error esperando SW ready', error);
      }

      try {
        await registration.update();
      } catch (error) {
        console.warn('Mis Compras PWA: no se pudo verificar update del SW', error);
      }

      return registration;
    })
    .catch((error) => {
      console.error('Mis Compras PWA: no se pudo registrar el service worker', error);
      return null;
    });
}

async function requestInstall() {
  if (deferredInstallPrompt) {
    deferredInstallPrompt.prompt();
    const choice = await deferredInstallPrompt.userChoice.catch(() => null);
    deferredInstallPrompt = null;
    if (choice?.outcome === 'accepted') {
      markInstalled(state.serverVersion);
      hideGate();
      return;
    }
  }

  const isAppleDevice = /iphone|ipad|ipod/i.test(navigator.userAgent);
  setGateState(
    'install',
    'Instalación obligatoria',
    isAppleDevice
      ? 'En iPhone o iPad instalá la app desde el botón Compartir y la opción "Agregar a pantalla de inicio".'
      : 'Abrí el menú del navegador y elegí "Instalar aplicación" o "Agregar a pantalla de inicio".',
    'La app no se habilita hasta estar instalada como PWA.',
    'Reintentar instalación',
    requestInstall
  );
}

async function requestForcedUpdate() {
  try {
    if (serviceWorkerRegistration) {
      await serviceWorkerRegistration.update();
      if (serviceWorkerRegistration.waiting) {
        serviceWorkerRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
        return;
      }
    }
  } catch (error) {
    console.warn('Mis Compras PWA: update manual falló', error);
  }

  markVersionAccepted(state.serverVersion || APP_VERSION);
  window.location.reload();
}

async function applyPwaPolicy() {
  state.serverVersion = await loadManifestVersion();

  if (!isStandaloneApp() && localStorage.getItem(STORAGE.installed) !== 'true') {
    setGateState(
      'install',
      'Instalación obligatoria',
      'Esta app debe instalarse como PWA antes de usarla por primera vez.',
      'El acceso a la interfaz queda bloqueado hasta instalarla.',
      'Instalar ahora',
      requestInstall
    );
    return;
  }

  if (needsForcedUpdate(state.serverVersion)) {
    setGateState(
      'update',
      'Actualización obligatoria',
      'Hay una nueva versión disponible en el servidor. Debés actualizar antes de seguir.',
      'La app se bloqueará hasta aplicar la actualización.',
      'Actualizar ahora',
      requestForcedUpdate
    );
    return;
  }

  hideGate();
  markInstalled(state.serverVersion);
}

function bindEvents() {
  els.form.addEventListener('submit', addItem);
  els.refreshButton.addEventListener('click', triggerRefresh);
  els.refreshButton.addEventListener('pointerup', triggerRefresh);
  els.refreshButton.addEventListener('touchend', triggerRefresh, { passive: false });
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
  }
async function init() {
  restoreUser();
  bindEvents();

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    localStorage.setItem(STORAGE.installSeen, 'true');
  });

  window.addEventListener('appinstalled', () => {
    markInstalled(state.serverVersion || APP_VERSION);
    hideGate();
    refreshAll().catch(() => {});
  });

  await registerServiceWorker();
  await applyPwaPolicy();

  if (els.gate.hidden) {
    refreshAll().catch(() => {});
  }
}

init();
