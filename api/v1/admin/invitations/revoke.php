<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../_common.php';

api_handle(function () use ($pdo_lacablera): void {
    api_require_method('POST');
    $admin = api_admin_user($pdo_lacablera, 'puede_eliminar');

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new HttpError(400, 'ID invalido.', 'invalid_invitation_id');
    }

    $service = new CorporateInvitationService($pdo_lacablera);
    api_json(['ok' => true, 'result' => $service->revokeInvitation($id, (int)$admin['id'])]);
});
