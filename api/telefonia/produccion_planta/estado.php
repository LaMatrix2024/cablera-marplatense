<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/conexion.php';

try {
    $sql = "
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
        WHERE nombre_automatizacion = 'bigstorm_produccion_planta'
        LIMIT 1
    ";

    $row = $pdo_laboratorio->query($sql)->fetch();

    echo json_encode([
        'ok' => true,
        'estado' => $row
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Error al obtener estado.',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}