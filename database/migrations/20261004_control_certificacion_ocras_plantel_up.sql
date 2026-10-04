-- Preparación reversible para Control de certificación OCRAS.
-- NO ejecutar desde la aplicación. Requiere backup previo y ventana coordinada.
-- Las sentencias son aditivas; no eliminan ni renombran tablas existentes.

SET NAMES utf8mb4;

-- Tablas operativas y fuente RAW. LIKE conserva columnas, índices y charset reales.
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`raw_certificacion_ocras`
  LIKE `u767019378_laboratorio`.`raw_certificacion_ocras`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`control_certificacion_tareas`
  LIKE `u767019378_laboratorio`.`control_certificacion_tareas`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`control_certificacion_asignaciones`
  LIKE `u767019378_laboratorio`.`control_certificacion_asignaciones`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`control_certificacion_eventos`
  LIKE `u767019378_laboratorio`.`control_certificacion_eventos`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`control_certificacion_fuente_estado`
  LIKE `u767019378_laboratorio`.`control_certificacion_fuente_estado`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`control_certificacion_historial_operativo`
  LIKE `u767019378_laboratorio`.`control_certificacion_historial_operativo`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`control_certificacion_monitores`
  LIKE `u767019378_laboratorio`.`control_certificacion_monitores`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`control_certificacion_novedades`
  LIKE `u767019378_laboratorio`.`control_certificacion_novedades`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`control_certificacion_sobrestantes`
  LIKE `u767019378_laboratorio`.`control_certificacion_sobrestantes`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`control_certificacion_comunicaciones`
  LIKE `u767019378_laboratorio`.`control_certificacion_comunicaciones`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`control_certificacion_snapshots`
  LIKE `u767019378_laboratorio`.`control_certificacion_snapshots`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`responsables_nexo`
  LIKE `u767019378_laboratorio`.`responsables_nexo`;
CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`nexo_usuarios_vinculaciones_operativas`
  LIKE `u767019378_laboratorio`.`nexo_usuarios_vinculaciones_operativas`;

-- La copia de datos NO se ejecuta con INSERT IGNORE: ocultaría modificaciones
-- concurrentes y no permite revertir relaciones e historial de forma segura.
-- Ejecutar primero el plan/backup y luego la conciliación controlada:
--   python tools/control_certificacion_ocras_reconcile.py --manifest <fuera-del-repo> --cutover-utc <corte>
-- La conciliación compara claves, huellas y fechas, conserva sólo-destino y
-- registra conflictos sin sobrescribir cambios posteriores al corte.

-- La conciliación de IDs centrales/legacy NO se resuelve automáticamente en SQL.
-- Debe validarse por correo normalizado y conservar snapshots históricos.
