const API_ESTADO = '/api/gerencia/analisis_economico_telco/estado.php';

const elementos = {
    estado: document.getElementById('estadoImportacion'),
    archivo: document.getElementById('archivoProcesado'),
    fecha: document.getElementById('fechaImportacion'),
    leidas: document.getElementById('filasLeidas'),
    insertadas: document.getElementById('filasInsertadas'),
    errores: document.getElementById('filasError'),
    mensaje: document.getElementById('mensajeEstado'),
};

function numero(valor) {
    return new Intl.NumberFormat('es-AR').format(Number(valor || 0));
}

function fechaHora(valor) {
    if (!valor) return '—';
    const fecha = new Date(String(valor).replace(' ', 'T'));
    if (Number.isNaN(fecha.getTime())) return valor;
    return new Intl.DateTimeFormat('es-AR', {
        dateStyle: 'short',
        timeStyle: 'medium',
    }).format(fecha);
}

function mostrarEstado(estado) {
    const normalizado = String(estado || 'SIN DATOS').toUpperCase();
    elementos.estado.textContent = normalizado;
    elementos.estado.className = 'iet-badge iet-badge--neutral';

    if (normalizado === 'OK') {
        elementos.estado.className = 'iet-badge iet-badge--ok';
    } else if (normalizado === 'ERROR') {
        elementos.estado.className = 'iet-badge iet-badge--error';
    } else if (normalizado === 'PARCIAL' || normalizado === 'PROCESANDO') {
        elementos.estado.className = 'iet-badge iet-badge--warning';
    }
}

async function cargarEstado() {
    try {
        const respuesta = await fetch(API_ESTADO, { cache: 'no-store' });
        const json = await respuesta.json();
        if (!respuesta.ok || !json.ok) {
            throw new Error(json.error || 'Respuesta inválida.');
        }

        const importacion = json.ultima_importacion;
        if (!importacion) {
            mostrarEstado('Sin datos');
            elementos.mensaje.textContent = 'Todavía no se registraron importaciones del Informe Económico TELCO.';
            return;
        }

        mostrarEstado(importacion.estado);
        elementos.archivo.textContent = importacion.archivo_nombre || '—';
        elementos.fecha.textContent = fechaHora(importacion.fecha_importacion);
        elementos.leidas.textContent = numero(importacion.filas_leidas);
        elementos.insertadas.textContent = numero(importacion.filas_insertadas);
        elementos.errores.textContent = numero(importacion.filas_error);
        elementos.mensaje.textContent = importacion.observaciones
            || 'La última ejecución no registró observaciones.';
    } catch (error) {
        mostrarEstado('Error');
        elementos.mensaje.textContent = 'No se pudo consultar el estado de la última importación.';
    }
}

cargarEstado();
