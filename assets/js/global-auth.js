import {
  AUTH_STATE,
  CABLERA_APP,
  fetchAuthMe as fetchCorporateProfile,
  firebaseErrorMessage,
  getSharedAuth,
  logAuthMeDiagnostic,
  onAuthStateChanged,
  sendPasswordResetEmail,
  signInWithEmailAndPassword,
  signOut
} from "./lcm-auth-core.js";

const SIDEBAR_KEY = "lcm.global.sidebar.collapsed";

const ICONS = {
  apps: '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/>',
  badge: '<path d="M8 7a4 4 0 1 1 8 0a4 4 0 0 1-8 0Z"/><path d="M5 21a7 7 0 0 1 14 0"/>',
  chart: '<path d="M4 19V5"/><path d="M4 19h16"/><path d="M8 16v-5M12 16V8M16 16v-8"/>',
  dashboard: '<path d="M4 13h7V4H4zM13 20h7V4h-7zM4 20h7v-5H4z"/>',
  file: '<path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4"/>',
  group: '<path d="M16 11a4 4 0 1 0-8 0a4 4 0 0 0 8 0Z"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  home: '<path d="m3 11 9-8 9 8"/><path d="M5 10v11h14V10"/><path d="M10 21v-6h4v6"/>',
  logout: '<path d="M10 17v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2"/><path d="M15 17l5-5-5-5"/><path d="M20 12H9"/>',
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
  phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.7 2.6a2 2 0 0 1-.5 2.1L8.1 9.6a16 16 0 0 0 6.3 6.3l1.2-1.2a2 2 0 0 1 2.1-.5c.8.3 1.7.6 2.6.7A2 2 0 0 1 22 16.9Z"/>',
  settings: '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1A2 2 0 1 1 4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1A2 2 0 1 1 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5h.1a1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
  wrench: '<path d="m14.7 6.3 3 3"/><path d="M7 21l10.6-10.6a4 4 0 0 0-5.7-5.7L11 5.6l2.8 2.8-.9.9L10.1 6.5 3 13.6V21z"/>'
};

const MODULE_REGISTRY = {
  GERENCIA: { label: "Gerencia", route: "/gerencia/", icon: "chart", section: "GESTION" },
  TELEFONIA: { label: "Telefonia", route: "/telefonia/", icon: "phone", section: "GESTION" },
  TELEFONIA_PRODUCCION_PLANTA: { label: "Produccion Planta", route: "/telefonia/produccion_planta/", icon: "chart", section: "TELEFONIA" },
  TELEFONIA_PRODUCCION_B2B: { label: "Produccion B2B", route: "/telefonia/produccion_b2b/", icon: "chart", section: "TELEFONIA" },
  TELEFONIA_PRODUCCION_INSTALACIONES: { label: "Produccion Instalaciones", route: "/telefonia/produccion_instalaciones/", icon: "chart", section: "TELEFONIA" },
  TELEFONIA_ECONOMICO: { label: "Informe economico", route: "/telefonia/economico/", icon: "file", section: "TELEFONIA" },
  TELEFONIA_PRECIARIO_TMA: { label: "Preciario TMA", route: "/telefonia/preciario_tma/", icon: "file", section: "TELEFONIA" },
  TELEFONIA_CONTROL_LOGICAS: { label: "Control Logicas", route: "/telefonia/control_logicas/", icon: "settings", section: "TELEFONIA" },
  TELEFONIA_TRACKING_TIRONES: { label: "Tracking Tirones", route: "/telefonia/tracking_tirones/", icon: "chart", section: "TELEFONIA" },
  OBRAS: { label: "Obras", route: "/obras/", icon: "wrench", section: "GESTION" },
  RRHH: { label: "RRHH", route: "/rrhh/", icon: "group", section: "GESTION" },
  CONTABLE: { label: "Contable", route: "/contable/", icon: "file", section: "GESTION" },
  MANTENIMIENTO: { label: "Mantenimiento", route: "/mantenimiento/", icon: "wrench", section: "GESTION" },
  LICITACIONES: { label: "Licitaciones", route: "/licitaciones/", icon: "file", section: "GESTION" },
  IDENTIDAD_ACCESOS: { label: "Identidad y Accesos", route: "/admin/identidad-accesos/", icon: "badge", section: "ADMINISTRACION" }
};

