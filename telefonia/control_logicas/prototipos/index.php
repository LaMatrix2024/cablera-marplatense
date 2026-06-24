<!doctype html>
<html lang="es" data-theme="bruma">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laboratorio visual LCM</title>
    <link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/brand.css">
    <link rel="stylesheet" href="/telefonia/control_logicas/prototipos/styles.css?v=2">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/telefonia/menu.php" aria-label="La Cablera Marplatense">
        <img src="/assets/brand/lcm-logo-horizontal.svg" alt="LCM · La Cablera Marplatense">
    </a>
    <nav aria-label="Navegacion principal">
        <a href="#">Dirección</a>
        <a class="active" href="#">Telefonía</a>
        <a href="#">Obras</a>
        <a href="#">RRHH</a>
        <a href="#">Contable</a>
    </nav>
    <div class="user-chip">PLANTEL</div>
</header>

<aside class="theme-lab" aria-label="Selector de propuesta visual">
    <div>
        <span class="lab-kicker">Laboratorio visual</span>
        <strong>Elegí una dirección</strong>
    </div>
    <div class="theme-options" role="group" aria-label="Temas disponibles">
        <button class="theme-option active" type="button" data-theme-button="bruma">
            <span class="swatches swatches--bruma" aria-hidden="true"><i></i><i></i><i></i></span>
            <span><b>01 · Bruma</b><small>Serena y operativa</small></span>
        </button>
        <button class="theme-option" type="button" data-theme-button="atlantica">
            <span class="swatches swatches--atlantica" aria-hidden="true"><i></i><i></i><i></i></span>
            <span><b>02 · Atlántica</b><small>Técnica y precisa</small></span>
        </button>
        <button class="theme-option" type="button" data-theme-button="lino">
            <span class="swatches swatches--lino" aria-hidden="true"><i></i><i></i><i></i></span>
            <span><b>03 · Lino</b><small>Cálida y editorial</small></span>
        </button>
    </div>
</aside>

<main class="shell">
    <section class="page-head">
        <div>
            <div class="breadcrumb">Telefonía <span>/</span> Certificación</div>
            <h1>Control de pruebas lógicas</h1>
            <p>Una propuesta de interfaz serena para trabajar muchas horas sin fatiga visual.</p>
        </div>
        <div class="head-actions">
            <span class="sync"><i></i> Actualizado 17:33</span>
            <button type="button" class="secondary-button">Exportar</button>
        </div>
    </section>

    <section class="kpi-grid" aria-label="Indicadores del dia">
        <article class="kpi">
            <div class="kpi-top"><span>HUB certificados</span><span class="trend trend--up">+8%</span></div>
            <strong>13</strong>
            <div class="kpi-detail"><b class="dot dot--ok"></b>10 correctos <span>·</span> 3 con observación</div>
        </article>
        <article class="kpi">
            <div class="kpi-top"><span>CTO certificadas</span><span class="trend trend--up">+12%</span></div>
            <strong>38</strong>
            <div class="kpi-detail"><b class="dot dot--ok"></b>31 correctas <span>·</span> 7 con observación</div>
        </article>
        <article class="kpi">
            <div class="kpi-top"><span>Efectividad del día</span><span class="trend">Meta 90%</span></div>
            <strong>80,4%</strong>
            <div class="progress"><i style="width:80.4%"></i></div>
        </article>
        <article class="kpi kpi--focus">
            <div class="kpi-top"><span>Requieren atención</span><span class="attention-mark">!</span></div>
            <strong>10</strong>
            <div class="kpi-detail">Pruebas con resultado NO OK</div>
        </article>
    </section>

    <section class="panel filters-panel">
        <div class="panel-title">
            <div><span class="section-kicker">Consulta</span><h2>Pruebas del período</h2></div>
            <span class="result-total">560 registros</span>
        </div>
        <form class="filters" onsubmit="return false">
            <label><span>Período</span><select><option>Junio 2026</option><option>Mayo 2026</option></select></label>
            <label><span>Red de acceso</span><input type="search" placeholder="Ej. BBLN"></label>
            <label><span>Pelo</span><input type="search" placeholder="Ej. 16"></label>
            <button class="primary-button" type="submit">Aplicar</button>
            <button class="text-button" type="reset">Limpiar</button>
        </form>
    </section>

    <section class="panel table-panel">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Fecha y hora</th><th>Tipo</th><th>Resultado</th><th>Red / pelo</th><th>Referencia</th><th>Potencia</th><th>Mensaje</th><th>Usuario</th></tr></thead>
                <tbody>
                    <tr><td><b>23 jun</b><small>17:32:39</small></td><td><span class="type-pill">CTO</span></td><td><span class="status status--ok"><i></i>OK</span></td><td><b>ADRG7</b></td><td>20273506</td><td>−15.83 dBm</td><td class="message">Certificación correcta</td><td>ECRNKO</td></tr>
                    <tr><td><b>23 jun</b><small>17:10:38</small></td><td><span class="type-pill">HUB</span></td><td><span class="status status--error"><i></i>NO OK</span></td><td><b>LNUS5504</b></td><td>3.1.1</td><td>0 dBm</td><td class="message">Sin datos de inventario</td><td>HOVEJERO</td></tr>
                    <tr><td><b>23 jun</b><small>16:54:16</small></td><td><span class="type-pill">HUB</span></td><td><span class="status status--ok"><i></i>OK</span></td><td><b>MRCS6</b></td><td>14.101</td><td>−10.80 dBm</td><td class="message">Certificación correcta</td><td>MSERNICO</td></tr>
                    <tr><td><b>23 jun</b><small>15:40:27</small></td><td><span class="type-pill">CTO</span></td><td><span class="status status--ok"><i></i>OK</span></td><td><b>PMPL6</b></td><td>2650823505</td><td>−16.00 dBm</td><td class="message">Certificación correcta</td><td>KEOCASTR</td></tr>
                    <tr><td><b>23 jun</b><small>15:01:43</small></td><td><span class="type-pill">CTO</span></td><td><span class="status status--error"><i></i>NO OK</span></td><td><b>PMPL6</b></td><td>2651065503</td><td>0 dBm</td><td class="message">No se establece link</td><td>KEOCASTR</td></tr>
                </tbody>
            </table>
        </div>
        <footer class="pagination"><span>Mostrando 1–100 de 560</span><div><button disabled>Anterior</button><b>1</b><button>Siguiente</button></div></footer>
    </section>

    <section class="decision-note">
        <span>Qué observar</span>
        <p>Jerarquía, contraste, densidad, comodidad de lectura y cuánto protagonismo debería tener el color.</p>
    </section>
</main>

<script src="/telefonia/control_logicas/prototipos/app.js?v=2"></script>
</body>
</html>
