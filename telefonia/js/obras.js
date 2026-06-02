async function cargarObras() {
  Matrix.setMessage('obras-mensaje', 'Cargando...');

  try {
    const payload = await Matrix.getJson('obras.php');
    Matrix.setText('obras-total', payload.total);
    Matrix.renderRows('obras-tabla', payload.datos, ['id', 'nombre', 'estado']);
    Matrix.setMessage('obras-mensaje', 'Datos cargados.');
  } catch (error) {
    Matrix.setMessage('obras-mensaje', error.message, true);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btn-cargar-obras')?.addEventListener('click', cargarObras);
});
