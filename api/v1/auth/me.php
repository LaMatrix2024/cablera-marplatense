<?php
declare(strict_types=1);
require_once __DIR__ . '/../_common.php';
require_once __DIR__ . '/../../../shared/auth/LocalAuthSession.php';
require_once __DIR__ . '/../../../shared/auth/RemoteCentralAuthClient.php';
require_once __DIR__ . '/../../../shared/auth/HostingerTokenService.php';

api_handle(function (): void {
    api_require_method('GET');
    if (lcm_auth_local_proxy_request()) {
        $local = new LocalAuthSession(); $snapshot = $local->snapshot();
        if (!is_array($snapshot)) throw new HttpError(401, 'Sesión no iniciada.', 'UNAUTHENTICATED');
        if ($snapshot['fresh'] === true) api_json(['ok' => true, 'data' => $snapshot['profile']]);
        try {
            $central = (new RemoteCentralAuthClient())->session((string)$snapshot['token']);
            $local->refresh((array)$central['profile'], (string)($central['expires_at'] ?? ''));
            api_json(['ok' => true, 'data' => $central['profile']]);
        } catch (HttpError $error) {
            if ($error->status() === 401) { $local->logout(); throw $error; }
            if ($snapshot['offline'] === true) api_json(['ok' => true, 'data' => $snapshot['profile'], 'meta' => ['permissions_stale' => true]]);
            throw $error;
        } catch (Throwable $error) {
            if ($snapshot['offline'] === true) api_json(['ok' => true, 'data' => $snapshot['profile'], 'meta' => ['permissions_stale' => true]]);
            throw new HttpError(503, 'La autenticación central está temporalmente inaccesible.', 'CENTRAL_UNAVAILABLE');
        }
    }
    $token = lcm_bearer_token();
    $central = (new HostingerTokenService(api_database()))->session($token);
    api_json(['ok' => true, 'data' => $central['profile'], 'meta' => ['expires_at' => $central['expires_at']]]);
});

function lcm_bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', (string)$header, $matches)) return trim($matches[1]);
    throw new HttpError(401, 'Sesión no válida.', 'MISSING_BEARER_TOKEN');
}
