<?php
require_once __DIR__ . '/../../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Análisis Económico TELCO', [
        '/gerencia/Analisis_Economico_TELCO/assets/modulo.css?v=1',
    ]); ?>
</head>
<body class="lcm-page lcm-page--with-nav">
<?php lcm_topbar('gerencia'); ?>

<main class="lcm-shell iet-page">
    <section class="lcm-page-head">
        <div>
            <span class="lcm-eyebrow">Gerencia</span>
            <h1>Análisis Económico TELCO</h1>
            <p class="lcm-muted">Control de la importación RAW del informe económico.</p>
        </div>
        <a class="lcm-action" href="/gerencia/">Volver a Gerencia</a>
    </section>

    <section class="iet-status-panel" aria-labelledby="estadoTitulo">
        <div class="iet-status-head">
            <div>
                <span class="lcm-eyebrow">Última ejecución</span>
                <h2 id="estadoTitulo">Estado de la importación</h2>
            </div>
            <span id="estadoImportacion" class="iet-badge iet-badge--neutral">Consultando…</span>
        </div>

        <div class="iet-grid">
            <article class="iet-card iet-card--wide">
                <span>Archivo procesado</span>
                <strong id="archivoProcesado">—</strong>
            </article>
            <article class="iet-card">
                <span>Última actualización</span>
                <strong id="fechaImportacion">—</strong>
            </article>
            <article class="iet-card">
                <span>Filas leídas</span>
                <strong id="filasLeidas">0</strong>
            </article>
            <article class="iet-card">
                <span>Filas insertadas</span>
                <strong id="filasInsertadas">0</strong>
            </article>
            <article class="iet-card">
                <span>Filas con error</span>
                <strong id="filasError">0</strong>
            </article>
        </div>

        <p id="mensajeEstado" class="iet-message" role="status" aria-live="polite">
            Consultando el estado de la última importación.
        </p>
    </section>

    <section class="iet-next-stage">
        <span class="lcm-eyebrow">Siguiente etapa</span>
        <h2>Indicadores económicos pendientes de validación</h2>
        <p>
            Los KPIs de producido, certificado, facturado, cobrado y pendientes se construirán
            después de validar la estructura y la calidad de la importación RAW.
        </p>
    </section>
</main>

<?php lcm_footer(); ?>
<script src="/gerencia/Analisis_Economico_TELCO/assets/modulo.js?v=1" defer></script>
</body>
</html>
