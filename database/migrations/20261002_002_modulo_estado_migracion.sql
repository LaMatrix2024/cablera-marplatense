CREATE TABLE modulo_migraciones (
    modulo_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    estado_migracion VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    app_id_canonico VARCHAR(100) NOT NULL,
    identificador_origen VARCHAR(180) NOT NULL,
    ruta_frontend VARCHAR(300) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_modulo_migraciones_modulo FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_modulo_migraciones_app (app_id_canonico)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
