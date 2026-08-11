<?php
require_once __DIR__ . '/../../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Identidad y Accesos', ['/admin/identidad-accesos/identidad-accesos.css?v=1']); ?>
</head>
<body class="lcm-page lcm-page--with-nav ia-page">
<?php lcm_topbar(''); ?>

<main class="lcm-shell ia-shell">
    <section class="lcm-page-head ia-head">
        <div>
            <span class="lcm-eyebrow">Administracion corporativa</span>
            <h1>Identidad y Accesos</h1>
            <p class="lcm-muted">Gobierno de usuarios, invitaciones, aplicaciones, roles, modulos y permisos.</p>
        </div>
        <div class="ia-session">
            <span id="ia-session-label">Sin sesion</span>
            <button class="ia-btn ia-btn--ghost" id="ia-signout" type="button" hidden>Salir</button>
        </div>
    </section>

    <section class="lcm-panel ia-login" id="ia-login-panel">
        <div>
            <span class="lcm-eyebrow">Acceso administrador</span>
            <h2>Ingresar</h2>
            <p class="lcm-muted">La autorizacion final se valida siempre en backend sobre IDENTIDAD_ACCESOS.</p>
        </div>
        <form id="ia-login-form" class="ia-form ia-form--login">
            <label>Email<input name="email" type="email" autocomplete="username" required></label>
            <label>Contrasena<input name="password" type="password" autocomplete="current-password" required></label>
            <button class="ia-btn ia-btn--primary" type="submit">Ingresar</button>
        </form>
    </section>

    <section class="ia-admin" id="ia-admin-panel" hidden>
        <nav class="ia-tabs" aria-label="Secciones">
            <button type="button" data-tab="dashboard" aria-current="page">Resumen</button>
            <button type="button" data-tab="users">Usuarios</button>
            <button type="button" data-tab="invitations">Invitaciones</button>
            <button type="button" data-tab="applications">Aplicaciones</button>
            <button type="button" data-tab="modules">Modulos</button>
            <button type="button" data-tab="roles">Roles</button>
            <button type="button" data-tab="permissions">Permisos</button>
        </nav>
        <div class="ia-alert" id="ia-alert" hidden></div>
        <section class="ia-view" id="ia-view-dashboard"></section>
        <section class="ia-view" id="ia-view-users" hidden></section>
        <section class="ia-view" id="ia-view-invitations" hidden></section>
        <section class="ia-view" id="ia-view-applications" hidden></section>
        <section class="ia-view" id="ia-view-modules" hidden></section>
        <section class="ia-view" id="ia-view-roles" hidden></section>
        <section class="ia-view" id="ia-view-permissions" hidden></section>
    </section>
</main>

<aside class="ia-drawer" id="ia-drawer" hidden>
    <div class="ia-drawer__panel">
        <div class="ia-drawer__head">
            <div>
                <span class="lcm-eyebrow" id="ia-drawer-kicker">Detalle</span>
                <h2 id="ia-drawer-title">Registro</h2>
            </div>
            <button class="ia-icon-btn" id="ia-drawer-close" type="button" aria-label="Cerrar">X</button>
        </div>
        <div class="ia-drawer__body" id="ia-drawer-body"></div>
    </div>
</aside>

<?php lcm_footer(); ?>
<script type="module" src="/admin/identidad-accesos/identidad-accesos.js?v=1"></script>
</body>
</html>
