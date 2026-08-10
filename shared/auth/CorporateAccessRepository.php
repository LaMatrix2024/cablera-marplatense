<?php

declare(strict_types=1);

require_once __DIR__ . '/CorporateAccess.php';

final class CorporateAccessRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findUserByFirebaseIdentity(string $firebaseUid, string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM usuarios
             WHERE firebase_uid = :firebase_uid_filter OR email = :email
             ORDER BY CASE WHEN firebase_uid = :firebase_uid_order THEN 0 ELSE 1 END
             LIMIT 1'
        );
        $stmt->execute([
            'firebase_uid_filter' => $firebaseUid,
            'firebase_uid_order' => $firebaseUid,
            'email' => strtolower(trim($email)),
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function linkFirebaseUid(int $usuarioId, string $firebaseUid): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios
             SET firebase_uid = :firebase_uid
             WHERE id = :id
               AND firebase_uid IS NULL'
        );
        $stmt->execute([
            'firebase_uid' => $firebaseUid,
            'id' => $usuarioId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('No se pudo asociar firebase_uid: el usuario ya tiene otra identidad vinculada.');
        }
    }

    public function effectivePermissions(int $usuarioId, string $aplicacionCodigo, string $moduloCodigo): array
    {
        $context = $this->loadContext($usuarioId, $aplicacionCodigo, $moduloCodigo);
        if ($context === null) {
            return [
                'authorized' => false,
                'status' => 403,
                'reason' => 'application_or_module_not_found',
                'permissions' => CorporateAccess::emptyPermissions(),
            ];
        }

        $user = $context['user'];
        if (!CorporateAccess::isActiveUser($user)) {
            return $this->denied('inactive_user');
        }

        if (CorporateAccess::isSuperadmin($user)) {
            return [
                'authorized' => true,
                'status' => 200,
                'reason' => 'superadmin',
                'permissions' => CorporateAccess::fullPermissions(),
            ];
        }

        if ((int)$context['aplicacion_activa'] !== 1) {
            return $this->denied('inactive_application');
        }

        if ((int)$context['modulo_activo'] !== 1) {
            return $this->denied('inactive_module');
        }

        if ((int)$context['usuario_aplicacion_activo'] !== 1) {
            return $this->denied('user_not_authorized_for_application');
        }

        $rolePermissions = $this->rolePermissions(
            $usuarioId,
            (int)$context['aplicacion_id'],
            (int)$context['modulo_id']
        );
        $permissions = CorporateAccess::combineRolePermissions($rolePermissions);
        $permissions = CorporateAccess::applyUserException(
            $permissions,
            $this->userModuleException($usuarioId, (int)$context['aplicacion_id'], (int)$context['modulo_id'])
        );

        return [
            'authorized' => in_array(true, $permissions, true),
            'status' => in_array(true, $permissions, true) ? 200 : 403,
            'reason' => in_array(true, $permissions, true) ? 'authorized' : 'no_module_permissions',
            'permissions' => $permissions,
        ];
    }

    public function requirePermission(
        int $usuarioId,
        string $aplicacionCodigo,
        string $moduloCodigo,
        string $permission
    ): array {
        $result = $this->effectivePermissions($usuarioId, $aplicacionCodigo, $moduloCodigo);
        if (!CorporateAccess::userCan($result['permissions'], $permission)) {
            $result['authorized'] = false;
            $result['status'] = 403;
            $result['reason'] = 'permission_denied';
        }

        return $result;
    }

    private function loadContext(int $usuarioId, string $aplicacionCodigo, string $moduloCodigo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                u.*,
                a.id AS aplicacion_id,
                a.activo AS aplicacion_activa,
                m.id AS modulo_id,
                m.activo AS modulo_activo,
                COALESCE(ua.activo, 0) AS usuario_aplicacion_activo
             FROM usuarios u
             INNER JOIN aplicaciones a ON a.codigo = :aplicacion_codigo
             INNER JOIN modulos m ON m.aplicacion_id = a.id AND m.codigo = :modulo_codigo
             LEFT JOIN usuario_aplicacion ua ON ua.usuario_id = u.id AND ua.aplicacion_id = a.id
             WHERE u.id = :usuario_id
             LIMIT 1'
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'aplicacion_codigo' => $aplicacionCodigo,
            'modulo_codigo' => $moduloCodigo,
        ]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        $user = $row;
        foreach (['aplicacion_id', 'aplicacion_activa', 'modulo_id', 'modulo_activo', 'usuario_aplicacion_activo'] as $key) {
            unset($user[$key]);
        }
        $row['user'] = $user;

        return $row;
    }

    private function rolePermissions(int $usuarioId, int $aplicacionId, int $moduloId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                rm.puede_ver,
                rm.puede_crear,
                rm.puede_editar,
                rm.puede_eliminar,
                rm.puede_exportar,
                rm.puede_aprobar
             FROM usuario_rol ur
             INNER JOIN roles r ON r.id = ur.rol_id AND r.aplicacion_id = ur.aplicacion_id AND r.activo = 1
             INNER JOIN rol_modulo rm ON rm.rol_id = r.id AND rm.aplicacion_id = r.aplicacion_id
             WHERE ur.usuario_id = :usuario_id
               AND ur.aplicacion_id = :aplicacion_id
               AND ur.activo = 1
               AND rm.modulo_id = :modulo_id'
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'aplicacion_id' => $aplicacionId,
            'modulo_id' => $moduloId,
        ]);

        return $stmt->fetchAll();
    }

    private function userModuleException(int $usuarioId, int $aplicacionId, int $moduloId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                puede_ver,
                puede_crear,
                puede_editar,
                puede_eliminar,
                puede_exportar,
                puede_aprobar
             FROM usuario_modulo
             WHERE usuario_id = :usuario_id
               AND aplicacion_id = :aplicacion_id
               AND modulo_id = :modulo_id
             LIMIT 1'
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'aplicacion_id' => $aplicacionId,
            'modulo_id' => $moduloId,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function denied(string $reason): array
    {
        return [
            'authorized' => false,
            'status' => 403,
            'reason' => $reason,
            'permissions' => CorporateAccess::emptyPermissions(),
        ];
    }
}
