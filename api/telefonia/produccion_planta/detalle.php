<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/conexion.php';

try {
    $periodo = $_GET['periodo'] ?? null;
    $zona = $_GET['zona'] ?? null;

    if (!$periodo) {
        $periodo = $pdo_laboratorio
            ->query("SELECT MAX(periodo) FROM raw_produccion_planta WHERE periodo REGEXP '^[0-9]{6}$'")
            ->fetchColumn();
    }

    $whereZona = '';

    if ($zona) {
        $whereZona = "
            AND (
                CASE
                    WHEN c_responsable = 'AMBA' THEN 'LOMAS'
                    WHEN c_responsable = 'LA PLATA' THEN 'La Plata'
                    WHEN c_responsable = 'MDP' THEN 'Pcia. Bs. As. y Patag.'
                    ELSE COALESCE(c_responsable, 'SIN ZONA')
                END
            ) = :zona
        ";
    }

    $sql = "
        SELECT
            c_responsable AS responsable,
            CASE
                WHEN c_responsable = 'AMBA' THEN 'LOMAS'
                WHEN c_responsable = 'LA PLATA' THEN 'La Plata'
                WHEN c_responsable = 'MDP' THEN 'Pcia. Bs. As. y Patag.'
                ELSE COALESCE(c_responsable, 'SIN ZONA')
            END AS zona,
            COALESCE(c_sucursal_nombre, c_sucursal, c_lugar, 'SIN SUCURSAL') AS sucursal,
            CASE
                WHEN c_cod_pl_tare_tipo LIKE '%OCRA%' THEN 'OCRAS'
                WHEN c_cod_pl_tare_tipo LIKE '%PTR%' THEN 'PTRS'
                WHEN c_cod_pl_tare_tipo LIKE '%MAREA%' THEN 'MAREA'
                ELSE COALESCE(c_cod_pl_tare_tipo, 'SIN TIPO')
            END AS tipo,
            COALESCE(SUM(n_totalhs_tasa), 0) AS horas,
            COALESCE(SUM(n_valor_tasa_total), 0) AS valor
        FROM raw_produccion_planta
        WHERE periodo = :periodo
        $whereZona
        GROUP BY responsable, zona, sucursal, tipo
        ORDER BY zona, sucursal, tipo
    ";

    $stmt = $pdo_laboratorio->prepare($sql);
    $stmt->bindValue(':periodo', $periodo);

    if ($zona) {
        $stmt->bindValue(':zona', $zona);
    }

    $stmt->execute();

    echo json_encode([
        'ok' => true,
        'periodo' => $periodo,
        'zona' => $zona,
        'rows' => $stmt->fetchAll()
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Error al obtener detalle.',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}