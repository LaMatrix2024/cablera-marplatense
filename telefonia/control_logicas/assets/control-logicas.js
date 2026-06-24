(() => {
    'use strict';

    const API_BASE = '/api/telefonia/control_logicas';
    const state = {
        page: 1,
        totalPages: 1,
        currentPeriod: '',
    };

    const elements = {
        filtersForm: document.querySelector('#filtersForm'),
        period: document.querySelector('#periodFilter'),
        network: document.querySelector('#networkFilter'),
        strand: document.querySelector('#strandFilter'),
        clear: document.querySelector('#clearFilters'),
        resultsBody: document.querySelector('#resultsBody'),
        resultCount: document.querySelector('#resultCount'),
        pageStatus: document.querySelector('#pageStatus'),
        previous: document.querySelector('#previousPage'),
        next: document.querySelector('#nextPage'),
        notice: document.querySelector('#notice'),
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const formatDateTime = (value) => {
        if (!value) return '—';
        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return String(value);
        return new Intl.DateTimeFormat('es-AR', {
            dateStyle: 'short',
            timeStyle: 'medium',
        }).format(date);
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

    const showNotice = (message = '') => {
        elements.notice.textContent = message;
        elements.notice.classList.toggle('logic-notice--hidden', !message);
    };

    const loadSummary = async () => {
        const payload = await fetchJson(`${API_BASE}/resumen.php`);
        document.querySelector('#kpiHubOk').textContent = payload.data.hub_ok;
        document.querySelector('#kpiHubNoOk').textContent = payload.data.hub_no_ok;
        document.querySelector('#kpiCtoOk').textContent = payload.data.cto_ok;
        document.querySelector('#kpiCtoNoOk').textContent = payload.data.cto_no_ok;
    };

    const loadStatus = async () => {
        const payload = await fetchJson(`${API_BASE}/estado.php`);
        const status = String(payload.data.status || 'sin_estado').toLowerCase();
        const statusLabel = status === 'ok' ? 'Actualización correcta' : 'Revisar automatización';
        const dot = document.querySelector('#statusDot');

        document.querySelector('#statusTitle').textContent = statusLabel;
        document.querySelector('#lastUpdate').textContent = formatDateTime(payload.data.last_update);
        document.querySelector('#lastOrigin').textContent = formatDateTime(payload.data.last_origin_data);
        dot.classList.remove('is-ok', 'is-error');
        dot.classList.add(status === 'ok' ? 'is-ok' : 'is-error');
    };

    const loadPeriods = async () => {
        const payload = await fetchJson(`${API_BASE}/periodos.php`);
        state.currentPeriod = payload.current_period;
        elements.period.replaceChildren();

        const periods = payload.data.some((item) => item.period === payload.current_period)
            ? payload.data
            : [{ period: payload.current_period, count: 0 }, ...payload.data];

        periods.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.period;
            option.textContent = `${item.period} · ${item.count} pruebas`;
            option.selected = item.period === payload.current_period;
            elements.period.append(option);
        });
    };

    const rowHtml = (row) => {
        const resultClass = row.result === 'OK'
            ? 'logic-badge--ok'
            : (row.result === 'NO OK' ? 'logic-badge--error' : 'logic-badge--neutral');
        return `
            <tr>
                <td>${escapeHtml(formatDateTime(row.date_time))}</td>
                <td><span class="logic-badge logic-badge--type">${escapeHtml(row.type)}</span></td>
                <td><span class="logic-badge ${resultClass}">${escapeHtml(row.result)}</span></td>
                <td><strong>${escapeHtml(row.network_strand || '—')}</strong></td>
                <td>${escapeHtml(row.reference_1 || '—')}</td>
                <td>${escapeHtml(row.reference_2 || '—')}</td>
                <td>${escapeHtml(row.optical_power_tx || '—')}</td>
                <td><span class="logic-message" title="${escapeHtml(row.message || '')}">${escapeHtml(row.message || '—')}</span></td>
                <td>${escapeHtml(row.user || '—')}</td>
            </tr>
        `;
    };

    const loadTests = async () => {
        showNotice();
        elements.resultsBody.innerHTML = '<tr><td class="logic-empty" colspan="9">Consultando pruebas...</td></tr>';

        const params = new URLSearchParams({
            periodo: elements.period.value || state.currentPeriod,
            pagina: String(state.page),
        });
        const network = elements.network.value.trim().toUpperCase();
        const strand = elements.strand.value.trim();
        if (network) params.set('red', network);
        if (strand) params.set('pelo', strand);

        try {
            const payload = await fetchJson(`${API_BASE}/pruebas.php?${params}`);
            state.page = payload.pagination.page;
            state.totalPages = payload.pagination.total_pages;

            elements.resultsBody.innerHTML = payload.data.length
                ? payload.data.map(rowHtml).join('')
                : '<tr><td class="logic-empty" colspan="9">No se encontraron pruebas para los filtros seleccionados.</td></tr>';

            elements.resultCount.textContent = `${payload.pagination.total_rows.toLocaleString('es-AR')} pruebas · ${payload.filters.period}`;
            elements.pageStatus.textContent = `Página ${state.page} de ${state.totalPages}`;
            elements.previous.disabled = state.page <= 1;
            elements.next.disabled = state.page >= state.totalPages;
        } catch (error) {
            elements.resultsBody.innerHTML = '<tr><td class="logic-empty" colspan="9">No fue posible cargar las pruebas.</td></tr>';
            elements.resultCount.textContent = 'Consulta no disponible';
            showNotice(error.message);
        }
    };

    elements.filtersForm.addEventListener('submit', (event) => {
        event.preventDefault();
        state.page = 1;
        loadTests();
    });

    elements.clear.addEventListener('click', () => {
        elements.period.value = state.currentPeriod;
        elements.network.value = '';
        elements.strand.value = '';
        state.page = 1;
        loadTests();
    });

    elements.previous.addEventListener('click', () => {
        if (state.page <= 1) return;
        state.page -= 1;
        loadTests();
    });

    elements.next.addEventListener('click', () => {
        if (state.page >= state.totalPages) return;
        state.page += 1;
        loadTests();
    });

    const initialize = async () => {
        const tasks = [loadSummary(), loadStatus(), loadPeriods()];
        const results = await Promise.allSettled(tasks);
        const failed = results.find((result) => result.status === 'rejected');
        if (failed) showNotice(failed.reason.message);
        if (elements.period.options.length) await loadTests();
    };

    initialize();
})();
