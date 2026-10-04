<?php

declare(strict_types=1);

require_once __DIR__ . '/CorporateAccessRepository.php';
require_once __DIR__ . '/HttpError.php';

final class IdentityAdminService
{
    private const PERMISSIONS = [
        'puede_ver',
        'puede_crear',
        'puede_editar',
        'puede_eliminar',
        'puede_exportar',
        'puede_aprobar',
    ];

    public function __construct(private PDO $pdo, private array $actor)
    {
    }

    public function dashboard(): array
    {
        return [
            'summary' => [
                'usuarios_activos' => $this->countWhere('usuarios', "estado = 'ACTIVO'"),
                'usuarios_pendientes' => $this->countWhere('usuarios', "estado = 'PENDIENTE'"),
                'usuarios_bloqueados' => $this->countWhere('usuarios', "estado = 'BLOQUEADO'"),
                'invitaciones_pendientes' => $this->countWhere('invitaciones_usuario', "estado = 'PENDIENTE'"),
                'invitaciones_vencidas' => $this->countWhere('invitaciones_usuario', "estado = 'VENCIDA' OR (estado = 'PENDIENTE' AND expires_at < NOW())"),
                'aplicaciones_activas' => $this->countWhere('aplicaciones', 'activo = 1'),
                'modulos_activos' => $this->countWhere('modulos', 'activo = 1'),
            ],
        ];
    }

    public function catalogs(): array
    {
        return [
            'applications' => $this->pdo->query('SELECT id, codigo, nombre, activo FROM aplicaciones ORDER BY nombre')->fetchAll(),
            'roles' => $this->pdo->query(
                'SELECT r.id, r.aplicacion_id, r.codigo, r.nombre, r.activo, a.codigo AS aplicacion_codigo
                 FROM roles r
                 INNER JOIN aplicaciones a ON a.id = r.aplicacion_id
                 ORDER BY a.nombre, r.nombre'
            )->fetchAll(),
            'modules' => $this->pdo->query(
                'SELECT m.id, m.aplicacion_id, m.codigo, m.nombre, m.descripcion, m.ruta, m.icono, m.color, m.grupo, m.orden, m.activo, a.codigo AS aplicacion_codigo
                 FROM modulos m
                 INNER JOIN aplicaciones a ON a.id = m.aplicacion_id
                 ORDER BY a.nombre, m.orden, m.nombre'
            )->fetchAll(),
        ];
    }

