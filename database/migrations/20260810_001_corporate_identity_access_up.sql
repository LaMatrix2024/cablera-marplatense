-- LCM / Plantel - Identidad y accesos corporativos
-- Migracion no destructiva: crea solo infraestructura inexistente.

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firebase_uid VARCHAR(128) NULL,
    email VARCHAR(190) NOT NULL,
    nombre VARCHAR(120) NOT NULL DEFAULT '',
    apellido VARCHAR(120) NOT NULL DEFAULT '',
    telefono VARCHAR(60) NULL,
    foto_url VARCHAR(500) NULL,
    cargo VARCHAR(120) NULL,
    sector VARCHAR(120) NULL,
    estado ENUM('PENDIENTE','ACTIVO','BLOQUEADO','BAJA') NOT NULL DEFAULT 'PENDIENTE',
    es_superadmin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    UNIQUE KEY uq_usuarios_firebase_uid (firebase_uid),
    KEY idx_usuarios_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aplicaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(80) NOT NULL,
    nombre VARCHAR(160) NOT NULL,
    descripcion VARCHAR(500) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aplicaciones_codigo (codigo),
    KEY idx_aplicaciones_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modulos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aplicacion_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(100) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    descripcion VARCHAR(500) NULL,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_modulos_aplicacion_codigo (aplicacion_id, codigo),
    UNIQUE KEY uq_modulos_id_aplicacion (id, aplicacion_id),
    KEY idx_modulos_aplicacion_id (aplicacion_id),
    KEY idx_modulos_codigo (codigo),
    CONSTRAINT fk_modulos_aplicacion
        FOREIGN KEY (aplicacion_id) REFERENCES aplicaciones(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aplicacion_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(100) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    descripcion VARCHAR(500) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_aplicacion_codigo (aplicacion_id, codigo),
    UNIQUE KEY uq_roles_id_aplicacion (id, aplicacion_id),
    KEY idx_roles_aplicacion_id (aplicacion_id),
    CONSTRAINT fk_roles_aplicacion
        FOREIGN KEY (aplicacion_id) REFERENCES aplicaciones(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_aplicacion (
    usuario_id BIGINT UNSIGNED NOT NULL,
    aplicacion_id BIGINT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, aplicacion_id),
    KEY idx_usuario_aplicacion_usuario_id (usuario_id),
    KEY idx_usuario_aplicacion_aplicacion_id (aplicacion_id),
    CONSTRAINT fk_usuario_aplicacion_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_usuario_aplicacion_aplicacion
        FOREIGN KEY (aplicacion_id) REFERENCES aplicaciones(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_rol (
    usuario_id BIGINT UNSIGNED NOT NULL,
    aplicacion_id BIGINT UNSIGNED NOT NULL,
    rol_id BIGINT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, aplicacion_id, rol_id),
    KEY idx_usuario_rol_usuario_id (usuario_id),
    KEY idx_usuario_rol_aplicacion_id (aplicacion_id),
    KEY idx_usuario_rol_rol_id (rol_id),
    CONSTRAINT fk_usuario_rol_usuario_aplicacion
        FOREIGN KEY (usuario_id, aplicacion_id) REFERENCES usuario_aplicacion(usuario_id, aplicacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_usuario_rol_rol_aplicacion
        FOREIGN KEY (rol_id, aplicacion_id) REFERENCES roles(id, aplicacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rol_modulo (
    rol_id BIGINT UNSIGNED NOT NULL,
    aplicacion_id BIGINT UNSIGNED NOT NULL,
    modulo_id BIGINT UNSIGNED NOT NULL,
    puede_ver TINYINT(1) NOT NULL DEFAULT 0,
    puede_crear TINYINT(1) NOT NULL DEFAULT 0,
    puede_editar TINYINT(1) NOT NULL DEFAULT 0,
    puede_eliminar TINYINT(1) NOT NULL DEFAULT 0,
    puede_exportar TINYINT(1) NOT NULL DEFAULT 0,
    puede_aprobar TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rol_id, modulo_id),
    KEY idx_rol_modulo_rol_id (rol_id),
    KEY idx_rol_modulo_modulo_id (modulo_id),
    KEY idx_rol_modulo_aplicacion_id (aplicacion_id),
    CONSTRAINT fk_rol_modulo_rol_aplicacion
        FOREIGN KEY (rol_id, aplicacion_id) REFERENCES roles(id, aplicacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rol_modulo_modulo_aplicacion
        FOREIGN KEY (modulo_id, aplicacion_id) REFERENCES modulos(id, aplicacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_modulo (
    usuario_id BIGINT UNSIGNED NOT NULL,
    aplicacion_id BIGINT UNSIGNED NOT NULL,
    modulo_id BIGINT UNSIGNED NOT NULL,
    puede_ver TINYINT(1) NULL,
    puede_crear TINYINT(1) NULL,
    puede_editar TINYINT(1) NULL,
    puede_eliminar TINYINT(1) NULL,
    puede_exportar TINYINT(1) NULL,
    puede_aprobar TINYINT(1) NULL,
    motivo VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, modulo_id),
    KEY idx_usuario_modulo_usuario_id (usuario_id),
    KEY idx_usuario_modulo_modulo_id (modulo_id),
    KEY idx_usuario_modulo_aplicacion_id (aplicacion_id),
    CONSTRAINT fk_usuario_modulo_usuario_aplicacion
        FOREIGN KEY (usuario_id, aplicacion_id) REFERENCES usuario_aplicacion(usuario_id, aplicacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_usuario_modulo_modulo_aplicacion
        FOREIGN KEY (modulo_id, aplicacion_id) REFERENCES modulos(id, aplicacion_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS identidad_conflictos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    email VARCHAR(190) NOT NULL,
    firebase_uid_existente VARCHAR(128) NULL,
    firebase_uid_recibido VARCHAR(128) NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    detalle VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_identidad_conflictos_usuario_id (usuario_id),
    KEY idx_identidad_conflictos_email (email),
    KEY idx_identidad_conflictos_tipo (tipo),
    CONSTRAINT fk_identidad_conflictos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
