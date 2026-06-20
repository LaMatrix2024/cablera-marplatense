<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';

if ($requestMethod === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function mc_load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

function mc_bootstrap_env(): void
{
    $projectRoot = dirname(__DIR__, 4);

    $envPhp = $projectRoot . '/config/env.php';
    if (is_file($envPhp)) {
        require_once $envPhp;
    }

    mc_load_env_file($projectRoot . '/config/env.local');
    mc_load_env_file('C:/plantel/DATOS_LOCALES/plantel.env');
    mc_load_env_file($projectRoot . '/.env');
}

function mc_env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        if (defined($name)) {
            $constantValue = constant($name);
            return is_scalar($constantValue) ? (string)$constantValue : $default;
        }

        return $default;
    }

    return $value;
}

function mc_first_env(array $names, ?string $default = null): ?string
{
    foreach ($names as $name) {
        $value = mc_env($name);
        if ($value !== null) {
            return $value;
        }
    }

    return $default;
}

function mc_required_env(array|string $names): string
{
    $nameList = is_array($names) ? $names : [$names];
    $value = mc_first_env($nameList);
    if ($value === null) {
        mc_json(['ok' => false, 'error' => 'Falta variable de entorno: ' . implode(' o ', $nameList)], 500);
    }

    return $value;
}

function mc_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    mc_bootstrap_env();

    $host = mc_required_env(['DB_HOSTINGER_LAB_HOST', 'LAB_DB_HOST']);
    $port = mc_first_env(['DB_HOSTINGER_LAB_PORT', 'LAB_DB_PORT'], '3306');
    $db = mc_required_env(['DB_HOSTINGER_LAB_DATABASE', 'LAB_DB_NAME']);
    $user = mc_required_env(['DB_HOSTINGER_LAB_USER', 'LAB_DB_USER']);
    $pass = mc_required_env(['DB_HOSTINGER_LAB_PASSWORD', 'LAB_DB_PASS']);

    if ($db !== 'u767019378_laboratorio') {
        mc_json(['ok' => false, 'error' => 'Base de datos de laboratorio no autorizada.'], 500);
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function mc_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mc_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        mc_json(['ok' => false, 'error' => 'JSON invalido.'], 400);
    }

    return $data;
}

function mc_token_from(array $input = []): string
{
    $token = (string)($input['lista'] ?? $_GET['lista'] ?? '');
    $token = trim($token);

    if ($token === '') {
        mc_json(['ok' => false, 'error' => 'Falta parametro lista.'], 400);
    }

    if (!preg_match('/^[a-zA-Z0-9_-]{3,120}$/', $token)) {
        mc_json(['ok' => false, 'error' => 'Lista invalida.'], 400);
    }

    return $token;
}

function mc_get_list(PDO $pdo, string $token): array
{
    $stmt = $pdo->prepare(
        'SELECT id, token, nombre, activa
         FROM pwa_compra_listas
         WHERE token = :token
         LIMIT 1'
    );
    $stmt->execute(['token' => $token]);
    $list = $stmt->fetch();

    if (!$list || (int)$list['activa'] !== 1) {
        mc_json(['ok' => false, 'error' => 'Lista no autorizada.'], 403);
    }

    return $list;
}

function mc_normalize_user(?string $user): string
{
    $user = strtolower(trim((string)$user));
    if (in_array($user, ['claudio', 'faby'], true)) {
        return $user;
    }

    return 'sin_identificar';
}

function mc_get_estado_id(PDO $pdo, string $codigo): int
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM pwa_compra_estados
         WHERE codigo = :codigo AND activo = 1
         LIMIT 1'
    );
    $stmt->execute(['codigo' => strtoupper($codigo)]);
    $row = $stmt->fetch();

    if (!$row) {
        mc_json(['ok' => false, 'error' => 'Estado invalido.'], 400);
    }

    return (int)$row['id'];
}

function mc_require_item(PDO $pdo, int $id, int $listaId): array
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM pwa_compra_items
         WHERE id = :id AND lista_id = :lista_id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id, 'lista_id' => $listaId]);
    $item = $stmt->fetch();

    if (!$item) {
        mc_json(['ok' => false, 'error' => 'Producto no encontrado.'], 404);
    }

    return $item;
}

function mc_item_response(PDO $pdo, int $id, int $listaId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            i.id,
            i.producto,
            i.cantidad,
            i.usuario,
            i.creado_por,
            i.actualizado_por,
            i.comprado_por,
            i.cancelado_por,
            i.excluido_por,
            i.created_at,
            i.updated_at,
            i.comprado_at,
            i.cancelado_at,
            i.excluido_at,
            r.id AS rubro_id,
            r.nombre AS rubro,
            e.codigo AS estado,
            e.nombre AS estado_nombre
         FROM pwa_compra_items i
         INNER JOIN pwa_compra_estados e ON e.id = i.estado_id
         LEFT JOIN pwa_compra_rubros r ON r.id = i.rubro_id
         WHERE i.id = :id AND i.lista_id = :lista_id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id, 'lista_id' => $listaId]);
    $item = $stmt->fetch();

    if (!$item) {
        mc_json(['ok' => false, 'error' => 'Producto no encontrado.'], 404);
    }

    return $item;
}

function mc_validate_rubro(PDO $pdo, int $listaId, ?int $rubroId): ?int
{
    if ($rubroId === null || $rubroId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM pwa_compra_rubros
         WHERE id = :id AND lista_id = :lista_id AND activo = 1
         LIMIT 1'
    );
    $stmt->execute(['id' => $rubroId, 'lista_id' => $listaId]);

    if (!$stmt->fetch()) {
        mc_json(['ok' => false, 'error' => 'Rubro invalido.'], 400);
    }

    return $rubroId;
}

function mc_public_list(array $list): array
{
    return [
        'id' => (int)$list['id'],
        'token' => $list['token'],
        'nombre' => $list['nombre'],
    ];
}