const ROUTE_GUARDS = [
  ["/admin/identidad-accesos/", "IDENTIDAD_ACCESOS"],
  ["/telefonia/produccion_planta", "TELEFONIA_PRODUCCION_PLANTA"],
  ["/telefonia/produccion_b2b", "TELEFONIA_PRODUCCION_B2B"],
  ["/telefonia/produccion_instalaciones", "TELEFONIA_PRODUCCION_INSTALACIONES"],
  ["/telefonia/economico", "TELEFONIA_ECONOMICO"],
  ["/telefonia/preciario_tma", "TELEFONIA_PRECIARIO_TMA"],
  ["/telefonia/control_logicas", "TELEFONIA_CONTROL_LOGICAS"],
  ["/telefonia/tracking_tirones", "TELEFONIA_TRACKING_TIRONES"],
  ["/telefonia/menu.php", "TELEFONIA"],
  ["/telefonia/", "TELEFONIA"],
  ["/gerencia/", "GERENCIA"],
  ["/obras/", "OBRAS"],
  ["/rrhh/", "RRHH"],
  ["/contable/", "CONTABLE"],
  ["/mantenimiento/", "MANTENIMIENTO"],
  ["/licitaciones/", "LICITACIONES"]
];

const state = { auth: null, config: null, profile: null, user: null, authState: AUTH_STATE.BOOTSTRAPPING };
const $ = (selector) => document.querySelector(selector);
const escapeHtml = (value) => String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;");
const icon = (name) => `<span class="lcm-global-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false">${ICONS[name] || ICONS.dashboard}</svg></span>`;
const fullName = (user) => `${user?.nombre || ""} ${user?.apellido || ""}`.trim() || user?.email || "Usuario";
const initials = (name) => {
  const parts = String(name || "US").trim().split(/\s+/).filter(Boolean);
  return ((parts[0]?.[0] || "U") + (parts[1]?.[0] || parts[0]?.[1] || "S")).toUpperCase();
};
const loginUrl = () => `/login/?returnTo=${encodeURIComponent(window.location.pathname + window.location.search + window.location.hash)}`;
const returnTo = () => new URLSearchParams(window.location.search).get("returnTo") || "/";

const requiredModuleForPath = (path) => {
  const normalized = path === "/telefonia" ? "/telefonia/" : path;
  const match = [...ROUTE_GUARDS].sort((a, b) => b[0].length - a[0].length)
    .find(([prefix]) => normalized === prefix || normalized.startsWith(prefix));
  return match?.[1] || null;
};

const showStatus = (title, message) => {
  const panel = $("#lcm-global-status");
  if (!panel) return;
  panel.hidden = false;
  panel.innerHTML = `<section class="lcm-global-status-card"><h2>${escapeHtml(title)}</h2><p>${escapeHtml(message)}</p></section>`;
};
const hideStatus = () => { const panel = $("#lcm-global-status"); if (panel) panel.hidden = true; };

const setAuthState = (next) => {
  state.authState = next;
  document.body?.setAttribute("data-auth-state", next);
};

const clearCorporateState = () => {
  state.user = null;
  state.profile = null;
  const sidebar = $("#lcm-global-sidebar");
  const header = $("#lcm-global-header");
  if (sidebar) sidebar.innerHTML = "";
  if (header) header.innerHTML = "";
};

const loadConfig = async () => {
  const shared = await getSharedAuth("/api/v1/auth/config");
  state.config = shared.config;
  state.auth = shared.auth;
};

const fetchAuthMe = async (forceRefresh = false) => {
  const result = await fetchCorporateProfile({ auth: state.auth, config: state.config, user: state.user, forceRefresh });
  logAuthMeDiagnostic({
    firebaseUser: state.user,
    status: result.status,
    body: result.body,
    requiredModule: requiredModuleForPath(window.location.pathname)
  });
  state.profile = result.body;
  return result.body;
};

const cableraModules = () => (state.profile?.modules || []).filter((module) =>
  module.aplicacion === CABLERA_APP && module.permissions?.puede_ver === true
);
const canViewModule = (code) => !code || cableraModules().some((module) => module.codigo === code);

