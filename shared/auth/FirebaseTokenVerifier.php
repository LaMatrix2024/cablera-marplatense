<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpError.php';
require_once __DIR__ . '/../../config/env_loader.php';

final class FirebaseTokenVerifier
{
    private const CERT_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
    private const DEFAULT_CACHE_SECONDS = 3600;

    public function __construct(private ?string $projectId = null)
    {
        $this->projectId = $projectId ?: lcm_config_value('FIREBASE_PROJECT_ID');
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

        $kid = (string)$header['kid'];
        $certificates = $this->certificates($kid);
        if (!isset($certificates[$kid])) {
            $this->logCertificateEvent('kid_not_found_after_refresh', $kid, null, null);
            throw new HttpError(401, 'No pudimos validar tu sesión en este momento. Intentá nuevamente.', 'firebase_cert_not_found');
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

    private function certificates(?string $requiredKid = null): array
    {
        $cache = $this->cachedCertificates();
        if ($cache !== null && ($requiredKid === null || isset($cache[$requiredKid]))) {
            return $cache;
        }

        $staleCache = $this->cachedCertificates(true);
        [$decoded, $headers, $status, $error] = $this->downloadCertificates();
        if ($decoded === null) {
            if ($staleCache !== null && ($requiredKid === null || isset($staleCache[$requiredKid]))) {
                $this->logCertificateEvent('stale_cache_used', $requiredKid, $status, $error);
                return $staleCache;
            }

            $this->logCertificateEvent('download_failed', $requiredKid, $status, $error);
            throw new HttpError(401, 'No pudimos validar tu sesión en este momento. Intentá nuevamente.', 'firebase_certs_unavailable');
        }

        $this->storeCertificates($decoded, $headers);
        $this->logCertificateEvent('certificates_refreshed', $requiredKid, $status, null);

        return $decoded;
    }

    private function downloadCertificates(): array
    {
        $curl = curl_init(self::CERT_URL);
        if ($curl === false) return [null, [], 0, 'curl_init_failed'];
        $ca = (string)(ini_get('curl.cainfo') ?: ini_get('openssl.cafile'));
        if ($ca !== '' && is_file($ca)) curl_setopt($curl, CURLOPT_CAINFO, $ca);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $error = curl_error($curl) ?: null;
        $headers = [];
        $contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        curl_close($curl);
        $raw = is_string($response) ? substr($response, $headerSize) : false;
        if (is_string($response) && $headerSize > 0) {
            $headers = preg_split("/\r\n|\n|\r/", substr($response, 0, $headerSize), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        $decoded = is_string($raw) && $status >= 200 && $status < 300 ? json_decode($raw, true) : null;
        if ($decoded === null && $error !== null && stripos($error, 'certificate') !== false && PHP_OS_FAMILY === 'Windows') {
            [$fallbackRaw, $fallbackHeaders, $fallbackStatus, $fallbackError] = $this->downloadWithWindowsCurl();
            if ($fallbackRaw !== null) {
                $raw = $fallbackRaw;
                $headers = $fallbackHeaders;
                $status = $fallbackStatus;
                $error = null;
                $decoded = $status >= 200 && $status < 300 ? json_decode($raw, true) : null;
                $this->logCertificateEvent('windows_trusted_curl_fallback', null, $status, null);
            } else {
                $error = $fallbackError ?: $error;
            }
        }
        if (!is_array($decoded) || $decoded === []) $decoded = null;
        if ($decoded !== null) {
            foreach ($decoded as $kid => $certificate) {
                if (!is_string($kid) || !is_string($certificate) || !str_contains($certificate, 'BEGIN CERTIFICATE')) {
                    $decoded = null;
                    break;
                }
            }
        }
        $headers[] = 'content-type: ' . $contentType;
        return [$decoded, $headers, $status, $error];
    }

    private function downloadWithWindowsCurl(): array
    {
        $curlPath = getenv('SystemRoot') . '\\System32\\curl.exe';
        if (!is_file($curlPath)) return [null, [], 0, 'trusted_curl_not_found'];
        $dir = sys_get_temp_dir();
        $bodyPath = tempnam($dir, 'firebase-cert-body-');
        $headerPath = tempnam($dir, 'firebase-cert-header-');
        if ($bodyPath === false || $headerPath === false) return [null, [], 0, 'temp_file_failed'];
        $command = [$curlPath, '--fail', '--silent', '--show-error', '--location', '--max-time', '10', '--proto', '=https', '--tlsv1.2', '--dump-header', $headerPath, '--output', $bodyPath, self::CERT_URL];
        $pipes = [];
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { @unlink($bodyPath); @unlink($headerPath); return [null, [], 0, 'trusted_curl_start_failed']; }
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) fclose($pipe);
        $exitCode = proc_close($process);
        $raw = $exitCode === 0 ? @file_get_contents($bodyPath) : false;
        $headers = @file($headerPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        @unlink($bodyPath); @unlink($headerPath);
        $status = 0;
        foreach (array_reverse($headers) as $line) if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $m)) { $status = (int)$m[1]; break; }
        return [is_string($raw) ? $raw : null, $headers, $status, $exitCode === 0 ? null : trim((string)$stderr)];
    }

    private function cachedCertificates(bool $allowExpired = false): ?array
    {
        $path = $this->cachePath();
        if (!is_file($path)) {
            return null;
        }

        $payload = json_decode((string)file_get_contents($path), true);
        if (!is_array($payload)) {
            return null;
        }

        if (!$allowExpired && (int)($payload['expires_at'] ?? 0) <= time()) {
            return null;
        }

        return is_array($payload['certificates'] ?? null) ? $payload['certificates'] : null;
    }

    private function storeCertificates(array $certificates, array $headers): void
    {
        $maxAge = self::DEFAULT_CACHE_SECONDS;
        foreach ($headers as $header) {
            if (preg_match('/cache-control:.*max-age=(\d+)/i', $header, $matches)) {
                $maxAge = max(60, (int)$matches[1]);
                break;
            }
        }

        $dir = dirname($this->cachePath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = json_encode([
            'expires_at' => time() + $maxAge,
            'certificates' => $certificates,
        ], JSON_UNESCAPED_SLASHES);
        $temporary = $this->cachePath() . '.tmp';
        if (is_string($payload) && @file_put_contents($temporary, $payload, LOCK_EX) !== false) {
            @rename($temporary, $this->cachePath());
        }
    }

    private function logCertificateEvent(string $event, ?string $kid, ?int $status, ?string $error): void
    {
        $dir = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $line = json_encode([
            'time' => date(DATE_ATOM),
            'event' => $event,
            'kid' => $kid,
            'http_status' => $status,
            'error' => $error,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
        @file_put_contents($dir . '/firebase-token.log', $line, FILE_APPEND | LOCK_EX);
    }

    private function cachePath(): string
    {
        return dirname(__DIR__, 2) . '/tmp/firebase_certificates.json';
    }

    private function base64UrlDecode(string $value): string
    {
        $base64 = strtr($value, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new HttpError(401, 'Token Firebase invalido.', 'invalid_base64url');
        }

        return $decoded;
    }
}
