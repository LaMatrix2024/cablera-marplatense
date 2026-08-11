-- TEMPORAL E2E - Plantel Mobile / Hola Mundo
-- Crea solo el modulo temporal usado para validar integracion Cablera -> Plantel Mobile.

INSERT INTO modulos (
    aplicacion_id,
    codigo,
    nombre,
    descripcion,
    orden,
    activo
)
SELECT
    a.id,
    'HOLA_MUNDO',
    'Hola Mundo!!',
    'Modulo temporal para validacion end-to-end',
    10,
    1
FROM aplicaciones a
WHERE a.codigo = 'PLANTEL_MOBILE'
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion),
    orden = VALUES(orden),
    activo = VALUES(activo);

