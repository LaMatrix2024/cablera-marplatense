<?php

declare(strict_types=1);

$url = $argv[1] ?? 'https://lacablera.com/api/v1/auth/me';
$token = $argv[2] ?? '';

if ($token === '') {
    fwrite(STDERR, "Uso: php tools/call_auth_me_with_token.php <url> <id_token>\n");
    exit(2);
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
        'timeout' => 20,
        'ignore_errors' => true,
    ],
]);

$raw = @file_get_contents($url, false, $context);
$status = 0;
if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
    $status = (int)$matches[1];
}

echo json_encode([
    'status' => $status,
    'body' => is_string($raw) ? json_decode($raw, true) : null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

