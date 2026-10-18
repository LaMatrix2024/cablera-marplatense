<?php
declare(strict_types=1);
require_once __DIR__ . '/central-auth/bootstrap.php';

lcm_central_run(function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        throw new HttpError(405, 'Método no permitido.', 'METHOD_NOT_ALLOWED');
    }
    $session = (new CentralHostingerTokenService(lcm_central_database()))->session(lcm_central_bearer());
    $profile = $session['profile'] ?? [];
    $authorized = !empty($profile['user']['es_superadmin']);
    foreach (($profile['modules'] ?? []) as $module) {
        if (($module['aplicacion'] ?? '') === 'PLANTEL_MOBILE'
            && ($module['codigo'] ?? '') === 'PRODUCCION_ABIERTA'
            && !empty($module['permissions']['puede_ver'])) {
            $authorized = true;
            break;
        }
    }
    if (!$authorized) {
        throw new HttpError(403, 'No tenés autorización para Producción abierta.', 'FORBIDDEN');
    }

    lcm_load_database_config();
    $pdo = api_crear_conexion_pdo((string) LAB_DB_HOST, (string) LAB_DB_PORT,
        (string) LAB_DB_NAME, (string) LAB_DB_USER, (string) LAB_DB_PASS);

    // Una sola consulta: el período y sus sumas pertenecen al mismo snapshot.
    // Sólo 0 + ABIERTO: NULL, desconocidos, bloqueados y cerrados quedan fuera.
    $sql = <<<'SQL'
WITH periodo_abierto AS (
    SELECT MAX(periodo) AS periodo
    FROM raw_produccion_planta
    WHERE periodo REGEXP '^[0-9]{4}(0[1-9]|1[0-2])$'
      AND n_bloqueo_produccion = 0
      AND UPPER(TRIM(bloqueo_estado)) = 'ABIERTO'
      AND UPPER(TRIM(c_cod_pl_tare_tipo)) IN ('OCRAS', 'PTRS')
)
SELECT p.periodo, UPPER(TRIM(r.c_cod_pl_tare_tipo)) AS tipo,
       COALESCE(NULLIF(TRIM(r.c_sucursal), ''), 'SIN_SUCURSAL') AS sucursal,
       MAX(NULLIF(TRIM(r.c_sucursal_nombre), '')) AS sucursal_nombre,
       COUNT(*) AS registros,
       SUM(CASE WHEN r.n_totalhs_tasa IS NULL THEN 1 ELSE 0 END) AS sin_horas,
       CAST(COALESCE(SUM(r.n_totalhs_tasa), 0) AS DECIMAL(20,4)) AS horas
FROM raw_produccion_planta r
JOIN periodo_abierto p ON p.periodo = r.periodo
WHERE r.n_bloqueo_produccion = 0
  AND UPPER(TRIM(r.bloqueo_estado)) = 'ABIERTO'
  AND UPPER(TRIM(r.c_cod_pl_tare_tipo)) IN ('OCRAS', 'PTRS')
GROUP BY p.periodo, UPPER(TRIM(r.c_cod_pl_tare_tipo)),
         COALESCE(NULLIF(TRIM(r.c_sucursal), ''), 'SIN_SUCURSAL')
ORDER BY sucursal, tipo
SQL;
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $tipos = ['OCRAS' => ['horas' => 0.0, 'registros' => 0, 'sin_horas' => 0],
              'PTRS' => ['horas' => 0.0, 'registros' => 0, 'sin_horas' => 0]];
    $sucursales = [];
    foreach ($rows as $row) {
        $tipo = (string) $row['tipo'];
        $horas = (float) $row['horas'];
        $registros = (int) $row['registros'];
        $sinHoras = (int) $row['sin_horas'];
        $tipos[$tipo]['horas'] += $horas;
        $tipos[$tipo]['registros'] += $registros;
        $tipos[$tipo]['sin_horas'] += $sinHoras;
        $sucursales[] = ['codigo' => (string) $row['sucursal'],
            'nombre' => (string) ($row['sucursal_nombre'] ?? $row['sucursal']),
            'tipo' => $tipo, 'horas' => $horas, 'registros' => $registros];
    }
    foreach ($tipos as &$tipo) { $tipo['horas'] = round($tipo['horas'], 4); }
    unset($tipo);

    // La fecha de consulta no se presenta como fecha de actualización del RAW.
    $actualizacion = null;
    try {
        $actualizacion = $pdo->query("SELECT ultima_fecha_hora_actualizacion
            FROM automatizaciones_estado
            WHERE nombre_automatizacion = 'bigstorm_produccion_planta' LIMIT 1")->fetchColumn() ?: null;
    } catch (Throwable $ignored) {
        // Si el registro de carga no está disponible, se informa fecha desconocida.
    }
    lcm_central_json(['ok' => true, 'data' => [
        'periodo' => $rows ? (string) $rows[0]['periodo'] : null,
        'horas' => round($tipos['OCRAS']['horas'] + $tipos['PTRS']['horas'], 4),
        'registros' => $tipos['OCRAS']['registros'] + $tipos['PTRS']['registros'],
        'sin_horas' => $tipos['OCRAS']['sin_horas'] + $tipos['PTRS']['sin_horas'],
        'tipos' => $tipos, 'sucursales' => $sucursales,
        'ultima_actualizacion' => $actualizacion,
        'consultado_at' => (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format(DATE_ATOM),
        'criterio' => 'Último período con OCRAS/PTRS en estado ABIERTO y bloqueo 0.',
    ]]);
});
