<?php
require_once __DIR__ . '/../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Ingresar'); ?>
</head>
<body class="lcm-login-page">
<section class="lcm-login-card">
    <div class="lcm-login-brand">
        <div class="lcm-global-mark">LCM</div>
        <div>
            <strong>La Cablera Marplatense</strong>
            <p>Acceso corporativo</p>
        </div>
    </div>

    <div>
        <h1>Ingresar</h1>
        <p>Usa tu correo y contrasena corporativa.</p>
    </div>

    <div class="lcm-login-alert" id="lcm-login-alert" hidden></div>

    <form class="lcm-login-form" id="lcm-login-form">
        <label>Correo
            <input name="email" type="email" autocomplete="username" required>
        </label>
        <label>Contrasena
            <input name="password" type="password" autocomplete="current-password" required>
        </label>
        <button class="lcm-login-btn" type="submit">Ingresar</button>
    </form>

    <div class="lcm-login-links">
        <button type="button" id="lcm-reset-password">Recuperar contrasena</button>
        <a href="/activar-invitacion/">Activar invitacion</a>
    </div>
</section>
</body>
</html>
