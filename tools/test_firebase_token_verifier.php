<?php

declare(strict_types=1);

require_once __DIR__ . '/../shared/auth/FirebaseTokenVerifier.php';

function verifier_expect_error(callable $callback, string $code): void
{
    try {
        $callback();
    } catch (HttpError $error) {
        if ($error->errorCode() !== $code || $error->status() !== 401) {
            throw new RuntimeException('Error esperado ' . $code . ', recibido ' . $error->errorCode());
        }
        return;
    }

    throw new RuntimeException('Se esperaba error ' . $code);
}

$verifier = new FirebaseTokenVerifier('mis-appspwa-claudio');

verifier_expect_error(fn () => $verifier->verifyBearer(null), 'missing_bearer_token');
verifier_expect_error(fn () => $verifier->verifyIdToken('token-invalido'), 'invalid_firebase_token');

$header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'kid' => 'fake'])), '+/', '-_'), '=');
$payload = rtrim(strtr(base64_encode(json_encode([
    'aud' => 'otro-proyecto',
    'iss' => 'https://securetoken.google.com/otro-proyecto',
    'exp' => time() + 3600,
    'iat' => time(),
    'sub' => 'uid',
    'email' => 'test@example.com',
])), '+/', '-_'), '=');
$signature = rtrim(strtr(base64_encode('firma'), '+/', '-_'), '=');
verifier_expect_error(fn () => $verifier->verifyIdToken($header . '.' . $payload . '.' . $signature), 'firebase_cert_not_found');

echo "test_firebase_token_verifier OK" . PHP_EOL;

