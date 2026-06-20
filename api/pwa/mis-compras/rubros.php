<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        mc_json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
    }

    $pdo = mc_pdo();
    $list = mc_get_list($pdo, mc_token_from());

    $stmt = $pdo->prepare(
        'SELECT id, nombre, orden
         FROM pwa_compra_rubros
         WHERE lista_id = :lista_id AND activo = 1
         ORDER BY orden ASC, nombre ASC'
    );
    $stmt->execute(['lista_id' => (int)$list['id']]);

    mc_json([
        'ok' => true,
        'rubros' => array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'orden' => (int)$row['orden'],
        ], $stmt->fetchAll()),
    ]);
} catch (Throwable $e) {
    error_log('mis-compras rubros | ' . $e->getMessage());
    mc_json(['ok' => false, 'error' => 'Error al obtener rubros.'], 500);
}

