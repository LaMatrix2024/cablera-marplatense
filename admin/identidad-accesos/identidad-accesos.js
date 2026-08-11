import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.4/firebase-app.js";
import {
  getAuth,
  onAuthStateChanged,
  setPersistence,
  browserLocalPersistence,
  signInWithEmailAndPassword,
  signOut
} from "https://www.gstatic.com/firebasejs/10.12.4/firebase-auth.js";

const PERMS = ["puede_ver", "puede_crear", "puede_editar", "puede_eliminar", "puede_exportar", "puede_aprobar"];
const PERM_LABELS = {
  puede_ver: "Ver",
  puede_crear: "Crear",
  puede_editar: "Editar",
  puede_eliminar: "Eliminar",
  puede_exportar: "Exportar",
  puede_aprobar: "Aprobar"
};
const CRITICAL = new Set(["BLOQUEADO", "BAJA", "deactivate-app", "deactivate-module", "revoke-invitation", "remove-superadmin"]);

const $ = (selector) => document.querySelector(selector);
const state = {
  auth: null,
  user: null,
  idToken: "",
  tab: "dashboard",
  catalogs: { applications: [], roles: [], modules: [] },
  cache: {}
};

const escapeHtml = (value) => String(value ?? "")
  .replaceAll("&", "&amp;")
  .replaceAll("<", "&lt;")
  .replaceAll(">", "&gt;")
  .replaceAll('"', "&quot;");

const badge = (value) => {
  const text = String(value ?? "");
  const tone = ["ACTIVO", "ACEPTADA", "1", "true"].includes(text) ? "ok"
    : ["BLOQUEADO", "BAJA", "REVOCADA", "0", "false"].includes(text) ? "danger"
    : "warn";
  return `<span class="ia-badge ia-badge--${tone}">${escapeHtml(text)}</span>`;
};

const setAlert = (message, error = false) => {
  const alert = $("#ia-alert");
  if (!message) {
    alert.hidden = true;
    alert.textContent = "";
    return;
  }
  alert.hidden = false;
  alert.textContent = message;
  alert.style.borderColor = error ? "rgba(255,88,88,.55)" : "";
};

const api = async (path, options = {}) => {
  if (!state.idToken) throw new Error("Sesion no iniciada.");
  const response = await fetch(`${state.config.apiBaseUrl}${path}`, {
    ...options,
    headers: {
      "Authorization": `Bearer ${state.idToken}`,
      "Content-Type": "application/json",
      ...(options.headers ?? {})
    }
  });
  const body = await response.json().catch(() => ({}));
  if (!response.ok || body.ok === false) {
    throw new Error(body.message || body.error || `HTTP ${response.status}`);
  }
  return body;
};

const apiJson = (method, path, payload) => api(path, { method, body: JSON.stringify(payload) });

const refreshToken = async () => {
  if (!state.user) return;
  state.idToken = await state.user.getIdToken();
};

const loadConfig = async () => {
  const response = await fetch("/api/v1/admin/config");
  const config = await response.json();
  if (!config.ok || !config.firebase?.apiKey) {
    throw new Error("Falta configurar FIREBASE_WEB_API_KEY para el administrador.");
  }
  state.config = config;
  const app = initializeApp(config.firebase);
  state.auth = getAuth(app);
  await setPersistence(state.auth, browserLocalPersistence);
};

const loadCatalogs = async () => {
  state.catalogs = await api("/admin/catalogs");
};

const setAuthenticated = async (user) => {
  state.user = user;
  await refreshToken();
  $("#ia-login-panel").hidden = true;
  $("#ia-admin-panel").hidden = false;
  $("#ia-signout").hidden = false;
  $("#ia-session-label").textContent = user.email || "Sesion activa";
  await loadCatalogs();
  await renderActiveTab();
};

