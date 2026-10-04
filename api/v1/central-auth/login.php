<?php
require_once __DIR__ . '/bootstrap.php';
lcm_central_run(function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new HttpError(405, 'Método no permitido.', 'METHOD_NOT_ALLOWED');
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new HttpError(400, 'JSON inválido.', 'INVALID_JSON');
    lcm_central_json(['ok' => true, 'data' => (new CentralHostingerTokenService(lcm_central_database()))->login((string)($input['email'] ?? ''), (string)($input['password'] ?? ''))]);
});
