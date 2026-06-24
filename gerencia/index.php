<?php
require_once __DIR__ . '/../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Gerencia'); ?>
</head>
<body class="lcm-page lcm-page--with-nav">
<?php lcm_topbar('gerencia'); ?>

<main class="lcm-shell">
    <section class="lcm-page-head">
        <div>
            <span class="lcm-eyebrow">Gestión ejecutiva</span>
            <h1>Gerencia</h1>
            <p class="lcm-muted">Análisis económico e indicadores transversales de La Cablera.</p>
        </div>
        <a class="lcm-action" href="/">Volver al inicio</a>
    </section>

    <section class="lcm-grid lcm-module-grid" aria-label="Módulos de Gerencia">
        <a class="lcm-card" href="/gerencia/Analisis_Economico_TELCO/">
            <span class="lcm-eyebrow">TELCO</span>
            <strong>Análisis Económico TELCO</strong>
            <small>Estado de importación y futura evolución del flujo económico.</small>
        </a>
    </section>
</main>

<?php lcm_footer(); ?>
</body>
</html>