const setUnauthenticated = () => {
  state.user = null;
  state.idToken = "";
  $("#ia-login-panel").hidden = false;
  $("#ia-admin-panel").hidden = true;
  $("#ia-signout").hidden = true;
  $("#ia-session-label").textContent = "Sin sesion";
};

const table = (headers, rows) => `
  <div class="ia-table-wrap">
    <table class="ia-table">
      <thead><tr>${headers.map((h) => `<th>${escapeHtml(h)}</th>`).join("")}</tr></thead>
      <tbody>${rows.join("") || `<tr><td colspan="${headers.length}" class="ia-muted">Sin registros.</td></tr>`}</tbody>
    </table>
  </div>`;

const renderDashboard = async () => {
  const data = await api("/admin/dashboard");
  const s = data.summary;
  $("#ia-view-dashboard").innerHTML = `
    <div class="ia-kpi-grid">
      ${[
        ["Usuarios activos", s.usuarios_activos],
        ["Usuarios pendientes", s.usuarios_pendientes],
        ["Usuarios bloqueados", s.usuarios_bloqueados],
        ["Invitaciones pendientes", s.invitaciones_pendientes],
        ["Invitaciones vencidas", s.invitaciones_vencidas],
        ["Aplicaciones activas", s.aplicaciones_activas],
        ["Modulos activos", s.modulos_activos]
      ].map(([label, value]) => `<article class="ia-kpi"><small>${label}</small><strong>${value}</strong></article>`).join("")}
    </div>
    <section class="ia-card">
      <h2>Acciones rapidas</h2>
      <div class="ia-actions">
        <button class="ia-btn ia-btn--primary" data-action="quick-invite">Invitar usuario</button>
        <button class="ia-btn" data-tab-jump="invitations">Ver pendientes</button>
        <button class="ia-btn" data-tab-jump="modules">Administrar modulos</button>
        <button class="ia-btn" data-tab-jump="permissions">Administrar permisos</button>
      </div>
    </section>`;
};

const appOptions = (selected = "") => state.catalogs.applications
  .map((app) => `<option value="${app.id}" ${String(app.id) === String(selected) ? "selected" : ""}>${escapeHtml(app.codigo)}</option>`)
  .join("");

const renderUsers = async () => {
  const data = await api("/admin/users");
  state.cache.users = data.users;
  $("#ia-view-users").innerHTML = `
    <div class="ia-toolbar">
      <h2>Usuarios</h2>
      <div class="ia-filters">
        <input id="user-q" placeholder="Buscar">
        <select id="user-status"><option value="">Estado</option><option>ACTIVO</option><option>PENDIENTE</option><option>BLOQUEADO</option><option>BAJA</option></select>
        <button class="ia-btn" data-action="filter-users">Filtrar</button>
      </div>
    </div>
    ${usersTable(data.users)}`;
};

const usersTable = (users) => table(
  ["Nombre", "Email", "Estado", "Apps", "Roles", "Superadmin", "Alta", ""],
  users.map((u) => `<tr>
    <td><strong>${escapeHtml(`${u.nombre || ""} ${u.apellido || ""}`.trim() || "-")}</strong></td>
    <td>${escapeHtml(u.email)}</td>
    <td>${badge(u.estado)}</td>
    <td>${escapeHtml(u.aplicaciones || "-")}</td>
    <td>${escapeHtml(u.roles || "-")}</td>
    <td>${Number(u.es_superadmin) === 1 ? badge("SI") : "No"}</td>
    <td>${escapeHtml(u.created_at)}</td>
    <td><button class="ia-btn" data-action="open-user" data-id="${u.id}">Abrir</button></td>
  </tr>`)
);

const filterUsers = async () => {
  const params = new URLSearchParams();
  const q = $("#user-q").value.trim();
  const estado = $("#user-status").value;
  if (q) params.set("q", q);
  if (estado) params.set("estado", estado);
  const data = await api(`/admin/users?${params}`);
  $("#ia-view-users .ia-table-wrap").outerHTML = usersTable(data.users);
};

