import {
  CABLERA_APP,
  fetchAuthMe,
  fetchWithTimeout,
  firebaseErrorMessage,
  getSharedAuth,
  logAuthMeDiagnostic,
  onAuthStateChanged,
  signInWithEmailAndPassword,
  signOut
} from "/assets/js/lcm-auth-core.js";

const ADMIN_MODULE = "IDENTIDAD_ACCESOS";
const SIDEBAR_KEY = "lcm.identity.sidebar.collapsed";
const loginUrl = () => `/login/?returnTo=${encodeURIComponent(window.location.pathname + window.location.search + window.location.hash)}`;

const NAV_ITEMS = [
  { id: "dashboard", label: "Dashboard", icon: "dashboard", section: "" },
  { id: "users", label: "Usuarios", icon: "group", section: "GESTION" },
  { id: "invitations", label: "Invitaciones", icon: "mail", section: "GESTION" },
  { id: "applications", label: "Aplicaciones", icon: "apps", section: "GESTION" },
  { id: "roles", label: "Roles", icon: "badge", section: "GESTION" },
  { id: "modules", label: "Modulos", icon: "view_module", section: "GESTION" },
  { id: "permissions", label: "Permisos", icon: "rule", section: "GESTION" },
  { id: "audit", label: "Auditoria", icon: "history", section: "GESTION" }
];

const $ = (selector) => document.querySelector(selector);
const state = {
  auth: null,
  user: null,
  idToken: "",
  profile: null,
  config: null,
  view: "dashboard",
  userFilters: {},
  catalogs: { applications: [], roles: [] },
  users: []
};

const escapeHtml = (value) => String(value ?? "")
  .replaceAll("&", "&amp;")
  .replaceAll("<", "&lt;")
  .replaceAll(">", "&gt;")
  .replaceAll('"', "&quot;");

const ICONS = {
  apps: '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/>',
  badge: '<path d="M8 7a4 4 0 1 1 8 0a4 4 0 0 1-8 0Z"/><path d="M5 21a7 7 0 0 1 14 0"/>',
  chevron_right: '<path d="m9 18 6-6-6-6"/>',
  check: '<path d="m5 13 4 4L19 7"/>',
  close: '<path d="m6 6 12 12M18 6 6 18"/>',
  dashboard: '<path d="M4 13h7V4H4zM13 20h7V4h-7zM4 20h7v-5H4z"/>',
  deployed_code: '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9z"/><path d="M12 12 4.5 7.7M12 12l7.5-4.3M12 12v8.5"/>',
  download: '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
  filter_list: '<path d="M4 6h16M7 12h10M10 18h4"/>',
  group: '<path d="M16 11a4 4 0 1 0-8 0a4 4 0 0 0 8 0Z"/><path d="M4 21a8 8 0 0 1 16 0"/><path d="M19 8a3 3 0 0 1 0 6M22 21a6 6 0 0 0-3-5"/>',
  history: '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/>',
  logout: '<path d="M10 17v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2"/><path d="M15 17l5-5-5-5"/><path d="M20 12H9"/>',
  mail: '<path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/>',
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
  notifications: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
  pending_actions: '<path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4"/><path d="M9 13h4"/><path d="M16 18l2 2 4-5"/>',
  person_add: '<path d="M15 8a4 4 0 1 0-8 0a4 4 0 0 0 8 0Z"/><path d="M3 21a8 8 0 0 1 14 0"/><path d="M19 8v6M16 11h6"/>',
  refresh: '<path d="M20 6v6h-6"/><path d="M4 18v-6h6"/><path d="M19 12a7 7 0 0 0-12-5M5 12a7 7 0 0 0 12 5"/>',
  rule: '<path d="M6 3h12v18H6z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
  schedule: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  view_module: '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>'
};

const icon = (name) => `
  <span class="ia-icon" aria-hidden="true">
    <svg viewBox="0 0 24 24" focusable="false">${ICONS[name] || ICONS.dashboard}</svg>
  </span>
`;

const initials = (name, fallback = "CA") => {
  const parts = String(name || fallback).trim().split(/\s+/).filter(Boolean);
  return (parts[0]?.[0] || "C").toUpperCase() + (parts[1]?.[0] || parts[0]?.[1] || "A").toUpperCase();
};

