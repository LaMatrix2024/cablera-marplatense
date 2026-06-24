<?php
require_once __DIR__ . '/../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Telefonía'); ?>
</head>
<body class="lcm-page lcm-page--with-nav">
<?php lcm_topbar('telefonia'); ?>

<main class="lcm-shell">
    <section class="lcm-page-head">
        <div>
            <!--<span class="lcm-eyebrow">Telefonía</span>-->
            <h1>Resumen del área</h1>
            <p class="lcm-muted">Módulos operativos, reportes y validaciones internas.</p>
        </div>
        <span class="lcm-chip">Atlántica · 2026-06-24</span>
    </section>

    <section class="lcm-grid lcm-module-grid" aria-label="Módulos de Telefonía">
        <a class="lcm-card" href="/telefonia/produccion_planta">
            <strong>Producción Planta</strong>
            <small>Panel y detalle de producción OCRAS y PTRs.</small>
        </a>
        
        <a class="lcm-card" href="/telefonia/produccion_b2b">
            <strong>Producción B2B</strong>
            <small>Panel de visión de órdenes de trabajo.</small>
        </a>
        <a class="lcm-card" href="/telefonia/produccion_instalaciones">
            <strong>Producción Instalaciones</strong>
            <small>Panel de visión de contratos.</small>
        </a>
        <a class="lcm-card" href="/telefonia/economico">
            <strong>Informe económico</strong>
            <small>Gestión económica del negocio TELCO.</small>
        </a>
        <a class="lcm-card" href="/telefonia/preciario_tma">
            <strong>Preciario TMA</strong>
            <small>Gestión de precios y referencias TMA.</small>
        </a>
        <a class="lcm-card" href="/telefonia/control_logicas">
            <strong>Lógicas HUB/CTO</strong>
            <small>Registros y control de certificación CTO.</small>
        </a>
    </section>
</main>

<?php lcm_footer(); ?>
<script src="/telefonia/js/util.js"></script>
<script src="/telefonia/js/app.js"></script>
</body>
</html>
