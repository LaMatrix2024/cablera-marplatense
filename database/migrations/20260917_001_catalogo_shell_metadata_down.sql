-- Rollback de los metadatos del catalogo dinamico.
ALTER TABLE modulos
    DROP COLUMN IF EXISTS grupo,
    DROP COLUMN IF EXISTS color,
    DROP COLUMN IF EXISTS icono,
    DROP COLUMN IF EXISTS ruta;
