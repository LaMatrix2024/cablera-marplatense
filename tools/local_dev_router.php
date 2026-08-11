<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = dirname(__DIR__);

$routes = [
    '/api/v1/auth/me' => '/api/v1/auth/me.php',
    '/api/v1/auth/invitation' => '/api/v1/auth/invitation.php',
    '/api/v1/auth/invitation/accept' => '/api/v1/auth/invitation/accept.php',
    '/api/v1/admin/invitations' => '/api/v1/admin/invitations/index.php',
];

if (isset($routes[$path])) {
    require $root . $routes[$path];
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

