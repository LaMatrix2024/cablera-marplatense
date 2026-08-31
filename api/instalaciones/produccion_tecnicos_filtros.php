<?php
declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/../../config/env_loader.php';

function api_filtros_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function api_filtros_pdo(): PDO
{
    lcm_load_database_config();
    $dsn = 'mysql:host=' . LAB_DB_HOST . ';port=' . LAB_DB_PORT . ';dbname=' . LAB_DB_NAME . ';charset=utf8mb4';
    return new PDO($dsn, LAB_DB_USER, LAB_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function api_filtros_period(?string $value, string $default, string $label): string
{
    $period = trim((string) ($value ?? '')) ?: $default;
    if (!preg_match('/^\d{6}$/', $period)) {
        api_filtros_json(['ok' => false, 'error' => "Parametro $label invalido. Usar formato AAAAMM."], 400);
    }
    $month = (int) substr($period, 4, 2);
    if ($month < 1 || $month > 12) {
        api_filtros_json(['ok' => false, 'error' => "Parametro $label invalido. El mes debe estar entre 01 y 12."], 400);
    }
    return $period;
}

function api_filtros_array_param(string $key): array
{
    $value = $_GET[$key] ?? [];
    if (!is_array($value)) {
        $value = [$value];
    }
    return array_values(array_unique(array_filter(array_map(
        static fn (mixed $item): string => trim((string) $item),
        $value
    ), static fn (string $item): bool => $item !== '')));
}

function api_filtros_in_clause(string $expression, array $values, array &$params, string $prefix): string
{
    if (!$values) {
        return '';
    }
    $placeholders = [];
    foreach ($values as $index => $value) {
        $key = $prefix . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $value;
    }
    return ' AND ' . $expression . ' IN (' . implode(', ', $placeholders) . ') ';
}

function api_filtros_normalize_key(string $value): string
{
    return mb_strtoupper(trim($value), 'UTF-8');
}

function api_filtros_distrito(string $contrato, string $central, array $districtConfig): string
{
    $fallback = (string) ($districtConfig['fallback'] ?? 'SIN DISTRITO');
    $contractMap = $districtConfig['centrales'][$contrato] ?? [];
    if (isset($contractMap[$central])) {
        return (string) $contractMap[$central];
    }
    $centralKey = api_filtros_normalize_key($central);
    foreach ($contractMap as $knownCentral => $district) {
        if (api_filtros_normalize_key((string) $knownCentral) === $centralKey) {
            return (string) $district;
        }
    }
    return $fallback;
}

try {
    $startedAt = microtime(true);
    $desde = api_filtros_period($_GET['desde'] ?? null, '202601', 'desde');
    $hasta = api_filtros_period($_GET['hasta'] ?? null, date('Ym'), 'hasta');
    if ($desde > $hasta) {
        api_filtros_json(['ok' => false, 'error' => 'El parametro desde no puede ser mayor que hasta.'], 400);
    }

    $contratos = api_filtros_array_param('contrato');
    $distritos = api_filtros_array_param('distrito');
    $districtConfig = require __DIR__ . '/../../config/toa_distritos.php';

    $tecnicoExpr = "TRIM(JSON_UNQUOTE(JSON_EXTRACT(datos_json, '$.tecnico')))";
    $centralExpr = "COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(datos_json, '$.cent_descrip_cota'))), ''), 'SIN CENTRAL')";
    $contratoExpr = "COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(datos_json, '$.zona22'))), ''), 'SIN CONTRATO')";

    $params = ['desde' => $desde, 'hasta' => $hasta];
    $filters = api_filtros_in_clause($contratoExpr, $contratos, $params, 'contrato_');

    $pdo = api_filtros_pdo();
    $statement = $pdo->prepare(
        "SELECT
            $tecnicoExpr AS tecnico,
            $centralExpr AS central,
            $contratoExpr AS contrato,
            COUNT(*) AS instalaciones
         FROM raw_dataflow_toa_instalaciones
         WHERE periodo BETWEEN :desde AND :hasta
           AND JSON_VALID(datos_json) = 1
           AND $tecnicoExpr IS NOT NULL
           AND $tecnicoExpr <> ''
           $filters
         GROUP BY tecnico, central, contrato"
    );
    $statement->execute($params);

    $options = [
        'contratos' => [],
        'distritos' => [],
        'centrales' => [],
        'tecnicos' => [],
    ];
    foreach ($statement->fetchAll() as $row) {
        $contrato = (string) $row['contrato'];
        $central = (string) $row['central'];
        $distrito = api_filtros_distrito($contrato, $central, $districtConfig);
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

    api_filtros_json([
        'ok' => true,
        'generated_at' => date(DATE_ATOM),
        'filters' => [
            'desde' => $desde,
            'hasta' => $hasta,
            'contratos' => $contratos,
            'distritos' => $distritos,
        ],
        'options' => $options,
        'query_ms' => round((microtime(true) - $startedAt) * 1000, 2),
    ]);
} catch (Throwable $exception) {
    api_filtros_json(['ok' => false, 'error' => 'No fue posible cargar los filtros.'], 500);
}
