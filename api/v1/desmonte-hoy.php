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
            && ($module['codigo'] ?? '') === 'DESMONTE_HOY'
            && !empty($module['puede_ver'])) {
            $authorized = true;
            break;
        }
    }
    if (!$authorized) {
        throw new HttpError(403, 'No tenés autorización para Desmonte Hoy.', 'FORBIDDEN');
    }

    $required = ['DB_HOSTINGER_LAB_HOST', 'DB_HOSTINGER_LAB_DATABASE', 'DB_HOSTINGER_LAB_USER', 'DB_HOSTINGER_LAB_PASSWORD'];
    $config = [];
    foreach ($required as $key) {
        $config[$key] = (string)lcm_config_value($key, '');
        if ($config[$key] === '') {
            throw new RuntimeException('Falta configuración de la base laboratorio.');
        }
    }
    $port = (string)lcm_config_value('DB_HOSTINGER_LAB_PORT', '3306');
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['DB_HOSTINGER_LAB_HOST'], $port, $config['DB_HOSTINGER_LAB_DATABASE']),
        $config['DB_HOSTINGER_LAB_USER'],
        $config['DB_HOSTINGER_LAB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $today = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT ROUND(COALESCE(SUM(metros), 0), 2) AS metros, COUNT(*) AS tirones
        FROM v_toolbox_tracking_tirones_kg
        WHERE fecha_alta_terreno >= :desde AND fecha_alta_terreno < :hasta
          AND TRIM(tipo_desmonte) = 'subterraneo'");
    $stmt->execute([
        'desde' => $today . ' 00:00:00',
        'hasta' => (new DateTimeImmutable($today, new DateTimeZone('America/Argentina/Buenos_Aires')))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
    ]);
    $row = $stmt->fetch() ?: [];
    lcm_central_json(['ok' => true, 'data' => [
        'fecha' => $today, 'tipo' => 'subterraneo',
        'metros' => (float)($row['metros'] ?? 0),
        'tirones' => (int)($row['tirones'] ?? 0),
        'criterio_fecha' => 'fecha_alta_terreno',
    ]]);
});
