<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/conexion.php';

try {
    $periodosParam = $_GET['periodos'] ?? ($_GET['periodo'] ?? null);
    $zona = $_GET['zona'] ?? null;
    $tipo = $_GET['tipo'] ?? 'TODAS';
    $tipoContratista = $_GET['tipo_contratista'] ?? 'TODOS';
    $contratista = $_GET['contratista'] ?? null;
    $sucursalesParam = $_GET['sucursales'] ?? '';

    $periodos = array_values(array_filter(array_map('trim', explode(',', $periodosParam))));
    if (!$periodos) {
        throw new Exception('Sin períodos válidos.');
    }

    $placeholders = implode(',', array_fill(0, count($periodos), '?'));
    $where = "WHERE periodo IN ($placeholders)";
    $params = $periodos;

    $zonaExpr = "
        CASE
            WHEN c_responsable = 'AMBA' THEN 'LOMAS'
            WHEN c_responsable = 'LA PLATA' THEN 'La Plata'
            WHEN c_responsable = 'MDP' THEN 'Pcia. Bs. As. y Patag.'
            ELSE COALESCE(c_responsable, 'SIN ZONA')
        END
    ";

    $sucursalExpr = "COALESCE(c_sucursal_nombre, c_sucursal, c_lugar, 'SIN SUCURSAL')";

    if ($zona && $zona !== 'Total compañía') {
        $where .= " AND ($zonaExpr) = ? ";
        $params[] = $zona;
    }

    $sucursales = array_values(array_filter(array_map('trim', explode(',', $sucursalesParam))));
    if ($sucursales) {
        $sucPlaceholders = implode(',', array_fill(0, count($sucursales), '?'));
        $where .= " AND ($sucursalExpr) IN ($sucPlaceholders) ";
        $params = array_merge($params, $sucursales);
    }

    if ($tipoContratista && strtoupper($tipoContratista) !== 'TODOS') {
        if (strtoupper($tipoContratista) === 'CONT') {
            $where .= " AND c_tipo_contratista IN ('CONT', 'CTTA') ";
        } else {
            $where .= " AND c_tipo_contratista = ? ";
            $params[] = $tipoContratista;
        }
    }

    if ($tipo && strtoupper($tipo) !== 'TODAS') {
        if (strtoupper($tipo) === 'PTRS') {
            $where .= " AND c_cod_pl_tare_tipo LIKE '%PTR%' ";
        } elseif (strtoupper($tipo) === 'OCRAS') {
            $where .= " AND c_cod_pl_tare_tipo LIKE '%OCRA%' ";
        } elseif (strtoupper($tipo) === 'MAREA') {
            $where .= " AND c_cod_pl_tare_tipo LIKE '%MAREA%' ";
        }
    }

    if ($contratista) {
        $where .= " AND c_nom_pl_cont LIKE ? ";
        $params[] = '%' . $contratista . '%';
    }

    $sql = "
        SELECT
            COALESCE(c_nom_pl_cont, 'SIN CONTRATISTA') AS contratista,
            SUM(CASE WHEN c_cod_pl_tare_tipo LIKE '%PTR%' THEN n_totalhs_tasa ELSE 0 END) AS hb_ptrs,
            SUM(CASE WHEN c_cod_pl_tare_tipo LIKE '%OCRA%' THEN n_totalhs_tasa ELSE 0 END) AS hb_ocras,
            SUM(n_totalhs_tasa) AS total_hb,
            SUM(n_valor_tasa_total) AS venta
        FROM raw_produccion_planta
        $where
        GROUP BY contratista
        ORDER BY total_hb DESC, contratista
    ";

    $stmt = $pdo_laboratorio->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $totalHb = array_sum(array_column($rows, 'total_hb'));

    foreach ($rows as &$r) {
        $r['hb_ptrs'] = (float)$r['hb_ptrs'];
        $r['hb_ocras'] = (float)$r['hb_ocras'];
        $r['total_hb'] = (float)$r['total_hb'];
        $r['venta'] = (float)$r['venta'];
        $r['share'] = $totalHb > 0 ? round(($r['total_hb'] / $totalHb) * 100, 2) : 0;
    }

    echo json_encode([
        'ok' => true,
        'rows' => $rows
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error al obtener contratistas.',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}