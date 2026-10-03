<?php
declare(strict_types=1);

/** Compatibilidad operativa aislada para el catálogo de responsables; no autentica usuarios. */
function nexo_read_users(): array
{
    static $data;
    if (is_array($data)) {
        return $data;
    }

    // El selector de responsables reproduce el catálogo operativo de NEXO.
    // Este catálogo no autentica usuarios ni contiene hashes; sólo conserva
    // los identificadores/nombres que usan responsables_nexo.
    $catalogPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'responsables_nexo_catalogo.php';
    if (is_readable($catalogPath)) {
        $catalog = require $catalogPath;
        if (is_array($catalog)) {
            return $data = ['usuarios' => array_values(array_filter($catalog, static fn($user): bool => is_array($user) && ($user['activo'] ?? true) === true))];
        }
    }

    // En la PC local, usar la fuente operativa original sólo para el catálogo
    // del selector. La autenticación sigue usando exclusivamente la sesión
    // central y Hostinger.
    $legacyPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'DATOS_LOCALES' . DIRECTORY_SEPARATOR . 'nexo_usuarios.json';
    if (is_readable($legacyPath)) {
        $legacy = json_decode((string)file_get_contents($legacyPath), true);
        $users = [];
        foreach ((array)($legacy['usuarios'] ?? []) as $user) {
            if (!is_array($user) || ($user['activo'] ?? false) !== true) continue;
            $id = trim((string)($user['id'] ?? ''));
            if ($id === '') continue;
            $name = trim((string)($user['nombre'] ?? '')) ?: trim((string)($user['email'] ?? ''));
            $users[] = ['id' => $id, 'nombre' => $name, 'email' => (string)($user['email'] ?? ''), 'activo' => true];
        }
        return $data = ['usuarios' => $users];
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
