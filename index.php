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
    <section class="lcm-page-head">
        <div>
            <span class="lcm-eyebrow">Plataforma corporativa</span>
            <h1>La Cablera Marplatense</h1>
            <p class="lcm-muted">Accesos autorizados segun tu perfil corporativo.</p>
        </div>
    </section>

    <section class="lcm-home-grid" id="lcm-home-authorized" aria-label="Modulos autorizados">
        <section class="lcm-home-card">
            <strong>Cargando accesos</strong>
            <p>Estamos consultando los permisos disponibles para tu usuario.</p>
        </section>
    </section>
</main>

<?php lcm_footer(); ?>
</body>
</html>
