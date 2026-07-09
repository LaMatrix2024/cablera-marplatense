(() => {
    'use strict';

    const API_BASE = '/api/telefonia/tracking_tirones';
    const PERIOD_NAMES = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    const formatNumber = (value, digits = 0) => new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(Number(value || 0));

    const formatDateTime = (value) => {
        if (!value) return '-';
        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return String(value);
        return new Intl.DateTimeFormat('es-AR', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(date);
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const currentPeriod = () => {
        const now = new Date();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        return `${now.getFullYear()}-${month}`;
    };

    const formatPeriodLabel = (period) => {
        const match = /^(\d{4})-(\d{2})$/.exec(String(period || '').trim());
        if (!match) return String(period || '');
        return `${match[1]}${match[2]}`;
    };

    const fetchJson = async (url) => {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.ok) {
            throw new Error(payload.error || 'No se pudo completar la consulta.');
        }
        return payload;
    };

    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    };

    const showNotice = (message = '') => {
        const notice = document.getElementById('notice');
        if (!notice) return;
        notice.textContent = message;
        notice.classList.toggle('tracking-notice--hidden', !message);
    };

    const periodState = {
        available: [],
        selected: [],
        pending: [],
    };

    const getPeriodButton = () => document.getElementById('periodoBtn');
    const getPeriodMenu = () => document.getElementById('periodoMenu');
    const getPeriodInput = () => document.getElementById('periodosInput');

    const syncPeriodInput = () => {
        const input = getPeriodInput();
        if (input) {
            input.value = periodState.selected.join(',');
        }
    };

    const periodParam = () => {
        const value = periodState.selected.join(',');
        return value || currentPeriod();
    };

    const updatePeriodButtonText = () => {
        const button = getPeriodButton();
        if (!button) return;
        const span = button.querySelector('span');
        if (!span) return;

        if (periodState.selected.length === 0) {
            span.textContent = 'Actual';
        } else if (periodState.selected.length === 1) {
            span.textContent = formatPeriodLabel(periodState.selected[0]);
        } else if (periodState.selected.length === 2) {
            span.textContent = periodState.selected.map(formatPeriodLabel).join(' + ');
        } else {
            span.textContent = `${periodState.selected.length} períodos`;
        }
        syncPeriodInput();
    };

    const renderPeriodMenu = () => {
        const menu = getPeriodMenu();
        const button = getPeriodButton();
        if (!menu || !button) return;

        menu.innerHTML = `
            <div class="tracking-period-options">
                ${periodState.available.map((period) => `
                    <label class="tracking-period-option">
                        <input type="checkbox" value="${escapeHtml(period)}" ${periodState.pending.includes(period) ? 'checked' : ''}>
                        <span>${escapeHtml(formatPeriodLabel(period))}</span>
                    </label>
                `).join('')}
            </div>
            <div class="tracking-period-actions">
                <button id="limpiarPeriodos" class="tracking-period-clear" type="button">Limpiar</button>
                <button id="aceptarPeriodos" class="tracking-period-apply" type="button" ${periodState.pending.length ? '' : 'disabled'}>Aceptar</button>
            </div>
        `;

        menu.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const value = checkbox.value;
                if (checkbox.checked) {
                    if (!periodState.pending.includes(value)) {
                        periodState.pending.push(value);
                    }
                } else {
                    periodState.pending = periodState.pending.filter((item) => item !== value);
                }
                periodState.pending.sort((a, b) => b.localeCompare(a));
                const accept = document.getElementById('aceptarPeriodos');
                if (accept) {
                    accept.disabled = periodState.pending.length === 0;
                }
            });
        });

        const clearButton = document.getElementById('limpiarPeriodos');
        const acceptButton = document.getElementById('aceptarPeriodos');

        clearButton?.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();
            periodState.pending = [];
            periodState.selected = [];
            updatePeriodButtonText();
            renderPeriodMenu();
            menu.classList.add('open');
            button.setAttribute('aria-expanded', 'true');
            await refreshSummaryOrDetail();
        });

        acceptButton?.addEventListener('click', async () => {
            if (!periodState.pending.length) return;
            periodState.selected = [...periodState.pending];
            updatePeriodButtonText();
            menu.classList.remove('open');
            button.setAttribute('aria-expanded', 'false');
            await refreshSummaryOrDetail();
        });
    };

    const openPeriodMenu = () => {
        const menu = getPeriodMenu();
        const button = getPeriodButton();
        if (!menu || !button) return;
        periodState.pending = [...periodState.selected];
        renderPeriodMenu();
        menu.classList.toggle('open');
        button.setAttribute('aria-expanded', menu.classList.contains('open') ? 'true' : 'false');
    };

    const closePeriodMenu = () => {
        const menu = getPeriodMenu();
        const button = getPeriodButton();
        if (!menu || !button) return;
        menu.classList.remove('open');
        button.setAttribute('aria-expanded', 'false');
    };

    const bindPeriodControls = () => {
        const button = getPeriodButton();
        if (!button) return;
        button.addEventListener('click', openPeriodMenu);
        document.addEventListener('click', (event) => {
            const menu = getPeriodMenu();
            if (menu && !event.target.closest('.tracking-period-dropdown')) {
                closePeriodMenu();
            }
        });
    };

    const renderBars = (elementId, rows, valueKey) => {
        const element = document.getElementById(elementId);
        if (!element) return;

        if (!rows || rows.length === 0) {
            element.innerHTML = '<div class="tracking-empty">Sin datos para los filtros actuales.</div>';
            return;
        }

        const maxValue = Math.max(...rows.map((row) => Number(row[valueKey] || 0)), 1);
        element.innerHTML = rows.map((row) => {
            const label = row.central || row.estado || row.label || '-';
            const value = Number(row[valueKey] || 0);
            const width = Math.max((value / maxValue) * 100, 3);
            const secondary = valueKey === 'total_registros'
                ? `${formatNumber(row.total_metros, 1)} m | ${formatNumber(row.total_kg, 1)} kg`
                : `${formatNumber(value, 0)} registros`;
            return `
                <div class="tracking-bar">
                    <div class="tracking-bar__head">
                        <strong>${escapeHtml(label)}</strong>
                        <span>${secondary}</span>
                    </div>
                    <div class="tracking-bar__track">
                        <div class="tracking-bar__fill" style="width:${width}%"></div>
                    </div>
                </div>
            `;
        }).join('');
    };

    const loadPeriods = async () => {
        const payload = await fetchJson(`${API_BASE}/periodos.php`);
        const available = Array.isArray(payload.data?.periodos) ? payload.data.periodos.filter(Boolean) : [];
        periodState.available = available;

        const desired = available.length ? available[0] : currentPeriod();
        periodState.selected = [desired];
        periodState.pending = [...periodState.selected];
        updatePeriodButtonText();
        renderPeriodMenu();
        bindPeriodControls();
    };

    const loadStatus = async () => {
        const payload = await fetchJson(`${API_BASE}/estado.php`);
        const status = String(payload.data.status || 'sin_estado').toLowerCase();
        const dot = document.getElementById('statusDot');
        if (dot) {
            dot.classList.remove('is-ok', 'is-error');
            dot.classList.add(status === 'ok' ? 'is-ok' : 'is-error');
        }
        setText('statusTitle', status === 'ok' ? 'Actualización correcta' : 'Revisar automatización');
        setText('lastUpdate', formatDateTime(payload.data.last_update));
        setText('lastOrigin', formatDateTime(payload.data.last_origin_data));
        setText('recordsInserted', formatNumber(payload.data.records_inserted, 0));
    };

    const loadSummary = async () => {
        const params = new URLSearchParams();
        params.set('periodos', periodParam());

        const payload = await fetchJson(`${API_BASE}/resumen.php?${params.toString()}`);
        const totals = payload.data.totals || {};

        setText('kpiRegistros', formatNumber(totals.total_registros, 0));
        setText('kpiMetros', formatNumber(totals.total_metros, 1));
        setText('kpiKgAereo', formatNumber(totals.kg_teoricos_aereo, 1));
        setText('kpiKgSubte', formatNumber(totals.kg_teoricos_subte, 1));
        setText('centralesMeta', `${formatNumber(totals.centrales_unicas, 0)} centrales`);
        setText('estadosMeta', `${formatNumber(totals.estados_unicos, 0)} estados`);

        renderBars('centralesBars', payload.data.centrales || [], 'total_registros');
        renderBars('estadosBars', payload.data.estados || [], 'total_registros');
    };

    const renderRows = (rows) => {
        const tbody = document.getElementById('resultsBody');
        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td class="tracking-empty" colspan="10">No se encontraron registros para los filtros seleccionados.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map((row) => `
            <tr>
                <td>${escapeHtml(formatDateTime(row.fecha_alta_terreno))}</td>
                <td>${escapeHtml(row.numero_tiron || '-')}</td>
                <td>${escapeHtml(row.sigest || '-')}</td>
                <td>${escapeHtml(row.central || '-')}</td>
                <td>${escapeHtml(row.estado || '-')}</td>
                <td>${escapeHtml(row.tipo || '-')}</td>
                <td>${escapeHtml(formatNumber(row.metros, 2))}</td>
                <td>${escapeHtml(formatNumber(row.kg_teoricos, 2))}</td>
                <td>${escapeHtml(row.contratista || '-')}</td>
                <td>${escapeHtml(row.almacen || '-')}</td>
            </tr>
        `).join('');
    };

    const loadOptions = async () => {
        const payload = await fetchJson(`${API_BASE}/opciones.php`);
        const central = document.getElementById('centralFilter');
        const estado = document.getElementById('estadoFilter');
        if (central) {
            central.insertAdjacentHTML('beforeend', payload.data.centrales.map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join(''));
        }
        if (estado) {
            estado.insertAdjacentHTML('beforeend', payload.data.estados.map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join(''));
        }
    };

    const loadDetail = async (page = 1) => {
        const filtersForm = document.getElementById('filtersForm');
        const params = new URLSearchParams();
        params.set('pagina', String(page));
        params.set('tamano', '100');
        params.set('periodos', periodParam());

        if (filtersForm) {
            const formData = new FormData(filtersForm);
            for (const [key, value] of formData.entries()) {
                const text = String(value || '').trim();
                if (text && key !== 'periodos') {
                    params.set(key, text);
                }
            }
        }

        showNotice();
        const tbody = document.getElementById('resultsBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td class="tracking-empty" colspan="10">Consultando registros...</td></tr>';
        }

        try {
            const payload = await fetchJson(`${API_BASE}/registros.php?${params.toString()}`);
            const pagination = payload.pagination || {};
            renderRows(payload.data || []);
            setText('resultCount', `${formatNumber(pagination.total_rows, 0)} registros`);
            setText('pageStatus', `Página ${pagination.page || 1} de ${pagination.total_pages || 1}`);
            const previous = document.getElementById('previousPage');
            const next = document.getElementById('nextPage');
            if (previous) previous.disabled = (pagination.page || 1) <= 1;
            if (next) next.disabled = (pagination.page || 1) >= (pagination.total_pages || 1);
            const detailShell = document.getElementById('trackingDetalle');
            if (detailShell) {
                detailShell.dataset.page = String(pagination.page || 1);
            }
        } catch (error) {
            if (tbody) {
                tbody.innerHTML = '<tr><td class="tracking-empty" colspan="10">No fue posible cargar los registros.</td></tr>';
            }
            setText('resultCount', 'Consulta no disponible');
            showNotice(error.message);
        }
    };

    const refreshSummaryOrDetail = async () => {
        const summary = document.getElementById('trackingResumen');
        const detail = document.getElementById('trackingDetalle');

        if (summary) {
            await bootstrapSummary();
            return;
        }
        if (detail) {
            await loadDetail(1);
        }
    };

    const bootstrapSummary = async () => {
        const results = await Promise.allSettled([loadStatus(), loadSummary()]);
        const failure = results.find((result) => result.status === 'rejected');
        if (failure) {
            showNotice(failure.reason?.message || 'No se pudo completar la consulta.');
        }
    };

    const bootstrapDetail = async () => {
        const filtersForm = document.getElementById('filtersForm');
        const clear = document.getElementById('clearFilters');
        const previous = document.getElementById('previousPage');
        const next = document.getElementById('nextPage');

        await loadOptions().catch((error) => showNotice(error.message));
        await loadDetail(1).catch((error) => showNotice(error.message));

        filtersForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            loadDetail(1);
        });

        clear?.addEventListener('click', () => {
            const central = document.getElementById('centralFilter');
            const estado = document.getElementById('estadoFilter');
            const search = document.getElementById('searchFilter');
            if (central) central.value = '';
            if (estado) estado.value = '';
            if (search) search.value = '';
            loadDetail(1);
        });

        previous?.addEventListener('click', () => {
            const page = Number(document.getElementById('trackingDetalle')?.dataset.page || 1);
            if (page > 1) {
                loadDetail(page - 1);
            }
        });

        next?.addEventListener('click', () => {
            const page = Number(document.getElementById('trackingDetalle')?.dataset.page || 1);
            loadDetail(page + 1);
        });
    };

    document.addEventListener('DOMContentLoaded', async () => {
        try {
            await loadPeriods();
            if (document.getElementById('trackingResumen')) {
                await bootstrapSummary();
            }
            if (document.getElementById('trackingDetalle')) {
                await bootstrapDetail();
            }
        } catch (error) {
            showNotice(error.message);
        }
    });
})();
