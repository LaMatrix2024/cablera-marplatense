/* global navigator, localStorage, window, document */

const state = {
  deferredPrompt: null,
  installModalSeenKey: 'cambio_install_modal_seen_v1',
  lastQuoteKey: 'cambio_last_quote_v1',
  installedKey: 'cambio_install_prompt_completed_v1',
};

const els = {
  statusDot: document.getElementById('status-dot'),
  statusTitle: document.getElementById('status-title'),
  statusText: document.getElementById('status-text'),
  quoteBuy: document.getElementById('quote-buy'),
  quoteSell: document.getElementById('quote-sell-small'),
  shareButton: document.getElementById('share-button'),
  refreshButton: document.getElementById('refresh-button'),
  shareCard: document.getElementById('share-card'),
  installModal: document.getElementById('install-modal'),
  installButton: document.getElementById('install-button'),
  dismissButton: document.getElementById('dismiss-button'),
};

const currencyFormatter = new Intl.NumberFormat('es-AR', {
  style: 'currency',
  currency: 'ARS',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

function formatCurrency(value) {
  const number = Number(value);
  if (Number.isNaN(number)) return '--';
  return currencyFormatter.format(number);
}

function formatDateTime(value) {
  if (!value) return '--';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  const pad = (num) => String(num).padStart(2, '0');
  const day = pad(date.getDate());
  const month = pad(date.getMonth() + 1);
  const year = date.getFullYear();
  const hours = pad(date.getHours());
  const minutes = pad(date.getMinutes());
  return `${day}/${month}/${year} ${hours}:${minutes}`;
}

function setStatus(kind, title, text) {
  const colors = {
    loading: '#94a3b8',
    ok: '#10b981',
    error: '#ef4444',
  };
  els.statusDot.style.background = colors[kind] || colors.loading;
  els.statusDot.style.boxShadow =
    kind === 'loading'
      ? '0 0 0 8px rgba(148, 163, 184, 0.16)'
      : kind === 'error'
        ? '0 0 0 8px rgba(239, 68, 68, 0.14)'
        : '0 0 0 8px rgba(16, 185, 129, 0.12)';
  els.statusTitle.textContent = title;
  els.statusText.textContent = text;
}

function persistQuote(quote) {
  localStorage.setItem(state.lastQuoteKey, JSON.stringify(quote));
}

function loadPersistedQuote() {
  try {
    return JSON.parse(localStorage.getItem(state.lastQuoteKey) || 'null');
  } catch {
    return null;
  }
}

function renderQuote(quote) {
  const existingOfflineBanner = document.querySelector('.offline-banner');
  if (existingOfflineBanner) {
    existingOfflineBanner.remove();
  }

  const buy = formatCurrency(quote?.compra);
  const sell = formatCurrency(quote?.venta);
  const updated = formatDateTime(quote?.fechaActualizacion);

  els.quoteBuy.textContent = buy;
  els.quoteSell.textContent = sell;
  setStatus('ok', 'Actualizado', updated);
}

function renderOfflineFallback() {
  const cached = loadPersistedQuote();
  if (cached) {
    renderQuote(cached);
  }
  const template = document.getElementById('offline-template');
  if (template && !document.querySelector('.offline-banner')) {
    els.shareCard.insertAdjacentElement('afterend', template.content.firstElementChild.cloneNode(true));
  }
  setStatus('error', 'Sin conexión', 'No fue posible consultar la cotización');
}

async function fetchQuote() {
  els.refreshButton.disabled = true;
  setStatus('loading', 'Cargando datos', 'Consultando API oficial');

  try {
    const response = await fetch('https://dolarapi.com/v1/dolares/oficial', {
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const quote = await response.json();
    persistQuote(quote);
    renderQuote(quote);
  } catch (error) {
    console.warn('No se pudo actualizar la cotización', error);
    renderOfflineFallback();
  } finally {
    els.refreshButton.disabled = false;
  }
}

function buildShareText() {
  const cached = loadPersistedQuote() || {};
  return [
    'Cambio - Dólar Oficial',
    `Compra: ${formatCurrency(cached.compra)}`,
    `Venta: ${formatCurrency(cached.venta)}`,
    `Actualizado: ${formatDateTime(cached.fechaActualizacion)}`,
    window.location.href,
  ].join('\n');
}

async function shareQuote() {
  const text = buildShareText();

  try {
    if (navigator.share) {
      await navigator.share({
        title: 'Cambio',
        text,
        url: window.location.href,
      });
      return;
    }
  } catch (error) {
    console.warn('share() falló', error);
  }

  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
      return;
    }
  } catch (error) {
    console.warn('Clipboard falló', error);
  }

  window.prompt('Copiá esta cotización', text);
}

function openInstallModal() {
  if (localStorage.getItem(state.installModalSeenKey) === 'dismissed') return;
  els.installModal.hidden = false;
}

function closeInstallModal(markSeen = true) {
  els.installModal.hidden = true;
  if (markSeen) {
    localStorage.setItem(state.installModalSeenKey, 'dismissed');
  }
}

function registerInstallPrompt() {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    state.deferredPrompt = event;
    openInstallModal();
  });

  window.addEventListener('appinstalled', () => {
    localStorage.setItem(state.installedKey, 'yes');
    closeInstallModal(true);
    state.deferredPrompt = null;
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
    localStorage.setItem(state.installedKey, 'yes');
    state.deferredPrompt = null;
    closeInstallModal(true);
  }
}

function registerServiceWorker() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js').catch((error) => {
      console.warn('SW registration failed', error);
    });
  }
}

function bindEvents() {
  els.shareButton.addEventListener('click', shareQuote);
  els.refreshButton.addEventListener('click', fetchQuote);
  els.installButton.addEventListener('click', handleInstall);
  els.dismissButton.addEventListener('click', () => closeInstallModal(true));
  els.installModal.addEventListener('click', (event) => {
    if (event.target === els.installModal || event.target.classList.contains('install-modal__backdrop')) {
      closeInstallModal(true);
    }
  });
}

function init() {
  registerServiceWorker();
  registerInstallPrompt();
  bindEvents();
  fetchQuote();

  if (localStorage.getItem(state.installedKey) !== 'yes' && 'onbeforeinstallprompt' in window) {
    setTimeout(() => {
      if (state.deferredPrompt) openInstallModal();
    }, 800);
  }
}

init();

window.shareQuote = shareQuote;