const renderSidebar = () => {
  const sidebar = $("#lcm-global-sidebar");
  const header = $("#lcm-global-header");
  if (!sidebar || !header) return;
  const user = state.profile?.user || {};
  const items = [
    { codigo: "HOME", label: "Dashboard", route: "/", icon: "home", section: "INICIO" },
    ...cableraModules().map((module) => ({
      codigo: module.codigo,
      label: MODULE_REGISTRY[module.codigo]?.label || module.nombre || module.codigo,
      route: MODULE_REGISTRY[module.codigo]?.route || "/",
      icon: MODULE_REGISTRY[module.codigo]?.icon || "apps",
      section: MODULE_REGISTRY[module.codigo]?.section || "GESTION"
    }))
  ];
  let lastSection = "";
  const nav = items.map((item) => {
    const section = item.section !== lastSection ? `<div class="lcm-global-nav-section">${escapeHtml(item.section)}</div>` : "";
    lastSection = item.section;
    const current = window.location.pathname === item.route || (item.route !== "/" && window.location.pathname.startsWith(item.route));
    return `${section}<a class="lcm-global-nav-link" href="${escapeHtml(item.route)}" ${current ? 'aria-current="page"' : ""} title="${escapeHtml(item.label)}">${icon(item.icon)}<span class="lcm-global-nav-label">${escapeHtml(item.label)}</span></a>`;
  }).join("");

  sidebar.innerHTML = `
    <div class="lcm-global-brand"><div class="lcm-global-mark">LCM</div><div class="lcm-global-brand-text"><strong>LA CABLERA</strong><span>MARPLATENSE</span></div></div>
    <nav class="lcm-global-nav" aria-label="Navegacion principal">${nav}</nav>
    <div class="lcm-global-footer">
      <div class="lcm-global-profile"><div class="lcm-global-avatar">${escapeHtml(initials(fullName(user)))}</div><div class="lcm-global-profile-text"><strong>${escapeHtml(fullName(user))}</strong><span>${Number(user.es_superadmin) === 1 ? "Superadministrador" : "Usuario corporativo"}</span></div></div>
      <button class="lcm-global-signout" type="button" data-lcm-action="signout">${icon("logout")}<span class="lcm-global-nav-label">Cerrar sesion</span></button>
    </div>`;
  header.innerHTML = `
    <div class="lcm-global-header-left"><button class="lcm-global-icon-btn" type="button" data-lcm-action="toggle-sidebar" aria-label="Abrir navegacion">${icon("menu")}</button><div class="lcm-global-header-title"><strong>La Cablera Marplatense</strong><span>Plataforma corporativa</span></div></div>
    <div class="lcm-global-header-right"><span class="lcm-global-avatar">${escapeHtml(initials(fullName(user)))}</span></div>`;
};

const setLoginUi = (next, message = "", isError = false) => {
  setAuthState(next);
  const form = $("#lcm-login-form");
  const links = $(".lcm-login-links");
  const alert = $("#lcm-login-alert");
  const status = $("#lcm-login-status");
  const showForm = next === AUTH_STATE.UNAUTHENTICATED || next === AUTH_STATE.ERROR;
  if (form) form.hidden = !showForm;
  if (links) links.hidden = !showForm;
  if (alert) {
    alert.hidden = !isError || !message;
    alert.textContent = isError ? message : "";
  }
  if (status) {
    status.hidden = showForm || !message || isError;
    status.innerHTML = showForm || !message || isError ? "" : `<span class="lcm-spinner" aria-hidden="true"></span><span>${escapeHtml(message)}</span>`;
  }
  document.documentElement.classList.toggle("lcm-auth-booting", next === AUTH_STATE.BOOTSTRAPPING);
};

const renderHome = () => {
  const target = $("#lcm-home-authorized");
  if (!target) return;
  const modules = cableraModules().map((module) => ({
    label: MODULE_REGISTRY[module.codigo]?.label || module.nombre || module.codigo,
    route: MODULE_REGISTRY[module.codigo]?.route || "/",
    description: module.descripcion || "Modulo autorizado.",
    icon: MODULE_REGISTRY[module.codigo]?.icon || "apps"
  })).filter((module) => module.route !== "/");
  target.innerHTML = modules.map((module) => `<a class="lcm-home-card" href="${escapeHtml(module.route)}">${icon(module.icon)}<strong>${escapeHtml(module.label)}</strong><p>${escapeHtml(module.description)}</p></a>`).join("")
    || `<section class="lcm-home-card"><strong>Sin modulos</strong><p>No tenes modulos autorizados para La Cablera.</p></section>`;
};

const showDenied = () => {
  const main = $("main");
  if (main) main.innerHTML = `<section class="lcm-denied-card"><h1>No tenes acceso a este modulo.</h1><p>Tu usuario esta autenticado, pero Cablera no autoriza esta ruta.</p><a class="lcm-denied-btn" href="/">Volver al inicio</a></section>`;
  hideStatus();
  document.documentElement.classList.remove("lcm-auth-booting");
};

const closeMobileSidebar = () => {
  document.body.classList.remove("lcm-mobile-sidebar-open", "lcm-scroll-locked");
  const backdrop = $("#lcm-global-backdrop");
  if (backdrop) backdrop.hidden = true;
};

const toggleSidebar = () => {
  if (window.matchMedia("(max-width: 900px)").matches) {
    document.body.classList.add("lcm-mobile-sidebar-open", "lcm-scroll-locked");
    $("#lcm-global-backdrop").hidden = false;
    return;
  }
  const collapsed = !document.body.classList.contains("lcm-sidebar-collapsed");
  document.body.classList.toggle("lcm-sidebar-collapsed", collapsed);
  localStorage.setItem(SIDEBAR_KEY, collapsed ? "1" : "0");
};

