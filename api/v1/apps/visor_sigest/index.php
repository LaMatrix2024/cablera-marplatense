<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'v1' . DIRECTORY_SEPARATOR . '_common.php';
require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'visor_sigest' . DIRECTORY_SEPARATOR . 'responsables_nexo.php';
require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'visor_sigest' . DIRECTORY_SEPARATOR . 'legacy_operational.php';

// El mismo endpoint funciona en dos contextos: proxy local (sesión lcm_local)
// y ejecución remota en Hostinger (Bearer validado contra la base local del hosting).
$visorLocalProxy = lcm_auth_local_proxy_request();
$GLOBALS['visorLocalProxy'] = $visorLocalProxy;
$centralPdo = null;
$centralToken = '';
if (!$visorLocalProxy) {
    $connectionFile = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'conexion.php';
    if (is_readable($connectionFile)) require_once $connectionFile;
    require_once dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'HostingerTokenService.php';
    $centralPdo = $GLOBALS['pdo'] ?? (function_exists('api_database') ? api_database() : null);
    if (!$centralPdo instanceof PDO) api_json(['ok' => false, 'error' => ['code' => 'service_unavailable', 'message' => 'El servicio no está disponible.']], 503);
    $authorization = api_authorization_header() ?? '';
    if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $matches)) api_json(['ok' => false, 'error' => ['code' => 'unauthenticated', 'message' => 'Sesión no iniciada.']], 401);
    $centralToken = strtolower($matches[1]);
    try {
        $centralProfile = (array)(new CentralHostingerTokenService($centralPdo))->session($centralToken)['profile'];
    } catch (HttpError $error) {
        api_json(['ok' => false, 'error' => ['code' => $error->errorCode(), 'message' => $error->getMessage()]], $error->status());
    } catch (Throwable) {
        api_json(['ok' => false, 'error' => ['code' => 'service_unavailable', 'message' => 'No se pudo validar la sesión.']], 503);
    }
    $GLOBALS['centralPdo'] = $centralPdo;
    $GLOBALS['visorCsrfValidator'] = static function (string $provided) use ($centralPdo, $centralToken): bool {
        if ($provided === '' || !preg_match('/^[a-f0-9]{64}$/i', $provided)) return false;
        $stmt = $centralPdo->prepare('SELECT csrf_hash FROM auth_sesiones WHERE token_hash=:token AND revoked_at IS NULL AND expires_at > NOW() LIMIT 1');
        $stmt->execute(['token' => hash('sha256', $centralToken)]);
        $hash = (string)($stmt->fetchColumn() ?: '');
        return $hash !== '' && hash_equals($hash, hash('sha256', $provided));
    };
}

$localAuth = $visorLocalProxy ? new LocalAuthSession() : null;
$GLOBALS['localAuth'] = $localAuth;
$localSnapshot = $visorLocalProxy ? $localAuth->snapshot() : ['profile' => $centralProfile, 'token' => $centralToken];
if (!is_array($localSnapshot)) api_json(['ok' => false, 'error' => ['code' => 'unauthenticated', 'message' => 'Sesión no iniciada.']], 401);
$centralProfile = (array)$localSnapshot['profile'];
$centralSession = (array)($centralProfile['user'] ?? []);
$centralUser = [
    'id' => (int)($centralSession['id'] ?? 0),
    'email' => (string)($centralSession['email'] ?? ''),
    'nombre' => trim((string)($centralSession['nombre'] ?? '')),
    'apellido' => trim((string)($centralSession['apellido'] ?? '')),
    'estado' => (string)($centralSession['estado'] ?? ''),
    'es_superadmin' => (int)($centralSession['es_superadmin'] ?? 0),
];
$access = ['permissions' => []];
foreach (($centralProfile['modules'] ?? []) as $module) {
    if (($module['codigo'] ?? '') === 'visor_sigest') { $access['permissions'] = (array)($module['permissions'] ?? []); break; }
}
if (($access['permissions']['puede_ver'] ?? false) !== true) {
    api_json(['ok' => false, 'error' => ['code' => 'module_forbidden', 'message' => 'No tenés permiso para acceder a Visor SIGEST.']], 403);
}
$visorUser = $centralUser;
$GLOBALS['visorUser'] = $visorUser;
$visorRoles = array_map(static fn($role): string => strtolower(trim((string)(is_array($role) ? ($role['codigo'] ?? $role['nombre'] ?? '') : $role))), (array)($centralProfile['roles'] ?? []));
$visorIsAdmin = (int)$centralUser['es_superadmin'] === 1 || in_array('superadmin', $visorRoles, true) || in_array('administrador', $visorRoles, true);
$visorIsCoordinator = in_array('coordinador', $visorRoles, true);
$visorCanViewAll = $visorIsAdmin || $visorIsCoordinator;
$visorCanManageResponsible = ($visorIsAdmin || $visorIsCoordinator) && (($access['permissions']['puede_editar'] ?? false) === true || $visorIsAdmin);

function visor_proxy_to_hostinger(string $token): never
{
    $action = trim((string)($_GET['accion'] ?? ''));
    $allowedGet = ['accion', 'sigest_exact', 'id', 'sigest', 'ids'];
    $query = [];
    foreach ($allowedGet as $key) if (array_key_exists($key, $_GET)) $query[$key] = (string)$_GET[$key];
    $url = 'https://lacablera.com/api/v1/apps/visor_sigest/index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $headers = ['Accept: application/json', 'Authorization: Bearer ' . $token, 'X-Requested-With: XMLHttpRequest'];
    $body = null;
    if ($method === 'POST') {
        $allowedPost = ['csrf', 'sigest', 'sigest_exact', 'usuario_nexo_id', 'motivo', 'operacion', 'comentario', 'ids'];
        $form = [];
        foreach ($allowedPost as $key) if (array_key_exists($key, $_POST)) $form[$key] = (string)$_POST[$key];
        $body = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
    }
    $curl = curl_init($url);
    if ($curl === false) api_json(['ok' => false, 'error' => ['code' => 'proxy_unavailable', 'message' => 'No se pudo contactar la API de Visor SIGEST.']], 503);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
    $ca = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cacert.pem';
    if (is_readable($ca)) $options[CURLOPT_CAINFO] = $ca;
    if ($body !== null) $options[CURLOPT_POSTFIELDS] = $body;
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if ($response === false || $status < 100) {
        error_log('visor_sigest proxy failure: ' . ($curlError !== '' ? $curlError : 'no response'));
        api_json(['ok' => false, 'error' => ['code' => 'remote_unavailable', 'message' => 'La API de Visor SIGEST no está disponible.']], 503);
    }
    http_response_code($status);
    header('Cache-Control: no-store');
    if (str_contains(strtolower($contentType), 'spreadsheet') || str_contains(strtolower($contentType), 'excel')) {
        header('Content-Type: ' . ($contentType !== '' ? $contentType : 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="visor_sigest_obras.xlsx"');
        echo $response;
        exit;
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo $response;
    exit;
}

$visorAction = trim((string)($_GET['accion'] ?? ''));
if ($visorLocalProxy && $visorAction !== '') {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && $localAuth instanceof LocalAuthSession && in_array($visorAction, ['responsable_nexo', 'bitacora_guardar'], true)) {
        $csrf = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!$localAuth->csrfValid($csrf)) api_json(['ok' => false, 'error' => ['code' => 'csrf_invalid', 'message' => 'La sesión de seguridad venció. Recargá la aplicación e intentá nuevamente.']], 403);
    }
    visor_proxy_to_hostinger($centralToken);
}

const VISOR_ENV_PATHS = [
    __DIR__ . DIRECTORY_SEPARATOR . '.env',
    'C:\\plantel\\DATOS_LOCALES\\plantel.env',
];

function visor_env(): array
{
    $result = [];
    foreach (['DB_HOSTINGER_LAB_HOST' => 'LAB_DB_HOST', 'DB_HOSTINGER_LAB_PORT' => 'LAB_DB_PORT', 'DB_HOSTINGER_LAB_DATABASE' => 'LAB_DB_NAME', 'DB_HOSTINGER_LAB_USER' => 'LAB_DB_USER', 'DB_HOSTINGER_LAB_PASSWORD' => 'LAB_DB_PASS', 'DB_HOSTINGER_PLANTEL_HOST' => 'DB_HOST', 'DB_HOSTINGER_PLANTEL_PORT' => 'DB_PORT', 'DB_HOSTINGER_PLANTEL_DATABASE' => 'DB_NAME', 'DB_HOSTINGER_PLANTEL_USER' => 'DB_USER', 'DB_HOSTINGER_PLANTEL_PASSWORD' => 'DB_PASS'] as $target => $source) {
        if (defined($source)) $result[$target] = (string)constant($source);
    }
    foreach (VISOR_ENV_PATHS as $path) {
        if (!is_readable($path)) continue;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            $result[trim($key)] = $value;
        }
    }
    return $result;
}

function visor_env_value(array $env, string $key, string $default = ''): string
{
    $runtime = getenv($key);
    return ($runtime !== false && $runtime !== '') ? $runtime : ($env[$key] ?? $default);
}

