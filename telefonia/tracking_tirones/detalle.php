<?php
require_once __DIR__ . '/../../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Tracking Tirones - Detalle', [
        '/telefonia/tracking_tirones/assets/tracking-tirones.css?v=4'
    ]); ?>
</head>
<body class="lcm-page lcm-page--with-nav tracking-page">
<?php lcm_topbar('telefonia'); ?>

<main class="lcm-shell tracking-shell tracking-shell--detail" id="trackingDetalle">
    <section class="tracking-head">
        <div class="tracking-head__title">
            <span class="tracking-eyebrow">Telefonía · Desmonte</span>
            <h1>Tracking Tirones - Detalle</h1>
            <p>Filtrado operativo sobre la tabla raw de ToolBox.</p>
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
            <a class="lcm-action" href="/telefonia/tracking_tirones/">Volver a resumen</a>
            <a class="lcm-action lcm-action--secondary" href="/telefonia/menu.php">Menú Telefonía</a>
        </aside>
    </section>

    <section class="tracking-panel tracking-filters">
        <div class="tracking-panel__head">
            <div>
                <span class="tracking-section-label">Consulta</span>
                <h2>Filtros</h2>
            </div>
            <button class="tracking-button tracking-button--ghost" id="clearFilters" type="button">Limpiar</button>
        </div>

        <form id="filtersForm" class="tracking-filter-grid">
            <input type="hidden" id="periodosInput" name="periodos">
            <label>
                <span>Central</span>
                <select id="centralFilter" name="central">
                    <option value="">Todas</option>
                </select>
            </label>
            <label>
                <span>Estado</span>
                <select id="estadoFilter" name="estado">
                    <option value="">Todos</option>
                </select>
            </label>
            <label class="tracking-filter-grid__wide">
                <span>Buscar</span>
                <input id="searchFilter" name="q" type="text" maxlength="120" autocomplete="off" placeholder="Número de tirón, sigest, contratista, QR">
            </label>
            <button class="tracking-button tracking-button--primary tracking-filter-submit" type="submit">Aplicar filtros</button>
        </form>
    </section>

    <section class="tracking-panel tracking-results">
        <div class="tracking-panel__head">
            <div>
                <span class="tracking-section-label">Detalle</span>
                <h2>Registros</h2>
            </div>
            <div class="tracking-result-count" id="resultCount">Cargando...</div>
        </div>

        <div class="tracking-notice tracking-notice--hidden" id="notice" role="status"></div>

        <div class="tracking-table-wrap">
            <table class="tracking-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tirón</th>
                        <th>Sigest</th>
                        <th>Central</th>
                        <th>Estado</th>
                        <th>Tipo</th>
                        <th>Metros</th>
                        <th>Kg teóricos</th>
                        <th>Contratista</th>
                        <th>Almacén</th>
                    </tr>
                </thead>
                <tbody id="resultsBody">
                    <tr><td class="tracking-empty" colspan="10">Consultando registros...</td></tr>
                </tbody>
            </table>
        </div>

        <nav class="tracking-pagination" aria-label="Paginación">
            <button class="tracking-button tracking-button--ghost" id="previousPage" type="button">Anterior</button>
            <span id="pageStatus">Página - de -</span>
            <button class="tracking-button tracking-button--ghost" id="nextPage" type="button">Siguiente</button>
        </nav>
    </section>
</main>

<?php lcm_footer(); ?>
<script src="/telefonia/tracking_tirones/assets/tracking-tirones.js?v=4" defer></script>
</body>
</html>
