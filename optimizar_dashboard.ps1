$ErrorActionPreference = 'Stop'
$path = Join-Path (Get-Location) 'admin\identidad-accesos\identidad-accesos.js'
if (!(Test-Path $path)) { throw "No se encontro $path" }
$utf8 = New-Object System.Text.UTF8Encoding($false)
$source = [System.IO.File]::ReadAllText($path)
$startMarker = 'const renderDashboard = async () => {'
$endMarker = 'const statCard ='
$start = $source.IndexOf($startMarker)
$end = $source.IndexOf($endMarker, [Math]::Max(0, $start))
if ($start -lt 0 -or $end -le $start) { throw 'No se encontraron los limites de renderDashboard. No se modifico nada.' }
if ($source.Contains('ia-dashboard-progressive-v1')) { throw 'La optimizacion ya esta aplicada. No se modifico nada.' }
$backup = "$path.respaldo-dashboard"
if (Test-Path $backup) { throw "Ya existe un respaldo: $backup. No se modifico nada." }
$replacement = @'
// ia-dashboard-progressive-v1: dashboard first, then optional details sequentially.
const renderDashboard = async () => {
  const root = $("#ia-view-dashboard");
  root.innerHTML = skeleton("Cargando dashboard...");
  const dashboard = await api("/admin/dashboard");
  if (state.view !== "dashboard") return;
  const summary = dashboard.summary || {};
  const invitationKeys = ["PENDIENTE", "ACEPTADA", "VENCIDA", "REVOCADA"];
  const userKeys = ["ACTIVO", "PENDIENTE", "BLOQUEADO", "BAJA"];
  const invitationLabels = { PENDIENTE: "Pendientes", ACEPTADA: "Aceptadas", VENCIDA: "Vencidas", REVOCADA: "Revocadas" };
  const userLabels = { ACTIVO: "Activos", PENDIENTE: "Pendientes", BLOQUEADO: "Bloqueados", BAJA: "Baja" };
  root.innerHTML = `
    <div class="ia-page-title"><div><h2>Dashboard</h2><p>Resumen general del sistema</p></div></div>
    <section class="ia-kpi-grid">
      ${statCard("group", summary.usuarios_activos ?? "—", "Usuarios activos", "Accesos habilitados")}
      ${statCard("schedule", summary.invitaciones_pendientes ?? "—", "Invitaciones pendientes", "Esperando activacion")}
      ${statCard("apps", summary.aplicaciones_activas ?? "—", "Aplicaciones activas", "Disponibles para usuarios")}
      ${statCard("deployed_code", summary.modulos_activos ?? "—", "Modulos activos", "Permisos operativos")}
    </section>
    <section class="ia-dashboard-grid">
      <div id="ia-dashboard-invitations">${skeleton("Cargando invitaciones...")}</div>
      <div id="ia-dashboard-users">${skeleton("Cargando usuarios...")}</div>
      <div id="ia-dashboard-audit">${skeleton("Cargando actividad...")}</div>
    </section>
    <section class="ia-quick-actions" aria-label="Acciones rapidas">
      ${quickAction("person_add", "Invitar usuario", "invitations")}
      ${quickAction("pending_actions", "Ver pendientes", "users", "PENDIENTE")}
      ${quickAction("view_module", "Gestionar modulos", "modules")}
      ${quickAction("rule", "Gestionar permisos", "permissions")}
    </section>
  `;
  // Only the current dashboard may be updated by delayed responses.
  const update = (selector, html) => {
    if (state.view !== "dashboard" || !root.isConnected) return;
    const target = root.querySelector(selector);
    if (target) target.outerHTML = html;
  };
  try {
    const invitations = await api("/admin/invitations");
    const counts = countBy(invitations.invitations || [], "estado", invitationKeys);
    update("#ia-dashboard-invitations", donut("Invitaciones por estado", counts, invitationLabels));
  } catch (error) {
    update("#ia-dashboard-invitations", '<section class="ia-card"><h3>Invitaciones por estado</h3><p class="ia-muted">No se pudieron cargar las invitaciones.</p></section>');
  }
  if (state.view !== "dashboard") return;
  try {
    const users = await api("/admin/users");
    const counts = countBy(users.users || [], "estado", userKeys);
    update("#ia-dashboard-users", donut("Usuarios por estado", counts, userLabels));
  } catch (error) {
    update("#ia-dashboard-users", '<section class="ia-card"><h3>Usuarios por estado</h3><p class="ia-muted">No se pudieron cargar los usuarios.</p></section>');
  }
  if (state.view !== "dashboard") return;
  try {
    const audit = await api("/admin/audit");
    update("#ia-dashboard-audit", activityCard(audit.audit || []));
  } catch (error) {
    update("#ia-dashboard-audit", '<section class="ia-card"><h3>Actividad reciente</h3><p class="ia-muted">No se pudo cargar la actividad.</p></section>');
  }
};

'@
$modified = $source.Substring(0,$start) + $replacement + $source.Substring($end)
[System.IO.File]::WriteAllText($backup,$source,$utf8)
try {
  [System.IO.File]::WriteAllText($path,$modified,$utf8)
  $node = Get-Command node -ErrorAction SilentlyContinue
  if ($node) {
    & node --check $path
    if ($LASTEXITCODE -ne 0) { throw 'Node detecto error de sintaxis.' }
    Write-Host 'Sintaxis JavaScript: OK'
  } else { Write-Host 'Node no instalado: no se pudo validar sintaxis automaticamente.' }
  Write-Host 'Dashboard progresivo aplicado localmente.'
  Write-Host "Respaldo: $backup"
  Write-Host 'GitHub y Hostinger no modificados.'
} catch {
  [System.IO.File]::WriteAllText($path,$source,$utf8)
  throw "Se restauro el archivo original. Error: $_"
}