const renderInvitations = async () => {
  const data = await api("/admin/invitations");
  $("#ia-view-invitations").innerHTML = `
    <div class="ia-toolbar">
      <h2>Invitaciones</h2>
      <button class="ia-btn ia-btn--primary" data-action="new-invitation">Crear invitacion</button>
    </div>
    ${table(["Email", "Estado", "Vence", "Creada", ""], data.invitations.map((i) => `<tr>
      <td>${escapeHtml(i.email)}</td>
      <td>${badge(i.estado)}</td>
      <td>${escapeHtml(i.expires_at)}</td>
      <td>${escapeHtml(i.created_at)}</td>
      <td class="ia-actions">
        <button class="ia-btn" data-action="open-invitation" data-id="${i.id}">Detalle</button>
        ${i.estado === "PENDIENTE" ? `<button class="ia-btn ia-btn--danger" data-action="revoke-invitation" data-id="${i.id}">Revocar</button>` : ""}
      </td>
    </tr>`))}`;
};

const renderApplications = async () => {
  const data = await api("/admin/applications");
  $("#ia-view-applications").innerHTML = `
    <div class="ia-toolbar"><h2>Aplicaciones</h2><button class="ia-btn ia-btn--primary" data-action="new-application">Crear</button></div>
    ${table(["Codigo", "Nombre", "Estado", "Modulos", "Usuarios", ""], data.applications.map((a) => `<tr>
      <td><strong>${escapeHtml(a.codigo)}</strong></td><td>${escapeHtml(a.nombre)}</td><td>${badge(String(a.activo))}</td>
      <td>${a.modulos_count}</td><td>${a.usuarios_autorizados_count}</td>
      <td><button class="ia-btn" data-action="toggle-application" data-id="${a.id}" data-active="${a.activo}" data-updated="${escapeHtml(a.updated_at)}">${Number(a.activo) === 1 ? "Desactivar" : "Activar"}</button></td>
    </tr>`))}`;
};

const renderModules = async () => {
  const data = await api("/admin/modules");
  $("#ia-view-modules").innerHTML = `
    <div class="ia-toolbar">
      <h2>Modulos</h2>
      <button class="ia-btn ia-btn--primary" data-action="new-module">Crear</button>
    </div>
    ${table(["Aplicacion", "Codigo", "Nombre", "Orden", "Estado", "Roles", "Excepciones", ""], data.modules.map((m) => `<tr>
      <td>${escapeHtml(m.aplicacion_codigo)}</td><td><strong>${escapeHtml(m.codigo)}</strong></td><td>${escapeHtml(m.nombre)}</td>
      <td>${escapeHtml(m.orden)}</td><td>${badge(String(m.activo))}</td><td>${m.roles_asociados_count}</td><td>${m.usuarios_excepciones_count}</td>
      <td><button class="ia-btn" data-action="toggle-module" data-id="${m.id}" data-active="${m.activo}" data-updated="${escapeHtml(m.updated_at)}">${Number(m.activo) === 1 ? "Desactivar" : "Activar"}</button></td>
    </tr>`))}`;
};

const renderRoles = async () => {
  const data = await api("/admin/roles");
  $("#ia-view-roles").innerHTML = `
    <div class="ia-toolbar"><h2>Roles</h2><button class="ia-btn ia-btn--primary" data-action="new-role">Crear</button></div>
    ${table(["Aplicacion", "Codigo", "Nombre", "Estado", "Usuarios", "Modulos", ""], data.roles.map((r) => `<tr>
      <td>${escapeHtml(r.aplicacion_codigo)}</td><td><strong>${escapeHtml(r.codigo)}</strong></td><td>${escapeHtml(r.nombre)}</td>
      <td>${badge(String(r.activo))}</td><td>${r.usuarios_count}</td><td>${r.modulos_count}</td>
      <td><button class="ia-btn" data-action="edit-role-matrix" data-id="${r.id}">Matriz</button></td>
    </tr>`))}`;
};

