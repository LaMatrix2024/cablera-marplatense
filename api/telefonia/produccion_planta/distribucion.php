<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/conexion.php';

try {
    $periodosParam = $_GET['periodos'] ?? ($_GET['periodo'] ?? null);
    $zona = $_GET['zona'] ?? 'Total compañía';
    $tipo = strtoupper(trim($_GET['tipo'] ?? 'TODAS'));
    $tipoContratista = strtoupper(trim($_GET['tipo_contratista'] ?? 'TODOS'));
    $contratista = trim($_GET['contratista'] ?? '');

    if (!$periodosParam) {
        $periodosParam = $pdo_laboratorio
            ->query("SELECT MAX(periodo) FROM raw_produccion_planta WHERE periodo REGEXP '^[0-9]{6}$'")
            ->fetchColumn();
    }

    $periodos = array_values(array_filter(array_map('trim', explode(',', $periodosParam))));
    $placeholders = implode(',', array_fill(0, count($periodos), '?'));

    $params = $periodos;
    $whereFiltros = '';

    if ($tipoContratista !== 'TODOS') {
        if ($tipoContratista === 'CONT') {
            $whereFiltros .= " AND c_tipo_contratista IN ('CONT', 'CTTA')";
        } else {
            $whereFiltros .= " AND c_tipo_contratista = ?";
            $params[] = $tipoContratista;
        }
    }

    if ($tipo === 'PTRS') {
        $whereFiltros .= " AND c_cod_pl_tare_tipo LIKE '%PTR%'";
    } elseif ($tipo === 'OCRAS') {
        $whereFiltros .= " AND c_cod_pl_tare_tipo LIKE '%OCRA%'";
    } elseif ($tipo === 'MAREA') {
        $whereFiltros .= " AND c_cod_pl_tare_tipo LIKE '%MAREA%'";
    }

    if ($contratista !== '') {
        $whereFiltros .= " AND c_nom_pl_cont LIKE ?";
        $params[] = '%' . $contratista . '%';
    }

    $zonaExpr = "
        CASE
            WHEN c_responsable = 'AMBA' THEN 'LOMAS'
            WHEN c_responsable = 'LA PLATA' THEN 'La Plata'
            WHEN c_responsable = 'MDP' THEN 'Pcia. Bs. As. y Patag.'
            ELSE COALESCE(c_responsable, 'SIN ZONA')
        END
    ";

    if ($zona && $zona !== 'Total compañía') {
        $grupo = "COALESCE(c_sucursal_nombre, c_sucursal, c_lugar, 'SIN SUCURSAL')";
        $whereZona = "AND ($zonaExpr) = ?";
        $params[] = $zona;
    } else {
        $grupo = $zonaExpr;
        $whereZona = "";
    }

    $sql = "
        SELECT
            $grupo AS nombre,
            SUM(CASE WHEN c_cod_pl_tare_tipo LIKE '%PTR%' THEN n_totalhs_tasa ELSE 0 END) AS hb_ptrs,
            SUM(CASE WHEN c_cod_pl_tare_tipo LIKE '%OCRA%' THEN n_totalhs_tasa ELSE 0 END) AS hb_ocras,
            SUM(n_totalhs_tasa) AS total_hb,
            SUM(n_valor_tasa_total) AS venta
        FROM raw_produccion_planta
        WHERE periodo IN ($placeholders)
        $whereFiltros
        $whereZona
        GROUP BY nombre
        ORDER BY venta DESC, nombre
    ";

    $stmt = $pdo_laboratorio->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $totalVenta = array_sum(array_column($rows, 'venta'));

    foreach ($rows as &$r) {
        $r['hb_ptrs'] = (float) $r['hb_ptrs'];
        $r['hb_ocras'] = (float) $r['hb_ocras'];
        $r['total_hb'] = (float) $r['total_hb'];
        $r['venta'] = (float) $r['venta'];
        $r['share'] = $totalVenta > 0 ? round(($r['venta'] / $totalVenta) * 100, 2) : 0;
    }

    echo json_encode([
        'ok' => true,
        'periodos' => $periodos,
        'zona' => $zona,
        'total_venta' => $totalVenta,
        'rows' => $rows
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error al obtener distribución.',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