    public function listUsers(array $filters): array
    {
        $where = [];
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(u.nombre LIKE :q OR u.apellido LIKE :q OR u.email LIKE :q)';
            $params['q'] = '%' . trim((string)$filters['q']) . '%';
        }
        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'u.estado = :estado';
            $params['estado'] = (string)$filters['estado'];
        }
        if (($filters['aplicacion'] ?? '') !== '') {
            $where[] = 'EXISTS (
                SELECT 1 FROM usuario_aplicacion ua
                INNER JOIN aplicaciones a2 ON a2.id = ua.aplicacion_id
                WHERE ua.usuario_id = u.id AND a2.codigo = :aplicacion AND ua.activo = 1
            )';
            $params['aplicacion'] = (string)$filters['aplicacion'];
        }
        if (($filters['rol'] ?? '') !== '') {
            $where[] = 'EXISTS (
                SELECT 1 FROM usuario_rol ur
                INNER JOIN roles r2 ON r2.id = ur.rol_id
                WHERE ur.usuario_id = u.id AND r2.codigo = :rol AND ur.activo = 1
            )';
            $params['rol'] = (string)$filters['rol'];
        }

        $sql = 'SELECT
                    u.id, u.email, u.nombre, u.apellido, u.telefono, u.cargo, u.sector,
                    u.estado, u.es_superadmin, u.created_at, u.updated_at,
                    GROUP_CONCAT(DISTINCT CASE WHEN ua.activo = 1 THEN a.codigo END ORDER BY a.codigo SEPARATOR ", ") AS aplicaciones,
                    GROUP_CONCAT(DISTINCT CASE WHEN ur.activo = 1 THEN r.codigo END ORDER BY r.codigo SEPARATOR ", ") AS roles
                FROM usuarios u
                LEFT JOIN usuario_aplicacion ua ON ua.usuario_id = u.id
                LEFT JOIN aplicaciones a ON a.id = ua.aplicacion_id
                LEFT JOIN usuario_rol ur ON ur.usuario_id = u.id
                LEFT JOIN roles r ON r.id = ur.rol_id
                ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
                GROUP BY u.id
                ORDER BY u.created_at DESC, u.id DESC
                LIMIT 250';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ['users' => $stmt->fetchAll()];
    }

    public function userDetail(int $id): array
    {
        $user = $this->fetchById('usuarios', $id);
        if ($user === null) {
            throw new HttpError(404, 'Usuario inexistente.', 'user_not_found');
        }

        return [
            'user' => $user,
            'applications' => $this->userApplications($id),
            'roles' => $this->userRoles($id),
            'modules' => $this->userEffectiveModules($id),
        ];
    }

    public function updateUser(int $id, array $payload): array
    {
        $before = $this->fetchById('usuarios', $id);
        if ($before === null) {
            throw new HttpError(404, 'Usuario inexistente.', 'user_not_found');
        }
        $this->assertVersion($before, $payload);

        $fields = [];
        $params = ['id' => $id];
        foreach (['nombre', 'apellido', 'telefono', 'cargo', 'sector', 'foto_url'] as $field) {
            if (array_key_exists($field, $payload)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $this->nullableString($payload[$field]);
            }
        }
        if (array_key_exists('estado', $payload)) {
            $estado = (string)$payload['estado'];
            if (!in_array($estado, ['ACTIVO', 'PENDIENTE', 'BLOQUEADO', 'BAJA'], true)) {
                throw new HttpError(422, 'Estado invalido.', 'invalid_user_status');
            }
            $fields[] = 'estado = :estado';
            $params['estado'] = $estado;
        }
        if (array_key_exists('es_superadmin', $payload)) {
            $this->assertSuperadminActor();
            $newSuper = $this->boolInt($payload['es_superadmin']);
            if ((int)$before['es_superadmin'] === 1 && $newSuper === 0) {
                $this->assertNotLastActiveSuperadmin($id);
            }
            $fields[] = 'es_superadmin = :es_superadmin';
            $params['es_superadmin'] = $newSuper;
        }
        if (!$fields) {
            return $this->userDetail($id);
        }

        $stmt = $this->pdo->prepare('UPDATE usuarios SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
        $after = $this->fetchById('usuarios', $id);
        $this->audit('usuario.actualizar', 'usuario', $id, $before, $after);

        return $this->userDetail($id);
    }

    public function setUserApplications(int $id, array $payload): array
    {
        $this->assertUserExists($id);
        $applications = $payload['applications'] ?? [];
        if (!is_array($applications)) {
            throw new HttpError(422, 'Aplicaciones invalidas.', 'invalid_applications');
        }

        $before = $this->userApplications($id);
        $this->pdo->beginTransaction();
        try {
            foreach ($applications as $item) {
                $applicationId = $this->applicationId($item['aplicacion_id'] ?? null);
                $active = $this->boolInt($item['activo'] ?? true);
                $stmt = $this->pdo->prepare(
                    'INSERT INTO usuario_aplicacion (usuario_id, aplicacion_id, activo)
                     VALUES (:usuario_id, :aplicacion_id, :activo)
                     ON DUPLICATE KEY UPDATE activo = VALUES(activo)'
                );
                $stmt->execute(['usuario_id' => $id, 'aplicacion_id' => $applicationId, 'activo' => $active]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        $after = $this->userApplications($id);
        $this->audit('usuario.aplicaciones', 'usuario', $id, $before, $after);

        return $this->userDetail($id);
    }

    public function setUserRoles(int $id, array $payload): array
    {
        $this->assertUserExists($id);
        $roles = $payload['roles'] ?? [];
        if (!is_array($roles)) {
            throw new HttpError(422, 'Roles invalidos.', 'invalid_roles');
        }
        $applicationId = array_key_exists('aplicacion_id', $payload) ? $this->applicationId($payload['aplicacion_id']) : null;
        $before = $this->userRoles($id);

        $this->pdo->beginTransaction();
        try {
            if ($applicationId !== null) {
                $this->pdo->prepare('UPDATE usuario_rol SET activo = 0 WHERE usuario_id = :usuario_id AND aplicacion_id = :aplicacion_id')
                    ->execute(['usuario_id' => $id, 'aplicacion_id' => $applicationId]);
            }
            foreach ($roles as $item) {
                $roleId = $this->roleId($item['rol_id'] ?? null);
                $role = $this->fetchById('roles', $roleId);
                $appId = (int)$role['aplicacion_id'];
                $this->ensureUserApplication($id, $appId);
                $active = $this->boolInt($item['activo'] ?? true);
                $stmt = $this->pdo->prepare(
                    'INSERT INTO usuario_rol (usuario_id, aplicacion_id, rol_id, activo)
                     VALUES (:usuario_id, :aplicacion_id, :rol_id, :activo)
                     ON DUPLICATE KEY UPDATE activo = VALUES(activo)'
                );
                $stmt->execute(['usuario_id' => $id, 'aplicacion_id' => $appId, 'rol_id' => $roleId, 'activo' => $active]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        $after = $this->userRoles($id);
        $this->audit('usuario.roles', 'usuario', $id, $before, $after);

        return $this->userDetail($id);
    }

    public function setUserModules(int $id, array $payload): array
    {
        $this->assertUserExists($id);
        $modules = $payload['modules'] ?? [];
        if (!is_array($modules)) {
            throw new HttpError(422, 'Modulos invalidos.', 'invalid_modules');
        }
        $before = $this->userEffectiveModules($id);

        $this->pdo->beginTransaction();
        try {
            foreach ($modules as $item) {
                $moduleId = $this->moduleId($item['modulo_id'] ?? null);
                $module = $this->fetchById('modulos', $moduleId);
                $appId = (int)$module['aplicacion_id'];
                $this->ensureUserApplication($id, $appId);
                $values = [
                    'usuario_id' => $id,
                    'aplicacion_id' => $appId,
                    'modulo_id' => $moduleId,
                    'motivo' => $this->nullableString($item['motivo'] ?? null),
                ];
                foreach (self::PERMISSIONS as $permission) {
                    $values[$permission] = $this->nullablePermission($item[$permission] ?? null);
                }
                $stmt = $this->pdo->prepare(
                    'INSERT INTO usuario_modulo (
                        usuario_id, aplicacion_id, modulo_id,
                        puede_ver, puede_crear, puede_editar, puede_eliminar, puede_exportar, puede_aprobar, motivo
                     ) VALUES (
                        :usuario_id, :aplicacion_id, :modulo_id,
                        :puede_ver, :puede_crear, :puede_editar, :puede_eliminar, :puede_exportar, :puede_aprobar, :motivo
                     ) ON DUPLICATE KEY UPDATE
                        puede_ver = VALUES(puede_ver),
                        puede_crear = VALUES(puede_crear),
                        puede_editar = VALUES(puede_editar),
                        puede_eliminar = VALUES(puede_eliminar),
                        puede_exportar = VALUES(puede_exportar),
                        puede_aprobar = VALUES(puede_aprobar),
                        motivo = VALUES(motivo)'
                );
                $stmt->execute($values);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        $after = $this->userEffectiveModules($id);
        $this->audit('usuario.modulos', 'usuario', $id, $before, $after);

        return $this->userDetail($id);
    }

    public function listApplications(): array
    {
        $rows = $this->pdo->query(
            'SELECT
                a.*,
                COUNT(DISTINCT m.id) AS modulos_count,
                COUNT(DISTINCT CASE WHEN ua.activo = 1 THEN ua.usuario_id END) AS usuarios_autorizados_count
             FROM aplicaciones a
             LEFT JOIN modulos m ON m.aplicacion_id = a.id
             LEFT JOIN usuario_aplicacion ua ON ua.aplicacion_id = a.id
             GROUP BY a.id
             ORDER BY a.nombre'
        )->fetchAll();

        return ['applications' => $rows];
    }

    public function createApplication(array $payload): array
    {
        $codigo = $this->code($payload['codigo'] ?? '');
        $stmt = $this->pdo->prepare(
            'INSERT INTO aplicaciones (codigo, nombre, descripcion, activo)
             VALUES (:codigo, :nombre, :descripcion, :activo)'
        );
        $stmt->execute([
            'codigo' => $codigo,
            'nombre' => trim((string)($payload['nombre'] ?? $codigo)),
            'descripcion' => $this->nullableString($payload['descripcion'] ?? null),
            'activo' => $this->boolInt($payload['activo'] ?? true),
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->audit('aplicacion.crear', 'aplicacion', $id, null, $this->fetchById('aplicaciones', $id));

        return $this->applicationResponse($id);
    }

    public function updateApplication(int $id, array $payload): array
    {
        return $this->updateCatalog('aplicaciones', 'aplicacion', $id, $payload, ['nombre', 'descripcion', 'activo']);
    }

    public function listModules(array $filters): array
    {
        $where = [];
        $params = [];
        if (($filters['aplicacion'] ?? '') !== '') {
            $where[] = 'a.codigo = :aplicacion';
            $params['aplicacion'] = (string)$filters['aplicacion'];
        }
        if (($filters['activo'] ?? '') !== '') {
            $where[] = 'm.activo = :activo';
            $params['activo'] = (int)$filters['activo'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(m.codigo LIKE :q OR m.nombre LIKE :q)';
            $params['q'] = '%' . trim((string)$filters['q']) . '%';
        }
        $stmt = $this->pdo->prepare(
            'SELECT
                m.*, a.codigo AS aplicacion_codigo, a.nombre AS aplicacion_nombre,
                COUNT(DISTINCT rm.rol_id) AS roles_asociados_count,
                COUNT(DISTINCT um.usuario_id) AS usuarios_excepciones_count
             FROM modulos m
             INNER JOIN aplicaciones a ON a.id = m.aplicacion_id
             LEFT JOIN rol_modulo rm ON rm.modulo_id = m.id
             LEFT JOIN usuario_modulo um ON um.modulo_id = m.id
             ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
             GROUP BY m.id
             ORDER BY a.nombre, m.orden, m.nombre'
        );
        $stmt->execute($params);

        return ['modules' => $stmt->fetchAll()];
    }

    public function createModule(array $payload): array
    {
        $applicationId = $this->applicationId($payload['aplicacion_id'] ?? null);
        $codigo = $this->code($payload['codigo'] ?? '');
        $stmt = $this->pdo->prepare(
            'INSERT INTO modulos (aplicacion_id, codigo, nombre, descripcion, ruta, icono, color, grupo, orden, activo)
             VALUES (:aplicacion_id, :codigo, :nombre, :descripcion, :ruta, :icono, :color, :grupo, :orden, :activo)'
        );
        $stmt->execute([
            'aplicacion_id' => $applicationId,
            'codigo' => $codigo,
            'nombre' => trim((string)($payload['nombre'] ?? $codigo)),
            'descripcion' => $this->nullableString($payload['descripcion'] ?? null),
            'ruta' => $this->nullableString($payload['ruta'] ?? null),
            'icono' => trim((string)($payload['icono'] ?? 'apps')) ?: 'apps',
            'color' => $this->nullableString($payload['color'] ?? null),
            'grupo' => trim((string)($payload['grupo'] ?? 'GESTION')) ?: 'GESTION',
            'orden' => (int)($payload['orden'] ?? 0),
            'activo' => $this->boolInt($payload['activo'] ?? true),
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->audit('modulo.crear', 'modulo', $id, null, $this->fetchById('modulos', $id));

        return $this->moduleResponse($id);
    }

    public function updateModule(int $id, array $payload): array
    {
        if (array_key_exists('ruta', $payload)) {
            $ruta = trim((string)$payload['ruta']);
            if ($ruta === '' || !str_starts_with($ruta, '/') || str_contains($ruta, '://')) {
                throw new HttpError(422, 'La ruta del módulo debe ser interna y comenzar con /.', 'invalid_module_route');
            }
            $payload['ruta'] = $ruta;
        }
        foreach (['nombre', 'grupo', 'icono'] as $required) {
            if (array_key_exists($required, $payload) && trim((string)$payload[$required]) === '') {
                throw new HttpError(422, 'El catálogo contiene campos incompletos.', 'invalid_module_catalog');
            }
        }
        return $this->updateCatalog('modulos', 'modulo', $id, $payload, ['nombre', 'descripcion', 'ruta', 'icono', 'color', 'grupo', 'orden', 'activo']);
    }

    public function listRoles(array $filters): array
    {
        $where = [];
        $params = [];
        if (($filters['aplicacion'] ?? '') !== '') {
            $where[] = 'a.codigo = :aplicacion';
            $params['aplicacion'] = (string)$filters['aplicacion'];
        }
        $stmt = $this->pdo->prepare(
            'SELECT
                r.*, a.codigo AS aplicacion_codigo, a.nombre AS aplicacion_nombre,
                COUNT(DISTINCT ur.usuario_id) AS usuarios_count,
                COUNT(DISTINCT rm.modulo_id) AS modulos_count
             FROM roles r
             INNER JOIN aplicaciones a ON a.id = r.aplicacion_id
             LEFT JOIN usuario_rol ur ON ur.rol_id = r.id AND ur.activo = 1
             LEFT JOIN rol_modulo rm ON rm.rol_id = r.id
             ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
             GROUP BY r.id
             ORDER BY a.nombre, r.nombre'
        );
        $stmt->execute($params);

        return ['roles' => $stmt->fetchAll()];
    }

    public function createRole(array $payload): array
    {
        $applicationId = $this->applicationId($payload['aplicacion_id'] ?? null);
        $codigo = $this->code($payload['codigo'] ?? '');
        $stmt = $this->pdo->prepare(
            'INSERT INTO roles (aplicacion_id, codigo, nombre, descripcion, activo)
             VALUES (:aplicacion_id, :codigo, :nombre, :descripcion, :activo)'
        );
        $stmt->execute([
            'aplicacion_id' => $applicationId,
            'codigo' => $codigo,
            'nombre' => trim((string)($payload['nombre'] ?? $codigo)),
            'descripcion' => $this->nullableString($payload['descripcion'] ?? null),
            'activo' => $this->boolInt($payload['activo'] ?? true),
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->audit('rol.crear', 'rol', $id, null, $this->fetchById('roles', $id));

        return $this->roleResponse($id);
    }

    public function updateRole(int $id, array $payload): array
    {
        return $this->updateCatalog('roles', 'rol', $id, $payload, ['nombre', 'descripcion', 'activo']);
    }

    public function setRoleModules(int $roleId, array $payload): array
    {
        $role = $this->fetchById('roles', $roleId);
        if ($role === null) {
            throw new HttpError(404, 'Rol inexistente.', 'role_not_found');
        }
        $modules = $payload['modules'] ?? [];
        if (!is_array($modules)) {
            throw new HttpError(422, 'Matriz invalida.', 'invalid_role_matrix');
        }
        $before = $this->roleModules($roleId);

        $this->pdo->beginTransaction();
        try {
            foreach ($modules as $item) {
                $moduleId = $this->moduleId($item['modulo_id'] ?? null);
                $module = $this->fetchById('modulos', $moduleId);
                if ((int)$module['aplicacion_id'] !== (int)$role['aplicacion_id']) {
                    throw new HttpError(422, 'El modulo pertenece a otra aplicacion.', 'module_application_mismatch');
                }
                $values = [
                    'rol_id' => $roleId,
                    'aplicacion_id' => (int)$role['aplicacion_id'],
                    'modulo_id' => $moduleId,
                ];
                foreach (self::PERMISSIONS as $permission) {
                    $values[$permission] = $this->boolInt($item[$permission] ?? false);
                }
                $stmt = $this->pdo->prepare(
                    'INSERT INTO rol_modulo (
                        rol_id, aplicacion_id, modulo_id,
                        puede_ver, puede_crear, puede_editar, puede_eliminar, puede_exportar, puede_aprobar
                     ) VALUES (
                        :rol_id, :aplicacion_id, :modulo_id,
                        :puede_ver, :puede_crear, :puede_editar, :puede_eliminar, :puede_exportar, :puede_aprobar
                     ) ON DUPLICATE KEY UPDATE
                        puede_ver = VALUES(puede_ver),
                        puede_crear = VALUES(puede_crear),
                        puede_editar = VALUES(puede_editar),
                        puede_eliminar = VALUES(puede_eliminar),
                        puede_exportar = VALUES(puede_exportar),
                        puede_aprobar = VALUES(puede_aprobar)'
                );
                $stmt->execute($values);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
        $after = $this->roleModules($roleId);
        $this->audit('rol.modulos', 'rol', $roleId, $before, $after);

        return $this->roleResponse($roleId);
    }

    public function auditRows(): array
    {
        $rows = $this->pdo->query(
            'SELECT ia.id, ia.accion, ia.entidad_tipo, ia.entidad_id, ia.created_at,
                    u.email AS actor_email, u.nombre AS actor_nombre, u.apellido AS actor_apellido
             FROM identidad_auditoria ia
             LEFT JOIN usuarios u ON u.id = ia.usuario_actor_id
             ORDER BY ia.id DESC
             LIMIT 100'
        )->fetchAll();

        return ['audit' => $rows];
    }

    public function recordAudit(string $action, string $entityType, ?int $entityId, mixed $before, mixed $after): void
    {
        $this->audit($action, $entityType, $entityId, $before, $after);
    }

    private function updateCatalog(string $table, string $entity, int $id, array $payload, array $allowed): array
    {
        $before = $this->fetchById($table, $id);
        if ($before === null) {
            throw new HttpError(404, 'Registro inexistente.', $entity . '_not_found');
        }
        $this->assertVersion($before, $payload);
        $fields = [];
        $params = ['id' => $id];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $fields[] = "{$field} = :{$field}";
            if ($field === 'activo') {
                $params[$field] = $this->boolInt($payload[$field]);
            } elseif ($field === 'orden') {
                $params[$field] = (int)$payload[$field];
            } else {
                $params[$field] = $this->nullableString($payload[$field]);
            }
        }
        if (!$fields) {
            return [$entity => $before];
        }
        $stmt = $this->pdo->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
        $after = $this->fetchById($table, $id);
        $this->audit($entity . '.actualizar', $entity, $id, $before, $after);

        if ($entity === 'aplicacion') {
            return $this->applicationResponse($id);
        }
        if ($entity === 'modulo') {
            return $this->moduleResponse($id);
        }

        return $this->roleResponse($id);
    }

    private function userApplications(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.codigo, a.nombre, a.activo AS aplicacion_activa, ua.activo, ua.updated_at
             FROM aplicaciones a
             LEFT JOIN usuario_aplicacion ua ON ua.aplicacion_id = a.id AND ua.usuario_id = :id
             ORDER BY a.nombre'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll();
    }

    private function userRoles(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ur.usuario_id, ur.activo, r.id, r.codigo, r.nombre, a.codigo AS aplicacion_codigo, a.id AS aplicacion_id
             FROM usuario_rol ur
             INNER JOIN roles r ON r.id = ur.rol_id
             INNER JOIN aplicaciones a ON a.id = ur.aplicacion_id
             WHERE ur.usuario_id = :id
             ORDER BY a.nombre, r.nombre'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll();
    }

    private function userEffectiveModules(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.codigo, m.nombre, m.activo, a.codigo AS aplicacion_codigo, a.id AS aplicacion_id,
                    um.puede_ver AS ex_puede_ver, um.puede_crear AS ex_puede_crear,
                    um.puede_editar AS ex_puede_editar, um.puede_eliminar AS ex_puede_eliminar,
                    um.puede_exportar AS ex_puede_exportar, um.puede_aprobar AS ex_puede_aprobar,
                    um.motivo
             FROM modulos m
             INNER JOIN aplicaciones a ON a.id = m.aplicacion_id
             LEFT JOIN usuario_aplicacion ua ON ua.aplicacion_id = a.id AND ua.usuario_id = :id
             LEFT JOIN usuario_modulo um ON um.modulo_id = m.id AND um.usuario_id = :id
             WHERE ua.usuario_id IS NOT NULL OR EXISTS (
                SELECT 1 FROM usuario_rol ur WHERE ur.usuario_id = :id AND ur.aplicacion_id = a.id
             )
             ORDER BY a.nombre, m.orden, m.nombre'
        );
        $stmt->execute(['id' => $id]);
        $repo = new CorporateAccessRepository($this->pdo);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $effective = $repo->effectivePermissions($id, (string)$row['aplicacion_codigo'], (string)$row['codigo']);
            $row['effective'] = $effective;
            $row['origins'] = $this->permissionOrigins($row, $effective);
            $rows[] = $row;
        }

        return $rows;
    }

    private function roleModules(int $roleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id AS modulo_id, m.codigo, m.nombre,
                    rm.puede_ver, rm.puede_crear, rm.puede_editar,
                    rm.puede_eliminar, rm.puede_exportar, rm.puede_aprobar
             FROM roles r
             INNER JOIN modulos m ON m.aplicacion_id = r.aplicacion_id
             LEFT JOIN rol_modulo rm ON rm.rol_id = r.id AND rm.modulo_id = m.id
             WHERE r.id = :role_id
             ORDER BY m.orden, m.nombre'
        );
        $stmt->execute(['role_id' => $roleId]);

        return $stmt->fetchAll();
    }

    private function permissionOrigins(array $row, array $effective): array
    {
        if (($effective['reason'] ?? '') === 'superadmin') {
            return array_fill_keys(self::PERMISSIONS, 'SUPERADMIN');
        }
        $origins = [];
        foreach (self::PERMISSIONS as $permission) {
            $exceptionKey = 'ex_' . $permission;
            if ($row[$exceptionKey] !== null) {
                $origins[$permission] = 'EXCEPCION_USUARIO';
            } elseif (($effective['permissions'][$permission] ?? false) === true) {
                $origins[$permission] = 'ROL';
            } else {
                $origins[$permission] = 'SIN_PERMISO';
            }
        }

        return $origins;
    }

    private function applicationResponse(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM aplicaciones WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return ['application' => $stmt->fetch()];
    }

    private function moduleResponse(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, a.codigo AS aplicacion_codigo
             FROM modulos m INNER JOIN aplicaciones a ON a.id = m.aplicacion_id
             WHERE m.id = :id'
        );
        $stmt->execute(['id' => $id]);

        return ['module' => $stmt->fetch()];
    }

    private function roleResponse(int $id): array
    {
        $role = $this->fetchById('roles', $id);
        if ($role === null) {
            throw new HttpError(404, 'Rol inexistente.', 'role_not_found');
        }

        return ['role' => $role, 'modules' => $this->roleModules($id)];
    }

    private function fetchById(string $table, int $id): ?array
    {
        $allowed = ['usuarios', 'aplicaciones', 'modulos', 'roles'];
        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException('Tabla no permitida.');
        }
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function countWhere(string $table, string $where): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where)->fetchColumn();
    }

    private function applicationId(mixed $value): int
    {
        $id = (int)$value;
        if ($id <= 0 || $this->fetchById('aplicaciones', $id) === null) {
            throw new HttpError(422, 'Aplicacion invalida.', 'invalid_application');
        }

        return $id;
    }

    private function moduleId(mixed $value): int
    {
        $id = (int)$value;
        if ($id <= 0 || $this->fetchById('modulos', $id) === null) {
            throw new HttpError(422, 'Modulo invalido.', 'invalid_module');
        }

        return $id;
    }

    private function roleId(mixed $value): int
    {
        $id = (int)$value;
        if ($id <= 0 || $this->fetchById('roles', $id) === null) {
            throw new HttpError(422, 'Rol invalido.', 'invalid_role');
        }

        return $id;
    }

    private function ensureUserApplication(int $usuarioId, int $applicationId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuario_aplicacion (usuario_id, aplicacion_id, activo)
             VALUES (:usuario_id, :aplicacion_id, 1)
             ON DUPLICATE KEY UPDATE activo = VALUES(activo)'
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'aplicacion_id' => $applicationId]);
    }

    private function assertUserExists(int $id): void
    {
        if ($this->fetchById('usuarios', $id) === null) {
            throw new HttpError(404, 'Usuario inexistente.', 'user_not_found');
        }
    }

    private function assertVersion(array $row, array $payload): void
    {
        $expected = $payload['updated_at'] ?? null;
        if ($expected !== null && (string)$expected !== (string)($row['updated_at'] ?? '')) {
            throw new HttpError(409, 'El registro fue modificado por otro administrador. Recarga antes de guardar.', 'version_conflict');
        }
    }

    private function assertSuperadminActor(): void
    {
        if ((int)($this->actor['es_superadmin'] ?? 0) !== 1) {
            throw new HttpError(403, 'Solo superadmin puede modificar superadmin.', 'superadmin_required');
        }
    }

    private function assertNotLastActiveSuperadmin(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM usuarios
             WHERE es_superadmin = 1 AND estado = 'ACTIVO' AND id <> :id"
        );
        $stmt->execute(['id' => $userId]);
        if ((int)$stmt->fetchColumn() < 1) {
            throw new HttpError(409, 'No se puede quitar el ultimo superadmin activo.', 'last_superadmin');
        }
    }

    private function code(mixed $value): string
    {
        $code = strtoupper(trim((string)$value));
        if (!preg_match('/^[A-Z0-9_]{2,100}$/', $code)) {
            throw new HttpError(422, 'Codigo invalido.', 'invalid_code');
        }

        return $code;
    }

    private function boolInt(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0;
    }

    private function nullablePermission(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'HEREDADO') {
            return null;
        }
        if ($value === 'PERMITIR') {
            return 1;
        }
        if ($value === 'DENEGAR') {
            return 0;
        }

        return $this->boolInt($value);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string)$value);

        return $text === '' ? null : $text;
    }

    private function audit(string $action, string $entityType, ?int $entityId, mixed $before, mixed $after): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO identidad_auditoria (
                usuario_actor_id, accion, entidad_tipo, entidad_id,
                datos_anteriores_json, datos_nuevos_json, ip, user_agent
             ) VALUES (
                :usuario_actor_id, :accion, :entidad_tipo, :entidad_id,
                :datos_anteriores_json, :datos_nuevos_json, :ip, :user_agent
             )'
        );
        $stmt->execute([
            'usuario_actor_id' => (int)$this->actor['id'],
            'accion' => $action,
            'entidad_tipo' => $entityType,
            'entidad_id' => $entityId,
            'datos_anteriores_json' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'datos_nuevos_json' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        ]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
