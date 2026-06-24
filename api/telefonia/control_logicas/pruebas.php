<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

try {
    $filters = controlLogicasFilters($_GET);
    $page = controlLogicasPage($_GET['pagina'] ?? 1);

    $sql = "
        SELECT
            id,
            external_row_id,
            fecha_hora_origen,
            resultado,
            usuario,
            fibra,
            nro_serie,
            empresa,
            JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tipo_cto')) AS tipo_origen,
            JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.perfil_ont')) AS perfil_ont,
            JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.mensaje')) AS mensaje,
            JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.potencia_optica_tx')) AS potencia_optica_tx,
            JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.dato_cto1')) AS dato_cto1,
            JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.dato_cto2')) AS dato_cto2
        FROM raw_toolbox_cert_cto
        WHERE {$filters['where']}
        ORDER BY fecha_hora_origen DESC, id DESC
    ";

    $statement = $pdo_laboratorio->prepare($sql);
    controlLogicasBind($statement, $filters['params']);
    $statement->execute();

    $periodRows = $statement->fetchAll();
    $filteredRows = array_values(array_filter(
        $periodRows,
        static fn (array $row): bool => controlLogicasFiberMatches(
            $row['fibra'],
            $filters['network'],
            $filters['strand']
        )
    ));

    $total = count($filteredRows);
    $totalPages = max(1, (int) ceil($total / CONTROL_LOGICAS_PAGE_SIZE));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * CONTROL_LOGICAS_PAGE_SIZE;
    $pageRows = array_slice($filteredRows, $offset, CONTROL_LOGICAS_PAGE_SIZE);

    $rows = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'external_row_id' => $row['external_row_id'],
            'date_time' => $row['fecha_hora_origen'],
            'type' => match ($row['tipo_origen']) {
                'HUB' => 'HUB',
                'SPLITTER' => 'CTO',
                default => 'SIN CLASIFICAR',
            },
            'source_type' => $row['tipo_origen'],
            'result' => match ($row['resultado']) {
                'OK' => 'OK',
                'ERROR' => 'NO OK',
                default => 'SIN CLASIFICAR',
            },
            'source_result' => $row['resultado'],
            'network_strand' => $row['fibra'],
            'serial_number' => $row['nro_serie'],
            'user' => $row['usuario'],
            'company' => $row['empresa'],
            'ont_profile' => $row['perfil_ont'],
            'message' => $row['mensaje'],
            'optical_power_tx' => $row['potencia_optica_tx'],
            'reference_1' => $row['dato_cto1'],
            'reference_2' => $row['dato_cto2'],
        ];
    }, $pageRows);

    controlLogicasResponse([
        'ok' => true,
        'filters' => [
            'period' => $filters['period'],
            'network' => $filters['network'],
            'strand' => $filters['strand'],
        ],
        'pagination' => [
            'page' => $page,
            'page_size' => CONTROL_LOGICAS_PAGE_SIZE,
            'total_rows' => $total,
            'total_pages' => $totalPages,
        ],
        'data' => $rows,
    ]);
} catch (Throwable $exception) {
    controlLogicasDatabaseError($exception);
}
