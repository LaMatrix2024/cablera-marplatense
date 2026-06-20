<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        mc_json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
    }

    $pdo = mc_pdo();
    mc_get_list($pdo, mc_token_from());

    $rows = $pdo
        ->query('SELECT codigo, nombre, descripcion, orden FROM pwa_compra_estados WHERE activo = 1 ORDER BY orden ASC')
        ->fetchAll();

    mc_json([
        'ok' => true,
        'estados' => $rows,
    ]);
} catch (Throwable $e) {
    error_log('mis-compras estados | ' . $e->getMessage());
    mc_json(['ok' => false, 'error' => 'Error al obtener estados.'], 500);
}

