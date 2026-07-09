<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

try {
    $filters = trackingTironesFilters($_GET);
    $whereSql = $filters['where'];
    $params = $filters['params'];

    $totalsStatement = $pdo_laboratorio->prepare("
        SELECT
            COUNT(*) AS total_registros,
            COALESCE(SUM(metros), 0) AS total_metros,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(tipo)) = 'aereo' THEN kg_teoricos ELSE 0 END), 0) AS kg_teoricos_aereo,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(tipo)) = 'subterraneo' THEN kg_teoricos ELSE 0 END), 0) AS kg_teoricos_subte,
            COUNT(DISTINCT central) AS centrales_unicas,
            COUNT(DISTINCT sigest) AS sigest_unicos,
            COUNT(DISTINCT contratista) AS contratistas_unicos,
            COUNT(DISTINCT estado) AS estados_unicos,
            MIN(fecha_alta_terreno) AS fecha_minima,
            MAX(fecha_alta_terreno) AS fecha_maxima
        FROM raw_toolbox_tracking_tirones
        WHERE $whereSql
    ");
    foreach ($params as $key => $value) {
        $totalsStatement->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $totalsStatement->execute();
    $totals = $totalsStatement->fetch() ?: [];

    $centralStatement = $pdo_laboratorio->prepare("
        SELECT
            COALESCE(NULLIF(TRIM(central), ''), 'SIN CENTRAL') AS central,
            COUNT(*) AS total_registros,
            COALESCE(SUM(metros), 0) AS total_metros,
            COALESCE(SUM(kg_teoricos), 0) AS total_kg
        FROM raw_toolbox_tracking_tirones
        WHERE $whereSql
        GROUP BY central
        ORDER BY total_registros DESC, central ASC
        LIMIT 8
    ");
    foreach ($params as $key => $value) {
        $centralStatement->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $centralStatement->execute();

    $estadoStatement = $pdo_laboratorio->prepare("
        SELECT
            COALESCE(NULLIF(TRIM(estado), ''), 'SIN ESTADO') AS estado,
            COUNT(*) AS total_registros,
            COALESCE(SUM(metros), 0) AS total_metros,
            COALESCE(SUM(kg_teoricos), 0) AS total_kg
        FROM raw_toolbox_tracking_tirones
        WHERE $whereSql
        GROUP BY estado
        ORDER BY total_registros DESC, estado ASC
        LIMIT 8
    ");
    foreach ($params as $key => $value) {
        $estadoStatement->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $estadoStatement->execute();

    trackingTironesResponse([
        'ok' => true,
        'filters' => $filters['filters'],
        'data' => [
            'totals' => [
                'total_registros' => (int) ($totals['total_registros'] ?? 0),
                'total_metros' => (float) ($totals['total_metros'] ?? 0),
                'kg_teoricos_aereo' => (float) ($totals['kg_teoricos_aereo'] ?? 0),
                'kg_teoricos_subte' => (float) ($totals['kg_teoricos_subte'] ?? 0),
                'centrales_unicas' => (int) ($totals['centrales_unicas'] ?? 0),
                'sigest_unicos' => (int) ($totals['sigest_unicos'] ?? 0),
                'contratistas_unicos' => (int) ($totals['contratistas_unicos'] ?? 0),
                'estados_unicos' => (int) ($totals['estados_unicos'] ?? 0),
                'fecha_minima' => $totals['fecha_minima'] ?? null,
                'fecha_maxima' => $totals['fecha_maxima'] ?? null,
            ],
            'centrales' => $centralStatement->fetchAll(),
            'estados' => $estadoStatement->fetchAll(),
        ],
    ]);
} catch (Throwable $exception) {
    trackingTironesDatabaseError($exception);
}