const renderPermissions = async () => {
  $("#ia-view-permissions").innerHTML = `
    <section class="ia-card">
      <h2>Permisos efectivos</h2>
      <p class="ia-muted">Abrir un usuario desde la seccion Usuarios para diagnosticar origen SUPERADMIN, ROL o EXCEPCION_USUARIO.</p>
    </section>`;
};

const renderActiveTab = async () => {
  setAlert("");
  document.querySelectorAll(".ia-view").forEach((view) => { view.hidden = true; });
  $(`#ia-view-${state.tab}`).hidden = false;
  document.querySelectorAll(".ia-tabs button").forEach((button) => {
    button.setAttribute("aria-current", button.dataset.tab === state.tab ? "page" : "false");
  });
  await refreshToken();
  if (state.tab === "dashboard") await renderDashboard();
  if (state.tab === "users") await renderUsers();
  if (state.tab === "invitations") await renderInvitations();
  if (state.tab === "applications") await renderApplications();
  if (state.tab === "modules") await renderModules();
  if (state.tab === "roles") await renderRoles();
  if (state.tab === "permissions") await renderPermissions();
};

const openDrawer = (title, kicker, html) => {
  $("#ia-drawer-title").textContent = title;
  $("#ia-drawer-kicker").textContent = kicker;
  $("#ia-drawer-body").innerHTML = html;
  $("#ia-drawer").hidden = false;
};

const closeDrawer = () => { $("#ia-drawer").hidden = true; };

const openUser = async (id) => {
  const data = await api(`/admin/users/${id}`);
  const u = data.user;
  openDrawer(`${u.nombre || ""} ${u.apellido || ""}`.trim() || u.email, "Usuario", `
    <form class="ia-form" id="user-detail-form" data-id="${u.id}" data-updated="${escapeHtml(u.updated_at)}">
      <div class="ia-grid-2">
        <label>Nombre<input name="nombre" value="${escapeHtml(u.nombre)}"></label>
        <label>Apellido<input name="apellido" value="${escapeHtml(u.apellido)}"></label>
        <label>Telefono<input name="telefono" value="${escapeHtml(u.telefono)}"></label>
        <label>Cargo<input name="cargo" value="${escapeHtml(u.cargo)}"></label>
        <label>Sector<input name="sector" value="${escapeHtml(u.sector)}"></label>
        <label>Foto URL<input name="foto_url" value="${escapeHtml(u.foto_url)}"></label>
        <label>Estado<select name="estado">${["ACTIVO","PENDIENTE","BLOQUEADO","BAJA"].map((e) => `<option ${u.estado === e ? "selected" : ""}>${e}</option>`).join("")}</select></label>
        <label>Superadmin<select name="es_superadmin"><option value="0">No</option><option value="1" ${Number(u.es_superadmin) === 1 ? "selected" : ""}>Si</option></select></label>
      </div>
      <button class="ia-btn ia-btn--primary" type="submit">Guardar perfil</button>
    </form>
    <section class="ia-card"><h3>Aplicaciones</h3><div class="ia-stack">
      ${data.applications.map((a) => `<label class="ia-toggle"><input type="checkbox" data-user-app="${a.id}" ${Number(a.activo) === 1 ? "checked" : ""}> ${escapeHtml(a.codigo)} ${Number(a.aplicacion_activa) === 1 ? "" : "(app inactiva)"}</label>`).join("")}
      <button class="ia-btn" data-action="save-user-apps" data-id="${u.id}">Guardar aplicaciones</button>
    </div></section>
    <section class="ia-card"><h3>Roles</h3><div class="ia-stack">
      ${state.catalogs.roles.map((r) => {
        const checked = data.roles.some((ur) => Number(ur.id) === Number(r.id) && Number(ur.activo) === 1);
        return `<label class="ia-toggle"><input type="checkbox" data-user-role="${r.id}" ${checked ? "checked" : ""}> ${escapeHtml(r.aplicacion_codigo)} / ${escapeHtml(r.codigo)}</label>`;
      }).join("")}
      <button class="ia-btn" data-action="save-user-roles" data-id="${u.id}">Guardar roles</button>
    </div></section>
    <section class="ia-card"><h3>Modulos y permisos efectivos</h3>${effectiveModulesHtml(data.modules, u.id)}</section>
  `);
};

