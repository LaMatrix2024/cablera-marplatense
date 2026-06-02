async function cargarCentrales() {
  Matrix.setMessage('centrales-mensaje', 'Cargando centrales...');

  try {
    const payload = await Matrix.getJson('centrales.php');
    Matrix.setText('centrales-total', payload.total);
    Matrix.renderRows('centrales-tabla', payload.datos, ['id_central', 'central']);
    Matrix.setMessage('centrales-mensaje', `Datos cargados: ${payload.total}`);
  } catch (error) {
    Matrix.setMessage('centrales-mensaje', error.message, true);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btn-cargar-centrales')?.addEventListener('click', cargarCentrales);
  cargarCentrales();
});