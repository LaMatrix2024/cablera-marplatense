<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        mc_json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
    }

    $pdo = mc_pdo();
    $list = mc_get_list($pdo, mc_token_from());

    mc_json([
        'ok' => true,
        'lista' => mc_public_list($list),
    ]);
} catch (Throwable $e) {
    error_log('mis-compras lista | ' . $e->getMessage());
    mc_json(['ok' => false, 'error' => 'Error al obtener lista.'], 500);
}

