<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

try {
    $sql = "
        SELECT
            nombre_automatizacion,
            resultado_actualizacion,
            ultima_fecha_hora_actualizacion,
            ultima_fecha_hora_origen,
            registros_leidos,
            registros_insertados,
            registros_actualizados,
            ultimo_error,
            activo
        FROM automatizaciones_estado
        WHERE nombre_automatizacion = :nombre
        LIMIT 1
    ";

    $statement = $pdo_laboratorio->prepare($sql);
    $statement->execute(['nombre' => 'toolbox_cert_cto']);
    $row = $statement->fetch();

    if (!$row) {
        $fallback = $pdo_laboratorio->query("
            SELECT
                MAX(updated_at) AS ultima_fecha_hora_actualizacion,
                MAX(fecha_hora_origen) AS ultima_fecha_hora_origen
            FROM raw_toolbox_cert_cto
        ")->fetch() ?: [];

        controlLogicasResponse([
            'ok' => true,
            'source' => 'raw_toolbox_cert_cto',
            'data' => [
                'status' => 'sin_estado',
                'last_update' => $fallback['ultima_fecha_hora_actualizacion'] ?? null,
                'last_origin_data' => $fallback['ultima_fecha_hora_origen'] ?? null,
                'last_error' => null,
            ],
        ]);
    }

    controlLogicasResponse([
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
            'last_error' => $row['ultimo_error'],
            'active' => (bool) $row['activo'],
        ],
    ]);
} catch (Throwable $exception) {
    controlLogicasDatabaseError($exception);
}
