<?php

declare(strict_types=1);

require_once __DIR__ . '/corporate_identity_db.php';
require_once dirname(__DIR__) . '/shared/auth/CorporateAccessRepository.php';

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$profile = $argv[1] ?? '';
if ($profile === '') {
    fwrite(STDERR, "Uso: php tools/test_corporate_access_mysql.php <perfil>\n");
    exit(2);
}

$pdo = corporate_identity_pdo($profile);
$repo = new CorporateAccessRepository($pdo);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$results = [];

$appId = (int)$pdo->query("SELECT id FROM aplicaciones WHERE codigo = 'CABLERAMARPLATENSE'")->fetchColumn();
$moduleId = (int)$pdo->query("SELECT id FROM modulos WHERE codigo = 'TELEFONIA_PRODUCCION_PLANTA' AND aplicacion_id = {$appId}")->fetchColumn();
$jefaturaRoleId = (int)$pdo->query("SELECT id FROM roles WHERE codigo = 'JEFATURA' AND aplicacion_id = {$appId}")->fetchColumn();

test_assert($appId > 0, 'Falta aplicacion CABLERAMARPLATENSE.');
test_assert($moduleId > 0, 'Falta modulo TELEFONIA_PRODUCCION_PLANTA.');
test_assert($jefaturaRoleId > 0, 'Falta rol JEFATURA.');

$pdo->beginTransaction();

try {
    $insertUser = $pdo->prepare(
        'INSERT INTO usuarios (email, nombre, apellido, estado, es_superadmin)
         VALUES (:email, :nombre, :apellido, :estado, :es_superadmin)'
    );

    $insertUser->execute([
        'email' => 'zz_test_superadmin@plantel.local',
        'nombre' => 'Test',
        'apellido' => 'Superadmin',
        'estado' => 'ACTIVO',
        'es_superadmin' => 1,
    ]);
    $superadminId = (int)$pdo->lastInsertId();

    $case = $repo->effectivePermissions($superadminId, 'CABLERAMARPLATENSE', 'TELEFONIA_PRODUCCION_PLANTA');
    test_assert($case['authorized'] === true, 'Caso A fallo: superadmin no autorizado.');
    test_assert($case['permissions'] === CorporateAccess::fullPermissions(), 'Caso A fallo: permisos no son totales.');
    $results['A_superadmin_activo'] = 'OK';

    $missing = $repo->findUserByFirebaseIdentity('uid-inexistente', 'inexistente@plantel.local');
    test_assert($missing === null, 'Caso B fallo: usuario inexistente encontrado.');
    $results['B_usuario_inexistente'] = 'OK';

    $insertUser->execute([
        'email' => 'zz_test_pendiente@plantel.local',
        'nombre' => 'Test',
        'apellido' => 'Pendiente',
        'estado' => 'PENDIENTE',
        'es_superadmin' => 0,
    ]);
    $pendingId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO usuario_aplicacion (usuario_id, aplicacion_id, activo) VALUES (?, ?, 1)')->execute([$pendingId, $appId]);
    $case = $repo->effectivePermissions($pendingId, 'CABLERAMARPLATENSE', 'TELEFONIA_PRODUCCION_PLANTA');
    test_assert($case['authorized'] === false && $case['reason'] === 'inactive_user', 'Caso C fallo.');
    $results['C_usuario_pendiente'] = 'OK';

    $insertUser->execute([
        'email' => 'zz_test_bloqueado@plantel.local',
        'nombre' => 'Test',
        'apellido' => 'Bloqueado',
        'estado' => 'BLOQUEADO',
        'es_superadmin' => 0,
    ]);
    $blockedId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO usuario_aplicacion (usuario_id, aplicacion_id, activo) VALUES (?, ?, 1)')->execute([$blockedId, $appId]);
    $case = $repo->effectivePermissions($blockedId, 'CABLERAMARPLATENSE', 'TELEFONIA_PRODUCCION_PLANTA');
    test_assert($case['authorized'] === false && $case['reason'] === 'inactive_user', 'Caso D fallo.');
    $results['D_usuario_bloqueado'] = 'OK';

    $insertUser->execute([
        'email' => 'zz_test_sin_app@plantel.local',
        'nombre' => 'Test',
        'apellido' => 'Sin App',
        'estado' => 'ACTIVO',
        'es_superadmin' => 0,
    ]);
    $noAppId = (int)$pdo->lastInsertId();
    $case = $repo->effectivePermissions($noAppId, 'CABLERAMARPLATENSE', 'TELEFONIA_PRODUCCION_PLANTA');
    test_assert($case['authorized'] === false && $case['reason'] === 'user_not_authorized_for_application', 'Caso E fallo.');
    $results['E_activo_sin_aplicacion'] = 'OK';

    $insertUser->execute([
        'email' => 'zz_test_jefatura@plantel.local',
        'nombre' => 'Test',
        'apellido' => 'Jefatura',
        'estado' => 'ACTIVO',
        'es_superadmin' => 0,
    ]);
    $jefaturaId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO usuario_aplicacion (usuario_id, aplicacion_id, activo) VALUES (?, ?, 1)')->execute([$jefaturaId, $appId]);
    $pdo->prepare('INSERT INTO usuario_rol (usuario_id, aplicacion_id, rol_id, activo) VALUES (?, ?, ?, 1)')->execute([$jefaturaId, $appId, $jefaturaRoleId]);
    $case = $repo->effectivePermissions($jefaturaId, 'CABLERAMARPLATENSE', 'TELEFONIA_PRODUCCION_PLANTA');
    test_assert($case['permissions']['puede_ver'] === true, 'Caso F fallo: no puede ver.');
    test_assert($case['permissions']['puede_exportar'] === true, 'Caso F fallo: no puede exportar.');
    test_assert($case['permissions']['puede_editar'] === false, 'Caso F fallo: edita sin excepcion.');
    $results['F_rol_modulos_parciales'] = 'OK';

    $pdo->prepare(
        'INSERT INTO usuario_modulo (usuario_id, aplicacion_id, modulo_id, puede_editar, motivo)
         VALUES (?, ?, ?, 1, ?)'
    )->execute([$jefaturaId, $appId, $moduleId, 'Prueba transaccional positiva']);
    $case = $repo->effectivePermissions($jefaturaId, 'CABLERAMARPLATENSE', 'TELEFONIA_PRODUCCION_PLANTA');
    test_assert($case['permissions']['puede_editar'] === true, 'Caso G fallo: excepcion positiva no agrega.');
    $results['G_excepcion_positiva'] = 'OK';

    $pdo->prepare(
        'UPDATE usuario_modulo
         SET puede_exportar = 0
         WHERE usuario_id = ? AND modulo_id = ?'
    )->execute([$jefaturaId, $moduleId]);
    $case = $repo->effectivePermissions($jefaturaId, 'CABLERAMARPLATENSE', 'TELEFONIA_PRODUCCION_PLANTA');
    test_assert($case['permissions']['puede_exportar'] === false, 'Caso H fallo: excepcion negativa no quita.');
    $results['H_excepcion_negativa'] = 'OK';

    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

echo json_encode([
    'ok' => true,
    'profile' => $profile,
    'database' => $database,
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

