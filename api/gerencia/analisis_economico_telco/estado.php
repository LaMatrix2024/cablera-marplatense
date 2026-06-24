<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/conexion.php';

try {
    $sql = "
        SELECT
            id,
            archivo_nombre,
            fecha_importacion,
            estado,
            filas_leidas,
            filas_insertadas,
            filas_error,
            observaciones
        FROM informe_economico_telco_importaciones
        ORDER BY fecha_importacion DESC, id DESC
        LIMIT 1
    ";

    $ultimaImportacion = $pdo_laboratorio->query($sql)->fetch();

    echo json_encode([
        'ok' => true,
        'ultima_importacion' => $ultimaImportacion ?: null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Estado Informe Económico TELCO: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'No se pudo consultar el estado de la importación.',
    ], JSON_UNESCAPED_UNICODE);
}
