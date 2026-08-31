<?php
declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/../../config/env_loader.php';

function produccion_tma_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
    exit;
}

function produccion_tma_wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return (string) ($_GET['format'] ?? '') === 'json'
        || str_contains($accept, 'application/json');
}

function produccion_tma_require_optional_token(): void
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
        produccion_tma_json([
            'ok' => false,
            'error' => 'No autorizado.',
        ], 401);
    }
}

function produccion_tma_laboratorio_pdo(): PDO
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

function produccion_tma_periodo(?string $value): ?string
{
    $periodo = preg_replace('/\D+/', '', trim((string) $value));
    if ($periodo === '') {
        return null;
    }
    if (!preg_match('/^20\d{4}$/', $periodo)) {
        produccion_tma_json([
            'ok' => false,
            'error' => 'Parametro periodo invalido. Usar formato AAAAMM, por ejemplo 202608.',
        ], 400);
    }

    $month = (int) substr($periodo, 4, 2);
    if ($month < 1 || $month > 12) {
        produccion_tma_json([
            'ok' => false,
            'error' => 'Parametro periodo invalido. El mes debe estar entre 01 y 12.',
        ], 400);
    }

    return $periodo;
}

function produccion_tma_sucursal(?string $value): ?string
{
    $sucursal = trim((string) $value);
    return $sucursal === '' ? null : $sucursal;
}

function produccion_tma_float(mixed $value): float
{
    return round((float) ($value ?? 0), 4);
}

function produccion_tma_number_fmt(mixed $value, int $decimals = 2): string
{
    return number_format((float) ($value ?? 0), $decimals, ',', '.');
}

function produccion_tma_money_fmt(mixed $value): string
{
    return '$ ' . produccion_tma_number_fmt($value, 2);
}

function produccion_tma_formatted_row(array $row): array
{
    return [
        'periodo' => $row['periodo'] ?? '',
        'contrato' => $row['contrato'] ?? '',
        'sucursal' => $row['sucursal'] ?? '',
        'horas_ptr' => produccion_tma_number_fmt($row['horas_ptr'] ?? 0),
        'horas_ocras' => produccion_tma_number_fmt($row['horas_ocras'] ?? 0),
        'total_horas' => produccion_tma_number_fmt($row['total_horas'] ?? 0),
        'monto' => produccion_tma_money_fmt($row['monto'] ?? 0),
    ];
}

function produccion_tma_warnings(array $rows): array
{
    $warnings = [];
    foreach ($rows as $row) {
        foreach (['horas_ptr', 'horas_ocras', 'total_horas', 'monto'] as $field) {
            $value = (float) ($row[$field] ?? 0);
            if ($value < 0) {
                $warnings[] = [
                    'type' => 'negative_value',
                    'field' => $field,
                    'sucursal' => (string) ($row['sucursal'] ?? ''),
                    'value' => $value,
                ];
            }
        }
    }

    return $warnings;
}

