<?php
require_once __DIR__ . '/../../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Producción Planta Externa'); ?>

    <style>
        .prod-page {
            display: grid;
            gap: 18px;
            padding-top: 64px;
        }

        .prod-head {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 22px;
            align-items: start;
        }

        .prod-title h1 {
            margin: 0 0 10px;
            font-size: clamp(42px, 5vw, 66px);
            line-height: .95;
        }

        .prod-controls {
            background: var(--lcm-panel);
            border: 1px solid var(--lcm-border);
            border-radius: 16px;
            padding: 16px;
            display: grid;
            gap: 12px;
        }

        .prod-label {
            display: grid;
            gap: 7px;
            color: var(--lcm-muted);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .prod-select,
        .prod-input {
            width: 100%;
            background: #f4f1ea;
            color: #111;
            border: 0;
            border-radius: 10px;
            padding: 10px 12px;
            font-weight: 800;
            font-family: var(--lcm-font-base);
        }

        .periodo-dropdown {
            position: relative;
        }

        .periodo-button {
            width: 100%;
            background: #f4f1ea;
            color: #d90000;
            border: 0;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 22px;
            font-weight: 900;
            text-align: center;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 14px;
            font-family: var(--lcm-font-base);
        }

        .periodo-button span {
            flex: 0 0 auto;
        }

        .periodo-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            max-height: 260px;
            overflow: auto;
            background: #f4f1ea;
            color: #111;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,.15);
            z-index: 30;
            box-shadow: 0 18px 45px rgba(0,0,0,.35);
            padding: 8px;
        }

        .periodo-menu.open {
            display: block;
        }

        .periodo-option {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 8px 9px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 900;
        }

        .periodo-option:hover {
            background: rgba(255,107,53,.14);
        }

        .periodo-option input {
            width: 17px;
            height: 17px;
            accent-color: var(--lcm-orange);
        }

        .prod-zone-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 12px;
        }

        .prod-zone-card {
            background: var(--lcm-panel);
            border: 1px solid var(--lcm-border);
            border-radius: 12px;
            padding: 16px;
            text-align: left;
            cursor: pointer;
            transition: .15s;
            color: var(--lcm-text);
        }

        .prod-zone-card:hover,
        .prod-zone-card.active {
            border-color: var(--lcm-orange);
            box-shadow: 0 0 0 3px rgba(255,107,53,.14);
        }

        .prod-zone-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
            font-weight: 900;
        }

        .prod-share {
            color: var(--lcm-orange);
            font-size: 12px;
        }

        .prod-values {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .prod-metric span {
            display: block;
            color: var(--lcm-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .prod-metric strong {
            display: block;
            margin-top: 4px;
            font-size: 21px;
            color: var(--lcm-text);
        }

        .prod-panel {
            background: var(--lcm-panel);
            border: 1px solid var(--lcm-border);
            border-radius: 14px;
            overflow: hidden;
        }

        .prod-panel-head {
            padding: 16px 18px;
            border-bottom: 1px solid var(--lcm-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
        }

        .prod-panel-head h2,
        .prod-panel-title h2 {
            margin: 0;
            font-size: 18px;
        }

        .prod-panel-head span,
        .prod-panel-title span {
            color: var(--lcm-muted);
            font-size: 12px;
        }

        .prod-panel-titlebar {
            padding: 16px 18px;
            border-bottom: 1px solid var(--lcm-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .prod-panel-title {
            display: grid;
            gap: 4px;
        }

        .prod-filter-bar {
            padding: 16px 18px;
            border-bottom: 1px solid var(--lcm-border);
            background: rgba(255,255,255,.025);
        }

        .prod-filter-group {
            display: grid;
            grid-template-columns: 170px 160px minmax(260px, 420px);
            gap: 16px;
            align-items: end;
            justify-content: start;
        }

        .prod-excel-wrap {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-left: 28px;
            min-width: 180px;
        }

        .prod-excel {
            height: 42px;
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 10px;
            background: #1f7a3a;
            color: #fff;
            font-weight: 900;
            padding: 0 18px;
            cursor: pointer;
            font-family: var(--lcm-font-base);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 8px 22px rgba(0,0,0,.22);
        }

        .prod-excel:hover {
            filter: brightness(1.12);
        }

        .prod-viz {
            padding: 22px 18px;
        }

        .prod-bar-row {
            display: grid;
            grid-template-columns: minmax(140px, 220px) 1fr 90px;
            align-items: center;
            gap: 12px;
            margin: 10px auto;
            max-width: 760px;
            font-size: 13px;
        }

        .prod-bar-label {
            font-weight: 900;
        }

        .prod-bar-track {
            height: 12px;
            background: #343434;
            border-radius: 999px;
            overflow: hidden;
        }

        .prod-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--lcm-orange), #ffb066);
            border-radius: 999px;
        }

        .prod-bar-value {
            text-align: right;
            color: var(--lcm-muted);
        }

        .prod-table-wrap {
            max-height: 54vh;
            overflow: auto;
        }

        .prod-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .prod-table th,
        .prod-table td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--lcm-border);
            text-align: left;
            white-space: nowrap;
        }

        .prod-table th {
            position: sticky;
            top: 0;
            background: #171717;
            color: #d4a084;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .10em;
            z-index: 2;
        }

        .prod-num {
            text-align: right !important;
            font-variant-numeric: tabular-nums;
        }

        .prod-total-row td {
            background: rgba(255,107,53,.10);
            font-weight: 900;
        }

        .prod-empty {
            padding: 20px;
            color: var(--lcm-muted);
        }

        .sucursal-filter {
            padding: 14px 18px;
            border-top: 1px solid var(--lcm-border);
            background: rgba(255,255,255,.02);
            display: grid;
            gap: 10px;
        }

        .sucursal-filter-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .sucursal-filter-head strong {
            font-size: 13px;
        }

        .sucursal-filter-actions {
            display: flex;
            gap: 8px;
        }

        .sucursal-filter-actions button {
            border: 1px solid var(--lcm-border);
            background: #2a2a2a;
            color: var(--lcm-text);
            border-radius: 999px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 800;
        }

        .sucursal-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .sucursal-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid var(--lcm-border);
            background: #262626;
            color: var(--lcm-text);
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 12px;
            cursor: pointer;
        }

        .sucursal-chip input {
            accent-color: var(--lcm-orange);
        }

        @media(max-width:1100px) {
            .prod-head {
                grid-template-columns: 1fr;
            }

            .prod-zone-strip {
                grid-template-columns: repeat(2, minmax(180px, 1fr));
            }

            .prod-filter-group {
                grid-template-columns: repeat(2, minmax(180px, 1fr));
            }
        }

        @media(max-width:760px) {
            .prod-page {
                padding-top: 58px;
            }

            .prod-title h1 {
                font-size: 44px;
                margin-top: 22px;
            }

            .prod-panel-head {
                display: grid;
                grid-template-columns: 1fr;
                align-items: start;
            }

            .prod-panel-titlebar {
                display: grid;
                grid-template-columns: 1fr;
                align-items: start;
            }

            .prod-excel-wrap {
                justify-content: flex-start;
                padding-left: 0;
                min-width: 0;
            }

            .prod-filter-group {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .prod-excel {
                width: auto;
                min-width: 160px;
                justify-content: center;
            }

            .periodo-button {
                font-size: 30px;
                font-weight: 900;
                justify-content: center;
                gap: 16px;
                color: #d90000;
                text-align: center;
            }

            .periodo-option {
                font-size: 22px;
                justify-content: center;
                font-weight: 900;
            }
        }

        @media(max-width:650px) {
            .prod-zone-strip {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="lcm-page lcm-page--with-nav">
<?php lcm_topbar('telefonia'); ?>

<main class="lcm-shell prod-page">

    <section class="prod-head">
        <div class="prod-title">
            <h1>Producción Planta Externa</h1>
            <p class="lcm-muted">
                Resumen venta devengada, período, zona contrato, sucursales, contratistas y HB ejecutadas.
            </p>
        </div>

        <aside class="prod-controls">
            <div class="prod-label">
                Periodos
                <div class="periodo-dropdown">
                    <button id="periodoBtn" class="periodo-button" type="button">
                        <span>Seleccionar</span>
                        <strong>▾</strong>
                    </button>
                    <div id="periodoMenu" class="periodo-menu"></div>
                </div>
            </div>

            <span id="stamp" class="lcm-chip">Actualizando...</span>
        </aside>
    </section>

    <section>
        <div id="zones" class="prod-zone-strip"></div>
    </section>

    <section class="prod-panel">
        <div class="prod-panel-head">
            <div>
                <span id="distribucionSubtitulo">Distribución de venta</span>
                <h2 id="distribucionTitulo">Sucursales por zona contrato</h2>
            </div>
            <strong id="totalVentaViz">$ 0</strong>
        </div>

        <div id="bars" class="prod-viz"></div>

        <div class="prod-table-wrap">
            <table class="prod-table">
                <thead>
                    <tr>
                        <th id="thDistribucion">Zona contrato</th>
                        <th class="prod-num">HB PTRS</th>
                        <th class="prod-num">HB OCRAS</th>
                        <th class="prod-num">Total HB</th>
                        <th class="prod-num">Venta</th>
                        <th class="prod-num">% Venta</th>
                    </tr>
                </thead>
                <tbody id="distribucionBody"></tbody>
                <tfoot id="distribucionTotal"></tfoot>
            </table>
        </div>

        <div class="sucursal-filter" id="sucursalFilter">
            <div class="sucursal-filter-head">
                <strong>Filtrar contratistas por sucursal</strong>
                <div class="sucursal-filter-actions">
                    <button type="button" onclick="seleccionarTodasSucursales()">Todas</button>
                    <button type="button" onclick="limpiarSucursales()">Ninguna</button>
                </div>
            </div>
            <div class="sucursal-options" id="sucursalOptions"></div>
        </div>
    </section>

    <section class="prod-panel">
        <div class="prod-panel-titlebar">
            <div class="prod-panel-title">
                <span id="zonaSeleccionada">Todas las zonas</span>
                <h2>Contratistas</h2>
            </div>

            <div class="prod-excel-wrap">
                <button class="prod-excel" type="button" onclick="exportarExcel()">
                    <span>📗</span> Exportar Excel
                </button>
            </div>
        </div>

        <div class="prod-filter-bar">
            <div class="prod-filter-group">
                <label class="prod-label">
                    Tipo contratista
                    <select id="tipoContratista" class="prod-select">
                        <option value="TODOS">Todos</option>
                        <option value="PROP">PROP</option>
                        <option value="CONT">CONT</option>
                    </select>
                </label>

                <label class="prod-label">
                    Tareas
                    <select id="tipo" class="prod-select">
                        <option value="TODAS">Todas</option>
                        <option value="PTRS">PTRS</option>
                        <option value="OCRAS">OCRAS</option>
                        <option value="MAREA">MAREA</option>
                    </select>
                </label>

                <label class="prod-label">
                    Contratista
                    <input id="contratista" class="prod-input" type="text" placeholder="Buscar...">
                </label>
            </div>
        </div>

        <div class="prod-table-wrap">
            <table class="prod-table" id="tablaContratistas">
                <thead>
                    <tr>
                        <th>Contratista</th>
                        <th class="prod-num">HB PTRS</th>
                        <th class="prod-num">HB OCRAS</th>
                        <th class="prod-num">Total HB</th>
                        <th class="prod-num">Venta</th>
                        <th class="prod-num">% sobre total</th>
                    </tr>
                </thead>
                <tbody id="contratistasBody"></tbody>
                <tfoot id="contratistasTotal"></tfoot>
            </table>
        </div>
    </section>

</main>

<?php lcm_footer(); ?>

<script>
const API_BASE = '/api/telefonia/produccion_planta';

const periodoBtn = document.getElementById('periodoBtn');
const periodoMenu = document.getElementById('periodoMenu');
const tipoEl = document.getElementById('tipo');
const tipoContratistaEl = document.getElementById('tipoContratista');
const contratistaEl = document.getElementById('contratista');

const zonesEl = document.getElementById('zones');
const barsEl = document.getElementById('bars');

const distribucionBody = document.getElementById('distribucionBody');
const distribucionTotal = document.getElementById('distribucionTotal');
const distribucionTitulo = document.getElementById('distribucionTitulo');
const distribucionSubtitulo = document.getElementById('distribucionSubtitulo');
const thDistribucion = document.getElementById('thDistribucion');

const sucursalOptions = document.getElementById('sucursalOptions');

const contratistasBody = document.getElementById('contratistasBody');
const contratistasTotal = document.getElementById('contratistasTotal');

const stamp = document.getElementById('stamp');
const totalVentaViz = document.getElementById('totalVentaViz');
const zonaSeleccionada = document.getElementById('zonaSeleccionada');

let zonaActiva = 'Total compañía';
let periodosDisponibles = [];
let periodosSeleccionados = [];
let sucursalesDisponibles = [];
let sucursalesSeleccionadas = [];
let debounceTimer = null;

function n(valor) {
    return Number(valor || 0);
}

function fmtNum(valor) {
    return n(valor).toLocaleString('es-AR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

function fmtMoney(valor) {
    return '$ ' + n(valor).toLocaleString('es-AR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

function fmtPct(valor) {
    return n(valor).toLocaleString('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + '%';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeJs(value) {
    return String(value ?? '').replaceAll("\\", "\\\\").replaceAll("'", "\\'");
}

function periodosParam() {
    return periodosSeleccionados.join(',');
}

function sucursalesParam() {
    if (!sucursalesDisponibles.length) return '';
    if (sucursalesSeleccionadas.length === sucursalesDisponibles.length) return '';
    return sucursalesSeleccionadas.join(',');
}

function actualizarTextoPeriodo() {
    const span = periodoBtn.querySelector('span');

    if (periodosSeleccionados.length === 0) {
        span.textContent = 'Seleccionar';
    } else if (periodosSeleccionados.length === 1) {
        span.textContent = periodosSeleccionados[0];
    } else if (periodosSeleccionados.length === 2) {
        span.textContent = periodosSeleccionados.join(' + ');
    } else {
        span.textContent = `${periodosSeleccionados.length} períodos seleccionados`;
    }
}

function renderPeriodos() {
    periodoMenu.innerHTML = periodosDisponibles.map(p => `
        <label class="periodo-option">
            <input type="checkbox" value="${p}" ${periodosSeleccionados.includes(p) ? 'checked' : ''}>
            <span>${p}</span>
        </label>
    `).join('');

    periodoMenu.querySelectorAll('input[type="checkbox"]').forEach(chk => {
        chk.addEventListener('change', () => {
            const value = chk.value;

            if (chk.checked) {
                if (!periodosSeleccionados.includes(value)) {
                    periodosSeleccionados.push(value);
                }
            } else {
                periodosSeleccionados = periodosSeleccionados.filter(p => p !== value);
            }

            periodosSeleccionados.sort((a, b) => b.localeCompare(a));
            actualizarTextoPeriodo();

            zonaActiva = 'Total compañía';
            sucursalesDisponibles = [];
            sucursalesSeleccionadas = [];
            refrescarTodo();
        });
    });
}

periodoBtn.addEventListener('click', () => {
    periodoMenu.classList.toggle('open');
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.periodo-dropdown')) {
        periodoMenu.classList.remove('open');
    }
});

async function cargarEstado() {
    try {
        const res = await fetch(`${API_BASE}/estado.php`);
        const json = await res.json();

        if (!json.ok || !json.estado) {
            stamp.textContent = 'Actualización: sin datos';
            return;
        }

        const fecha = json.estado.ultima_fecha_hora_actualizacion || json.estado.updated_at;
        stamp.textContent = `Actualizado: ${fecha}`;
    } catch (e) {
        stamp.textContent = 'Actualización: sin datos';
    }
}

async function cargarPeriodos() {
    const res = await fetch(`${API_BASE}/periodos.php`);
    const json = await res.json();

    if (!json.ok) {
        zonesEl.innerHTML = `<div class="prod-empty">No se pudieron cargar los períodos.</div>`;
        return;
    }

    periodosDisponibles = json.periodos || [];
    periodosSeleccionados = periodosDisponibles.length ? [periodosDisponibles[0]] : [];

    renderPeriodos();
    actualizarTextoPeriodo();
    await refrescarTodo();
}

async function refrescarTodo() {
    await cargarEstado();
    await cargarZonas();
    await cargarDistribucion();
    await cargarContratistas();
}

async function cargarZonas() {
    const periodos = periodosParam();

    if (!periodos) {
        zonesEl.innerHTML = `<div class="prod-empty">Seleccioná al menos un período.</div>`;
        return;
    }

    const res = await fetch(`${API_BASE}/zonas.php?periodos=${encodeURIComponent(periodos)}`);
    const json = await res.json();

    if (!json.ok) {
        zonesEl.innerHTML = `<div class="prod-empty">No se pudieron cargar las zonas.</div>`;
        return;
    }

    const total = json.total;
    const zonas = json.zonas || [];
    const cards = [total, ...zonas];

    zonesEl.innerHTML = cards.map(z => `
        <button class="prod-zone-card ${z.zona === zonaActiva ? 'active' : ''}"
                type="button"
                onclick="seleccionarZona('${escapeJs(z.zona)}')">
            <div class="prod-zone-top">
                <span>${escapeHtml(z.zona)}</span>
                <span class="prod-share">${fmtPct(z.share)}</span>
            </div>

            <div class="prod-values">
                <div class="prod-metric">
                    <span>HB</span>
                    <strong>${fmtNum(z.total_hb)}</strong>
                </div>
                <div class="prod-metric">
                    <span>Importe</span>
                    <strong>${fmtNum(z.venta)}</strong>
                </div>
            </div>
        </button>
    `).join('');
}

async function cargarDistribucion() {
    const periodos = periodosParam();

    if (!periodos) return;

    const params = new URLSearchParams();
    params.set('periodos', periodos);
    params.set('zona', zonaActiva);

    const res = await fetch(`${API_BASE}/distribucion.php?${params.toString()}`);
    const json = await res.json();

    if (!json.ok) {
        barsEl.innerHTML = `<div class="prod-empty">No se pudo cargar la distribución.</div>`;
        return;
    }

    const rows = json.rows || [];
    const totalVenta = n(json.total_venta);

    totalVentaViz.textContent = fmtMoney(totalVenta);

    if (zonaActiva === 'Total compañía') {
        distribucionTitulo.textContent = 'Sucursales por zona contrato';
        distribucionSubtitulo.textContent = 'Distribución de venta por zona';
        thDistribucion.textContent = 'Zona contrato';
    } else {
        distribucionTitulo.textContent = `Sucursales de ${zonaActiva}`;
        distribucionSubtitulo.textContent = 'Distribución de venta por sucursal';
        thDistribucion.textContent = 'Sucursal';
    }

    if (!rows.length) {
        barsEl.innerHTML = `<div class="prod-empty">Sin datos para la distribución.</div>`;
        distribucionBody.innerHTML = '';
        distribucionTotal.innerHTML = '';
        renderSucursales([]);
        return;
    }

    barsEl.innerHTML = rows.map(r => `
        <div class="prod-bar-row">
            <div class="prod-bar-label">${escapeHtml(r.nombre)}</div>
            <div class="prod-bar-track">
                <div class="prod-bar-fill" style="width:${Math.max(0, Math.min(100, n(r.share)))}%"></div>
            </div>
            <div class="prod-bar-value">${fmtPct(r.share)}</div>
        </div>
    `).join('');

    let totalPtr = 0;
    let totalOcra = 0;
    let totalHb = 0;

    distribucionBody.innerHTML = rows.map(r => {
        totalPtr += n(r.hb_ptrs);
        totalOcra += n(r.hb_ocras);
        totalHb += n(r.total_hb);

        return `
            <tr>
                <td><strong>${escapeHtml(r.nombre)}</strong></td>
                <td class="prod-num">${fmtNum(r.hb_ptrs)}</td>
                <td class="prod-num">${fmtNum(r.hb_ocras)}</td>
                <td class="prod-num">${fmtNum(r.total_hb)}</td>
                <td class="prod-num">${fmtMoney(r.venta)}</td>
                <td class="prod-num">${fmtPct(r.share)}</td>
            </tr>
        `;
    }).join('');

    distribucionTotal.innerHTML = `
        <tr class="prod-total-row">
            <td>Total</td>
            <td class="prod-num">${fmtNum(totalPtr)}</td>
            <td class="prod-num">${fmtNum(totalOcra)}</td>
            <td class="prod-num">${fmtNum(totalHb)}</td>
            <td class="prod-num">${fmtMoney(totalVenta)}</td>
            <td class="prod-num">100,00%</td>
        </tr>
    `;

    renderSucursales(rows.map(r => r.nombre));
}

function renderSucursales(nombres) {
    sucursalesDisponibles = nombres;

    if (!sucursalesSeleccionadas.length || sucursalesSeleccionadas.some(s => !nombres.includes(s))) {
        sucursalesSeleccionadas = [...nombres];
    }

    if (!nombres.length) {
        sucursalOptions.innerHTML = `<span class="lcm-muted">Sin sucursales disponibles.</span>`;
        return;
    }

    sucursalOptions.innerHTML = nombres.map(nombre => `
        <label class="sucursal-chip">
            <input type="checkbox"
                   value="${escapeHtml(nombre)}"
                   ${sucursalesSeleccionadas.includes(nombre) ? 'checked' : ''}>
            <span>${escapeHtml(nombre)}</span>
        </label>
    `).join('');

    sucursalOptions.querySelectorAll('input[type="checkbox"]').forEach(chk => {
        chk.addEventListener('change', () => {
            const value = chk.value;

            if (chk.checked) {
                if (!sucursalesSeleccionadas.includes(value)) {
                    sucursalesSeleccionadas.push(value);
                }
            } else {
                sucursalesSeleccionadas = sucursalesSeleccionadas.filter(s => s !== value);
            }

            cargarContratistas();
        });
    });
}

function seleccionarTodasSucursales() {
    sucursalesSeleccionadas = [...sucursalesDisponibles];
    renderSucursales(sucursalesDisponibles);
    cargarContratistas();
}

function limpiarSucursales() {
    sucursalesSeleccionadas = [];
    renderSucursales(sucursalesDisponibles);
    cargarContratistas();
}

async function cargarContratistas() {
    const periodos = periodosParam();

    if (!periodos) {
        contratistasBody.innerHTML = `<tr><td colspan="6">Seleccioná al menos un período.</td></tr>`;
        return;
    }

    const params = new URLSearchParams();
    params.set('periodos', periodos);

    if (zonaActiva && zonaActiva !== 'Total compañía') {
        params.set('zona', zonaActiva);
    }

    const sucursales = sucursalesParam();
    if (sucursales) {
        params.set('sucursales', sucursales);
    }

    params.set('tipo_contratista', tipoContratistaEl.value);
    params.set('tipo', tipoEl.value);

    if (contratistaEl.value.trim()) {
        params.set('contratista', contratistaEl.value.trim());
    }

    contratistasBody.innerHTML = `<tr><td colspan="6">Cargando contratistas...</td></tr>`;

    const res = await fetch(`${API_BASE}/contratistas.php?${params.toString()}`);
    const json = await res.json();

    if (!json.ok) {
        contratistasBody.innerHTML = `<tr><td colspan="6">No se pudieron cargar los contratistas.</td></tr>`;
        return;
    }

    const rows = json.rows || [];

    zonaSeleccionada.textContent = zonaActiva === 'Total compañía'
        ? 'Todas las zonas'
        : zonaActiva;

    let totalPtr = 0;
    let totalOcra = 0;
    let totalHb = 0;
    let totalVenta = 0;

    if (!rows.length) {
        contratistasBody.innerHTML = `<tr><td colspan="6">Sin datos para los filtros seleccionados.</td></tr>`;
        contratistasTotal.innerHTML = '';
        return;
    }

    contratistasBody.innerHTML = rows.map(r => {
        totalPtr += n(r.hb_ptrs);
        totalOcra += n(r.hb_ocras);
        totalHb += n(r.total_hb);
        totalVenta += n(r.venta);

        return `
            <tr>
                <td><strong>${escapeHtml(r.contratista)}</strong></td>
                <td class="prod-num">${fmtNum(r.hb_ptrs)}</td>
                <td class="prod-num">${fmtNum(r.hb_ocras)}</td>
                <td class="prod-num">${fmtNum(r.total_hb)}</td>
                <td class="prod-num">${fmtMoney(r.venta)}</td>
                <td class="prod-num">${fmtPct(r.share)}</td>
            </tr>
        `;
    }).join('');

    contratistasTotal.innerHTML = `
        <tr class="prod-total-row">
            <td>Total</td>
            <td class="prod-num">${fmtNum(totalPtr)}</td>
            <td class="prod-num">${fmtNum(totalOcra)}</td>
            <td class="prod-num">${fmtNum(totalHb)}</td>
            <td class="prod-num">${fmtMoney(totalVenta)}</td>
            <td class="prod-num">100,00%</td>
        </tr>
    `;
}

function seleccionarZona(zona) {
    zonaActiva = zona;
    sucursalesDisponibles = [];
    sucursalesSeleccionadas = [];
    cargarZonas();
    cargarDistribucion();
    cargarContratistas();
}

function exportarExcel() {
    const params = new URLSearchParams();
    params.set('periodos', periodosParam());

    if (zonaActiva && zonaActiva !== 'Total compañía') {
        params.set('zona', zonaActiva);
    }

    const sucursales = sucursalesParam();
    if (sucursales) {
        params.set('sucursales', sucursales);
    }

    params.set('tipo_contratista', tipoContratistaEl.value);
    params.set('tipo', tipoEl.value);

    if (contratistaEl.value.trim()) {
        params.set('contratista', contratistaEl.value.trim());
    }

    window.location.href = `${API_BASE}/exportar_excel.php?${params.toString()}`;
}

tipoEl.addEventListener('change', cargarContratistas);
tipoContratistaEl.addEventListener('change', cargarContratistas);

contratistaEl.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(cargarContratistas, 350);
});

cargarPeriodos();
</script>

</body>
</html>