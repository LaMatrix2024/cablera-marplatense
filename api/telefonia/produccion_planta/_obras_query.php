<?php

function obtenerDetalleObras(PDO $pdo, array $input): array
{
    $periodos = array_values(array_filter(array_map(
        'trim',
        explode(',', (string)($input['periodos'] ?? ''))
    )));

    $contratista = trim((string)($input['contratista'] ?? ''));
    $zona = trim((string)($input['zona'] ?? ''));
    $tipo = strtoupper(trim((string)($input['tipo'] ?? 'TODAS')));
    $tipoContratista = strtoupper(trim((string)($input['tipo_contratista'] ?? 'TODOS')));
    $sucursales = array_values(array_filter(array_map(
        'trim',
        explode(',', (string)($input['sucursales'] ?? ''))
    )));

    if (!$periodos || $contratista === '') {
        throw new InvalidArgumentException('Períodos y contratista son obligatorios.');
    }

    $placeholders = implode(',', array_fill(0, count($periodos), '?'));
    $params = $periodos;
    $where = "WHERE periodo IN ($placeholders)";

    $zonaExpr = "
        CASE
            WHEN c_responsable = 'AMBA' THEN 'LOMAS'
            WHEN c_responsable = 'LA PLATA' THEN 'La Plata'
            WHEN c_responsable = 'MDP' THEN 'Pcia. Bs. As. y Patag.'
            ELSE COALESCE(c_responsable, 'SIN ZONA')
        END
    ";
    $sucursalExpr = "COALESCE(c_sucursal_nombre, c_sucursal, c_lugar, 'SIN SUCURSAL')";

    if ($zona !== '' && $zona !== 'Total compañía') {
        $where .= " AND ($zonaExpr) = ?";
        $params[] = $zona;
    }

    if ($sucursales) {
        $sucPlaceholders = implode(',', array_fill(0, count($sucursales), '?'));
        $where .= " AND ($sucursalExpr) IN ($sucPlaceholders)";
        $params = array_merge($params, $sucursales);
    }

    if ($tipoContratista === 'CONT') {
        $where .= " AND c_tipo_contratista IN ('CONT', 'CTTA')";
    } elseif ($tipoContratista !== '' && $tipoContratista !== 'TODOS') {
        $where .= ' AND c_tipo_contratista = ?';
        $params[] = $tipoContratista;
    }

    if ($tipo === 'PTRS') {
        $where .= " AND c_cod_pl_tare_tipo LIKE '%PTR%'";
    } elseif ($tipo === 'OCRAS') {
        $where .= " AND c_cod_pl_tare_tipo LIKE '%OCRA%'";
    } elseif ($tipo === 'MAREA') {
        $where .= " AND c_cod_pl_tare_tipo LIKE '%MAREA%'";
    }

    $where .= " AND COALESCE(c_nom_pl_cont, 'SIN CONTRATISTA') = ?";
    $params[] = $contratista;

    $tipoExpr = "
        CASE
            WHEN c_cod_pl_tare_tipo LIKE '%OCRA%' THEN 'OCRAS'
            WHEN c_cod_pl_tare_tipo LIKE '%PTR%' THEN 'PTRS'
            WHEN c_cod_pl_tare_tipo LIKE '%MAREA%' THEN 'MAREA'
            ELSE COALESCE(c_cod_pl_tare_tipo, 'SIN TIPO')
        END
    ";
    $tituloBaseExpr = "COALESCE(NULLIF(TRIM(c_titulo), ''), 'SIN DESCRIPCIÓN')";
    $precioExpr = "COALESCE(precio_tasa, 0)";
    $sigestExpr = "
        CASE
            WHEN $tituloBaseExpr REGEXP '^[0-9]{10}' THEN LEFT($tituloBaseExpr, 10)
            ELSE 'SIN SIGEST'
        END
    ";
    $descripcionExpr = "
        CASE
            WHEN $tituloBaseExpr REGEXP '^[0-9]{10}' THEN TRIM(SUBSTRING($tituloBaseExpr, 11))
            ELSE $tituloBaseExpr
        END
    ";

    $sql = "
        SELECT
            $sigestExpr AS sigest,
            $sucursalExpr AS sucursal,
            $descripcionExpr AS descripcion,
            $tipoExpr AS tipo,
            COALESCE(SUM(n_l_tasa), 0) AS nl,
            COALESCE(SUM(n_n_tasa), 0) AS nn,
            COALESCE(SUM(n_lz_tasa), 0) AS nlz,
            COALESCE(SUM(n_lc_tasa), 0) AS nlc,
            COALESCE(SUM(n_totalhs_tasa), 0) AS total_hb,
            $precioExpr AS precio,
            COALESCE(SUM(n_valor_tasa_total), 0) AS valor_venta
        FROM raw_produccion_planta
        $where
        GROUP BY sigest, sucursal, descripcion, tipo, precio
        ORDER BY sigest, sucursal, descripcion
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $totales = [
        'nl' => 0.0,
        'nn' => 0.0,
        'nlz' => 0.0,
        'nlc' => 0.0,
        'total_hb' => 0.0,
        'valor_venta' => 0.0,
    ];
    foreach ($rows as &$row) {
        foreach (array_keys($totales) as $campo) {
            $row[$campo] = (float)$row[$campo];
            $totales[$campo] += $row[$campo];
        }
        $row['precio'] = (float)$row['precio'];
    }
    unset($row);

    return [
        'rows' => $rows,
        'totales' => $totales,
        'periodos' => $periodos,
        'contratista' => $contratista,
        'zona' => $zona,
        'tipo' => $tipo,
        'tipo_contratista' => $tipoContratista,
    ];
}
