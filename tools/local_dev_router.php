<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = dirname(__DIR__);

// El servidor integrado no procesa las protecciones de Apache.
$path = rawurldecode($path);
if (str_contains($path, '\\') || str_contains($path, "\0")
    || preg_match('#(?:^|/)\.{1,2}(?:/|$)|(?:^|/)\.[^/]+#', $path)
    || preg_match('#^/(?:config|logs|tmp|storage|scripts|tools|database|docs|tests|shared)(?:/|$)#i', $path)
    || preg_match('#\.(?:env|log|sql|ps1|bat|md|bak|ini|txt|pid)$#i', $path)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Recurso no disponible.']], JSON_UNESCAPED_UNICODE);
    return true;
}

// Las apps nuevas viven en /apps; las PWA existentes conservan su ubicación.
if (str_starts_with($path, '/apps/') && !is_file($root . $path) && !is_dir($root . $path)) {
    $legacy = realpath($root . '/public' . $path);
    $base = realpath($root . '/public/apps');
    if ($legacy !== false && $base !== false && str_starts_with($legacy, $base . DIRECTORY_SEPARATOR)) {
        if (is_dir($legacy)) {
            foreach (['index.php', 'index.html'] as $index) {
                if (is_file($legacy . '/' . $index)) { $legacy .= '/' . $index; break; }
            }
        }
        if (is_file($legacy)) {
            $extension = strtolower(pathinfo($legacy, PATHINFO_EXTENSION));
            if ($extension === 'php') { require $legacy; return true; }
            $types = ['html' => 'text/html; charset=UTF-8', 'css' => 'text/css; charset=UTF-8',
                'js' => 'application/javascript; charset=UTF-8', 'json' => 'application/json; charset=UTF-8',
                'png' => 'image/png', 'ico' => 'image/x-icon', 'svg' => 'image/svg+xml'];
            header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
            readfile($legacy);
            return true;
        }
    }
}
$path = rtrim($path, '/') ?: '/';

$routes = [
    '/api/v1/health' => '/api/v1/health/index.php',
    '/api/v1/health/' => '/api/v1/health/index.php',
    '/api/v1/auth/login' => '/api/v1/auth/login.php',
    '/api/v1/auth/logout' => '/api/v1/auth/logout.php',
    '/api/v1/auth/me' => '/api/v1/auth/me.php',
    '/api/v1/auth/session' => '/api/v1/auth/session.php',
    '/api/v1/auth/config' => '/api/v1/auth/config.php',
    '/api/v1/auth/invitation' => '/api/v1/auth/invitation.php',
    '/api/v1/auth/invitation/accept' => '/api/v1/auth/invitation/accept.php',
    '/api/v1/admin/config' => '/api/v1/admin/config.php',
    '/api/v1/admin/invitations' => '/api/v1/admin/invitations/index.php',
];

$identityRoutes = [
    '/api/v1/admin/dashboard' => ['resource' => 'dashboard'],
    '/api/v1/admin/catalogs' => ['resource' => 'catalogs'],
    '/api/v1/admin/audit' => ['resource' => 'audit'],
    '/api/v1/admin/users' => ['resource' => 'users'],
    '/api/v1/admin/applications' => ['resource' => 'applications'],
    '/api/v1/admin/modules' => ['resource' => 'modules'],
    '/api/v1/admin/roles' => ['resource' => 'roles'],
];

if (isset($routes[$path])) {
    require $root . $routes[$path];
    return true;
}

if (isset($identityRoutes[$path])) {
    $_GET = array_merge($_GET, $identityRoutes[$path]);
    require $root . '/api/v1/admin/identity.php';
    return true;
}

if (preg_match('#^/api/v1/admin/(users|applications|modules|roles)/([0-9]+)$#', $path, $matches)) {
    $_GET['resource'] = $matches[1];
    $_GET['id'] = $matches[2];
    require $root . '/api/v1/admin/identity.php';
    return true;
}

if (preg_match('#^/api/v1/admin/users/([0-9]+)/(applications|roles|modules)$#', $path, $matches)) {
    $_GET['resource'] = 'users';
    $_GET['id'] = $matches[1];
    $_GET['action'] = $matches[2];
    require $root . '/api/v1/admin/identity.php';
    return true;
}

if (preg_match('#^/api/v1/admin/roles/([0-9]+)/modules$#', $path, $matches)) {
    $_GET['resource'] = 'roles';
    $_GET['id'] = $matches[1];
    $_GET['action'] = 'modules';
    require $root . '/api/v1/admin/identity.php';
    return true;
}

if (preg_match('#^/api/v1/admin/invitations/([0-9]+)$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require $root . '/api/v1/admin/invitations/detail.php';
    return true;
}

if (preg_match('#^/api/v1/admin/invitations/([0-9]+)/revoke$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require $root . '/api/v1/admin/invitations/revoke.php';
    return true;
}

$file = realpath($root . $path);
if ($file !== false && str_starts_with($file, $root) && is_file($file)) {
    return false;
}

return false;

