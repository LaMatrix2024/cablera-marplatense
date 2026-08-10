<?php

declare(strict_types=1);

require_once __DIR__ . '/CorporateAccess.php';
require_once __DIR__ . '/CorporateAccessRepository.php';
require_once __DIR__ . '/HttpError.php';

final class CorporateInvitationService
{
    public const DEFAULT_EXPIRATION_HOURS = 72;

    public function __construct(private PDO $pdo)
    {
    }

    public function createInvitation(array $payload, int $createdByUsuarioId): array
    {
        $email = self::normalizeEmail((string)($payload['email'] ?? ''));
        if ($email === '') {
            throw new HttpError(422, 'Email invalido.', 'invalid_email');
        }

        $hours = (int)($payload['expires_in_hours'] ?? self::DEFAULT_EXPIRATION_HOURS);
        if ($hours < 1 || $hours > 720) {
            throw new HttpError(422, 'Vencimiento invalido.', 'invalid_expiration');
        }

        $applications = $payload['applications'] ?? [];
        if (!is_array($applications) || $applications === []) {
            throw new HttpError(422, 'La invitacion requiere aplicaciones.', 'missing_applications');
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = self::tokenHash($token);
        $expiresAt = (new DateTimeImmutable('now'))->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $user = $this->findUserByEmailForUpdate($email);
            if ($user === null) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO usuarios (email, estado, es_superadmin)
                     VALUES (:email, "PENDIENTE", 0)'
                );
                $stmt->execute(['email' => $email]);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO invitaciones_usuario (
                    email,
                    token_hash,
                    estado,
                    expires_at,
                    created_by_usuario_id
                 ) VALUES (
                    :email,
                    :token_hash,
                    "PENDIENTE",
                    :expires_at,
                    :created_by_usuario_id
                 )'
            );
            $stmt->execute([
                'email' => $email,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'created_by_usuario_id' => $createdByUsuarioId,
            ]);
            $invitationId = (int)$this->pdo->lastInsertId();