const effectiveModulesHtml = (modules, userId) => `
  <div class="ia-perm-grid">
    ${modules.map((m) => `<div class="ia-card">
      <strong>${escapeHtml(m.aplicacion_codigo)} / ${escapeHtml(m.codigo)}</strong>
      <div class="ia-perm-row">
        <span class="ia-muted">Permiso</span>
        ${PERMS.map((p) => `<span>${PERM_LABELS[p]}</span>`).join("")}
      </div>
      <div class="ia-perm-row">
        <span>Efectivo</span>
        ${PERMS.map((p) => badge((m.effective?.permissions?.[p] ? "PERMITIDO" : "DENEGADO"))).join("")}
      </div>
      <div class="ia-perm-row">
        <span>Origen</span>
        ${PERMS.map((p) => `<span class="ia-badge">${escapeHtml(m.origins?.[p] || "SIN_PERMISO")}</span>`).join("")}
      </div>
      <div class="ia-perm-row">
        <span>Excepcion</span>
        ${PERMS.map((p) => `<select data-user-module="${m.id}" data-permission="${p}">
          ${["HEREDADO","PERMITIR","DENEGAR"].map((v) => {
            const raw = m[`ex_${p}`];
            const selected = (raw === null && v === "HEREDADO") || (String(raw) === "1" && v === "PERMITIR") || (String(raw) === "0" && v === "DENEGAR");
            return `<option ${selected ? "selected" : ""}>${v}</option>`;
          }).join("")}
        </select>`).join("")}
      </div>
    </div>`).join("")}
    <button class="ia-btn" data-action="save-user-modules" data-id="${userId}">Guardar excepciones</button>
  </div>`;

const saveUserProfile = async (form) => {
  const payload = Object.fromEntries(new FormData(form).entries());
  payload.updated_at = form.dataset.updated;
  if (CRITICAL.has(payload.estado) && !confirm(`Confirmar cambio de estado a ${payload.estado}.`)) return;
  if (payload.es_superadmin === "0" && !confirm("Confirmar si corresponde quitar superadmin. El backend rechazara quitar el ultimo.")) return;
  await apiJson("PATCH", `/admin/users/${form.dataset.id}`, payload);
  setAlert("Usuario actualizado.");
  await openUser(form.dataset.id);
};

const saveUserApps = async (id) => {
  const applications = [...document.querySelectorAll("[data-user-app]")].map((input) => ({
    aplicacion_id: input.dataset.userApp,
    activo: input.checked
  }));
  if (!confirm("Confirmar cambios de aplicaciones del usuario.")) return;
  await apiJson("PUT", `/admin/users/${id}/applications`, { applications });
  setAlert("Aplicaciones actualizadas.");
  await openUser(id);
};

const saveUserRoles = async (id) => {
  const roles = [...document.querySelectorAll("[data-user-role]")].map((input) => ({
    rol_id: input.dataset.userRole,
    activo: input.checked
  }));
  await apiJson("PUT", `/admin/users/${id}/roles`, { roles });
  setAlert("Roles actualizados.");
  await openUser(id);
};

const saveUserModules = async (id) => {
  const grouped = {};
  document.querySelectorAll("[data-user-module][data-permission]").forEach((select) => {
    grouped[select.dataset.userModule] ??= { modulo_id: select.dataset.userModule };
    grouped[select.dataset.userModule][select.dataset.permission] = select.value;
  });
  await apiJson("PUT", `/admin/users/${id}/modules`, { modules: Object.values(grouped) });
  setAlert("Excepciones actualizadas.");
  await openUser(id);
};

