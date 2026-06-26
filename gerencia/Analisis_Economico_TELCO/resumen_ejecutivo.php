<?php
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../shared/layout.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

function q(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function periodoExpr(string $campo): string
{
    return "CASE
        WHEN {$campo} IS NULL THEN NULL
        WHEN CHAR_LENGTH(CAST({$campo} AS CHAR)) = 5 THEN CONCAT(LEFT(CAST({$campo} AS CHAR), 4), '-0', RIGHT(CAST({$campo} AS CHAR), 1))
        WHEN CHAR_LENGTH(CAST({$campo} AS CHAR)) = 6 THEN CONCAT(LEFT(CAST({$campo} AS CHAR), 4), '-', RIGHT(CAST({$campo} AS CHAR), 2))
        ELSE NULL
    END";
}

function fmtPesos(?float $value): string
{
    if ($value === null) {
        return '$0';
    }
    $sign = $value < 0 ? '-' : '';
    return $sign . '$' . number_format(abs($value), 0, ',', '.');
}

function fmtPct(?float $value): string
{
    return number_format((float) ($value ?? 0), 2, ',', '.') . '%';
}

function normalizePeriodoParam(string $value): ?string
{
    $value = trim($value);
    if (!preg_match('/^(\d{4})-?(\d{1,2})$/', $value, $m)) {
        return null;
    }
    return $m[1] . '-' . str_pad((string) (int) $m[2], 2, '0', STR_PAD_LEFT);
}

function selectedPeriodLabel(array $selected): string
{
    $selected = array_values(array_unique($selected));
    return !$selected ? 'Sin período' : (count($selected) === 1 ? $selected[0] : (count($selected) <= 3 ? implode(' · ', $selected) : count($selected) . ' períodos'));
}

function classifyGroup(string $grupo): string
{
    $g = mb_strtolower(trim($grupo), 'UTF-8');
    if (str_contains($g, 'ventas')) {
        return 'ingresos';
    }
    if (str_contains($g, 'gastos financieros')) {
        return 'financieros';
    }
    return 'operativos';
}

function normalizeGroupKey(string $grupo): string
{
    $grupo = mb_strtolower(trim($grupo), 'UTF-8');
    $grupo = preg_replace('/\s+/', ' ', $grupo) ?: 'sin grupo';
    return $grupo;
}

function domSafeId(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $value) ?: 'item';
    return 'id_' . trim($value, '_');
}

function normalizeLineKey(string $grupo, string $codigo, string $nombre): string
{
    $parts = [$grupo, $codigo, $nombre];
    $parts = array_map(static function ($value) {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        return $value;
    }, $parts);
    return implode('|', $parts);
}

function normalizeOptionLabel(?string $value): string
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/', ' ', $value) ?: '';
    return mb_strtolower($value, 'UTF-8');
}

function isExcludedOption(?string $value): bool
{
    $normalized = normalizeOptionLabel($value);
    return in_array($normalized, ['otros', 'otras'], true);
}

function rowClass(float $value): string
{
    return $value > 0 ? 'row-positive' : ($value < 0 ? 'row-negative' : 'row-neutral');
}

function shareClass(float $value): string
{
    return $value > 0 ? 'share-positive' : ($value < 0 ? 'share-negative' : 'share-neutral');
}

$baseWhere = "categoria = '1-TELEFONIA' AND sub_categoria = '1-TASA'";
$periodExpr = periodoExpr('periodo');
$negocioExpr = "COALESCE(NULLIF(TRIM(negocio), ''), 'SIN NEGOCIO')";

