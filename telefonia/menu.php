<?php
require_once __DIR__ . '/../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Telefonia'); ?>
</head>
<body class="lcm-page lcm-page--with-nav">
<?php lcm_topbar('telefonia'); ?>

<main class="lcm-shell">
    <section class="lcm-page-head">
        <div>
            <span class="lcm-eyebrow">Telefonia</span>
            <h1>Resumen del area</h1>
            <p class="lcm-muted">Modulos operativos, reportes y validaciones internas.</p>
        </div>
        <span class="lcm-chip">v0.1 · 2026-06-01</span>
    </section>

    <section class="lcm-grid lcm-module-grid" aria-label="Modulos de Telefonia">
        <a class="lcm-card" href="/telefonia/produccion_planta">
            <strong>Produccion Planta</strong>
            <small>Resumen y detalle operativo de planta externa.</small>
        </a>
        <a class="lcm-card" href="/telefonia/produccion_planta_externa">
            <strong>Produccion Planta Externa</strong>
            <small>Dashboard vision contratos.</small>
        </a>
        <a class="lcm-card" href="/telefonia/produccion_b2b">
            <strong>Produccion B2B</strong>
            <small>Dashboard vision OT.</small>
        </a>
        <a class="lcm-card" href="/telefonia/produccion_instalaciones">
            <strong>Produccion Instalaciones</strong>
            <small>Dashboard vision contratos.</small>
        </a>
        <a class="lcm-card" href="/telefonia/economico">
            <strong>Informe economico</strong>
            <small>Gestion economica del negocio TELCO.</small>
        </a>
        <a class="lcm-card" href="/telefonia/preciario_tma">
            <strong>Preciario TMA</strong>
            <small>Gestion de precios y referencias TMA.</small>
        </a>
        <a class="lcm-card" href="/telefonia/control_logicas">
            <strong>Logicas HUB/CTO</strong>
            <small>Registros y control de certificacion CTO.</small>
        </a>
    </section>
</main>

<?php lcm_footer(); ?>
<script src="/telefonia/js/util.js"></script>
<script src="/telefonia/js/app.js"></script>
</body>
</html>
