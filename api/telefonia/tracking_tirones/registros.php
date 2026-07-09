<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

try {
    $filters = trackingTironesFilters($_GET);
    $whereSql = $filters['where'];
    $params = $filters['params'];
    $page = max(1, (int) ($_GET['pagina'] ?? 1));
    $pageSize = max(10, min(500, (int) ($_GET['tamano'] ?? TRACKING_TIRONES_PAGE_SIZE)));
    $offset = ($page - 1) * $pageSize;

    $countStatement = $pdo_laboratorio->prepare("
        SELECT COUNT(*) AS total
        FROM raw_toolbox_tracking_tirones
        WHERE $whereSql
    ");
    foreach ($params as $key => $value) {
        $countStatement->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $countStatement->execute();
    $totalRows = (int) ($countStatement->fetchColumn() ?: 0);

    $rows = trackingTironesQueryList($pdo_laboratorio, $whereSql, $params, $pageSize, $offset);

    trackingTironesResponse([
        'ok' => true,
        'filters' => $filters['filters'],
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total_rows' => $totalRows,
            'total_pages' => max(1, (int) ceil($totalRows / max($pageSize, 1))),
        ],
        'data' => $rows,
    ]);
} catch (Throwable $exception) {
    trackingTironesDatabaseError($exception);
}

