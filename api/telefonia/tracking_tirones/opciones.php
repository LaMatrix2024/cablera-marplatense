<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

try {
    $centrales = $pdo_laboratorio->query("
        SELECT DISTINCT COALESCE(NULLIF(TRIM(central), ''), 'SIN CENTRAL') AS valor
        FROM raw_toolbox_tracking_tirones
        WHERE central IS NOT NULL AND TRIM(central) <> ''
        ORDER BY valor ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $estados = $pdo_laboratorio->query("
        SELECT DISTINCT COALESCE(NULLIF(TRIM(estado), ''), 'SIN ESTADO') AS valor
        FROM raw_toolbox_tracking_tirones
        WHERE estado IS NOT NULL AND TRIM(estado) <> ''
        ORDER BY valor ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $tipos = $pdo_laboratorio->query("
        SELECT DISTINCT COALESCE(NULLIF(TRIM(tipo), ''), 'SIN TIPO') AS valor
        FROM raw_toolbox_tracking_tirones
        WHERE tipo IS NOT NULL AND TRIM(tipo) <> ''
        ORDER BY valor ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    trackingTironesResponse([
        'ok' => true,
        'data' => [
            'centrales' => $centrales,
            'estados' => $estados,
            'tipos' => $tipos,
        ],
    ]);
} catch (Throwable $exception) {
    trackingTironesDatabaseError($exception);
}

