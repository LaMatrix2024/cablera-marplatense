<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

try {
    $statement = $pdo_laboratorio->prepare("
        SELECT
            nombre_automatizacion,
            nombre_tabla,
            sistema_origen,
            resultado_actualizacion,
            ultima_fecha_hora_actualizacion,
            ultima_fecha_hora_origen,
            registros_leidos,
            registros_insertados,
            registros_actualizados,
            registros_omitidos,
            ultimo_error,
            updated_at
        FROM automatizaciones_estado
        WHERE nombre_automatizacion = :nombre
        LIMIT 1
    ");
    $statement->execute(['nombre' => 'toolbox_desmonte']);
    $row = $statement->fetch() ?: null;

    if (! $row) {
        $fallback = $pdo_laboratorio->query("
            SELECT
                MAX(imported_at) AS ultima_fecha_hora_actualizacion,
                MAX(fecha_alta_terreno) AS ultima_fecha_hora_origen,
                COUNT(*) AS registros
            FROM raw_toolbox_tracking_tirones
        ")->fetch() ?: [];

        trackingTironesResponse([
            'ok' => true,
            'source' => 'raw_toolbox_tracking_tirones',
            'data' => [
                'automation' => 'toolbox_desmonte',
                'status' => 'sin_estado',
                'last_update' => $fallback['ultima_fecha_hora_actualizacion'] ?? null,
                'last_origin_data' => $fallback['ultima_fecha_hora_origen'] ?? null,
                'records_read' => (int) ($fallback['registros'] ?? 0),
                'records_inserted' => (int) ($fallback['registros'] ?? 0),
                'records_updated' => 0,
                'records_omitted' => 0,
                'last_error' => null,
                'active' => true,
            ],
        ]);
    }

    trackingTironesResponse([
        'ok' => true,
        'source' => 'automatizaciones_estado',
        'data' => [
            'automation' => $row['nombre_automatizacion'],
            'status' => strtolower((string) $row['resultado_actualizacion']),
            'last_update' => $row['ultima_fecha_hora_actualizacion'],
            'last_origin_data' => $row['ultima_fecha_hora_origen'],
            'records_read' => (int) $row['registros_leidos'],
            'records_inserted' => (int) $row['registros_insertados'],
            'records_updated' => (int) $row['registros_actualizados'],
            'records_omitted' => (int) $row['registros_omitidos'],
            'last_error' => $row['ultimo_error'],
            'active' => (bool) $row['updated_at'],
        ],
    ]);
} catch (Throwable $exception) {
    trackingTironesDatabaseError($exception);
}

