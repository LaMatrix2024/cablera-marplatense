<?php
require_once __DIR__ . '/shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Demo Marca'); ?>
</head>
<body class="lcm-page">
    <main class="lcm-shell">
        <section class="lcm-page-head">
            <div>
                <span class="lcm-eyebrow">Identidad oficial</span>
                <h1>LCM</h1>
                <p class="lcm-muted">La Cablera Marplatense · Plataforma de Gestion Grupo Plantel</p>
            </div>
            <a class="lcm-action" href="/">Inicio</a>
        </section>

        <section class="lcm-grid">
            <div class="lcm-brand-card">
                <h2>Marca principal</h2>
                <?= lcm_logo('horizontal') ?>
            </div>
            <div class="lcm-brand-card">
                <h2>Navbar</h2>
                <a href="/" class="lcm-brand"><?= lcm_logo('nav') ?></a>
            </div>
            <div class="lcm-brand-card">
                <h2>Compacta / App</h2>
                <?= lcm_logo('icon') ?>
            </div>
        </section>
    </main>
</body>
</html>
