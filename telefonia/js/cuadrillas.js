async function cargarCuadrillas() {
  Matrix.setMessage('cuadrillas-mensaje', 'Cargando...');

  try {
    const payload = await Matrix.getJson('cuadrillas.php');
    Matrix.setText('cuadrillas-total', payload.total);
    Matrix.renderRows('cuadrillas-tabla', payload.datos, ['id', 'nombre', 'zona']);
    Matrix.setMessage('cuadrillas-mensaje', 'Datos cargados.');
  } catch (error) {
    Matrix.setMessage('cuadrillas-mensaje', error.message, true);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btn-cargar-cuadrillas')?.addEventListener('click', cargarCuadrillas);
});
