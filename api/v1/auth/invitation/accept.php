<?php

declare(strict_types=1);

require_once __DIR__ . '/../../_common.php';

api_handle(function (): void {
    api_require_method('POST');
    $input = api_input();
    $token = (string)($input['token'] ?? '');
    if (trim($token) === '') {
        throw new HttpError(400, 'Token requerido.', 'missing_invitation_token');
    }

    $verifier = new FirebaseTokenVerifier();
    $identity = $verifier->verifyBearer(api_authorization_header());
    $profile = is_array($input['profile'] ?? null) ? $input['profile'] : [];

    $service = new CorporateInvitationService(api_database());
    api_json(['ok' => true, 'result' => $service->acceptInvitation($token, $identity, $profile)]);
});

