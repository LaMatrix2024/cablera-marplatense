(() => {
  const timeout = (ms) => new Promise((_, reject) => setTimeout(() => reject(new Error('La operación tardó demasiado. Podés reintentar.')), ms));
  const run = async (operation, options = {}) => {
    const button = options.button;
    if (button) button.disabled = true;
    try { return await Promise.race([Promise.resolve().then(operation), timeout(options.timeout || 60000)]); }
    finally { if (button) button.disabled = false; }
  };
  const download = async (url, options = {}) => {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    if (!response.ok) { const body = await response.json().catch(() => ({})); throw new Error(body?.error?.message || body?.error || 'No se pudo descargar el archivo.'); }
    const blob = await response.blob(); const href = URL.createObjectURL(blob); const anchor = document.createElement('a'); anchor.href = href; anchor.download = options.filename || 'descarga'; anchor.click(); setTimeout(() => URL.revokeObjectURL(href), 1000); return true;
  };
  window.NexoLoading = { run, download, isSessionExpired: (error) => error?.status === 401 };
})();
