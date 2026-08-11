<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = dirname(__DIR__);

$routes = [
    '/api/v1/auth/me' => '/api/v1/auth/me.php',
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

