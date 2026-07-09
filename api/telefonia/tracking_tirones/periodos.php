<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

try {
    $rows = $pdo_laboratorio->query("
        SELECT DISTINCT DATE_FORMAT(fecha_alta_terreno, '%Y-%m') AS periodo
        FROM raw_toolbox_tracking_tirones
        WHERE fecha_alta_terreno IS NOT NULL
        ORDER BY periodo DESC
    ")->fetchAll(PDO::FETCH_COLUMN);

    trackingTironesResponse([
        'ok' => true,
        'data' => [
            'periodos' => array_values(array_filter(array_map('strval', $rows))),
        ],
    ]);
} catch (Throwable $exception) {
    trackingTironesDatabaseError($exception);
}
