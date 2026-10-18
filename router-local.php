<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = '/' . ltrim((string)$path, '/');

$root = __DIR__;
$file = realpath($root . $path);

if ($file !== false && is_file($file)) {
    return false;
}

$routes = [
    '#^/api/v1/admin/(dashboard|catalogs|audit|users|applications|modules|roles)/?$#'
        => 'api/v1/admin/identity.php',
    '#^/api/v1/admin/users/([0-9]+)/(applications|roles|modules)/?$#'
        => 'api/v1/admin/identity.php',
    '#^/api/v1/admin/roles/([0-9]+)/modules/?$#'
        => 'api/v1/admin/identity.php',
    '#^/api/v1/admin/(users|applications|modules|roles)/([0-9]+)/?$#'
        => 'api/v1/admin/identity.php',
    '#^/api/v1/admin/invitations/?$#'
        => 'api/v1/admin/invitations/index.php',
    '#^/api/v1/admin/invitations/([0-9]+)/?$#'
        => 'api/v1/admin/invitations/detail.php',
    '#^/api/v1/admin/invitations/([0-9]+)/revoke/?$#'
        => 'api/v1/admin/invitations/revoke.php',
];

foreach ($routes as $pattern => $target) {
    if (!preg_match($pattern, $path, $matches)) {
        continue;
    }

    if (str_contains($target, 'identity.php')) {
        if (in_array($matches[1], ['dashboard', 'catalogs', 'audit'], true)) {
            $_GET['resource'] = $matches[1];
        } elseif (is_numeric($matches[1])) {
            $_GET['resource'] = 'users';
            $_GET['id'] = $matches[1];
            $_GET['action'] = $matches[2];
        } else {
            $_GET['resource'] = $matches[1];
            if (isset($matches[2])) {
                $_GET['id'] = $matches[2];
            }
        }

        if (str_contains($path, '/roles/') && str_ends_with($path, '/modules')) {
            $_GET['resource'] = 'roles';
            $_GET['action'] = 'modules';
        }
    } elseif (isset($matches[1]) && is_numeric($matches[1])) {
        $_GET['id'] = $matches[1];
    }

    require $root . '/' . $target;
    exit;
}

http_response_code(404);
echo 'Ruta no encontrada';
