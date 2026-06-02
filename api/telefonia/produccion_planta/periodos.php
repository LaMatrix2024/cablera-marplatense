<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/conexion.php';

try {
    $sql = "
        SELECT DISTINCT periodo
        FROM raw_produccion_planta
        WHERE periodo REGEXP '^[0-9]{6}$'
        ORDER BY periodo DESC
    ";

    echo json_encode([
        'ok' => true,
        'periodos' => $pdo_laboratorio->query($sql)->fetchAll(PDO::FETCH_COLUMN)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al obtener periodos.']);
}