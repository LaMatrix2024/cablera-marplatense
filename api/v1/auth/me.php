<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

api_handle(function (): void {
    api_require_method('GET');

    $identity = (new FirebaseTokenVerifier())->verifyBearer(api_authorization_header());
    $pdo = api_database();
    $auth = new CorporateAuth($pdo);
    $session = $auth->requireAuthenticatedIdentity($identity);
    $service = new CorporateInvitationService($pdo);

    api_json(['ok' => true] + $service->authenticatedProfile((int)$session['user']['id']));
});

