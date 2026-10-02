<?php
declare(strict_types=1);
require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../../../shared/auth/LocalAuthSession.php';
require_once __DIR__ . '/../../../shared/auth/RemoteCentralAuthClient.php';
require_once __DIR__ . '/../../../shared/auth/HostingerTokenService.php';
api_handle(function (): void {
    api_require_method('POST');
    $input = api_input();
    $email = (string)($input['email'] ?? ''); $password = (string)($input['password'] ?? '');
    if (lcm_auth_local_proxy_request()) {
        try {
            $central = (new RemoteCentralAuthClient())->login($email, $password);
        } catch (RuntimeException $error) {
            throw new HttpError(503, 'La autenticación central está temporalmente inaccesible.', 'CENTRAL_UNAVAILABLE');
        }
        $csrf = (new LocalAuthSession())->establish((array)$central['profile'], (string)$central['token'], (string)($central['expires_at'] ?? ''));
        api_json(['ok' => true, 'data' => ['csrf_token' => $csrf, 'profile' => $central['profile']]]);
    }
    $central = (new HostingerTokenService(api_database()))->login($email, $password);
    api_json(['ok' => true, 'data' => $central]);
});
