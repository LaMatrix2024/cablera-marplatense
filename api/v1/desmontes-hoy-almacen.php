<?php
declare(strict_types=1);
require_once __DIR__ . '/central-auth/bootstrap.php';
lcm_central_run(function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') throw new HttpError(405, 'Método no permitido.', 'METHOD_NOT_ALLOWED');
    $session = (new CentralHostingerTokenService(lcm_central_database()))->session(lcm_central_bearer());
    $profile = $session['profile'] ?? [];
    $authorized = !empty($profile['user']['es_superadmin']);
    foreach (($profile['modules'] ?? []) as $module) {
        if (($module['aplicacion'] ?? '') === 'PLANTEL_MOBILE' && ($module['codigo'] ?? '') === 'DESMONTE_HOY' && !empty($module['permissions']['puede_ver'])) {
            $authorized = true; break;
        }
    }
    if (!$authorized) throw new HttpError(403, 'No tenés autorización para Desmontes de Hoy.', 'FORBIDDEN');
    lcm_load_database_config();
    $pdo = api_crear_conexion_pdo((string) LAB_DB_HOST, (string) LAB_DB_PORT, (string) LAB_DB_NAME, (string) LAB_DB_USER, (string) LAB_DB_PASS);
    $columns = $pdo->query('SHOW COLUMNS FROM v_toolbox_tracking_tirones_kg')->fetchAll(PDO::FETCH_ASSOC);
    $available = array_map(static fn($c) => strtolower((string)$c['Field']), $columns);
    $almacen = null;
    foreach (['almacen', 'almacén', 'nombre_almacen', 'almacen_nombre', 'deposito', 'depósito', 'cod_almacen', 'codigo_almacen'] as $candidate) {
        $index = array_search($candidate, $available, true);
        if ($index !== false) { $almacen = (string)$columns[$index]['Field']; break; }
    }
    if ($almacen === null) throw new HttpError(500, 'No se identificó la columna de almacén en ToolBox.', 'WAREHOUSE_COLUMN_NOT_FOUND');
    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $hoy = new DateTimeImmutable('today', $tz);
    $sql = "SELECT COALESCE(NULLIF(TRIM(`$almacen`), ''), 'Sin almacén') AS almacen, ROUND(COALESCE(SUM(metros), 0), 2) AS metros, COUNT(*) AS tirones FROM v_toolbox_tracking_tirones_kg WHERE fecha_alta_terreno >= :desde AND fecha_alta_terreno < :hasta AND TRIM(tipo_desmonte) = 'subterraneo' GROUP BY COALESCE(NULLIF(TRIM(`$almacen`), ''), 'Sin almacén') ORDER BY metros DESC, almacen ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['desde' => $hoy->format('Y-m-d') . ' 00:00:00', 'hasta' => $hoy->modify('+1 day')->format('Y-m-d') . ' 00:00:00']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $detalle = array_map(static fn($r) => ['almacen' => (string)$r['almacen'], 'metros' => (float)$r['metros'], 'tirones' => (int)$r['tirones']], $rows);
    lcm_central_json(['ok' => true, 'data' => ['fecha' => $hoy->format('Y-m-d'), 'tipo' => 'subterraneo', 'criterio_fecha' => 'fecha_alta_terreno', 'metros' => round(array_sum(array_column($detalle, 'metros')), 2), 'tirones' => array_sum(array_column($detalle, 'tirones')), 'almacenes' => $detalle]]);
});
