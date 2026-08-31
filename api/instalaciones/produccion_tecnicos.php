<?php
declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/../../config/env_loader.php';

function api_tecnicos_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
    exit;
}

function api_tecnicos_wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return (string) ($_GET['format'] ?? '') === 'json'
        || str_contains($accept, 'application/json');
}

function api_tecnicos_laboratorio_pdo(): PDO
{
    lcm_load_database_config();

    foreach (['LAB_DB_HOST', 'LAB_DB_PORT', 'LAB_DB_NAME', 'LAB_DB_USER'] as $constant) {
        if (!defined($constant) || trim((string) constant($constant)) === '') {
            throw new RuntimeException('Falta configurar ' . $constant . '.');
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        LAB_DB_HOST,
        LAB_DB_PORT,
        LAB_DB_NAME
    );

    return new PDO($dsn, LAB_DB_USER, defined('LAB_DB_PASS') ? LAB_DB_PASS : '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function api_tecnicos_require_optional_token(): void
{
    $expected = trim((string) getenv('ADMINOBRAS_API_TOKEN'));
    if ($expected === '') {
        return;
    }

    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }

    $prefix = 'Bearer ';
    $received = str_starts_with($header, $prefix) ? substr($header, strlen($prefix)) : '';
    if (!hash_equals($expected, $received)) {
        api_tecnicos_json(['ok' => false, 'error' => 'No autorizado.'], 401);
    }
}

function api_tecnicos_period(?string $value, string $default, string $label): string
{
    $period = trim((string) ($value ?? ''));
    if ($period === '') {
        $period = $default;
    }
    if (!preg_match('/^\d{6}$/', $period)) {
        api_tecnicos_json(['ok' => false, 'error' => "Parametro $label invalido. Usar formato AAAAMM."], 400);
    }

    $month = (int) substr($period, 4, 2);
    if ($month < 1 || $month > 12) {
        api_tecnicos_json(['ok' => false, 'error' => "Parametro $label invalido. El mes debe estar entre 01 y 12."], 400);
    }

    return $period;
}

function api_tecnicos_periods(string $desde, string $hasta): array
{
    if ($desde > $hasta) {
        api_tecnicos_json(['ok' => false, 'error' => 'El parametro desde no puede ser mayor que hasta.'], 400);
    }

    $year = (int) substr($desde, 0, 4);
    $month = (int) substr($desde, 4, 2);
    $periods = [];
    while (true) {
        $period = sprintf('%04d%02d', $year, $month);
        $periods[] = $period;
        if ($period === $hasta) {
            break;
        }
        $month++;
        if ($month === 13) {
            $month = 1;
            $year++;
        }
        if (count($periods) > 120) {
            api_tecnicos_json(['ok' => false, 'error' => 'Rango de periodos demasiado amplio.'], 400);
        }
    }

    return $periods;
}

function api_tecnicos_array_param(string $key): array
{
    $value = $_GET[$key] ?? [];
    if (!is_array($value)) {
        $value = [$value];
    }

    $items = [];
    foreach ($value as $item) {
        $text = trim((string) $item);
        if ($text !== '') {
            $items[] = $text;
        }
    }

    return array_values(array_unique($items));
}

function api_tecnicos_in_clause(string $expression, array $values, array &$params, string $prefix): string
{
    if (!$values) {
        return '';
    }

    $placeholders = [];
    foreach ($values as $index => $value) {
        $name = $prefix . $index;
        $placeholders[] = ':' . $name;
        $params[$name] = $value;
    }

    return ' AND UPPER(TRIM(' . $expression . ')) IN (' . implode(', ', array_map(static fn (string $name): string => 'UPPER(TRIM(' . $name . '))', $placeholders)) . ')';
}

function api_tecnicos_int(mixed $value): int
{
    return (int) ($value ?? 0);
}

function api_tecnicos_float(mixed $value): float
{
    return round((float) ($value ?? 0), 2);
}

function api_tecnicos_date_key(mixed $value): ?string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return null;
    }

    $text = substr($text, 0, 10);
    foreach (['Y-m-d', 'd/m/Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $text);
        if ($date instanceof DateTimeImmutable && $date->format($format) === $text) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

function api_tecnicos_normalize_key(string $value): string
{
    return mb_strtoupper(trim($value), 'UTF-8');
}

function api_tecnicos_distrito(string $contrato, string $central, array $districtConfig): string
{
    $fallback = (string) ($districtConfig['fallback'] ?? 'SIN DISTRITO');
    $map = $districtConfig['centrales'] ?? [];
    $contractMap = $map[$contrato] ?? [];
    if (isset($contractMap[$central])) {
        return (string) $contractMap[$central];
    }

    $centralKey = api_tecnicos_normalize_key($central);
    foreach ($contractMap as $knownCentral => $district) {
        if (api_tecnicos_normalize_key((string) $knownCentral) === $centralKey) {
            return (string) $district;
        }
    }

    return $fallback;
}

function api_tecnicos_log(Throwable $exception): void
{
    $dir = __DIR__ . '/../../logs';
    if (is_dir($dir) || @mkdir($dir, 0775, true)) {
        @error_log(date('Y-m-d H:i:s') . ' | produccion_tecnicos | ' . $exception->getMessage() . PHP_EOL, 3, $dir . '/errores.log');
    }
}

function api_tecnicos_render(array $response): never
{
    if (api_tecnicos_wants_json()) {
        api_tecnicos_json($response);
    }

    $viewerData = $response;
    $viewerConfig = [
        'title' => 'API Producción Técnicos',
        'subtitle' => 'Instalaciones por técnico',
        'asset_base' => '../_viewer',
        'columns' => [
            ['field' => 'tecnico', 'label' => 'Técnico'],
            ['field' => 'total', 'label' => 'Total', 'kind' => 'number', 'numeric' => true, 'decimals' => 0],
            ['field' => 'promedio_mensual', 'label' => 'Prom./mes', 'kind' => 'number', 'numeric' => true, 'decimals' => 1],
            ['field' => 'ultimo_mes', 'label' => 'Último mes', 'kind' => 'number', 'numeric' => true, 'decimals' => 0],
        ],
        'totals' => [
            ['field' => 'instalaciones', 'label' => 'Instalaciones', 'kind' => 'number', 'tone' => 'green'],
            ['field' => 'tecnicos_activos', 'label' => 'Técnicos activos', 'kind' => 'number', 'tone' => 'blue'],
            ['field' => 'promedio_tecnico', 'label' => 'Promedio por técnico', 'kind' => 'number', 'tone' => 'violet'],
        ],
    ];
    $viewerData['totals'] = [
        'instalaciones' => $response['summary']['instalaciones'] ?? 0,
        'tecnicos_activos' => $response['summary']['tecnicos_activos'] ?? 0,
        'promedio_tecnico' => $response['summary']['promedio_tecnico'] ?? 0,
    ];
    require __DIR__ . '/../_viewer/api_viewer.php';
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        api_tecnicos_json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
    }

    api_tecnicos_require_optional_token();

    $defaultHasta = date('Ym');
    $desde = api_tecnicos_period($_GET['desde'] ?? null, '202601', 'desde');
    $hasta = api_tecnicos_period($_GET['hasta'] ?? null, $defaultHasta, 'hasta');
    $periodos = api_tecnicos_periods($desde, $hasta);
    $contratos = api_tecnicos_array_param('contrato');
    $distritos = api_tecnicos_array_param('distrito');
    $centrales = api_tecnicos_array_param('central');
    $tecnicos = api_tecnicos_array_param('tecnico');
    $districtConfig = require __DIR__ . '/../../config/toa_distritos.php';

    $tecnicoExpr = "JSON_UNQUOTE(JSON_EXTRACT(datos_json, '$.tecnico'))";
    $centralExpr = "JSON_UNQUOTE(JSON_EXTRACT(datos_json, '$.cent_descrip_cota'))";
    $contratoExpr = "JSON_UNQUOTE(JSON_EXTRACT(datos_json, '$.zona22'))";
    $fechaCitaExpr = "LEFT(JSON_UNQUOTE(JSON_EXTRACT(datos_json, '$.fecha_de_cita')), 10)";

    $params = [
        'desde' => $desde,
        'hasta' => $hasta,
    ];
    $whereFilters = '';
    $whereFilters .= api_tecnicos_in_clause($contratoExpr, $contratos, $params, 'contrato_');
    $whereFilters .= api_tecnicos_in_clause($centralExpr, $centrales, $params, 'central_');
    $whereFilters .= api_tecnicos_in_clause($tecnicoExpr, $tecnicos, $params, 'tecnico_');

    $baseWhere = "
        periodo BETWEEN :desde AND :hasta
        AND JSON_VALID(datos_json) = 1
        AND $tecnicoExpr IS NOT NULL
        AND TRIM($tecnicoExpr) <> ''
    ";

    $pdo = api_tecnicos_laboratorio_pdo();
    $startedAt = microtime(true);

    $statement = $pdo->prepare(
        "SELECT
            periodo,
            $tecnicoExpr AS tecnico,
            COALESCE(NULLIF(TRIM($centralExpr), ''), 'SIN CENTRAL') AS central,
            COALESCE(NULLIF(TRIM($contratoExpr), ''), 'SIN CONTRATO') AS contrato,
            $fechaCitaExpr AS fecha_cita,
            COUNT(*) AS instalaciones
         FROM raw_dataflow_toa_instalaciones
         WHERE $baseWhere
         $whereFilters
         GROUP BY periodo, tecnico, central, contrato, fecha_cita"
    );
    $statement->execute($params);
    $detailRows = $statement->fetchAll();

    $optionParams = [
        'desde' => $desde,
        'hasta' => $hasta,
    ];
    $optionFilters = api_tecnicos_in_clause($contratoExpr, $contratos, $optionParams, 'opt_contrato_');

    $optionStatement = $pdo->prepare(
        "SELECT
            $tecnicoExpr AS tecnico,
            COALESCE(NULLIF(TRIM($centralExpr), ''), 'SIN CENTRAL') AS central,
            COALESCE(NULLIF(TRIM($contratoExpr), ''), 'SIN CONTRATO') AS contrato,
            COUNT(*) AS instalaciones
         FROM raw_dataflow_toa_instalaciones
         WHERE $baseWhere
         $optionFilters
         GROUP BY tecnico, central, contrato"
    );
    $optionStatement->execute($optionParams);
    $optionRows = $optionStatement->fetchAll();

    $qualityStatement = $pdo->prepare(
        "SELECT
            COUNT(*) AS registros_desde,
            SUM(CASE WHEN JSON_VALID(datos_json) = 0 THEN 1 ELSE 0 END) AS json_invalido,
            COUNT(DISTINCT periodo) AS periodos_disponibles,
            SUM(CASE WHEN JSON_VALID(datos_json) = 1 AND ($tecnicoExpr IS NULL OR TRIM($tecnicoExpr) = '') THEN 1 ELSE 0 END) AS tecnicos_vacios
         FROM raw_dataflow_toa_instalaciones
         WHERE periodo BETWEEN :desde AND :hasta"
    );
    $qualityStatement->execute(['desde' => $desde, 'hasta' => $hasta]);
    $quality = $qualityStatement->fetch() ?: [];

    $byTechnician = [];
    foreach ($detailRows as $row) {
        $tecnico = (string) $row['tecnico'];
        $periodo = (string) $row['periodo'];
        $central = (string) $row['central'];
        $contrato = (string) $row['contrato'];
        $distrito = api_tecnicos_distrito($contrato, $central, $districtConfig);
        if ($distritos && !in_array($distrito, $distritos, true)) {
            continue;
        }
        $fechaCita = trim((string) ($row['fecha_cita'] ?? ''));
        $count = api_tecnicos_int($row['instalaciones']);

        if (!isset($byTechnician[$tecnico])) {
            $byTechnician[$tecnico] = [
                'tecnico' => $tecnico,
                'total' => 0,
                'promedio_mensual' => 0,
                'ultimo_mes' => 0,
                'dias_trabajados' => 0,
                'promedio_diario' => 0,
                'meses' => array_fill_keys($periodos, 0),
                '_dias' => [],
                '_centrales' => [],
                '_contratos' => [],
                '_distritos' => [],
                '_distritos_detalle' => [],
            ];
        }

        if (!isset($byTechnician[$tecnico]['_distritos_detalle'][$distrito])) {
            $byTechnician[$tecnico]['_distritos_detalle'][$distrito] = [
                'distrito' => $distrito,
                'total' => 0,
                'promedio_mensual' => 0,
                'ultimo_mes' => 0,
                'dias_trabajados' => 0,
                'promedio_diario' => 0,
                'meses' => array_fill_keys($periodos, 0),
                '_dias' => [],
            ];
        }

        $byTechnician[$tecnico]['total'] += $count;
        $byTechnician[$tecnico]['meses'][$periodo] += $count;
        $byTechnician[$tecnico]['_distritos_detalle'][$distrito]['total'] += $count;
        $byTechnician[$tecnico]['_distritos_detalle'][$distrito]['meses'][$periodo] += $count;
        $dateKey = api_tecnicos_date_key($fechaCita);
        if ($dateKey !== null) {
            $byTechnician[$tecnico]['_dias'][$dateKey] = true;
            $byTechnician[$tecnico]['_distritos_detalle'][$distrito]['_dias'][$dateKey] = true;
        }
        $byTechnician[$tecnico]['_centrales'][$central] = ($byTechnician[$tecnico]['_centrales'][$central] ?? 0) + $count;
        $byTechnician[$tecnico]['_contratos'][$contrato] = ($byTechnician[$tecnico]['_contratos'][$contrato] ?? 0) + $count;
        $byTechnician[$tecnico]['_distritos'][$distrito] = ($byTechnician[$tecnico]['_distritos'][$distrito] ?? 0) + $count;
    }

    $rows = [];
    $lastPeriod = $periodos[count($periodos) - 1] ?? $hasta;
    $monthCount = max(count($periodos), 1);
    foreach ($byTechnician as $item) {
        arsort($item['_centrales']);
        arsort($item['_contratos']);
        arsort($item['_distritos']);
        $centralesList = [];
        foreach ($item['_centrales'] as $central => $count) {
            $centralesList[] = ['central' => $central, 'instalaciones' => $count];
        }
        $contratosList = [];
        foreach ($item['_contratos'] as $contrato => $count) {
            $contratosList[] = ['contrato' => $contrato, 'instalaciones' => $count];
        }
        $distritosList = [];
        foreach ($item['_distritos'] as $distrito => $count) {
            $distritosList[] = ['distrito' => $distrito, 'instalaciones' => $count];
        }
        $distritosDetalleList = [];
        foreach ($item['_distritos_detalle'] as $detalle) {
            $districtDays = count($detalle['_dias']);
            $distritosDetalleList[] = [
                'distrito' => $detalle['distrito'],
                'total' => $detalle['total'],
                'promedio_mensual' => api_tecnicos_float($detalle['total'] / $monthCount),
                'ultimo_mes' => $detalle['meses'][$lastPeriod] ?? 0,
                'dias_trabajados' => $districtDays,
                'promedio_diario' => $districtDays ? api_tecnicos_float($detalle['total'] / $districtDays) : 0,
                'meses' => $detalle['meses'],
            ];
        }
        usort($distritosDetalleList, static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['distrito'], $b['distrito']));

        $rows[] = [
            'tecnico' => $item['tecnico'],
            'total' => $item['total'],
            'promedio_mensual' => api_tecnicos_float($item['total'] / $monthCount),
            'ultimo_mes' => $item['meses'][$lastPeriod] ?? 0,
            'dias_trabajados' => count($item['_dias']),
            'promedio_diario' => count($item['_dias']) ? api_tecnicos_float($item['total'] / count($item['_dias'])) : 0,
            'meses' => $item['meses'],
            'distritos' => $distritosList,
            'distritos_detalle' => $distritosDetalleList,
            'centrales' => $centralesList,
            'contratos' => $contratosList,
        ];
    }

    usort($rows, static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['tecnico'], $b['tecnico']));

    $totalInstalaciones = array_sum(array_column($rows, 'total'));
    $leader = $rows[0] ?? ['tecnico' => '', 'total' => 0];
    $options = [
        'contratos' => [],
        'distritos' => [],
        'centrales' => [],
        'tecnicos' => [],
    ];
    foreach ($optionRows as $row) {
        $contrato = (string) $row['contrato'];
        $central = (string) $row['central'];
        $distrito = api_tecnicos_distrito($contrato, $central, $districtConfig);
        $options['contratos'][$contrato] = true;
        $options['distritos'][$distrito] = true;
        if ($distritos && !in_array($distrito, $distritos, true)) {
            continue;
        }
        $options['centrales'][$central] = true;
        $options['tecnicos'][(string) $row['tecnico']] = true;
    }
    foreach ($options as $key => $values) {
        $items = array_keys($values);
        sort($items, SORT_NATURAL | SORT_FLAG_CASE);
        $options[$key] = $items;
    }

    $response = [
        'ok' => true,
        'generated_at' => date(DATE_ATOM),
        'filters' => [
            'desde' => $desde,
            'hasta' => $hasta,
            'contratos' => $contratos,
            'distritos' => $distritos,
            'centrales' => $centrales,
            'tecnicos' => $tecnicos,
        ],
        'periodos' => $periodos,
        'summary' => [
            'instalaciones' => $totalInstalaciones,
            'tecnicos_activos' => count($rows),
            'promedio_tecnico' => count($rows) ? api_tecnicos_float($totalInstalaciones / count($rows)) : 0,
            'lider' => [
                'tecnico' => (string) ($leader['tecnico'] ?? ''),
                'instalaciones' => api_tecnicos_int($leader['total'] ?? 0),
                'porcentaje_total' => $totalInstalaciones ? api_tecnicos_float((($leader['total'] ?? 0) / $totalInstalaciones) * 100) : 0,
            ],
        ],
        'options' => $options,
        'quality' => [
            'registros_periodo' => api_tecnicos_int($quality['registros_desde'] ?? 0),
            'tecnicos_distintos_validos' => count($options['tecnicos']),
            'periodos_disponibles' => api_tecnicos_int($quality['periodos_disponibles'] ?? 0),
            'contratos_distintos' => count($options['contratos']),
            'distritos_distintos' => count($options['distritos']),
            'centrales_distintas' => count($options['centrales']),
            'json_invalido' => api_tecnicos_int($quality['json_invalido'] ?? 0),
            'tecnicos_vacios' => api_tecnicos_int($quality['tecnicos_vacios'] ?? 0),
            'query_ms' => api_tecnicos_float((microtime(true) - $startedAt) * 1000),
        ],
        'rows' => $rows,
    ];

    api_tecnicos_render($response);
} catch (Throwable $exception) {
    api_tecnicos_log($exception);
    api_tecnicos_json([
        'ok' => false,
        'error' => 'No fue posible cargar los datos.',
    ], 500);
}
