<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/conexion.php';

const TRACKING_TIRONES_DEFAULT_DAYS = 30;
const TRACKING_TIRONES_PAGE_SIZE = 100;
const TRACKING_TIRONES_DEFAULT_PERIOD = 'current';

date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function trackingTironesResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function trackingTironesError(string $message, int $status = 400): never
{
    trackingTironesResponse([
        'ok' => false,
        'error' => $message,
    ], $status);
}

function trackingTironesDatabaseError(Throwable $exception): never
{
    error_log('Tracking Tirones | ' . $exception->getMessage());
    trackingTironesError('No se pudo consultar Tracking Tirones.', 500);
}

function trackingTironesNormalizeText(mixed $value): string
{
    return trim((string) $value);
}

function trackingTironesParseDate(string $value): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    foreach (['Y-m-d', 'd/m/Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }
    }

    return null;
}

function trackingTironesFilters(array $input): array
{
    $today = new DateTimeImmutable('today');
    $periodsRaw = trackingTironesNormalizeText($input['periodos'] ?? '');
    $periods = trackingTironesParsePeriods($periodsRaw);
    if ($periods === []) {
        $periods = [$today->format('Y-m')];
    }

    $where = [];
    $params = [];
    $rangeConditions = [];
    if ($periods !== []) {
        foreach ($periods as $index => $period) {
            $start = DateTimeImmutable::createFromFormat('!Y-m', $period);
            if (!$start instanceof DateTimeImmutable) {
                continue;
            }
            $end = $start->modify('first day of next month');
            $startKey = 'periodo_' . $index . '_inicio';
            $endKey = 'periodo_' . $index . '_fin';
            $params[$startKey] = $start->format('Y-m-d');
            $params[$endKey] = $end->format('Y-m-d');
            $rangeConditions[] = "(fecha_alta_terreno >= :$startKey AND fecha_alta_terreno < :$endKey)";
        }
        if (!empty($rangeConditions)) {
            $where[] = '(' . implode(' OR ', $rangeConditions) . ')';
        }
    }

    foreach (['central', 'estado', 'tipo'] as $field) {
        $value = trackingTironesNormalizeText($input[$field] ?? '');
        if ($value === '' || strtoupper($value) === 'TODOS') {
            continue;
        }

        $where[] = "CONVERT(COALESCE(NULLIF(TRIM($field), ''), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(:$field USING utf8mb4) COLLATE utf8mb4_unicode_ci";
        $params[$field] = $value;
    }

    $search = trackingTironesNormalizeText($input['q'] ?? '');
    if ($search !== '') {
        $where[] = '(
            CONVERT(numero_tiron USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(sigest USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(qr_tiron USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(central USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(contratista USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(almacen USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(tecnico_alta USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(tipo USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(tipo_cable USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_unicode_ci
            OR CONVERT(estado USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_unicode_ci
        )';
        $params['q'] = '%' . $search . '%';
    }

    return [
        'where' => implode(' AND ', $where),
        'params' => $params,
        'filters' => [
            'periodos' => $periods,
            'central' => trackingTironesNormalizeText($input['central'] ?? ''),
            'estado' => trackingTironesNormalizeText($input['estado'] ?? ''),
            'tipo' => trackingTironesNormalizeText($input['tipo'] ?? ''),
            'q' => $search,
        ],
    ];
}

function trackingTironesParsePeriods(string $value): array
{
    if ($value === '') {
        return [];
    }

    $periods = [];
    foreach (explode(',', $value) as $item) {
        $period = trim($item);
        if ($period !== '' && preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
            $periods[$period] = $period;
        }
    }

    $periods = array_values($periods);
    rsort($periods, SORT_NATURAL);
    return $periods;
}

function trackingTironesQueryList(PDO $pdo, string $whereSql, array $params, int $limit, int $offset): array
{
    $sql = "
        SELECT
            id,
            batch_id,
            source_row_number,
            source_system,
            source_file,
            tecnico_alta,
            qr_tiron,
            fecha_alta_terreno,
            numero_tiron,
            sigest,
            tipo_cable,
            tipo,
            central,
            numero_cable,
            estado,
            metros,
            kg_teoricos,
            contratista,
            almacen,
            imported_at
        FROM raw_toolbox_tracking_tirones
        WHERE $whereSql
        ORDER BY fecha_alta_terreno DESC, id DESC
        LIMIT $limit OFFSET $offset
    ";

    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $statement->execute();

    return $statement->fetchAll();
}
