<?php

declare(strict_types=1);

final class CorporateAccess
{
    public const PERMISSIONS = [
        'puede_ver',
        'puede_crear',
        'puede_editar',
        'puede_eliminar',
        'puede_exportar',
        'puede_aprobar',
    ];

    public static function emptyPermissions(): array
    {
        return array_fill_keys(self::PERMISSIONS, false);
    }

    public static function fullPermissions(): array
    {
        return array_fill_keys(self::PERMISSIONS, true);
    }

    public static function combineRolePermissions(array $rolePermissionRows): array
    {
        $effective = self::emptyPermissions();

        foreach ($rolePermissionRows as $row) {
            foreach (self::PERMISSIONS as $permission) {
                $effective[$permission] = $effective[$permission] || self::toBool($row[$permission] ?? false);
            }
        }

        return $effective;
    }

    public static function applyUserException(array $permissions, ?array $exceptionRow): array
    {
        if ($exceptionRow === null) {
            return $permissions;
        }

        foreach (self::PERMISSIONS as $permission) {
            if (!array_key_exists($permission, $exceptionRow) || $exceptionRow[$permission] === null) {
                continue;
            }

            $permissions[$permission] = self::toBool($exceptionRow[$permission]);
        }

        return $permissions;
    }

    public static function userCan(array $effectivePermissions, string $permission): bool
    {
        return (bool)($effectivePermissions[$permission] ?? false);
    }

    public static function resolveIdentityLink(array $user, string $firebaseUid, string $firebaseEmail): array
    {
        $storedUid = $user['firebase_uid'] ?? null;
        $userEmail = strtolower(trim((string)($user['email'] ?? '')));
        $tokenEmail = strtolower(trim($firebaseEmail));

        if ($userEmail === '' || $userEmail !== $tokenEmail) {
            return ['ok' => false, 'reason' => 'email_mismatch'];
        }

        if ($storedUid === null || $storedUid === '') {
            return ['ok' => true, 'action' => 'link_uid'];
        }

        if (hash_equals((string)$storedUid, $firebaseUid)) {
            return ['ok' => true, 'action' => 'uid_already_linked'];
        }

        return ['ok' => false, 'reason' => 'firebase_uid_conflict'];
    }

    public static function isActiveUser(array $user): bool
    {
        return ($user['estado'] ?? null) === 'ACTIVO';
    }

    public static function isSuperadmin(array $user): bool
    {
        return self::toBool($user['es_superadmin'] ?? false);
    }

    private static function toBool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}

