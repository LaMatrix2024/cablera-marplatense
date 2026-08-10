<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../shared/auth/CorporateAuth.php';
require_once __DIR__ . '/../../shared/auth/CorporateInvitationService.php';
require_once __DIR__ . '/../../shared/auth/HttpError.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

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
        error_log('API v1 | ' . $exception->getMessage());
        api_json([
            'ok' => false,
            'error' => 'internal_error',
            'message' => 'Error interno.',
        ], 500);
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