const fullName = (user) => {
  const name = `${user?.nombre || ""} ${user?.apellido || ""}`.trim();
  return name || user?.email || "Usuario";
};

const setAlert = (message, error = false) => {
  const alert = $("#ia-alert");
  if (!message) {
    alert.hidden = true;
    alert.textContent = "";
    alert.classList.remove("is-error");
    return;
  }
  alert.hidden = false;
  alert.textContent = message;
  alert.classList.toggle("is-error", error);
};

const getToken = async () => {
  if (!state.user) throw new Error("Sesion no iniciada.");
  state.idToken = await state.user.getIdToken();
  return state.idToken;
};

const api = async (path, options = {}) => {
  const token = await getToken();
  const response = await fetchWithTimeout(`${state.config.apiBaseUrl}${path}`, {
    ...options,
    headers: {
      "Authorization": `Bearer ${token}`,
      "Content-Type": "application/json",
      ...(options.headers ?? {})
    }
  }, 15000);
  const body = await response.json().catch(() => ({}));
  if (!response.ok || body.ok === false) {
    const error = new Error(body.message || body.error || `HTTP ${response.status}`);
    error.status = response.status;
    error.code = body.error || null;
    throw error;
  }
  return body;
};

const loadConfig = async () => {
  const shared = await getSharedAuth("/api/v1/admin/config");
  state.config = shared.config;
  state.auth = shared.auth;
};

const loadProfile = async () => {
  const result = await fetchAuthMe({
    auth: state.auth,
    config: state.config,
    user: state.user
  });
  logAuthMeDiagnostic({
    firebaseUser: state.user,
    status: result.status,
    body: result.body,
    requiredModule: ADMIN_MODULE
  });
  state.profile = result.body;
  return state.profile;
};

const hasAdminAccess = () => {
  const modules = state.profile?.modules ?? [];
  return modules.some((module) =>
    module.aplicacion === CABLERA_APP
    && module.codigo === ADMIN_MODULE
    && module.permissions?.puede_ver === true
  );
};

const adminModule = () => (state.profile?.modules ?? []).find((module) =>
  module.aplicacion === CABLERA_APP && module.codigo === ADMIN_MODULE && module.permissions?.puede_ver === true
);

const setShellState = () => {
  const isCollapsed = localStorage.getItem(SIDEBAR_KEY) === "1";
  $("#ia-app-shell").classList.toggle("is-collapsed", isCollapsed);
};

const toggleSidebar = () => {
  if (window.matchMedia("(max-width: 760px)").matches) {
    $("#ia-app-shell").classList.toggle("mobile-open", true);
    $("#ia-mobile-backdrop").hidden = false;
    syncBodyScrollLock();
    return;
  }
  const next = !$("#ia-app-shell").classList.contains("is-collapsed");
  $("#ia-app-shell").classList.toggle("is-collapsed", next);
  localStorage.setItem(SIDEBAR_KEY, next ? "1" : "0");
};

const closeMobileSidebar = () => {
  $("#ia-app-shell").classList.remove("mobile-open");
  $("#ia-mobile-backdrop").hidden = !document.body.classList.contains("ia-filters-open");
  syncBodyScrollLock();
};

const openUserFilters = () => {
  document.body.classList.add("ia-filters-open");
  $("#ia-mobile-backdrop").hidden = false;
  syncBodyScrollLock();
};

const closeUserFilters = () => {
  document.body.classList.remove("ia-filters-open");
  $("#ia-mobile-backdrop").hidden = !$("#ia-app-shell").classList.contains("mobile-open");
  syncBodyScrollLock();
};

const syncBodyScrollLock = () => {
  const locked = $("#ia-app-shell")?.classList.contains("mobile-open")
    || document.body.classList.contains("ia-filters-open")
    || $("#ia-drawer")?.hidden === false;
  document.body.classList.toggle("ia-scroll-locked", Boolean(locked));
};