const initShellEvents = () => {
  document.addEventListener("click", async (event) => {
    const action = event.target.closest("[data-lcm-action]")?.dataset.lcmAction;
    if (action === "toggle-sidebar") toggleSidebar();
    if (action === "signout") {
      clearCorporateState();
      await signOut(state.auth);
      window.location.href = loginUrl();
    }
    if (event.target.closest(".lcm-global-nav-link")) closeMobileSidebar();
  });
  $("#lcm-global-backdrop")?.addEventListener("click", closeMobileSidebar);
};

const initLoginPage = async () => {
  const alert = $("#lcm-login-alert");
  const form = $("#lcm-login-form");
  const setAlert = (message) => {
    if (!alert) return;
    alert.hidden = !message;
    alert.textContent = message || "";
  };
  setLoginUi(AUTH_STATE.BOOTSTRAPPING, "Cargando sesion...");
  await loadConfig();
  onAuthStateChanged(state.auth, async (user) => {
    if (!user) {
      clearCorporateState();
      setLoginUi(AUTH_STATE.UNAUTHENTICATED);
      document.documentElement.classList.remove("lcm-auth-booting");
      return;
    }
    state.user = user;
    try {
      setLoginUi(AUTH_STATE.AUTHENTICATED_LOADING_PROFILE, "Validando acceso...");
      await fetchAuthMe();
      setLoginUi(AUTH_STATE.AUTHORIZED, "Ingresando...");
      window.location.href = returnTo();
    } catch (error) {
      setLoginUi(AUTH_STATE.ERROR, error.status ? `Sesion autenticada, pero /auth/me fallo con HTTP ${error.status}.` : error.message, true);
    }
  });
  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    setAlert("");
    const data = new FormData(form);
    try {
      setLoginUi(AUTH_STATE.AUTHENTICATING, "Autenticando...");
      const credential = await signInWithEmailAndPassword(state.auth, String(data.get("email")), String(data.get("password")));
      state.user = credential.user;
      setLoginUi(AUTH_STATE.AUTHENTICATED_LOADING_PROFILE, "Validando acceso...");
      await fetchAuthMe();
      setLoginUi(AUTH_STATE.AUTHORIZED, "Ingresando...");
      window.location.href = returnTo();
    } catch (error) {
      setLoginUi(AUTH_STATE.ERROR, firebaseErrorMessage(error), true);
    }
  });
  $("#lcm-reset-password")?.addEventListener("click", async () => {
    const email = String(new FormData(form).get("email") || "").trim();
    if (!email) {
      setAlert("Ingresa tu correo para enviar el restablecimiento.");
      return;
    }
    try {
      await sendPasswordResetEmail(state.auth, email);
      setAlert("Te enviamos un correo para restablecer la contrasena.");
    } catch (error) {
      setAlert(firebaseErrorMessage(error));
    }
  });
};

const initProtectedPage = async () => {
  setAuthState(AUTH_STATE.BOOTSTRAPPING);
  showStatus("Cargando sesion", "Estamos validando tu acceso corporativo.");
  if (localStorage.getItem(SIDEBAR_KEY) === "1") document.body.classList.add("lcm-sidebar-collapsed");
  initShellEvents();
  await loadConfig();
  onAuthStateChanged(state.auth, async (user) => {
    if (!user) {
      clearCorporateState();
      setAuthState(AUTH_STATE.UNAUTHENTICATED);
      window.location.href = loginUrl();
      return;
    }
    state.user = user;
    try {
      setAuthState(AUTH_STATE.AUTHENTICATED_LOADING_PROFILE);
      showStatus("Validando permisos", "Consultando el perfil corporativo.");
      await fetchAuthMe();
      renderSidebar();
      if (!canViewModule(requiredModuleForPath(window.location.pathname))) {
        setAuthState(AUTH_STATE.DENIED);
        showDenied();
        return;
      }
      renderHome();
      setAuthState(AUTH_STATE.AUTHORIZED);
      hideStatus();
      document.documentElement.classList.remove("lcm-auth-booting");
    } catch (error) {
      setAuthState(AUTH_STATE.ERROR);
      const message = error.message === "Respuesta invalida de /auth/me."
        ? "GET /api/v1/auth/me no devolvio un perfil corporativo valido."
        : (error.status ? `GET /api/v1/auth/me fallo con HTTP ${error.status}.` : "No pudimos conectar con el servicio de autenticacion.");
      showStatus("No pudimos validar tu sesion", message);
      document.documentElement.classList.remove("lcm-auth-booting");
    }
  });
};

if (document.body.classList.contains("lcm-login-page") && $("#lcm-login-form")) {
  void initLoginPage();
} else if (document.body.classList.contains("lcm-page--with-nav")) {
  void initProtectedPage();
}
