<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../_common.php';

api_handle(function (): void {
    api_require_method('GET');
    $pdo = api_database();
    api_admin_user($pdo, 'puede_ver');

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new HttpError(400, 'ID invalido.', 'invalid_invitation_id');
    }

    $service = new CorporateInvitationService($pdo);
    api_json(['ok' => true] + $service->publicInvitation($id));
});
