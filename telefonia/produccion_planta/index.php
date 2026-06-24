<?php
require_once __DIR__ . '/../../shared/layout.php';
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Producción Planta Externa', [
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css'
    ]); ?>

    <style>
        .prod-page {
            display: grid;
            gap: 18px;
            padding-top: 64px;
            padding-bottom: 120px;
        }

        .prod-head {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 22px;
            align-items: start;
        }

        .prod-title h1 {
            margin: 0 0 10px;
            font-size: clamp(32px, 5vw, 52px);
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
            font-size: 18px;
            font-weight: bold;
            text-align: center;
             text-shadow: 0 0 1px #d90000;
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

        .periodo-options { max-height: 200px; overflow: auto; }

        .periodo-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(0,0,0,.14);
        }

        .periodo-actions button {
            border: 0;
            border-radius: 8px;
            padding: 9px 10px;
            cursor: pointer;
            font-family: var(--lcm-font-base);
            font-weight: 900;
        }

        .periodo-clear { background: #ded8ce; color: #111; }
        .periodo-apply { background: var(--lcm-orange); color: #111; }
        .periodo-apply:disabled { cursor: not-allowed; opacity: .5; }

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

        .prod-table-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(120px, 1fr));
            gap: 1px;
            background: var(--lcm-border);
            border-bottom: 1px solid var(--lcm-border);
        }

        .prod-table-summary div { padding: 12px 18px; background: #2a1d16; }
        .prod-table-summary span {
            display: block;
            margin-bottom: 4px;
            color: var(--lcm-muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .prod-table-summary strong {
            color: var(--lcm-text);
            font-size: 18px;
            font-variant-numeric: tabular-nums;
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

        .prod-detail-button {
            width: 34px;
            height: 34px;
            border: 1px solid var(--lcm-border);
            border-radius: 9px;
            background: #2a2a2a;
            color: var(--lcm-orange);
            cursor: pointer;
        }

        .prod-detail-button:hover,
        .prod-detail-button:focus-visible {
            border-color: var(--lcm-orange);
            background: rgba(255,107,53,.12);
        }

        .prod-modal[hidden] { display: none; }
        .prod-modal {
            position: fixed;
            inset: 0;
            bottom: 49px;
            z-index: 100;
            display: grid;
            place-items: center;
            padding: 12px;
            background: rgba(0,0,0,.78);
        }

        .prod-modal-card {
            width: 100%;
            max-height: 100%;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            overflow: hidden;
            border: 1px solid var(--lcm-border);
            border-radius: 16px;
            background: var(--lcm-panel);
            box-shadow: 0 24px 80px rgba(0,0,0,.55);
        }

        .prod-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--lcm-border);
        }

        .prod-modal-title { min-width: 0; }
        .prod-modal-title h2 { margin: 0 0 4px; font-size: 20px; }
        .prod-modal-title strong {
            display: block;
            margin-bottom: 2px;
            color: var(--lcm-text);
            font-size: 18px;
            font-weight: 900;
        }
        .prod-modal-title span { display: block; color: var(--lcm-muted); font-size: 12px; }
        .prod-modal-actions { display: flex; align-items: center; gap: 10px; }
        .prod-modal-close {
            width: 40px;
            height: 40px;
            border: 1px solid var(--lcm-border);
            border-radius: 10px;
            background: #2a2a2a;
            color: var(--lcm-text);
            cursor: pointer;
            font-size: 18px;
        }

        .prod-modal .prod-table-wrap { max-height: none; }
        .prod-modal .prod-table th { z-index: 3; }
        .prod-modal .prod-table { min-width: 1250px; }
        .prod-detail-description {
            width: 100ch;
            max-width: 100ch;
            white-space: normal !important;
            overflow-wrap: break-word;
            word-break: normal;
            line-height: 1.35;
        }

        .lcm-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
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

        .prod-table tfoot td {
            position: static;
            background: #2a1d16;
            font-weight: 900;
            border-top: 2px solid var(--lcm-orange);
        }

        .prod-num {
            text-align: right !important;
            font-variant-numeric: tabular-nums;
        }

        .prod-center {
            text-align: center !important;
            font-variant-numeric: tabular-nums;
        }

        .prod-total-row td {
            background: rgba(255,107,53,.10);
            font-weight: 900;
            color: #fff;
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


       

@media(max-width:760px) {
    .prod-summary-fixed {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 7px 14px;
        justify-content: start;
        font-size: 12px;
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


            .prod-summary-fixed {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 6px 14px;
                font-size: 12px;
                padding: 8px 14px;
                bottom: 43px;
            }

        @media(max-width:650px) {
            .prod-table-summary { grid-template-columns: 1fr 1fr; }

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
                Período
                <div class="periodo-dropdown">
                    <button id="periodoBtn" class="periodo-button" type="button" aria-expanded="false" aria-controls="periodoMenu">
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
                        <option value="PROP">MANO OBRA PROPIA</option>
                        <option value="CONT">M.O. SUBCONTRATADA</option>
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

        <div class="prod-table-summary" aria-live="polite" aria-label="Totales de contratistas filtrados">
            <div><span>HB PTRS</span><strong id="resumenPtr">0</strong></div>
            <div><span>HB OCRAS</span><strong id="resumenOcra">0</strong></div>
            <div><span>Total HB</span><strong id="resumenHb">0</strong></div>
            <div><span>Venta devengada</span><strong id="resumenVenta">$ 0</strong></div>
        </div>

        <div class="prod-table-wrap">
            
            <table class="prod-table" id="tablaContratistas">
                <thead>
                    <tr>
                        <th>Contratista</th>
                        <th class="prod-num">HB PTRS</th>
                        <th class="prod-num">HB OCRAS</th>
                        <th class="prod-num">TOTAL HB</th>
                        <th class="prod-num">Venta Devengada</th>
                        <th class="prod-num">PONDERADO</th>
                        <th class="prod-num">Detalle</th>
                    </tr>
                </thead>
                <tbody id="contratistasBody"></tbody>
                <tfoot id="contratistasTotal"></tfoot>
            </table>
        </div>
    </section>

    <div id="detalleModal" class="prod-modal" role="dialog" aria-modal="true" aria-labelledby="detalleTitulo" hidden>
        <section class="prod-modal-card">
            <header class="prod-modal-head">
                <div class="prod-modal-title">
                    <h2 id="detalleTitulo">Detalle de producción por Cuadrilla/Contratista</h2>
                    <strong id="detalleContratista">Contratista</strong>
                    <span id="detalleContexto">Período</span>
                </div>
                <div class="prod-modal-actions">
                    <button id="detalleExcel" class="prod-excel" type="button">
                        <i class="fa-solid fa-file-excel" aria-hidden="true"></i> Exportar XLSX
                    </button>
                    <button id="detalleCerrar" class="prod-modal-close" type="button" aria-label="Cerrar detalle">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            </header>
            <div id="detalleTableWrap" class="prod-table-wrap">
                <table class="prod-table">
                    <thead>
                        <tr>
                            <th>SIGEST</th>
                            <th>Sucursal</th>
                            <th class="prod-detail-description">Descripción</th>
                            <th>Tarea</th>
                            <th class="prod-num">HS L</th>
                            <th class="prod-num">HS N</th>
                            <th class="prod-num">HS LZ</th>
                            <th class="prod-num">HS LC</th>
                            <th class="prod-num">Total HB</th>
                            <th class="prod-center">Precio</th>
                            <th class="prod-num">Valor Venta</th>
                        </tr>
                    </thead>
                    <tbody id="detalleBody"></tbody>
                    <tfoot id="detalleTotal"></tfoot>
                </table>
            </div>
        </section>
    </div>

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
const resumenPtr = document.getElementById('resumenPtr');
const resumenOcra = document.getElementById('resumenOcra');
const resumenHb = document.getElementById('resumenHb');
const resumenVenta = document.getElementById('resumenVenta');
const detalleModal = document.getElementById('detalleModal');
const detalleTitulo = document.getElementById('detalleTitulo');
const detalleContratista = document.getElementById('detalleContratista');
const detalleContexto = document.getElementById('detalleContexto');
const detalleBody = document.getElementById('detalleBody');
const detalleTotal = document.getElementById('detalleTotal');
const detalleTableWrap = document.getElementById('detalleTableWrap');
const detalleExcel = document.getElementById('detalleExcel');
const detalleCerrar = document.getElementById('detalleCerrar');

let zonaActiva = 'Total compañía';
let periodosDisponibles = [];
let periodosSeleccionados = [];
let periodosPendientes = [];
let sucursalesDisponibles = [];
let sucursalesSeleccionadas = [];
let debounceTimer = null;
let contratistaDetalle = '';
let detalleTrigger = null;

function n(valor) {
    return Number(valor || 0);
}

function fmtNum(valor) {
    return n(valor).toLocaleString('es-AR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

function fmtHoras(valor) {
    return n(valor).toLocaleString('es-AR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
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

function textoEnLineas(value, maximo = 100) {
    const palabras = String(value ?? '').trim().split(/\s+/).filter(Boolean);
    const lineas = [];
    let linea = '';

    palabras.forEach(palabra => {
        const propuesta = linea ? `${linea} ${palabra}` : palabra;
        if (linea && propuesta.length > maximo) {
            lineas.push(linea);
            linea = palabra;
        } else {
            linea = propuesta;
        }
    });

    if (linea) lineas.push(linea);
    return lineas.map(escapeHtml).join('<br>');
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
    periodoMenu.innerHTML = `
        <div class="periodo-options">
            ${periodosDisponibles.map(p => `
                <label class="periodo-option">
                    <input type="checkbox" value="${escapeHtml(p)}" ${periodosPendientes.includes(p) ? 'checked' : ''}>
                    <span>${escapeHtml(p)}</span>
                </label>
            `).join('')}
        </div>
        <div class="periodo-actions">
            <button id="limpiarPeriodos" class="periodo-clear" type="button">Limpiar</button>
            <button id="aceptarPeriodos" class="periodo-apply" type="button" ${periodosPendientes.length ? '' : 'disabled'}>Aceptar</button>
        </div>
    `;

    periodoMenu.querySelectorAll('input[type="checkbox"]').forEach(chk => {
        chk.addEventListener('change', () => {
            const value = chk.value;

            if (chk.checked) {
                if (!periodosPendientes.includes(value)) {
                    periodosPendientes.push(value);
                }
            } else {
                periodosPendientes = periodosPendientes.filter(p => p !== value);
            }

            periodosPendientes.sort((a, b) => b.localeCompare(a));
            document.getElementById('aceptarPeriodos').disabled = periodosPendientes.length === 0;
        });
    });

    document.getElementById('limpiarPeriodos').addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        periodosPendientes = [];
        periodosSeleccionados = [];
        actualizarTextoPeriodo();
        zonaActiva = 'Total compañía';
        sucursalesDisponibles = [];
        sucursalesSeleccionadas = [];
        renderPeriodos();
        periodoMenu.classList.add('open');
        periodoBtn.setAttribute('aria-expanded', 'true');
        await refrescarTodo();
    });

    document.getElementById('aceptarPeriodos').addEventListener('click', async () => {
        if (!periodosPendientes.length) return;

        periodosSeleccionados = [...periodosPendientes];
        actualizarTextoPeriodo();
        periodoMenu.classList.remove('open');
        periodoBtn.setAttribute('aria-expanded', 'false');
        sucursalesDisponibles = [];
        sucursalesSeleccionadas = [];
        await refrescarTodo();
    });
}

periodoBtn.addEventListener('click', () => {
    periodosPendientes = [...periodosSeleccionados];
    renderPeriodos();
    periodoMenu.classList.toggle('open');
    periodoBtn.setAttribute('aria-expanded', periodoMenu.classList.contains('open') ? 'true' : 'false');
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.periodo-dropdown')) {
        periodoMenu.classList.remove('open');
        periodoBtn.setAttribute('aria-expanded', 'false');
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
    let json;

    try {
        const res = await fetch(`${API_BASE}/periodos.php`);
        json = await res.json();
    } catch (e) {
        zonesEl.innerHTML = `<div class="prod-empty">No se pudieron cargar los períodos.</div>`;
        actualizarResumenFijo();
        return;
    }

    if (!json.ok) {
        zonesEl.innerHTML = `<div class="prod-empty">No se pudieron cargar los períodos.</div>`;
        return;
    }

    periodosDisponibles = json.periodos || [];
    periodosSeleccionados = periodosDisponibles.length ? [periodosDisponibles[0]] : [];
    periodosPendientes = [...periodosSeleccionados];

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

    if (!cards.some(z => z && z.zona === zonaActiva)) {
        zonaActiva = total?.zona || 'Total compañía';
    }

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

    if (!periodos) {
        barsEl.innerHTML = `<div class="prod-empty">Seleccioná al menos un período.</div>`;
        distribucionBody.innerHTML = '';
        distribucionTotal.innerHTML = '';
        totalVentaViz.textContent = fmtMoney(0);
        renderSucursales([]);
        return;
    }

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

function actualizarResumenFijo(totalPtr = 0, totalOcra = 0, totalHb = 0, totalVenta = 0) {
    resumenPtr.textContent = fmtNum(totalPtr);
    resumenOcra.textContent = fmtNum(totalOcra);
    resumenHb.textContent = fmtNum(totalHb);
    resumenVenta.textContent = fmtMoney(totalVenta);
}

function crearParametrosDetalle(contratista) {
    const params = new URLSearchParams();
    params.set('periodos', periodosParam());
    params.set('contratista', contratista);
    params.set('tipo_contratista', tipoContratistaEl.value);
    params.set('tipo', tipoEl.value);

    if (zonaActiva && zonaActiva !== 'Total compañía') {
        params.set('zona', zonaActiva);
    }

    const sucursales = sucursalesParam();
    if (sucursales) {
        params.set('sucursales', sucursales);
    }

    return params;
}

async function abrirDetalleContratista(contratista, trigger) {
    contratistaDetalle = contratista;
    detalleTrigger = trigger;
    detalleContratista.textContent = contratista;
    detalleContexto.textContent = `Período: ${periodosSeleccionados.join(', ')} · Tareas: ${tipoEl.options[tipoEl.selectedIndex].text}`;
    detalleBody.innerHTML = `<tr><td colspan="11">Cargando obras...</td></tr>`;
    detalleTotal.innerHTML = '';
    detalleModal.hidden = false;
    document.body.style.overflow = 'hidden';
    detalleTableWrap.scrollTop = 0;
    detalleTableWrap.scrollLeft = 0;
    detalleCerrar.focus();

    try {
        const params = crearParametrosDetalle(contratista);
        const res = await fetch(`${API_BASE}/obras.php?${params.toString()}`);
        const json = await res.json();

        if (!res.ok || !json.ok) {
            throw new Error(json.error || 'No se pudo cargar el detalle.');
        }

        const rows = json.rows || [];
        if (!rows.length) {
            detalleBody.innerHTML = `<tr><td colspan="11">Sin obras para los filtros seleccionados.</td></tr>`;
            return;
        }

        detalleBody.innerHTML = rows.map(r => `
            <tr>
                <td><strong>${escapeHtml(r.sigest)}</strong></td>
                <td>${escapeHtml(r.sucursal)}</td>
                <td class="prod-detail-description">${textoEnLineas(r.descripcion)}</td>
                <td>${escapeHtml(r.tipo)}</td>
                <td class="prod-num">${fmtHoras(r.nl)}</td>
                <td class="prod-num">${fmtHoras(r.nn)}</td>
                <td class="prod-num">${fmtHoras(r.nlz)}</td>
                <td class="prod-num">${fmtHoras(r.nlc)}</td>
                <td class="prod-num">${fmtHoras(r.total_hb)}</td>
                <td class="prod-center">${fmtNum(r.precio)}</td>
                <td class="prod-num">${fmtMoney(r.valor_venta)}</td>
            </tr>
        `).join('');

        const totales = json.totales || {};
        detalleTotal.innerHTML = `
            <tr class="prod-total-row">
                <td colspan="4">Total</td>
                <td class="prod-num">${fmtHoras(totales.nl)}</td>
                <td class="prod-num">${fmtHoras(totales.nn)}</td>
                <td class="prod-num">${fmtHoras(totales.nlz)}</td>
                <td class="prod-num">${fmtHoras(totales.nlc)}</td>
                <td class="prod-num">${fmtHoras(totales.total_hb)}</td>
                <td></td>
                <td class="prod-num">${fmtMoney(totales.valor_venta)}</td>
            </tr>
        `;
    } catch (e) {
        detalleBody.innerHTML = `<tr><td colspan="11">No se pudo cargar el detalle de obras.</td></tr>`;
    }
}

function cerrarDetalleContratista() {
    detalleModal.hidden = true;
    document.body.style.overflow = '';
    detalleTrigger?.focus();
}

async function cargarContratistas() {
    const periodos = periodosParam();

    if (!periodos) {
        contratistasBody.innerHTML = `<tr><td colspan="7">Seleccioná al menos un período.</td></tr>`;
        contratistasTotal.innerHTML = '';
        actualizarResumenFijo();
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

    contratistasBody.innerHTML = `<tr><td colspan="7">Cargando contratistas...</td></tr>`;

    const res = await fetch(`${API_BASE}/contratistas.php?${params.toString()}`);
    const json = await res.json();

    if (!json.ok) {
        contratistasBody.innerHTML = `<tr><td colspan="7">No se pudieron cargar los contratistas.</td></tr>`;
        contratistasTotal.innerHTML = '';
        actualizarResumenFijo();
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
        contratistasBody.innerHTML = `<tr><td colspan="7">Sin datos para los filtros seleccionados.</td></tr>`;
        contratistasTotal.innerHTML = '';
        actualizarResumenFijo();
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
                <td class="prod-num">
                    <button class="prod-detail-button" type="button" data-contratista="${escapeHtml(r.contratista)}" aria-label="Ver obras de ${escapeHtml(r.contratista)}" title="Ver detalle de obras">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    contratistasBody.querySelectorAll('.prod-detail-button').forEach(button => {
        button.addEventListener('click', () => abrirDetalleContratista(button.dataset.contratista, button));
    });

    actualizarResumenFijo(totalPtr, totalOcra, totalHb, totalVenta);

    contratistasTotal.innerHTML = `
        <tr class="prod-total-row">
            <td>Total</td>
            <td class="prod-num">${fmtNum(totalPtr)}</td>
            <td class="prod-num">${fmtNum(totalOcra)}</td>
            <td class="prod-num">${fmtNum(totalHb)}</td>
            <td class="prod-num">${fmtMoney(totalVenta)}</td>
            <td class="prod-num">100,00%</td>
            <td></td>
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

detalleCerrar.addEventListener('click', cerrarDetalleContratista);
detalleModal.addEventListener('click', (e) => {
    if (e.target === detalleModal) cerrarDetalleContratista();
});
detalleExcel.addEventListener('click', () => {
    if (!contratistaDetalle) return;
    window.location.href = `${API_BASE}/exportar_obras_excel.php?${crearParametrosDetalle(contratistaDetalle).toString()}`;
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !detalleModal.hidden) cerrarDetalleContratista();
});

cargarPeriodos();
</script>

</body>
</html>
