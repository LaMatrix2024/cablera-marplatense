<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/conexion.php';

try {
    $periodoSql = "
        SELECT MAX(periodo) AS periodo
        FROM raw_produccion_planta
        WHERE periodo REGEXP '^[0-9]{6}$'
    ";

    $periodo = $pdo_laboratorio
        ->query($periodoSql)
        ->fetchColumn();

    $sql = "
        SELECT
            periodo,
            COUNT(DISTINCT c_cod_pl_cont) AS contratistas,
            COALESCE(SUM(n_totalhs_tasa), 0) AS horas,
            COALESCE(SUM(n_valor_tasa_total), 0) AS valor
        FROM raw_produccion_planta
        WHERE periodo = :periodo
    ";

    $stmt = $pdo_laboratorio->prepare($sql);
    $stmt->execute(['periodo' => $periodo]);

    $data = $stmt->fetch();

    echo json_encode([
        'ok' => true,
        'periodo' => $periodo,
        'contratistas' => (int) $data['contratistas'],
        'horas' => (float) $data['horas'],
        'valor' => (float) $data['valor'],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Error al obtener resumen de producción.'
    ], JSON_UNESCAPED_UNICODE);
}