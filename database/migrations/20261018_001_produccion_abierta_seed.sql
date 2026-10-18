-- Preparado, NO ejecutado. Usar la base de identidad central vigente.
-- No crea usuarios ni concede permisos; asignarlos desde Identidad y Accesos.
INSERT INTO modulos (aplicacion_id, codigo, nombre, descripcion, ruta, icono, color, grupo, orden, activo)
SELECT id, 'PRODUCCION_ABIERTA', 'Producción abierta',
       'Horas OCRAS y PTRS del último período sin bloquear ni cerrar.',
       '/plantel/modulos/produccion-abierta', 'chart', '#047857', 'TELEFONIA', 20, 1
FROM aplicaciones
WHERE codigo = 'PLANTEL_MOBILE'
  AND NOT EXISTS (SELECT 1 FROM modulos m
      WHERE m.aplicacion_id = aplicaciones.id AND m.codigo = 'PRODUCCION_ABIERTA');
