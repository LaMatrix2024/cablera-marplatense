<?php
require_once __DIR__ . '/../../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Identidad y Accesos', ['/admin/identidad-accesos/identidad-accesos.css?v=1']); ?>
</head>
<body class="ia-page">
<div class="ia-mobile-backdrop" id="ia-mobile-backdrop" hidden></div>

<div class="ia-app-shell" id="ia-app-shell">
    <aside class="ia-sidebar" id="ia-sidebar" aria-label="Navegacion principal"></aside>

    <section class="ia-workspace">
        <header class="ia-top-header" id="ia-top-header"></header>
        <main class="ia-main">
            <div class="ia-toast" id="ia-alert" hidden></div>

            <section class="ia-login-card" id="ia-login-panel">
                <div class="ia-login-copy">
                    <span class="ia-kicker">Acceso administrador</span>
                    <h1>Identidad y Accesos</h1>
                    <p>Administracion corporativa de usuarios, invitaciones, aplicaciones, roles, modulos y permisos.</p>
                </div>
                <form id="ia-login-form" class="ia-form ia-form--login">
                    <label>Email<input name="email" type="email" autocomplete="username" required></label>
                    <label>Contrasena<input name="password" type="password" autocomplete="current-password" required></label>
                    <button class="ia-btn ia-btn--primary" type="submit">
                        Ingresar
                    </button>
                </form>
            </section>

            <section class="ia-denied-card" id="ia-denied-panel" hidden>
                <h2>Acceso denegado</h2>
                <p>No tenes permiso para ver IDENTIDAD_ACCESOS. La validacion fue realizada por el backend corporativo.</p>
            </section>

            <section class="ia-admin" id="ia-admin-panel" hidden>
                <section class="ia-view" id="ia-view-dashboard"></section>
                <section class="ia-view" id="ia-view-users" hidden></section>
                <section class="ia-view" id="ia-view-placeholder" hidden></section>
            </section>
        </main>
    </section>
</div>

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

<script type="module" src="/admin/identidad-accesos/identidad-accesos.js?v=1"></script>
</body>
</html>
