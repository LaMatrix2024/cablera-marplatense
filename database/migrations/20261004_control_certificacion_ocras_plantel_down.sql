-- Reversión segura y no destructiva.
-- No se eliminan tablas: podrían contener escrituras posteriores al corte.
-- Procedimiento: detener el enrutamiento nuevo, restaurar consumidores a la
-- versión anterior, conservar estas tablas como respaldo y conciliar cualquier
-- diferencia posterior mediante el manifiesto de conflictos. Un DROP sólo
-- puede ejecutarse después de exportar cada tabla y verificar consumidores.
SELECT 'REVERSIÓN NO DESTRUCTIVA: no se ejecutaron DROP TABLE' AS estado;
SELECT 'Tablas a conciliar: raw_certificacion_ocras, raw_bigstorm_precios, automatizaciones_estado, control_certificacion_tareas, control_certificacion_asignaciones, control_certificacion_eventos, control_certificacion_fuente_estado, control_certificacion_historial_operativo, control_certificacion_novedades, control_certificacion_monitores, control_certificacion_sobrestantes, control_certificacion_comunicaciones, control_certificacion_snapshots, responsables_nexo' AS tablas;
SELECT 'Excluidas del corte automático: nexo_usuarios_vinculaciones_operativas y tablas centrales de identidad/permisos' AS exclusiones;
