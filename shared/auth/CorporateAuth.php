<?php

declare(strict_types=1);

require_once __DIR__ . '/CorporateAccess.php';
require_once __DIR__ . '/CorporateAccessRepository.php';
require_once __DIR__ . '/FirebaseTokenVerifier.php';
require_once __DIR__ . '/HttpError.php';

final class CorporateAuth
{
    public function __construct(
        private PDO $pdo,
        private ?FirebaseTokenVerifier $verifier = null
    ) {
        $this->verifier ??= new FirebaseTokenVerifier();
    }

    public function requireAuthenticatedUser(?string $authorizationHeader): array
    {
        $identity = $this->verifier->verifyBearer($authorizationHeader);
        return $this->requireAuthenticatedIdentity($identity);
    }

    public function requireAuthenticatedIdentity(array $identity): array
    {
        $user = $this->resolveAndLinkUser($identity);

        if ($user === null) {
            throw new HttpError(403, 'Usuario corporativo no autorizado.', 'corporate_user_not_found');
        }

        if (!CorporateAccess::isActiveUser($user)) {
            throw new HttpError(403, 'Usuario corporativo inactivo.', 'inactive_user');
        }

        return [
            'identity' => $identity,
            'user' => $user,
        ];
    }

    public function requireModulePermission(array $user, string $moduleCode, string $permission): void
    {
        $repo = new CorporateAccessRepository($this->pdo);
        $result = $repo->requirePermission((int)$user['id'], 'CABLERAMARPLATENSE', $moduleCode, $permission);
        if (!$result['authorized']) {
            throw new HttpError(403, 'Permiso insuficiente.', 'permission_denied');
        }
    }

    private function resolveAndLinkUser(array $identity): ?array
    {
        $uid = (string)$identity['uid'];
        $email = strtolower(trim((string)$identity['email']));
        $conflict = null;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT *
                 FROM usuarios
                 WHERE firebase_uid = :uid_filter OR email = :email
                 ORDER BY CASE WHEN firebase_uid = :uid_order THEN 0 ELSE 1 END
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([
                'uid_filter' => $uid,
                'uid_order' => $uid,
                'email' => $email,
            ]);
            $user = $stmt->fetch();
            if (!is_array($user)) {
                $this->pdo->commit();
                return null;
            }

            $storedUid = $user['firebase_uid'] ?? null;
            if ($storedUid !== null && $storedUid !== '') {
                if (hash_equals((string)$storedUid, $uid)) {
                    $this->pdo->commit();
                    return $user;
                }

                $conflict = [(int)$user['id'], $email, (string)$storedUid, $uid];
                throw new HttpError(409, 'Conflicto de identidad.', 'identity_uid_conflict');
            }

            if (strtolower(trim((string)$user['email'])) !== $email) {
                throw new HttpError(409, 'Conflicto de email.', 'identity_email_conflict');
            }

            if (!CorporateAccess::isActiveUser($user)) {
                $this->pdo->commit();
                return $user;
            }

            $uidStmt = $this->pdo->prepare(
                'SELECT id
                 FROM usuarios
                 WHERE firebase_uid = :uid AND id <> :id
                 LIMIT 1
                 FOR UPDATE'
            );
            $uidStmt->execute([
                'uid' => $uid,
                'id' => (int)$user['id'],
            ]);
            if ($uidStmt->fetch()) {
                $conflict = [(int)$user['id'], $email, null, $uid];
                throw new HttpError(409, 'Conflicto de identidad.', 'identity_uid_conflict');
            }

            $update = $this->pdo->prepare(
                'UPDATE usuarios
                 SET firebase_uid = :uid
                 WHERE id = :id AND firebase_uid IS NULL'
            );
            $update->execute([
                'uid' => $uid,
                'id' => (int)$user['id'],
            ]);
            if ($update->rowCount() !== 1) {
                $conflict = [(int)$user['id'], $email, $user['firebase_uid'] ?? null, $uid];
                throw new HttpError(409, 'Conflicto de identidad.', 'identity_uid_conflict');
            }

            $user['firebase_uid'] = $uid;
            $this->pdo->commit();
            return $user;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($conflict !== null) {
                (new CorporateAccessRepository($this->pdo))->registerIdentityConflict(
                    $conflict[0],
                    $conflict[1],
                    $conflict[2],
                    $conflict[3],
                    'identity_uid_conflict',
                    'Conflicto detectado en autenticacion corporativa.'
                );
            }
            throw $exception;
        }
    }
}
