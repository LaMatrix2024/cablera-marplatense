<?php
require_once __DIR__ . '/shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Inicio'); ?>
</head>
<body class="lcm-page lcm-page--with-nav">
<?php lcm_topbar(''); ?>

<main class="lcm-shell">
    <!--
    <section class="lcm-page-head">
        <div>
            <span class="lcm-eyebrow">Plataforma corporativa</span>
            <h1>LCM</h1>
            <p class="lcm-muted">La Cablera Marplatense · Plataforma de Gestion Grupo Plantel</p>
        </div>
        <a class="lcm-action" href="/telefonia/menu.php">Ingresar</a>
    </section>
    -->
<a class="lcm-action" href="/telefonia/menu.php">Ingresar</a>
    <section class="lcm-grid lcm-module-grid" aria-label="Areas de negocio">
        <a class="lcm-card" href="/direccion/">
            <strong>Dirección</strong>
            <small>Indicadores ejecutivos y gestion transversal.</small>
        </a>
        <a class="lcm-card" href="/telefonia/menu.php">
            <strong>Telefonia</strong>
            <small>Producción, certificación, precios y control operativo.</small>
        </a>
        <a class="lcm-card" href="/obras/">
            <strong>Obras</strong>
            <small>Procesos y reportes del area.</small>
        </a>
        <a class="lcm-card" href="/rrhh/">
            <strong>RRHH</strong>
            <small>Gestion de personas y estructura organizacional.</small>
        </a>
        <a class="lcm-card" href="/contable/">
            <strong>Contable</strong>
            <small>Información contable y tableros de gestión.</small>
        </a>
        <a class="lcm-card" href="/mantenimiento/">
            <strong>Mantenimiento</strong>
            <small>Seguimiento y administración operativa.</small>
        </a>
        <a class="lcm-card" href="/licitaciones/">
            <strong>Licitaciones</strong>
            <small>Procesos transversales de licitación del grupo.</small>
        </a>
        <a class="lcm-card" href="/mi_info/bcoCentral/indicesBCRA.html">
            <strong>Mis Datos</strong>
            <small>Indices y mas </small>
        </a>
    </section>
</main>

<?php lcm_footer(); ?>
</body>
</html>