function produccion_tma_render_response(array $response): never
{
    if (produccion_tma_wants_json()) {
        produccion_tma_json($response);
    }

    $viewerData = $response;
    $viewerConfig = [
        'title' => 'API Producción TMA',
        'subtitle' => 'Resumen de respuesta',
        'asset_base' => '../_viewer',
        'columns' => [
            ['field' => 'periodo', 'label' => 'Periodo'],
            ['field' => 'contrato', 'label' => 'Contrato'],
            ['field' => 'sucursal', 'label' => 'Sucursal'],
            ['field' => 'horas_ptr', 'label' => 'Horas PTR', 'kind' => 'number', 'numeric' => true],
            ['field' => 'horas_ocras', 'label' => 'Horas OCRAS', 'kind' => 'number', 'numeric' => true],
            ['field' => 'total_horas', 'label' => 'Total Horas', 'kind' => 'number', 'numeric' => true],
            ['field' => 'monto', 'label' => 'Monto', 'kind' => 'money', 'numeric' => true],
        ],
        'totals' => [
            ['field' => 'horas_ptr', 'label' => 'Horas PTR', 'kind' => 'number', 'suffix' => 'hs', 'tone' => 'blue'],
            ['field' => 'horas_ocras', 'label' => 'Horas OCRAS', 'kind' => 'number', 'suffix' => 'hs', 'tone' => 'orange'],
            ['field' => 'total_horas', 'label' => 'Total Horas', 'kind' => 'number', 'suffix' => 'hs', 'tone' => 'green'],
            ['field' => 'monto', 'label' => 'Monto Total', 'kind' => 'money', 'tone' => 'violet'],
        ],
    ];
    require __DIR__ . '/../_viewer/api_viewer.php';
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        produccion_tma_json([
            'ok' => false,
            'error' => 'Metodo no permitido.',
        ], 405);
    }

    produccion_tma_require_optional_token();

    $periodo = produccion_tma_periodo($_GET['periodo'] ?? null);
    $sucursal = produccion_tma_sucursal($_GET['sucursal'] ?? null);
    $pdo = produccion_tma_laboratorio_pdo();

    $where = [
        "UPPER(TRIM(COALESCE(c_cod_pl_tare_tipo, ''))) IN ('PTRS', 'OCRAS')",
    ];
    $params = [];

    if ($periodo !== null) {
        $where[] = 'periodo = :periodo';
        $params['periodo'] = $periodo;
    }

    if ($sucursal !== null) {
        $where[] = 'UPPER(TRIM(COALESCE(c_sucursal_nombre, \'\'))) = UPPER(TRIM(:sucursal))';
        $params['sucursal'] = $sucursal;
    }

    $statement = $pdo->prepare(
        'SELECT
            periodo,
            COALESCE(NULLIF(TRIM(c_responsable), \'\'), \'SIN CONTRATO\') AS contrato,
            COALESCE(NULLIF(TRIM(c_sucursal_nombre), \'\'), \'SIN SUCURSAL\') AS sucursal,
            SUM(CASE WHEN UPPER(TRIM(COALESCE(c_cod_pl_tare_tipo, \'\'))) = \'PTRS\' THEN COALESCE(n_totalhs_tasa, 0) ELSE 0 END) AS horas_ptr,
            SUM(CASE WHEN UPPER(TRIM(COALESCE(c_cod_pl_tare_tipo, \'\'))) = \'OCRAS\' THEN COALESCE(n_totalhs_tasa, 0) ELSE 0 END) AS horas_ocras,
            SUM(COALESCE(n_totalhs_tasa, 0)) AS total_horas,
            SUM(COALESCE(n_valor_tasa_total, 0)) AS monto
         FROM raw_produccion_planta
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY periodo, contrato, sucursal
         ORDER BY periodo DESC, contrato ASC, sucursal ASC'
    );
    $statement->execute($params);

    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $rows[] = [
            'periodo' => (string) ($row['periodo'] ?? ''),
            'contrato' => (string) ($row['contrato'] ?? ''),
            'sucursal' => (string) ($row['sucursal'] ?? ''),
            'horas_ptr' => produccion_tma_float($row['horas_ptr'] ?? 0),
            'horas_ocras' => produccion_tma_float($row['horas_ocras'] ?? 0),
            'total_horas' => produccion_tma_float($row['total_horas'] ?? 0),
            'monto' => produccion_tma_float($row['monto'] ?? 0),
        ];
    }

    $totals = [
        'horas_ptr' => 0.0,
        'horas_ocras' => 0.0,
        'total_horas' => 0.0,
        'monto' => 0.0,
    ];
    foreach ($rows as $row) {
        $totals['horas_ptr'] += (float) $row['horas_ptr'];
        $totals['horas_ocras'] += (float) $row['horas_ocras'];
        $totals['total_horas'] += (float) $row['total_horas'];
        $totals['monto'] += (float) $row['monto'];
    }
    foreach ($totals as $field => $value) {
        $totals[$field] = produccion_tma_float($value);
    }

    $rowsFormatted = array_map('produccion_tma_formatted_row', $rows);
    $totalsFormatted = [
        'horas_ptr' => produccion_tma_number_fmt($totals['horas_ptr']),
        'horas_ocras' => produccion_tma_number_fmt($totals['horas_ocras']),
        'total_horas' => produccion_tma_number_fmt($totals['total_horas']),
        'monto' => produccion_tma_money_fmt($totals['monto']),
    ];

    $response = [
        'ok' => true,
        'generated_at' => date(DATE_ATOM),
        'filters' => [
            'periodo' => $periodo,
            'sucursal' => $sucursal,
        ],
        'count' => count($rows),
        'totals' => $totals,
        'totals_fmt' => $totalsFormatted,
        'warnings' => produccion_tma_warnings($rows),
        'rows' => $rows,
        'rows_fmt' => $rowsFormatted,
    ];

    produccion_tma_render_response($response);
} catch (Throwable $exception) {
    produccion_tma_json([
        'ok' => false,
        'error' => 'Error al obtener produccion TMA.',
        'debug' => $exception->getMessage(),
    ], 500);
}
