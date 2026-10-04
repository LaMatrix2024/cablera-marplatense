export const CABLERA_APP = 'CABLERAMARPLATENSE';
export const AUTH_STATE = { BOOTSTRAPPING: 'BOOTSTRAPPING', UNAUTHENTICATED: 'UNAUTHENTICATED', AUTHENTICATING: 'AUTHENTICATING', AUTHENTICATED_LOADING_PROFILE: 'AUTHENTICATED_LOADING_PROFILE', AUTHORIZED: 'AUTHORIZED', DENIED: 'DENIED', ERROR: 'ERROR' };
let currentCsrf = sessionStorage.getItem('lcm.csrf') || '';
let currentUser = null;
let currentProfile = null;

export const fetchWithTimeout = async (url, options = {}, timeoutMs = 5000) => {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await fetch(url, { credentials: 'same-origin', ...options, signal: options.signal || controller.signal });
  } catch (error) {
    if (error?.name === 'AbortError') throw Object.assign(new Error('La solicitud tardó demasiado y fue cancelada.'), { code: 'timeout' });
    throw error;
  } finally { window.clearTimeout(timeout); }
};
export const loadAuthConfig = async () => ({ apiBaseUrl: '', central: true });
export const getSharedAuth = async () => ({ auth: {}, config: await loadAuthConfig() });
export const fetchAuthMe = async () => {
  const response = await fetchWithTimeout('/api/v1/auth/me', { headers: { Accept: 'application/json' } }, 5000);
  const body = await response.json().catch(() => null);
  if (!response.ok || body?.ok !== true) {
    const error = new Error(body?.error?.message || 'No pudimos validar la sesión.');
    error.status = response.status; error.code = body?.error?.code; error.body = body; throw error;
  }
  currentProfile = body.data;
  currentUser = body.data?.user || null;
  sessionStorage.setItem('lcm.authenticated', '1');
  return { status: response.status, body: body.data, meta: body.meta || {} };
};
export const onAuthStateChanged = (auth, callback) => {
  void fetchAuthMe().then(profile => callback(currentUser, profile.body, profile.meta || {})).catch(error => {
    // Un timeout no equivale a una sesión inválida. El backend conserva la sesión local.
    if (error?.code === 'timeout' && sessionStorage.getItem('lcm.authenticated') === '1' && currentUser && currentProfile) {
      callback(currentUser, currentProfile, { permissions_stale: true }); return;
    }
    currentUser = null; currentProfile = null;
    if (error?.status === 401) sessionStorage.removeItem('lcm.authenticated');
    callback(null, null, { error });
  });
  return () => {};
};
export const signInWithEmailAndPassword = async (auth, email, password) => {
  const response = await fetchWithTimeout('/api/v1/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: String(email).trim().toLowerCase(), password }) }, 5000);
  const body = await response.json().catch(() => null);
  if (!response.ok || body?.ok !== true) { const error = new Error(body?.error?.message || 'No pudimos iniciar sesión.'); error.code = body?.error?.code; throw error; }
  currentCsrf = body.data.csrf_token; sessionStorage.setItem('lcm.csrf', currentCsrf); sessionStorage.setItem('lcm.authenticated', '1');
  return { user: { email: String(email).trim().toLowerCase() } };
};
export const signOut = async () => {
  if (currentCsrf) await fetchWithTimeout('/api/v1/auth/logout', { method: 'POST', headers: { 'X-CSRF-Token': currentCsrf, Accept: 'application/json' } }, 5000).catch(() => {});
  currentCsrf = ''; currentUser = null; currentProfile = null; sessionStorage.removeItem('lcm.csrf'); sessionStorage.removeItem('lcm.authenticated');
};
export const sendPasswordResetEmail = async () => { throw new Error('La recuperación por correo se habilitará mediante la identidad central.'); };
export const firebaseErrorMessage = error => error?.message || 'No pudimos iniciar sesión.';
export const logAuthMeDiagnostic = ({ status, body, requiredModule = null }) => console.info('LCM auth/me central', { status, user_id: body?.user?.id ?? null, estado: body?.user?.estado ?? null, es_superadmin: body?.user?.es_superadmin ?? null, modulo_requerido: requiredModule });
