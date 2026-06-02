async function cargarCertificacion() {
  Matrix.setMessage('certificacion-mensaje', 'Cargando...');

  try {
    const payload = await Matrix.getJson('certificacion.php');
    Matrix.setText('certificacion-total', payload.total);
    Matrix.renderRows('certificacion-tabla', payload.datos, ['id', 'concepto', 'estado']);
    Matrix.setMessage('certificacion-mensaje', 'Datos cargados.');
  } catch (error) {
    Matrix.setMessage('certificacion-mensaje', error.message, true);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btn-cargar-certificacion')?.addEventListener('click', cargarCertificacion);
});
