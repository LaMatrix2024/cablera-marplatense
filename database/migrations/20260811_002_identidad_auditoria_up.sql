CREATE TABLE IF NOT EXISTS identidad_auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_actor_id BIGINT UNSIGNED NULL,
    accion VARCHAR(120) NOT NULL,
    entidad_tipo VARCHAR(80) NOT NULL,
    entidad_id BIGINT UNSIGNED NULL,
    datos_anteriores_json JSON NULL,
    datos_nuevos_json JSON NULL,
    ip VARCHAR(80) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_identidad_auditoria_actor (usuario_actor_id),
    KEY idx_identidad_auditoria_entidad (entidad_tipo, entidad_id),
    KEY idx_identidad_auditoria_accion (accion),
    KEY idx_identidad_auditoria_created_at (created_at),
    CONSTRAINT fk_identidad_auditoria_actor
        FOREIGN KEY (usuario_actor_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
