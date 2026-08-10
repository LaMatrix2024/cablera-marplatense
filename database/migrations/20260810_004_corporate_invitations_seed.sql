-- Datos maestros para gestion de invitaciones.

INSERT INTO modulos (aplicacion_id, codigo, nombre, descripcion, orden, activo)
SELECT a.id, 'IDENTIDAD_ACCESOS', 'Identidad y accesos', 'Gestion corporativa de usuarios, invitaciones y accesos.', 5, 1
FROM aplicaciones a
WHERE a.codigo = 'CABLERAMARPLATENSE'
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    orden = VALUES(orden),
    activo = VALUES(activo);

INSERT INTO rol_modulo (
    rol_id,
    aplicacion_id,
    modulo_id,
    puede_ver,
    puede_crear,
    puede_editar,
    puede_eliminar,
    puede_exportar,
    puede_aprobar
)
SELECT r.id, a.id, m.id, 1, 1, 1, 1, 1, 1
FROM aplicaciones a
JOIN roles r ON r.aplicacion_id = a.id AND r.codigo = 'ADMINISTRADOR'
JOIN modulos m ON m.aplicacion_id = a.id AND m.codigo = 'IDENTIDAD_ACCESOS'
WHERE a.codigo = 'CABLERAMARPLATENSE'
ON DUPLICATE KEY UPDATE
    puede_ver = VALUES(puede_ver),
    puede_crear = VALUES(puede_crear),
    puede_editar = VALUES(puede_editar),
    puede_eliminar = VALUES(puede_eliminar),
    puede_exportar = VALUES(puede_exportar),
    puede_aprobar = VALUES(puede_aprobar);