const renderHeader = () => {
  const user = state.profile?.user;
  $("#ia-top-header").innerHTML = `
    <div class="ia-header-left">
      <button class="ia-icon-btn" type="button" data-action="toggle-sidebar" aria-label="Abrir navegacion">
        ${icon("menu")}
      </button>
      <div class="ia-header-title">
        <h1>Identidad y Accesos</h1>
        <p>Administracion corporativa</p>
      </div>
    </div>
    <div class="ia-header-right">
      <button class="ia-icon-btn" type="button" aria-label="Notificaciones">${icon("notifications")}</button>
      <div class="ia-header-user" title="${escapeHtml(user?.email || "")}">
        <span class="ia-user-avatar ia-user-avatar--small">${escapeHtml(initials(fullName(user)))}</span>
      </div>
    </div>
  `;
};

const renderSidebar = () => {
  const user = state.profile?.user;
  const canRenderAdmin = Boolean(adminModule());
  const items = canRenderAdmin ? NAV_ITEMS : [];
  let lastSection = Symbol("none");

  const nav = items.map((item) => {
    const section = item.section !== lastSection && item.section
      ? `<div class="ia-nav-section">${escapeHtml(item.section)}</div>`
      : "";
    lastSection = item.section;
    return `${section}
      <button class="ia-nav-item" type="button" data-view="${item.id}" aria-current="${state.view === item.id ? "page" : "false"}" title="${escapeHtml(item.label)}">
        ${icon(item.icon)}
        <span class="ia-nav-label">${escapeHtml(item.label)}</span>
      </button>`;
  }).join("");

  $("#ia-sidebar").innerHTML = `
    <div class="ia-brand">
      <div class="ia-brand-mark">LCM</div>
      <div class="ia-brand-text">
        <strong>LA CABLERA</strong>
        <span>MARPLATENSE</span>
      </div>
    </div>
    <nav class="ia-sidebar-nav">${nav}</nav>
    <div class="ia-sidebar-footer">
      <div class="ia-profile">
        <div class="ia-avatar">${escapeHtml(initials(fullName(user)))}</div>
        <div class="ia-profile-text">
          <strong>${escapeHtml(fullName(user))}</strong>
          <span>${Number(user?.es_superadmin) === 1 ? "Superadministrador" : "Administrador"}</span>
        </div>
      </div>
      <button class="ia-btn ia-btn--ghost" type="button" data-action="signout">
        ${icon("logout")}
        <span class="ia-nav-label">Cerrar sesion</span>
      </button>
    </div>
  `;
};

const showLogin = () => {
  state.user = null;
  state.idToken = "";
  state.profile = null;
  window.location.href = loginUrl();
  $("#ia-login-panel").hidden = true;
  $("#ia-denied-panel").hidden = true;
  $("#ia-admin-panel").hidden = true;
  renderHeader();
  $("#ia-sidebar").innerHTML = `
    <div class="ia-brand">
      <div class="ia-brand-mark">LCM</div>
      <div class="ia-brand-text"><strong>LA CABLERA</strong><span>MARPLATENSE</span></div>
    </div>
    <nav class="ia-sidebar-nav"></nav>
    <div></div>
  `;
};

const clearSessionState = () => {
  state.user = null;
  state.idToken = "";
  state.profile = null;
  state.users = [];
  state.catalogs = { applications: [], roles: [] };
  setAlert("");
  $("#ia-sidebar").innerHTML = "";
  $("#ia-top-header").innerHTML = "";
  $("#ia-admin-panel").hidden = true;
  $("#ia-denied-panel").hidden = true;
  $("#ia-login-panel").hidden = true;
};

const showDenied = (
  title = "Acceso denegado",
  message = "No tenes permiso para ver IDENTIDAD_ACCESOS. La validacion fue realizada por el backend corporativo."
) => {
  document.documentElement.classList.remove("lcm-auth-booting");
  $("#ia-login-panel").hidden = true;
  $("#ia-admin-panel").hidden = true;
  $("#ia-denied-panel").hidden = false;
  $("#ia-denied-panel h2").textContent = title;
  $("#ia-denied-panel p").textContent = message;
  if (state.profile?.user) {
    renderHeader();
    renderSidebar();
  } else {
    $("#ia-sidebar").innerHTML = "";
    $("#ia-top-header").innerHTML = "";
  }
};

