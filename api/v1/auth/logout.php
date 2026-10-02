<?php
declare(strict_types=1);
require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../../../shared/auth/LocalAuthSession.php';
require_once __DIR__ . '/../../../shared/auth/RemoteCentralAuthClient.php';
require_once __DIR__ . '/../../../shared/auth/HostingerTokenService.php';

api_handle(function (): void {
    api_require_method('POST');
    if (lcm_auth_local_proxy_request()) {
        $local = new LocalAuthSession(); $snapshot = $local->snapshot();
        if (!is_array($snapshot)) api_json(['ok' => true, 'data' => ['logged_out' => true]]);
        if (!$local->csrfValid((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) throw new HttpError(403, 'Solicitud no válida.', 'CSRF_INVALID');
        $token = (string)$snapshot['token'];
        $local->logout();
        (new RemoteCentralAuthClient())->logout($token);
        api_json(['ok' => true, 'data' => ['logged_out' => true]]);
    }
    $token = lcm_bearer_token_logout();
    (new HostingerTokenService(api_database()))->logout($token);
    api_json(['ok' => true, 'data' => ['logged_out' => true]]);
});

function lcm_bearer_token_logout(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', (string)$header, $matches)) return trim($matches[1]);
    return '';
}
