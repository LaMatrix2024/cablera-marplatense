import { getApp, getApps, initializeApp } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-app.js";
import {
  browserLocalPersistence,
  getAuth,
  onAuthStateChanged,
  sendPasswordResetEmail,
  setPersistence,
  signInWithEmailAndPassword,
  signOut
} from "https://www.gstatic.com/firebasejs/10.12.4/firebase-auth.js";

export {
  onAuthStateChanged,
  sendPasswordResetEmail,
  signInWithEmailAndPassword,
  signOut
};

export const CABLERA_APP = "CABLERAMARPLATENSE";
export const AUTH_STATE = {
  BOOTSTRAPPING: "BOOTSTRAPPING",
  UNAUTHENTICATED: "UNAUTHENTICATED",
  AUTHENTICATING: "AUTHENTICATING",
  AUTHENTICATED_LOADING_PROFILE: "AUTHENTICATED_LOADING_PROFILE",
  AUTHORIZED: "AUTHORIZED",
  DENIED: "DENIED",
  ERROR: "ERROR"
};

let sharedConfig = null;
let sharedAuth = null;

export const fetchWithTimeout = async (url, options = {}, timeoutMs = 15000) => {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await fetch(url, {
      ...options,
      signal: options.signal || controller.signal
    });
  } catch (error) {
    if (error?.name === "AbortError") {
      throw new Error("La solicitud tardo demasiado y fue cancelada.");
    }
    throw error;
  } finally {
    window.clearTimeout(timeout);
  }
};

export const loadAuthConfig = async (endpoint = "/api/v1/auth/config") => {
  const response = await fetchWithTimeout(endpoint, { headers: { "Accept": "application/json" } }, 10000);
  const body = await response.json().catch(() => ({}));
  if (!response.ok || body.ok === false || !body.firebase?.apiKey) {
    throw new Error(body.message || "No pudimos cargar la configuracion de acceso.");
  }
  sharedConfig = body;
  return sharedConfig;
};

export const getSharedAuth = async (endpoint = "/api/v1/auth/config") => {
  const config = sharedConfig || await loadAuthConfig(endpoint);
  const app = getApps().length ? getApp() : initializeApp(config.firebase);
  sharedAuth = sharedAuth || getAuth(app);
  await setPersistence(sharedAuth, browserLocalPersistence);
  return { auth: sharedAuth, config };
};

export const fetchAuthMe = async ({ auth, config, user, forceRefresh = false }) => {
  if (!user) throw new Error("Sesion no iniciada.");
  const token = await user.getIdToken(forceRefresh);
  const response = await fetchWithTimeout(`${config.apiBaseUrl}/auth/me`, {
    headers: {
      "Authorization": `Bearer ${token}`,
      "Content-Type": "application/json"
    }
  }, 15000);
  const body = await response.json().catch(() => null);
  if (response.status === 401 && !forceRefresh) {
    return fetchAuthMe({ auth, config, user, forceRefresh: true });
  }
  if (!response.ok || body?.ok !== true) {
    const error = new Error(body?.message || body?.error || (body === null ? "Respuesta invalida de /auth/me." : `HTTP ${response.status}`));
    error.status = response.status;
    error.code = body?.error || null;
    error.body = body;
    throw error;
  }
  return { status: response.status, body };
};

export const logAuthMeDiagnostic = ({ firebaseUser, status, body, requiredModule = null, requiredPermission = "puede_ver" }) => {
  const modules = body?.modules ?? [];
  const applications = body?.applications ?? [];
  const required = requiredModule
    ? modules.find((module) => module.aplicacion === CABLERA_APP && module.codigo === requiredModule)
    : null;
  const uid = firebaseUser?.uid || "";
  console.info("LCM auth/me diagnostico", {
    email: firebaseUser?.email || null,
    uid_parcial: uid ? `${uid.slice(0, 8)}...${uid.slice(-4)}` : null,
    status,
    user_id: body?.user?.id ?? null,
    estado: body?.user?.estado ?? null,
    es_superadmin: body?.user?.es_superadmin ?? null,
    tiene_cablera: applications.some((app) => app.codigo === CABLERA_APP),
    modulo_requerido: requiredModule,
    permiso_requerido: requiredPermission,
    resultado: requiredModule ? Boolean(required?.permissions?.[requiredPermission]) : null
  });
};

export const firebaseErrorMessage = (error) => {
  if (error?.code === "auth/invalid-credential") return "Firebase rechazo las credenciales: auth/invalid-credential.";
  if (error?.code === "auth/too-many-requests") return "Demasiados intentos. Proba nuevamente mas tarde.";
  return error?.message || "No pudimos iniciar sesion.";
};
