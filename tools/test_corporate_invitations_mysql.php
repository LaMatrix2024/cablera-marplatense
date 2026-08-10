<?php

declare(strict_types=1);

require_once __DIR__ . '/corporate_identity_db.php';
require_once dirname(__DIR__) . '/shared/auth/CorporateAccessRepository.php';
require_once dirname(__DIR__) . '/shared/auth/CorporateInvitationService.php';
require_once dirname(__DIR__) . '/shared/auth/HttpError.php';

function inv_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function inv_expect_error(callable $callback, int $status, string $code): void
{
    try {
        $callback();
    } catch (HttpError $error) {
        inv_assert($error->status() === $status, 'Status esperado ' . $status . ', recibido ' . $error->status());
        inv_assert($error->errorCode() === $code, 'Codigo esperado ' . $code . ', recibido ' . $error->errorCode());
        return;
    }

    throw new RuntimeException('Se esperaba error ' . $code . '.');
}

function inv_cleanup(PDO $pdo, string $prefix): void
{
    $like = $prefix . '%@plantel.local';
    $userIds = $pdo->prepare('SELECT id FROM usuarios WHERE email LIKE :like');
    $userIds->execute(['like' => $like]);
    $users = array_map('intval', array_column($userIds->fetchAll(), 'id'));

    $invitationIds = $pdo->prepare('SELECT id FROM invitaciones_usuario WHERE email LIKE :like');
    $invitationIds->execute(['like' => $like]);
    $invitations = array_map('intval', array_column($invitationIds->fetchAll(), 'id'));

    if ($invitations !== []) {
        $placeholders = implode(',', array_fill(0, count($invitations), '?'));
        foreach (['invitacion_modulo', 'invitacion_rol', 'invitacion_aplicacion'] as $table) {
            $pdo->prepare('DELETE FROM ' . $table . ' WHERE invitacion_id IN (' . $placeholders . ')')->execute($invitations);
        }
        $pdo->prepare('DELETE FROM invitaciones_usuario WHERE id IN (' . $placeholders . ')')->execute($invitations);
    }

    if ($users !== []) {
        $placeholders = implode(',', array_fill(0, count($users), '?'));
        $pdo->prepare('DELETE FROM identidad_conflictos WHERE usuario_id IN (' . $placeholders . ')')->execute($users);
        foreach (['usuario_modulo', 'usuario_rol', 'usuario_aplicacion'] as $table) {
            $pdo->prepare('DELETE FROM ' . $table . ' WHERE usuario_id IN (' . $placeholders . ')')->execute($users);
        }
        $pdo->prepare('DELETE FROM usuarios WHERE id IN (' . $placeholders . ')')->execute($users);
    }
}

function inv_payload(string $email, array $roles = ['JEFATURA'], array $modules = []): array
{
    return [
        'email' => $email,
        'expires_in_hours' => 72,
        'applications' => [
            [
                'codigo' => 'CABLERAMARPLATENSE',
                'roles' => $roles,
                'modules' => $modules,
            ],
        ],
    ];
}

$profile = $argv[1] ?? '';
if ($profile === '') {
    fwrite(STDERR, "Uso: php tools/test_corporate_invitations_mysql.php <perfil>\n");
    exit(2);
}

$pdo = corporate_identity_pdo($profile);
$service = new CorporateInvitationService($pdo);
$repo = new CorporateAccessRepository($pdo);
$prefix = 'zz_inv_' . substr(hash('sha1', $profile . microtime(true)), 0, 8);
$results = [];

$adminId = (int)$pdo->query("SELECT id FROM usuarios WHERE email = 'aguileraclaudiomdq@gmail.com'")->fetchColumn();
inv_assert($adminId > 0, 'Falta superadmin.');

