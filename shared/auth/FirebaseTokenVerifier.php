<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpError.php';

final class FirebaseTokenVerifier
{
    private const CERT_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function __construct(private ?string $projectId = null)
    {
        $this->projectId = $projectId ?: getenv('FIREBASE_PROJECT_ID') ?: null;
    }

    public function verifyBearer(?string $authorizationHeader): array
    {
        if ($authorizationHeader === null || !preg_match('/^Bearer\s+(.+)$/i', trim($authorizationHeader), $matches)) {
            throw new HttpError(401, 'Token Firebase ausente.', 'missing_bearer_token');
        }

        return $this->verifyIdToken($matches[1]);
    }

    public function verifyIdToken(string $token): array
    {
        if ($this->projectId === null || $this->projectId === '') {
            throw new HttpError(401, 'Firebase no esta configurado.', 'firebase_not_configured');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new HttpError(401, 'Token Firebase invalido.', 'invalid_firebase_token');
        }

        $header = $this->jsonPart($parts[0]);
        $payload = $this->jsonPart($parts[1]);

        if (($header['alg'] ?? null) !== 'RS256' || empty($header['kid'])) {
            throw new HttpError(401, 'Token Firebase invalido.', 'invalid_firebase_header');
        }

        $certificates = $this->certificates();
        $kid = (string)$header['kid'];
        if (!isset($certificates[$kid])) {
            throw new HttpError(401, 'Certificado Firebase no encontrado.', 'firebase_cert_not_found');
        }

        $signed = $parts[0] . '.' . $parts[1];
        $signature = $this->base64UrlDecode($parts[2]);
        $ok = openssl_verify($signed, $signature, $certificates[$kid], OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new HttpError(401, 'Firma Firebase invalida.', 'invalid_firebase_signature');
        }

        $now = time();
        if (($payload['aud'] ?? null) !== $this->projectId) {
            throw new HttpError(401, 'Proyecto Firebase invalido.', 'invalid_firebase_project');
        }
        if (($payload['iss'] ?? null) !== 'https://securetoken.google.com/' . $this->projectId) {
            throw new HttpError(401, 'Issuer Firebase invalido.', 'invalid_firebase_issuer');
        }
        if ((int)($payload['exp'] ?? 0) <= $now || (int)($payload['iat'] ?? 0) > $now + 60) {
            throw new HttpError(401, 'Token Firebase expirado.', 'expired_firebase_token');
        }

        $uid = (string)($payload['sub'] ?? '');
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        if ($uid === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpError(401, 'Token Firebase sin identidad valida.', 'invalid_firebase_identity');
        }

        return [
            'uid' => $uid,
            'email' => $email,
        ];
    }

    private function jsonPart(string $part): array
    {
        $decoded = json_decode($this->base64UrlDecode($part), true);
        if (!is_array($decoded)) {
            throw new HttpError(401, 'Token Firebase invalido.', 'invalid_firebase_token');
        }

        return $decoded;
    }

    private function certificates(): array
    {
        $raw = @file_get_contents(self::CERT_URL);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw new HttpError(401, 'No se pudieron obtener certificados Firebase.', 'firebase_certs_unavailable');
        }

        return $decoded;
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new HttpError(401, 'Token Firebase invalido.', 'invalid_base64url');
        }

        return $decoded;
    }
}

