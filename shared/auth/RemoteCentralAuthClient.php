<?php
declare(strict_types=1);

require_once __DIR__ . '/HttpError.php';

final class RemoteCentralAuthClient
{
    private const DEFAULT_BASE = 'https://lacablera.com/api/v1/central-auth';
    private const CONNECT_TIMEOUT = 3;
    private const TOTAL_TIMEOUT = 8;

    public function login(string $email, string $password): array { return $this->request('/login', 'POST', ['email' => strtolower(trim($email)), 'password' => $password]); }
    public function session(string $token): array { return $this->request('/session', 'GET', null, $token); }
    public function logout(string $token): void { try { $this->request('/logout', 'POST', null, $token); } catch (Throwable) {} }

    private function request(string $path, string $method, ?array $payload, ?string $token = null): array
    {
        $base = rtrim((string)(getenv('LCM_CENTRAL_AUTH_URL') ?: self::DEFAULT_BASE), '/');
        if (!str_starts_with(strtolower($base), 'https://')) throw new RuntimeException('La API central debe utilizar HTTPS.');
        $curl = curl_init($base . $path);
        if ($curl === false) throw new RuntimeException('No se pudo iniciar la conexión con la API central.');
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
        $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT, CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
        $caBundle = dirname(__DIR__, 2) . '/config/cacert.pem';
        if (is_readable($caBundle)) $options[CURLOPT_CAINFO] = $caBundle;
        if ($payload !== null) $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        curl_setopt_array($curl, $options);
        $raw = curl_exec($curl); $errno = curl_errno($curl); $error = curl_error($curl); $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
        if ($raw === false || $errno !== 0) throw new RuntimeException('La API central no está disponible temporalmente.');
        $body = json_decode((string)$raw, true);
        if (!is_array($body)) throw new RuntimeException('La API central devolvió una respuesta inválida.');
        if ($status < 200 || $status >= 300 || ($body['ok'] ?? false) !== true) {
            $errorBody = is_array($body['error'] ?? null) ? $body['error'] : [];
            $code = (string)($errorBody['code'] ?? 'central_api_error');
            $message = (string)($errorBody['message'] ?? 'No se pudo validar la sesión central.');
            throw new HttpError($status > 0 ? $status : 503, $message, $code);
        }
        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }
}
