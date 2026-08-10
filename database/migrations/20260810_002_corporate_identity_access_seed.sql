-- Datos maestros iniciales. Script idempotente.

INSERT INTO aplicaciones (codigo, nombre, descripcion, activo)
VALUES
    ('CABLERAMARPLATENSE', 'La Cablera Marplatense', 'Plataforma de Gestion Grupo Plantel.', 1),
    ('PLANTEL_MOBILE', 'Plantel Mobile', 'PWA corporativa Plantel.', 1)
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

INSERT INTO modulos (aplicacion_id, codigo, nombre, descripcion, orden, activo)
SELECT a.id, x.codigo, x.nombre, x.descripcion, x.orden, 1
FROM aplicaciones a
JOIN (
    SELECT 'GERENCIA' codigo, 'Gerencia' nombre, 'Area de gerencia y analisis transversal.' descripcion, 10 orden
    UNION ALL SELECT 'TELEFONIA', 'Telefonia', 'Area Telefonia.', 20
    UNION ALL SELECT 'TELEFONIA_PRODUCCION_PLANTA', 'Produccion Planta', 'Panel y detalle de produccion OCRAS y PTRs.', 30
    UNION ALL SELECT 'TELEFONIA_PRODUCCION_B2B', 'Produccion B2B', 'Panel de vision de ordenes de trabajo.', 40
    UNION ALL SELECT 'TELEFONIA_PRODUCCION_INSTALACIONES', 'Produccion Instalaciones', 'Panel de vision de contratos.', 50
    UNION ALL SELECT 'TELEFONIA_ECONOMICO', 'Informe economico', 'Gestion economica del negocio TELCO.', 60
    UNION ALL SELECT 'TELEFONIA_PRECIARIO_TMA', 'Preciario TMA', 'Gestion de precios y referencias TMA.', 70
    UNION ALL SELECT 'TELEFONIA_CONTROL_LOGICAS', 'Logicas HUB/CTO', 'Registros y control de certificacion CTO.', 80
    UNION ALL SELECT 'TELEFONIA_TRACKING_TIRONES', 'Tracking Tirones', 'Desmonte de cables desde ToolBox.', 90
    UNION ALL SELECT 'OBRAS', 'Obras', 'Procesos y reportes del area Obras.', 100
    UNION ALL SELECT 'RRHH', 'RRHH', 'Gestion de personas y estructura organizacional.', 110
    UNION ALL SELECT 'CONTABLE', 'Contable', 'Informacion contable y tableros de gestion.', 120
    UNION ALL SELECT 'MANTENIMIENTO', 'Mantenimiento', 'Seguimiento y administracion operativa.', 130
    UNION ALL SELECT 'LICITACIONES', 'Licitaciones', 'Procesos transversales de licitacion.', 140
) x
WHERE a.codigo = 'CABLERAMARPLATENSE'
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    orden = VALUES(orden),
    activo = VALUES(activo);

INSERT INTO roles (aplicacion_id, codigo, nombre, descripcion, activo)
SELECT a.id, x.codigo, x.nombre, x.descripcion, 1
FROM aplicaciones a
JOIN (
    SELECT 'ADMINISTRADOR' codigo, 'Administrador' nombre, 'Administracion funcional de La Cablera.' descripcion
    UNION ALL SELECT 'GERENCIA', 'Gerencia', 'Acceso gerencial a informacion de gestion.'
    UNION ALL SELECT 'JEFATURA', 'Jefatura', 'Seguimiento operativo y exportaciones autorizadas.'
    UNION ALL SELECT 'RESPONSABLE_PLANTA', 'Responsable Planta', 'Responsable de modulos de planta.'
    UNION ALL SELECT 'RESPONSABLE_INSTALACIONES', 'Responsable Instalaciones', 'Responsable de modulos de instalaciones.'
    UNION ALL SELECT 'ASISTENTE', 'Asistente', 'Consulta operativa basica.'
) x
WHERE a.codigo = 'CABLERAMARPLATENSE'
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    activo = VALUES(activo);

