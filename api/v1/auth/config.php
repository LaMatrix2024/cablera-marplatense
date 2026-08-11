<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/env_loader.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'method_not_allowed',
        'message' => 'Metodo no permitido.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$firebase = [
    'apiKey' => lcm_config_value('FIREBASE_WEB_API_KEY', ''),
    'authDomain' => lcm_config_value('FIREBASE_AUTH_DOMAIN', 'lacablera.com'),
    'projectId' => lcm_config_value('FIREBASE_PROJECT_ID', ''),
    'storageBucket' => lcm_config_value('FIREBASE_STORAGE_BUCKET', ''),
    'messagingSenderId' => lcm_config_value('FIREBASE_MESSAGING_SENDER_ID', ''),
    'appId' => lcm_config_value('FIREBASE_APP_ID', ''),
    'measurementId' => lcm_config_value('FIREBASE_MEASUREMENT_ID', ''),
];

$missing = [];
foreach (['apiKey', 'authDomain', 'projectId', 'appId'] as $key) {
    if (trim((string)$firebase[$key]) === '') {
        $missing[] = $key;
    }
}

if ($missing !== []) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'firebase_web_config_missing',
        'message' => 'Falta configuracion web de Firebase.',
        'missing' => $missing,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'ok' => true,
    'firebase' => $firebase,
    'apiBaseUrl' => '/api/v1',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
