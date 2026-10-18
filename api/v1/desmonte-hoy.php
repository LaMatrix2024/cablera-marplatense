<?php
declare(strict_types=1);
require_once __DIR__ . '/central-auth/bootstrap.php';

lcm_central_run(function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        throw new HttpError(405, 'Método no permitido.', 'METHOD_NOT_ALLOWED');
    }
    $session = (new CentralHostingerTokenService(lcm_central_database()))->session(lcm_central_bearer());
    $profile = $session['profile'] ?? [];
    $modules = $profile['modules'] ?? [];
    $authorized = !empty($profile['user']['es_superadmin']);
    foreach ($modules as $module) {
        if (($module['aplicacion'] ?? '') === 'PLANTEL_MOBILE'
            && in_array(($module['codigo'] ?? ''), ['DESMONTE_HOY', 'ULTIMO_DESMONTE'], true)
            && !empty($module['permissions']['puede_ver'])) {
            $authorized = true;
            break;
        }
    }
    if (!$authorized) {
        throw new HttpError(403, 'No tenés autorización para Desmonte Hoy.', 'FORBIDDEN');
    }

    lcm_load_database_config();
    $pdo = api_crear_conexion_pdo(
        (string) LAB_DB_HOST,
        (string) LAB_DB_PORT,
        (string) LAB_DB_NAME,
        (string) LAB_DB_USER,
        (string) LAB_DB_PASS
    );
    $ayer = (new DateTimeImmutable('today', new DateTimeZone('America/Argentina/Buenos_Aires')))->modify('-1 day')->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT ROUND(COALESCE(SUM(metros), 0), 2) AS metros, COUNT(*) AS tirones
        FROM v_toolbox_tracking_tirones_kg
        WHERE fecha_alta_terreno >= :desde AND fecha_alta_terreno < :hasta
          AND TRIM(tipo_desmonte) = 'subterraneo'");
    $stmt->execute([
        'desde' => $ayer . ' 00:00:00',
        'hasta' => (new DateTimeImmutable($ayer, new DateTimeZone('America/Argentina/Buenos_Aires')))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
    ]);
    $row = $stmt->fetch() ?: [];
    lcm_central_json(['ok' => true, 'data' => [
        'fecha' => $ayer, 'tipo' => 'subterraneo',
        'metros' => (float)($row['metros'] ?? 0),
        'tirones' => (int)($row['tirones'] ?? 0),
        'criterio_fecha' => 'fecha_alta_terreno',
    ]]);
});