            $this->persistInvitationGrants($invitationId, $applications);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollBack();
            if ($exception instanceof HttpError) {
                throw $exception;
            }
            throw $exception;
        }

        return [
            'id' => $invitationId,
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function invitationByToken(string $token): array
    {
        $invitation = $this->findInvitationByToken($token);
        if ($invitation === null) {
            throw new HttpError(404, 'Invitacion inexistente.', 'invitation_not_found');
        }

        $this->expireIfNeeded($invitation);

        return $this->publicInvitation((int)$invitation['id']);
    }

    public function acceptInvitation(string $token, array $firebaseIdentity, array $profile = []): array
    {
        $email = self::normalizeEmail((string)($firebaseIdentity['email'] ?? ''));
        $firebaseUid = trim((string)($firebaseIdentity['uid'] ?? ''));
        if ($email === '' || $firebaseUid === '') {
            throw new HttpError(401, 'Identidad Firebase invalida.', 'invalid_firebase_identity');
        }

        $identityConflict = null;
        $this->pdo->beginTransaction();
        try {
            $invitation = $this->findInvitationByTokenForUpdate($token);
            if ($invitation === null) {
                throw new HttpError(404, 'Invitacion inexistente.', 'invitation_not_found');
            }

            $this->assertInvitationUsable($invitation);
            if (!hash_equals((string)$invitation['email'], $email)) {
                throw new HttpError(422, 'El email autenticado no coincide con la invitacion.', 'invitation_email_mismatch');
            }

            $user = $this->findUserByEmailForUpdate($email);
            if ($user === null) {
                throw new HttpError(403, 'Usuario corporativo no encontrado.', 'corporate_user_not_found');
            }
            if (in_array((string)$user['estado'], ['BLOQUEADO', 'BAJA'], true)) {
                throw new HttpError(403, 'Usuario corporativo inactivo.', 'inactive_user');
            }

            $link = CorporateAccess::resolveIdentityLink($user, $firebaseUid, $email);
            if (!$link['ok']) {
                if (($link['reason'] ?? '') === 'firebase_uid_conflict') {
                    $identityConflict = [
                        'usuario_id' => (int)$user['id'],
                        'email' => $email,
                        'existing_uid' => $user['firebase_uid'] ?? null,
                        'received_uid' => $firebaseUid,
                    ];
                }
                throw new HttpError(409, 'Conflicto de identidad.', (string)($link['reason'] ?? 'identity_conflict'));
            }

            $this->activateUserFromInvitation((int)$user['id'], $firebaseUid, $profile);
            $this->applyInvitationGrants((int)$invitation['id'], (int)$user['id']);

            $stmt = $this->pdo->prepare(
                'UPDATE invitaciones_usuario
                 SET estado = "ACEPTADA",
                     accepted_by_usuario_id = :usuario_id,
                     accepted_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'usuario_id' => (int)$user['id'],
                'id' => (int)$invitation['id'],
            ]);

            $this->pdo->commit();

            return [
                'accepted' => true,
                'usuario_id' => (int)$user['id'],
                'invitation_id' => (int)$invitation['id'],
            ];
        } catch (Throwable $exception) {
            $this->rollBack();
            if ($identityConflict !== null) {
                (new CorporateAccessRepository($this->pdo))->registerIdentityConflict(
                    $identityConflict['usuario_id'],
                    $identityConflict['email'],
                    $identityConflict['existing_uid'],
                    $identityConflict['received_uid'],
                    'firebase_uid_conflict',
                    'Conflicto al aceptar invitacion.'
                );
            }
            if ($exception instanceof HttpError) {
                throw $exception;
            }
            throw $exception;
        }
    }

    public function revokeInvitation(int $invitationId, int $revokedByUsuarioId): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM invitaciones_usuario WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $invitationId]);
            $invitation = $stmt->fetch();
            if (!is_array($invitation)) {
                throw new HttpError(404, 'Invitacion inexistente.', 'invitation_not_found');
            }
            if ($this->isExpired($invitation)) {
                $this->markExpired((int)$invitation['id']);
                throw new HttpError(410, 'Invitacion vencida.', 'invitation_expired');
            }
            if (($invitation['estado'] ?? '') !== 'PENDIENTE') {
                throw new HttpError(409, 'Solo se pueden revocar invitaciones pendientes.', 'invitation_not_pending');
            }

            $stmt = $this->pdo->prepare(
                'UPDATE invitaciones_usuario
                 SET estado = "REVOCADA",
                     revoked_at = NOW(),
                     revoked_by_usuario_id = :usuario_id
                 WHERE id = :id'
            );
            $stmt->execute([
                'usuario_id' => $revokedByUsuarioId,
                'id' => $invitationId,
            ]);
            $this->pdo->commit();

            return ['revoked' => true, 'id' => $invitationId];
        } catch (Throwable $exception) {
            $this->rollBack();
            if ($exception instanceof HttpError) {
                throw $exception;
            }
            throw $exception;
        }
    }

    public function listInvitations(): array
    {
        return $this->pdo->query(
            'SELECT id, email, estado, expires_at, created_by_usuario_id,
                    accepted_by_usuario_id, accepted_at, revoked_at, revoked_by_usuario_id,
                    created_at, updated_at
             FROM invitaciones_usuario
             ORDER BY id DESC
             LIMIT 200'
        )->fetchAll();
    }

    public function publicInvitation(int $invitationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, estado, expires_at, accepted_at, revoked_at, created_at
             FROM invitaciones_usuario
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $invitationId]);
        $invitation = $stmt->fetch();
        if (!is_array($invitation)) {
            throw new HttpError(404, 'Invitacion inexistente.', 'invitation_not_found');
        }

        return [
            'invitation' => $invitation,
            'applications' => $this->invitationApplications($invitationId),
            'roles' => $this->invitationRoles($invitationId),
            'module_exceptions' => $this->invitationModuleExceptions($invitationId),
        ];
    }

    public function authenticatedProfile(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, nombre, apellido, telefono, foto_url, cargo, sector, estado, es_superadmin, created_at, updated_at
             FROM usuarios
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $usuarioId]);
        $user = $stmt->fetch();
        if (!is_array($user)) {
            throw new HttpError(404, 'Usuario inexistente.', 'corporate_user_not_found');
        }

        $isSuperadmin = (int)$user['es_superadmin'] === 1;

        if ($isSuperadmin) {
            $appsRows = $this->pdo->query(
                'SELECT codigo, nombre, activo
                 FROM aplicaciones
                 WHERE activo = 1
                 ORDER BY codigo'
            )->fetchAll();
        } else {
            $apps = $this->pdo->prepare(
                'SELECT a.codigo, a.nombre, ua.activo
                 FROM usuario_aplicacion ua
                 INNER JOIN aplicaciones a ON a.id = ua.aplicacion_id
                 WHERE ua.usuario_id = :usuario_id
                 ORDER BY a.codigo'
            );
            $apps->execute(['usuario_id' => $usuarioId]);
            $appsRows = $apps->fetchAll();
        }

        $roles = $this->pdo->prepare(
            'SELECT a.codigo AS aplicacion, r.codigo, r.nombre
             FROM usuario_rol ur
             INNER JOIN aplicaciones a ON a.id = ur.aplicacion_id
             INNER JOIN roles r ON r.id = ur.rol_id
             WHERE ur.usuario_id = :usuario_id AND ur.activo = 1
             ORDER BY a.codigo, r.codigo'
        );
        $roles->execute(['usuario_id' => $usuarioId]);

        if ($isSuperadmin) {
            $moduleRows = $this->pdo->query(
                'SELECT a.codigo AS aplicacion, m.codigo, m.nombre, m.orden
                 FROM modulos m
                 INNER JOIN aplicaciones a ON a.id = m.aplicacion_id
                 WHERE m.activo = 1 AND a.activo = 1
                 ORDER BY a.codigo, m.orden'
            )->fetchAll();
        } else {
            $modules = $this->pdo->prepare(
                'SELECT a.codigo AS aplicacion, m.codigo, m.nombre, m.orden
                 FROM modulos m
                 INNER JOIN aplicaciones a ON a.id = m.aplicacion_id
                 INNER JOIN usuario_aplicacion ua ON ua.aplicacion_id = a.id AND ua.usuario_id = :usuario_id AND ua.activo = 1
                 WHERE m.activo = 1
                 ORDER BY a.codigo, m.orden'
            );
            $modules->execute(['usuario_id' => $usuarioId]);
            $moduleRows = $modules->fetchAll();
        }

        $repo = new CorporateAccessRepository($this->pdo);
        foreach ($moduleRows as &$module) {
            $permissions = $repo->effectivePermissions($usuarioId, $module['aplicacion'], $module['codigo']);
            $module['permissions'] = $permissions['permissions'];
        }
        unset($module);

        return [
            'user' => $user,
            'applications' => $appsRows,
            'roles' => $roles->fetchAll(),
            'modules' => $moduleRows,
        ];
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', trim($token));
    }

    public static function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function persistInvitationGrants(int $invitationId, array $applications): void
    {
        foreach ($applications as $application) {
            if (!is_array($application)) {
                throw new HttpError(422, 'Aplicacion invalida.', 'invalid_application');
            }
            $app = $this->applicationByCode((string)($application['codigo'] ?? ''));

            $stmt = $this->pdo->prepare(
                'INSERT INTO invitacion_aplicacion (invitacion_id, aplicacion_id, activo)
                 VALUES (:invitacion_id, :aplicacion_id, 1)
                 ON DUPLICATE KEY UPDATE activo = VALUES(activo)'
            );
            $stmt->execute([
                'invitacion_id' => $invitationId,
                'aplicacion_id' => (int)$app['id'],
            ]);

            foreach (($application['roles'] ?? []) as $roleCode) {
                $role = $this->roleByCode((int)$app['id'], (string)$roleCode);
                $stmt = $this->pdo->prepare(
                    'INSERT INTO invitacion_rol (invitacion_id, aplicacion_id, rol_id)
                     VALUES (:invitacion_id, :aplicacion_id, :rol_id)'
                );
                $stmt->execute([
                    'invitacion_id' => $invitationId,
                    'aplicacion_id' => (int)$app['id'],
                    'rol_id' => (int)$role['id'],
                ]);
            }

            foreach (($application['modules'] ?? []) as $moduleGrant) {
                if (!is_array($moduleGrant)) {
                    throw new HttpError(422, 'Modulo invalido.', 'invalid_module');
                }
                $module = $this->moduleByCode((int)$app['id'], (string)($moduleGrant['codigo'] ?? ''));
                $stmt = $this->pdo->prepare(
                    'INSERT INTO invitacion_modulo (
                        invitacion_id, aplicacion_id, modulo_id,
                        puede_ver, puede_crear, puede_editar,
                        puede_eliminar, puede_exportar, puede_aprobar
                     ) VALUES (
                        :invitacion_id, :aplicacion_id, :modulo_id,
                        :puede_ver, :puede_crear, :puede_editar,
                        :puede_eliminar, :puede_exportar, :puede_aprobar
                     )'
                );
                $params = [
                    'invitacion_id' => $invitationId,
                    'aplicacion_id' => (int)$app['id'],
                    'modulo_id' => (int)$module['id'],
                ];
                foreach (CorporateAccess::PERMISSIONS as $permission) {
                    $params[$permission] = $this->nullablePermission($moduleGrant[$permission] ?? null);
                }
                $stmt->execute($params);
            }
        }
    }

    private function applyInvitationGrants(int $invitationId, int $usuarioId): void
    {
        $apps = $this->pdo->prepare(
            'SELECT aplicacion_id
             FROM invitacion_aplicacion
             WHERE invitacion_id = :invitacion_id AND activo = 1'
        );
        $apps->execute(['invitacion_id' => $invitationId]);
        foreach ($apps->fetchAll() as $app) {
            $this->pdo->prepare(
                'INSERT INTO usuario_aplicacion (usuario_id, aplicacion_id, activo)
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE activo = VALUES(activo), updated_at = CURRENT_TIMESTAMP'
            )->execute([$usuarioId, (int)$app['aplicacion_id']]);
        }

        $roles = $this->pdo->prepare(
            'SELECT aplicacion_id, rol_id
             FROM invitacion_rol
             WHERE invitacion_id = :invitacion_id'
        );
        $roles->execute(['invitacion_id' => $invitationId]);
        foreach ($roles->fetchAll() as $role) {
            $this->pdo->prepare(
                'INSERT INTO usuario_rol (usuario_id, aplicacion_id, rol_id, activo)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE activo = VALUES(activo), updated_at = CURRENT_TIMESTAMP'
            )->execute([$usuarioId, (int)$role['aplicacion_id'], (int)$role['rol_id']]);
        }

        $modules = $this->pdo->prepare(
            'SELECT aplicacion_id, modulo_id, puede_ver, puede_crear, puede_editar,
                    puede_eliminar, puede_exportar, puede_aprobar
             FROM invitacion_modulo
             WHERE invitacion_id = :invitacion_id'
        );
        $modules->execute(['invitacion_id' => $invitationId]);
        foreach ($modules->fetchAll() as $module) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO usuario_modulo (
                    usuario_id, aplicacion_id, modulo_id,
                    puede_ver, puede_crear, puede_editar,
                    puede_eliminar, puede_exportar, puede_aprobar,
                    motivo
                 ) VALUES (
                    :usuario_id, :aplicacion_id, :modulo_id,
                    :puede_ver, :puede_crear, :puede_editar,
                    :puede_eliminar, :puede_exportar, :puede_aprobar,
                    "Excepcion otorgada por invitacion"
                 )
                 ON DUPLICATE KEY UPDATE
                    puede_ver = VALUES(puede_ver),
                    puede_crear = VALUES(puede_crear),
                    puede_editar = VALUES(puede_editar),
                    puede_eliminar = VALUES(puede_eliminar),
                    puede_exportar = VALUES(puede_exportar),
                    puede_aprobar = VALUES(puede_aprobar),
                    motivo = VALUES(motivo),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $params = [
                'usuario_id' => $usuarioId,
                'aplicacion_id' => (int)$module['aplicacion_id'],
                'modulo_id' => (int)$module['modulo_id'],
            ];
            foreach (CorporateAccess::PERMISSIONS as $permission) {
                $params[$permission] = $module[$permission];
            }
            $stmt->execute($params);
        }
    }

    private function activateUserFromInvitation(int $usuarioId, string $firebaseUid, array $profile): void
    {
        $sets = [
            'firebase_uid = COALESCE(firebase_uid, :firebase_uid)',
            'estado = :estado',
        ];
        $params = [
            'firebase_uid' => $firebaseUid,
            'estado' => 'ACTIVO',
            'id' => $usuarioId,
        ];

        foreach (['nombre', 'apellido', 'telefono'] as $field) {
            $value = trim((string)($profile[$field] ?? ''));
            if ($value !== '') {
                $sets[] = $field . ' = :' . $field;
                $params[$field] = $value;
            }
        }

        $stmt = $this->pdo->prepare(
            'UPDATE usuarios
             SET ' . implode(', ', $sets) . '
             WHERE id = :id'
        );
        $stmt->execute($params);
    }

    private function assertInvitationUsable(array $invitation): void
    {
        if ($this->isExpired($invitation)) {
            $this->markExpired((int)$invitation['id']);
            throw new HttpError(410, 'Invitacion vencida.', 'invitation_expired');
        }
        $estado = (string)$invitation['estado'];
        if ($estado === 'ACEPTADA') {
            throw new HttpError(409, 'Invitacion ya utilizada.', 'invitation_already_accepted');
        }
        if ($estado === 'REVOCADA') {
            throw new HttpError(410, 'Invitacion revocada.', 'invitation_revoked');
        }
        if ($estado !== 'PENDIENTE') {
            throw new HttpError(409, 'Invitacion no disponible.', 'invitation_not_pending');
        }
    }

    private function findInvitationByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invitaciones_usuario WHERE token_hash = :token_hash LIMIT 1');
        $stmt->execute(['token_hash' => self::tokenHash($token)]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function findInvitationByTokenForUpdate(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invitaciones_usuario WHERE token_hash = :token_hash LIMIT 1 FOR UPDATE');
        $stmt->execute(['token_hash' => self::tokenHash($token)]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function findUserByEmailForUpdate(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE email = :email LIMIT 1 FOR UPDATE');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function applicationByCode(string $code): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM aplicaciones WHERE codigo = :codigo AND activo = 1 LIMIT 1');
        $stmt->execute(['codigo' => strtoupper(trim($code))]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new HttpError(422, 'Aplicacion invalida.', 'invalid_application');
        }

        return $row;
    }

    private function roleByCode(int $applicationId, string $code): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM roles WHERE aplicacion_id = :aplicacion_id AND codigo = :codigo AND activo = 1 LIMIT 1'
        );
        $stmt->execute([
            'aplicacion_id' => $applicationId,
            'codigo' => strtoupper(trim($code)),
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new HttpError(422, 'Rol invalido para la aplicacion.', 'invalid_role');
        }

        return $row;
    }

    private function moduleByCode(int $applicationId, string $code): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM modulos WHERE aplicacion_id = :aplicacion_id AND codigo = :codigo AND activo = 1 LIMIT 1'
        );
        $stmt->execute([
            'aplicacion_id' => $applicationId,
            'codigo' => strtoupper(trim($code)),
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new HttpError(422, 'Modulo invalido para la aplicacion.', 'invalid_module');
        }

        return $row;
    }

    private function invitationApplications(int $invitationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.codigo, a.nombre, ia.activo
             FROM invitacion_aplicacion ia
             INNER JOIN aplicaciones a ON a.id = ia.aplicacion_id
             WHERE ia.invitacion_id = :id
             ORDER BY a.codigo'
        );
        $stmt->execute(['id' => $invitationId]);

        return $stmt->fetchAll();
    }

    private function invitationRoles(int $invitationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.codigo AS aplicacion, r.codigo, r.nombre
             FROM invitacion_rol ir
             INNER JOIN aplicaciones a ON a.id = ir.aplicacion_id
             INNER JOIN roles r ON r.id = ir.rol_id
             WHERE ir.invitacion_id = :id
             ORDER BY a.codigo, r.codigo'
        );
        $stmt->execute(['id' => $invitationId]);

        return $stmt->fetchAll();
    }

    private function invitationModuleExceptions(int $invitationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.codigo AS aplicacion, m.codigo AS modulo,
                    im.puede_ver, im.puede_crear, im.puede_editar,
                    im.puede_eliminar, im.puede_exportar, im.puede_aprobar
             FROM invitacion_modulo im
             INNER JOIN aplicaciones a ON a.id = im.aplicacion_id
             INNER JOIN modulos m ON m.id = im.modulo_id
             WHERE im.invitacion_id = :id
             ORDER BY a.codigo, m.codigo'
        );
        $stmt->execute(['id' => $invitationId]);

        return $stmt->fetchAll();
    }

    private function nullablePermission(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value === true || $value === 1 || $value === '1') ? 1 : 0;
    }

    private function expireIfNeeded(array $invitation): void
    {
        if (($invitation['estado'] ?? '') === 'PENDIENTE' && $this->isExpired($invitation)) {
            $this->markExpired((int)$invitation['id']);
        }
    }

    private function isExpired(array $invitation): bool
    {
        return strtotime((string)$invitation['expires_at']) < time();
    }

    private function markExpired(int $invitationId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE invitaciones_usuario
             SET estado = "VENCIDA"
             WHERE id = :id AND estado = "PENDIENTE"'
        );
        $stmt->execute(['id' => $invitationId]);
    }

    private function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
