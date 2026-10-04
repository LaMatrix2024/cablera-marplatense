-- Metadatos administrables para el catalogo dinamico de La Cablera.
-- Idempotente y no destructiva.

ALTER TABLE modulos
    ADD COLUMN IF NOT EXISTS ruta VARCHAR(300) NULL AFTER descripcion,
    ADD COLUMN IF NOT EXISTS icono VARCHAR(60) NOT NULL DEFAULT 'apps' AFTER ruta,
    ADD COLUMN IF NOT EXISTS color VARCHAR(20) NULL AFTER icono,
    ADD COLUMN IF NOT EXISTS grupo VARCHAR(100) NOT NULL DEFAULT 'GESTION' AFTER color;

UPDATE modulos SET ruta = CASE codigo
    WHEN 'GERENCIA' THEN '/gerencia/'
    WHEN 'TELEFONIA' THEN '/telefonia/'
    WHEN 'TELEFONIA_PRODUCCION_PLANTA' THEN '/telefonia/produccion_planta/'
    WHEN 'TELEFONIA_PRODUCCION_B2B' THEN '/telefonia/produccion_b2b/'
    WHEN 'TELEFONIA_PRODUCCION_INSTALACIONES' THEN '/telefonia/produccion_instalaciones/'
    WHEN 'TELEFONIA_ECONOMICO' THEN '/telefonia/economico/'
    WHEN 'TELEFONIA_PRECIARIO_TMA' THEN '/telefonia/preciario_tma/'
    WHEN 'TELEFONIA_CONTROL_LOGICAS' THEN '/telefonia/control_logicas/'
    WHEN 'TELEFONIA_TRACKING_TIRONES' THEN '/telefonia/tracking_tirones/'
    WHEN 'OBRAS' THEN '/obras/'
    WHEN 'RRHH' THEN '/rrhh/'
    WHEN 'CONTABLE' THEN '/contable/'
    WHEN 'MANTENIMIENTO' THEN '/mantenimiento/'
    WHEN 'LICITACIONES' THEN '/licitaciones/'
    WHEN 'IDENTIDAD_ACCESOS' THEN '/admin/identidad-accesos/'
    ELSE ruta END,
    icono = CASE codigo
    WHEN 'TELEFONIA' THEN 'phone'
    WHEN 'IDENTIDAD_ACCESOS' THEN 'badge'
    WHEN 'RRHH' THEN 'group'
    WHEN 'CONTABLE' THEN 'file'
    WHEN 'TELEFONIA_PRECIARIO_TMA' THEN 'file'
    WHEN 'TELEFONIA_CONTROL_LOGICAS' THEN 'settings'
    WHEN 'MANTENIMIENTO' THEN 'wrench'
    WHEN 'LICITACIONES' THEN 'file'
    ELSE 'chart' END,
    grupo = CASE
    WHEN codigo = 'IDENTIDAD_ACCESOS' THEN 'ADMINISTRACION'
    WHEN codigo IN ('TELEFONIA_PRODUCCION_PLANTA','TELEFONIA_PRODUCCION_B2B','TELEFONIA_PRODUCCION_INSTALACIONES','TELEFONIA_ECONOMICO','TELEFONIA_PRECIARIO_TMA','TELEFONIA_CONTROL_LOGICAS','TELEFONIA_TRACKING_TIRONES') THEN 'TELEFONIA'
    ELSE 'GESTION' END
WHERE aplicacion_id = (SELECT id FROM aplicaciones WHERE codigo = 'CABLERAMARPLATENSE' LIMIT 1);