const toggleCatalog = async (kind, id, active, updatedAt) => {
  const next = Number(active) === 1 ? 0 : 1;
  const label = kind === "applications" ? "aplicacion" : "modulo";
  if (next === 0 && !confirm(`Confirmar desactivar ${label}. Las relaciones se conservan.`)) return;
  await apiJson("PATCH", `/admin/${kind}/${id}`, { activo: next, updated_at: updatedAt });
  setAlert(`${label} actualizado.`);
  await loadCatalogs();
  await renderActiveTab();
};

const openRoleMatrix = async (id) => {
  const data = await api(`/admin/roles/${id}`);
  openDrawer(data.role.nombre, "Matriz de permisos por rol", `
    <section class="ia-card"><strong>${escapeHtml(data.role.codigo)}</strong></section>
    <div class="ia-perm-grid">
      ${data.modules.map((m) => `<div class="ia-perm-row" data-role-module-row="${m.modulo_id}">
        <strong>${escapeHtml(m.codigo)}</strong>
        ${PERMS.map((p) => `<label class="ia-toggle"><input type="checkbox" data-role-module="${m.modulo_id}" data-permission="${p}" ${Number(m[p]) === 1 ? "checked" : ""}> ${PERM_LABELS[p]}</label>`).join("")}
      </div>`).join("")}
    </div>
    <button class="ia-btn ia-btn--primary" data-action="save-role-matrix" data-id="${id}">Guardar matriz</button>
  `);
};

const saveRoleMatrix = async (id) => {
  const grouped = {};
  document.querySelectorAll("[data-role-module][data-permission]").forEach((input) => {
    grouped[input.dataset.roleModule] ??= { modulo_id: input.dataset.roleModule };
    grouped[input.dataset.roleModule][input.dataset.permission] = input.checked;
  });
  await apiJson("PUT", `/admin/roles/${id}/modules`, { modules: Object.values(grouped) });
  setAlert("Matriz guardada.");
  closeDrawer();
  await renderActiveTab();
};

const revokeInvitation = async (id) => {
  if (!confirm("Confirmar revocacion de invitacion pendiente.")) return;
  await apiJson("POST", `/admin/invitations/${id}/revoke`, {});
  setAlert("Invitacion revocada.");
  await renderInvitations();
};

const openInvitationForm = () => {
  openDrawer("Crear invitacion", "Invitaciones", `
    <form class="ia-form" id="invitation-form">
      <label>Email<input name="email" type="email" required></label>
      <label>Vencimiento horas<input name="expires_in_hours" type="number" min="1" max="720" value="72"></label>
      <section class="ia-card">
        <h3>Aplicaciones y roles</h3>
        <div class="ia-stack">
          ${state.catalogs.applications.map((app) => `
            <div class="ia-card">
              <label class="ia-toggle"><input type="checkbox" data-invite-app="${app.codigo}"> ${escapeHtml(app.codigo)} / ${escapeHtml(app.nombre)}</label>
              <div class="ia-stack">
                ${state.catalogs.roles.filter((role) => Number(role.aplicacion_id) === Number(app.id)).map((role) => `
                  <label class="ia-toggle"><input type="checkbox" data-invite-role="${app.codigo}" value="${role.codigo}"> ${escapeHtml(role.codigo)}</label>
                `).join("")}
              </div>
            </div>`).join("")}
        </div>
      </section>
      <button class="ia-btn ia-btn--primary" type="submit">Crear invitacion</button>
    </form>
  `);
};

