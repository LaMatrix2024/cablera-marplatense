<?php
declare(strict_types=1);

/** Compatibilidad operativa aislada, sin leer el JSON de NEXO. */
function nexo_read_users(): array
{
    static $data;
    if (is_array($data)) {
        return $data;
    }
    $pdo = $GLOBALS['centralPdo'] ?? null;
    if (!$pdo instanceof PDO) return $data = ['usuarios' => []];
    $stmt = $pdo->query("SELECT id, nombre, apellido, email, estado FROM usuarios WHERE estado='ACTIVO' ORDER BY nombre, apellido, email");
    $users = [];
    foreach (($stmt ? $stmt->fetchAll() : []) as $user) {
        $name = trim(implode(' ', array_filter([(string)($user['nombre'] ?? ''), (string)($user['apellido'] ?? '')])));
        $users[] = ['id' => (string)$user['id'], 'nombre' => $name !== '' ? $name : (string)$user['email'], 'email' => (string)$user['email'], 'activo' => true];
    }
    return $data = ['usuarios' => $users];
}

function nexo_current_user(): array
{
    return $GLOBALS['visorUser'] ?? [];
}

function nexo_csrf_token(): string
{
    // El token efímero se entrega al iniciar sesión y permanece en sessionStorage;
    // nunca se genera ni se persiste un token nuevo desde la vista.
    return '';
}

function nexo_legacy_user_for_email(string $email): array
{
    return [];
}

function nexo_check_csrf(?string $token): void
{
    $validator = $GLOBALS['visorCsrfValidator'] ?? null;
    if (is_callable($validator)) {
        if (!$validator((string)$token)) throw new RuntimeException('CSRF inválido.');
        return;
    }
    $localAuth = $GLOBALS['localAuth'] ?? null;
    if (is_object($localAuth) && method_exists($localAuth, 'csrfValid')) {
        if (!$localAuth->csrfValid((string)$token)) throw new RuntimeException('CSRF inválido.');
        return;
    }
    $pdo = $GLOBALS['centralPdo'] ?? null;
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Sesión central no disponible.');
    }
    (new CentralSessionService($pdo))->csrf((string)$token);
}
