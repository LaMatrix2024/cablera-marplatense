<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env_loader.php';

lcm_load_dotenv_to_process();

$email = strtolower(trim((string)($argv[1] ?? lcm_config_value('FIREBASE_TEST_EMAIL', ''))));
$password = (string)($argv[2] ?? lcm_config_value('FIREBASE_TEST_PASSWORD', ''));
$apiKey = (string)lcm_config_value('FIREBASE_WEB_API_KEY', '');

if ($apiKey === '' || $email === '' || $password === '') {
    fwrite(STDERR, "Uso: php tools/firebase_email_password_token.php <email> <password>\n");
    fwrite(STDERR, "Requiere FIREBASE_WEB_API_KEY en plantel.env. No imprime password ni refresh token.\n");
    exit(2);
}

$payload = json_encode([
    'email' => $email,
    'password' => $password,
    'returnSecureToken' => true,
], JSON_UNESCAPED_SLASHES);

$url = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . rawurlencode($apiKey);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 12,
        'ignore_errors' => true,
    ],
]);

$raw = @file_get_contents($url, false, $context);
$status = 0;
if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
    $status = (int)$matches[1];
}

$decoded = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($decoded) || $status < 200 || $status >= 300) {
    echo json_encode([
        'ok' => false,
        'status' => $status,
        'error' => $decoded['error']['message'] ?? 'firebase_login_failed',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

echo json_encode([
    'ok' => true,
    'email' => strtolower((string)($decoded['email'] ?? $email)),
    'local_id_hash' => substr(hash('sha256', (string)($decoded['localId'] ?? '')), 0, 16),
    'id_token' => $decoded['idToken'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

