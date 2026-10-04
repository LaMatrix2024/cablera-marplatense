<?php
require_once __DIR__ . '/bootstrap.php';
lcm_central_run(function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new HttpError(405, 'Método no permitido.', 'METHOD_NOT_ALLOWED');
    $token = '';
    try { $token = lcm_central_bearer(); } catch (HttpError) { }
    if ($token !== '') (new CentralHostingerTokenService(lcm_central_database()))->logout($token);
    lcm_central_json(['ok' => true, 'data' => ['logged_out' => true]]);
});
