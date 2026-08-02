<?php

require_once __DIR__ . '/IdentitySignature.php';

final class IdentityApiClient
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $actorEmail;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) (getenv('IDENTITY_API_BASE_URL') ?: 'https://mis-apps-nine.vercel.app/api/identity'), '/');
        $this->clientId = (string) (getenv('IDENTITY_CLIENT_ID') ?: 'cablera-marplatense');
        $this->clientSecret = (string) (getenv('IDENTITY_CLIENT_SECRET') ?: '');
        $this->actorEmail = (string) (getenv('IDENTITY_ADMIN_ACTOR_EMAIL') ?: 'aguileraclaudiomdq@gmail.com');
    }

    public function health(): array
    {
        return $this->request('GET', '/health');
    }

    public function listInvitations(): array
    {
        return $this->request('GET', '/invitations', true);
    }

    private function request(string $method, string $path, bool $signed = false, array $payload = []): array
    {
        $body = $payload === [] ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Accept: application/json'];

        if ($body !== '') {
            $headers[] = 'Content-Type: application/json';
        }

        if ($signed) {
            if ($this->clientSecret === '') {
                return [
                    'ok' => false,
                    'error' => 'missing_identity_client_secret',
                    'status' => 503,
                ];
            }

            $timestamp = (string) round(microtime(true) * 1000);
            $signaturePayload = IdentitySignature::payload(
                $method,
                '/api/identity' . $path,
                $timestamp,
                $this->clientId,
                $this->actorEmail,
                $body
            );

            $headers[] = 'X-Identity-Client: ' . $this->clientId;
            $headers[] = 'X-Identity-Timestamp: ' . $timestamp;
            $headers[] = 'X-Identity-Actor-Email: ' . $this->actorEmail;
            $headers[] = 'X-Identity-Signature: ' . IdentitySignature::sign($signaturePayload, $this->clientSecret);
        }

        $url = $this->baseUrl . $path;

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            if ($body !== '') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            }

            $raw = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($raw === false) {
                return ['ok' => false, 'error' => $error ?: 'identity_request_failed', 'status' => 0];
            }

            return $this->decode($raw, $status);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $status = 0;

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $status = (int) $matches[1];
        }

        if ($raw === false) {
            return ['ok' => false, 'error' => 'identity_request_failed', 'status' => $status];
        }

        return $this->decode($raw, $status);
    }

    private function decode(string $raw, int $status): array
    {
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'invalid_identity_response', 'status' => $status];
        }

        $decoded['status'] = $status;

        return $decoded;
    }
}
