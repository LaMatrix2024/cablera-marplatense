<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/env_loader.php';
lcm_load_dotenv_to_process();

const TEXT_PREVIEW_BYTES = 262144;
const BLOCKED_NAMES = ['.env', 'env.php', 'plantel.env', 'errores.log'];
const BLOCKED_PATTERNS = [
    '/(^|[\\\\\/])\.git([\\\\\/]|$)/i',
    '/(^|[\\\\\/])vendor([\\\\\/]|$)/i',
    '/(^|[\\\\\/])node_modules([\\\\\/]|$)/i',
    '/(^|[\\\\\/])logs([\\\\\/]|$)/i',
    '/(^|[\\\\\/])tmp([\\\\\/]|$)/i',
];

function files_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function files_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return is_string($value) && trim($value) !== '' ? trim($value) : $default;
}

function files_authorization_header(): string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }

    return $header;
}

function files_require_token(): void
{
    $expected = files_env('ADMINOBRAS_FILES_TOKEN');
    if ($expected === '') {
        files_json(['ok' => false, 'error' => 'Explorador de archivos no configurado.'], 503);
    }

    $prefix = 'Bearer ';
    $header = files_authorization_header();
    $received = str_starts_with($header, $prefix) ? substr($header, strlen($prefix)) : '';
    if ($received === '' || !hash_equals($expected, $received)) {
        files_json(['ok' => false, 'error' => 'No autorizado.'], 401);
    }
}

function files_root(): string
{
    $configured = files_env('ADMINOBRAS_FILES_ROOT');
    $root = $configured !== '' ? realpath($configured) : realpath(dirname(__DIR__, 2));
    if ($root === false || !is_dir($root)) {
        throw new RuntimeException('Raiz de archivos no disponible.');
    }

    return $root;
}

function files_within_root(string $path, string $root): bool
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    $root = rtrim(str_replace('\\', '/', $root), '/');
    return $path === $root || str_starts_with($path . '/', $root . '/');
}

function files_blocked(string $path): bool
{
    if (in_array(strtolower(basename($path)), BLOCKED_NAMES, true)) {
        return true;
    }
    foreach (BLOCKED_PATTERNS as $pattern) {
        if (preg_match($pattern, $path) === 1) {
            return true;
        }
    }

    return false;
}

function files_selected(string $root, string $relative): string
{
    $relative = trim(str_replace(['..', "\0"], '', $relative), " \t\n\r\0\x0B\\/");
    $candidate = $relative === '' ? $root : $root . DIRECTORY_SEPARATOR . $relative;
    $real = realpath($candidate);
    if ($real === false || !files_within_root($real, $root) || files_blocked($real)) {
        return $root;
    }

    return $real;
}

function files_relative(string $path, string $root): string
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    $root = rtrim(str_replace('\\', '/', $root), '/');
    return trim(substr($path, strlen($root)), '/');
}

function files_can_preview(string $path): bool
{
    if (!is_file($path) || filesize($path) > TEXT_PREVIEW_BYTES) {
        return false;
    }
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, ['php', 'html', 'css', 'js', 'json', 'md', 'txt', 'xml', 'csv', 'ini', 'htaccess'], true);
}

function files_items(string $path, string $root): array
{
    $items = [];
    foreach (new DirectoryIterator($path) as $item) {
        if ($item->isDot() || files_blocked($item->getPathname())) {
            continue;
        }
        $items[] = [
            'name' => $item->getFilename(),
            'relative' => files_relative($item->getPathname(), $root),
            'type' => $item->isDir() ? 'dir' : 'file',
            'size' => $item->isFile() ? $item->getSize() : null,
            'modified' => $item->getMTime(),
        ];
    }
    usort($items, static fn (array $a, array $b): int => strcmp($a['type'], $b['type']) ?: strnatcasecmp($a['name'], $b['name']));

    return $items;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        files_json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
    }

    files_require_token();
    $root = files_root();
    $path = files_selected($root, (string) ($_GET['path'] ?? ''));
    $isFile = is_file($path);

    $payload = [
        'ok' => true,
        'root_label' => files_env('ADMINOBRAS_FILES_LABEL', 'Hostinger'),
        'relative' => files_relative($path, $root),
        'name' => basename($path) ?: files_env('ADMINOBRAS_FILES_LABEL', 'Hostinger'),
        'type' => $isFile ? 'file' : 'dir',
        'size' => $isFile ? filesize($path) : null,
        'modified' => filemtime($path) ?: time(),
        'items' => $isFile ? [] : files_items($path, $root),
        'preview' => null,
        'preview_error' => '',
    ];

    if ($isFile) {
        if (files_can_preview($path)) {
            $payload['preview'] = (string) file_get_contents($path);
        } else {
            $payload['preview_error'] = 'Vista previa no disponible para este tipo o tamano de archivo.';
        }
    }

    files_json($payload);
} catch (Throwable) {
    files_json(['ok' => false, 'error' => 'Error al leer archivos desplegados.'], 500);
}
