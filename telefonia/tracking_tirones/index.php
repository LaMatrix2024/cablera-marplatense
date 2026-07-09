<?php
require_once __DIR__ . '/../../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Tracking Tirones', [
        '/telefonia/tracking_tirones/assets/tracking-tirones.css?v=4'
    ]); ?>
</head>
<body class="lcm-page lcm-page--with-nav tracking-page">
<?php lcm_topbar('telefonia'); ?>

<main class="lcm-shell tracking-shell" id="trackingResumen">
    <section class="tracking-head">
        <div class="tracking-head__title">
            <span class="tracking-eyebrow">Telefonía · Desmonte</span>
            <h1>Tracking Tirones</h1>
            <p>Vista resumen de la RAW de desmonte desde ToolBox.</p>
            <a class="tracking-detail-link" href="/telefonia/tracking_tirones/detalle.php">Ver detalle</a>
        </div>

        <aside class="tracking-controls">
            <div>
                <span class="tracking-control-label">Período</span>
                <div class="tracking-period-dropdown">
                    <button id="periodoBtn" class="tracking-period-button" type="button" aria-expanded="false" aria-controls="periodoMenu">
                        <span>Actual</span>
                        <strong>▾</strong>
                    </button>
                    <div id="periodoMenu" class="tracking-period-menu"></div>
                </div>
            </div>
            <span id="stamp" class="lcm-chip">Actualizando...</span>
        </aside>
    </section>

    <section class="tracking-kpis" aria-label="Indicadores principales">
        <article class="tracking-kpi">
            <span>Total registros</span>
            <strong id="kpiRegistros">-</strong>
            <small>Filas en el período seleccionado</small>
        </article>
        <article class="tracking-kpi">
            <span>Metros</span>
            <strong id="kpiMetros">-</strong>
            <small>Suma de metros cargados</small>
        </article>
        <article class="tracking-kpi">
            <span>Kg teóricos aéreo</span>
            <strong id="kpiKgAereo">-</strong>
            <small>Suma exclusiva de tipo = aereo</small>
        </article>
        <article class="tracking-kpi">
            <span>Kg teóricos subterráneo</span>
            <strong id="kpiKgSubte">-</strong>
            <small>Suma exclusiva de tipo = subterraneo</small>
        </article>
    </section>

    <section class="tracking-status">
        <div class="tracking-status__title">
            <span class="tracking-status__dot" id="statusDot" aria-hidden="true"></span>
            <div>
                <span>Estado de automatización</span>
                <strong id="statusTitle">Consultando...</strong>
            </div>
        </div>
        <dl>
            <div>
                <dt>Última actualización</dt>
                <dd id="lastUpdate">-</dd>
            </div>
            <div>
                <dt>Último dato de origen</dt>
                <dd id="lastOrigin">-</dd>
            </div>
            <div>
                <dt>Registros insertados</dt>
                <dd id="recordsInserted">-</dd>
            </div>
        </dl>
    </section>

    <div class="tracking-notice tracking-notice--hidden" id="notice" role="status"></div>

    <section class="tracking-panels">
        <article class="tracking-panel">
            <div class="tracking-panel__head">
                <div>
                    <span class="tracking-section-label">Distribución</span>
                    <h2>Por central</h2>
                </div>
                <span class="tracking-panel__meta" id="centralesMeta">-</span>
            </div>
            <div class="tracking-bars" id="centralesBars">
                <div class="tracking-empty">Cargando datos...</div>
            </div>
        </article>

        <article class="tracking-panel">
            <div class="tracking-panel__head">
                <div>
                    <span class="tracking-section-label">Distribución</span>
                    <h2>Por estado</h2>
                </div>
                <span class="tracking-panel__meta" id="estadosMeta">-</span>
            </div>
            <div class="tracking-bars" id="estadosBars">
                <div class="tracking-empty">Cargando datos...</div>
            </div>
        </article>
    </section>
</main>

<?php lcm_footer(); ?>
<script src="/telefonia/tracking_tirones/assets/tracking-tirones.js?v=4" defer></script>
</body>
</html>
