<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env_loader.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

api_apply_cors();
api_require_https_in_production();

require_once __DIR__ . '/../../shared/auth/CorporateAuth.php';
require_once __DIR__ . '/../../shared/auth/CorporateInvitationService.php';
require_once __DIR__ . '/../../shared/auth/IdentityAdminService.php';
require_once __DIR__ . '/../../shared/auth/HttpError.php';

function api_database(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    require_once __DIR__ . '/../../config/env_loader.php';
    lcm_load_database_config();
    $pdo = api_crear_conexion_pdo(
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_USER,
        DB_PASS
    );
    return $pdo;
}

function api_crear_conexion_pdo(string $host, string $port, string $db, string $user, string $pass): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

    return new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function api_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new HttpError(400, 'JSON invalido.', 'invalid_json');
    }

    return $decoded;
}

function api_authorization_header(): ?string
{
    return $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
}

function api_apply_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $requiredAllowedOrigins = [
        'https://lacablera.com',
        'https://mis-apps-nine.vercel.app',
    ];
    $configuredAllowedOrigins = array_filter(array_map(
        'trim',
        explode(',', (string)lcm_config_value('LCM_ALLOWED_ORIGINS', ''))
    ));
    $allowed = array_values(array_unique(array_merge($requiredAllowedOrigins, $configuredAllowedOrigins)));

    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function api_require_https_in_production(): void
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
        return;
    }

    $isHttps = (($_SERVER['HTTPS'] ?? '') === 'on')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    if (!$isHttps && str_contains($host, 'lacablera.com')) {
        api_json([
            'ok' => false,
            'error' => 'https_required',
            'message' => 'HTTPS requerido.',
        ], 403);
    }
}

function api_handle(callable $callback): never
{
    try {
        $callback();
    } catch (HttpError $error) {
        api_json([
            'ok' => false,
            'error' => $error->errorCode(),
            'message' => $error->getMessage(),
            'details' => $error->details(),
        ], $error->status());
    } catch (Throwable $exception) {
        $logDir = __DIR__ . '/../../logs';
        if (is_dir($logDir) || @mkdir($logDir, 0775, true)) {
            @error_log(
                date('Y-m-d H:i:s') . ' | API v1 | ' . $exception::class . ' | ' . $exception->getMessage() . PHP_EOL,
                3,
                $logDir . '/api-v1-error.log'
            );
        }
        error_log('API v1 | ' . $exception->getMessage());
        $payload = [
            'ok' => false,
            'error' => 'internal_error',
            'message' => 'Error interno.',
        ];
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if (str_starts_with($host, '127.0.0.1') || str_starts_with($host, 'localhost')) {
            $payload['details'] = [
                'type' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }
        api_json($payload, 500);
    }
}

function api_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        throw new HttpError(405, 'Metodo no permitido.', 'method_not_allowed');
    }
}

function api_admin_user(PDO $pdo, string $permission = 'puede_crear'): array
{
    $auth = new CorporateAuth($pdo);
    $session = $auth->requireAuthenticatedUser(api_authorization_header());
    $auth->requireModulePermission($session['user'], 'IDENTIDAD_ACCESOS', $permission);

    return $session['user'];
}

function api_admin_service(PDO $pdo, string $permission = 'puede_editar'): IdentityAdminService
{
    return new IdentityAdminService($pdo, api_admin_user($pdo, $permission));
}
