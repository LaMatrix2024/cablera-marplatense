<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

api_handle(function () use ($pdo_lacablera): void {
    api_require_method('GET');

    $token = (string)($_GET['token'] ?? '');
    if (trim($token) === '') {
        throw new HttpError(400, 'Token requerido.', 'missing_invitation_token');
    }

    $service = new CorporateInvitationService($pdo_lacablera);
    api_json(['ok' => true] + $service->invitationByToken($token));
});

