-- Reversión segura y no destructiva.
-- Estas tablas pueden contener históricos y publicaciones posteriores.
-- Para volver atrás: restaurar el consumidor anterior, conservar las tablas,
-- exportar y conciliar los períodos. No se ejecutan DROP TABLE automáticamente.

SELECT 'REVERSIÓN NO DESTRUCTIVA: no se ejecutaron DROP TABLE' AS estado;
SELECT 'Tablas a conservar: bajada_toa, bajada_toa_staging, bajada_toa_importaciones' AS tablas;
