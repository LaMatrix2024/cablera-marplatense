<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

try {
    $today = new DateTimeImmutable('today');
    $tomorrow = $today->modify('+1 day');

    $sql = "
        SELECT
            COALESCE(SUM(tipo_origen = 'HUB' AND resultado = 'OK'), 0) AS hub_ok,
            COALESCE(SUM(tipo_origen = 'HUB' AND resultado = 'ERROR'), 0) AS hub_no_ok,
            COALESCE(SUM(tipo_origen = 'SPLITTER' AND resultado = 'OK'), 0) AS cto_ok,
            COALESCE(SUM(tipo_origen = 'SPLITTER' AND resultado = 'ERROR'), 0) AS cto_no_ok
        FROM (
            SELECT
                resultado,
                JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tipo_cto')) AS tipo_origen
            FROM raw_toolbox_cert_cto
            WHERE fecha_hora_origen >= :desde
              AND fecha_hora_origen < :hasta
        ) AS pruebas_hoy
    ";

    $statement = $pdo_laboratorio->prepare($sql);
    $statement->execute([
        'desde' => $today->format('Y-m-d H:i:s'),
        'hasta' => $tomorrow->format('Y-m-d H:i:s'),
    ]);
    $row = $statement->fetch() ?: [];

    controlLogicasResponse([
        'ok' => true,
        'date' => $today->format('Y-m-d'),
        'data' => [
            'hub_ok' => (int) ($row['hub_ok'] ?? 0),
            'hub_no_ok' => (int) ($row['hub_no_ok'] ?? 0),
            'cto_ok' => (int) ($row['cto_ok'] ?? 0),
            'cto_no_ok' => (int) ($row['cto_no_ok'] ?? 0),
        ],
    ]);
} catch (Throwable $exception) {
    controlLogicasDatabaseError($exception);
}
