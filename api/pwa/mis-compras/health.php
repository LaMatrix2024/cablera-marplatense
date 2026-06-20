<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        mc_json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
    }

    $pdo = mc_pdo();
    $pdo->query('SELECT 1');

    mc_json([
        'ok' => true,
        'service' => 'mis-compras',
        'database' => 'u767019378_laboratorio',
    ]);
} catch (Throwable $e) {
    error_log('mis-compras health | ' . $e->getMessage());
    mc_json(['ok' => false, 'error' => 'Servicio no disponible.'], 500);
}

