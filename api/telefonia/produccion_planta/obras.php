<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/_obras_query.php';

try {
    $detalle = obtenerDetalleObras($pdo_laboratorio, $_GET);

    echo json_encode([
        'ok' => true,
        'rows' => $detalle['rows'],
        'totales' => $detalle['totales'],
        'periodos' => $detalle['periodos'],
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Detalle de obras: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error al obtener el detalle de obras.'], JSON_UNESCAPED_UNICODE);
}
