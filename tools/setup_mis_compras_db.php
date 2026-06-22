<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/pwa/mis-compras/bootstrap.php';

$pdo = mc_pdo();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS pwa_compra_listas (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(120) NOT NULL UNIQUE,
        nombre VARCHAR(160) NOT NULL,
        activa TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS pwa_compra_rubros (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lista_id BIGINT UNSIGNED NOT NULL,
        nombre VARCHAR(80) NOT NULL,
        orden INT UNSIGNED NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_pwa_compra_rubros_lista (lista_id),
        CONSTRAINT fk_pwa_compra_rubros_lista
            FOREIGN KEY (lista_id) REFERENCES pwa_compra_listas(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS pwa_compra_estados (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(40) NOT NULL UNIQUE,
        nombre VARCHAR(80) NOT NULL,
        descripcion VARCHAR(255) NULL,
        orden INT UNSIGNED NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS pwa_compra_items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lista_id BIGINT UNSIGNED NOT NULL,
        rubro_id BIGINT UNSIGNED NULL,
        estado_id BIGINT UNSIGNED NOT NULL,
        producto VARCHAR(160) NOT NULL,
        cantidad VARCHAR(60) NULL,
        usuario VARCHAR(40) NULL,
        creado_por VARCHAR(40) NULL,
        actualizado_por VARCHAR(40) NULL,
        comprado_por VARCHAR(40) NULL,
        cancelado_por VARCHAR(40) NULL,
        excluido_por VARCHAR(40) NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        comprado_at TIMESTAMP NULL,
        cancelado_at TIMESTAMP NULL,
        excluido_at TIMESTAMP NULL,
        INDEX idx_pwa_compra_items_lista_estado (lista_id, estado_id),
        INDEX idx_pwa_compra_items_rubro (rubro_id),
        INDEX idx_pwa_compra_items_updated (updated_at),
        CONSTRAINT fk_pwa_compra_items_lista
            FOREIGN KEY (lista_id) REFERENCES pwa_compra_listas(id),
        CONSTRAINT fk_pwa_compra_items_rubro
            FOREIGN KEY (rubro_id) REFERENCES pwa_compra_rubros(id),
        CONSTRAINT fk_pwa_compra_items_estado
            FOREIGN KEY (estado_id) REFERENCES pwa_compra_estados(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->prepare("
    INSERT INTO pwa_compra_listas (token, nombre, activa)
    VALUES ('claudio-faby', 'Lista Claudio y Faby', 1)
    ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activa = VALUES(activa)
")->execute();

$listaId = (int)$pdo->query("SELECT id FROM pwa_compra_listas WHERE token = 'claudio-faby' LIMIT 1")->fetchColumn();

$estados = [
    ['PENDIENTE', 'Pendiente', 'Producto pendiente de compra.', 10],
    ['COMPRADO', 'Comprado', 'Producto adquirido.', 20],
    ['CANCELADO', 'Cancelado', 'Producto que ya no es necesario comprar.', 30],
    ['EXCLUIDO', 'Excluido', 'Error de carga o registro incorrecto.', 40],
];

$stmtEstado = $pdo->prepare("
    INSERT INTO pwa_compra_estados (codigo, nombre, descripcion, orden, activo)
    VALUES (:codigo, :nombre, :descripcion, :orden, 1)
    ON DUPLICATE KEY UPDATE
        nombre = VALUES(nombre),
        descripcion = VALUES(descripcion),
        orden = VALUES(orden),
        activo = VALUES(activo)
");

foreach ($estados as [$codigo, $nombre, $descripcion, $orden]) {
    $stmtEstado->execute([
        'codigo' => $codigo,
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'orden' => $orden,
    ]);
}

$rubros = [
    ['Almacen', 10],
    ['Carnicería', 20],
    ['Verduleria', 30],
    ['Limpieza', 40],
    ['Farmacia', 50],
    ['Bebidas', 60],
    ['Otros', 70],
];

$stmtFixRubro = $pdo->prepare("
    UPDATE pwa_compra_rubros
    SET nombre = 'Carnicería', orden = 20, activo = 1
    WHERE lista_id = :lista_id AND nombre = 'Carniceria'
");
$stmtFixRubro->execute(['lista_id' => $listaId]);

$stmtRubro = $pdo->prepare("
    INSERT INTO pwa_compra_rubros (lista_id, nombre, orden, activo)
    SELECT :lista_id, :nombre, :orden, 1
    WHERE NOT EXISTS (
        SELECT 1 FROM pwa_compra_rubros
        WHERE lista_id = :lista_id_check AND nombre = :nombre_check
    )
");

foreach ($rubros as [$nombre, $orden]) {
    $stmtRubro->execute([
        'lista_id' => $listaId,
        'nombre' => $nombre,
        'orden' => $orden,
        'lista_id_check' => $listaId,
        'nombre_check' => $nombre,
    ]);
}

echo "OK: tablas y semillas de Mis Compras listas en u767019378_laboratorio" . PHP_EOL;