function visor_pdo(array $env): PDO
{
    if (!(bool)($GLOBALS['visorLocalProxy'] ?? false)) {
        $shared = $GLOBALS['pdo_laboratorio'] ?? null;
        if ($shared instanceof PDO) return $shared;
    }
    $host = visor_env_value($env, 'DB_HOSTINGER_LAB_HOST');
    $port = visor_env_value($env, 'DB_HOSTINGER_LAB_PORT', '3306');
    $database = visor_env_value($env, 'DB_HOSTINGER_LAB_DATABASE', 'laboratorio');
    $user = visor_env_value($env, 'DB_HOSTINGER_LAB_USER');
    $password = visor_env_value($env, 'DB_HOSTINGER_LAB_PASSWORD');
    if ($host === '' || $user === '') throw new RuntimeException('La conexión de SIGEST no está configurada.');
    return new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function visor_pdo_plantel(array $env): PDO
{
    if (!(bool)($GLOBALS['visorLocalProxy'] ?? false)) {
        $shared = $GLOBALS['pdo'] ?? null;
        if ($shared instanceof PDO) return $shared;
    }
    $host = visor_env_value($env, 'DB_HOSTINGER_PLANTEL_HOST');
    $port = visor_env_value($env, 'DB_HOSTINGER_PLANTEL_PORT', '3306');
    $database = visor_env_value($env, 'DB_HOSTINGER_PLANTEL_DATABASE');
    $user = visor_env_value($env, 'DB_HOSTINGER_PLANTEL_USER');
    $password = visor_env_value($env, 'DB_HOSTINGER_PLANTEL_PASSWORD');
    if ($host === '' || $database === '' || $user === '') throw new RuntimeException('La conexión de bitácora no está configurada.');
    return new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function visor_json(array $payload, int $status = 200): never
{
    if (($payload['ok'] ?? false) === true && !array_key_exists('data', $payload)) {
        $payload = ['ok' => true, 'data' => array_diff_key($payload, ['ok' => true])];
    } elseif (($payload['ok'] ?? true) === false && is_string($payload['error'] ?? null)) {
        $payload = ['ok' => false, 'error' => ['code' => 'visor_sigest_error', 'message' => (string)$payload['error']]];
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function visor_h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function visor_date(mixed $value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') return '';
    try { return (new DateTimeImmutable($value))->format('d/m/Y'); } catch (Throwable) { return ''; }
}

function visor_days(mixed $value, ?DateTimeImmutable $today = null): ?int
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') return null;
    try {
        $timezone = new DateTimeZone('America/Argentina/Buenos_Aires');
        $base = new DateTimeImmutable((new DateTimeImmutable($value, $timezone))->format('Y-m-d'), $timezone);
        $reference = $today instanceof DateTimeImmutable ? new DateTimeImmutable($today->format('Y-m-d'), $timezone) : new DateTimeImmutable('today', $timezone);
        return max(0, (int) $base->diff($reference)->format('%r%a'));
    } catch (Throwable) { return null; }
}

function visor_number(mixed $value): float
{
    if ($value === null || $value === '') return 0.0;
    $text = trim((string) $value);
    if (str_contains($text, ',') && str_contains($text, '.')) {
        $text = str_replace('.', '', $text);
        $text = str_replace(',', '.', $text);
    } elseif (str_contains($text, ',')) {
        $text = str_replace(',', '.', $text);
    }
    return (float) $text;
}

function visor_row(array $row): array
{
    $row['age_hand'] = visor_days($row['const_f_contrata_envio'] ?? null);
    $row['age_execution'] = visor_days($row['const_f_ini'] ?? null);
    $row['time_advance'] = visor_days($row['const_f_avan'] ?? null);
    $row['total'] = visor_number($row['ing_lz'] ?? 0) + visor_number($row['ing_lc'] ?? 0) + visor_number($row['ing_l'] ?? 0) + visor_number($row['ing_n'] ?? 0);
    return $row;
}

function visor_identity_normalize(mixed $value): string
{
    $text = trim((string)($value ?? ''));
    if ($text === '') return '';
    $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;
    $text = preg_replace('/[\p{Cf}\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;
    if (class_exists('Normalizer')) $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function visor_operational_links(PDO $pdo): array
{
    try {
        $links = [];
        $statement = $pdo->query("SELECT usuario_nexo_id,valor_origen,valor_normalizado FROM nexo_usuarios_vinculaciones_operativas WHERE sistema_origen='SIGEST/TMA' AND campo_origen='contrata_us' AND activo=1");
        foreach ($statement as $link) $links[visor_identity_normalize($link['valor_origen'] ?? $link['valor_normalizado'] ?? '')] = (string)$link['usuario_nexo_id'];
        return $links;
    } catch (Throwable) {
        return [];
    }
}

function visor_effective_visibility_id(PDO $pdo, array $row, array $assignments, array $links): string
{
    $sigest = trim((string)($row['sisvadi'] ?? ''));
    $assignment = $assignments[$sigest] ?? null;
    if ($assignment !== null) return trim((string)($assignment['usuario_nexo_id'] ?? ''));
    return $links[visor_identity_normalize($row['contrata_us'] ?? '')] ?? '';
}

function visor_row_visible_to_user(PDO $pdo, array $row, array $user, bool $canViewAll, ?array $assignments = null, ?array $links = null): bool
{
    if ($canViewAll) return true;
    $assignments ??= rn_active_map($pdo, [trim((string)($row['sisvadi'] ?? ''))]);
    $links ??= visor_operational_links($pdo);
    $effective = visor_effective_visibility_id($pdo, $row, $assignments, $links);
    return $effective !== '' && $effective === trim((string)($user['id'] ?? ''));
}

function visor_grid_rows(PDO $pdo, ?PDO $plantel = null, ?array $user = null, bool $canViewAll = true): array
{
            $sql = "SELECT id, region, distrito, central, tipo_proyecto, sisvadi, titulo, ing_lz, ing_lc, ing_l, ing_n, ing_cash,
                   const_f_contrata_envio, const_f_ini, const_f_fin, const_f_fin_adm, const_f_dpl,
                   const_f_costo_certif, contrata_f_comp_fin, const_avan_total, const_f_avan, const_us, contrata_us,
                   proceso, bandeja_estado
            FROM sigest_obras_plantel_crea
            WHERE proceso = :proceso AND bandeja_estado IN ('Demorada', 'En Ejecución')
            ORDER BY id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['proceso' => 'En Construcción']);
    $rows = array_map('visor_row', $stmt->fetchAll());
    rn_schema($pdo);
    $sigestValues = [];
    foreach ($rows as $row) {
        $sigest = trim((string) ($row['sisvadi'] ?? ''));
        if ($sigest !== '') $sigestValues[$sigest] = true;
    }
    $assignments = rn_active_map($pdo, array_keys($sigestValues));
    $links = visor_operational_links($pdo);
    foreach ($rows as &$row) {
        $sigest = trim((string) ($row['sisvadi'] ?? ''));
        $assignment = $assignments[$sigest] ?? null;
        $row['responsable_sigest'] = trim((string) ($row['contrata_us'] ?? ''));
        $row['responsable_nexo'] = $assignment ? (string) $assignment['usuario_nexo_nombre_snapshot'] : null;
        $row['responsable_nexo_id'] = $assignment ? (string) $assignment['usuario_nexo_id'] : null;
        $row['responsable_efectivo'] = $row['responsable_nexo'] ?? $row['responsable_sigest'];
        $row['responsable_origen'] = $assignment ? 'NEXO' : 'SIGEST';
        $row['responsable_efectivo_display'] = $row['responsable_efectivo'];
        $row['visor_visibilidad_usuario_id'] = visor_effective_visibility_id($pdo, $row, $assignments, $links);
    }
    unset($row);
    if ($user !== null && !$canViewAll) $rows = array_values(array_filter($rows, static fn(array $row): bool => visor_row_visible_to_user($pdo, $row, $user, false, $assignments, $links)));
    if ($plantel === null || $rows === []) return $rows;
    foreach ($rows as $row) {
        $sigest = trim((string) ($row['sisvadi'] ?? ''));
        if ($sigest !== '') $sigestValues[$sigest] = true;
    }
    if ($sigestValues === []) return $rows;
    $marks = implode(',', array_fill(0, count($sigestValues), '?'));
    $countStmt = $plantel->prepare("SELECT TRIM(sigest) AS sigest, COUNT(*) AS bitacora_count FROM bitacora_obras WHERE TRIM(sigest) IN ($marks) GROUP BY TRIM(sigest)");
    $countStmt->execute(array_keys($sigestValues));
    $counts = [];
    foreach ($countStmt->fetchAll() as $countRow) $counts[trim((string) $countRow['sigest'])] = (int) $countRow['bitacora_count'];
    foreach ($rows as &$row) $row['bitacora_count'] = $counts[trim((string) ($row['sisvadi'] ?? ''))] ?? 0;
    unset($row);
    return $rows;
}

function visor_exact_sigest_row(PDO $pdo, string $sigest, array $user, bool $canViewAll, ?PDO $plantel = null): ?array
{
    $statement = $pdo->prepare("SELECT id, region, distrito, central, tipo_proyecto, sisvadi, titulo, ing_lz, ing_lc, ing_l, ing_n, ing_cash,
                   const_f_contrata_envio, const_f_ini, const_f_fin, const_f_fin_adm, const_f_dpl,
                   const_f_costo_certif, contrata_f_comp_fin, const_avan_total, const_f_avan, const_us, contrata_us,
                   proceso, bandeja_estado
            FROM sigest_obras_plantel_crea
            WHERE TRIM(sisvadi) = :sigest
            ORDER BY id DESC
            LIMIT 1");
    $statement->execute(['sigest' => trim($sigest)]);
    $row = $statement->fetch();
    if ($row === false) return null;
    $row = visor_row($row);
    rn_schema($pdo);
    $key = trim((string)($row['sisvadi'] ?? ''));
    $assignments = rn_active_map($pdo, [$key]);
    $links = visor_operational_links($pdo);
    $assignment = $assignments[$key] ?? null;
    $row['responsable_sigest'] = trim((string)($row['contrata_us'] ?? ''));
    $row['responsable_nexo'] = $assignment ? (string)$assignment['usuario_nexo_nombre_snapshot'] : null;
    $row['responsable_nexo_id'] = $assignment ? (string)$assignment['usuario_nexo_id'] : null;
    $row['responsable_efectivo'] = $row['responsable_nexo'] ?? $row['responsable_sigest'];
    $row['responsable_origen'] = $assignment ? 'NEXO' : 'SIGEST';
    $row['responsable_efectivo_display'] = $row['responsable_efectivo'];
    $row['visor_visibilidad_usuario_id'] = visor_effective_visibility_id($pdo, $row, $assignments, $links);
    if (!$canViewAll && !visor_row_visible_to_user($pdo, $row, $user, false, $assignments, $links)) return null;
    if ($plantel !== null && $key !== '') {
        $count = $plantel->prepare('SELECT COUNT(*) FROM bitacora_obras WHERE TRIM(sigest) = :sigest');
        $count->execute(['sigest' => $key]);
        $row['bitacora_count'] = (int)$count->fetchColumn();
    }
    return $row;
}

function visor_detail(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM sigest_obras_plantel_crea WHERE id = :id AND proceso = :proceso AND bandeja_estado IN ('Demorada', 'En Ejecución') LIMIT 1");
    $stmt->execute(['id' => $id, 'proceso' => 'En Construcción']);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function visor_valid_sigest(PDO $pdo, string $sigest): ?array
{
    $stmt = $pdo->prepare("SELECT sisvadi, titulo, central, bandeja_estado, contrata_us FROM sigest_obras_plantel_crea WHERE TRIM(sisvadi) = :sigest AND proceso = :proceso AND bandeja_estado IN ('Demorada', 'En Ejecución') LIMIT 1");
    $stmt->execute(['sigest' => trim($sigest), 'proceso' => 'En Construcción']);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function visor_responsable_data(PDO $pdo, array $row): array
{
    rn_schema($pdo);
    $resolved = rn_resolve($pdo, trim((string)($row['sisvadi'] ?? '')), (string)($row['contrata_us'] ?? ''));
    $row['responsable_sigest'] = $resolved['responsable_tma'];
    $row['responsable_nexo'] = $resolved['responsable_nexo'];
    $row['responsable_nexo_id'] = $resolved['usuario_nexo_id'];
    $row['responsable_efectivo'] = $resolved['responsable_efectivo'];
    $row['responsable_origen'] = $resolved['origen'];
    $row['responsable_efectivo_display'] = $row['responsable_efectivo'];
    $row['responsable_nexo_historial'] = rn_history($pdo, trim((string)($row['sisvadi'] ?? '')));
    return $row;
}

function visor_bitacora(PDO $pdo, string $sigest): array
{
    $stmt = $pdo->prepare('SELECT id, sigest, fecha, usuario, comentario FROM bitacora_obras WHERE sigest = :sigest ORDER BY fecha DESC, id DESC');
    $stmt->execute(['sigest' => $sigest]);
    return $stmt->fetchAll();
}

function visor_xlsx_col(int $index): string
{
    $letters = '';
    while ($index > 0) { $mod = ($index - 1) % 26; $letters = chr(65 + $mod) . $letters; $index = intdiv($index - $mod, 26) - 1; }
    return $letters;
}

function visor_xlsx_cell(string $ref, mixed $value, bool $number = false): string
{
    if ($number && is_numeric($value)) return '<c r="' . $ref . '"><v>' . (string) (float) $value . '</v></c>';
    return '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</t></is></c>';
}

function visor_write_xlsx(string $path, array $headers, array $rows): void
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('La generación XLSX no está disponible en este servidor.');
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo crear el archivo Excel.');
    $sheetRows = [];
    $headerCells = [];
    foreach ($headers as $i => $header) $headerCells[] = visor_xlsx_cell(visor_xlsx_col($i + 1) . '1', $header);
    $sheetRows[] = '<row r="1">' . implode('', $headerCells) . '</row>';
    foreach ($rows as $rIndex => $row) {
        $cells = [];
        foreach (array_values($row) as $i => $value) {
            $numeric = in_array($i, [5, 6, 7, 8, 9, 10, 11, 12, 19, 21], true);
            $cells[] = visor_xlsx_cell(visor_xlsx_col($i + 1) . ($rIndex + 2), $value, $numeric);
        }
        $sheetRows[] = '<row r="' . ($rIndex + 2) . '">' . implode('', $cells) . '</row>';
    }
    $last = visor_xlsx_col(count($headers));
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:' . $last . max(1, count($rows) + 1) . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>' . implode('', $sheetRows) . '</sheetData><autoFilter ref="A1:' . $last . max(1, count($rows) + 1) . '"/></worksheet>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Obras" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    $zip->addFromString('[Content_Types].xml', $types); $zip->addFromString('_rels/.rels', $rels); $zip->addFromString('xl/workbook.xml', $workbook); $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels); $zip->addFromString('xl/worksheets/sheet1.xml', $sheet); $zip->close();
}

$env = visor_env();
$action = (string) ($_GET['accion'] ?? '');
if (!$visorLocalProxy && trim($action) === '') {
    visor_json(['ok' => false, 'error' => ['code' => 'method_or_action_required', 'message' => 'Indicá una operación válida del visor.']], 405);
}
if ($action !== '') {
    try {
        $pdo = visor_pdo($env);
        if ($action === 'responsable_nexo') {
            if (!$visorCanManageResponsible) visor_json(['ok' => false, 'error' => 'No tenés permiso para realizar esta acción.'], 403);
            try { nexo_check_csrf((string) ($_POST['csrf'] ?? '')); } catch (Throwable) { visor_json(['ok' => false, 'error' => 'La sesión de seguridad venció. Recargá la aplicación e intentá nuevamente.'], 403); }
            rn_schema($pdo);
            $ids = json_decode((string) ($_POST['sigest'] ?? '[]'), true);
            if (!is_array($ids)) $ids = [];
            $remove = (string) ($_POST['operacion'] ?? '') === 'restablecer';
            try {
                $result = rn_apply_sigest_action($pdo, $visorUser, $ids, $remove ? null : trim((string) ($_POST['usuario_nexo_id'] ?? '')), trim((string) ($_POST['motivo'] ?? '')), $remove);
                if ($remove) {
                    $message = count($result['updates']) === 1 ? 'Responsable SIGEST restablecido correctamente.' : count($result['updates']) . ' responsables SIGEST restablecidos correctamente.';
                } else {
                    $destinationName = (string)($result['destination_name'] ?? 'el usuario seleccionado');
                    $changed = count($result['updates']); $unchanged = count($result['unchanged']);
                    if ($changed > 0 && $unchanged > 0) $message = $changed . ' obras asignadas correctamente a ' . $destinationName . '. ' . $unchanged . ' ya estaban asignadas y no requirieron cambios.';
                    elseif ($changed > 0) $message = $changed === 1 ? 'La obra fue asignada correctamente a ' . $destinationName . '.' : $changed . ' obras asignadas correctamente a ' . $destinationName . '.';
                    else $message = ($result['selected'] ?? 0) . ' obras ya estaban asignadas a ' . $destinationName . '. No fue necesario realizar cambios.';
                }
                visor_json(['ok' => true, 'actualizadas' => count($result['updates']), 'sin_cambio' => count($result['unchanged']), 'seleccionadas' => $result['selected'], 'tareas' => $result['updates'], 'message' => $message]);
            } catch (InvalidArgumentException $exception) {
                visor_json(['ok' => false, 'error' => $exception->getMessage()], 422);
            }
        }
        if ($action === 'datos') {
            try { $plantel = visor_pdo_plantel($env); } catch (Throwable) { $plantel = null; }
            $rows = visor_grid_rows($pdo, $plantel, $visorUser, $visorCanViewAll);
            $exact = trim((string)($_GET['sigest_exact'] ?? ''));
            if ($exact !== '' && preg_match('/^\d+$/', $exact) === 1) {
                $extra = visor_exact_sigest_row($pdo, $exact, $visorUser, $visorCanViewAll, $plantel);
                if ($extra !== null) {
                    $knownIds = array_fill_keys(array_map(static fn(array $row): string => (string)$row['id'], $rows), true);
                    if (!isset($knownIds[(string)$extra['id']])) $rows[] = $extra;
                }
            }
            visor_json(['ok' => true, 'rows' => $rows]);
        }
        if ($action === 'detalle') {
            $requestedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $exact = trim((string)($_GET['sigest_exact'] ?? ''));
            $row = ($exact !== '' && preg_match('/^\d+$/', $exact) === 1)
                ? visor_exact_sigest_row($pdo, $exact, $visorUser, $visorCanViewAll)
                : visor_detail($pdo, $requestedId);
            if ($row !== null && $requestedId > 0 && (int)($row['id'] ?? 0) !== $requestedId) $row = null;
            if ($row === null || !$visorCanViewAll && !visor_row_visible_to_user($pdo, $row, $visorUser, false)) visor_json(['ok' => false, 'error' => 'La obra no está disponible en el universo autorizado.'], 404);
            visor_json(['ok' => true, 'row' => visor_responsable_data($pdo, $row)]);
        }
        if ($action === 'bitacora') {
            $sigest = trim((string) ($_GET['sigest'] ?? ''));
            if ($sigest === '') visor_json(['ok' => false, 'error' => 'No se indicó la obra.'], 422);
            $obra = visor_valid_sigest($pdo, $sigest);
            if ($obra === null || !$visorCanViewAll && !visor_row_visible_to_user($pdo, $obra, $visorUser, false)) visor_json(['ok' => false, 'error' => 'La obra no está disponible en el universo autorizado.'], 404);
            $plantel = visor_pdo_plantel($env);
            visor_json(['ok' => true, 'obra' => $obra, 'entries' => visor_bitacora($plantel, $sigest)]);
        }
        if ($action === 'bitacora_guardar') {
            try { nexo_check_csrf((string) ($_POST['csrf'] ?? '')); } catch (Throwable) { visor_json(['ok' => false, 'error' => 'La sesión de seguridad venció. Recargá la aplicación e intentá nuevamente.'], 403); }
            $sigest = trim((string) ($_POST['sigest'] ?? ''));
            $comentario = trim((string) ($_POST['comentario'] ?? ''));
            if ($sigest === '' || $comentario === '') visor_json(['ok' => false, 'error' => 'El comentario es obligatorio.'], 422);
            if (mb_strlen($comentario, 'UTF-8') > 10000) visor_json(['ok' => false, 'error' => 'El comentario es demasiado largo.'], 422);
            $obra = visor_valid_sigest($pdo, $sigest);
            if ($obra === null || !$visorCanViewAll && !visor_row_visible_to_user($pdo, $obra, $visorUser, false)) visor_json(['ok' => false, 'error' => 'La obra no está disponible en el universo autorizado.'], 404);
            $user = nexo_current_user();
            $nombre = trim((string) ($user['nombre'] ?? $user['email'] ?? $user['id'] ?? 'Usuario NEXO'));
            $plantel = visor_pdo_plantel($env);
            $fecha = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $stmt = $plantel->prepare('INSERT INTO bitacora_obras (sigest, fecha, usuario, comentario) VALUES (:sigest, :fecha, :usuario, :comentario)');
            $stmt->execute(['sigest' => $sigest, 'fecha' => $fecha, 'usuario' => $nombre, 'comentario' => $comentario]);
            visor_json(['ok' => true, 'entry' => ['id' => (int) $plantel->lastInsertId(), 'sigest' => $sigest, 'fecha' => $fecha, 'usuario' => $nombre, 'comentario' => $comentario]]);
        }
        if ($action === 'exportar') {
            $idsRaw = trim((string) ($_POST['ids'] ?? $_GET['ids'] ?? ''));
            $ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw)), static fn (int $id): bool => $id > 0));
            if (!$ids || count($ids) > 10000) visor_json(['ok' => false, 'error' => 'No hay obras seleccionadas para exportar.'], 422);
            $authorizedRows = visor_grid_rows($pdo, null, $visorUser, $visorCanViewAll);
            $exportExact = trim((string)($_POST['sigest_exact'] ?? $_GET['sigest_exact'] ?? ''));
            if ($exportExact !== '' && preg_match('/^\d+$/', $exportExact) === 1) {
                $extra = visor_exact_sigest_row($pdo, $exportExact, $visorUser, $visorCanViewAll);
                if ($extra !== null) {
                    $knownIds = array_fill_keys(array_map(static fn(array $row): string => (string)$row['id'], $authorizedRows), true);
                    if (!isset($knownIds[(string)$extra['id']])) $authorizedRows[] = $extra;
                }
            }
            $allowedIds = array_fill_keys(array_map(static fn(array $row): string => (string)$row['id'], $authorizedRows), true);
            $ids = array_values(array_filter($ids, static fn(int $id): bool => isset($allowedIds[(string)$id])));
            if (!$ids) visor_json(['ok' => false, 'error' => 'No hay obras autorizadas para exportar.'], 403);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, central, tipo_proyecto, sisvadi, titulo, bandeja_estado, ing_lz, ing_lc, ing_l, ing_n, ing_cash, const_f_contrata_envio, const_f_ini, const_f_fin, const_f_fin_adm, const_f_dpl, const_f_costo_certif, contrata_f_comp_fin, const_avan_total, const_f_avan, const_us, contrata_us FROM sigest_obras_plantel_crea WHERE proceso = ? AND bandeja_estado IN ('Demorada','En Ejecución') AND id IN ($marks)");
            $stmt->execute(array_merge(['En Construcción'], $ids));
            $fetched = [];
            foreach ($stmt->fetchAll() as $dbRow) $fetched[(int) $dbRow['id']] = $dbRow;
            if ($exportExact !== '' && preg_match('/^\d+$/', $exportExact) === 1) {
                foreach ($authorizedRows as $authorizedRow) {
                    if (trim((string)($authorizedRow['sisvadi'] ?? '')) !== $exportExact || isset($fetched[(int)$authorizedRow['id']])) continue;
                    $extraStatement = $pdo->prepare('SELECT * FROM sigest_obras_plantel_crea WHERE id = :id LIMIT 1');
                    $extraStatement->execute(['id' => (int)$authorizedRow['id']]);
                    $extraRow = $extraStatement->fetch();
                    if ($extraRow !== false) $fetched[(int)$extraRow['id']] = $extraRow;
                }
            }
            $headers = ['CENTRAL','TIPO_PROY','SIGEST','TITULO','ESTADO','LZ','LC','L','N','TOTAL HS','Cash','Ant_en_Mano','Ant_Ejecucion','FECHA_INICIO','FECHA_FIN','FECHA_FIN_ADM','FECHA_DPL','FECHA_COSTO','FECHA_COMPROM.','AVANCE','FECHA_AVANCE','TIEMPO','USUARIO_TMA','RESPONSABLE','RESPONSABLE_SIGEST'];
            $rows = [];
            foreach ($ids as $id) { if (!isset($fetched[$id])) continue; $r = visor_responsable_data($pdo, visor_row($fetched[$id])); $rows[] = [$r['central'],$r['tipo_proyecto'],$r['sisvadi'],$r['titulo'],$r['bandeja_estado'],visor_number($r['ing_lz']),visor_number($r['ing_lc']),visor_number($r['ing_l']),visor_number($r['ing_n']),$r['total'],visor_number($r['ing_cash']),$r['age_hand'] ?? '',$r['age_execution'] ?? '',visor_date($r['const_f_ini']),visor_date($r['const_f_fin']),visor_date($r['const_f_fin_adm']),visor_date($r['const_f_dpl']),visor_date($r['const_f_costo_certif']),visor_date($r['contrata_f_comp_fin']),$r['const_avan_total'],$r['const_f_avan'] ? visor_date($r['const_f_avan']) : '',$r['time_advance'] ?? '',$r['const_us'],$r['responsable_efectivo'],$r['responsable_sigest']]; }
            $tmp = tempnam(sys_get_temp_dir(), 'visor_sigest_');
            visor_write_xlsx($tmp, $headers, $rows);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="visor_sigest_obras.xlsx"'); header('Content-Length: ' . filesize($tmp)); readfile($tmp); @unlink($tmp); exit;
        }
        visor_json(['ok' => false, 'error' => 'Acción no reconocida.'], 400);
    } catch (Throwable $e) {
        error_log('Visor SIGEST: ' . $e->getMessage());
        visor_json(['ok' => false, 'error' => 'No se pudo consultar SIGEST. Podés reintentar.'], 500);
    }
}
if ($action === '') {
    visor_json(['ok' => false, 'error' => ['code' => 'missing_action', 'message' => 'Acción requerida.']], 404);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= visor_h(nexo_csrf_token()) ?>">
<title>Visor SIGEST</title>
<script src="../../assets/js/nexo-loading.js"></script>
<style>
:root{--teal:#087f8c;--ink:#17324d;--line:#d4e1e8;--soft:#f4f8fa;--warn:#d49b20;--danger:#b74747;--sticky0:0px;--sticky1:140px;--sticky2:275px;--sticky3:385px}
*{box-sizing:border-box}body{margin:0;background:#eef4f7;color:var(--ink);font:14px/1.4 Arial,sans-serif}.visor{padding:18px;max-width:100%;overflow:hidden}.visor-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:12px}.visor-head h1{margin:0;font-size:24px}.visor-head p{margin:4px 0 0;color:#607a8b}.tabs{display:flex;gap:6px;margin-bottom:12px}.tab{border:1px solid #bcd2dc;background:#fff;color:var(--ink);padding:8px 14px;border-radius:8px;font-weight:700}.tab.active{background:var(--teal);border-color:var(--teal);color:#fff}.tab.disabled{opacity:.6;cursor:not-allowed}.toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;background:#fff;border:1px solid var(--line);border-radius:10px;padding:10px;margin-bottom:10px}.toolbar input{height:34px;border:1px solid #b7ccd7;border-radius:7px;padding:0 10px;min-width:260px}.toolbar button,.toolbar .button{border:1px solid var(--teal);background:var(--teal);color:#fff;border-radius:7px;padding:8px 12px;font-weight:700;cursor:pointer;text-decoration:none}.toolbar .secondary{background:#fff;color:var(--teal)}.count{font-weight:700;margin-right:auto}.filters{background:#fff;border:1px solid var(--line);border-radius:10px;margin-bottom:10px}.filters summary{cursor:pointer;padding:9px 12px;font-weight:700}.filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;padding:0 12px 12px}.filter-grid label{display:flex;flex-direction:column;gap:4px;font-size:12px;font-weight:700}.filter-grid select{height:34px;border:1px solid #b7ccd7;border-radius:7px;background:#fff;padding:0 7px}.multi{position:relative}.multi>button{width:100%;height:34px;text-align:left;border:1px solid #b7ccd7;border-radius:7px;background:#fff;padding:0 8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.multi-panel{display:none;position:absolute;z-index:10;top:38px;left:0;width:100%;max-height:220px;overflow:auto;background:#fff;border:1px solid #b7ccd7;border-radius:7px;box-shadow:0 8px 20px #17324d22;padding:6px}.multi.open .multi-panel{display:block}.multi-panel label{display:block;padding:4px;font-weight:400;white-space:nowrap}.multi-panel input{margin-right:6px}.active-filter{font-size:12px;color:var(--teal);font-weight:700}.table-wrap{background:#fff;border:1px solid var(--line);border-radius:10px;overflow:auto;max-height:calc(100vh - 205px);position:relative}.status{padding:30px;text-align:center;color:#607a8b}.status.error{color:var(--danger)}table{border-collapse:separate;border-spacing:0;min-width:2260px;width:100%}th,td{border-right:1px solid #e0e9ed;border-bottom:1px solid #e0e9ed;padding:7px 8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px}th{position:sticky;top:0;z-index:4;background:#dcecf1;color:#244d63;text-align:left;font-size:12px;cursor:pointer;user-select:none}th .resize{position:absolute;right:-3px;top:0;width:7px;height:100%;cursor:col-resize}tbody tr{cursor:pointer}tbody tr:hover{background:#f0f8fa}.sticky{position:sticky;z-index:2;background:#fff}.sticky-head{z-index:6!important;background:#dcecf1!important}.c0{left:var(--sticky0);width:140px;min-width:140px}.c1{left:var(--sticky1);width:135px;min-width:135px}.c2{left:var(--sticky2);width:110px;min-width:110px}.c3{left:var(--sticky3);width:220px;min-width:220px}.age{font-weight:700;border-radius:5px;padding:2px 6px}.age.green{background:#e0f1e4;color:#23733a}.age.yellow{background:#fff2cc;color:#8a6500}.age.red{background:#f8dddd;color:#9b3030}.modal-back{position:fixed;inset:0;background:#15304799;display:flex;align-items:center;justify-content:center;padding:20px;z-index:30}.modal{background:#fff;border-radius:12px;max-width:1000px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 20px 50px #0004}.modal-head{position:sticky;top:0;background:#fff;border-bottom:1px solid var(--line);padding:14px;display:flex;justify-content:space-between;align-items:center}.modal-head h2{margin:0;font-size:18px}.modal-close{border:1px solid #b7ccd7;background:#fff;border-radius:7px;padding:6px 10px}.modal-body{padding:14px}.detail-group{margin-bottom:14px}.detail-group h3{font-size:14px;color:var(--teal);border-bottom:1px solid var(--line);padding-bottom:4px;margin:0 0 7px}.detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:7px}.detail-item{background:var(--soft);padding:6px 8px;border-radius:5px}.detail-item small{display:block;color:#607a8b}.copy-ok{color:#23733a;font-weight:700;font-size:12px;margin-left:8px}@media(max-width:700px){.visor{padding:10px}.visor-head{display:block}.toolbar input{min-width:180px;width:100%}.table-wrap{max-height:calc(100vh - 260px)}}
</style>
<style>.modal-back[hidden]{display:none!important}</style>
<style>
html,body{height:100%;overflow:hidden}
.visor{height:100%;display:flex;flex-direction:column;min-height:0}
.table-wrap{flex:1 1 auto;min-height:0;max-height:none}
.action-col{position:sticky;left:0;z-index:3;width:40px;min-width:40px;max-width:40px;text-align:center;background:#fff;padding:4px}.action-head{z-index:7!important;background:#dcecf1!important}.bitacora-btn{width:27px;height:27px;border:1px solid #1686ba;background:#fff;color:#087f8c;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.bitacora-btn:hover,.bitacora-btn:focus-visible{background:#e7f6f8;outline:2px solid #a7dbe3}.bitacora-btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.estado-badge{display:inline-block;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:700}.estado-ejecucion{background:#dff3e5;color:#23733a}.estado-demorada{background:#fff0c7;color:#8a6500}.bitacora-modal{max-width:620px}.bitacora-summary{display:grid;grid-template-columns:110px 1fr;gap:4px 10px;background:#edf7fa;border:1px solid #d4e8ee;border-radius:8px;padding:10px;margin-bottom:12px}.bitacora-summary strong{font-size:12px;color:#315b70}.bitacora-list{max-height:48vh;overflow:auto;padding:4px 2px 4px 16px;border-left:2px solid #c4e2e8}.bitacora-entry{position:relative;padding:0 0 13px 14px}.bitacora-entry:before{content:"";position:absolute;left:-22px;top:3px;width:9px;height:9px;border-radius:50%;background:#1686ba;border:2px solid #fff;box-shadow:0 0 0 1px #1686ba}.bitacora-entry time{font-size:12px;color:#607a8b}.bitacora-entry .entry-user{font-weight:700;margin:2px 0;color:#17324d}.bitacora-entry .entry-comment{white-space:pre-wrap;background:#f7fafb;border:1px solid #e0eaee;border-radius:7px;padding:8px}.bitacora-empty{padding:22px 8px;text-align:center;color:#607a8b}.bitacora-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:10px}.bitacora-float{position:sticky;float:right;bottom:8px;width:44px;height:44px;border:0;border-radius:50%;background:#1686ba;color:#fff;font-size:27px;line-height:1;box-shadow:0 4px 12px #17324d44;cursor:pointer}.comment-form{border-top:1px solid var(--line);margin-top:12px;padding-top:12px}.comment-form[hidden]{display:none}.comment-form label{display:block;font-weight:700;margin-bottom:4px}.comment-form textarea{width:100%;min-height:110px;border:1px solid #b7ccd7;border-radius:7px;padding:9px;resize:vertical;font:inherit}.comment-status{font-size:12px;color:#23733a;margin-right:auto;align-self:center}@media(max-width:700px){.bitacora-summary{grid-template-columns:90px 1fr}.bitacora-list{max-height:42vh}}
</style>
<style>.visor .cash-summary{font-weight:700;color:#17324d;white-space:nowrap}.visor .numeric-cell{text-align:right}.visor .date-cell{text-align:center}.visor th.numeric-cell{text-align:right}.visor th.date-cell{text-align:center}</style>
<style>
.visor .action-col{width:40px;min-width:40px;max-width:40px;overflow:visible}
.visor .action-buttons{position:relative;display:flex;align-items:center;justify-content:center;gap:3px}
.visor .copy-btn{width:27px;height:27px;border:1px solid #1686ba;background:#fff;color:#087f8c;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.visor .copy-btn:hover,.visor .copy-btn:focus-visible{background:#e7f6f8;outline:2px solid #a7dbe3}
.visor .copy-btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.visor .copy-feedback{position:absolute;left:calc(100% + 6px);top:50%;transform:translateY(-50%);z-index:20;background:#17324d;color:#fff;border-radius:5px;padding:3px 6px;font-size:11px;white-space:nowrap;pointer-events:none}
.visor .copy-btn .check-icon{stroke:#23733a}
.visor .sigest-copy{position:relative;display:flex;align-items:center;gap:5px;width:100%;min-width:0}
.visor .sigest-copy .copy-btn{width:22px;height:22px;padding:0;border-color:#b7ccd7;color:#4d7183;border-radius:5px;flex:0 0 auto}
.visor .sigest-copy .copy-btn svg{width:14px;height:14px}
.visor .sigest-copy .copy-btn:hover,.visor .sigest-copy .copy-btn:focus-visible{color:#087f8c;border-color:#1686ba}
.visor .sigest-copy .copy-feedback{left:calc(100% + 5px);top:50%}
.visor .sigest-copy>span:first-child{display:block;flex:1 1 auto;min-width:0;max-width:none;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
.visor-copy-feedback{position:fixed;right:18px;bottom:18px;z-index:80;background:#17324d;color:#fff;border-radius:6px;padding:7px 10px;font-size:12px;box-shadow:0 6px 18px #17324d44;pointer-events:none}
.visor #tableWrap tbody tr{content-visibility:auto;contain-intrinsic-size:36px}
.visor .c2{width:170px;min-width:170px}
.visor th .resize{right:0;width:10px;height:100%;z-index:8;touch-action:none}
.visor th:hover .resize,.visor th .resize:hover{background:#1686ba33}
.visor th .resize::after{content:"";position:absolute;right:3px;top:50%;width:2px;height:18px;transform:translateY(-50%);border-left:2px dotted #6f95a5;opacity:.7}
.visor .bitacora-btn{position:relative}
.visor .bitacora-indicator{position:absolute;right:-4px;top:-4px;min-width:12px;height:12px;padding:0 3px;border-radius:999px;background:#d49b20;color:#fff;font-size:8px;line-height:12px;font-weight:700;text-align:center;box-shadow:0 0 0 2px #fff}
.visor .filter-clear{display:inline-flex;align-items:center;gap:5px;height:34px;padding:0 9px!important;border:1px solid #b7ccd7!important;background:#fff!important;color:#315b70!important;border-radius:7px!important;font-size:12px;font-weight:700;cursor:pointer;transition:background .15s ease,border-color .15s ease,color .15s ease}
.visor .filter-clear:hover:not(:disabled),.visor .filter-clear:focus-visible:not(:disabled){background:#edf7fa!important;border-color:#1686ba!important;color:#087f8c!important;outline:2px solid #c4e2e8}
.visor .filter-clear.is-inactive{opacity:.58}
.visor .rn-select{width:34px;min-width:34px;text-align:center}.visor .rn-select input{width:16px;height:16px}.visor .rn-badge{display:inline-block;margin-left:4px;padding:2px 5px;border-radius:999px;background:#e7f6f8;color:#087f8c;font-size:10px;font-weight:700;vertical-align:middle}.visor .rn-action{border:0;background:transparent;color:#087f8c;cursor:pointer;font-size:11px;padding:2px}.visor .rn-action:hover{text-decoration:underline}.visor .rn-modal{position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;padding:18px;background:#15304799}.visor .rn-modal[hidden]{display:none}.visor .rn-dialog{width:min(460px,100%);background:#fff;border-radius:12px;padding:16px;box-shadow:0 20px 50px #0004}.visor .rn-dialog-head,.visor .rn-dialog-actions{display:flex;align-items:center;justify-content:space-between;gap:8px}.visor .rn-dialog h2{margin:0;font-size:18px}.visor .rn-dialog form{display:grid;gap:10px;margin-top:12px}.visor .rn-dialog label{display:grid;gap:4px;font-weight:700}.visor .rn-dialog select,.visor .rn-dialog textarea{width:100%;border:1px solid #b7ccd7;border-radius:7px;padding:8px;font:inherit}.visor .rn-dialog .button{border:1px solid #087f8c;background:#087f8c;color:#fff;border-radius:7px;padding:8px 12px;font-weight:700;cursor:pointer}.visor .rn-remove{border:1px solid #efb3ad;background:#fff3f2;color:#b74747;border-radius:7px;padding:7px 10px;cursor:pointer}.visor .rn-status{font-size:12px;color:#b74747;min-height:18px}
</style>
</head>
<body>
<main class="visor filters-mode">
 <style>.view-switch{display:inline-flex;gap:0;border:1px solid #b7ccd7;border-radius:7px;overflow:hidden}.view-switch#obraViewSwitch{margin-bottom:10px}.view-switch-button{border:0;background:#fff;color:#315b70;padding:7px 10px;font-size:12px;font-weight:700;cursor:pointer}.view-switch-button+ .view-switch-button{border-left:1px solid #b7ccd7}.view-switch-button.active{background:#087f8c;color:#fff}.view-switch-button:focus-visible{outline:2px solid #a7dbe3;outline-offset:-2px}</style>
 <div class="view-switch" id="obraViewSwitch" role="group" aria-label="Vista de obras"><?php if (!$visorIsAdmin): ?><button id="myWorksView" class="view-switch-button" type="button" aria-pressed="false">MIS OBRAS</button><?php endif; ?><button id="allWorksView" class="view-switch-button" type="button" aria-pressed="false">TODAS</button></div>
 <header class="visor-head"><div><h1>VISOR SIGEST</h1><p>Seguimiento de obras en construcción</p></div><button class="button secondary" id="exportBtn" type="button">EXPORTAR EXCEL</button></header>
 <nav class="tabs" aria-label="Módulos"><button class="tab active" type="button">OBRAS</button><button class="tab disabled" type="button" disabled>MANTENIMIENTO · Próximamente</button></nav>
<section class="toolbar"><input id="quickSearch" type="search" placeholder="Buscar por central, SIGEST, título o responsable efectivo" aria-label="Buscar obras"><span class="count" id="countLabel">Cargando obras…</span><span class="cash-summary" id="cashLabel"></span><span class="active-filter" id="activeFilter" hidden></span><?php if ($visorCanManageResponsible): ?><button id="assignResponsibleBtn" type="button" disabled>ASIGNAR RESPONSABLE</button><?php endif; ?><button class="secondary" id="resetView" type="button">RESTABLECER VISTA</button></section>
 <details class="filters" id="filters" open><summary>FILTROS <span id="filterBadge" class="active-filter" hidden></span></summary><div class="filter-grid">
  <label>REGIÓN<div class="multi" id="regionMulti"><button type="button">Todas</button><div class="multi-panel"></div></div></label>
  <label>DISTRITO<div class="multi" id="distritoMulti"><button type="button">Todos</button><div class="multi-panel"></div></div></label>
  <label>CENTRAL<div class="multi" id="centralMulti"><button type="button">Todas</button><div class="multi-panel"></div></div></label>
  <label>TIPO_PROY<div class="multi" id="tipoMulti"><button type="button">Todos</button><div class="multi-panel"></div></div></label>
  <label>RESPONSABLE<select id="plantelFilter"><option value="">Todos</option></select></label>
  <label>USUARIO_TMA<select id="tmaFilter"><option value="">Todos</option></select></label>
  <label>BANDEJA ESTADO<select id="stateFilter"><option value="">Todas</option><option>Demorada</option><option>En Ejecución</option></select></label>
  <label>AVANCE<select id="advanceFilter"><option value="">Todos</option><option value="0-25">0–25%</option><option value="26-50">26–50%</option><option value="51-75">51–75%</option><option value="76-99">76–99%</option><option value="100">100%</option></select></label>
   <label>ANTIGÜEDAD EN MANO<select id="handFilter"><option value="">Todos</option><option value="0-30">0–30 días</option><option value="31-60">31–60 días</option><option value="gt60">+ de 60 días</option></select></label>
   <label>ANTIGÜEDAD EJECUCIÓN<select id="executionFilter"><option value="">Todos</option><option value="0-30">0–30 días</option><option value="31-60">31–60 días</option><option value="gt60">+ de 60 días</option></select></label>
   <label>SITUACIÓN<select id="situacionFilter"><option value="">Todas</option><option value="iniciadas">Iniciadas</option><option value="pendientes">Pendientes</option></select></label>
   <button class="filter-clear" id="clearFilters" type="button"><span aria-hidden="true">×</span> Limpiar filtros</button>
 </div></details>
 <style>
 #rnModal.rn-modal{position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;padding:18px;background:#15304799}
 #rnModal.rn-modal[hidden]{display:none!important}
 #rnModal .rn-dialog{width:min(460px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:12px;padding:16px;box-shadow:0 20px 50px #0004}
 #rnModal .rn-dialog-head,#rnModal .rn-dialog-actions{display:flex;align-items:center;justify-content:space-between;gap:8px}
 #rnModal .rn-dialog h2{margin:0;font-size:18px}
 #rnModal .rn-dialog form{display:grid;gap:10px;margin-top:12px}
 #rnModal .rn-dialog label{display:grid;gap:4px;font-weight:700}
 #rnModal .rn-dialog select,#rnModal .rn-dialog textarea{width:100%;border:1px solid #b7ccd7;border-radius:7px;padding:8px;font:inherit}
 #rnModal .rn-dialog-actions{margin-top:4px}
 #rnModal .rn-dialog-actions button,#rnModal .rn-remove{min-height:34px;padding:7px 14px;border-radius:7px;font-weight:700;cursor:pointer}
 #rnModal .rn-dialog-actions .secondary{border:1px solid #b7ccd7;background:#fff;color:#315b70}
 #rnModal .rn-dialog-actions .button{border:1px solid #087f8c;background:#087f8c;color:#fff}
 #rnModal .rn-remove{border:1px solid #efb3ad;background:#fff3f2;color:#b74747}
 #rnModal #rnStatus.error{color:#b74747;font-weight:700;background:#fff3f2;border:1px solid #efb3ad;border-radius:7px;padding:8px}
 #rnModal .rn-dialog-actions{justify-content:flex-end}
 .visor .selection-col{position:sticky;left:0;z-index:4;width:34px;min-width:34px;max-width:34px;text-align:center;background:#fff;padding:4px}
 .visor .action-col{position:sticky;left:34px;z-index:4;width:40px;min-width:40px;max-width:40px;text-align:center;background:#fff;padding:4px;overflow:visible}
 .visor .selection-head,.visor .action-head{z-index:7!important;background:#dcecf1!important}
 </style>
 <section class="table-wrap" id="tableWrap"><div class="status">Cargando universo base…</div></section>
</main>
<div id="detailBack" class="modal-back" hidden><section class="modal" role="dialog" aria-modal="true" aria-labelledby="detailTitle"><header class="modal-head"><h2 id="detailTitle">Detalle de obra</h2><button class="modal-close" id="detailClose" type="button">Cerrar</button></header><div class="modal-body" id="detailBody"></div></section></div>
<div id="bitacoraBack" class="modal-back" hidden><section class="modal bitacora-modal" role="dialog" aria-modal="true" aria-labelledby="bitacoraTitle"><header class="modal-head"><h2 id="bitacoraTitle">BITÁCORA DE OBRA</h2><button class="modal-close" id="bitacoraClose" type="button">Cerrar</button></header><div class="modal-body"><div id="bitacoraSummary" class="bitacora-summary"></div><div id="bitacoraList" class="bitacora-list"></div><button id="bitacoraAdd" class="bitacora-float" type="button" title="Agregar comentario" aria-label="Agregar comentario">+</button><form id="commentForm" class="comment-form" hidden><label for="commentText">COMENTARIO *</label><textarea id="commentText" maxlength="10000" placeholder="Ingrese el comentario de la bitácora..."></textarea><div class="bitacora-actions"><span id="commentStatus" class="comment-status"></span><button id="commentCancel" class="secondary" type="button">CANCELAR</button><button id="commentSave" class="button" type="submit">GUARDAR</button></div></form></div></section></div>
<?php if ($visorCanManageResponsible): ?>
<div id="rnModal" class="rn-modal" hidden><section class="rn-dialog" role="dialog" aria-modal="true" aria-labelledby="rnTitle"><div class="rn-dialog-head"><h2 id="rnTitle">Asignar Responsable NEXO</h2><button id="rnClose" class="modal-close" type="button">Cerrar</button></div><p id="rnCount" class="summary"></p><form id="rnForm"><input type="hidden" name="csrf" value="<?= visor_h(nexo_csrf_token()) ?>"><input type="hidden" name="sigest" id="rnSigest"><label>Responsable NEXO<select name="usuario_nexo_id" id="rnUser" required><option value="">Seleccionar usuario activo</option><?php foreach (rn_active_users() as $user): ?><option value="<?= visor_h($user['id']) ?>"><?= visor_h($user['nombre']) ?></option><?php endforeach; ?></select></label><label>Motivo (opcional)<textarea name="motivo" maxlength="500" rows="3"></textarea></label><p id="rnStatus" class="rn-status" role="status"></p><div class="rn-dialog-actions"><button id="rnCancel" class="secondary" type="button">Cancelar</button><button id="rnSave" class="button" type="submit">Asignar</button></div></form><div id="rnRestoreWrap" hidden><button id="rnRestore" class="rn-remove" type="button">RESTABLECER RESPONSABLE SIGEST</button></div></section></div>
<?php endif; ?>
<script>window.visorIsAdmin=<?= $visorIsAdmin ? 'true' : 'false' ?>;window.visorIsCoordinator=<?= $visorIsCoordinator ? 'true' : 'false' ?>;window.visorCanViewAll=<?= $visorCanViewAll ? 'true' : 'false' ?>;window.visorCanManageResponsible=<?= $visorCanManageResponsible ? 'true' : 'false' ?>;window.visorUser=<?= json_encode(['id'=>(string)($visorUser['id']??''),'nombre'=>(string)($visorUser['nombre']??'')], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script>
(() => {
 const cols=[
  ['central','CENTRAL','text'],['tipo_proyecto','TIPO_PROY','text'],['sisvadi','SIGEST','text'],['titulo','TITULO','text'],['bandeja_estado','ESTADO','status'],['ing_lz','LZ','number'],['ing_lc','LC','number'],['ing_l','L','number'],['ing_n','N','number'],['total','TOTAL HS','number'],['ing_cash','Cash','money'],['age_hand','Ant_en_Mano','days'],['age_execution','Ant_Ejecucion','days'],['const_f_ini','FECHA_INICIO','date'],['const_f_fin','FECHA_FIN','date'],['const_f_fin_adm','FECHA_FIN_ADM','date'],['const_f_dpl','FECHA_DPL','date'],['const_f_costo_certif','FECHA_COSTO','date'],['contrata_f_comp_fin','FECHA_COMPROM.','date'],['const_avan_total','AVANCE','percent'],['const_f_avan','FECHA_AVANCE','date'],['time_advance','TIEMPO','daysTime'],['const_us','USUARIO_TMA','text'],['responsable_efectivo','RESPONSABLE','responsable']
 ];
 const responsibleColumn=cols.find(column=>column[1]==='RESPONSABLE'); if(responsibleColumn) responsibleColumn[0]='responsable_efectivo';
 const stickyCount=4, key='visor_sigest_view_v1'; let allRows=[], exactRow=null, exactSearchToken=0, filtered=[], rowById=new Map(), order=[], widths={}, sort={key:null,dir:1}; let selectedRegion=[],selectedDistrito=[],selectedCentral=[],selectedTipo=[],selectedIds=new Set(); let obraView=window.visorCanViewAll?'all':'mine';
  const $=id=>document.getElementById(id), esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
 const normalizeFilterValue=value=>String(value??'').normalize('NFKC').replace(/[\u0000-\u001F\u007F-\u009F\u200B-\u200D\u2060\uFEFF]/gu,'').replace(/[\u00A0\u202F]/gu,' ').replace(/\s+/gu,' ').trim();
 const hasStartDate=value=>{const text=normalizeFilterValue(value);if(!text||/^0{4}-0{2}-0{2}(?:[ T].*)?$/.test(text))return false;const date=new Date(text.replace(' ','T'));return !Number.isNaN(date.getTime())&&date.getFullYear()>0};
 const num=v=>{if(v===null||v==='')return null; const n=Number(String(v).replace(',','.')); return Number.isFinite(n)?n:null};
  function isMine(r){if(obraView!=='mine')return true;const userId=normalizeFilterValue(window.visorUser?.id),visibilityId=normalizeFilterValue(r.visor_visibilidad_usuario_id),assignedId=normalizeFilterValue(r.responsable_nexo_id);if(userId&&visibilityId)return userId===visibilityId;if(userId&&assignedId)return userId===assignedId;return normalizeFilterValue(r.responsable_efectivo)===normalizeFilterValue(window.visorUser?.nombre)}
 function viewRows(){return allRows.filter(isMine)}
 function updateViewButtons(){const mine=$('myWorksView'),all=$('allWorksView');if(!mine||!all)return;mine.classList.toggle('active',obraView==='mine');all.classList.toggle('active',obraView==='all');mine.setAttribute('aria-pressed',obraView==='mine'?'true':'false');all.setAttribute('aria-pressed',obraView==='all'?'true':'false')}
  function setObraView(view){obraView=window.visorIsAdmin?'all':(view==='mine'?'mine':'all');updateViewButtons();refreshFilters();render()}
 const fmtNum=v=>{const n=num(v);return n===null?'':n.toLocaleString('es-AR',{maximumFractionDigits:0})};
 const fmtMoney=v=>{const n=num(v);return n===null?'':'$ '+n.toLocaleString('es-AR',{maximumFractionDigits:0})};
 const fmtDate=v=>{if(!v)return ''; const d=new Date(String(v).replace(' ','T')); return Number.isNaN(d.getTime())?'':d.toLocaleDateString('es-AR')};
 const fmtPct=v=>{const n=num(v); if(n===null)return ''; return (n<=1?n*100:n).toLocaleString('es-AR',{maximumFractionDigits:0})+'%'};
 const ageClass=v=>v===null||v===''?'':(Number(v)<=30?'green':Number(v)<=60?'yellow':'red');
 const timeClass=v=>v===null||v===''?'':(Number(v)<=10?'green':Number(v)<=15?'yellow':'red');
 const display=(c,r)=>c==='money'?fmtMoney(r):c==='number'?fmtNum(r):c==='date'?fmtDate(r):c==='percent'?fmtPct(r):c==='days'?(r==null?'':`<span class="age ${ageClass(r)}">${esc(r)} días</span>`):c==='daysTime'?(r==null?'':`<span class="age ${timeClass(r)}">${esc(r)} días</span>`):c==='status'?`<span class="estado-badge ${r==='Demorada'?'estado-demorada':'estado-ejecucion'}">${esc(r)}</span>`:esc(r);
 const numericTypes=['number','money','days','daysTime','percent'];
 const cellClass=(c,sticky=false)=>`${sticky?'sticky ':''}${numericTypes.includes(c[2])?'numeric-cell ':''}${c[2]==='date'?'date-cell ':''}`.trim();
 function persist(){localStorage.setItem(key,JSON.stringify({order,widths}))} function loadPrefs(){const defaults=cols.map(c=>c[0]);try{const p=JSON.parse(localStorage.getItem(key)||'{}'),valid=new Set(defaults),candidate=Array.isArray(p.order)?p.order.filter(k=>valid.has(k)).filter((k,i,a)=>a.indexOf(k)===i):[];order=candidate.length===defaults.length?candidate:defaults.slice();widths=p.widths&&typeof p.widths==='object'?p.widths:{}}catch{order=defaults.slice();widths={}}const sigestWidth=Number(widths.sisvadi);widths.sisvadi=Number.isFinite(sigestWidth)?Math.max(sigestWidth,170):170}
 function updateSticky(){let left=74; for(let i=0;i<stickyCount;i++){const k=order[i],w=widths[k]||([140,135,170,220][i]); document.documentElement.style.setProperty('--sticky'+i,left+'px'); left+=w}}
 function exactSigestQuery(){const value=normalizeFilterValue($('quickSearch').value);return /^\d+$/.test(value)?value:''}
 function currentBase(){const exact=exactSigestQuery();if(exact&&exactRow&&normalizeFilterValue(exactRow.sisvadi)===exact&&isMine(exactRow))return [exactRow];return allRows.filter(r=>matchesFilters(r,''))}
  function rangeMatch(v,range,percent){if(!range)return true;let n=num(v);if(n===null)return false;if(percent&&n<=1)n*=100;if(range==='100')return n>=100;if(range==='gt60')return n>60;const [a,b]=range.split('-').map(Number);return Number.isFinite(a)&&Number.isFinite(b)&&n>=a&&n<=b}
 function values(rows,k){return [...new Set(rows.map(r=>normalizeFilterValue(r[k])).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'es'))}
 function renderMulti(id,vals,selected,label){const root=$(id),panel=root.querySelector('.multi-panel'),button=root.querySelector('button');panel.innerHTML=vals.map(v=>`<label><input type="checkbox" value="${esc(v)}" ${selected.includes(v)?'checked':''}>${esc(v)}</label>`).join('');button.textContent=selected.length?selected.join(', '):label; panel.querySelectorAll('input').forEach(i=>i.addEventListener('change',()=>{if(id==='regionMulti')selectedRegion=[...panel.querySelectorAll('input:checked')].map(x=>x.value);else if(id==='distritoMulti')selectedDistrito=[...panel.querySelectorAll('input:checked')].map(x=>x.value);else if(id==='centralMulti')selectedCentral=[...panel.querySelectorAll('input:checked')].map(x=>x.value);else selectedTipo=[...panel.querySelectorAll('input:checked')].map(x=>x.value);refreshFilters();render() }))}
 const multiFilterDefs=[
  {key:'region',field:'region',get:()=>selectedRegion,set:v=>{selectedRegion=v}},
  {key:'distrito',field:'distrito',get:()=>selectedDistrito,set:v=>{selectedDistrito=v}},
  {key:'central',field:'central',get:()=>selectedCentral,set:v=>{selectedCentral=v}},
  {key:'tipo',field:'tipo_proyecto',get:()=>selectedTipo,set:v=>{selectedTipo=v}}
 ];
 const scalarFilterDefs=[
  {key:'responsable',id:'plantelFilter',label:'Todos',field:'responsable_efectivo',values:rows=>values(rows,'responsable_efectivo')},
  {key:'tma',id:'tmaFilter',label:'Todos',field:'const_us',values:rows=>values(rows,'const_us')},
  {key:'estado',id:'stateFilter',label:'Todas',field:'bandeja_estado',values:rows=>values(rows,'bandeja_estado')},
  {key:'avance',id:'advanceFilter',label:'Todos',options:[['0-25','0?25%'],['26-50','26?50%'],['51-75','51?75%'],['76-99','76?99%'],['100','100%']],matches:(r,v)=>rangeMatch(r.const_avan_total,v,true)},
  {key:'mano',id:'handFilter',label:'Todos',options:[['0-30','0?30 d?as'],['31-60','31?60 d?as'],['gt60','+ de 60 d?as']],matches:(r,v)=>rangeMatch(r.age_hand,v,false)},
  {key:'ejecucion',id:'executionFilter',label:'Todos',options:[['0-30','0?30 d?as'],['31-60','31?60 d?as'],['gt60','+ de 60 d?as']],matches:(r,v)=>rangeMatch(r.age_execution,v,false)},
  {key:'situacion',id:'situacionFilter',label:'Todas',options:[['iniciadas','Iniciadas'],['pendientes','Pendientes']],matches:(r,v)=>v==='iniciadas'?hasStartDate(r.const_f_ini):!hasStartDate(r.const_f_ini)}
 ];
 function matchesFilters(r,skip){if(!isMine(r))return false;const q=normalizeFilterValue($('quickSearch').value).toLowerCase();const haystack=[r.central,r.sisvadi,r.titulo,r.responsable_efectivo].map(v=>normalizeFilterValue(v).toLowerCase()).join(' ');if(q&&!haystack.includes(q))return false;const multi=[['region',selectedRegion,'region'],['distrito',selectedDistrito,'distrito'],['central',selectedCentral,'central'],['tipo',selectedTipo,'tipo_proyecto']];for(const [key,selection,field] of multi){if(key!==skip&&selection.length&&!selection.includes(normalizeFilterValue(r[field])))return false}const scalar=[['responsable','plantelFilter',r=>normalizeFilterValue(r.responsable_efectivo)===normalizeFilterValue($("plantelFilter").value)],['tma','tmaFilter',r=>normalizeFilterValue(r.const_us)===normalizeFilterValue($("tmaFilter").value)],['estado','stateFilter',r=>normalizeFilterValue(r.bandeja_estado)===normalizeFilterValue($("stateFilter").value)],['avance','advanceFilter',r=>rangeMatch(r.const_avan_total,$('advanceFilter').value,true)],['mano','handFilter',r=>rangeMatch(r.age_hand,$('handFilter').value,false)],['ejecucion','executionFilter',r=>rangeMatch(r.age_execution,$('executionFilter').value,false)],['situacion','situacionFilter',r=>$("situacionFilter").value==='iniciadas'?hasStartDate(r.const_f_ini):!hasStartDate(r.const_f_ini)]];for(const [key,id,predicate] of scalar){if(key!==skip&&$(id).value&&!predicate(r))return false}return true}
 function optionRows(skip){return allRows.filter(r=>matchesFilters(r,skip))}
  function scalarOptions(def,rows){if(def.values)return def.values(rows).map(v=>[v,v]);if(def.key==='situacion')return def.options;return def.options.filter(([value])=>rows.some(r=>def.matches(r,value)))}
 function fillSelectEntries(el,entries,label){const old=normalizeFilterValue(el.value);el.innerHTML='<option value="">'+esc(label)+'</option>'+entries.map(([value,text])=>'<option value="'+esc(value)+'">'+esc(text)+'</option>').join('');el.value=entries.some(([value])=>normalizeFilterValue(value)===old)?old:''}
 function refreshFilters(){for(let pass=0;pass<10;pass++){let changed=false;for(const def of multiFilterDefs){const allowed=new Set(values(optionRows(def.key),def.field));const next=def.get().map(normalizeFilterValue).filter(v=>allowed.has(v));if(next.length!==def.get().length){def.set(next);changed=true}}for(const def of scalarFilterDefs){const el=$(def.id),current=normalizeFilterValue(el.value);if(!current)continue;const allowed=scalarOptions(def,optionRows(def.key)).map(([value])=>normalizeFilterValue(value));if(!allowed.includes(current)){el.value='';changed=true}}if(!changed)break}for(const def of multiFilterDefs){renderMulti(def.key==='region'?'regionMulti':def.key==='distrito'?'distritoMulti':def.key==='central'?'centralMulti':'tipoMulti',values(optionRows(def.key),def.field),def.get(),def.key==='region'?'Todas':def.key==='distrito'?'Todos':def.key==='central'?'Todas':'Todos')}for(const def of scalarFilterDefs)fillSelectEntries($(def.id),scalarOptions(def,optionRows(def.key)),def.label)}
 function render(){filtered=currentBase();if(sort.key){const c=cols.find(x=>x[0]===sort.key);filtered.sort((a,b)=>{let x=a[sort.key],y=b[sort.key];if(c&&['number','money','days','daysTime','percent'].includes(c[2])){x=num(x)??-Infinity;y=num(y)??-Infinity}else if(c&&c[2]==='date'){x=new Date(x||0).getTime();y=new Date(y||0).getTime()}else{x=String(x??'').toLowerCase();y=String(y??'').toLowerCase()}return x<y?-sort.dir:x>y?sort.dir:0})} $('countLabel').textContent=filtered.length.toLocaleString('es-AR')+' obras';const active=[selectedRegion.length,selectedDistrito.length,selectedCentral.length,selectedTipo.length,$('plantelFilter').value,$('tmaFilter').value,$('stateFilter').value,$('advanceFilter').value,$('handFilter').value,$('executionFilter').value].filter(Boolean).length; $('filterBadge').hidden=!active;$('filterBadge').textContent=active?` · Filtros activos: ${active}`:'';const head=order.map((k,i)=>{const c=cols.find(x=>x[0]===k);const cls=i<stickyCount?'sticky sticky-head c'+i:'';return `<th draggable="${i>=stickyCount}" data-key="${k}" class="${cls}" style="width:${widths[k]||''}px">${esc(c[1])}<span class="resize"></span></th>`}).join('');const body=filtered.map(r=>`<tr data-id="${r.id}"><td class="action-col"><button type="button" class="bitacora-btn" data-sigest="${esc(r.sisvadi||'')}" title="Ver bitácora" aria-label="Ver bitácora"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h8l3 3v15H7zM15 3v4h4M9 11h6M9 15h6M9 19h4"/></svg></button></td>${order.map((k,i)=>{const c=cols.find(x=>x[0]===k);return `<td class="${i<stickyCount?'sticky c'+i:''}" style="width:${widths[k]||''}px" title="${esc(r[k]??'')}">${display(c[2],r[k])}</td>`}).join('')}</tr>`).join('');$('tableWrap').innerHTML=`<table><thead><tr><th class="action-col action-head">&nbsp;</th>${head}</tr></thead><tbody>${body}</tbody></table>`;updateSticky();bindTable()}
 function applyGridAlignment(){const byKey=new Map(cols.map(c=>[c[0],c])),headers=[...document.querySelectorAll('#tableWrap th[data-key]')];headers.forEach(th=>{const c=byKey.get(th.dataset.key);if(c)th.classList.add(...cellClass(c,false).split(' ').filter(Boolean))});document.querySelectorAll('#tableWrap tbody tr').forEach(tr=>{[...tr.cells].forEach((td,index)=>{if(index<2)return;const c=byKey.get(order[index-2]);if(c)td.classList.add(...cellClass(c,false).split(' ').filter(Boolean))})})}
 function applyColumnWidths(){const tableWrap=$('tableWrap');tableWrap.querySelectorAll('th[data-key]').forEach(th=>{const width=Number(widths[th.dataset.key]);if(!Number.isFinite(width)||width<=0)return;th.style.minWidth=width+'px';const column=th.cellIndex;tableWrap.querySelectorAll(`tbody td:nth-child(${column+1})`).forEach(td=>{td.style.minWidth=width+'px'})})}
  let copyFeedbackTimer=0;
  function fallbackCopy(text){const area=document.createElement('textarea');area.value=text;area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.focus();area.select();let ok=false;try{ok=document.execCommand('copy')}catch{}area.remove();return ok}
  function showOperationFeedback(message){let feedback=$('visorCopyFeedback');if(!feedback){feedback=document.createElement('span');feedback.id='visorCopyFeedback';feedback.className='visor-copy-feedback';document.body.appendChild(feedback)}feedback.textContent=String(message||'Operaci?n completada.');feedback.hidden=false;clearTimeout(copyFeedbackTimer);copyFeedbackTimer=setTimeout(()=>{feedback.hidden=true},4200)}
  async function copySigest(button){const value=String(button.dataset.sigest||'');let ok=false;try{if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(value);ok=true}else ok=fallbackCopy(value)}catch{ok=fallbackCopy(value)}const copyIcon=button.querySelector('.copy-icon'),checkIcon=button.querySelector('.check-icon');if(ok&&copyIcon&&checkIcon){copyIcon.hidden=true;checkIcon.hidden=false;setTimeout(()=>{copyIcon.hidden=false;checkIcon.hidden=true},1500)}let feedback=$('visorCopyFeedback');if(!feedback){feedback=document.createElement('span');feedback.id='visorCopyFeedback';feedback.className='visor-copy-feedback';feedback.setAttribute('role','status');document.body.appendChild(feedback)}feedback.textContent=ok?'SIGEST copiado':'No se pudo copiar el SIGEST';feedback.hidden=false;clearTimeout(copyFeedbackTimer);copyFeedbackTimer=setTimeout(()=>{feedback.hidden=true},1800)}
  function addCopyActions(){document.querySelectorAll('#tableWrap tbody tr').forEach(tr=>{const action=tr.querySelector('.action-col'),bit=action?.querySelector('.bitacora-btn'),row=rowById.get(String(tr.dataset.id)),count=Number(row?.bitacora_count||0);if(bit){bit.title=count>0?`Ver bitácora · ${count} comentario${count===1?'':'s'}`:'Ver bitácora';bit.setAttribute('aria-label',bit.title);if(count>0&&!bit.querySelector('.bitacora-indicator')){const badge=document.createElement('span');badge.className='bitacora-indicator';badge.setAttribute('aria-label',`${count} comentario${count===1?'':'s'}`);badge.textContent=count>99?'99+':String(count);bit.appendChild(badge)}}const sigestIndex=order.indexOf('sisvadi'),sigestCell=sigestIndex>=0?tr.children[sigestIndex+1]:null;if(!sigestCell||sigestCell.querySelector('.sigest-copy'))return;const value=String(row?.sisvadi||''),text=document.createElement('span');text.textContent=value;const wrap=document.createElement('span');wrap.className='sigest-copy';const copy=document.createElement('button');copy.type='button';copy.className='copy-btn';copy.dataset.sigest=value;copy.title='Copiar SIGEST';copy.setAttribute('aria-label','Copiar SIGEST');copy.innerHTML='<svg class="copy-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="5" width="12" height="16" rx="2"></rect><path d="M9 5V3h8v2M10 10h6M10 14h6M10 18h4"></path></svg><svg class="check-icon" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="m5 12 4 4L19 6"></path></svg>';wrap.append(text,copy);sigestCell.replaceChildren(wrap);copy.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();copySigest(copy)})})}
  function updateClearButton(){const active=selectedRegion.length||selectedDistrito.length||selectedCentral.length||selectedTipo.length||$('plantelFilter').value||$('tmaFilter').value||$('stateFilter').value||$('advanceFilter').value||$('handFilter').value||$('executionFilter').value||String($('quickSearch').value||'').trim();$('clearFilters').classList.toggle('is-inactive',!active)}
  function installResizeCapture(){const tableWrap=$('tableWrap');tableWrap.addEventListener('mousedown',event=>{const handle=event.target.closest('th .resize');if(!handle)return;const th=handle.closest('th');if(!th?.dataset.key)return;event.preventDefault();event.stopPropagation();const startX=event.clientX,startWidth=th.getBoundingClientRect().width,column=th.cellIndex,keyName=th.dataset.key,wasDraggable=th.draggable;th.draggable=false;const move=moveEvent=>{moveEvent.preventDefault();const width=Math.max(70,Math.round(startWidth+moveEvent.clientX-startX));widths[keyName]=width;th.style.width=width+'px';th.style.minWidth=width+'px';tableWrap.querySelectorAll(`tbody td:nth-child(${column+1})`).forEach(td=>{td.style.width=width+'px';td.style.minWidth=width+'px'});updateSticky()};const finish=()=>{persist();th.draggable=wasDraggable;document.removeEventListener('mousemove',move);document.removeEventListener('mouseup',finish)};document.addEventListener('mousemove',move);document.addEventListener('mouseup',finish)},true);tableWrap.addEventListener('dragstart',event=>{if(event.target.closest('th .resize')){event.preventDefault();event.stopPropagation()}},true)}
  function installPointerResize(){const tableWrap=$('tableWrap');tableWrap.addEventListener('pointerdown',event=>{const handle=event.target.closest('th .resize');if(!handle)return;const th=handle.closest('th');if(!th?.dataset.key)return;event.preventDefault();event.stopPropagation();const startX=event.clientX,startWidth=th.getBoundingClientRect().width,column=th.cellIndex,keyName=th.dataset.key,wasDraggable=th.draggable;th.draggable=false;handle.setPointerCapture?.(event.pointerId);const move=moveEvent=>{const width=Math.max(70,Math.round(startWidth+moveEvent.clientX-startX));widths[keyName]=width;th.style.width=width+'px';th.style.minWidth=width+'px';tableWrap.querySelectorAll(`tbody td:nth-child(${column+1})`).forEach(td=>{td.style.width=width+'px';td.style.minWidth=width+'px'});updateSticky()};const finish=()=>{persist();th.draggable=wasDraggable;handle.releasePointerCapture?.(event.pointerId);handle.removeEventListener('pointermove',move);handle.removeEventListener('pointerup',finish);handle.removeEventListener('pointercancel',finish)};handle.addEventListener('pointermove',move);handle.addEventListener('pointerup',finish);handle.addEventListener('pointercancel',finish)},true)}
 function splitActionColumns(){const table=document.querySelector('#tableWrap table');if(!table)return;const head=table.querySelector('thead tr');if(head&&!head.querySelector('.selection-head')){const th=document.createElement('th');th.className='selection-col selection-head';head.insertBefore(th,head.firstElementChild)}table.querySelectorAll('tbody tr').forEach(tr=>{if(tr.querySelector('.selection-col'))return;const td=document.createElement('td');td.className='selection-col';tr.insertBefore(td,tr.firstElementChild)})}
 function repairGridCells(){document.querySelectorAll('#tableWrap tbody tr').forEach(tr=>{const row=rowById.get(String(tr.dataset.id));if(!row)return;order.forEach((key,index)=>{const cell=tr.children[index+2];const column=cols.find(item=>item[0]===key);if(cell&&column)cell.innerHTML=display(column[2],row[key])})})}
 const renderGrid=render;render=function(){renderGrid();splitActionColumns();applyColumnWidths();addCopyActions();repairGridCells();addManagementControls();$('cashLabel').textContent='Cash: '+fmtMoney(filtered.reduce((sum,row)=>sum+num(row.ing_cash||0),0));applyGridAlignment();updateSticky();updateClearButton()};
 function bindTable(){document.querySelectorAll('th[data-key]').forEach(th=>{th.addEventListener('click',e=>{if(e.target.classList.contains('resize'))return;const k=th.dataset.key;sort={key:k,dir:sort.key===k?-sort.dir:1};render()});th.addEventListener('dragstart',e=>{e.dataTransfer.setData('text/plain',th.dataset.key)});th.addEventListener('dragover',e=>{if(th.cellIndex>stickyCount)e.preventDefault()});th.addEventListener('drop',e=>{e.preventDefault();const from=e.dataTransfer.getData('text/plain'),to=th.dataset.key;if(!from||from===to||order.indexOf(to)<stickyCount)return;const a=order.indexOf(from),b=order.indexOf(to);order.splice(a,1);order.splice(b,0,from);persist();render()});const handle=th.querySelector('.resize');handle.addEventListener('mousedown',e=>{e.stopPropagation();const start=e.clientX,startW=th.getBoundingClientRect().width;const move=ev=>{const w=Math.max(70,startW+ev.clientX-start);widths[th.dataset.key]=w;th.style.width=w+'px';document.querySelectorAll(`td:nth-child(${th.cellIndex+1})`).forEach(td=>td.style.width=w+'px');updateSticky()};const up=()=>{persist();document.removeEventListener('mousemove',move);document.removeEventListener('mouseup',up)};document.addEventListener('mousemove',move);document.addEventListener('mouseup',up)})});document.querySelectorAll('tbody tr').forEach(tr=>tr.addEventListener('click',()=>openDetail(Number(tr.dataset.id))));document.querySelectorAll('.bitacora-btn').forEach(button=>button.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();openBitacora(button.dataset.sigest)}))}
 async function loadExactSigest(){const token=++exactSearchToken,exact=exactSigestQuery();if(!exact){exactRow=null;refreshFilters();render();return}try{const request=async()=>{const response=await fetch('?accion=datos&sigest_exact='+encodeURIComponent(exact),{headers:{Accept:'application/json'}});const payload=await response.json();if(!response.ok||!payload.ok)throw new Error(payload.error||'No se pudo consultar el SIGEST exacto.');if(token!==exactSearchToken)return;const candidate=(payload.rows||[]).find(row=>normalizeFilterValue(row.sisvadi)===exact);exactRow=candidate||null;if(candidate)rowById.set(String(candidate.id),candidate);refreshFilters();render()};if(window.NexoLoading)await window.NexoLoading.run(request,{message:'Buscando SIGEST',timeout:60000});else await request()}catch(error){if(token!==exactSearchToken)return;exactRow=null;refreshFilters();render();if(!window.NexoLoading?.isSessionExpired(error))showOperationFeedback(error.message||'No se pudo consultar el SIGEST exacto.')}}
 async function load(){try{await window.NexoLoading.run(async()=>{const response=await fetch('?accion=datos',{headers:{Accept:'application/json'}});const payload=await response.json();if(!response.ok||!payload.ok)throw new Error(payload.error||'No se pudo consultar SIGEST.');allRows=payload.rows;rowById=new Map(allRows.map(row=>[String(row.id),row]));loadPrefs();refreshFilters();render()},{message:'Cargando obras',timeout:90000})}catch(e){if(window.NexoLoading?.isSessionExpired(e))return;$('tableWrap').innerHTML='<div class="status error">No se pudo cargar el universo de obras. <button class="secondary" id="retry">Reintentar</button></div>';$('retry')?.addEventListener('click',load)}}
 function closeDetail(){const back=$('detailBack');back.hidden=true;$('detailBody').innerHTML='';document.body.style.overflow=''}
 async function openDetail(id){const back=$('detailBack');back.hidden=true;$('detailBody').innerHTML='<div class="status">Cargando detalle…</div>';try{await window.NexoLoading.run(async()=>{const exact=exactSigestQuery(),suffix=exact&&String(rowById.get(String(id))?.sisvadi||'')===exact?'&sigest_exact='+encodeURIComponent(exact):'';const response=await fetch(`?accion=detalle&id=${id}${suffix}`,{headers:{Accept:'application/json'}});const payload=await response.json();if(!response.ok||!payload.ok)throw new Error(payload.error||'No se pudo cargar el detalle.');renderDetail(payload.row);back.hidden=false;document.body.style.overflow='hidden'},{message:'Cargando detalle',timeout:60000})}catch(e){back.hidden=true;$('detailBody').innerHTML='';document.body.style.overflow='';if(!window.NexoLoading?.isSessionExpired(e))alert('No se pudo cargar el detalle de la obra. Podés reintentar seleccionándola nuevamente.')}}
 function renderDetail(row){const groups={Identificación:['id','sisvadi','titulo','central','region','distrito','tipo_proyecto','fact_item','pep'],Ingeniería:['ing_estado','ing_lz','ing_lc','ing_l','ing_n','ing_cash','ing_mat_cont','ing_total','ing_moa','ing_us','ing_of'],Construcción:['const_estado','const_f_contrata_envio','const_f_ini','const_f_fin','const_f_fin_adm','const_f_cierre_g3','const_f_dpl','const_f_costo_certif','const_avan_can','const_avan_tendido','const_avan_empalme','const_avan_term_clie','const_avan_total','const_f_avan','const_us'],Contratista:['contrata_of','contrata_us','contrata_f_comp_fin','ctta_sap','unidad_construccion','codigo_unidad_construccion'],Estados:['hmc_estado','parte_estado','parte_fecha_ar','proceso','bandeja_estado'],Responsables:['perm_of','perm_us','ing_us_desp','alta_descri','fact_descri']};const assigned=new Set(Object.values(groups).flat());const other=Object.keys(row).filter(k=>!assigned.has(k));if(other.length)groups['Otros']=other;const labels={sisvadi:'SIGEST',tipo_proyecto:'Tipo de proyecto',const_us:'Usuario TMA',contrata_us:'Usuario Plantel',const_f_ini:'Fecha inicio',const_f_fin:'Fecha fin',const_f_fin_adm:'Fecha fin administrativa',const_f_dpl:'Fecha DPL',const_f_costo_certif:'Fecha costo',contrata_f_comp_fin:'Fecha compromiso',const_f_avan:'Fecha avance',const_avan_total:'Avance'};let html=`<button class="button" id="copySigest" type="button">COPIAR SIGEST</button><span id="copyOk" class="copy-ok" hidden>SIGEST copiado</span>`;for(const [group,keys] of Object.entries(groups)){const items=keys.filter(k=>row[k]!==null&&row[k]!==undefined&&String(row[k]).trim()!=='');if(!items.length)continue;html+=`<section class="detail-group"><h3>${esc(group)}</h3><div class="detail-grid">${items.map(k=>`<div class="detail-item"><small>${esc(labels[k]||k.replaceAll('_',' '))}</small>${esc(String(row[k]))}</div>`).join('')}</div></section>`}$('detailTitle').textContent=(row.sisvadi||'Obra')+' · '+(row.titulo||'Detalle');$('detailBody').innerHTML=html;$('copySigest').addEventListener('click',async()=>{try{await navigator.clipboard.writeText(String(row.sisvadi||''));$('copyOk').hidden=false;setTimeout(()=>$('copyOk').hidden=true,1800)}catch{$('copyOk').textContent='No se pudo copiar';$('copyOk').hidden=false}})}
  function renderResponsibleManagement(row){const box=document.createElement('section');box.className='detail-group';box.innerHTML=`<h3>Responsable de obra</h3><div class="detail-grid"><div class="detail-item"><small>Responsable efectivo</small>${esc(row.responsable_efectivo||'')}</div><div class="detail-item"><small>Responsable SIGEST</small>${esc(row.responsable_sigest||'')}</div><div class="detail-item"><small>Origen</small>${esc(row.responsable_origen==='NEXO'?'Reasignación NEXO':'SIGEST')}</div></div>`;if(window.visorCanManageResponsible){const actions=document.createElement('div');actions.className='bitacora-actions';const button=document.createElement('button');button.type='button';button.className='button';button.textContent=row.responsable_nexo_id?'REASIGNAR RESPONSABLE':'ASIGNAR RESPONSABLE';button.addEventListener('click',()=>openAssign([String(row.sisvadi)]));actions.appendChild(button);if(row.responsable_nexo_id){const restore=document.createElement('button');restore.type='button';restore.className='rn-remove';restore.textContent='RESTABLECER RESPONSABLE SIGEST';restore.addEventListener('click',()=>restoreResponsible([String(row.sisvadi)]));actions.appendChild(restore)}box.appendChild(actions)}$('detailBody').appendChild(box)}
 const baseRenderDetail=renderDetail;renderDetail=function(row){const clean={...row};['responsable_sigest','responsable_nexo','responsable_nexo_id','responsable_efectivo','responsable_origen','responsable_efectivo_display','responsable_nexo_historial'].forEach(key=>delete clean[key]);baseRenderDetail(clean);renderResponsibleManagement(row)};
 let currentAssignIds=[];
 function updateAssignButton(){const button=$('assignResponsibleBtn');if(button){button.disabled=selectedIds.size===0;button.textContent=selectedIds.size?'ASIGNAR RESPONSABLE ('+selectedIds.size+')':'ASIGNAR RESPONSABLE'}}
  function addManagementControls(){if(!window.visorCanManageResponsible)return;const visibleIds=new Set(filtered.map(row=>String(row.sisvadi)));selectedIds=new Set([...selectedIds].filter(id=>visibleIds.has(id)));document.querySelectorAll('#tableWrap tbody tr').forEach(tr=>{const cell=tr.querySelector('.selection-col');if(!cell||cell.querySelector('.rn-select'))return;const row=rowById.get(String(tr.dataset.id)),sigest=String(row?.sisvadi||'');const check=document.createElement('input');check.type='checkbox';check.className='rn-select';check.checked=selectedIds.has(sigest);check.setAttribute('aria-label','Seleccionar obra');check.addEventListener('click',event=>event.stopPropagation());check.addEventListener('change',()=>{if(check.checked)selectedIds.add(sigest);else selectedIds.delete(sigest);updateAssignButton();updateSelectionHeader()});cell.append(check)});const head=document.querySelector('#tableWrap .selection-head');if(head&&!head.querySelector('.rn-select')){const check=document.createElement('input');check.type='checkbox';check.className='rn-select';check.setAttribute('aria-label','Seleccionar todas las obras visibles');check.addEventListener('click',event=>event.stopPropagation());check.addEventListener('change',()=>{document.querySelectorAll('#tableWrap tbody .rn-select').forEach(item=>{item.checked=check.checked;const row=rowById.get(String(item.closest('tr')?.dataset.id)),sigest=String(row?.sisvadi||'');if(sigest){if(check.checked)selectedIds.add(sigest);else selectedIds.delete(sigest)}});updateAssignButton();updateSelectionHeader()});head.append(check)}updateSelectionHeader();updateAssignButton()}
 function updateSelectionHeader(){const head=document.querySelector('#tableWrap .selection-head .rn-select'),checks=[...document.querySelectorAll('#tableWrap tbody .selection-col .rn-select')];if(head){head.checked=checks.length>0&&checks.every(check=>check.checked);head.indeterminate=checks.some(check=>check.checked)&&!head.checked}}
 function openAssign(ids){currentAssignIds=[...new Set(ids.map(String))];$('rnSigest').value=JSON.stringify(currentAssignIds);$('rnCount').textContent=currentAssignIds.length+(currentAssignIds.length===1?' obra seleccionada.':' obras seleccionadas.');$('rnStatus').textContent='';$('rnUser').value='';const first=rowById.get(String(allRows.find(row=>String(row.sisvadi)===currentAssignIds[0])?.id||''));$('rnRestoreWrap').hidden=!(currentAssignIds.length===1&&first&&first.responsable_nexo_id);$('rnModal').hidden=false}
 async function submitResponsible(remove=false){const button=$('rnSave');button.disabled=true;const operation=async()=>{const response=await fetch('?accion=responsable_nexo',{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:new URLSearchParams({csrf:document.querySelector('#rnForm [name="csrf"]').value,sigest:JSON.stringify(currentAssignIds),usuario_nexo_id:$('rnUser').value,motivo:document.querySelector('#rnForm [name="motivo"]').value,operacion:remove?'restablecer':'asignar'})});const payload=await response.json().catch(()=>({}));if(!response.ok||!payload.ok)throw new Error(payload.error||'No se pudo completar la operación.');return payload};try{const payload=window.NexoLoading?.run?await window.NexoLoading.run(operation,{message:remove?'Restableciendo Responsable SIGEST':'Asignando Responsable NEXO',button,timeout:120000}):await operation();for(const update of payload.tareas||[]){const row=allRows.find(item=>String(item.sisvadi)===String(update.sigest));if(row){Object.assign(row,update);row.responsable_sigest=update.responsable_tma;row.responsable_efectivo_display=update.responsable_efectivo}}selectedIds=new Set([...selectedIds].filter(id=>!currentAssignIds.includes(String(id))));$('rnStatus').classList.remove('error');$('rnStatus').textContent=payload.message||'Responsable actualizado correctamente.';showOperationFeedback(payload.message||'Responsable actualizado correctamente.');$('rnModal').hidden=true;refreshFilters();render()}catch(error){const count=currentAssignIds.length;const message=error?.message||'No se pudo completar la operación.';$('rnModal').hidden=false;$('rnStatus').classList.add('error');$('rnStatus').setAttribute('role','alert');$('rnStatus').textContent=count>1?'No se pudo completar la asignación de '+count+' obras. '+message:message}finally{button.disabled=false}}
 function restoreResponsible(ids){currentAssignIds=ids;openAssign(ids);$('rnRestoreWrap').hidden=false;$('rnUser').removeAttribute('required')}
 let currentBitacoraSigest='';
 function bitacoraDate(value){const d=new Date(String(value||'').replace(' ','T'));return Number.isNaN(d.getTime())?String(value||''):d.toLocaleString('es-AR',{dateStyle:'short',timeStyle:'short'})}
 function renderBitacora(payload){const obra=payload.obra||{};$('bitacoraSummary').innerHTML=`<strong>SIGEST</strong><span>${esc(obra.sisvadi||'')}</span><strong>TÍTULO</strong><span>${esc(obra.titulo||'')}</span><strong>CENTRAL</strong><span>${esc(obra.central||'')}</span><strong>ESTADO</strong><span>${esc(obra.bandeja_estado||'')}</span>`;const entries=payload.entries||[];$('bitacoraList').innerHTML=entries.length?entries.map(entry=>`<article class="bitacora-entry"><time>${esc(bitacoraDate(entry.fecha))}</time><div class="entry-user">${esc(entry.usuario)}</div><div class="entry-comment">${esc(entry.comentario)}</div></article>`).join(''):'<div class="bitacora-empty">Todavía no hay comentarios para esta obra.</div>';}
 async function loadBitacora(){const response=await fetch(`?accion=bitacora&sigest=${encodeURIComponent(currentBitacoraSigest)}`,{headers:{Accept:'application/json'}});const payload=await response.json();if(!response.ok||!payload.ok)throw new Error(payload.error||'No se pudo cargar la bitácora.');renderBitacora(payload)}
 async function openBitacora(sigest){if(!sigest)return;currentBitacoraSigest=String(sigest);const back=$('bitacoraBack');back.hidden=true;$('bitacoraList').innerHTML='<div class="status">Cargando bitácora…</div>';$('commentForm').hidden=true;$('commentStatus').textContent='';try{await window.NexoLoading.run(loadBitacora,{message:'Cargando bitácora',timeout:60000});back.hidden=false;document.body.style.overflow='hidden'}catch(error){back.hidden=true;$('bitacoraList').innerHTML='';if(!window.NexoLoading?.isSessionExpired(error))alert('No se pudo cargar la bitácora. Podés reintentar desde la fila de la obra.')}}
 function closeBitacora(){$('bitacoraBack').hidden=true;$('bitacoraList').innerHTML='';$('commentForm').hidden=true;$('commentText').value='';currentBitacoraSigest='';document.body.style.overflow=''}
 function showCommentForm(){$('commentForm').hidden=false;$('commentText').focus()}
 async function saveComment(event){event.preventDefault();const text=$('commentText').value.trim(),button=$('commentSave');if(!text){$('commentStatus').textContent='Ingresá un comentario.';$('commentText').focus();return}button.disabled=true;$('commentStatus').textContent='';try{await window.NexoLoading.run(async()=>{const response=await fetch('?accion=bitacora_guardar',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams({csrf:document.querySelector('meta[name="csrf-token"]').content,sigest:currentBitacoraSigest,comentario:text})});const payload=await response.json();if(!response.ok||!payload.ok)throw new Error(payload.error||'No se pudo guardar el comentario.');$('commentText').value='';$('commentForm').hidden=true;$('commentStatus').textContent='Comentario agregado';await loadBitacora();setTimeout(()=>$('commentStatus').textContent='',2200)},{message:'Guardando comentario',button,timeout:60000})}catch(error){if(!window.NexoLoading?.isSessionExpired(error))$('commentStatus').textContent=error.message||'No se pudo guardar el comentario.'}finally{button.disabled=false}}
  function resetFilters(){selectedRegion=[];selectedDistrito=[];selectedCentral=[];selectedTipo=[];$('quickSearch').value='';['plantelFilter','tmaFilter','stateFilter','advanceFilter','handFilter','executionFilter'].forEach(id=>$(id).value='');refreshFilters();render()}
 function resetView(){localStorage.removeItem(key);order=cols.map(c=>c[0]);widths={};loadPrefs();updateSticky();setObraView(window.visorCanViewAll?'all':'mine')}
 $('quickSearch').addEventListener('input',render);['plantelFilter','tmaFilter','stateFilter','advanceFilter','handFilter','executionFilter'].forEach(id=>$(id).addEventListener('change',()=>{refreshFilters();render()}));$('clearFilters').addEventListener('click',resetFilters);$('resetView').addEventListener('click',()=>{localStorage.removeItem(key);loadPrefs();render()});document.querySelectorAll('.multi>button').forEach(b=>b.addEventListener('click',()=>b.parentElement.classList.toggle('open')));document.addEventListener('click',e=>document.querySelectorAll('.multi.open').forEach(m=>{if(!m.contains(e.target))m.classList.remove('open')}));$('detailClose').addEventListener('click',closeDetail);$('detailBack').addEventListener('click',e=>{if(e.target===$('detailBack'))closeDetail()});$('bitacoraClose').addEventListener('click',closeBitacora);$('bitacoraBack').addEventListener('click',e=>{if(e.target===$('bitacoraBack'))closeBitacora()});$('bitacoraAdd').addEventListener('click',showCommentForm);$('commentCancel').addEventListener('click',()=>{$('commentForm').hidden=true});$('commentForm').addEventListener('submit',saveComment);document.addEventListener('keydown',e=>{if(e.key==='Escape'){if(!$('detailBack').hidden)closeDetail();if(!$('bitacoraBack').hidden)closeBitacora()}});$('exportBtn').addEventListener('click',async()=>{if(!filtered.length)return;try{await window.NexoLoading.download('?accion=exportar',{method:'POST',body:new URLSearchParams({ids:filtered.map(r=>r.id).join(',')}),filename:'visor_sigest_obras.xlsx',message:'Generando Excel',button:$('exportBtn'),timeout:120000})}catch(e){if(!window.NexoLoading?.isSessionExpired(e))alert('No se pudo generar el Excel. Podés reintentar.')}});load();
  $('resetView').replaceWith($('resetView').cloneNode(true));$('resetView').addEventListener('click',resetView);
  installResizeCapture();
  installPointerResize();
  function installSearchDebounce(){const current=$('quickSearch'),search=current.cloneNode(true);search.value=current.value;current.replaceWith(search);let timer=0;search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{loadExactSigest()},180)})}
  $('assignResponsibleBtn')?.addEventListener('click',()=>{if(selectedIds.size)openAssign([...selectedIds])});
  $('rnClose')?.addEventListener('click',()=>{$('rnModal').hidden=true});$('rnCancel')?.addEventListener('click',()=>{$('rnModal').hidden=true});$('rnForm')?.addEventListener('submit',event=>{event.preventDefault();$('rnUser').setAttribute('required','required');submitResponsible(false)});$('rnRestore')?.addEventListener('click',()=>{if(confirm('¿Restablecer el Responsable SIGEST actual? Se conservará el historial NEXO.'))submitResponsible(true)});
  installSearchDebounce();
  $('exportBtn').addEventListener('click',async event=>{const exact=exactSigestQuery();if(!exact)return;event.preventDefault();event.stopImmediatePropagation();if(!filtered.length)return;try{await window.NexoLoading.download('?accion=exportar',{method:'POST',body:new URLSearchParams({ids:filtered.map(r=>r.id).join(','),sigest_exact:exact}),filename:'visor_sigest_obras.xlsx',message:'Generando Excel',button:$('exportBtn'),timeout:120000})}catch(error){if(!window.NexoLoading?.isSessionExpired(error))showOperationFeedback(error.message||'No se pudo generar el Excel. Podés reintentar.')}},true);
  $('myWorksView')?.addEventListener('click',()=>setObraView('mine'));$('allWorksView')?.addEventListener('click',()=>setObraView('all'));updateViewButtons();
  $('rnTitle')?.replaceChildren(document.createTextNode('ASIGNAR RESPONSABLE'));
  const rnUserPlaceholder=$('rnUser')?.querySelector('option[value=""]');if(rnUserPlaceholder)rnUserPlaceholder.textContent='Seleccionar Responsable';
  function updateSituationBadge(){const active=[selectedRegion.length,selectedDistrito.length,selectedCentral.length,selectedTipo.length,$('plantelFilter').value,$('tmaFilter').value,$('stateFilter').value,$('advanceFilter').value,$('handFilter').value,$('executionFilter').value,$('situacionFilter').value].filter(Boolean).length;$('filterBadge').hidden=!active;$('filterBadge').textContent=active?` · Filtros activos: ${active}`:''}
  const clearFiltersWithSituation=updateClearButton;updateClearButton=function(){clearFiltersWithSituation();const active=selectedRegion.length||selectedDistrito.length||selectedCentral.length||selectedTipo.length||$('plantelFilter').value||$('tmaFilter').value||$('stateFilter').value||$('advanceFilter').value||$('handFilter').value||$('executionFilter').value||$('situacionFilter').value||String($('quickSearch').value||'').trim();$('clearFilters').classList.toggle('is-inactive',!active)};
  const renderWithSituation=render;render=function(){renderWithSituation();updateSituationBadge()};
  const resetFiltersWithSituation=resetFilters;resetFilters=function(){resetFiltersWithSituation();$('situacionFilter').value='';refreshFilters();render()};
  $('situacionFilter').addEventListener('change',()=>{refreshFilters();render()});
  const modeStyle=document.createElement('style');modeStyle.textContent='.visor.filters-mode #filters{display:block}.visor.filters-mode #tableWrap{display:none}.visor.grid-mode #filters{display:none}.visor.grid-mode #tableWrap{display:block;flex:1 1 auto;min-height:0;max-height:none}.visor.grid-mode #viewModeToggle{font-weight:700}';document.head.appendChild(modeStyle);
  const viewModeButton=document.createElement('button');viewModeButton.type='button';viewModeButton.id='viewModeToggle';viewModeButton.className='secondary';viewModeButton.setAttribute('aria-pressed','false');$('resetView').insertAdjacentElement('afterend',viewModeButton);
  let gridMode=false;
  function updateViewMode(){document.querySelector('.visor').classList.toggle('grid-mode',gridMode);document.querySelector('.visor').classList.toggle('filters-mode',!gridMode);const filters=$('filters');if(filters)filters.open=!gridMode;viewModeButton.textContent=gridMode?'FILTROS':'GRILLA';viewModeButton.setAttribute('aria-pressed',gridMode?'true':'false');const badge=$('filterBadge');const active=badge&&!badge.hidden?badge.textContent.replace(/^\s*[·•]\s*/,'').trim():'';if(gridMode&&active)viewModeButton.textContent='FILTROS ('+(active.match(/\d+/)?.[0]||'')+')';}
  viewModeButton.addEventListener('click',()=>{gridMode=!gridMode;updateViewMode()});
  const baseRenderForMode=render;render=function(){baseRenderForMode();updateViewMode()};updateViewMode();
 })();
</script>
</body></html>
