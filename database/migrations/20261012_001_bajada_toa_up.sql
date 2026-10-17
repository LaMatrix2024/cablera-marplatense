-- Preparación local para la integración Dataflow TOA RAW -> Plantel -> NEXO.
-- NO ejecutar sin autorización de Etapa B, respaldo y validación de permisos.
-- Base física verificada en lectura el 12/10/2026: u767019378_plantel.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`bajada_toa_importaciones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_uuid` CHAR(36) NOT NULL,
  `periodo` CHAR(6) NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `archivo_origen` VARCHAR(255) NOT NULL,
  `archivo_sha256` CHAR(64) NOT NULL,
  `encabezados_sha256` CHAR(64) NOT NULL,
  `mapeo_columnas_json` JSON NOT NULL,
  `filas_excel` INT UNSIGNED NOT NULL DEFAULT 0,
  `filas_staging` INT UNSIGNED NOT NULL DEFAULT 0,
  `filas_publicadas` INT UNSIGNED NOT NULL DEFAULT 0,
  `fecha_inicio` DATETIME(6) NOT NULL,
  `fecha_publicacion` DATETIME(6) NULL,
  `fecha_fin` DATETIME(6) NULL,
  `ultimo_error` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bti_run_uuid` (`run_uuid`),
  UNIQUE KEY `uq_bti_periodo_archivo` (`periodo`, `archivo_sha256`),
  KEY `idx_bti_periodo_estado_publicacion` (`periodo`, `estado`, `fecha_publicacion`),
  CONSTRAINT `chk_bti_periodo` CHECK (`periodo` REGEXP '^[0-9]{6}$'),
  CONSTRAINT `chk_bti_estado` CHECK (`estado` IN ('CARGANDO', 'PUBLICADA', 'ERROR'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`bajada_toa_staging` (
  `importacion_id` BIGINT UNSIGNED NOT NULL,
  `fila_excel` INT UNSIGNED NOT NULL,
  `periodo` CHAR(6) NOT NULL,
  `row_sha256` CHAR(64) NOT NULL,
  `numero_de_peticion` VARCHAR(80) NULL,
  `cita_id` VARCHAR(80) NULL,
  `fecha_de_cita` DATETIME NULL,
  `dia` DATE NULL,
  `tecnologia` VARCHAR(50) NULL,
  `zona22` VARCHAR(80) NULL,
  `estado_de_la_orden` VARCHAR(50) NULL,
  `alta` TINYINT(1) NULL,
  `datos_json` JSON NOT NULL,
  PRIMARY KEY (`importacion_id`, `fila_excel`),
  CONSTRAINT `chk_bts_periodo` CHECK (`periodo` REGEXP '^[0-9]{6}$'),
  CONSTRAINT `fk_bts_importacion`
    FOREIGN KEY (`importacion_id`)
    REFERENCES `u767019378_plantel`.`bajada_toa_importaciones` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `u767019378_plantel`.`bajada_toa` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `importacion_id` BIGINT UNSIGNED NOT NULL,
  `fila_excel` INT UNSIGNED NOT NULL,
  `periodo` CHAR(6) NOT NULL,
  `row_sha256` CHAR(64) NOT NULL,
  `numero_de_peticion` VARCHAR(80) NULL,
  `cita_id` VARCHAR(80) NULL,
  `fecha_de_cita` DATETIME NULL,
  `dia` DATE NULL,
  `tecnologia` VARCHAR(50) NULL,
  `zona22` VARCHAR(80) NULL,
  `estado_de_la_orden` VARCHAR(50) NULL,
  `alta` TINYINT(1) NULL,
  `datos_json` JSON NOT NULL,
  `fecha_importacion` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bt_importacion_fila` (`importacion_id`, `fila_excel`),
  KEY `idx_bt_periodo_tecnologia_fecha` (`periodo`, `tecnologia`, `fecha_de_cita`),
  KEY `idx_bt_periodo_estado_alta` (`periodo`, `estado_de_la_orden`, `alta`),
  CONSTRAINT `chk_bt_periodo` CHECK (`periodo` REGEXP '^[0-9]{6}$'),
  CONSTRAINT `fk_bt_importacion`
    FOREIGN KEY (`importacion_id`)
    REFERENCES `u767019378_plantel`.`bajada_toa_importaciones` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La aplicación debe consultar sólo bajada_toa. Nunca debe leer staging.
-- La fecha visible de actualización debe provenir de fecha_publicacion de la
-- última fila PUBLICADA en bajada_toa_importaciones.
