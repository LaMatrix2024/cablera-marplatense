-- Alta explícita del módulo OCRAS móvil en identidad central.
-- Ejecutar en la base CENTRAL de identidad, NO en la base de obras.
-- Idempotente: no modifica autorizaciones de otros usuarios.
-- Requiere que exista la aplicación PLANTEL_MOBILE y el usuario corporativo.
START TRANSACTION;

INSERT INTO modulos (aplicacion_id, codigo, nombre, descripcion, ruta, icono, color, grupo, orden, activo)
SELECT a.id, 'CERTIFICACION_OCRAS', 'Certificación OCRAS',
       'Consulta móvil de pendientes y avances', '/plantel/modulos/certificacion-ocras',
       'certificacion', '#c91523', 'Obras', 50, 1
FROM aplicaciones a
WHERE a.codigo = 'PLANTEL_MOBILE'
  AND NOT EXISTS (
    SELECT 1 FROM modulos m
    WHERE m.aplicacion_id = a.id AND m.codigo = 'CERTIFICACION_OCRAS'
  );

-- Permiso de consulta, sin habilitar acciones de escritura.
-- No asigna el módulo a toda la organización.
INSERT INTO usuario_modulo
  (usuario_id, aplicacion_id, modulo_id, puede_ver, puede_crear,
   puede_editar, puede_eliminar, puede_exportar, puede_aprobar, motivo)
SELECT u.id, a.id, m.id, 1, 0, 0, 0, 0, 0,
       'Alta inicial de Certificación OCRAS móvil (solo lectura)'
FROM usuarios u
JOIN aplicaciones a ON a.codigo = 'PLANTEL_MOBILE'
JOIN modulos m ON m.aplicacion_id = a.id AND m.codigo = 'CERTIFICACION_OCRAS'
WHERE LOWER(u.email) = 'aguilera@plantel.com.ar'
  AND NOT EXISTS (
    SELECT 1 FROM usuario_modulo um
    WHERE um.usuario_id = u.id AND um.aplicacion_id = a.id AND um.modulo_id = m.id
  );

-- Comprobar que el usuario tiene asignada la aplicación PLANTEL_MOBILE.
-- No se crea esa asignación de manera implícita.
SELECT u.email, a.codigo AS aplicacion, m.codigo AS modulo,
       ua.activo AS aplicacion_habilitada, um.puede_ver
FROM usuarios u
JOIN aplicaciones a ON a.codigo = 'PLANTEL_MOBILE'
JOIN modulos m ON m.aplicacion_id = a.id AND m.codigo = 'CERTIFICACION_OCRAS'
LEFT JOIN usuario_aplicacion ua ON ua.usuario_id = u.id AND ua.aplicacion_id = a.id
LEFT JOIN usuario_modulo um ON um.usuario_id = u.id AND um.aplicacion_id = a.id AND um.modulo_id = m.id
WHERE LOWER(u.email) = 'aguilera@plantel.com.ar';

COMMIT;