const showAuthValidationError = (error) => {
  const status = error?.status || "error";
  const code = error?.code ? ` ${error.code}` : "";
  const message = error?.message || "Revisar Network/Console.";
  showDenied(
    "No pudimos validar tu sesion",
    `GET /api/v1/auth/me fallo con ${status}${code}. ${message}`
  );
};

const showAdmin = async () => {
  document.documentElement.classList.remove("lcm-auth-booting");
  $("#ia-login-panel").hidden = true;
  $("#ia-denied-panel").hidden = true;
  $("#ia-admin-panel").hidden = false;
  renderHeader();
  renderSidebar();
  await renderView();
};

const handleAuthenticatedUser = async (user) => {
  state.user = user;
  try {
    await loadProfile();
  } catch (error) {
    showAuthValidationError(error);
    return;
  }
  if (!hasAdminAccess()) {
    showDenied();
    return;
  }
  await showAdmin();
};

const statusBadge = (status) => {
  const key = String(status || "").toUpperCase();
  const tone = key === "ACTIVO" || key === "ACEPTADA" ? "ok"
    : key === "BLOQUEADO" || key === "BAJA" || key === "REVOCADA" ? "danger"
    : key ? "warn" : "";
  return `<span class="ia-badge ${tone ? `ia-badge--${tone}` : ""}">${escapeHtml(key || "-")}</span>`;
};

const countBy = (rows, field, allowed) => {
  const counts = Object.fromEntries(allowed.map((key) => [key, 0]));
  rows.forEach((row) => {
    const key = String(row?.[field] || "").toUpperCase();
    if (key in counts) counts[key] += 1;
  });
  return counts;
};

const donut = (title, counts, labels) => {
  const colors = ["#1769aa", "#219653", "#f2b84b", "#c2413b"];
  const total = Object.values(counts).reduce((sum, value) => sum + Number(value || 0), 0);
  let start = 0;
  const stops = Object.keys(labels).map((key, index) => {
    const value = total ? (counts[key] / total) * 100 : 25;
    const segment = `${colors[index]} ${start}% ${start + value}%`;
    start += value;
    return segment;
  }).join(", ");

  return `
    <article class="ia-card">
      <h3>${escapeHtml(title)}</h3>
      <div class="ia-donut-wrap">
        <div class="ia-donut" style="background: conic-gradient(${stops});" aria-hidden="true"></div>
        <div class="ia-legend">
          ${Object.entries(labels).map(([key, label], index) => `
            <div class="ia-legend-row">
              <span><i style="background:${colors[index]}"></i>${escapeHtml(label)}</span>
              <strong>${counts[key] || 0}</strong>
            </div>
          `).join("")}
        </div>
      </div>
    </article>
  `;
};

const renderDashboard = async () => {
  $("#ia-view-dashboard").innerHTML = skeleton("Cargando dashboard...");
  const [dashboard, invitations, users, audit, applications, modules] = await Promise.all([
    api("/admin/dashboard"),
    api("/admin/invitations").catch(() => ({ invitations: [] })),
    api("/admin/users").catch(() => ({ users: [] })),
    api("/admin/audit").catch(() => ({ audit: [] })),
    api("/admin/applications").catch(() => ({ applications: [] })),
    api("/admin/modules").catch(() => ({ modules: [] }))
  ]);

  const summary = dashboard.summary || {};
  const invitationCounts = countBy(invitations.invitations || [], "estado", ["PENDIENTE", "ACEPTADA", "VENCIDA", "REVOCADA"]);
  const userCounts = countBy(users.users || [], "estado", ["ACTIVO", "PENDIENTE", "BLOQUEADO", "BAJA"]);
  const activeApps = (applications.applications || []).filter((app) => Number(app.activo) === 1).length || summary.aplicaciones_activas || 0;
  const activeModules = (modules.modules || []).filter((module) => Number(module.activo) === 1).length || summary.modulos_activos || 0;

  $("#ia-view-dashboard").innerHTML = `
    <div class="ia-page-title">
      <div>
        <h2>Dashboard</h2>
        <p>Resumen general del sistema</p>
      </div>
    </div>
    <section class="ia-kpi-grid">
      ${statCard("group", summary.usuarios_activos ?? userCounts.ACTIVO, "Usuarios activos", "Accesos habilitados")}
      ${statCard("schedule", summary.invitaciones_pendientes ?? invitationCounts.PENDIENTE, "Invitaciones pendientes", "Esperando activacion")}
      ${statCard("apps", activeApps, "Aplicaciones activas", "Disponibles para usuarios")}
      ${statCard("deployed_code", activeModules, "Modulos activos", "Permisos operativos")}
    </section>
    <section class="ia-dashboard-grid">
      ${donut("Invitaciones por estado", invitationCounts, {
        PENDIENTE: "Pendientes",
        ACEPTADA: "Aceptadas",
        VENCIDA: "Vencidas",
        REVOCADA: "Revocadas"
      })}
      ${donut("Usuarios por estado", userCounts, {
        ACTIVO: "Activos",
        PENDIENTE: "Pendientes",
        BLOQUEADO: "Bloqueados",
        BAJA: "Baja"
      })}
      ${activityCard(audit.audit || [])}
    </section>
    <section class="ia-quick-actions" aria-label="Acciones rapidas">
      ${quickAction("person_add", "Invitar usuario", "invitations")}
      ${quickAction("pending_actions", "Ver pendientes", "users", "PENDIENTE")}
      ${quickAction("view_module", "Gestionar modulos", "modules")}
      ${quickAction("rule", "Gestionar permisos", "permissions")}
    </section>
  `;
};

