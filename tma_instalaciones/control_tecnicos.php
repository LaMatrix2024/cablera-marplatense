<?php
declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalaciones por técnico</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/branding/favicon-32.png">
  <link rel="stylesheet" href="../assets/css/lcm-erp.css">
  <style>
    :root {
      --bg: var(--lcm-bg);
      --panel: var(--lcm-surface);
      --text: var(--lcm-text);
      --muted: var(--lcm-text-muted);
      --line: var(--lcm-border);
      --blue: var(--lcm-active);
      --green: var(--lcm-success);
      --violet: var(--lcm-primary);
      --orange: var(--lcm-warning);
      --red: var(--lcm-danger);
      --shadow: var(--lcm-shadow);
    }
    * { box-sizing: border-box; }
    html,
    body {
      height: 100%;
    }
    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font-family: var(--lcm-font);
      overflow: hidden;
    }
    button, input, select { font: inherit; }
    .page {
      width: 100%;
      height: 100dvh;
      margin: 0 auto;
      padding: 8px 10px;
      display: flex;
      flex-direction: column;
      gap: 7px;
      overflow: hidden;
    }
    .topbar {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 20px;
      flex: 0 0 auto;
      background: var(--panel);
      color: var(--text);
      border: 1px solid var(--line);
      border-left: 5px solid var(--lcm-primary);
      border-radius: var(--lcm-radius-lg);
      padding: 9px 12px;
      box-shadow: 0 6px 18px rgba(11, 29, 58, .06);
    }
    h1, h2, p { margin: 0; }
    h1 { font-size: 20px; line-height: 1.12; letter-spacing: 0; }
    h2 { font-size: 13px; text-transform: uppercase; letter-spacing: 0; }
    .subtitle { color: var(--muted); margin-top: 2px; }
    .topbar .subtitle { color: var(--muted); }
    .period-pill {
      border: 1px solid var(--line);
      background: #fff;
      border-radius: 999px;
      padding: 6px 10px;
      color: var(--muted);
      white-space: nowrap;
    }
    .topbar .period-pill {
      border-color: #c9d8ea;
      background: var(--lcm-primary-soft);
      color: var(--lcm-primary);
    }
    .filters, .kpis, .card, .table-card, .drawer {
      background: var(--panel);
      border: 1px solid var(--line);
      box-shadow: var(--shadow);
      border-radius: var(--lcm-radius);
    }
    .filters {
      display: flex;
      gap: 8px;
      padding: 8px;
      align-items: center;
      flex-wrap: wrap;
    }
    .filters.intro {
      width: 100%;
      margin: 6px 0 3px;
    }
    .filter-note {
      width: 100%;
      margin: 0 0 3px;
      color: var(--muted);
      font-size: 13px;
    }
    label span {
      display: block;
      color: var(--muted);
      font-size: 12px;
      margin-bottom: 6px;
    }
    input, select {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: var(--lcm-radius-sm);
      padding: 7px 9px;
      background: #fff;
      color: var(--text);
    }
    select[multiple] {
      min-height: 82px;
    }
    .native-filter {
      position: absolute;
      width: 1px;
      height: 1px;
      overflow: hidden;
      clip: rect(0 0 0 0);
      clip-path: inset(50%);
      white-space: nowrap;
    }
    .period-control {
      display: grid;
      grid-template-columns: auto 86px auto 86px;
      gap: 7px;
      align-items: center;
      height: 38px;
      padding: 0 10px;
      border: 1px solid var(--line);
      border-radius: var(--lcm-radius);
      background: #fff;
    }
    .period-control span {
      margin: 0;
      color: var(--muted);
      font-size: 12px;
    }
    .period-control input {
      height: 28px;
      padding: 4px 6px;
      border: 0;
      background: #f8fafc;
      text-align: center;
      font-weight: 650;
    }
    .multi {
      position: relative;
      min-width: 170px;
    }
    .multi-button {
      width: 100%;
      height: 38px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      border: 1px solid var(--line);
      border-radius: var(--lcm-radius);
      padding: 0 11px;
      background: #fff;
      color: var(--text);
      cursor: pointer;
    }
    .multi.active .multi-button,
    .multi.has-selection .multi-button {
      border-color: #9ec5ff;
      background: #f2f7ff;
    }
    .multi-label {
      display: block;
      color: var(--muted);
      font-size: 11px;
      line-height: 1;
      margin-bottom: 3px;
    }
    .multi-value {
      display: block;
      max-width: 132px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-weight: 650;
      line-height: 1.1;
    }
    .multi-menu {
      position: absolute;
      top: calc(100% + 8px);
      left: 0;
      z-index: 20;
      width: min(330px, 92vw);
      border: 1px solid var(--line);
      border-radius: var(--lcm-radius-lg);
      background: #fff;
      box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18);
      padding: 10px;
    }
    .multi-menu input {
      height: 36px;
      margin-bottom: 8px;
    }
    .multi-options {
      max-height: 260px;
      overflow: auto;
      display: grid;
      gap: 0;
    }
    .multi-option {
      display: flex;
      align-items: center;
      gap: 6px;
      min-height: 28px;
      padding: 3px 8px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 13px;
      line-height: 1.2;
    }
    .multi-option:hover { background: #f4f7fb; }
    .multi-option input {
      width: 14px;
      height: 14px;
      margin: 0;
      flex: 0 0 auto;
    }
    .multi-foot {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-top: 9px;
      color: var(--muted);
      font-size: 12px;
    }
    .link-button {
      border: 0;
      background: transparent;
      color: var(--blue);
      cursor: pointer;
      padding: 4px;
    }
    .chips {
      width: 100%;
      margin: 0 0 5px;
      display: flex;
      gap: 7px;
      flex-wrap: wrap;
      align-items: center;
    }
    .chips:empty { display: none; }
    .chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border: 1px solid #cfe0ff;
      border-radius: 999px;
      background: var(--lcm-primary-soft);
      color: var(--lcm-primary);
      padding: 6px 9px;
      font-size: 12px;
    }
    .chip button {
      border: 0;
      background: transparent;
      color: inherit;
      cursor: pointer;
      padding: 0;
      font-weight: 800;
    }
    .pdi-company {
      margin-left: auto;
      display: inline-flex;
      align-items: baseline;
      gap: 7px;
      border: 1px solid #bfdbfe;
      border-radius: 999px;
      background: var(--lcm-primary-soft);
      color: var(--lcm-primary);
      padding: 6px 11px;
      font-size: 12px;
      font-weight: 650;
    }
    .pdi-company strong {
      font-size: 14px;
      color: #0f172a;
    }
    .filter-status {
      color: var(--muted);
      font-size: 12px;
      min-width: 90px;
      display: inline-flex;
      align-items: center;
      gap: 7px;
    }
    .spinner {
      width: 14px;
      height: 14px;
      border: 2px solid #d7e3f8;
      border-top-color: var(--blue);
      border-radius: 50%;
      animation: spin .75s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .button[disabled] {
      opacity: .45;
      cursor: default;
    }
    .button {
      border: 1px solid var(--blue);
      background: var(--blue);
      color: #fff;
      border-radius: var(--lcm-radius);
      padding: 8px 11px;
      cursor: pointer;
      white-space: nowrap;
    }
    .button.secondary {
      background: #fff;
      color: var(--lcm-primary);
      border-color: var(--line);
    }
    .results[hidden],
    .kpis[hidden],
    .filter-note[hidden],
    .filters[hidden],
    [hidden] {
      display: none !important;
    }
    .kpis {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0;
      overflow: hidden;
      flex: 0 0 auto;
    }
    .kpi {
      padding: 10px 14px;
      border-right: 1px solid var(--line);
    }
    .kpi:last-child { border-right: 0; }
    .kpi span { display: block; color: var(--muted); font-size: 13px; }
    .kpi strong {
      display: block;
      margin-top: 5px;
      font-size: 21px;
      line-height: 1.1;
    }
    .kpi small { display: block; color: var(--muted); margin-top: 4px; }
    .layout {
      display: flex;
      flex-direction: column;
      gap: 10px;
      align-items: stretch;
      flex: 1 1 auto;
      min-height: 0;
      overflow-y: auto;
      overflow-x: hidden;
      padding-bottom: 0;
      scroll-snap-type: y mandatory;
      scroll-behavior: smooth;
    }
    .card, .table-card { padding: 8px; }
    .table-card {
      display: flex;
      flex-direction: column;
      flex: 0 0 calc(100dvh - 78px);
      min-height: calc(100dvh - 78px);
      scroll-snap-align: start;
    }
    .card {
      flex: 0 0 calc(100dvh - 78px);
      min-height: calc(100dvh - 78px);
      display: flex;
      flex-direction: column;
      scroll-snap-align: start;
    }
    .bars {
      display: grid;
      gap: 10px;
      margin-top: 14px;
      overflow: auto;
      align-content: start;
      flex: 1 1 auto;
      min-height: 0;
    }
    .bar-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 72px;
      gap: 10px;
      align-items: center;
      cursor: pointer;
    }
    .bar-name { font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bar-track {
      grid-column: 1 / -1;
      height: 9px;
      border-radius: 999px;
      background: #e8eef7;
      overflow: hidden;
    }
    .bar-fill { height: 100%; background: var(--blue); border-radius: inherit; }
    .bar-value { text-align: right; font-weight: 700; }
    .table-actions {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
      margin: 6px 0 7px;
      flex-wrap: wrap;
      flex: 0 0 auto;
    }
    .search-tools {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1 1 auto;
      min-width: 360px;
    }
    .search-tools input {
      width: min(340px, 100%);
      flex: 0 1 340px;
    }
    .table-counts {
      display: flex;
      gap: 8px;
      align-items: center;
      color: var(--muted);
      font-size: 13px;
      white-space: nowrap;
    }
    .table-counts span {
      display: inline-flex;
      gap: 5px;
      align-items: baseline;
      border: 1px solid var(--line);
      border-radius: 999px;
      padding: 7px 10px;
      background: #f8fafc;
    }
    .table-counts strong {
      color: var(--text);
      font-size: 14px;
    }
    .exports { display: flex; gap: 8px; flex-wrap: wrap; }
    .table-wrap {
      width: 100%;
      flex: 1 1 auto;
      min-height: 0;
      overflow: auto;
      border: 1px solid var(--line);
      border-radius: var(--lcm-radius);
    }
    table {
      width: 100%;
      min-width: 980px;
      border-collapse: collapse;
    }
    th, td {
      border-bottom: 1px solid var(--line);
      padding: 5px 8px;
      text-align: left;
      white-space: nowrap;
    }
    th {
      position: sticky;
      top: 0;
      z-index: 2;
      background: var(--lcm-surface-alt);
      color: #405168;
      font-size: 12px;
      text-transform: uppercase;
      cursor: pointer;
      user-select: none;
    }
    th.active { color: var(--blue); }
    td.num, th.num { text-align: right; }
    td.pdi-good {
      color: #047857;
      background: #ecfdf5;
      font-weight: 700;
    }
    td.pdi-mid {
      color: #92400e;
      background: #fffbeb;
      font-weight: 700;
    }
    td.pdi-low {
      color: #b91c1c;
      background: #fef2f2;
      font-weight: 700;
    }
    tbody tr { cursor: pointer; }
    tbody tr:hover { background: #f8fbff; }
    tbody tr.selected {
      background: var(--lcm-primary-soft);
      box-shadow: inset 4px 0 0 var(--blue);
    }
    tbody tr.selected:hover { background: #e1efff; }
    .state {
      padding: 36px;
      text-align: center;
      color: var(--muted);
    }
    .skeleton {
      display: grid;
      gap: 10px;
      padding: 12px;
      flex: 1 1 auto;
      min-height: 140px;
    }
    .table-loading {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      flex: 1 1 auto;
      min-height: 180px;
      color: var(--muted);
      font-weight: 650;
    }
    .skeleton div {
      height: 16px;
      border-radius: 999px;
      background: linear-gradient(90deg, #edf1f7, #f8fafc, #edf1f7);
      background-size: 220% 100%;
      animation: pulse 1.2s infinite linear;
    }
    @keyframes pulse { to { background-position: -220% 0; } }
    .drawer-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.18);
      opacity: 0;
      pointer-events: none;
      transition: opacity .18s ease;
    }
    .drawer {
      position: fixed;
      inset: 0 0 0 auto;
      width: min(540px, 94vw);
      border-radius: 0;
      transform: translateX(102%);
      transition: transform .18s ease;
      overflow: auto;
      padding: 20px;
      z-index: 5;
    }
    body.drawer-open .drawer-backdrop { opacity: 1; pointer-events: auto; }
    body.drawer-open .drawer { transform: translateX(0); }
    .drawer-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
      margin-bottom: 12px;
    }
    .drawer-actions {
      display: flex;
      gap: 8px;
      align-items: center;
      flex: 0 0 auto;
    }
    .drawer-actions .button {
      height: 36px;
      padding: 0 12px;
    }
    .icon-button {
      width: 36px;
      height: 36px;
      border: 1px solid var(--line);
      border-radius: var(--lcm-radius);
      background: #fff;
      cursor: pointer;
    }
    .mini-kpis {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 10px;
      margin: 12px 0 18px;
    }
    .mini-kpis div {
      border: 1px solid var(--line);
      border-radius: var(--lcm-radius);
      padding: 11px;
    }
    .mini-kpis span { display: block; color: var(--muted); font-size: 12px; }
    .mini-kpis strong { display: block; margin-top: 5px; font-size: 20px; }
    .detail-section { margin-top: 18px; }
    .detail-section .table-wrap {
      max-height: 260px;
    }
    .detail-section table {
      min-width: 0;
      table-layout: fixed;
    }
    .detail-section th:first-child,
    .detail-section td:first-child {
      white-space: normal;
      width: 52%;
    }
    .detail-section th.num,
    .detail-section td.num {
      width: 24%;
    }
    .spark { display: flex; align-items: end; gap: 7px; height: 130px; margin-top: 12px; }
    .spark div { flex: 1; background: var(--green); border-radius: 5px 5px 0 0; min-width: 16px; position: relative; }
    .spark span { position: absolute; bottom: -21px; left: 50%; transform: translateX(-50%); color: var(--muted); font-size: 11px; }
    @media (max-width: 1050px) {
      .filters,
      .filters.intro { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .kpi { border-bottom: 1px solid var(--line); }
    }
    @media (max-width: 640px) {
      body { overflow: auto; }
      .page {
        min-height: 100dvh;
        height: auto;
        padding: 12px;
        overflow: visible;
      }
      .topbar { flex-direction: column; }
      .filters,
      .filters.intro,
      .kpis,
      .mini-kpis { grid-template-columns: 1fr; }
      .search-tools {
        min-width: 0;
        width: 100%;
        flex-direction: column;
        align-items: stretch;
      }
      .search-tools input {
        width: 100%;
        flex-basis: auto;
      }
      .table-counts {
        flex-wrap: wrap;
      }
      .kpi { border-right: 0; }
      .layout {
        overflow: visible;
        scroll-snap-type: none;
      }
      .table-card,
      .card {
        flex: none;
        min-height: auto;
      }
      .table-wrap { max-height: 65dvh; }
    }
  </style>
</head>
<body>
  <main class="page">
    <header class="topbar">
      <div>
        <h1>FIBRA HOGAR - Control de producción</h1>
        <p class="subtitle">Producción acumulada y evolución mensual</p>
      </div>
      <div class="period-pill" id="periodLabel">ENE 2026 - AGO 2026</div>
    </header>

    <p class="filter-note" id="filterNote">Seleccioná el período y, si hace falta, contrato, central o técnico. Al aplicar, se ocultan los filtros para aprovechar todo el ancho de pantalla.</p>
    <section class="filters intro" id="filtersPanel">
      <label class="period-control"><span>Período</span><input id="desde" value="202601" inputmode="numeric"><span>a</span><input id="hasta" value="<?= htmlspecialchars(date('Ym'), ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric"></label>
      <label class="native-filter"><span>Contrato</span><select id="contrato" multiple></select></label>
      <label class="native-filter"><span>Distrito</span><select id="distrito" multiple></select></label>
      <label class="native-filter"><span>Central</span><select id="central" multiple></select></label>
      <label class="native-filter"><span>Técnico</span><select id="tecnico" multiple></select></label>
      <div class="multi" id="multi-contrato"></div>
      <div class="multi" id="multi-distrito"></div>
      <div class="multi" id="multi-central"></div>
      <div class="multi" id="multi-tecnico"></div>
      <button class="button secondary" id="clearFilters" type="button" disabled>Limpiar filtros</button>
      <button class="button" id="apply" type="button">Aplicar</button>
      <span class="filter-status" id="filterStatus"></span>
    </section>
    <div class="chips" id="activeChips"></div>

    <section class="kpis" id="kpis" hidden>
      <article class="kpi"><span>INSTALACIONES</span><strong>-</strong></article>
      <article class="kpi"><span>TÉCNICOS ACTIVOS</span><strong>-</strong></article>
      <article class="kpi"><span>PROMEDIO POR TÉCNICO</span><strong>-</strong></article>
      <article class="kpi"><span>LÍDER</span><strong>-</strong><small></small></article>
    </section>

    <section class="layout results" id="results" hidden>
      <article class="table-card">
        <h2>Tabla principal</h2>
        <div class="table-actions">
          <div class="search-tools">
            <input id="search" type="search" placeholder="Buscar técnico...">
            <div class="table-counts" id="tableCounts">
              <span>Técnicos <strong>-</strong></span>
              <span>Centrales <strong>-</strong></span>
            </div>
          </div>
          <div class="exports">
            <button class="button secondary" id="changeFilters" type="button">Cambiar filtros</button>
            <button class="button secondary" id="csv" type="button">Exportar CSV</button>
            <button class="button secondary" id="excel" type="button">Exportar Excel</button>
          </div>
        </div>
        <div id="status" class="skeleton"><div></div><div></div><div></div></div>
        <div class="table-wrap" id="tableWrap" hidden>
          <table id="mainTable"></table>
        </div>
      </article>

      <article class="card">
        <h2>TOP 15 TÉCNICOS</h2>
        <div class="bars" id="topBars"></div>
      </article>
    </section>
  </main>

  <div class="drawer-backdrop" id="backdrop"></div>
  <aside class="drawer" id="drawer" aria-hidden="true">
    <div class="drawer-head">
      <div>
        <h2 id="drawerName">Técnico</h2>
        <p class="subtitle">Detalle del período filtrado</p>
      </div>
      <div class="drawer-actions">
        <button class="button secondary" id="prevTech" type="button">Anterior</button>
        <button class="button secondary" id="nextTech" type="button">Siguiente</button>
        <button class="icon-button" id="closeDrawer" type="button">×</button>
      </div>
    </div>
    <div class="mini-kpis" id="drawerKpis"></div>
    <section class="detail-section">
      <h2>EVOLUCIÓN MENSUAL</h2>
      <div class="spark" id="spark"></div>
    </section>
    <section class="detail-section">
      <h2>CENTRALES TRABAJADAS</h2>
      <div class="table-wrap"><table id="centralTable"></table></div>
    </section>
    <section class="detail-section">
      <h2>CONTRATOS</h2>
      <div class="table-wrap"><table id="contractTable"></table></div>
    </section>
  </aside>

  <script>
    const API_URL = '../api/instalaciones/produccion_tecnicos.php';
    const FILTERS_API_URL = '../api/instalaciones/produccion_tecnicos_filtros.php';
    const EXCEL_API_URL = '../api/instalaciones/produccion_tecnicos_excel.php';
    const state = { data: null, rows: [], visibleRows: [], sortKey: 'total', sortDir: 'desc', selectedIndex: -1, selectedTech: '' };
    let filterRefreshTimer = null;
    let filterRefreshController = null;
    const DEFAULT_DESDE = '202601';
    const DEFAULT_HASTA = '<?= htmlspecialchars(date('Ym'), ENT_QUOTES, 'UTF-8') ?>';
    const multiConfigs = [
      { id: 'contrato', label: 'Contrato', all: 'Todos' },
      { id: 'distrito', label: 'Distrito', all: 'Todos' },
      { id: 'central', label: 'Central', all: 'Todas' },
      { id: 'tecnico', label: 'Técnico', all: 'Todos' }
    ];
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    const $ = (id) => document.getElementById(id);
    const fmtInt = (n) => new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 }).format(Number(n || 0));
    const fmtDec = (n) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(Number(n || 0));

    function periodName(period) {
      const year = String(period).slice(2, 4);
      const month = Number(String(period).slice(4, 6));
      return `${months[month - 1]} ${year}`;
    }

    function shortMonth(period, allPeriods) {
      const years = new Set(allPeriods.map(p => String(p).slice(0, 4)));
      const month = Number(String(period).slice(4, 6));
      return years.size > 1 ? `${months[month - 1]} ${String(period).slice(2, 4)}` : months[month - 1];
    }

    function selectedValues(select) {
      return Array.from(select.selectedOptions).map(option => option.value);
    }

    function setOptions(select, values, selected) {
      const current = new Set(selected.length ? selected : selectedValues(select));
      select.innerHTML = values.map(value => `<option value="${escapeHtml(value)}" ${current.has(value) ? 'selected' : ''}>${escapeHtml(value)}</option>`).join('');
      renderMultiSelect(select.id);
      renderFilterSummary();
    }

    function optionValues(id) {
      return Array.from($(id).options).map(option => option.value);
    }

    function renderMultiSelect(id, term = '') {
      const config = multiConfigs.find(item => item.id === id);
      if (!config) return;
      const root = $(`multi-${id}`);
      const selected = selectedValues($(id));
      const values = optionValues(id);
      const selectedSet = new Set(selected);
      const query = term.trim().toLowerCase();
      const visible = query ? values.filter(value => value.toLowerCase().includes(query)) : values;
      const summary = selected.length === 0
        ? config.all
        : selected.length === 1
          ? selected[0]
          : `${selected.length} seleccionados`;

      root.classList.toggle('has-selection', selected.length > 0);
      root.innerHTML = `
        <button class="multi-button" type="button" data-multi-open="${id}">
          <span><span class="multi-label">${config.label}</span><span class="multi-value">${escapeHtml(summary)}</span></span>
          <span>⌄</span>
        </button>
        <div class="multi-menu" ${root.classList.contains('active') ? '' : 'hidden'}>
          <input type="search" value="${escapeHtml(term)}" placeholder="Buscar ${config.label.toLowerCase()}...">
          <div class="multi-options">
            <label class="multi-option"><input type="checkbox" data-multi-all="${id}" ${selected.length === 0 ? 'checked' : ''}> ${config.all}</label>
            ${visible.map(value => `<label class="multi-option"><input type="checkbox" data-multi-option="${id}" value="${escapeHtml(value)}" ${selectedSet.has(value) ? 'checked' : ''}> ${escapeHtml(value)}</label>`).join('')}
          </div>
          <div class="multi-foot"><span>${selected.length} seleccionados</span><button class="link-button" type="button" data-multi-clear="${id}">Limpiar</button></div>
        </div>
      `;

      root.querySelector('[data-multi-open]')?.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleMultiSelect(id);
      });
      const search = root.querySelector('.multi-menu input[type="search"]');
      root.querySelector('.multi-menu')?.addEventListener('click', event => event.stopPropagation());
      search?.addEventListener('click', event => event.stopPropagation());
      search?.addEventListener('input', event => renderMultiSelect(id, event.target.value));
      root.querySelector('[data-multi-all]')?.addEventListener('change', () => {
        clearSelect(id);
        renderMultiSelect(id);
        notifyFilterChanged(id);
      });
      root.querySelector('[data-multi-clear]')?.addEventListener('click', (event) => {
        event.stopPropagation();
        clearSelect(id);
        renderMultiSelect(id);
        notifyFilterChanged(id);
      });
      root.querySelectorAll('[data-multi-option]').forEach(input => input.addEventListener('change', () => {
        setOptionSelected(id, input.value, input.checked);
        renderMultiSelect(id, search?.value || '');
        notifyFilterChanged(id);
      }));
      if (root.classList.contains('active')) {
        setTimeout(() => root.querySelector('.multi-menu input[type="search"]')?.focus(), 0);
      }
    }

    function renderMultiSelects() {
      multiConfigs.forEach(config => renderMultiSelect(config.id));
    }

    function toggleMultiSelect(id) {
      multiConfigs.forEach(config => {
        const root = $(`multi-${config.id}`);
        root.classList.toggle('active', config.id === id && !root.classList.contains('active'));
        renderMultiSelect(config.id);
      });
    }

    function closeMultiSelects() {
      multiConfigs.forEach(config => {
        const root = $(`multi-${config.id}`);
        if (root.classList.contains('active')) {
          root.classList.remove('active');
          renderMultiSelect(config.id);
        }
      });
    }

    function setOptionSelected(id, value, checked) {
      const option = Array.from($(id).options).find(item => item.value === value);
      if (option) option.selected = checked;
    }

    function clearSelect(id) {
      Array.from($(id).options).forEach(option => { option.selected = false; });
    }

    function notifyFilterChanged(id) {
      $(id).dispatchEvent(new Event('change'));
      renderFilterSummary();
    }

    function isDirtyFilters() {
      return $('desde').value.trim() !== DEFAULT_DESDE
        || $('hasta').value.trim() !== DEFAULT_HASTA
        || multiConfigs.some(config => selectedValues($(config.id)).length > 0);
    }

    function renderFilterSummary() {
      $('clearFilters').disabled = !isDirtyFilters();
      const chips = [];
      for (const config of multiConfigs) {
        const selected = selectedValues($(config.id));
        if (selected.length === 1) {
          chips.push(`<span class="chip">${escapeHtml(selected[0])}<button type="button" data-chip-clear="${config.id}" data-chip-value="${escapeHtml(selected[0])}">×</button></span>`);
        } else if (selected.length > 1) {
          chips.push(`<span class="chip">${config.label}: ${selected.length} seleccionados<button type="button" data-chip-clear="${config.id}">×</button></span>`);
        }
      }
      if (state.data) {
        chips.push(`<span class="pdi-company">PDI Empresa <strong>${fmtDec(companyPdi())}</strong></span>`);
      }
      $('activeChips').innerHTML = chips.join('');
      document.querySelectorAll('[data-chip-clear]').forEach(button => button.addEventListener('click', () => {
        const id = button.dataset.chipClear;
        const value = button.dataset.chipValue;
        if (value) setOptionSelected(id, value, false);
        else clearSelect(id);
        renderMultiSelect(id);
        notifyFilterChanged(id);
      }));
    }

    function companyPdi() {
      const rows = state.rows || [];
      const total = rows.reduce((sum, row) => sum + Number(row.total || 0), 0);
      const days = rows.reduce((sum, row) => sum + Number(row.dias_trabajados || 0), 0);
      return days ? total / days : 0;
    }

    function pdiClass(value) {
      const average = companyPdi();
      const pdi = Number(value || 0);
      if (average > 0 && pdi > average) return 'pdi-good';
      if (pdi >= 2.2) return 'pdi-mid';
      return 'pdi-low';
    }

    function clearAllFilters() {
      $('desde').value = DEFAULT_DESDE;
      $('hasta').value = DEFAULT_HASTA;
      multiConfigs.forEach(config => clearSelect(config.id));
      renderMultiSelects();
      renderFilterSummary();
      scheduleDependentFilterRefresh();
    }

    function escapeHtml(value) {
      return String(value ?? '').replace(/[&<>"']/g, ch => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[ch]));
    }

    function buildUrl() {
      const params = new URLSearchParams();
      params.set('desde', $('desde').value.trim() || '202601');
      params.set('hasta', $('hasta').value.trim());
      for (const value of selectedValues($('contrato'))) params.append('contrato[]', value);
      for (const value of selectedValues($('distrito'))) params.append('distrito[]', value);
      for (const value of selectedValues($('central'))) params.append('central[]', value);
      for (const value of selectedValues($('tecnico'))) params.append('tecnico[]', value);
      params.set('format', 'json');
      return `${API_URL}?${params.toString()}`;
    }

    function buildOptionsUrl() {
      const params = new URLSearchParams();
      params.set('desde', $('desde').value.trim() || '202601');
      params.set('hasta', $('hasta').value.trim());
      for (const value of selectedValues($('contrato'))) params.append('contrato[]', value);
      for (const value of selectedValues($('distrito'))) params.append('distrito[]', value);
      params.set('format', 'json');
      return `${FILTERS_API_URL}?${params.toString()}`;
    }

    function setFilterLoading(isLoading, text = '') {
      $('filterStatus').innerHTML = isLoading ? `<span class="spinner"></span><span>${escapeHtml(text || 'Actualizando...')}</span>` : '';
    }

    function scheduleDependentFilterRefresh() {
      renderFilterSummary();
      clearTimeout(filterRefreshTimer);
      filterRefreshTimer = setTimeout(refreshDependentFilters, 350);
    }

    async function refreshDependentFilters() {
      if (filterRefreshController) filterRefreshController.abort();
      filterRefreshController = new AbortController();
      const selectedCentral = selectedValues($('central'));
      const selectedTecnico = selectedValues($('tecnico'));
      const selectedDistrito = selectedValues($('distrito'));
      $('distrito').disabled = true;
      $('central').disabled = true;
      $('tecnico').disabled = true;
      setFilterLoading(true, 'Actualizando...');
      $('filterNote').textContent = 'Actualizando centrales y técnicos disponibles...';

      try {
        const response = await fetch(buildOptionsUrl(), {
          headers: { Accept: 'application/json' },
          signal: filterRefreshController.signal
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Error API');
        setOptions($('contrato'), data.options?.contratos || [], selectedValues($('contrato')));
        setOptions($('distrito'), data.options?.distritos || [], selectedDistrito);
        setOptions($('central'), data.options?.centrales || [], selectedCentral);
        setOptions($('tecnico'), data.options?.tecnicos || [], selectedTecnico);
        renderMultiSelects();
        renderFilterSummary();
        $('filterNote').textContent = 'Seleccioná el período y, si hace falta, contrato, central o técnico. Al aplicar, se ocultan los filtros para aprovechar todo el ancho de pantalla.';
      } catch (error) {
        if (error.name !== 'AbortError') {
          $('filterNote').textContent = 'No fue posible actualizar las opciones de filtros.';
        }
      } finally {
        $('distrito').disabled = false;
        $('central').disabled = false;
        $('tecnico').disabled = false;
        setFilterLoading(false);
      }
    }

    async function loadFilterOptions() {
      setFilterLoading(true, 'Cargando filtros...');
      $('filterNote').textContent = 'Cargando opciones de filtros...';
      try {
        const response = await fetch(buildOptionsUrl(), { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Error API');
        setOptions($('contrato'), data.options?.contratos || [], []);
        setOptions($('distrito'), data.options?.distritos || [], []);
        setOptions($('central'), data.options?.centrales || [], []);
        setOptions($('tecnico'), data.options?.tecnicos || [], []);
        renderMultiSelects();
        renderFilterSummary();
        $('filterNote').textContent = 'Seleccioná el período y, si hace falta, contrato, distrito, central o técnico.';
      } catch (error) {
        $('filterNote').textContent = 'No fue posible cargar las opciones. Revisá el servidor local y volvé a intentar.';
      } finally {
        setFilterLoading(false);
      }
    }

    function showFilterView() {
      $('filtersPanel').hidden = false;
      $('filterNote').hidden = false;
      $('kpis').hidden = true;
      $('results').hidden = true;
      closeDrawer();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function showResultView() {
      $('filtersPanel').hidden = true;
      $('filterNote').hidden = true;
      $('kpis').hidden = true;
      $('results').hidden = false;
      $('results').scrollTop = 0;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function loadData(showResults = true) {
      if (showResults) {
        showResultView();
        $('status').className = 'table-loading';
        $('status').innerHTML = '<span class="spinner"></span><span>Cargando tabla...</span>';
        $('status').hidden = false;
        $('tableWrap').hidden = true;
        setFilterLoading(true, 'Cargando tabla...');
      } else {
        return loadFilterOptions();
      }
      try {
        const response = await fetch(buildUrl(), { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Error API');
        state.data = data;
        state.rows = data.rows || [];
        setOptions($('contrato'), data.options?.contratos || [], data.filters?.contratos || []);
        setOptions($('distrito'), data.options?.distritos || [], data.filters?.distritos || []);
        setOptions($('central'), data.options?.centrales || [], data.filters?.centrales || []);
        setOptions($('tecnico'), data.options?.tecnicos || [], data.filters?.tecnicos || []);
        renderMultiSelects();
        renderFilterSummary();
        renderAll(showResults);
      } catch (error) {
        if (showResults) {
          $('status').className = 'state';
          $('status').textContent = 'No fue posible cargar los datos.';
        } else {
          $('filterNote').textContent = 'No fue posible cargar las opciones. Revisá el servidor local y volvé a intentar.';
        }
      }
      finally {
        if (showResults) setFilterLoading(false);
      }
    }

    function renderAll(showResults = true) {
      const data = state.data;
      $('periodLabel').textContent = `${periodName(data.filters.desde).toUpperCase()} - ${periodName(data.filters.hasta).toUpperCase()}`;
      renderKpis();
      renderTop();
      applySearchAndSort();
      if (showResults) {
        showResultView();
        $('status').hidden = true;
        $('tableWrap').hidden = false;
      } else {
        $('filterNote').textContent = 'Seleccioná el período y, si hace falta, contrato, central o técnico. Al aplicar, se ocultan los filtros para aprovechar todo el ancho de pantalla.';
      }
    }

    function renderKpis() {
      const s = state.data.summary;
      const leader = s.lider || {};
      $('kpis').innerHTML = `
        <article class="kpi"><span>INSTALACIONES</span><strong>${fmtInt(s.instalaciones)}</strong></article>
        <article class="kpi"><span>TÉCNICOS ACTIVOS</span><strong>${fmtInt(s.tecnicos_activos)}</strong></article>
        <article class="kpi"><span>PROMEDIO POR TÉCNICO</span><strong>${fmtDec(s.promedio_tecnico)}</strong></article>
        <article class="kpi"><span>LÍDER</span><strong>${escapeHtml(leader.tecnico || '-')}</strong><small>${fmtInt(leader.instalaciones)} instalaciones · ${fmtDec(leader.porcentaje_total)}% del total</small></article>
      `;
    }

    function sortRows(rows) {
      const key = state.sortKey;
      const dir = state.sortDir === 'asc' ? 1 : -1;
      return [...rows].sort((a, b) => {
        const av = key.startsWith('mes_') ? a.meses[key.slice(4)] : a[key];
        const bv = key.startsWith('mes_') ? b.meses[key.slice(4)] : b[key];
        if (typeof av === 'string' || typeof bv === 'string') return String(av).localeCompare(String(bv), 'es') * dir;
        return ((Number(av || 0) > Number(bv || 0)) - (Number(av || 0) < Number(bv || 0))) * dir;
      });
    }

    function applySearchAndSort() {
      const term = $('search').value.trim().toLowerCase();
      const filtered = term ? state.rows.filter(row => row.tecnico.toLowerCase().includes(term)) : state.rows;
      state.visibleRows = sortRows(filtered);
      renderTableCounts();
      renderTable();
    }

    function renderTableCounts() {
      const centrales = new Set();
      for (const row of state.visibleRows) {
        for (const item of row.centrales || []) {
          if (item.central) centrales.add(item.central);
        }
      }
      $('tableCounts').innerHTML = `
        <span>Técnicos <strong>${fmtInt(state.visibleRows.length)}</strong></span>
        <span>Centrales <strong>${fmtInt(centrales.size)}</strong></span>
      `;
    }

    function renderTop() {
      const top = [...state.rows].sort((a, b) => b.total - a.total).slice(0, 15);
      const max = top[0]?.total || 1;
      $('topBars').innerHTML = top.map(row => `
        <div class="bar-row" data-tech="${escapeHtml(row.tecnico)}">
          <div class="bar-name">${escapeHtml(row.tecnico)}</div>
          <div class="bar-value">${fmtInt(row.total)}</div>
          <div class="bar-track"><div class="bar-fill" style="width:${Math.max(2, row.total / max * 100)}%"></div></div>
        </div>
      `).join('') || '<div class="state">No se encontraron instalaciones para los filtros seleccionados.</div>';
      document.querySelectorAll('[data-tech]').forEach(node => node.addEventListener('click', () => openByName(node.dataset.tech)));
    }

    function header(label, key, numeric = false) {
      const active = state.sortKey === key ? 'active' : '';
      const sign = state.sortKey === key ? (state.sortDir === 'asc' ? ' ↑' : ' ↓') : '';
      return `<th class="${numeric ? 'num ' : ''}${active}" data-sort="${key}">${label}${sign}</th>`;
    }

    function renderTable() {
      const periods = state.data.periodos;
      const monthHeaders = periods.map(period => header(shortMonth(period, periods), `mes_${period}`, true)).join('');
      const rows = state.visibleRows.map(row => `
        <tr data-name="${escapeHtml(row.tecnico)}" class="${row.tecnico === state.selectedTech ? 'selected' : ''}">
          <td>${escapeHtml(row.tecnico)}</td>
          ${periods.map(period => `<td class="num">${fmtInt(row.meses[period] || 0)}</td>`).join('')}
          <td class="num">${fmtInt(row.total)}</td>
          <td class="num">${fmtDec(row.promedio_mensual)}</td>
          <td class="num ${pdiClass(row.promedio_diario)}">${fmtDec(row.promedio_diario)}</td>
          <td class="num">${fmtInt(row.ultimo_mes)}</td>
        </tr>
      `).join('');
      $('mainTable').innerHTML = `
        <thead><tr>
          ${header('Técnico', 'tecnico')}
          ${monthHeaders}
          ${header('Total', 'total', true)}
          ${header('Prom./mes', 'promedio_mensual', true)}
          ${header('PDI', 'promedio_diario', true)}
          ${header('Último mes', 'ultimo_mes', true)}
        </tr></thead>
        <tbody>${rows || `<tr><td colspan="${periods.length + 5}" class="state">No se encontraron instalaciones para los filtros seleccionados.</td></tr>`}</tbody>
      `;
      document.querySelectorAll('[data-sort]').forEach(th => th.addEventListener('click', () => changeSort(th.dataset.sort)));
      document.querySelectorAll('#mainTable tbody tr[data-name]').forEach(tr => tr.addEventListener('click', () => openByName(tr.dataset.name)));
    }

    function changeSort(key) {
      if (state.sortKey === key) state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
      else { state.sortKey = key; state.sortDir = key === 'tecnico' ? 'asc' : 'desc'; }
      applySearchAndSort();
    }

    function openByName(name) {
      const index = state.visibleRows.findIndex(row => row.tecnico === name);
      if (index >= 0) openDrawer(index);
    }

    function openDrawer(index) {
      state.selectedIndex = index;
      const row = state.visibleRows[index];
      if (!row) return;
      state.selectedTech = row.tecnico;
      $('drawerName').textContent = row.tecnico;
      $('drawerKpis').innerHTML = [
        `<div><span>Total instalaciones</span><strong>${fmtInt(row.total)}</strong></div>`,
        `<div><span>Promedio mensual</span><strong>${fmtDec(row.promedio_mensual)}</strong></div>`,
        `<div><span>Último mes</span><strong>${fmtInt(row.ultimo_mes)}</strong></div>`,
        `<div><span>Días trabajados</span><strong>${fmtInt(row.dias_trabajados)}</strong></div>`,
        `<div><span>Promedio diario</span><strong>${fmtDec(row.promedio_diario)}</strong></div>`
      ].join('');
      const values = state.data.periodos.map(period => row.meses[period] || 0);
      const max = Math.max(...values, 1);
      $('spark').innerHTML = state.data.periodos.map(period => {
        const value = row.meses[period] || 0;
        return `<div title="${shortMonth(period, state.data.periodos)}: ${fmtInt(value)}" style="height:${Math.max(3, value / max * 100)}%"><span>${shortMonth(period, state.data.periodos)}</span></div>`;
      }).join('');
      renderDetailTable('centralTable', ['Central', 'Instalaciones', '%'], row.centrales.map(item => [item.central, item.instalaciones, row.total ? item.instalaciones / row.total * 100 : 0]));
      renderDetailTable('contractTable', ['Contrato', 'Instalaciones', '%'], row.contratos.map(item => [item.contrato, item.instalaciones, row.total ? item.instalaciones / row.total * 100 : 0]));
      document.body.classList.add('drawer-open');
      $('drawer').setAttribute('aria-hidden', 'false');
      renderTable();
      requestAnimationFrame(() => {
        const selectedRow = $('mainTable').querySelector('tbody tr.selected');
        selectedRow?.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' });
      });
    }

    function renderDetailTable(id, headers, rows) {
      $(id).innerHTML = `<thead><tr><th>${headers[0]}</th><th class="num">${headers[1]}</th><th class="num">${headers[2]}</th></tr></thead><tbody>${rows.map(row => `<tr><td>${escapeHtml(row[0])}</td><td class="num">${fmtInt(row[1])}</td><td class="num">${fmtDec(row[2])}%</td></tr>`).join('')}</tbody>`;
    }

    function closeDrawer() {
      document.body.classList.remove('drawer-open');
      $('drawer').setAttribute('aria-hidden', 'true');
      state.selectedTech = '';
      if (state.data) renderTable();
    }

    function exportCsv(filename) {
      const periods = state.data.periodos;
      const headers = ['Técnico', ...periods.map(period => shortMonth(period, periods)), 'Total', 'Promedio mensual', 'PDI', 'Último mes'];
      const lines = [headers, ...state.visibleRows.map(row => [row.tecnico, ...periods.map(period => row.meses[period] || 0), row.total, row.promedio_mensual, row.promedio_diario, row.ultimo_mes])];
      const csv = '\ufeff' + lines.map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(';')).join('\r\n');
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    }

    async function exportExcel() {
      if (!state.data || !state.visibleRows.length) return;
      const periods = state.data.periodos;
      const headers = ['Técnico', ...periods.map(period => shortMonth(period, periods)), 'Total', 'Promedio mensual', 'PDI', 'Último mes'];
      const rows = state.visibleRows.map(row => [row.tecnico, ...periods.map(period => row.meses[period] || 0), row.total, row.promedio_mensual, row.promedio_diario, row.ultimo_mes]);
      const sheets = [{ name: 'TOTAL', headers, rows }];
      const districtRows = new Map();
      state.visibleRows.forEach(row => {
        (row.distritos_detalle || []).forEach(detail => {
          const districtName = detail.distrito || 'SIN DISTRITO';
          if (!districtRows.has(districtName)) districtRows.set(districtName, []);
          districtRows.get(districtName).push([
            row.tecnico,
            ...periods.map(period => detail.meses?.[period] || 0),
            detail.total || 0,
            detail.promedio_mensual || 0,
            detail.promedio_diario || 0,
            detail.ultimo_mes || 0
          ]);
        });
      });
      if (districtRows.size > 1) {
        Array.from(districtRows.entries())
          .sort((a, b) => a[0].localeCompare(b[0], 'es'))
          .forEach(([districtName, districtSheetRows]) => {
            districtSheetRows.sort((a, b) => Number(b[periods.length + 1] || 0) - Number(a[periods.length + 1] || 0) || String(a[0]).localeCompare(String(b[0]), 'es'));
            sheets.push({ name: districtName, headers, rows: districtSheetRows });
          });
      }
      const button = $('excel');
      const original = button.textContent;
      button.disabled = true;
      button.textContent = 'Generando XLSX...';
      try {
        const response = await fetch(EXCEL_API_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
          body: JSON.stringify({ filename: 'instalaciones_tecnicos.xlsx', sheets })
        });
        if (!response.ok) throw new Error('No se pudo generar el XLSX');
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'instalaciones_tecnicos.xlsx';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
      } catch (error) {
        alert('No fue posible exportar el Excel.');
      } finally {
        button.disabled = false;
        button.textContent = original;
      }
    }

    $('apply').addEventListener('click', () => loadData(true));
    $('changeFilters').addEventListener('click', showFilterView);
    $('clearFilters').addEventListener('click', clearAllFilters);
    $('desde').addEventListener('input', scheduleDependentFilterRefresh);
    $('hasta').addEventListener('input', scheduleDependentFilterRefresh);
    $('contrato').addEventListener('change', scheduleDependentFilterRefresh);
    $('distrito').addEventListener('change', scheduleDependentFilterRefresh);
    $('search').addEventListener('input', applySearchAndSort);
    $('closeDrawer').addEventListener('click', closeDrawer);
    $('backdrop').addEventListener('click', closeDrawer);
    $('prevTech').addEventListener('click', () => openDrawer(Math.max(0, state.selectedIndex - 1)));
    $('nextTech').addEventListener('click', () => openDrawer(Math.min(state.visibleRows.length - 1, state.selectedIndex + 1)));
    $('csv').addEventListener('click', () => exportCsv('instalaciones_tecnicos.csv'));
    $('excel').addEventListener('click', exportExcel);
    document.addEventListener('click', closeMultiSelects);

    loadFilterOptions();
  </script>
</body>
</html>