const createInvitation = async (form) => {
  const formData = new FormData(form);
  const applications = [...document.querySelectorAll("[data-invite-app]:checked")].map((input) => ({
    codigo: input.dataset.inviteApp,
    roles: [...document.querySelectorAll(`[data-invite-role="${input.dataset.inviteApp}"]:checked`)].map((role) => role.value),
    modules: []
  }));
  const created = await apiJson("POST", "/admin/invitations", {
    email: String(formData.get("email")),
    expires_in_hours: Number(formData.get("expires_in_hours") || 72),
    applications
  });
  const link = `${location.origin}/api/v1/auth/invitation?token=${encodeURIComponent(created.activation_token)}`;
  openDrawer("Invitacion creada", "Token visible una sola vez", `
    <section class="ia-card">
      <p>${escapeHtml(created.invitation.email)}</p>
      <label>Enlace de invitacion<input id="invitation-link" readonly value="${escapeHtml(link)}"></label>
      <div class="ia-actions">
        <button class="ia-btn ia-btn--primary" data-action="copy-invitation-link">Copiar enlace</button>
        <button class="ia-btn" data-action="close-drawer">Cerrar</button>
      </div>
    </section>
  `);
  await renderInvitations();
};

const handleAction = async (target) => {
  const action = target.dataset.action;
  if (!action) return;
  try {
    if (action === "filter-users") await filterUsers();
    if (action === "open-user") await openUser(target.dataset.id);
    if (action === "save-user-apps") await saveUserApps(target.dataset.id);
    if (action === "save-user-roles") await saveUserRoles(target.dataset.id);
    if (action === "save-user-modules") await saveUserModules(target.dataset.id);
    if (action === "toggle-application") await toggleCatalog("applications", target.dataset.id, target.dataset.active, target.dataset.updated);
    if (action === "toggle-module") await toggleCatalog("modules", target.dataset.id, target.dataset.active, target.dataset.updated);
    if (action === "edit-role-matrix") await openRoleMatrix(target.dataset.id);
    if (action === "save-role-matrix") await saveRoleMatrix(target.dataset.id);
    if (action === "revoke-invitation") await revokeInvitation(target.dataset.id);
    if (action === "quick-invite" || action === "new-invitation") openInvitationForm();
    if (action === "copy-invitation-link") {
      const input = $("#invitation-link");
      input.select();
      await navigator.clipboard.writeText(input.value);
      setAlert("Enlace copiado.");
    }
    if (action === "close-drawer") closeDrawer();
    if (action === "new-application" || action === "new-module" || action === "new-role") setAlert("Alta disponible por API. La edicion visual completa se agregara en la siguiente iteracion.", false);
  } catch (error) {
    setAlert(error.message, true);
  }
};

document.addEventListener("click", async (event) => {
  const target = event.target.closest("[data-action], [data-tab], [data-tab-jump], #ia-drawer-close, #ia-signout");
  if (!target) return;
  if (target.id === "ia-drawer-close") return closeDrawer();
  if (target.id === "ia-signout") return signOut(state.auth);
  if (target.dataset.tab || target.dataset.tabJump) {
    state.tab = target.dataset.tab || target.dataset.tabJump;
    try { await renderActiveTab(); } catch (error) { setAlert(error.message, true); }
    return;
  }
  await handleAction(target);
});

document.addEventListener("submit", async (event) => {
  event.preventDefault();
  try {
    if (event.target.id === "ia-login-form") {
      const form = new FormData(event.target);
      await signInWithEmailAndPassword(state.auth, String(form.get("email")), String(form.get("password")));
      return;
    }
    if (event.target.id === "user-detail-form") {
      await saveUserProfile(event.target);
    }
    if (event.target.id === "invitation-form") {
      await createInvitation(event.target);
    }
  } catch (error) {
    setAlert(error.message, true);
  }
});

const boot = async () => {
  try {
    await loadConfig();
    onAuthStateChanged(state.auth, async (user) => {
      try {
        if (user) await setAuthenticated(user);
        else setUnauthenticated();
      } catch (error) {
        setAlert(error.message, true);
        setUnauthenticated();
      }
    });
  } catch (error) {
    $("#ia-login-panel").innerHTML = `<div class="ia-alert">${escapeHtml(error.message)}</div>`;
  }
};

void boot();