const statCard = (iconName, value, label, secondary) => `
  <article class="ia-card ia-stat-card">
    <div class="ia-stat-top">
      <div class="ia-stat-icon">${icon(iconName)}</div>
    </div>
    <div>
      <strong>${escapeHtml(value ?? 0)}</strong>
      <span>${escapeHtml(label)}</span>
      <small>${escapeHtml(secondary)}</small>
    </div>
  </article>
`;

const quickAction = (iconName, label, view, status = "") => `
  <button class="ia-btn ia-quick-card" type="button" data-view="${escapeHtml(view)}" data-status="${escapeHtml(status)}">
    ${icon(iconName)}
    <span>${escapeHtml(label)}</span>
  </button>
`;

const activityCard = (rows) => `
  <article class="ia-card">
    <h3>Actividad reciente</h3>
    <div class="ia-activity-list">
      ${rows.slice(0, 5).map((row) => `
        <div class="ia-activity-item">
          <div class="ia-activity-icon">${icon("history")}</div>
          <div>
            <strong>${escapeHtml(actionLabel(row.accion))}</strong>
            <small>${escapeHtml(row.actor_email || "Sistema")} · ${escapeHtml(row.created_at || "")}</small>
            <small>${escapeHtml([row.entidad_tipo, row.entidad_id].filter(Boolean).join(" #"))}</small>
          </div>
        </div>
      `).join("") || `<p class="ia-muted">Sin actividad reciente.</p>`}
    </div>
  </article>
`;

const actionLabel = (action) => String(action || "")
  .replaceAll(".", " ")
  .replaceAll("_", " ")
  .replace(/\b\w/g, (letter) => letter.toUpperCase());

const skeleton = (message) => `<section class="ia-card ia-skeleton">${escapeHtml(message)}</section>`;

const loadCatalogs = async () => {
  const catalogs = await api("/admin/catalogs");
  state.catalogs = {
    applications: catalogs.applications || [],
    roles: catalogs.roles || []
  };
};

