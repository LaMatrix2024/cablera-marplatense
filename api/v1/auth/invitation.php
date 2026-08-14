<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

api_handle(function (): void {
    api_require_method('GET');

    $token = (string)($_GET['token'] ?? '');
    if (trim($token) === '') {
        throw new HttpError(400, 'Token requerido.', 'missing_invitation_token');
    }

    $service = new CorporateInvitationService(api_database());
    api_json(['ok' => true] + $service->invitationByToken($token));
});

