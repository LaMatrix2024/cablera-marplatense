-- Cambios aditivos para la identidad central de La Cablera.
-- No modifica users ni las tablas de Firebase/legacy.
ALTER TABLE usuarios
    ADD COLUMN password_hash VARCHAR(255) NULL AFTER email,
    ADD COLUMN sesion_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER es_superadmin;

CREATE TABLE auth_sesiones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    csrf_hash CHAR(64) NOT NULL,
    sesion_version INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_sesiones_token_hash (token_hash),
    KEY ix_auth_sesiones_usuario (usuario_id, revoked_at),
    KEY ix_auth_sesiones_expira (expires_at),
    CONSTRAINT fk_auth_sesiones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_rate_limits (
    rate_key VARCHAR(190) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rate_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
