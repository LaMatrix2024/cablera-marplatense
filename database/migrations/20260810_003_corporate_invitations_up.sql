-- LCM / Plantel - Invitaciones corporativas
-- Migracion no destructiva.

CREATE TABLE IF NOT EXISTS invitaciones_usuario (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    estado ENUM('PENDIENTE','ACEPTADA','VENCIDA','REVOCADA') NOT NULL DEFAULT 'PENDIENTE',
    expires_at DATETIME NOT NULL,
    created_by_usuario_id BIGINT UNSIGNED NOT NULL,
    accepted_by_usuario_id BIGINT UNSIGNED NULL,
    accepted_at DATETIME NULL,
    revoked_at DATETIME NULL,
    revoked_by_usuario_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invitaciones_usuario_token_hash (token_hash),
    KEY idx_invitaciones_usuario_email (email),
    KEY idx_invitaciones_usuario_estado (estado),
    KEY idx_invitaciones_usuario_expires_at (expires_at),
    KEY idx_invitaciones_usuario_created_by (created_by_usuario_id),
    KEY idx_invitaciones_usuario_accepted_by (accepted_by_usuario_id),
    KEY idx_invitaciones_usuario_revoked_by (revoked_by_usuario_id),
    CONSTRAINT fk_invitaciones_usuario_created_by
        FOREIGN KEY (created_by_usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_invitaciones_usuario_accepted_by
        FOREIGN KEY (accepted_by_usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_invitaciones_usuario_revoked_by
        FOREIGN KEY (revoked_by_usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invitacion_aplicacion (
    invitacion_id BIGINT UNSIGNED NOT NULL,
    aplicacion_id BIGINT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (invitacion_id, aplicacion_id),
    KEY idx_invitacion_aplicacion_invitacion_id (invitacion_id),
    KEY idx_invitacion_aplicacion_aplicacion_id (aplicacion_id),
    CONSTRAINT fk_invitacion_aplicacion_invitacion
        FOREIGN KEY (invitacion_id) REFERENCES invitaciones_usuario(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_invitacion_aplicacion_aplicacion
        FOREIGN KEY (aplicacion_id) REFERENCES aplicaciones(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invitacion_rol (
    invitacion_id BIGINT UNSIGNED NOT NULL,
    aplicacion_id BIGINT UNSIGNED NOT NULL,
    rol_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (invitacion_id, aplicacion_id, rol_id),
    KEY idx_invitacion_rol_invitacion_id (invitacion_id),
    KEY idx_invitacion_rol_aplicacion_id (aplicacion_id),
    KEY idx_invitacion_rol_rol_id (rol_id),
    CONSTRAINT fk_invitacion_rol_invitacion_aplicacion
        FOREIGN KEY (invitacion_id, aplicacion_id) REFERENCES invitacion_aplicacion(invitacion_id, aplicacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_invitacion_rol_rol_aplicacion
        FOREIGN KEY (rol_id, aplicacion_id) REFERENCES roles(id, aplicacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invitacion_modulo (
    invitacion_id BIGINT UNSIGNED NOT NULL,
    aplicacion_id BIGINT UNSIGNED NOT NULL,
    modulo_id BIGINT UNSIGNED NOT NULL,
    puede_ver TINYINT(1) NULL,
    puede_crear TINYINT(1) NULL,
    puede_editar TINYINT(1) NULL,
    puede_eliminar TINYINT(1) NULL,
    puede_exportar TINYINT(1) NULL,
    puede_aprobar TINYINT(1) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (invitacion_id, modulo_id),
    KEY idx_invitacion_modulo_invitacion_id (invitacion_id),
    KEY idx_invitacion_modulo_aplicacion_id (aplicacion_id),
    KEY idx_invitacion_modulo_modulo_id (modulo_id),
    CONSTRAINT fk_invitacion_modulo_invitacion_aplicacion
        FOREIGN KEY (invitacion_id, aplicacion_id) REFERENCES invitacion_aplicacion(invitacion_id, aplicacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_invitacion_modulo_modulo_aplicacion
        FOREIGN KEY (modulo_id, aplicacion_id) REFERENCES modulos(id, aplicacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

