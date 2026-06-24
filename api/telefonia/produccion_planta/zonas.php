<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/conexion.php';

try {
    $periodosParam = $_GET['periodos'] ?? ($_GET['periodo'] ?? null);
    $tipo = strtoupper(trim($_GET['tipo'] ?? 'TODAS'));
    $tipoContratista = strtoupper(trim($_GET['tipo_contratista'] ?? 'TODOS'));
    $contratista = trim($_GET['contratista'] ?? '');

    if (!$periodosParam) {
        $periodosParam = $pdo_laboratorio
            ->query("SELECT MAX(periodo) FROM raw_produccion_planta WHERE periodo REGEXP '^[0-9]{6}$'")
            ->fetchColumn();
    }

    $periodos = array_values(array_filter(array_map('trim', explode(',', $periodosParam))));

    if (!$periodos) {
        throw new Exception('Sin períodos válidos.');
    }

    $placeholders = implode(',', array_fill(0, count($periodos), '?'));
    $where = "WHERE periodo IN ($placeholders)";
    $params = $periodos;

    if ($tipoContratista !== 'TODOS') {
        if ($tipoContratista === 'CONT') {
            $where .= " AND c_tipo_contratista IN ('CONT', 'CTTA')";
        } else {
            $where .= " AND c_tipo_contratista = ?";
            $params[] = $tipoContratista;
        }
    }

    if ($tipo === 'PTRS') {
        $where .= " AND c_cod_pl_tare_tipo LIKE '%PTR%'";
    } elseif ($tipo === 'OCRAS') {
        $where .= " AND c_cod_pl_tare_tipo LIKE '%OCRA%'";
    } elseif ($tipo === 'MAREA') {
        $where .= " AND c_cod_pl_tare_tipo LIKE '%MAREA%'";
    }

    if ($contratista !== '') {
        $where .= " AND c_nom_pl_cont LIKE ?";
        $params[] = '%' . $contratista . '%';
    }

    $sql = "
        SELECT
            CASE
                WHEN c_responsable = 'AMBA' THEN 'LOMAS'
                WHEN c_responsable = 'LA PLATA' THEN 'La Plata'
                WHEN c_responsable = 'MDP' THEN 'Pcia. Bs. As. y Patag.'
                ELSE COALESCE(c_responsable, 'SIN ZONA')
            END AS zona,
            SUM(CASE WHEN c_cod_pl_tare_tipo LIKE '%PTR%' THEN n_totalhs_tasa ELSE 0 END) AS hb_ptrs,
            SUM(CASE WHEN c_cod_pl_tare_tipo LIKE '%OCRA%' THEN n_totalhs_tasa ELSE 0 END) AS hb_ocras,
            SUM(n_totalhs_tasa) AS total_hb,
            SUM(n_valor_tasa_total) AS venta
        FROM raw_produccion_planta
        $where
        GROUP BY zona
        ORDER BY zona
    ";

    $stmt = $pdo_laboratorio->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $totalVenta = array_sum(array_column($rows, 'venta'));
    $totalHb = array_sum(array_column($rows, 'total_hb'));

    foreach ($rows as &$r) {
        $r['hb_ptrs'] = (float)$r['hb_ptrs'];
        $r['hb_ocras'] = (float)$r['hb_ocras'];
        $r['total_hb'] = (float)$r['total_hb'];
        $r['venta'] = (float)$r['venta'];
        $r['share'] = $totalVenta > 0 ? round(($r['venta'] / $totalVenta) * 100, 2) : 0;
    }

    echo json_encode([
        'ok' => true,
        'periodos' => $periodos,
        'total' => [
            'zona' => 'Total compañía',
            'hb_ptrs' => array_sum(array_column($rows, 'hb_ptrs')),
            'hb_ocras' => array_sum(array_column($rows, 'hb_ocras')),
            'total_hb' => $totalHb,
            'venta' => $totalVenta,
            'share' => 100
        ],
        'zonas' => $rows
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error al obtener zonas.',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