try {
    inv_cleanup($pdo, 'zz_inv_');

    $email01 = $prefix . '_01@plantel.local';
    $invite01 = $service->createInvitation(inv_payload($email01), $adminId);
    $row = $pdo->query("SELECT estado FROM usuarios WHERE email = " . $pdo->quote($email01))->fetch();
    inv_assert(is_array($row) && $row['estado'] === 'PENDIENTE', 'INV-01 usuario PENDIENTE fallo.');
    $row = $pdo->query('SELECT estado FROM invitaciones_usuario WHERE id = ' . (int)$invite01['id'])->fetch();
    inv_assert($row['estado'] === 'PENDIENTE', 'INV-01 invitacion PENDIENTE fallo.');
    $results['INV-01'] = 'OK';

    $accepted = $service->acceptInvitation($invite01['token'], ['uid' => 'uid-inv-02', 'email' => $email01], [
        'nombre' => 'Inv',
        'apellido' => 'Dos',
        'telefono' => '223000000',
    ]);
    inv_assert($accepted['accepted'] === true, 'INV-02 no acepto.');
    $user = $pdo->query("SELECT id, firebase_uid, estado FROM usuarios WHERE email = " . $pdo->quote($email01))->fetch();
    inv_assert($user['firebase_uid'] === 'uid-inv-02' && $user['estado'] === 'ACTIVO', 'INV-02 usuario no activado.');
    $perm = $repo->effectivePermissions((int)$user['id'], 'CABLERAMARPLATENSE', 'TELEFONIA_PRODUCCION_PLANTA');
    inv_assert($perm['permissions']['puede_ver'] === true, 'INV-02 permisos no asignados.');
    $results['INV-02'] = 'OK';

    inv_expect_error(fn () => $service->acceptInvitation('token-invalido', ['uid' => 'uid-x', 'email' => $email01]), 404, 'invitation_not_found');
    $results['INV-03'] = 'OK';

    $email04 = $prefix . '_04@plantel.local';
    $invite04 = $service->createInvitation(inv_payload($email04), $adminId);
    $pdo->prepare('UPDATE invitaciones_usuario SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = ?')->execute([(int)$invite04['id']]);
    inv_expect_error(fn () => $service->acceptInvitation($invite04['token'], ['uid' => 'uid-inv-04', 'email' => $email04]), 410, 'invitation_expired');
    $results['INV-04'] = 'OK';

    $email05 = $prefix . '_05@plantel.local';
    $invite05 = $service->createInvitation(inv_payload($email05), $adminId);
    $service->revokeInvitation((int)$invite05['id'], $adminId);
    inv_expect_error(fn () => $service->acceptInvitation($invite05['token'], ['uid' => 'uid-inv-05', 'email' => $email05]), 410, 'invitation_revoked');
    $results['INV-05'] = 'OK';

    inv_expect_error(fn () => $service->acceptInvitation($invite01['token'], ['uid' => 'uid-inv-02', 'email' => $email01]), 409, 'invitation_already_accepted');
    $countRel = $pdo->query('SELECT COUNT(*) FROM usuario_rol WHERE usuario_id = ' . (int)$user['id'])->fetchColumn();
    inv_assert((int)$countRel === 1, 'INV-06 duplico relaciones.');
    $results['INV-06'] = 'OK';

    $email07 = $prefix . '_07@plantel.local';
    $invite07 = $service->createInvitation(inv_payload($email07), $adminId);
    inv_expect_error(fn () => $service->acceptInvitation($invite07['token'], ['uid' => 'uid-inv-07', 'email' => $prefix . '_otro@plantel.local']), 422, 'invitation_email_mismatch');
    $results['INV-07'] = 'OK';

    $email08 = $prefix . '_08@plantel.local';
    $pdo->prepare('INSERT INTO usuarios (email, firebase_uid, estado, es_superadmin) VALUES (?, ?, "ACTIVO", 0)')->execute([$email08, 'uid-original']);
    $invite08 = $service->createInvitation(inv_payload($email08), $adminId);
    inv_expect_error(fn () => $service->acceptInvitation($invite08['token'], ['uid' => 'uid-distinto', 'email' => $email08]), 409, 'firebase_uid_conflict');
    $conflicts = $pdo->query("SELECT COUNT(*) FROM identidad_conflictos WHERE email = " . $pdo->quote($email08))->fetchColumn();
    inv_assert((int)$conflicts === 1, 'INV-08 no registro conflicto.');
    $results['INV-08'] = 'OK';

    $email09 = $prefix . '_09@plantel.local';
    $pdo->prepare('INSERT INTO usuarios (email, firebase_uid, estado, es_superadmin) VALUES (?, ?, "ACTIVO", 0)')->execute([$email09, 'uid-inv-09']);
    $existingId09 = (int)$pdo->lastInsertId();
    $invite09 = $service->createInvitation([
        'email' => $email09,
        'applications' => [['codigo' => 'PLANTEL_MOBILE']],
    ], $adminId);
    $service->acceptInvitation($invite09['token'], ['uid' => 'uid-inv-09', 'email' => $email09]);
    $sameId09 = (int)$pdo->query("SELECT id FROM usuarios WHERE email = " . $pdo->quote($email09))->fetchColumn();
    inv_assert($sameId09 === $existingId09, 'INV-09 creo usuario duplicado.');
    $apps09 = $pdo->query('SELECT COUNT(*) FROM usuario_aplicacion WHERE usuario_id = ' . $existingId09)->fetchColumn();
    inv_assert((int)$apps09 === 1, 'INV-09 no agrego aplicacion.');
    $results['INV-09'] = 'OK';

    $email10 = $prefix . '_10@plantel.local';
    $invite10a = $service->createInvitation(inv_payload($email10, ['JEFATURA']), $adminId);
    $service->acceptInvitation($invite10a['token'], ['uid' => 'uid-inv-10', 'email' => $email10]);
    $invite10b = $service->createInvitation(inv_payload($email10, ['ASISTENTE']), $adminId);
    $service->acceptInvitation($invite10b['token'], ['uid' => 'uid-inv-10', 'email' => $email10]);
    $user10 = (int)$pdo->query("SELECT id FROM usuarios WHERE email = " . $pdo->quote($email10))->fetchColumn();
    $roles10 = $pdo->query('SELECT COUNT(*) FROM usuario_rol WHERE usuario_id = ' . $user10)->fetchColumn();
    inv_assert((int)$roles10 === 2, 'INV-10 no agrego rol nuevo.');
    $results['INV-10'] = 'OK';

    $email11 = $prefix . '_11@plantel.local';
    $invite11 = $service->createInvitation(inv_payload($email11, ['JEFATURA'], [[
        'codigo' => 'TELEFONIA_PRODUCCION_PLANTA',
        'puede_editar' => true,
    ]]), $adminId);
    $service->acceptInvitation($invite11['token'], ['uid' => 'uid-inv-11', 'email' => $email11]);
    $user11 = (int)$pdo->query("SELECT id FROM usuarios WHERE email = " . $pdo->quote($email11))->fetchColumn();
    $perm11 = $repo->effectivePermissions($user11, 'CABLERAMARPLATENSE', 'TELEFONIA_PRODUCCION_PLANTA');
    inv_assert($perm11['permissions']['puede_editar'] === true, 'INV-11 no agrego permiso.');
    $results['INV-11'] = 'OK';

    $email12 = $prefix . '_12@plantel.local';
    $invite12 = $service->createInvitation(inv_payload($email12, ['JEFATURA'], [[
        'codigo' => 'TELEFONIA_PRODUCCION_PLANTA',
        'puede_exportar' => false,
    ]]), $adminId);
    $service->acceptInvitation($invite12['token'], ['uid' => 'uid-inv-12', 'email' => $email12]);
    $user12 = (int)$pdo->query("SELECT id FROM usuarios WHERE email = " . $pdo->quote($email12))->fetchColumn();
    $perm12 = $repo->effectivePermissions($user12, 'CABLERAMARPLATENSE', 'TELEFONIA_PRODUCCION_PLANTA');
    inv_assert($perm12['permissions']['puede_exportar'] === false, 'INV-12 no quito permiso.');
    $results['INV-12'] = 'OK';

    $email13 = $prefix . '_13@plantel.local';
    $invite13 = $service->createInvitation(inv_payload($email13), $adminId);
    $service->revokeInvitation((int)$invite13['id'], $adminId);
    $state13 = $pdo->query('SELECT estado FROM invitaciones_usuario WHERE id = ' . (int)$invite13['id'])->fetchColumn();
    inv_assert($state13 === 'REVOCADA', 'INV-13 no revoco.');
    $results['INV-13'] = 'OK';

    inv_expect_error(fn () => $service->revokeInvitation((int)$invite01['id'], $adminId), 409, 'invitation_not_pending');
    $results['INV-14'] = 'OK';

    $email15 = $prefix . '_15@plantel.local';
    $invite15 = $service->createInvitation(inv_payload($email15), $adminId);
    $service->acceptInvitation($invite15['token'], ['uid' => 'uid-inv-15', 'email' => $email15]);
    inv_expect_error(fn () => $service->acceptInvitation($invite15['token'], ['uid' => 'uid-inv-15', 'email' => $email15]), 409, 'invitation_already_accepted');
    $user15 = (int)$pdo->query("SELECT id FROM usuarios WHERE email = " . $pdo->quote($email15))->fetchColumn();
    $roles15 = $pdo->query('SELECT COUNT(*) FROM usuario_rol WHERE usuario_id = ' . $user15)->fetchColumn();
    inv_assert((int)$roles15 === 1, 'INV-15 duplico aceptacion.');
    $results['INV-15'] = 'OK';

    $email16 = $prefix . '_16@plantel.local';
    $pdo->prepare('INSERT INTO usuarios (email, firebase_uid, estado, es_superadmin) VALUES (?, ?, "ACTIVO", 0)')->execute([$email16, 'uid-inv-16']);
    $user16 = (int)$pdo->lastInsertId();
    $authz16 = $repo->requirePermission($user16, 'CABLERAMARPLATENSE', 'IDENTIDAD_ACCESOS', 'puede_crear');
    inv_assert($authz16['authorized'] === false && $authz16['status'] === 403, 'INV-16 usuario no autorizado podria invitar.');
    $results['INV-16'] = 'OK';

    $email17 = $prefix . '_17@plantel.local';
    $invite17 = $service->createInvitation(inv_payload($email17), $adminId);
    inv_assert((int)$invite17['id'] > 0 && strlen($invite17['token']) >= 64, 'INV-17 superadmin no creo invitacion.');
    $results['INV-17'] = 'OK';

    echo json_encode([
        'ok' => true,
        'profile' => $profile,
        'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'results' => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    inv_cleanup($pdo, 'zz_inv_');
}

