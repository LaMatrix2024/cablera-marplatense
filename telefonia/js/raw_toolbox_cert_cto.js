function valorTexto(value) {
  return value === null || value === undefined || value === '' ? '-' : value;
}

function formatoFechaCorta(value) {
  if (!value) {
    return '-';
  }

  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
  if (!match) {
    return value;
  }

  return `${match[3]}-${match[2]}-${match[1].slice(2)} ${match[4]}:${match[5]}`;
}

let rawCtoRows = [];
let fibrasCargadas = false;

function selectedValues(select) {
  return Array.from(select?.selectedOptions || [])
    .map((option) => option.value)
    .filter(Boolean);
}

function cargarOpcionesFibra(options) {
  const select = document.getElementById('raw-cto-fibra');
  if (!select || fibrasCargadas) {
    return;
  }

  const todas = document.createElement('option');
  todas.value = '';
  todas.textContent = 'Todas';
  todas.selected = true;
  select.appendChild(todas);

  (options || []).forEach((item) => {
    const option = document.createElement('option');
    option.value = item.fibra_grupo;
    option.textContent = `${item.fibra_grupo} (${item.total})`;
    select.appendChild(option);
  });

  fibrasCargadas = true;
}

function abrirPayload(index) {
  const modal = document.getElementById('payload-modal');
  const content = document.getElementById('payload-modal-content');
  const row = rawCtoRows[index];
  if (!modal || !content || !row) {
    return;
  }

  try {
    content.textContent = JSON.stringify(JSON.parse(row.payload_json), null, 2);
  } catch (error) {
    content.textContent = row.payload_json || '';
  }
  modal.hidden = false;
}

function cerrarPayload() {
  const modal = document.getElementById('payload-modal');
  if (modal) {
    modal.hidden = true;
  }
}

function renderRawCtoRows(rows) {
  const tbody = document.getElementById('raw-cto-tabla');
  if (!tbody) {
    return;
  }

  tbody.innerHTML = '';
  rawCtoRows = rows;

  rows.forEach((row, index) => {
    const tr = document.createElement('tr');
    const fechaTd = document.createElement('td');
    fechaTd.textContent = formatoFechaCorta(row.fecha_hora_origen);
    tr.appendChild(fechaTd);

    [
      'apellido',
      'fibra',
      'tipo_cto',
      'nro_serie',
      'potencia_optica_tx',
      'dato_cto1',
      'dato_cto2',
      'mensaje'
    ].forEach((column) => {
      const td = document.createElement('td');
      td.textContent = valorTexto(row[column]);
      tr.appendChild(td);
    });

    const action = document.createElement('td');
    const button = document.createElement('button');
    button.className = 'icon-button';
    button.type = 'button';
    button.title = 'Ver payload raw';
    button.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>';
    button.addEventListener('click', () => abrirPayload(index));
    action.appendChild(button);
    tr.appendChild(action);

    tbody.appendChild(tr);
  });
}

async function cargarRawToolboxCto() {
  Matrix.setMessage('raw-cto-mensaje', 'Cargando registros...');

  try {
    const params = new URLSearchParams({ limit: '50' });
    const fechaDesde = document.getElementById('raw-cto-fecha-desde')?.value || '';
    const fechaHasta = document.getElementById('raw-cto-fecha-hasta')?.value || '';
    const fibras = selectedValues(document.getElementById('raw-cto-fibra')).filter((value) => value !== '');
    const resultado = document.getElementById('raw-cto-resultado')?.value || '';

    if (fechaDesde) params.set('fecha_desde', fechaDesde);
    if (fechaHasta) params.set('fecha_hasta', fechaHasta);
    if (fibras.length) params.set('fibra', fibras.join(','));
    if (resultado) params.set('resultado', resultado);

    const payload = await Matrix.getJson(`raw_toolbox_cert_cto.php?${params.toString()}`);
    cargarOpcionesFibra(payload.fibra_options);
    const status = payload.status || {};
    Matrix.setText('raw-cto-total', payload.total);
    Matrix.setText('raw-cto-estado', valorTexto(status.resultado_actualizacion));
    Matrix.setText('raw-cto-actualizacion', formatoFechaCorta(status.ultima_fecha_hora_actualizacion));
    Matrix.setText('raw-cto-fecha-origen', formatoFechaCorta(status.ultima_fecha_hora_origen || payload.summary?.fecha_max));
    renderRawCtoRows(payload.datos || []);
    Matrix.setMessage(
      'raw-cto-mensaje',
      `Datos cargados: ${payload.datos.length} ultimos registros | Filtrados: ${payload.filtered_total}`
    );
  } catch (error) {
    Matrix.setMessage('raw-cto-mensaje', error.message, true);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btn-cargar-raw-cto')?.addEventListener('click', cargarRawToolboxCto);
  ['raw-cto-fecha-desde', 'raw-cto-fecha-hasta', 'raw-cto-fibra', 'raw-cto-resultado'].forEach((id) => {
    document.getElementById(id)?.addEventListener('change', cargarRawToolboxCto);
  });
  document.getElementById('payload-modal-close')?.addEventListener('click', cerrarPayload);
  document.getElementById('payload-modal')?.addEventListener('click', (event) => {
    if (event.target.id === 'payload-modal') {
      cerrarPayload();
    }
  });
  cargarRawToolboxCto();
});
