<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

api_handle(function () use ($pdo_lacablera): void {
    api_require_method('GET');

    $auth = new CorporateAuth($pdo_lacablera);
    $session = $auth->requireAuthenticatedUser(api_authorization_header());
    $service = new CorporateInvitationService($pdo_lacablera);

    api_json(['ok' => true] + $service->authenticatedProfile((int)$session['user']['id']));
});

