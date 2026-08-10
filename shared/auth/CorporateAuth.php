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
        $repo = new CorporateAccessRepository($this->pdo);
        $user = $repo->findUserByFirebaseIdentity($identity['uid'], $identity['email']);

        if ($user === null) {
            throw new HttpError(403, 'Usuario corporativo no autorizado.', 'corporate_user_not_found');
        }

        $link = CorporateAccess::resolveIdentityLink($user, $identity['uid'], $identity['email']);
        if (!$link['ok']) {
            if (($link['reason'] ?? '') === 'firebase_uid_conflict') {
                $repo->registerIdentityConflict(
                    (int)$user['id'],
                    $identity['email'],
                    $user['firebase_uid'] ?? null,
                    $identity['uid'],
                    'firebase_uid_conflict',
                    'Conflicto detectado en autenticacion corporativa.'
                );
            }
            throw new HttpError(409, 'Conflicto de identidad.', (string)($link['reason'] ?? 'identity_conflict'));
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
}

