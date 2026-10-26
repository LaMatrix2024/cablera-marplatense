<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_common.php';
require_once dirname(__DIR__, 3) . '/shared/auth/HostingerTokenService.php';

function lcm_central_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lcm_central_run(callable $callback): never
{
    try { $callback(); }
    catch (HttpError $error) { lcm_central_json(['ok' => false, 'error' => ['code' => $error->errorCode(), 'message' => $error->getMessage()]], $error->status()); }
    catch (Throwable $error) { @error_log(date('c') . ' | CENTRAL_INTERNAL_ERROR' . PHP_EOL, 3, dirname(__DIR__, 3) . '/logs/errores.log'); lcm_central_json(['ok' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => 'Error interno.']], 500); }
}

function lcm_central_bearer(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', (string)$header, $matches)) return trim($matches[1]);
    throw new HttpError(401, 'Sesión no válida.', 'MISSING_BEARER_TOKEN');
}

function lcm_central_database(): PDO
{
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Conexión de base no disponible.');
    }
    return $pdo;
}