INSERT INTO usuarios (
    firebase_uid,
    email,
    nombre,
    apellido,
    estado,
    es_superadmin
)
VALUES (
    NULL,
    'aguileraclaudiomdq@gmail.com',
    'Claudio',
    'Aguilera',
    'ACTIVO',
    1
)
ON DUPLICATE KEY UPDATE
    estado = 'ACTIVO',
    es_superadmin = 1,
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO usuario_aplicacion (usuario_id, aplicacion_id, activo)
SELECT u.id, a.id, 1
FROM usuarios u
JOIN aplicaciones a ON a.codigo = 'CABLERAMARPLATENSE'
WHERE u.email = 'aguileraclaudiomdq@gmail.com'
ON DUPLICATE KEY UPDATE
    activo = VALUES(activo),
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO usuario_rol (usuario_id, aplicacion_id, rol_id, activo)
SELECT u.id, a.id, r.id, 1
FROM usuarios u
JOIN aplicaciones a ON a.codigo = 'CABLERAMARPLATENSE'
JOIN roles r ON r.aplicacion_id = a.id AND r.codigo = 'ADMINISTRADOR'
WHERE u.email = 'aguileraclaudiomdq@gmail.com'
ON DUPLICATE KEY UPDATE
    activo = VALUES(activo),
    updated_at = CURRENT_TIMESTAMP;

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
JOIN modulos m ON m.aplicacion_id = a.id
WHERE a.codigo = 'CABLERAMARPLATENSE'
ON DUPLICATE KEY UPDATE
    puede_ver = VALUES(puede_ver),
    puede_crear = VALUES(puede_crear),
    puede_editar = VALUES(puede_editar),
    puede_eliminar = VALUES(puede_eliminar),
    puede_exportar = VALUES(puede_exportar),
    puede_aprobar = VALUES(puede_aprobar);

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
SELECT r.id, a.id, m.id, 1, 0, 0, 0, 1, 0
FROM aplicaciones a
JOIN roles r ON r.aplicacion_id = a.id AND r.codigo IN ('GERENCIA', 'JEFATURA')
JOIN modulos m ON m.aplicacion_id = a.id
WHERE a.codigo = 'CABLERAMARPLATENSE'
ON DUPLICATE KEY UPDATE
    puede_ver = VALUES(puede_ver),
    puede_crear = VALUES(puede_crear),
    puede_editar = VALUES(puede_editar),
    puede_eliminar = VALUES(puede_eliminar),
    puede_exportar = VALUES(puede_exportar),
    puede_aprobar = VALUES(puede_aprobar);

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
SELECT r.id, a.id, m.id, 1, 0, 1, 0, 1, 1
FROM aplicaciones a
JOIN roles r ON r.aplicacion_id = a.id AND r.codigo = 'RESPONSABLE_PLANTA'
JOIN modulos m ON m.aplicacion_id = a.id AND m.codigo = 'TELEFONIA_PRODUCCION_PLANTA'
WHERE a.codigo = 'CABLERAMARPLATENSE'
ON DUPLICATE KEY UPDATE
    puede_ver = VALUES(puede_ver),
    puede_crear = VALUES(puede_crear),
    puede_editar = VALUES(puede_editar),
    puede_eliminar = VALUES(puede_eliminar),
    puede_exportar = VALUES(puede_exportar),
    puede_aprobar = VALUES(puede_aprobar);

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
SELECT r.id, a.id, m.id, 1, 0, 1, 0, 1, 1
FROM aplicaciones a
JOIN roles r ON r.aplicacion_id = a.id AND r.codigo = 'RESPONSABLE_INSTALACIONES'
JOIN modulos m ON m.aplicacion_id = a.id AND m.codigo = 'TELEFONIA_PRODUCCION_INSTALACIONES'
WHERE a.codigo = 'CABLERAMARPLATENSE'
ON DUPLICATE KEY UPDATE
    puede_ver = VALUES(puede_ver),
    puede_crear = VALUES(puede_crear),
    puede_editar = VALUES(puede_editar),
    puede_eliminar = VALUES(puede_eliminar),
    puede_exportar = VALUES(puede_exportar),
    puede_aprobar = VALUES(puede_aprobar);

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
SELECT r.id, a.id, m.id, 1, 0, 0, 0, 0, 0
FROM aplicaciones a
JOIN roles r ON r.aplicacion_id = a.id AND r.codigo = 'ASISTENTE'
JOIN modulos m ON m.aplicacion_id = a.id
WHERE a.codigo = 'CABLERAMARPLATENSE'
ON DUPLICATE KEY UPDATE
    puede_ver = VALUES(puede_ver),
    puede_crear = VALUES(puede_crear),
    puede_editar = VALUES(puede_editar),
    puede_eliminar = VALUES(puede_eliminar),
    puede_exportar = VALUES(puede_exportar),
    puede_aprobar = VALUES(puede_aprobar);
