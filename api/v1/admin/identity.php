<?php

declare(strict_types=1);

require_once __DIR__ . '/../_common.php';

api_handle(function () use ($pdo_lacablera): void {
    $resource = (string)($_GET['resource'] ?? '');
    $action = (string)($_GET['action'] ?? '');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? '');
    $input = in_array($method, ['POST', 'PUT', 'PATCH'], true) ? api_input() : [];

    $readOnly = $method === 'GET';
    $service = api_admin_service($pdo_lacablera, $readOnly ? 'puede_ver' : 'puede_editar');

    if ($resource === 'dashboard' && $method === 'GET') {
        api_json(['ok' => true] + $service->dashboard());
    }
    if ($resource === 'catalogs' && $method === 'GET') {
        api_json(['ok' => true] + $service->catalogs());
    }
    if ($resource === 'audit' && $method === 'GET') {
        api_json(['ok' => true] + $service->auditRows());
    }

    if ($resource === 'users') {
        if ($method === 'GET' && $id === null) {
            api_json(['ok' => true] + $service->listUsers($_GET));
        }
        if ($method === 'GET' && $id !== null) {
            api_json(['ok' => true] + $service->userDetail($id));
        }
        if ($method === 'PATCH' && $id !== null) {
            api_json(['ok' => true] + $service->updateUser($id, $input));
        }
        if ($method === 'PUT' && $id !== null && $action === 'applications') {
            api_json(['ok' => true] + $service->setUserApplications($id, $input));
        }
        if ($method === 'PUT' && $id !== null && $action === 'roles') {
            api_json(['ok' => true] + $service->setUserRoles($id, $input));
        }
        if ($method === 'PUT' && $id !== null && $action === 'modules') {
            api_json(['ok' => true] + $service->setUserModules($id, $input));
        }
    }

    if ($resource === 'applications') {
        if ($method === 'GET') {
            api_json(['ok' => true] + $service->listApplications());
        }
        if ($method === 'POST') {
            api_json(['ok' => true] + $service->createApplication($input), 201);
        }
        if ($method === 'PATCH' && $id !== null) {
            api_json(['ok' => true] + $service->updateApplication($id, $input));
        }
    }

    if ($resource === 'modules') {
        if ($method === 'GET') {
            api_json(['ok' => true] + $service->listModules($_GET));
        }
        if ($method === 'POST') {
            api_json(['ok' => true] + $service->createModule($input), 201);
        }
        if ($method === 'PATCH' && $id !== null) {
            api_json(['ok' => true] + $service->updateModule($id, $input));
        }
    }

    if ($resource === 'roles') {
        if ($method === 'GET') {
            api_json(['ok' => true] + $service->listRoles($_GET));
        }
        if ($method === 'POST') {
            api_json(['ok' => true] + $service->createRole($input), 201);
        }
        if ($method === 'PATCH' && $id !== null) {
            api_json(['ok' => true] + $service->updateRole($id, $input));
        }
        if ($method === 'PUT' && $id !== null && $action === 'modules') {
            api_json(['ok' => true] + $service->setRoleModules($id, $input));
        }
    }

    throw new HttpError(404, 'Endpoint administrativo inexistente.', 'admin_endpoint_not_found');
});
