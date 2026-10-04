<?php
require_once __DIR__ . '/../shared/layout.php';
?><!doctype html>
<html lang="es">
<head><?php lcm_head('Ingresar'); ?></head>
<body class="lcm-login-page">
<main class="lcm-login-shell">
  <section class="lcm-login-card" aria-labelledby="login-title">
    <div class="lcm-login-brand"><img src="/assets/branding/logo-horizontal.png" alt="La Cablera Marplatense" class="lcm-login-logo"></div>
    <div class="lcm-login-heading"><span class="lcm-login-kicker">Plataforma de Gestión Grupo Plantel</span><h1 id="login-title">Ingresar</h1><p>Accedé a las herramientas de La Cablera.</p></div>
    <div class="lcm-login-alert" id="lcm-login-alert" role="alert" hidden></div>
    <div class="lcm-login-status" id="lcm-login-status" hidden></div>
    <form class="lcm-login-form" id="lcm-login-form" novalidate>
      <label for="lcm-email">Correo electrónico</label>
      <input id="lcm-email" name="email" type="email" autocomplete="username" placeholder="nombre@plantel.com.ar" required>
      <label for="lcm-password">Contraseña</label>
      <div class="lcm-password-field"><input id="lcm-password" name="password" type="password" autocomplete="current-password" required><button type="button" class="lcm-password-toggle" aria-label="Mostrar contraseña" aria-pressed="false">Mostrar</button></div>
      <button class="lcm-login-btn" type="submit"><span class="lcm-login-btn-label">Ingresar</span><span class="lcm-spinner lcm-login-spinner" aria-hidden="true" hidden></span></button>
    </form>
    <div class="lcm-login-links"><button type="button" id="lcm-reset-password">Olvidé mi contraseña</button><a href="/activar-invitacion/">Activar invitación</a></div>
    <p class="lcm-login-footer">Acceso seguro con identidad central LCM</p>
  </section>
</main>
<script type="module" src="/assets/js/login-ux.js?v=3"></script>
</body>
</html>