$periodosDisponibles = q($pdo_laboratorio, "
    SELECT DISTINCT {$periodExpr} AS periodo_norm
    FROM raw_economico_provisorio
    WHERE {$baseWhere} AND {$periodExpr} IS NOT NULL
    ORDER BY periodo_norm DESC
");
$periodosDisponibles = array_values(array_filter(array_map(static fn ($r) => $r['periodo_norm'] ?? null, $periodosDisponibles)));

$negociosDisponibles = q($pdo_laboratorio, "
    SELECT DISTINCT {$negocioExpr} AS negocio_norm
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    ORDER BY negocio_norm
");
$negociosDisponibles = array_values(array_filter(array_map(static fn ($r) => $r['negocio_norm'] ?? null, $negociosDisponibles), static fn ($value) => !isExcludedOption($value)));

$sucursalExpr = "COALESCE(NULLIF(TRIM(sucursal), ''), 'SIN SUCURSAL')";
$sucursalesDisponibles = q($pdo_laboratorio, "
    SELECT DISTINCT {$sucursalExpr} AS sucursal_norm
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    ORDER BY sucursal_norm
");
$sucursalesDisponibles = array_values(array_filter(array_map(static fn ($r) => $r['sucursal_norm'] ?? null, $sucursalesDisponibles), static fn ($value) => !isExcludedOption($value)));

$seleccion = [];
if (!empty($_GET['periodos'])) {
    foreach (array_filter(array_map('trim', explode(',', (string) $_GET['periodos']))) as $p) {
        $n = normalizePeriodoParam($p);
        if ($n !== null) {
            $seleccion[] = $n;
        }
    }
} elseif (!empty($_GET['periodo'])) {
    $n = normalizePeriodoParam((string) $_GET['periodo']);
    if ($n !== null) {
        $seleccion[] = $n;
    }
}

$seleccion = array_values(array_unique(array_intersect($seleccion, $periodosDisponibles)));
if (!$seleccion && $periodosDisponibles) {
    $seleccion = [$periodosDisponibles[0]];
}

$negocioSeleccionado = null;
if (isset($_GET['negocio'])) {
    $n = trim((string) $_GET['negocio']);
    if ($n !== '' && in_array($n, $negociosDisponibles, true) && !isExcludedOption($n)) {
        $negocioSeleccionado = $n;
    }
}

$sucursalSeleccionada = null;
if (isset($_GET['sucursal'])) {
    $s = trim((string) $_GET['sucursal']);
    if ($s !== '' && in_array($s, $sucursalesDisponibles, true) && !isExcludedOption($s)) {
        $sucursalSeleccionada = $s;
    }
}

$params = [];
$periodFilterSql = '';
if ($seleccion) {
    $placeholders = [];
    foreach ($seleccion as $i => $periodoSel) {
        $key = ':p' . $i;
        $placeholders[] = $key;
        $params[$key] = $periodoSel;
    }
    $periodFilterSql = ' AND ' . $periodExpr . ' IN (' . implode(',', $placeholders) . ') ';
}

$negocioFilterSql = '';
if ($negocioSeleccionado !== null) {
    $negocioFilterSql = ' AND ' . $negocioExpr . ' = :negocio ';
    $params[':negocio'] = $negocioSeleccionado;
}

$sucursalFilterSql = '';
if ($sucursalSeleccionada !== null) {
    $sucursalFilterSql = ' AND ' . $sucursalExpr . ' = :sucursal ';
    $params[':sucursal'] = $sucursalSeleccionada;
}

$resumen = q($pdo_laboratorio, "
    SELECT
        MIN(fecha) AS fecha_min,
        MAX(fecha) AS fecha_max,
        MIN({$periodExpr}) AS periodo_min,
        MAX({$periodExpr}) AS periodo_max,
        SUM(total) AS total_original,
        SUM(ars_ajustados) AS total_ajustado
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    {$periodFilterSql}
    {$negocioFilterSql}
    {$sucursalFilterSql}
", $params)[0] ?? [];

$detalleLineas = q($pdo_laboratorio, "
    SELECT
        COALESCE(NULLIF(TRIM(grupo_cuenta), ''), 'SIN GRUPO') AS grupo_cuenta,
        COALESCE(NULLIF(TRIM(codigo_cuenta), ''), '—') AS codigo_cuenta,
        COALESCE(NULLIF(TRIM(nombre_cuenta), ''), 'SIN NOMBRE') AS nombre_cuenta,
        COUNT(*) AS filas_detalle,
        COUNT(DISTINCT COALESCE(NULLIF(TRIM(detalle), ''), 'SIN DETALLE')) AS detalle_count,
        COUNT(DISTINCT COALESCE(NULLIF(TRIM(cnompvprov), ''), 'SIN PROV')) AS proveedor_count,
        COUNT(DISTINCT COALESCE(NULLIF(TRIM(ot), ''), 'SIN OT')) AS ot_count,
        SUM(ars_ajustados) AS total_ajustado
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    {$periodFilterSql}
    {$negocioFilterSql}
    {$sucursalFilterSql}
      AND NOT (LOWER(TRIM(COALESCE(NULLIF(TRIM(grupo_cuenta), ''), ''))) IN ('otros', 'otras'))
    GROUP BY
        COALESCE(NULLIF(TRIM(grupo_cuenta), ''), 'SIN GRUPO'),
        COALESCE(NULLIF(TRIM(codigo_cuenta), ''), '—'),
        COALESCE(NULLIF(TRIM(nombre_cuenta), ''), 'SIN NOMBRE')
    ORDER BY COALESCE(NULLIF(TRIM(grupo_cuenta), ''), 'SIN GRUPO'), ABS(SUM(ars_ajustados)) DESC
", $params);

$detalleProfundo = q($pdo_laboratorio, "
    SELECT
        COALESCE(NULLIF(TRIM(grupo_cuenta), ''), 'SIN GRUPO') AS grupo_cuenta,
        COALESCE(NULLIF(TRIM(codigo_cuenta), ''), '—') AS codigo_cuenta,
        COALESCE(NULLIF(TRIM(nombre_cuenta), ''), 'SIN NOMBRE') AS nombre_cuenta,
        COALESCE(NULLIF(TRIM(detalle), ''), 'SIN DETALLE') AS detalle,
        COALESCE(NULLIF(TRIM(cnompvprov), ''), 'SIN PROVEEDOR') AS proveedor,
        COALESCE(NULLIF(TRIM(ot), ''), 'SIN OT') AS ot,
        COALESCE(NULLIF(TRIM(ot_agrupada), ''), 'SIN OT AGRUPADA') AS ot_agrupada,
        COALESCE(NULLIF(TRIM(ot_nombre), ''), 'SIN OT NOMBRE') AS ot_nombre,
        SUM(total) AS total_original,
        SUM(ars_ajustados) AS total_ajustado,
        COUNT(*) AS filas
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    {$periodFilterSql}
    {$negocioFilterSql}
    {$sucursalFilterSql}
      AND NOT (LOWER(TRIM(COALESCE(NULLIF(TRIM(grupo_cuenta), ''), ''))) IN ('otros', 'otras'))
    GROUP BY
        COALESCE(NULLIF(TRIM(grupo_cuenta), ''), 'SIN GRUPO'),
        COALESCE(NULLIF(TRIM(codigo_cuenta), ''), '—'),
        COALESCE(NULLIF(TRIM(nombre_cuenta), ''), 'SIN NOMBRE'),
        COALESCE(NULLIF(TRIM(detalle), ''), 'SIN DETALLE'),
        COALESCE(NULLIF(TRIM(cnompvprov), ''), 'SIN PROVEEDOR'),
        COALESCE(NULLIF(TRIM(ot), ''), 'SIN OT'),
        COALESCE(NULLIF(TRIM(ot_agrupada), ''), 'SIN OT AGRUPADA'),
        COALESCE(NULLIF(TRIM(ot_nombre), ''), 'SIN OT NOMBRE')
    ORDER BY COALESCE(NULLIF(TRIM(codigo_cuenta), ''), '—'), ABS(SUM(ars_ajustados)) DESC, detalle
", $params);

$matrizSucursal = q($pdo_laboratorio, "
    SELECT
        COALESCE(NULLIF(TRIM(grupo_cuenta), ''), 'SIN GRUPO') AS grupo_cuenta,
        COALESCE(NULLIF(TRIM(sucursal), ''), 'SIN SUCURSAL') AS sucursal_norm,
        SUM(ars_ajustados) AS total_ajustado
    FROM raw_economico_provisorio
    WHERE {$baseWhere}
    {$periodFilterSql}
    {$negocioFilterSql}
    {$sucursalFilterSql}
      AND NOT (LOWER(TRIM(COALESCE(NULLIF(TRIM(grupo_cuenta), ''), ''))) IN ('otros', 'otras'))
      AND NOT (LOWER(TRIM(COALESCE(NULLIF(TRIM(sucursal), ''), ''))) IN ('otros', 'otras'))
    GROUP BY
        COALESCE(NULLIF(TRIM(grupo_cuenta), ''), 'SIN GRUPO'),
        COALESCE(NULLIF(TRIM(sucursal), ''), 'SIN SUCURSAL')
    ORDER BY COALESCE(NULLIF(TRIM(grupo_cuenta), ''), 'SIN GRUPO'), COALESCE(NULLIF(TRIM(sucursal), ''), 'SIN SUCURSAL')
", $params);

$detallesPorGrupo = [];
$totalesPorGrupo = [];
$detalleProfundoPorGrupo = [];
$detalleProfundoPorLinea = [];
foreach ($detalleLineas as $row) {
    $grupo = trim((string) ($row['grupo_cuenta'] ?? 'SIN GRUPO'));
    $grupo = preg_replace('/\s+/', ' ', $grupo) ?: 'SIN GRUPO';
    $groupKey = normalizeGroupKey($grupo);
    $section = classifyGroup($grupo);
    $entry = [
        'codigo_cuenta' => (string) ($row['codigo_cuenta'] ?? '—'),
        'nombre_cuenta' => (string) ($row['nombre_cuenta'] ?? 'SIN NOMBRE'),
        'grupo_cuenta' => $grupo,
        'filas_detalle' => (int) ($row['filas_detalle'] ?? 0),
        'detail_group' => $groupKey,
        'detalle_count' => (int) ($row['detalle_count'] ?? 0),
        'proveedor_count' => (int) ($row['proveedor_count'] ?? 0),
        'ot_count' => (int) ($row['ot_count'] ?? 0),
        'total_ajustado' => (float) ($row['total_ajustado'] ?? 0),
    ];

    $detallesPorGrupo[$groupKey]['label'] = $grupo;
    $detallesPorGrupo[$groupKey]['rows'][] = $entry;
    if (!isset($totalesPorGrupo[$groupKey])) {
        $totalesPorGrupo[$groupKey] = ['label' => $grupo, 'section' => $section, 'total_ajustado' => 0.0];
    }
    $totalesPorGrupo[$groupKey]['total_ajustado'] += $entry['total_ajustado'];
}

foreach ($detalleProfundo as $row) {
    $grupo = trim((string) ($row['grupo_cuenta'] ?? 'SIN GRUPO'));
    $grupo = preg_replace('/\s+/', ' ', $grupo) ?: 'SIN GRUPO';
    $groupKey = normalizeGroupKey($grupo);
    $codigo = (string) ($row['codigo_cuenta'] ?? '—');
    $nombre = (string) ($row['nombre_cuenta'] ?? 'SIN NOMBRE');
    $lineKey = normalizeLineKey($groupKey, $codigo, $nombre);
    $detalleProfundoPorGrupo[$groupKey]['label'] = $grupo;
    $detalleProfundoPorGrupo[$groupKey]['rows'][] = [
        'codigo_cuenta' => $codigo,
        'nombre_cuenta' => $nombre,
        'detalle' => (string) ($row['detalle'] ?? 'SIN DETALLE'),
        'proveedor' => (string) ($row['proveedor'] ?? 'SIN PROVEEDOR'),
        'ot' => (string) ($row['ot'] ?? 'SIN OT'),
        'ot_agrupada' => (string) ($row['ot_agrupada'] ?? 'SIN OT AGRUPADA'),
        'ot_nombre' => (string) ($row['ot_nombre'] ?? 'SIN OT NOMBRE'),
        'filas' => (int) ($row['filas'] ?? 0),
        'total_original' => (float) ($row['total_original'] ?? 0),
        'total_ajustado' => (float) ($row['total_ajustado'] ?? 0),
    ];
    $detalleProfundoPorLinea[$lineKey]['label'] = $nombre;
    $detalleProfundoPorLinea[$lineKey]['codigo_cuenta'] = $codigo;
    $detalleProfundoPorLinea[$lineKey]['grupo_cuenta'] = $grupo;
    $detalleProfundoPorLinea[$lineKey]['rows'][] = [
        'grupo_cuenta' => $grupo,
        'detalle' => (string) ($row['detalle'] ?? 'SIN DETALLE'),
        'proveedor' => (string) ($row['proveedor'] ?? 'SIN PROVEEDOR'),
        'ot' => (string) ($row['ot'] ?? 'SIN OT'),
        'ot_agrupada' => (string) ($row['ot_agrupada'] ?? 'SIN OT AGRUPADA'),
        'ot_nombre' => (string) ($row['ot_nombre'] ?? 'SIN OT NOMBRE'),
        'filas' => (int) ($row['filas'] ?? 0),
        'total_original' => (float) ($row['total_original'] ?? 0),
        'total_ajustado' => (float) ($row['total_ajustado'] ?? 0),
    ];
}

$grupos = ['ingresos' => [], 'operativos' => [], 'financieros' => []];
foreach ($totalesPorGrupo as $grupoKey => $info) {
    $grupos[$info['section']][] = [
        'grupo_cuenta' => $info['label'],
        'total_ajustado' => (float) $info['total_ajustado'],
    ];
}

$matrizPorGrupo = [];
foreach ($matrizSucursal as $row) {
    $grupo = trim((string) ($row['grupo_cuenta'] ?? 'SIN GRUPO'));
    $grupo = preg_replace('/\s+/', ' ', $grupo) ?: 'SIN GRUPO';
    $groupKey = normalizeGroupKey($grupo);
    $sucursal = (string) ($row['sucursal_norm'] ?? 'SIN SUCURSAL');
    $matrizPorGrupo[$groupKey]['label'] = $grupo;
    $matrizPorGrupo[$groupKey]['section'] = classifyGroup($grupo);
        $matrizPorGrupo[$groupKey]['sucursales'][$sucursal] = (float) ($row['total_ajustado'] ?? 0);
}

$ventas = 0.0;
foreach ($grupos['ingresos'] as $item) {
    $ventas += (float) $item['total_ajustado'];
}
$gastosOperativos = 0.0;
foreach ($grupos['operativos'] as $item) {
    $gastosOperativos += (float) $item['total_ajustado'];
}
$gastosFinancieros = 0.0;
foreach ($grupos['financieros'] as $item) {
    $gastosFinancieros += (float) $item['total_ajustado'];
}

$ebitda = $ventas + $gastosOperativos;
$resultadoFinal = $ebitda + $gastosFinancieros;
$margenEbitda = $ventas != 0.0 ? ($ebitda / $ventas) * 100 : 0.0;
$shareVentas = static fn (float $v): float => abs($ventas) > 0 ? (abs($v) / abs($ventas)) * 100 : 0.0;
$maxAbsLinea = max(1.0, abs($ventas), abs($gastosOperativos), abs($gastosFinancieros), abs($ebitda), abs($resultadoFinal));

$lineasPorSeccion = [
    'ingresos' => [],
    'operativos' => [],
    'financieros' => [],
];
foreach ($grupos['ingresos'] as $item) {
    $lineasPorSeccion['ingresos'][] = [$item['grupo_cuenta'], (float) $item['total_ajustado']];
}
foreach ($grupos['operativos'] as $item) {
    $lineasPorSeccion['operativos'][] = [$item['grupo_cuenta'], (float) $item['total_ajustado']];
}
foreach ($grupos['financieros'] as $item) {
    $lineasPorSeccion['financieros'][] = [$item['grupo_cuenta'], (float) $item['total_ajustado']];
}

$selectedPeriodsLabel = selectedPeriodLabel($seleccion);
$selectedNegocioLabel = $negocioSeleccionado ?? 'Todos los negocios';
$selectedSucursalLabel = $sucursalSeleccionada ?? 'Todas las sucursales';
$fechaMin = $resumen['fecha_min'] ?? null;
$fechaMax = $resumen['fecha_max'] ?? null;

$detalleLineaJson = json_encode($detalleProfundoPorLinea, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ventasJson = json_encode((float) $ventas);
?>
<!doctype html>
<html lang="es">
<head>
    <?php lcm_head('Resumen Ejecutivo Gerencial TMA', ['https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css']); ?>
    <style>
        .tma-page { display:grid; gap:14px; }
        .tma-hero, .tma-panel, .detail-card { border:1px solid var(--atlantic-line); border-radius:18px; background:#fff; box-shadow:var(--atlantic-shadow); }
        .tma-hero { padding:18px 20px 16px; }
        .tma-hero-top { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; }
        .tma-hero h1 { margin:3px 0 4px; font-size:clamp(22px, 2.3vw, 30px); line-height:1.05; color:var(--atlantic-ink); }
        .tma-hero p { margin:0; color:var(--atlantic-muted); font-size:13px; line-height:1.35; }
        .tma-hero-meta { display:grid; gap:8px; min-width:240px; text-align:right; }
        .tma-hero-meta .label { color:var(--atlantic-muted); font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; }
        .tma-hero-meta .value { color:var(--atlantic-ink); font-size:12px; font-weight:800; }
        .periodo-selector { position:relative; display:inline-flex; align-items:center; gap:10px; margin-top:14px; }
        .periodo-button { display:inline-flex; align-items:center; gap:10px; padding:8px 12px; border:1px solid #dfe5ed; border-radius:12px; background:#fff; color:var(--atlantic-ink); font-size:12px; font-weight:800; cursor:pointer; }
        .periodo-button span { color:var(--atlantic-muted); font-size:11px; font-weight:700; }
        .periodo-menu { position:absolute; top:calc(100% + 8px); left:0; z-index:30; width:min(340px, 92vw); padding:12px; border:1px solid #dde4ec; border-radius:14px; background:#fff; box-shadow:0 20px 42px rgba(24,39,75,.16); display:none; }
        .periodo-menu.open { display:block; }
        .periodo-options { max-height:220px; overflow:auto; display:grid; gap:6px; padding-right:4px; }
        .periodo-option { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:10px; background:#f7f9fc; cursor:pointer; font-size:12px; }
        .periodo-actions { display:flex; gap:8px; margin-top:10px; }
        .periodo-actions button { flex:1; padding:8px 10px; border:0; border-radius:10px; font-weight:800; font-size:12px; cursor:pointer; }
        .periodo-clear { background:#e6eaef; } .periodo-apply { background:var(--atlantic-primary); color:#fff; }
        .negocio-filter { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .negocio-filter label { color:var(--atlantic-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; }
        .negocio-filter select { padding:8px 12px; border:1px solid #dfe5ed; border-radius:12px; background:#fff; color:var(--atlantic-ink); font-size:12px; font-weight:800; }
        .filters-row { display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap; margin-top:10px; }
        .filters-row > * { flex: 0 0 auto; }
        .selection-chiprow { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
        .selection-chip { padding:5px 9px; border-radius:999px; background:#edf2f8; color:var(--atlantic-ink); font-size:11px; font-weight:700; }
        .kpi-grid { display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:10px; }
        .kpi-card { padding:12px; border-radius:14px; border:1px solid #e7ebf1; background:#fff; }
        .kpi-card .label { color:var(--atlantic-muted); font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; }
        .kpi-card .value { display:block; margin-top:6px; color:var(--atlantic-ink); font-size:18px; font-weight:900; line-height:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .kpi-card .sub { display:block; margin-top:6px; color:var(--atlantic-muted); font-size:10px; line-height:1.2; }
        .kpi-card .value.positive { color:#1f8a5a; } .kpi-card .value.negative { color:#c94a4a; }
        .kpi-card .share { display:block; margin-top:4px; font-size:18px; font-weight:900; line-height:1; }
        .statement { display:grid; gap:10px; }
        .statement-head h2 { margin:0; font-size:18px; color:var(--atlantic-ink); }
        .statement-section { padding:14px; border-radius:16px; border:1px solid #e8edf3; background:#fff; }
        .statement-section--income { border-left:4px solid #2f6df6; } .statement-section--expense { border-left:4px solid #e07a33; } .statement-section--financial { border-left:4px solid #b55bcb; }
        .statement-section h3 { margin:0 0 12px; font-size:16px; font-weight:800; color:var(--atlantic-ink); }
        .statement-list { display:grid; gap:7px; }
        .statement-row { display:grid; grid-template-columns:220px minmax(0,1fr) 220px; gap:10px; align-items:center; padding:10px 12px; border-radius:12px; background:#f9fbfd; }
        .row-positive { background:#f2fbf6; } .row-negative { background:#fff6f2; } .row-neutral { background:#f9fbfd; }
        .statement-row strong { color:var(--atlantic-ink); font-size:13px; font-weight:800; line-height:1.2; }
        .bar { height:6px; border-radius:999px; background:#e9edf3; overflow:hidden; opacity:.7; }
        .bar > span { display:block; height:100%; border-radius:inherit; }
        .bar--income > span { background:linear-gradient(90deg, #2f6df6 0%, #78a6ff 100%); } .bar--expense > span { background:linear-gradient(90deg, #e07a33 0%, #f0a36b 100%); } .bar--financial > span { background:linear-gradient(90deg, #b55bcb 0%, #d08fe0 100%); }
        .amount-wrap { display:flex; align-items:center; justify-content:flex-end; gap:6px; white-space:nowrap; min-width:0; }
        .amount { color:var(--atlantic-ink); font-size:13px; font-weight:800; font-variant-numeric:tabular-nums; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:-.01em; }
        .share { font-size:12px; font-weight:800; white-space:nowrap; }
        .share-positive { color:#1f8a5a; }
        .share-negative { color:#c94a4a; }
        .share-neutral { color:var(--atlantic-muted); }
        .detail-btn { width:34px; height:34px; border:1px solid #dce3ec; border-radius:11px; background:#fff; color:#2557d6; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; flex:0 0 auto; box-shadow:0 1px 2px rgba(15,23,42,.06); }
        .detail-btn--text { width:auto; min-width:74px; padding:0 10px; gap:6px; }
        .detail-btn--text span { font-size:12px; font-weight:800; line-height:1; }
        .detail-btn i { font-size:15px; }
        .detail-btn:hover { background:#f3f7ff; border-color:#b9cbef; }
        .detail-btn:disabled { opacity:.4; cursor:not-allowed; box-shadow:none; }
        .statement-subtotal { display:grid; grid-template-columns:1fr 220px; gap:8px; align-items:center; margin-top:8px; padding:10px 12px; border-radius:12px; background:#eef3f9; }
        .statement-subtotal strong { color:var(--atlantic-ink); font-size:12px; }
        .result-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:10px; }
        .result-card { padding:12px; border-radius:14px; border:1px solid #e7ebf1; background:#fff; text-align:center; }
        .result-card .label { color:var(--atlantic-muted); font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; }
        .result-card .value { display:block; margin-top:6px; color:var(--atlantic-ink); font-size:19px; font-weight:900; line-height:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .result-card .sub { display:block; margin-top:5px; color:var(--atlantic-muted); font-size:10px; line-height:1.2; }
        .result-card .value.positive { color:#1f8a5a; } .result-card .value.negative { color:#c94a4a; }
        .result-card .share { display:block; margin-top:4px; font-size:19px; font-weight:900; line-height:1; }
        .selected-column { background:#edf5ff !important; }
        .detail-modal { position:fixed; inset:0; z-index:2000; display:none; align-items:center; justify-content:center; padding:16px; background:rgba(17,24,39,.45); }
        .detail-modal.open { display:flex; }
        .detail-card { width:min(1100px, 100%); max-height:min(86vh, 920px); overflow:auto; }
        .detail-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; padding:14px 16px; border-bottom:1px solid #e7ebf1; }
        .detail-head h3 { margin:0; font-size:15px; }
        .detail-close { width:32px; height:32px; border:0; border-radius:10px; background:#eef3f9; cursor:pointer; }
        .detail-body { padding:14px 16px 16px; }
        .detail-summary { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:8px; margin-bottom:12px; }
        .detail-summary div { padding:10px; border-radius:12px; background:#f7f9fc; }
        .detail-summary span { display:block; color:var(--atlantic-muted); font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; }
        .detail-summary strong { display:block; margin-top:4px; color:var(--atlantic-ink); font-size:13px; }
        .detail-table { width:100%; min-width:980px; border-collapse:separate; border-spacing:0; font-size:14px; table-layout:fixed; }
        .detail-table th, .detail-table td { padding:11px 10px; border-bottom:1px solid #e5ebf2; border-right:1px solid #edf1f6; text-align:left; vertical-align:middle; }
        .detail-table th:last-child, .detail-table td:last-child { border-right:0; }
        .detail-table th { position:sticky; top:0; background:#f4f7fb; color:var(--atlantic-muted); font-size:12px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; z-index:1; }
        .detail-table tbody tr:nth-child(even) { background:#fbfcfe; }
        .detail-table tbody tr:hover { background:#f2f7ff; }
        .detail-summary-table td:first-child, .detail-summary-table th:first-child { width:420px; font-weight:800; color:var(--atlantic-ink); }
        .detail-summary-table td:nth-child(2), .detail-summary-table th:nth-child(2) { width:160px; }
        .detail-summary-table td:nth-child(3), .detail-summary-table th:nth-child(3) { width:120px; }
        .detail-summary-table td:nth-child(4), .detail-summary-table th:nth-child(4) { width:90px; }
        .table-total-stack { display:flex; flex-direction:column; align-items:flex-end; gap:2px; line-height:1; }
        .table-total-stack .amount { font-size:15px; font-weight:900; }
        .table-total-stack .share { font-size:11px; font-weight:800; }
        .detail-deep-table td:first-child, .detail-deep-table th:first-child { width:260px; font-weight:800; color:var(--atlantic-ink); }
        .detail-deep-table td:nth-child(2), .detail-deep-table th:nth-child(2) { width:180px; }
        .detail-deep-table td:nth-child(3), .detail-deep-table th:nth-child(3) { width:120px; }
        .detail-deep-table td:nth-child(4), .detail-deep-table th:nth-child(4) { width:140px; }
        .detail-deep-table td:nth-child(5), .detail-deep-table th:nth-child(5) { width:160px; }
        .detail-deep-table td:nth-child(6), .detail-deep-table th:nth-child(6) { width:140px; }
        .detail-deep-table td:nth-child(7), .detail-deep-table th:nth-child(7) { width:110px; }
        .detail-deep-table td:nth-child(8), .detail-deep-table th:nth-child(8) { width:78px; }
        .detail-line-modal .detail-card { width:min(1040px, 100%); }
        .detail-line-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:8px; margin-bottom:10px; }
        .detail-line-grid div { padding:10px 12px; border-radius:12px; background:#f7f9fc; }
        .detail-line-grid span { display:block; color:var(--atlantic-muted); font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; }
        .detail-line-grid strong { display:block; margin-top:4px; color:var(--atlantic-ink); font-size:13px; }
        .detail-line-modal .detail-table { min-width: 0; }
        .detail-line-modal .detail-table th:nth-child(1), .detail-line-modal .detail-table td:nth-child(1) { width: 130px; }
        .detail-line-modal .detail-table th:nth-child(2), .detail-line-modal .detail-table td:nth-child(2) { width: 180px; }
        .detail-line-modal .detail-table th:nth-child(3), .detail-line-modal .detail-table td:nth-child(3) { width: 140px; }
        .detail-line-modal .detail-table th:nth-child(4), .detail-line-modal .detail-table td:nth-child(4) { width: auto; }
        .detail-head-actions { display:flex; align-items:center; gap:8px; }
        .detail-export { display:inline-flex; align-items:center; gap:8px; padding:0 12px; height:32px; border:1px solid #d7e1ea; border-radius:10px; background:#f4fbf6; color:#1f8a5a; font:inherit; font-size:12px; font-weight:900; cursor:pointer; appearance:none; -webkit-appearance:none; line-height:1; }
        .detail-export:hover { background:#eaf8ef; border-color:#bfe0c9; }
        .detail-export i { font-size:14px; }
        .breakdown-row td { padding:0; border-bottom:1px solid #e5ebf2; background:#f8fbff; }
        .breakdown-panel { padding:12px 12px 14px; border-top:1px solid #e1e8f1; background:#f7faff; }
        .breakdown-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; }
        .breakdown-head strong { color:var(--atlantic-ink); font-size:13px; font-weight:900; }
        .breakdown-head span { display:block; color:var(--atlantic-muted); font-size:11px; font-weight:700; margin-top:2px; }
        .breakdown-table { width:100%; min-width:760px; border-collapse:separate; border-spacing:0; font-size:12px; table-layout:fixed; }
        .breakdown-table th, .breakdown-table td { padding:10px 9px; border-bottom:1px solid #e5ebf2; border-right:1px solid #edf1f6; vertical-align:middle; }
        .breakdown-table th:last-child, .breakdown-table td:last-child { border-right:0; }
        .breakdown-table th { background:#eef3fa; color:var(--atlantic-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; text-align:left; }
        .breakdown-table td.amount, .breakdown-table th.amount { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .breakdown-table tbody tr:nth-child(even) { background:#fcfdff; }
        .breakdown-table tbody tr:hover { background:#f0f6ff; }
        .breakdown-code { color:var(--atlantic-primary); font-weight:900; font-size:11px; white-space:nowrap; }
        .breakdown-table .detail-account-name { font-size:12px; }
        .breakdown-table .detail-account-meta { font-size:10px; }
        .breakdown-table .amount-total { font-size:12px; font-weight:900; }
        .detail-table td.amount, .detail-table th.amount { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .detail-table td.amount { font-size:14px; font-weight:800; }
        .detail-table tfoot th { background:#edf3fa; color:var(--atlantic-ink); font-size:13px; font-weight:900; }
        .detail-table tfoot th.amount { font-size:14px; }
        .detail-table .selected-column { background:#e8f1ff !important; }
        .statement-section .detail-table { min-width:0; }
        .detail-account { display:grid; gap:3px; }
        .detail-account-head { display:flex; align-items:baseline; gap:8px; min-width:0; }
        .detail-account-code { flex:0 0 auto; color:var(--atlantic-primary); font-size:12px; font-weight:900; letter-spacing:.03em; }
        .detail-account-name { min-width:0; color:var(--atlantic-ink); font-size:13px; font-weight:800; line-height:1.2; }
        .detail-account-meta { color:var(--atlantic-muted); font-size:10px; font-weight:700; line-height:1.1; }
        .statement-section .detail-table th:nth-last-child(3), .statement-section .detail-table td:nth-last-child(3) { width:170px; }
        .statement-section .detail-table th:nth-last-child(2), .statement-section .detail-table td:nth-last-child(2) { width:90px; }
        .statement-section .detail-table th:last-child, .statement-section .detail-table td:last-child { width:76px; }
        .statement-section .detail-table th:first-child, .statement-section .detail-table td:first-child { min-width:240px; }
        .statement-section .detail-table th.amount, .statement-section .detail-table td.amount { width:auto; }
        .detail-table td:first-child { letter-spacing:-.02em; padding-right:6px; }
        .detail-table td:nth-child(2) { padding-left:6px; }
        .detail-action-btn { width:34px; height:34px; border:1px solid #dce3ec; border-radius:11px; background:#fff; color:var(--atlantic-primary); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 1px 2px rgba(15,23,42,.06); }
        .detail-action-btn:hover { background:#f3f7ff; border-color:#b9cbef; }
        .detail-line-modal .detail-action-btn { width:38px; height:38px; border-radius:12px; color:#2557d6; }
        .detail-line-modal .detail-action-btn i { font-size:15px; }
        .detail-line-modal .detail-action-btn:disabled { opacity:.35; cursor:not-allowed; }
        .detail-table .amount-total { font-size:15px; font-weight:900; }
        .detail-empty { color:var(--atlantic-muted); font-size:12px; }
        .note { margin:0; padding:10px 12px; border-radius:12px; background:#f5f7fa; color:var(--atlantic-muted); font-size:11px; line-height:1.3; }
        @media (max-width: 1180px) { .kpi-grid, .result-grid { grid-template-columns:repeat(3, minmax(0,1fr)); } .detail-summary { grid-template-columns:repeat(2, minmax(0,1fr)); } }
        @media (max-width: 780px) { .tma-hero-top { flex-direction:column; } .tma-hero-meta { text-align:left; min-width:0; } .kpi-grid, .result-grid { grid-template-columns:1fr; } .statement-row, .statement-subtotal { grid-template-columns:1fr; } .amount-wrap { justify-content:flex-start; } .detail-summary { grid-template-columns:1fr; } }
    </style>
</head>
<body class="lcm-page lcm-page--with-nav">
<?php lcm_topbar('gerencia'); ?>

<main class="lcm-shell tma-page">
    <section class="tma-hero">
        <div class="tma-hero-top">
            <div>
                <span class="lcm-eyebrow">Gerencia</span>
                <h1>Resumen Ejecutivo Gerencial TMA</h1>
                <p>Vista compacta del estado económico con ventas a la cabeza, costos y gastos debajo, EBITDA, financieros y resultado final.</p>

                <div class="filters-row">
                    <div class="periodo-selector">
                        <button id="periodoBtn" class="periodo-button" type="button" aria-expanded="false" aria-controls="periodoMenu">
                            <span>Períodos</span>
                            <strong><?php echo htmlspecialchars($selectedPeriodsLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                        </button>
                        <div id="periodoMenu" class="periodo-menu">
                            <div class="periodo-options">
                                <?php foreach ($periodosDisponibles as $p): ?>
                                    <label class="periodo-option">
                                        <input type="checkbox" value="<?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($p, $seleccion, true) ? 'checked' : ''; ?>>
                                        <span><?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="periodo-actions">
                                <button id="limpiarPeriodos" class="periodo-clear" type="button">Limpiar</button>
                                <button id="aplicarPeriodos" class="periodo-apply" type="button">Aplicar</button>
                            </div>
                        </div>
                    </div>

                    <div class="negocio-filter">
                        <label for="negocioSelect">Negocio</label>
                        <select id="negocioSelect">
                            <option value="">Todos los negocios</option>
                            <?php foreach ($negociosDisponibles as $negocio): ?>
                                <option value="<?php echo htmlspecialchars($negocio, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $negocioSeleccionado === $negocio ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($negocio, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="negocio-filter">
                        <label for="sucursalSelect">Sucursal</label>
                        <select id="sucursalSelect">
                            <option value="">Todas las sucursales</option>
                            <?php foreach ($sucursalesDisponibles as $sucursal): ?>
                                <option value="<?php echo htmlspecialchars($sucursal, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $sucursalSeleccionada === $sucursal ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sucursal, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="selection-chiprow" aria-label="Filtros seleccionados">
                    <?php foreach ($seleccion as $p): ?>
                        <span class="selection-chip"><?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endforeach; ?>
                    <span class="selection-chip"><?php echo htmlspecialchars($selectedNegocioLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="selection-chip"><?php echo htmlspecialchars($selectedSucursalLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>

            <div class="tma-hero-meta">
                <div>
                    <div class="label">Actualización</div>
                    <div class="value"><?php echo date('d/m/Y H:i'); ?></div>
                </div>
                <div>
                    <div class="label">Cobertura</div>
                    <div class="value"><?php echo $fechaMin && $fechaMax ? htmlspecialchars(date('d/m/Y', strtotime((string) $fechaMin)) . ' a ' . date('d/m/Y', strtotime((string) $fechaMax)), ENT_QUOTES, 'UTF-8') : 'Sin datos'; ?></div>
                </div>
            </div>
        </div>
    </section>

    <section class="kpi-grid" aria-label="Indicadores principales">
        <article class="kpi-card"><span class="label">Ventas</span><strong class="value positive"><?php echo htmlspecialchars(fmtPesos($ventas), ENT_QUOTES, 'UTF-8'); ?></strong><span class="sub">Ventas + materiales.</span><span class="share share-neutral">100%</span></article>
        <article class="kpi-card"><span class="label">Gastos operativos</span><strong class="value negative"><?php echo htmlspecialchars(fmtPesos($gastosOperativos), ENT_QUOTES, 'UTF-8'); ?></strong><span class="sub">Sin financieros.</span><span class="share <?php echo shareClass($gastosOperativos); ?>"><?php echo htmlspecialchars(fmtPct($shareVentas($gastosOperativos)), ENT_QUOTES, 'UTF-8'); ?></span></article>
        <article class="kpi-card"><span class="label">EBITDA</span><strong class="value <?php echo $ebitda >= 0 ? 'positive' : 'negative'; ?>"><?php echo htmlspecialchars(fmtPesos($ebitda), ENT_QUOTES, 'UTF-8'); ?></strong><span class="sub">Ventas menos gastos operativos.</span><span class="share <?php echo shareClass($ebitda); ?>"><?php echo htmlspecialchars(fmtPct($shareVentas($ebitda)), ENT_QUOTES, 'UTF-8'); ?></span></article>
        <article class="kpi-card"><span class="label">Gastos financieros</span><strong class="value negative"><?php echo htmlspecialchars(fmtPesos($gastosFinancieros), ENT_QUOTES, 'UTF-8'); ?></strong><span class="sub">Impacto financiero.</span><span class="share <?php echo shareClass($gastosFinancieros); ?>"><?php echo htmlspecialchars(fmtPct($shareVentas($gastosFinancieros)), ENT_QUOTES, 'UTF-8'); ?></span></article>
        <article class="kpi-card"><span class="label">Resultado final</span><strong class="value <?php echo $resultadoFinal >= 0 ? 'positive' : 'negative'; ?>"><?php echo htmlspecialchars(fmtPesos($resultadoFinal), ENT_QUOTES, 'UTF-8'); ?></strong><span class="sub">EBITDA menos financieros.</span><span class="share <?php echo shareClass($resultadoFinal); ?>"><?php echo htmlspecialchars(fmtPct($shareVentas($resultadoFinal)), ENT_QUOTES, 'UTF-8'); ?></span></article>
        <article class="kpi-card"><span class="label">Margen EBITDA</span><strong class="value <?php echo $margenEbitda >= 0 ? 'positive' : 'negative'; ?>"><?php echo htmlspecialchars(fmtPct($margenEbitda), ENT_QUOTES, 'UTF-8'); ?></strong><span class="sub">EBITDA sobre ventas.</span></article>
    </section>

    <section class="statement">
        <div class="statement-head"><h2>Estado de resultados</h2></div>

        <?php
        $secciones = [
            'ingresos' => ['titulo' => 'Ingresos', 'clase' => 'statement-section--income'],
            'operativos' => ['titulo' => 'Costos y gastos operativos', 'clase' => 'statement-section--expense'],
            'financieros' => ['titulo' => 'Gastos financieros', 'clase' => 'statement-section--financial'],
        ];
        $sucursalColumnas = $sucursalesDisponibles;
        ?>

        <?php foreach ($secciones as $sectionKey => $sectionMeta): ?>
            <section class="statement-section <?php echo $sectionMeta['clase']; ?>">
                <h3><?php echo htmlspecialchars($sectionMeta['titulo'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <div style="overflow:auto;">
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th>Concepto</th>
                                <?php foreach ($sucursalColumnas as $sucursal): ?>
                                    <th class="amount <?php echo ($sucursalSeleccionada !== null && $sucursalSeleccionada === $sucursal) ? 'selected-column' : ''; ?>"><?php echo htmlspecialchars($sucursal, ENT_QUOTES, 'UTF-8'); ?></th>
                                <?php endforeach; ?>
                                <th class="amount">Total</th>
                                <th class="amount">%</th>
                                <th class="amount">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grupos[$sectionKey] as $item): ?>
                                <?php
                                $groupKey = normalizeGroupKey((string) $item['grupo_cuenta']);
                                $breakdownId = domSafeId($groupKey);
                                $rowTotal = (float) $item['total_ajustado'];
                                $rowMatrix = $matrizPorGrupo[$groupKey]['sucursales'] ?? [];
                                $breakdownRows = $detallesPorGrupo[$groupKey]['rows'] ?? [];
                                ?>
                                <tr class="main-group-row" data-group-row="<?php echo htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td><?php echo htmlspecialchars((string) $item['grupo_cuenta'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php foreach ($sucursalColumnas as $sucursal): ?>
                                        <?php $cell = (float) ($rowMatrix[$sucursal] ?? 0); ?>
                                        <td class="amount <?php echo rowClass($cell); ?> <?php echo ($sucursalSeleccionada !== null && $sucursalSeleccionada === $sucursal) ? 'selected-column' : ''; ?>"><?php echo htmlspecialchars(fmtPesos($cell), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php endforeach; ?>
                                    <?php $rowShare = $shareVentas($rowTotal); ?>
                                    <td class="amount <?php echo rowClass($rowTotal); ?>">
                                        <div class="table-total-stack">
                                            <span class="amount"><?php echo htmlspecialchars(fmtPesos($rowTotal), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="share <?php echo shareClass($rowTotal); ?>"><?php echo htmlspecialchars(fmtPct($rowShare), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </td>
                                    <td class="amount <?php echo shareClass($rowTotal); ?>"><?php echo htmlspecialchars(fmtPct($rowShare), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="amount">
                                        <button
                                            class="detail-btn"
                                            type="button"
                                            data-toggle-group="<?php echo htmlspecialchars($breakdownId, ENT_QUOTES, 'UTF-8'); ?>"
                                            aria-expanded="false"
                                            aria-controls="breakdown-<?php echo htmlspecialchars($breakdownId, ENT_QUOTES, 'UTF-8'); ?>"
                                            aria-label="Desglosar <?php echo htmlspecialchars((string) $item['grupo_cuenta'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr id="breakdown-<?php echo htmlspecialchars($breakdownId, ENT_QUOTES, 'UTF-8'); ?>" class="breakdown-row" hidden>
                                    <td colspan="<?php echo count($sucursalColumnas) + 4; ?>">
                                        <div class="breakdown-panel">
                                            <div class="breakdown-head">
                                                <div>
                                                    <strong><?php echo htmlspecialchars((string) $item['grupo_cuenta'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <span><?php echo count($breakdownRows); ?> líneas de cuenta</span>
                                                </div>
                                            </div>
                                            <div style="overflow:auto;">
                                                <table class="breakdown-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Código</th>
                                                            <th>Cuenta</th>
                                                            <th class="amount">Importe ajustado</th>
                                                            <th class="amount">% ventas</th>
                                                            <th class="amount">Detalle</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!$breakdownRows): ?>
                                                            <tr><td colspan="5" class="detail-empty">Sin datos para mostrar.</td></tr>
                                                        <?php else: ?>
                                                            <?php foreach ($breakdownRows as $line): ?>
                                                                <?php
                                                                $lineKey = normalizeLineKey($groupKey, (string) $line['codigo_cuenta'], (string) $line['nombre_cuenta']);
                                                                $lineRows = $detalleProfundoPorLinea[$lineKey]['rows'] ?? [];
                                                                ?>
                                                                <tr>
                                                                    <td class="breakdown-code"><?php echo htmlspecialchars((string) $line['codigo_cuenta'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                                    <td>
                                                                        <div class="detail-account">
                                                                            <span class="detail-account-name"><?php echo htmlspecialchars((string) $line['nombre_cuenta'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                                            <span class="detail-account-meta"><?php echo htmlspecialchars((string) $line['filas_detalle'], ENT_QUOTES, 'UTF-8'); ?> movimientos · <?php echo htmlspecialchars((string) $line['detalle_count'], ENT_QUOTES, 'UTF-8'); ?> detalles</span>
                                                                        </div>
                                                                    </td>
                                                                    <td class="amount amount-total"><?php echo htmlspecialchars(fmtPesos((float) $line['total_ajustado']), ENT_QUOTES, 'UTF-8'); ?></td>
                                                                    <td class="amount <?php echo shareClass((float) $line['total_ajustado']); ?>"><?php echo htmlspecialchars(fmtPct($shareVentas((float) $line['total_ajustado'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                                                    <td class="amount">
                                                                        <button
                                                                            class="detail-action-btn"
                                                                            type="button"
                                                                            data-line-key="<?php echo htmlspecialchars($lineKey, ENT_QUOTES, 'UTF-8'); ?>"
                                                                            data-line-group="<?php echo htmlspecialchars((string) $line['grupo_cuenta'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                            data-line-title="<?php echo htmlspecialchars((string) $line['nombre_cuenta'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                            data-line-code="<?php echo htmlspecialchars((string) $line['codigo_cuenta'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                            aria-label="Ver detalle de <?php echo htmlspecialchars((string) $line['nombre_cuenta'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                        >
                                                                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <?php foreach ($sucursalColumnas as $sucursal): ?>
                                <?php
                                $sucursalTotal = 0.0;
                                foreach ($grupos[$sectionKey] as $item) {
                                    $groupKey = normalizeGroupKey((string) $item['grupo_cuenta']);
                                    $sucursalTotal += (float) ($matrizPorGrupo[$groupKey]['sucursales'][$sucursal] ?? 0);
                                }
                                $sucursalShare = $shareVentas($sucursalTotal);
                                ?>
                                    <th class="amount <?php echo ($sucursalSeleccionada !== null && $sucursalSeleccionada === $sucursal) ? 'selected-column' : ''; ?>">
                                        <div class="table-total-stack">
                                            <span class="amount"><?php echo htmlspecialchars(fmtPesos($sucursalTotal), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="share <?php echo shareClass($sucursalTotal); ?>"><?php echo htmlspecialchars(fmtPct($sucursalShare), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                                <?php
                                $sectionTotal = 0.0;
                                foreach ($grupos[$sectionKey] as $item) {
                                    $sectionTotal += (float) $item['total_ajustado'];
                                }
                                $sectionShare = $shareVentas($sectionTotal);
                                ?>
                                <th class="amount">
                                    <div class="table-total-stack">
                                        <span class="amount"><?php echo htmlspecialchars(fmtPesos($sectionTotal), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="share <?php echo shareClass($sectionTotal); ?>"><?php echo htmlspecialchars(fmtPct($sectionShare), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </th>
                                <th class="amount <?php echo shareClass($sectionTotal); ?>"><?php echo htmlspecialchars(fmtPct($sectionShare), ENT_QUOTES, 'UTF-8'); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="result-grid">
            <article class="result-card">
                <span class="label">EBITDA</span>
                <strong class="value <?php echo $ebitda >= 0 ? 'positive' : 'negative'; ?>"><?php echo htmlspecialchars(fmtPesos($ebitda), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="sub">Ventas menos gastos operativos.</span>
                <span class="share <?php echo shareClass($ebitda); ?>"><?php echo htmlspecialchars(fmtPct($shareVentas($ebitda)), ENT_QUOTES, 'UTF-8'); ?></span>
            </article>
            <article class="result-card">
                <span class="label">Gastos financieros</span>
                <strong class="value negative"><?php echo htmlspecialchars(fmtPesos($gastosFinancieros), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="sub">Impacto financiero.</span>
                <span class="share <?php echo shareClass($gastosFinancieros); ?>"><?php echo htmlspecialchars(fmtPct($shareVentas($gastosFinancieros)), ENT_QUOTES, 'UTF-8'); ?></span>
            </article>
            <article class="result-card">
                <span class="label">Resultado final</span>
                <strong class="value <?php echo $resultadoFinal >= 0 ? 'positive' : 'negative'; ?>"><?php echo htmlspecialchars(fmtPesos($resultadoFinal), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="sub">EBITDA menos financieros.</span>
                <span class="share <?php echo shareClass($resultadoFinal); ?>"><?php echo htmlspecialchars(fmtPct($shareVentas($resultadoFinal)), ENT_QUOTES, 'UTF-8'); ?></span>
            </article>
        </div>
    </section>

    <p class="note">La selección actual se aplica a la información contable disponible. La lectura es preliminar y compacta para dirección.</p>
</main>

<div id="detailLineModal" class="detail-modal detail-line-modal" aria-hidden="true">
    <div class="detail-card" role="dialog" aria-modal="true" aria-labelledby="detailLineTitle">
        <header class="detail-head">
            <div>
                <h3 id="detailLineTitle">Detalle de línea</h3>
                <div id="detailLineSubtitle" class="lcm-muted" style="font-size:12px;"></div>
            </div>
            <div class="detail-head-actions">
                <button id="detailLineExport" class="detail-export" type="button" aria-label="Exportar a Excel"><i class="fa-solid fa-file-excel" aria-hidden="true"></i><span>Exportar</span></button>
                <button id="detailLineClose" class="detail-close" type="button" aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
        </header>
        <div class="detail-body">
            <div class="detail-line-grid" id="detailLineGrid"></div>
            <p class="note" style="margin-bottom:10px;">Esta línea se muestra en mayor profundidad. El archivo exportado conserva la misma estructura visible.</p>
            <div style="max-height: 46vh; overflow:auto;">
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>OT agrupada</th>
                            <th>OT nombre</th>
                            <th class="amount">Importe</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody id="detailLineBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php lcm_footer(); ?>

<script>
const periodoBtn = document.getElementById('periodoBtn');
const periodoMenu = document.getElementById('periodoMenu');
const negocioSelect = document.getElementById('negocioSelect');
const sucursalSelect = document.getElementById('sucursalSelect');
const limpiarPeriodos = document.getElementById('limpiarPeriodos');
const aplicarPeriodos = document.getElementById('aplicarPeriodos');
const detailLineModal = document.getElementById('detailLineModal');
const detailLineTitle = document.getElementById('detailLineTitle');
const detailLineSubtitle = document.getElementById('detailLineSubtitle');
const detailLineGrid = document.getElementById('detailLineGrid');
const detailLineBody = document.getElementById('detailLineBody');
const detailLineClose = document.getElementById('detailLineClose');
const detailLineExport = document.getElementById('detailLineExport');
const detailLineCatalog = <?php echo $detalleLineaJson; ?>;
const ventasReferencia = <?php echo $ventasJson; ?>;
let currentLineRows = [];
let currentLineTitle = '';
let currentLineCode = '';

function fmtPesosJs(valor) {
    const value = Number(valor || 0);
    const sign = value < 0 ? '-' : '';
    return sign + '$' + Math.abs(value).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function fmtPctJs(valor) {
    const value = Number(valor || 0);
    return value.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
}

function getSelectedPeriods() {
    return [...periodoMenu.querySelectorAll('input[type="checkbox"]:checked')].map(el => el.value);
}

function updateButtonLabel() {
    const selected = getSelectedPeriods();
    const strong = periodoBtn.querySelector('strong');
    if (!selected.length) {
        strong.textContent = 'Sin período';
    } else if (selected.length === 1) {
        strong.textContent = selected[0];
    } else if (selected.length <= 3) {
        strong.textContent = selected.join(' · ');
    } else {
        strong.textContent = `${selected.length} períodos`;
    }
}

function closeLineDetail() {
    detailLineModal.classList.remove('open');
    detailLineModal.setAttribute('aria-hidden', 'true');
}

function openLineDetail(lineKey, title, code) {
    const bundle = detailLineCatalog[lineKey] || {};
    const rows = Array.isArray(bundle.rows) ? bundle.rows.slice() : [];
    rows.sort((a, b) => {
        const detailA = String(a.detalle ?? '');
        const detailB = String(b.detalle ?? '');
        return detailA.localeCompare(detailB, 'es', { numeric: true, sensitivity: 'base' });
    });
    currentLineRows = rows;
    currentLineTitle = title || String(bundle.label || 'Detalle');
    currentLineCode = code || String(bundle.codigo_cuenta || '');
    let totalOriginal = 0;
    let totalAjustado = 0;
    rows.forEach(row => {
        totalOriginal += Number(row.total_original || 0);
        totalAjustado += Number(row.total_ajustado || 0);
    });

    detailLineTitle.textContent = currentLineTitle;
    detailLineSubtitle.textContent = currentLineCode ? `Código ${currentLineCode} · ${rows.length} registros` : `${rows.length} registros`;
    detailLineGrid.innerHTML = [
        ['Registros', rows.length],
        ['Importe original', fmtPesosJs(totalOriginal)],
        ['Importe ajustado', fmtPesosJs(totalAjustado)],
        ['Pond. ventas', fmtPctJs(ventasReferencia ? Math.abs(totalAjustado) / Math.abs(ventasReferencia) * 100 : 0)]
    ].map(([l, v]) => `<div><span>${l}</span><strong>${v}</strong></div>`).join('');

    if (!rows.length) {
        detailLineBody.innerHTML = '<tr><td colspan="4" class="detail-empty">Sin datos para mostrar.</td></tr>';
        detailLineExport.disabled = true;
    } else {
        detailLineExport.disabled = false;
        detailLineBody.innerHTML = rows.map(row => `
            <tr>
                <td>${String(row.ot_agrupada ?? 'SIN OT AGRUPADA')}</td>
                <td>${String(row.ot_nombre ?? 'SIN OT NOMBRE')}</td>
                <td class="amount amount-total">${fmtPesosJs(row.total_ajustado)}</td>
                <td>${String(row.detalle ?? 'SIN DETALLE')}</td>
            </tr>
        `).join('');
    }

    detailLineModal.classList.add('open');
    detailLineModal.setAttribute('aria-hidden', 'false');
}

async function exportCurrentLineToExcel() {
    if (!currentLineRows.length) {
        return;
    }
    const suggestedName = (currentLineCode || currentLineTitle || 'detalle')
        .replace(/[\\/:*?"<>|]+/g, '_')
        .replace(/\s+/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_+|_+$/g, '') || 'detalle';
    try {
        const response = await fetch('exportar_detalle_linea_xlsx_v2.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                title: currentLineTitle,
                code: currentLineCode,
                rows: currentLineRows
            })
        });
        if (!response.ok) {
            const errorText = await response.text().catch(() => '');
            throw new Error(errorText || `No se pudo exportar (${response.status}).`);
        }
        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        const downloadLink = document.createElement('a');
        downloadLink.href = objectUrl;
        downloadLink.download = `${suggestedName}.xlsx`;
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        downloadLink.remove();
        setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    } catch (error) {
        console.error(error);
        alert('No se pudo exportar el detalle a Excel.');
    }
}

function toggleGroupBreakdown(button) {
    const key = button.dataset.toggleGroup || '';
    if (!key) {
        return;
    }
    const row = document.getElementById(`breakdown-${key}`);
    if (!row) {
        return;
    }
    const isHidden = row.hasAttribute('hidden');
    if (isHidden) {
        row.removeAttribute('hidden');
        button.setAttribute('aria-expanded', 'true');
        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-plus');
            icon.classList.add('fa-minus');
        }
    } else {
        row.setAttribute('hidden', '');
        button.setAttribute('aria-expanded', 'false');
        const icon = button.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-minus');
            icon.classList.add('fa-plus');
        }
    }
}

periodoBtn.addEventListener('click', () => {
    periodoMenu.classList.toggle('open');
    periodoBtn.setAttribute('aria-expanded', periodoMenu.classList.contains('open') ? 'true' : 'false');
});

document.addEventListener('click', (event) => {
    if (!event.target.closest('.periodo-selector')) {
        periodoMenu.classList.remove('open');
        periodoBtn.setAttribute('aria-expanded', 'false');
    }
});

periodoMenu.querySelectorAll('input[type="checkbox"]').forEach(chk => chk.addEventListener('change', updateButtonLabel));

if (negocioSelect) {
    negocioSelect.addEventListener('change', () => {
        const url = new URL(window.location.href);
        const selected = getSelectedPeriods();
        if (selected.length) {
            url.searchParams.set('periodos', selected.join(','));
        } else {
            url.searchParams.delete('periodos');
        }
        if (negocioSelect.value) {
            url.searchParams.set('negocio', negocioSelect.value);
        } else {
            url.searchParams.delete('negocio');
        }
        window.location.href = url.toString();
    });
}

if (sucursalSelect) {
    sucursalSelect.addEventListener('change', () => {
        const url = new URL(window.location.href);
        const selected = getSelectedPeriods();
        if (selected.length) {
            url.searchParams.set('periodos', selected.join(','));
        } else {
            url.searchParams.delete('periodos');
        }
        if (negocioSelect && negocioSelect.value) {
            url.searchParams.set('negocio', negocioSelect.value);
        } else {
            url.searchParams.delete('negocio');
        }
        if (sucursalSelect.value) {
            url.searchParams.set('sucursal', sucursalSelect.value);
        } else {
            url.searchParams.delete('sucursal');
        }
        window.location.href = url.toString();
    });
}

limpiarPeriodos.addEventListener('click', () => {
    const url = new URL(window.location.href);
    url.searchParams.delete('periodos');
    url.searchParams.delete('periodo');
    url.searchParams.delete('negocio');
    window.location.href = url.toString();
});

aplicarPeriodos.addEventListener('click', () => {
    const selected = getSelectedPeriods();
    const url = new URL(window.location.href);
    if (selected.length) {
        url.searchParams.set('periodos', selected.join(','));
    } else {
        url.searchParams.delete('periodos');
        url.searchParams.delete('periodo');
    }
    if (negocioSelect && negocioSelect.value) {
        url.searchParams.set('negocio', negocioSelect.value);
    } else {
        url.searchParams.delete('negocio');
    }
    if (sucursalSelect && sucursalSelect.value) {
        url.searchParams.set('sucursal', sucursalSelect.value);
    } else {
        url.searchParams.delete('sucursal');
    }
    window.location.href = url.toString();
});

document.addEventListener('click', (event) => {
    const toggleBtn = event.target.closest('[data-toggle-group]');
    if (toggleBtn) {
        toggleGroupBreakdown(toggleBtn);
        return;
    }
    const lineBtn = event.target.closest('[data-line-key]');
    if (lineBtn) {
        openLineDetail(
            lineBtn.dataset.lineKey || '',
            lineBtn.dataset.lineTitle || 'Detalle de línea',
            lineBtn.dataset.lineCode || ''
        );
        return;
    }
});
detailLineClose.addEventListener('click', closeLineDetail);
detailLineExport.addEventListener('click', exportCurrentLineToExcel);
detailLineModal.addEventListener('click', (event) => {
    if (event.target === detailLineModal) {
        closeLineDetail();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeLineDetail();
    }
});
</script>
</body>
</html>

