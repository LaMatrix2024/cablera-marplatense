<?php
require_once __DIR__ . '/bootstrap.php';
lcm_central_run(function (): void {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) throw new HttpError(405, 'Método no permitido.', 'METHOD_NOT_ALLOWED');
    $token = lcm_central_bearer();
    $result = (new CentralHostingerTokenService(lcm_central_database()))->session($token);
    lcm_central_json(['ok' => true, 'data' => $result]);
});
