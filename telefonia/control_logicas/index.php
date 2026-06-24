<?php
require_once __DIR__ . '/../../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Control de Pruebas Lógicas', [
        '/telefonia/control_logicas/assets/control-logicas.css?v=3'
    ]); ?>
</head>
<body class="lcm-page lcm-page--with-nav logic-page">
<?php lcm_topbar('telefonia'); ?>

<main class="logic-shell">
    <section class="logic-heading">
        <div>
            <span class="logic-eyebrow">Telefonía · Certificación</span>
            <h1>Control de Pruebas Lógicas</h1>
            <p>Seguimiento diario de certificaciones realizadas sobre HUBs y CTOs.</p>
        </div>
        <a class="logic-back" href="/telefonia/menu.php">Volver a Telefonía</a>
    </section>

    <section class="logic-kpis" aria-label="Indicadores del dia">
        <article class="logic-kpi logic-kpi--ok">
            <span>HUB OK hoy</span>
            <strong id="kpiHubOk">—</strong>
            <small>Certificaciones correctas</small>
        </article>
        <article class="logic-kpi logic-kpi--error">
            <span>HUB NO OK hoy</span>
            <strong id="kpiHubNoOk">—</strong>
            <small>Certificaciones con error</small>
        </article>
        <article class="logic-kpi logic-kpi--ok">
            <span>CTO OK hoy</span>
            <strong id="kpiCtoOk">—</strong>
            <small>Certificaciones correctas</small>
        </article>
        <article class="logic-kpi logic-kpi--error">
            <span>CTO NO OK hoy</span>
            <strong id="kpiCtoNoOk">—</strong>
            <small>Certificaciones con error</small>
        </article>
    </section>

    <section class="logic-status" aria-labelledby="statusTitle">
        <div class="logic-status__title">
            <span class="logic-status__dot" id="statusDot" aria-hidden="true"></span>
            <div>
                <span>Estado de automatización</span>
                <strong id="statusTitle">Consultando...</strong>
            </div>
        </div>
        <dl>
            <div>
                <dt>Última actualización</dt>
                <dd id="lastUpdate">—</dd>
            </div>
            <div>
                <dt>Último dato de origen</dt>
                <dd id="lastOrigin">—</dd>
            </div>
        </dl>
    </section>

    <section class="logic-panel logic-filters" aria-labelledby="filtersTitle">
        <div class="logic-panel__head">
            <div>
                <span class="logic-section-label">Consulta</span>
                <h2 id="filtersTitle">Filtros del período</h2>
            </div>
            <button class="logic-button logic-button--ghost" id="clearFilters" type="button">Limpiar</button>
        </div>

        <form id="filtersForm" class="logic-filter-grid">
            <label>
                <span>Período</span>
                <select id="periodFilter" name="periodo" required></select>
            </label>
            <label>
                <span>Red de acceso</span>
                <input id="networkFilter" name="red" type="text" maxlength="20" inputmode="text" autocomplete="off" placeholder="Ej. BBLN">
            </label>
            <label>
                <span>Pelo</span>
                <input id="strandFilter" name="pelo" type="text" maxlength="4" inputmode="numeric" autocomplete="off" placeholder="Ej. 16">
            </label>
            <button class="logic-button logic-button--primary" type="submit">Aplicar filtros</button>
        </form>
        <p class="logic-filter-help">Los valores compuestos, como MPNR8-EMRT7, pueden encontrarse por cualquiera de sus pares red/pelo.</p>
    </section>

    <section class="logic-panel logic-results" aria-labelledby="resultsTitle">
        <div class="logic-panel__head logic-results__head">
            <div>
                <span class="logic-section-label">Detalle operativo</span>
                <h2 id="resultsTitle">Pruebas del período</h2>
            </div>
            <div class="logic-result-count" id="resultCount">Cargando...</div>
        </div>

        <div class="logic-notice logic-notice--hidden" id="notice" role="status"></div>

        <div class="logic-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Tipo</th>
                        <th>Resultado</th>
                        <th>RDA</th>
                        <th>HUB</th>
                        <th>Pelo</th>
                        <th>Potencia TX</th>
                        <th>Mensaje</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody id="resultsBody">
                    <tr><td class="logic-empty" colspan="9">Consultando pruebas...</td></tr>
                </tbody>
            </table>
        </div>

        <nav class="logic-pagination" aria-label="Paginación de pruebas">
            <button class="logic-button logic-button--ghost" id="previousPage" type="button">Anterior</button>
            <span id="pageStatus">Página — de —</span>
            <button class="logic-button logic-button--ghost" id="nextPage" type="button">Siguiente</button>
        </nav>
    </section>
</main>

<?php lcm_footer(); ?>
<script src="/telefonia/control_logicas/assets/control-logicas.js?v=2" defer></script>
</body>
</html>
