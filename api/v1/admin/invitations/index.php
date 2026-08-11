<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../_common.php';

api_handle(function () use ($pdo_lacablera): void {
    $service = new CorporateInvitationService($pdo_lacablera);

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        api_admin_user($pdo_lacablera, 'puede_ver');
        api_json(['ok' => true, 'invitations' => $service->listInvitations()]);
    }

    api_require_method('POST');
    $admin = api_admin_user($pdo_lacablera, 'puede_crear');
    $created = $service->createInvitation(api_input(), (int)$admin['id']);
    (new IdentityAdminService($pdo_lacablera, $admin))->recordAudit(
        'invitacion.crear',
        'invitacion',
        (int)$created['id'],
        null,
        [
            'id' => (int)$created['id'],
            'email' => $created['email'],
            'expires_at' => $created['expires_at'],
        ]
    );

    api_json([
        'ok' => true,
        'invitation' => [
            'id' => $created['id'],
            'email' => $created['email'],
            'expires_at' => $created['expires_at'],
        ],
        'activation_token' => $created['token'],
    ], 201);
});