const renderUsers = async (filters = {}) => {
  state.userFilters = filters;
  $("#ia-view-users").innerHTML = skeleton("Cargando usuarios...");
  await loadCatalogs();
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value) params.set(key, value);
  });
  const data = await api(`/admin/users${params.size ? `?${params}` : ""}`);
  state.users = data.users || [];

  $("#ia-view-users").innerHTML = `
    <div class="ia-toolbar">
      <div>
        <h2>Usuarios</h2>
        <p>Gestiona los usuarios del sistema</p>
      </div>
      <div class="ia-toolbar-actions">
        <button class="ia-btn ia-mobile-filter-trigger" type="button" data-action="open-user-filters">${icon("filter_list")} Filtros</button>
        <button class="ia-btn" type="button" data-action="export-users">${icon("download")} Exportar</button>
        <button class="ia-btn ia-btn--primary" type="button" data-view="invitations">${icon("person_add")} Invitar usuario</button>
      </div>
    </div>
    <form class="ia-filters" id="ia-user-filters">
      <div class="ia-filter-panel-head">
        <strong>Filtros</strong>
        <button class="ia-icon-btn" type="button" data-action="close-user-filters" aria-label="Cerrar filtros">${icon("close")}</button>
      </div>
      <input class="ia-search" name="q" value="${escapeHtml(filters.q || "")}" placeholder="Buscar por nombre, email o cargo..." aria-label="Buscar usuarios">
      <select name="estado" aria-label="Estado">
        ${option("", "Estado: Todos", filters.estado)}
        ${["ACTIVO", "PENDIENTE", "BLOQUEADO", "BAJA"].map((item) => option(item, item, filters.estado)).join("")}
      </select>
      <select name="aplicacion" aria-label="Aplicacion">
        ${option("", "Aplicacion: Todas", filters.aplicacion)}
        ${state.catalogs.applications.map((app) => option(app.codigo, app.nombre || app.codigo, filters.aplicacion)).join("")}
      </select>
      <select name="rol" aria-label="Rol">
        ${option("", "Rol: Todos", filters.rol)}
        ${state.catalogs.roles.map((role) => option(role.codigo, `${role.aplicacion_codigo} / ${role.nombre || role.codigo}`, filters.rol)).join("")}
      </select>
      <div class="ia-filter-actions">
        <button class="ia-btn" type="button" data-action="close-user-filters">Cancelar</button>
        <button class="ia-btn ia-btn--primary" type="submit">${icon("filter_list")} Aplicar</button>
      </div>
    </form>
    ${usersTable(state.users)}
  `;
};

const option = (value, label, selected) =>
  `<option value="${escapeHtml(value)}" ${String(value) === String(selected || "") ? "selected" : ""}>${escapeHtml(label)}</option>`;

const usersTable = (users) => `
  <section class="ia-card ia-table-card">
    <table class="ia-table">
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Estado</th>
          <th>Aplicaciones</th>
          <th>Rol principal</th>
          <th>Ultimo acceso</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        ${users.map(userRow).join("") || `<tr><td colspan="6" class="ia-muted">Sin usuarios para estos filtros.</td></tr>`}
      </tbody>
    </table>
  </section>
`;

const userRow = (user) => {
  const name = fullName(user);
  const role = primaryRole(user.roles);
  return `
    <tr>
      <td data-label="Usuario">
        <div class="ia-user-cell">
          <span class="ia-user-avatar">${escapeHtml(initials(name))}</span>
          <div>
            <div class="ia-user-mobile-head">
              <strong>${escapeHtml(name)}</strong>
              ${statusBadge(user.estado)}
            </div>
            <small>${escapeHtml(user.email)}</small>
            <div class="ia-user-mobile-meta">
              <span>${appBadges(user.aplicaciones)}</span>
              <span>${escapeHtml(role)}</span>
              <span>${escapeHtml(user.ultimo_acceso || "Sin registro")}</span>
            </div>
          </div>
        </div>
      </td>
      <td data-label="Estado">${statusBadge(user.estado)}</td>
      <td data-label="Aplicaciones">${appBadges(user.aplicaciones)}</td>
      <td data-label="Rol principal">${escapeHtml(role)}</td>
      <td data-label="Ultimo acceso"><span class="ia-muted">${escapeHtml(user.ultimo_acceso || "Sin registro")}</span></td>
      <td data-label="Acciones">
        <button class="ia-icon-btn" type="button" data-action="open-user" data-id="${escapeHtml(user.id)}" aria-label="Abrir usuario">
          ${icon("chevron_right")}
        </button>
      </td>
    </tr>
  `;
};

const appBadges = (apps) => {
  const values = String(apps || "").split(",").map((item) => item.trim()).filter(Boolean);
  if (!values.length) return `<span class="ia-muted">Sin apps</span>`;
  return `<div class="ia-app-badges">${values.map((app) => `
    <span class="ia-mini-app" title="${escapeHtml(app)}">${escapeHtml(app.startsWith("PLANTEL") ? "P" : "C")}</span>
  `).join("")}</div>`;
};

