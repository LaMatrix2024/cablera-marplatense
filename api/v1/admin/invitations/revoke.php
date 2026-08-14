<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../_common.php';

api_handle(function (): void {
    api_require_method('POST');
    $pdo = api_database();
    $admin = api_admin_user($pdo, 'puede_eliminar');

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new HttpError(400, 'ID invalido.', 'invalid_invitation_id');
    }

    $service = new CorporateInvitationService($pdo);
    $before = $service->publicInvitation($id);
    $result = $service->revokeInvitation($id, (int)$admin['id']);
    $after = $service->publicInvitation($id);
    (new IdentityAdminService($pdo, $admin))->recordAudit(
        'invitacion.revocar',
        'invitacion',
        $id,
        $before,
        $after
    );
    api_json(['ok' => true, 'result' => $result]);
});
