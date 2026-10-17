<?php

declare(strict_types=1);

/**
 * Fuente preparada para Proyección de altas.
 *
 * Etapa A: este archivo no está conectado a una ruta pública ni a la vista de
 * NEXO. La Etapa B deberá envolverlo en un endpoint autenticado y cambiar el
 * consumidor sólo después de publicar y conciliar la nueva fuente.
 */

const TOA_PROYECCION_TECNOLOGIAS = [
    'FIBRA',
    'FIBRA+IPTV',
    'FIBRA+IPTV_SD',
    'IPTV',
];

function toa_proyeccion_validar_periodo(string $periodo): string
{
    if (!preg_match('/^\d{6}$/', $periodo)) {
        throw new InvalidArgumentException('El período debe tener formato AAAAMM.');
    }
    $mes = (int) substr($periodo, 4, 2);
    if ($mes < 1 || $mes > 12) {
        throw new InvalidArgumentException('El período contiene un mes inválido.');
    }
    return $periodo;
}

/**
 * @param list<string> $tecnologias
 * @return array{sql: string, params: list<string>}
 */
function toa_proyeccion_filtro_tecnologias(array $tecnologias): array
{
    $normalizadas = [];
    foreach ($tecnologias as $tecnologia) {
        $valor = strtoupper(trim((string) $tecnologia));
        if ($valor === '' || $valor === 'TODOS') {
            continue;
        }
        if (!in_array($valor, TOA_PROYECCION_TECNOLOGIAS, true)) {
            throw new InvalidArgumentException('La tecnología indicada no está permitida.');
        }
        $normalizadas[$valor] = strtolower($valor);
    }
    if (!$normalizadas) {
        return ['sql' => '', 'params' => []];
    }
    $params = array_values($normalizadas);
    return [
        'sql' => ' AND LOWER(TRIM(tecnologia)) IN (' . implode(',', array_fill(0, count($params), '?')) . ')',
        'params' => $params,
    ];
}

/** @return list<string> */
function toa_proyeccion_periodos(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT DISTINCT periodo
         FROM bajada_toa
         WHERE periodo REGEXP '^[0-9]{6}$'
         ORDER BY periodo DESC
         LIMIT 36"
    );
    return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}

/**
 * Conserva el universo anterior: instalaciones completadas y marcadas como alta.
 *
 * @param list<string> $tecnologias
 * @return list<array{periodo:string,zona:string,fecha:string,n:int}>
 */
function toa_proyeccion_historico(
    PDO $pdo,
    string $desde,
    string $hasta,
    array $tecnologias
): array {
    $desde = toa_proyeccion_validar_periodo($desde);
    $hasta = toa_proyeccion_validar_periodo($hasta);
    if ($desde > $hasta) {
        throw new InvalidArgumentException('El período inicial no puede superar al período final.');
    }
    $filtro = toa_proyeccion_filtro_tecnologias($tecnologias);
    $stmt = $pdo->prepare(
        "SELECT periodo, zona22 AS zona,
                DATE_FORMAT(fecha_de_cita, '%Y-%m-%d %H:%i:%s') AS fecha,
                COUNT(*) AS n
         FROM bajada_toa
         WHERE periodo BETWEEN ? AND ?
           AND UPPER(estado_de_la_orden) = 'COMPLETADO'
           AND alta = 1" . $filtro['sql'] . "
         GROUP BY periodo, zona22, fecha_de_cita
         ORDER BY periodo, zona22, fecha_de_cita"
    );
    $stmt->execute(array_merge([$desde, $hasta], $filtro['params']));
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'periodo' => (string) $row['periodo'],
            'zona' => (string) ($row['zona'] ?? ''),
            'fecha' => (string) ($row['fecha'] ?? ''),
            'n' => (int) $row['n'],
        ];
    }
    return $rows;
}

/**
 * @param list<string> $tecnologias
 * @return array<string,int>
 */
function toa_proyeccion_realizadas(PDO $pdo, string $periodo, array $tecnologias): array
{
    $periodo = toa_proyeccion_validar_periodo($periodo);
    $filtro = toa_proyeccion_filtro_tecnologias($tecnologias);
    $stmt = $pdo->prepare(
        "SELECT zona22 AS zona, COUNT(*) AS realizadas
         FROM bajada_toa
         WHERE periodo = ?
           AND UPPER(estado_de_la_orden) = 'COMPLETADO'
           AND alta = 1" . $filtro['sql'] . "
         GROUP BY zona22"
    );
    $stmt->execute(array_merge([$periodo], $filtro['params']));
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[(string) ($row['zona'] ?? '')] = (int) $row['realizadas'];
    }
    return $result;
}

/** @return array{periodo:string,fecha_publicacion:?string,filas_publicadas:int}|null */
function toa_proyeccion_ultima_publicacion(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        "SELECT periodo, fecha_publicacion, filas_publicadas
         FROM bajada_toa_importaciones
         WHERE estado = 'PUBLICADA' AND fecha_publicacion IS NOT NULL
         ORDER BY fecha_publicacion DESC, id DESC
         LIMIT 1"
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'periodo' => (string) $row['periodo'],
        'fecha_publicacion' => $row['fecha_publicacion'] !== null
            ? (string) $row['fecha_publicacion']
            : null,
        'filas_publicadas' => (int) $row['filas_publicadas'],
    ];
}