const roleLabel = (code) => {
  const raw = String(code || "").trim();
  if (!raw) return "Sin rol";
  const found = state.catalogs.roles.find((role) => role.codigo === raw);
  if (found?.nombre) return found.nombre;
  return raw.toLowerCase().replaceAll("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
};

const primaryRole = (roles) => roleLabel(String(roles || "").split(",")[0]);

const renderPlaceholder = (item) => {
  $("#ia-view-placeholder").innerHTML = `
    <div class="ia-page-title">
      <div>
        <h2>${escapeHtml(item?.label || "Seccion")}</h2>
        <p>Esta pantalla queda para la siguiente fase visual. La API y permisos existentes no se modificaron.</p>
      </div>
    </div>
    <section class="ia-card">
      <h3>Primera iteracion</h3>
      <p class="ia-muted">La entrega actual se limita a Layout, Sidebar, Header, Dashboard y Usuarios.</p>
    </section>
  `;
};

const showViewElement = (id) => {
  document.querySelectorAll(".ia-view").forEach((view) => { view.hidden = true; });
  const elementId = id === "dashboard" || id === "users" ? `ia-view-${id}` : "ia-view-placeholder";
  $(`#${elementId}`).hidden = false;
};

const renderView = async () => {
  setAlert("");
  showViewElement(state.view);
  renderSidebar();
  try {
    if (state.view === "dashboard") await renderDashboard();
    else if (state.view === "users") await renderUsers();
    else renderPlaceholder(NAV_ITEMS.find((item) => item.id === state.view));
  } catch (error) {
    const target = state.view === "users" ? $("#ia-view-users") : $("#ia-view-dashboard");
    target.innerHTML = `
      <section class="ia-card">
        <h3>${state.view === "users" ? "No pudimos cargar los usuarios." : "No pudimos cargar el dashboard."}</h3>
        <p class="ia-muted">${escapeHtml(error.message)}</p>
        <button class="ia-btn" type="button" data-action="retry-view">${icon("refresh")} Reintentar</button>
      </section>
    `;
  }
};

const openUser = async (id) => {
  const data = await api(`/admin/users/${id}`);
  const user = data.user;
  $("#ia-drawer-title").textContent = fullName(user);
  $("#ia-drawer-kicker").textContent = "Usuario";
  $("#ia-drawer-body").innerHTML = `
    <section class="ia-user-detail-head">
      <span class="ia-user-avatar ia-user-avatar--large">${escapeHtml(initials(fullName(user)))}</span>
      <div>
        <h3>${escapeHtml(fullName(user))}</h3>
        <p>${escapeHtml(user.email)}</p>
        ${statusBadge(user.estado)}
      </div>
    </section>
    <section class="ia-card">
      <h3>Perfil</h3>
      <dl class="ia-detail-grid">
        <div><dt>Telefono</dt><dd>${escapeHtml(user.telefono || "-")}</dd></div>
        <div><dt>Cargo</dt><dd>${escapeHtml(user.cargo || "-")}</dd></div>
        <div><dt>Sector</dt><dd>${escapeHtml(user.sector || "-")}</dd></div>
        <div><dt>Fecha alta</dt><dd>${escapeHtml(user.created_at || "-")}</dd></div>
        <div><dt>Ultimo acceso</dt><dd>Sin registro</dd></div>
        <div><dt>Superadmin</dt><dd>${Number(user.es_superadmin) === 1 ? "Si" : "No"}</dd></div>
      </dl>
    </section>
    <section class="ia-card">
      <h3>Aplicaciones y roles</h3>
      <div class="ia-app-role-list">
        ${(data.applications || []).filter((app) => Number(app.activo) === 1).map((app) => {
          const roles = (data.roles || []).filter((role) => Number(role.aplicacion_id) === Number(app.id) && Number(role.activo) === 1);
          return `
            <div class="ia-app-role-item">
              <strong>${escapeHtml(app.codigo)}</strong>
              <span>${escapeHtml(roles.map((role) => role.codigo).join(", ") || "Sin rol")}</span>
              ${icon("chevron_right")}
            </div>
          `;
        }).join("") || `<p class="ia-muted">Sin aplicaciones activas.</p>`}
      </div>
    </section>
    <section class="ia-card">
      <h3>Modulos</h3>
      ${permissionMatrix(data.modules || [])}
    </section>
  `;
  $("#ia-drawer").hidden = false;
  syncBodyScrollLock();
};

const permissionMatrix = (modules) => `
  <div class="ia-permission-table">
    <div class="ia-permission-row ia-permission-head">
      <span>Modulo</span><span>Ver</span><span>Crear</span><span>Editar</span><span>Eliminar</span><span>Aprobar</span><span>Origen</span>
    </div>
    ${modules.map((module) => {
      const permissions = module.effective?.permissions || {};
      const origin = module.origins?.puede_ver || "SIN_PERMISO";
      return `
        <div class="ia-permission-row">
          <strong>${escapeHtml(module.aplicacion_codigo)} / ${escapeHtml(module.codigo)}</strong>
          ${["puede_ver", "puede_crear", "puede_editar", "puede_eliminar", "puede_aprobar"].map((permission) => permDot(permissions[permission])).join("")}
          <span class="ia-badge">${escapeHtml(origin)}</span>
        </div>
      `;
    }).join("") || `<p class="ia-muted">Sin modulos efectivos.</p>`}
  </div>
`;

const permDot = (value) => `<span class="ia-perm-dot ${value ? "is-allowed" : "is-denied"}">${icon(value ? "check" : "close")}</span>`;

const closeDrawer = () => {
  $("#ia-drawer").hidden = true;
  syncBodyScrollLock();
};

document.addEventListener("click", async (event) => {
  const actionTarget = event.target.closest("[data-action]");
  const viewTarget = event.target.closest("[data-view]");
  if (actionTarget) {
    const action = actionTarget.dataset.action;
    if (action === "toggle-sidebar") toggleSidebar();
    if (action === "signout") {
      clearSessionState();
      await signOut(state.auth);
      window.location.href = loginUrl();
    }
    if (action === "retry-view") await renderView();
    if (action === "open-user-filters") openUserFilters();
    if (action === "close-user-filters") closeUserFilters();
    if (action === "open-user") await openUser(actionTarget.dataset.id).catch((error) => setAlert(error.message, true));
    if (action === "export-users") setAlert("La exportacion se conectara en la siguiente iteracion visual.");
    return;
  }
  if (viewTarget) {
    state.view = viewTarget.dataset.view;
    closeMobileSidebar();
    closeUserFilters();
    if (viewTarget.dataset.status) {
      await renderUsers({ estado: viewTarget.dataset.status });
      state.view = "users";
      showViewElement("users");
      renderSidebar();
      return;
    }
    await renderView();
  }
});

document.addEventListener("submit", async (event) => {
  event.preventDefault();
  try {
    if (event.target.id === "ia-login-form") {
      const form = new FormData(event.target);
      const credential = await signInWithEmailAndPassword(state.auth, String(form.get("email")), String(form.get("password")));
      await handleAuthenticatedUser(credential.user);
      return;
    }
    if (event.target.id === "ia-user-filters") {
      const form = new FormData(event.target);
      await renderUsers({
        q: String(form.get("q") || "").trim(),
        estado: String(form.get("estado") || ""),
        aplicacion: String(form.get("aplicacion") || ""),
        rol: String(form.get("rol") || "")
      });
      closeUserFilters();
    }
  } catch (error) {
    setAlert(firebaseErrorMessage(error), true);
  }
});

$("#ia-drawer-close").addEventListener("click", closeDrawer);
$("#ia-mobile-backdrop").addEventListener("click", () => {
  closeMobileSidebar();
  closeUserFilters();
});

const boot = async () => {
  try {
    setShellState();
    await loadConfig();
    onAuthStateChanged(state.auth, async (user) => {
      try {
        if (!user) {
          window.location.href = loginUrl();
          return;
        }
        await handleAuthenticatedUser(user);
      } catch (error) {
        setAlert(error.message, true);
        showAuthValidationError(error);
      }
    });
  } catch (error) {
    document.documentElement.classList.remove("lcm-auth-booting");
    $("#ia-login-panel").innerHTML = `<section class="ia-card"><h2>No pudimos iniciar el administrador.</h2><p>${escapeHtml(error.message)}</p></section>`;
  }
};

void boot();
